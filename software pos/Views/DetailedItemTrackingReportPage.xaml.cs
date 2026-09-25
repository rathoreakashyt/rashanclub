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
    public partial class DetailedItemTrackingReportPage : UserControl
    {
        private readonly MainDashboard _dashboard;
        private readonly DatabaseService _db = new();
        private DataTable _allData = new DataTable();

        public DetailedItemTrackingReportPage(MainDashboard dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () => dataGrid.ItemsSource as DataView, "Detailed Item Tracking Report");
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
                string itemCond = itemId > 0 ? $" AND t_item_id={itemId}" : "";

                var dt = new DataTable();
                dt.Columns.Add("sn", typeof(int));
                dt.Columns.Add("date", typeof(string));
                dt.Columns.Add("transaction_type", typeof(string));
                dt.Columns.Add("reference_no", typeof(string));
                dt.Columns.Add("details", typeof(string));
                dt.Columns.Add("quantity_in", typeof(string));
                dt.Columns.Add("quantity_out", typeof(string));
                dt.Columns.Add("current_stock", typeof(string));
                dt.Columns.Add("_sortDate", typeof(DateTime));
                dt.Columns.Add("_itemId", typeof(long));
                dt.Columns.Add("_qtyIn", typeof(double));
                dt.Columns.Add("_qtyOut", typeof(double));

                var movements = new System.Collections.Generic.List<(DateTime sortDate, string date, string type, string refNo, string details, double qtyIn, double qtyOut, long itemId, string itemName, long outletId)>();

                // 1. Purchases (Stock In)
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $@"SELECT p.date, p.reference_no, pd.item_id, i.name,
                        IFNULL(pd.quantity_amount,0), IFNULL(pd.unit_price,0), p.outlet_id
                        FROM purchase_details pd
                        INNER JOIN purchases p ON p.Id=pd.purchase_id
                        LEFT JOIN items i ON i.Id=pd.item_id
                        WHERE (pd.del_status IS NULL OR pd.del_status='Live') AND (p.del_status IS NULL OR p.del_status='Live')
                        {dateCond("p.date")}";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        var date = r.IsDBNull(0) ? DateTime.MinValue : DateTime.Parse(r.GetString(0));
                        string refNo = r.IsDBNull(1) ? "-" : r.GetString(1);
                        long iId = r.IsDBNull(2) ? 0 : Convert.ToInt64(r.GetValue(2));
                        string iName = r.IsDBNull(3) ? "-" : r.GetString(3);
                        double qty = r.IsDBNull(4) ? 0 : Convert.ToDouble(r.GetValue(4));
                        long movOutletId = r.IsDBNull(6) ? 0 : Convert.ToInt64(r.GetValue(6));
                        movements.Add((date, date.ToString("dd/MM/yyyy"), "Purchase", refNo, iName, qty, 0, iId, iName, movOutletId));
                    }
                }

                // 2. Sales (Stock Out)
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $@"SELECT s.sale_date, s.sale_no, sd.item_id, i.name,
                        IFNULL(sd.qty,0), s.outlet_id
                        FROM sale_details sd
                        INNER JOIN sales s ON s.Id=sd.sales_id
                        LEFT JOIN items i ON i.Id=sd.item_id
                        WHERE (sd.del_status IS NULL OR sd.del_status='Live') AND (s.del_status IS NULL OR s.del_status='Live')
                        {dateCond("s.sale_date")}";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        var date = r.IsDBNull(0) ? DateTime.MinValue : DateTime.Parse(r.GetString(0));
                        string refNo = r.IsDBNull(1) ? "-" : r.GetString(1);
                        long iId = r.IsDBNull(2) ? 0 : Convert.ToInt64(r.GetValue(2));
                        string iName = r.IsDBNull(3) ? "-" : r.GetString(3);
                        double qty = r.IsDBNull(4) ? 0 : Convert.ToDouble(r.GetValue(4));
                        long movOutletId = r.IsDBNull(5) ? 0 : Convert.ToInt64(r.GetValue(5));
                        movements.Add((date, date.ToString("dd/MM/yyyy"), "Sale", refNo, iName, 0, qty, iId, iName, movOutletId));
                    }
                }

                // 3. Purchase Returns (Stock Out)
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $@"SELECT pr.date, pr.reference_no, prd.item_id, i.name,
                        IFNULL(prd.return_quantity_amount,0), pr.outlet_id
                        FROM purchase_return_details prd
                        INNER JOIN purchase_returns pr ON pr.Id=prd.pur_return_id
                        LEFT JOIN items i ON i.Id=prd.item_id
                        WHERE (prd.del_status IS NULL OR prd.del_status='Live') AND (pr.del_status IS NULL OR pr.del_status='Live')
                        {dateCond("pr.date")}";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        var date = r.IsDBNull(0) ? DateTime.MinValue : DateTime.Parse(r.GetString(0));
                        string refNo = r.IsDBNull(1) ? "-" : r.GetString(1);
                        long iId = r.IsDBNull(2) ? 0 : Convert.ToInt64(r.GetValue(2));
                        string iName = r.IsDBNull(3) ? "-" : r.GetString(3);
                        double qty = r.IsDBNull(4) ? 0 : Convert.ToDouble(r.GetValue(4));
                        long movOutletId = r.IsDBNull(5) ? 0 : Convert.ToInt64(r.GetValue(5));
                        movements.Add((date, date.ToString("dd/MM/yyyy"), "Purchase Return", refNo, iName, 0, qty, iId, iName, movOutletId));
                    }
                }

                // 4. Sale Returns (Stock In)
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $@"SELECT sr.date, sr.reference_no, srd.item_id, i.name,
                        IFNULL(srd.return_quantity_amount,0), sr.outlet_id
                        FROM sale_return_details srd
                        INNER JOIN sale_returns sr ON sr.Id=srd.sale_return_id
                        LEFT JOIN items i ON i.Id=srd.item_id
                        WHERE (srd.del_status IS NULL OR srd.del_status='Live') AND (sr.del_status IS NULL OR sr.del_status='Live')
                        {dateCond("sr.date")}";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        var date = r.IsDBNull(0) ? DateTime.MinValue : DateTime.Parse(r.GetString(0));
                        string refNo = r.IsDBNull(1) ? "-" : r.GetString(1);
                        long iId = r.IsDBNull(2) ? 0 : Convert.ToInt64(r.GetValue(2));
                        string iName = r.IsDBNull(3) ? "-" : r.GetString(3);
                        double qty = r.IsDBNull(4) ? 0 : Convert.ToDouble(r.GetValue(4));
                        long movOutletId = r.IsDBNull(5) ? 0 : Convert.ToInt64(r.GetValue(5));
                        movements.Add((date, date.ToString("dd/MM/yyyy"), "Sale Return", refNo, iName, qty, 0, iId, iName, movOutletId));
                    }
                }

                // 5. Damages (Stock Out)
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $@"SELECT d.date, d.reference_no, dd.item_id, i.name,
                        IFNULL(dd.damage_quantity,0), d.outlet_id
                        FROM damage_details dd
                        INNER JOIN damages d ON d.Id=dd.damage_id
                        LEFT JOIN items i ON i.Id=dd.item_id
                        WHERE (dd.del_status IS NULL OR dd.del_status='Live') AND (d.del_status IS NULL OR d.del_status='Live')
                        {dateCond("d.date")}";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        var date = r.IsDBNull(0) ? DateTime.MinValue : DateTime.Parse(r.GetString(0));
                        string refNo = r.IsDBNull(1) ? "-" : r.GetString(1);
                        long iId = r.IsDBNull(2) ? 0 : Convert.ToInt64(r.GetValue(2));
                        string iName = r.IsDBNull(3) ? "-" : r.GetString(3);
                        double qty = r.IsDBNull(4) ? 0 : Convert.ToDouble(r.GetValue(4));
                        long movOutletId = r.IsDBNull(5) ? 0 : Convert.ToInt64(r.GetValue(5));
                        movements.Add((date, date.ToString("dd/MM/yyyy"), "Damage", refNo, iName, 0, qty, iId, iName, movOutletId));
                    }
                }

                // 6. Transfers (Stock In on destination, Stock Out on source)
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $@"SELECT t.date, t.reference_no, td.item_id, i.name,
                        IFNULL(td.quantity,0), t.from_outlet_id
                        FROM transfer_details td
                        INNER JOIN transfers t ON t.Id=td.transfer_id
                        LEFT JOIN items i ON i.Id=td.item_id
                        WHERE (td.del_status IS NULL OR td.del_status='Live') AND (t.del_status IS NULL OR t.del_status='Live')
                        {dateCond("t.date")}";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        var date = r.IsDBNull(0) ? DateTime.MinValue : DateTime.Parse(r.GetString(0));
                        string refNo = r.IsDBNull(1) ? "-" : r.GetString(1);
                        long iId = r.IsDBNull(2) ? 0 : Convert.ToInt64(r.GetValue(2));
                        string iName = r.IsDBNull(3) ? "-" : r.GetString(3);
                        double qty = r.IsDBNull(4) ? 0 : Convert.ToDouble(r.GetValue(4));
                        long movOutletId = r.IsDBNull(5) ? 0 : Convert.ToInt64(r.GetValue(5));
                        movements.Add((date, date.ToString("dd/MM/yyyy"), "Transfer Out", refNo, iName, 0, qty, iId, iName, movOutletId));
                    }
                }

                // Apply item and outlet filters
                if (itemId > 0 || outletId > 0)
                {
                    var filtered = new System.Collections.Generic.List<(DateTime, string, string, string, string, double, double, long, string, long)>();
                    foreach (var m in movements)
                    {
                        bool itemMatch = itemId == 0 || m.itemId == itemId;
                        bool outletMatch = outletId == 0 || m.outletId == outletId;
                        if (itemMatch && outletMatch)
                            filtered.Add(m);
                    }
                    movements = filtered;
                }

                // Sort by date
                movements.Sort((a, b) => a.Item1.CompareTo(b.Item1));

                // Build running stock per item
                var stockByItem = new System.Collections.Generic.Dictionary<long, double>();
                int sn = 0;
                foreach (var m in movements)
                {
                    sn++;
                    if (!stockByItem.ContainsKey(m.itemId))
                        stockByItem[m.itemId] = 0;
                    stockByItem[m.itemId] += m.qtyIn - m.qtyOut;
                    double currentStock = stockByItem[m.itemId];

                    dt.Rows.Add(sn, m.date, m.type, m.refNo, m.details,
                        m.qtyIn > 0 ? m.qtyIn.ToString("N2") : "-",
                        m.qtyOut > 0 ? m.qtyOut.ToString("N2") : "-",
                        currentStock.ToString("N2"),
                        m.sortDate, m.itemId, m.qtyIn, m.qtyOut);
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
            doc.Blocks.Add(new Paragraph(new Run("Detailed Item Tracking Report")) { FontSize = 16, FontWeight = FontWeights.Bold, Margin = new Thickness(0, 0, 0, 6) });
            if (dpDateFrom.SelectedDate.HasValue)
                doc.Blocks.Add(new Paragraph(new Run($"Period: {dpDateFrom.SelectedDate:dd MMM yyyy} to {dpDateTo.SelectedDate:dd MMM yyyy}")) { Foreground = Brushes.Gray, Margin = new Thickness(0, 0, 0, 12) });

            var tbl = new System.Windows.Documents.Table { CellSpacing = 2 };
            string[] headers = { "SN", "DATE", "TRANSACTION", "REF NO", "DETAILS", "QTY IN", "QTY OUT", "STOCK" };
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
                    rv["quantity_in"].ToString(), rv["quantity_out"].ToString(), rv["current_stock"].ToString()
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
            dlg.PrintDocument(pag, "Detailed Item Tracking Report");
        }

        private void BtnExportCsv_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            var dv = dataGrid.ItemsSource as DataView;
            if (dv == null || dv.Count == 0) { MessageBox.Show("No data to export.", "Info"); return; }

            var sfd = new Microsoft.Win32.SaveFileDialog { Filter = "CSV|*.csv", FileName = "DetailedItemTrackingReport.csv" };
            if (sfd.ShowDialog() != true) return;

            using var sw = new StreamWriter(sfd.FileName, false, System.Text.Encoding.UTF8);
            sw.WriteLine("SN,DATE,TRANSACTION TYPE,REFERENCE NO,DETAILS,QUANTITY IN,QUANTITY OUT,CURRENT STOCK");
            foreach (DataRowView rv in dv)
            {
                sw.WriteLine(string.Join(",",
                    rv["sn"], "\"" + rv["date"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["transaction_type"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["reference_no"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["details"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["quantity_in"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["quantity_out"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["current_stock"].ToString().Replace("\"", "\"\"") + "\""));
            }
            MessageBox.Show("Exported successfully.", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
        }
    }
}
