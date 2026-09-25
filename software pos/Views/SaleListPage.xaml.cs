using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;
using Microsoft.Win32;
using QuestPDF.Fluent;
using QuestPDF.Helpers;
using QuestPDF.Infrastructure;
using RashanKiDukan.Database;
using Color = System.Windows.Media.Color;

namespace RashanKiDukan.Views
{
    public partial class SaleListPage : UserControl, ISyncRefreshable
    {
        private readonly DatabaseService _db = new();
        private readonly MainDashboard? _dashboard;
        private List<SaleRowItem> _allRows = new();
        private List<SaleRowItem> _filteredRows = new();
        private int _currentPage = 1;
        private int _pageSize = 10;
        private int _totalCount;
        private bool _suppressFilter = false;

        public SaleListPage() { InitializeComponent(); }
        public SaleListPage(MainDashboard dashboard) : this()
        {
            _dashboard = dashboard;
            // Page size options: 10 / 20 / 50 / 100 / 500 — jitna chune utne sales screen par
            cmbPageSize.Items.Add("10");
            cmbPageSize.Items.Add("20");
            cmbPageSize.Items.Add("50");
            cmbPageSize.Items.Add("100");
            cmbPageSize.Items.Add("500");
            cmbPageSize.SelectedIndex = 0;
            // Filter options pehle se load — Filter button khole bina bhi combos ready
            _suppressFilter = true;
            LoadFilterOptions();
            // Time slots — 30 min ke options select karne ke liye (editable: type bhi kar sakte ho)
            for (int h = 0; h < 24; h++)
            {
                cmbTimeFrom.Items.Add($"{h:00}:00");
                cmbTimeFrom.Items.Add($"{h:00}:30");
                cmbTimeTo.Items.Add($"{h:00}:00");
                cmbTimeTo.Items.Add($"{h:00}:30");
            }
            _suppressFilter = false;
            // Date range guard: To kabhi From se pehle nahi — galat select par auto-adjust
            Helpers.DateRangeGuard.Wire(dtFrom, dtTo);
            LoadData();
        }

        // ═══ DATA LOADING (real SQLite) ═══

        // Cloud/Web se change aaya (sync pull) → page khud reload — bina refresh ke
        public void OnSyncPulled()
        {
            Dispatcher.BeginInvoke(new Action(() =>
            {
                if (IsLoaded) LoadData();
            }), System.Windows.Threading.DispatcherPriority.Background);
        }

        private void LoadData()
        {
            _allRows.Clear();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();

                cmd.CommandText = @"
                    SELECT s.id,
                           s.invoice_no,
                           s.sale_no,
                           s.sale_date,
                           s.date_time,
                           IFNULL(c.name, 'Walk-in Customer') AS customer,
                           s.customer_id,
                           s.outlet_id,
                           COALESCE(NULLIF(e.ServerId,0), e.Id, s.employee_id) AS salesman_id,
                           IFNULL(e.name, '') AS salesman,
                           (SELECT IFNULL(SUM(sd.qty), 0) FROM sale_details sd WHERE sd.sales_id = s.id) AS items,
                           IFNULL(s.sub_total, 0) AS subtotal,
                           IFNULL(s.vat, 0) AS tax,
                           CASE WHEN IFNULL(s.total_discount_amount, IFNULL(s.disc_actual, IFNULL(s.disc, 0))) > 0
                                THEN IFNULL(s.total_discount_amount, IFNULL(s.disc_actual, IFNULL(s.disc, 0)))
                                ELSE MAX(IFNULL(s.sub_total,0) + IFNULL(s.vat,0) - IFNULL(s.total_payable,0), 0)
                           END AS discount,
                           IFNULL(s.total_payable, 0) AS total_payable,
                           IFNULL(s.paid_amount, 0) AS paid_amount,
                           IFNULL(s.due_amount, 0) AS due_amount
                    FROM sales s
                    LEFT JOIN customers c ON c.id = s.customer_id
                    LEFT JOIN employees e ON e.Id = s.employee_id OR e.ServerId = s.employee_id
                    WHERE s.del_status IS NULL OR s.del_status != 'Deleted'
                    ORDER BY s.id DESC";

                using var r = cmd.ExecuteReader();
                int sn = 0;
                while (r.Read())
                {
                    sn++;
                    double tp = r.IsDBNull(12) ? 0 : r.GetDouble(12);
                    double pa = r.IsDBNull(13) ? 0 : r.GetDouble(13);
                    double da = r.IsDBNull(14) ? 0 : r.GetDouble(14);
                    string invoiceNo = r["invoice_no"]?.ToString() ?? "";
                    if (string.IsNullOrWhiteSpace(invoiceNo))
                        invoiceNo = r["sale_no"]?.ToString() ?? "";
                    DateTime.TryParse(r["sale_date"]?.ToString(), out DateTime dt);

                    // Full date+time — time filter ke liye. date_time mila to use,
                    // warna sale_date (midnight).
                    DateTime dtv = dt == default ? default : dt.Date;
                    if (!r.IsDBNull(4) && DateTime.TryParse(r.GetString(4), out DateTime dtFull))
                        dtv = dtFull;

                    _allRows.Add(new SaleRowItem
                    {
                        Id = r.GetInt64(0),
                        SN = sn.ToString(),
                        InvoiceNo = invoiceNo,
                        Customer = r["customer"]?.ToString() ?? "",
                        CustomerId = r.IsDBNull(6) ? 0 : r.GetInt64(6),
                        OutletId = r.IsDBNull(7) ? 0 : r.GetInt64(7),
                        SalesmanId = r.IsDBNull(8) ? 0 : r.GetInt64(8),
                        Salesman = r["salesman"]?.ToString() ?? "",
                        Items = (r.IsDBNull(10) ? 0 : r.GetDouble(10)).ToString("0.##"),
                        Subtotal = $"₹{(r.IsDBNull(11) ? 0 : r.GetDouble(11)):N2}",
                        Tax = $"₹{(r.IsDBNull(12) ? 0 : r.GetDouble(12)):N2}",
                        Discount = $"₹{(r.IsDBNull(13) ? 0 : r.GetDouble(13)):N2}",
                        SaleDate = r["sale_date"]?.ToString() ?? "",
                        DateValue = dtv == default ? null : dtv.Date,
                        DateTimeValue = dtv == default ? null : dtv,
                        TotalPayable = $"₹{tp:N2}",
                        PaidAmount = $"₹{pa:N2}",
                        DueAmount = $"₹{da:N2}",
                        TotalRaw = tp,
                        PaidRaw = pa,
                        DueRaw = da,
                        Status = da > 0.009 && pa <= 0.009 ? "Unpaid"
                               : da > 0.009 ? "Partial"
                               : "Paid"
                    });
                }
            }
            catch
            {
                // Fallback: Tran1 table (legacy ledger) agar sales table empty/fail ho
                LoadFromTran1();
            }

            _currentPage = 1;
            ApplyFilter();
        }

        private void LoadFromTran1()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"
                    SELECT t.rowid * -1 AS id,
                           t.VchNo AS sale_no,
                           IFNULL(m.Name, 'Walk-in Customer') AS customer,
                           t.VchDate AS sale_date,
                           IFNULL(t.Amount, 0) AS total_payable,
                           IFNULL(t.Amount, 0) AS paid_amount,
                           0 AS due_amount
                    FROM Tran1 t
                    LEFT JOIN Master1 m ON m.Code = t.MasterCode1
                    WHERE t.VchType='Sales' AND t.IsCancelled=0
                    ORDER BY t.rowid DESC";

                using var r = cmd.ExecuteReader();
                int sn = 0;
                while (r.Read())
                {
                    sn++;
                    double tp = r.IsDBNull(4) ? 0 : r.GetDouble(4);
                    double pa = r.IsDBNull(5) ? 0 : r.GetDouble(5);
                    double da = r.IsDBNull(6) ? 0 : r.GetDouble(6);
                    _allRows.Add(new SaleRowItem
                    {
                        Id = r.GetInt64(0),
                        SN = sn.ToString(),
                        InvoiceNo = r["sale_no"]?.ToString() ?? "",
                        Customer = r["customer"]?.ToString() ?? "",
                        SaleDate = r["sale_date"]?.ToString() ?? "",
                        TotalPayable = $"₹{tp:N2}",
                        PaidAmount = $"₹{pa:N2}",
                        DueAmount = $"₹{da:N2}",
                        TotalRaw = tp,
                        PaidRaw = pa,
                        DueRaw = da,
                        Status = da > 0.009 && pa <= 0.009 ? "Unpaid"
                               : da > 0.009 ? "Partial"
                               : "Paid"
                    });
                }
            }
            catch { }
        }

        // ═══ FILTERS ═══

        private void LoadFilterOptions()
        {
            cmbOutlet.Items.Clear();
            cmbOutlet.Items.Add(new FilterOption(0, "All Outlets"));
            cmbCustomer.Items.Clear();
            cmbCustomer.Items.Add(new FilterOption(0, "All Customers"));
            cmbSalesman.Items.Clear();
            cmbSalesman.Items.Add(new FilterOption(0, "All Salesmen"));
            cmbStatus.Items.Clear();
            cmbStatus.Items.Add("All");
            cmbStatus.Items.Add("Paid");
            cmbStatus.Items.Add("Partial");
            cmbStatus.Items.Add("Unpaid");

            try
            {
                using var conn = _db.GetConnection();
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT id, name FROM outlets WHERE (del_status IS NULL OR del_status != 'Deleted') AND name <> '' ORDER BY name";
                    using var r = cmd.ExecuteReader();
                    while (r.Read()) cmbOutlet.Items.Add(new FilterOption(r.GetInt64(0), r["name"]?.ToString() ?? ""));
                }
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT id, name FROM customers WHERE (del_status IS NULL OR del_status != 'Deleted') AND name <> '' ORDER BY name";
                    using var r = cmd.ExecuteReader();
                    while (r.Read()) cmbCustomer.Items.Add(new FilterOption(r.GetInt64(0), r["name"]?.ToString() ?? ""));
                }
                // Salesman — ServerId preference (items/categories jaise hi mapping pattern)
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT Id, ServerId, name FROM employees WHERE (del_status IS NULL OR del_status != 'Deleted') AND name <> '' ORDER BY name";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                        cmbSalesman.Items.Add(new FilterOption(
                            r.IsDBNull(1) || r.GetInt64(1) <= 0 ? r.GetInt64(0) : r.GetInt64(1),
                            r["name"]?.ToString() ?? ""));
                }
            }
            catch { }

            cmbOutlet.SelectedIndex = 0;
            cmbCustomer.SelectedIndex = 0;
            cmbSalesman.SelectedIndex = 0;
            cmbStatus.SelectedIndex = 0;
        }

        private void ApplyFilter()
        {
            string q = txtSearch?.Text?.Trim().ToLower() ?? "";
            long outletId = cmbOutlet?.SelectedItem is FilterOption fo ? fo.Id : 0;
            long customerId = cmbCustomer?.SelectedItem is FilterOption fc ? fc.Id : 0;
            long salesmanId = cmbSalesman?.SelectedItem is FilterOption fs ? fs.Id : 0;
            string status = cmbStatus?.SelectedItem?.ToString() ?? "All";
            DateTime? from = dtFrom?.SelectedDate;
            DateTime? to = dtTo?.SelectedDate;
            if (to.HasValue) to = to.Value.Date.AddDays(1).AddTicks(-1);

            TimeSpan? timeFrom = ParseTime(cmbTimeFrom?.Text);
            TimeSpan? timeTo = ParseTime(cmbTimeTo?.Text);

            _filteredRows = _allRows.Where(x =>
                (string.IsNullOrEmpty(q) ||
                 x.InvoiceNo.ToLower().Contains(q) ||
                 x.Customer.ToLower().Contains(q) ||
                 x.SaleDate.ToLower().Contains(q) ||
                 x.Salesman.ToLower().Contains(q)) &&
                (outletId == 0 || x.OutletId == outletId) &&
                (customerId == 0 || x.CustomerId == customerId) &&
                (salesmanId == 0 || x.SalesmanId == salesmanId) &&
                (status == "All" || x.Status == status) &&
                MatchesFrom(x, from, timeFrom) &&
                MatchesTo(x, to, timeTo)
            ).ToList();

            _totalCount = _filteredRows.Count;
            int totalPages = Math.Max(1, (int)Math.Ceiling((double)_totalCount / _pageSize));
            if (_currentPage > totalPages) _currentPage = totalPages;

            int start = (_currentPage - 1) * _pageSize;
            int end = Math.Min(start + _pageSize, _totalCount);

            var pageItems = _filteredRows.Count == 0
                ? new List<SaleRowItem>()
                : _filteredRows.GetRange(start, Math.Max(0, end - start));
            dgSales.ItemsSource = pageItems;
            bool empty = _filteredRows.Count == 0;
            dgSales.Visibility = empty ? Visibility.Collapsed : Visibility.Visible;
            emptyState.Visibility = empty ? Visibility.Visible : Visibility.Collapsed;

            UpdateFooter(start, end);
            lblPageInfo.Text = _totalCount == 0
                ? ""
                : $"Page {_currentPage} of {totalPages}";
            UpdateTotals();
            BuildPageButtons(totalPages);
        }

        // Date + time mila-kar compare. Sirf time diya (bina date) to din ke time se
        // compare hota hai; sirf date diya to poora din (existing behavior).
        private static bool MatchesFrom(SaleRowItem x, DateTime? from, TimeSpan? timeFrom)
        {
            if (!x.DateTimeValue.HasValue) return true;
            var dt = x.DateTimeValue.Value;
            if (from.HasValue)
            {
                if (dt.Date < from.Value.Date) return false;
                if (dt.Date > from.Value.Date) return true; // is date ke baad ka — ho gaya
            }
            if (timeFrom.HasValue && dt.TimeOfDay < timeFrom.Value) return false;
            return true;
        }

        private static bool MatchesTo(SaleRowItem x, DateTime? to, TimeSpan? timeTo)
        {
            if (!x.DateTimeValue.HasValue) return true;
            var dt = x.DateTimeValue.Value;
            if (to.HasValue)
            {
                if (dt.Date > to.Value.Date) return false;
                if (dt.Date < to.Value.Date) return true; // is date se pehle ka — ho gaya
            }
            if (timeTo.HasValue && dt.TimeOfDay > timeTo.Value) return false;
            return true;
        }

        private static TimeSpan? ParseTime(string? text)
        {
            if (string.IsNullOrWhiteSpace(text)) return null;
            // "HH:mm" ya "H:mm" — 14:30, 9:05
            if (TimeSpan.TryParse(text.Trim(), out var t)) return t;
            return null;
        }

        private void UpdateFooter(int start, int end)
        {
            lblShowing.Text = _totalCount == 0
                ? "Showing 0 entries"
                : $"Showing {start + 1} to {end} of {_totalCount} entries";
        }

        private void UpdateTotals()
        {
            double pay = _filteredRows.Sum(x => x.TotalRaw);
            double paid = _filteredRows.Sum(x => x.PaidRaw);
            double due = _filteredRows.Sum(x => x.DueRaw);
            lblTotPayable.Text = $"₹{pay:N2}";
            lblTotPaid.Text = $"₹{paid:N2}";
            lblTotDue.Text = $"₹{due:N2}";
        }

        // ═══ PAGINATION ═══

        // Windowed pagination — bahut saare pages ho to sirf current ke aas-paas buttons
        // dikhte hain, beech mein ellipsis "…" aata hai.
        private void BuildPageButtons(int totalPages)
        {
            pageButtons.Items.Clear();
            foreach (var p in GetPageWindow(totalPages, _currentPage))
            {
                if (p == 0)
                {
                    pageButtons.Items.Add(new TextBlock
                    {
                        Text = "…",
                        FontSize = 13,
                        Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#64748B")),
                        VerticalAlignment = System.Windows.VerticalAlignment.Center,
                        Margin = new Thickness(2, 0, 2, 0)
                    });
                    continue;
                }

                var btn = new Button
                {
                    Content = p.ToString(),
                    FontSize = 12,
                    FontFamily = new FontFamily("Inter, Segoe UI"),
                    FontWeight = p == _currentPage ? FontWeights.SemiBold : FontWeights.Normal,
                    Padding = new Thickness(10, 5, 10, 5),
                    Cursor = System.Windows.Input.Cursors.Hand,
                    Margin = new Thickness(2, 0, 2, 0),
                    MinWidth = 34,
                    BorderThickness = new Thickness(1),
                    BorderBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#D1D5DB")),
                    Background = p == _currentPage
                        ? new SolidColorBrush((Color)ColorConverter.ConvertFromString("#696CFF"))
                        : Brushes.White,
                    Foreground = p == _currentPage
                        ? Brushes.White
                        : new SolidColorBrush((Color)ColorConverter.ConvertFromString("#374151"))
                };
                btn.Template = CreatePageButtonTemplate();

                int pageNum = p;
                btn.Click += (s, e) => { _currentPage = pageNum; ApplyFilter(); };
                pageButtons.Items.Add(btn);
            }

            btnPrev.IsEnabled = _currentPage > 1;
            btnNext.IsEnabled = _currentPage < totalPages;
            btnPrev.Opacity = btnPrev.IsEnabled ? 1 : 0.5;
            btnNext.Opacity = btnNext.IsEnabled ? 1 : 0.5;
        }

        private static List<int> GetPageWindow(int totalPages, int current, int maxButtons = 7)
        {
            var list = new List<int>();
            if (totalPages <= maxButtons)
            {
                for (int i = 1; i <= totalPages; i++) list.Add(i);
                return list;
            }
            list.Add(1);
            int start = Math.Max(2, current - 1);
            int end = Math.Min(totalPages - 1, current + 1);
            if (start > 2) list.Add(0); // ellipsis
            for (int i = start; i <= end; i++) list.Add(i);
            if (end < totalPages - 1) list.Add(0); // ellipsis
            list.Add(totalPages);
            return list;
        }

        private static ControlTemplate CreatePageButtonTemplate()
        {
            var template = new ControlTemplate(typeof(Button));
            var border = new FrameworkElementFactory(typeof(Border));
            border.SetValue(Border.BackgroundProperty, new TemplateBindingExtension(Button.BackgroundProperty));
            border.SetValue(Border.BorderBrushProperty, new TemplateBindingExtension(Button.BorderBrushProperty));
            border.SetValue(Border.BorderThicknessProperty, new TemplateBindingExtension(Button.BorderThicknessProperty));
            border.SetValue(Border.CornerRadiusProperty, new CornerRadius(7));
            border.SetValue(Border.PaddingProperty, new TemplateBindingExtension(Button.PaddingProperty));
            var content = new FrameworkElementFactory(typeof(ContentPresenter));
            content.SetValue(ContentPresenter.VerticalAlignmentProperty, System.Windows.VerticalAlignment.Center);
            content.SetValue(ContentPresenter.HorizontalAlignmentProperty, System.Windows.HorizontalAlignment.Center);
            border.AppendChild(content);
            template.VisualTree = border;

            var hoverTrigger = new Trigger { Property = Control.IsMouseOverProperty, Value = true };
            hoverTrigger.Setters.Add(new Setter(Border.BackgroundProperty, new SolidColorBrush((Color)ColorConverter.ConvertFromString("#EEF2FF")), border.Name));
            template.Triggers.Add(hoverTrigger);
            template.Seal();
            return template;
        }

        // ═══ EVENT HANDLERS ═══

        private void BtnBack_Click(object sender, RoutedEventArgs e) => _dashboard?.ShowDashboard();
        private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e) { _currentPage = 1; ApplyFilter(); }

        private void BtnToggleFilter_Click(object sender, RoutedEventArgs e)
        {
            if (filterCard.Visibility == Visibility.Collapsed)
            {
                if (cmbOutlet.Items.Count == 0)
                {
                    _suppressFilter = true;
                    LoadFilterOptions();
                    _suppressFilter = false;
                }
                filterCard.Visibility = Visibility.Visible;
            }
            else filterCard.Visibility = Visibility.Collapsed;
        }

        private void BtnApplyFilter_Click(object sender, RoutedEventArgs e) { _currentPage = 1; ApplyFilter(); }

        // Combo se select karte hi filter turant apply — Apply Filter button ka wait nahi
        private void FilterCombo_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (_suppressFilter) return;
            _currentPage = 1;
            ApplyFilter();
        }

        private void CmbPageSize_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (cmbPageSize.SelectedItem is not string s || !int.TryParse(s, out var size)) return;
            _pageSize = size;
            _currentPage = 1;
            ApplyFilter();
        }

        private void BtnClearFilter_Click(object sender, RoutedEventArgs e)
        {
            dtFrom.SelectedDate = null;
            dtTo.SelectedDate = null;
            cmbTimeFrom.Text = "";
            cmbTimeTo.Text = "";
            if (cmbOutlet.Items.Count > 0) cmbOutlet.SelectedIndex = 0;
            if (cmbCustomer.Items.Count > 0) cmbCustomer.SelectedIndex = 0;
            if (cmbSalesman.Items.Count > 0) cmbSalesman.SelectedIndex = 0;
            if (cmbStatus.Items.Count > 0) cmbStatus.SelectedIndex = 0;
            _currentPage = 1;
            ApplyFilter();
        }

        // Time select karte hi filter turant apply
        private void TimeFilter_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (_suppressFilter) return;
            _currentPage = 1;
            ApplyFilter();
        }

        // Editable ComboBox ke andar wale TextBox se type karne par bhi turant apply
        private void CmbTime_Loaded(object sender, RoutedEventArgs e)
        {
            if (sender is not ComboBox cb) return;
            if (cb.Template?.FindName("PART_EditableTextBox", cb) is TextBox tb)
                tb.TextChanged += (_, _) =>
                {
                    if (_suppressFilter) return;
                    _currentPage = 1;
                    ApplyFilter();
                };
        }

        private void BtnPagePrev(object sender, RoutedEventArgs e) { if (_currentPage > 1) { _currentPage--; ApplyFilter(); } }
        private void BtnPageNext(object sender, RoutedEventArgs e)
        {
            int totalPages = Math.Max(1, (int)Math.Ceiling((double)_totalCount / _pageSize));
            if (_currentPage < totalPages) { _currentPage++; ApplyFilter(); }
        }

        private void DgSales_DoubleClick(object sender, System.Windows.Input.MouseButtonEventArgs e)
        {
            if (dgSales.SelectedItem is SaleRowItem row) ViewSale(row.Id);
        }

        private void BtnView_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is long id) ViewSale(id);
        }

        private void BtnPrint_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is long id)
            {
                string temp = Path.Combine(Path.GetTempPath(), "Sale_" + id + "_" + DateTime.Now.Ticks + ".pdf");
                ShowSalePdf(id, temp);
            }
        }

        private void BtnNewSale_Click(object sender, RoutedEventArgs e)
        {
            if (_dashboard == null) return;
            var parent = Parent as Panel;
            if (parent != null)
            {
                parent.Children.Clear();
                parent.Children.Add(new POSPage(_dashboard));
            }
        }

        private void BtnDelete_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is long id)
            {
                var result = MessageBox.Show("Delete this sale? This action cannot be undone.",
                    "Confirm Delete", MessageBoxButton.YesNo, MessageBoxImage.Warning);
                if (result != MessageBoxResult.Yes) return;

                try
                {
                    using var conn = _db.GetConnection();
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = "UPDATE sales SET del_status='Deleted' WHERE id=@id";
                    cmd.Parameters.AddWithValue("@id", id);
                    cmd.ExecuteNonQuery();
                    Services.SyncService.EnqueueSync("sales", id, "delete");
                    _dashboard?.TriggerSync();
                    LoadData();
                }
                catch (Exception ex)
                {
                    MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
                }
            }
        }

        // ═══ EXPORT ═══

        // Export dropdown (CSV / PDF) — jo sales abhi screen par dikh rahe hain
        // (selected page size ke hisaab se) wahi export hote hain.
        private void BtnExport_Click(object sender, RoutedEventArgs e)
        {
            if (btnExport.ContextMenu != null)
            {
                btnExport.ContextMenu.PlacementTarget = btnExport;
                btnExport.ContextMenu.IsOpen = true;
            }
        }

        private List<SaleRowItem> CurrentPageRows()
        {
            if (_filteredRows.Count == 0) return new List<SaleRowItem>();
            int start = (_currentPage - 1) * _pageSize;
            if (start >= _filteredRows.Count) return new List<SaleRowItem>();
            int count = Math.Min(_pageSize, _filteredRows.Count - start);
            return _filteredRows.GetRange(start, count);
        }

        private static string Num(string price) => price.Replace("₹", "").Trim();

        private void BtnExportCsv_Click(object sender, RoutedEventArgs e)
        {
            var rows = CurrentPageRows();
            if (rows.Count == 0) { MessageBox.Show("No data to export.", "Export", MessageBoxButton.OK, MessageBoxImage.Information); return; }

            var dlg = new SaveFileDialog
            {
                Title = "Export Sales",
                Filter = "CSV files (*.csv)|*.csv",
                FileName = "Sales_" + DateTime.Now.ToString("yyyyMMdd_HHmm") + ".csv"
            };
            if (dlg.ShowDialog() != true) return;

            try
            {
                var sb = new System.Text.StringBuilder();
                sb.AppendLine("SN,Invoice No,Date,Customer,Salesman,Items,Subtotal,Tax,Discount,Total,Paid,Due,Status");
                foreach (var row in rows)
                {
                    sb.AppendLine(string.Join(",",
                        Csv(row.SN), Csv(row.InvoiceNo), Csv(row.SaleDate), Csv(row.Customer), Csv(row.Salesman), Csv(row.Items),
                        Csv(Num(row.Subtotal)), Csv(Num(row.Tax)), Csv(Num(row.Discount)), Csv(Num(row.TotalPayable)),
                        Csv(Num(row.PaidAmount)), Csv(Num(row.DueAmount)), Csv(row.Status)));
                }
                File.WriteAllText(dlg.FileName, sb.ToString(), System.Text.Encoding.UTF8);
                MessageBox.Show("Export complete.\n" + dlg.FileName, "Export", MessageBoxButton.OK, MessageBoxImage.Information);
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Export", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void BtnExportPdf_Click(object sender, RoutedEventArgs e)
        {
            var rows = CurrentPageRows();
            if (rows.Count == 0) { MessageBox.Show("No data to export.", "Export", MessageBoxButton.OK, MessageBoxImage.Information); return; }

            var dlg = new SaveFileDialog
            {
                Title = "Export Sales",
                Filter = "PDF files (*.pdf)|*.pdf",
                FileName = "Sales_" + DateTime.Now.ToString("yyyyMMdd_HHmm") + ".pdf"
            };
            if (dlg.ShowDialog() != true) return;

            try
            {
                QuestPDF.Settings.License = LicenseType.Community;
                Document.Create(container =>
                {
                    container.Page(page =>
                    {
                        page.Size(PageSizes.A4.Landscape());
                        page.Margin(30);
                        page.DefaultTextStyle(x => x.FontSize(9));
                        page.Header().Column(col =>
                        {
                            col.Item().Text("Sales List").FontSize(16).Bold();
                            col.Item().PaddingTop(4).Text("Generated: " + DateTime.Now.ToString("dd MMM yyyy, hh:mm tt")).FontSize(9).FontColor("#64748B");
                        });
                        page.Content().PaddingTop(10).Table(table =>
                        {
                            table.ColumnsDefinition(c =>
                            {
                                c.ConstantColumn(28);
                                c.RelativeColumn(1.2f);
                                c.RelativeColumn(1.2f);
                                c.RelativeColumn(1.8f);
                                c.RelativeColumn(1.2f);
                                c.RelativeColumn(0.8f);
                                c.RelativeColumn(1.1f);
                                c.RelativeColumn(0.8f);
                                c.RelativeColumn(0.9f);
                                c.RelativeColumn(1.1f);
                                c.RelativeColumn(1.1f);
                                c.RelativeColumn(1.1f);
                                c.RelativeColumn(1f);
                            });
                            table.Header(h =>
                            {
                                h.Cell().Background("#EEF2FF").Padding(5).Text("SN").Bold();
                                h.Cell().Background("#EEF2FF").Padding(5).Text("Invoice No").Bold();
                                h.Cell().Background("#EEF2FF").Padding(5).Text("Date").Bold();
                                h.Cell().Background("#EEF2FF").Padding(5).Text("Customer").Bold();
                                h.Cell().Background("#EEF2FF").Padding(5).Text("Salesman").Bold();
                                h.Cell().Background("#EEF2FF").Padding(5).Text("Items").Bold();
                                h.Cell().Background("#EEF2FF").Padding(5).Text("Subtotal").Bold();
                                h.Cell().Background("#EEF2FF").Padding(5).Text("Tax").Bold();
                                h.Cell().Background("#EEF2FF").Padding(5).Text("Discount").Bold();
                                h.Cell().Background("#EEF2FF").Padding(5).Text("Total").Bold();
                                h.Cell().Background("#EEF2FF").Padding(5).Text("Paid").Bold();
                                h.Cell().Background("#EEF2FF").Padding(5).Text("Due").Bold();
                                h.Cell().Background("#EEF2FF").Padding(5).Text("Status").Bold();
                            });
                            foreach (var row in rows)
                            {
                                table.Cell().Padding(5).Text(row.SN);
                                table.Cell().Padding(5).Text(row.InvoiceNo);
                                table.Cell().Padding(5).Text(row.SaleDate);
                                table.Cell().Padding(5).Text(row.Customer);
                                table.Cell().Padding(5).Text(row.Salesman);
                                table.Cell().Padding(5).Text(row.Items);
                                table.Cell().Padding(5).Text(Num(row.Subtotal));
                                table.Cell().Padding(5).Text(Num(row.Tax));
                                table.Cell().Padding(5).Text(Num(row.Discount));
                                table.Cell().Padding(5).Text(Num(row.TotalPayable));
                                table.Cell().Padding(5).Text(Num(row.PaidAmount));
                                table.Cell().Padding(5).Text(Num(row.DueAmount));
                                table.Cell().Padding(5).Text(row.Status);
                            }
                        });
                        page.Footer().AlignCenter().Text("Generated by Rashan Ki Dukan POS").FontSize(8).FontColor("#94A3B8");
                    });
                }).GeneratePdf(dlg.FileName);
                PdfPreviewWindow.ShowPdf(dlg.FileName);
            }
            catch (Exception ex)
            {
                MessageBox.Show("PDF export failed: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private static string Csv(string v) => "\"" + v.Replace("\"", "\"\"") + "\"";

        private void ViewSale(long id)
        {
            string temp = Path.Combine(Path.GetTempPath(), "Sale_" + id + "_" + DateTime.Now.Ticks + ".pdf");
            ShowSalePdf(id, temp);
        }

        /// <summary>
        /// Sale ki PDF banao + custom viewer me kholo. Positive id = sales table,
        /// negative id = Tran1 voucher (id = rowid * -1). savePath null = Save dialog.
        /// </summary>
        private void ShowSalePdf(long id, string? savePath)
        {
            if (id > 0)
            {
                Services.PdfService.GenerateSalePdf(id, savePath);
                return;
            }
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT VchCode FROM Tran1 WHERE rowid=@rid LIMIT 1";
                cmd.Parameters.AddWithValue("@rid", -id);
                var vch = cmd.ExecuteScalar()?.ToString();
                if (!string.IsNullOrEmpty(vch))
                    Services.PdfService.GenerateTranPdf(vch, savePath, "SALE INVOICE");
                else
                    MessageBox.Show("Sale not found.", "Print", MessageBoxButton.OK, MessageBoxImage.Warning);
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }
    }

    public class SaleRowItem
    {
        public long Id { get; set; }
        public string SN { get; set; } = "";
        public string InvoiceNo { get; set; } = "";
        public string Customer { get; set; } = "";
        public long CustomerId { get; set; }
        public long OutletId { get; set; }
        public long SalesmanId { get; set; }
        public string Salesman { get; set; } = "";
        public string SaleDate { get; set; } = "";
        public DateTime? DateValue { get; set; }
        public DateTime? DateTimeValue { get; set; }
        public string Items { get; set; } = "";
        public string Subtotal { get; set; } = "";
        public string Tax { get; set; } = "";
        public string Discount { get; set; } = "";
        public string TotalPayable { get; set; } = "";
        public string PaidAmount { get; set; } = "";
        public string DueAmount { get; set; } = "";
        public double TotalRaw { get; set; }
        public double PaidRaw { get; set; }
        public double DueRaw { get; set; }
        public string Status { get; set; } = "Paid";

        public Brush StatusBg => Status switch
        {
            "Unpaid" => new SolidColorBrush((Color)ColorConverter.ConvertFromString("#FEE2E2")),
            "Partial" => new SolidColorBrush((Color)ColorConverter.ConvertFromString("#FFF3CD")),
            _ => new SolidColorBrush((Color)ColorConverter.ConvertFromString("#E8FFD6"))
        };

        public Brush StatusFg => Status switch
        {
            "Unpaid" => new SolidColorBrush((Color)ColorConverter.ConvertFromString("#FF3E1D")),
            "Partial" => new SolidColorBrush((Color)ColorConverter.ConvertFromString("#FFAB00")),
            _ => new SolidColorBrush((Color)ColorConverter.ConvertFromString("#4CAF50"))
        };
    }

    public class FilterOption
    {
        public long Id { get; set; }
        public string Name { get; set; } = "";
        public FilterOption(long id, string name) { Id = id; Name = name; }
    }
}
