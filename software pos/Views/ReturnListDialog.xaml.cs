using System;
using System.Collections.Generic;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Threading;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views
{
    public partial class ReturnListDialog : Window
    {
        private readonly DatabaseService _db = new();
        private readonly MainDashboard? _dashboard;
        private List<ReturnRow> _all = new();
        private List<ReturnRow> _filtered = new();
        private readonly DispatcherTimer _autoRefreshTimer;

        public ReturnListDialog(MainDashboard? dashboard = null)
        {
            InitializeComponent();
            _dashboard = dashboard;
            PreviewKeyDown += Window_PreviewKeyDown;

            // Auto-refresh every 10 seconds to pick up sync status changes
            _autoRefreshTimer = new DispatcherTimer { Interval = TimeSpan.FromSeconds(10) };
            _autoRefreshTimer.Tick += (s, e) => LoadData();

            Loaded += async (s, e) =>
            {
                LoadData();
                if (lstReturns.Items.Count > 0)
                {
                    lstReturns.SelectedIndex = 0;
                    lstReturns.Focus();
                }
                else txtSearch.Focus();

                // Trigger instant sync when dialog opens
                if (_dashboard != null)
                {
                    lblSyncStatus.Text = "Syncing...";
                    lblSyncStatus.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#F59E0B"));
                    try
                    {
                        await _dashboard.TriggerSync();
                        lblSyncStatus.Text = "Synced ✓";
                        lblSyncStatus.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#16A34A"));
                        LoadData();
                    }
                    catch
                    {
                        lblSyncStatus.Text = "Sync pending";
                        lblSyncStatus.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#DC2626"));
                    }
                }
                _autoRefreshTimer.Start();
            };

            Closed += (s, e) => _autoRefreshTimer.Stop();
        }

        // ═══ DATA ═══

        private void LoadData()
        {
            _all.Clear();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"
                    SELECT sr.Id, sr.reference_no, sr.date, sr.total_return_amount, sr.paid,
                           sr.note, sr.SyncStatus, sr.ServerId, sr.sale_id,
                           IFNULL(m.Name,'Walk-in') AS customer,
                           (SELECT COUNT(*) FROM sale_return_details d WHERE d.sale_return_id=sr.Id) AS items
                    FROM sale_returns sr
                    LEFT JOIN Master1 m ON m.ServerId=sr.customer_id AND m.MasterType='Party' AND m.PartyType IN ('Customer','Both')
                    WHERE (sr.del_status IS NULL OR sr.del_status != 'Deleted')
                    ORDER BY sr.Id DESC";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    _all.Add(new ReturnRow
                    {
                        Id = r.GetInt64(0),
                        ReferenceNo = r["reference_no"]?.ToString() ?? "",
                        Date = r["date"]?.ToString() ?? "",
                        Total = r.IsDBNull(3) ? 0 : r.GetDouble(3),
                        Paid = r.IsDBNull(4) ? 0 : r.GetDouble(4),
                        Note = r["note"]?.ToString() ?? "",
                        SyncStatus = r["syncstatus"]?.ToString() ?? "",
                        ServerId = r.IsDBNull(7) ? 0 : r.GetInt64(7),
                        SaleId = r.IsDBNull(8) ? 0 : r.GetInt64(8),
                        Customer = r["customer"]?.ToString() ?? "",
                        ItemCount = r.IsDBNull(10) ? 0 : (int)r.GetInt64(10)
                    });
                }
            }
            catch { }

            double todayTotal = 0;
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT IFNULL(SUM(total_return_amount),0) FROM sale_returns
                                    WHERE date=@d AND (del_status IS NULL OR del_status='Live')";
                cmd.Parameters.AddWithValue("@d", DateTime.Now.ToString("yyyy-MM-dd"));
                todayTotal = Convert.ToDouble(cmd.ExecuteScalar());
            }
            catch { }
            lblToday.Text = "₹" + todayTotal.ToString("N2");

            string q = txtSearch.Text.Trim().ToLower();
            _filtered = string.IsNullOrEmpty(q)
                ? new List<ReturnRow>(_all)
                : _all.Where(x =>
                    x.ReferenceNo.ToLower().Contains(q) ||
                    x.Customer.ToLower().Contains(q) ||
                    x.Note.ToLower().Contains(q) ||
                    x.Date.ToLower().Contains(q) ||
                    x.Total.ToString("N2").Contains(q)).ToList();

            lblCount.Text = $"{_filtered.Count} return{(_filtered.Count == 1 ? "" : "s")}";
            int prev = lstReturns.SelectedIndex;
            lstReturns.Items.Clear();
            for (int i = 0; i < _filtered.Count; i++)
                lstReturns.Items.Add(BuildRow(i, _filtered[i]));
            if (lstReturns.Items.Count > 0)
                lstReturns.SelectedIndex = Math.Clamp(prev < 0 ? 0 : prev, 0, lstReturns.Items.Count - 1);

            lblSearchHint.Visibility = string.IsNullOrEmpty(txtSearch.Text)
                ? Visibility.Visible : Visibility.Collapsed;
            UpdateFooter();
        }

        private FrameworkElement BuildRow(int index, ReturnRow x)
        {
            string preview = string.IsNullOrEmpty(x.Note) ? "(no note)" : x.Note;
            bool pending = !string.Equals(x.SyncStatus, "Synced", StringComparison.OrdinalIgnoreCase);

            var root = new Grid { Height = 40 };
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(32) });
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(90) });
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(145) });
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(140) });
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(70) });
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(90) });
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(95) });

            root.Children.Add(Cell(0, (index + 1).ToString(), "#94A3B8", 11, center: true));
            root.Children.Add(Cell(1, x.Date, "#64748B", 12));
            root.Children.Add(Cell(2, x.ReferenceNo, "#1D4ED8", 12.5, bold: true));
            root.Children.Add(Cell(3, x.Customer, "#1E293B", 13, bold: true));
            root.Children.Add(Cell(4, x.SaleId > 0 ? x.SaleId.ToString() : "—", "#475569", 12));
            root.Children.Add(Cell(5, preview, "#64748B", 11.5));
            root.Children.Add(Cell(6, $"₹{x.Total:N2}", "#16A34A", 13, bold: true, right: true));

            var status = new Border
            {
                Background = new SolidColorBrush(pending ? Color.FromRgb(0xFE, 0xE2, 0xE2) : Color.FromRgb(0xDC, 0xFC, 0xE7)),
                CornerRadius = new CornerRadius(4),
                Padding = new Thickness(6, 2, 6, 2),
                HorizontalAlignment = HorizontalAlignment.Center,
                VerticalAlignment = VerticalAlignment.Center
            };
            status.Child = new TextBlock
            {
                Text = pending ? "Pending" : "Synced",
                FontSize = 10.5, FontWeight = FontWeights.SemiBold,
                Foreground = new SolidColorBrush(pending ? Color.FromRgb(0xDC, 0x26, 0x26) : Color.FromRgb(0x16, 0xA3, 0x4A)),
                FontFamily = new FontFamily("Segoe UI")
            };
            Grid.SetColumn(status, 7);
            root.Children.Add(status);
            return root;
        }

        private static TextBlock Cell(int col, string text, string hex, double size,
            bool bold = false, bool right = false, bool center = false)
        {
            var tb = new TextBlock
            {
                Text = text, FontSize = size,
                FontFamily = new FontFamily("Segoe UI"),
                FontWeight = bold ? FontWeights.SemiBold : FontWeights.Normal,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString(hex)),
                VerticalAlignment = VerticalAlignment.Center,
                TextTrimming = TextTrimming.CharacterEllipsis,
                HorizontalAlignment = right ? HorizontalAlignment.Right
                                    : center ? HorizontalAlignment.Center
                                    : HorizontalAlignment.Left
            };
            Grid.SetColumn(tb, col);
            return tb;
        }

        // ═══ ACTIONS ═══

        private void ViewDetail(int idx)
        {
            if (idx < 0 || idx >= _filtered.Count) return;
            var x = _filtered[idx];
            string status = SyncLabel(x.SyncStatus);

            var itemLines = new List<string>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT IFNULL(i.name, t2.Description) AS name,
                                           d.return_quantity_amount AS qty,
                                           d.unit_price_in_return AS price
                                    FROM sale_return_details d
                                    LEFT JOIN items i ON i.id = d.item_id
                                    LEFT JOIN Tran2 t2 ON t2.Id = d.sale_id
                                    WHERE d.sale_return_id=@id ORDER BY d.Id";
                cmd.Parameters.AddWithValue("@id", x.Id);
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    string name = r["name"]?.ToString() ?? "";
                    double qty = r.IsDBNull(1) ? 0 : r.GetDouble(1);
                    double price = r.IsDBNull(2) ? 0 : r.GetDouble(2);
                    if (string.IsNullOrWhiteSpace(name)) name = "Item #" + x.Id;
                    itemLines.Add($"   • {name}  × {qty:N2}  @ ₹{price:N2}  = ₹{(qty * price):N2}");
                }
            }
            catch { }

            string itemsText = itemLines.Count > 0
                ? "Items:\n" + string.Join("\n", itemLines)
                : "Items: —";

            MessageBox.Show(
                $"Reference: {x.ReferenceNo}\nDate: {x.Date}\nCustomer: {x.Customer}\n" +
                $"Sale ID: {(x.SaleId > 0 ? x.SaleId.ToString() : "—")}\n{itemsText}\n\n" +
                $"Amount: ₹{x.Total:N2}  |  Paid: ₹{x.Paid:N2}\n\n{x.Note}\n\nStatus: {status}",
                "Return Detail", MessageBoxButton.OK, MessageBoxImage.Information);
        }

        private void DoDelete(int idx)
        {
            if (idx < 0 || idx >= _filtered.Count) return;
            var x = _filtered[idx];
            if (MessageBox.Show(
                $"Delete return?\n\nRef: {x.ReferenceNo}\nCustomer: {x.Customer}\nAmount: ₹{x.Total:N2}",
                "Delete Return", MessageBoxButton.YesNo, MessageBoxImage.Warning) != MessageBoxResult.Yes) return;
            try
            {
                using var conn = _db.GetConnection();
                // ═══ SYNC-DELETE FIX (SYNC_DELETE_PLAN STEP 5) ═══
                // Pehle HARD delete tha → cloud par row Live reh jaati thi aur
                // agle pull par wapas aa jaati thi (resurrection). Ab soft delete +
                // cloud push — server se bhi hat jati hai.
                using (var d = conn.CreateCommand())
                {
                    d.CommandText = "UPDATE sale_returns SET del_status='Deleted' WHERE Id=@id";
                    d.Parameters.AddWithValue("@id", x.Id);
                    d.ExecuteNonQuery();
                }
                using (var d = conn.CreateCommand())
                {
                    d.CommandText = "DELETE FROM sale_return_details WHERE sale_return_id=@id";
                    d.Parameters.AddWithValue("@id", x.Id);
                    d.ExecuteNonQuery();
                }
                // Cloud push sirf tab jab row pehle sync ho chuki hai (ServerId>0)
                if (x.ServerId > 0)
                {
                    Services.SyncService.EnqueueSync("sale_returns", x.ServerId, "delete");
                    _dashboard?.TriggerSync();
                }
                int next = idx;
                LoadData();
                if (lstReturns.Items.Count > 0)
                {
                    lstReturns.SelectedIndex = Math.Clamp(next, 0, lstReturns.Items.Count - 1);
                    lstReturns.Focus();
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void UpdateFooter()
        {
            int i = lstReturns.SelectedIndex;
            if (i >= 0 && i < _filtered.Count)
            {
                var x = _filtered[i];
                string sync = SyncLabel(x.SyncStatus);
                lblSelected.Text = $"#{i + 1}  {x.ReferenceNo}  ·  {x.Customer}  ·  ₹{x.Total:N2}  ·  {sync}";
            }
            else lblSelected.Text = "↑↓ navigate  ·  Enter detail  ·  Del delete  ·  F5 refresh  ·  Type to search";
        }

        // ═══ GLOBAL KEYBOARD ═══

        private static string SyncLabel(string status)
        {
            if (string.Equals(status, "Synced", StringComparison.OrdinalIgnoreCase)) return "✓ Synced";
            if (string.Equals(status, "PushError", StringComparison.OrdinalIgnoreCase)) return "✗ Failed";
            return "⏳ Pending sync";
        }

        private void Window_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            switch (e.Key)
            {
                case Key.Up:
                    if (lstReturns.SelectedIndex > 0) lstReturns.SelectedIndex--;
                    else if (lstReturns.Items.Count > 0) lstReturns.SelectedIndex = 0;
                    lstReturns.ScrollIntoView(lstReturns.SelectedItem);
                    lstReturns.Focus();
                    e.Handled = true; break;

                case Key.Down:
                    if (lstReturns.SelectedIndex < lstReturns.Items.Count - 1) lstReturns.SelectedIndex++;
                    lstReturns.ScrollIntoView(lstReturns.SelectedItem);
                    lstReturns.Focus();
                    e.Handled = true; break;

                case Key.Enter:
                    ViewDetail(lstReturns.SelectedIndex < 0 ? 0 : lstReturns.SelectedIndex);
                    e.Handled = true; break;

                case Key.Delete:
                    DoDelete(lstReturns.SelectedIndex < 0 ? 0 : lstReturns.SelectedIndex);
                    e.Handled = true; break;

                case Key.F5:
                    _ = RefreshWithSync();
                    e.Handled = true; break;

                case Key.Escape:
                    Close(); e.Handled = true; break;

                default:
                    if (!txtSearch.IsFocused)
                    {
                        bool isLetter = e.Key >= Key.A && e.Key <= Key.Z;
                        bool isDigit = e.Key >= Key.D0 && e.Key <= Key.D9
                                    || e.Key >= Key.NumPad0 && e.Key <= Key.NumPad9;
                        bool isSpace = e.Key == Key.Space;
                        bool noMod = Keyboard.Modifiers == ModifierKeys.None
                                  || Keyboard.Modifiers == ModifierKeys.Shift;
                        if ((isLetter || isDigit || isSpace) && noMod)
                        {
                            txtSearch.Focus();
                            txtSearch.CaretIndex = txtSearch.Text.Length;
                        }
                    }
                    break;
            }
        }

        // ═══ EVENT HANDLERS ═══

        private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e) => LoadData();
        private void LstReturns_SelectionChanged(object sender, SelectionChangedEventArgs e) => UpdateFooter();
        private async void BtnRefresh_Click(object sender, MouseButtonEventArgs e) => await RefreshWithSync();
        private void LstReturns_MouseDoubleClick(object sender, MouseButtonEventArgs e) => ViewDetail(lstReturns.SelectedIndex);
        private void BtnDelete_Click(object sender, MouseButtonEventArgs e) => DoDelete(lstReturns.SelectedIndex);
        private void BtnClose_Click(object sender, MouseButtonEventArgs e) => Close();

        private async System.Threading.Tasks.Task RefreshWithSync()
        {
            if (_dashboard != null)
            {
                lblSyncStatus.Text = "Syncing...";
                lblSyncStatus.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#F59E0B"));
                try
                {
                    await _dashboard.TriggerSync();
                    lblSyncStatus.Text = "Synced ✓";
                    lblSyncStatus.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#16A34A"));
                }
                catch
                {
                    lblSyncStatus.Text = "Sync pending";
                    lblSyncStatus.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#DC2626"));
                }
            }
            LoadData();
            lstReturns.Focus();
        }
    }

    public class ReturnRow
    {
        public long Id { get; set; }
        public string ReferenceNo { get; set; } = "";
        public string Date { get; set; } = "";
        public string Customer { get; set; } = "";
        public double Total { get; set; }
        public double Paid { get; set; }
        public string Note { get; set; } = "";
        public string SyncStatus { get; set; } = "";
        public long ServerId { get; set; }
        public long SaleId { get; set; }
        public int ItemCount { get; set; }
    }
}
