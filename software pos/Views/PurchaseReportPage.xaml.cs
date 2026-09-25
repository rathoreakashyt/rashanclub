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
    public partial class PurchaseReportPage : UserControl
    {
        private readonly MainDashboard _dashboard;
        private readonly DatabaseService _db = new();
        private DataTable _allData = new DataTable();

        public PurchaseReportPage(MainDashboard dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () => dataGrid.ItemsSource as DataView, "Purchase Report");
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
                string supplierCond = supplierId > 0 ? $" AND p.supplier_id={supplierId}" : "";

                var dt = new DataTable();
                dt.Columns.Add("sn", typeof(int));
                dt.Columns.Add("reference_no", typeof(string));
                dt.Columns.Add("date_time", typeof(string));
                dt.Columns.Add("purchase_date", typeof(string));
                dt.Columns.Add("supplier", typeof(string));
                dt.Columns.Add("items", typeof(int));
                dt.Columns.Add("grand_total", typeof(double));
                dt.Columns.Add("paid", typeof(double));
                dt.Columns.Add("due", typeof(double));
                dt.Columns.Add("purchase_by", typeof(string));
                dt.Columns.Add("_grand_raw", typeof(double));
                dt.Columns.Add("_paid_raw", typeof(double));
                dt.Columns.Add("_due_raw", typeof(double));

                double tGrand = 0, tPaid = 0, tDue = 0;
                int sn = 0;

                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $@"SELECT p.id, p.reference_no, p.created_at, p.date,
                        p.grand_total, p.paid, p.due_amount, p.user_id, p.supplier_id,
                        (SELECT COUNT(*) FROM purchase_details pd WHERE pd.purchase_id=p.id AND (pd.del_status IS NULL OR pd.del_status='Live')) AS total_items
                        FROM purchases p
                        WHERE (p.del_status IS NULL OR p.del_status='Live')
                        {dateCond("p.date")}
                        {outletCond("p")}
                        {supplierCond}
                        ORDER BY p.date DESC, p.id DESC";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        sn++;
                        long purchaseId = r.IsDBNull(0) ? 0 : r.GetInt64(0);
                        string refNo = r.IsDBNull(1) ? "-" : r.GetString(1);
                        var createdAt = r.IsDBNull(2) ? (object)null : r.GetValue(2);
                        var purchaseDate = r.IsDBNull(3) ? (object)null : r.GetValue(3);
                        double grandTotal = r.IsDBNull(4) ? 0 : Convert.ToDouble(r.GetValue(4));
                        double paid = r.IsDBNull(5) ? 0 : Convert.ToDouble(r.GetValue(5));
                        double due = r.IsDBNull(6) ? 0 : Convert.ToDouble(r.GetValue(6));
                        long userId = r.IsDBNull(7) ? 0 : Convert.ToInt64(r.GetValue(7));
                        long suppId = r.IsDBNull(8) ? 0 : Convert.ToInt64(r.GetValue(8));
                        int items = r.IsDBNull(9) ? 0 : Convert.ToInt32(r.GetValue(9));

                        string dtStr = createdAt is DateTime d1 ? d1.ToString("dd/MM/yyyy hh:mm tt") : createdAt?.ToString() ?? "";
                        string pdStr = purchaseDate is DateTime d2 ? d2.ToString("dd/MM/yyyy") : purchaseDate?.ToString() ?? "";

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

                        // Purchase by (user) name + phone
                        string purchaseBy = "-";
                        if (userId > 0)
                        {
                            using var uc = conn.CreateCommand();
                            uc.CommandText = $"SELECT name, phone FROM users WHERE id={userId}";
                            using var ur = uc.ExecuteReader();
                            if (ur.Read())
                            {
                                purchaseBy = ur.IsDBNull(0) ? "-" : ur.GetString(0);
                                if (!ur.IsDBNull(1))
                                {
                                    string phone = ur.GetString(1);
                                    if (!string.IsNullOrEmpty(phone))
                                        purchaseBy += " (" + phone + ")";
                                }
                            }
                        }

                        tGrand += grandTotal;
                        tPaid += paid;
                        tDue += due;

                        dt.Rows.Add(sn, refNo, dtStr, pdStr, supplierName, items, grandTotal, paid, due, purchaseBy, grandTotal, paid, due);
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

                lblGrandTotal.Text = "₹ " + tGrand.ToString("N2");
                lblPaid.Text = "₹ " + tPaid.ToString("N2");
                lblDue.Text = "₹ " + tDue.ToString("N2");
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
            doc.Blocks.Add(new Paragraph(new Run("Purchase Report")) { FontSize = 16, FontWeight = FontWeights.Bold, Margin = new Thickness(0, 0, 0, 6) });
            if (dpDateFrom.SelectedDate.HasValue)
                doc.Blocks.Add(new Paragraph(new Run($"Period: {dpDateFrom.SelectedDate:dd MMM yyyy} to {dpDateTo.SelectedDate:dd MMM yyyy}")) { Foreground = Brushes.Gray, Margin = new Thickness(0, 0, 0, 12) });

            var tbl = new System.Windows.Documents.Table { CellSpacing = 2 };
            string[] headers = { "SN", "REF NO", "DATE TIME", "PURCHASE DATE", "SUPPLIER", "ITEMS", "GRAND TOTAL", "PAID", "DUE", "PURCHASE BY" };
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
                    rv["purchase_date"].ToString(), rv["supplier"].ToString(), rv["items"].ToString(),
                    "₹ " + Convert.ToDouble(rv["grand_total"]).ToString("N2"),
                    "₹ " + Convert.ToDouble(rv["paid"]).ToString("N2"),
                    "₹ " + Convert.ToDouble(rv["due"]).ToString("N2"),
                    rv["purchase_by"].ToString()
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
            dlg.PrintDocument(pag, "Purchase Report");
        }

        private void BtnExportCsv_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            var dv = dataGrid.ItemsSource as DataView;
            if (dv == null || dv.Count == 0) { MessageBox.Show("No data to export.", "Info"); return; }

            var sfd = new Microsoft.Win32.SaveFileDialog { Filter = "CSV|*.csv", FileName = "PurchaseReport.csv" };
            if (sfd.ShowDialog() != true) return;

            using var sw = new StreamWriter(sfd.FileName, false, System.Text.Encoding.UTF8);
            sw.WriteLine("SN,REFERENCE NO,DATE TIME,PURCHASE DATE,SUPPLIER,ITEMS,GRAND TOTAL,PAID,DUE,PURCHASE BY");
            foreach (DataRowView rv in dv)
            {
                sw.WriteLine(string.Join(",",
                    rv["sn"], rv["reference_no"], "\"" + rv["date_time"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["purchase_date"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["supplier"].ToString().Replace("\"", "\"\"") + "\"",
                    rv["items"],
                    "\"" + Convert.ToDouble(rv["grand_total"]).ToString("N2") + "\"",
                    "\"" + Convert.ToDouble(rv["paid"]).ToString("N2") + "\"",
                    "\"" + Convert.ToDouble(rv["due"]).ToString("N2") + "\"",
                    "\"" + rv["purchase_by"].ToString().Replace("\"", "\"\"") + "\""));
            }
            MessageBox.Show("Exported successfully.", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
        }
    }
}
