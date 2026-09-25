using System;
using System.Collections.Generic;
using System.Data;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Documents;
using System.Windows.Media;
using Microsoft.Win32;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class ProductProfitReportPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();
        private DataTable? _currentTable;

        public ProductProfitReportPage(MainDashboard? dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () => dataGrid.ItemsSource as DataView, "Product Profit Report");
            _dashboard = dashboard;
            dpDateFrom.SelectedDate = new DateTime(DateTime.Today.Year, DateTime.Today.Month, 1);
            dpDateTo.SelectedDate   = DateTime.Today;
            LoadOutlets();
            LoadItems();
            LoadFormulas();
            LoadData();
        }

        private void LoadOutlets()
        {
            var list = new List<ReportComboItem> { new(0, "All Outlets") };
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, outlet_name FROM outlets WHERE (del_status IS NULL OR del_status='Live') ORDER BY id";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add(new ReportComboItem(r.IsDBNull(0) ? 0 : r.GetInt64(0), r.IsDBNull(1) ? "" : r.GetString(1)));
            }
            catch { }
            cmbOutlet.ItemsSource = list;
            cmbOutlet.SelectedIndex = 0;
        }

        private void LoadItems()
        {
            var list = new List<ReportComboItem> { new(0, "All Items") };
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, name, code FROM items WHERE (del_status IS NULL OR del_status='Live') ORDER BY name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    long id = r.IsDBNull(0) ? 0 : r.GetInt64(0);
                    string name = r.IsDBNull(1) ? "" : r.GetString(1);
                    string code = r.IsDBNull(2) ? "" : r.GetString(2);
                    list.Add(new ReportComboItem(id, string.IsNullOrEmpty(code) ? name : $"{name} ({code})"));
                }
            }
            catch { }
            cmbItem.ItemsSource = list;
            cmbItem.SelectedIndex = 0;
        }

        private void LoadFormulas()
        {
            cmbFormula.Items.Clear();
            cmbFormula.Items.Add("PP_Price (Last Purchase Price)");
            cmbFormula.Items.Add("AVG (Last 3 Purchase Average)");
            cmbFormula.SelectedIndex = 0;
        }

        private void LoadData()
        {
            emptyState.Visibility  = Visibility.Collapsed;
            dataGrid.Visibility    = Visibility.Collapsed;
            totalsBar.Visibility   = Visibility.Collapsed;
            rowCountBar.Visibility = Visibility.Collapsed;

            string from = dpDateFrom.SelectedDate?.ToString("yyyy-MM-dd") ?? "1900-01-01";
            string to   = dpDateTo.SelectedDate?.ToString("yyyy-MM-dd") ?? "2099-12-31";

            long outletId = (cmbOutlet.SelectedItem as ReportComboItem)?.Id ?? 0;
            long itemId   = (cmbItem.SelectedItem as ReportComboItem)?.Id ?? 0;
            bool useAvg   = cmbFormula.SelectedIndex == 1;

            var sql = "SELECT ROW_NUMBER() OVER (ORDER BY s.sale_date DESC, sd.Id DESC) AS sn,"
                + " s.sale_no AS invoice_no,"
                + " COALESCE(s.date_time, s.sale_date, s.created_at) AS date_time,"
                + " ROUND(IFNULL(sd.menu_price_with_discount, IFNULL(sd.menu_unit_price, 0)), 2) AS sale_unit_price,"
                + " ROUND(IFNULL(sd.qty, 0), 2) AS quantity,"
                + " ROUND(IFNULL(sd.discount_amount, 0), 2) AS discount,"
                + " ROUND(IFNULL(sd.qty, 0) * IFNULL(sd.menu_price_with_discount, IFNULL(sd.menu_unit_price, 0)), 2) AS total_sale,"
                + " ROUND(IFNULL(i.last_purchase_price, 0), 2) AS costing_price,"
                + " ROUND(IFNULL(sd.qty, 0) * IFNULL(i.last_purchase_price, 0), 2) AS total_cost,"
                + " ROUND(IFNULL(sd.qty, 0) * IFNULL(sd.menu_price_with_discount, IFNULL(sd.menu_unit_price, 0))"
                + "  - IFNULL(sd.qty, 0) * IFNULL(i.last_purchase_price, 0), 2) AS profit"
                + " FROM sale_details sd"
                + " JOIN sales s ON s.Id = sd.sales_id"
                + " LEFT JOIN items i ON i.Id = sd.item_id"
                + " WHERE (sd.del_status IS NULL OR sd.del_status='Live')"
                + " AND (s.del_status IS NULL OR s.del_status='Live')";

            if (outletId > 0) sql += $" AND s.outlet_id = {outletId}";
            if (itemId > 0)   sql += $" AND sd.item_id = {itemId}";

            sql += " AND s.sale_date BETWEEN @from AND @to";
            sql += " ORDER BY s.sale_date DESC, sd.Id DESC";

            try
            {
                using var conn = _db.GetConnection();
                using var cmd  = conn.CreateCommand();
                cmd.CommandText = sql;
                cmd.Parameters.AddWithValue("@from", from);
                cmd.Parameters.AddWithValue("@to",   to);

                var table = new DataTable();
                using (var r = cmd.ExecuteReader()) table.Load(r);

                if (useAvg)
                {
                    // Recalculate costing with last_three_purchase_avg
                    foreach (DataRow row in table.Rows)
                    {
                        long itemDbId = 0;
                        if (table.Columns.Contains("invoice_no"))
                        {
                            // We need item_id from sale_details - re-query with item_id
                        }
                    }
                    // Simpler: re-query with AVG formula
                    table = LoadDataWithFormula(from, to, outletId, itemId, useAvg);
                }

                _currentTable = table;

                if (table.Rows.Count == 0)
                { emptyState.Visibility = Visibility.Visible; return; }

                dataGrid.ItemsSource    = table.DefaultView;
                dataGrid.Visibility     = Visibility.Visible;
                rowCountBar.Visibility  = Visibility.Visible;
                lblRowCount.Text        = $"Showing {table.Rows.Count} entries";
                BuildTotals(table);
            }
            catch (Exception ex)
            {
                emptyState.Visibility = Visibility.Visible;
                MessageBox.Show("Error: " + ex.Message, "Product Profit Report", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private DataTable LoadDataWithFormula(string from, string to, long outletId, long itemId, bool useAvg)
        {
            var costingExpr = useAvg
                ? "ROUND(IFNULL(i.last_three_purchase_avg, IFNULL(i.last_purchase_price, 0)), 2)"
                : "ROUND(IFNULL(i.last_purchase_price, 0), 2)";

            var sql = "SELECT ROW_NUMBER() OVER (ORDER BY s.sale_date DESC, sd.Id DESC) AS sn,"
                + " s.sale_no AS invoice_no,"
                + " COALESCE(s.date_time, s.sale_date, s.created_at) AS date_time,"
                + " ROUND(IFNULL(sd.menu_price_with_discount, IFNULL(sd.menu_unit_price, 0)), 2) AS sale_unit_price,"
                + " ROUND(IFNULL(sd.qty, 0), 2) AS quantity,"
                + " ROUND(IFNULL(sd.discount_amount, 0), 2) AS discount,"
                + " ROUND(IFNULL(sd.qty, 0) * IFNULL(sd.menu_price_with_discount, IFNULL(sd.menu_unit_price, 0)), 2) AS total_sale,"
                + costingExpr + " AS costing_price,"
                + " ROUND(IFNULL(sd.qty, 0) * " + costingExpr + ", 2) AS total_cost,"
                + " ROUND(IFNULL(sd.qty, 0) * IFNULL(sd.menu_price_with_discount, IFNULL(sd.menu_unit_price, 0))"
                + "  - IFNULL(sd.qty, 0) * " + costingExpr + ", 2) AS profit"
                + " FROM sale_details sd"
                + " JOIN sales s ON s.Id = sd.sales_id"
                + " LEFT JOIN items i ON i.Id = sd.item_id"
                + " WHERE (sd.del_status IS NULL OR sd.del_status='Live')"
                + " AND (s.del_status IS NULL OR s.del_status='Live')";

            if (outletId > 0) sql += $" AND s.outlet_id = {outletId}";
            if (itemId > 0)   sql += $" AND sd.item_id = {itemId}";

            sql += " AND s.sale_date BETWEEN @from AND @to";
            sql += " ORDER BY s.sale_date DESC, sd.Id DESC";

            using var conn = _db.GetConnection();
            using var cmd  = conn.CreateCommand();
            cmd.CommandText = sql;
            cmd.Parameters.AddWithValue("@from", from);
            cmd.Parameters.AddWithValue("@to",   to);

            var table = new DataTable();
            using (var r = cmd.ExecuteReader()) table.Load(r);
            return table;
        }

        private double ColSum(DataTable t, string col)
        {
            if (!t.Columns.Contains(col)) return 0;
            double sum = 0;
            foreach (DataRow r in t.Rows)
                if (double.TryParse(r[col]?.ToString(), NumberStyles.Any, CultureInfo.InvariantCulture, out double d)) sum += d;
            return Math.Round(sum, 2);
        }

        private void BuildTotals(DataTable t)
        {
            lblTotalSale.Text = $"₹ {ColSum(t, "total_sale"):N2}";
            lblTotalCost.Text = $"₹ {ColSum(t, "total_cost"):N2}";
            lblProfit.Text    = $"₹ {ColSum(t, "profit"):N2}";
            totalsBar.Visibility = Visibility.Visible;
        }

        private void ShowFilterInfo()
        {
            filterInfoPanel.Children.Clear();
            AddInfo("Report", "Product Profit Report");
            AddInfo("Date Range", $"{dpDateFrom.SelectedDate:dd MMM yyyy}  →  {dpDateTo.SelectedDate:dd MMM yyyy}");
            if ((cmbOutlet.SelectedItem as ReportComboItem)?.Id > 0)
                AddInfo("Outlet", ((ReportComboItem)cmbOutlet.SelectedItem).Name);
            if ((cmbItem.SelectedItem as ReportComboItem)?.Id > 0)
                AddInfo("Item", ((ReportComboItem)cmbItem.SelectedItem).Name);
            AddInfo("Formula", cmbFormula.SelectedItem?.ToString() ?? "PP_Price");
            AddInfo("Generated", DateTime.Now.ToString("dd MMM yyyy, hh:mm tt"));
            filterInfoBar.Visibility = Visibility.Visible;
        }

        private void AddInfo(string label, string value)
        {
            var sp = new StackPanel { Orientation = Orientation.Horizontal, Margin = new Thickness(0, 0, 0, 2) };
            sp.Children.Add(new TextBlock { Text = label + ": ", FontSize = 12, FontWeight = FontWeights.SemiBold, Foreground = Brushes.DarkSlateGray, FontFamily = new FontFamily("Inter, Segoe UI") });
            sp.Children.Add(new TextBlock { Text = value,        FontSize = 12, Foreground = Brushes.DarkSlateGray, FontFamily = new FontFamily("Inter, Segoe UI") });
            filterInfoPanel.Children.Add(sp);
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
            => _dashboard?.ShowPage(new ReportsIndexPage(_dashboard));

        private void BtnToggleFilter_Click(object sender, RoutedEventArgs e)
            => filterSection.Visibility = filterSection.Visibility == Visibility.Collapsed
               ? Visibility.Visible : Visibility.Collapsed;

        private void BtnApplyFilter_Click(object sender, RoutedEventArgs e)
        {
            filterSection.Visibility = Visibility.Collapsed;
            LoadData();
            ShowFilterInfo();
        }

        private void BtnCancelFilter_Click(object sender, RoutedEventArgs e)
            => filterSection.Visibility = Visibility.Collapsed;

        private void BtnExport_Click(object sender, RoutedEventArgs e)
            => exportPopup.IsOpen = !exportPopup.IsOpen;

        private void BtnExportCsv_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            if (_currentTable == null || _currentTable.Rows.Count == 0) { MessageBox.Show("No data."); return; }
            var dlg = new SaveFileDialog { Filter = "CSV (*.csv)|*.csv", FileName = "ProductProfitReport_" + DateTime.Today.ToString("yyyyMMdd") + ".csv" };
            if (dlg.ShowDialog() != true) return;
            try
            {
                using var sw = new StreamWriter(dlg.FileName, false, System.Text.Encoding.UTF8);
                sw.WriteLine(string.Join(",", _currentTable.Columns.Cast<DataColumn>().Select(c => c.ColumnName)));
                foreach (DataRow row in _currentTable.Rows)
                    sw.WriteLine(string.Join(",", _currentTable.Columns.Cast<DataColumn>()
                        .Select(c => "\"" + (row[c]?.ToString() ?? "").Replace("\"", "\"\"") + "\"")));
                MessageBox.Show("Exported: " + dlg.FileName, "Success");
            }
            catch (Exception ex) { MessageBox.Show("Error: " + ex.Message); }
        }

        private void BtnPrint_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            if (_currentTable == null || _currentTable.Rows.Count == 0) { MessageBox.Show("No data."); return; }
            var dlg = new PrintDialog();
            if (dlg.ShowDialog() != true) return;
            var doc = new FlowDocument { FontFamily = new FontFamily("Segoe UI"), FontSize = 11, PagePadding = new Thickness(40) };
            doc.Blocks.Add(new Paragraph(new Run("Product Profit Report")) { FontSize = 16, FontWeight = FontWeights.Bold, Margin = new Thickness(0, 0, 0, 6) });
            doc.Blocks.Add(new Paragraph(new Run($"Period: {dpDateFrom.SelectedDate:dd MMM yyyy} to {dpDateTo.SelectedDate:dd MMM yyyy}")) { Foreground = Brushes.Gray, Margin = new Thickness(0, 0, 0, 12) });
            var tbl = new System.Windows.Documents.Table { CellSpacing = 2 };
            int cols = _currentTable.Columns.Count;
            for (int i = 0; i < cols; i++) tbl.Columns.Add(new TableColumn { Width = new GridLength(1, GridUnitType.Star) });
            var rg = new TableRowGroup();
            var hdr = new TableRow { Background = new SolidColorBrush(Color.FromRgb(0x69, 0x6c, 0xff)) };
            foreach (DataColumn col in _currentTable.Columns)
                hdr.Cells.Add(new TableCell(new Paragraph(new Run(col.ColumnName.Replace("_", " ").ToUpper()))) { Padding = new Thickness(4, 3, 4, 3), Foreground = Brushes.White, FontWeight = FontWeights.SemiBold });
            rg.Rows.Add(hdr);
            bool alt = false;
            foreach (DataRow row in _currentTable.Rows)
            {
                var tr = new TableRow { Background = alt ? new SolidColorBrush(Color.FromRgb(0xFA, 0xFA, 0xFF)) : Brushes.White };
                foreach (DataColumn col in _currentTable.Columns)
                    tr.Cells.Add(new TableCell(new Paragraph(new Run(row[col]?.ToString() ?? ""))) { Padding = new Thickness(4, 2, 4, 2) });
                rg.Rows.Add(tr);
                alt = !alt;
            }
            tbl.RowGroups.Add(rg);
            doc.Blocks.Add(tbl);
            var pag = ((IDocumentPaginatorSource)doc).DocumentPaginator;
            pag.PageSize = new Size(dlg.PrintableAreaWidth, dlg.PrintableAreaHeight);
            dlg.PrintDocument(pag, "Product Profit Report");
        }
    }
}
