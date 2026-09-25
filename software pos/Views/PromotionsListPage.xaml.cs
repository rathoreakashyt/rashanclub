using System;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public class PromotionRow
    {
        public int Sn { get; set; }
        public long Id { get; set; }
        public string Title { get; set; } = "";
        public string TypeName { get; set; } = "";
        public string StartDate { get; set; } = "";
        public string EndDate { get; set; } = "";
        public string Status { get; set; } = "";
    }

    public partial class PromotionsListPage : UserControl, ISyncRefreshable
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();
        private List<PromotionRow> _allRows = new();
        private long _editId;

        // (code, display) pairs
        private static readonly (string Code, string Name)[] Types =
        {
            ("1", "Discount"),
            ("2", "Coupon Discount"),
            ("3", "Free Item (Buy X Get Y)"),
        };
        private static readonly (string Code, string Name)[] Statuses =
        {
            ("1", "Active"),
            ("2", "Inactive"),
        };
private static readonly (string Code, string Name)[] SchemeBases =
        {
            ("item", "Item Wise"),
            ("bill", "Bill Level"),
            ("party", "Party Wise"),
        };

        // items loaded from DB for dropdowns
        private List<(long Id, string Name)> _items = new();

        public PromotionsListPage() { InitializeComponent(); InitCombos(); }
        public PromotionsListPage(MainDashboard dashboard) : this()
        {
            _dashboard = dashboard;
            LoadItems();
            LoadData();
        }

        // ── Load items from DB ────────────────────────────────────────────
        private void LoadItems()
        {
            _items.Clear();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"
                    SELECT id, name, code FROM items
                    WHERE (del_status IS NULL OR del_status='Live')
                      AND (enable_disable_status IS NULL OR enable_disable_status=1)
                    ORDER BY name ASC";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    long id = r["id"] is long lid ? lid : Convert.ToInt64(r["id"]);
                    string name = r["name"]?.ToString() ?? "";
                    string code = r["code"]?.ToString() ?? "";
                    string display = string.IsNullOrWhiteSpace(code) ? name : $"{name} ({code})";
                    _items.Add((id, display));
                }
            }
            catch { }
        }

        // ── Init all combos ───────────────────────────────────────────────
        private void InitCombos()
        {
            // Time slots — 30 min ke options select karne ke liye (editable: type bhi kar sakte ho)
            cmbStartTime.Items.Clear();
            cmbEndTime.Items.Clear();
            for (int h = 0; h < 24; h++)
            {
                cmbStartTime.Items.Add($"{h:00}:00");
                cmbStartTime.Items.Add($"{h:00}:30");
                cmbEndTime.Items.Add($"{h:00}:00");
                cmbEndTime.Items.Add($"{h:00}:30");
            }
            cmbType.Items.Clear();
            foreach (var t in Types) cmbType.Items.Add(t.Name);
            cmbType.SelectedIndex = 0;

            cmbStatus.Items.Clear();
            foreach (var s in Statuses) cmbStatus.Items.Add(s.Name);
            cmbStatus.SelectedIndex = 0;

cmbSchemeBasis.Items.Clear();
            foreach (var sb in SchemeBases) cmbSchemeBasis.Items.Add(sb.Name);
            cmbSchemeBasis.SelectedIndex = 0;
        }

        private void PopulateItemDropdowns()
        {
            cmbItemDiscount.Items.Clear();
            cmbBuyItem.Items.Clear();
            cmbGetItem.Items.Clear();

            cmbItemDiscount.Items.Add("-- Select Item --");
            cmbBuyItem.Items.Add("-- Select Item --");
            cmbGetItem.Items.Add("-- Select Item --");

            foreach (var item in _items)
            {
                cmbItemDiscount.Items.Add(item.Name);
                cmbBuyItem.Items.Add(item.Name);
                cmbGetItem.Items.Add(item.Name);
            }
            cmbItemDiscount.SelectedIndex = 0;
            cmbBuyItem.SelectedIndex = 0;
            cmbGetItem.SelectedIndex = 0;
        }

        // ── Load list data ────────────────────────────────────────────────
        // Cloud/Web se change aaya (sync pull) → page khud reload — bina refresh ke
        public void OnSyncPulled()
        {
            Dispatcher.BeginInvoke(new Action(() =>
            {
                if (IsLoaded) LoadData();
            }), System.Windows.Threading.DispatcherPriority.Background);
        }

        private void LoadData()
        {
            _allRows.Clear();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT id, title, type, status, start_date, end_date
                    FROM promotions WHERE del_status IS NULL OR del_status != 'Deleted'
                    ORDER BY id DESC";
                using var r = cmd.ExecuteReader();
                int sn = 1;
                while (r.Read())
                {
                    string rawType = r["type"]?.ToString() ?? "";
                    string typeName = Array.Find(Types, t => t.Code == rawType).Name;
                    if (string.IsNullOrEmpty(typeName)) typeName = rawType;

                    string rawStatus = r["status"]?.ToString() ?? "";
                    // cloud stores "1"=Active, "2"=Inactive
                    string statusName = rawStatus == "1" ? "Active" : rawStatus == "2" ? "Inactive" : rawStatus;

                    _allRows.Add(new PromotionRow
                    {
                        Sn = sn++,
                        Id = r["id"] is long id ? id : Convert.ToInt64(r["id"]),
                        Title = r["title"]?.ToString() ?? "",
                        TypeName = typeName,
                        StartDate = r["start_date"]?.ToString() ?? "",
                        EndDate = r["end_date"]?.ToString() ?? "",
                        Status = statusName
                    });
                }
            }
            catch { }
            ApplyFilter();
            if (lblCount != null) lblCount.Text = _allRows.Count.ToString();
        }

        private void ApplyFilter()
        {
            string q = txtSearch.Text.Trim().ToLowerInvariant();
            var filtered = q == "" ? _allRows : _allRows.FindAll(x =>
                x.Title.ToLowerInvariant().Contains(q) ||
                x.TypeName.ToLowerInvariant().Contains(q));
            itemsList.ItemsSource = filtered.ToList();
            emptyState.Visibility = filtered.Count == 0 ? Visibility.Visible : Visibility.Collapsed;
            itemsList.Visibility = filtered.Count == 0 ? Visibility.Collapsed : Visibility.Visible;
        }

        private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (txtSearch != null) ApplyFilter();
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e) => _dashboard?.ShowDashboard();


// ── Type panel show/hide ──────────────────────────────────────────
        private void CmbType_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            ShowTypePanel(cmbType.SelectedIndex);
        }

        private void ShowTypePanel(int typeIdx)
        {
            if (panelType1 == null) return;
            panelType1.Visibility = typeIdx == 0 ? Visibility.Visible : Visibility.Collapsed;
            panelType2.Visibility = typeIdx == 1 ? Visibility.Visible : Visibility.Collapsed;
            panelType3.Visibility = typeIdx == 2 ? Visibility.Visible : Visibility.Collapsed;

            // web: when type 3 is selected, auto-generate tier rows from buy/get qty
            if (typeIdx == 2 && tierRowsPanel.Children.Count == 0)
                AutoGenerateTiers();
        }

        // web-style auto tier rows: partial qty 1..buyQty-1, suggested percent = buy/(buy+get)
        private void AutoGenerateTiers()
        {
            if (!int.TryParse(txtBuyQty.Text.Trim(), out int buy) || buy <= 1) return;
            if (!int.TryParse(txtGetQty.Text.Trim(), out int get) || get <= 0) return;
            int suggested = (int)Math.Round((double)buy / (buy + get) * 100);
            for (int q = 1; q < buy; q++)
                AddTierRow(q.ToString(), "", suggested.ToString());
        }

        // web: .scheme-bill section visible only when scheme_basis=bill
        private void CmbSchemeBasis_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (panelBillDiscount1 == null) return;
            string code = SchemeBases[cmbSchemeBasis.SelectedIndex >= 0 ? cmbSchemeBasis.SelectedIndex : 0].Code;
            panelBillDiscount1.Visibility = code == "bill" ? Visibility.Visible : Visibility.Collapsed;
        }

        // ── ClearForm ────────────────────────────────────────────────────
        private void ClearForm()
        {
            cmbType.SelectedIndex = 0;
            cmbType.IsEnabled = true;
            txtTitle.Text = "";
            dpStartDate.SelectedDate = null;
            dpEndDate.SelectedDate = null;
            cmbSchemeBasis.SelectedIndex = 0;
            cmbStartTime.Text = "--:--";
            cmbEndTime.Text = "--:--";
            txtMinPurchase.Text = "0";
            txtMaxDiscount.Text = "0";
            cmbStatus.SelectedIndex = 0;

            // Type 1
            cmbItemDiscount.SelectedIndex = 0;
            txtDiscount1.Text = "";
            txtBillDiscount1.Text = "0";
            panelBillDiscount1.Visibility = Visibility.Collapsed;

            // Type 2
            txtDiscount2.Text = "";
            txtCouponCode.Text = "";
            chkCustRetail.IsChecked = true;
            chkCustWholesale.IsChecked = true;

            // Type 3
            cmbBuyItem.SelectedIndex = 0;
            txtBuyQty.Text = "";
            cmbGetItem.SelectedIndex = 0;
            txtGetQty.Text = "";
            tierRowsPanel.Children.Clear();

            ShowTypePanel(0);
        }

        // ── Navigation buttons ───────────────────────────────────────────
        private void BtnAdd_Click(object sender, RoutedEventArgs e)
        {
            OpenAddForm();
        }

        // Dashboard ke "Add Promotion" menu se bhi wahi horizontal form khule
        // (pehle generic vertical Crud page khulta tha — ab yahi rich form)
        public void OpenAddForm()
        {
            _editId = 0;
            PopulateItemDropdowns();
            ClearForm();
            lblFormTitle.Text = "Add Promotion";
            lblBreadcrumbPage.Text = "Add Promotion";
            listPanel.Visibility = Visibility.Collapsed;
            formPanel.Visibility = Visibility.Visible;
        }

        private void BtnEdit_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is not long id || id <= 0) return;
            _editId = id;
            PopulateItemDropdowns();
            lblFormTitle.Text = "Edit Promotion";
            lblBreadcrumbPage.Text = "Edit Promotion";
            LoadIntoForm(id);
            listPanel.Visibility = Visibility.Collapsed;
            formPanel.Visibility = Visibility.Visible;
        }

        private void BtnFormCancel_Click(object sender, RoutedEventArgs e)
        {
            formPanel.Visibility = Visibility.Collapsed;
            listPanel.Visibility = Visibility.Visible;
        }

        private void BtnDelete_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is not long id) return;
            if (MessageBox.Show("Delete this promotion?", "Confirm",
                MessageBoxButton.YesNo, MessageBoxImage.Question) != MessageBoxResult.Yes) return;
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "UPDATE promotions SET del_status='Deleted' WHERE id=@id";
                cmd.Parameters.AddWithValue("@id", id);
                cmd.ExecuteNonQuery();
Services.SyncService.EnqueueSync("promotions", id, "delete");
                _dashboard?.TriggerSync();
                LoadData();
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        // ── LoadIntoForm ─────────────────────────────────────────────────
        private void LoadIntoForm(long id)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT * FROM promotions WHERE id=@id";
                cmd.Parameters.AddWithValue("@id", id);
                using var r = cmd.ExecuteReader();
                if (!r.Read()) return;

                string rawType = r["type"]?.ToString() ?? "1";
                int typeIdx = Array.FindIndex(Types, t => t.Code == rawType);
                cmbType.SelectedIndex = typeIdx >= 0 ? typeIdx : 0;
                cmbType.IsEnabled = false; // web: type not editable in edit mode
                ShowTypePanel(cmbType.SelectedIndex);

                txtTitle.Text = r["title"]?.ToString() ?? "";

                if (DateTime.TryParse(r["start_date"]?.ToString(), out var sd)) dpStartDate.SelectedDate = sd;
                if (DateTime.TryParse(r["end_date"]?.ToString(), out var ed)) dpEndDate.SelectedDate = ed;

                cmbStartTime.Text = r["start_time"]?.ToString() is string st && st.Length > 0 ? st : "--:--";
                cmbEndTime.Text   = r["end_time"]?.ToString()   is string et && et.Length > 0 ? et : "--:--";

                string rawScheme = r["scheme_basis"]?.ToString() ?? "item";
                int schemeIdx = Array.FindIndex(SchemeBases, s => s.Code == rawScheme);
                cmbSchemeBasis.SelectedIndex = schemeIdx >= 0 ? schemeIdx : 0;

                double.TryParse(r["min_purchase_amount"]?.ToString(), out var minP);
                txtMinPurchase.Text = minP.ToString("N2");

                double.TryParse(r["max_discount_amount"]?.ToString(), out var maxD);
                txtMaxDiscount.Text = maxD.ToString("N2");

                // Status: cloud "1"=Active, "2"=Inactive
                string rawStatus = r["status"]?.ToString() ?? "1";
                int statusIdx = Array.FindIndex(Statuses, s => s.Code == rawStatus);
                cmbStatus.SelectedIndex = statusIdx >= 0 ? statusIdx : 0;

                // Applicable customer types (cloud JSON array ["1","2"])
                string custJson = r["applicable_customer_types"]?.ToString() ?? "";
                chkCustRetail.IsChecked = false;
                chkCustWholesale.IsChecked = false;
                if (!string.IsNullOrWhiteSpace(custJson))
                {
                    try
                    {
                        var custList = JsonSerializer.Deserialize<List<string>>(custJson);
                        if (custList != null)
                        {
                            chkCustRetail.IsChecked = custList.Contains("1");
                            chkCustWholesale.IsChecked = custList.Contains("2");
                        }
                    }
                    catch { }
                }
                if (chkCustRetail.IsChecked == false && chkCustWholesale.IsChecked == false)
                {
                    chkCustRetail.IsChecked = true;
                    chkCustWholesale.IsChecked = true;
                }

                // Bill level section shows when scheme_basis=bill (web: .scheme-bill)
                panelBillDiscount1.Visibility = rawScheme == "bill" ? Visibility.Visible : Visibility.Collapsed;

                // ── Type-specific fields ──
                if (rawType == "1")
                {
                    SetItemCombo(cmbItemDiscount, r["item_id"]);
                    txtDiscount1.Text = r["discount"]?.ToString() ?? "";

                    double.TryParse(r["bill_level_discount"]?.ToString(), out var bld);
                    txtBillDiscount1.Text = bld.ToString("N2");
                }
                else if (rawType == "2")
                {
                    txtDiscount2.Text  = r["discount"]?.ToString()    ?? "";
                    txtCouponCode.Text = r["coupon_code"]?.ToString() ?? "";
                }
                else if (rawType == "3")
                {
                    SetItemCombo(cmbBuyItem, r["item_id"]);
                    txtBuyQty.Text = r["qty"]?.ToString() ?? "";
                    SetItemCombo(cmbGetItem, r["get_item_id"]);
                    txtGetQty.Text = r["get_qty"]?.ToString() ?? "";

                    // Tier percentages
                    tierRowsPanel.Children.Clear();
                    string tierJson = r["tier_percentages"]?.ToString() ?? "";
                    if (!string.IsNullOrWhiteSpace(tierJson))
                    {
                        try
                        {
                            var tiers = JsonSerializer.Deserialize<List<JsonElement>>(tierJson);
                            if (tiers != null)
                                foreach (var tier in tiers)
                                    AddTierRow(
                                        tier.TryGetProperty("qty", out var q) ? q.ToString() : "",
                                        tier.TryGetProperty("custom_price", out var cp) ? cp.ToString() : "",
                                        tier.TryGetProperty("percent", out var pct) ? pct.ToString() : "");
                        }
                        catch { }
                    }
                }
            }
            catch { }
        }

        // helper: select item in combo by DB id value
        private void SetItemCombo(ComboBox cmb, object? rawId)
        {
            if (rawId == null || rawId == DBNull.Value) { cmb.SelectedIndex = 0; return; }
            if (!long.TryParse(rawId.ToString(), out long itemId)) { cmb.SelectedIndex = 0; return; }
            int idx = _items.FindIndex(x => x.Id == itemId);
            cmb.SelectedIndex = idx >= 0 ? idx + 1 : 0; // +1 because index 0 = "-- Select Item --"
        }

        // helper: get item id from combo selection
        private string GetItemId(ComboBox cmb)
        {
            int sel = cmb.SelectedIndex - 1; // -1 offset
            return sel >= 0 && sel < _items.Count ? _items[sel].Id.ToString() : "";
        }


        // ── Tier row UI helpers ───────────────────────────────────────────
        private void BtnAddTier_Click(object sender, RoutedEventArgs e) => AddTierRow("", "", "");

        private void AddTierRow(string qty, string customPrice, string percent)
        {
            var row = new Grid { Margin = new Thickness(0, 0, 0, 8) };
            row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(70) });
            row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(120) });
            row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(32) });

            var lblQty = new TextBlock { Text = "Qty", VerticalAlignment = VerticalAlignment.Center,
                FontSize = 12.5, FontWeight = FontWeights.SemiBold, Foreground = new SolidColorBrush(Color.FromRgb(0x47,0x55,0x69)),
                FontFamily = new System.Windows.Media.FontFamily("Inter, Segoe UI"), Margin = new Thickness(0,0,8,0) };

            var tbQty = new TextBox { Text = qty, Height = 38, FontSize = 13.5, Padding = new Thickness(10,0,10,0),
                VerticalContentAlignment = VerticalAlignment.Center,
                BorderBrush = new SolidColorBrush(Color.FromRgb(0xE2,0xE8,0xF0)), BorderThickness = new Thickness(1),
                Background = Brushes.White,
                Margin = new Thickness(0,0,8,0), Tag = "qty" };

            // Type selector: % or fixed amount
            var cmbTierType = new ComboBox { Height = 38, FontSize = 12.5,
                VerticalContentAlignment = VerticalAlignment.Center,
                BorderBrush = new SolidColorBrush(Color.FromRgb(0xE2,0xE8,0xF0)), BorderThickness = new Thickness(1),
                Background = Brushes.White,
                Margin = new Thickness(0,0,8,0), Tag = "type" };
            cmbTierType.Items.Add("% (Percent of price)");
            cmbTierType.Items.Add("₹ (Fixed amount)");
            cmbTierType.SelectedIndex = string.IsNullOrEmpty(customPrice) ? 0 : 1;

            var tbVal = new TextBox
            {
                Text = string.IsNullOrEmpty(customPrice) ? percent : customPrice,
                Height = 38, FontSize = 13.5, Padding = new Thickness(10,0,10,0),
                VerticalContentAlignment = VerticalAlignment.Center,
                BorderBrush = new SolidColorBrush(Color.FromRgb(0xE2,0xE8,0xF0)), BorderThickness = new Thickness(1),
                Background = Brushes.White,
                Margin = new Thickness(0,0,8,0), Tag = "val"
            };

            var btnDel = new Button
            {
                Content = "✕", FontSize = 13, Width = 30, Height = 30,
                Background = new SolidColorBrush(Color.FromRgb(0xFE,0xF2,0xF2)),
                Foreground = new SolidColorBrush(Color.FromRgb(0xEF,0x44,0x44)),
                BorderBrush = new SolidColorBrush(Color.FromRgb(0xFE,0xCA,0xCA)), BorderThickness = new Thickness(1),
                Cursor = System.Windows.Input.Cursors.Hand,
                VerticalAlignment = VerticalAlignment.Center, Tag = row
            };
            btnDel.Click += (s, _) => { if (s is Button b && b.Tag is Grid g) tierRowsPanel.Children.Remove(g); };

            // Combine label+qty into a sub-panel for column 0
            var col0 = new StackPanel { Orientation = Orientation.Horizontal };
            col0.Children.Add(lblQty);
            col0.Children.Add(tbQty);

            Grid.SetColumn(col0, 0);
            Grid.SetColumn(cmbTierType, 1);
            Grid.SetColumn(tbVal, 2);
            Grid.SetColumn(btnDel, 3);
            row.Children.Add(col0);
            row.Children.Add(cmbTierType);
            row.Children.Add(tbVal);
            row.Children.Add(btnDel);

            tierRowsPanel.Children.Add(row);
        }

        // Build tier_percentages JSON from UI rows
        private string BuildTierJson()
        {
            var list = new List<object>();
            foreach (var child in tierRowsPanel.Children)
            {
                if (child is not Grid row) continue;
                string qtyStr = "", val = ""; int typeIdx = 0;
                foreach (var c in row.Children)
                {
                    if (c is StackPanel sp)
                        foreach (var sc in sp.Children)
                            if (sc is TextBox tb && tb.Tag?.ToString() == "qty") qtyStr = tb.Text.Trim();
                    if (c is ComboBox cb && cb.Tag?.ToString() == "type") typeIdx = cb.SelectedIndex;
                    if (c is TextBox tbv && tbv.Tag?.ToString() == "val") val = tbv.Text.Trim();
                }
                if (!int.TryParse(qtyStr, out int qty) || qty <= 0) continue;
                if (!double.TryParse(val, out double numVal) || numVal < 0) continue;
                if (typeIdx == 1) list.Add(new { qty, custom_price = numVal });
                else              list.Add(new { qty, percent = numVal });
            }
            return list.Count > 0 ? JsonSerializer.Serialize(list) : "";
        }

        // ── BtnFormSave_Click ─────────────────────────────────────────────
        private void BtnFormSave_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(txtTitle.Text))
            { MessageBox.Show("Title required.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning); return; }
            if (dpStartDate.SelectedDate == null || dpEndDate.SelectedDate == null)
            { MessageBox.Show("Start and End dates required.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning); return; }

            int typeIdx    = cmbType.SelectedIndex >= 0 ? cmbType.SelectedIndex : 0;
            string typeCode = Types[typeIdx].Code;
            string schemeCode = SchemeBases[cmbSchemeBasis.SelectedIndex >= 0 ? cmbSchemeBasis.SelectedIndex : 0].Code;
            string statusCode = Statuses[cmbStatus.SelectedIndex >= 0 ? cmbStatus.SelectedIndex : 0].Code;
            string startTime = cmbStartTime.Text.Trim() == "--:--" ? "" : cmbStartTime.Text.Trim();
            string endTime   = cmbEndTime.Text.Trim()   == "--:--" ? "" : cmbEndTime.Text.Trim();
            double.TryParse(txtMinPurchase.Text, out double minPurchase);

            // Type-specific validation + values
            string itemId = "", discount = "", couponCode = "",
                   qty = "", getItemId = "", getQty = "",
                   billDiscount = "", billDiscType = "percentage",
                   maxDiscount = "", tierJson = "",
                   applicableCustTypes = "";

            if (typeCode == "1")
            {
                if (schemeCode == "item")
                {
                    itemId = GetItemId(cmbItemDiscount);
                    if (string.IsNullOrEmpty(itemId))
                    { MessageBox.Show("Select an item.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning); return; }
                }
                discount = txtDiscount1.Text.Trim();
                if (string.IsNullOrEmpty(discount))
                { MessageBox.Show("Discount required (e.g. 10 or 10%).", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning); return; }
            }
            else if (typeCode == "2")
            {
                discount = txtDiscount2.Text.Trim();
                couponCode = txtCouponCode.Text.Trim();
                if (string.IsNullOrEmpty(discount))
                { MessageBox.Show("Discount required.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning); return; }
                if (string.IsNullOrEmpty(couponCode))
                { MessageBox.Show("Coupon code required.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning); return; }
                // Applicable customer types: at least one must be selected
                var checkedCust = new List<string>();
                if (chkCustRetail.IsChecked == true) checkedCust.Add("1");
                if (chkCustWholesale.IsChecked == true) checkedCust.Add("2");
                if (checkedCust.Count == 0)
                { MessageBox.Show("Select at least one applicable customer type.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning); return; }
                applicableCustTypes = JsonSerializer.Serialize(checkedCust);
            }
            else if (typeCode == "3")
            {
                itemId    = GetItemId(cmbBuyItem);
                qty       = txtBuyQty.Text.Trim();
                getItemId = GetItemId(cmbGetItem);
                getQty    = txtGetQty.Text.Trim();
                if (string.IsNullOrEmpty(itemId) || string.IsNullOrEmpty(getItemId))
                { MessageBox.Show("Buy item and Get item required.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning); return; }
                if (string.IsNullOrEmpty(qty) || string.IsNullOrEmpty(getQty))
                { MessageBox.Show("Buy qty and Get qty required.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning); return; }
                tierJson = BuildTierJson();
            }

            // Bill level discount applies when scheme_basis=bill (web: .scheme-bill section)
            if (schemeCode == "bill")
            {
                billDiscount = txtBillDiscount1.Text.Trim();
                if (string.IsNullOrEmpty(billDiscount)) billDiscount = "0";
                // web auto-detects type from value: "10%" -> percentage, else fixed
                billDiscType = billDiscount.Contains("%") ? "percentage" : "fixed";
                billDiscount = billDiscount.Replace("%", "").Trim();
                if (!double.TryParse(billDiscount, out _))
                { MessageBox.Show("Invalid bill level discount.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning); return; }
                if (!double.TryParse(txtMaxDiscount.Text.Trim(), out _))
                { MessageBox.Show("Invalid max discount amount.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning); return; }
                maxDiscount = txtMaxDiscount.Text.Trim();
            }

            try
            {
                using var conn = _db.GetConnection();
                using var tx   = conn.BeginTransaction();
                using var cmd  = conn.CreateCommand();

                if (_editId > 0)
                {
                    cmd.CommandText = @"UPDATE promotions SET
                        title=@title, type=@type, scheme_basis=@scheme,
                        start_date=@sdate, end_date=@edate,
                        start_time=@stime, end_time=@etime,
                        status=@status, min_purchase_amount=@minp,
                        item_id=@item_id, discount=@discount, coupon_code=@coupon,
                        qty=@qty, get_item_id=@gitem, get_qty=@gqty,
                        bill_level_discount=@bld, bill_level_discount_type=@bldt,
                        max_discount_amount=@maxd, tier_percentages=@tiers,
                        applicable_customer_types=@custtypes,
                        updated_at=datetime('now')
                        WHERE id=@id";
                    cmd.Parameters.AddWithValue("@id", _editId);
                }
                else
                {
                    cmd.CommandText = @"INSERT INTO promotions
                        (title, type, scheme_basis, start_date, end_date,
                         start_time, end_time, status, min_purchase_amount,
                         item_id, discount, coupon_code,
                         qty, get_item_id, get_qty,
                         bill_level_discount, bill_level_discount_type,
                         max_discount_amount, tier_percentages, applicable_customer_types,
                         del_status, created_at, updated_at, SyncStatus)
                        VALUES
                        (@title, @type, @scheme, @sdate, @edate,
                         @stime, @etime, @status, @minp,
                         @item_id, @discount, @coupon,
                         @qty, @gitem, @gqty,
                         @bld, @bldt, @maxd, @tiers, @custtypes,
                         'Live', datetime('now'), datetime('now'), 'Local')";
                }

                cmd.Parameters.AddWithValue("@title",   txtTitle.Text.Trim());
                cmd.Parameters.AddWithValue("@type",    typeCode);
                cmd.Parameters.AddWithValue("@scheme",  schemeCode);
                cmd.Parameters.AddWithValue("@sdate",   dpStartDate.SelectedDate!.Value.ToString("yyyy-MM-dd"));
                cmd.Parameters.AddWithValue("@edate",   dpEndDate.SelectedDate!.Value.ToString("yyyy-MM-dd"));
                cmd.Parameters.AddWithValue("@stime",   startTime);
                cmd.Parameters.AddWithValue("@etime",   endTime);
                cmd.Parameters.AddWithValue("@status",  statusCode);
                cmd.Parameters.AddWithValue("@minp",    minPurchase);
                cmd.Parameters.AddWithValue("@item_id", string.IsNullOrEmpty(itemId)    ? DBNull.Value : (object)long.Parse(itemId));
                cmd.Parameters.AddWithValue("@discount", string.IsNullOrEmpty(discount) ? DBNull.Value : (object)discount);
                cmd.Parameters.AddWithValue("@coupon",  string.IsNullOrEmpty(couponCode) ? DBNull.Value : (object)couponCode);
                cmd.Parameters.AddWithValue("@qty",     string.IsNullOrEmpty(qty)       ? DBNull.Value : (object)int.Parse(qty));
                cmd.Parameters.AddWithValue("@gitem",   string.IsNullOrEmpty(getItemId) ? DBNull.Value : (object)long.Parse(getItemId));
                cmd.Parameters.AddWithValue("@gqty",    string.IsNullOrEmpty(getQty)    ? DBNull.Value : (object)int.Parse(getQty));
                cmd.Parameters.AddWithValue("@bld",     double.TryParse(billDiscount, out var bldVal) ? bldVal : 0);
                cmd.Parameters.AddWithValue("@bldt",    billDiscType);
                cmd.Parameters.AddWithValue("@maxd",    double.TryParse(maxDiscount, out var maxDVal) ? maxDVal : 0);
                cmd.Parameters.AddWithValue("@tiers",   string.IsNullOrEmpty(tierJson) ? DBNull.Value : (object)tierJson);
                cmd.Parameters.AddWithValue("@custtypes", string.IsNullOrEmpty(applicableCustTypes) ? DBNull.Value : (object)applicableCustTypes);

                cmd.ExecuteNonQuery();
                tx.Commit();

                long savedId = _editId;
                if (savedId == 0)
                {
                    using var lid = conn.CreateCommand();
                    lid.CommandText = "SELECT last_insert_rowid()";
                    savedId = Convert.ToInt64(lid.ExecuteScalar());
                }
                Services.SyncService.EnqueueSync("promotions", savedId, _editId > 0 ? "update" : "insert");
                Services.SyncService.MarkLocalPending("promotions", savedId);

                MessageBox.Show("Promotion saved!", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
                formPanel.Visibility = Visibility.Collapsed;
                listPanel.Visibility = Visibility.Visible;
                LoadData();
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }
    }
}
