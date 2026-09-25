using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;
using Microsoft.Win32;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views
{
    public class PurchaseRow
    {
        public int Sn { get; set; }
        public long Id { get; set; }
        public string ReferenceNo { get; set; } = "";
        public string Supplier { get; set; } = "";
        public string Date { get; set; } = "";
        public string InvoiceNo { get; set; } = "";
        public string Items { get; set; } = "";
        public string GrandTotal { get; set; } = "";
        public string Paid { get; set; } = "";
        public string Due { get; set; } = "";
        public double TotalRaw { get; set; }
        public double PaidRaw { get; set; }
        public double DueRaw { get; set; }
        public DateTime? DateValue { get; set; }
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

    public partial class PurchaseListPage : UserControl, ISyncRefreshable
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();
        private List<PurchaseRow> _allRows = new();
        private List<PurchaseRow> _filteredRows = new();
        private int _currentPage = 1;
        private const int PageSize = 10;

        public PurchaseListPage() { InitializeComponent(); }
        public PurchaseListPage(MainDashboard dashboard) : this() { _dashboard = dashboard; LoadData(); }

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
                cmd.CommandText = @"SELECT p.Id, p.reference_no, p.date, p.invoice_no,
                                           IFNULL(p.grand_total,0) AS grand_total,
                                           IFNULL(p.paid,0) AS paid,
                                           IFNULL(p.due_amount,0) AS due_amount,
                                           COALESCE(s.name, '') AS supplier,
                                           (SELECT IFNULL(SUM(d.quantity_amount),0) FROM purchase_details d WHERE d.purchase_id = p.Id) AS items
                                    FROM purchases p LEFT JOIN Suppliers s ON s.Id = p.supplier_id
                                    WHERE (p.del_status IS NULL OR p.del_status='Live')
                                    ORDER BY p.Id DESC";
                using var r = cmd.ExecuteReader();
                int sn = 1;
                while (r.Read())
                {
                    long id = r["Id"] is long idv ? idv : 0;
                    double total = r["grand_total"] is double gt ? gt : 0;
                    double paid = r["paid"] is double pd ? pd : 0;
                    double due = r["due_amount"] is double dd ? dd : 0;
                    DateTime.TryParse(r["date"]?.ToString(), out DateTime dt);
                    string status = due > 0.009 && paid <= 0.009 ? "Unpaid"
                                  : due > 0.009 ? "Partial"
                                  : "Paid";

                    _allRows.Add(new PurchaseRow
                    {
                        Sn = sn++,
                        Id = id,
                        ReferenceNo = r["reference_no"]?.ToString() ?? "",
                        Supplier = r["supplier"]?.ToString() ?? "",
                        Date = r["date"]?.ToString() ?? "",
                        DateValue = dt == default ? null : dt.Date,
                        InvoiceNo = r["invoice_no"]?.ToString() ?? "",
                        Items = (r["items"] is double it ? it : 0).ToString("0.##"),
                        GrandTotal = $"₹ {total:N2}",
                        Paid = $"₹ {paid:N2}",
                        Due = $"₹ {due:N2}",
                        TotalRaw = total,
                        PaidRaw = paid,
                        DueRaw = due,
                        Status = status
                    });
                }
            }
            catch { }
            _currentPage = 1;
            ApplyFilter();
        }

        // ═══ FILTERS ═══

        private void LoadFilterOptions()
        {
            cmbStatus.Items.Clear();
            cmbStatus.Items.Add("All");
            cmbStatus.Items.Add("Paid");
            cmbStatus.Items.Add("Partial");
            cmbStatus.Items.Add("Unpaid");
            cmbStatus.SelectedIndex = 0;
        }

        private void ApplyFilter()
        {
            string q = txtSearch?.Text?.Trim().ToLowerInvariant() ?? "";
            string statusF = cmbStatus?.SelectedItem?.ToString() ?? "All";
            DateTime? from = dtFrom?.SelectedDate;
            DateTime? to = dtTo?.SelectedDate;
            if (to.HasValue) to = to.Value.Date.AddDays(1).AddTicks(-1);

            _filteredRows = _allRows.Where(x =>
                (string.IsNullOrEmpty(q) ||
                 x.ReferenceNo.ToLowerInvariant().Contains(q) ||
                 x.Supplier.ToLowerInvariant().Contains(q) ||
                 x.InvoiceNo.ToLowerInvariant().Contains(q) ||
                 x.Date.ToLowerInvariant().Contains(q)) &&
                (statusF == "All" || x.Status == statusF) &&
                (!from.HasValue || (x.DateValue.HasValue && x.DateValue >= from.Value.Date)) &&
                (!to.HasValue || (x.DateValue.HasValue && x.DateValue <= to.Value))
            ).ToList();

            int totalPages = Math.Max(1, (int)Math.Ceiling((double)_filteredRows.Count / PageSize));
            if (_currentPage > totalPages) _currentPage = totalPages;

            int start = (_currentPage - 1) * PageSize;
            int end = Math.Min(start + PageSize, _filteredRows.Count);
            var pageItems = _filteredRows.GetRange(start, Math.Max(0, end - start));

            dgPurchases.ItemsSource = pageItems;
            bool empty = _filteredRows.Count == 0;
            dgPurchases.Visibility = empty ? Visibility.Collapsed : Visibility.Visible;
            emptyState.Visibility = empty ? Visibility.Visible : Visibility.Collapsed;

            lblShowing.Text = _filteredRows.Count == 0
                ? "Showing 0 entries"
                : $"Showing {start + 1} to {end} of {_filteredRows.Count} entries";

            lblTotTotal.Text = $"₹ {_filteredRows.Sum(x => x.TotalRaw):N2}";
            lblTotPaid.Text = $"₹ {_filteredRows.Sum(x => x.PaidRaw):N2}";
            lblTotDue.Text = $"₹ {_filteredRows.Sum(x => x.DueRaw):N2}";

            BuildPageButtons(totalPages);
        }

        private void BuildPageButtons(int totalPages)
        {
            pageButtons.Items.Clear();
            for (int p = 1; p <= totalPages; p++)
            {
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
                        : new SolidColorBrush(Colors.White),
                    Foreground = p == _currentPage
                        ? new SolidColorBrush(Colors.White)
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
            content.SetValue(ContentPresenter.VerticalAlignmentProperty, VerticalAlignment.Center);
            content.SetValue(ContentPresenter.HorizontalAlignmentProperty, HorizontalAlignment.Center);
            border.AppendChild(content);
            template.VisualTree = border;

            var hoverTrigger = new Trigger { Property = Control.IsMouseOverProperty, Value = true };
            hoverTrigger.Setters.Add(new Setter(Border.BackgroundProperty, new SolidColorBrush((Color)ColorConverter.ConvertFromString("#EEF2FF")), border.Name));
            template.Triggers.Add(hoverTrigger);
            template.Seal();
            return template;
        }

        // ═══ EVENT HANDLERS ═══

        private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (txtSearch == null) return;
            _currentPage = 1;
            ApplyFilter();
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowDashboard();
        }

        private void BtnAdd_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowPage(new PurchaseCreatePage(_dashboard!));
        }

        private void BtnToggleFilter_Click(object sender, RoutedEventArgs e)
        {
            if (filterCard.Visibility == Visibility.Collapsed)
            {
                LoadFilterOptions();
                filterCard.Visibility = Visibility.Visible;
            }
            else filterCard.Visibility = Visibility.Collapsed;
        }

        private void BtnApplyFilter_Click(object sender, RoutedEventArgs e) { _currentPage = 1; ApplyFilter(); }

        private void BtnClearFilter_Click(object sender, RoutedEventArgs e)
        {
            dtFrom.SelectedDate = null;
            dtTo.SelectedDate = null;
            if (cmbStatus.Items.Count > 0) cmbStatus.SelectedIndex = 0;
            _currentPage = 1;
            ApplyFilter();
        }

        private void BtnPagePrev(object sender, RoutedEventArgs e) { if (_currentPage > 1) { _currentPage--; ApplyFilter(); } }
        private void BtnPageNext(object sender, RoutedEventArgs e)
        {
            int totalPages = Math.Max(1, (int)Math.Ceiling((double)_filteredRows.Count / PageSize));
            if (_currentPage < totalPages) { _currentPage++; ApplyFilter(); }
        }

        private void BtnCsvExport_Click(object sender, RoutedEventArgs e)
        {
            if (_filteredRows.Count == 0) { MessageBox.Show("No data to export.", "Export", MessageBoxButton.OK, MessageBoxImage.Information); return; }

            var dlg = new SaveFileDialog
            {
                Title = "Export Purchases",
                Filter = "CSV files (*.csv)|*.csv",
                FileName = "Purchases_" + DateTime.Now.ToString("yyyyMMdd_HHmm") + ".csv"
            };
            if (dlg.ShowDialog() != true) return;

            try
            {
                var sb = new System.Text.StringBuilder();
                sb.AppendLine("SN,Ref No,Date,Supplier,Invoice No,Items,Grand Total,Paid,Due,Status");
                foreach (var x in _filteredRows)
                {
                    sb.AppendLine(string.Join(",",
                        Csv(x.Sn.ToString()), Csv(x.ReferenceNo), Csv(x.Date), Csv(x.Supplier), Csv(x.InvoiceNo),
                        Csv(x.Items), Csv(x.GrandTotal), Csv(x.Paid), Csv(x.Due), Csv(x.Status)));
                }
                File.WriteAllText(dlg.FileName, sb.ToString(), System.Text.Encoding.UTF8);
                MessageBox.Show("Export complete.\n" + dlg.FileName, "Export", MessageBoxButton.OK, MessageBoxImage.Information);
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Export", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private static string Csv(string v) => "\"" + v.Replace("\"", "\"\"") + "\"";

        private void BtnView_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is long id)
                new VoucherDetailWindow(_dashboard, false, id).Show();
        }

        private void BtnPrint_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is long id)
                new VoucherDetailWindow(_dashboard, false, id, true).Show();
        }

        private void BtnEdit_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is long id)
                _dashboard?.ShowPage(new PurchaseCreatePage(_dashboard!, id));
        }

        private void BtnDelete_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is not long id) return;
            var result = MessageBox.Show("Delete this purchase? Stock will be reverted.", "Confirm", MessageBoxButton.YesNo, MessageBoxImage.Question);
            if (result != MessageBoxResult.Yes) return;

            try
            {
                using var conn = _db.GetConnection();
                using var tx = conn.BeginTransaction();
                var revert = new List<(long Item, double Qty)>();
                using (var rd = conn.CreateCommand())
                {
                    rd.Transaction = tx;
                    rd.CommandText = "SELECT item_id, quantity_amount FROM purchase_details WHERE purchase_id=@id";
                    rd.Parameters.AddWithValue("@id", id);
                    using var r = rd.ExecuteReader();
                    while (r.Read())
                        revert.Add((r["item_id"] is long iid ? iid : 0,
                                    r["quantity_amount"] is double qd ? qd : 0));
                }
                foreach (var (item, q) in revert)
                {
                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.Transaction = tx;
                        cmd.CommandText = "UPDATE items SET stock_quantity = MAX(IFNULL(stock_quantity,0) - @q, 0) WHERE ServerId=@item";
                        cmd.Parameters.AddWithValue("@q", q);
                        cmd.Parameters.AddWithValue("@item", item);
                        cmd.ExecuteNonQuery();
                    }
                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.Transaction = tx;
                        cmd.CommandText = "UPDATE Master1 SET CurrentStock = MAX(IFNULL(CurrentStock,0) - @q, 0) WHERE ServerId=@item AND MasterType='Item'";
                        cmd.Parameters.AddWithValue("@q", q);
                        cmd.Parameters.AddWithValue("@item", item);
                        cmd.ExecuteNonQuery();
                    }
                }
                using (var cmd = conn.CreateCommand())
                {
                    cmd.Transaction = tx;
                    cmd.CommandText = "DELETE FROM purchase_payments WHERE purchase_id=@id";
                    cmd.Parameters.AddWithValue("@id", id);
                    cmd.ExecuteNonQuery();
                }
                using (var cmd = conn.CreateCommand())
                {
                    cmd.Transaction = tx;
                    cmd.CommandText = "DELETE FROM purchase_details WHERE purchase_id=@id";
                    cmd.Parameters.AddWithValue("@id", id);
                    cmd.ExecuteNonQuery();
                }
                using (var cmd = conn.CreateCommand())
                {
                    cmd.Transaction = tx;
                    cmd.CommandText = "UPDATE purchases SET del_status='Deleted', updated_at=datetime('now') WHERE Id=@id";
                    cmd.Parameters.AddWithValue("@id", id);
                    cmd.ExecuteNonQuery();
                }
                tx.Commit();
                Services.SyncService.EnqueueSync("purchases", id, "delete");
                _dashboard?.TriggerSync();
                LoadData();
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }
    }
}
