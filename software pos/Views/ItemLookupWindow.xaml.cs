using System;
using System.Collections.Generic;
using System.Linq;
using System.Threading;
using System.Windows;
using System.Windows.Input;
using System.Windows.Threading;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class ItemLookupWindow : Window
    {
        private readonly DatabaseService _db = new();

        // ═══ DB-SIDE SEARCH: memory mein kuch load nahi, sirf current page ═══
        // _allItems aur _filteredItems hataye — ab sab kuch DB se aata hai
        private List<ItemLookupRow> _filteredItems = new();  // sirf current page
        private int _selectedIndex = -1;
        private readonly IReadOnlyDictionary<string, double> _cartQtys;
        private readonly string? _initialSearch;

        // Pagination
        private int _currentPage = 1;
        private int _perPage = 20;
        private int _totalPages = 1;
        private int _totalCount = 0;

        // ═══ DEBOUNCE: har keystroke par DB hit nahi, 200ms wait karo ═══
        private readonly DispatcherTimer _debounceTimer;
        private string _lastSearchQuery = "";
        private string _lastCategoryFilter = "All Groups";

        // Category list (sirf ek baar load hoti hai startup pe)
        private List<string> _categoryList = new();

        public ItemLookupRow? SelectedItem { get; private set; }
        public double SelectedQuantity { get; private set; } = 1;

        public ItemLookupWindow(IReadOnlyDictionary<string, double>? cartQtys = null)
        {
            InitializeComponent();
            _cartQtys = cartQtys ?? new Dictionary<string, double>();

            // Debounce timer: 200ms after last keystroke → DB query
            _debounceTimer = new DispatcherTimer { Interval = TimeSpan.FromMilliseconds(200) };
            _debounceTimer.Tick += (_, _) => { _debounceTimer.Stop(); RunSearchNow(); };

            Loaded += (_, _) => LoadInitialData();
        }

        public ItemLookupWindow(IReadOnlyDictionary<string, double>? cartQtys, string initialSearch)
            : this(cartQtys)
        {
            _initialSearch = initialSearch;
        }

        // ─── Startup: categories load karo + pehla page dikhao ───────────────────
        private void LoadInitialData()
        {
            LoadCategories();
            if (!string.IsNullOrEmpty(_initialSearch))
            {
                txtSearch.Text = _initialSearch;
                txtSearch.CaretIndex = txtSearch.Text.Length;
                // text set karte hi TextChanged fire hoga → debounce → search
            }
            else
            {
                RunSearchNow(); // empty search = all items, first page
            }
            txtSearch.Focus();
            Keyboard.Focus(txtSearch);
        }

        // ─── Categories: ek baar DB se, phir memory mein ─────────────────────────
        private void LoadCategories()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT DISTINCT c.Name FROM item_categories c
                    INNER JOIN items i ON i.category_id = c.Id
                    WHERE (i.del_status IS NULL OR i.del_status != 'Deleted')
                      AND (i.parent_id IS NULL OR i.parent_id = 0 OR i.parent_id = '')
                      AND IFNULL(i.type,'') != '0'
                    ORDER BY c.Name";
                using var r = cmd.ExecuteReader();
                _categoryList.Clear();
                cmbCategory.Items.Clear();
                cmbCategory.Items.Add("All Groups");
                while (r.Read())
                {
                    var cat = r.IsDBNull(0) ? "" : r.GetString(0);
                    if (!string.IsNullOrEmpty(cat))
                    {
                        _categoryList.Add(cat);
                        cmbCategory.Items.Add(cat);
                    }
                }
                cmbCategory.SelectedIndex = 0;
            }
            catch { }
        }

        // ─── MAIN SEARCH: DB-side, paginated — BACKGROUND THREAD (UI freeze fix) ──
        private int _searchSeq; // stale-result guard: purani search ka result discard
        private void RunSearchNow()
        {
            var search = txtSearch.Text.Trim();
            var catFilter = cmbCategory.SelectedItem?.ToString() ?? "All Groups";
            int seq = ++_searchSeq;
            int page = _currentPage;

            // DB query heavy hai (11k+ items) — UI thread block na ho, background me chalao
            Task.Run(() =>
            {
                try
                {
                    var rows = new List<ItemLookupRow>();
                    int totalCount = 0;

                    using (var conn = _db.GetConnection())
                    {
                        // ── COUNT query (pagination ke liye) ──────────────────────────
                        using (var countCmd = conn.CreateCommand())
                        {
                            countCmd.CommandText = BuildSearchQuery(search, catFilter, countOnly: true);
                            BindSearchParams(countCmd, search, catFilter);
                            totalCount = Convert.ToInt32(countCmd.ExecuteScalar() ?? 0);
                        }

                        // ── DATA query (sirf current page rows) ──────────────────────
                        using (var dataCmd = conn.CreateCommand())
                        {
                            dataCmd.CommandText = BuildSearchQuery(search, catFilter, countOnly: false);
                            BindSearchParams(dataCmd, search, catFilter);
                            dataCmd.Parameters.AddWithValue("@limit", _perPage);
                            dataCmd.Parameters.AddWithValue("@offset", (page - 1) * _perPage);

                            using var r = dataCmd.ExecuteReader();
                            while (r.Read())
                            {
                                var name = r["name"]?.ToString() ?? "";
                                var code = r["code"]?.ToString() ?? "";
                                var salePrice = r["sale_price"] != DBNull.Value ? Math.Round(Convert.ToDouble(r["sale_price"]), 2) : 0;
                                var mrp = r["mrp_price"] != DBNull.Value ? Math.Round(Convert.ToDouble(r["mrp_price"]), 2) : salePrice;
                                var stock = r["stock_quantity"] != DBNull.Value ? Convert.ToDouble(r["stock_quantity"]) : 0;
                                var tax = r["TaxRate"] != DBNull.Value ? Convert.ToDouble(r["TaxRate"]) : 0;
                                if (tax == 0) tax = ParseTaxString(r["tax_string"]?.ToString() ?? "");
                                var hsnForTax = r["hsn_code"]?.ToString() ?? "";
                                if (tax == 0 && !string.IsNullOrEmpty(hsnForTax))
                                {
                                    var hsnRate = Services.GstValidationService.GetGstRateForHsn(hsnForTax);
                                    if (hsnRate.HasValue) tax = hsnRate.Value;
                                }
                                var unitType = r["unit_type"]?.ToString() ?? "";
                                var conversion = r["conversion_rate"] != DBNull.Value ? Convert.ToDouble(r["conversion_rate"]) : 0;
                                var packageSize = DetectPackageSize(name, unitType, conversion);
                                var maxQty = packageSize > 0 ? Math.Floor(stock / packageSize) : stock;
                                var variantCount = r["variant_count"] != DBNull.Value ? Convert.ToInt32(r["variant_count"]) : 0;
                                var cat = r["CategoryName"]?.ToString() ?? "";
                                var unit = r["UnitName"]?.ToString() ?? "";

                                string displayName = variantCount > 0 ? $"{name} ({variantCount} variants)" : name;

                                rows.Add(new ItemLookupRow
                                {
                                    Name = displayName,
                                    Code = code,
                                    Category = cat,
                                    Unit = unit,
                                    Hsn = r["hsn_code"]?.ToString() ?? "",
                                    SalePrice = variantCount > 0 ? "Select →" : $"₹ {salePrice:N2}",
                                    Stock = variantCount > 0 ? "" : $"{stock:0.##}",
                                    RateValue = salePrice,
                                    MrpValue = mrp,
                                    StockValue = stock,
                                    TaxPerc = tax,
                                    PackageSize = packageSize,
                                    MaxQty = maxQty,
                                    IsVariantParent = variantCount > 0,
                                    OriginalName = name,
                                    Type = r["type"]?.ToString() ?? ""
                                });
                            }
                        }
                    }

                    // UI update — dispatcher par, sirf agar ye search abhi bhi latest hai
                    Dispatcher.Invoke(() =>
                    {
                        if (seq != _searchSeq) return; // beech me nayi search aa gayi — discard
                        _totalCount = totalCount;
                        _totalPages = Math.Max(1, (int)Math.Ceiling((double)totalCount / _perPage));
                        _filteredItems.Clear();
                        foreach (var row in rows) _filteredItems.Add(row);
                        UpdateDisplay();
                    });
                }
                catch (Exception ex)
                {
                    System.Diagnostics.Debug.WriteLine("RunSearchNow ERR: " + ex.Message);
                }
            });
        }

        // ─── SQL builder: count ya data query ────────────────────────────────────
        private static string BuildSearchQuery(string search, string catFilter, bool countOnly)
        {
            bool hasSearch = !string.IsNullOrEmpty(search);
            bool hasCat = catFilter != "All Groups";

            string select = countOnly
                ? "SELECT COUNT(*)"
                : @"SELECT i.id, i.name, i.code,
                    CASE WHEN IFNULL(i.sale_price,0) > 0 THEN i.sale_price
                         ELSE COALESCE(NULLIF(i.mrp_price,0), NULLIF(i.last_purchase_price,0), i.purchase_price, 0) END AS sale_price,
                    i.mrp_price, COALESCE(s.qty, IFNULL(i.stock_quantity,0)) AS stock_quantity, i.hsn_code,
                    i.unit_type, i.conversion_rate, i.tax_string, i.applicable_tax_id, i.parent_id, i.type,
                    c.Name as CategoryName, u.UnitName as UnitName, t.tax_rate as TaxRate,
                    (SELECT COUNT(*) FROM items ch
                        WHERE (ch.parent_id = i.id OR (ch.type='Variation_Product' AND ch.code LIKE i.code || '-%'))
                          AND (ch.del_status IS NULL OR ch.del_status != 'Deleted')) as variant_count";

            string from = @" FROM items i
                    LEFT JOIN (SELECT v.item_id, SUM(CASE WHEN v.type=1 THEN v.stock_quantity ELSE -v.stock_quantity END) AS qty
                               FROM view_stock_detail v WHERE v.del_status IS NULL OR v.del_status='Live' GROUP BY v.item_id) s
                           ON s.item_id = i.id
                    LEFT JOIN item_categories c ON i.category_id = c.Id
                    LEFT JOIN units u ON i.sale_unit_id = u.Id
                    LEFT JOIN taxs t ON i.applicable_tax_id = t.id";

            string where = @" WHERE (i.del_status IS NULL OR i.del_status != 'Deleted')
                      AND (i.parent_id IS NULL OR i.parent_id = 0 OR i.parent_id = '')
                      AND IFNULL(i.type,'') != '0'
                      AND COALESCE(s.qty, IFNULL(i.stock_quantity,0)) > 0";

            if (hasSearch)
                where += @" AND (i.name LIKE @q OR i.code LIKE @q OR i.hsn_code LIKE @q
                                  OR c.Name LIKE @q)";
            if (hasCat)
                where += " AND c.Name = @cat";

            string order = countOnly ? "" : " ORDER BY i.name LIMIT @limit OFFSET @offset";

            return select + from + where + order;
        }

        private static void BindSearchParams(SqliteCommand cmd, string search, string catFilter)
        {
            if (!string.IsNullOrEmpty(search))
                cmd.Parameters.AddWithValue("@q", "%" + search + "%");
            if (catFilter != "All Groups")
                cmd.Parameters.AddWithValue("@cat", catFilter);
        }

        // ─── UpdateDisplay: sirf current page items dikhao ───────────────────────
        private void UpdateDisplay()
        {
            dgItems.ItemsSource = _filteredItems; // already paginated from DB

            // Header labels
            lblItemCount.Text = $"{_totalCount} Items";
            if (_selectedIndex >= 0 && _selectedIndex < _filteredItems.Count)
            {
                var sel = _filteredItems[_selectedIndex];
                lblSelectedItem.Text = $"Selected: {sel.Name} ({sel.Code})";
            }
            else
            {
                lblSelectedItem.Text = "Selected: —";
            }

            // Pagination labels
            int startIdx = (_currentPage - 1) * _perPage;
            int endIdx = Math.Min(startIdx + _filteredItems.Count, _totalCount);
            if (_totalCount > 0)
                lblShowing.Text = $"Showing {startIdx + 1} to {startIdx + _filteredItems.Count} of {_totalCount} items";
            else
                lblShowing.Text = "No items found";

            lblPageNum.Text = _currentPage.ToString();
            UpdatePlaceholder();
        }

        private void UpdatePlaceholder()
        {
            if (searchPlaceholder != null)
                searchPlaceholder.Visibility = string.IsNullOrEmpty(txtSearch.Text)
                    ? Visibility.Visible : Visibility.Collapsed;
        }

        // ═══════ EVENT HANDLERS ═══════

        // ─── Debounced search: 200ms wait after last keystroke ────────────────────
        private void TxtSearch_TextChanged(object sender, System.Windows.Controls.TextChangedEventArgs e)
        {
            _currentPage = 1;
            _debounceTimer.Stop();
            _debounceTimer.Start();
        }

        private void CmbCategory_SelectionChanged(object sender, System.Windows.Controls.SelectionChangedEventArgs e)
        {
            _currentPage = 1;
            RunSearchNow();
        }

        private void CmbPerPage_SelectionChanged(object sender, System.Windows.Controls.SelectionChangedEventArgs e)
        {
            _perPage = cmbPerPage.SelectedIndex switch
            {
                0 => 10,
                1 => 20,
                2 => 50,
                _ => 20
            };
            _currentPage = 1;
            RunSearchNow();
        }

        private void PageFirst_Click(object sender, MouseButtonEventArgs e) { _currentPage = 1; RunSearchNow(); }
        private void PagePrev_Click(object sender, MouseButtonEventArgs e) { if (_currentPage > 1) { _currentPage--; RunSearchNow(); } }
        private void PageNext_Click(object sender, MouseButtonEventArgs e) { if (_currentPage < _totalPages) { _currentPage++; RunSearchNow(); } }
        private void PageLast_Click(object sender, MouseButtonEventArgs e) { _currentPage = _totalPages; RunSearchNow(); }

        private void DgItems_SelectionChanged(object sender, System.Windows.Controls.SelectionChangedEventArgs e)
        {
            if (dgItems.SelectedItem is ItemLookupRow item)
            {
                // Find in full filtered list
                _selectedIndex = _filteredItems.IndexOf(item);
                UpdateDisplay();
            }
        }

        private void OpenQuantityPopup()
        {
            // Get selected item from current page
            var item = dgItems.SelectedItem as ItemLookupRow;
            if (item == null)
            {
                if (_selectedIndex >= 0 && _selectedIndex < _filteredItems.Count)
                    item = _filteredItems[_selectedIndex];
            }
            if (item == null) return;

            // ═══ VARIANT PARENT: Open variant selection popup ═══
            if (item.IsVariantParent)
            {
                var variants = LoadVariantChildren(item.Code, item.OriginalName);
                if (variants != null && variants.Count > 0)
                {
                    var popup = new VariantSelectionWindow(item.OriginalName, variants) { Owner = this };
                    if (popup.ShowDialog() == true && popup.SelectedVariant != null)
                    {
                        SelectedItem = popup.SelectedVariant;
                        SelectedQuantity = 1;
                        DialogResult = true;
                        Close();
                    }
                }
                return;
            }

            double inCart = 0;
            if (_cartQtys.TryGetValue(item.Code.ToLower(), out var cq)) inCart = cq;

            var qtyWin = new QuantityWindow(item.Name, item.Code, item.RateValue, item.Unit, item.StockValue, item.MaxQty, item.PackageSize, inCart)
            {
                Owner = this
            };

            if (qtyWin.ShowDialog() == true)
            {
                SelectedItem = item;
                SelectedQuantity = qtyWin.Quantity;
                DialogResult = true;
                Close();
            }
            else
            {
                txtSearch.Focus();
                Keyboard.Focus(txtSearch);
            }
        }

        private void Window_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            switch (e.Key)
            {
                case Key.Escape:
                    DialogResult = false;
                    Close();
                    e.Handled = true;
                    break;

                case Key.Down:
                    NavigateSelection(1);
                    e.Handled = true;
                    break;

                case Key.Up:
                    NavigateSelection(-1);
                    e.Handled = true;
                    break;

                case Key.Right:
                    if (_currentPage < _totalPages) { _currentPage++; RunSearchNow(); }
                    e.Handled = true;
                    break;

                case Key.Left:
                    if (_currentPage > 1) { _currentPage--; RunSearchNow(); }
                    e.Handled = true;
                    break;

                case Key.Enter:
                    OpenQuantityPopup();
                    e.Handled = true;
                    break;

                case Key.F3:
                    txtSearch.Focus();
                    txtSearch.SelectAll();
                    e.Handled = true;
                    break;

                case Key.Tab:
                    e.Handled = true;
                    break;
            }
        }

        private void NavigateSelection(int direction)
        {
            if (_filteredItems.Count == 0) return;

            int pageIdx = _filteredItems.IndexOf(dgItems.SelectedItem as ItemLookupRow);
            int newPageIdx = pageIdx + direction;

            if (newPageIdx < 0)
            {
                if (_currentPage > 1)
                {
                    _currentPage--;
                    RunSearchNow();
                    if (dgItems.Items.Count > 0)
                    {
                        dgItems.SelectedIndex = dgItems.Items.Count - 1;
                        dgItems.ScrollIntoView(dgItems.SelectedItem);
                    }
                }
            }
            else if (newPageIdx >= _filteredItems.Count)
            {
                if (_currentPage < _totalPages)
                {
                    _currentPage++;
                    RunSearchNow();
                    if (dgItems.Items.Count > 0)
                    {
                        dgItems.SelectedIndex = 0;
                        dgItems.ScrollIntoView(dgItems.SelectedItem);
                    }
                }
            }
            else
            {
                dgItems.SelectedIndex = newPageIdx;
                dgItems.ScrollIntoView(dgItems.SelectedItem);
            }

            if (dgItems.SelectedItem is ItemLookupRow sel)
                _selectedIndex = _filteredItems.IndexOf(sel);

            UpdateDisplay();
        }

        private void DgItems_MouseDoubleClick(object sender, MouseButtonEventArgs e)
        {
            OpenQuantityPopup();
        }

        private void BtnSelect_Click(object sender, RoutedEventArgs e)
        {
            OpenQuantityPopup();
        }

        private void BtnClose_Click(object sender, MouseButtonEventArgs e)
        {
            DialogResult = false;
            Close();
        }

        private static double DetectPackageSize(string name, string unitType, double conversion)
        {
            if (unitType == "2" && conversion > 0) return conversion;
            return 1;
        }

        private static double ParseTaxString(string taxString)
        {
            if (string.IsNullOrWhiteSpace(taxString)) return 0;
            var match = System.Text.RegularExpressions.Regex.Match(taxString, @"(\d+(?:\.\d+)?)\s*%");
            return match.Success && double.TryParse(match.Groups[1].Value, System.Globalization.NumberStyles.Any, System.Globalization.CultureInfo.InvariantCulture, out var val) ? val : 0;
        }

        private List<VariantDisplayItem>? LoadVariantChildren(string parentCode, string parentName)
        {
            try
            {
                using var conn = _db.GetConnection();
                // Find parent id by code
                using var pidCmd = conn.CreateCommand();
                pidCmd.CommandText = "SELECT id FROM items WHERE code=@c AND (del_status IS NULL OR del_status!='Deleted') LIMIT 1";
                pidCmd.Parameters.AddWithValue("@c", parentCode);
                var pidVal = pidCmd.ExecuteScalar();
                if (pidVal == null) return null;
                long parentId = Convert.ToInt64(pidVal);

                // Find children: by parent_id OR by code prefix (PARENTCODE-%) with Variation_Product type
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT i.name, i.code,
                    CASE WHEN IFNULL(i.sale_price,0) > 0 THEN i.sale_price
                         ELSE COALESCE(NULLIF(i.mrp_price,0), NULLIF(i.last_purchase_price,0), i.purchase_price, 0) END AS sale_price,
                    i.mrp_price, COALESCE(s.qty, IFNULL(i.stock_quantity,0)) AS stock_quantity,
                    i.hsn_code, i.unit_type, i.conversion_rate, i.tax_string,
                    c.Name as CategoryName, u.UnitName
                    FROM items i
                    LEFT JOIN (SELECT v.item_id, SUM(CASE WHEN v.type=1 THEN v.stock_quantity ELSE -v.stock_quantity END) AS qty
                               FROM view_stock_detail v WHERE v.del_status IS NULL OR v.del_status='Live' GROUP BY v.item_id) s
                           ON s.item_id = i.id
                    LEFT JOIN item_categories c ON i.category_id = c.Id
                    LEFT JOIN units u ON i.sale_unit_id = u.Id
                    WHERE (i.del_status IS NULL OR i.del_status != 'Deleted')
                      AND (i.parent_id = @pid
                           OR (i.type='Variation_Product' AND i.code LIKE @codeprefix)
                           OR (i.type='0' AND i.parent_id = @pid))
                      AND COALESCE(s.qty, IFNULL(i.stock_quantity,0)) > 0
                    ORDER BY i.sale_price";
                cmd.Parameters.AddWithValue("@pid", parentId);
                cmd.Parameters.AddWithValue("@codeprefix", parentCode + "-%");
                using var r = cmd.ExecuteReader();

                var variants = new List<VariantDisplayItem>();
                while (r.Read())
                {
                    var sp = r["sale_price"] != DBNull.Value ? Math.Round(Convert.ToDouble(r["sale_price"]), 2) : 0;
                    var mrp = r["mrp_price"] != DBNull.Value ? Math.Round(Convert.ToDouble(r["mrp_price"]), 2) : sp;
                    var stk = r["stock_quantity"] != DBNull.Value ? Convert.ToDouble(r["stock_quantity"]) : 0;
                    var tax = ParseTaxString(r["tax_string"]?.ToString() ?? "");
                    var ut = r["unit_type"]?.ToString() ?? "";
                    var conv = r["conversion_rate"] != DBNull.Value ? Convert.ToDouble(r["conversion_rate"]) : 0;
                    var ps = DetectPackageSize(r["name"]?.ToString() ?? "", ut, conv);

                    variants.Add(new VariantDisplayItem
                    {
                        Name = r["name"]?.ToString() ?? "",
                        Code = r["code"]?.ToString() ?? "",
                        PriceDisplay = $"₹{sp:N2}",
                        MrpDisplay = mrp > sp ? $"₹{mrp:N2}" : "",
                        StockDisplay = $"Stock: {stk:0.##}",
                        ParentName = parentName,
                        LookupRow = new ItemLookupRow
                        {
                            Name = parentName + " - " + (r["name"]?.ToString() ?? ""),
                            Code = r["code"]?.ToString() ?? "",
                            Category = r["CategoryName"]?.ToString() ?? "",
                            Unit = r["UnitName"]?.ToString() ?? "",
                            Hsn = r["hsn_code"]?.ToString() ?? "",
                            SalePrice = $"₹ {sp:N2}",
                            Stock = $"{stk:0.##}",
                            RateValue = sp, MrpValue = mrp, StockValue = stk,
                            TaxPerc = tax, PackageSize = ps,
                            MaxQty = ps > 0 ? Math.Floor(stk / ps) : stk
                        }
                    });
                }
                return variants.Count > 0 ? variants : null;
            }
            catch { return null; }
        }
    }

    public class ItemLookupRow
    {
        public string Name { get; set; } = "";
        public string Code { get; set; } = "";
        public string Category { get; set; } = "";
        public string Unit { get; set; } = "";
        public string Hsn { get; set; } = "";
        public string SalePrice { get; set; } = "";
        public string Stock { get; set; } = "";
        public double RateValue { get; set; }
        public double MrpValue { get; set; }
        public double StockValue { get; set; }
        public double TaxPerc { get; set; }
        public double PackageSize { get; set; } = 1;
        public double MaxQty { get; set; }
        public bool IsVariantParent { get; set; }
        public string OriginalName { get; set; } = "";
        public string Type { get; set; } = "";
    }
}
