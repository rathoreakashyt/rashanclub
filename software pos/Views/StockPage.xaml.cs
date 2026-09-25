using System;
using System.Collections.Generic;
using System.IO;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;
using Microsoft.Win32;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class StockPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();
        private List<StockRow> _allRows = new();
        private int _pageSize = 10;
        private bool _lowStockMode = false;
        private bool _loaded = false;

        public StockPage(MainDashboard? dashboard)
        {
            InitializeComponent();
            _dashboard = dashboard;
            Loaded += StockPage_Loaded;
            // Stock update kahin bhi hua to turant refresh
            Services.StockEvents.StockChanged += OnStockChanged;
            Unloaded += (_, _) => Services.StockEvents.StockChanged -= OnStockChanged;
        }

        private void OnStockChanged()
        {
            Dispatcher.BeginInvoke(new Action(() =>
            {
                if (_loaded) { LoadStats(); LoadData(); }
            }), System.Windows.Threading.DispatcherPriority.Background);
        }

        private void StockPage_Loaded(object sender, RoutedEventArgs e)
        {
            _loaded = true;
            LoadStats();
            LoadData();
        }
        private void LoadStats()
        {
            try
            {
                using var conn = _db.GetConnection();

                double totalValue = 0;
                double totalCount = 0;

                using (var c = conn.CreateCommand())
                {
                    c.CommandText = @"SELECT
                        IFNULL(SUM(CASE WHEN i.unit_type = '2'
                            THEN i.stock_quantity * COALESCE(NULLIF(i.last_three_purchase_avg,0), NULLIF(i.last_purchase_price,0), IFNULL(i.purchase_price,0)) / MAX(IFNULL(i.conversion_rate,1),1)
                            ELSE i.stock_quantity * COALESCE(NULLIF(i.last_three_purchase_avg,0), NULLIF(i.last_purchase_price,0), IFNULL(i.purchase_price,0)) END),0) AS total_value,
                        IFNULL(SUM(i.stock_quantity),0) AS total_count
                        FROM items i
                        WHERE (i.del_status IS NULL OR i.del_status = '' OR i.del_status = 'Live')
                          AND IFNULL(i.enable_disable_status,1) = 1
                          AND IFNULL(i.type,'') NOT IN ('Service_Product','Combo_Product','0')
                          AND i.parent_id IS NULL";
                    using var r = c.ExecuteReader();
                    if (r.Read())
                    {
                        totalValue = r.IsDBNull(0) ? 0 : r.GetDouble(0);
                        totalCount = r.IsDBNull(1) ? 0 : r.GetDouble(1);
                    }
                }

                lblStockValue.Text = totalValue.ToString("N2");
                lblStockCount.Text = totalCount.ToString("N2");
            }
            catch { }
        }

        private void LoadData()
        {
            try
            {
                string search = txtSearch?.Text?.Trim() ?? "";
                string searchFilter = string.IsNullOrEmpty(search)
                    ? ""
                    : $" AND (LOWER(i.name) LIKE '%{search.ToLower()}%' OR LOWER(i.code) LIKE '%{search.ToLower()}%')";

                string lowStockFilter = _lowStockMode
                    ? " AND (IFNULL(i.stock_quantity,0) < IFNULL(i.alert_quantity,0) OR IFNULL(i.stock_quantity,0) <= 0)"
                    : "";

                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();

                cmd.CommandText = @"SELECT i.name, i.code,
                    IFNULL(i.category_id, 0) AS category_id,
                    IFNULL(i.stock_quantity,0) AS current_stock,
                    IFNULL(i.alert_quantity, 0) AS alert_quantity,
                    COALESCE(NULLIF(i.last_three_purchase_avg,0), NULLIF(i.last_purchase_price,0), IFNULL(i.purchase_price,0)) AS avg_price,
                    IFNULL(i.conversion_rate, 1) AS conv,
                    IFNULL(i.unit_type, '1') AS unit_type,
                    IFNULL(u.UnitName, '') AS unit,
                    COALESCE(ic.name, '') AS category_name
                    FROM items i
                    LEFT JOIN units u ON i.sale_unit_id = u.Id
                    LEFT JOIN item_categories ic ON ic.id = i.category_id
                    WHERE (i.del_status IS NULL OR i.del_status = '' OR i.del_status = 'Live')
                      AND IFNULL(i.enable_disable_status,1) = 1
                      AND IFNULL(i.type,'') NOT IN ('Service_Product','Combo_Product','0')
                      AND i.parent_id IS NULL" + lowStockFilter + searchFilter + @"
                    ORDER BY i.name";

                var rawRows = new List<(string Name, string Code, double Stock, double Alert, double AvgPrice, double Conv, string UnitType, string Unit, string CategoryName)>();
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    rawRows.Add((
                        r.IsDBNull(0) ? "" : r.GetString(0),
                        r.IsDBNull(1) ? "" : r.GetString(1),
                        r.IsDBNull(3) ? 0 : r.GetDouble(3),
                        r.IsDBNull(4) ? 0 : r.GetDouble(4),
                        r.IsDBNull(5) ? 0 : r.GetDouble(5),
                        r.IsDBNull(6) ? 1 : r.GetDouble(6),
                        r.IsDBNull(7) ? "1" : r.GetString(7),
                        r.IsDBNull(8) ? "" : r.GetString(8),
                        r.IsDBNull(9) ? "" : r.GetString(9)
                    ));
                }

                _allRows.Clear();
                var displayRows = new List<StockRow>();
                int sn = 1;
                foreach (var row in rawRows)
                {
                    double conv = row.Conv > 0 ? row.Conv : 1;
                    double lpp = row.AvgPrice / conv;
                    double stockValue = lpp * row.Stock;
                    bool isLowStock = row.Alert > 0 && row.Stock <= row.Alert;
                    bool isOutOfStock = row.Stock <= 0;

                    var stockRow = new StockRow
                    {
                        SN = sn++,
                        ItemCode = string.IsNullOrEmpty(row.Code) ? row.Name : $"{row.Name} ({row.Code})",
                        Category = row.CategoryName,
                        StockDetails = "",
                        TotalStockQty = $"{row.Stock:N2} {row.Unit}".Trim(),
                        LppPp = $"INR{lpp:N2}",
                        TotalValue = $"INR{stockValue:N2}",
                        StockQtyNum = row.Stock,
                        AlertQty = row.Alert,
                        ForegroundColor = (isLowStock || isOutOfStock)
                            ? new SolidColorBrush(Color.FromRgb(239, 68, 68))
                            : Brushes.Black
                    };
                    _allRows.Add(stockRow);
                    displayRows.Add(stockRow);
                }

                itemsList.ItemsSource = displayRows;
                emptyState.Visibility = _allRows.Count == 0 ? Visibility.Visible : Visibility.Collapsed;
                itemsList.Visibility = _allRows.Count == 0 ? Visibility.Collapsed : Visibility.Visible;
            }
            catch (Exception ex)
            {
                MessageBox.Show("LoadData Error: " + ex.Message);
            }
        }

        private void Tab_Checked(object sender, RoutedEventArgs e)
        {
            if (tabLowStock == null || !_loaded) return;
            _lowStockMode = tabLowStock.IsChecked == true;
            LoadData();
        }

        private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e) { if (_loaded) LoadData(); }

        private void BtnRefresh_Click(object sender, RoutedEventArgs e)
        {
            LoadStats();
            LoadData();
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            if (_dashboard != null) _dashboard.ShowDashboard();
        }

        private void BtnExport_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                if (_allRows.Count == 0) { MessageBox.Show("No data to export.", "Info"); return; }
                var dlg = new SaveFileDialog
                {
                    Filter = "CSV files (*.csv)|*.csv",
                    FileName = (_lowStockMode ? "LowStock" : "Stock") + "_" + DateTime.Today.ToString("yyyyMMdd") + ".csv"
                };
                if (dlg.ShowDialog() != true) return;
                using var sw = new StreamWriter(dlg.FileName, false, System.Text.Encoding.UTF8);
                sw.WriteLine("SN,Item Name,Code,Stock Qty,Alert Qty,Purchase Price,Sale Price,Stock Value");
                int idx = 1;
                foreach (var row in _allRows)
                {
                    string name = row.ItemCode;
                    double qty = row.StockQtyNum;
                    double alert = row.AlertQty;
                    double pp = double.TryParse(row.LppPp.Replace("INR", ""), out var pv) ? pv : 0;
                    double total = double.TryParse(row.TotalValue.Replace("INR", ""), out var tv) ? tv : 0;
                    sw.WriteLine($"{idx++},\"{name}\",{qty},{alert},{pp},{total / Math.Max(qty, 0.0001)}");
                }
                MessageBox.Show("Exported to: " + dlg.FileName, "Success", MessageBoxButton.OK, MessageBoxImage.Information);
            }
            catch (Exception ex) { MessageBox.Show("Export error: " + ex.Message); }
        }

        private void BtnFilter_Click(object sender, RoutedEventArgs e)
        {
            // Placeholder for advanced filter
        }

        private void CmbEntriesPerPage_Changed(object sender, SelectionChangedEventArgs e)
        {
            if (!_loaded) return;
            if (cmbEntriesPerPage.SelectedItem is ComboBoxItem item)
            {
                if (int.TryParse(item.Content?.ToString(), out int size))
                {
                    _pageSize = size;
                    LoadData();
                }
            }
        }
    }
}
