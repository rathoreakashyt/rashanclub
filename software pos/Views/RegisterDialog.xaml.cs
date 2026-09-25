using System;
using System.Collections.Generic;
using System.Globalization;
using System.Linq;
using System.Text.Json;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;
using RashanKiDukan.Models;

namespace RashanKiDukan.Views
{
    public partial class RegisterDialog : Window
    {
        private readonly DatabaseService _db;
        private readonly User? _user;
        private readonly long _userId, _outletId, _companyId;
        private readonly List<(long Id, string Name)> _paymentMethods = new();
        private readonly List<TextBox> _balanceInputs = new();
        private readonly Dictionary<long, TextBox> _balanceBoxes = new();
        private readonly Dictionary<long, double> _openingAmounts = new();
        private long _registerId;
        private int _focusedBoxIndex = 0;
        private int _summaryBtnIndex = 0; // 0=Refresh, 1=Close Register, 2=Done

        public RegisterDialog(DatabaseService db, User? user)
        {
            InitializeComponent();
            _db = db;
            _user = user;
            _userId = user?.Id ?? 1;
            _outletId = 1;
            _companyId = user?.CompanyId ?? 1;
            LoadCounters();
            LoadPaymentMethods();
            BuildBalanceInputs();
            RefreshView();
        }

        private void LoadCounters()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, name FROM counters WHERE del_status='Live' OR del_status IS NULL ORDER BY id";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    cmbCounter.Items.Add(new ComboBoxItem { Content = r.GetString(1), Tag = r.GetInt64(0) });
            }
            catch { }
            if (cmbCounter.Items.Count == 0)
                cmbCounter.Items.Add(new ComboBoxItem { Content = "Counter 1", Tag = (long)1 });
            cmbCounter.SelectedIndex = 0;
            // Hide counter row if only 1 counter
            if (cmbCounter.Items.Count <= 1) counterRow.Visibility = Visibility.Collapsed;
        }

        private void LoadPaymentMethods()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT Id, Name FROM payment_methods
                                    WHERE Name<>'' AND IFNULL(account_type,'')<>'Loyalty Point'
                                    ORDER BY IFNULL(sort_id, 0), Id";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    _paymentMethods.Add((r.GetInt64(0), r.GetString(1)));
            }
            catch { }
            // Default if empty
            if (_paymentMethods.Count == 0)
            {
                _paymentMethods.Add((1, "Cash"));
                _paymentMethods.Add((2, "Card"));
                _paymentMethods.Add((3, "UPI"));
            }
        }

        private void BuildBalanceInputs()
        {
            _balanceBoxes.Clear();
            _balanceInputs.Clear();
            var stack = new StackPanel();

            string[] icons = { "💵", "💳", "📱", "🏦", "💰" };

            for (int i = 0; i < _paymentMethods.Count; i++)
            {
                var pm = _paymentMethods[i];
                string icon = i < icons.Length ? icons[i] : "💰";

                var row = new Border
                {
                    CornerRadius = new CornerRadius(8),
                    Padding = new Thickness(12, 10, 12, 10),
                    Margin = new Thickness(0, i > 0 ? 6 : 0, 0, 0),
                    Background = Brushes.White,
                    BorderBrush = new SolidColorBrush(Color.FromRgb(0xE2, 0xE8, 0xF0)),
                    BorderThickness = new Thickness(1),
                    Tag = i
                };

                var grid = new Grid();
                grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(140) });
                grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });

                var label = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };
                label.Children.Add(new TextBlock
                {
                    Text = icon,
                    FontSize = 18,
                    VerticalAlignment = VerticalAlignment.Center,
                    Margin = new Thickness(0, 0, 8, 0)
                });
                label.Children.Add(new TextBlock
                {
                    Text = pm.Name,
                    FontSize = 14,
                    FontWeight = FontWeights.SemiBold,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x1E, 0x29, 0x3B)),
                    FontFamily = new FontFamily("Segoe UI"),
                    VerticalAlignment = VerticalAlignment.Center
                });

                var box = new TextBox
                {
                    Height = 38,
                    FontSize = 16,
                    FontWeight = FontWeights.Bold,
                    FontFamily = new FontFamily("Segoe UI"),
                    Text = "0",
                    TextAlignment = TextAlignment.Right,
                    VerticalContentAlignment = VerticalAlignment.Center,
                    Background = new SolidColorBrush(Color.FromRgb(0xF8, 0xFA, 0xFC)),
                    BorderBrush = new SolidColorBrush(Color.FromRgb(0xCB, 0xD5, 0xE1)),
                    BorderThickness = new Thickness(1.5),
                    Padding = new Thickness(12, 0, 12, 0),
                    Tag = i
                };
                box.GotFocus += (s, e) =>
                {
                    if (s is TextBox tb && tb.Tag is int idx)
                    {
                        _focusedBoxIndex = idx;
                        HighlightRow(idx);
                        tb.SelectAll();
                    }
                };
                box.TextChanged += (s, e) => UpdateOpeningTotal();

                grid.Children.Add(label);
                grid.Children.Add(box);
                Grid.SetColumn(box, 1);
                row.Child = grid;

                stack.Children.Add(row);
                _balanceInputs.Add(box);
                _balanceBoxes[pm.Id] = box;
            }

            balancesHost.Child = stack;

            // Focus first box after loaded
            Loaded += (s, e) =>
            {
                if (_balanceInputs.Count > 0 && openPanel.Visibility == Visibility.Visible)
                {
                    _balanceInputs[0].Focus();
                    _balanceInputs[0].SelectAll();
                    HighlightRow(0);
                }
            };
        }

        private void HighlightRow(int index)
        {
            var parent = balancesHost.Child as StackPanel;
            if (parent == null) return;
            for (int i = 0; i < parent.Children.Count; i++)
            {
                if (parent.Children[i] is Border b)
                {
                    bool sel = i == index;
                    b.Background = new SolidColorBrush(sel ? Color.FromRgb(0xF0, 0xFD, 0xFA) : Colors.White);
                    b.BorderBrush = new SolidColorBrush(sel ? Color.FromRgb(0x0F, 0x76, 0x6E) : Color.FromRgb(0xE2, 0xE8, 0xF0));
                    b.BorderThickness = new Thickness(sel ? 2 : 1);
                }
            }
        }

        private void UpdateOpeningTotal()
        {
            double total = 0;
            foreach (var box in _balanceInputs)
            {
                if (double.TryParse(box.Text.Trim(), NumberStyles.Any, CultureInfo.InvariantCulture, out double v))
                    total += v;
            }
            if (lblOpeningTotal != null)
                lblOpeningTotal.Text = $"₹ {total:N2}";
        }

        private (bool Open, long RegisterId, double OpeningBalance, string OpeningDateTime, string OpeningDetails) CurrentOpenRegister()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT id, opening_balance, IFNULL(opening_balance_date_time, created_at), IFNULL(opening_details,'')
                                    FROM registers
                                    WHERE user_id=@u AND outlet_id=@o AND company_id=@c
                                      AND del_status='Live' AND register_status=1
                                    ORDER BY id DESC LIMIT 1";
                cmd.Parameters.AddWithValue("@u", _userId);
                cmd.Parameters.AddWithValue("@o", _outletId);
                cmd.Parameters.AddWithValue("@c", _companyId);
                using var r = cmd.ExecuteReader();
                if (r.Read())
                {
                    long id = r.GetInt64(0);
                    double bal = r.IsDBNull(1) ? 0 : r.GetDouble(1);
                    string dt = r.IsDBNull(2) ? "" : r.GetString(2);
                    string details = r.IsDBNull(3) ? "" : r.GetString(3);
                    return (true, id, bal, dt, details);
                }
            }
            catch { }
            return (false, 0, 0, "", "");
        }

        private void RefreshView()
        {
            var reg = CurrentOpenRegister();
            if (reg.Open)
            {
                _registerId = reg.RegisterId;
                _openingAmounts.Clear();
                foreach (var d in ParseOpeningDetails(reg.OpeningDetails))
                    _openingAmounts[d.Id] = d.Amount;
                openPanel.Visibility = Visibility.Collapsed;
                summaryPanel.Visibility = Visibility.Visible;
                lblStatusPill.Background = new SolidColorBrush(Color.FromRgb(0x0F, 0x76, 0x6E));
                lblStatus.Text = "REGISTER OPEN";
                lblStatusDetail.Text = $"Opened: {reg.OpeningDateTime}  ·  Opening: ₹{reg.OpeningBalance:N2}";
                lblHeaderHint.Text = "Register open hai — shift ka summary dekhein ya close karein";
                btnRefresh.Visibility = Visibility.Visible;
                btnCloseRegister.Visibility = Visibility.Visible;
                btnDone.Visibility = Visibility.Collapsed;
                lblGrandTotal.Foreground = new SolidColorBrush(Color.FromRgb(0x0F, 0x76, 0x6E));
                _summaryBtnIndex = 1; // Default to Close Register
                BuildSummary(false);
                HighlightSummaryButton();
            }
            else
            {
                openPanel.Visibility = Visibility.Visible;
                summaryPanel.Visibility = Visibility.Collapsed;
                lblStatusPill.Background = new SolidColorBrush(Color.FromRgb(0x64, 0x74, 0x8B));
                lblStatus.Text = "REGISTER CLOSED";
                lblStatusDetail.Text = "Register kholne ke liye opening balance daalein";
                lblHeaderHint.Text = "Opening balance daalein aur Enter press karein";
            }
        }

        private List<(long Id, double Amount)> ParseOpeningDetails(string json)
        {
            var result = new List<(long, double)>();
            if (string.IsNullOrWhiteSpace(json)) return result;
            try
            {
                using var doc = JsonDocument.Parse(json);
                if (doc.RootElement.ValueKind == JsonValueKind.Array)
                {
                    foreach (var el in doc.RootElement.EnumerateArray())
                    {
                        if (el.ValueKind != JsonValueKind.String) continue;
                        var parts = el.GetString()?.Split("||");
                        if (parts == null || parts.Length < 3) continue;
                        if (long.TryParse(parts[0], out long id) && double.TryParse(parts[2], NumberStyles.Any, CultureInfo.InvariantCulture, out double amt))
                            result.Add((id, amt));
                    }
                }
            }
            catch { }
            return result;
        }

        // ═══════ OPEN REGISTER ═══════

        private void BtnOpen_MouseClick(object sender, MouseButtonEventArgs e) => DoOpenRegister();

        private void DoOpenRegister()
        {
            lblOpenError.Text = "";
            if (cmbCounter.SelectedItem is not ComboBoxItem counter)
            {
                lblOpenError.Text = "Pehle counter select karein.";
                return;
            }
            long counterId = counter.Tag is long l ? l : 1;

            // Prevent double-open: check if a register is already open for this user/outlet/company
            try
            {
                using var checkConn = _db.GetConnection();
                using var checkCmd = checkConn.CreateCommand();
                checkCmd.CommandText = @"SELECT COUNT(*) FROM registers
                                         WHERE user_id=@u AND outlet_id=@o AND company_id=@c
                                           AND del_status='Live' AND register_status=1";
                checkCmd.Parameters.AddWithValue("@u", _userId);
                checkCmd.Parameters.AddWithValue("@o", _outletId);
                checkCmd.Parameters.AddWithValue("@c", _companyId);
                var openCount = Convert.ToInt64(checkCmd.ExecuteScalar());
                if (openCount > 0)
                {
                    lblOpenError.Text = "Register pehle se open hai! Pehle close karein.";
                    RefreshView();
                    return;
                }
            }
            catch (Exception ex)
            {
                lblOpenError.Text = "Check failed: " + ex.Message;
                return;
            }

            var details = new List<string>();
            double total = 0;
            foreach (var pm in _paymentMethods)
            {
                double amt = 0;
                if (_balanceBoxes.TryGetValue(pm.Id, out var box))
                {
                    double.TryParse(box.Text.Trim(), NumberStyles.Any, CultureInfo.InvariantCulture, out amt);
                    if (amt < 0) amt = 0;
                }
                details.Add($"{pm.Id}||{pm.Name}||{amt.ToString("F2", CultureInfo.InvariantCulture)}");
                total += amt;
            }

            string now = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"INSERT INTO registers (opening_balance, register_status, user_id, outlet_id, company_id,
                                    counter_id, del_status, opening_details, opening_balance_date_time, created_at, updated_at)
                                    VALUES (@ob, 1, @u, @o, @c, @cid, 'Live', @od, @dt, @now, @now)";
                cmd.Parameters.AddWithValue("@ob", Math.Round(total, 2));
                cmd.Parameters.AddWithValue("@u", _userId);
                cmd.Parameters.AddWithValue("@o", _outletId);
                cmd.Parameters.AddWithValue("@c", _companyId);
                cmd.Parameters.AddWithValue("@cid", counterId);
                cmd.Parameters.AddWithValue("@od", JsonSerializer.Serialize(details));
                cmd.Parameters.AddWithValue("@dt", now);
                cmd.Parameters.AddWithValue("@now", now);
                cmd.ExecuteNonQuery();
            }
            catch (Exception ex)
            {
                lblOpenError.Text = "Register open nahi hua: " + ex.Message;
                return;
            }
            RefreshView();
        }

        // ═══════ KEYBOARD NAV ═══════

        protected override void OnPreviewKeyDown(KeyEventArgs e)
        {
            base.OnPreviewKeyDown(e);

            if (openPanel.Visibility == Visibility.Visible)
            {
                switch (e.Key)
                {
                    case Key.Down:
                        if (_focusedBoxIndex < _balanceInputs.Count - 1)
                        {
                            _focusedBoxIndex++;
                            _balanceInputs[_focusedBoxIndex].Focus();
                            _balanceInputs[_focusedBoxIndex].SelectAll();
                        }
                        e.Handled = true; break;
                    case Key.Up:
                        if (_focusedBoxIndex > 0)
                        {
                            _focusedBoxIndex--;
                            _balanceInputs[_focusedBoxIndex].Focus();
                            _balanceInputs[_focusedBoxIndex].SelectAll();
                        }
                        e.Handled = true; break;
                    case Key.Enter:
                        DoOpenRegister();
                        e.Handled = true; break;
                    case Key.Escape:
                        Close();
                        e.Handled = true; break;
                }
            }
            else
            {
                switch (e.Key)
                {
                    case Key.Left:
                        _summaryBtnIndex = Math.Max(0, _summaryBtnIndex - 1);
                        HighlightSummaryButton();
                        e.Handled = true; break;
                    case Key.Right:
                        _summaryBtnIndex = Math.Min(GetMaxBtnIndex(), _summaryBtnIndex + 1);
                        HighlightSummaryButton();
                        e.Handled = true; break;
                    case Key.Enter:
                        ExecuteSummaryButton();
                        e.Handled = true; break;
                    case Key.Escape:
                        Close();
                        e.Handled = true; break;
                    case Key.F10:
                        BuildSummary(false);
                        e.Handled = true; break;
                }
            }
        }

        // ═══════ SUMMARY (same as before) ═══════

        private int GetMaxBtnIndex()
        {
            // Refresh(0), CloseRegister(1) when open; Done(0) when closed
            if (btnDone.Visibility == Visibility.Visible) return 0;
            return 1; // Refresh=0, Close=1
        }

        private void HighlightSummaryButton()
        {
            // Reset all buttons
            var normalBg = new SolidColorBrush(Color.FromRgb(0xF1, 0xF5, 0xF9));
            var normalFg = new SolidColorBrush(Color.FromRgb(0x33, 0x41, 0x55));
            var closeBg = new SolidColorBrush(Color.FromRgb(0xDC, 0x26, 0x26));
            var doneBg = new SolidColorBrush(Color.FromRgb(0x0F, 0x76, 0x6E));
            var highlightBorder = new SolidColorBrush(Color.FromRgb(0x0F, 0x17, 0x2A));
            var normalBorder = new SolidColorBrush(Color.FromRgb(0xCB, 0xD5, 0xE1));

            btnRefresh.BorderThickness = new Thickness(_summaryBtnIndex == 0 && btnRefresh.Visibility == Visibility.Visible ? 2 : 1);
            btnRefresh.BorderBrush = _summaryBtnIndex == 0 && btnRefresh.Visibility == Visibility.Visible ? highlightBorder : normalBorder;

            btnCloseRegister.BorderThickness = new Thickness(_summaryBtnIndex == 1 && btnCloseRegister.Visibility == Visibility.Visible ? 2 : 0);
            btnCloseRegister.BorderBrush = _summaryBtnIndex == 1 ? highlightBorder : closeBg;

            btnDone.BorderThickness = new Thickness(_summaryBtnIndex == 0 && btnDone.Visibility == Visibility.Visible ? 2 : 0);
            btnDone.BorderBrush = _summaryBtnIndex == 0 && btnDone.Visibility == Visibility.Visible ? highlightBorder : doneBg;
        }

        private void ExecuteSummaryButton()
        {
            if (btnDone.Visibility == Visibility.Visible)
            {
                // Closed state — only Done button
                Close();
                return;
            }

            // Open state — Refresh(0) or Close Register(1)
            if (_summaryBtnIndex == 0 && btnRefresh.Visibility == Visibility.Visible)
                BuildSummary(false);
            else if (_summaryBtnIndex == 1 && btnCloseRegister.Visibility == Visibility.Visible)
                BtnCloseRegister_Click(btnCloseRegister, new RoutedEventArgs());
        }

        private void BtnRefresh_Click(object sender, RoutedEventArgs e) => BuildSummary(false);

        private void BuildSummary(bool closed)
        {
            var reg = CurrentOpenRegister();
            if (!reg.Open && !closed) return;

            string openDt = reg.Open ? reg.OpeningDateTime : _closingOpenDt;
            string openDate = openDt.Length >= 10 ? openDt.Substring(0, 10) : openDt;
            string now = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
            string nowDate = DateTime.Now.ToString("yyyy-MM-dd");

            var panel = new StackPanel();
            double grandTotal = 0;

            foreach (var pm in _paymentMethods)
            {
                var rows = new List<(string Label, double Amount, bool Positive)>();
                double opening = _openingAmounts.TryGetValue(pm.Id, out var oa) ? oa : 0;
                if (opening > 0) rows.Add(("Opening Balance", opening, true));

                double sale = SumScalar(@"SELECT IFNULL(SUM(sp.amount),0) FROM sale_payments sp JOIN sales s ON s.id=sp.sale_id
                    WHERE sp.del_status='Live' AND sp.payment_id=@pm AND s.del_status='Live'
                      AND s.user_id=@u AND s.outlet_id=@o AND s.company_id=@c
                      AND ((s.date_time IS NOT NULL AND s.date_time BETWEEN @open AND @now)
                        OR (s.date_time IS NULL AND s.sale_date BETWEEN @od AND @nd)
                        OR (s.date_time IS NULL AND s.sale_date IS NULL AND s.created_at BETWEEN @open AND @now))", pm.Id, openDt, now, openDate, nowDate);
                if (sale > 0) rows.Add(("Sale", sale, true));

                double saleReturn = SumScalar(@"SELECT IFNULL(SUM(total_return_amount),0) FROM sale_returns
                    WHERE del_status='Live' AND payment_method_id=@pm AND user_id=@u AND outlet_id=@o AND company_id=@c
                      AND ((date BETWEEN @od AND @nd) OR (date IS NULL AND created_at BETWEEN @open AND @now))", pm.Id, openDt, now, openDate, nowDate);
                if (saleReturn > 0) rows.Add(("Sale Return", -saleReturn, false));

                double expense = SumScalar(@"SELECT IFNULL(SUM(amount),0) FROM expenses
                    WHERE del_status='Live' AND payment_method_id=@pm AND user_id=@u AND outlet_id=@o AND company_id=@c
                      AND ((date BETWEEN @od AND @nd) OR (date IS NULL AND created_at BETWEEN @open AND @now))", pm.Id, openDt, now, openDate, nowDate);
                if (expense > 0) rows.Add(("Expense", -expense, false));

                double methodTotal = rows.Sum(r => r.Amount);
                grandTotal += methodTotal;

                // Header
                var header = new Border
                {
                    Background = new SolidColorBrush(Color.FromRgb(0xF1, 0xF5, 0xF9)),
                    CornerRadius = new CornerRadius(6),
                    Margin = new Thickness(0, 6, 0, 2),
                    Padding = new Thickness(10, 6, 10, 6)
                };
                var hGrid = new Grid();
                hGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                hGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
                hGrid.Children.Add(new TextBlock { Text = pm.Name, FontSize = 12.5, FontWeight = FontWeights.Bold, Foreground = new SolidColorBrush(Color.FromRgb(0x0F, 0x17, 0x2A)), FontFamily = new FontFamily("Segoe UI") });
                var totalTxt = new TextBlock { Text = $"₹ {methodTotal:N2}", FontSize = 12.5, FontWeight = FontWeights.Bold, Foreground = new SolidColorBrush(Color.FromRgb(0x0F, 0x76, 0x6E)), FontFamily = new FontFamily("Segoe UI") };
                Grid.SetColumn(totalTxt, 1);
                hGrid.Children.Add(totalTxt);
                header.Child = hGrid;
                panel.Children.Add(header);

                foreach (var (label, amount, positive) in rows)
                {
                    var rGrid = new Grid { Margin = new Thickness(10, 2, 10, 2) };
                    rGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                    rGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
                    rGrid.Children.Add(new TextBlock { Text = label, FontSize = 12, Foreground = new SolidColorBrush(Color.FromRgb(0x64, 0x74, 0x8B)), FontFamily = new FontFamily("Segoe UI") });
                    var aTxt = new TextBlock { Text = $"₹ {Math.Abs(amount):N2}", FontSize = 12, FontFamily = new FontFamily("Segoe UI"), Foreground = new SolidColorBrush(positive ? Color.FromRgb(0x15, 0x80, 0x3D) : Color.FromRgb(0xDC, 0x26, 0x26)) };
                    Grid.SetColumn(aTxt, 1);
                    rGrid.Children.Add(aTxt);
                    panel.Children.Add(rGrid);
                }
            }

            if (panel.Children.Count == 0)
                panel.Children.Add(new TextBlock { Text = "Is shift me abhi koi transaction nahi hai.", FontSize = 12.5, Foreground = new SolidColorBrush(Color.FromRgb(0x64, 0x74, 0x8B)), FontFamily = new FontFamily("Segoe UI"), Margin = new Thickness(10, 12, 10, 12) });

            icSummary.ItemsSource = null;
            icSummary.Items.Clear();
            icSummary.Items.Add(panel);
            lblGrandTotal.Text = "₹ " + grandTotal.ToString("N2");
        }

        private string _closingOpenDt = "";
        private double _lastClosingBalance = 0;

        private double SumScalar(string sql, long pmId, string openDt, string nowDt, string openDate, string nowDate)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = sql;
                cmd.Parameters.AddWithValue("@pm", pmId);
                cmd.Parameters.AddWithValue("@u", _userId);
                cmd.Parameters.AddWithValue("@o", _outletId);
                cmd.Parameters.AddWithValue("@c", _companyId);
                cmd.Parameters.AddWithValue("@open", openDt);
                cmd.Parameters.AddWithValue("@now", nowDt);
                cmd.Parameters.AddWithValue("@od", openDate);
                cmd.Parameters.AddWithValue("@nd", nowDate);
                var v = cmd.ExecuteScalar();
                return v == null || v is DBNull ? 0 : Convert.ToDouble(v);
            }
            catch { return 0; }
        }

        // ═══════ CLOSE REGISTER ═══════

        private void BtnCloseRegister_Click(object sender, RoutedEventArgs e)
        {
            var reg = CurrentOpenRegister();
            if (!reg.Open) { RefreshView(); return; }

            var confirm = MessageBox.Show(this,
                "Register band karein?\n\nIske baad is shift ke totals final ho jayenge.",
                "Close Register", MessageBoxButton.YesNo, MessageBoxImage.Question);
            if (confirm != MessageBoxResult.Yes) return;

            _registerId = reg.RegisterId;
            _closingOpenDt = reg.OpeningDateTime;
            string openDt = reg.OpeningDateTime;
            string now = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
            string openDate = openDt.Length >= 10 ? openDt.Substring(0, 10) : openDt;
            string nowDate = DateTime.Now.ToString("yyyy-MM-dd");

            double salePaid = SumWhere("SELECT IFNULL(SUM(paid_amount),0) FROM sales WHERE del_status='Live' AND user_id=@u AND outlet_id=@o AND company_id=@c AND ((date_time BETWEEN @open AND @now) OR (date_time IS NULL AND created_at BETWEEN @open AND @now))", openDt, now, openDate, nowDate);
            double refund = SumWhere("SELECT IFNULL(SUM(total_return_amount),0) FROM sale_returns WHERE del_status='Live' AND user_id=@u AND outlet_id=@o AND company_id=@c AND ((date BETWEEN @od AND @nd) OR (date IS NULL AND created_at BETWEEN @open AND @now))", openDt, now, openDate, nowDate);
            double expense = SumWhere("SELECT IFNULL(SUM(amount),0) FROM expenses WHERE del_status='Live' AND user_id=@u AND outlet_id=@o AND company_id=@c AND ((date BETWEEN @od AND @nd) OR (date IS NULL AND created_at BETWEEN @open AND @now))", openDt, now, openDate, nowDate);

            double closingBalance = reg.OpeningBalance + salePaid - refund - expense;
            _lastClosingBalance = closingBalance;

            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"UPDATE registers SET register_status=2, closing_balance=@cb,
                                    sale_paid_amount=@sp, refund_amount=@rf, total_expense=@te,
                                    closing_balance_date_time=@now, updated_at=@now
                                    WHERE id=@id";
                cmd.Parameters.AddWithValue("@cb", Math.Round(closingBalance, 2));
                cmd.Parameters.AddWithValue("@sp", Math.Round(salePaid, 2));
                cmd.Parameters.AddWithValue("@rf", Math.Round(refund, 2));
                cmd.Parameters.AddWithValue("@te", Math.Round(expense, 2));
                cmd.Parameters.AddWithValue("@now", now);
                cmd.Parameters.AddWithValue("@id", _registerId);
                cmd.ExecuteNonQuery();
            }
            catch (Exception ex)
            {
                MessageBox.Show(this, "Register close error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
                return;
            }

            lblStatusPill.Background = new SolidColorBrush(Color.FromRgb(0x64, 0x74, 0x8B));
            lblStatus.Text = "REGISTER CLOSED";
            lblStatusDetail.Text = $"Shift band — Closing Balance: ₹{closingBalance:N2}";
            lblHeaderHint.Text = "Register band ho gaya";
            btnRefresh.Visibility = Visibility.Collapsed;
            btnCloseRegister.Visibility = Visibility.Collapsed;
            btnDone.Visibility = Visibility.Visible;
            lblGrandTotal.Foreground = new SolidColorBrush(Color.FromRgb(0xDC, 0x26, 0x26));
            lblClosingInfo.Text = $"Closing: ₹{closingBalance:N2}";
            BuildSummary(true);
        }

        private double SumWhere(string sql, string openDt, string nowDt, string openDate, string nowDate)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = sql;
                cmd.Parameters.AddWithValue("@u", _userId);
                cmd.Parameters.AddWithValue("@o", _outletId);
                cmd.Parameters.AddWithValue("@c", _companyId);
                cmd.Parameters.AddWithValue("@open", openDt);
                cmd.Parameters.AddWithValue("@now", nowDt);
                cmd.Parameters.AddWithValue("@od", openDate);
                cmd.Parameters.AddWithValue("@nd", nowDate);
                var v = cmd.ExecuteScalar();
                return v == null || v is DBNull ? 0 : Convert.ToDouble(v);
            }
            catch { return 0; }
        }

        private void BtnDone_Click(object sender, RoutedEventArgs e) => Close();
        private void BtnClose_Click(object sender, MouseButtonEventArgs e) => Close();
    }
}
