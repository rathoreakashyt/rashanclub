using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Threading;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;
using Microsoft.Win32;
using QuestPDF.Fluent;
using QuestPDF.Helpers;
using QuestPDF.Infrastructure;
using RashanKiDukan.Database;
using Color = System.Windows.Media.Color;

namespace RashanKiDukan.Views
{
    public partial class ItemMasterListingPage : UserControl, ISyncRefreshable
    {
        private readonly DatabaseService _db = new();
        private readonly MainDashboard? _dashboard;
        private string _currentFilter = "All";
        private string _searchQuery = "";
        // _allItems removed — ab DB-level pagination use hoti hai
        private List<ItemListItem> _filteredItems = new(); // sirf current page items
        private int _currentPage = 1;
        private int _pageSize = 10;
        private bool _suppressFilter = false;

        // Totals (DB se count queries)
        private int _totalFilteredCount = 0;
        private int _totalAllCount = 0;
        private int _lowStockCount = 0;
        private int _outStockCount = 0;

        // Search debounce — har keystroke pe query nahi, 300ms baad
        private CancellationTokenSource? _searchCts;
        private bool _isLoading = false;

        public ItemMasterListingPage() { InitializeComponent(); }
        public ItemMasterListingPage(MainDashboard dashboard) : this()
        {
            _dashboard = dashboard;
            // Page size options: 10 / 20 / 50 / 100 / 500 — jitna chune utne items screen par
            cmbPageSize.Items.Add("10");
            cmbPageSize.Items.Add("20");
            cmbPageSize.Items.Add("50");
            cmbPageSize.Items.Add("100");
            cmbPageSize.Items.Add("500");
            cmbPageSize.SelectedIndex = 0;
            // Filter options pehle se load — Filter button khole bina bhi combos ready
            _suppressFilter = true;
            LoadFilterOptions();
            _suppressFilter = false;
            // Stock update kahin bhi hua to turant refresh
            Services.StockEvents.StockChanged += OnStockChanged;
            Unloaded += (_, _) => Services.StockEvents.StockChanged -= OnStockChanged;
            LoadItems();
        }

        private void OnStockChanged()
        {
            Dispatcher.BeginInvoke(new Action(() =>
            {
                if (IsLoaded) LoadItems();
            }), System.Windows.Threading.DispatcherPriority.Background);
        }

        // ═══ CLOUD→DESKTOP LIVE REFRESH ═══
        // Web/cloud se item delete/edit aaya (sync pull) → list khud reload.
        // LoadItems local DB se padhta hai (del_status='Live' filter) — web par
        // delete kiya item yahan bina refresh ke list se hat jata hai.
        public void OnSyncPulled()
        {
            Dispatcher.BeginInvoke(new Action(() =>
            {
                if (IsLoaded) LoadItemsAsync();
            }), System.Windows.Threading.DispatcherPriority.Background);
        }

        // ═══ DATA LOADING — DB-level pagination (fast even with 100k items) ═══

        public void LoadItems() => LoadItemsAsync();

        private async void LoadItemsAsync(CancellationToken ct = default)
        {
            if (_isLoading) return;
            _isLoading = true;
            try
            {
                // Filter params captured on UI thread before Task.Run
                string filter = _currentFilter;
                string search = _searchQuery;
                int page = _currentPage;
                int pageSize = _pageSize;
                long categoryId = cmbCategory?.SelectedItem is FilterOption fc ? fc.Id : 0;
                long brandId    = cmbBrand?.SelectedItem    is FilterOption fb ? fb.Id : 0;
                long supplierId = cmbSupplier?.SelectedItem is FilterOption fs ? fs.Id : 0;
                string stockF   = cmbStock?.SelectedItem?.ToString() ?? "All";

                var (items, totalFiltered, totalAll, activeCount, inactiveCount, lowCount, outCount) =
                    await Task.Run(() => FetchPageFromDb(filter, search, page, pageSize, categoryId, brandId, supplierId, stockF, ct), ct);

                if (ct.IsCancellationRequested) return;

                _filteredItems      = items;
                _totalFilteredCount = totalFiltered;
                _totalAllCount      = totalAll;
                _lowStockCount      = lowCount;
                _outStockCount      = outCount;

                RenderPage(totalAll, activeCount, inactiveCount);
            }
            catch (OperationCanceledException) { }
            catch (Exception ex) { Services.LogService.Error($"LoadItemsAsync: {ex.Message}"); }
            finally { _isLoading = false; }
        }

        private (List<ItemListItem> items, int totalFiltered, int totalAll, int activeCount, int inactiveCount, int lowCount, int outCount)
            FetchPageFromDb(string filter, string search, int page, int pageSize,
                            long categoryId, long brandId, long supplierId, string stockF,
                            CancellationToken ct)
        {
            using var conn = _db.GetConnection();

            // ── WHERE clause ──
            string where = "(i.del_status IS NULL OR i.del_status='Live') AND (i.type IS NULL OR i.type != '0')";
            if (filter == "Active")   where += " AND (i.enable_disable_status IS NULL OR i.enable_disable_status=1)";
            else if (filter == "Inactive") where += " AND i.enable_disable_status=0";
            bool hasSearch = !string.IsNullOrWhiteSpace(search);
            if (hasSearch) where += " AND (i.name LIKE @q OR i.code LIKE @q)";

            // Category/Brand/Supplier filters in WHERE (DB-level, not in-memory)
            if (categoryId > 0) where += " AND (COALESCE(NULLIF(ic.ServerId,0), c.id, i.category_id) = @catId)";
            if (brandId > 0)    where += " AND ib.Id = @brandId";
            if (supplierId > 0) where += " AND isup.Id = @supplierId";
            if (stockF == "In Stock")       where += " AND IFNULL(COALESCE(v.total_stock, i.stock_quantity),0) > IFNULL(i.alert_quantity,5)";
            else if (stockF == "Low Stock") where += " AND IFNULL(COALESCE(v.total_stock, i.stock_quantity),0) > 0.009 AND IFNULL(COALESCE(v.total_stock, i.stock_quantity),0) <= IFNULL(i.alert_quantity,5)";
            else if (stockF == "Out of Stock") where += " AND IFNULL(COALESCE(v.total_stock, i.stock_quantity),0) <= 0.009";

            string variantSub = @"SELECT parent_id,
                MIN(CASE WHEN sale_price>0 THEN sale_price END) AS min_sale,
                MIN(CASE WHEN purchase_price>0 THEN purchase_price END) AS min_purchase,
                MIN(CASE WHEN mrp_price>0 THEN mrp_price END) AS min_mrp,
                SUM(IFNULL(stock_quantity,0)) AS total_stock
                FROM items WHERE parent_id IS NOT NULL AND parent_id>0
                AND (del_status IS NULL OR del_status='Live') GROUP BY parent_id";

            string joinClause = @"FROM items i
                LEFT JOIN ItemCategories ic ON ic.Id = i.category_id
                LEFT JOIN item_categories c ON c.id = i.category_id
                LEFT JOIN Brands ib ON ib.Id = i.brand_id
                LEFT JOIN Suppliers isup ON isup.Id = i.supplier_id
                LEFT JOIN (" + variantSub + ") v ON v.parent_id = i.id";

            ct.ThrowIfCancellationRequested();

            // ── COUNT queries ──
            long totalFiltered, totalAll, activeCount, inactiveCount, lowCount, outCount;

            using (var c = conn.CreateCommand())
            {
                c.CommandText = $"SELECT COUNT(*) {joinClause} WHERE {where}";
                if (hasSearch) c.Parameters.AddWithValue("@q", $"%{search}%");
                if (categoryId > 0) c.Parameters.AddWithValue("@catId", categoryId);
                if (brandId > 0)    c.Parameters.AddWithValue("@brandId", brandId);
                if (supplierId > 0) c.Parameters.AddWithValue("@supplierId", supplierId);
                totalFiltered = (long)(c.ExecuteScalar() ?? 0L);
            }
            using (var c = conn.CreateCommand())
            {
                c.CommandText = "SELECT COUNT(*) FROM items WHERE del_status IS NULL OR del_status='Live'";
                totalAll = (long)(c.ExecuteScalar() ?? 0L);
            }
            using (var c = conn.CreateCommand())
            {
                c.CommandText = "SELECT COUNT(*) FROM items WHERE (del_status IS NULL OR del_status='Live') AND (enable_disable_status IS NULL OR enable_disable_status=1)";
                activeCount = (long)(c.ExecuteScalar() ?? 0L);
            }
            using (var c = conn.CreateCommand())
            {
                c.CommandText = "SELECT COUNT(*) FROM items WHERE (del_status IS NULL OR del_status='Live') AND enable_disable_status=0";
                inactiveCount = (long)(c.ExecuteScalar() ?? 0L);
            }
            using (var c = conn.CreateCommand())
            {
                c.CommandText = @"SELECT
                    SUM(CASE WHEN IFNULL(stock_quantity,0)>0.009 AND IFNULL(stock_quantity,0)<=IFNULL(alert_quantity,5) THEN 1 ELSE 0 END),
                    SUM(CASE WHEN IFNULL(stock_quantity,0)<=0.009 THEN 1 ELSE 0 END)
                    FROM items WHERE del_status IS NULL OR del_status='Live'";
                using var r = c.ExecuteReader();
                r.Read();
                lowCount = r.IsDBNull(0) ? 0 : r.GetInt64(0);
                outCount = r.IsDBNull(1) ? 0 : r.GetInt64(1);
            }

            ct.ThrowIfCancellationRequested();

            // ── PAGE data — only pageSize rows ──
            int offset = (page - 1) * pageSize;
            using var cmd = conn.CreateCommand();
            cmd.CommandText = $@"SELECT i.id, i.name, i.code,
                IFNULL(COALESCE(v.total_stock, i.stock_quantity), 0) AS stock_qty,
                IFNULL(i.alert_quantity, 5) AS alert_qty,
                CASE WHEN (i.type='Variation_Product' AND IFNULL(i.purchase_price,0)<=0) THEN IFNULL(v.min_purchase,0) ELSE IFNULL(i.purchase_price,0) END AS purchase_price,
                CASE WHEN (i.type='Variation_Product' AND IFNULL(i.sale_price,0)<=0)     THEN IFNULL(v.min_sale,0)     ELSE IFNULL(i.sale_price,0)     END AS sale_price,
                CASE WHEN (i.type='Variation_Product' AND IFNULL(i.mrp_price,0)<=0)      THEN IFNULL(v.min_mrp,0)      ELSE IFNULL(i.mrp_price,0)      END AS mrp_price,
                COALESCE(ic.Name, c.name, '') AS category_name,
                COALESCE(NULLIF(ic.ServerId,0), c.id, i.category_id) AS cat_id,
                COALESCE(ib.Name, '') AS brand_name,
                COALESCE(NULLIF(ib.ServerId,0), i.brand_id) AS brand_id,
                COALESCE(isup.Name, '') AS supplier_name,
                COALESCE(NULLIF(isup.ServerId,0), i.supplier_id) AS supplier_id,
                CASE WHEN (i.enable_disable_status IS NULL OR i.enable_disable_status=1) THEN 'Active' ELSE 'Inactive' END AS Status
                {joinClause}
                WHERE {where}
                ORDER BY i.id DESC
                LIMIT @limit OFFSET @offset";

            if (hasSearch) cmd.Parameters.AddWithValue("@q", $"%{search}%");
            if (categoryId > 0) cmd.Parameters.AddWithValue("@catId", categoryId);
            if (brandId > 0)    cmd.Parameters.AddWithValue("@brandId", brandId);
            if (supplierId > 0) cmd.Parameters.AddWithValue("@supplierId", supplierId);
            cmd.Parameters.AddWithValue("@limit", pageSize);
            cmd.Parameters.AddWithValue("@offset", offset);

            var items = new List<ItemListItem>();
            int sn = offset + 1;
            using var reader = cmd.ExecuteReader();
            while (reader.Read())
            {
                double stock = reader.IsDBNull(3) ? 0 : reader.GetDouble(3);
                double alert = reader.IsDBNull(4) ? 5 : reader.GetDouble(4);
                string status = reader["Status"]?.ToString() ?? "Active";
                string stockStatus = stock <= 0.009 ? "Out" : stock <= alert ? "Low" : "In";
                items.Add(new ItemListItem
                {
                    Id        = Convert.ToInt32(reader["id"]),
                    SN        = (sn++).ToString(),
                    ItemName  = reader["name"]?.ToString() ?? "",
                    Code      = reader["code"]?.ToString() ?? "",
                    Category  = reader["category_name"]?.ToString() ?? "",
                    CategoryId= reader.IsDBNull(reader.GetOrdinal("cat_id"))      ? 0 : reader.GetInt64(reader.GetOrdinal("cat_id")),
                    BrandId   = reader.IsDBNull(reader.GetOrdinal("brand_id"))    ? 0 : reader.GetInt64(reader.GetOrdinal("brand_id")),
                    SupplierId= reader.IsDBNull(reader.GetOrdinal("supplier_id")) ? 0 : reader.GetInt64(reader.GetOrdinal("supplier_id")),
                    StockQty  = stock,
                    StockQtyDisplay = stock.ToString("0.##"),
                    PurchasePrice = $"₹{Convert.ToDouble(reader["purchase_price"]):N2}",
                    SalePrice     = $"₹{Convert.ToDouble(reader["sale_price"]):N2}",
                    MrpPrice      = $"₹{Convert.ToDouble(reader["mrp_price"]):N2}",
                    Status    = status,
                    StockStatus = stockStatus
                });
            }

            return (items, (int)totalFiltered, (int)totalAll, (int)activeCount, (int)inactiveCount, (int)lowCount, (int)outCount);
        }

        private void RenderPage(int totalAll, int activeCount, int inactiveCount)
        {
            int totalPages = Math.Max(1, (int)Math.Ceiling((double)_totalFilteredCount / _pageSize));
            if (_currentPage > totalPages) _currentPage = totalPages;

            int start = (_currentPage - 1) * _pageSize;
            int end = start + _filteredItems.Count;

            dgItems.ItemsSource = _filteredItems;
            bool empty = _filteredItems.Count == 0;
            dgItems.Visibility = empty ? Visibility.Collapsed : Visibility.Visible;
            emptyState.Visibility = empty ? Visibility.Visible : Visibility.Collapsed;

            lblShowing.Text = empty ? "Showing 0 entries"
                : $"Showing {start + 1} to {end} of {_totalFilteredCount} entries";
            lblPageInfo.Text = empty ? "" : $"Page {_currentPage} of {totalPages}";
            lblTotal.Text    = $"Total: {totalAll} items";
            lblLowCount.Text = _lowStockCount.ToString();
            lblOutCount.Text = _outStockCount.ToString();

            tabAll.Content      = $"All ({totalAll})";
            tabActive.Content   = $"Active ({activeCount})";
            tabInactive.Content = $"Inactive ({inactiveCount})";

            BuildPageButtons(totalPages);
            btnPrev.IsEnabled = _currentPage > 1;
            btnNext.IsEnabled = _currentPage < totalPages;
            btnPrev.Opacity   = btnPrev.IsEnabled ? 1 : 0.5;
            btnNext.Opacity   = btnNext.IsEnabled ? 1 : 0.5;
        }


        private void LoadFilterOptions()
        {
            cmbCategory.Items.Clear();
            cmbCategory.Items.Add(new FilterOption(0, "All Categories"));
            cmbBrand.Items.Clear();
            cmbBrand.Items.Add(new FilterOption(0, "All Brands"));
            cmbSupplier.Items.Clear();
            cmbSupplier.Items.Add(new FilterOption(0, "All Suppliers"));
            cmbStock.Items.Clear();
            cmbStock.Items.Add("All");
            cmbStock.Items.Add("In Stock");
            cmbStock.Items.Add("Low Stock");
            cmbStock.Items.Add("Out of Stock");

            try
            {
                using var conn = _db.GetConnection();

                // Category — item list mein cat_id ServerId preference se resolve hota hai,
                // isliye filter options bhi ServerId (agar ho) use karte hain, warna local id.
                // (local id + ServerId dono ko ek hi list mein add karne se filter kabhi
                //  mismatch nahi hota)
                var seenCategories = new HashSet<long>();
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT Id, ServerId, Name FROM ItemCategories WHERE (del_status IS NULL OR del_status!='Deleted') AND Name<>'' ORDER BY Name";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        long id = r.IsDBNull(1) || r.GetInt64(1) <= 0 ? r.GetInt64(0) : r.GetInt64(1);
                        if (!seenCategories.Add(id)) continue;
                        cmbCategory.Items.Add(new FilterOption(id, r["Name"]?.ToString() ?? ""));
                    }
                }
                // Fallback: mirror table rows jo ItemCategories mein nahi hain
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT id, name FROM item_categories WHERE (del_status IS NULL OR del_status!='Deleted') AND name<>'' ORDER BY name";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        long id = r.GetInt64(0);
                        if (!seenCategories.Add(id)) continue;
                        cmbCategory.Items.Add(new FilterOption(id, r["name"]?.ToString() ?? ""));
                    }
                }

                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT Id, ServerId, Name FROM Brands WHERE (del_status IS NULL OR del_status!='Deleted') AND Name<>'' ORDER BY Name";
                    using var r = cmd.ExecuteReader();
                    while (r.Read()) cmbBrand.Items.Add(new FilterOption(r.IsDBNull(1) || r.GetInt64(1) <= 0 ? r.GetInt64(0) : r.GetInt64(1), r["Name"]?.ToString() ?? ""));
                }
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT Id, ServerId, Name FROM Suppliers WHERE (del_status IS NULL OR del_status!='Deleted') AND Name<>'' ORDER BY Name";
                    using var r = cmd.ExecuteReader();
                    while (r.Read()) cmbSupplier.Items.Add(new FilterOption(r.IsDBNull(1) || r.GetInt64(1) <= 0 ? r.GetInt64(0) : r.GetInt64(1), r["Name"]?.ToString() ?? ""));
                }
            }
            catch (Exception ex)
            {
                Services.LogService.Error($"ItemMasterListingPage.LoadFilterOptions: {ex.Message}");
                // suppliers table missing → Master1 legacy fallback
                try
                {
                    using var conn = _db.GetConnection();
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = "SELECT ServerId, Name FROM Master1 WHERE MasterType='Party' AND PartyType IN ('Supplier','Both') AND IsActive=1 AND Name<>'' ORDER BY Name";
                    using var r = cmd.ExecuteReader();
                    while (r.Read()) cmbSupplier.Items.Add(new FilterOption(r.GetInt64(0), r["name"]?.ToString() ?? ""));
                }
                catch (Exception ex2)
                {
                    Services.LogService.Error($"ItemMasterListingPage.LoadFilterOptions fallback: {ex2.Message}");
                }
            }

            cmbCategory.SelectedIndex = 0;
            cmbBrand.SelectedIndex = 0;
            cmbSupplier.SelectedIndex = 0;
            cmbStock.SelectedIndex = 0;
        }

        // ═══ ApplyFilter — delegates to DB-level pagination ═══
        private void ApplyFilter()
        {
            LoadItemsAsync();
        }

        // ═══ FILTERS ═══
        // dikhte hain, beech mein ellipsis "…" aata hai.
        private void BuildPageButtons(int totalPages)
        {
            pageButtons.Items.Clear();
            foreach (var p in GetPageWindow(totalPages, _currentPage))
            {
                if (p == 0)
                {
                    pageButtons.Items.Add(new TextBlock
                    {
                        Text = "…",
                        FontSize = 13,
                        Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#64748B")),
                        VerticalAlignment = System.Windows.VerticalAlignment.Center,
                        Margin = new Thickness(2, 0, 2, 0)
                    });
                    continue;
                }

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
                        : Brushes.White,
                    Foreground = p == _currentPage
                        ? Brushes.White
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

        private static List<int> GetPageWindow(int totalPages, int current, int maxButtons = 7)
        {
            var list = new List<int>();
            if (totalPages <= maxButtons)
            {
                for (int i = 1; i <= totalPages; i++) list.Add(i);
                return list;
            }
            list.Add(1);
            int start = Math.Max(2, current - 1);
            int end = Math.Min(totalPages - 1, current + 1);
            if (start > 2) list.Add(0); // ellipsis
            for (int i = start; i <= end; i++) list.Add(i);
            if (end < totalPages - 1) list.Add(0); // ellipsis
            list.Add(totalPages);
            return list;
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
            content.SetValue(ContentPresenter.VerticalAlignmentProperty, System.Windows.VerticalAlignment.Center);
            content.SetValue(ContentPresenter.HorizontalAlignmentProperty, System.Windows.HorizontalAlignment.Center);
            border.AppendChild(content);
            template.VisualTree = border;

            var hoverTrigger = new Trigger { Property = Control.IsMouseOverProperty, Value = true };
            hoverTrigger.Setters.Add(new Setter(Border.BackgroundProperty, new SolidColorBrush((Color)ColorConverter.ConvertFromString("#EEF2FF")), border.Name));
            template.Triggers.Add(hoverTrigger);
            template.Seal();
            return template;
        }

        // ═══ EVENT HANDLERS ═══

        private void Tab_Click(object sender, RoutedEventArgs e)
        {
            if (tabAll.IsChecked == true) _currentFilter = "All";
            else if (tabActive.IsChecked == true) _currentFilter = "Active";
            else if (tabInactive.IsChecked == true) _currentFilter = "Inactive";
            _currentPage = 1;
            LoadItems();
        }

        private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e)
        {
            // Debounce: 300ms baad query chalao — har keystroke pe nahi
            _searchCts?.Cancel();
            _searchCts = new CancellationTokenSource();
            var ct = _searchCts.Token;
            var query = txtSearch.Text.Trim();
            _ = Task.Delay(300, ct).ContinueWith(_ =>
            {
                if (ct.IsCancellationRequested) return;
                Dispatcher.Invoke(() =>
                {
                    _searchQuery = query;
                    _currentPage = 1;
                    LoadItemsAsync(ct);
                });
            }, ct);
        }

        private void CmbPageSize_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (cmbPageSize.SelectedItem is not string s || !int.TryParse(s, out var size)) return;
            _pageSize = size;
            _currentPage = 1;
            ApplyFilter();
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e) { _dashboard?.ShowDashboard(); }

        private void BtnAddItem_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowPage(new ItemMasterPage(_dashboard!));
        }

        private void BtnToggleFilter_Click(object sender, RoutedEventArgs e)
        {
            if (filterCard.Visibility == Visibility.Collapsed)
            {
                if (cmbCategory.Items.Count == 0)
                {
                    _suppressFilter = true;
                    LoadFilterOptions();
                    _suppressFilter = false;
                }
                filterCard.Visibility = Visibility.Visible;
            }
            else filterCard.Visibility = Visibility.Collapsed;
        }

        private void BtnApplyFilter_Click(object sender, RoutedEventArgs e) { _currentPage = 1; ApplyFilter(); }

        // Combo se select karte hi filter turant apply — Apply Filter button ka wait nahi
        private void FilterCombo_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (_suppressFilter) return;
            _currentPage = 1;
            ApplyFilter();
        }

        private void BtnClearFilter_Click(object sender, RoutedEventArgs e)
        {
            if (cmbCategory.Items.Count > 0) cmbCategory.SelectedIndex = 0;
            if (cmbBrand.Items.Count > 0) cmbBrand.SelectedIndex = 0;
            if (cmbSupplier.Items.Count > 0) cmbSupplier.SelectedIndex = 0;
            if (cmbStock.Items.Count > 0) cmbStock.SelectedIndex = 0;
            _currentPage = 1;
            ApplyFilter();
        }

        private void BtnPagePrev(object sender, RoutedEventArgs e) { if (_currentPage > 1) { _currentPage--; ApplyFilter(); } }
        private void BtnPageNext(object sender, RoutedEventArgs e)
        {
            int totalPages = Math.Max(1, (int)Math.Ceiling((double)_totalFilteredCount / _pageSize));
            if (_currentPage < totalPages) { _currentPage++; ApplyFilter(); }
        }

        // ═══ EXPORT ═══

        // Export dropdown (CSV / PDF) — jo items abhi screen par dikh rahe hain
        // (selected page size ke hisaab se) wahi export hote hain.
        private void BtnExport_Click(object sender, RoutedEventArgs e)
        {
            if (btnExport.ContextMenu != null)
            {
                btnExport.ContextMenu.PlacementTarget = btnExport;
                btnExport.ContextMenu.IsOpen = true;
            }
        }

        private List<ItemListItem> CurrentPageItems()
        {
            // _filteredItems ab sirf current page ke items hai (DB-level pagination)
            return _filteredItems;
        }

        private static string Num(string price) => price.Replace("₹", "").Trim();

        private void BtnExportCsv_Click(object sender, RoutedEventArgs e)
        {
            var rows = CurrentPageItems();
            if (rows.Count == 0) { MessageBox.Show("No data to export.", "Export", MessageBoxButton.OK, MessageBoxImage.Information); return; }

            var dlg = new SaveFileDialog
            {
                Title = "Export Items",
                Filter = "CSV files (*.csv)|*.csv",
                FileName = "Items_" + DateTime.Now.ToString("yyyyMMdd_HHmm") + ".csv"
            };
            if (dlg.ShowDialog() != true) return;

            try
            {
                var sb = new System.Text.StringBuilder();
                sb.AppendLine("SN,Code,Name,Category,Stock Qty,Purchase Price,Sale Price,MRP,Stock Status,Status");
                foreach (var x in rows)
                {
                    sb.AppendLine(string.Join(",",
                        Csv(x.SN), Csv(x.Code), Csv(x.ItemName), Csv(x.Category), Csv(x.StockQtyDisplay),
                        Csv(Num(x.PurchasePrice)), Csv(Num(x.SalePrice)), Csv(Num(x.MrpPrice)),
                        Csv(x.StockLabel), Csv(x.Status)));
                }
                File.WriteAllText(dlg.FileName, sb.ToString(), System.Text.Encoding.UTF8);
                MessageBox.Show("Export complete.\n" + dlg.FileName, "Export", MessageBoxButton.OK, MessageBoxImage.Information);
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Export", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void BtnExportPdf_Click(object sender, RoutedEventArgs e)
        {
            var rows = CurrentPageItems();
            if (rows.Count == 0) { MessageBox.Show("No data to export.", "Export", MessageBoxButton.OK, MessageBoxImage.Information); return; }

            var dlg = new SaveFileDialog
            {
                Title = "Export Items",
                Filter = "PDF files (*.pdf)|*.pdf",
                FileName = "Items_" + DateTime.Now.ToString("yyyyMMdd_HHmm") + ".pdf"
            };
            if (dlg.ShowDialog() != true) return;

            try
            {
                QuestPDF.Settings.License = LicenseType.Community;
                Document.Create(container =>
                {
                    container.Page(page =>
                    {
                        page.Size(PageSizes.A4.Landscape());
                        page.Margin(30);
                        page.DefaultTextStyle(x => x.FontSize(9));
                        page.Header().Column(col =>
                        {
                            col.Item().Text("Item List").FontSize(16).Bold();
                            col.Item().PaddingTop(4).Text("Generated: " + DateTime.Now.ToString("dd MMM yyyy, hh:mm tt")).FontSize(9).FontColor("#64748B");
                        });
                        page.Content().PaddingTop(10).Table(table =>
                        {
                            table.ColumnsDefinition(c =>
                            {
                                c.ConstantColumn(30);
                                c.RelativeColumn(1.4f);
                                c.RelativeColumn(2.2f);
                                c.RelativeColumn(1.6f);
                                c.RelativeColumn(1);
                                c.RelativeColumn(1.1f);
                                c.RelativeColumn(1.1f);
                                c.RelativeColumn(1.1f);
                                c.RelativeColumn(1);
                                c.RelativeColumn(1);
                            });
                            table.Header(h =>
                            {
                                h.Cell().Background("#EEF2FF").Padding(5).Text("SN").Bold();
                                h.Cell().Background("#EEF2FF").Padding(5).Text("Code").Bold();
                                h.Cell().Background("#EEF2FF").Padding(5).Text("Name").Bold();
                                h.Cell().Background("#EEF2FF").Padding(5).Text("Group").Bold();
                                h.Cell().Background("#EEF2FF").Padding(5).Text("Stock").Bold();
                                h.Cell().Background("#EEF2FF").Padding(5).Text("Purchase").Bold();
                                h.Cell().Background("#EEF2FF").Padding(5).Text("Sale").Bold();
                                h.Cell().Background("#EEF2FF").Padding(5).Text("MRP").Bold();
                                h.Cell().Background("#EEF2FF").Padding(5).Text("Stock Status").Bold();
                                h.Cell().Background("#EEF2FF").Padding(5).Text("Status").Bold();
                            });
                            foreach (var row in rows)
                            {
                                table.Cell().Padding(5).Text(row.SN);
                                table.Cell().Padding(5).Text(row.Code);
                                table.Cell().Padding(5).Text(row.ItemName);
                                table.Cell().Padding(5).Text(row.Category);
                                table.Cell().Padding(5).Text(row.StockQtyDisplay);
                                table.Cell().Padding(5).Text(Num(row.PurchasePrice));
                                table.Cell().Padding(5).Text(Num(row.SalePrice));
                                table.Cell().Padding(5).Text(Num(row.MrpPrice));
                                table.Cell().Padding(5).Text(row.StockLabel);
                                table.Cell().Padding(5).Text(row.Status);
                            }
                        });
                        page.Footer().AlignCenter().Text("Generated by Rashan Ki Dukan POS").FontSize(8).FontColor("#94A3B8");
                    });
                }).GeneratePdf(dlg.FileName);
                PdfPreviewWindow.ShowPdf(dlg.FileName);
            }
            catch (Exception ex)
            {
                MessageBox.Show("PDF export failed: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private static string Csv(string v) => "\"" + v.Replace("\"", "\"\"") + "\"";

        private void BtnBarcode_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not Button btn) return;
            // Tag can be int or long depending on binding
            int id = btn.Tag is int i ? i : btn.Tag is long l ? (int)l : 0;
            if (id == 0) return;

            var item = _filteredItems.Find(x => x.Id == id);
            if (item == null) return;

            // Build price string — use MRP if available, else SalePrice
            string price = !string.IsNullOrWhiteSpace(item.MrpPrice) && item.MrpPrice != "₹0.00"
                ? item.MrpPrice.Replace("₹", "").Trim()
                : item.SalePrice.Replace("₹", "").Trim();

            var dialog = new BarcodePrintDialog(item.ItemName, item.Code, price)
            {
                Owner = Window.GetWindow(this)
            };
            dialog.ShowDialog();
        }

        private void BtnView_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is int id)
                _dashboard?.ShowPage(new ItemMasterPage(_dashboard!, (long)id));
        }

        private void BtnEdit_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is int id)
                _dashboard?.ShowPage(new ItemMasterPage(_dashboard!, (long)id));
        }

        private void BtnDelete_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is int id)
            {
                var item = _filteredItems.Find(x => x.Id == id);
                var result = MessageBox.Show($"Delete '{item?.ItemName}'?\nThis action cannot be undone.", "Confirm Delete",
                    MessageBoxButton.YesNo, MessageBoxImage.Warning);
                if (result != MessageBoxResult.Yes) return;

                try
                {
                    using var conn = _db.GetConnection();
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = "UPDATE items SET del_status='Deleted' WHERE id=@id";
                    cmd.Parameters.AddWithValue("@id", id);
                    cmd.ExecuteNonQuery();
                    // FIX: server delete ke liye ServerId resolve karo (local id mapping
                    // pe depend mat karo) — baaki pages jaise hi
                    long serverId = 0;
                    using (var get = conn.CreateCommand())
                    {
                        get.CommandText = "SELECT ServerId FROM items WHERE id=@id";
                        get.Parameters.AddWithValue("@id", id);
                        var val = get.ExecuteScalar();
                        if (val != null && long.TryParse(val.ToString(), out var sid)) serverId = sid;
                    }
                    Services.SyncService.EnqueueSync("items", serverId > 0 ? serverId : id, "delete");

                    // ═══ VARIATION CHILDREN PROPAGATION ═══
                    // Variation parent delete → uske children bhi soft-delete + enqueue.
                    // Nahi to children server par Live orphaned reh jate hain
                    // (Amul Ice Cream - 100/200 Gram wali problem).
                    // parent_id link + code-prefix fallback (orphaned children
                    // jinke parent_id NULL reh gaya tha).
                    string parentCode = "";
                    using (var getCode = conn.CreateCommand())
                    {
                        getCode.CommandText = "SELECT code FROM items WHERE id=@id";
                        getCode.Parameters.AddWithValue("@id", id);
                        var v = getCode.ExecuteScalar();
                        if (v != null) parentCode = v.ToString() ?? "";
                    }
                    var children = new List<(long Id, long ServerId)>();
                    using (var childList = conn.CreateCommand())
                    {
                        childList.CommandText = @"SELECT id, ServerId FROM items
                                                  WHERE (del_status IS NULL OR del_status != 'Deleted')
                                                    AND (parent_id=@pid
                                                         OR (@pcode != '' AND type='0' AND code LIKE @prefix))";
                        childList.Parameters.AddWithValue("@pid", id);
                        childList.Parameters.AddWithValue("@pcode", parentCode);
                        childList.Parameters.AddWithValue("@prefix", parentCode + "-%");
                        using var cr = childList.ExecuteReader();
                        while (cr.Read())
                        {
                            long childServerId = 0;
                            if (!cr.IsDBNull(1)) long.TryParse(cr.GetValue(1).ToString(), out childServerId);
                            children.Add((cr.GetInt64(0), childServerId));
                        }
                    }
                    foreach (var ch in children)
                    {
                        using var up = conn.CreateCommand();
                        up.CommandText = "UPDATE items SET del_status='Deleted' WHERE id=@cid";
                        up.Parameters.AddWithValue("@cid", ch.Id);
                        up.ExecuteNonQuery();
                        Services.SyncService.EnqueueSync("items", ch.ServerId > 0 ? ch.ServerId : ch.Id, "delete");
                    }

                    _dashboard?.TriggerSync();
                    LoadItems();
                }
                catch (Exception ex)
                {
                    MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
                }
            }
        }
    }

    public class ItemListItem
    {
        public int Id { get; set; }
        public string SN { get; set; } = "";
        public string ItemName { get; set; } = "";
        public string Code { get; set; } = "";
        public string Category { get; set; } = "";
        public long CategoryId { get; set; }
        public long BrandId { get; set; }
        public long SupplierId { get; set; }
        public double StockQty { get; set; }
        public string StockQtyDisplay { get; set; } = "";
        public string PurchasePrice { get; set; } = "";
        public string SalePrice { get; set; } = "";
        public string MrpPrice { get; set; } = "";
        public string Status { get; set; } = "";
        public string StockStatus { get; set; } = "In";

        // ── Avatar (cloud-style colored circle with initials + glow) ──────
        // 8 vibrant colors — assigned by item id mod 8
        private static readonly (string Hex, string Glow)[] _palette =
        {
            ("#696CFF", "#696CFF"), // purple
            ("#71DD37", "#71DD37"), // green
            ("#FF3E1D", "#FF3E1D"), // red
            ("#03C3EC", "#03C3EC"), // cyan
            ("#FFAB00", "#FFAB00"), // amber
            ("#FF6B6B", "#FF6B6B"), // coral
            ("#26C6DA", "#26C6DA"), // teal
            ("#7E57C2", "#7E57C2"), // deep purple
        };

        public string AvatarInitials
        {
            get
            {
                if (string.IsNullOrWhiteSpace(ItemName)) return "?";
                var parts = ItemName.Trim().Split(' ', StringSplitOptions.RemoveEmptyEntries);
                if (parts.Length >= 2)
                    return $"{char.ToUpper(parts[0][0])}{char.ToUpper(parts[1][0])}";
                return char.ToUpper(ItemName[0]).ToString();
            }
        }

        public Brush AvatarBg
        {
            get
            {
                var (hex, _) = _palette[Math.Abs(Id) % _palette.Length];
                return new SolidColorBrush((Color)ColorConverter.ConvertFromString(hex));
            }
        }

        public Color AvatarGlowColor
        {
            get
            {
                var (_, glow) = _palette[Math.Abs(Id) % _palette.Length];
                return (Color)ColorConverter.ConvertFromString(glow);
            }
        }
        // ──────────────────────────────────────────────────────────────────

        public Brush StatusBg => Status == "Active"
            ? new SolidColorBrush((Color)ColorConverter.ConvertFromString("#E8FFD6"))
            : new SolidColorBrush((Color)ColorConverter.ConvertFromString("#F1F1F4"));

        public Brush StatusFg => Status == "Active"
            ? new SolidColorBrush((Color)ColorConverter.ConvertFromString("#4CAF50"))
            : new SolidColorBrush((Color)ColorConverter.ConvertFromString("#8E8EA1"));

        public string StockLabel => StockStatus switch
        {
            "Out" => "Out of Stock",
            "Low" => "Low Stock",
            _ => "In Stock"
        };

        public Brush StockBg => StockStatus switch
        {
            "Out" => new SolidColorBrush((Color)ColorConverter.ConvertFromString("#FEE2E2")),
            "Low" => new SolidColorBrush((Color)ColorConverter.ConvertFromString("#FFF3CD")),
            _ => new SolidColorBrush((Color)ColorConverter.ConvertFromString("#E8FFD6"))
        };

        public Brush StockFg => StockStatus switch
        {
            "Out" => new SolidColorBrush((Color)ColorConverter.ConvertFromString("#FF3E1D")),
            "Low" => new SolidColorBrush((Color)ColorConverter.ConvertFromString("#FFAB00")),
            _ => new SolidColorBrush((Color)ColorConverter.ConvertFromString("#4CAF50"))
        };
    }
}