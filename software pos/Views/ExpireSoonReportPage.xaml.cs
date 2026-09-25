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
    public partial class ExpireSoonReportPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();
        private DataTable? _currentTable;

        public ExpireSoonReportPage(MainDashboard? dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () => dataGrid.ItemsSource as DataView, "Expire Soon Report");
            _dashboard = dashboard;
            dpDateFrom.SelectedDate = DateTime.Today;
            dpDateTo.SelectedDate   = DateTime.Today.AddMonths(3);
            LoadOutlets();
            LoadData();
        }

        private void LoadOutlets()
        {
            var list = new List<ReportComboItem> { new(0, "All Outlets") };
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT Id, outlet_name FROM outlets WHERE (del_status IS NULL OR del_status='Live') ORDER BY Id";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add(new ReportComboItem(r.IsDBNull(0) ? 0 : r.GetInt64(0), r.IsDBNull(1) ? "" : r.GetString(1)));
            }
            catch { }
            cmbOutlet.ItemsSource = list;
            cmbOutlet.SelectedIndex = 0;
        }

        private void LoadData()
        {
            emptyState.Visibility  = Visibility.Collapsed;
            dataGrid.Visibility    = Visibility.Collapsed;
            rowCountBar.Visibility = Visibility.Collapsed;

            string from = dpDateFrom.SelectedDate?.ToString("yyyy-MM-dd") ?? DateTime.Today.ToString("yyyy-MM-dd");
            string to   = dpDateTo.SelectedDate?.ToString("yyyy-MM-dd") ?? DateTime.Today.AddMonths(3).ToString("yyyy-MM-dd");

            long outletId = (cmbOutlet.SelectedItem as ReportComboItem)?.Id ?? 0;
            string genericName = txtGenericName.Text.Trim();

            try
            {
                using var conn = _db.GetConnection();

                // Get all Medicine_Product items
                var itemSql = "SELECT i.id, i.name, i.code, IFNULL(cat.name, '') AS category,"
                    + " IFNULL(i.last_purchase_price, 0) AS last_purchase_price"
                    + " FROM items i"
                    + " LEFT JOIN item_categories cat ON cat.id = i.category_id"
                    + " WHERE i.type = 'Medicine_Product'"
                    + " AND (i.del_status IS NULL OR i.del_status='Live')"
                    + " AND i.parent_id IS NULL";

                if (!string.IsNullOrEmpty(genericName))
                    itemSql += $" AND i.generic_name LIKE '%{genericName.Replace("'", "''")}%'";

                itemSql += " ORDER BY i.name ASC";

                var resultTable = new DataTable();
                resultTable.Columns.Add("sn", typeof(int));
                resultTable.Columns.Add("item_code", typeof(string));
                resultTable.Columns.Add("category", typeof(string));
                resultTable.Columns.Add("stock_segmentation", typeof(string));
                resultTable.Columns.Add("total_stock_qty", typeof(string));
                resultTable.Columns.Add("last_purchase_price", typeof(double));
                resultTable.Columns.Add("total", typeof(double));

                using (var itemCmd = conn.CreateCommand())
                {
                    itemCmd.CommandText = itemSql;
                    using var itemReader = itemCmd.ExecuteReader();
                    var items = new List<(long id, string name, string code, string category, double lpp)>();
                    while (itemReader.Read())
                    {
                        long id = itemReader.IsDBNull(0) ? 0 : itemReader.GetInt64(0);
                        string name = itemReader.IsDBNull(1) ? "" : itemReader.GetString(1);
                        string code = itemReader.IsDBNull(2) ? "" : itemReader.GetString(2);
                        string cat  = itemReader.IsDBNull(3) ? "" : itemReader.GetString(3);
                        double lpp  = itemReader.IsDBNull(4) ? 0 : itemReader.GetDouble(4);
                        items.Add((id, name, code, cat, lpp));
                    }

                    int sn = 1;
                    foreach (var item in items)
                    {
                        // Get stock grouped by expiry date from purchase_details
                        var stockSql = "SELECT pd.expiry_imei_serial AS expiry_date, SUM(pd.quantity_amount) AS qty"
                            + " FROM purchase_details pd"
                            + " JOIN purchases p ON p.id = pd.purchase_id"
                            + " WHERE pd.item_id = @itemId"
                            + " AND pd.expiry_imei_serial IS NOT NULL AND pd.expiry_imei_serial != ''"
                            + " AND pd.expiry_imei_serial >= @from AND pd.expiry_imei_serial <= @to"
                            + " AND (p.del_status IS NULL OR p.del_status='Live')";

                        if (outletId > 0)
                            stockSql += $" AND p.outlet_id = {outletId}";

                        stockSql += " GROUP BY pd.expiry_imei_serial ORDER BY pd.expiry_imei_serial ASC";

                        using var stockCmd = conn.CreateCommand();
                        stockCmd.CommandText = stockSql;
                        stockCmd.Parameters.AddWithValue("@itemId", item.id);
                        stockCmd.Parameters.AddWithValue("@from", from);
                        stockCmd.Parameters.AddWithValue("@to", to);

                        var segments = new List<string>();
                        double totalQty = 0;

                        using (var stockReader = stockCmd.ExecuteReader())
                        {
                            while (stockReader.Read())
                            {
                                string expiryDate = stockReader.IsDBNull(0) ? "" : stockReader.GetString(0);
                                double qty = stockReader.IsDBNull(1) ? 0 : stockReader.GetDouble(1);
                                if (qty > 0 && !string.IsNullOrEmpty(expiryDate))
                                {
                                    // Try to format date
                                    string displayDate = expiryDate;
                                    if (DateTime.TryParse(expiryDate, out DateTime dt))
                                        displayDate = dt.ToString("dd MMM yyyy");
                                    segments.Add($"{displayDate}: {qty:N2}");
                                    totalQty += qty;
                                }
                            }
                        }

                        if (totalQty <= 0) continue;

                        double totalValue = totalQty * item.lpp;
                        resultTable.Rows.Add(
                            sn++,
                            $"{item.name} ({item.code})",
                            item.category,
                            string.Join(", ", segments),
                            totalQty.ToString("N2"),
                            item.lpp,
                            Math.Round(totalValue, 2)
                        );
                    }
                }

                _currentTable = resultTable;

                if (resultTable.Rows.Count == 0)
                { emptyState.Visibility = Visibility.Visible; return; }

                dataGrid.ItemsSource    = resultTable.DefaultView;
                dataGrid.Visibility     = Visibility.Visible;
                rowCountBar.Visibility  = Visibility.Visible;
                lblRowCount.Text        = $"Showing {resultTable.Rows.Count} entries";
            }
            catch (Exception ex)
            {
                emptyState.Visibility = Visibility.Visible;
                MessageBox.Show("Error: " + ex.Message, "Expire Soon Report", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void ShowFilterInfo()
        {
            filterInfoPanel.Children.Clear();
            AddInfo("Report", "Expire Soon Report");
            if (dpDateFrom.SelectedDate != null && dpDateTo.SelectedDate != null)
                AddInfo("Date Range", $"{dpDateFrom.SelectedDate:dd MMM yyyy}  →  {dpDateTo.SelectedDate:dd MMM yyyy}");
            if ((cmbOutlet.SelectedItem as ReportComboItem)?.Id > 0)
                AddInfo("Outlet", ((ReportComboItem)cmbOutlet.SelectedItem).Name);
            if (!string.IsNullOrWhiteSpace(txtGenericName.Text))
                AddInfo("Generic Name", txtGenericName.Text.Trim());
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
            var dlg = new SaveFileDialog { Filter = "CSV (*.csv)|*.csv", FileName = "ExpireSoonReport_" + DateTime.Today.ToString("yyyyMMdd") + ".csv" };
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
            doc.Blocks.Add(new Paragraph(new Run("Expire Soon Report")) { FontSize = 16, FontWeight = FontWeights.Bold, Margin = new Thickness(0, 0, 0, 6) });
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
            dlg.PrintDocument(pag, "Expire Soon Report");
        }
    }
}
