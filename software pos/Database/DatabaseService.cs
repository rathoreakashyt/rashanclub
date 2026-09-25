using Microsoft.Data.Sqlite;
using RashanKiDukan.Services;
using System;
using System.Collections.Generic;
using System.IO;
using System.Text.Json;

namespace RashanKiDukan.Database
{
    public class DatabaseService
    {
        private readonly string _connectionString;
        private readonly string _dbPath;

        // Connection pool - reuse connections for better performance
        private static readonly object _poolLock = new();
        private static readonly Queue<SqliteConnection> _pool = new();
        private static string? _poolConnectionString;
        private const int MaxPoolSize = 5;

        public DatabaseService()
        {
            _dbPath = Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                "RashanKiDukan", "data.db");

            Directory.CreateDirectory(Path.GetDirectoryName(_dbPath)!);
            _connectionString = $"Data Source={_dbPath}";
            _poolConnectionString ??= _connectionString;
        }

        public SqliteConnection GetConnection()
        {
            SqliteConnection? conn = null;

            lock (_poolLock)
            {
                while (_pool.Count > 0)
                {
                    var pooled = _pool.Dequeue();
                    if (pooled.State == System.Data.ConnectionState.Open)
                    {
                        conn = pooled;
                        break;
                    }
                    try { pooled.Dispose(); } catch { }
                }
            }

            if (conn == null)
            {
                conn = new SqliteConnection(_connectionString);
                conn.Open();
            }

            // Enforce foreign keys and busy timeout on every connection
            using (var cmd = conn.CreateCommand())
            {
                cmd.CommandText = "PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 15000;";
                cmd.ExecuteNonQuery();
            }

            return conn;
        }

        /// <summary>
        /// Return a connection to the pool instead of disposing it.
        /// Use this for high-frequency operations (sync, POS billing).
        /// </summary>
        public void ReturnConnection(SqliteConnection conn)
        {
            if (conn == null || conn.State != System.Data.ConnectionState.Open)
            {
                conn?.Dispose();
                return;
            }

            lock (_poolLock)
            {
                if (_pool.Count < MaxPoolSize)
                {
                    _pool.Enqueue(conn);
                    return;
                }
            }
            conn.Dispose();
        }

        /// <summary>
        /// Read a value from AppSettings table.
        /// </summary>
        public string? GetAppSetting(string key)
        {
            try
            {
                using var conn = GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT Value FROM AppSettings WHERE Key = @k";
                cmd.Parameters.AddWithValue("@k", key);
                return cmd.ExecuteScalar() as string;
            }
            catch { return null; }
        }

        /// <summary>
        /// Execute an action inside a transaction. Automatically commits on success,
        /// rolls back on exception. Enterprise-grade data consistency.
        /// </summary>
        public void ExecuteInTransaction(Action<SqliteConnection, SqliteTransaction> action)
        {
            using var conn = GetConnection();
            using var txn = conn.BeginTransaction();
            try
            {
                action(conn, txn);
                txn.Commit();
            }
            catch
            {
                try { txn.Rollback(); } catch { }
                throw;
            }
        }

        /// <summary>
        /// Execute a function inside a transaction and return a result.
        /// </summary>
        public T ExecuteInTransaction<T>(Func<SqliteConnection, SqliteTransaction, T> func)
        {
            using var conn = GetConnection();
            using var txn = conn.BeginTransaction();
            try
            {
                var result = func(conn, txn);
                txn.Commit();
                return result;
            }
            catch
            {
                try { txn.Rollback(); } catch { }
                throw;
            }
        }

        /// <summary>
        /// Async transaction wrapper for sync operations.
        /// </summary>
        public async Task ExecuteInTransactionAsync(Func<SqliteConnection, SqliteTransaction, Task> action)
        {
            using var conn = GetConnection();
            using var txn = conn.BeginTransaction();
            try
            {
                await action(conn, txn);
                txn.Commit();
            }
            catch
            {
                try { txn.Rollback(); } catch { }
                throw;
            }
        }

        public void Initialize()
        {
            using var conn = GetConnection();

            // ═══ ENTERPRISE: Enable WAL mode for concurrent read/write ═══
            using (var cmd = conn.CreateCommand())
            {
                cmd.CommandText = @"
                    PRAGMA journal_mode = WAL;
                    PRAGMA synchronous = NORMAL;
                    PRAGMA cache_size = -20000;
                    PRAGMA temp_store = MEMORY;
                    PRAGMA mmap_size = 268435456;
                    PRAGMA wal_autocheckpoint = 1000;
                    PRAGMA busy_timeout = 15000;";
                cmd.ExecuteNonQuery();
            }

            LogService.Info($"Database initialized at: {_dbPath}");

            string schema = ReadSchema();
            if (!string.IsNullOrEmpty(schema))
            {
                string[] statements = schema.Split(';');
                foreach (var stmt in statements)
                {
                    if (!string.IsNullOrWhiteSpace(stmt))
                    {
                        try
                        {
                            using var cmd = conn.CreateCommand();
                            cmd.CommandText = stmt.Trim() + ";";
                            cmd.ExecuteNonQuery();
                        }
                        catch (Exception ex)
                        {
                            LogService.Error($"Schema statement failed: {stmt.Trim().Substring(0, Math.Min(80, stmt.Trim().Length))}...", ex);
                        }
                    }
                }
            }

            Migrate(conn);
            CreateDefaultAdmin(conn);
            CreateIndexes(conn);

            // Migrate plain-text credentials to encrypted storage
            try { SecureSettingsService.MigrateExistingCredentials(this); }
            catch (Exception ex) { LogService.Error("Credential migration failed", ex); }
        }

        private static string ReadSchema()
        {
            // Embedded resource (works in single-file publish where the file may not be on disk)
            try
            {
                var asm = typeof(DatabaseService).Assembly;
                var name = asm.GetManifestResourceNames().FirstOrDefault(n => n.EndsWith("DbSchema.sql", StringComparison.OrdinalIgnoreCase));
                if (name != null)
                {
                    using var stream = asm.GetManifestResourceStream(name);
                    if (stream != null)
                        using (var reader = new StreamReader(stream))
                            return reader.ReadToEnd();
                }
            }
            catch { }

            // File fallback
            try
            {
                string path = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "Database", "DbSchema.sql");
                if (File.Exists(path)) return File.ReadAllText(path);
            }
            catch { }
            return "";
        }

        private void Migrate(SqliteConnection conn)
        {
            // Sync columns
            AddColumnIfMissing(conn, "Master1", "ServerId", "INTEGER");
            AddColumnIfMissing(conn, "Master1", "SyncStatus", "TEXT DEFAULT 'Local'");
            AddColumnIfMissing(conn, "Master1", "SyncError", "TEXT");
            AddColumnIfMissing(conn, "Master1", "DelStatus", "TEXT");
            AddColumnIfMissing(conn, "Master1", "BusinessType", "TEXT");
            AddColumnIfMissing(conn, "Master1", "SameOrDiffState", "TEXT");
            AddColumnIfMissing(conn, "Master1", "DateOfBirth", "TEXT");
            AddColumnIfMissing(conn, "Master1", "DateOfAnniversary", "TEXT");
            AddColumnIfMissing(conn, "Master1", "ContactPerson", "TEXT");
            AddColumnIfMissing(conn, "Master1", "DrCr", "TEXT");
            AddColumnIfMissing(conn, "Master1", "BankName", "TEXT");
            AddColumnIfMissing(conn, "Master1", "IFSCCode", "TEXT");
            AddColumnIfMissing(conn, "Master1", "SwiftCode", "TEXT");
            AddColumnIfMissing(conn, "Master1", "AccountNo", "TEXT");
            AddColumnIfMissing(conn, "Master1", "TelNo", "TEXT");
            AddColumnIfMissing(conn, "Master1", "Fax", "TEXT");
            AddColumnIfMissing(conn, "Tran1", "ServerId", "INTEGER");
            AddColumnIfMissing(conn, "Tran1", "SyncStatus", "TEXT DEFAULT 'Local'");
            AddColumnIfMissing(conn, "Tran1", "SyncError", "TEXT");
            AddColumnIfMissing(conn, "Tran1", "PaymentMode", "TEXT");
            AddColumnIfMissing(conn, "Tran1", "SyncPayload", "TEXT");

            // Columns added to DbSchema.sql after early DBs were created
            AddColumnIfMissing(conn, "Master1", "AliasName", "TEXT");
            AddColumnIfMissing(conn, "Master1", "PrintName", "TEXT");
            AddColumnIfMissing(conn, "Master1", "Description", "TEXT");
            AddColumnIfMissing(conn, "Master1", "Station", "TEXT");
            AddColumnIfMissing(conn, "Master1", "TaxCategory", "TEXT DEFAULT 'GST 0%'");
            AddColumnIfMissing(conn, "Master1", "MinSalePrice", "REAL DEFAULT 0");
            AddColumnIfMissing(conn, "Master1", "MainUnit", "TEXT");
            AddColumnIfMissing(conn, "Master1", "Address", "TEXT");
            AddColumnIfMissing(conn, "Master1", "Country", "TEXT");
            AddColumnIfMissing(conn, "Master1", "WhatsApp", "TEXT");
            AddColumnIfMissing(conn, "Master1", "DealerType", "TEXT");
            AddColumnIfMissing(conn, "Master1", "FilingFreq", "TEXT");
            AddColumnIfMissing(conn, "Master1", "Transport", "TEXT");
            AddColumnIfMissing(conn, "Master1", "Distance", "TEXT");
            AddColumnIfMissing(conn, "Master1", "Aadhaar", "TEXT");
            AddColumnIfMissing(conn, "Master1", "TIN", "TEXT");
            AddColumnIfMissing(conn, "Master1", "Ward", "TEXT");
            AddColumnIfMissing(conn, "Master1", "CST", "TEXT");
            AddColumnIfMissing(conn, "Master1", "LST", "TEXT");
            AddColumnIfMissing(conn, "Master1", "IECode", "TEXT");
            AddColumnIfMissing(conn, "Master1", "SelfVal", "REAL DEFAULT 0");
            AddColumnIfMissing(conn, "Master1", "OpeningStock", "REAL DEFAULT 0");
            AddColumnIfMissing(conn, "Master1", "OpeningValue", "REAL DEFAULT 0");

            // Laravel item-form parity columns
            AddColumnIfMissing(conn, "Master1", "ItemType", "TEXT");
            AddColumnIfMissing(conn, "Master1", "Category", "TEXT");
            AddColumnIfMissing(conn, "Master1", "Brand", "TEXT");
            AddColumnIfMissing(conn, "Master1", "CategoryId", "INTEGER");
            AddColumnIfMissing(conn, "Master1", "BrandId", "INTEGER");
            AddColumnIfMissing(conn, "Master1", "WholeSalePrice", "REAL DEFAULT 0");
            AddColumnIfMissing(conn, "Master1", "ProfitMargin", "REAL DEFAULT 0");
            AddColumnIfMissing(conn, "Master1", "AlertQty", "REAL DEFAULT 0");

            // Laravel item-form parity (unit info / stock / warranty / tax)
            AddColumnIfMissing(conn, "Master1", "SupplierId", "INTEGER");
            AddColumnIfMissing(conn, "Master1", "LoyaltyPoint", "INTEGER DEFAULT 0");
            AddColumnIfMissing(conn, "Master1", "UnitType", "TEXT");
            AddColumnIfMissing(conn, "Master1", "SaleUnitId", "INTEGER");
            AddColumnIfMissing(conn, "Master1", "PurchaseUnitId", "INTEGER");
            AddColumnIfMissing(conn, "Master1", "ConversionRate", "REAL DEFAULT 1");
            AddColumnIfMissing(conn, "Master1", "Warranty", "TEXT");
            AddColumnIfMissing(conn, "Master1", "WarrantyDate", "TEXT");
            AddColumnIfMissing(conn, "Master1", "Guarantee", "TEXT");
            AddColumnIfMissing(conn, "Master1", "GuaranteeDate", "TEXT");
            AddColumnIfMissing(conn, "Master1", "TaxType", "TEXT DEFAULT 'Exclusive'");

            // Lowercase Laravel mirror tables - offline tracking
            AddColumnIfMissing(conn, "items", "ServerId", "INTEGER");
            AddColumnIfMissing(conn, "items", "SyncStatus", "TEXT DEFAULT 'Synced'");

            // customers table – installment customer fields
            AddColumnIfMissing(conn, "customers", "work_address", "TEXT");

            // promotions table – tier pricing for partial qty
            AddColumnIfMissing(conn, "promotions", "tier_percentages", "TEXT DEFAULT ''");

            // sales table — coupon tracking
            AddColumnIfMissing(conn, "sales", "coupon_code", "TEXT");
            AddColumnIfMissing(conn, "sales", "coupon_discount", "REAL DEFAULT 0");

            // ═══ SAVINGS FEATURE (server sales.mrp_total/savings parity) ═══
            // MRP total vs bill amount: savings = max(0, mrp_total - grand_total).
            // Backfill purani sales ke liye ek baar (column naya bana ho tab hi).
            bool hadSavingsColumn = false;
            using (var chk = conn.CreateCommand())
            {
                chk.CommandText = "SELECT COUNT(*) FROM pragma_table_info('sales') WHERE lower(name)='savings'";
                hadSavingsColumn = (long)chk.ExecuteScalar() > 0;
            }
            AddColumnIfMissing(conn, "sales", "mrp_total", "REAL DEFAULT 0");
            AddColumnIfMissing(conn, "sales", "savings", "REAL DEFAULT 0");
            if (!hadSavingsColumn)
            {
                using var bf = conn.CreateCommand();
                bf.CommandText = @"
                    UPDATE sales SET
                        mrp_total = COALESCE((SELECT SUM(sd.qty * i.mrp_price)
                                              FROM sale_details sd
                                              LEFT JOIN items i ON i.id = sd.item_id
                                              WHERE sd.sales_id = sales.id AND sd.del_status='Live'), 0),
                        savings = MAX(0, COALESCE((SELECT SUM(sd.qty * i.mrp_price)
                                                   FROM sale_details sd
                                                   LEFT JOIN items i ON i.id = sd.item_id
                                                   WHERE sd.sales_id = sales.id AND sd.del_status='Live'), 0)
                                      - COALESCE(grand_total, 0))
                    WHERE del_status='Live'";
                bf.ExecuteNonQuery();
            }

            AddColumnIfMissing(conn, "customers", "guarantor_name", "TEXT");
            AddColumnIfMissing(conn, "customers", "guarantor_mobile", "TEXT");
            AddColumnIfMissing(conn, "customers", "is_installment_customer", "TEXT");
            AddColumnIfMissing(conn, "customers", "customer_nid", "TEXT");
            AddColumnIfMissing(conn, "customers", "customer_photo", "TEXT");
            AddColumnIfMissing(conn, "customers", "guarantor_present_address", "TEXT");
            AddColumnIfMissing(conn, "customers", "guarantor_work_address", "TEXT");
            AddColumnIfMissing(conn, "customers", "guarantor_nid", "TEXT");
            AddColumnIfMissing(conn, "customers", "guarantor_photo", "TEXT");

            CreateTableIfMissing(conn, "Units", "Id INTEGER PRIMARY KEY, UnitName TEXT NOT NULL");
            CreateTableIfMissing(conn, "Brands", "Id INTEGER PRIMARY KEY, Name TEXT NOT NULL");
            CreateTableIfMissing(conn, "ItemCategories", "Id INTEGER PRIMARY KEY, Name TEXT NOT NULL");
            CreateTableIfMissing(conn, "Suppliers", "Id INTEGER PRIMARY KEY, Name TEXT NOT NULL");
            CreateTableIfMissing(conn, "Racks", "Id INTEGER PRIMARY KEY, Name TEXT NOT NULL");
            CreateTableIfMissing(conn, "Variations", "Id INTEGER PRIMARY KEY, VariationName TEXT NOT NULL");
            CreateTableIfMissing(conn, "ExpenseCategories", "Id INTEGER PRIMARY KEY, Name TEXT NOT NULL");

            // Config lookup parity columns (older DBs)
            AddColumnIfMissing(conn, "Units", "Description", "TEXT");
            AddColumnIfMissing(conn, "Units", "ServerId", "INTEGER DEFAULT 0");
            AddColumnIfMissing(conn, "Units", "SyncStatus", "TEXT DEFAULT 'Synced'");
            AddColumnIfMissing(conn, "Units", "del_status", "TEXT DEFAULT 'Live'");
            AddColumnIfMissing(conn, "Brands", "Description", "TEXT");
            AddColumnIfMissing(conn, "Brands", "ServerId", "INTEGER DEFAULT 0");
            AddColumnIfMissing(conn, "Brands", "SyncStatus", "TEXT DEFAULT 'Synced'");
            AddColumnIfMissing(conn, "Brands", "del_status", "TEXT DEFAULT 'Live'");
            AddColumnIfMissing(conn, "ItemCategories", "Description", "TEXT");
            AddColumnIfMissing(conn, "ItemCategories", "ServerId", "INTEGER DEFAULT 0");
            AddColumnIfMissing(conn, "ItemCategories", "SyncStatus", "TEXT DEFAULT 'Synced'");
            AddColumnIfMissing(conn, "ItemCategories", "del_status", "TEXT DEFAULT 'Live'");
            AddColumnIfMissing(conn, "Racks", "Description", "TEXT");
            AddColumnIfMissing(conn, "Racks", "ServerId", "INTEGER DEFAULT 0");
            AddColumnIfMissing(conn, "Racks", "SyncStatus", "TEXT DEFAULT 'Synced'");
            AddColumnIfMissing(conn, "Racks", "del_status", "TEXT DEFAULT 'Live'");
            AddColumnIfMissing(conn, "Variations", "VariationValue", "TEXT");
            AddColumnIfMissing(conn, "Variations", "ServerId", "INTEGER DEFAULT 0");
            AddColumnIfMissing(conn, "Variations", "SyncStatus", "TEXT DEFAULT 'Synced'");
            AddColumnIfMissing(conn, "Variations", "del_status", "TEXT DEFAULT 'Live'");
            AddColumnIfMissing(conn, "Suppliers", "ServerId", "INTEGER DEFAULT 0");
            AddColumnIfMissing(conn, "Suppliers", "SyncStatus", "TEXT DEFAULT 'Synced'");
            AddColumnIfMissing(conn, "Suppliers", "del_status", "TEXT DEFAULT 'Live'");
            AddColumnIfMissing(conn, "ExpenseCategories", "Description", "TEXT");
            AddColumnIfMissing(conn, "ExpenseCategories", "ServerId", "INTEGER DEFAULT 0");
            AddColumnIfMissing(conn, "ExpenseCategories", "SyncStatus", "TEXT DEFAULT 'Synced'");
            AddColumnIfMissing(conn, "ExpenseCategories", "del_status", "TEXT DEFAULT 'Live'");

            // Transfers
            AddColumnIfMissing(conn, "transfers", "reference_no", "TEXT");
            AddColumnIfMissing(conn, "transfers", "date", "TEXT");
            AddColumnIfMissing(conn, "transfers", "from_outlet_id", "INTEGER");
            AddColumnIfMissing(conn, "transfers", "to_outlet_id", "INTEGER");
            AddColumnIfMissing(conn, "transfers", "note", "TEXT");
            AddColumnIfMissing(conn, "transfers", "status", "TEXT DEFAULT 'Draft'");
            AddColumnIfMissing(conn, "transfers", "note_for_sender", "TEXT");
            AddColumnIfMissing(conn, "transfers", "note_for_receiver", "TEXT");
            AddColumnIfMissing(conn, "transfers", "del_status", "TEXT DEFAULT 'Live'");
            AddColumnIfMissing(conn, "transfers", "ServerId", "INTEGER");
            AddColumnIfMissing(conn, "transfers", "SyncStatus", "TEXT DEFAULT 'Local'");
            // Transfer details
            AddColumnIfMissing(conn, "transfer_details", "transfer_id", "INTEGER");
            AddColumnIfMissing(conn, "transfer_details", "item_id", "INTEGER");
            AddColumnIfMissing(conn, "transfer_details", "quantity", "REAL DEFAULT 0");
            AddColumnIfMissing(conn, "transfer_details", "unit_price", "REAL DEFAULT 0");
            AddColumnIfMissing(conn, "transfer_details", "total", "REAL DEFAULT 0");
            AddColumnIfMissing(conn, "transfer_details", "del_status", "TEXT DEFAULT 'Live'");

            // Damages
            AddColumnIfMissing(conn, "damages", "reference_no", "TEXT");
            AddColumnIfMissing(conn, "damages", "date", "TEXT");
            AddColumnIfMissing(conn, "damages", "total_loss", "REAL DEFAULT 0");
            AddColumnIfMissing(conn, "damages", "note", "TEXT");
            AddColumnIfMissing(conn, "damages", "employee_id", "INTEGER");
            AddColumnIfMissing(conn, "damages", "damage_type", "TEXT");
            AddColumnIfMissing(conn, "damages", "del_status", "TEXT DEFAULT 'Live'");
            AddColumnIfMissing(conn, "damages", "ServerId", "INTEGER");
            AddColumnIfMissing(conn, "damages", "SyncStatus", "TEXT DEFAULT 'Local'");
            // Damage details
            AddColumnIfMissing(conn, "damage_details", "damage_id", "INTEGER");
            AddColumnIfMissing(conn, "damage_details", "item_id", "INTEGER");
            AddColumnIfMissing(conn, "damage_details", "damage_quantity", "REAL DEFAULT 0");
            AddColumnIfMissing(conn, "damage_details", "last_purchase_price", "REAL DEFAULT 0");
            AddColumnIfMissing(conn, "damage_details", "loss_amount", "REAL DEFAULT 0");
            AddColumnIfMissing(conn, "damage_details", "total_amount", "REAL DEFAULT 0");
            AddColumnIfMissing(conn, "damage_details", "del_status", "TEXT DEFAULT 'Live'");

            // Quotations
            AddColumnIfMissing(conn, "quotations", "reference_no", "TEXT");
            AddColumnIfMissing(conn, "quotations", "customer_id", "INTEGER");
            AddColumnIfMissing(conn, "quotations", "date", "TEXT");
            AddColumnIfMissing(conn, "quotations", "grand_total", "REAL DEFAULT 0");
            AddColumnIfMissing(conn, "quotations", "note", "TEXT");
            AddColumnIfMissing(conn, "quotations", "discount", "TEXT");
            AddColumnIfMissing(conn, "quotations", "del_status", "TEXT DEFAULT 'Live'");
            AddColumnIfMissing(conn, "quotations", "ServerId", "INTEGER");
            AddColumnIfMissing(conn, "quotations", "SyncStatus", "TEXT DEFAULT 'Local'");
            // Quotation details
            AddColumnIfMissing(conn, "quotation_details", "quotation_id", "INTEGER");
            AddColumnIfMissing(conn, "quotation_details", "item_id", "INTEGER");
            AddColumnIfMissing(conn, "quotation_details", "quantity", "REAL DEFAULT 1");
            AddColumnIfMissing(conn, "quotation_details", "unit_price", "REAL DEFAULT 0");
            AddColumnIfMissing(conn, "quotation_details", "total", "REAL DEFAULT 0");
            AddColumnIfMissing(conn, "quotation_details", "description", "TEXT");
            AddColumnIfMissing(conn, "quotation_details", "del_status", "TEXT DEFAULT 'Live'");

            // Payment methods description
            AddColumnIfMissing(conn, "payment_methods", "description", "TEXT");

            // Mirror transaction tables (Laravel docker schema) - sync tracking
            foreach (string t in new[] { "purchases", "purchase_details", "purchase_payments",
                                          "purchase_returns", "purchase_return_details",
                                          "supplier_payments", "expenses", "payment_methods",
                                          "expense_categories", "sale_returns", "sale_return_details" })
            {
                AddColumnIfMissing(conn, t, "ServerId", "INTEGER DEFAULT 0");
                AddColumnIfMissing(conn, t, "SyncStatus", "TEXT DEFAULT 'Synced'");
                AddColumnIfMissing(conn, t, "SyncError", "TEXT");
            }
            AddColumnIfMissing(conn, "sale_returns", "SyncPayload", "TEXT");

            // Laravel docker (MySQL off_pos) full column parity - config lookup tables
            AddColumnIfMissing(conn, "brands", "company_id", "INTEGER");
            AddColumnIfMissing(conn, "brands", "created_at", "TEXT");
            AddColumnIfMissing(conn, "brands", "del_status", "TEXT");
            AddColumnIfMissing(conn, "brands", "updated_at", "TEXT");
            AddColumnIfMissing(conn, "brands", "user_id", "INTEGER");
            AddColumnIfMissing(conn, "racks", "company_id", "INTEGER");
            AddColumnIfMissing(conn, "racks", "created_at", "TEXT");
            AddColumnIfMissing(conn, "racks", "del_status", "TEXT");
            AddColumnIfMissing(conn, "racks", "updated_at", "TEXT");
            AddColumnIfMissing(conn, "racks", "user_id", "INTEGER");
            AddColumnIfMissing(conn, "suppliers", "address", "TEXT");
            AddColumnIfMissing(conn, "suppliers", "city", "TEXT");
            AddColumnIfMissing(conn, "suppliers", "company_id", "INTEGER");
            AddColumnIfMissing(conn, "suppliers", "company_name", "TEXT");
            AddColumnIfMissing(conn, "suppliers", "country", "TEXT");
            AddColumnIfMissing(conn, "suppliers", "created_at", "TEXT");
            AddColumnIfMissing(conn, "suppliers", "credit_limit", "REAL");
            AddColumnIfMissing(conn, "suppliers", "del_status", "TEXT");
            AddColumnIfMissing(conn, "suppliers", "description", "TEXT");
            AddColumnIfMissing(conn, "suppliers", "email", "TEXT");
            AddColumnIfMissing(conn, "suppliers", "gst_number", "TEXT");
            AddColumnIfMissing(conn, "suppliers", "opening_balance", "REAL");
            AddColumnIfMissing(conn, "suppliers", "opening_balance_type", "TEXT");
            AddColumnIfMissing(conn, "suppliers", "payment_method_id", "INTEGER");
            AddColumnIfMissing(conn, "suppliers", "phone", "TEXT");
            AddColumnIfMissing(conn, "suppliers", "photo", "TEXT");
            AddColumnIfMissing(conn, "suppliers", "postal_code", "TEXT");
            AddColumnIfMissing(conn, "suppliers", "state", "TEXT");
            AddColumnIfMissing(conn, "suppliers", "updated_at", "TEXT");
            AddColumnIfMissing(conn, "suppliers", "user_id", "INTEGER");
            AddColumnIfMissing(conn, "suppliers", "vat_number", "TEXT");
            AddColumnIfMissing(conn, "units", "company_id", "INTEGER");
            AddColumnIfMissing(conn, "units", "created_at", "TEXT");
            AddColumnIfMissing(conn, "units", "del_status", "TEXT");
            AddColumnIfMissing(conn, "units", "unit_name", "TEXT");
            AddColumnIfMissing(conn, "units", "updated_at", "TEXT");
            AddColumnIfMissing(conn, "units", "user_id", "INTEGER");
            AddColumnIfMissing(conn, "users", "answer", "TEXT");
            AddColumnIfMissing(conn, "users", "commission", "REAL");
            AddColumnIfMissing(conn, "users", "company_id", "INTEGER");
            AddColumnIfMissing(conn, "users", "created_at", "TEXT");
            AddColumnIfMissing(conn, "users", "del_status", "TEXT");
            AddColumnIfMissing(conn, "users", "discount_amt", "REAL");
            AddColumnIfMissing(conn, "users", "discount_permission_code", "TEXT");
            AddColumnIfMissing(conn, "users", "email", "TEXT");
            AddColumnIfMissing(conn, "users", "email_verified_at", "TEXT");
            AddColumnIfMissing(conn, "users", "end_date", "TEXT");
            AddColumnIfMissing(conn, "users", "login_notifications", "INTEGER");
            AddColumnIfMissing(conn, "users", "name", "TEXT");
            AddColumnIfMissing(conn, "users", "outlet_id", "TEXT");
            AddColumnIfMissing(conn, "users", "password", "TEXT");
            AddColumnIfMissing(conn, "users", "phone", "TEXT");
            AddColumnIfMissing(conn, "users", "photo", "TEXT");
            AddColumnIfMissing(conn, "users", "question", "TEXT");
            AddColumnIfMissing(conn, "users", "remember_token", "TEXT");
            AddColumnIfMissing(conn, "users", "salary", "REAL");
            AddColumnIfMissing(conn, "users", "session_timeout", "INTEGER");
            AddColumnIfMissing(conn, "users", "start_date", "TEXT");
            AddColumnIfMissing(conn, "users", "two_factor_enabled", "INTEGER");
            AddColumnIfMissing(conn, "users", "updated_at", "TEXT");
            AddColumnIfMissing(conn, "users", "will_login", "TEXT");
            AddColumnIfMissing(conn, "variations", "company_id", "INTEGER");
            AddColumnIfMissing(conn, "variations", "created_at", "TEXT");
            AddColumnIfMissing(conn, "variations", "del_status", "TEXT");
            AddColumnIfMissing(conn, "variations", "updated_at", "TEXT");
            AddColumnIfMissing(conn, "variations", "user_id", "INTEGER");
            AddColumnIfMissing(conn, "variations", "variation_name", "TEXT");
            AddColumnIfMissing(conn, "variations", "variation_value", "TEXT");

            // Laravel sessions table
            CreateTableIfMissing(conn, "sessions", "id TEXT PRIMARY KEY, user_id INTEGER, ip_address TEXT, user_agent TEXT, payload TEXT, last_activity INTEGER");

            // Fixed asset FK column alias (schema uses asset_stock_in_id, EntityRegistry expects fixed_asset_stock_in_id)
            AddColumnIfMissing(conn, "fixed_asset_stock_in_details", "fixed_asset_stock_in_id", "INTEGER");
            AddColumnIfMissing(conn, "fixed_asset_stock_out_details", "fixed_asset_stock_out_id", "INTEGER");

            // Additional sync columns for all key tables
            foreach (string t in new[] { "sales", "sale_details", "sale_payments", "sale_returns", "sale_return_details",
                "customers", "suppliers", "items", "item_categories", "brands", "units", "racks", "variations",
                "damages", "damage_details", "transfers", "transfer_details",
                "quotations", "quotation_details", "promotions",
                "servicings", "warranties", "bookings",
                "installment_sales", "installment_sale_details",
                "customer_receives", "payment_methods",
                "income_categories", "incomes", "expense_categories", "expenses", "deposit_withdraws",
                "fixed_asset_items", "fixed_asset_stock_ins", "fixed_asset_stock_in_details",
                "fixed_asset_stock_outs", "fixed_asset_stock_out_details",
                "price_lists", "price_list_items",
                "roles", "users", "employees", "attendances", "salaries", "salary_items",
                "employee_advance_payments", "denominations", "counters", "outlets",
                "printers", "multiple_currencies", "delivery_partners",
                "business_club_settings", "customer_wallets", "wallet_transactions",
                "business_club_members", "business_club_transactions",
                "registers", "permissions" })
            {
                AddColumnIfMissing(conn, t, "ServerId", "INTEGER DEFAULT 0");
                AddColumnIfMissing(conn, t, "SyncStatus", "TEXT DEFAULT 'Synced'");
                AddColumnIfMissing(conn, t, "SyncError", "TEXT");
            }

            // ═══ Business Club NEW schema fields (membership_amount, profit_share_percentage, redemption_day, is_active) ═══
            AddColumnIfMissing(conn, "business_club_settings", "membership_amount", "REAL DEFAULT 0");
            AddColumnIfMissing(conn, "business_club_settings", "profit_share_percentage", "REAL DEFAULT 0");
            AddColumnIfMissing(conn, "business_club_settings", "redemption_day", "INTEGER DEFAULT 1");
            AddColumnIfMissing(conn, "business_club_settings", "is_active", "TEXT DEFAULT 'yes'");

            // fixed_asset_items stock_quantity for display
            AddColumnIfMissing(conn, "fixed_asset_items", "stock_quantity", "REAL DEFAULT 0");
            AddColumnIfMissing(conn, "installment_sales", "amount", "REAL DEFAULT 0");

            // installment_sales: payment_method_id column (MySQL has it)
            AddColumnIfMissing(conn, "installment_sales", "payment_method_id", "INTEGER");

            // sale_details table uses 'sales_id' but some queries use 'sale_id'
            AddColumnIfMissing(conn, "sale_details", "sale_id", "INTEGER DEFAULT 0");

            // Sync columns for hold/combo/booking tables (MySQL parity)
            foreach (string t in new[] { "holds", "hold_details", "hold_combo_items",
                                          "combo_items", "combo_sales", "bookings",
                                          "set_opening_stocks", "wallet_transactions" })
            {
                AddColumnIfMissing(conn, t, "ServerId", "INTEGER DEFAULT 0");
                AddColumnIfMissing(conn, t, "SyncStatus", "TEXT DEFAULT 'Synced'");
                AddColumnIfMissing(conn, t, "SyncError", "TEXT");
            }

            // Loyalty sync pending flag for customers (wallet/loyalty push)
            AddColumnIfMissing(conn, "customers", "LoyaltySyncPending", "INTEGER DEFAULT 0");

            // pending_sync table for offline queue
            CreateTableIfMissing(conn, "pending_sync", @"id INTEGER PRIMARY KEY AUTOINCREMENT,
                entity_type TEXT NOT NULL,
                entity_id INTEGER NOT NULL,
                operation TEXT NOT NULL,
                payload TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                retry_count INTEGER DEFAULT 0,
                last_error TEXT");

            // held_bills table for persisting held vouchers across app restart
            CreateTableIfMissing(conn, "held_bills", @"id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_id INTEGER,
                customer_name TEXT,
                customer_phone TEXT,
                items_json TEXT,
                held_at TEXT,
                note TEXT");

            // app_settings key-value store
            CreateTableIfMissing(conn, "app_settings", "key TEXT PRIMARY KEY, value TEXT");

            // "employees" mirror = Laravel "users" table (renamed because SQLite treats
            // "users"/"Users" as the same table, and "Users" is the app login table).
            CreateTableIfMissing(conn, "employees", @"""id"" INTEGER PRIMARY KEY, ""name"" TEXT, ""email"" TEXT, ""password"" TEXT, ""role"" TEXT, ""phone"" TEXT, ""photo"" TEXT,
                ""salary"" REAL, ""commission"" REAL, ""outlet_id"" TEXT, ""will_login"" TEXT, ""start_date"" TEXT, ""end_date"" TEXT,
                ""email_verified_at"" TEXT, ""remember_token"" TEXT, ""question"" TEXT, ""answer"" TEXT, ""discount_permission_code"" INTEGER,
                ""discount_amt"" REAL, ""login_notifications"" INTEGER, ""session_timeout"" INTEGER, ""two_factor_enabled"" INTEGER,
                ""del_status"" TEXT, ""company_id"" INTEGER, ""user_id"" INTEGER, ""created_at"" TEXT, ""updated_at"" TEXT");

            // Cash register (mirrors Laravel Modules\Sale registers + register management columns)
            CreateTableIfMissing(conn, "registers", @"id INTEGER PRIMARY KEY AUTOINCREMENT,
                opening_balance REAL DEFAULT 0, closing_balance REAL DEFAULT 0,
                sale_paid_amount REAL DEFAULT 0, refund_amount REAL DEFAULT 0, customer_due_receive REAL DEFAULT 0,
                total_purchase REAL DEFAULT 0, total_downpayment REAL DEFAULT 0, total_installmentcollection REAL DEFAULT 0,
                total_servicing REAL DEFAULT 0, total_purchase_return REAL DEFAULT 0, total_due_payment REAL DEFAULT 0,
                total_expense REAL DEFAULT 0, register_status INTEGER DEFAULT 1,
                user_id INTEGER, outlet_id INTEGER, company_id INTEGER, counter_id INTEGER,
                del_status TEXT, created_at TEXT, updated_at TEXT,
                opening_details TEXT, opening_balance_date_time TEXT, closing_balance_date_time TEXT,
                payment_methods_sale TEXT, others_currency TEXT");
            AddColumnIfMissing(conn, "registers", "opening_details", "TEXT");
            AddColumnIfMissing(conn, "registers", "opening_balance_date_time", "TEXT");
            AddColumnIfMissing(conn, "registers", "closing_balance_date_time", "TEXT");
            AddColumnIfMissing(conn, "registers", "payment_methods_sale", "TEXT");
            AddColumnIfMissing(conn, "registers", "others_currency", "TEXT");

            // WhatsApp Twilio columns (added 2026-08-09 — missing from earlier DBs)
            AddColumnIfMissing(conn, "companies", "whatsapp_account_sid", "TEXT");
            AddColumnIfMissing(conn, "companies", "whatsapp_auth_token",  "TEXT");
            AddColumnIfMissing(conn, "companies", "whatsapp_from_number", "TEXT");

            // Module management table (added 2026-08-12)
            CreateTableIfMissing(conn, "installed_modules", @"id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT,
                version TEXT,
                author TEXT,
                zip_path TEXT,
                is_enabled INTEGER DEFAULT 1,
                installed_at TEXT,
                updated_at TEXT");

            // ═══ SYNC CONFLICT FIX v2.0 (2026-08-16) ═══

            // FIX 4: dead_letters — pending_sync 100 retry exhaust hone par record
            // silently drop nahi hota; yahan permanent log hota hai (user dikh sakta hai)
            CreateTableIfMissing(conn, "dead_letters", @"id INTEGER PRIMARY KEY AUTOINCREMENT,
                entity_type TEXT,
                entity_id INTEGER,
                operation TEXT,
                payload TEXT,
                error_message TEXT,
                retry_count INTEGER DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                resolved INTEGER DEFAULT 0");

            // Sync history — har sync cycle ka start/complete time + result.
            // "Up to date" (skip) cycles log nahi hote; sirf actual push/pull
            // chalne wale cycles + overlap-skipped requests likhi jati hain.
            CreateTableIfMissing(conn, "sync_history", @"id INTEGER PRIMARY KEY AUTOINCREMENT,
                start_time TEXT NOT NULL,
                end_time TEXT,
                status TEXT NOT NULL,
                pushed INTEGER DEFAULT 0,
                pulled INTEGER DEFAULT 0,
                error TEXT,
                overlap_prevented INTEGER DEFAULT 0");

            // FIX 2: SyncVersion logical clock — shared master tables. Har LOCAL edit
            // (SyncStatus='Local' update) par trigger version bump karta hai, isliye
            // har edit path (POSPage, EntityCrudPage, sab CRUD pages) mein alag se
            // version code add karne ki zaroorat nahi. Pull/status updates ('Synced')
            // kabhi bump nahi karte.
            string[] versionTables = {
                "items", "item_categories", "brands", "units", "racks", "variations",
                "suppliers", "customers", "promotions", "price_lists", "price_list_items",
                "payment_methods", "counters", "expense_categories"
            };
            foreach (var vt in versionTables)
            {
                AddColumnIfMissing(conn, vt, "SyncVersion", "INTEGER DEFAULT 1");
                if (TableExistsForTrigger(conn, vt))
                {
                    using var trg = conn.CreateCommand();
                    trg.CommandText = $@"CREATE TRIGGER IF NOT EXISTS trg_{vt}_version
                        AFTER UPDATE ON ""{vt}""
                        WHEN NEW.SyncStatus = 'Local' AND IFNULL(OLD.SyncStatus, '') <> 'Local'
                        BEGIN
                            UPDATE ""{vt}"" SET SyncVersion = IFNULL(NEW.SyncVersion, 0) + 1 WHERE Id = NEW.Id;
                        END";
                    trg.ExecuteNonQuery();
                }
            }

            // Master1 has no numeric Id column (Code is the key), so the version
            // trigger above cannot apply. Add the column manually — bumping is done
            // by the edit paths (CustomerWindow / CreateCustomerV2Page).
            AddColumnIfMissing(conn, "Master1", "SyncVersion", "INTEGER DEFAULT 1");

            RenumberLegacySaleInvoices(conn);
            RepairLocalSaleMirror(conn);
            CleanupDuplicateSaleDetails(conn);
            EnsureUpiPaymentMethod(conn);
            SeedBuiltInModules(conn);
        }

        /// <summary>
        /// Ensure "UPI" exists in payment_methods. If only "Online" exists, add UPI alias.
        /// </summary>
        private void EnsureUpiPaymentMethod(SqliteConnection conn)
        {
            try
            {
                using var chk = conn.CreateCommand();
                chk.CommandText = "SELECT COUNT(*) FROM payment_methods WHERE LOWER(name)='upi'";
                if (Convert.ToInt64(chk.ExecuteScalar()) > 0) return;

                // Add UPI if not present
                using var ins = conn.CreateCommand();
                ins.CommandText = "INSERT INTO payment_methods (Id, name, del_status, SyncStatus) VALUES (@id, 'UPI', 'Live', 'Local')";
                ins.Parameters.AddWithValue("@id", NextLocalMirrorId(conn, "payment_methods"));
                ins.ExecuteNonQuery();
            }
            catch { }
        }

        /// <summary>
        /// Seed built-in modules (like Business Club) so they appear in the module list
        /// and can be toggled on/off without needing a ZIP upload.
        /// </summary>
        private void SeedBuiltInModules(SqliteConnection conn)
        {
            try
            {
                var builtInModules = new[]
                {
                    new { Name = "Business Club", Description = "Loyalty program with wallet, cashback & membership tiers for customers", Version = "1.0.0", Author = "RashanKiDukan" }
                };

                foreach (var mod in builtInModules)
                {
                    using var chk = conn.CreateCommand();
                    chk.CommandText = "SELECT COUNT(*) FROM installed_modules WHERE LOWER(name)=LOWER(@n)";
                    chk.Parameters.AddWithValue("@n", mod.Name);
                    if (Convert.ToInt64(chk.ExecuteScalar()) > 0) continue;

                    using var ins = conn.CreateCommand();
                    ins.CommandText = @"INSERT INTO installed_modules (name, description, version, author, zip_path, is_enabled, installed_at, updated_at)
                                        VALUES (@name, @desc, @ver, @author, '', 1, @now, @now)";
                    ins.Parameters.AddWithValue("@name", mod.Name);
                    ins.Parameters.AddWithValue("@desc", mod.Description);
                    ins.Parameters.AddWithValue("@ver", mod.Version);
                    ins.Parameters.AddWithValue("@author", mod.Author);
                    ins.Parameters.AddWithValue("@now", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
                    ins.ExecuteNonQuery();

                    // Get the new ID and enqueue sync to cloud
                    using var getId = conn.CreateCommand();
                    getId.CommandText = "SELECT last_insert_rowid()";
                    var newId = Convert.ToInt64(getId.ExecuteScalar());
                    Services.SyncService.EnqueueSync("installed_modules", newId, "insert");
                }
            }
            catch { }
        }

        // One-time repair: purane dinon ke bills (VchNo=1/2 old in-memory counter) ko
        // SALE-{year}-{counter}-{000001} format me renumber karta hai. Idempotent — rows
        // jo already 'SALE-' se shuru hain chhod di jati hain. Local bills ka SyncPayload
        // invoice_no bhi update hota hai aur unhe dubara push ke liye 'Local' mark kiya jata hai
        // (server local_id se upsert karta hai, isliye duplicate nahi banege).
        private void RenumberLegacySaleInvoices(SqliteConnection conn)
        {
            try
            {
                string counter = InvoiceNumberService.GetCounterCode(conn, null);

                var legacy = new List<(long Rowid, string OldNo, string Date, string VchCode, string Payload, string CreatedAt)>();
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = @"SELECT rowid, VchNo, VchDate, VchCode, IFNULL(SyncPayload,''), IFNULL(CreatedAt,'')
                                      FROM Tran1
                                      WHERE VchType = 'Sales'
                                        AND (VchNo IS NULL OR VchNo = '' OR VchNo NOT LIKE 'SALE-%')
                                      ORDER BY rowid";
                    using var r = c.ExecuteReader();
                    while (r.Read())
                    {
                        legacy.Add((r.GetInt64(0),
                                    r.IsDBNull(1) ? "" : r.GetString(1),
                                    r.IsDBNull(2) ? "" : r.GetString(2),
                                    r.IsDBNull(3) ? "" : r.GetString(3),
                                    r.GetString(4),
                                    r.GetString(5)));
                    }
                }
                if (legacy.Count == 0) return;

                var seqByYear = new Dictionary<string, long>();
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = @"SELECT VchNo FROM Tran1 WHERE VchType = 'Sales' AND VchNo LIKE 'SALE-%'";
                    using var r = c.ExecuteReader();
                    while (r.Read())
                    {
                        string[] parts = r.GetString(0).Split('-');
                        if (parts.Length < 4) continue;
                        if (long.TryParse(parts[1], out long y) && long.TryParse(parts[3], out long s))
                            if (!seqByYear.TryGetValue(parts[1], out long cur) || s > cur)
                                seqByYear[parts[1]] = s;
                    }
                }
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = "SELECT invoice_no FROM sales WHERE invoice_no LIKE 'SALE-%'";
                    using var r = c.ExecuteReader();
                    while (r.Read())
                    {
                        string[] parts = r.GetString(0).Split('-');
                        if (parts.Length < 4) continue;
                        if (long.TryParse(parts[1], out long y) && long.TryParse(parts[3], out long s))
                            if (!seqByYear.TryGetValue(parts[1], out long cur) || s > cur)
                                seqByYear[parts[1]] = s;
                    }
                }

                var renumbered = new List<(long Rowid, string NewNo, string VchCode, string Payload, string CreatedAt)>();
                foreach (var (rowid, _, date, vchCode, payload, createdAt) in legacy)
                {
                    string year = ExtractYear(date);
                    if (string.IsNullOrEmpty(year)) year = DateTime.Now.Year.ToString();
                    seqByYear.TryGetValue(year, out long cur);
                    cur++;
                    seqByYear[year] = cur;
                    string newNo = $"SALE-{year}-{counter}-{cur:D6}";

                    using var u = conn.CreateCommand();
                    u.CommandText = "UPDATE Tran1 SET VchNo = @v WHERE rowid = @id";
                    u.Parameters.AddWithValue("@v", newNo);
                    u.Parameters.AddWithValue("@id", rowid);
                    u.ExecuteNonQuery();

                    renumbered.Add((rowid, newNo, vchCode, payload, createdAt));
                }

                // Local bills: SyncPayload ke andar invoice_no update karo + dubara push ke liye 'Local' mark
                foreach (var (_, newNo, vchCode, payload, _) in renumbered)
                {
                    if (string.IsNullOrEmpty(vchCode) || string.IsNullOrEmpty(payload)) continue;
                    try
                    {
                        using var doc = System.Text.Json.JsonDocument.Parse(payload);
                        var root = doc.RootElement.Clone();
                        if (root.ValueKind == System.Text.Json.JsonValueKind.Object && root.TryGetProperty("invoice_no", out _))
                        {
                            var dict = new System.Text.Json.Nodes.JsonObject();
                            foreach (var p in root.EnumerateObject())
                                dict[p.Name] = System.Text.Json.Nodes.JsonNode.Parse(p.Value.GetRawText());
                            dict["invoice_no"] = newNo;
                            string updated = dict.ToJsonString();

                            using var u = conn.CreateCommand();
                            u.CommandText = "UPDATE Tran1 SET SyncPayload = @p, SyncStatus = 'Local', SyncError = NULL WHERE VchCode = @c";
                            u.Parameters.AddWithValue("@p", updated);
                            u.Parameters.AddWithValue("@c", vchCode);
                            u.ExecuteNonQuery();
                        }
                    }
                    catch { }
                }

                // sales mirror table — local POS bills: Tran1 (SLS, SyncPayload wale) se
                // (CreatedAt, Amount) match karke invoice_no/sale_no sync karo
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = @"UPDATE sales
                                      SET invoice_no = t.NewNo, sale_no = t.NewNo
                                      FROM (SELECT VchNo AS NewNo, CreatedAt, Amount FROM Tran1
                                            WHERE VchType='Sales' AND SyncPayload IS NOT NULL AND SyncPayload <> '') t
                                      WHERE sales.date_time = t.CreatedAt AND sales.grand_total = t.Amount";
                    c.ExecuteNonQuery();
                }
            }
            catch { }
        }

        private static string ExtractYear(string date)
        {
            if (string.IsNullOrEmpty(date)) return "";
            string d = date.Trim();
            // dd-MM-yyyy / dd/MM/yyyy
            if (d.Length >= 10 && (d[2] == '-' || d[2] == '/') && char.IsDigit(d[6]))
                return d.Substring(6, 4);
            // yyyy-MM-dd / yyyy/MM/dd
            if (d.Length >= 10 && (d[4] == '-' || d[4] == '/') && char.IsDigit(d[9]))
                return d.Substring(0, 4);
            return "";
        }

        // One-time repair: local POS sales ke sale_details/sale_payments rows jo server
        // pull ke Id-collision me overwrite ho gayi thin, unhe Tran1 ke SyncPayload se
        // rebuild karta hai. Idempotent — jo rows already hain unhe nahi chheerta.
        private void RepairLocalSaleMirror(SqliteConnection conn)
        {
            try
            {
                var localSales = new List<(long Id, string Payload, string SaleDate)>();
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = @"SELECT s.id, IFNULL(t.SyncPayload,''), IFNULL(s.sale_date,'')
                                      FROM sales s
                                      JOIN Tran1 t ON t.VchType='Sales' AND t.VchNo=s.sale_no
                                      WHERE s.SyncStatus='Local'
                                        AND t.SyncPayload IS NOT NULL AND t.SyncPayload != ''";
                    using var r = c.ExecuteReader();
                    while (r.Read())
                        localSales.Add((r.GetInt64(0), r.GetString(1), r.GetString(2)));
                }

                foreach (var (saleId, payload, saleDate) in localSales)
                {
                    long detailLocal = 0, paymentLocal = 0;
                    using (var c = conn.CreateCommand())
                    {
                        c.CommandText = "SELECT COUNT(*) FROM sale_details WHERE sales_id=@id AND SyncStatus='Local'";
                        c.Parameters.AddWithValue("@id", saleId);
                        detailLocal = (long)c.ExecuteScalar();
                    }
                    using (var c = conn.CreateCommand())
                    {
                        c.CommandText = "SELECT COUNT(*) FROM sale_payments WHERE sale_id=@id AND SyncStatus='Local'";
                        c.Parameters.AddWithValue("@id", saleId);
                        paymentLocal = (long)c.ExecuteScalar();
                    }
                    if (detailLocal > 0 && paymentLocal > 0) continue;

                    using var doc = JsonDocument.Parse(payload);
                    var root = doc.RootElement;

                    if (detailLocal == 0 && root.TryGetProperty("items", out var items) && items.ValueKind == JsonValueKind.Array)
                    {
                        foreach (var line in items.EnumerateArray())
                        {
                            string code = line.TryGetProperty("item_code", out var ic) ? ic.GetString() ?? "" : "";
                            if (code == "") continue;
                            long itemId = 0;
                            using (var f = conn.CreateCommand())
                            {
                                f.CommandText = "SELECT id FROM items WHERE code=@c LIMIT 1";
                                f.Parameters.AddWithValue("@c", code);
                                var v = f.ExecuteScalar();
                                if (v != null) itemId = Convert.ToInt64(v);
                            }
                            if (itemId == 0) continue;

                            double qty = GetJsonDouble(line, "qty");
                            double price = GetJsonDouble(line, "menu_unit_price");
                            double taxPerc = GetJsonDouble(line, "menu_vat_percentage");
                            double taxAmt = GetJsonDouble(line, "item_tax_amount");
                            double disc = GetJsonDouble(line, "discount_amount");

                            using var ins = conn.CreateCommand();
                            ins.CommandText = @"INSERT INTO sale_details (Id, sales_id, item_id, qty, menu_unit_price, menu_price_without_discount,
                                                menu_price_with_discount, discount_amount, menu_vat_percentage, item_tax_amount, del_status, SyncStatus, created_at, updated_at)
                                                VALUES (@id, @sid, @item, @qty, @price, @price, @price-@disc, @disc, @tax, @taxamt, 'Live', 'Local', datetime('now'), datetime('now'))";
                            ins.Parameters.AddWithValue("@id", NextLocalMirrorId(conn, "sale_details"));
                            ins.Parameters.AddWithValue("@sid", saleId);
                            ins.Parameters.AddWithValue("@item", itemId);
                            ins.Parameters.AddWithValue("@qty", qty);
                            ins.Parameters.AddWithValue("@price", price);
                            ins.Parameters.AddWithValue("@disc", disc);
                            ins.Parameters.AddWithValue("@tax", taxPerc);
                            ins.Parameters.AddWithValue("@taxamt", taxAmt);
                            ins.ExecuteNonQuery();
                        }
                    }

                    if (paymentLocal == 0 && root.TryGetProperty("payments", out var pays) && pays.ValueKind == JsonValueKind.Array)
                    {
                        foreach (var p in pays.EnumerateArray())
                        {
                            string name = p.TryGetProperty("payment_name", out var pn) ? pn.GetString() ?? "Cash" : "Cash";
                            double amt = GetJsonDouble(p, "amount");

                            using var ins = conn.CreateCommand();
                            ins.CommandText = @"INSERT INTO sale_payments (Id, sale_id, payment_id, amount, date, del_status, SyncStatus, created_at, updated_at)
                                                VALUES (@id, @sid, IFNULL((SELECT id FROM payment_methods WHERE LOWER(name)=LOWER(@name) LIMIT 1),1), @amt, @date, 'Live', 'Local', datetime('now'), datetime('now'))";
                            ins.Parameters.AddWithValue("@id", NextLocalMirrorId(conn, "sale_payments"));
                            ins.Parameters.AddWithValue("@sid", saleId);
                            ins.Parameters.AddWithValue("@name", name);
                            ins.Parameters.AddWithValue("@amt", amt);
                            ins.Parameters.AddWithValue("@date", string.IsNullOrEmpty(saleDate) ? DateTime.Now.ToString("yyyy-MM-dd") : saleDate);
                            ins.ExecuteNonQuery();
                        }
                    }
                }
            }
            catch { }
        }

        /// <summary>
        /// One-time cleanup: if a sales_id has BOTH negative-Id rows (local POS) AND
        /// positive-Id rows (server-synced), the negative ones are stale duplicates.
        /// Delete them so SUM(qty) doesn't double-count. Idempotent.
        /// </summary>
        private void CleanupDuplicateSaleDetails(SqliteConnection conn)
        {
            try
            {
                // sale_details: delete negative-Id rows where same sales_id also has positive-Id rows
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"DELETE FROM sale_details
                        WHERE Id < 0
                          AND sales_id IN (
                              SELECT DISTINCT sales_id FROM sale_details WHERE Id > 0
                          )";
                    cmd.ExecuteNonQuery();
                }
                // sale_payments: same cleanup
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"DELETE FROM sale_payments
                        WHERE Id < 0
                          AND sale_id IN (
                              SELECT DISTINCT sale_id FROM sale_payments WHERE Id > 0
                          )";
                    cmd.ExecuteNonQuery();
                }
                // Generic: any table with Id<0 and ServerId matching a positive-Id row = stale duplicate
                // Also: any table with different positive Id but same ServerId = stale duplicate
                var tables = new[] { "supplier_payments", "expenses", "incomes", "purchases", "sales",
                    "damages", "transfers", "quotations", "customer_receives", "deposit_withdraws" };
                foreach (var t in tables)
                {
                    try
                    {
                        // Negative Id duplicates
                        using (var cmd = conn.CreateCommand())
                        {
                            cmd.CommandText = $"DELETE FROM \"{t}\" WHERE Id < 0 AND ServerId > 0 AND ServerId IN (SELECT ServerId FROM \"{t}\" WHERE Id > 0 AND ServerId > 0)";
                            cmd.ExecuteNonQuery();
                        }
                        // Positive Id duplicates (different Id, same ServerId) — keep the one where Id=ServerId
                        using (var cmd = conn.CreateCommand())
                        {
                            cmd.CommandText = $"DELETE FROM \"{t}\" WHERE Id > 0 AND ServerId > 0 AND Id != ServerId AND ServerId IN (SELECT Id FROM \"{t}\" WHERE Id > 0 AND Id = ServerId)";
                            cmd.ExecuteNonQuery();
                        }
                    }
                    catch { }
                }
            }
            catch { }
        }

        private static long NextLocalMirrorId(SqliteConnection conn, string table)
        {
            try
            {
                using var cmd = conn.CreateCommand();
                cmd.CommandText = $"SELECT MIN(Id) FROM {table} WHERE Id < 0";
                var v = cmd.ExecuteScalar();
                return (v is long l ? l : 0) - 1;
            }
            catch { return -1; }
        }

        private static double GetJsonDouble(JsonElement el, string prop)
        {
            return el.TryGetProperty(prop, out var v) && v.ValueKind == JsonValueKind.Number ? v.GetDouble() : 0;
        }

        private void CreateTableIfMissing(SqliteConnection conn, string table, string definition)
        {
            using var cmd = conn.CreateCommand();
            cmd.CommandText = $"CREATE TABLE IF NOT EXISTS {table} ({definition})";
            cmd.ExecuteNonQuery();
        }

        private static bool TableExistsForTrigger(SqliteConnection conn, string table)
        {
            using var cmd = conn.CreateCommand();
            cmd.CommandText = $"SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND lower(name)=lower('{table}')";
            return (long)cmd.ExecuteScalar() > 0;
        }

        private void AddColumnIfMissing(SqliteConnection conn, string table, string column, string definition)
        {
            // Table exist nahi karti to skip — fresh schema (DbSchema.sql) mein table
            // add na hone par bhi startup crash na ho (e.g. 'no such table: employees')
            using var tbl = conn.CreateCommand();
            tbl.CommandText = $"SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND lower(name)=lower('{table}')";
            long tableCount = (long)tbl.ExecuteScalar();
            if (tableCount == 0) return;

            using var check = conn.CreateCommand();
            check.CommandText = $"SELECT COUNT(*) FROM pragma_table_info('{table}') WHERE lower(name) = lower('{column}')";
            long count = (long)check.ExecuteScalar();
            if (count > 0) return;

            using var cmd = conn.CreateCommand();
            cmd.CommandText = $"ALTER TABLE {table} ADD COLUMN {column} {definition}";
            cmd.ExecuteNonQuery();
        }

        /// <summary>
        /// Enterprise indexes for high-performance queries on large datasets.
        /// Covers sync operations, POS lookups, report generation.
        /// </summary>
        private void CreateIndexes(SqliteConnection conn)
        {
            string[] indexes = new[]
            {
                // ═══ SYNC PERFORMANCE (most critical - queried every 20 seconds) ═══
                "CREATE INDEX IF NOT EXISTS idx_master1_syncstatus ON Master1(SyncStatus) WHERE SyncStatus != 'Synced'",
                "CREATE INDEX IF NOT EXISTS idx_master1_type_active ON Master1(MasterType, IsActive)",
                "CREATE INDEX IF NOT EXISTS idx_master1_serverid ON Master1(ServerId) WHERE ServerId IS NOT NULL",
                "CREATE INDEX IF NOT EXISTS idx_tran1_syncstatus ON Tran1(SyncStatus) WHERE SyncStatus != 'Synced'",
                "CREATE INDEX IF NOT EXISTS idx_tran1_vchtype ON Tran1(VchType, VchDate)",
                "CREATE INDEX IF NOT EXISTS idx_tran1_vchno ON Tran1(VchNo)",
                "CREATE INDEX IF NOT EXISTS idx_tran1_vchcode ON Tran1(VchCode)",

                // ═══ ITEMS (POS lookups, barcode scanning) ═══
                "CREATE INDEX IF NOT EXISTS idx_items_code ON items(code)",
                "CREATE INDEX IF NOT EXISTS idx_items_barcode ON items(barcode) WHERE barcode IS NOT NULL",
                "CREATE INDEX IF NOT EXISTS idx_items_name ON items(name)",
                "CREATE INDEX IF NOT EXISTS idx_items_syncstatus ON items(SyncStatus) WHERE SyncStatus != 'Synced'",
                "CREATE INDEX IF NOT EXISTS idx_items_delstatus ON items(del_status)",
                "CREATE INDEX IF NOT EXISTS idx_items_category ON items(category_id)",
                "CREATE INDEX IF NOT EXISTS idx_items_brand ON items(brand_id)",
                "CREATE INDEX IF NOT EXISTS idx_items_supplier ON items(supplier_id)",
                "CREATE INDEX IF NOT EXISTS idx_items_parent ON items(parent_id) WHERE parent_id IS NOT NULL",
                "CREATE INDEX IF NOT EXISTS idx_items_enable_disable ON items(enable_disable_status)",

                // ═══ SALES (reporting, sync) ═══
                "CREATE INDEX IF NOT EXISTS idx_sales_syncstatus ON sales(SyncStatus) WHERE SyncStatus != 'Synced'",
                "CREATE INDEX IF NOT EXISTS idx_sales_date ON sales(sale_date)",
                "CREATE INDEX IF NOT EXISTS idx_sales_customer ON sales(customer_id)",
                "CREATE INDEX IF NOT EXISTS idx_sales_invoiceno ON sales(invoice_no)",
                "CREATE INDEX IF NOT EXISTS idx_sales_delstatus ON sales(del_status)",
                "CREATE INDEX IF NOT EXISTS idx_sale_details_saleid ON sale_details(sales_id)",
                "CREATE INDEX IF NOT EXISTS idx_sale_details_itemid ON sale_details(item_id)",
                "CREATE INDEX IF NOT EXISTS idx_sale_payments_saleid ON sale_payments(sale_id)",

                // ═══ CUSTOMERS ═══
                "CREATE INDEX IF NOT EXISTS idx_customers_syncstatus ON customers(SyncStatus) WHERE SyncStatus != 'Synced'",
                "CREATE INDEX IF NOT EXISTS idx_customers_phone ON customers(phone)",
                "CREATE INDEX IF NOT EXISTS idx_customers_name ON customers(name)",
                "CREATE INDEX IF NOT EXISTS idx_customers_delstatus ON customers(del_status)",

                // ═══ PURCHASES ═══
                "CREATE INDEX IF NOT EXISTS idx_purchases_syncstatus ON purchases(SyncStatus) WHERE SyncStatus != 'Synced'",
                "CREATE INDEX IF NOT EXISTS idx_purchases_date ON purchases(date)",
                "CREATE INDEX IF NOT EXISTS idx_purchase_details_purchaseid ON purchase_details(purchase_id)",
                "CREATE INDEX IF NOT EXISTS idx_purchase_details_itemid ON purchase_details(item_id)",

                // ═══ SUPPLIERS ═══
                "CREATE INDEX IF NOT EXISTS idx_suppliers_syncstatus ON suppliers(SyncStatus) WHERE SyncStatus != 'Synced'",
                "CREATE INDEX IF NOT EXISTS idx_suppliers_delstatus ON suppliers(del_status)",

                // ═══ EXPENSES/INCOMES ═══
                "CREATE INDEX IF NOT EXISTS idx_expenses_syncstatus ON expenses(SyncStatus) WHERE SyncStatus != 'Synced'",
                "CREATE INDEX IF NOT EXISTS idx_incomes_syncstatus ON incomes(SyncStatus) WHERE SyncStatus != 'Synced'",

                // ═══ RETURNS ═══
                "CREATE INDEX IF NOT EXISTS idx_sale_returns_syncstatus ON sale_returns(SyncStatus) WHERE SyncStatus != 'Synced'",
                "CREATE INDEX IF NOT EXISTS idx_purchase_returns_syncstatus ON purchase_returns(SyncStatus) WHERE SyncStatus != 'Synced'",

                // ═══ TRANSFERS/DAMAGES/QUOTATIONS ═══
                "CREATE INDEX IF NOT EXISTS idx_transfers_syncstatus ON transfers(SyncStatus) WHERE SyncStatus != 'Synced'",
                "CREATE INDEX IF NOT EXISTS idx_damages_syncstatus ON damages(SyncStatus) WHERE SyncStatus != 'Synced'",
                "CREATE INDEX IF NOT EXISTS idx_quotations_syncstatus ON quotations(SyncStatus) WHERE SyncStatus != 'Synced'",

                // ═══ PENDING SYNC QUEUE ═══
                "CREATE INDEX IF NOT EXISTS idx_pending_sync_retry ON pending_sync(retry_count) WHERE retry_count < 100",
                "CREATE INDEX IF NOT EXISTS idx_pending_sync_entity ON pending_sync(entity_type, entity_id)",

                // ═══ EMPLOYEES / HR ═══
                "CREATE INDEX IF NOT EXISTS idx_employees_email ON employees(email)",
                "CREATE INDEX IF NOT EXISTS idx_employees_outlet ON employees(outlet_id)",
                "CREATE INDEX IF NOT EXISTS idx_attendances_syncstatus ON attendances(SyncStatus) WHERE SyncStatus != 'Synced'",

                // ═══ REGISTERS ═══
                "CREATE INDEX IF NOT EXISTS idx_registers_status ON registers(register_status)",
                "CREATE INDEX IF NOT EXISTS idx_registers_user ON registers(user_id)",

                // ═══ APP SETTINGS (frequent lookups) ═══
                "CREATE INDEX IF NOT EXISTS idx_appsettings_key ON AppSettings(Key)",

                // ═══ PERFORMANCE: NOCASE indexes for POS item/customer search ═══
                // Ye existing databases ke liye bhi kaam karega (schema file se nahi milte purane DBs mein)
                "CREATE INDEX IF NOT EXISTS idx_items_name_nocase ON items(name COLLATE NOCASE)",
                "CREATE INDEX IF NOT EXISTS idx_items_code_nocase ON items(code COLLATE NOCASE)",
                "CREATE INDEX IF NOT EXISTS idx_items_search_compound ON items(del_status, parent_id, name COLLATE NOCASE)",
                "CREATE INDEX IF NOT EXISTS idx_customers_phone_nocase ON customers(phone COLLATE NOCASE, del_status)",
                "CREATE INDEX IF NOT EXISTS idx_customer_wallets_cid ON customer_wallets(customer_id)",
            };

            foreach (var sql in indexes)
            {
                try
                {
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = sql;
                    cmd.ExecuteNonQuery();
                }
                catch { /* Table may not exist yet — skip silently */ }
            }

            LogService.Info($"Enterprise indexes verified ({indexes.Length} indexes)");
        }

        private void CreateDefaultAdmin(SqliteConnection conn)
        {
            using var check = conn.CreateCommand();
            check.CommandText = "SELECT COUNT(*) FROM Users WHERE Username = 'admin'";
            long count = (long)check.ExecuteScalar();
            if (count > 0) return;

            string hash = BCrypt.Net.BCrypt.HashPassword("admin123");
            using var cmd = conn.CreateCommand();
            cmd.CommandText = @"INSERT INTO Users (Username, PasswordHash, FullName, Role)
                               VALUES (@u, @p, @f, @r)";
            cmd.Parameters.AddWithValue("@u", "admin");
            cmd.Parameters.AddWithValue("@p", hash);
            cmd.Parameters.AddWithValue("@f", "Administrator");
            cmd.Parameters.AddWithValue("@r", "Admin");
            cmd.ExecuteNonQuery();
        }
    }
}
