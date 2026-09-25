using System;
using System.Collections.Generic;
using System.Text.Json;
using System.Windows;
using System.Windows.Input;
using System.Windows.Media;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views
{
    /// <summary>
    /// Sync Monitor — 2 tabs:
    ///   1) Dead Letters: stuck sync records (local SQLite + server API).
    ///   2) Sync History: har sync cycle ka start / complete time + result.
    /// Overlap prevention: syncs kabhi ek saath nahi chalti — busy par aayi
    /// request agle cycle ke liye auto-queue hoti hai (drop nahi hoti).
    /// </summary>
    public partial class DeadLettersDialog : Window
    {
        private readonly DatabaseService _db = new();
        private readonly ApiService _api;
        private readonly SyncService _sync;
        private bool _changed;

        private sealed class DeadRow
        {
            public string Source { get; set; } = "";
            public Brush SourceBrush { get; set; } = Brushes.Gray;
            public string Entity { get; set; } = "";
            public string LocalId { get; set; } = "";
            public string Operation { get; set; } = "";
            public string Error { get; set; } = "";
            public string Retries { get; set; } = "";
            public string When { get; set; } = "";
        }

        public DeadLettersDialog(ApiService api, SyncService sync)
        {
            InitializeComponent();
            _api = api;
            _sync = sync;
            PreviewKeyDown += Window_PreviewKeyDown;
            Loaded += async (s, e) => await LoadDataAsync();
        }

        private void Window_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Escape)
            {
                DialogResult = _changed;
                Close();
            }
            else if (e.Key == Key.F5)
            {
                BtnRefresh_Click(sender, null);
            }
        }

        // ═══ DATA ═══

        private async System.Threading.Tasks.Task LoadDataAsync()
        {
            var rows = new List<DeadRow>();
            int localCount = 0, serverCount = 0;

            // 1) Local dead_letters
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT entity_type, entity_id, operation, error_message, retry_count, created_at
                                    FROM dead_letters WHERE resolved = 0 ORDER BY created_at DESC";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    localCount++;
                    rows.Add(new DeadRow
                    {
                        Source = "LOCAL",
                        SourceBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#DC2626")),
                        Entity = r.IsDBNull(0) ? "?" : r.GetString(0),
                        LocalId = r.IsDBNull(1) ? "-" : r.GetInt64(1).ToString(),
                        Operation = r.IsDBNull(2) ? "-" : r.GetString(2),
                        Error = r.IsDBNull(3) ? "" : r.GetString(3),
                        Retries = r.IsDBNull(4) ? "0" : r.GetInt64(4).ToString(),
                        When = r.IsDBNull(5) ? "" : r.GetString(5),
                    });
                }
            }
            catch (Exception ex)
            {
                LogService.Error("DeadLettersDialog local load failed", ex);
            }

            // 1b) Local pending_sync — records waiting to be pushed (retry_count < 100)
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT entity_type, entity_id, operation, last_error, retry_count, created_at
                                    FROM pending_sync WHERE retry_count < 100 ORDER BY created_at DESC";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    localCount++;
                    string entity = r.IsDBNull(0) ? "?" : r.GetString(0);
                    string op = r.IsDBNull(2) ? "-" : r.GetString(2);
                    string error = r.IsDBNull(3) ? "" : r.GetString(3);
                    // Truncate payload for display
                    if (error.Length > 120) error = error.Substring(0, 117) + "...";
                    rows.Add(new DeadRow
                    {
                        Source = "PENDING",
                        SourceBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#D97706")),
                        Entity = entity,
                        LocalId = r.IsDBNull(1) ? "-" : r.GetInt64(1).ToString(),
                        Operation = op,
                        Error = error,
                        Retries = r.IsDBNull(4) ? "0" : r.GetInt64(4).ToString(),
                        When = r.IsDBNull(5) ? "" : r.GetString(5),
                    });
                }
            }
            catch (Exception ex)
            {
                LogService.Error("DeadLettersDialog pending_sync load failed", ex);
            }

            // 2) Server dead letters (only when connected)
            try
            {
                if (_api.Token != null)
                {
                    var (ok, _, data) = await _api.GetDeadLettersAsync();
                    if (ok && data != null && data.RootElement.TryGetProperty("dead_letters", out var arr))
                    {
                        foreach (var item in arr.EnumerateArray())
                        {
                            serverCount++;
                            string device = item.TryGetProperty("device_id", out var d) ? d.GetString() ?? "" : "";
                            if (device.Length > 10) device = device.Substring(0, 10) + "…";
                            rows.Add(new DeadRow
                            {
                                Source = "SERVER",
                                SourceBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#B45309")),
                                Entity = item.TryGetProperty("entity_type", out var et) ? et.GetString() ?? "?" : "?",
                                LocalId = item.TryGetProperty("local_id", out var li) ? li.GetString() ?? "-" : "-",
                                Operation = device,
                                Error = item.TryGetProperty("error_message", out var em) ? em.GetString() ?? "" : "",
                                Retries = item.TryGetProperty("retry_count", out var rc) ? rc.GetInt32().ToString() : "0",
                                When = item.TryGetProperty("last_failed_at", out var lf) ? lf.GetString() ?? "" : "",
                            });
                        }
                    }
                }
            }
            catch (Exception ex)
            {
                LogService.Error("DeadLettersDialog server load failed", ex);
                lblServerNote.Text = "(server unavailable)";
            }

            lstDead.ItemsSource = rows;
            lblCount.Text = $"{rows.Count} record{(rows.Count == 1 ? "" : "s")}";
            lblLocalInfo.Text = $"Local: {localCount}";
            lblServerInfo.Text = $"Server: {serverCount}";

            // ═══ SYNC HISTORY tab ═══
            try
            {
                var history = _sync.GetRecentSyncHistory(50);
                lstHistory.ItemsSource = history;
                lblHistoryCount.Text = $"{history.Count} cycle{(history.Count == 1 ? "" : "s")}";
            }
            catch (Exception ex)
            {
                LogService.Error("DeadLettersDialog history load failed", ex);
            }
        }

        // ═══ ACTIONS ═══

        private void BtnDismiss_Click(object sender, System.Windows.Input.MouseButtonEventArgs e)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "UPDATE dead_letters SET resolved = 1 WHERE resolved = 0";
                cmd.ExecuteNonQuery();
                _changed = true;
            }
            catch (Exception ex)
            {
                LogService.Error("DeadLettersDialog dismiss failed", ex);
            }
            _ = LoadDataAsync();
        }

        private async void BtnRefresh_Click(object sender, System.Windows.Input.MouseButtonEventArgs e)
        {
            await LoadDataAsync();
        }

        private void BtnClose_Click(object sender, System.Windows.Input.MouseButtonEventArgs e)
        {
            DialogResult = _changed;
            Close();
        }
    }
}