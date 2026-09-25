using System;
using System.Data;
using System.IO;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Documents;
using System.Windows.Media;
using RashanKiDukan.Database;
using RashanKiDukan.Models;

namespace RashanKiDukan.Views
{
    public partial class PurchaseReturnReportPage : UserControl
    {
        private readonly MainDashboard _dashboard;
        private readonly DatabaseService _db = new();
        private DataTable _allData = new DataTable();

        public PurchaseReturnReportPage(MainDashboard dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () => dataGrid.ItemsSource as DataView, "Purchase Return Report");
            _dashboard = dashboard;
            dpDateFrom.SelectedDate = new DateTime(DateTime.Today.Year, DateTime.Today.Month, 1);
            dpDateTo.SelectedDate = DateTime.Today;
            LoadFilters();
            LoadReport();
        }

        private void LoadFilters()
        {
            var outletList = new System.Collections.Generic.List<ReportComboItem> { new(0, "All Outlets") };
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, IFNULL(outlet_name,name) FROM outlets WHERE (del_status IS NULL OR del_status='Live') ORDER BY id";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    outletList.Add(new ReportComboItem(r.GetInt64(0), r.GetString(1)));
            }
            catch { }
            cmbOutlet.ItemsSource = outletList;
            cmbOutlet.SelectedIndex = 0;

            var supplierList = new System.Collections.Generic.List<ReportComboItem> { new(0, "All Suppliers") };
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, name FROM suppliers WHERE (del_status IS NULL OR del_status='Live') ORDER BY name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    supplierList.Add(new ReportComboItem(r.GetInt64(0), r.GetString(1)));
            }
            catch { }
            cmbSupplier.ItemsSource = supplierList;
            cmbSupplier.SelectedIndex = 0;
        }

        private void LoadReport(DateTime? dateFrom = null, DateTime? dateTo = null, string outlet = "", long supplierId = 0, long outletId = 0)
        {
            try
            {
                using var conn = _db.GetConnection();

                string dateCond(string col) => dateFrom.HasValue && dateTo.HasValue
                    ? $" AND {col} BETWEEN '{dateFrom.Value:yyyy-MM-dd}' AND '{dateTo.Value:yyyy-MM-dd}'"
                    : dateFrom.HasValue ? $" AND {col} >= '{dateFrom.Value:yyyy-MM-dd}'"
                    : dateTo.HasValue ? $" AND {col} <= '{dateTo.Value:yyyy-MM-dd}'"
                    : "";
                string outletCond(string tbl) => outletId > 0 ? $" AND {tbl}.outlet_id = {outletId}" : "";
                string supplierCond = supplierId > 0 ? $" AND pr.supplier_id={supplierId}" : "";

                var dt = new DataTable();
                dt.Columns.Add("sn", typeof(int));
                dt.Columns.Add("reference_no", typeof(string));
                dt.Columns.Add("date_time", typeof(string));
                dt.Columns.Add("supplier", typeof(string));
                dt.Columns.Add("items", typeof(int));
                dt.Columns.Add("payment_method", typeof(string));
                dt.Columns.Add("amount", typeof(double));
                dt.Columns.Add("_amountRaw", typeof(double));

                double tAmount = 0;
                int sn = 0;

                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $@"SELECT pr.id, pr.reference_no, pr.created_at,
                        pr.supplier_id, pr.payment_method_id, pr.total_return_amount,
                        (SELECT COUNT(*) FROM purchase_return_details prd WHERE prd.pur_return_id=pr.id AND (prd.del_status IS NULL OR prd.del_status='Live')) AS total_items
                        FROM purchase_returns pr
                        WHERE (pr.del_status IS NULL OR pr.del_status='Live')
                        {dateCond("pr.date")}
                        {outletCond("pr")}
                        {supplierCond}
                        ORDER BY pr.date DESC, pr.id DESC";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        sn++;
                        long id = r.IsDBNull(0) ? 0 : r.GetInt64(0);
                        string refNo = r.IsDBNull(1) ? "-" : r.GetString(1);
                        var createdAt = r.IsDBNull(2) ? (object)null : r.GetValue(2);
                        long suppId = r.IsDBNull(3) ? 0 : Convert.ToInt64(r.GetValue(3));
                        long payMethodId = r.IsDBNull(4) ? 0 : Convert.ToInt64(r.GetValue(4));
                        double amount = r.IsDBNull(5) ? 0 : Convert.ToDouble(r.GetValue(5));
                        int items = r.IsDBNull(6) ? 0 : Convert.ToInt32(r.GetValue(6));

                        string dtStr = createdAt is DateTime d ? d.ToString("dd/MM/yyyy hh:mm tt") : createdAt?.ToString() ?? "";

                        // Supplier name + phone
                        string supplierName = "-";
                        if (suppId > 0)
                        {
                            using var sc = conn.CreateCommand();
                            sc.CommandText = "SELECT name, phone FROM suppliers WHERE id=@sid AND (del_status IS NULL OR del_status='Live')";
                            sc.Parameters.AddWithValue("@sid", suppId);
                            using var sr = sc.ExecuteReader();
                            if (sr.Read())
                            {
                                supplierName = sr.IsDBNull(0) ? "-" : sr.GetString(0);
                                if (!sr.IsDBNull(1))
                                {
                                    string phone = sr.GetString(1);
                                    if (!string.IsNullOrEmpty(phone))
                                        supplierName += " (" + phone + ")";
                                }
                            }
                        }

                        // Payment method
                        string payMethod = "-";
                        if (payMethodId > 0)
                        {
                            using var pc = conn.CreateCommand();
                            pc.CommandText = $"SELECT name FROM payment_methods WHERE id={payMethodId}";
                            var pv = pc.ExecuteScalar();
                            if (pv != null) payMethod = pv.ToString() ?? "-";
                        }

                        tAmount += amount;
                        dt.Rows.Add(sn, refNo, dtStr, supplierName, items, payMethod, amount, amount);
                    }
                }

                _allData = dt;
                dataGrid.ItemsSource = null;
                dataGrid.ItemsSource = dt.DefaultView;

                bool hasData = dt.Rows.Count > 0;
                emptyState.Visibility = hasData ? Visibility.Collapsed : Visibility.Visible;
                dataGrid.Visibility = hasData ? Visibility.Visible : Visibility.Collapsed;
                totalsBar.Visibility = hasData ? Visibility.Visible : Visibility.Collapsed;
                rowCountBar.Visibility = hasData ? Visibility.Visible : Visibility.Collapsed;

                lblTotal.Text = "₹ " + tAmount.ToString("N2");
                lblRowCount.Text = $"Showing {dt.Rows.Count} entries";
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            if (_dashboard != null)
                _dashboard.ShowPage(new ReportsPage(_dashboard));
        }

        private void BtnToggleFilter_Click(object sender, RoutedEventArgs e)
        {
            filterSection.Visibility = filterSection.Visibility == Visibility.Visible
                ? Visibility.Collapsed : Visibility.Visible;
        }

        private void BtnApplyFilter_Click(object sender, RoutedEventArgs e)
        {
            DateTime? from = dpDateFrom.SelectedDate;
            DateTime? to = dpDateTo.SelectedDate;
            var outletItem = cmbOutlet.SelectedItem as ReportComboItem;
            string outlet = outletItem?.Name ?? "";
            long outId = outletItem?.Id ?? 0;
            long supId = (cmbSupplier.SelectedItem as ReportComboItem)?.Id ?? 0;

            LoadReport(from, to, outlet, supId, outId);

            filterInfoBar.Visibility = Visibility.Visible;
            filterInfoPanel.Children.Clear();

            if (from.HasValue)
                filterInfoPanel.Children.Add(new TextBlock
                {
                    Text = "Date: " + from.Value.ToString("dd/MM/yyyy") + (to.HasValue ? " – " + to.Value.ToString("dd/MM/yyyy") : ""),
                    FontSize = 11.5, FontFamily = new FontFamily("Inter, Segoe UI"),
                    Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0x65, 0x34)),
                    Margin = new Thickness(0, 0, 16, 0)
                });

            if (!string.IsNullOrEmpty(outlet) && outlet != "All Outlets")
                filterInfoPanel.Children.Add(new TextBlock
                {
                    Text = "Outlet: " + outlet,
                    FontSize = 11.5, FontFamily = new FontFamily("Inter, Segoe UI"),
                    Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0x65, 0x34)),
                    Margin = new Thickness(0, 0, 16, 0)
                });

            if (supId > 0)
                filterInfoPanel.Children.Add(new TextBlock
                {
                    Text = "Supplier: " + (cmbSupplier.SelectedItem as ReportComboItem)?.Name,
                    FontSize = 11.5, FontFamily = new FontFamily("Inter, Segoe UI"),
                    Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0x65, 0x34))
                });

            filterSection.Visibility = Visibility.Collapsed;
        }

        private void BtnCancelFilter_Click(object sender, RoutedEventArgs e)
        {
            filterSection.Visibility = Visibility.Collapsed;
        }

        private void BtnExport_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = !exportPopup.IsOpen;
        }

        private void BtnPrint_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            var dlg = new PrintDialog();
            if (dlg.ShowDialog() != true) return;

            var dv = dataGrid.ItemsSource as DataView;
            if (dv == null || dv.Count == 0) { MessageBox.Show("No data to print.", "Info"); return; }

            var doc = new FlowDocument { FontFamily = new FontFamily("Segoe UI"), FontSize = 11, PagePadding = new Thickness(40) };
            doc.Blocks.Add(new Paragraph(new Run("Purchase Return Report")) { FontSize = 16, FontWeight = FontWeights.Bold, Margin = new Thickness(0, 0, 0, 6) });
            if (dpDateFrom.SelectedDate.HasValue)
                doc.Blocks.Add(new Paragraph(new Run($"Period: {dpDateFrom.SelectedDate:dd MMM yyyy} to {dpDateTo.SelectedDate:dd MMM yyyy}")) { Foreground = Brushes.Gray, Margin = new Thickness(0, 0, 0, 12) });

            var tbl = new System.Windows.Documents.Table { CellSpacing = 2 };
            string[] headers = { "SN", "REF NO", "DATE TIME", "SUPPLIER", "ITEMS", "PAYMENT METHOD", "AMOUNT" };
            for (int i = 0; i < headers.Length; i++) tbl.Columns.Add(new TableColumn { Width = new GridLength(1, GridUnitType.Star) });
            var rg = new TableRowGroup();
            var hdr = new TableRow { Background = new SolidColorBrush(Color.FromRgb(0x69, 0x6c, 0xff)) };
            foreach (var h in headers)
                hdr.Cells.Add(new TableCell(new Paragraph(new Run(h))) { Padding = new Thickness(4, 3, 4, 3), Foreground = Brushes.White, FontWeight = FontWeights.SemiBold });
            rg.Rows.Add(hdr);
            bool alt = false;
            foreach (DataRowView rv in dv)
            {
                var tr = new TableRow { Background = alt ? new SolidColorBrush(Color.FromRgb(0xFA, 0xFA, 0xFF)) : Brushes.White };
                string[] vals = {
                    rv["sn"].ToString(), rv["reference_no"].ToString(), rv["date_time"].ToString(),
                    rv["supplier"].ToString(), rv["items"].ToString(), rv["payment_method"].ToString(),
                    "₹ " + Convert.ToDouble(rv["amount"]).ToString("N2")
                };
                foreach (var v in vals)
                    tr.Cells.Add(new TableCell(new Paragraph(new Run(v))) { Padding = new Thickness(4, 2, 4, 2) });
                rg.Rows.Add(tr);
                alt = !alt;
            }
            tbl.RowGroups.Add(rg);
            doc.Blocks.Add(tbl);
            var pag = ((IDocumentPaginatorSource)doc).DocumentPaginator;
            pag.PageSize = new Size(dlg.PrintableAreaWidth, dlg.PrintableAreaHeight);
            dlg.PrintDocument(pag, "Purchase Return Report");
        }

        private void BtnExportCsv_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            var dv = dataGrid.ItemsSource as DataView;
            if (dv == null || dv.Count == 0) { MessageBox.Show("No data to export.", "Info"); return; }

            var sfd = new Microsoft.Win32.SaveFileDialog { Filter = "CSV|*.csv", FileName = "PurchaseReturnReport.csv" };
            if (sfd.ShowDialog() != true) return;

            using var sw = new StreamWriter(sfd.FileName, false, System.Text.Encoding.UTF8);
            sw.WriteLine("SN,REFERENCE NO,DATE TIME,SUPPLIER,ITEMS,PAYMENT METHOD,AMOUNT");
            foreach (DataRowView rv in dv)
            {
                sw.WriteLine(string.Join(",",
                    rv["sn"], rv["reference_no"], "\"" + rv["date_time"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["supplier"].ToString().Replace("\"", "\"\"") + "\"",
                    rv["items"],
                    "\"" + rv["payment_method"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + Convert.ToDouble(rv["amount"]).ToString("N2") + "\""));
            }
            MessageBox.Show("Exported successfully.", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
        }
    }
}
