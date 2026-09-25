using System;
using System.IO;
using System.Text.Json;
using System.Windows.Threading;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Services
{
    public class SyncStatusInfo
    {
        public bool Syncing { get; set; }
        public bool Connected { get; set; }
        public string Message { get; set; } = "";
        public DateTime? LastSyncAt { get; set; }
        public int Pushed { get; set; }
        public int Pulled { get; set; }
        public int DeadLetters { get; set; }
    }

    /// <summary>
    /// Two-way sync engine (offline-first):
    ///  - Push: local sales/items/customers marked pending -> server
    ///  - Pull: server changes (items, stock, customers, suppliers, sales) -> local SQLite
    ///  - Auto-sync on a timer + manual Resync trigger
    /// </summary>
    public class SyncService
    {
        private readonly ApiService _api;
        private readonly DatabaseService _db = new();
        private readonly DispatcherTimer _timer;
        private bool _syncing;

        // ═══ OVERLAP PREVENTION ═══
        // Sync kabhi ek saath 2 baar nahi chalti: busy par aayi request drop nahi
        // hoti — flag set hota hai aur current cycle khatam hote hi ek aur cycle
        // chalti hai (delete/edit TriggerSync request kabhi lose nahi hoti).
        private bool _syncRequestedWhileRunning;

        private DateTime _lastPullAt = DateTime.MinValue;
        private bool _lastSyncFailed;
        private int _failStreak;
        private bool _pullUpsertFailed;
        private readonly HashSet<string> _knownColumns = new();

        // ═══ BUSY STOCK POLLING ═══
        private readonly System.Timers.Timer _busyStockTimer;
        private bool _busyStockPolling;
        private DateTime _lastBusyStockSync = DateTime.MinValue;
        private const int BusyStockPollIntervalMs = 30 * 1000; // 30 seconds (live)

        public event Action<SyncStatusInfo>? StatusChanged;

        // ═══ TIMER OBSERVABILITY (Sync Status popup ke liye) ═══
        // DispatcherTimer next-tick time expose nahi karta — hum khud track
        // karte hain: har tick / interval-change par NextTickAt update.
        public bool IsTimerRunning => _timer.IsEnabled;
        public TimeSpan TimerInterval => _timer.Interval;
        public DateTime NextTickAt { get; private set; }
        public DateTime? SyncingSince { get; private set; }
        // Clash guard: pichla sync abhi chal raha ho to naya tick skip (DB contention fix)
        private int _syncInFlight;

        public SyncService(ApiService api)
        {
            _api = api;
            _timer = new DispatcherTimer { Interval = TimeSpan.FromSeconds(10) };
            _timer.Tick += async (_, _) =>
            {
                NextTickAt = DateTime.Now + _timer.Interval;
                // Pichla sync abhi bhi chal raha hai (slow network/bada payload) —
                // naya tick skip karo, warna dono ek hi DB pe likhenge (clash)
                if (Interlocked.CompareExchange(ref _syncInFlight, 1, 0) != 0)
                {
                    LogService.Info("Sync tick skipped — previous cycle still running");
                    return;
                }
                try
                {
                    await SyncNowAsync(silent: true);
                }
                finally
                {
                    Interlocked.Exchange(ref _syncInFlight, 0);
                }
            };

            // Busy stock polling timer — background thread (System.Timers.Timer)
            _busyStockTimer = new System.Timers.Timer(BusyStockPollIntervalMs);
            _busyStockTimer.Elapsed += async (_, _) => await PollBusyStockAsync();
            _busyStockTimer.AutoReset = true;
        }

        public SyncStatusInfo LastStatus { get; private set; } = new();

        public void StartAutoSync()
        {
            if (_api.Token != null)
            {
                bool firstRun = string.IsNullOrEmpty(_api.GetSetting("last_sync_at"));
                if (!firstRun)
                {
                    _lastPullAt = DateTime.Now;
                }
                _ = SyncNowAsync(silent: true, forcePull: true);

                // ── Busy: startup par server ko instant stock sync trigger karo
                // aur 5 sec baad delta pull karo (server sync complete ho jaye)
                _ = Task.Run(async () =>
                {
                    try
                    {
                        // Server pe busy:sync-stock command chalao (background)
                        await _api.PostDesktopReportAsync("/api/desktop/busy-import/sync-stock", new { });
                        LogService.Info("Busy startup stock sync triggered");

                        // 8 sec wait — server sync complete ho jata hai usually
                        await Task.Delay(8000);
                        await PollBusyStockAsync();
                    }
                    catch (Exception ex)
                    {
                        LogService.Warn($"Busy startup sync: {ex.Message}");
                    }
                });
            }

            _timer.Start();
            NextTickAt = DateTime.Now + _timer.Interval;

            // Busy stock polling — har 2 minute (background thread)
            _busyStockTimer.Start();
        }

        public void StopAutoSync()
        {
            _timer.Stop();
            _busyStockTimer.Stop();
        }

        public async Task SyncNowAsync(bool silent = false, bool forcePull = false)
        {
            // Overlap prevention: ek cycle chal raha hai to request ko queue karo
            // (drop nahi). Current cycle khatam hote hi ek aur cycle chalti hai.
            if (_syncing)
            {
                _syncRequestedWhileRunning = true;
                LogService.Info("Sync overlap prevented — request queued for next cycle");
                return;
            }

            if (_api.Token == null)
            {
                UpdateStatus(new SyncStatusInfo { Connected = false, Message = "Not configured — open Settings → Server Sync" });
                return;
            }

            // Self-heal: any employee created locally that never reached the cloud
            // (older builds, offline periods) gets enqueued so it pushes this cycle.
            SeedPendingEmployees();

            // No network when there is nothing to do: only push pending local data,
            // and only pull on first run / manual resync / every 10 minutes.
            bool hasPending = HasPendingLocal();
            bool pullDue = forcePull
                || string.IsNullOrEmpty(_api.GetSetting("last_sync_at"))
                || _lastSyncFailed
                || (DateTime.Now - _lastPullAt) > TimeSpan.FromSeconds(10);
            if (!hasPending && !pullDue)
            {
                if (!silent || LastStatus.Message == "")
                {
                    UpdateStatus(new SyncStatusInfo { Connected = true, Message = "Up to date", DeadLetters = CountDeadLetters() });
                }
                return;
            }

            // ═══ SYNC HISTORY: start-time track (overlap-safe — _syncing guard upar) ═══
            _syncing = true;
            SyncingSince = DateTime.Now;
            var stopwatch = System.Diagnostics.Stopwatch.StartNew();
            string startTime = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
            string historyStatus = "Success";
            string? historyError = null;
            var status = new SyncStatusInfo { Syncing = true, DeadLetters = LastStatus.DeadLetters };
            UpdateStatus(status);

            try
            {
                int pushed = hasPending ? await PushPendingAsync() : 0;
                // Also process offline pending_sync queue
                int queuePushed = hasPending ? await ProcessPendingQueueAsync() : 0;
                int pulled = pullDue ? await PullAsync() : 0;
                _lastPullAt = DateTime.Now;
                _lastSyncFailed = false;

                // ═══ LIVE UPDATE: cloud se kuch bhi naya aaya (stock/items/sales) →
                // jo pages abhi open hain (Stock/Inventory/ItemMaster/Dashboard)
                // unhe turant batao taaki woh live refresh ho jaayen. Handlers khud
                // UI thread pe marshal karte hain, isliye yahan safe hai.
                if (pulled > 0)
                {
                    try { StockEvents.NotifyStockChanged(); }
                    catch (Exception ex) { LogService.Error("Live stock notify failed", ex); }
                }

                // ═══ FEATURE ACTIVATION: Sync feature flags from cloud ═══
                if (pullDue && _api.Token != null)
                {
                    await FeatureActivationService.SyncFromCloud(_api.BaseUrl, _api.Token);
                }

                // ═══ RETRY BACKOFF (E3): success pe reset → 10s normal ═══
                if (_failStreak > 0)
                {
                    _failStreak = 0;
                    _timer.Interval = TimeSpan.FromSeconds(10);
                }

                status.Pushed = pushed + queuePushed;
                status.Pulled = pulled;
                status.Syncing = false;
                status.Connected = true;
                status.LastSyncAt = DateTime.Now;
                status.Message = pullDue
                    ? $"Synced {pushed + queuePushed} pushed / {pulled} pulled"
                    : $"Pushed {pushed + queuePushed} record(s)";
                status.DeadLetters = CountDeadLetters();
                UpdateStatus(status);

                if (pushed + queuePushed + pulled > 0)
                    LogService.Info($"Sync complete: {pushed + queuePushed} pushed, {pulled} pulled");
            }
            catch (Exception ex)
            {
                _lastSyncFailed = true;
                historyStatus = "Failed";
                historyError = ex.Message;

                // ═══ RETRY BACKOFF (E3): exponential 10s → 30s → 60s → 2m → 5m (max) ═══
                _failStreak++;
                int backoffSec = (int)Math.Min(300, 10 * Math.Pow(2, Math.Min(_failStreak, 5)));
                _timer.Interval = TimeSpan.FromSeconds(backoffSec);

                LogService.Error("Sync cycle failed", ex);
                // Clean error for UI — strip HTML, shorten
                string errMsg = ex.Message;
                if (errMsg.Contains("<html") || errMsg.Contains("<HTML"))
                    errMsg = System.Text.RegularExpressions.Regex.Replace(errMsg, "<[^>]+>", "").Trim();
                if (errMsg.Contains("502") || errMsg.Contains("Bad Gateway"))
                    errMsg = "Server unavailable (502) — retrying...";
                else if (errMsg.Contains("503"))
                    errMsg = "Server maintenance (503) — retrying...";
                else if (errMsg.Contains("invalid JSON literal", StringComparison.OrdinalIgnoreCase)
                      || errMsg.Contains("incomplete input", StringComparison.OrdinalIgnoreCase))
                    // Truncated response (network drop mid-download) — retry hoga, data safe hai
                    errMsg = "Connection beech me cut gaya — dobara sync try kar rahe hain...";
                else if (errMsg.Length > 80)
                    errMsg = errMsg.Substring(0, 77) + "...";
                UpdateStatus(new SyncStatusInfo { Connected = false, Message = errMsg, DeadLetters = CountDeadLetters() });
            }
            finally
            {
                stopwatch.Stop();
                // ═══ SYNC HISTORY: complete-time + result log (har real cycle) ═══
                try
                {
                    InsertSyncHistory(startTime, DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"),
                        historyStatus, status.Pushed, status.Pulled, historyError);
                }
                catch (Exception ex)
                {
                    LogService.Error("Sync history insert failed", ex);
                }
                _syncing = false;
                SyncingSince = null;
                // Interval change ho sakta hai (backoff reset) — next-tick recalibrate
                NextTickAt = DateTime.Now + _timer.Interval;

                // Overlap queue: sync request aayi thi jab hum chalu the → ab ek aur cycle
                if (_syncRequestedWhileRunning)
                {
                    _syncRequestedWhileRunning = false;
                    _ = SyncNowAsync(silent, forcePull);
                }
            }
        }

        /// <summary>
        /// SYNC HISTORY: har real sync cycle ka record (start, complete, status).
        /// 'Up to date' skip cycles log nahi hote. Dialog isi table se padhta hai.
        /// </summary>
        private void InsertSyncHistory(string startTime, string endTime, string status, int pushed, int pulled, string? error)
        {
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = @"INSERT INTO sync_history (start_time, end_time, status, pushed, pulled, error)
                                VALUES (@s, @e, @st, @p, @pu, @er)";
            cmd.Parameters.AddWithValue("@s", startTime);
            cmd.Parameters.AddWithValue("@e", endTime);
            cmd.Parameters.AddWithValue("@st", status);
            cmd.Parameters.AddWithValue("@p", pushed);
            cmd.Parameters.AddWithValue("@pu", pulled);
            cmd.Parameters.AddWithValue("@er", (object?)error ?? DBNull.Value);
            cmd.ExecuteNonQuery();
        }

        public sealed class SyncHistoryRow
        {
            public long Id { get; set; }
            public string StartTime { get; set; } = "";
            public string EndTime { get; set; } = "";
            public string Duration { get; set; } = "";
            public int Pushed { get; set; }
            public int Pulled { get; set; }
            public string Status { get; set; } = "";
            public System.Windows.Media.Brush StatusBrush { get; set; } = System.Windows.Media.Brushes.Green;
            public string Error { get; set; } = "";
        }

        /// <summary>
        /// Dialog ke liye — last N sync cycles (newest first).
        /// </summary>
        public List<SyncHistoryRow> GetRecentSyncHistory(int limit = 50)
        {
            var rows = new List<SyncHistoryRow>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT id, start_time, end_time, status, pushed, pulled, error
                                    FROM sync_history ORDER BY id DESC LIMIT @n";
                cmd.Parameters.AddWithValue("@n", limit);
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    string start = r.IsDBNull(1) ? "" : r.GetString(1);
                    string end = r.IsDBNull(2) ? "" : r.GetString(2);
                    string st = r.IsDBNull(3) ? "Success" : r.GetString(3);
                    TimeSpan dur = TimeSpan.Zero;
                    if (DateTime.TryParse(start, out var s) && DateTime.TryParse(end, out var e))
                        dur = e - s;
                    rows.Add(new SyncHistoryRow
                    {
                        Id = r.GetInt64(0),
                        StartTime = start,
                        EndTime = end,
                        Duration = dur.TotalSeconds < 60 ? $"{dur.TotalSeconds:0.0}s" : $"{dur.TotalMinutes:0.0}m",
                        Pushed = r.IsDBNull(4) ? 0 : r.GetInt32(4),
                        Pulled = r.IsDBNull(5) ? 0 : r.GetInt32(5),
                        Status = st,
                        StatusBrush = st == "Failed"
                            ? System.Windows.Media.Brushes.Red
                            : System.Windows.Media.Brushes.Green,
                        Error = r.IsDBNull(6) ? "" : r.GetString(6),
                    });
                }
            }
            catch (Exception ex)
            {
                LogService.Error("GetRecentSyncHistory failed", ex);
            }
            return rows;
        }

        public bool HasPendingLocalData => HasPendingLocal();

        private bool HasPendingLocal()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT
                    (SELECT COUNT(*) FROM Master1 WHERE MasterType='Item' AND IsActive=1 AND Name<>'' AND (SyncStatus IS NULL OR SyncStatus != 'Synced'))
                  + (SELECT COUNT(*) FROM Master1 WHERE MasterType='Party' AND PartyType='Customer' AND IsActive=1 AND (SyncStatus IS NULL OR SyncStatus != 'Synced'))
                  + (SELECT COUNT(*) FROM Tran1 WHERE VchType='Sales' AND SyncPayload IS NOT NULL AND SyncPayload != '' AND (SyncStatus IS NULL OR SyncStatus != 'Synced'))
                  + (SELECT COUNT(*) FROM sale_returns WHERE SyncPayload IS NOT NULL AND SyncPayload != '' AND (SyncStatus IS NULL OR SyncStatus != 'Synced'))
                  + (SELECT COUNT(*) FROM Units WHERE SyncStatus IS NULL OR SyncStatus != 'Synced')
                  + (SELECT COUNT(*) FROM Brands WHERE SyncStatus IS NULL OR SyncStatus != 'Synced')
                  + (SELECT COUNT(*) FROM ItemCategories WHERE SyncStatus IS NULL OR SyncStatus != 'Synced')
                  + (SELECT COUNT(*) FROM Racks WHERE SyncStatus IS NULL OR SyncStatus != 'Synced')
                  + (SELECT COUNT(*) FROM Variations WHERE SyncStatus IS NULL OR SyncStatus != 'Synced')
                  + (SELECT COUNT(*) FROM ExpenseCategories WHERE SyncStatus IS NULL OR SyncStatus != 'Synced')
                  + (SELECT COUNT(*) FROM purchases WHERE SyncStatus IS NULL OR SyncStatus != 'Synced')
                  + (SELECT COUNT(*) FROM purchase_returns WHERE SyncStatus IS NULL OR SyncStatus != 'Synced')
                  + (SELECT COUNT(*) FROM supplier_payments WHERE SyncStatus IS NULL OR SyncStatus != 'Synced')
                  + (SELECT COUNT(*) FROM expenses WHERE SyncStatus IS NULL OR SyncStatus != 'Synced')
                  + (SELECT COUNT(*) FROM sales WHERE (SyncStatus IS NULL OR SyncStatus = 'Local') AND del_status='Live')
                  + (SELECT COUNT(*) FROM customers WHERE (SyncStatus IS NULL OR SyncStatus = 'Local') AND del_status='Live')
                  + (SELECT COUNT(*) FROM Master1 WHERE MasterType='Party' AND PartyType='Supplier' AND IsActive=1 AND (SyncStatus IS NULL OR SyncStatus != 'Synced'))
                  + (SELECT COUNT(*) FROM items WHERE (SyncStatus IS NULL OR SyncStatus = 'Local') AND del_status='Live')
                  + (SELECT COUNT(*) FROM outlets WHERE del_status='Live' AND (SyncStatus IS NULL OR SyncStatus != 'Synced' OR ServerId IS NULL OR ServerId = 0))
                  + (SELECT COUNT(*) FROM customer_receives WHERE (SyncStatus IS NULL OR SyncStatus = 'Local') AND del_status='Live')
                  + (SELECT COUNT(*) FROM damages WHERE (SyncStatus IS NULL OR SyncStatus = 'Local') AND del_status='Live')
                  + (SELECT COUNT(*) FROM transfers WHERE (SyncStatus IS NULL OR SyncStatus = 'Local') AND del_status='Live')
                  + (SELECT COUNT(*) FROM quotations WHERE (SyncStatus IS NULL OR SyncStatus = 'Local') AND del_status='Live')
                  + (SELECT COUNT(*) FROM incomes WHERE (SyncStatus IS NULL OR SyncStatus = 'Local') AND del_status='Live')
                  + (SELECT COUNT(*) FROM deposit_withdraws WHERE (SyncStatus IS NULL OR SyncStatus = 'Local') AND del_status='Live')
                  + (SELECT COUNT(*) FROM servicings WHERE (SyncStatus IS NULL OR SyncStatus = 'Local') AND del_status='Live')
                  + (SELECT COUNT(*) FROM warranties WHERE (SyncStatus IS NULL OR SyncStatus = 'Local') AND del_status='Live')
                  + (SELECT COUNT(*) FROM bookings WHERE (SyncStatus IS NULL OR SyncStatus = 'Local') AND del_status='Live')
                  + (SELECT COUNT(*) FROM installment_sales WHERE (SyncStatus IS NULL OR SyncStatus = 'Local') AND del_status='Live')
                  + (SELECT COUNT(*) FROM attendances WHERE (SyncStatus IS NULL OR SyncStatus = 'Local') AND del_status='Live')
                  + (SELECT COUNT(*) FROM employee_advance_payments WHERE (SyncStatus IS NULL OR SyncStatus = 'Local') AND del_status='Live')
                  + (SELECT COUNT(*) FROM promotions WHERE (SyncStatus IS NULL OR SyncStatus = 'Local') AND del_status='Live')
                  + (SELECT COUNT(*) FROM wallet_transactions WHERE (SyncStatus IS NULL OR SyncStatus = 'Local') AND del_status='Live')
                  + (SELECT COUNT(*) FROM business_club_transactions WHERE (SyncStatus IS NULL OR SyncStatus = 'Local') AND del_status='Live')
                  + (SELECT IFNULL((SELECT COUNT(*) FROM pending_sync), 0))";
                return ((long)cmd.ExecuteScalar()) > 0;
            }
            catch { return false; }
        }

        private void UpdateStatus(SyncStatusInfo s)
        {
            LastStatus = s;
            StatusChanged?.Invoke(s);
        }

        /// <summary>
        /// FIX 4: Local sync-records count — UI badge ke liye.
        /// pending_sync (retry_count < 100) + dead_letters (resolved=0) dono
        /// count karte hain taaki user ko dikh sake kitne records atke hain.
        /// </summary>
        private int CountDeadLetters()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT
                    (SELECT IFNULL((SELECT COUNT(*) FROM pending_sync WHERE retry_count < 100), 0))
                    +
                    (SELECT IFNULL((SELECT COUNT(*) FROM dead_letters WHERE resolved = 0), 0))";
                return Convert.ToInt32(cmd.ExecuteScalar());
            }
            catch { return 0; }
        }

        // ═══════════════════ PUSH ═══════════════════

        private async Task<int> PushPendingAsync()
        {
            var items = GetPendingItems();
            var newItems = GetPendingNewItems();
            items.AddRange(newItems);
            var customers = GetPendingCustomers();
            var suppliers = GetPendingSuppliers();
            var sales = GetPendingSales();
            var configs = GetPendingConfigs();
            var purchases = GetPendingPurchases();
            var purchaseReturns = GetPendingPurchaseReturns();
            var supplierPayments = GetPendingSupplierPayments();
            var expenses = GetPendingExpenses();
            var saleReturns = GetPendingSaleReturns();
            var walletTransactions = GetPendingWalletTransactions();
            var bcTransactions = GetPendingBusinessClubTransactions();
            var loyaltyChanges = GetPendingLoyaltyChanges();
            var outlets = GetPendingOutlets();
            var salaries = GetPendingSalaries();
            var attendances = GetPendingAttendances();
            var promotions = GetPendingPromotions();
            var bookings = GetPendingBookings();
            var servicings = GetPendingServicings();
            var warranties = GetPendingWarranties();
            var depositWithdraws = GetPendingDepositWithdraws();
            var installmentSales = GetPendingInstallmentSales();
            var quotations = GetPendingQuotations();
            var incomes = GetPendingIncomes();
            var customerReceives = GetPendingCustomerReceives();

            if (items.Count == 0 && customers.Count == 0 && suppliers.Count == 0 && sales.Count == 0 && !HasPendingConfigs(configs)
                && purchases.Count == 0 && purchaseReturns.Count == 0 && supplierPayments.Count == 0 && expenses.Count == 0
                && saleReturns.Count == 0 && walletTransactions.Count == 0 && bcTransactions.Count == 0 && loyaltyChanges.Count == 0 && outlets.Count == 0
                && salaries.Count == 0 && attendances.Count == 0 && promotions.Count == 0 && bookings.Count == 0
                && servicings.Count == 0 && warranties.Count == 0 && depositWithdraws.Count == 0 && installmentSales.Count == 0
                && quotations.Count == 0 && incomes.Count == 0 && customerReceives.Count == 0)
                return 0;

            // ═══ SANITIZE all data before sending ═══
            SanitizeDictList(items);
            SanitizeDictList(customers);
            SanitizeDictList(suppliers);
            SanitizeDictList(saleReturns);
            SanitizeDictList(purchases);
            SanitizeDictList(purchaseReturns);
            SanitizeDictList(supplierPayments);
            SanitizeDictList(expenses);
            SanitizeDictList(walletTransactions);
            SanitizeDictList(bcTransactions);
            SanitizeDictList(loyaltyChanges);
            SanitizeDictList(outlets);
            SanitizeDictList(salaries);
            SanitizeDictList(attendances);
            SanitizeDictList(promotions);
            SanitizeDictList(bookings);
            SanitizeDictList(servicings);
            SanitizeDictList(warranties);
            SanitizeDictList(depositWithdraws);
            SanitizeDictList(installmentSales);
            SanitizeDictList(quotations);
            SanitizeDictList(incomes);
            SanitizeDictList(customerReceives);
            SanitizeSalesPayload(sales);

            var payload = new Dictionary<string, object>
            {
                ["items"] = items,
                ["customers"] = customers,
                ["suppliers"] = suppliers,
                ["sales"] = sales,
                ["configs"] = configs,
                ["purchases"] = purchases,
                ["purchase_returns"] = purchaseReturns,
                ["supplier_payments"] = supplierPayments,
                ["expenses"] = expenses,
                ["sale_returns"] = saleReturns,
                ["wallet_transactions"] = walletTransactions,
                ["business_club_transactions"] = bcTransactions,
                ["loyalty_changes"] = loyaltyChanges,
                ["outlets"] = outlets,
                ["salaries"] = salaries,
                ["attendances"] = attendances,
                ["promotions"] = promotions,
                ["bookings"] = bookings,
                ["servicings"] = servicings,
                ["warranties"] = warranties,
                ["deposit_withdraws"] = depositWithdraws,
                ["installment_sales"] = installmentSales,
                ["quotations"] = quotations,
                ["incomes"] = incomes,
                ["customer_receives"] = customerReceives
            };

            var (ok, message, data) = await _api.PushAsync(payload);
            if (!ok || data == null)
            {
                // Network/offline failure → fallback mat karo (24 sequential requests
                // aur retries offline mein useless hain); backoff timer retry karega.
                if (!IsServerSideError(message))
                {
                    LogService.Warn($"Push failed (non-fatal): {message}");
                    Log("push", "all", "", "error", message);
                    return 0;
                }

                // ═══ FIX 3: SPLIT PUSH FALLBACK — main batch fail (ek entity ki
                // validation error poore transaction ko rollback kar deti hai) to
                // entity-by-entity push karo. Jo entities sahi hain wo isi cycle
                // mein sync ho jayein; sirf wahi atakti hai jo actually kharab hai.
                LogService.Warn($"Batch push failed ({message}) — falling back to per-entity split push");
                Log("push", "all", "", "error", message);
                return await SplitPushFallbackAsync(payload);
            }

            int pushed = ProcessPushResults(data.RootElement);
            Log("push", "all", "", "ok", $"Pushed {pushed} records");
            return pushed;
        }

        /// <summary>
        /// FIX 3: Push response ke results ko process karke rows ko 'Synced' mark
        /// karo. Har entity ka result shape main /push ke jaisa hi hai, isliye ye
        /// method batch push aur split push (push/batch) dono ke liye kaam karta hai.
        /// </summary>
        private int ProcessPushResults(JsonElement root)
        {
            int pushed = 0;

            try
            {
                // Items
                if (root.TryGetProperty("items", out var itemResults))
                {
                    foreach (var r in itemResults.EnumerateArray())
                    {
                        var local = r.GetProperty("local_code").GetString();
                        var serverId = r.TryGetProperty("server_id", out var sid) && sid.ValueKind == JsonValueKind.Number ? sid.GetInt64() : 0;
                        MarkMasterSynced(local, serverId, "Item");
                        MarkNewItemSynced(local, serverId);
                        pushed++;
                    }
                }

                // Customers
                if (root.TryGetProperty("customers", out var custResults))
                {
                    foreach (var r in custResults.EnumerateArray())
                    {
                        var local = r.GetProperty("local_code").GetString();
                        var serverId = r.TryGetProperty("server_id", out var sid) && sid.ValueKind == JsonValueKind.Number ? sid.GetInt64() : 0;
                        MarkMasterSynced(local, serverId, "Party");
                        MarkPartyMirrorSynced("customers", local, serverId);
                        pushed++;
                    }
                }

                // Suppliers
                if (root.TryGetProperty("suppliers", out var supResults))
                {
                    foreach (var r in supResults.EnumerateArray())
                    {
                        var local = r.GetProperty("local_code").GetString();
                        var serverId = r.TryGetProperty("server_id", out var sid) && sid.ValueKind == JsonValueKind.Number ? sid.GetInt64() : 0;
                        MarkMasterSynced(local, serverId, "Party");
                        MarkPartyMirrorSynced("suppliers", local, serverId);
                        pushed++;
                    }
                }

                // Sales
                if (root.TryGetProperty("sales", out var saleResults))
                {
                    foreach (var r in saleResults.EnumerateArray())
                    {
                        var local = r.GetProperty("local_id").GetString();
                        var serverId = r.TryGetProperty("server_id", out var sid) && sid.ValueKind == JsonValueKind.Number ? sid.GetInt64() : 0;
                        var hasError = r.TryGetProperty("error", out _);
                        MarkSaleSynced(local, serverId, hasError ? "PushError" : "Synced");
                        pushed++;
                    }
                }

                // Sale returns (F6)
                if (root.TryGetProperty("sale_returns", out var srResults))
                {
                    foreach (var r in srResults.EnumerateArray())
                    {
                        long local = 0;
                        if (r.TryGetProperty("local_id", out var li) && li.ValueKind == JsonValueKind.Number)
                            local = li.GetInt64();
                        var serverId = r.TryGetProperty("server_id", out var sid) && sid.ValueKind == JsonValueKind.Number ? sid.GetInt64() : 0;
                        var hasError = r.TryGetProperty("error", out _);
                        MarkSaleReturnSynced(local, serverId, hasError ? "PushError" : "Synced");
                        pushed++;
                    }
                }

                // Configs
                if (root.TryGetProperty("configs", out var configResults))
                {
                    pushed += ApplyConfigResults(configResults);
                }

                // Outlets (master records created/edited on the desktop)
                if (root.TryGetProperty("outlets", out var outletResults))
                {
                    foreach (var r in outletResults.EnumerateArray())
                    {
                        string? code = null;
                        if (r.TryGetProperty("local_code", out var lc) && lc.ValueKind == JsonValueKind.String)
                            code = lc.GetString();
                        if (code == null && r.TryGetProperty("outlet_code", out var oc) && oc.ValueKind == JsonValueKind.String)
                            code = oc.GetString();
                        if (string.IsNullOrEmpty(code)) continue;
                        var serverId = r.TryGetProperty("server_id", out var sid) && sid.ValueKind == JsonValueKind.Number ? sid.GetInt64() : 0;
                        var hasError = r.TryGetProperty("error", out _);
                        MarkOutletSynced(code, serverId, hasError ? "PushError" : "Synced");
                        pushed++;
                    }
                }

                // Purchases
                if (root.TryGetProperty("purchases", out var purchaseResults))
                {
                    foreach (var r in purchaseResults.EnumerateArray())
                        pushed += ApplySimpleResult(r, "purchases");
                }

                // Purchase returns
                if (root.TryGetProperty("purchase_returns", out var prResults))
                {
                    foreach (var r in prResults.EnumerateArray())
                        pushed += ApplySimpleResult(r, "purchase_returns");
                }

                // Supplier payments
                if (root.TryGetProperty("supplier_payments", out var spResults))
                {
                    foreach (var r in spResults.EnumerateArray())
                        pushed += ApplySimpleResult(r, "supplier_payments");
                }

                // Expenses
                if (root.TryGetProperty("expenses", out var expResults))
                {
                    foreach (var r in expResults.EnumerateArray())
                        pushed += ApplySimpleResult(r, "expenses");
                }

                // Wallet Transactions
                if (root.TryGetProperty("wallet_transactions", out var wtResults))
                {
                    foreach (var r in wtResults.EnumerateArray())
                    {
                        long localId = 0;
                        if (r.TryGetProperty("local_id", out var li) && li.ValueKind == JsonValueKind.Number)
                            localId = li.GetInt64();
                        long serverId = 0;
                        if (r.TryGetProperty("server_id", out var sid) && sid.ValueKind == JsonValueKind.Number)
                            serverId = sid.GetInt64();
                        if (localId == 0) continue;
                        MarkWalletTransactionSynced(localId, serverId);
                        pushed++;
                    }
                }

                // Business Club Transactions
                if (root.TryGetProperty("business_club_transactions", out var bctResults))
                {
                    foreach (var r in bctResults.EnumerateArray())
                    {
                        long localId = 0;
                        if (r.TryGetProperty("local_id", out var li) && li.ValueKind == JsonValueKind.Number)
                            localId = li.GetInt64();
                        long serverId = 0;
                        if (r.TryGetProperty("server_id", out var sid) && sid.ValueKind == JsonValueKind.Number)
                            serverId = sid.GetInt64();
                        if (localId == 0) continue;
                        MarkBusinessClubTransactionSynced(localId, serverId);
                        pushed++;
                    }
                }

                // Loyalty Changes
                if (root.TryGetProperty("loyalty_changes", out var lcResults))
                {
                    foreach (var r in lcResults.EnumerateArray())
                    {
                        long custId = 0;
                        if (r.TryGetProperty("customer_id", out var ci) && ci.ValueKind == JsonValueKind.Number)
                            custId = ci.GetInt64();
                        if (custId > 0) MarkCustomerLoyaltySynced(custId);
                        pushed++;
                    }
                }

                // 11 new entities - mark synced
                string[] newEntityKeys = { "salaries", "attendances", "promotions", "bookings", "servicings", "warranties", "deposit_withdraws", "installment_sales", "quotations", "incomes", "customer_receives" };
                string[] newEntityTables = { "salaries", "attendances", "promotions", "bookings", "servicings", "warranties", "deposit_withdraws", "installment_sales", "quotations", "incomes", "customer_receives" };
                for (int i = 0; i < newEntityKeys.Length; i++)
                {
                    if (root.TryGetProperty(newEntityKeys[i], out var entityResults))
                    {
                        foreach (var r in entityResults.EnumerateArray())
                        {
                            long localId = 0;
                            if (r.TryGetProperty("local_id", out var li) && li.ValueKind == JsonValueKind.Number)
                                localId = li.GetInt64();
                            long serverId = 0;
                            if (r.TryGetProperty("server_id", out var sid) && sid.ValueKind == JsonValueKind.Number)
                                serverId = sid.GetInt64();
                            if (localId != 0) MarkGenericSynced(newEntityTables[i], localId, serverId);
                            pushed++;
                        }
                    }
                }
            }
            catch (Exception ex)
            {
                LogService.Warn($"Push result processing error (non-fatal): {ex.Message}");
            }

            return pushed;
        }

        /// <summary>
        /// FIX 3: Main batch push fail hone par har entity type alag request mein
        /// push karo (/api/sync/push/batch). Dependency order: pehle master tables
        /// (configs, items, parties), phir transactions. Ek entity fail ho to sirf
        /// wo retry karti hai — baaki isi cycle mein sync ho jati hain.
        /// </summary>
        private async Task<int> SplitPushFallbackAsync(Dictionary<string, object> payload)
        {
            int pushed = 0;

            // Dependency order: configs (units/brands/categories/...) pehle, phir
            // items (categories/brands FK), parties, aur aakhri mein transactions.
            string[] order = {
                "configs", "items", "customers", "suppliers", "outlets",
                "sales", "sale_returns", "purchases", "purchase_returns",
                "supplier_payments", "expenses", "incomes", "customer_receives",
                "wallet_transactions", "business_club_transactions", "loyalty_changes", "salaries", "attendances",
                "promotions", "bookings", "servicings", "warranties",
                "deposit_withdraws", "installment_sales", "quotations"
            };

            foreach (var key in order)
            {
                if (!payload.TryGetValue(key, out var rawValue)) continue;
                object records = rawValue;

                // configs ek nested dict hai (groups) — push/batch ko records[0] = dict chahiye
                if (key == "configs" && rawValue is Dictionary<string, object> configDict)
                {
                    if (configDict.Count == 0) continue;
                    records = new List<object> { configDict };
                }
                if (records is List<object> list && list.Count == 0) continue;
                if (records is Dictionary<string, object> dict && dict.Count == 0) continue;

                try
                {
                    var (ok, message, data) = await _api.PushBatchAsync(key, records);
                    if (ok && data != null)
                    {
                        int entityPushed = ProcessPushResults(data.RootElement);
                        pushed += entityPushed;
                        LogService.Info($"Split push OK: {key} ({entityPushed} records)");
                    }
                    else
                    {
                        // FIX 4: ye entity atak gayi — retry count burn mat karo (network
                        // issue ho sakta hai), sirf log. Server validation errors dead
                        // letter table mein bhi log ho rahi hain (sync_dead_letters).
                        LogService.Warn($"Split push failed for {key}: {message}");
                        Log("push", key, "", "error", message);
                    }
                }
                catch (Exception ex)
                {
                    LogService.Warn($"Split push exception for {key}: {ex.Message}");
                }
            }

            return pushed;
        }

        // ═══════ BULLETPROOF: Sanitize all dictionary list payloads ═══════
        private static void SanitizeDictList(List<object> list)
        {
            foreach (var obj in list)
            {
                if (obj is Dictionary<string, object?> dict)
                    SanitizeDict(dict);
            }
        }

        private static void SanitizeDict(Dictionary<string, object?> dict)
        {
            var keys = dict.Keys.ToList();
            foreach (var key in keys)
            {
                var val = dict[key];
                if (val == null || val == DBNull.Value)
                {
                    dict[key] = null;
                    continue;
                }
                if (val is string s)
                {
                    // Strip null bytes, trim whitespace, limit length
                    s = s.Replace("\0", "").Trim();
                    if (s.Length > 500) s = s[..500];
                    dict[key] = string.IsNullOrEmpty(s) ? null : s;
                }
            }
        }

        private static void SanitizeSalesPayload(List<object> sales)
        {
            foreach (var obj in sales)
            {
                if (obj is not Dictionary<string, object?> sale) continue;
                SanitizeDict(sale);

                // Ensure company_id is valid (not 0)
                if (sale.TryGetValue("company_id", out var cid) && cid is long cId && cId == 0)
                    sale["company_id"] = 1L;

                // Ensure grand_total > 0
                if (sale.TryGetValue("grand_total", out var gt) && gt is double gTotal && gTotal <= 0)
                    sale["grand_total"] = 0.01;

                // Sanitize nested items array
                if (sale.TryGetValue("items", out var itemsObj) && itemsObj is List<object> items)
                {
                    foreach (var itemObj in items)
                    {
                        if (itemObj is Dictionary<string, object?> itemDict)
                        {
                            SanitizeDict(itemDict);
                            // Ensure qty > 0
                            if (itemDict.TryGetValue("qty", out var qtyObj) && qtyObj is double qty && qty <= 0)
                                itemDict["qty"] = 1.0;
                            // Ensure price >= 0
                            if (itemDict.TryGetValue("menu_unit_price", out var priceObj) && priceObj is double price && price < 0)
                                itemDict["menu_unit_price"] = 0.0;
                        }
                    }
                }

                // Sanitize nested payments array
                if (sale.TryGetValue("payments", out var payObj) && payObj is List<object> pays)
                {
                    foreach (var payObjItem in pays)
                    {
                        if (payObjItem is Dictionary<string, object?> payDict)
                        {
                            SanitizeDict(payDict);
                            // Ensure payment_name is not empty
                            if (payDict.TryGetValue("payment_name", out var pn) && pn is string pnStr && string.IsNullOrWhiteSpace(pnStr))
                                payDict["payment_name"] = "Cash";
                        }
                    }
                }
            }
        }

        private int ApplySimpleResult(JsonElement r, string table)
        {
            long localId = 0;
            if (r.TryGetProperty("local_id", out var li) && li.ValueKind == JsonValueKind.Number)
                localId = li.GetInt64();
            long serverId = 0;
            if (r.TryGetProperty("server_id", out var sid) && sid.ValueKind == JsonValueKind.Number)
                serverId = sid.GetInt64();
            if (localId == 0) return 0;

            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = $"UPDATE {table} SET ServerId=@sid, SyncStatus='Synced', SyncError=NULL WHERE Id=@id";
                cmd.Parameters.AddWithValue("@sid", serverId);
                cmd.Parameters.AddWithValue("@id", localId);
                cmd.ExecuteNonQuery();
                return 1;
            }
            catch { return 0; }
        }

        private List<object> GetPendingPurchases()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT Id, reference_no, invoice_no, supplier_id, date, grand_total, paid, due_amount, note, discount, status
                                    FROM purchases
                                    WHERE SyncStatus IS NULL OR SyncStatus != 'Synced'";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    long purchaseId = r["Id"] is long pid ? pid : 0;
                    if (purchaseId == 0) continue;
                    list.Add(new Dictionary<string, object?>
                    {
                        ["local_id"] = purchaseId,
                        ["reference_no"] = r["reference_no"]?.ToString(),
                        ["invoice_no"] = r["invoice_no"]?.ToString(),
                        ["supplier_id"] = r["supplier_id"] as long?,
                        ["date"] = r["date"]?.ToString(),
                        ["grand_total"] = ToD(r["grand_total"]),
                        ["paid"] = ToD(r["paid"]),
                        ["due_amount"] = ToD(r["due_amount"]),
                        ["note"] = r["note"]?.ToString(),
                        ["discount"] = r["discount"]?.ToString(),
                        ["status"] = r["status"]?.ToString() ?? "Pending",
                        ["items"] = GetPendingPurchaseItems(conn, purchaseId),
                        ["payments"] = GetPendingPurchasePayments(conn, purchaseId)
                    });
                }
            }
            catch { }
            return list;
        }

        private List<object> GetPendingPurchaseItems(SqliteConnection conn, long purchaseId)
        {
            var items = new List<object>();
            try
            {
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT pd.item_id, pd.unit_price, pd.quantity_amount, m.Code AS item_code
                                    FROM purchase_details pd
                                    LEFT JOIN Master1 m ON m.ServerId = pd.item_id AND m.MasterType='Item'
                                    WHERE pd.purchase_id=@pid";
                cmd.Parameters.AddWithValue("@pid", purchaseId);
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    items.Add(new Dictionary<string, object?>
                    {
                        ["item_id"] = r["item_id"] as long?,
                        ["item_code"] = r["item_code"]?.ToString(),
                        ["unit_price"] = ToD(r["unit_price"]),
                        ["quantity"] = ToD(r["quantity_amount"])
                    });
                }
            }
            catch { }
            return items;
        }

        private List<object> GetPendingPurchasePayments(SqliteConnection conn, long purchaseId)
        {
            var payments = new List<object>();
            try
            {
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT payment_id, amount, date, reference_no FROM purchase_payments WHERE purchase_id=@pid";
                cmd.Parameters.AddWithValue("@pid", purchaseId);
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    payments.Add(new Dictionary<string, object?>
                    {
                        ["payment_id"] = r["payment_id"] as long?,
                        ["amount"] = ToD(r["amount"]),
                        ["date"] = r["date"]?.ToString(),
                        ["reference_no"] = r["reference_no"]?.ToString()
                    });
                }
            }
            catch { }
            return payments;
        }

        private List<object> GetPendingPurchaseReturns()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT Id, reference_no, pur_ref_no, supplier_id, date, purchase_date, return_status,
                                    total_return_amount, payment_method_id, note
                                    FROM purchase_returns
                                    WHERE SyncStatus IS NULL OR SyncStatus != 'Synced'";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    long retId = r["Id"] is long rid ? rid : 0;
                    if (retId == 0) continue;
                    list.Add(new Dictionary<string, object?>
                    {
                        ["local_id"] = retId,
                        ["reference_no"] = r["reference_no"]?.ToString(),
                        ["pur_ref_no"] = r["pur_ref_no"]?.ToString(),
                        ["supplier_id"] = r["supplier_id"] as long?,
                        ["date"] = r["date"]?.ToString(),
                        ["purchase_date"] = r["purchase_date"]?.ToString(),
                        ["return_status"] = r["return_status"]?.ToString(),
                        ["total_return_amount"] = ToD(r["total_return_amount"]),
                        ["payment_method_id"] = r["payment_method_id"] as long?,
                        ["note"] = r["note"]?.ToString(),
                        ["items"] = GetPendingReturnItems(conn, retId)
                    });
                }
            }
            catch { }
            return list;
        }

        private List<object> GetPendingReturnItems(SqliteConnection conn, long returnId)
        {
            var items = new List<object>();
            try
            {
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT prd.item_id, prd.unit_price, prd.return_quantity_amount, m.Code AS item_code
                                    FROM purchase_return_details prd
                                    LEFT JOIN Master1 m ON m.ServerId = prd.item_id AND m.MasterType='Item'
                                    WHERE prd.pur_return_id=@rid";
                cmd.Parameters.AddWithValue("@rid", returnId);
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    items.Add(new Dictionary<string, object?>
                    {
                        ["item_id"] = r["item_id"] as long?,
                        ["item_code"] = r["item_code"]?.ToString(),
                        ["unit_price"] = ToD(r["unit_price"]),
                        ["quantity"] = ToD(r["return_quantity_amount"])
                    });
                }
            }
            catch { }
            return items;
        }

        private List<object> GetPendingSupplierPayments()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT Id, reference_no, supplier_id, payment_method_id, amount, date, note
                                    FROM supplier_payments
                                    WHERE SyncStatus IS NULL OR SyncStatus != 'Synced'";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    list.Add(new Dictionary<string, object?>
                    {
                        ["local_id"] = r["Id"] as long?,
                        ["reference_no"] = r["reference_no"]?.ToString(),
                        ["supplier_id"] = r["supplier_id"] as long?,
                        ["payment_method_id"] = r["payment_method_id"] as long?,
                        ["amount"] = ToD(r["amount"]),
                        ["date"] = r["date"]?.ToString(),
                        ["note"] = r["note"]?.ToString()
                    });
                }
            }
            catch { }
            return list;
        }

        private List<object> GetPendingExpenses()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT Id, reference_no, date, category_id, payment_method_id, amount, note, employee_id
                                    FROM expenses
                                    WHERE (del_status IS NULL OR del_status='Live')
                                      AND (SyncStatus IS NULL OR SyncStatus != 'Synced')";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    list.Add(new Dictionary<string, object?>
                    {
                        ["local_id"] = r["Id"] as long?,
                        ["reference_no"] = r["reference_no"]?.ToString(),
                        ["date"] = r["date"]?.ToString(),
                        ["category_id"] = r["category_id"] as long?,
                        ["payment_method_id"] = r["payment_method_id"] as long?,
                        ["amount"] = ToD(r["amount"]),
                        ["note"] = r["note"]?.ToString(),
                        ["employee_id"] = r["employee_id"] as long?
                    });
                }
            }
            catch { }
            return list;
        }

        // ═══════════ 11 MISSING ENTITY PUSH METHODS ═══════════

        private List<object> GetPendingSalaries()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT id, reference_no, year, month, generated_date, total_amount, user_id
                                    FROM salaries WHERE (del_status IS NULL OR del_status='Live') AND (SyncStatus IS NULL OR SyncStatus != 'Synced')";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add(new Dictionary<string, object?> { ["local_id"] = r["id"] as long?, ["reference_no"] = r["reference_no"]?.ToString(), ["year"] = r["year"] as long?, ["month"] = r["month"] as long?, ["generated_date"] = r["generated_date"]?.ToString(), ["total_amount"] = ToD(r["total_amount"]), ["user_id"] = r["user_id"] as long? });
            } catch { }
            return list;
        }

        private List<object> GetPendingAttendances()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT id, reference_no, date, employee_id, in_time, out_time, note, user_id
                                    FROM attendances WHERE (del_status IS NULL OR del_status='Live') AND (SyncStatus IS NULL OR SyncStatus != 'Synced')";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add(new Dictionary<string, object?> { ["local_id"] = r["id"] as long?, ["reference_no"] = r["reference_no"]?.ToString(), ["date"] = r["date"]?.ToString(), ["employee_id"] = r["employee_id"] as long?, ["in_time"] = r["in_time"]?.ToString(), ["out_time"] = r["out_time"]?.ToString(), ["note"] = r["note"]?.ToString(), ["user_id"] = r["user_id"] as long? });
            } catch { }
            return list;
        }

        private List<object> GetPendingPromotions()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT id, name, title, type, scheme_basis, discount_type, discount_value, start_date, end_date, start_time, end_time, status, user_id, outlet_id, item_id, qty, get_item_id, get_qty, applicable_items, applicable_categories, applicable_customers, applicable_customer_types, min_purchase_amount, max_discount_amount, bill_level_discount, bill_level_discount_type, coupon_code, tier_percentages, del_status, updated_at
                                    FROM promotions WHERE (SyncStatus IS NULL OR SyncStatus != 'Synced')";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add(new Dictionary<string, object?> { ["local_id"] = r["id"] as long?, ["name"] = r["name"]?.ToString(), ["title"] = r["title"]?.ToString(), ["type"] = r["type"]?.ToString(), ["scheme_basis"] = r["scheme_basis"]?.ToString(), ["discount_type"] = r["discount_type"]?.ToString(), ["discount_value"] = ToD(r["discount_value"]), ["start_date"] = r["start_date"]?.ToString(), ["end_date"] = r["end_date"]?.ToString(), ["start_time"] = r["start_time"]?.ToString(), ["end_time"] = r["end_time"]?.ToString(), ["status"] = r["status"]?.ToString(), ["user_id"] = r["user_id"] as long?, ["outlet_id"] = r["outlet_id"] as long?, ["item_id"] = r["item_id"] as long?, ["qty"] = r["qty"] as long?, ["get_item_id"] = r["get_item_id"] as long?, ["get_qty"] = r["get_qty"] as long?, ["applicable_items"] = r["applicable_items"]?.ToString(), ["applicable_categories"] = r["applicable_categories"]?.ToString(), ["applicable_customers"] = r["applicable_customers"]?.ToString(), ["applicable_customer_types"] = r["applicable_customer_types"]?.ToString(), ["min_purchase_amount"] = ToD(r["min_purchase_amount"]), ["max_discount_amount"] = ToD(r["max_discount_amount"]), ["bill_level_discount"] = ToD(r["bill_level_discount"]), ["bill_level_discount_type"] = r["bill_level_discount_type"]?.ToString(), ["coupon_code"] = r["coupon_code"]?.ToString(), ["tier_percentages"] = r["tier_percentages"]?.ToString(), ["del_status"] = r["del_status"]?.ToString() ?? "Live", ["updated_at"] = r["updated_at"]?.ToString() });
            } catch { }
            return list;
        }

        private List<object> GetPendingBookings()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT id, customer_id, service_seller_id, start_date, end_date, status, note, user_id, outlet_id, item_id, service_note
                                    FROM bookings WHERE (del_status IS NULL OR del_status='Live') AND (SyncStatus IS NULL OR SyncStatus != 'Synced')";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add(new Dictionary<string, object?> { ["local_id"] = r["id"] as long?, ["customer_id"] = r["customer_id"] as long?, ["service_seller_id"] = r["service_seller_id"] as long?, ["start_date"] = r["start_date"]?.ToString(), ["end_date"] = r["end_date"]?.ToString(), ["status"] = r["status"]?.ToString(), ["note"] = r["note"]?.ToString(), ["user_id"] = r["user_id"] as long?, ["outlet_id"] = r["outlet_id"] as long?, ["item_id"] = r["item_id"] as long?, ["service_note"] = r["service_note"]?.ToString() });
            } catch { }
            return list;
        }

        private List<object> GetPendingServicings()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT id, reference_no, customer_id, employee_id, date, receiving_date, delivery_date, servicing_charge, paid_amount, due_amount, payment_method_id, description, note, user_id, outlet_id, current_status
                                    FROM servicings WHERE (del_status IS NULL OR del_status='Live') AND (SyncStatus IS NULL OR SyncStatus != 'Synced')";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add(new Dictionary<string, object?> { ["local_id"] = r["id"] as long?, ["reference_no"] = r["reference_no"]?.ToString(), ["customer_id"] = r["customer_id"] as long?, ["employee_id"] = r["employee_id"] as long?, ["date"] = r["date"]?.ToString(), ["receiving_date"] = r["receiving_date"]?.ToString(), ["delivery_date"] = r["delivery_date"]?.ToString(), ["servicing_charge"] = ToD(r["servicing_charge"]), ["paid_amount"] = ToD(r["paid_amount"]), ["due_amount"] = ToD(r["due_amount"]), ["payment_method_id"] = r["payment_method_id"] as long?, ["description"] = r["description"]?.ToString(), ["note"] = r["note"]?.ToString(), ["user_id"] = r["user_id"] as long?, ["outlet_id"] = r["outlet_id"] as long?, ["current_status"] = r["current_status"]?.ToString() });
            } catch { }
            return list;
        }

        private List<object> GetPendingWarranties()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT id, reference_no, customer_id, technician_id, receiving_date, delivery_date, current_status, description, note, user_id, outlet_id, item_name, item_model, serial_no
                                    FROM warranties WHERE (del_status IS NULL OR del_status='Live') AND (SyncStatus IS NULL OR SyncStatus != 'Synced')";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add(new Dictionary<string, object?> { ["local_id"] = r["id"] as long?, ["reference_no"] = r["reference_no"]?.ToString(), ["customer_id"] = r["customer_id"] as long?, ["technician_id"] = r["technician_id"] as long?, ["receiving_date"] = r["receiving_date"]?.ToString(), ["delivery_date"] = r["delivery_date"]?.ToString(), ["current_status"] = r["current_status"]?.ToString(), ["description"] = r["description"]?.ToString(), ["note"] = r["note"]?.ToString(), ["user_id"] = r["user_id"] as long?, ["outlet_id"] = r["outlet_id"] as long?, ["item_name"] = r["item_name"]?.ToString(), ["item_model"] = r["item_model"]?.ToString(), ["serial_no"] = r["serial_no"]?.ToString() });
            } catch { }
            return list;
        }

        private List<object> GetPendingDepositWithdraws()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT id, reference_no, date, type, payment_method_id, amount, note, user_id, outlet_id
                                    FROM deposit_withdraws WHERE (del_status IS NULL OR del_status='Live') AND (SyncStatus IS NULL OR SyncStatus != 'Synced')";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add(new Dictionary<string, object?> { ["local_id"] = r["id"] as long?, ["reference_no"] = r["reference_no"]?.ToString(), ["date"] = r["date"]?.ToString(), ["type"] = r["type"]?.ToString(), ["payment_method_id"] = r["payment_method_id"] as long?, ["amount"] = ToD(r["amount"]), ["note"] = r["note"]?.ToString(), ["user_id"] = r["user_id"] as long?, ["outlet_id"] = r["outlet_id"] as long? });
            } catch { }
            return list;
        }

        private List<object> GetPendingInstallmentSales()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT id, reference_no, customer_id, item_id, date, price, discount_amount, percentage_of_interest, interest_amount, shipping_other, total, down_payment, payment_method_id, remaining, paid_amount, due_amount, status, installment_count, note, user_id, outlet_id, discount
                                    FROM installment_sales WHERE (del_status IS NULL OR del_status='Live') AND (SyncStatus IS NULL OR SyncStatus != 'Synced')";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add(new Dictionary<string, object?> { ["local_id"] = r["id"] as long?, ["reference_no"] = r["reference_no"]?.ToString(), ["customer_id"] = r["customer_id"] as long?, ["item_id"] = r["item_id"] as long?, ["date"] = r["date"]?.ToString(), ["price"] = ToD(r["price"]), ["discount_amount"] = ToD(r["discount_amount"]), ["percentage_of_interest"] = ToD(r["percentage_of_interest"]), ["interest_amount"] = ToD(r["interest_amount"]), ["shipping_other"] = ToD(r["shipping_other"]), ["total"] = ToD(r["total"]), ["down_payment"] = ToD(r["down_payment"]), ["payment_method_id"] = r["payment_method_id"] as long?, ["remaining"] = ToD(r["remaining"]), ["paid_amount"] = ToD(r["paid_amount"]), ["due_amount"] = ToD(r["due_amount"]), ["status"] = r["status"]?.ToString(), ["installment_count"] = r["installment_count"] as long?, ["note"] = r["note"]?.ToString(), ["user_id"] = r["user_id"] as long?, ["outlet_id"] = r["outlet_id"] as long?, ["discount"] = r["discount"]?.ToString() });
            } catch { }
            return list;
        }

        private List<object> GetPendingQuotations()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT id, reference_no, customer_id, date, grand_total, note, user_id, outlet_id, discount
                                    FROM quotations WHERE (del_status IS NULL OR del_status='Live') AND (SyncStatus IS NULL OR SyncStatus != 'Synced')";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    var dict = new Dictionary<string, object?> { ["local_id"] = r["id"] as long?, ["reference_no"] = r["reference_no"]?.ToString(), ["customer_id"] = r["customer_id"] as long?, ["date"] = r["date"]?.ToString(), ["grand_total"] = ToD(r["grand_total"]), ["note"] = r["note"]?.ToString(), ["user_id"] = r["user_id"] as long?, ["outlet_id"] = r["outlet_id"] as long?, ["discount"] = r["discount"]?.ToString() };
                    // Fetch quotation_details
                    var details = new List<object>();
                    try
                    {
                        using var dc = _db.GetConnection();
                        using var dcmd = dc.CreateCommand();
                        dcmd.CommandText = $"SELECT item_id, quantity, unit_price, total, description FROM quotation_details WHERE quotation_id={r["id"]}";
                        using var dr = dcmd.ExecuteReader();
                        while (dr.Read())
                            details.Add(new Dictionary<string, object?> { ["item_id"] = dr["item_id"] as long?, ["quantity"] = ToD(dr["quantity"]), ["unit_price"] = ToD(dr["unit_price"]), ["total"] = ToD(dr["total"]), ["description"] = dr["description"]?.ToString() });
                    } catch { }
                    dict["items"] = details;
                    list.Add(dict);
                }
            } catch { }
            return list;
        }

        private List<object> GetPendingIncomes()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT id, reference_no, date, category_id, payment_method_id, amount, note, employee_id, user_id, outlet_id
                                    FROM incomes WHERE (del_status IS NULL OR del_status='Live') AND (SyncStatus IS NULL OR SyncStatus != 'Synced')";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add(new Dictionary<string, object?> { ["local_id"] = r["id"] as long?, ["reference_no"] = r["reference_no"]?.ToString(), ["date"] = r["date"]?.ToString(), ["category_id"] = r["category_id"] as long?, ["payment_method_id"] = r["payment_method_id"] as long?, ["amount"] = ToD(r["amount"]), ["note"] = r["note"]?.ToString(), ["employee_id"] = r["employee_id"] as long?, ["user_id"] = r["user_id"] as long?, ["outlet_id"] = r["outlet_id"] as long? });
            } catch { }
            return list;
        }

        private List<object> GetPendingCustomerReceives()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT id, customer_id, payment_method_id, amount, date, note, user_id, outlet_id, reference_no
                                    FROM customer_receives WHERE (del_status IS NULL OR del_status='Live') AND (SyncStatus IS NULL OR SyncStatus != 'Synced')";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add(new Dictionary<string, object?> { ["local_id"] = r["id"] as long?, ["customer_id"] = r["customer_id"] as long?, ["payment_method_id"] = r["payment_method_id"] as long?, ["amount"] = ToD(r["amount"]), ["date"] = r["date"]?.ToString(), ["note"] = r["note"]?.ToString(), ["user_id"] = r["user_id"] as long?, ["outlet_id"] = r["outlet_id"] as long?, ["reference_no"] = r["reference_no"]?.ToString() });
            } catch { }
            return list;
        }

        private bool HasPendingConfigs(Dictionary<string, object> configs)
        {
            foreach (var kv in configs)
            {
                if (kv.Value is List<object> list && list.Count > 0) return true;
            }
            return false;
        }

        private Dictionary<string, object> GetPendingConfigs()
        {
            var result = new Dictionary<string, object>();
            result["categories"] = GetPendingConfigRows("ItemCategories", "Name");
            result["brands"] = GetPendingConfigRows("Brands", "Name");
            result["units"] = GetPendingConfigRows("Units", "UnitName");
            result["racks"] = GetPendingConfigRows("Racks", "Name");
            result["variations"] = GetPendingVariations();
            result["expense_categories"] = GetPendingConfigRows("ExpenseCategories", "Name");
            return result;
        }

        private List<object> GetPendingConfigRows(string table, string nameCol)
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = $"SELECT Id, {nameCol}, Description, del_status FROM {table} WHERE (SyncStatus IS NULL OR SyncStatus != 'Synced')";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    string name = r[nameCol]?.ToString() ?? "";
                    if (name == "") continue;
                    list.Add(new Dictionary<string, object?>
                    {
                        ["local_id"] = r["Id"] as long?,
                        ["name"] = name,
                        ["description"] = r["Description"]?.ToString(),
                        ["del_status"] = r["del_status"]?.ToString() ?? "Live"
                    });
                }
            }
            catch { }
            return list;
        }

        private List<object> GetPendingVariations()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT Id, VariationName, VariationValue FROM Variations WHERE (SyncStatus IS NULL OR SyncStatus != 'Synced') AND (del_status IS NULL OR del_status != 'Deleted')";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    string name = r["VariationName"]?.ToString() ?? "";
                    if (name == "") continue;
                    string raw = r["VariationValue"]?.ToString() ?? "[]";
                    object values = new List<string>();
                    try
                    {
                        using var doc = JsonDocument.Parse(raw == "" ? "[]" : raw);
                        values = JsonSerializer.Deserialize<List<string>>(doc.RootElement.GetRawText()) ?? new List<string>();
                    }
                    catch { }
                    list.Add(new Dictionary<string, object?>
                    {
                        ["local_id"] = r["Id"] as long?,
                        ["variation_name"] = name,
                        ["variation_value"] = values
                    });
                }
            }
            catch { }
            return list;
        }

        private int ApplyConfigResults(JsonElement configResults)
        {
            int pushed = 0;
            var tableMap = new Dictionary<string, string>
            {
                ["categories"] = "ItemCategories",
                ["brands"] = "Brands",
                ["units"] = "Units",
                ["racks"] = "Racks",
                ["variations"] = "Variations",
                ["expense_categories"] = "ExpenseCategories"
            };

            // Map PascalCase config table → lowercase mirror table + name column
            var mirrorMap = new Dictionary<string, (string table, string nameCol)>
            {
                ["ItemCategories"] = ("item_categories", "name"),
                ["Brands"] = ("brands", "name"),
                ["Units"] = ("units", "unit_name"),
                ["Racks"] = ("racks", "name"),
                ["Variations"] = ("variations", "variation_name"),
                ["ExpenseCategories"] = ("expense_categories", "name")
            };

            using var conn = _db.GetConnection();
            foreach (var kv in tableMap)
            {
                if (!configResults.TryGetProperty(kv.Key, out var results)) continue;
                foreach (var r in results.EnumerateArray())
                {
                    long localId = 0;
                    if (r.TryGetProperty("local_id", out var li) && li.ValueKind == JsonValueKind.Number)
                        localId = li.GetInt64();
                    long serverId = 0;
                    if (r.TryGetProperty("server_id", out var sid) && sid.ValueKind == JsonValueKind.Number)
                        serverId = sid.GetInt64();
                    if (localId == 0) continue;

                    // Update config table (PascalCase) with ServerId
                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.CommandText = $"UPDATE {kv.Value} SET ServerId=@sid, SyncStatus='Synced' WHERE Id=@id";
                        cmd.Parameters.AddWithValue("@sid", serverId);
                        cmd.Parameters.AddWithValue("@id", localId);
                        cmd.ExecuteNonQuery();
                    }

                    // Also insert into lowercase mirror table so pull won't create duplicate
                    if (serverId > 0 && mirrorMap.TryGetValue(kv.Value, out var mirror))
                    {
                        try
                        {
                            // Only insert when the lowercase mirror table actually exists.
                            // SQLite identifiers are case-insensitive, so inserting into a
                            // non-existent lowercase name like "brands" would silently write
                            // into the PascalCase table and create duplicate rows.
                            using (var existsCmd = conn.CreateCommand())
                            {
                                existsCmd.CommandText = "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=@name COLLATE BINARY";
                                existsCmd.Parameters.AddWithValue("@name", mirror.table);
                                if (Convert.ToInt64(existsCmd.ExecuteScalar()) == 0) continue;
                            }
                            // Get the name from config table
                            string? name = null;
                            using (var nameCmd = conn.CreateCommand())
                            {
                                string configNameCol = kv.Value == "Units" ? "UnitName"
                                    : kv.Value == "Variations" ? "VariationName" : "Name";
                                nameCmd.CommandText = $"SELECT {configNameCol} FROM {kv.Value} WHERE Id=@id";
                                nameCmd.Parameters.AddWithValue("@id", localId);
                                name = nameCmd.ExecuteScalar()?.ToString();
                            }
                            if (!string.IsNullOrEmpty(name))
                            {
                                using var ins = conn.CreateCommand();
                                ins.CommandText = $"INSERT OR IGNORE INTO \"{mirror.table}\" (id, \"{mirror.nameCol}\") VALUES (@id, @name)";
                                ins.Parameters.AddWithValue("@id", serverId);
                                ins.Parameters.AddWithValue("@name", name);
                                ins.ExecuteNonQuery();
                            }
                        }
                        catch { }
                    }
                    pushed++;
                }
            }
            return pushed;
        }

        private List<object> GetPendingItems()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT Code, Name, AliasName, HSNCode, MRP, SaleRate, MinSalePrice, PurchaseRate, TaxCategory, MainUnit,
                                    ItemType, CategoryId, BrandId, WholeSalePrice, ProfitMargin, AlertQty, SupplierId, LoyaltyPoint,
                                    UnitType, SaleUnitId, PurchaseUnitId, ConversionRate, Warranty, WarrantyDate, Guarantee, GuaranteeDate, TaxType,
                                    CurrentStock, UpdatedAt
                                    FROM Master1
                                    WHERE MasterType='Item' AND IsActive=1 AND Name<>'' AND (SyncStatus IS NULL OR SyncStatus != 'Synced')";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    // The POS updates stock in items.stock_quantity; Master1.CurrentStock
                    // can go stale. Reconcile: prefer items.stock_quantity (by code) so
                    // POS stock deductions actually reach the server.
                    double stockQty = ToD(r["CurrentStock"]);
                    using (var stockCmd = conn.CreateCommand())
                    {
                        stockCmd.CommandText = "SELECT stock_quantity FROM items WHERE lower(code)=lower(@c) LIMIT 1";
                        stockCmd.Parameters.AddWithValue("@c", r["Code"]?.ToString() ?? "");
                        var sv = stockCmd.ExecuteScalar();
                        if (sv != null && sv != DBNull.Value)
                            stockQty = Convert.ToDouble(sv);
                    }

                    list.Add(new Dictionary<string, object?>
                    {
                        ["local_code"] = r["Code"]?.ToString() ?? "",
                        ["code"] = r["Code"]?.ToString() ?? "",
                        ["name"] = r["Name"]?.ToString() ?? "",
                        ["alternative_name"] = r["AliasName"]?.ToString(),
                        ["type"] = r["ItemType"] as string ?? "General_Product",
                        ["category_id"] = r["CategoryId"] as long?,
                        ["brand_id"] = r["BrandId"] as long?,
                        ["supplier_id"] = r["SupplierId"] as long?,
                        ["hsn_code"] = r["HSNCode"]?.ToString(),
                        ["unit_type"] = r["UnitType"]?.ToString(),
                        ["purchase_unit_id"] = r["PurchaseUnitId"] as long?,
                        ["sale_unit_id"] = r["SaleUnitId"] as long?,
                        ["conversion_rate"] = ToD(r["ConversionRate"]),
                        ["mrp_price"] = ToD(r["MRP"]),
                        ["sale_price"] = ToD(r["SaleRate"]),
                        ["whole_sale_price"] = ToD(r["WholeSalePrice"]) > 0 ? ToD(r["WholeSalePrice"]) : ToD(r["MinSalePrice"]),
                        ["purchase_price"] = ToD(r["PurchaseRate"]),
                        ["profit_margin"] = ToD(r["ProfitMargin"]),
                        ["alert_quantity"] = ToD(r["AlertQty"]),
                        ["loyalty_point"] = r["LoyaltyPoint"] as long? ?? 0,
                        ["warranty"] = r["Warranty"]?.ToString(),
                        ["warranty_date"] = r["WarrantyDate"]?.ToString(),
                        ["guarantee"] = r["Guarantee"]?.ToString(),
                        ["guarantee_date"] = r["GuaranteeDate"]?.ToString(),
                        ["tax_string"] = r["TaxCategory"]?.ToString(),
                        ["tax_type"] = r["TaxType"]?.ToString(),
                        ["stock_quantity"] = stockQty,
                        ["enable_disable_status"] = 1,
                        ["updated_at"] = r["UpdatedAt"]?.ToString()
                    });
                }
            }
            catch { }
            return list;
        }

        private List<object> GetPendingNewItems()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT code, name, alternative_name, generic_name, type, parent_id,
                                    (SELECT p.code FROM items p WHERE p.Id = i.parent_id LIMIT 1) AS parent_code,
                                    category_id, rack_id,
                                    brand_id, supplier_id, hsn_code, unit_type, purchase_unit_id, sale_unit_id,
                                    conversion_rate, purchase_price, profit_margin, sale_price, whole_sale_price,
                                    mrp_price, description, warranty, warranty_date, guarantee, guarantee_date,
                                    tax_type, applicable_tax_id, alert_quantity, loyalty_point, stock_quantity
                                    FROM items i
                                    WHERE del_status='Live' AND (SyncStatus IS NULL OR SyncStatus = 'Local')
                                    ORDER BY CASE WHEN i.parent_id IS NULL OR i.parent_id=0 THEN 0 ELSE 1 END, i.id";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    list.Add(new Dictionary<string, object?>
                    {
                        ["local_code"] = r["code"]?.ToString() ?? "",
                        ["code"] = r["code"]?.ToString() ?? "",
                        ["name"] = r["name"]?.ToString() ?? "",
                        ["alternative_name"] = r["alternative_name"]?.ToString(),
                        ["type"] = r["type"] as string ?? "General_Product",
                        ["parent_id"] = ResolveItemParentId(conn, r["parent_id"]),
                        ["parent_code"] = r["parent_code"] as string,
                        ["category_id"] = ResolveLocalToServer(conn, "ItemCategories", r["category_id"]),
                        ["brand_id"] = ResolveLocalToServer(conn, "Brands", r["brand_id"]),
                        ["supplier_id"] = ResolveLocalToServer(conn, "Suppliers", r["supplier_id"]),
                        ["hsn_code"] = r["hsn_code"]?.ToString(),
                        ["unit_type"] = r["unit_type"]?.ToString(),
                        ["purchase_unit_id"] = ResolveLocalToServer(conn, "Units", r["purchase_unit_id"]),
                        ["sale_unit_id"] = ResolveLocalToServer(conn, "Units", r["sale_unit_id"]),
                        ["conversion_rate"] = ToD(r["conversion_rate"]),
                        ["mrp_price"] = ToD(r["mrp_price"]),
                        ["sale_price"] = ToD(r["sale_price"]),
                        ["whole_sale_price"] = ToD(r["whole_sale_price"]),
                        ["purchase_price"] = ToD(r["purchase_price"]),
                        ["profit_margin"] = ToD(r["profit_margin"]),
                        ["alert_quantity"] = ToD(r["alert_quantity"]),
                        ["loyalty_point"] = r["loyalty_point"] as long? ?? 0,
                        ["warranty"] = r["warranty"]?.ToString(),
                        ["warranty_date"] = r["warranty_date"]?.ToString(),
                        ["guarantee"] = r["guarantee"]?.ToString(),
                        ["guarantee_date"] = r["guarantee_date"]?.ToString(),
                        ["tax_type"] = r["tax_type"] as string ?? "Exclusive",
                        ["tax_string"] = r["applicable_tax_id"] != null ? r["applicable_tax_id"].ToString() : null,
                        ["stock_quantity"] = ToD(r["stock_quantity"]),
                        ["enable_disable_status"] = 1
                    });
                }
            }
            catch { }
            return list;
        }

        private long? ResolveLocalToServer(SqliteConnection conn, string table, object? localId)
        {
            if (localId == null || localId == DBNull.Value) return null;
            long lid = Convert.ToInt64(localId);
            if (lid == 0) return null;
            try
            {
                // localId may be a local negative id (lookup by Id) or an already-resolved
                // server id stored in items.category_id/brand_id/unit_id (lookup by ServerId).
                using var cmd = conn.CreateCommand();
                cmd.CommandText = $"SELECT ServerId FROM {table} WHERE (Id=@id OR ServerId=@id) AND ServerId IS NOT NULL AND ServerId > 0 LIMIT 1";
                cmd.Parameters.AddWithValue("@id", lid);
                var v = cmd.ExecuteScalar();
                return v is long s && s > 0 ? s : null;
            }
            catch { return null; }
        }

        private long? ResolveItemParentId(SqliteConnection conn, object? localParentId)
        {
            if (localParentId == null || localParentId == DBNull.Value) return null;
            long lid = Convert.ToInt64(localParentId);
            if (lid <= 0) return null;
            try
            {
                // parent_id may be a negative local id or already a server id; resolve via items.ServerId
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT ServerId FROM items WHERE Id=@id AND ServerId IS NOT NULL AND ServerId > 0 LIMIT 1";
                cmd.Parameters.AddWithValue("@id", lid);
                var v = cmd.ExecuteScalar();
                return v is long s && s > 0 ? s : null;
            }
            catch { return null; }
        }

        private List<object> GetPendingCustomers()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT Code, Name, Email, Phone, Address1, City, PinCode, GSTIN, OpeningBalance, CreditLimit, BusinessType, UpdatedAt,
                                           IFNULL(SyncVersion, 1) AS SyncVersion
                                    FROM Master1
                                    WHERE MasterType='Party' AND PartyType='Customer' AND IsActive=1
                                      AND (SyncStatus IS NULL OR SyncStatus != 'Synced')";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    string businessType = r["BusinessType"]?.ToString() ?? "B2C";
                    if (string.IsNullOrWhiteSpace(businessType)) businessType = "B2C";
                    list.Add(new Dictionary<string, object?>
                    {
                        ["local_code"] = r["Code"]?.ToString() ?? "",
                        ["name"] = r["Name"]?.ToString() ?? "",
                        ["email"] = r["Email"]?.ToString(),
                        ["phone"] = r["Phone"]?.ToString(),
                        ["address"] = r["Address1"]?.ToString(),
                        ["city"] = r["City"]?.ToString(),
                        ["postal_code"] = r["PinCode"]?.ToString(),
                        ["gst_number"] = r["GSTIN"]?.ToString(),
                        ["opening_balance"] = ToD(r["OpeningBalance"]),
                        ["credit_limit"] = ToD(r["CreditLimit"]),
                        ["customer_type"] = businessType,
                        ["business_type"] = businessType,
                        ["updated_at"] = r["UpdatedAt"]?.ToString(),
                        ["sync_version"] = r["SyncVersion"] is long sv ? sv : Convert.ToInt64(ToD(r["SyncVersion"]))
                    });
                }
            }
            catch { }
            return list;
        }

        private List<object> GetPendingSuppliers()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT m.Code, m.Name, m.Email, COALESCE(NULLIF(m.Phone,''), NULLIF(m.Mobile,''), '') AS phone,
                                           m.Address, m.Station AS city, m.PinCode, m.GSTIN, m.OpeningBalance, m.CreditLimit,
                                           m.DrCr, m.ContactPerson, m.UpdatedAt,
                                           COALESCE((SELECT description FROM suppliers WHERE id = m.ServerId), '') AS description
                                    FROM Master1 m
                                    WHERE m.MasterType='Party' AND m.PartyType='Supplier' AND m.IsActive=1
                                      AND (m.SyncStatus IS NULL OR m.SyncStatus != 'Synced')";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    list.Add(new Dictionary<string, object?>
                    {
                        ["local_code"] = r["Code"]?.ToString() ?? "",
                        ["name"] = r["Name"]?.ToString() ?? "",
                        ["email"] = r["Email"]?.ToString(),
                        ["phone"] = r["phone"]?.ToString(),
                        ["address"] = r["Address"]?.ToString(),
                        ["city"] = r["city"]?.ToString(),
                        ["postal_code"] = r["PinCode"]?.ToString(),
                        ["gst_number"] = r["GSTIN"]?.ToString(),
                        ["opening_balance"] = ToD(r["OpeningBalance"]),
                        ["opening_balance_type"] = r["DrCr"]?.ToString() ?? "Dr",
                        ["credit_limit"] = ToD(r["CreditLimit"]),
                        ["contact_person"] = r["ContactPerson"]?.ToString() ?? "",
                        ["description"] = r["description"]?.ToString() ?? "",
                        ["updated_at"] = r["UpdatedAt"]?.ToString()
                    });
                }
            }
            catch { }
            return list;
        }

        private List<object> GetPendingSales()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT VchCode, SyncPayload
                                    FROM Tran1
                                    WHERE VchType='Sales'
                                      AND SyncPayload IS NOT NULL AND SyncPayload != ''
                                      AND (SyncStatus IS NULL OR SyncStatus != 'Synced')";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    var payload = r["SyncPayload"]?.ToString();
                    if (string.IsNullOrWhiteSpace(payload)) continue;
                    try
                    {
                        using var doc = JsonDocument.Parse(payload);
                        var root = doc.RootElement;

                        // FK validation: skip if customer_id references a local-only customer (negative/0)
                        if (root.TryGetProperty("customer_id", out var cid) && cid.ValueKind == JsonValueKind.Number)
                        {
                            long custId = cid.GetInt64();
                            if (custId < 0)
                            {
                                using var chk = conn.CreateCommand();
                                chk.CommandText = "SELECT ServerId FROM customers WHERE id=@id AND ServerId > 0 LIMIT 1";
                                chk.Parameters.AddWithValue("@id", custId);
                                var srvId = chk.ExecuteScalar();
                                if (srvId == null) continue;
                            }
                        }

                        // Validate: must have items array with at least 1 item
                        if (!root.TryGetProperty("items", out var itemsEl) || itemsEl.GetArrayLength() == 0)
                        {
                            LogService.Warn($"Skipping sale with no items: {r["VchCode"]}");
                            continue;
                        }

                        // Validate: grand_total must be > 0
                        if (root.TryGetProperty("grand_total", out var gt) && (gt.GetDouble() <= 0 || double.IsNaN(gt.GetDouble())))
                        {
                            LogService.Warn($"Skipping sale with invalid grand_total: {r["VchCode"]}");
                            continue;
                        }

                        // Validate: each item must have item_code and qty > 0
                        bool badItem = false;
                        foreach (var item in itemsEl.EnumerateArray())
                        {
                            if (!item.TryGetProperty("item_code", out var ic) || string.IsNullOrWhiteSpace(ic.GetString()))
                            {
                                badItem = true;
                                break;
                            }
                            if (item.TryGetProperty("qty", out var q) && q.GetDouble() <= 0)
                            {
                                badItem = true;
                                break;
                            }
                            if (!item.TryGetProperty("menu_unit_price", out var p) || p.GetDouble() < 0)
                            {
                                badItem = true;
                                break;
                            }
                        }
                        if (badItem)
                        {
                            LogService.Warn($"Skipping sale with invalid items: {r["VchCode"]}");
                            continue;
                        }

                        list.Add(JsonSerializer.Deserialize<object>(payload) ?? new object());
                    }
                    catch (Exception ex)
                    {
                        LogService.Warn($"Skipping sale with bad JSON payload: {r["VchCode"]} - {ex.Message}");
                    }
                }
            }
            catch { }
            return list;
        }

        private void MarkMasterSynced(string? code, long serverId, string masterType)
        {
            if (string.IsNullOrEmpty(code)) return;
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = @"UPDATE Master1 SET ServerId=@sid, SyncStatus='Synced', SyncError=NULL WHERE Code=@c AND MasterType=@t";
            cmd.Parameters.AddWithValue("@sid", serverId);
            cmd.Parameters.AddWithValue("@c", code);
            cmd.Parameters.AddWithValue("@t", masterType);
            cmd.ExecuteNonQuery();
        }

        /// <summary>
        /// After a successful party push, mark the matching customers/suppliers mirror
        /// row Synced too. Otherwise it stays 'Local' forever and the app keeps
        /// re-pushing it every 10s (perpetual churn). Match by ServerId if assigned,
        /// else by name/phone pulled from the Master1 row we just pushed. NULL
        /// SyncStatus rows are treated as pending per the existing predicates.
        /// </summary>
        private void MarkPartyMirrorSynced(string table, string? localCode, long serverId)
        {
            if (string.IsNullOrEmpty(localCode)) return;
            try
            {
                using var conn = _db.GetConnection();

                // ServerId match first (already-synced row that got edited again).
                if (serverId > 0)
                {
                    using var bySid = conn.CreateCommand();
                    bySid.CommandText = $"UPDATE \"{table}\" SET ServerId=@sid, SyncStatus='Synced', SyncError=NULL WHERE ServerId=@sid AND (SyncStatus IS NULL OR SyncStatus='Local')";
                    bySid.Parameters.AddWithValue("@sid", serverId);
                    if (bySid.ExecuteNonQuery() > 0) return;
                }

                // Pull the pushed row's identity from Master1 (source of the push payload).
                string? name = null, phone = null;
                using (var q = conn.CreateCommand())
                {
                    q.CommandText = "SELECT Name, Phone FROM Master1 WHERE Code=@c AND MasterType='Party' LIMIT 1";
                    q.Parameters.AddWithValue("@c", localCode);
                    using var qr = q.ExecuteReader();
                    if (qr.Read())
                    {
                        name = qr["Name"] as string;
                        phone = qr["Phone"] as string;
                    }
                }
                if (string.IsNullOrEmpty(name) && string.IsNullOrEmpty(phone)) return;

                var conds = new List<string>();
                var ps = new List<(string n, object? v)>();
                if (!string.IsNullOrEmpty(name))
                {
                    conds.Add("LOWER(TRIM(Name))=LOWER(TRIM(@name))");
                    ps.Add(("@name", name));
                }
                if (!string.IsNullOrEmpty(phone))
                {
                    conds.Add("LOWER(TRIM(IFNULL(Phone,''))) = LOWER(TRIM(@phone))");
                    ps.Add(("@phone", phone));
                }
                using var cmd = conn.CreateCommand();
                cmd.CommandText = $"UPDATE \"{table}\" SET ServerId=@sid, SyncStatus='Synced', SyncError=NULL WHERE (SyncStatus IS NULL OR SyncStatus='Local') AND del_status='Live' AND ({string.Join(" OR ", conds)})";
                cmd.Parameters.AddWithValue("@sid", serverId);
                foreach (var (n, v) in ps) cmd.Parameters.AddWithValue(n, v ?? (object)DBNull.Value);
                cmd.ExecuteNonQuery();
            }
            catch { }
        }

        private void MarkNewItemSynced(string? code, long serverId)
        {
            if (string.IsNullOrEmpty(code)) return;
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"UPDATE items SET ServerId=@sid, SyncStatus='Synced' WHERE code=@c AND (SyncStatus IS NULL OR SyncStatus='Local')";
                cmd.Parameters.AddWithValue("@sid", serverId);
                cmd.Parameters.AddWithValue("@c", code);
                cmd.ExecuteNonQuery();
            }
            catch { }
        }

        private void MarkSaleSynced(string? vchCode, long serverId, string status)
        {
            if (string.IsNullOrEmpty(vchCode)) return;
            try
            {
                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();

                using (var cmd = conn.CreateCommand())
                {
                    cmd.Transaction = txn;
                    cmd.CommandText = @"UPDATE Tran1 SET ServerId=@sid, SyncStatus=@st WHERE VchCode=@c";
                    cmd.Parameters.AddWithValue("@sid", serverId);
                    cmd.Parameters.AddWithValue("@st", status);
                    cmd.Parameters.AddWithValue("@c", vchCode);
                    cmd.ExecuteNonQuery();
                }

                // The POS writes BOTH a Tran1 voucher AND a `sales` mirror row.
                // The mirror row's invoice_no/sale_no live inside Tran1.SyncPayload,
                // so extract it and mark the mirror rows (sales, sale_details,
                // sale_payments) Synced too — otherwise they stay 'Local' forever
                // and the app pushes on every 20s tick without ever clearing them.
                string? invoiceNo = null;
                using (var pr = conn.CreateCommand())
                {
                    pr.Transaction = txn;
                    pr.CommandText = "SELECT SyncPayload FROM Tran1 WHERE VchCode=@c";
                    pr.Parameters.AddWithValue("@c", vchCode);
                    var payload = pr.ExecuteScalar() as string;
                    if (!string.IsNullOrEmpty(payload))
                    {
                        try
                        {
                            using var doc = JsonDocument.Parse(payload);
                            if (doc.RootElement.TryGetProperty("invoice_no", out var ip) && ip.ValueKind == JsonValueKind.String)
                                invoiceNo = ip.GetString();
                        }
                        catch { }
                    }
                }

                if (!string.IsNullOrEmpty(invoiceNo))
                {
                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.Transaction = txn;
                        cmd.CommandText = @"UPDATE sales SET ServerId=@sid, SyncStatus=@st, SyncError=NULL
                                            WHERE SyncStatus='Local' AND del_status='Live'
                                              AND (invoice_no=@inv OR sale_no=@inv)";
                        cmd.Parameters.AddWithValue("@sid", serverId);
                        cmd.Parameters.AddWithValue("@st", status);
                        cmd.Parameters.AddWithValue("@inv", invoiceNo);
                        cmd.ExecuteNonQuery();
                    }
                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.Transaction = txn;
                        cmd.CommandText = @"UPDATE sale_details SET SyncStatus=@st
                                            WHERE SyncStatus='Local' AND sales_id IN
                                                (SELECT id FROM sales WHERE invoice_no=@inv OR sale_no=@inv)";
                        cmd.Parameters.AddWithValue("@st", status);
                        cmd.Parameters.AddWithValue("@inv", invoiceNo);
                        cmd.ExecuteNonQuery();
                    }
                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.Transaction = txn;
                        cmd.CommandText = @"UPDATE sale_payments SET SyncStatus=@st
                                            WHERE SyncStatus='Local' AND sale_id IN
                                                (SELECT id FROM sales WHERE invoice_no=@inv OR sale_no=@inv)";
                        cmd.Parameters.AddWithValue("@st", status);
                        cmd.Parameters.AddWithValue("@inv", invoiceNo);
                        cmd.ExecuteNonQuery();
                    }
                }

                txn.Commit();
            }
            catch { }
        }

        private List<object> GetPendingSaleReturns()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT id, SyncPayload
                                    FROM sale_returns
                                    WHERE SyncPayload IS NOT NULL AND SyncPayload != ''
                                      AND (SyncStatus IS NULL OR SyncStatus != 'Synced')";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    long id = r.GetInt64(0);
                    var payload = r["SyncPayload"]?.ToString();
                    if (string.IsNullOrWhiteSpace(payload)) continue;
                    try
                    {
                        using var doc = JsonDocument.Parse(payload);
                        if (doc.RootElement.ValueKind != JsonValueKind.Object) continue;
                        var obj = JsonSerializer.Deserialize<Dictionary<string, object?>>(payload)
                                  ?? new Dictionary<string, object?>();
                        obj["local_id"] = id;
                        list.Add(obj);
                    }
                    catch { }
                }
            }
            catch { }
            return list;
        }

        private List<object> GetPendingWalletTransactions()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT id, wallet_id, customer_id, sale_id, type, amount,
                                           balance_before, balance_after, description, transaction_date,
                                           company_id, del_status, created_at, updated_at
                                    FROM wallet_transactions
                                    WHERE del_status='Live'
                                      AND (SyncStatus IS NULL OR SyncStatus = 'Local')";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    long id = r["id"] is long lid ? lid : 0;
                    if (id == 0) continue;
                    list.Add(new Dictionary<string, object?>
                    {
                        ["local_id"] = id,
                        ["wallet_id"] = r["wallet_id"] as long?,
                        ["customer_id"] = r["customer_id"] as long?,
                        ["sale_id"] = r["sale_id"] as long?,
                        ["type"] = r["type"]?.ToString(),
                        ["amount"] = ToD(r["amount"]),
                        ["balance_before"] = ToD(r["balance_before"]),
                        ["balance_after"] = ToD(r["balance_after"]),
                        ["description"] = r["description"]?.ToString(),
                        ["transaction_date"] = r["transaction_date"]?.ToString(),
                        ["company_id"] = r["company_id"] as long?,
                        ["created_at"] = r["created_at"]?.ToString(),
                        ["updated_at"] = r["updated_at"]?.ToString()
                    });
                }
            }
            catch { }
            return list;
        }

        private List<object> GetPendingLoyaltyChanges()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                // Customers whose loyalty was changed locally (marked via LoyaltySyncPending flag)
                cmd.CommandText = @"SELECT id, IFNULL(loyalty_point,0) AS loyalty_point
                                    FROM customers
                                    WHERE del_status='Live'
                                      AND LoyaltySyncPending = 1";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    long id = r["id"] is long lid ? lid : 0;
                    if (id == 0) continue;
                    list.Add(new Dictionary<string, object?>
                    {
                        ["customer_id"] = id,
                        ["loyalty_point"] = r["loyalty_point"] is long lp ? lp : Convert.ToInt64(ToD(r["loyalty_point"]))
                    });
                }
            }
            catch { }
            return list;
        }

        private List<object> GetPendingOutlets()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT id, ServerId, outlet_code, outlet_name, name, phone, email, address, state_id, active_status, is_active
                                    FROM outlets
                                    WHERE del_status='Live'
                                      AND (SyncStatus IS NULL OR SyncStatus != 'Synced' OR ServerId IS NULL OR ServerId = 0)";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    long id = r["id"] is long lid ? lid : Convert.ToInt64(r["id"]);
                    if (id == 0) continue;
                    long serverId = r["ServerId"] is long sid2 ? sid2 : (r["ServerId"] != DBNull.Value ? Convert.ToInt64(r["ServerId"]) : 0);
                    list.Add(new Dictionary<string, object?>
                    {
                        ["local_code"] = r["outlet_code"]?.ToString(),
                        ["outlet_code"] = r["outlet_code"]?.ToString(),
                        ["server_id"] = serverId > 0 ? serverId : null,
                        ["outlet_name"] = r["outlet_name"]?.ToString() ?? r["name"]?.ToString(),
                        ["name"] = r["name"]?.ToString() ?? r["outlet_name"]?.ToString(),
                        ["phone"] = r["phone"]?.ToString(),
                        ["email"] = r["email"]?.ToString(),
                        ["address"] = r["address"]?.ToString(),
                        ["state_id"] = r["state_id"] as long? ?? 0,
                        ["active_status"] = r["active_status"]?.ToString() ?? "Active",
                        ["is_active"] = r["is_active"] as long? ?? 1,
                        ["del_status"] = "Live"
                    });
                }
            }
            catch { }
            return list;
        }

        private void MarkOutletSynced(string outletCode, long serverId, string status)
        {
            if (string.IsNullOrEmpty(outletCode)) return;
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"UPDATE outlets SET ServerId=@sid, SyncStatus=@st, SyncError=NULL
                                    WHERE outlet_code=@code";
                cmd.Parameters.AddWithValue("@sid", serverId);
                cmd.Parameters.AddWithValue("@st", status);
                cmd.Parameters.AddWithValue("@code", outletCode);
                cmd.ExecuteNonQuery();
            }
            catch { }
        }

        private void MarkWalletTransactionSynced(long localId, long serverId)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "UPDATE wallet_transactions SET ServerId=@sid, SyncStatus='Synced', SyncError=NULL WHERE id=@id";
                cmd.Parameters.AddWithValue("@sid", serverId);
                cmd.Parameters.AddWithValue("@id", localId);
                cmd.ExecuteNonQuery();
            }
            catch { }
        }

        private List<object> GetPendingBusinessClubTransactions()
        {
            var list = new List<object>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT id, member_id, customer_id, sale_id, type, amount,
                                           balance_before, balance_after, description, transaction_date,
                                           company_id, del_status, created_at, updated_at
                                    FROM business_club_transactions
                                    WHERE del_status='Live'
                                      AND (SyncStatus IS NULL OR SyncStatus = 'Local')";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    long id = r["id"] is long lid ? lid : 0;
                    if (id == 0) continue;
                    list.Add(new Dictionary<string, object?>
                    {
                        ["local_id"] = id,
                        ["member_id"] = r["member_id"]?.ToString(),
                        ["customer_id"] = r["customer_id"] as long?,
                        ["sale_id"] = r["sale_id"] as long?,
                        ["type"] = r["type"]?.ToString(),
                        ["amount"] = ToD(r["amount"]),
                        ["balance_before"] = ToD(r["balance_before"]),
                        ["balance_after"] = ToD(r["balance_after"]),
                        ["description"] = r["description"]?.ToString(),
                        ["transaction_date"] = r["transaction_date"]?.ToString(),
                        ["company_id"] = r["company_id"] as long?,
                        ["created_at"] = r["created_at"]?.ToString(),
                        ["updated_at"] = r["updated_at"]?.ToString()
                    });
                }
            }
            catch { }
            return list;
        }

        private void MarkBusinessClubTransactionSynced(long localId, long serverId)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "UPDATE business_club_transactions SET ServerId=@sid, SyncStatus='Synced', SyncError=NULL WHERE id=@id";
                cmd.Parameters.AddWithValue("@sid", serverId);
                cmd.Parameters.AddWithValue("@id", localId);
                cmd.ExecuteNonQuery();
            }
            catch { }
        }

        private void MarkCustomerLoyaltySynced(long customerId)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "UPDATE customers SET LoyaltySyncPending=0 WHERE id=@id";
                cmd.Parameters.AddWithValue("@id", customerId);
                cmd.ExecuteNonQuery();
            }
            catch { }
        }

        private void MarkSaleReturnSynced(long localId, long serverId, string status)
        {
            if (localId == 0) return;
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"UPDATE sale_returns SET ServerId=@sid, SyncStatus=@st WHERE id=@id";
                cmd.Parameters.AddWithValue("@sid", serverId);
                cmd.Parameters.AddWithValue("@st", status);
                cmd.Parameters.AddWithValue("@id", localId);
                cmd.ExecuteNonQuery();
            }
            catch { }
        }

        private void MarkGenericSynced(string table, long localId, long serverId)
        {
            if (localId == 0) return;
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = $"UPDATE {table} SET ServerId=@sid, SyncStatus='Synced' WHERE id=@id";
                cmd.Parameters.AddWithValue("@sid", serverId);
                cmd.Parameters.AddWithValue("@id", localId);
                cmd.ExecuteNonQuery();
            }
            catch { }
        }

        // ═══════════════════ PULL ═══════════════════

        private async Task<int> PullAsync()
        {
            string? since = _api.GetSetting("last_sync_at");
            LogService.Info($"Pull starting (since: {since ?? "initial"})");

            // ── RETRY: truncated response / transient network error pe 2 extra tries ──
            var (ok, message, data) = await _api.PullAsync(since);
            for (int retry = 0; !ok && retry < 2; retry++)
            {
                bool transient = message.Contains("invalid JSON literal", StringComparison.OrdinalIgnoreCase)
                              || message.Contains("EOF", StringComparison.OrdinalIgnoreCase)
                              || message.Contains("timed out", StringComparison.OrdinalIgnoreCase)
                              || message.Contains("connection", StringComparison.OrdinalIgnoreCase);
                if (!transient) break;
                LogService.Warn($"Pull transient failure — retry {retry + 1}/2 after 3s");
                await Task.Delay(3000);
                (ok, message, data) = await _api.PullAsync(since);
            }
            if (!ok || data == null)
            {
                LogService.Error($"Pull failed: {message}");
                throw new Exception(message);
            }

            int pulled = 0;
            var root = data.RootElement;

            // ─── Core tables with dedicated upsert logic ───
            // Every upsert below swallows its own exceptions; the catch blocks set
            // _pullUpsertFailed so a partially-failed pull never advances the
            // last_sync_at watermark (otherwise failed rows are skipped forever).
            _pullUpsertFailed = false;
            if (root.TryGetProperty("items", out var items))
                pulled += UpsertItems(items);

            if (root.TryGetProperty("customers", out var customers))
                pulled += UpsertParties(customers, "Customer");

            if (root.TryGetProperty("suppliers", out var suppliers))
                pulled += UpsertParties(suppliers, "Supplier");

            if (root.TryGetProperty("sales", out var sales))
                pulled += UpsertSales(sales);

            if (root.TryGetProperty("purchases", out var purchases))
                pulled += UpsertPurchases(purchases);

            if (root.TryGetProperty("purchase_returns", out var purchaseReturns))
                pulled += UpsertPurchaseReturns(purchaseReturns);

            if (root.TryGetProperty("sale_returns", out var saleReturns))
                pulled += UpsertSaleReturns(saleReturns);

            // ─── Simple tables (direct mirror upsert) ───
            // These were previously not synced to desktop — they now all come
            // from the server and are written into the matching SQLite tables.
            var simpleTables = new[]
            {
                "supplier_payments", "expenses",
                "promotions", "servicings", "warranties", "bookings",
                "customer_receives", "incomes", "deposit_withdraws",
                "installment_sales", "installment_sale_details",
                "installment_sale_payments",
                "damages", "damage_details",
                "transfers", "transfer_details",
                "quotations", "quotation_details",
                "attendances", "salaries", "salary_items",
                "employee_advance_payments", "wallet_transactions",
                "sales", "sale_details", "purchase_details", "sale_payments",
                "purchase_payments", "registers", "set_opening_stocks",
                "holds", "hold_details",
                "view_stock_detail",
                "installed_modules",
                "business_club_members", "business_club_transactions",
            };

            foreach (var t in simpleTables)
            {
                if (root.TryGetProperty(t, out var rows))
                    pulled += UpsertSimpleRows(rows, t);
            }

            // ─── Master mirror (outlets, payment_methods, item_categories, etc.) ───
            pulled += UpsertMirrors(root);

            // ─── Meta (units, brands, racks, etc. from /api/meta) ───
            pulled += await PullMetaAsync();

            // ─── Company profile (invoice header — logo/email/GSTIN cloud se) ───
            await RefreshCompanyProfileAsync();

            var serverTime = root.TryGetProperty("server_time", out var st) ? st.GetString() : null;
            if (!_pullUpsertFailed && !string.IsNullOrEmpty(serverTime))
                _api.SetSetting("last_sync_at", serverTime);

            Log("pull", "all", "", "ok", $"Pulled {pulled} records");
            return pulled;
        }

        private async Task RefreshCompanyProfileAsync()
        {
            try
            {
                var data = await _api.GetCompanyProfileAsync();
                if (data == null) return;

                var root = data.RootElement;
                string S(string k) => root.TryGetProperty(k, out var v) && v.ValueKind == JsonValueKind.String ? v.GetString() ?? "" : "";

                string name = S("business_name");
                if (string.IsNullOrEmpty(name)) name = S("name");
                string email = S("email");
                string phone = S("phone");
                string address = S("address");
                string gstin = S("tax_registration_no");
                string invoiceLogo = S("invoice_logo");
                string invLogoShow = S("inv_logo_is_show");

                using var conn = _db.GetConnection();
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"UPDATE companies SET
                                        name=@name, business_name=@name, email=@email, phone=@phone,
                                        address=@address, tax_registration_no=@gstin,
                                        invoice_logo=@logo, inv_logo_is_show=@logoShow
                                        WHERE id=1";
                    cmd.Parameters.AddWithValue("@name", name);
                    cmd.Parameters.AddWithValue("@email", email);
                    cmd.Parameters.AddWithValue("@phone", phone);
                    cmd.Parameters.AddWithValue("@address", address);
                    cmd.Parameters.AddWithValue("@gstin", gstin);
                    cmd.Parameters.AddWithValue("@logo", invoiceLogo);
                    cmd.Parameters.AddWithValue("@logoShow", invLogoShow);
                    cmd.ExecuteNonQuery();
                }

                if (!string.IsNullOrEmpty(invoiceLogo) && invLogoShow != "No")
                {
                    var logosDir = Path.Combine(
                        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                        "RashanKiDukan", "logos");
                    Directory.CreateDirectory(logosDir);
                    var dest = Path.Combine(logosDir, Path.GetFileName(invoiceLogo));
                    if (!File.Exists(dest))
                    {
                        var bytes = await _api.DownloadFileAsync("uploads/site_settings/" + invoiceLogo);
                        if (bytes != null && bytes.Length > 0)
                            File.WriteAllBytes(dest, bytes);
                    }
                }
            }
            catch (Exception ex)
            {
                LogService.Warn("RefreshCompanyProfile failed: " + ex.Message);
            }
        }

        private async Task<int> PullMetaAsync()
        {
            try
            {
                var (ok, message, data) = await _api.GetMetaAsync();
                if (!ok || data == null)
                {
                    Log("pull", "meta", "", "error", message);
                    return 0;
                }

                var root = data.RootElement;
                int count = 0;

                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();

                if (root.TryGetProperty("units", out var units))
                    count += UpsertConfigRows(conn, units, "Units", "id", "unit_name", "UnitName", "description", "Description");
                if (root.TryGetProperty("brands", out var brands))
                    count += UpsertConfigRows(conn, brands, "Brands", "id", "name", "Name", "description", "Description");
                if (root.TryGetProperty("categories", out var categories))
                    count += UpsertConfigRows(conn, categories, "ItemCategories", "id", "name", "Name", "description", "Description");
                if (root.TryGetProperty("suppliers", out var suppliers))
                    count += UpsertConfigRows(conn, suppliers, "Suppliers", "id", "name", "Name", null, null);
                if (root.TryGetProperty("racks", out var racks))
                    count += UpsertConfigRows(conn, racks, "Racks", "id", "name", "Name", "description", "Description");
                if (root.TryGetProperty("variations", out var variations))
                    count += UpsertConfigRows(conn, variations, "Variations", "id", "variation_name", "VariationName", "variation_value", "VariationValue");
                if (root.TryGetProperty("expense_categories", out var expenseCategories))
                    count += UpsertConfigRows(conn, expenseCategories, "ExpenseCategories", "id", "name", "Name", "description", "Description");
                if (root.TryGetProperty("payment_methods", out var paymentMethods))
                    count += UpsertPaymentMethods(conn, paymentMethods);
                if (root.TryGetProperty("counters", out var counters))
                    count += UpsertCounters(conn, counters);

                txn.Commit();
                Log("pull", "meta", "", "ok", $"Synced {count} lookup values (units/brands/categories/suppliers/racks/variations/payment_methods)");
                return count;
            }
            catch (Exception ex)
            {
                LogService.Error("PullMetaAsync failed", ex);
                _pullUpsertFailed = true;
                Log("pull", "meta", "", "error", ex.Message);
                return 0;
            }
        }

        /// <summary>
        /// Upserts server lookup rows into a local config table. Rows that were
        /// locally created/edited (SyncStatus != 'Synced') are never overwritten.
        /// </summary>
        private static int UpsertConfigRows(SqliteConnection conn, JsonElement rows, string table,
            string idProp, string jsonNameProp, string sqlNameCol, string? jsonDescProp, string? sqlDescCol)
        {
            int count = 0;
            foreach (var r in rows.EnumerateArray())
            {
                long id = 0;
                if (r.TryGetProperty(idProp, out var i) && i.ValueKind == JsonValueKind.Number)
                    id = i.GetInt64();
                string name = r.TryGetProperty(jsonNameProp, out var n) ? n.GetString() ?? "" : "";
                if (id == 0 || name == "") continue;
                string desc = "";
                if (jsonDescProp != null && r.TryGetProperty(jsonDescProp, out var d) && d.ValueKind == JsonValueKind.String)
                    desc = d.GetString() ?? "";
                // del_status bhi sync karo — server par delete ki gai categories/brands
                // software me bhi turant hat jayein (tombstone propagation).
                string delStatus = "Live";
                if (r.TryGetProperty("del_status", out var ds) && ds.ValueKind == JsonValueKind.String)
                    delStatus = ds.GetString() ?? "Live";

                using var find = conn.CreateCommand();
                find.CommandText = $"SELECT Id, SyncStatus FROM {table} WHERE ServerId=@id OR Id=@id LIMIT 1";
                find.Parameters.AddWithValue("@id", id);
                long existingRowId = 0;
                string? status = null;
                using (var fr = find.ExecuteReader())
                {
                    if (fr.Read())
                    {
                        existingRowId = fr.GetInt64(0);
                        status = fr["SyncStatus"] as string;
                    }
                }

                if (status == "Local") continue;

                // Same-name wali LOCAL pending row ho to insert mat karo — push khud
                // server par dedupe karke ServerId set kar dega (Parle double-add bug).
                if (existingRowId == 0 && !string.IsNullOrEmpty(name))
                {
                    using var byName = conn.CreateCommand();
                    byName.CommandText = $"SELECT COUNT(*) FROM {table} WHERE lower({sqlNameCol})=lower(@n) AND SyncStatus='Local'";
                    byName.Parameters.AddWithValue("@n", name);
                    if (Convert.ToInt64(byName.ExecuteScalar()) > 0) continue;
                }

                if (existingRowId != 0)
                {
                    // Delete duplicate rows matched only by ServerId (locally-created rows
                    // keep a negative/local Id — if a stale row with the server Id also exists,
                    // fold it into the canonical row so the table never holds two entries).
                    // Match by ServerId OR by the server Id column so an orphan row with
                    // ServerId=0 (created by an earlier pull) is cleaned up too.
                    using var cleanup = conn.CreateCommand();
                    cleanup.CommandText = $"DELETE FROM {table} WHERE (ServerId=@id OR Id=@id) AND Id<>@rid";
                    cleanup.Parameters.AddWithValue("@id", id);
                    cleanup.Parameters.AddWithValue("@rid", existingRowId);
                    cleanup.ExecuteNonQuery();

                    using var upd = conn.CreateCommand();
                    upd.CommandText = sqlDescCol != null
                        ? $"UPDATE {table} SET {sqlNameCol}=@name, {sqlDescCol}=@desc, del_status=@del, ServerId=@id, SyncStatus='Synced' WHERE Id=@rid"
                        : $"UPDATE {table} SET {sqlNameCol}=@name, del_status=@del, ServerId=@id, SyncStatus='Synced' WHERE Id=@rid";
                    upd.Parameters.AddWithValue("@name", name);
                    upd.Parameters.AddWithValue("@desc", desc);
                    upd.Parameters.AddWithValue("@del", delStatus);
                    upd.Parameters.AddWithValue("@id", id);
                    upd.Parameters.AddWithValue("@rid", existingRowId);
                    upd.ExecuteNonQuery();
                }
                else
                {
                    using var ins = conn.CreateCommand();
                    ins.CommandText = sqlDescCol != null
                        ? $"INSERT INTO {table} (Id, {sqlNameCol}, {sqlDescCol}, del_status, ServerId, SyncStatus) VALUES (@id, @name, @desc, @del, @id, 'Synced')"
                        : $"INSERT INTO {table} (Id, {sqlNameCol}, del_status, ServerId, SyncStatus) VALUES (@id, @name, @del, @id, 'Synced')";
                    ins.Parameters.AddWithValue("@id", id);
                    ins.Parameters.AddWithValue("@name", name);
                    ins.Parameters.AddWithValue("@desc", desc);
                    ins.Parameters.AddWithValue("@del", delStatus);
                    ins.ExecuteNonQuery();
                }
                count++;
            }
            return count;
        }

        private int UpsertMirrors(JsonElement root)
        {
            int count = 0;
            // items/customers/suppliers are handled by UpsertItems/UpsertParties
            // — mirroring them again here would re-process (and overwrite) those
            // rows, so they are deliberately excluded from the mirrored set.
            if (root.TryGetProperty("master", out var master) && master.ValueKind == JsonValueKind.Object)
            {
                foreach (var prop in master.EnumerateObject())
                {
                    if (prop.Value.ValueKind != JsonValueKind.Array) continue;
                    // Laravel "users" mirrors to local "employees" (SQLite treats users/Users as one table)
                    string localTable = prop.Name == "users" ? "employees" : prop.Name;

                    // Mapping tables (no 'id' column) — wipe and rewrite fully
                    if (localTable == "role_has_permissions" || localTable == "model_has_roles")
                    {
                        count += TryMirrorMappingTable(localTable, prop.Value);
                        continue;
                    }

                    count += TryMirror(localTable, prop.Value);

                    // ── ROLES TOMBSTONE (SYNC_DELETE_PLAN STEP 4) ──
                    // Web se role HARD-delete hota hai (del_status column nahi hai)
                    // → pull mein wo row aata hi nahi. Mirror ke baad: server list
                    // mein na hone wali SYNCED local roles hatao. Guards: local
                    // pending rows (SyncStatus='Local') kabhi nahi; built-in roles
                    // (Super Admin / admin) kabhi nahi; users wali role kabhi nahi.
                    if (localTable == "roles")
                    {
                        try
                        {
                            var serverRoleIds = new HashSet<long>();
                            foreach (var rr in prop.Value.EnumerateArray())
                            {
                                if (rr.TryGetProperty("id", out var idp) && idp.ValueKind == System.Text.Json.JsonValueKind.Number)
                                    serverRoleIds.Add(idp.GetInt64());
                            }
                            using var rc = _db.GetConnection();
                            using (var del = rc.CreateCommand())
                            {
                                del.CommandText = @"DELETE FROM roles
                                    WHERE ServerId > 0
                                      AND (SyncStatus IS NULL OR SyncStatus != 'Local')
                                      AND lower(name) NOT IN ('super admin', 'admin', 'administrator')
                                      AND NOT EXISTS (SELECT 1 FROM model_has_roles WHERE role_id = roles.Id)
                                      AND ServerId NOT IN (" + (serverRoleIds.Count > 0 ? string.Join(",", serverRoleIds) : "0") + ")";
                                del.ExecuteNonQuery();
                            }
                        }
                        catch (Exception ex)
                        {
                            LogService.Error("roles tombstone cleanup failed", ex);
                        }
                    }
                }
            }
            return count;
        }

        /// <summary>
        /// Wipe-and-rewrite for mapping tables (role_has_permissions, model_has_roles)
        /// that don't have an 'id' column. Small tables — full replace is safe.
        /// </summary>
        private int TryMirrorMappingTable(string table, JsonElement rows)
        {
            try
            {
                using var conn = _db.GetConnection();

                // Check table exists
                using (var chk = conn.CreateCommand())
                {
                    chk.CommandText = "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND lower(name)=lower(@t)";
                    chk.Parameters.AddWithValue("@t", table);
                    if ((long)chk.ExecuteScalar() == 0) return 0;
                }

                using var txn = conn.BeginTransaction();

                // Wipe existing data
                using (var del = conn.CreateCommand())
                {
                    del.CommandText = $"DELETE FROM \"{table}\"";
                    del.ExecuteNonQuery();
                }

                // Get columns
                var cols = new List<string>();
                using (var q = conn.CreateCommand())
                {
                    q.CommandText = $"PRAGMA table_info(\"{table}\")";
                    using var r = q.ExecuteReader();
                    while (r.Read()) cols.Add(r.GetString(1));
                }

                int count = 0;
                foreach (var row in rows.EnumerateArray())
                {
                    var values = new List<(string Col, object Val)>();
                    foreach (var col in cols)
                    {
                        if (row.TryGetProperty(col, out var v))
                        {
                            values.Add((col, v.ValueKind switch
                            {
                                System.Text.Json.JsonValueKind.Number => v.GetInt64(),
                                System.Text.Json.JsonValueKind.String => v.GetString() ?? "",
                                System.Text.Json.JsonValueKind.Null => DBNull.Value,
                                _ => v.GetRawText()
                            }));
                        }
                    }
                    if (values.Count == 0) continue;

                    using var ins = conn.CreateCommand();
                    ins.CommandText = $"INSERT OR IGNORE INTO \"{table}\" ({string.Join(',', values.Select(v => $"\"{v.Col}\""))}) VALUES ({string.Join(',', values.Select((_, i) => $"@p{i}"))})";
                    for (int i = 0; i < values.Count; i++)
                        ins.Parameters.AddWithValue($"@p{i}", values[i].Val);
                    ins.ExecuteNonQuery();
                    count++;
                }

                txn.Commit();
                return count;
            }
            catch (Exception ex)
            {
                LogService.Error($"TryMirrorMappingTable({table}) failed", ex);
                _pullUpsertFailed = true;
                return 0;
            }
        }

        private int TryMirror(string table, JsonElement rows)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();
                int n = UpsertMirrorRows(conn, table, rows);
                txn.Commit();
                return n;
            }
            catch (Exception ex)
            {
                LogService.Error($"TryMirror({table}) failed", ex);
                _pullUpsertFailed = true;
                Log("pull", "mirror-" + table, "", "error", ex.Message);
                return 0;
            }
        }

        /// <summary>
        /// Generic upsert of server rows into a local mirror table. Only JSON
        /// properties whose names match an existing local column are written;
        /// rows the user created locally (SyncStatus='Local') are never
        /// overwritten.
        /// </summary>
        private static int UpsertMirrorRows(SqliteConnection conn, string table, JsonElement rows)
        {
            string actual = table;
            using (var q = conn.CreateCommand())
            {
                // Exact-case match first so the lowercase mirror table (e.g. "users")
                // wins over the app's login table with the same name (e.g. "Users").
                q.CommandText = "SELECT name FROM sqlite_master WHERE type='table' AND name=@t LIMIT 1";
                q.Parameters.AddWithValue("@t", table);
                var nm = q.ExecuteScalar() as string;
                if (nm == null)
                {
                    using var q2 = conn.CreateCommand();
                    q2.CommandText = "SELECT name FROM sqlite_master WHERE type='table' AND lower(name)=lower(@t) LIMIT 1";
                    q2.Parameters.AddWithValue("@t", table);
                    nm = q2.ExecuteScalar() as string;
                }
                if (nm == null) return 0;
                actual = nm;
            }

            // Never write into the app's login table (it has a "Username" column).
            using (var guard = conn.CreateCommand())
            {
                guard.CommandText = $"SELECT COUNT(*) FROM pragma_table_info(\"{actual}\") WHERE lower(name)='username'";
                if (Convert.ToInt64(guard.ExecuteScalar()) > 0) return 0;
            }

            var cols = new List<string>();
            using (var q = conn.CreateCommand())
            {
                q.CommandText = $"PRAGMA table_info(\"{actual}\")";
                using var r = q.ExecuteReader();
                while (r.Read()) cols.Add((r["name"] as string) ?? "");
            }
            var lowerMap = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
            foreach (var c in cols) lowerMap[c.ToLowerInvariant()] = c;
            bool hasSyncStatus = cols.Exists(c => string.Equals(c, "SyncStatus", StringComparison.OrdinalIgnoreCase));
            bool hasServerId = cols.Exists(c => string.Equals(c, "ServerId", StringComparison.OrdinalIgnoreCase));

            int count = 0;
            foreach (var row in rows.EnumerateArray())
            {
                long id = 0;
                if (row.TryGetProperty("id", out var idp) && idp.ValueKind == JsonValueKind.Number) id = idp.GetInt64();
                if (id == 0) continue;

                // Match by ServerId OR Id: locally-created rows keep a negative/local Id,
                // so an Id-only lookup misses them and the server row is inserted twice.
                long existingRowId = 0;
                string? status = null;
                using (var st = conn.CreateCommand())
                {
                    st.CommandText = hasServerId
                        ? (hasSyncStatus
                            ? $"SELECT Id, SyncStatus FROM \"{actual}\" WHERE ServerId=@id OR Id=@id LIMIT 1"
                            : $"SELECT Id FROM \"{actual}\" WHERE ServerId=@id OR Id=@id LIMIT 1")
                        : (hasSyncStatus
                            ? $"SELECT Id, SyncStatus FROM \"{actual}\" WHERE Id=@id LIMIT 1"
                            : $"SELECT Id FROM \"{actual}\" WHERE Id=@id LIMIT 1");
                    st.Parameters.AddWithValue("@id", id);
                    using var sr = st.ExecuteReader();
                    if (sr.Read())
                    {
                        existingRowId = sr.GetInt64(0);
                        if (hasSyncStatus) status = sr["SyncStatus"] as string;
                    }
                }
                if (hasSyncStatus && status == "Local") continue;

                // Multi-counter race: push se pehle pull aaye to same-name wali LOCAL
                // pending row ke saamne server copy insert mat karo (Parle bug).
                if (hasSyncStatus && existingRowId == 0)
                {
                    string nameCol = cols.Find(c => string.Equals(c, "Name", StringComparison.OrdinalIgnoreCase));
                    if (nameCol == null) nameCol = cols.Find(c => string.Equals(c, "UnitName", StringComparison.OrdinalIgnoreCase));
                    if (nameCol != null)
                    {
                        string nameProp = string.Equals(nameCol, "UnitName", StringComparison.OrdinalIgnoreCase) ? "unit_name" : "name";
                        if (row.TryGetProperty(nameProp, out var nv) && nv.ValueKind == JsonValueKind.String)
                        {
                            using var byName = conn.CreateCommand();
                            byName.CommandText = $"SELECT COUNT(*) FROM \"{actual}\" WHERE lower({nameCol})=lower(@n) AND SyncStatus='Local'";
                            byName.Parameters.AddWithValue("@n", nv.GetString());
                            if (Convert.ToInt64(byName.ExecuteScalar()) > 0) continue;
                        }
                    }
                }

                var setCols = new List<string>();
                var parms = new List<(string name, object? val)>();
                foreach (var prop in row.EnumerateObject())
                {
                    string key = prop.Name.ToLowerInvariant();
                    if (key == "id" || !lowerMap.TryGetValue(key, out var real)) continue;
                    object? val = null;
                    switch (prop.Value.ValueKind)
                    {
                        case JsonValueKind.String: val = prop.Value.GetString(); break;
                        case JsonValueKind.Number: val = prop.Value.TryGetInt64(out var l) ? (object)l : prop.Value.GetDouble(); break;
                        case JsonValueKind.True: val = 1L; break;
                        case JsonValueKind.False: val = 0L; break;
                    }
                    setCols.Add(real);
                    parms.Add((key, val));
                }
                if (setCols.Count == 0) continue;

                using var cmd = conn.CreateCommand();
                if (existingRowId != 0)
                {
                    // Fold stale duplicates (same ServerId, different Id) into the
                    // canonical row so the table never holds two entries.
                    if (hasServerId)
                    {
                        using var cleanup = conn.CreateCommand();
                        cleanup.CommandText = $"DELETE FROM \"{actual}\" WHERE ServerId=@id AND Id<>@rid";
                        cleanup.Parameters.AddWithValue("@id", id);
                        cleanup.Parameters.AddWithValue("@rid", existingRowId);
                        cleanup.ExecuteNonQuery();
                    }
                    var sets = new List<string>();
                    for (int i = 0; i < setCols.Count; i++) sets.Add($"{setCols[i]}=@p{i}");
                    if (hasServerId) sets.Add("ServerId=@id");
                    cmd.CommandText = $"UPDATE \"{actual}\" SET {string.Join(", ", sets)} WHERE Id=@rid";
                    cmd.Parameters.AddWithValue("@rid", existingRowId);
                    cmd.Parameters.AddWithValue("@id", id);
                    for (int i = 0; i < setCols.Count; i++) cmd.Parameters.AddWithValue("@p" + i, parms[i].val ?? DBNull.Value);
                }
                else
                {
                    var colsList = new List<string> { "Id" };
                    var valsList = new List<string> { "@id" };
                    if (hasServerId) { colsList.Add("ServerId"); valsList.Add("@id"); }
                    for (int i = 0; i < setCols.Count; i++)
                    {
                        colsList.Add(setCols[i]);
                        valsList.Add("@p" + i);
                    }
                    cmd.CommandText = $"INSERT INTO \"{actual}\" ({string.Join(", ", colsList)}) VALUES ({string.Join(", ", valsList)})";
                    cmd.Parameters.AddWithValue("@id", id);
                    for (int i = 0; i < setCols.Count; i++) cmd.Parameters.AddWithValue("@p" + i, parms[i].val ?? DBNull.Value);
                }
                cmd.ExecuteNonQuery();
                count++;
            }
            return count;
        }

        private int UpsertItems(JsonElement items)
        {
            int count = 0;
            try
            {
                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();

                foreach (var item in items.EnumerateArray())
                {
                    long id = item.TryGetProperty("id", out var i) && i.ValueKind == JsonValueKind.Number ? i.GetInt64() : 0;
                    string code = item.TryGetProperty("code", out var c) ? c.GetString() ?? "" : "";
                    string name = item.TryGetProperty("name", out var n) ? n.GetString() ?? "" : "";
                    if (id == 0 || name == "") continue;
                    if (code == "") code = "IT" + id;

                    // ── Tombstone: server ne ye item delete kar di ──
                    string delStatus = item.TryGetProperty("del_status", out var ds) ? ds.GetString() ?? "Live" : "Live";
                    if (delStatus == "Deleted")
                    {
                        using (var tomb = conn.CreateCommand())
                        {
                            tomb.CommandText = @"UPDATE items SET del_status='Deleted', SyncStatus='Synced'
                                                 WHERE ServerId=@id AND (SyncStatus IS NULL OR SyncStatus != 'Local')";
                            tomb.Parameters.AddWithValue("@id", id);
                            tomb.ExecuteNonQuery();
                        }
                        using (var tombM = conn.CreateCommand())
                        {
                            tombM.CommandText = "UPDATE Master1 SET DelStatus='Deleted', IsActive=0, UpdatedAt=datetime('now') WHERE ServerId=@id AND MasterType='Item'";
                            tombM.Parameters.AddWithValue("@id", id);
                            tombM.ExecuteNonQuery();
                        }
                        count++;
                        continue;
                    }

                    double stock      = item.TryGetProperty("stock_quantity", out var sq) ? sq.GetDouble() : 0;
                    double salePrice  = item.TryGetProperty("sale_price", out var sp) ? sp.GetDouble() : 0;
                    double purPrice   = item.TryGetProperty("purchase_price", out var pp) ? pp.GetDouble() : 0;
                    double mrp        = item.TryGetProperty("mrp_price", out var mp) ? mp.GetDouble() : 0;
                    double whole      = item.TryGetProperty("whole_sale_price", out var ws) ? ws.GetDouble() : 0;
                    double margin     = item.TryGetProperty("profit_margin", out var pmg) ? pmg.GetDouble() : 0;
                    double alert      = item.TryGetProperty("alert_quantity", out var aq) ? aq.GetDouble() : 0;
                    long   loyalty    = item.TryGetProperty("loyalty_point", out var lp) && lp.ValueKind == JsonValueKind.Number ? lp.GetInt64() : 0;
                    double conv       = item.TryGetProperty("conversion_rate", out var cr) ? cr.GetDouble() : 1;
                    double avg3       = item.TryGetProperty("last_three_purchase_avg", out var a3) ? a3.GetDouble() : 0;
                    double lpp        = item.TryGetProperty("last_purchase_price", out var lp2) ? lp2.GetDouble() : 0;
                    long syncVersion  = item.TryGetProperty("sync_version", out var sv) && sv.ValueKind == JsonValueKind.Number ? sv.GetInt64() : 1;
                    bool   active     = !item.TryGetProperty("enable_disable_status", out var ena) || ena.ValueKind != JsonValueKind.Number || ena.GetInt32() != 0;
                    string taxCat     = item.TryGetProperty("tax_string", out var ts) ? ts.GetString() ?? "" : "";
                    string taxType    = item.TryGetProperty("tax_type", out var tt) ? tt.GetString() ?? "Exclusive" : "Exclusive";
                    string itemType   = item.TryGetProperty("type", out var ty) ? ty.GetString() ?? "" : "";
                    string unitType   = item.TryGetProperty("unit_type", out var ut) ? ut.GetString() ?? "" : "";
                    string alias      = item.TryGetProperty("alternative_name", out var al) ? al.GetString() ?? "" : "";
                    string hsn        = item.TryGetProperty("hsn_code", out var h) ? h.GetString() ?? "" : "";
                    string warranty   = item.TryGetProperty("warranty", out var wa) ? wa.GetString() ?? "" : "";
                    string warrantyDt = item.TryGetProperty("warranty_date", out var wd) ? wd.GetString() ?? "" : "";
                    string guarantee  = item.TryGetProperty("guarantee", out var gu) ? gu.GetString() ?? "" : "";
                    string guaranteeDt= item.TryGetProperty("guarantee_date", out var gd) ? gd.GetString() ?? "" : "";
                    long   catId      = item.TryGetProperty("category_id", out var ci) && ci.ValueKind == JsonValueKind.Number ? ci.GetInt64() : 0;
                    long   brandId    = item.TryGetProperty("brand_id", out var bi) && bi.ValueKind == JsonValueKind.Number ? bi.GetInt64() : 0;
                    long   suppId     = item.TryGetProperty("supplier_id", out var si) && si.ValueKind == JsonValueKind.Number ? si.GetInt64() : 0;
                    long   parentId   = item.TryGetProperty("parent_id", out var pi) && pi.ValueKind == JsonValueKind.Number ? pi.GetInt64() : 0;
                    long   saleUnitId = item.TryGetProperty("sale_unit_id", out var sui) && sui.ValueKind == JsonValueKind.Number ? sui.GetInt64() : 0;
                    long   purUnitId  = item.TryGetProperty("purchase_unit_id", out var pui) && pui.ValueKind == JsonValueKind.Number ? pui.GetInt64() : 0;
                    string saleUnit   = saleUnitId > 0 ? GetUnitName(conn, saleUnitId) : unitType;

                    // ── 1. Write into items table (lowercase, Laravel mirror) ──────
                    // Check if locally created (don't overwrite). Match by ServerId OR
                    // Id: locally-created rows keep a negative/local Id, so an Id-only
                    // lookup misses them and the same item gets inserted twice.
                    bool skipItems = false;
                    long existingItemId = 0;
                    using (var chk = conn.CreateCommand())
                    {
                        chk.CommandText = "SELECT Id, SyncStatus FROM items WHERE ServerId=@id OR Id=@id LIMIT 1";
                        chk.Parameters.AddWithValue("@id", id);
                        using var chkReader = chk.ExecuteReader();
                        if (chkReader.Read())
                        {
                            existingItemId = chkReader.GetInt64(0);
                            skipItems = (chkReader["SyncStatus"] as string) == "Local";
                        }
                    }

                    // Multi-counter race: push se pehle pull aaye to same-code wali
                    // LOCAL pending item ke saamne server copy insert mat karo.
                    if (!skipItems && existingItemId == 0 && !string.IsNullOrEmpty(code))
                    {
                        using var byCode = conn.CreateCommand();
                        byCode.CommandText = "SELECT COUNT(*) FROM items WHERE lower(code)=lower(@c) AND SyncStatus='Local'";
                        byCode.Parameters.AddWithValue("@c", code);
                        if (Convert.ToInt64(byCode.ExecuteScalar()) > 0) skipItems = true;
                    }

                    if (!skipItems)
                    {
                        long? taxId = ResolveTaxId(conn, taxCat);

                        void BindItemParams(SqliteCommand c)
                        {
                            c.Parameters.AddWithValue("@id", id);
                            c.Parameters.AddWithValue("@name", name);
                            c.Parameters.AddWithValue("@code", code);
                            c.Parameters.AddWithValue("@alias", alias);
                            c.Parameters.AddWithValue("@itype", itemType);
                            c.Parameters.AddWithValue("@catid", catId > 0 ? (object)catId : DBNull.Value);
                            c.Parameters.AddWithValue("@brandid", brandId > 0 ? (object)brandId : DBNull.Value);
                            c.Parameters.AddWithValue("@suppid", suppId > 0 ? (object)suppId : DBNull.Value);
                            c.Parameters.AddWithValue("@parentid", parentId > 0 ? (object)parentId : DBNull.Value);
                            c.Parameters.AddWithValue("@hsn", hsn);
                            c.Parameters.AddWithValue("@unittype", unitType);
                            c.Parameters.AddWithValue("@sauid", saleUnitId > 0 ? (object)saleUnitId : DBNull.Value);
                            c.Parameters.AddWithValue("@puuid", purUnitId > 0 ? (object)purUnitId : DBNull.Value);
                            c.Parameters.AddWithValue("@conv", conv);
                            c.Parameters.AddWithValue("@mrp", mrp);
                            c.Parameters.AddWithValue("@sale", salePrice);
                            c.Parameters.AddWithValue("@whole", whole);
                            c.Parameters.AddWithValue("@pur", purPrice);
                            c.Parameters.AddWithValue("@margin", margin);
                            c.Parameters.AddWithValue("@alert", alert);
                            c.Parameters.AddWithValue("@loyalty", loyalty);
                            c.Parameters.AddWithValue("@warranty", warranty);
                            c.Parameters.AddWithValue("@warrantydt", warrantyDt);
                            c.Parameters.AddWithValue("@guarantee", guarantee);
                            c.Parameters.AddWithValue("@guaranteedt", guaranteeDt);
                            c.Parameters.AddWithValue("@taxstr", taxCat);
                            c.Parameters.AddWithValue("@taxtype", taxType);
                            c.Parameters.AddWithValue("@taxid", taxId.HasValue ? (object)taxId.Value : DBNull.Value);
                            c.Parameters.AddWithValue("@active", active ? 1 : 0);
                            c.Parameters.AddWithValue("@stock", stock);
                            c.Parameters.AddWithValue("@avg3", avg3);
                            c.Parameters.AddWithValue("@lpp", lpp);
                            c.Parameters.AddWithValue("@sver", syncVersion);
                        }

                        if (existingItemId != 0)
                        {
                            // Fold stale duplicates (same ServerId, different Id) into the
                            // canonical row so the item is never duplicated.
                            using var cleanup = conn.CreateCommand();
                            cleanup.CommandText = "DELETE FROM items WHERE ServerId=@id AND Id<>@rid";
                            cleanup.Parameters.AddWithValue("@id", id);
                            cleanup.Parameters.AddWithValue("@rid", existingItemId);
                            cleanup.ExecuteNonQuery();

                            using var ic = conn.CreateCommand();
                            ic.CommandText = @"UPDATE items SET
                                name=@name, code=@code, alternative_name=@alias, type=@itype,
                                category_id=@catid, brand_id=@brandid, supplier_id=@suppid, parent_id=@parentid,
                                hsn_code=@hsn, unit_type=@unittype, sale_unit_id=@sauid, purchase_unit_id=@puuid,
                                conversion_rate=@conv, mrp_price=@mrp, sale_price=@sale, whole_sale_price=@whole,
                                purchase_price=@pur, profit_margin=@margin, alert_quantity=@alert,
                                loyalty_point=@loyalty, warranty=@warranty, warranty_date=@warrantydt,
                                guarantee=@guarantee, guarantee_date=@guaranteedt,
                                tax_string=@taxstr, tax_type=@taxtype, applicable_tax_id=@taxid,
                                enable_disable_status=@active,
                                last_three_purchase_avg=@avg3, last_purchase_price=@lpp,
                                stock_quantity=MAX(@stock, 0), ServerId=@id, SyncStatus='Synced', SyncVersion=@sver, updated_at=datetime('now')
                                WHERE Id=@rid";
                            ic.Parameters.AddWithValue("@rid", existingItemId);
                            BindItemParams(ic);
                            ic.ExecuteNonQuery();
                        }
                        else
                        {
                            using var ic = conn.CreateCommand();
                            ic.CommandText = @"INSERT INTO items
                                (id, name, code, alternative_name, type, category_id, brand_id, supplier_id, parent_id,
                                 hsn_code, unit_type, sale_unit_id, purchase_unit_id, conversion_rate,
                                 mrp_price, sale_price, whole_sale_price, purchase_price, profit_margin,
                                 alert_quantity, loyalty_point, warranty, warranty_date, guarantee, guarantee_date,
                                 tax_string, tax_type, applicable_tax_id, enable_disable_status, stock_quantity,
                                 last_three_purchase_avg, last_purchase_price,
                                 del_status, ServerId, SyncStatus, SyncVersion, updated_at)
                                VALUES
                                (@id,@name,@code,@alias,@itype,@catid,@brandid,@suppid,@parentid,
                                 @hsn,@unittype,@sauid,@puuid,@conv,
                                 @mrp,@sale,@whole,@pur,@margin,
                                 @alert,@loyalty,@warranty,@warrantydt,@guarantee,@guaranteedt,
                                 @taxstr,@taxtype,@taxid,@active,@stock,
                                 @avg3,@lpp,
                                 'Live',@id,'Synced',@sver,datetime('now'))
                                ON CONFLICT(id) DO UPDATE SET
                                name=@name, code=@code, alternative_name=@alias, type=@itype,
                                category_id=@catid, brand_id=@brandid, supplier_id=@suppid, parent_id=@parentid,
                                hsn_code=@hsn, unit_type=@unittype, sale_unit_id=@sauid, purchase_unit_id=@puuid,
                                conversion_rate=@conv, mrp_price=@mrp, sale_price=@sale, whole_sale_price=@whole,
                                purchase_price=@pur, profit_margin=@margin, alert_quantity=@alert,
                                loyalty_point=@loyalty, warranty=@warranty, warranty_date=@warrantydt,
                                guarantee=@guarantee, guarantee_date=@guaranteedt,
                                tax_string=@taxstr, tax_type=@taxtype, applicable_tax_id=@taxid,
                                enable_disable_status=@active,
                                last_three_purchase_avg=@avg3, last_purchase_price=@lpp,
                                stock_quantity=MAX(@stock, 0), ServerId=@id, SyncStatus='Synced', SyncVersion=@sver, updated_at=datetime('now')";
                            BindItemParams(ic);
                            ic.ExecuteNonQuery();
                        }

                        // ── 2. Also write into Master1 (legacy Busy-style, used by POS page) ──
                        // A local-pending Master1 row (edited at the POS) must never be
                        // clobbered by the server copy — preserve local data.
                        bool master1Pending = false;
                        using (var mguard = conn.CreateCommand())
                        {
                            mguard.CommandText = "SELECT SyncStatus FROM Master1 WHERE Code=@c AND MasterType='Item' LIMIT 1";
                            mguard.Parameters.AddWithValue("@c", code);
                            master1Pending = (mguard.ExecuteScalar() as string) == "Local";
                        }
                        if (!master1Pending)
                        {
                            using var mc = conn.CreateCommand();
                    mc.CommandText = @"INSERT INTO Master1
                        (Code, Name, AliasName, MasterType, HSNCode, MRP, SaleRate, PurchaseRate,
                         TaxCategory, TaxType, Unit, MainUnit, CurrentStock, IsActive,
                         ServerId, SyncStatus, UpdatedAt,
                         ItemType, CategoryId, BrandId, SupplierId, UnitType, SaleUnitId, PurchaseUnitId,
                         ConversionRate, WholeSalePrice, ProfitMargin, AlertQty, LoyaltyPoint,
                         Warranty, WarrantyDate, Guarantee, GuaranteeDate)
                        VALUES
                        (@code,@name,@alias,'Item',@hsn,@mrp,@sale,@pur,
                         @tax,@taxtype,@saleunit,@saleunit,MAX(@stock,0),@active,
                         @sid,'Synced',datetime('now'),
                         @itype,@catid,@brandid,@suppid,@unittype,@sauid,@puuid,
                         @conv,@whole,@margin,@alert,@loyalty,
                         @warranty,@warrantydt,@guarantee,@guaranteedt)
                        ON CONFLICT(Code) DO UPDATE SET
                        Name=@name,AliasName=@alias,HSNCode=@hsn,MRP=@mrp,SaleRate=@sale,
                        PurchaseRate=@pur,TaxCategory=@tax,TaxType=@taxtype,Unit=@saleunit,MainUnit=@saleunit,
                        CurrentStock=MAX(@stock,0),IsActive=@active,ServerId=@sid,SyncStatus='Synced',UpdatedAt=datetime('now'),
                        ItemType=@itype,CategoryId=@catid,BrandId=@brandid,SupplierId=@suppid,
                        UnitType=@unittype,SaleUnitId=@sauid,PurchaseUnitId=@puuid,
                        ConversionRate=@conv,WholeSalePrice=@whole,
                        ProfitMargin=@margin,AlertQty=@alert,LoyaltyPoint=@loyalty,
                        Warranty=@warranty,WarrantyDate=@warrantydt,Guarantee=@guarantee,GuaranteeDate=@guaranteedt";
                    mc.Parameters.AddWithValue("@code", code);
                    mc.Parameters.AddWithValue("@name", name);
                    mc.Parameters.AddWithValue("@alias", alias);
                    mc.Parameters.AddWithValue("@hsn", hsn);
                    mc.Parameters.AddWithValue("@mrp", mrp);
                    mc.Parameters.AddWithValue("@sale", salePrice);
                    mc.Parameters.AddWithValue("@pur", purPrice);
                    mc.Parameters.AddWithValue("@tax", taxCat);
                    mc.Parameters.AddWithValue("@taxtype", taxType);
                    mc.Parameters.AddWithValue("@saleunit", saleUnit);
                    mc.Parameters.AddWithValue("@stock", stock);
                    mc.Parameters.AddWithValue("@active", active ? 1 : 0);
                    mc.Parameters.AddWithValue("@sid", id);
                    mc.Parameters.AddWithValue("@itype", itemType);
                    mc.Parameters.AddWithValue("@catid", catId > 0 ? (object)catId : DBNull.Value);
                    mc.Parameters.AddWithValue("@brandid", brandId > 0 ? (object)brandId : DBNull.Value);
                    mc.Parameters.AddWithValue("@suppid", suppId > 0 ? (object)suppId : DBNull.Value);
                    mc.Parameters.AddWithValue("@unittype", unitType);
                    mc.Parameters.AddWithValue("@sauid", saleUnitId > 0 ? (object)saleUnitId : DBNull.Value);
                    mc.Parameters.AddWithValue("@puuid", purUnitId > 0 ? (object)purUnitId : DBNull.Value);
                    mc.Parameters.AddWithValue("@conv", conv);
                    mc.Parameters.AddWithValue("@whole", whole);
                    mc.Parameters.AddWithValue("@margin", margin);
                    mc.Parameters.AddWithValue("@alert", alert);
                    mc.Parameters.AddWithValue("@loyalty", loyalty);
                    mc.Parameters.AddWithValue("@warranty", warranty);
                    mc.Parameters.AddWithValue("@warrantydt", warrantyDt);
                    mc.Parameters.AddWithValue("@guarantee", guarantee);
                    mc.Parameters.AddWithValue("@guaranteedt", guaranteeDt);
                    mc.ExecuteNonQuery();
                        }
                    }

                    count++;
                }
                txn.Commit();
            }
            catch (Exception ex)
            {
                LogService.Error("UpsertItems failed", ex);
                _pullUpsertFailed = true;
                Log("pull", "items", "", "error", ex.Message);
            }
            return count;
        }

        private int UpsertParties(JsonElement parties, string partyType)
        {
            int count = 0;
            string table = partyType == "Customer" ? "customers" : "suppliers";
            try
            {
                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();

                foreach (var p in parties.EnumerateArray())
                {
                    try
                    {
                    long id = p.TryGetProperty("id", out var i) && i.ValueKind == JsonValueKind.Number ? i.GetInt64() : 0;
                    string name = p.TryGetProperty("name", out var n) ? n.GetString() ?? "" : "";
                    if (id == 0 || name == "") continue;
                    // Walk-in Customer skip — generic entry, sync nahi karni
                    if (partyType == "Customer" && name.Trim().Equals("Walk-in Customer", StringComparison.OrdinalIgnoreCase)) continue;

                    string phone  = p.TryGetProperty("phone",      out var ph) ? ph.GetString() ?? "" : "";
                    string email  = p.TryGetProperty("email",      out var em) ? em.GetString() ?? "" : "";
                    string gstin  = p.TryGetProperty("gst_number", out var g)  ? g.GetString()  ?? "" : "";
                    string addr   = p.TryGetProperty("address",    out var a)  ? a.GetString()  ?? "" : "";
                    string city   = p.TryGetProperty("city",       out var ci) ? ci.GetString() ?? "" : "";
                    string zip    = p.TryGetProperty("postal_code",out var z)  ? z.GetString()  ?? "" : "";
                    double bal    = p.TryGetProperty("opening_balance", out var ob) ? ob.GetDouble() : 0;
                    string obType = p.TryGetProperty("opening_balance_type", out var obt) ? obt.GetString() ?? "Dr" : "Dr";
                    double limit  = p.TryGetProperty("credit_limit",    out var cl) ? cl.GetDouble() : 0;
                    string code   = (partyType == "Customer" ? "PC" : "PS") + id;
                    string delStatus = p.TryGetProperty("del_status", out var ds) ? ds.GetString() ?? "Live" : "Live";
                    string isInstCust = "No";
                    if (p.TryGetProperty("is_installment_customer", out var ic2))
                    {
                        if (ic2.ValueKind == JsonValueKind.String) isInstCust = ic2.GetString() ?? "No";
                        else if (ic2.ValueKind == JsonValueKind.Number) isInstCust = ic2.GetInt32() == 1 ? "Yes" : "No";
                        else if (ic2.ValueKind == JsonValueKind.True) isInstCust = "Yes";
                        else if (ic2.ValueKind == JsonValueKind.False) isInstCust = "No";
                    }
                    string workAddr   = p.TryGetProperty("work_address",   out var wa) ? wa.GetString() ?? "" : "";
                    string gName      = p.TryGetProperty("guarantor_name", out var gn) ? gn.GetString() ?? "" : "";
                    string gMobile    = p.TryGetProperty("guarantor_mobile",out var gm) ? gm.GetString() ?? "" : "";
                    long syncVersion  = p.TryGetProperty("sync_version", out var pv) && pv.ValueKind == JsonValueKind.Number ? pv.GetInt64() : 1;

                    // ── 0. Tombstone: server deleted this party ──
                    if (delStatus == "Deleted")
                    {
                        using (var dc = conn.CreateCommand())
                        {
                            dc.CommandText = $"UPDATE \"{table}\" SET del_status='Deleted', updated_at=datetime('now') WHERE ServerId=@id AND (SyncStatus IS NULL OR SyncStatus != 'Local')";
                            dc.Parameters.AddWithValue("@id", id);
                            dc.ExecuteNonQuery();
                        }
                        using (var dm = conn.CreateCommand())
                        {
                            dm.CommandText = "UPDATE Master1 SET IsActive=0, DelStatus='Deleted', UpdatedAt=datetime('now') WHERE Code=@code";
                            dm.Parameters.AddWithValue("@code", code);
                            dm.ExecuteNonQuery();
                        }
                        continue;
                    }

                    // ── 1. Write into customers / suppliers table ──────────────
                    // ServerId-first matching: a local party created with a positive
                    // auto-increment Id must never shadow — or be shadowed by — an
                    // unrelated server row that happens to share the same Id.
                    long tableRowId = 0;
                    string? tableStatus = null;
                    long tableRowServerId = 0;
                    bool adoptedLocalRow = false;

                    using (var chk = conn.CreateCommand())
                    {
                        chk.CommandText = $"SELECT Id, SyncStatus, IFNULL(ServerId,0) FROM \"{table}\" WHERE ServerId=@sid LIMIT 1";
                        chk.Parameters.AddWithValue("@sid", id);
                        using var chkReader = chk.ExecuteReader();
                        if (chkReader.Read())
                        {
                            tableRowId = chkReader.GetInt64(0);
                            tableStatus = chkReader["SyncStatus"] as string;
                        }
                    }

                    if (tableRowId == 0)
                    {
                        using (var chk2 = conn.CreateCommand())
                        {
                            chk2.CommandText = $"SELECT SyncStatus, IFNULL(ServerId,0) FROM \"{table}\" WHERE Id=@id LIMIT 1";
                            chk2.Parameters.AddWithValue("@id", id);
                            using var chk2Reader = chk2.ExecuteReader();
                            if (chk2Reader.Read())
                            {
                                tableRowId = id;
                                tableStatus = chk2Reader["SyncStatus"] as string;
                                tableRowServerId = chk2Reader.GetInt64(1);
                            }
                        }
                        // Positive auto-increment Id collision with a locally-created
                        // party — adopt it (assign the server id) instead of skipping
                        // the server row forever.
                        if (tableRowId != 0 && (tableStatus == "Local" || tableRowServerId <= 0))
                            adoptedLocalRow = true;

                        // Pull-before-push race: same party created locally whose push
                        // failed before the server id was assigned — adopt by name/phone
                        // (mirrors the Master1 adoption logic further down).
                        if (tableRowId == 0 && !string.IsNullOrEmpty(name))
                        {
                            string w = $"LOWER(TRIM(Name))=LOWER(TRIM(@name))";
                            if (!string.IsNullOrEmpty(phone))
                                w += $" OR (IFNULL(Phone,'')<>'' AND LOWER(TRIM(IFNULL(Phone,''))) = LOWER(TRIM(@phone)))";
                            using var ac = conn.CreateCommand();
                            ac.CommandText = $"SELECT Id FROM \"{table}\" WHERE (SyncStatus IS NULL OR SyncStatus='Local') AND del_status='Live' AND ({w}) ORDER BY Id LIMIT 1";
                            ac.Parameters.AddWithValue("@name", name);
                            if (!string.IsNullOrEmpty(phone)) ac.Parameters.AddWithValue("@phone", phone);
                            var av = ac.ExecuteScalar();
                            if (av != null)
                            {
                                tableRowId = Convert.ToInt64(av);
                                adoptedLocalRow = true;
                            }
                        }
                    }

                    if (adoptedLocalRow)
                    {
                        // Adopt: assign the server id and mark synced. The row's own
                        // data is preserved (never clobber a local pending row).
                        using var ad = conn.CreateCommand();
                        ad.CommandText = $"UPDATE \"{table}\" SET ServerId=@sid, SyncStatus='Synced', SyncError=NULL, updated_at=datetime('now') WHERE Id=@rid";
                        ad.Parameters.AddWithValue("@sid", id);
                        ad.Parameters.AddWithValue("@rid", tableRowId);
                        ad.ExecuteNonQuery();
                    }
                    else if (tableRowId != 0 && tableRowId != id)
                    {
                        // The server row already exists under a different local Id
                        // (negative mirror id / previously adopted row) — update it in
                        // place instead of inserting a duplicate.
                        if (partyType == "Customer")
                        {
                            using var uc = conn.CreateCommand();
                            uc.CommandText = @"UPDATE customers SET name=@name, email=@email, phone=@phone, address=@addr,
                                city=@city, postal_code=@zip, gst_number=@gstin,
                                opening_balance=@bal, opening_balance_type=@obt,
                                credit_limit=@limit, is_installment_customer=@isInstCust,
                                work_address=@workAddr, guarantor_name=@gName,
                                guarantor_mobile=@gMobile,
                                ServerId=@sid, SyncStatus='Synced', SyncVersion=@sver, del_status='Live', updated_at=datetime('now')
                                WHERE Id=@rid";
                            uc.Parameters.AddWithValue("@rid", tableRowId);
                            uc.Parameters.AddWithValue("@sid", id);
                            uc.Parameters.AddWithValue("@sver", syncVersion);
                            uc.Parameters.AddWithValue("@name", name);
                            uc.Parameters.AddWithValue("@email", email);
                            uc.Parameters.AddWithValue("@phone", phone);
                            uc.Parameters.AddWithValue("@addr", addr);
                            uc.Parameters.AddWithValue("@city", city);
                            uc.Parameters.AddWithValue("@zip", zip);
                            uc.Parameters.AddWithValue("@gstin", gstin);
                            uc.Parameters.AddWithValue("@bal", bal);
                            uc.Parameters.AddWithValue("@obt", obType);
                            uc.Parameters.AddWithValue("@limit", limit);
                            uc.Parameters.AddWithValue("@isInstCust", isInstCust);
                            uc.Parameters.AddWithValue("@workAddr", workAddr);
                            uc.Parameters.AddWithValue("@gName", gName);
                            uc.Parameters.AddWithValue("@gMobile", gMobile);
                            uc.ExecuteNonQuery();
                        }
                        else
                        {
                            string companyName = p.TryGetProperty("company_name", out var cn) ? cn.GetString() ?? "" : "";
                            string desc = p.TryGetProperty("description", out var dsc) ? dsc.GetString() ?? "" : "";
                            using var uc = conn.CreateCommand();
                            uc.CommandText = @"UPDATE suppliers SET name=@name, company_name=@cname, email=@email, phone=@phone,
                                address=@addr, city=@city, postal_code=@zip, gst_number=@gstin,
                                opening_balance=@bal, opening_balance_type=@obt, credit_limit=@limit,
                                description=@desc,
                                ServerId=@sid, SyncStatus='Synced', SyncVersion=@sver, del_status='Live', updated_at=datetime('now')
                                WHERE Id=@rid";
                            uc.Parameters.AddWithValue("@rid", tableRowId);
                            uc.Parameters.AddWithValue("@sid", id);
                            uc.Parameters.AddWithValue("@sver", syncVersion);
                            uc.Parameters.AddWithValue("@name", name);
                            uc.Parameters.AddWithValue("@cname", companyName);
                            uc.Parameters.AddWithValue("@email", email);
                            uc.Parameters.AddWithValue("@phone", phone);
                            uc.Parameters.AddWithValue("@addr", addr);
                            uc.Parameters.AddWithValue("@city", city);
                            uc.Parameters.AddWithValue("@zip", zip);
                            uc.Parameters.AddWithValue("@gstin", gstin);
                            uc.Parameters.AddWithValue("@bal", bal);
                            uc.Parameters.AddWithValue("@obt", obType);
                            uc.Parameters.AddWithValue("@limit", limit);
                            uc.Parameters.AddWithValue("@desc", desc);
                            uc.ExecuteNonQuery();
                        }
                    }
                    else
                    {
                        if (partyType == "Customer")
                        {
                            using var ic = conn.CreateCommand();
                            ic.CommandText = @"INSERT INTO customers
                                (id, name, email, phone, address, city, postal_code, gst_number,
                                 opening_balance, opening_balance_type, credit_limit, del_status,
                                 is_installment_customer, work_address, guarantor_name,
                                 guarantor_mobile, company_id, ServerId, SyncStatus, SyncVersion, updated_at)
                                VALUES
                                (@id,@name,@email,@phone,@addr,@city,@zip,@gstin,
                                 @bal,@obt,@limit,'Live',@isInstCust,@workAddr,@gName,
                                 @gMobile,1,@id,'Synced',@sver,datetime('now'))
                                ON CONFLICT(id) DO UPDATE SET
                                name=@name, email=@email, phone=@phone, address=@addr,
                                city=@city, postal_code=@zip, gst_number=@gstin,
                                opening_balance=@bal, opening_balance_type=@obt,
                                credit_limit=@limit, is_installment_customer=@isInstCust,
                                work_address=@workAddr, guarantor_name=@gName,
                                guarantor_mobile=@gMobile,
                                ServerId=@id, SyncStatus='Synced', SyncVersion=@sver, del_status='Live', updated_at=datetime('now')";
                            ic.Parameters.AddWithValue("@id", id);
                            ic.Parameters.AddWithValue("@sver", syncVersion);
                            ic.Parameters.AddWithValue("@name", name);
                            ic.Parameters.AddWithValue("@email", email);
                            ic.Parameters.AddWithValue("@phone", phone);
                            ic.Parameters.AddWithValue("@addr", addr);
                            ic.Parameters.AddWithValue("@city", city);
                            ic.Parameters.AddWithValue("@zip", zip);
                            ic.Parameters.AddWithValue("@gstin", gstin);
                            ic.Parameters.AddWithValue("@bal", bal);
                            ic.Parameters.AddWithValue("@obt", obType);
                            ic.Parameters.AddWithValue("@limit", limit);
                            ic.Parameters.AddWithValue("@isInstCust", isInstCust);
                            ic.Parameters.AddWithValue("@workAddr", workAddr);
                            ic.Parameters.AddWithValue("@gName", gName);
                            ic.Parameters.AddWithValue("@gMobile", gMobile);
                            ic.ExecuteNonQuery();
                        }
                        else // Supplier
                        {
                            string companyName = p.TryGetProperty("company_name", out var cn) ? cn.GetString() ?? "" : "";
                            string suppObType = p.TryGetProperty("opening_balance_type", out var sobt) ? sobt.GetString() ?? "Dr" : "Dr";
                            string desc = p.TryGetProperty("description", out var dsc) ? dsc.GetString() ?? "" : "";
                            using var is2 = conn.CreateCommand();
                            is2.CommandText = @"INSERT INTO suppliers
                                (id, name, company_name, email, phone, address, city, postal_code, gst_number,
                                 opening_balance, opening_balance_type, credit_limit, description, del_status, company_id,
                                 ServerId, SyncStatus, SyncVersion, updated_at)
                                VALUES
                                (@id,@name,@cname,@email,@phone,@addr,@city,@zip,@gstin,
                                 @bal,@obt,@limit,@desc,'Live',1,@id,'Synced',@sver,datetime('now'))
                                ON CONFLICT(id) DO UPDATE SET
                                name=@name, company_name=@cname, email=@email, phone=@phone,
                                address=@addr, city=@city, postal_code=@zip, gst_number=@gstin,
                                opening_balance=@bal, opening_balance_type=@obt, credit_limit=@limit,
                                description=@desc,
                                ServerId=@id, SyncStatus='Synced', SyncVersion=@sver, del_status='Live', updated_at=datetime('now')";
                            is2.Parameters.AddWithValue("@id", id);
                            is2.Parameters.AddWithValue("@sver", syncVersion);
                            is2.Parameters.AddWithValue("@name", name);
                            is2.Parameters.AddWithValue("@cname", companyName);
                            is2.Parameters.AddWithValue("@email", email);
                            is2.Parameters.AddWithValue("@phone", phone);
                            is2.Parameters.AddWithValue("@addr", addr);
                            is2.Parameters.AddWithValue("@city", city);
                            is2.Parameters.AddWithValue("@zip", zip);
                            is2.Parameters.AddWithValue("@gstin", gstin);
                            is2.Parameters.AddWithValue("@bal", bal);
                            is2.Parameters.AddWithValue("@obt", suppObType);
                            is2.Parameters.AddWithValue("@limit", limit);
                            is2.Parameters.AddWithValue("@desc", desc);
                            is2.ExecuteNonQuery();
                        }
                    }

                    // ── 2. Also write into Master1 (legacy Busy-style, used by voucher pages) ──
                    // First check if there's already a local entry with this ServerId (to avoid duplicates)
                    string existingCode = "";
                    using (var ec = conn.CreateCommand())
                    {
                        ec.CommandText = "SELECT Code FROM Master1 WHERE MasterType='Party' AND PartyType=@ptype AND ServerId=@sid AND Code!=@code LIMIT 1";
                        ec.Parameters.AddWithValue("@ptype", partyType);
                        ec.Parameters.AddWithValue("@sid", id);
                        ec.Parameters.AddWithValue("@code", code);
                        existingCode = ec.ExecuteScalar()?.ToString() ?? "";
                    }
                    // If a local entry exists with this ServerId, update it and skip creating PS{id}
                    if (!string.IsNullOrEmpty(existingCode))
                    {
                        using var mu = conn.CreateCommand();
                        mu.CommandText = @"UPDATE Master1 SET
                            Name=@name,Phone=@phone,Email=@email,GSTIN=@gstin,
                            Address1=@addr,City=@city,PinCode=@pin,
                            OpeningBalance=@bal,CreditLimit=@limit,
                            IsActive=1,DelStatus='Live',
                            SyncStatus='Synced',SyncVersion=@sver,UpdatedAt=datetime('now')
                            WHERE Code=@ecode";
                        mu.Parameters.AddWithValue("@ecode", existingCode);
                        mu.Parameters.AddWithValue("@sver", syncVersion);
                        mu.Parameters.AddWithValue("@name", name);
                        mu.Parameters.AddWithValue("@phone", phone);
                        mu.Parameters.AddWithValue("@email", email);
                        mu.Parameters.AddWithValue("@gstin", gstin);
                        mu.Parameters.AddWithValue("@addr", addr);
                        mu.Parameters.AddWithValue("@city", city);
                        mu.Parameters.AddWithValue("@pin", zip);
                        mu.Parameters.AddWithValue("@bal", bal);
                        mu.Parameters.AddWithValue("@limit", limit);
                        mu.ExecuteNonQuery();
                    }
                    else
                    {
                        // Pull-before-push race: a supplier created locally (SUP... row)
                        // may not have its ServerId assigned yet (push hasn't run or failed).
                        // Match it by name and adopt it instead of creating a duplicate PS{id} row.
                        string adoptCode = "";
                        using (var ac = conn.CreateCommand())
                        {
                            ac.CommandText = @"SELECT Code FROM Master1
                                WHERE MasterType='Party' AND PartyType=@ptype
                                  AND (DelStatus IS NULL OR DelStatus != 'Deleted')
                                  AND (ServerId IS NULL OR ServerId=0)
                                  AND LOWER(TRIM(Name)) = LOWER(@name) LIMIT 1";
                            ac.Parameters.AddWithValue("@ptype", partyType);
                            ac.Parameters.AddWithValue("@name", name);
                            adoptCode = ac.ExecuteScalar()?.ToString() ?? "";
                        }
                        if (!string.IsNullOrEmpty(adoptCode))
                        {
                            using var au = conn.CreateCommand();
                            au.CommandText = @"UPDATE Master1 SET
                                Name=@name,Phone=@phone,Email=@email,GSTIN=@gstin,
                                Address1=@addr,City=@city,PinCode=@pin,
                                OpeningBalance=@bal,CreditLimit=@limit,
                                IsActive=1,DelStatus='Live',
                                ServerId=@sid, SyncStatus='Synced', SyncVersion=@sver, UpdatedAt=datetime('now')
                                WHERE Code=@acode";
                            au.Parameters.AddWithValue("@acode", adoptCode);
                            au.Parameters.AddWithValue("@sver", syncVersion);
                            au.Parameters.AddWithValue("@name", name);
                            au.Parameters.AddWithValue("@phone", phone);
                            au.Parameters.AddWithValue("@email", email);
                            au.Parameters.AddWithValue("@gstin", gstin);
                            au.Parameters.AddWithValue("@addr", addr);
                            au.Parameters.AddWithValue("@city", city);
                            au.Parameters.AddWithValue("@pin", zip);
                            au.Parameters.AddWithValue("@bal", bal);
                            au.Parameters.AddWithValue("@limit", limit);
                            au.Parameters.AddWithValue("@sid", id);
                            au.ExecuteNonQuery();
                        }
                        else
                        {
                        using var mc = conn.CreateCommand();
                    mc.CommandText = @"INSERT INTO Master1
                        (Code, Name, MasterType, PartyType, Phone, Email, GSTIN,
                         Address1, City, PinCode, OpeningBalance, CreditLimit,
                         IsActive, DelStatus, ServerId, SyncStatus, SyncVersion, UpdatedAt)
                        VALUES
                        (@code,@name,'Party',@ptype,@phone,@email,@gstin,
                         @addr,@city,@pin,@bal,@limit,
                         1,'Live',@sid,'Synced',@sver,datetime('now'))
                        ON CONFLICT(Code) DO UPDATE SET
                        Name=@name,Phone=@phone,Email=@email,GSTIN=@gstin,
                        Address1=@addr,City=@city,PinCode=@pin,
                        OpeningBalance=@bal,CreditLimit=@limit,
                        IsActive=1,DelStatus='Live',
                        ServerId=@sid,SyncStatus='Synced',SyncVersion=@sver,UpdatedAt=datetime('now')";
                    mc.Parameters.AddWithValue("@code", code);
                    mc.Parameters.AddWithValue("@sver", syncVersion);
                    mc.Parameters.AddWithValue("@name", name);
                    mc.Parameters.AddWithValue("@ptype", partyType);
                    mc.Parameters.AddWithValue("@phone", phone);
                    mc.Parameters.AddWithValue("@email", email);
                    mc.Parameters.AddWithValue("@gstin", gstin);
                    mc.Parameters.AddWithValue("@addr", addr);
                    mc.Parameters.AddWithValue("@city", city);
                    mc.Parameters.AddWithValue("@pin", zip);
                    mc.Parameters.AddWithValue("@bal", bal);
                    mc.Parameters.AddWithValue("@limit", limit);
                    mc.Parameters.AddWithValue("@sid", id);
                    mc.ExecuteNonQuery();
                        } // end else (adopt local row)
                    } // end else (no existing entry with this ServerId)

                    count++;
                    }
                    catch (Exception exRow)
                    {
                        // Skip bad record — log and continue
                        long rowId = p.TryGetProperty("id", out var rid) && rid.ValueKind == JsonValueKind.Number ? rid.GetInt64() : -1;
                        LogService.Warn($"UpsertParties: skipping {partyType} id={rowId} — {exRow.Message}");
                    }
                } // end foreach
                txn.Commit();
            }
            catch (Exception ex)
            {
                LogService.Error("UpsertParties failed", ex);
                // Don't set _pullUpsertFailed — individual records handled above
                Log("pull", partyType + "s", "", "error", ex.Message);
            }
            return count;
        }

        private int UpsertSales(JsonElement sales)
        {
            int count = 0;
            try
            {
                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();
                foreach (var s in sales.EnumerateArray())
                {
                    long id = s.TryGetProperty("id", out var i) ? i.GetInt64() : 0;
                    string vchCode = "SRV" + id;
                    string invoiceNo = s.TryGetProperty("invoice_no", out var n) ? n.GetString() ?? id.ToString() : id.ToString();
                    string saleDate = s.TryGetProperty("sale_date", out var d) ? d.GetString() ?? "" : "";

                    // ═══ TOMBSTONE GUARD (delete parity — SYNC_DELETE_PLAN STEP 2) ═══
                    // Cloud par delete hua sale Tran1 legacy book mein insert mat karo.
                    // (Local `sales` mirror table ka tombstone UpsertSimpleRows sambhalta hai.)
                    string sDel = s.TryGetProperty("del_status", out var sd) ? sd.GetString() ?? "" : "";
                    if (string.Equals(sDel, "Deleted", StringComparison.OrdinalIgnoreCase)) continue;

                    // Skip if already pulled
                    using (var check = conn.CreateCommand())
                    {
                        check.CommandText = "SELECT COUNT(*) FROM Tran1 WHERE VchCode=@c";
                        check.Parameters.AddWithValue("@c", vchCode);
                        if ((long)check.ExecuteScalar() > 0) continue;
                    }

                    // A locally-pushed sale keeps its POS VchCode, so the "SRV"+id
                    // row never exists and the same sale would be inserted twice into
                    // Tran1 → double-counted in reports. Skip it if a Sales voucher
                    // with this invoice number already exists.
                    using (var checkInv = conn.CreateCommand())
                    {
                        checkInv.CommandText = "SELECT COUNT(*) FROM Tran1 WHERE VchType='Sales' AND VchNo=@vno";
                        checkInv.Parameters.AddWithValue("@vno", invoiceNo);
                        if ((long)checkInv.ExecuteScalar() > 0) continue;
                    }

                    double subTotal = GetD(s, "sub_total");
                    double totalPayable = GetD(s, "total_payable");
                    double vat = GetD(s, "vat");
                    string paymentMode = "Cash";
                    if (s.TryGetProperty("payments", out var pays) && pays.GetArrayLength() > 0)
                    {
                        var pm = pays[0];
                        if (pm.TryGetProperty("payment_id", out var pid))
                        {
                            paymentMode = pid.GetInt32() == 3 ? "UPI" : pid.GetInt32() == 4 ? "Card" : "Cash";
                        }
                    }

                    // Map server customer_id -> local party code (PC<n>), NULL if not found
                    string partyCode = "";
                    if (s.TryGetProperty("customer_id", out var cid) && cid.ValueKind == JsonValueKind.Number && cid.GetInt64() > 0)
                    {
                        using (var p = conn.CreateCommand())
                        {
                            p.CommandText = "SELECT Code FROM Master1 WHERE MasterType='Party' AND ServerId=@sid LIMIT 1";
                            p.Parameters.AddWithValue("@sid", cid.GetInt64());
                            partyCode = p.ExecuteScalar() as string ?? "";
                        }
                    }

                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.CommandText = @"INSERT INTO Tran1 (VchCode, VchType, VchNo, VchDate, MasterCode1, Narration, Amount, PaymentMode,
                                                ServerId, SyncStatus, CreatedAt, UpdatedAt)
                                            VALUES (@code, 'Sales', @vno, @vdate, @party, 'Synced from server', @amt, @pmode, @sid, 'Synced', datetime('now'), datetime('now'))";
                        cmd.Parameters.AddWithValue("@code", vchCode);
                        cmd.Parameters.AddWithValue("@vno", invoiceNo);
                        cmd.Parameters.AddWithValue("@vdate", saleDate);
                        cmd.Parameters.AddWithValue("@party", (object)(partyCode == "" ? DBNull.Value : partyCode));
                        cmd.Parameters.AddWithValue("@amt", totalPayable);
                        cmd.Parameters.AddWithValue("@pmode", paymentMode);
                        cmd.Parameters.AddWithValue("@sid", id);
                        cmd.ExecuteNonQuery();
                    }

                    // Items
                    if (s.TryGetProperty("items", out var items))
                    {
                        int sr = 1;
                        foreach (var line in items.EnumerateArray())
                        {
                            long itemId = line.TryGetProperty("item_id", out var iid) ? iid.GetInt64() : 0;
                            double qty = GetD(line, "qty");
                            double price = GetD(line, "menu_unit_price");
                            double taxPerc = GetD(line, "menu_vat_percentage");
                            double taxAmt = GetD(line, "item_tax_amount");
                            double disc = GetD(line, "discount_amount");

                            string itemCode = "";
                            using (var find = conn.CreateCommand())
                            {
                                find.CommandText = "SELECT Code FROM Master1 WHERE MasterType='Item' AND ServerId=@sid";
                                find.Parameters.AddWithValue("@sid", itemId);
                                itemCode = find.ExecuteScalar() as string ?? "";
                            }

                            using var cmd2 = conn.CreateCommand();
                            cmd2.CommandText = @"INSERT INTO Tran2 (VchCode, SrNo, MasterCode1, Description, Quantity, Unit, Rate, Amount,
                                                    DiscountPercent, DiscountAmount, TaxableAmount)
                                                VALUES (@vch, @sr, @item, @desc, @qty, 'NOS', @rate, @amt, 0, @disc, @taxable)";
                            cmd2.Parameters.AddWithValue("@vch", vchCode);
                            cmd2.Parameters.AddWithValue("@sr", sr++);
                            cmd2.Parameters.AddWithValue("@item", (object)(itemCode == "" ? DBNull.Value : itemCode));
                            cmd2.Parameters.AddWithValue("@desc", "");
                            cmd2.Parameters.AddWithValue("@qty", qty);
                            cmd2.Parameters.AddWithValue("@rate", price);
                            cmd2.Parameters.AddWithValue("@amt", price * qty);
                            cmd2.Parameters.AddWithValue("@disc", disc);
                            cmd2.Parameters.AddWithValue("@taxable", price * qty - taxAmt);
                            cmd2.ExecuteNonQuery();
                        }
                    }

                    count++;
                }
                txn.Commit();
            }
            catch (Exception ex)
            {
                LogService.Error("UpsertSales failed", ex);
                _pullUpsertFailed = true;
                Log("pull", "sales", "", "error", ex.Message);
            }
            return count;
        }

        private int UpsertPurchases(JsonElement purchases)
        {
            int count = 0;
            try
            {
                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();
                foreach (var p in purchases.EnumerateArray())
                {
                    long id = p.TryGetProperty("id", out var i) ? i.GetInt64() : 0;
                    if (id == 0) continue;

                    // ── Tombstone: server ne ye purchase delete kar di ──
                    string delStatus = p.TryGetProperty("del_status", out var ds) ? ds.GetString() ?? "Live" : "Live";
                    if (delStatus == "Deleted")
                    {
                        using var tomb = conn.CreateCommand();
                        tomb.CommandText = "UPDATE purchases SET del_status='Deleted', SyncStatus='Synced' WHERE Id=@id AND (SyncStatus IS NULL OR SyncStatus != 'Local')";
                        tomb.Parameters.AddWithValue("@id", id);
                        tomb.ExecuteNonQuery();
                        count++;
                        continue;
                    }

                    using var find = conn.CreateCommand();
                    find.CommandText = "SELECT SyncStatus FROM purchases WHERE Id=@id";
                    find.Parameters.AddWithValue("@id", id);
                    var status = find.ExecuteScalar() as string;
                    if (status == "Local") continue;

                    string supplierName = p.TryGetProperty("supplier_name", out var sn) ? sn.GetString() ?? "" : "";
                    long supplierId = 0;
                    if (p.TryGetProperty("supplier_id", out var si) && si.ValueKind == JsonValueKind.Number)
                        supplierId = si.GetInt64();

                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.CommandText = @"INSERT INTO purchases (Id, reference_no, invoice_no, supplier_id, date, grand_total, paid,
                                                due_amount, note, discount, status, ServerId, SyncStatus)
                                            VALUES (@id, @ref, @inv, @sup, @date, @gt, @paid, @due, @note, @disc, @status, @id, 'Synced')
                                            ON CONFLICT(Id) DO UPDATE SET
                                                reference_no=@ref, invoice_no=@inv, supplier_id=@sup, date=@date, grand_total=@gt,
                                                paid=@paid, due_amount=@due, note=@note, discount=@disc, status=@status, SyncStatus='Synced'";
                        cmd.Parameters.AddWithValue("@id", id);
                        cmd.Parameters.AddWithValue("@ref", p.TryGetProperty("reference_no", out var rn) ? (object)(rn.GetString() ?? "") : "");
                        cmd.Parameters.AddWithValue("@inv", p.TryGetProperty("invoice_no", out var inv) ? (object)(inv.GetString() ?? "") : "");
                        cmd.Parameters.AddWithValue("@sup", supplierId == 0 ? (object)DBNull.Value : supplierId);
                        cmd.Parameters.AddWithValue("@date", p.TryGetProperty("date", out var d) ? (object)(d.GetString() ?? "") : "");
                        cmd.Parameters.AddWithValue("@gt", GetD(p, "grand_total"));
                        cmd.Parameters.AddWithValue("@paid", GetD(p, "paid"));
                        cmd.Parameters.AddWithValue("@due", GetD(p, "due_amount"));
                        cmd.Parameters.AddWithValue("@note", p.TryGetProperty("note", out var nt) ? (object)(nt.GetString() ?? "") : "");
                        cmd.Parameters.AddWithValue("@disc", p.TryGetProperty("discount", out var dc) ? (dc.ValueKind == JsonValueKind.Number ? (object)dc.GetDouble().ToString() : (object)(dc.GetString() ?? "")) : "");
                        cmd.Parameters.AddWithValue("@status", p.TryGetProperty("status", out var st) ? (object)(st.GetString() ?? "Pending") : "Pending");
                        cmd.ExecuteNonQuery();
                    }

                    // Details: wipe + re-insert
                    using (var del = conn.CreateCommand())
                    {
                        del.CommandText = "DELETE FROM purchase_details WHERE purchase_id=@id";
                        del.Parameters.AddWithValue("@id", id);
                        del.ExecuteNonQuery();
                    }
                    if (p.TryGetProperty("items", out var items))
                    {
                        foreach (var line in items.EnumerateArray())
                        {
                            long itemId = line.TryGetProperty("item_id", out var ii) ? ii.GetInt64() : 0;
                            using var cmd = conn.CreateCommand();
                            cmd.CommandText = @"INSERT INTO purchase_details (purchase_id, item_id, unit_price, quantity_amount, total, SyncStatus)
                                                VALUES (@pid, @item, @price, @qty, @total, 'Synced')";
                            cmd.Parameters.AddWithValue("@pid", id);
                            cmd.Parameters.AddWithValue("@item", itemId);
                            cmd.Parameters.AddWithValue("@price", GetD(line, "unit_price"));
                            cmd.Parameters.AddWithValue("@qty", GetD(line, "quantity"));
                            cmd.Parameters.AddWithValue("@total", GetD(line, "total"));
                            cmd.ExecuteNonQuery();
                        }
                    }
                    count++;
                }
                txn.Commit();
            }
            catch (Exception ex)
            {
                LogService.Error("UpsertPurchases failed", ex);
                _pullUpsertFailed = true;
                Log("pull", "purchases", "", "error", ex.Message);
            }
            return count;
        }

        private int UpsertPurchaseReturns(JsonElement purchaseReturns)
        {
            int count = 0;
            try
            {
                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();
                foreach (var p in purchaseReturns.EnumerateArray())
                {
                    long id = p.TryGetProperty("id", out var i) ? i.GetInt64() : 0;
                    if (id == 0) continue;

                    // ── Tombstone: server ne ye purchase return delete kar di ──
                    string delStatus = p.TryGetProperty("del_status", out var ds) ? ds.GetString() ?? "Live" : "Live";
                    if (delStatus == "Deleted")
                    {
                        using var tomb = conn.CreateCommand();
                        tomb.CommandText = "UPDATE purchase_returns SET del_status='Deleted', SyncStatus='Synced' WHERE Id=@id AND (SyncStatus IS NULL OR SyncStatus != 'Local')";
                        tomb.Parameters.AddWithValue("@id", id);
                        tomb.ExecuteNonQuery();
                        count++;
                        continue;
                    }

                    using var find = conn.CreateCommand();
                    find.CommandText = "SELECT SyncStatus FROM purchase_returns WHERE Id=@id";
                    find.Parameters.AddWithValue("@id", id);
                    var status = find.ExecuteScalar() as string;
                    if (status == "Local") continue;

                    long supplierId = 0;
                    if (p.TryGetProperty("supplier_id", out var si) && si.ValueKind == JsonValueKind.Number)
                        supplierId = si.GetInt64();
                    long paymentMethodId = 0;
                    if (p.TryGetProperty("payment_method_id", out var pmid) && pmid.ValueKind == JsonValueKind.Number)
                        paymentMethodId = pmid.GetInt64();

                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.CommandText = @"INSERT INTO purchase_returns (Id, reference_no, pur_ref_no, supplier_id, date, purchase_date,
                                                return_status, total_return_amount, payment_method_id, note, ServerId, SyncStatus)
                                            VALUES (@id, @ref, @purref, @sup, @date, @purdate, @status, @amt, @pm, @note, @id, 'Synced')
                                            ON CONFLICT(Id) DO UPDATE SET
                                                reference_no=@ref, pur_ref_no=@purref, supplier_id=@sup, date=@date, purchase_date=@purdate,
                                                return_status=@status, total_return_amount=@amt, payment_method_id=@pm, note=@note, SyncStatus='Synced'";
                        cmd.Parameters.AddWithValue("@id", id);
                        cmd.Parameters.AddWithValue("@ref", p.TryGetProperty("reference_no", out var rn) ? (object)(rn.GetString() ?? "") : "");
                        cmd.Parameters.AddWithValue("@purref", p.TryGetProperty("pur_ref_no", out var prn) ? (object)(prn.GetString() ?? "") : "");
                        cmd.Parameters.AddWithValue("@sup", supplierId == 0 ? (object)DBNull.Value : supplierId);
                        cmd.Parameters.AddWithValue("@date", p.TryGetProperty("date", out var d) ? (object)(d.GetString() ?? "") : "");
                        cmd.Parameters.AddWithValue("@purdate", p.TryGetProperty("purchase_date", out var pd) ? (object)(pd.GetString() ?? "") : "");
                        cmd.Parameters.AddWithValue("@status", p.TryGetProperty("return_status", out var rs) ? (object)(rs.GetString() ?? "") : "");
                        cmd.Parameters.AddWithValue("@amt", GetD(p, "total_return_amount"));
                        cmd.Parameters.AddWithValue("@pm", paymentMethodId == 0 ? (object)DBNull.Value : paymentMethodId);
                        cmd.Parameters.AddWithValue("@note", p.TryGetProperty("note", out var nt) ? (object)(nt.GetString() ?? "") : "");
                        cmd.ExecuteNonQuery();
                    }

                    using (var del = conn.CreateCommand())
                    {
                        del.CommandText = "DELETE FROM purchase_return_details WHERE pur_return_id=@id";
                        del.Parameters.AddWithValue("@id", id);
                        del.ExecuteNonQuery();
                    }
                    if (p.TryGetProperty("items", out var items))
                    {
                        foreach (var line in items.EnumerateArray())
                        {
                            long itemId = line.TryGetProperty("item_id", out var ii) ? ii.GetInt64() : 0;
                            using var cmd = conn.CreateCommand();
                            cmd.CommandText = @"INSERT INTO purchase_return_details (pur_return_id, item_id, return_quantity_amount, unit_price, total, SyncStatus)
                                                VALUES (@rid, @item, @qty, @price, @total, 'Synced')";
                            cmd.Parameters.AddWithValue("@rid", id);
                            cmd.Parameters.AddWithValue("@item", itemId);
                            cmd.Parameters.AddWithValue("@qty", GetD(line, "quantity"));
                            cmd.Parameters.AddWithValue("@price", GetD(line, "unit_price"));
                            cmd.Parameters.AddWithValue("@total", GetD(line, "total"));
                            cmd.ExecuteNonQuery();
                        }
                    }
                    count++;
                }
                txn.Commit();
            }
            catch (Exception ex)
            {
                LogService.Error("UpsertPurchaseReturns failed", ex);
                _pullUpsertFailed = true;
                Log("pull", "purchase_returns", "", "error", ex.Message);
            }
            return count;
        }

        private int UpsertSimpleRows(JsonElement rows, string table)
        {
            int count = 0;
            try
            {
                using var conn = _db.GetConnection();

                // Verify table exists
                using (var ex = conn.CreateCommand())
                {
                    ex.CommandText = "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND lower(name)=lower(@t)";
                    ex.Parameters.AddWithValue("@t", table);
                    if ((long)ex.ExecuteScalar() == 0) return 0;
                }

                // Build per-call column set (NOT a shared field — prevents cross-table contamination)
                var tableColumns = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
                using (var colsCmd = conn.CreateCommand())
                {
                    colsCmd.CommandText = $"PRAGMA table_info(\"{table}\")";
                    using var colsR = colsCmd.ExecuteReader();
                    while (colsR.Read())
                        tableColumns.Add(colsR.GetString(1)); // column name is index 1 in pragma_table_info
                }

                bool hasSyncStatus = tableColumns.Contains("SyncStatus");
                bool hasServerId   = tableColumns.Contains("ServerId");
                bool hasIdColumn   = tableColumns.Contains("Id");

                using var txn = conn.BeginTransaction();

                if (!hasIdColumn)
                {
                    // Tables without an Id column (e.g. view_stock_detail) are simple
                    // mirrors: wipe and rewrite from the server payload.
                    // GUARD: EMPTY payload kabhi wipe mat karo — server error/
                    // truncated response par local stock view khaali ho jata tha
                    // aur har stock report 0 dikhati tha. Empty = kuch naya nahi aaya.
                    if (rows.ValueKind != JsonValueKind.Array || rows.GetArrayLength() == 0)
                        return 0;

                    using (var del = conn.CreateCommand())
                    {
                        del.CommandText = $"DELETE FROM \"{table}\"";
                        del.ExecuteNonQuery();
                    }
                    foreach (var r in rows.EnumerateArray())
                    {
                        var fields = r.EnumerateObject()
                            .Where(p => tableColumns.Contains(p.Name))
                            .ToList();
                        if (fields.Count == 0) continue;

                        var colNames  = fields.Select(p => $"\"{p.Name}\"").ToList();
                        var paramNames = fields.Select(p => "@p_" + p.Name).ToList();

                        using var cmd = conn.CreateCommand();
                        cmd.CommandText = $"INSERT INTO \"{table}\" ({string.Join(", ", colNames)}) VALUES ({string.Join(", ", paramNames)})";
                        foreach (var p in fields)
                        {
                            object v = p.Value.ValueKind switch
                            {
                                JsonValueKind.Number => (object)p.Value.GetDouble(),
                                JsonValueKind.True   => 1L,
                                JsonValueKind.False  => 0L,
                                JsonValueKind.Null   => DBNull.Value,
                                _                    => p.Value.GetString() ?? (object)DBNull.Value
                            };
                            cmd.Parameters.AddWithValue("@p_" + p.Name, v);
                        }
                        cmd.ExecuteNonQuery();
                        count++;
                    }
                    txn.Commit();
                    return count;
                }

                // ─── Dedup: detail tables — when server sends rows with a parent FK,
                // remove stale local rows (negative IDs, already Synced) to prevent
                // double-counting (e.g., sale qty showing 4 instead of 2). ───
                var detailParentFk = table switch
                {
                    "sale_details" => "sales_id",
                    "sale_payments" => "sale_id",
                    "purchase_details" => "purchase_id",
                    "purchase_payments" => "purchase_id",
                    "hold_details" => "hold_id",
                    "damage_details" => "damage_id",
                    "transfer_details" => "transfer_id",
                    "quotation_details" => "quotation_id",
                    "installment_sale_details" => "installment_sale_id",
                    _ => (string?)null
                };

                if (detailParentFk != null && tableColumns.Contains(detailParentFk))
                {
                    // Collect parent IDs from server payload
                    var serverParentIds = new HashSet<long>();
                    foreach (var r in rows.EnumerateArray())
                    {
                        if (r.TryGetProperty(detailParentFk, out var fk) && fk.ValueKind == JsonValueKind.Number)
                            serverParentIds.Add(fk.GetInt64());
                    }
                    // Delete old local rows (negative Id, non-Local status) for those parent IDs
                    foreach (var pid in serverParentIds)
                    {
                        using var del = conn.CreateCommand();
                        del.CommandText = $"DELETE FROM \"{table}\" WHERE \"{detailParentFk}\"=@pid AND Id < 0 AND (SyncStatus IS NULL OR SyncStatus != 'Local')";
                        del.Parameters.AddWithValue("@pid", pid);
                        del.ExecuteNonQuery();
                    }
                }

                // ─── Dedup: header tables — when server sends rows with positive Ids,
                // delete stale local rows that have the same ServerId but different Id ───
                if (hasServerId)
                {
                    var serverIds = new HashSet<long>();
                    foreach (var r in rows.EnumerateArray())
                    {
                        long id = r.TryGetProperty("id", out var idp) && idp.ValueKind == JsonValueKind.Number ? idp.GetInt64() : 0;
                        if (id > 0) serverIds.Add(id);
                    }
                    if (serverIds.Count > 0)
                    {
                        // Delete rows with negative Id and matching ServerId
                        using (var del = conn.CreateCommand())
                        {
                            del.CommandText = $"DELETE FROM \"{table}\" WHERE Id < 0 AND ServerId IN ({string.Join(",", serverIds)}) AND (SyncStatus IS NULL OR SyncStatus != 'Local')";
                            del.ExecuteNonQuery();
                        }
                        // Delete rows with different positive Id but matching ServerId (e.g., sales table auto-increment)
                        using (var del = conn.CreateCommand())
                        {
                            del.CommandText = $"DELETE FROM \"{table}\" WHERE Id > 0 AND Id NOT IN ({string.Join(",", serverIds)}) AND ServerId IN ({string.Join(",", serverIds)}) AND (SyncStatus IS NULL OR SyncStatus != 'Local')";
                            del.ExecuteNonQuery();
                        }
                    }
                }

                foreach (var r in rows.EnumerateArray())
                {
                    long id = r.TryGetProperty("id", out var idp) && idp.ValueKind == JsonValueKind.Number
                        ? idp.GetInt64() : 0;
                    if (id == 0) continue;

                    // Don't overwrite locally-created records
                    if (hasSyncStatus)
                    {
                        using var find = conn.CreateCommand();
                        find.CommandText = $"SELECT SyncStatus FROM \"{table}\" WHERE Id=@id";
                        find.Parameters.AddWithValue("@id", id);
                        var status = find.ExecuteScalar() as string;
                        if (status == "Local") continue;
                    }

                    // Only write JSON fields that exist in this table
                    var fields = r.EnumerateObject()
                        .Where(p => p.Name != "id" && tableColumns.Contains(p.Name))
                        .ToList();

                    if (fields.Count == 0) continue;

                    // Build INSERT … ON CONFLICT(Id) DO UPDATE
                    var colNames  = fields.Select(p => $"\"{p.Name}\"").ToList();
                    var paramNames = fields.Select(p => "@p_" + p.Name).ToList();
                    var setClauses = fields.Select(p => $"\"{p.Name}\"=@p_{p.Name}").ToList();

                    // Optionally include ServerId / SyncStatus
                    string extraInsertCols = "";
                    string extraInsertVals = "";
                    string extraUpdateSet  = "";
                    if (hasServerId)   { extraInsertCols += ", \"ServerId\""; extraInsertVals += ", @id"; extraUpdateSet += ", \"ServerId\"=@id"; }
                    if (hasSyncStatus) { extraInsertCols += ", \"SyncStatus\""; extraInsertVals += ", 'Synced'"; extraUpdateSet += ", \"SyncStatus\"='Synced'"; }

                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = $@"INSERT INTO ""{table}"" (""Id"", {string.Join(", ", colNames)}{extraInsertCols})
                                         VALUES (@id, {string.Join(", ", paramNames)}{extraInsertVals})
                                         ON CONFLICT(""Id"") DO UPDATE SET
                                         {string.Join(", ", setClauses)}{extraUpdateSet}";

                    cmd.Parameters.AddWithValue("@id", id);
                    foreach (var p in fields)
                    {
                        object v = p.Value.ValueKind switch
                        {
                            JsonValueKind.Number => (object)p.Value.GetDouble(),
                            JsonValueKind.True   => 1L,
                            JsonValueKind.False  => 0L,
                            JsonValueKind.Null   => DBNull.Value,
                            _                    => p.Value.GetString() ?? (object)DBNull.Value
                        };
                        cmd.Parameters.AddWithValue("@p_" + p.Name, v);
                    }
                    cmd.ExecuteNonQuery();
                    count++;
                }

                txn.Commit();
            }
            catch (Exception ex)
            {
                LogService.Error($"UpsertSimpleRows({table}) failed", ex);
                _pullUpsertFailed = true;
                Log("pull", table, "", "error", ex.Message);
            }
            return count;
        }

        private static double GetD(JsonElement el, string prop)
        {
            return el.TryGetProperty(prop, out var v) ? v.GetDouble() : 0;
        }

        private static double GetNumSafe(JsonElement el, string prop)
        {
            if (el.TryGetProperty(prop, out var v) && v.ValueKind == JsonValueKind.Number) return v.GetDouble();
            return 0;
        }

        private static string GetStr(JsonElement el, string prop, string fallback = "")
        {
            if (el.TryGetProperty(prop, out var v) && v.ValueKind == JsonValueKind.String) return v.GetString() ?? fallback;
            return fallback;
        }

        private int UpsertSaleReturns(JsonElement rows)
        {
            int count = 0;
            SqliteConnection? conn = null;
            SqliteTransaction? txn = null;
            try
            {
                conn = _db.GetConnection();
                txn = conn.BeginTransaction();
                foreach (var r in rows.EnumerateArray())
                {
                    long id = r.TryGetProperty("id", out var i) ? i.GetInt64() : 0;
                    if (id <= 0) continue; // server ids are positive; negative local rows are never touched

                    double saleId = GetNumSafe(r, "sale_id");

                    // ═══ TOMBSTONE (delete parity — SYNC_DELETE_PLAN STEP 2) ═══
                    // Cloud par delete hua sale return → local row bhi Deleted mark.
                    // Local pending row (SyncStatus='Local') kabhi mat khao — wo
                    // aage push hoga (LWW: local pending edit server delete par win).
                    string incomingDel = GetStr(r, "del_status");
                    if (string.Equals(incomingDel, "Deleted", StringComparison.OrdinalIgnoreCase))
                    {
                        long tombTargetId = 0;
                        using (var f = conn.CreateCommand())
                        {
                            f.Transaction = txn;
                            f.CommandText = "SELECT Id FROM sale_returns WHERE ServerId=@sid AND ServerId>0 LIMIT 1";
                            f.Parameters.AddWithValue("@sid", id);
                            var v = f.ExecuteScalar();
                            if (v != null) tombTargetId = Convert.ToInt64(v);
                        }
                        if (tombTargetId == 0)
                        {
                            using (var chk = conn.CreateCommand())
                            {
                                chk.Transaction = txn;
                                chk.CommandText = "SELECT SyncStatus, IFNULL(ServerId,0) FROM sale_returns WHERE Id=@id LIMIT 1";
                                chk.Parameters.AddWithValue("@id", id);
                                using var cr = chk.ExecuteReader();
                                if (cr.Read())
                                {
                                    var rowStatus = cr["SyncStatus"] as string;
                                    long rowServerId = cr.GetInt64(1);
                                    if (rowStatus == "Local" || (rowStatus != null && rowServerId <= 0))
                                        continue; // unrelated local pending row — preserve
                                }
                            }
                            tombTargetId = id;
                        }
                        else
                        {
                            // ServerId-matched row — local pending version ho to mat khao
                            using (var chk = conn.CreateCommand())
                            {
                                chk.Transaction = txn;
                                chk.CommandText = "SELECT SyncStatus FROM sale_returns WHERE Id=@id2 LIMIT 1";
                                chk.Parameters.AddWithValue("@id2", tombTargetId);
                                var st = chk.ExecuteScalar() as string;
                                if (st == "Local") continue;
                            }
                        }
                        using (var tomb = conn.CreateCommand())
                        {
                            tomb.Transaction = txn;
                            tomb.CommandText = @"UPDATE sale_returns SET del_status='Deleted', SyncStatus='Synced', ServerId=@sid
                                                 WHERE Id=@tid AND (del_status IS NULL OR del_status != 'Deleted')";
                            tomb.Parameters.AddWithValue("@sid", id);
                            tomb.Parameters.AddWithValue("@tid", tombTargetId);
                            if (tomb.ExecuteNonQuery() > 0) count++;
                        }
                        // Children bhi hatao — parent delete ke saath (mirror-only rows)
                        using var delD = conn.CreateCommand();
                        delD.Transaction = txn;
                        delD.CommandText = "DELETE FROM sale_return_details WHERE sale_return_id=@rid";
                        delD.Parameters.AddWithValue("@rid", tombTargetId);
                        delD.ExecuteNonQuery();
                        continue;
                    }

                    // 1) Match by ServerId first: this cloud return was already pushed
                    //    from this device and exists under a local (possibly negative) id.
                    long targetId = 0;
                    using (var f = conn.CreateCommand())
                    {
                        f.Transaction = txn;
                        f.CommandText = "SELECT Id FROM sale_returns WHERE ServerId=@sid AND ServerId>0 LIMIT 1";
                        f.Parameters.AddWithValue("@sid", id);
                        var v = f.ExecuteScalar();
                        if (v != null) targetId = Convert.ToInt64(v);
                    }

                    // 2) No ServerId match: an unrelated local return may share the same
                    //    positive auto-increment Id. NEVER clobber it — adopt it by
                    //    assigning the server id and copying the non-conflicting fields,
                    //    then skip inserting the server copy (the local row keeps its data).
                    if (targetId == 0)
                    {
                        string? rowStatus = null;
                        long rowServerId = 0;
                        using (var chk = conn.CreateCommand())
                        {
                            chk.Transaction = txn;
                            chk.CommandText = "SELECT SyncStatus, IFNULL(ServerId,0) FROM sale_returns WHERE Id=@id LIMIT 1";
                            chk.Parameters.AddWithValue("@id", id);
                            using var cr = chk.ExecuteReader();
                            if (cr.Read())
                            {
                                rowStatus = cr["SyncStatus"] as string;
                                rowServerId = cr.GetInt64(1);
                            }
                        }
                        if (rowStatus == "Local" || (rowStatus != null && rowServerId <= 0))
                        {
                            using var ad = conn.CreateCommand();
                            ad.Transaction = txn;
                            ad.CommandText = @"UPDATE sale_returns SET ServerId=@sid, reference_no=@ref, sale_id=@sale, customer_id=@cust, date=@date,
                                total_return_amount=@total, paid=@paid, due=@due, payment_method_id=@pm, note=@note
                                WHERE Id=@id";
                            ad.Parameters.AddWithValue("@sid", id);
                            ad.Parameters.AddWithValue("@id", id);
                            ad.Parameters.AddWithValue("@ref", GetStr(r, "reference_no"));
                            ad.Parameters.AddWithValue("@sale", saleId);
                            ad.Parameters.AddWithValue("@cust", GetNumSafe(r, "customer_id"));
                            ad.Parameters.AddWithValue("@date", GetStr(r, "date"));
                            ad.Parameters.AddWithValue("@total", GetNumSafe(r, "total_return_amount"));
                            ad.Parameters.AddWithValue("@paid", GetNumSafe(r, "paid"));
                            ad.Parameters.AddWithValue("@due", GetNumSafe(r, "due"));
                            ad.Parameters.AddWithValue("@pm", GetNumSafe(r, "payment_method_id"));
                            ad.Parameters.AddWithValue("@note", GetStr(r, "note"));
                            ad.ExecuteNonQuery();
                            count++;
                            continue; // adopted — keep the local row & its details
                        }
                        targetId = id; // insert/upsert into a fresh (or server-owned) id
                    }

                    if (targetId > 0 && targetId != id)
                    {
                        // A different local row shares the server id's auto-increment Id.
                        // Only drop it when it is NOT a local pending row (never lose data).
                        using var del = conn.CreateCommand();
                        del.Transaction = txn;
                        del.CommandText = @"DELETE FROM sale_returns
                                           WHERE Id=@id AND Id!=@tid
                                             AND (SyncStatus IS NULL OR SyncStatus != 'Local')";
                        del.Parameters.AddWithValue("@id", id);
                        del.Parameters.AddWithValue("@tid", targetId);
                        del.ExecuteNonQuery();
                    }

                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.Transaction = txn;
                        if (targetId > 0 && targetId != id)
                        {
                            cmd.CommandText = @"UPDATE sale_returns SET reference_no=@ref, sale_id=@sale, customer_id=@cust, date=@date,
                                total_return_amount=@total, paid=@paid, due=@due, payment_method_id=@pm, note=@note,
                                ServerId=@sid, SyncStatus='Synced' WHERE Id=@tid";
                            cmd.Parameters.AddWithValue("@tid", targetId);
                            cmd.Parameters.AddWithValue("@sid", id);
                        }
                        else
                        {
                            cmd.CommandText = @"INSERT INTO sale_returns (Id, reference_no, sale_id, customer_id, date, total_return_amount, paid, due, payment_method_id, note, user_id, outlet_id, company_id, del_status, created_at, updated_at, ServerId, SyncStatus)
                                VALUES (@id, @ref, @sale, @cust, @date, @total, @paid, @due, @pm, @note, 1, 1, 1, 'Live', datetime('now'), datetime('now'), @id, 'Synced')
                                ON CONFLICT(Id) DO UPDATE SET reference_no=@ref, sale_id=@sale, customer_id=@cust, date=@date,
                                    total_return_amount=@total, paid=@paid, due=@due, payment_method_id=@pm, note=@note, SyncStatus='Synced'";
                            cmd.Parameters.AddWithValue("@id", id);
                        }
                        cmd.Parameters.AddWithValue("@ref", GetStr(r, "reference_no"));
                        cmd.Parameters.AddWithValue("@sale", saleId);
                        cmd.Parameters.AddWithValue("@cust", GetNumSafe(r, "customer_id"));
                        cmd.Parameters.AddWithValue("@date", GetStr(r, "date"));
                        cmd.Parameters.AddWithValue("@total", GetNumSafe(r, "total_return_amount"));
                        cmd.Parameters.AddWithValue("@paid", GetNumSafe(r, "paid"));
                        cmd.Parameters.AddWithValue("@due", GetNumSafe(r, "due"));
                        cmd.Parameters.AddWithValue("@pm", GetNumSafe(r, "payment_method_id"));
                        cmd.Parameters.AddWithValue("@note", GetStr(r, "note"));
                        cmd.ExecuteNonQuery();
                    }

                    long rid = targetId > 0 ? targetId : id;

                    if (r.TryGetProperty("items", out var items) && items.ValueKind == JsonValueKind.Array)
                    {
                        // Only wipe the details of a server-owned parent row — a local
                        // pending parent is adopted above (with its details preserved).
                        using var del = conn.CreateCommand();
                        del.Transaction = txn;
                        del.CommandText = "DELETE FROM sale_return_details WHERE sale_return_id=@rid";
                        del.Parameters.AddWithValue("@rid", rid);
                        del.ExecuteNonQuery();

                        foreach (var line in items.EnumerateArray())
                        {
                            using var cmd = conn.CreateCommand();
                            cmd.Transaction = txn;
                            cmd.CommandText = @"INSERT INTO sale_return_details (sale_return_id, sale_id, item_id, sale_quantity_amount, return_quantity_amount, unit_price_in_sale, unit_price_in_return, user_id, outlet_id, company_id, del_status, created_at, updated_at, ServerId, SyncStatus)
                                VALUES (@rid, @sale, @item, @sq, @rq, @ps, @pr, 1, 1, 1, 'Live', datetime('now'), datetime('now'), @rid, 'Synced')";
                            cmd.Parameters.AddWithValue("@rid", rid);
                            cmd.Parameters.AddWithValue("@sale", GetNumSafe(line, "sale_id") != 0 ? GetNumSafe(line, "sale_id") : saleId);
                            cmd.Parameters.AddWithValue("@item", GetNumSafe(line, "item_id"));
                            cmd.Parameters.AddWithValue("@sq", GetNumSafe(line, "sale_quantity_amount"));
                            cmd.Parameters.AddWithValue("@rq", GetNumSafe(line, "return_quantity_amount"));
                            cmd.Parameters.AddWithValue("@ps", GetNumSafe(line, "unit_price_in_sale"));
                            cmd.Parameters.AddWithValue("@pr", GetNumSafe(line, "unit_price_in_return"));
                            cmd.ExecuteNonQuery();
                        }
                    }
                    count++;
                }
                txn.Commit();
            }
            catch (Exception ex)
            {
                try { txn?.Rollback(); } catch { }
                LogService.Error("UpsertSaleReturns failed", ex);
                _pullUpsertFailed = true;
                Log("pull", "sale_returns", "", "error", ex.Message);
            }
            return count;
        }

        private static string GetUnitName(SqliteConnection conn, long unitId)
        {
            try
            {
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT UnitName FROM Units WHERE Id=@id";
                cmd.Parameters.AddWithValue("@id", unitId);
                return cmd.ExecuteScalar() as string ?? "";
            }
            catch { return ""; }
        }

        private static long? ResolveTaxId(SqliteConnection conn, string taxString)
        {
            if (string.IsNullOrWhiteSpace(taxString)) return null;
            var taxName = taxString.TrimEnd(':', ' ').Trim();
            if (string.IsNullOrEmpty(taxName)) return null;
            try
            {
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id FROM taxs WHERE tax_name=@name LIMIT 1";
                cmd.Parameters.AddWithValue("@name", taxName);
                var result = cmd.ExecuteScalar();
                if (result != null && result != DBNull.Value) return Convert.ToInt64(result);
            }
            catch { }
            var match = System.Text.RegularExpressions.Regex.Match(taxString, @"(\d+(?:\.\d+)?)\s*%");
            if (!match.Success || !double.TryParse(match.Groups[1].Value, System.Globalization.NumberStyles.Any, System.Globalization.CultureInfo.InvariantCulture, out var rate)) return null;
            try
            {
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id FROM taxs WHERE tax_rate=@rate AND (tax_name LIKE 'GST%' OR tax_name LIKE 'CGST%' OR tax_name LIKE 'SGST%') LIMIT 1";
                cmd.Parameters.AddWithValue("@rate", rate);
                var result = cmd.ExecuteScalar();
                return result != null && result != DBNull.Value ? Convert.ToInt64(result) : (long?)null;
            }
            catch { return null; }
        }

        private void Log(string direction, string type, string key, string status, string message)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"INSERT INTO SyncLog (Direction, EntityType, EntityKey, Status, Message) VALUES (@d, @t, @k, @s, @m)";
                cmd.Parameters.AddWithValue("@d", direction);
                cmd.Parameters.AddWithValue("@t", type);
                cmd.Parameters.AddWithValue("@k", key);
                cmd.Parameters.AddWithValue("@s", status);
                cmd.Parameters.AddWithValue("@m", message);
                cmd.ExecuteNonQuery();
            }
            catch { }
        }

        private static int UpsertPaymentMethods(SqliteConnection conn, JsonElement rows)
        {
            int count = 0;
            foreach (var r in rows.EnumerateArray())
            {
                long id = r.TryGetProperty("id", out var i) && i.ValueKind == JsonValueKind.Number ? i.GetInt64() : 0;
                string name = r.TryGetProperty("name", out var n) ? n.GetString() ?? "" : "";
                string type = r.TryGetProperty("type", out var t) ? t.GetString() ?? "" : "";
                if (id == 0 || name == "") continue;

                using var find = conn.CreateCommand();
                find.CommandText = "SELECT SyncStatus FROM payment_methods WHERE Id=@id";
                find.Parameters.AddWithValue("@id", id);
                var status = find.ExecuteScalar() as string;
                if (status == "Local") continue;

                if (status != null)
                {
                    using var upd = conn.CreateCommand();
                    upd.CommandText = "UPDATE payment_methods SET Name=@name, Type=@type, ServerId=@id, SyncStatus='Synced' WHERE Id=@id";
                    upd.Parameters.AddWithValue("@name", name);
                    upd.Parameters.AddWithValue("@type", type);
                    upd.Parameters.AddWithValue("@id", id);
                    upd.ExecuteNonQuery();
                }
                else
                {
                    using var ins = conn.CreateCommand();
                    ins.CommandText = "INSERT INTO payment_methods (Id, Name, Type, ServerId, SyncStatus) VALUES (@id, @name, @type, @id, 'Synced')";
                    ins.Parameters.AddWithValue("@id", id);
                    ins.Parameters.AddWithValue("@name", name);
                    ins.Parameters.AddWithValue("@type", type);
                    ins.ExecuteNonQuery();
                }
                count++;
            }
            return count;
        }

        private static int UpsertCounters(SqliteConnection conn, JsonElement rows)
        {
            int count = 0;
            foreach (var r in rows.EnumerateArray())
            {
                long id = r.TryGetProperty("id", out var i) && i.ValueKind == JsonValueKind.Number ? i.GetInt64() : 0;
                string name = r.TryGetProperty("name", out var n) ? n.GetString() ?? "" : "";
                long outletId = r.TryGetProperty("outlet_id", out var oi) && oi.ValueKind == JsonValueKind.Number ? oi.GetInt64() : 0;
                long printerId = r.TryGetProperty("printer_id", out var pi) && pi.ValueKind == JsonValueKind.Number ? pi.GetInt64() : 0;
                if (id == 0 || name == "") continue;

                using var find = conn.CreateCommand();
                find.CommandText = "SELECT id FROM counters WHERE id=@id";
                find.Parameters.AddWithValue("@id", id);
                var exists = find.ExecuteScalar();

                if (exists != null)
                {
                    using var upd = conn.CreateCommand();
                    upd.CommandText = "UPDATE counters SET name=@name, outlet_id=@oid, printer_id=@pid, del_status='Live', SyncStatus='Synced' WHERE id=@id";
                    upd.Parameters.AddWithValue("@name", name);
                    upd.Parameters.AddWithValue("@oid", outletId);
                    upd.Parameters.AddWithValue("@pid", printerId);
                    upd.Parameters.AddWithValue("@id", id);
                    upd.ExecuteNonQuery();
                }
                else
                {
                    using var ins = conn.CreateCommand();
                    ins.CommandText = "INSERT INTO counters (id, name, outlet_id, printer_id, del_status, SyncStatus) VALUES (@id, @name, @oid, @pid, 'Live', 'Synced')";
                    ins.Parameters.AddWithValue("@id", id);
                    ins.Parameters.AddWithValue("@name", name);
                    ins.Parameters.AddWithValue("@oid", outletId);
                    ins.Parameters.AddWithValue("@pid", printerId);
                    ins.ExecuteNonQuery();
                }
                count++;
            }
            return count;
        }

        private static double ToD(object? v)
        {
            double.TryParse(v?.ToString(), out double d);
            return d;
        }

        // ═══════════════════ OFFLINE QUEUE ═══════════════════

        /// <summary>
        /// Enqueue a record for sync. Called by EntityCrudPage/POSPage after local save.
        /// entity_type = table name (sales, customers, items, etc.)
        /// operation   = insert | update | delete
        /// payload     = JSON string of the record
        /// </summary>
        public static void EnqueueSync(string entityType, long entityId, string operation, string? payload = null)
        {
            // These are server-managed — never push from client
            var noPush = new HashSet<string>(StringComparer.OrdinalIgnoreCase)
            {
                "users", "roles", "permissions", "model_has_roles", "model_has_permissions",
                "personal_access_tokens", "migrations", "feature_activations"
            };
            if (noPush.Contains(entityType)) return;

            try
            {
                var db = new DatabaseService();
                using var conn = db.GetConnection();
                using var cmd = conn.CreateCommand();
                // Remove old entry for same record to avoid duplicates
                cmd.CommandText = "DELETE FROM pending_sync WHERE entity_type=@t AND entity_id=@id AND operation != 'delete'";
                cmd.Parameters.AddWithValue("@t", entityType);
                cmd.Parameters.AddWithValue("@id", entityId);
                cmd.ExecuteNonQuery();

                using var ins = conn.CreateCommand();
                ins.CommandText = @"INSERT INTO pending_sync (entity_type, entity_id, operation, payload, created_at, retry_count)
                                    VALUES (@t, @id, @op, @p, datetime('now'), 0)";
                ins.Parameters.AddWithValue("@t", entityType);
                ins.Parameters.AddWithValue("@id", entityId);
                ins.Parameters.AddWithValue("@op", operation);
                ins.Parameters.AddWithValue("@p", (object?)payload ?? DBNull.Value);
                ins.ExecuteNonQuery();
            }
            catch { }
        }

        /// <summary>
        /// Mark a table row as Local (needs sync). Used after offline inserts/updates.
        /// </summary>
        public static void MarkLocalPending(string table, long id)
        {
            try
            {
                var db = new DatabaseService();
                using var conn = db.GetConnection();
                // Check if SyncStatus column exists
                using var check = conn.CreateCommand();
                check.CommandText = $"SELECT COUNT(*) FROM pragma_table_info('{table}') WHERE lower(name)='syncstatus'";
                if ((long)check.ExecuteScalar() == 0) return;

                using var cmd = conn.CreateCommand();
                cmd.CommandText = $"UPDATE \"{table}\" SET SyncStatus='Local' WHERE Id=@id";
                cmd.Parameters.AddWithValue("@id", id);
                cmd.ExecuteNonQuery();
            }
            catch { }
        }

        /// <summary>
        /// Drop all pending queue entries for a record (used when a never-synced
        /// local row is deleted — nothing to push anymore).
        /// </summary>
        public static void RemovePendingSync(string entityType, long entityId)
        {
            try
            {
                var db = new DatabaseService();
                using var conn = db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "DELETE FROM pending_sync WHERE entity_type=@t AND entity_id=@id";
                cmd.Parameters.AddWithValue("@t", entityType);
                cmd.Parameters.AddWithValue("@id", entityId);
                cmd.ExecuteNonQuery();
            }
            catch { }
        }

        /// <summary>
        /// Enqueue every live local employee that has no ServerId yet (created on
        /// the desktop, never pushed — old builds, offline periods). Payload is
        /// shaped like the Laravel "users" table. Safe to run on every sync: rows
        /// that already have a pending entry (incl. exhausted retries) are skipped.
        /// </summary>
        private void SeedPendingEmployees()
        {
            try
            {
                var toPush = new List<(long id, string json)>();
                using (var conn = _db.GetConnection())
                {
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = @"SELECT e.id, COALESCE(e.name,'') AS name, COALESCE(e.email,'') AS email,
                            COALESCE(e.phone,'') AS phone, COALESCE(e.role,'') AS role,
                            COALESCE(e.salary,0) AS salary, COALESCE(e.commission,0) AS commission,
                            COALESCE(e.outlet_id,'') AS outlet_id, COALESCE(e.will_login,'No') AS will_login,
                            COALESCE(e.start_date,'') AS start_date, COALESCE(e.end_date,'') AS end_date,
                            COALESCE(e.discount_permission_code,'') AS discount_code,
                            COALESCE(e.discount_amt,0) AS discount_amt, COALESCE(e.photo,'') AS photo,
                            COALESCE(e.password,'') AS password
                            FROM employees e
                            WHERE e.del_status='Live'
                              AND (e.ServerId IS NULL OR e.ServerId=0)
                              AND NOT EXISTS (SELECT 1 FROM pending_sync p
                                              WHERE p.entity_type='users' AND p.entity_id=e.id)";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        long id = r.GetInt64(0);
                        string roleRaw = r.GetString(4);

                        // Cloud stores the role id; resolve legacy names stored by older builds
                        string roleId = roleRaw;
                        if (!long.TryParse(roleRaw, out _))
                        {
                            using var rq = conn.CreateCommand();
                            rq.CommandText = "SELECT CAST(id AS TEXT) FROM roles WHERE name=@n LIMIT 1";
                            rq.Parameters.AddWithValue("@n", roleRaw);
                            var v = rq.ExecuteScalar();
                            if (v != null) roleId = v.ToString() ?? roleRaw;
                        }

                        string password = r.GetString(14);
                        var payload = new Dictionary<string, object?>
                        {
                            ["name"] = r.GetString(1),
                            ["email"] = r.GetString(2),
                            ["phone"] = r.GetString(3),
                            ["role"] = roleId,
                            ["salary"] = r.IsDBNull(5) ? 0 : r.GetDouble(5),
                            ["commission"] = r.IsDBNull(6) ? 0 : r.GetDouble(6),
                            ["outlet_id"] = r.GetString(7),
                            ["will_login"] = r.GetString(8),
                            ["start_date"] = r.GetString(9),
                            ["end_date"] = r.GetString(10),
                            ["discount_permission_code"] = r.GetString(11),
                            ["discount_amt"] = r.IsDBNull(12) ? 0 : r.GetDouble(12),
                            ["photo"] = r.GetString(13),
                            ["password"] = string.IsNullOrEmpty(password) ? null : password
                        };
                        toPush.Add((id, System.Text.Json.JsonSerializer.Serialize(payload)));
                    }
                }

                // Enqueue only after the reader is closed (SQLite writer lock)
                foreach (var (id, json) in toPush)
                    EnqueueSync("users", id, "insert", json);
            }
            catch { }
        }

        /// <summary>
        /// Process the pending_sync queue - push items to server via generic endpoint.
        /// Returns number of items processed.
        /// </summary>
        private async Task<int> ProcessPendingQueueAsync()
        {
            int processed = 0;
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, entity_type, entity_id, operation, payload FROM pending_sync WHERE retry_count < 100 ORDER BY created_at LIMIT 50";
                var pending = new List<(long qid, string type, long eid, string op, string? payload)>();
                using (var r = cmd.ExecuteReader())
                {
                    while (r.Read())
                        pending.Add((r.GetInt64(0), r.GetString(1), r.GetInt64(2), r.GetString(3), r.IsDBNull(4) ? null : r.GetString(4)));
                }

                foreach (var (qid, entityType, entityId, operation, payload) in pending)
                {
                    try
                    {
                        // Queue entity "users" is the Laravel name for local "employees"
                        string localTable = entityType == "users" ? "employees" : entityType switch
                        {
                            "item_categories" => "ItemCategories",
                            "brands" => "Brands",
                            "units" => "Units",
                            "racks" => "Racks",
                            "variations" => "Variations",
                            "expense_categories" => "ExpenseCategories",
                            _ => entityType
                        };

                        // Build payload from table if not provided.
                        // Deletes do NOT need a payload (server resolves by id) — so
                        // never drop a delete just because the row is gone or the local
                        // table name differs from the server name (item_categories vs
                        // ItemCategories). Only insert/update rows must have content.
                        bool isDelete = operation == "delete";
                        string jsonPayload = payload ?? BuildPayloadFromTable(conn, localTable, entityId);
                        if (!isDelete && string.IsNullOrEmpty(jsonPayload)) { MarkQueueItemDone(conn, qid); continue; }
                        if (isDelete && string.IsNullOrEmpty(jsonPayload)) jsonPayload = "{}";

                        var pushData = new Dictionary<string, object>
                        {
                            ["entity_type"] = entityType,
                            ["entity_id"] = entityId,
                            ["operation"] = operation,
                            ["payload"] = jsonPayload,
                            ["device_id"] = DeviceContext.GetDeviceId(),
                            ["timestamp"] = DateTime.UtcNow.ToString("yyyy-MM-ddTHH:mm:ssZ")
                        };

                        // Generic entity endpoint (/api/sync/push-entity). The typed
                        // /api/sync/push endpoint does NOT understand
                        // entity_type/entity_id/operation payloads — posting there
                        // returned 200 with empty results and the queue item was
                        // deleted, silently losing data. This endpoint acks with
                        // server_id so we only clear the queue on a real success.
                        var (ok, message, data) = await _api.PushEntityAsync(pushData);
                        if (ok && data != null)
                        {
                            var root = data.RootElement;
                            string? error = null;
                            if (root.TryGetProperty("error", out var ep) && ep.ValueKind == JsonValueKind.String)
                                error = ep.GetString();

                            long serverId = 0;
                            if (root.TryGetProperty("server_id", out var sid) && sid.ValueKind == JsonValueKind.Number)
                                serverId = sid.GetInt64();

                            if (!string.IsNullOrEmpty(error))
                            {
                                if (IncrementRetry(conn, qid, error) >= 100) RequeueExhausted(conn, qid);
                                continue;
                            }

                            MarkQueueItemDone(conn, qid);
                            if (serverId > 0)
                                ApplyQueueServerId(conn, localTable, entityId, serverId);
                            MarkLocalPending_SetSynced(conn, localTable, entityId);
                            processed++;
                        }
                        else
                        {
                            // Offline (network/timeout) hone par retry_count burn mat
                            // karo — warna kuch attempts me op drop ho jata aur offline
                            // ki gayi changes kabhi sync na hoti. Sirf server-side
                            // errors (4xx/5xx/JSON) pe retry_count badhao.
                            if (IsServerSideError(message)) { if (IncrementRetry(conn, qid, message) >= 100) RequeueExhausted(conn, qid); }
                            else UpdateQueueError(conn, qid, message);
                        }
                    }
                    catch (Exception ex)
                    {
                        if (IsServerSideError(ex.Message)) { if (IncrementRetry(conn, qid, ex.Message) >= 100) RequeueExhausted(conn, qid); }
                        else UpdateQueueError(conn, qid, ex.Message);
                    }
                }
            }
            catch { }
            return processed;
        }

        private string BuildPayloadFromTable(Microsoft.Data.Sqlite.SqliteConnection conn, string table, long id)
        {
            try
            {
                using var cmd = conn.CreateCommand();
                cmd.CommandText = $"SELECT * FROM \"{table}\" WHERE Id=@id LIMIT 1";
                cmd.Parameters.AddWithValue("@id", id);
                using var r = cmd.ExecuteReader();
                if (!r.Read()) return "";
                var dict = new Dictionary<string, object?>();
                for (int i = 0; i < r.FieldCount; i++)
                    dict[r.GetName(i)] = r.IsDBNull(i) ? null : r.GetValue(i);
                return System.Text.Json.JsonSerializer.Serialize(dict);
            }
            catch { return ""; }
        }

        private static void MarkQueueItemDone(Microsoft.Data.Sqlite.SqliteConnection conn, long qid)
        {
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "DELETE FROM pending_sync WHERE id=@id";
            cmd.Parameters.AddWithValue("@id", qid);
            cmd.ExecuteNonQuery();
        }

        private static void ApplyQueueServerId(Microsoft.Data.Sqlite.SqliteConnection conn, string table, long id, long serverId)
        {
            try
            {
                using var check = conn.CreateCommand();
                check.CommandText = $"SELECT COUNT(*) FROM pragma_table_info('{table}') WHERE lower(name)='serverid'";
                if ((long)check.ExecuteScalar() == 0) return;
                using var cmd = conn.CreateCommand();
                cmd.CommandText = $"UPDATE \"{table}\" SET ServerId=@sid WHERE Id=@id";
                cmd.Parameters.AddWithValue("@sid", serverId);
                cmd.Parameters.AddWithValue("@id", id);
                cmd.ExecuteNonQuery();
            }
            catch { }
        }

        private static void MarkLocalPending_SetSynced(Microsoft.Data.Sqlite.SqliteConnection conn, string table, long id)
        {
            try
            {
                using var check = conn.CreateCommand();
                check.CommandText = $"SELECT COUNT(*) FROM pragma_table_info('{table}') WHERE lower(name)='syncstatus'";
                if ((long)check.ExecuteScalar() == 0) return;
                using var cmd = conn.CreateCommand();
                cmd.CommandText = $"UPDATE \"{table}\" SET SyncStatus='Synced' WHERE Id=@id";
                cmd.Parameters.AddWithValue("@id", id);
                cmd.ExecuteNonQuery();
            }
            catch { }
        }

        private static int IncrementRetry(Microsoft.Data.Sqlite.SqliteConnection conn, long qid, string error)
        {
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "UPDATE pending_sync SET retry_count=retry_count+1, last_error=@e WHERE id=@id";
            cmd.Parameters.AddWithValue("@e", error ?? "");
            cmd.Parameters.AddWithValue("@id", qid);
            cmd.ExecuteNonQuery();
            using var get = conn.CreateCommand();
            get.CommandText = "SELECT retry_count FROM pending_sync WHERE id=@id";
            get.Parameters.AddWithValue("@id", qid);
            var v = get.ExecuteScalar();
            return v != null ? Convert.ToInt32(v) : 0;
        }

        /// <summary>
        /// A queue item that exhausted its server-side retries is never silently
        /// dropped: re-insert a fresh row (new created_at, retry_count reset) so the
        /// operation (often a DELETE/INSERT) keeps being delivered. Offline-type
        /// failures never reach this path — they don't burn retry_count at all.
        /// </summary>
        private static void RequeueExhausted(Microsoft.Data.Sqlite.SqliteConnection conn, long qid)
        {
            try
            {
                string? type = null, op = null, payload = null;
                long eid = 0;
                using (var copy = conn.CreateCommand())
                {
                    copy.CommandText = "SELECT entity_type, entity_id, operation, payload FROM pending_sync WHERE id=@id";
                    copy.Parameters.AddWithValue("@id", qid);
                    using var r = copy.ExecuteReader();
                    if (!r.Read()) return;
                    type = r.GetString(0);
                    eid = r.GetInt64(1);
                    op = r.GetString(2);
                    payload = r.IsDBNull(3) ? null : r.GetString(3);
                }
                if (type == null || op == null) return;

                LogService.Error($"CRITICAL: pending_sync item {qid} ({type}/{eid}/{op}) requeued after exhausting 100 server-side retries");

                // ═══ FIX 4: SILENT DROP nahi — record ko dead_letters table mein
                // permanent log karo (log files ke alawa bhi), taaki ye kabhi
                // silently gayab na ho. Resolve hone tak DB mein dikhta rahega.
                try
                {
                    using var dead = conn.CreateCommand();
                    dead.CommandText = @"INSERT INTO dead_letters (entity_type, entity_id, operation, payload, error_message, retry_count, resolved)
                                         VALUES (@t, @id, @op, @p, 'Exhausted 100 server-side retries — see logs', 100, 0)";
                    dead.Parameters.AddWithValue("@t", type);
                    dead.Parameters.AddWithValue("@id", eid);
                    dead.Parameters.AddWithValue("@op", op);
                    dead.Parameters.AddWithValue("@p", (object?)payload ?? DBNull.Value);
                    dead.ExecuteNonQuery();
                }
                catch (Exception deadEx)
                {
                    LogService.Error("DeadLetter insert failed", deadEx);
                }

                using (var del = conn.CreateCommand())
                {
                    del.CommandText = "DELETE FROM pending_sync WHERE id=@id";
                    del.Parameters.AddWithValue("@id", qid);
                    del.ExecuteNonQuery();
                }

                using var ins = conn.CreateCommand();
                ins.CommandText = @"INSERT INTO pending_sync (entity_type, entity_id, operation, payload, created_at, retry_count)
                                    VALUES (@t, @id, @op, @p, datetime('now'), 0)";
                ins.Parameters.AddWithValue("@t", type);
                ins.Parameters.AddWithValue("@id", eid);
                ins.Parameters.AddWithValue("@op", op);
                ins.Parameters.AddWithValue("@p", (object?)payload ?? DBNull.Value);
                ins.ExecuteNonQuery();
            }
            catch (Exception ex)
            {
                LogService.Error("RequeueExhausted failed", ex);
            }
        }

        /// <summary>
        /// Server-reachable errors (HTTP failure, JSON error, auth) are treated as
        /// permanent-ish — retry a few times then drop. Anything else (timeout,
        /// connection refused, DNS failure) means the device is OFFLINE — those must
        /// never burn retry_count, otherwise offline changes get silently lost.
        /// </summary>
        private static bool IsServerSideError(string msg)
        {
            if (string.IsNullOrEmpty(msg)) return false;
            return msg.StartsWith("Push failed", StringComparison.OrdinalIgnoreCase)
                || msg.StartsWith("Pull failed", StringComparison.OrdinalIgnoreCase)
                || msg.StartsWith("Login failed", StringComparison.OrdinalIgnoreCase)
                || msg.Contains("Unauthorized", StringComparison.OrdinalIgnoreCase)
                || msg.Contains("Unsupported entity_type", StringComparison.OrdinalIgnoreCase)
                || msg.Contains("Invalid payload", StringComparison.OrdinalIgnoreCase);
        }

        private static void UpdateQueueError(Microsoft.Data.Sqlite.SqliteConnection conn, long qid, string error)
        {
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "UPDATE pending_sync SET last_error=@e WHERE id=@id";
            cmd.Parameters.AddWithValue("@e", error ?? "");
            cmd.Parameters.AddWithValue("@id", qid);
            cmd.ExecuteNonQuery();
        }

        // ═══ BUSY STOCK POLLING ═══
        /// <summary>
        /// Called every 2 minutes by _busyStockTimer.
        /// Triggers server-side Busy → stock sync, then pulls updated stock into local SQLite.
        /// </summary>
        private async Task PollBusyStockAsync()
        {
            if (_busyStockPolling) return;
            _busyStockPolling = true;
            try
            {
                // Ask server to pull latest stock from BusyNotify
                await _api.PostDesktopReportAsync("/api/desktop/busy-import/sync-stock", new { });
                LogService.Info("BusyNotify stock poll triggered");

                // Wait briefly for server to complete
                await Task.Delay(3000);

                // Now do a regular sync pull to get updated stock into local DB
                await SyncNowAsync(silent: true, forcePull: true);
                _lastBusyStockSync = DateTime.Now;
            }
            catch (Exception ex)
            {
                LogService.Warn($"BusyNotify stock poll failed: {ex.Message}");
            }
            finally
            {
                _busyStockPolling = false;
            }
        }
    }
}
