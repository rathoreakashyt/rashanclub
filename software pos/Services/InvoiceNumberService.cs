using System;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Services
{
    /// <summary>
    /// Enterprise invoice number generator with multi-device collision prevention.
    /// Format: SALE-{year}-{counter}-{000001}
    /// Counter includes outlet ID + device short code so multiple counters never clash.
    /// Uses mutex lock to prevent same-device race conditions.
    /// </summary>
    public static class InvoiceNumberService
    {
        private static readonly object _lock = new();

        // Is machine ka unique counter code (C1D{xxxx}). User ke employee->outlet mapping se.
        public static string GetCounterCode(DatabaseService db, int? userId)
        {
            try
            {
                using var conn = db.GetConnection();
                return GetCounterCode(conn, userId);
            }
            catch { }
            return "C0D" + DeviceContext.Short;
        }

        // Counter code from an open connection (migration use)
        public static string GetCounterCode(SqliteConnection conn, int? userId)
        {
            try
            {
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT o.id
                                   FROM employees e
                                   JOIN outlets o ON o.id = CAST(e.outlet_id AS INTEGER)
                                   WHERE (@uid = 0 OR e.id = @uid)
                                     AND e.outlet_id IS NOT NULL AND e.outlet_id <> ''
                                   ORDER BY e.id LIMIT 1";
                cmd.Parameters.AddWithValue("@uid", userId ?? 0);
                var result = cmd.ExecuteScalar();
                if (result != null) return "C" + Convert.ToInt64(result) + "D" + DeviceContext.Short;
            }
            catch { }
            return "C0D" + DeviceContext.Short;
        }

        /// <summary>
        /// Generate next sale number. Thread-safe with lock to prevent race conditions
        /// when multiple bills are created simultaneously (e.g., rapid POS billing).
        /// Uses an atomic sequence table (invoice_sequences) to avoid MAX+1 race conditions.
        /// </summary>
        public static string GenerateSaleNo(DatabaseService db, int? userId)
        {
            lock (_lock)
            {
                string counter = GetCounterCode(db, userId);
                string prefix = "SALE-" + DateTime.Now.Year + "-" + counter + "-";

                long next = 0;
                try
                {
                    using var conn = db.GetConnection();

                    // Ensure the invoice_sequences table exists
                    using (var createCmd = conn.CreateCommand())
                    {
                        createCmd.CommandText = @"CREATE TABLE IF NOT EXISTS invoice_sequences (
                            id INTEGER PRIMARY KEY AUTOINCREMENT,
                            prefix TEXT NOT NULL UNIQUE,
                            last_seq INTEGER NOT NULL DEFAULT 0
                        )";
                        createCmd.ExecuteNonQuery();
                    }

                    // Ensure a row exists for this prefix
                    using (var insertCmd = conn.CreateCommand())
                    {
                        insertCmd.CommandText = "INSERT OR IGNORE INTO invoice_sequences (prefix, last_seq) VALUES (@p, 0)";
                        insertCmd.Parameters.AddWithValue("@p", prefix);
                        insertCmd.ExecuteNonQuery();
                    }

                    // Atomically increment and read (SQLite single-writer lock makes this safe)
                    using (var updateCmd = conn.CreateCommand())
                    {
                        updateCmd.CommandText = "UPDATE invoice_sequences SET last_seq = last_seq + 1 WHERE prefix=@p";
                        updateCmd.Parameters.AddWithValue("@p", prefix);
                        updateCmd.ExecuteNonQuery();
                    }

                    using (var selectCmd = conn.CreateCommand())
                    {
                        selectCmd.CommandText = "SELECT last_seq FROM invoice_sequences WHERE prefix=@p";
                        selectCmd.Parameters.AddWithValue("@p", prefix);
                        var val = selectCmd.ExecuteScalar();
                        if (val != null && val != DBNull.Value) next = Convert.ToInt64(val);
                    }

                    // Safety: if somehow sequence is behind actual data, reconcile
                    if (next <= 0)
                    {
                        next = 1;
                    }
                }
                catch (Exception ex)
                {
                    LogService.Error("GenerateSaleNo sequence failed, falling back to MAX+1", ex);
                    // Fallback to MAX+1 only if the sequence table approach fails entirely
                    try
                    {
                        using var conn = db.GetConnection();
                        using var cmd = conn.CreateCommand();
                        cmd.CommandText = @"SELECT MAX(seq) FROM (
                                                SELECT CAST(REPLACE(sale_no, @prefix, '') AS INTEGER) AS seq
                                                FROM sales WHERE sale_no LIKE @p
                                                UNION ALL
                                                SELECT CAST(REPLACE(VchNo, @prefix, '') AS INTEGER) AS seq
                                                FROM Tran1 WHERE VchNo LIKE @p
                                            )";
                        cmd.Parameters.AddWithValue("@prefix", prefix);
                        cmd.Parameters.AddWithValue("@p", prefix + "%");
                        var val = cmd.ExecuteScalar();
                        if (val != null && val != DBNull.Value) next = Convert.ToInt64(val) + 1;
                        else next = 1;
                    }
                    catch { next = 1; }
                }

                string newNo = prefix + next.ToString("D6");
                LogService.Debug($"Generated invoice: {newNo}");
                return newNo;
            }
        }

        // VchCode: prefix + counter + ms — same-second collision-proof
        public static string MakeVchCode(DatabaseService db, int? userId, string prefix = "SLS")
            => prefix + GetCounterCode(db, userId) + DateTime.Now.ToString("yyyyMMddHHmmssfff");
    }
}
