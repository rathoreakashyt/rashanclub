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
    public partial class DetailedPriceHistoryReportPage : UserControl
    {
        private readonly MainDashboard _dashboard;
        private readonly DatabaseService _db = new();
        private DataTable _allData = new DataTable();

        public DetailedPriceHistoryReportPage(MainDashboard dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () => dataGrid.ItemsSource as DataView, "Detailed Price History Report");
            _dashboard = dashboard;
            dpDateFrom.SelectedDate = new DateTime(DateTime.Today.Year, DateTime.Today.Month, 1);
            dpDateTo.SelectedDate = DateTime.Today;
            LoadFilters();
            LoadReport();
        }

        private void LoadFilters()
        {
            var itemList = new System.Collections.Generic.List<ReportComboItem> { new(0, "All Items") };
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, name FROM items WHERE (del_status IS NULL OR del_status='Live') ORDER BY name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    itemList.Add(new ReportComboItem(r.GetInt64(0), r.GetString(1)));
            }
            catch { }
            cmbItem.ItemsSource = itemList;
            cmbItem.SelectedIndex = 0;

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
        }

        private void LoadReport(DateTime? dateFrom = null, DateTime? dateTo = null, long itemId = 0, string outlet = "", long outletId = 0)
        {
            try
            {
                using var conn = _db.GetConnection();

                string dateCond(string col) => dateFrom.HasValue && dateTo.HasValue
                    ? $" AND {col} BETWEEN '{dateFrom.Value:yyyy-MM-dd}' AND '{dateTo.Value:yyyy-MM-dd}'"
                    : dateFrom.HasValue ? $" AND {col} >= '{dateFrom.Value:yyyy-MM-dd}'"
                    : dateTo.HasValue ? $" AND {col} <= '{dateTo.Value:yyyy-MM-dd}'"
                    : "";
                string itemCond = itemId > 0 ? $" AND pd.item_id={itemId}" : "";
                string itemCond2 = itemId > 0 ? $" AND sd.item_id={itemId}" : "";
                string outletCond = outletId > 0 ? $" AND p.outlet_id = {outletId}" : "";
                string outletCond2 = outletId > 0 ? $" AND s.outlet_id = {outletId}" : "";

                var dt = new DataTable();
                dt.Columns.Add("sn", typeof(int));
                dt.Columns.Add("date", typeof(string));
                dt.Columns.Add("transaction_type", typeof(string));
                dt.Columns.Add("reference_no", typeof(string));
                dt.Columns.Add("details", typeof(string));
                dt.Columns.Add("quantity", typeof(string));
                dt.Columns.Add("unit_price", typeof(string));
                dt.Columns.Add("total_amount", typeof(string));
                dt.Columns.Add("_totalRaw", typeof(double));

                double grandTotal = 0;
                int sn = 0;

                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $@"SELECT 'Purchase' AS type, p.date, p.reference_no, i.name AS item,
                        IFNULL(pd.quantity_amount,0), IFNULL(pd.unit_price,0),
                        ROUND(IFNULL(pd.quantity_amount,0)*IFNULL(pd.unit_price,0),2) AS total_amount
                        FROM purchase_details pd
                        INNER JOIN purchases p ON p.Id=pd.purchase_id
                        LEFT JOIN items i ON i.Id=pd.item_id
                        WHERE (pd.del_status IS NULL OR pd.del_status='Live') AND (p.del_status IS NULL OR p.del_status='Live')
                        {dateCond("p.date")} {itemCond} {outletCond}

                        UNION ALL

                        SELECT 'Sale', s.sale_date, s.sale_no, i2.name AS item,
                        IFNULL(sd.qty,0), IFNULL(sd.menu_price_with_discount, IFNULL(sd.menu_unit_price,0)),
                        ROUND(IFNULL(sd.qty,0)*IFNULL(sd.menu_price_with_discount, IFNULL(sd.menu_unit_price,0)),2)
                        FROM sale_details sd
                        INNER JOIN sales s ON s.Id=sd.sales_id
                        LEFT JOIN items i2 ON i2.Id=sd.item_id
                        WHERE (sd.del_status IS NULL OR sd.del_status='Live') AND (s.del_status IS NULL OR s.del_status='Live')
                        {dateCond("s.sale_date")} {itemCond2} {outletCond2}

                        ORDER BY date ASC";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        sn++;
                        string transType = r.IsDBNull(0) ? "Purchase" : r.GetString(0);
                        var date = r.IsDBNull(1) ? DateTime.MinValue : DateTime.Parse(r.GetString(1));
                        string refNo = r.IsDBNull(2) ? "-" : r.GetString(2);
                        string details = r.IsDBNull(3) ? "-" : r.GetString(3);
                        double qty = r.IsDBNull(4) ? 0 : Convert.ToDouble(r.GetValue(4));
                        double unitPrice = r.IsDBNull(5) ? 0 : Convert.ToDouble(r.GetValue(5));
                        double totalAmount = r.IsDBNull(6) ? 0 : Convert.ToDouble(r.GetValue(6));

                        string dateStr = date.ToString("dd/MM/yyyy");
                        grandTotal += totalAmount;

                        dt.Rows.Add(sn, dateStr, transType, refNo, details,
                            qty.ToString("N2"),
                            "₹ " + unitPrice.ToString("N2"),
                            "₹ " + totalAmount.ToString("N2"),
                            totalAmount);
                    }
                }

                _allData = dt;
                dataGrid.ItemsSource = null;
                dataGrid.ItemsSource = dt.DefaultView;

                bool hasData = dt.Rows.Count > 0;
                emptyState.Visibility = hasData ? Visibility.Collapsed : Visibility.Visible;
                dataGrid.Visibility = hasData ? Visibility.Visible : Visibility.Collapsed;
                rowCountBar.Visibility = hasData ? Visibility.Visible : Visibility.Collapsed;

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
            long itemId = (cmbItem.SelectedItem as ReportComboItem)?.Id ?? 0;
            var outletItem = cmbOutlet.SelectedItem as ReportComboItem;
            string outlet = outletItem?.Name ?? "";
            long outId = outletItem?.Id ?? 0;

            LoadReport(from, to, itemId, outlet, outId);

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

            if (itemId > 0)
                filterInfoPanel.Children.Add(new TextBlock
                {
                    Text = "Item: " + (cmbItem.SelectedItem as ReportComboItem)?.Name,
                    FontSize = 11.5, FontFamily = new FontFamily("Inter, Segoe UI"),
                    Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0x65, 0x34)),
                    Margin = new Thickness(0, 0, 16, 0)
                });

            if (!string.IsNullOrEmpty(outlet) && outlet != "All Outlets")
                filterInfoPanel.Children.Add(new TextBlock
                {
                    Text = "Outlet: " + outlet,
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
            doc.Blocks.Add(new Paragraph(new Run("Detailed Price History Report")) { FontSize = 16, FontWeight = FontWeights.Bold, Margin = new Thickness(0, 0, 0, 6) });
            if (dpDateFrom.SelectedDate.HasValue)
                doc.Blocks.Add(new Paragraph(new Run($"Period: {dpDateFrom.SelectedDate:dd MMM yyyy} to {dpDateTo.SelectedDate:dd MMM yyyy}")) { Foreground = Brushes.Gray, Margin = new Thickness(0, 0, 0, 12) });

            var tbl = new System.Windows.Documents.Table { CellSpacing = 2 };
            string[] headers = { "SN", "DATE", "TRANSACTION", "REF NO", "DETAILS", "QTY", "UNIT PRICE", "TOTAL" };
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
                    rv["sn"].ToString(), rv["date"].ToString(), rv["transaction_type"].ToString(),
                    rv["reference_no"].ToString(), rv["details"].ToString(),
                    rv["quantity"].ToString(), rv["unit_price"].ToString(), rv["total_amount"].ToString()
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
            dlg.PrintDocument(pag, "Detailed Price History Report");
        }

        private void BtnExportCsv_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            var dv = dataGrid.ItemsSource as DataView;
            if (dv == null || dv.Count == 0) { MessageBox.Show("No data to export.", "Info"); return; }

            var sfd = new Microsoft.Win32.SaveFileDialog { Filter = "CSV|*.csv", FileName = "DetailedPriceHistoryReport.csv" };
            if (sfd.ShowDialog() != true) return;

            using var sw = new StreamWriter(sfd.FileName, false, System.Text.Encoding.UTF8);
            sw.WriteLine("SN,DATE,TRANSACTION TYPE,REFERENCE NO,DETAILS,QUANTITY,UNIT PRICE,TOTAL AMOUNT");
            foreach (DataRowView rv in dv)
            {
                sw.WriteLine(string.Join(",",
                    rv["sn"], "\"" + rv["date"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["transaction_type"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["reference_no"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["details"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["quantity"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["unit_price"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["total_amount"].ToString().Replace("\"", "\"\"") + "\""));
            }
            MessageBox.Show("Exported successfully.", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
        }
    }
}
