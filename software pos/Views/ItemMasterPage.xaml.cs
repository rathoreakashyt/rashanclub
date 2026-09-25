using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views
{
    public partial class ItemMasterPage : UserControl
    {
        private readonly DatabaseService _db = new DatabaseService();
        private readonly ApiService _api = new ApiService();
        private MainDashboard? _dashboard;
        private long? _editId;
        private static readonly string DebugLog = @"C:\Users\Akash\AppData\Local\Temp\opencode\itemmaster_debug.txt";

        private static void Debug(string msg)
        {
            try { System.IO.File.AppendAllText(DebugLog, $"{DateTime.Now:HH:mm:ss.fff} {msg}\r\n"); } catch { }
        }

        public ItemMasterPage()
        {
            Debug("ctor() start");
            InitializeComponent();
            Debug("ctor() init done");
        }

        public ItemMasterPage(MainDashboard dashboard) : this()
        {
            Debug("ctor(dashboard) start");
            _dashboard = dashboard;
            LoadMasterData();
            GenerateCode();
            Debug("ctor(dashboard) before RefreshLookups");
            _ = RefreshLookupsAsync();
            Debug("ctor(dashboard) done");
        }

        public ItemMasterPage(MainDashboard dashboard, long itemId) : this()
        {
            _dashboard = dashboard;
            _editId = itemId;
            LoadMasterData();
            LoadItem(itemId);
            _ = RefreshLookupsAsync();
        }

        // ═══════════ SERVER LOOKUP REFRESH (pull + reload) ═══════════
        private async Task RefreshLookupsAsync()
        {
            try
            {
                if (_dashboard != null && _api.IsConfigured)
                {
                    await _dashboard.TriggerSync();
                    LoadMasterData();
                    if (_editId.HasValue) LoadItem(_editId.Value);
                }
            }
            catch { }
        }

        // ═══════════ MASTER DATA LOADING (OFFLINE SQLite) ═══════════
        private void LoadMasterData()
        {
            Debug("LoadMasterData start");
            LoadCategories();
            LoadBrands();
            LoadSuppliers();
            LoadUnits();
            LoadRacks();
            LoadTaxes();
            Debug($"LoadMasterData done - C:{cmbCategory.Items.Count} B:{cmbBrand.Items.Count} S:{cmbSupplier.Items.Count} U:{cmbSaleUnit.Items.Count} R:{cmbRack.Items.Count} T:{cmbTax.Items.Count}");
        }

        private void LoadCategories()
        {
            try
            {
                using var conn = _db.GetConnection();
                var items = QueryCombos(conn, "SELECT Id, Name FROM ItemCategories WHERE Name<>'' AND (del_status IS NULL OR del_status != 'Deleted') ORDER BY Name", "ItemCategories");
                if (items.Count == 0)
                    items = QueryCombos(conn, "SELECT Id, name FROM item_categories WHERE (del_status IS NULL OR del_status != 'Deleted') ORDER BY name", "item_categories");
                cmbCategory.ItemsSource = items;
                cmbCategory.DisplayMemberPath = "Display";
                cmbCategory.SelectedValuePath = "Id";
                Debug($"LoadCategories -> {items.Count}");
            }
            catch (Exception ex) { Debug("LoadCategories ERR: " + ex.Message); }
        }

        private void LoadBrands()
        {
            try
            {
                using var conn = _db.GetConnection();
                var items = QueryCombos(conn, "SELECT Id, Name FROM Brands WHERE Name<>'' AND (del_status IS NULL OR del_status != 'Deleted') ORDER BY Name", "Brands");
                if (items.Count == 0)
                    items = QueryCombos(conn, "SELECT Id, name FROM brands ORDER BY name", "brands");
                cmbBrand.ItemsSource = items;
                cmbBrand.DisplayMemberPath = "Display";
                cmbBrand.SelectedValuePath = "Id";
                Debug($"LoadBrands -> {items.Count}");
            }
            catch (Exception ex) { Debug("LoadBrands ERR: " + ex.Message); }
        }

        private void LoadSuppliers()
        {
            try
            {
                using var conn = _db.GetConnection();
                var items = QueryCombos(conn, "SELECT Id, Name FROM Suppliers WHERE Name<>'' ORDER BY Name", "Suppliers");
                if (items.Count == 0)
                    items = QueryCombos(conn, "SELECT ServerId AS Id, Name FROM Master1 WHERE MasterType='Party' AND PartyType IN ('Supplier','Both') AND IsActive=1 AND Name<>'' ORDER BY Name", "Master1");
                if (items.Count == 0)
                    items = QueryCombos(conn, "SELECT Id, name FROM suppliers ORDER BY name", "suppliers");
                cmbSupplier.ItemsSource = items;
                cmbSupplier.DisplayMemberPath = "Display";
                cmbSupplier.SelectedValuePath = "Id";
                Debug($"LoadSuppliers -> {items.Count}");
            }
            catch (Exception ex) { Debug("LoadSuppliers ERR: " + ex.Message); }
        }

        private void LoadUnits()
        {
            try
            {
                using var conn = _db.GetConnection();
                var items = QueryCombos(conn, "SELECT Id, UnitName AS Name FROM Units WHERE UnitName<>'' ORDER BY UnitName", "Units");
                if (items.Count == 0)
                    items = QueryCombos(conn, "SELECT Id, unit_name AS Name FROM units ORDER BY unit_name", "units");
                cmbPurchaseUnit.ItemsSource = items;
                cmbSaleUnit.ItemsSource = items;
                cmbPurchaseUnit.DisplayMemberPath = "Display";
                cmbPurchaseUnit.SelectedValuePath = "Id";
                cmbSaleUnit.DisplayMemberPath = "Display";
                cmbSaleUnit.SelectedValuePath = "Id";
                Debug($"LoadUnits -> {items.Count}");
            }
            catch (Exception ex) { Debug("LoadUnits ERR: " + ex.Message); }
        }

        private void LoadRacks()
        {
            try
            {
                using var conn = _db.GetConnection();
                var items = QueryCombos(conn, "SELECT Id, Name FROM Racks WHERE Name<>'' ORDER BY Name", "Racks");
                if (items.Count == 0)
                    items = QueryCombos(conn, "SELECT Id, name FROM racks ORDER BY name", "racks");
                cmbRack.ItemsSource = items;
                cmbRack.DisplayMemberPath = "Display";
                cmbRack.SelectedValuePath = "Id";
                Debug($"LoadRacks -> {items.Count}");
            }
            catch (Exception ex) { Debug("LoadRacks ERR: " + ex.Message); }
        }

        private static List<ComboItem> QueryCombos(SqliteConnection conn, string sql, string tag)
        {
            var items = new List<ComboItem>();
            try
            {
                using var cmd = conn.CreateCommand();
                cmd.CommandText = sql;
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    var id = r["Id"];
                    var name = r["Name"]?.ToString() ?? "";
                    if (name == "") continue;
                    if (id is long lid) items.Add(new ComboItem((int)lid, name));
                    else if (id is int iid) items.Add(new ComboItem(iid, name));
                    else Debug($"QueryCombos({tag}): unexpected Id type {id?.GetType().Name} value '{id}'");
                }
            }
            catch (Exception ex) { Debug($"QueryCombos({tag}) ERR: {ex.Message}"); }
            return items;
        }

        private void LoadTaxes()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, tax_name, tax_rate FROM taxs WHERE (del_status IS NULL OR del_status='Live') AND show_in_item_profile=1 ORDER BY tax_name";
                using var r = cmd.ExecuteReader();
                var items = new List<TaxItem>();
                while (r.Read())
                    items.Add(new TaxItem(Convert.ToInt32(r["id"]),
                        r["tax_name"].ToString() ?? "",
                        Convert.ToDouble(r["tax_rate"] ?? 0)));
                if (items.Count == 0)
                {
                    foreach (var (rate, name) in new (double, string)[]
                    {
                        (0, "GST 0%"), (5, "GST 5%"), (12, "GST 12%"), (18, "GST 18%"), (28, "GST 28%")
                    })
                    {
                        items.Add(new TaxItem(0, name, rate));
                    }
                }
                cmbTax.ItemsSource = items;
                cmbTax.DisplayMemberPath = "Display";
                cmbTax.SelectedValuePath = "Id";
            }
            catch { }
        }

        private void GenerateCode()
        {
            try
            {
                using var conn = _db.GetConnection();
                // Device tag ke saath code — alag counters same "ITM-00001" na banayein
                string prefix = "ITM-" + RashanKiDukan.Services.DeviceContext.Short + "-";
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT IFNULL(MAX(CAST(SUBSTR(code, @len) AS INTEGER)),0)+1
                                    FROM items WHERE code LIKE @p";
                cmd.Parameters.AddWithValue("@len", prefix.Length + 1);
                cmd.Parameters.AddWithValue("@p", prefix + "%");
                long next = Convert.ToInt64(cmd.ExecuteScalar());
                txtCode.Text = prefix + next.ToString().PadLeft(5, '0');
            }
            catch { }
        }

        // ═══════════ TYPE/UNIT VISIBILITY ═══════════
        private void TxtInteger_PreviewTextInput(object sender, TextCompositionEventArgs e)
        {
            foreach (var ch in e.Text)
                if (!char.IsDigit(ch)) { e.Handled = true; return; }
        }

        private void TxtDecimal_PreviewTextInput(object sender, TextCompositionEventArgs e)
        {
            foreach (var ch in e.Text)
                if (!char.IsDigit(ch) && ch != '.') { e.Handled = true; return; }
            if (e.Text.Contains('.') && ((TextBox)sender).Text.Contains('.')) e.Handled = true;
        }

        private void CmbType_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (pnlPharmacy == null || pnlUnit == null || pnlStock == null || pnlWarranty == null) return;

            var type = (cmbType.SelectedItem as ComboBoxItem)?.Tag?.ToString() ?? "General_Product";

            bool isService = type == "Service_Product";
            bool isCombo = type == "Combo_Product";
            bool isMedicine = type == "Medicine_Product";
            bool isVariation = type == "Variation_Product";

            pnlPharmacy.Visibility = isMedicine ? Visibility.Visible : Visibility.Collapsed;
            pnlUnit.Visibility = (isService || isCombo) ? Visibility.Collapsed : Visibility.Visible;
            pnlStock.Visibility = isService ? Visibility.Collapsed : Visibility.Visible;
            pnlWarranty.Visibility = (isService || isCombo) ? Visibility.Collapsed : Visibility.Visible;
            if (pnlVariation != null) pnlVariation.Visibility = isVariation ? Visibility.Visible : Visibility.Collapsed;
            if (pnlCombo != null) { pnlCombo.Visibility = isCombo ? Visibility.Visible : Visibility.Collapsed; if (isCombo) LoadComboDropdown(); }
            // Hide price section for Variation_Product and Combo_Product (children have their own prices)
            if (priceSection != null) priceSection.Visibility = (isVariation || isCombo) ? Visibility.Collapsed : Visibility.Visible;

            // For combo products, show child stock info instead of editable stock fields
            if (isCombo && _editId.HasValue)
            {
                LoadComboStockInfo(_editId.Value);
            }
            else if (isCombo)
            {
                // New combo — show placeholder
                txtOpeningStock.Text = "";
                txtOpeningStock.IsEnabled = false;
                txtAlertQty.Text = "";
                txtAlertQty.IsEnabled = false;
            }
            else
            {
                txtOpeningStock.IsEnabled = true;
                txtAlertQty.IsEnabled = true;
            }
        }

        private void CmbUnitType_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (cmbPurchaseUnit == null || txtConversion == null) return;

            var unitType = (cmbUnitType.SelectedItem as ComboBoxItem)?.Tag?.ToString() ?? "1";
            bool isDouble = unitType == "2";

            pnlPurchaseUnit.Visibility = isDouble ? Visibility.Visible : Visibility.Collapsed;
            pnlConversion.Visibility = isDouble ? Visibility.Visible : Visibility.Collapsed;
            lblSaleUnit.Text = isDouble ? "Sale Unit *" : "Unit *";
            cmbSaleUnit.Tag = isDouble ? "Select Sale Unit" : "Select Unit";
            colPurchase.Width = new GridLength(isDouble ? 1 : 0, GridUnitType.Star);
            colConversion.Width = new GridLength(isDouble ? 1 : 0, GridUnitType.Star);
        }

        // ═══════════ SAVE (OFFLINE FIRST + LARAVEL SYNC) ═══════════
        private void BtnSave_Click(object sender, RoutedEventArgs e)
        {
            if (!ValidateForm()) return;

            var type = (cmbType.SelectedItem as ComboBoxItem)?.Tag?.ToString() ?? "General_Product";
            var now = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
            var name = txtName.Text.Trim();
            var code = txtCode.Text.Trim();

            try
            {
                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();

                long id;
                if (_editId.HasValue)
                {
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = @"UPDATE items SET name=@name, alternative_name=@alt, generic_name=@generic,
                        type=@type, expiry_date_maintain=@expiry, category_id=@cat, rack_id=@rack,
                        brand_id=@brand, supplier_id=@supplier, alert_quantity=@alert,
                        unit_type=@unit_type, purchase_unit_id=@purchase_unit, sale_unit_id=@sale_unit,
                        conversion_rate=@conversion, purchase_price=@purchase_price, profit_margin=@profit,
                        sale_price=@sale_price, whole_sale_price=@whole_sale, mrp_price=@mrp,
                        description=@desc, warranty=@warranty, warranty_date=@warranty_date,
                        guarantee=@guarantee, guarantee_date=@guarantee_date,
                        tax_type=@tax_type, applicable_tax_id=@applicable_tax, hsn_code=@hsn,
                        loyalty_point=@loyalty, stock_quantity=MAX(@opening_stock, 0),
                        SyncStatus='Local',
                        updated_at=@now, del_status='Live'
                        WHERE id=@id";
                    BindParameters(cmd, code);
                    cmd.Parameters.AddWithValue("@id", _editId.Value);
                    cmd.ExecuteNonQuery();
                    id = _editId.Value;
                }
                else
                {
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = @"INSERT INTO items
                        (name, alternative_name, generic_name, type, expiry_date_maintain,
                         category_id, rack_id, brand_id, supplier_id, alert_quantity,
                         unit_type, purchase_unit_id, sale_unit_id, conversion_rate,
                         purchase_price, profit_margin, sale_price, whole_sale_price, mrp_price,
                         description, warranty, warranty_date, guarantee, guarantee_date,
                         tax_type, applicable_tax_id, hsn_code, loyalty_point,
                         code, created_at, updated_at, del_status, stock_quantity, SyncStatus)
                        VALUES
                        (@name, @alt, @generic, @type, @expiry,
                         @cat, @rack, @brand, @supplier, @alert,
                         @unit_type, @purchase_unit, @sale_unit, @conversion,
                         @purchase_price, @profit, @sale_price, @whole_sale, @mrp,
                         @desc, @warranty, @warranty_date, @guarantee, @guarantee_date,
                         @tax_type, @applicable_tax, @hsn, @loyalty,
                         @code, @now, @now, 'Live', MAX(@opening_stock, 0), 'Local')";
                    BindParameters(cmd, code);
                    cmd.ExecuteNonQuery();
                    using (var idCmd = conn.CreateCommand())
                    {
                        idCmd.CommandText = "SELECT last_insert_rowid()";
                        id = Convert.ToInt64(idCmd.ExecuteScalar());
                    }
                }

                txn.Commit();

                // Save variation children (after parent commit, in new transaction)
                var itemType = (cmbType.SelectedItem as ComboBoxItem)?.Tag?.ToString() ?? "";
                if (itemType == "Variation_Product" && generatedVariationsPanel.Children.Count > 0)
                {
                    SaveVariationChildren(code);
                }

                // Save combo children (after parent commit)
                if (itemType == "Combo_Product" && comboItemRows.Children.Count > 0)
                {
                    SaveComboItems(conn, code, _editId ?? id);
                }

                // Stock update hua — saare subscribed pages (Stock/Low Stock/Inventory) refresh
                Services.StockEvents.NotifyStockChanged();

                ShowStatus("Item saved successfully! Syncing to server...", true);
                _ = SyncToServerAsync(id, name, code);

                // Save ke baad seedha Item List page par wapas jao (Sneat UX)
                if (_dashboard != null)
                {
                    _dashboard.ShowPage(new ItemMasterListingPage(_dashboard));
                }
            }
            catch (Exception ex)
            {
                ShowStatus("Error: " + ex.Message, false);
            }
        }

        private void BindParameters(SqliteCommand cmd, string code)
        {
            cmd.Parameters.AddWithValue("@code", code);
            cmd.Parameters.AddWithValue("@name", txtName.Text.Trim());
            cmd.Parameters.AddWithValue("@alt", txtAltName.Text.Trim());
            cmd.Parameters.AddWithValue("@generic", txtGeneric.Text.Trim());
            cmd.Parameters.AddWithValue("@type", (cmbType.SelectedItem as ComboBoxItem)?.Tag?.ToString() ?? "General_Product");
            cmd.Parameters.AddWithValue("@expiry", SelectedContent(cmbExpiry) == "Yes" ? 1 : 0);
            cmd.Parameters.AddWithValue("@cat", cmbCategory.SelectedValue is int cat ? cat : 0);
            cmd.Parameters.AddWithValue("@rack", cmbRack.SelectedValue is int rack ? rack : 0);
            cmd.Parameters.AddWithValue("@brand", cmbBrand.SelectedValue is int brand ? brand : 0);
            cmd.Parameters.AddWithValue("@supplier", cmbSupplier.SelectedValue is int supplier ? supplier : 0);
            cmd.Parameters.AddWithValue("@alert", ParseDouble(txtAlertQty.Text));
            cmd.Parameters.AddWithValue("@unit_type", (cmbUnitType.SelectedItem as ComboBoxItem)?.Tag?.ToString() ?? "1");
            cmd.Parameters.AddWithValue("@purchase_unit", cmbPurchaseUnit.SelectedValue is int pu ? pu : 0);
            cmd.Parameters.AddWithValue("@sale_unit", cmbSaleUnit.SelectedValue is int su ? su : 0);
            cmd.Parameters.AddWithValue("@conversion", ParseDouble(txtConversion.Text));
            cmd.Parameters.AddWithValue("@purchase_price", ParseDouble(txtPurchasePrice.Text));
            cmd.Parameters.AddWithValue("@profit", ParseDouble(txtProfitMargin.Text));
            cmd.Parameters.AddWithValue("@sale_price", ParseDouble(txtSalePrice.Text));
            cmd.Parameters.AddWithValue("@whole_sale", ParseDouble(txtWholeSale.Text));
            cmd.Parameters.AddWithValue("@mrp", ParseDouble(txtMRP.Text));
            cmd.Parameters.AddWithValue("@desc", txtDesc.Text.Trim());
            cmd.Parameters.AddWithValue("@warranty", txtWarranty.Text.Trim());
            cmd.Parameters.AddWithValue("@warranty_date", (cmbWarrantyDate.SelectedItem as ComboBoxItem)?.Content?.ToString().ToLower() ?? "day");
            cmd.Parameters.AddWithValue("@guarantee", txtGuarantee.Text.Trim());
            cmd.Parameters.AddWithValue("@guarantee_date", (cmbGuaranteeDate.SelectedItem as ComboBoxItem)?.Content?.ToString().ToLower() ?? "day");
            cmd.Parameters.AddWithValue("@tax_type", SelectedContent(cmbTaxType));
            cmd.Parameters.AddWithValue("@applicable_tax", cmbTax.SelectedValue is int tax ? tax : 0);
            cmd.Parameters.AddWithValue("@hsn", txtHSN.Text.Trim());
            cmd.Parameters.AddWithValue("@loyalty", ParseDouble(txtLoyalty.Text));
            cmd.Parameters.AddWithValue("@opening_stock", ParseDouble(txtOpeningStock.Text));
            cmd.Parameters.AddWithValue("@now", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
        }

        // ═══════════ LARAVEL BACKEND SYNC ═══════════
        private async Task SyncToServerAsync(long localId, string name, string code)
        {
            try
            {
                if (!_api.IsConfigured) return;

                var payload = new
                {
                    items = new[]
                    {
                        new
                        {
                            local_code = code,
                            name = name,
                            code = code,
                            alternative_name = txtAltName.Text.Trim(),
                            generic_name = txtGeneric.Text.Trim(),
                            type = (cmbType.SelectedItem as ComboBoxItem)?.Tag?.ToString() ?? "General_Product",
                            expiry_date_maintain = SelectedContent(cmbExpiry) == "Yes" ? 1 : 0,
                            category_id = ResolveServerId("ItemCategories", cmbCategory.SelectedValue is int cat ? cat : 0),
                            rack_id = ResolveServerId("Racks", cmbRack.SelectedValue is int rack ? rack : 0),
                            brand_id = ResolveServerId("Brands", cmbBrand.SelectedValue is int brand ? brand : 0),
                            supplier_id = ResolveServerId("Suppliers", cmbSupplier.SelectedValue is int supplier ? supplier : 0),
                            alert_quantity = ParseDouble(txtAlertQty.Text),
                            unit_type = (cmbUnitType.SelectedItem as ComboBoxItem)?.Tag?.ToString() ?? "1",
                            purchase_unit_id = ResolveServerId("Units", cmbPurchaseUnit.SelectedValue is int pu ? pu : 0),
                            sale_unit_id = ResolveServerId("Units", cmbSaleUnit.SelectedValue is int su ? su : 0),
                            conversion_rate = ParseDouble(txtConversion.Text),
                            purchase_price = ParseDouble(txtPurchasePrice.Text),
                            profit_margin = ParseDouble(txtProfitMargin.Text),
                            sale_price = ParseDouble(txtSalePrice.Text),
                            whole_sale_price = ParseDouble(txtWholeSale.Text),
                            mrp_price = ParseDouble(txtMRP.Text),
                            description = txtDesc.Text.Trim(),
                            warranty = txtWarranty.Text.Trim(),
                            warranty_date = (cmbWarrantyDate.SelectedItem as ComboBoxItem)?.Content?.ToString().ToLower() ?? "day",
                            guarantee = txtGuarantee.Text.Trim(),
                            guarantee_date = (cmbGuaranteeDate.SelectedItem as ComboBoxItem)?.Content?.ToString().ToLower() ?? "day",
                            tax_type = SelectedContent(cmbTaxType),
                            applicable_tax_id = cmbTax.SelectedValue is int tax && tax > 0 ? tax : (int?)null,
                            hsn_code = txtHSN.Text.Trim(),
                            loyalty_point = ParseDouble(txtLoyalty.Text),
                            stock_quantity = ParseDouble(txtOpeningStock.Text),
                            del_status = "Live"
                        }
                    }
                };

                var (ok, message, data) = await _api.PushAsync(payload);
                if (!ok)
                {
                    ShowStatus("Item saved locally. Will sync when server is available.", true);
                    return;
                }

                // Map the server id back to the local row so the next pull does not duplicate it
                long serverId = 0;
                if (data != null && data.RootElement.TryGetProperty("items", out var items)
                    && items.ValueKind == JsonValueKind.Array && items.GetArrayLength() > 0
                    && items[0].TryGetProperty("server_id", out var sid) && sid.ValueKind == JsonValueKind.Number)
                {
                    serverId = sid.GetInt64();
                }
                if (serverId > 0) MapServerId(localId, serverId);

                ShowStatus("Item saved and synced to server!", true);
            }
            catch
            {
                ShowStatus("Item saved locally. Will sync when server is available.", true);
            }
        }

        private void MapServerId(long localId, long serverId)
        {            try
            {
                using var conn = _db.GetConnection();
                using var chk = conn.CreateCommand();
                chk.CommandText = "SELECT COUNT(*) FROM items WHERE Id=@sid";
                chk.Parameters.AddWithValue("@sid", serverId);
                bool idFree = Convert.ToInt64(chk.ExecuteScalar()) == 0;

                using var upd = conn.CreateCommand();
                if (idFree)
                {
                    upd.CommandText = "UPDATE items SET Id=@sid, ServerId=@sid, SyncStatus='Synced' WHERE Id=@local";
                }
                else
                {
                    upd.CommandText = "UPDATE items SET ServerId=@sid, SyncStatus='Synced' WHERE Id=@local";
                }
                upd.Parameters.AddWithValue("@sid", serverId);
                upd.Parameters.AddWithValue("@local", localId);
                upd.ExecuteNonQuery();
            }
            catch { }
        }

        private static long ResolveServerId(string table, long localId)
        {
            if (localId > 0) return localId;
            if (localId == 0) return 0;
            try
            {
                using var conn = new DatabaseService().GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = $"SELECT ServerId FROM {table} WHERE Id=@id";
                cmd.Parameters.AddWithValue("@id", localId);
                var v = cmd.ExecuteScalar();
                return v is long s && s > 0 ? s : 0;
            }
            catch { return 0; }
        }

        // ═══════════ LOAD ITEM FOR EDIT ═══════════
        private void LoadItem(long id)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT * FROM items WHERE id=@id";
                cmd.Parameters.AddWithValue("@id", id);
                using var r = cmd.ExecuteReader();
                if (r.Read())
                {
                    txtName.Text = r["name"]?.ToString() ?? "";
                    txtAltName.Text = r["alternative_name"]?.ToString() ?? "";
                    txtGeneric.Text = r["generic_name"]?.ToString() ?? "";
                    txtCode.Text = r["code"]?.ToString() ?? "";
                    txtLoyalty.Text = r["loyalty_point"]?.ToString() ?? "";
                    txtDesc.Text = r["description"]?.ToString() ?? "";
                    txtAlertQty.Text = r["alert_quantity"]?.ToString() ?? "";
                    txtPurchasePrice.Text = r["purchase_price"]?.ToString() ?? "";
                    txtProfitMargin.Text = r["profit_margin"]?.ToString() ?? "";
                    txtSalePrice.Text = r["sale_price"]?.ToString() ?? "";
                    txtWholeSale.Text = r["whole_sale_price"]?.ToString() ?? "";
                    txtMRP.Text = r["mrp_price"]?.ToString() ?? "";
                    txtWarranty.Text = r["warranty"]?.ToString() ?? "";
                    txtGuarantee.Text = r["guarantee"]?.ToString() ?? "";
                    txtHSN.Text = r["hsn_code"]?.ToString() ?? "";
                    txtOpeningStock.Text = r["stock_quantity"]?.ToString() ?? "";

                    var type = r["type"]?.ToString() ?? "General_Product";
                    SelectComboByTag(cmbType, type);

                    if (r["category_id"] != DBNull.Value) cmbCategory.SelectedValue = Convert.ToInt32(r["category_id"]);
                    if (r["brand_id"] != DBNull.Value) cmbBrand.SelectedValue = Convert.ToInt32(r["brand_id"]);
                    if (r["supplier_id"] != DBNull.Value) cmbSupplier.SelectedValue = Convert.ToInt32(r["supplier_id"]);
                    if (r["rack_id"] != DBNull.Value) cmbRack.SelectedValue = Convert.ToInt32(r["rack_id"]);
                    if (r["sale_unit_id"] != DBNull.Value) cmbSaleUnit.SelectedValue = Convert.ToInt32(r["sale_unit_id"]);
                    if (r["purchase_unit_id"] != DBNull.Value) cmbPurchaseUnit.SelectedValue = Convert.ToInt32(r["purchase_unit_id"]);
                    if (r["applicable_tax_id"] != DBNull.Value) cmbTax.SelectedValue = Convert.ToInt32(r["applicable_tax_id"]);

                    SelectComboByContent(cmbExpiry, (r["expiry_date_maintain"]?.ToString() == "1") ? "Yes" : "No");
                    SelectComboByContent(cmbUnitType, r["unit_type"]?.ToString() == "2" ? "Double Unit" : "Single Unit");
                    SelectComboByContent(cmbTaxType, r["tax_type"]?.ToString() ?? "Exclusive");
                    SelectComboByContent(cmbWarrantyDate, Capitalize(r["warranty_date"]?.ToString() ?? "day"));
                    SelectComboByContent(cmbGuaranteeDate, Capitalize(r["guarantee_date"]?.ToString() ?? "day"));
                    txtConversion.Text = r["conversion_rate"]?.ToString() ?? "1";

                    // Load variation children if Variation_Product
                    if (type == "Variation_Product")
                    {
                        string code = r["code"]?.ToString() ?? "";
                        LoadVariationChildrenUI(conn, id, code);
                    }

                    // Load combo children if Combo_Product
                    if (type == "Combo_Product")
                    {
                        LoadComboChildrenUI(conn, id);
                    }
                }
            }
            catch { }
        }

        private void LoadVariationChildrenUI(SqliteConnection conn, long parentId, string parentCode)
        {
            // Try items table first (cloud synced children have parent_id)
            var children = new List<(string Name, string Code, double Sale, double Purc, double Mrp, double Whole, double Alert, double Stock)>();
            using (var cmd = conn.CreateCommand())
            {
                cmd.CommandText = "SELECT name, code, IFNULL(sale_price,0) AS sale, IFNULL(purchase_price,0) AS purc, IFNULL(mrp_price,0) AS mrp, IFNULL(whole_sale_price,0) AS whole, IFNULL(alert_quantity,0) AS alert, IFNULL(stock_quantity,0) AS stock FROM items WHERE parent_id=@pid AND (del_status IS NULL OR del_status='Live')";
                cmd.Parameters.AddWithValue("@pid", parentId);
                using var r2 = cmd.ExecuteReader();
                while (r2.Read())
                    children.Add((r2["name"]?.ToString() ?? "", r2["code"]?.ToString() ?? "", Convert.ToDouble(r2["sale"]), Convert.ToDouble(r2["purc"]), Convert.ToDouble(r2["mrp"]), Convert.ToDouble(r2["whole"]), Convert.ToDouble(r2["alert"]), Convert.ToDouble(r2["stock"])));
            }
            // Fallback: Master1 children (local, ParentGroup based)
            if (children.Count == 0)
            {
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT Name, Code, IFNULL(SaleRate,0) AS sale, IFNULL(PurchaseRate,0) AS purc, IFNULL(MRP,0) AS mrp, IFNULL(WholeSalePrice,0) AS whole, IFNULL(AlertQty,0) AS alert, IFNULL(OpeningStock,0) AS stock FROM Master1 WHERE MasterType='Item' AND ItemType='0' AND ParentGroup=@pc AND IsActive=1";
                cmd.Parameters.AddWithValue("@pc", parentCode);
                using var r2 = cmd.ExecuteReader();
                while (r2.Read())
                    children.Add((r2["Name"]?.ToString() ?? "", r2["Code"]?.ToString() ?? "", Convert.ToDouble(r2["sale"]), Convert.ToDouble(r2["purc"]), Convert.ToDouble(r2["mrp"]), Convert.ToDouble(r2["whole"]), Convert.ToDouble(r2["alert"]), Convert.ToDouble(r2["stock"])));
            }

            if (children.Count == 0) return;

            // Show in generatedVariationsPanel as cards
            generatedVariationsPanel.Children.Clear();
            var title = new TextBlock { Text = "Variations", FontSize = 14, FontWeight = FontWeights.Bold, FontFamily = new System.Windows.Media.FontFamily("Segoe UI"), Margin = new Thickness(0, 0, 0, 10) };
            generatedVariationsPanel.Children.Add(title);

            var wp = new WrapPanel { Orientation = Orientation.Horizontal };
            foreach (var c in children)
            {
                var card = new Border
                {
                    Width = 220,
                    Background = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#F8FAFC")),
                    BorderBrush = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#E2E8F0")),
                    BorderThickness = new Thickness(1), CornerRadius = new CornerRadius(10),
                    Padding = new Thickness(14), Margin = new Thickness(0, 0, 12, 12)
                };
                var stack = new StackPanel();
                string varName = c.Name.Contains(" - ") ? c.Name.Substring(c.Name.LastIndexOf(" - ") + 3) : c.Name;
                stack.Children.Add(new TextBlock { Text = varName, FontSize = 12, FontWeight = FontWeights.Bold, Foreground = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#1E293B")), FontFamily = new System.Windows.Media.FontFamily("Segoe UI"), Margin = new Thickness(0, 0, 0, 4) });
                stack.Children.Add(MakeVarField("Item Code *", "vcode", c.Code));
                stack.Children.Add(MakeVarField("Purchase Price", "vpurc", c.Purc.ToString("0.##")));
                stack.Children.Add(MakeVarField("Sale Price *", "vsale", c.Sale.ToString("0.##")));
                stack.Children.Add(MakeVarField("MRP Price", "vmrp", c.Mrp.ToString("0.##")));
                stack.Children.Add(MakeVarField("Whole Sale Price", "vwhole", c.Whole.ToString("0.##")));
                stack.Children.Add(MakeVarField("Alert Quantity", "valert", c.Alert.ToString("0.##")));
                stack.Children.Add(MakeVarField("Stock", "vstock", c.Stock.ToString("0.##")));
                var hiddenName = new TextBlock { Text = varName, Tag = "vname", Visibility = Visibility.Collapsed };
                stack.Children.Add(hiddenName);
                card.Child = stack;
                wp.Children.Add(card);
            }
            generatedVariationsPanel.Children.Add(wp);
        }

        private void LoadComboChildrenUI(SqliteConnection conn, long comboId)
        {
            var children = new List<(long ItemId, string Name, string Code, double Qty, double Price, double Total, bool ShowInInvoice, string Unit)>();
            using (var cmd = conn.CreateCommand())
            {
                cmd.CommandText = @"SELECT ci.item_id, ci.quantity, ci.amount, ci.total, ci.show_in_invoice,
                    i.name, i.code, u.UnitName
                    FROM combo_items ci
                    LEFT JOIN items i ON i.id = ci.item_id
                    LEFT JOIN units u ON i.sale_unit_id = u.Id
                    WHERE ci.combo_item_id = @cid
                      AND (i.del_status IS NULL OR i.del_status != 'Deleted')";
                cmd.Parameters.AddWithValue("@cid", comboId);
                using var r2 = cmd.ExecuteReader();
                while (r2.Read())
                    children.Add((
                        Convert.ToInt64(r2["item_id"]),
                        r2["name"]?.ToString() ?? "",
                        r2["code"]?.ToString() ?? "",
                        r2["quantity"] != DBNull.Value ? Convert.ToDouble(r2["quantity"]) : 1,
                        r2["amount"] != DBNull.Value ? Convert.ToDouble(r2["amount"]) : 0,
                        r2["total"] != DBNull.Value ? Convert.ToDouble(r2["total"]) : 0,
                        r2["show_in_invoice"] == null || r2["show_in_invoice"] == DBNull.Value || Convert.ToInt32(r2["show_in_invoice"]) == 1,
                        r2["UnitName"]?.ToString() ?? ""
                    ));
            }

            if (children.Count == 0) return;

            comboItemRows.Children.Clear();
            _comboSn = 0;

            foreach (var c in children)
            {
                AddComboTableRow(c.ItemId, c.Name, c.Code, c.Qty, c.Price, c.Unit);
            }

            // Set total
            double grandTotal = children.Sum(c => c.Total);
            if (txtComboPrice != null)
                txtComboPrice.Text = grandTotal.ToString("0.00");
        }

        private void LoadComboStockInfo(long comboId)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT i.name, IFNULL(i.stock_quantity,0) as stock
                    FROM combo_items ci
                    LEFT JOIN items i ON i.id = ci.item_id
                    WHERE ci.combo_item_id = @cid
                      AND (i.del_status IS NULL OR i.del_status != 'Deleted')";
                cmd.Parameters.AddWithValue("@cid", comboId);
                using var r = cmd.ExecuteReader();

                var items = new List<(string Name, double Stock)>();
                while (r.Read())
                    items.Add((r["name"]?.ToString() ?? "", r["stock"] != DBNull.Value ? Convert.ToDouble(r["stock"]) : 0));

                if (items.Count == 0)
                {
                    txtOpeningStock.Text = "0";
                    txtAlertQty.Text = "0";
                    return;
                }

                // Effective combo stock = min of all child stocks
                double effectiveStock = items.Min(i => i.Stock);

                // Show effective stock in Opening Stock field
                txtOpeningStock.Text = effectiveStock.ToString("0");
                txtAlertQty.Text = "0";
            }
            catch { }
        }

        private void SaveComboItems(SqliteConnection conn, string parentCode, long parentId)
        {
            // Delete old combo items first
            using (var delCmd = conn.CreateCommand())
            {
                delCmd.CommandText = "DELETE FROM combo_items WHERE combo_item_id=@pid";
                delCmd.Parameters.AddWithValue("@pid", parentId);
                delCmd.ExecuteNonQuery();
            }

            // Insert current combo items from UI
            foreach (UIElement child in comboItemRows.Children)
            {
                if (child is not Border bd || bd.Child is not Grid g) continue;
                long itemId = 0;
                double qty = 0, price = 0, total = 0;
                bool showInInvoice = true;

                long.TryParse(bd.Tag?.ToString() ?? "", out itemId);

                foreach (UIElement c in g.Children)
                {
                    if (c is CheckBox chk && chk.Tag as string == "showInInvoice")
                        showInInvoice = chk.IsChecked == true;
                    if (c is TextBox tb)
                    {
                        if (tb.Tag as string == "qty") double.TryParse(tb.Text, out qty);
                        if (tb.Tag as string == "price") double.TryParse(tb.Text, out price);
                    }
                    if (c is TextBlock tbl && tbl.Tag as string == "total")
                        double.TryParse(tbl.Text.Replace("₹", ""), out total);
                }
                if (itemId <= 0 || qty <= 0) continue;

                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"INSERT INTO combo_items (id, combo_item_id, item_id, quantity, amount, total, show_in_invoice, user_id, company_id, created_at, updated_at)
                    VALUES (@id, @pid, @item, @qty, @price, @total, @show, 1, 1, datetime('now'), datetime('now'))";
                cmd.Parameters.AddWithValue("@id", LocalTxn.NextLocalId(_db, "combo_items"));
                cmd.Parameters.AddWithValue("@pid", parentId);
                cmd.Parameters.AddWithValue("@item", itemId);
                cmd.Parameters.AddWithValue("@qty", qty);
                cmd.Parameters.AddWithValue("@price", price);
                cmd.Parameters.AddWithValue("@total", total);
                cmd.Parameters.AddWithValue("@show", showInInvoice ? 1 : 0);
                cmd.ExecuteNonQuery();
            }
        }

        private void SelectComboByTag(ComboBox combo, string tag)
        {
            foreach (var item in combo.Items)
            {
                if (item is ComboBoxItem cbi && cbi.Tag?.ToString() == tag)
                {
                    combo.SelectedItem = item;
                    break;
                }
            }
        }

        private static void SelectComboByContent(ComboBox combo, string content)
        {
            foreach (var item in combo.Items)
            {
                if (item is ComboBoxItem cbi && cbi.Content?.ToString() == content)
                {
                    combo.SelectedItem = item;
                    return;
                }
            }
        }

        private static string SelectedContent(ComboBox combo)
            => (combo.SelectedItem as ComboBoxItem)?.Content?.ToString() ?? "";

        private string Capitalize(string s)
        {
            if (string.IsNullOrEmpty(s)) return s;
            return char.ToUpper(s[0]) + s.Substring(1);
        }

        // ═══════════ VALIDATION & HELPERS ═══════════
        private bool ValidateForm()
        {
            if (string.IsNullOrWhiteSpace(txtName.Text))
            {
                ShowStatus("Item Name is required", false);
                txtName.Focus();
                return false;
            }
            if (string.IsNullOrWhiteSpace(txtCode.Text))
            {
                ShowStatus("Item Code is required", false);
                txtCode.Focus();
                return false;
            }
            if (cmbCategory.SelectedValue == null)
            {
                ShowStatus("Category is required", false);
                cmbCategory.Focus();
                return false;
            }
            bool isDouble = (cmbUnitType.SelectedItem as ComboBoxItem)?.Tag?.ToString() == "2";
            bool isCombo = (cmbType.SelectedItem as ComboBoxItem)?.Tag?.ToString() == "Combo_Product";
            bool isVariation = (cmbType.SelectedItem as ComboBoxItem)?.Tag?.ToString() == "Variation_Product";
            bool isService = (cmbType.SelectedItem as ComboBoxItem)?.Tag?.ToString() == "Service_Product";
            if (!isVariation && !isCombo && !isService && string.IsNullOrWhiteSpace(txtSalePrice.Text))
            {
                ShowStatus("Sale Price is required", false);
                txtSalePrice.Focus();
                return false;
            }
            if (!isCombo && !isService && cmbSaleUnit.SelectedValue == null)
            {
                ShowStatus(isDouble ? "Sale Unit is required" : "Unit is required", false);
                cmbSaleUnit.Focus();
                return false;
            }
            if (isDouble && !isCombo && !isService)
            {
                if (cmbPurchaseUnit.SelectedValue == null)
                {
                    ShowStatus("Purchase Unit is required", false);
                    cmbPurchaseUnit.Focus();
                    return false;
                }
                if (ParseDouble(txtConversion.Text) <= 0)
                {
                    ShowStatus("Conversion Rate is required", false);
                    txtConversion.Focus();
                    return false;
                }
            }
            if (isVariation && generatedVariationsPanel.Children.Count == 0)
            {
                ShowStatus("Generate at least one variation first", false);
                return false;
            }
            return true;
        }

        private void Price_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (double.TryParse(txtPurchasePrice.Text, out double purchase) && purchase > 0 &&
                double.TryParse(txtSalePrice.Text, out double sale) && sale > 0)
            {
                double profit = sale - purchase;
                txtProfitMargin.Text = profit.ToString("0.##");
            }
        }

        private void ClearForm()
        {
            txtName.Text = txtAltName.Text = txtGeneric.Text = "";
            txtLoyalty.Text = txtDesc.Text = txtAlertQty.Text = "";
            txtPurchasePrice.Text = txtProfitMargin.Text = "";
            txtSalePrice.Text = txtWholeSale.Text = txtMRP.Text = "";
            txtWarranty.Text = txtGuarantee.Text = txtHSN.Text = "";
            txtOpeningStock.Text = txtConversion.Text = "";
            cmbCategory.SelectedIndex = -1;
            cmbBrand.SelectedIndex = -1;
            cmbSupplier.SelectedIndex = -1;
            cmbRack.SelectedIndex = -1;
            cmbTax.SelectedIndex = -1;
            cmbSaleUnit.SelectedIndex = -1;
            cmbPurchaseUnit.SelectedIndex = -1;
        }

        private double ParseDouble(string? text)
        {
            return double.TryParse(text, out double val) ? val : 0;
        }

        private void ShowStatus(string message, bool isSuccess)
        {
            lblStatus.Text = message;
            lblStatus.Foreground = new SolidColorBrush(isSuccess
                ? (Color)ColorConverter.ConvertFromString("#16A34A")
                : (Color)ColorConverter.ConvertFromString("#DC2626"));
            lblStatus.Visibility = Visibility.Visible;
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowDashboard();
        }

        private void BtnValidateHSN_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(txtHSN.Text))
            {
                MessageBox.Show("Enter HSN code first.", "Validate", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }
            var result = Services.GstValidationService.ValidateHsn(txtHSN.Text.Trim());
            if (result.IsValid)
            {
                string desc = result.Description ?? "Valid format";
                string rate = result.GstRate.HasValue ? $"\nGST Rate: {result.GstRate.Value}%" : "";
                MessageBox.Show($"✅ HSN Verified!\n\nCode: {result.Code}\nType: {result.Type}\nDescription: {desc}{rate}",
                    "HSN Valid", MessageBoxButton.OK, MessageBoxImage.Information);
                lblHsnStatus.Text = $"✅ {desc}" + (result.GstRate.HasValue ? $" (GST {result.GstRate.Value}%)" : "");
                lblHsnStatus.Foreground = new System.Windows.Media.SolidColorBrush(
                    (System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#059669"));

                // Auto-fill tax category
                if (result.GstRate.HasValue)
                {
                    string targetRate = $"GST {result.GstRate.Value}%";
                    for (int i = 0; i < cmbTax.Items.Count; i++)
                    {
                        if (cmbTax.Items[i]?.ToString()?.Contains(targetRate) == true)
                        { cmbTax.SelectedIndex = i; break; }
                    }
                }
            }
            else
            {
                MessageBox.Show($"❌ Invalid HSN\n\n{result.Message}", "HSN Validation Failed",
                    MessageBoxButton.OK, MessageBoxImage.Error);
                lblHsnStatus.Text = $"❌ {result.Message}";
                lblHsnStatus.Foreground = new System.Windows.Media.SolidColorBrush(
                    (System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#E11D48"));
            }
        }

        // ═══ VARIATION: Add Row ═══
        private void BtnAddVariationRow_Click(object sender, RoutedEventArgs e)
        {
            var rowBorder = new Border
            {
                Background = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#F8FAFC")),
                BorderBrush = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#E2E8F0")),
                BorderThickness = new Thickness(1), CornerRadius = new CornerRadius(10),
                Padding = new Thickness(16, 12, 16, 12), Margin = new Thickness(0, 0, 0, 10)
            };
            var row = new Grid();
            row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1.5, GridUnitType.Star) });
            row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(16) });
            row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(2.5, GridUnitType.Star) });
            row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(12) });
            row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(36) });

            // Variation Option dropdown
            var cmbStack = new StackPanel();
            cmbStack.Children.Add(new TextBlock { Text = "Variation Option", FontSize = 11, FontWeight = FontWeights.SemiBold, Foreground = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#64748B")), Margin = new Thickness(0, 0, 0, 4) });
            var cmb = new ComboBox { FontSize = 13, Padding = new Thickness(10, 8, 10, 8) };
            cmb.Items.Add(new ComboBoxItem { Content = "Select Variation", Tag = "" });
            try { using var conn = _db.GetConnection(); using var cmd = conn.CreateCommand(); cmd.CommandText = "SELECT variation_name, variation_value FROM variations WHERE (del_status IS NULL OR del_status='Live') ORDER BY variation_name"; using var r = cmd.ExecuteReader(); while (r.Read()) cmb.Items.Add(new ComboBoxItem { Content = r["variation_name"]?.ToString() ?? "", Tag = r["variation_value"]?.ToString() ?? "[]" }); } catch { }
            cmb.SelectedIndex = 0;
            cmbStack.Children.Add(cmb);
            Grid.SetColumn(cmbStack, 0); row.Children.Add(cmbStack);

            // Values — chip multi-select
            var valStack = new StackPanel();
            valStack.Children.Add(new TextBlock { Text = "Click to select values", FontSize = 11, FontWeight = FontWeights.SemiBold, Foreground = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#64748B")), Margin = new Thickness(0, 0, 0, 4) });
            var chipPanel = new WrapPanel();
            valStack.Children.Add(chipPanel);
            var hiddenValues = new TextBox { Tag = "values", Visibility = Visibility.Collapsed };
            valStack.Children.Add(hiddenValues);
            Grid.SetColumn(valStack, 2); row.Children.Add(valStack);

            cmb.SelectionChanged += (s, e2) =>
            {
                chipPanel.Children.Clear();
                if (cmb.SelectedItem is ComboBoxItem ci && !string.IsNullOrEmpty(ci.Tag?.ToString()) && ci.Tag.ToString() != "")
                {
                    try
                    {
                        var vals = System.Text.Json.JsonSerializer.Deserialize<string[]>(ci.Tag.ToString()!);
                        if (vals != null) foreach (var val in vals)
                        {
                            var chip = new Border { Background = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#EEF2FF")), BorderBrush = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#C7D2FE")), BorderThickness = new Thickness(1), CornerRadius = new CornerRadius(16), Padding = new Thickness(12, 5, 12, 5), Margin = new Thickness(0, 0, 6, 6), Cursor = System.Windows.Input.Cursors.Hand, Tag = "unselected" };
                            var chipTxt = new TextBlock { Text = val, FontSize = 12, FontWeight = FontWeights.SemiBold, Foreground = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#4F46E5")), FontFamily = new System.Windows.Media.FontFamily("Segoe UI") };
                            chip.Child = chipTxt;
                            chip.MouseLeftButtonDown += (cs, ce) =>
                            {
                                bool sel = chip.Tag as string == "selected";
                                chip.Tag = sel ? "unselected" : "selected";
                                chip.Background = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString(sel ? "#EEF2FF" : "#4F46E5"));
                                chipTxt.Foreground = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString(sel ? "#4F46E5" : "#FFFFFF"));
                                var sv = new List<string>(); foreach (UIElement ch in chipPanel.Children) if (ch is Border b && b.Tag as string == "selected" && b.Child is TextBlock t) sv.Add(t.Text);
                                hiddenValues.Text = string.Join(", ", sv);
                            };
                            chipPanel.Children.Add(chip);
                        }
                    } catch { }
                }
            };

            var btnDel = new Button { Content = "✕", FontSize = 14, Width = 32, Height = 32, Background = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#FEE2E2")), Foreground = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#DC2626")), BorderThickness = new Thickness(0), Cursor = System.Windows.Input.Cursors.Hand, VerticalAlignment = VerticalAlignment.Top, Margin = new Thickness(0, 18, 0, 0) };
            btnDel.Click += (s, e2) => variationRows.Children.Remove(rowBorder);
            Grid.SetColumn(btnDel, 4); row.Children.Add(btnDel);

            rowBorder.Child = row;
            variationRows.Children.Add(rowBorder);
        }

        // ═══ VARIATION: Generate Combinations ═══
        private void BtnGenerateVariations_Click(object sender, RoutedEventArgs e)
        {
            var options = new List<(string Name, List<string> Values)>();
            foreach (UIElement child in variationRows.Children)
            {
                if (child is not Border bd || bd.Child is not Grid g) continue;
                string optName = "";
                string vals = "";
                foreach (UIElement c in g.Children)
                {
                    if (c is StackPanel sp)
                    {
                        foreach (UIElement sc in sp.Children)
                        {
                            if (sc is ComboBox cb && cb.SelectedItem is ComboBoxItem ci && ci.Content?.ToString() != "Select Variation")
                                optName = ci.Content?.ToString() ?? "";
                            if (sc is TextBox tb && tb.Tag as string == "values") vals = tb.Text.Trim();
                        }
                    }
                }
                if (!string.IsNullOrEmpty(optName) && !string.IsNullOrEmpty(vals))
                {
                    var vl = vals.Split(',').Select(v => v.Trim()).Where(v => v != "").ToList();
                    if (vl.Count > 0) options.Add((optName, vl));
                }
            }
            if (options.Count == 0) { MessageBox.Show("Add at least one variation with values."); return; }

            var combos = new List<string>();
            GenCombos(options, 0, "", combos);

            generatedVariationsPanel.Children.Clear();
            string baseCode = txtCode?.Text?.Trim() ?? "ITM";

            // Header row
            var headerRow = new Grid { Margin = new Thickness(0, 0, 0, 10) };
            headerRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            headerRow.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
            var genTitle = new TextBlock { Text = "Generated Variations", FontSize = 14, FontWeight = FontWeights.Bold, FontFamily = new System.Windows.Media.FontFamily("Segoe UI"), Foreground = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#1E293B")), VerticalAlignment = VerticalAlignment.Center };
            Grid.SetColumn(genTitle, 0); headerRow.Children.Add(genTitle);
            generatedVariationsPanel.Children.Add(headerRow);

            // Card grid - wrapping panel
            var wrapPanel = new WrapPanel { Orientation = Orientation.Horizontal };

            int idx = 1;
            foreach (var combo in combos)
            {
                // Each variation as a card (matches screenshot)
                var card = new Border
                {
                    Width = 220,
                    Background = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#F8FAFC")),
                    BorderBrush = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#E2E8F0")),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(10),
                    Padding = new Thickness(14),
                    Margin = new Thickness(0, 0, 12, 12)
                };

                var stack = new StackPanel();

                // Title + delete
                var titleRow = new Grid();
                titleRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                titleRow.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
                var titleTxt = new TextBlock { Text = combo, FontSize = 12, FontWeight = FontWeights.Bold, Foreground = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#1E293B")), FontFamily = new System.Windows.Media.FontFamily("Segoe UI") };
                Grid.SetColumn(titleTxt, 0); titleRow.Children.Add(titleTxt);
                var delBtn = new Button { Content = "🗑", FontSize = 12, Background = System.Windows.Media.Brushes.Transparent, BorderThickness = new Thickness(0), Foreground = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#EF4444")), Cursor = System.Windows.Input.Cursors.Hand };
                delBtn.Click += (s, e2) => { wrapPanel.Children.Remove(card); };
                Grid.SetColumn(delBtn, 1); titleRow.Children.Add(delBtn);
                stack.Children.Add(titleRow);

                // Fields
                stack.Children.Add(MakeVarField("Item Code *", "vcode", baseCode + "-" + idx));
                stack.Children.Add(MakeVarField("Purchase Price", "vpurc", "0"));
                stack.Children.Add(MakeVarField("Sale Price *", "vsale", "0"));
                stack.Children.Add(MakeVarField("MRP Price", "vmrp", "0"));
                stack.Children.Add(MakeVarField("Whole Sale Price", "vwhole", "0"));
                stack.Children.Add(MakeVarField("Alert Quantity", "valert", "0"));
                stack.Children.Add(MakeVarField("Opening Stock", "vstock", "0"));

                // Store variation name hidden
                var hiddenName = new TextBlock { Text = combo, Tag = "vname", Visibility = Visibility.Collapsed };
                stack.Children.Add(hiddenName);

                card.Child = stack;
                wrapPanel.Children.Add(card);
                idx++;
            }

            generatedVariationsPanel.Children.Add(wrapPanel);
        }

        private StackPanel MakeVarField(string label, string tag, string value)
        {
            var sp = new StackPanel { Margin = new Thickness(0, 8, 0, 0) };
            sp.Children.Add(new TextBlock { Text = label, FontSize = 11, FontWeight = FontWeights.SemiBold, Foreground = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#475569")), FontFamily = new System.Windows.Media.FontFamily("Segoe UI") });
            sp.Children.Add(new TextBox { Tag = tag, Text = value, FontSize = 12, Padding = new Thickness(8, 6, 8, 6), Background = System.Windows.Media.Brushes.White, BorderBrush = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#E2E8F0")), BorderThickness = new Thickness(1) });
            return sp;
        }
        private void GenCombos(List<(string Name, List<string> Values)> opts, int d, string cur, List<string> res)
        {
            if (d == opts.Count) { if (cur != "") res.Add(cur); return; }
            foreach (var v in opts[d].Values) GenCombos(opts, d + 1, cur == "" ? v : cur + "-" + v, res);
        }

        private void SaveVariationChildren(string parentCode)
        {
            try
            {
                using var conn = _db.GetConnection();
                WrapPanel? wp = null;
                foreach (UIElement el in generatedVariationsPanel.Children)
                    if (el is WrapPanel w) { wp = w; break; }
                if (wp == null) return;

                // Get parent item id from items table
                long parentItemId = 0;
                using (var pidCmd = conn.CreateCommand())
                {
                    pidCmd.CommandText = "SELECT id FROM items WHERE code=@c LIMIT 1";
                    pidCmd.Parameters.AddWithValue("@c", parentCode);
                    var v = pidCmd.ExecuteScalar();
                    if (v != null) parentItemId = Convert.ToInt64(v);
                }

                foreach (UIElement child in wp.Children)
                {
                    if (child is not Border card || card.Child is not StackPanel stack) continue;
                    string vName = "", vCode = "";
                    double vSale = 0, vPurc = 0, vMrp = 0, vWhole = 0, vAlert = 0, vStock = 0;

                    foreach (UIElement el in stack.Children)
                    {
                        if (el is TextBlock tb && tb.Tag as string == "vname") vName = tb.Text;
                        if (el is StackPanel sp && sp.Children.Count >= 2 && sp.Children[1] is TextBox box)
                        {
                            switch (box.Tag as string)
                            {
                                case "vcode": vCode = box.Text.Trim(); break;
                                case "vsale": double.TryParse(box.Text, out vSale); break;
                                case "vpurc": double.TryParse(box.Text, out vPurc); break;
                                case "vmrp": double.TryParse(box.Text, out vMrp); break;
                                case "vwhole": double.TryParse(box.Text, out vWhole); break;
                                case "valert": double.TryParse(box.Text, out vAlert); break;
                                case "vstock": double.TryParse(box.Text, out vStock); break;
                            }
                        }
                    }
                    if (string.IsNullOrEmpty(vCode)) continue;

                    string itemName = (txtName?.Text?.Trim() ?? "") + " - " + vName;

                    // Insert into Master1
                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.CommandText = @"INSERT OR IGNORE INTO Master1 (Code, Name, MasterType, ItemType, SaleRate, PurchaseRate, MRP, WholeSalePrice,
                            MainUnit, OpeningStock, AlertQty, IsActive, CreatedAt, UpdatedAt, ParentGroup)
                            VALUES (@code, @name, 'Item', '0', @sale, @purc, @mrp, @whole,
                            @unit, @stock, @alert, 1, datetime('now'), datetime('now'), @parent)";
                        cmd.Parameters.AddWithValue("@code", vCode);
                        cmd.Parameters.AddWithValue("@name", itemName);
                        cmd.Parameters.AddWithValue("@sale", vSale);
                        cmd.Parameters.AddWithValue("@purc", vPurc);
                        cmd.Parameters.AddWithValue("@mrp", vMrp);
                        cmd.Parameters.AddWithValue("@whole", vWhole);
                        cmd.Parameters.AddWithValue("@unit", cmbSaleUnit?.Text ?? "NOS");
                        cmd.Parameters.AddWithValue("@stock", vStock);
                        cmd.Parameters.AddWithValue("@alert", vAlert);
                        cmd.Parameters.AddWithValue("@parent", parentCode);
                        cmd.ExecuteNonQuery();
                    }

                    // Also insert into items table (cloud mirror) for sync
                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.CommandText = @"INSERT OR IGNORE INTO items (id, name, code, type, parent_id, sale_price, purchase_price, mrp_price, whole_sale_price, alert_quantity, stock_quantity, del_status, created_at, updated_at)
                            VALUES (@id, @name, @code, '0', @pid, @sale, @purc, @mrp, @whole, @alert, @stock, 'Live', datetime('now'), datetime('now'))";
                        cmd.Parameters.AddWithValue("@id", Views.LocalTxn.NextLocalId(_db, "items"));
                        cmd.Parameters.AddWithValue("@name", itemName);
                        cmd.Parameters.AddWithValue("@code", vCode);
                        cmd.Parameters.AddWithValue("@pid", parentItemId > 0 ? (object)parentItemId : DBNull.Value);
                        cmd.Parameters.AddWithValue("@sale", vSale);
                        cmd.Parameters.AddWithValue("@purc", vPurc);
                        cmd.Parameters.AddWithValue("@mrp", vMrp);
                        cmd.Parameters.AddWithValue("@whole", vWhole);
                        cmd.Parameters.AddWithValue("@alert", vAlert);
                        cmd.Parameters.AddWithValue("@stock", vStock);
                        cmd.ExecuteNonQuery();
                    }
                }
            }
            catch { }
        }

        // ═══ COMBO: Add Item Row ═══
        private void BtnAddComboRow_Click(object sender, RoutedEventArgs e)
        {
            // Old button method — now items are added via CmbComboSelect_Changed
        }

        private void CmbComboSelect_Changed(object sender, SelectionChangedEventArgs e)
        {
            if (cmbComboSelect.SelectedItem is not ComboBoxItem si || si.Tag == null) return;
            string tag = si.Tag.ToString() ?? "";
            if (!tag.Contains("|")) return;
            var parts = tag.Split('|');
            long itemId = 0; double itemPrice = 0;
            long.TryParse(parts[0], out itemId);
            if (parts.Length > 1) double.TryParse(parts[1], out itemPrice);
            string itemName = parts.Length > 2 ? parts[2] : (si.Content?.ToString() ?? "");
            string itemCode = parts.Length > 3 ? parts[3] : "";
            if (itemId <= 0) return;

            // Lookup unit from items table
            string unit = "";
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT u.UnitName FROM items i LEFT JOIN units u ON i.sale_unit_id=u.Id WHERE i.id=@id LIMIT 1";
                cmd.Parameters.AddWithValue("@id", itemId);
                var result = cmd.ExecuteScalar();
                if (result != null && result != DBNull.Value) unit = result.ToString() ?? "";
            }
            catch { }

            AddComboTableRow(itemId, itemName, itemCode, 1, itemPrice, unit);
            cmbComboSelect.SelectedIndex = -1;
        }

        private void LoadComboDropdown()
        {
            cmbComboSelect.Items.Clear();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, name, code, COALESCE(sale_price,0) as sale_price FROM items WHERE (del_status IS NULL OR del_status='Live') AND (type IS NULL OR type='General_Product' OR type='') ORDER BY name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    long id = Convert.ToInt64(r["id"]);
                    string nm = r["name"]?.ToString() ?? "";
                    string cd = r["code"]?.ToString() ?? "";
                    double pr = r["sale_price"] != DBNull.Value ? Convert.ToDouble(r["sale_price"]) : 0;
                    cmbComboSelect.Items.Add(new ComboBoxItem { Content = $"{nm} - {cd}", Tag = $"{id}|{pr}|{nm}|{cd}" });
                }
            }
            catch { }
        }

        private int _comboSn = 0;

        private void AddComboTableRow(long itemId, string itemName, string itemCode, double qty, double price, string unit = "")
        {
            _comboSn = comboItemRows.Children.Count + 1;
            double total = qty * price;

            var row = new Border
            {
                Tag = $"{itemId}",
                BorderBrush = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#E5E7EB")),
                BorderThickness = new Thickness(0, 0, 0, 1),
                Padding = new Thickness(12, 10, 12, 10),
                Background = System.Windows.Media.Brushes.White
            };

            var grid = new Grid();
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(40) });   // S.No
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(75) });   // ShowInv
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) }); // Item Name
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(70) });   // Unit
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(70) });   // Qty
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(100) });  // Price
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(100) });  // Total
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(60) });   // Delete

            var lblSn = new TextBlock { Text = _comboSn.ToString(), FontSize = 12, FontFamily = new System.Windows.Media.FontFamily("Segoe UI"), Foreground = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#374151")), VerticalAlignment = VerticalAlignment.Center };
            Grid.SetColumn(lblSn, 0); grid.Children.Add(lblSn);

            var chk = new CheckBox { Tag = "showInv", IsChecked = true, HorizontalAlignment = HorizontalAlignment.Center, VerticalAlignment = VerticalAlignment.Center };
            Grid.SetColumn(chk, 1); grid.Children.Add(chk);

            string displayName = string.IsNullOrEmpty(itemCode) ? itemName : $"{itemName} - {itemCode}";
            var lblItem = new TextBlock { Text = displayName, FontSize = 12, FontFamily = new System.Windows.Media.FontFamily("Segoe UI"), Foreground = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#1E293B")), VerticalAlignment = VerticalAlignment.Center, Margin = new Thickness(8, 0, 0, 0), TextTrimming = TextTrimming.CharacterEllipsis };
            Grid.SetColumn(lblItem, 2); grid.Children.Add(lblItem);

            var lblUnit = new TextBlock { Text = unit, FontSize = 11, FontFamily = new System.Windows.Media.FontFamily("Segoe UI"), Foreground = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#64748B")), VerticalAlignment = VerticalAlignment.Center, HorizontalAlignment = HorizontalAlignment.Center };
            Grid.SetColumn(lblUnit, 3); grid.Children.Add(lblUnit);

            var txtQty = new TextBox { Tag = "cqty", Text = qty.ToString("0"), FontSize = 12, FontFamily = new System.Windows.Media.FontFamily("Segoe UI"), Padding = new Thickness(8, 4, 8, 4), BorderBrush = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#D1D5DB")), BorderThickness = new Thickness(1), Width = 70, HorizontalAlignment = HorizontalAlignment.Center, VerticalAlignment = VerticalAlignment.Center, TextAlignment = TextAlignment.Center };
            txtQty.TextChanged += (s, e2) => UpdateComboRowCalc(grid);
            Grid.SetColumn(txtQty, 4); grid.Children.Add(txtQty);

            var txtPrice = new TextBox { Tag = "cprice", Text = price.ToString("0"), FontSize = 12, FontFamily = new System.Windows.Media.FontFamily("Segoe UI"), Padding = new Thickness(8, 4, 8, 4), BorderBrush = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#D1D5DB")), BorderThickness = new Thickness(1), Width = 70, HorizontalAlignment = HorizontalAlignment.Center, VerticalAlignment = VerticalAlignment.Center, TextAlignment = TextAlignment.Center };
            txtPrice.TextChanged += (s, e2) => UpdateComboRowCalc(grid);
            Grid.SetColumn(txtPrice, 5); grid.Children.Add(txtPrice);

            var lblTotal = new TextBlock { Tag = "ctotal", Text = total.ToString("0.00"), FontSize = 12, FontFamily = new System.Windows.Media.FontFamily("Segoe UI"), FontWeight = FontWeights.SemiBold, VerticalAlignment = VerticalAlignment.Center, HorizontalAlignment = HorizontalAlignment.Center, Foreground = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#1E293B")) };
            Grid.SetColumn(lblTotal, 6); grid.Children.Add(lblTotal);

            var btnDel = new Button { Content = "🗑", FontSize = 14, Background = System.Windows.Media.Brushes.Transparent, BorderThickness = new Thickness(0), Foreground = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#EF4444")), Cursor = System.Windows.Input.Cursors.Hand, HorizontalAlignment = HorizontalAlignment.Center, VerticalAlignment = VerticalAlignment.Center };
            btnDel.Click += (s, e2) => { comboItemRows.Children.Remove(row); RenumberComboSn(); UpdateComboTotalCalc(); };
            Grid.SetColumn(btnDel, 7); grid.Children.Add(btnDel);

            row.Child = grid;
            comboItemRows.Children.Add(row);
            UpdateComboTotalCalc();
        }

        private void RenumberComboSn()
        {
            int sn = 1;
            foreach (UIElement child in comboItemRows.Children)
            {
                if (child is Border bd && bd.Child is Grid g)
                {
                    foreach (UIElement c in g.Children)
                    {
                        if (c is TextBlock tb && Grid.GetColumn(tb) == 0 && tb.Tag == null)
                        { tb.Text = sn.ToString(); break; }
                    }
                    sn++;
                }
            }
            _comboSn = sn - 1;
        }

        private void UpdateComboRowCalc(Grid row)
        {
            double qty = 0, price = 0;
            TextBlock? lbl = null;
            foreach (UIElement c in row.Children)
            {
                if (c is TextBox tb) { if (tb.Tag as string == "cqty") double.TryParse(tb.Text, out qty); if (tb.Tag as string == "cprice") double.TryParse(tb.Text, out price); }
                if (c is TextBlock tbl && tbl.Tag as string == "ctotal") lbl = tbl;
            }
            if (lbl != null) lbl.Text = (qty * price).ToString("0.00");
            UpdateComboTotalCalc();
        }

        private void UpdateComboTotalCalc()
        {
            double t = 0;
            foreach (UIElement c in comboItemRows.Children)
                if (c is Border bd && bd.Child is Grid g) foreach (UIElement gc in g.Children) if (gc is TextBlock tb && tb.Tag as string == "ctotal") { double.TryParse(tb.Text, out double v); t += v; }
            txtComboPrice.Text = t.ToString("0.00");
        }
    }

    public class ComboItem
    {
        public int Id { get; }
        public string Display { get; }
        public ComboItem(int id, string display)
        {
            Id = id;
            Display = display;
        }

        public override string ToString() => Display;
    }

    public class TaxItem
    {
        public int Id { get; }
        public string Display { get; }
        public TaxItem(int id, string name, double rate)
        {
            Id = id;
            Display = name + " (" + rate.ToString("0.##") + "%)";
        }

        public override string ToString() => Display;
    }
}
