using System;
using System.Collections.Generic;
using System.IO;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class InventoryPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();
        private List<StockRow> _allRows = new();
        private int _currentPage = 1;
        private int _pageSize = 25;
        private bool _isLowStock;

        public InventoryPage() { InitializeComponent(); }
        public InventoryPage(MainDashboard dashboard) : this()
        {
            _dashboard = dashboard;
            // Stock update kahin bhi hua to turant refresh
            Services.StockEvents.StockChanged += OnStockChanged;
            Unloaded += (_, _) => Services.StockEvents.StockChanged -= OnStockChanged;
        }

        private void OnStockChanged()
        {
            Dispatcher.BeginInvoke(new Action(() => LoadStock()), System.Windows.Threading.DispatcherPriority.Background);
        }

        private void UserControl_Loaded(object sender, RoutedEventArgs e)
        {
            LoadStock();
        }

        private void Tab_Click(object sender, RoutedEventArgs e)
        {
            _isLowStock = tabLowStock.IsChecked == true;
            lblTitle.Text = _isLowStock ? "Low Stock" : "Inventory";
            _currentPage = 1;
            txtSearch.Text = "";
            LoadStock();
        }

        private void LoadStock()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                if (_isLowStock)
                {
                    cmd.CommandText = @"SELECT i.id, i.name, i.code, i.stock_quantity, i.purchase_price,
                        i.last_purchase_price, i.alert_quantity,
                        c.Name as CategoryName, u.UnitName as UnitName
                        FROM items i
                        LEFT JOIN item_categories c ON i.category_id = c.Id
                        LEFT JOIN units u ON i.sale_unit_id = u.Id
                        WHERE (i.del_status IS NULL OR i.del_status != 'Deleted')
                        AND i.alert_quantity > 0 AND COALESCE(i.stock_quantity, 0) <= i.alert_quantity
                        ORDER BY i.name";
                }
                else
                {
                    cmd.CommandText = @"SELECT i.id, i.name, i.code, i.stock_quantity, i.purchase_price,
                        i.last_purchase_price, i.alert_quantity,
                        c.Name as CategoryName, u.UnitName as UnitName
                        FROM items i
                        LEFT JOIN item_categories c ON i.category_id = c.Id
                        LEFT JOIN units u ON i.sale_unit_id = u.Id
                        WHERE (i.del_status IS NULL OR i.del_status != 'Deleted')
                        ORDER BY i.name";
                }

                _allRows.Clear();
                using var r = cmd.ExecuteReader();
                int sn = 1;
                while (r.Read())
                {
                    var name = r["name"]?.ToString() ?? "";
                    var code = r["code"]?.ToString() ?? "";
                    var cat = r["CategoryName"]?.ToString() ?? "";
                    var unit = r["UnitName"]?.ToString() ?? "";
                    var stockQty = r["stock_quantity"] != DBNull.Value ? Convert.ToDouble(r["stock_quantity"]) : 0;
                    var purchasePrice = r["purchase_price"] != DBNull.Value ? Convert.ToDouble(r["purchase_price"]) : 0;
                    var lpp = r["last_purchase_price"] != DBNull.Value ? Convert.ToDouble(r["last_purchase_price"]) : 0;
                    var alertQty = r["alert_quantity"] != DBNull.Value ? Convert.ToDouble(r["alert_quantity"]) : 0;
                    var ppDisplay = purchasePrice > 0 ? purchasePrice : lpp;

                    _allRows.Add(new StockRow
                    {
                        SN = sn++,
                        ItemCode = $"{name} ({code})",
                        Category = cat,
                        StockDetails = "",
                        TotalStockQty = $"{stockQty:0.##} {unit}".Trim(),
                        LppPp = $"₹{ppDisplay:0.00}",
                        TotalValue = $"₹{stockQty * ppDisplay:0.00}",
                        StockQtyNum = stockQty,
                        AlertQty = alertQty
                    });
                }

                UpdateSummary();
                ApplyFilter();
            }
            catch (Exception ex)
            {
                System.Diagnostics.Debug.WriteLine("LoadStock ERR: " + ex.Message);
            }
        }

        private void UpdateSummary()
        {
            double totalValue = 0, totalCount = 0;
            foreach (var row in _allRows)
            {
                totalCount += row.StockQtyNum;
                var numStr = row.TotalValue.Replace("₹", "");
                if (double.TryParse(numStr, out double val)) totalValue += val;
            }
            runStockValue.Text = $"₹ {totalValue:N2}";
            runStockCount.Text = totalCount.ToString("N0");
        }

        private void ApplyFilter()
        {
            var search = txtSearch?.Text?.Trim().ToLower() ?? "";
            var filtered = string.IsNullOrEmpty(search)
                ? _allRows
                : _allRows.FindAll(r =>
                    r.ItemCode.ToLower().Contains(search) ||
                    r.Category.ToLower().Contains(search));

            int totalPages = Math.Max(1, (int)Math.Ceiling((double)filtered.Count / _pageSize));
            if (_currentPage > totalPages) _currentPage = totalPages;

            int skip = (_currentPage - 1) * _pageSize;
            var page = filtered.GetRange(skip, Math.Min(_pageSize, filtered.Count - skip));

            itemsList.ItemsSource = page;
            lblEmpty.Visibility = page.Count == 0 ? Visibility.Visible : Visibility.Collapsed;
            itemsList.Visibility = page.Count == 0 ? Visibility.Collapsed : Visibility.Visible;

            int showFrom = filtered.Count == 0 ? 0 : skip + 1;
            int showTo = Math.Min(skip + _pageSize, filtered.Count);
            lblPaging.Text = $"Showing {showFrom} to {showTo} of {filtered.Count} entries";

            btnPrev.IsEnabled = _currentPage > 1;
            btnNext.IsEnabled = _currentPage < totalPages;

            pagingItems.Items.Clear();
            for (int i = 1; i <= totalPages; i++)
            {
                var pg = i;
                var btn = new Button
                {
                    Content = pg.ToString(),
                    Width = 34,
                    Height = 34,
                    FontSize = 13,
                    FontFamily = new FontFamily("Inter, Segoe UI"),
                    Margin = new Thickness(2, 0, 2, 0),
                    Cursor = System.Windows.Input.Cursors.Hand
                };
                btn.Click += (_, _) => { _currentPage = pg; ApplyFilter(); };

                if (pg == _currentPage)
                {
                    btn.Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#4F46E5"));
                    btn.Foreground = Brushes.White;
                    btn.BorderThickness = new Thickness(0);
                }
                else
                {
                    btn.Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#F3F4F6"));
                    btn.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#374151"));
                    btn.BorderThickness = new Thickness(1);
                    btn.BorderBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#E5E7EB"));
                }
                btn.Template = (ControlTemplate)FindResource("PagingBtn");
                pagingItems.Items.Add(btn);
            }
        }

        private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e)
        {
            _currentPage = 1;
            ApplyFilter();
        }

        private void CmbPageSize_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (cmbPageSize.SelectedItem is ComboBoxItem cbi && int.TryParse(cbi.Content?.ToString(), out int ps))
                _pageSize = ps;
            _currentPage = 1;
            ApplyFilter();
        }

        private void BtnPrev_Click(object sender, RoutedEventArgs e)
        {
            if (_currentPage > 1) { _currentPage--; ApplyFilter(); }
        }

        private void BtnNext_Click(object sender, RoutedEventArgs e)
        {
            int totalPages = Math.Max(1, (int)Math.Ceiling((double)_allRows.Count / _pageSize));
            if (_currentPage < totalPages) { _currentPage++; ApplyFilter(); }
        }

        private void BtnExport_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                var search = txtSearch?.Text?.Trim().ToLower() ?? "";
                var filtered = string.IsNullOrEmpty(search)
                    ? _allRows
                    : _allRows.FindAll(r =>
                        r.ItemCode.ToLower().Contains(search) ||
                        r.Category.ToLower().Contains(search));

                var dialog = new Microsoft.Win32.SaveFileDialog
                {
                    Filter = "CSV files (*.csv)|*.csv",
                    FileName = (_isLowStock ? "LowStock" : "Stock") + "_" + DateTime.Now.ToString("yyyyMMdd_HHmmss") + ".csv"
                };
                if (dialog.ShowDialog() != true) return;

                using var writer = new StreamWriter(dialog.FileName);
                writer.WriteLine("SN,ITEM(CODE),CATEGORY,STOCK DETAILS,TOTAL STOCK QUANTITY,LPP/PP,TOTAL");
                foreach (var row in filtered)
                    writer.WriteLine($"{row.SN},\"{row.ItemCode}\",\"{row.Category}\",\"{row.StockDetails}\",\"{row.TotalStockQty}\",{row.LppPp},{row.TotalValue}");

                MessageBox.Show($"Exported {filtered.Count} records successfully!", "Export",
                    MessageBoxButton.OK, MessageBoxImage.Information);
            }
            catch (Exception ex)
            {
                MessageBox.Show("Export failed: " + ex.Message, "Error",
                    MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowDashboard();
        }
    }

    public class StockRow
    {
        public int SN { get; set; }
        public string ItemCode { get; set; } = "";
        public string Category { get; set; } = "";
        public string StockDetails { get; set; } = "";
        public string TotalStockQty { get; set; } = "";
        public string LppPp { get; set; } = "";
        public string TotalValue { get; set; } = "";
        public double StockQtyNum { get; set; }
        public double AlertQty { get; set; }
        public SolidColorBrush? ForegroundColor { get; set; }
    }
}
