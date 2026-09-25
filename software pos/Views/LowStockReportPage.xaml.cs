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
    public partial class LowStockReportPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();
        private DataTable? _currentTable;

        public LowStockReportPage(MainDashboard? dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () => dataGrid.ItemsSource as DataView, "Low Stock Report");
            _dashboard = dashboard;
            LoadCategories();
            LoadBrands();
            LoadSuppliers();
            LoadData();
        }

        private void LoadCategories()
        {
            var list = new List<ReportComboItem> { new(0, "All Categories") };
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, name FROM item_categories WHERE (del_status IS NULL OR del_status='Live') ORDER BY name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add(new ReportComboItem(r.IsDBNull(0) ? 0 : r.GetInt64(0), r.IsDBNull(1) ? "" : r.GetString(1)));
            }
            catch { }
            cmbCategory.ItemsSource = list;
            cmbCategory.SelectedIndex = 0;
        }

        private void LoadBrands()
        {
            var list = new List<ReportComboItem> { new(0, "All Brands") };
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, name FROM brands WHERE (del_status IS NULL OR del_status='Live') ORDER BY name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add(new ReportComboItem(r.IsDBNull(0) ? 0 : r.GetInt64(0), r.IsDBNull(1) ? "" : r.GetString(1)));
            }
            catch { }
            cmbBrand.ItemsSource = list;
            cmbBrand.SelectedIndex = 0;
        }

        private void LoadSuppliers()
        {
            var list = new List<ReportComboItem> { new(0, "All Suppliers") };
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, name FROM suppliers WHERE (del_status IS NULL OR del_status='Live') ORDER BY name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add(new ReportComboItem(r.IsDBNull(0) ? 0 : r.GetInt64(0), r.IsDBNull(1) ? "" : r.GetString(1)));
            }
            catch { }
            cmbSupplier.ItemsSource = list;
            cmbSupplier.SelectedIndex = 0;
        }

        private void LoadData()
        {
            emptyState.Visibility  = Visibility.Collapsed;
            dataGrid.Visibility    = Visibility.Collapsed;
            rowCountBar.Visibility = Visibility.Collapsed;

            long categoryId  = (cmbCategory.SelectedItem  as ReportComboItem)?.Id ?? 0;
            long brandId     = (cmbBrand.SelectedItem     as ReportComboItem)?.Id ?? 0;
            long supplierId  = (cmbSupplier.SelectedItem  as ReportComboItem)?.Id ?? 0;
            string genericName = txtGenericName.Text.Trim();

            var sql = "SELECT ROW_NUMBER() OVER (ORDER BY i.name ASC) AS sn,"
                + " i.name || ' (' || IFNULL(i.code, '') || ')' AS item_code,"
                + " IFNULL(cat.name, '') AS category,"
                + " '' AS stock_details,"
                + " ROUND(IFNULL(i.stock_quantity, 0), 2) || ' ' || CASE WHEN i.unit_type='2' THEN IFNULL(u2.unit_name, IFNULL(u1.unit_name, '')) ELSE IFNULL(u1.unit_name, '') END AS total_stock_qty,"
                + " ROUND(CASE WHEN i.unit_type='2' AND IFNULL(i.conversion_rate,0) > 0 THEN IFNULL(i.purchase_price, 0) / i.conversion_rate ELSE IFNULL(i.purchase_price, 0) END, 2) AS lpp,"
                + " ROUND(IFNULL(i.stock_quantity, 0) * CASE WHEN i.unit_type='2' AND IFNULL(i.conversion_rate,0) > 0 THEN IFNULL(i.purchase_price, 0) / i.conversion_rate ELSE IFNULL(i.purchase_price, 0) END, 2) AS total,"
                + " i.stock_quantity AS raw_stock"
                + " FROM items i"
                + " LEFT JOIN item_categories cat ON cat.id = i.category_id"
                + " LEFT JOIN units u1 ON u1.id = i.purchase_unit_id"
                + " LEFT JOIN units u2 ON u2.id = i.sale_unit_id"
                + " WHERE (i.del_status IS NULL OR i.del_status='Live')"
                + " AND (i.type IS NULL OR i.type NOT IN ('Service_Product','Combo_Product'))"
                + " AND i.parent_id IS NULL"
                + " AND IFNULL(i.stock_quantity, 0) <= IFNULL(i.alert_quantity, 0)";

            if (categoryId > 0)  sql += $" AND i.category_id = {categoryId}";
            if (brandId > 0)     sql += $" AND i.brand_id = {brandId}";
            if (supplierId > 0)  sql += $" AND i.supplier_id = {supplierId}";
            if (!string.IsNullOrEmpty(genericName))
                sql += $" AND i.generic_name LIKE '%{genericName.Replace("'", "''")}%'";

            sql += " ORDER BY i.name ASC";

            try
            {
                using var conn = _db.GetConnection();
                using var cmd  = conn.CreateCommand();
                cmd.CommandText = sql;

                var table = new DataTable();
                using (var r = cmd.ExecuteReader()) table.Load(r);

                // Remove hidden column
                if (table.Columns.Contains("raw_stock")) table.Columns.Remove("raw_stock");

                _currentTable = table;

                if (table.Rows.Count == 0)
                { emptyState.Visibility = Visibility.Visible; return; }

                dataGrid.ItemsSource    = table.DefaultView;
                dataGrid.Visibility     = Visibility.Visible;
                rowCountBar.Visibility  = Visibility.Visible;
                lblRowCount.Text        = $"Showing {table.Rows.Count} entries";
            }
            catch (Exception ex)
            {
                emptyState.Visibility = Visibility.Visible;
                MessageBox.Show("Error: " + ex.Message, "Low Stock Report", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void ShowFilterInfo()
        {
            filterInfoPanel.Children.Clear();
            AddInfo("Report", "Low Stock Report");
            if ((cmbCategory.SelectedItem as ReportComboItem)?.Id > 0)
                AddInfo("Group", ((ReportComboItem)cmbCategory.SelectedItem).Name);
            if ((cmbBrand.SelectedItem as ReportComboItem)?.Id > 0)
                AddInfo("Brand", ((ReportComboItem)cmbBrand.SelectedItem).Name);
            if ((cmbSupplier.SelectedItem as ReportComboItem)?.Id > 0)
                AddInfo("Supplier", ((ReportComboItem)cmbSupplier.SelectedItem).Name);
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
            var dlg = new SaveFileDialog { Filter = "CSV (*.csv)|*.csv", FileName = "LowStockReport_" + DateTime.Today.ToString("yyyyMMdd") + ".csv" };
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
            doc.Blocks.Add(new Paragraph(new Run("Low Stock Report")) { FontSize = 16, FontWeight = FontWeights.Bold, Margin = new Thickness(0, 0, 0, 6) });
            doc.Blocks.Add(new Paragraph(new Run($"Generated: {DateTime.Now:dd MMM yyyy, hh:mm tt}")) { Foreground = Brushes.Gray, Margin = new Thickness(0, 0, 0, 12) });
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
            dlg.PrintDocument(pag, "Low Stock Report");
        }
    }
}
