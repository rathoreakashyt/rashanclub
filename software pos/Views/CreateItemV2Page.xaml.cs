using System;
using System.Collections.Generic;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class CreateItemV2Page : UserControl
    {
        private readonly DatabaseService _db = new DatabaseService();
        private readonly MainDashboard? _dashboard;

        public CreateItemV2Page() { InitializeComponent(); }
        public CreateItemV2Page(MainDashboard dashboard) : this() { _dashboard = dashboard; LoadDropdowns(); }

        private void LoadDropdowns()
        {
            try
            {
                using var conn = _db.GetConnection();

                txtCode.Text = "ITM" + DateTime.Now.ToString("yyyyMMddHHmmss");

                string[] itemTypes = { "General_Product", "Variation_Product", "IMEI_Product", "Serial_Product", "Medicine_Product", "Installment_Product", "Service_Product", "Combo_Product" };
                foreach (var t in itemTypes) cmbItemType.Items.Add(new ComboBoxItem { Content = t });
                if (cmbItemType.Items.Count > 0) cmbItemType.SelectedIndex = 0;

                // Unit Type (server: 1=Single Unit, 2=Double Unit)
                cmbUnitType.Items.Add(new ComboBoxItem { Content = "Single Unit", Tag = "1" });
                cmbUnitType.Items.Add(new ComboBoxItem { Content = "Double Unit", Tag = "2" });
                cmbUnitType.SelectedIndex = 0;

                // Warranty / Guarantee periods (server values)
                string[] periods = { "day", "month", "year" };
                foreach (var p in periods)
                {
                    cmbWarrantyDate.Items.Add(new ComboBoxItem { Content = p.ToUpper(), Tag = p });
                    cmbGuaranteeDate.Items.Add(new ComboBoxItem { Content = p.ToUpper(), Tag = p });
                }
                if (cmbWarrantyDate.Items.Count > 0) cmbWarrantyDate.SelectedIndex = 0;
                if (cmbGuaranteeDate.Items.Count > 0) cmbGuaranteeDate.SelectedIndex = 0;

                // Tax Type (server: Exclusive/Inclusive)
                cmbTaxType.Items.Add(new ComboBoxItem { Content = "Exclusive" });
                cmbTaxType.Items.Add(new ComboBoxItem { Content = "Inclusive" });
                cmbTaxType.SelectedIndex = 0;

                string[] taxCats = { "Exempt", "GST 0%", "GST 5%", "GST 12%", "GST 18%", "GST 28%", "IGST 0%", "IGST 5%", "IGST 12%", "IGST 18%", "IGST 28%" };
                foreach (var t in taxCats) cmbTaxCat.Items.Add(new ComboBoxItem { Content = t });
                if (cmbTaxCat.Items.Count > 0) cmbTaxCat.SelectedIndex = 1;

                // Units from server meta (mirrors Laravel `units` table)
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT Id, UnitName FROM Units ORDER BY UnitName";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        var item = new ComboBoxItem { Content = r["UnitName"]?.ToString() ?? "", Tag = r["Id"]?.ToString() ?? "" };
                        cmbPurchaseUnit.Items.Add(item);
                        cmbSaleUnit.Items.Add(new ComboBoxItem { Content = item.Content, Tag = item.Tag });
                    }
                }
                if (cmbPurchaseUnit.Items.Count == 0)
                {
                    cmbPurchaseUnit.Items.Add(new ComboBoxItem { Content = "NOS", Tag = "" });
                    cmbSaleUnit.Items.Add(new ComboBoxItem { Content = "NOS", Tag = "" });
                }
                if (cmbPurchaseUnit.Items.Count > 0) { cmbPurchaseUnit.SelectedIndex = 0; cmbSaleUnit.SelectedIndex = 0; }

                // Categories from server meta (mirrors Laravel `item_categories` table)
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT Id, Name FROM ItemCategories WHERE (del_status IS NULL OR del_status != 'Deleted') ORDER BY Name";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                        cmbCategory.Items.Add(new ComboBoxItem { Content = r["Name"]?.ToString() ?? "", Tag = r["Id"]?.ToString() ?? "" });
                }
                if (cmbCategory.Items.Count == 0)
                    cmbCategory.Items.Add(new ComboBoxItem { Content = "General", Tag = "" });

                // Brands from server meta (mirrors Laravel `brands` table)
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT Id, Name FROM Brands WHERE (del_status IS NULL OR del_status != 'Deleted') ORDER BY Name";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                        cmbBrand.Items.Add(new ComboBoxItem { Content = r["Name"]?.ToString() ?? "", Tag = r["Id"]?.ToString() ?? "" });
                }
                if (cmbBrand.Items.Count == 0)
                    cmbBrand.Items.Add(new ComboBoxItem { Content = "No Brand", Tag = "" });

                // Suppliers from server meta (mirrors Laravel `suppliers` table); fallback to local supplier master
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT Id, Name FROM Suppliers ORDER BY Name";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                        cmbSupplier.Items.Add(new ComboBoxItem { Content = r["Name"]?.ToString() ?? "", Tag = r["Id"]?.ToString() ?? "" });
                }
                if (cmbSupplier.Items.Count == 0)
                {
                    using var cmd2 = conn.CreateCommand();
                    cmd2.CommandText = "SELECT ServerId, Name FROM Master1 WHERE MasterType='Party' AND PartyType='Supplier' AND IsActive=1 ORDER BY Name";
                    using var r2 = cmd2.ExecuteReader();
                    while (r2.Read())
                    {
                        long sid = r2["ServerId"] is long s ? s : 0;
                        cmbSupplier.Items.Add(new ComboBoxItem { Content = r2["Name"]?.ToString() ?? "", Tag = sid.ToString() });
                    }
                }
                if (cmbSupplier.Items.Count == 0)
                    cmbSupplier.Items.Add(new ComboBoxItem { Content = "No Supplier", Tag = "" });
            }
            catch { }
        }

        private long GetLookupId(string table, string nameColumn, string name)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = $"SELECT Id FROM {table} WHERE {nameColumn} = @n LIMIT 1";
                cmd.Parameters.AddWithValue("@n", name);
                var val = cmd.ExecuteScalar();
                return val is long l ? l : 0;
            }
            catch { return 0; }
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowPage(new ItemMasterListingPage(_dashboard!));
        }

        private void BtnSave_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(txtName.Text))
            {
                MessageBox.Show("Please enter item name.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }

            // ═══ ENTERPRISE: HSN Code Validation ═══
            if (!string.IsNullOrWhiteSpace(txtHSN.Text))
            {
                var hsnResult = Services.GstValidationService.ValidateHsn(txtHSN.Text.Trim());
                if (!hsnResult.IsValid)
                {
                    var answer = MessageBox.Show($"⚠️ HSN Validation:\n{hsnResult.Message}\n\nSave anyway?",
                        "HSN Validation", MessageBoxButton.YesNo, MessageBoxImage.Warning);
                    if (answer == MessageBoxResult.No)
                    {
                        txtHSN.Focus();
                        return;
                    }
                }
                // Auto-fill GST rate from HSN if available
                if (hsnResult.IsValid && hsnResult.GstRate.HasValue && cmbTaxCat.SelectedIndex <= 0)
                {
                    string targetRate = $"GST {hsnResult.GstRate.Value}%";
                    for (int i = 0; i < cmbTaxCat.Items.Count; i++)
                    {
                        if (cmbTaxCat.Items[i] is ComboBoxItem ci && ci.Content?.ToString() == targetRate)
                        { cmbTaxCat.SelectedIndex = i; break; }
                        if (cmbTaxCat.Items[i]?.ToString() == targetRate)
                        { cmbTaxCat.SelectedIndex = i; break; }
                    }
                }
            }

            try
            {
                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();

                string code = txtCode.Text.Trim();
                double.TryParse(txtPurchasePrice.Text, out double purcPrice);
                double.TryParse(txtProfitMargin.Text, out double profitMargin);
                double.TryParse(txtMRP.Text, out double mrp);
                double.TryParse(txtSalePrice.Text, out double salePrice);
                double.TryParse(txtWholeSale.Text, out double wholeSale);
                double.TryParse(txtOpeningStock.Text, out double opQty);
                double.TryParse(txtOpeningValue.Text, out double opVal);
                double.TryParse(txtAlertQty.Text, out double alertQty);
                double.TryParse(txtLoyaltyPoint.Text, out double loyaltyPoint);
                double.TryParse(txtConversionRate.Text, out double conversionRate);
                double.TryParse(txtWarranty.Text, out double warranty);
                double.TryParse(txtGuarantee.Text, out double guarantee);

                long categoryId = GetLookupId("ItemCategories", "Name", cmbCategory.Text);
                long brandId = GetLookupId("Brands", "Name", cmbBrand.Text);
                long supplierId = 0;
                long.TryParse((cmbSupplier.SelectedItem as ComboBoxItem)?.Tag as string ?? "", out supplierId);
                string purchaseUnit = cmbPurchaseUnit.Text;
                string saleUnit = cmbSaleUnit.Text;
                long purchaseUnitId = 0;
                long saleUnitId = 0;
                long.TryParse((cmbPurchaseUnit.SelectedItem as ComboBoxItem)?.Tag as string ?? "", out purchaseUnitId);
                long.TryParse((cmbSaleUnit.SelectedItem as ComboBoxItem)?.Tag as string ?? "", out saleUnitId);
                string unitType = (cmbUnitType.SelectedItem as ComboBoxItem)?.Tag as string ?? "1";
                string taxType = cmbTaxType.Text;
                string warrantyDate = (cmbWarrantyDate.SelectedItem as ComboBoxItem)?.Tag as string ?? "day";
                string guaranteeDate = (cmbGuaranteeDate.SelectedItem as ComboBoxItem)?.Tag as string ?? "day";

                // Auto-derive tax from HSN if user didn't select any
                string taxCatValue = cmbTaxCat.Text;
                if (string.IsNullOrWhiteSpace(taxCatValue) || taxCatValue == "Exempt" || taxCatValue == "Select Tax")
                {
                    string hsnForAuto = txtHSN.Text.Trim();
                    if (!string.IsNullOrEmpty(hsnForAuto))
                    {
                        var autoRate = Services.GstValidationService.GetGstRateForHsn(hsnForAuto);
                        if (autoRate.HasValue && autoRate.Value > 0)
                            taxCatValue = $"GST {autoRate.Value}%";
                    }
                }

                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"INSERT INTO Master1 (Code, Name, AliasName, MasterType, MainUnit, SaleRate, PurchaseRate, MRP, WholeSalePrice,
                    TaxCategory, TaxType, HSNCode, OpeningStock, OpeningValue, AlertQty, Description, IsActive, CreatedAt, UpdatedAt,
                    ItemType, Category, Brand, CategoryId, BrandId, SupplierId, LoyaltyPoint, UnitType, SaleUnitId, PurchaseUnitId,
                    ConversionRate, Warranty, WarrantyDate, Guarantee, GuaranteeDate, ProfitMargin)
                    VALUES (@code, @name, @alias, 'Item', @unit, @sale, @purc, @mrp, @whole,
                    @taxcat, @taxtype, @hsn, @opqty, @opval, @alert, @desc, 1, datetime('now'), datetime('now'),
                    @itype, @category, @brand, @catid, @brandid, @supplierid, @loyalty, @unittype, @saleunitid, @purchaseunitid,
                    @conversion, @warranty, @warrantydate, @guarantee, @guaranteedate, @margin)";
                cmd.Parameters.AddWithValue("@code", code);
                cmd.Parameters.AddWithValue("@name", txtName.Text.Trim());
                cmd.Parameters.AddWithValue("@alias", txtAlias.Text.Trim());
                cmd.Parameters.AddWithValue("@unit", saleUnit);
                cmd.Parameters.AddWithValue("@sale", salePrice);
                cmd.Parameters.AddWithValue("@purc", purcPrice);
                cmd.Parameters.AddWithValue("@mrp", mrp);
                cmd.Parameters.AddWithValue("@whole", wholeSale);
                cmd.Parameters.AddWithValue("@taxcat", taxCatValue);
                cmd.Parameters.AddWithValue("@taxtype", taxType);
                cmd.Parameters.AddWithValue("@hsn", txtHSN.Text.Trim());
                cmd.Parameters.AddWithValue("@opqty", opQty);
                cmd.Parameters.AddWithValue("@opval", opVal);
                cmd.Parameters.AddWithValue("@alert", alertQty);
                cmd.Parameters.AddWithValue("@desc", txtDescription.Text.Trim());
                cmd.Parameters.AddWithValue("@itype", cmbItemType.Text);
                cmd.Parameters.AddWithValue("@category", cmbCategory.Text);
                cmd.Parameters.AddWithValue("@brand", cmbBrand.Text);
                cmd.Parameters.AddWithValue("@catid", categoryId);
                cmd.Parameters.AddWithValue("@brandid", brandId);
                cmd.Parameters.AddWithValue("@supplierid", supplierId);
                cmd.Parameters.AddWithValue("@loyalty", (long)loyaltyPoint);
                cmd.Parameters.AddWithValue("@unittype", unitType);
                cmd.Parameters.AddWithValue("@saleunitid", saleUnitId);
                cmd.Parameters.AddWithValue("@purchaseunitid", purchaseUnitId);
                cmd.Parameters.AddWithValue("@conversion", conversionRate);
                cmd.Parameters.AddWithValue("@warranty", warranty.ToString());
                cmd.Parameters.AddWithValue("@warrantydate", warrantyDate);
                cmd.Parameters.AddWithValue("@guarantee", guarantee.ToString());
                cmd.Parameters.AddWithValue("@guaranteedate", guaranteeDate);
                cmd.Parameters.AddWithValue("@margin", profitMargin);
                cmd.ExecuteNonQuery();

                // Save variation child items
                string itemType = cmbItemType.Text;
                if (itemType == "Variation_Product")
                    SaveVariations(conn, code);
                // Save combo items
                if (itemType == "Combo_Product")
                    SaveComboItems(conn, code);

                txn.Commit();
                MessageBox.Show("Item saved successfully!", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
                _dashboard?.ShowPage(new ItemMasterListingPage(_dashboard!));
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        // ═══ HSN Live Validation ═══
        private void TxtHSN_TextChanged(object sender, System.Windows.Controls.TextChangedEventArgs e)
        {
            string hsn = txtHSN.Text.Trim();
            if (string.IsNullOrEmpty(hsn))
            {
                lblHsnStatus.Text = "4/6/8 digit HSN or SAC code";
                lblHsnStatus.Foreground = new System.Windows.Media.SolidColorBrush(
                    (System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#9CA3AF"));
                return;
            }
            if (!System.Text.RegularExpressions.Regex.IsMatch(hsn, @"^\d+$"))
            {
                lblHsnStatus.Text = "❌ Only digits allowed";
                lblHsnStatus.Foreground = new System.Windows.Media.SolidColorBrush(
                    (System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#E11D48"));
                return;
            }
            if (hsn.Length != 4 && hsn.Length != 6 && hsn.Length != 8)
            {
                lblHsnStatus.Text = $"⏳ {hsn.Length} digits — need 4, 6, or 8";
                lblHsnStatus.Foreground = new System.Windows.Media.SolidColorBrush(
                    (System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#D97706"));
                return;
            }
            // Valid length — lookup in master
            var result = Services.GstValidationService.ValidateHsn(hsn);
            if (result.IsValid && result.Description != null)
            {
                lblHsnStatus.Text = $"✅ {result.Description} (GST {result.GstRate}%)";
                lblHsnStatus.Foreground = new System.Windows.Media.SolidColorBrush(
                    (System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#059669"));
                // Auto-fill tax rate
                if (result.GstRate.HasValue)
                {
                    string targetRate = $"GST {result.GstRate.Value}%";
                    for (int i = 0; i < cmbTaxCat.Items.Count; i++)
                    {
                        if (cmbTaxCat.Items[i] is ComboBoxItem ci && ci.Content?.ToString() == targetRate)
                        { cmbTaxCat.SelectedIndex = i; break; }
                    }
                }
            }
            else if (result.IsValid)
            {
                lblHsnStatus.Text = "✅ Valid format";
                lblHsnStatus.Foreground = new System.Windows.Media.SolidColorBrush(
                    (System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#059669"));
            }
            else
            {
                lblHsnStatus.Text = $"❌ {result.Message}";
                lblHsnStatus.Foreground = new System.Windows.Media.SolidColorBrush(
                    (System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#E11D48"));
            }
        }

        private void BtnValidateHsn_Click(object sender, RoutedEventArgs e)
        {
            string hsn = txtHSN.Text.Trim();
            if (string.IsNullOrWhiteSpace(hsn))
            {
                MessageBox.Show("Please enter HSN code first.", "Validate", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }
            var result = Services.GstValidationService.ValidateHsn(hsn);
            if (result.IsValid)
            {
                string desc = result.Description ?? "Valid format (not in master)";
                string rate = result.GstRate.HasValue ? $"\nGST Rate: {result.GstRate.Value}%" : "";
                MessageBox.Show($"✅ HSN Verified!\n\nCode: {result.Code}\nType: {result.Type}\nDescription: {desc}{rate}",
                    "HSN Valid", MessageBoxButton.OK, MessageBoxImage.Information);
            }
            else
            {
                MessageBox.Show($"❌ Invalid HSN\n\n{result.Message}", "HSN Validation Failed",
                    MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        // ═══ ITEM TYPE CHANGE — Show/hide panels ═══

        private void CmbItemType_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (variationPanel == null || comboPanel == null || priceSection == null) return;
            string type = (cmbItemType.SelectedItem as ComboBoxItem)?.Content?.ToString() ?? "";
            variationPanel.Visibility = type == "Variation_Product" ? Visibility.Visible : Visibility.Collapsed;
            comboPanel.Visibility = type == "Combo_Product" ? Visibility.Visible : Visibility.Collapsed;
            // Hide price section for Variation_Product (children have their own prices)
            priceSection.Visibility = type == "Variation_Product" || type == "Combo_Product"
                ? Visibility.Collapsed : Visibility.Visible;
            if (type == "Combo_Product") LoadComboItemDropdown();
        }

        // ═══ VARIATION PRODUCTS ═══

        private List<(string Name, string Code, double SalePrice, double PurchasePrice, double WholeSale, double Mrp, double Stock)> _variations = new();
        private List<(string Option, string Values)> _variationOptionRows = new();

        private void BtnAddVariation_Click(object sender, RoutedEventArgs e)
        {
            // Add a variation row: [Select Variation ▼] [Values textbox] [Delete btn]
            var row = new Grid { Margin = new Thickness(0, 0, 0, 8) };
            row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1.5, GridUnitType.Star) });
            row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(12) });
            row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(2, GridUnitType.Star) });
            row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(12) });
            row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(40) });

            var cmbVariation = new ComboBox { IsEditable = true, FontSize = 13, Padding = new Thickness(8, 6, 8, 6) };
            cmbVariation.Items.Add(new ComboBoxItem { Content = "Select Variation", Tag = "" });
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT variation_name, variation_value FROM variations WHERE (del_status IS NULL OR del_status='Live') ORDER BY variation_name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    string name = r["variation_name"]?.ToString() ?? "";
                    string values = r["variation_value"]?.ToString() ?? "[]";
                    cmbVariation.Items.Add(new ComboBoxItem { Content = name, Tag = values });
                }
            }
            catch { }
            cmbVariation.SelectedIndex = 0;
            Grid.SetColumn(cmbVariation, 0);
            row.Children.Add(cmbVariation);

            var txtValues = new TextBox { FontSize = 13, Padding = new Thickness(8, 6, 8, 6), ToolTip = "Enter comma-separated values (e.g., Red, Blue, Green)" };
            // Auto-fill values when variation selected
            cmbVariation.SelectionChanged += (s, e2) =>
            {
                if (cmbVariation.SelectedItem is ComboBoxItem ci && !string.IsNullOrEmpty(ci.Tag?.ToString()) && ci.Tag.ToString() != "")
                {
                    try
                    {
                        var vals = System.Text.Json.JsonSerializer.Deserialize<string[]>(ci.Tag.ToString()!);
                        if (vals != null) txtValues.Text = string.Join(", ", vals);
                    }
                    catch { }
                }
            };
            Grid.SetColumn(txtValues, 2);
            row.Children.Add(txtValues);

            var btnDel = new Button
            {
                Content = "🗑",
                FontSize = 14,
                Background = System.Windows.Media.Brushes.Transparent,
                BorderThickness = new Thickness(0),
                Foreground = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#EF4444")),
                Cursor = Cursors.Hand,
                VerticalAlignment = VerticalAlignment.Center
            };
            btnDel.Click += (s, e2) => variationRows.Children.Remove(row);
            Grid.SetColumn(btnDel, 4);
            row.Children.Add(btnDel);

            variationRows.Children.Add(row);
        }

        private void BtnGenerateVariations_Click(object sender, RoutedEventArgs e)
        {
            // Collect variation options and values from rows
            var options = new List<(string Name, List<string> Values)>();
            foreach (UIElement child in variationRows.Children)
            {
                if (child is not Grid g) continue;
                string optName = "";
                string vals = "";
                foreach (UIElement c in g.Children)
                {
                    if (c is ComboBox cb && cb.SelectedItem is ComboBoxItem ci && ci.Content?.ToString() != "Select Variation")
                        optName = ci.Content?.ToString() ?? "";
                    if (c is TextBox tb) vals = tb.Text.Trim();
                }
                if (!string.IsNullOrEmpty(optName) && !string.IsNullOrEmpty(vals))
                {
                    var valueList = vals.Split(',').Select(v => v.Trim()).Where(v => v != "").ToList();
                    if (valueList.Count > 0) options.Add((optName, valueList));
                }
            }

            if (options.Count == 0)
            {
                MessageBox.Show("Add at least one variation with values.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }

            // Generate all combinations
            var combinations = new List<string>();
            GenerateCombinations(options, 0, "", combinations);

            // Build variation item table
            generatedVariationsPanel.Children.Clear();
            string baseCode = txtCode.Text.Trim();

            // Header
            var header = new Grid { Margin = new Thickness(0, 0, 0, 6) };
            header.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1.5, GridUnitType.Star) });
            header.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(8) });
            header.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1.2, GridUnitType.Star) });
            header.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(8) });
            header.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            header.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(8) });
            header.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            header.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(8) });
            header.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(0.8, GridUnitType.Star) });
            string[] hdrs = { "Variation*", "Code*", "Sale Price*", "Purchase Price", "Stock" };
            int[] cols = { 0, 2, 4, 6, 8 };
            for (int i = 0; i < hdrs.Length; i++)
            {
                var tb = new TextBlock { Text = hdrs[i], FontSize = 11, FontWeight = FontWeights.Bold, Foreground = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#475569")), FontFamily = new System.Windows.Media.FontFamily("Segoe UI") };
                Grid.SetColumn(tb, cols[i]);
                header.Children.Add(tb);
            }
            generatedVariationsPanel.Children.Add(header);

            int idx = 1;
            foreach (var combo in combinations)
            {
                var row = new Border
                {
                    Background = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#F8FAFC")),
                    BorderBrush = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#E2E8F0")),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(6),
                    Padding = new Thickness(10, 6, 10, 6),
                    Margin = new Thickness(0, 0, 0, 6)
                };
                var g = new Grid();
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1.5, GridUnitType.Star) });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(8) });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1.2, GridUnitType.Star) });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(8) });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(8) });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(8) });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(0.8, GridUnitType.Star) });

                var t1 = new TextBox { Tag = "name", Text = combo, FontSize = 12, Padding = new Thickness(6, 4, 6, 4) };
                Grid.SetColumn(t1, 0); g.Children.Add(t1);
                var t2 = new TextBox { Tag = "code", Text = baseCode + "-" + idx, FontSize = 12, Padding = new Thickness(6, 4, 6, 4) };
                Grid.SetColumn(t2, 2); g.Children.Add(t2);
                var t3 = new TextBox { Tag = "sale", Text = "0", FontSize = 12, Padding = new Thickness(6, 4, 6, 4) };
                Grid.SetColumn(t3, 4); g.Children.Add(t3);
                var t4 = new TextBox { Tag = "purchase", Text = "0", FontSize = 12, Padding = new Thickness(6, 4, 6, 4) };
                Grid.SetColumn(t4, 6); g.Children.Add(t4);
                var t5 = new TextBox { Tag = "stock", Text = "0", FontSize = 12, Padding = new Thickness(6, 4, 6, 4) };
                Grid.SetColumn(t5, 8); g.Children.Add(t5);

                row.Child = g;
                generatedVariationsPanel.Children.Add(row);
                idx++;
            }
        }

        private void GenerateCombinations(List<(string Name, List<string> Values)> options, int depth, string current, List<string> result)
        {
            if (depth == options.Count)
            {
                if (!string.IsNullOrEmpty(current)) result.Add(current);
                return;
            }
            foreach (var val in options[depth].Values)
            {
                string next = string.IsNullOrEmpty(current) ? val : current + "-" + val;
                GenerateCombinations(options, depth + 1, next, result);
            }
        }

        // ═══ COMBO PRODUCTS ═══

        private void BtnAddComboItem_Click(object sender, RoutedEventArgs e)
        {
            // No longer used — items are added via CmbComboItemSelect_Changed
        }

        private void CmbComboItemSelect_Changed(object sender, SelectionChangedEventArgs e)
        {
            if (cmbComboItemSelect.SelectedItem is not ComboBoxItem si || si.Tag == null) return;
            string tag = si.Tag.ToString() ?? "";
            if (!tag.Contains("|")) return;

            var parts = tag.Split('|');
            long itemId = 0; double itemPrice = 0;
            long.TryParse(parts[0], out itemId);
            if (parts.Length > 1) double.TryParse(parts[1], out itemPrice);
            string itemName = si.Content?.ToString() ?? "";
            string itemCode = parts.Length > 2 ? parts[2] : "";

            if (itemId <= 0) return;

            AddComboRow(itemId, itemName, itemCode, 1, itemPrice);

            // Reset dropdown
            cmbComboItemSelect.SelectedIndex = -1;
        }

        private void LoadComboItemDropdown()
        {
            cmbComboItemSelect.Items.Clear();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, name, code, COALESCE(sale_price,0) as sale_price FROM items WHERE (del_status IS NULL OR del_status='Live') AND (type IS NULL OR type='General_Product' OR type='') ORDER BY name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    long id = Convert.ToInt64(r["id"]);
                    string name = r["name"]?.ToString() ?? "";
                    string code = r["code"]?.ToString() ?? "";
                    double price = r["sale_price"] != DBNull.Value ? Convert.ToDouble(r["sale_price"]) : 0;
                    cmbComboItemSelect.Items.Add(new ComboBoxItem { Content = $"{name} - {code}", Tag = $"{id}|{price}|{code}" });
                }
            }
            catch { }
        }

        private int _comboSrNo = 0;

        private void AddComboRow(long itemId, string itemName, string itemCode, double qty, double price)
        {
            _comboSrNo = comboItemRows.Children.Count + 1;
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
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(40) });   // SN
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(80) });   // Checkbox
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) }); // Name-Code
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(100) });  // Qty
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(100) });  // Amount
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(100) });  // Total
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(60) });   // Action

            // SN
            var lblSn = new TextBlock { Text = _comboSrNo.ToString(), FontSize = 12, FontFamily = new System.Windows.Media.FontFamily("Segoe UI"), Foreground = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#374151")), VerticalAlignment = VerticalAlignment.Center };
            Grid.SetColumn(lblSn, 0); grid.Children.Add(lblSn);

            // Show in Invoice Checkbox
            var chk = new CheckBox { Tag = "showInInvoice", IsChecked = true, HorizontalAlignment = HorizontalAlignment.Center, VerticalAlignment = VerticalAlignment.Center };
            Grid.SetColumn(chk, 1); grid.Children.Add(chk);

            // Item Name - Code
            var lblItem = new TextBlock { Tag = "itemLabel", Text = $"{itemName} - {itemCode}", FontSize = 12, FontFamily = new System.Windows.Media.FontFamily("Segoe UI"), Foreground = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#1E293B")), VerticalAlignment = VerticalAlignment.Center, Margin = new Thickness(8, 0, 0, 0) };
            Grid.SetColumn(lblItem, 2); grid.Children.Add(lblItem);

            // Quantity
            var txtQty = new TextBox { Tag = "qty", Text = qty.ToString("0"), FontSize = 12, FontFamily = new System.Windows.Media.FontFamily("Segoe UI"), Padding = new Thickness(8, 4, 8, 4), BorderBrush = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#D1D5DB")), BorderThickness = new Thickness(1), Width = 70, HorizontalAlignment = HorizontalAlignment.Center, VerticalAlignment = VerticalAlignment.Center, TextAlignment = TextAlignment.Center };
            txtQty.TextChanged += (s, e2) => UpdateComboRow(grid);
            Grid.SetColumn(txtQty, 3); grid.Children.Add(txtQty);

            // Amount (unit price)
            var txtPrice = new TextBox { Tag = "price", Text = price.ToString("0"), FontSize = 12, FontFamily = new System.Windows.Media.FontFamily("Segoe UI"), Padding = new Thickness(8, 4, 8, 4), BorderBrush = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#D1D5DB")), BorderThickness = new Thickness(1), Width = 70, HorizontalAlignment = HorizontalAlignment.Center, VerticalAlignment = VerticalAlignment.Center, TextAlignment = TextAlignment.Center };
            txtPrice.TextChanged += (s, e2) => UpdateComboRow(grid);
            Grid.SetColumn(txtPrice, 4); grid.Children.Add(txtPrice);

            // Total
            var lblTotal = new TextBlock { Tag = "total", Text = total.ToString("0.00"), FontSize = 12, FontFamily = new System.Windows.Media.FontFamily("Segoe UI"), FontWeight = FontWeights.SemiBold, Foreground = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#1E293B")), VerticalAlignment = VerticalAlignment.Center, Width = 70, TextAlignment = TextAlignment.Center, HorizontalAlignment = HorizontalAlignment.Center };
            Grid.SetColumn(lblTotal, 5); grid.Children.Add(lblTotal);

            // Delete button
            var btnDel = new Button { Content = "🗑", FontSize = 14, Background = System.Windows.Media.Brushes.Transparent, BorderThickness = new Thickness(0), Foreground = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#EF4444")), Cursor = Cursors.Hand, HorizontalAlignment = HorizontalAlignment.Center, VerticalAlignment = VerticalAlignment.Center };
            btnDel.Click += (s, e2) => { comboItemRows.Children.Remove(row); RenumberComboRows(); UpdateComboTotal(); };
            Grid.SetColumn(btnDel, 6); grid.Children.Add(btnDel);

            row.Child = grid;
            comboItemRows.Children.Add(row);
            UpdateComboTotal();
        }

        private void RenumberComboRows()
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
            _comboSrNo = sn - 1;
        }

        private void UpdateComboRow(Grid grid)
        {
            double qty = 0, price = 0;
            TextBlock? totalLbl = null;
            foreach (UIElement child in grid.Children)
            {
                if (child is TextBox tb)
                {
                    if (tb.Tag as string == "qty") double.TryParse(tb.Text, out qty);
                    if (tb.Tag as string == "price") double.TryParse(tb.Text, out price);
                }
                if (child is TextBlock tbl && tbl.Tag as string == "total") totalLbl = tbl;
            }
            double total = qty * price;
            if (totalLbl != null) totalLbl.Text = total.ToString("0.00");
            UpdateComboTotal();
        }

        private void UpdateComboTotal()
        {
            double total = 0;
            foreach (UIElement child in comboItemRows.Children)
            {
                if (child is Border bd && bd.Child is Grid g)
                {
                    foreach (UIElement c in g.Children)
                    {
                        if (c is TextBlock tb && tb.Tag as string == "total")
                        {
                            double.TryParse(tb.Text.Replace("₹", ""), out double v);
                            total += v;
                        }
                    }
                }
            }
            txtComboSalePrice.Text = total.ToString("0.00");
        }

        // ═══ SAVE — Variation & Combo support ═══

        private void SaveVariations(SqliteConnection conn, string parentCode)
        {
            foreach (UIElement child in generatedVariationsPanel.Children)
            {
                if (child is not Border bd || bd.Child is not Grid g) continue;
                string vName = "", vCode = "";
                double vSale = 0, vPurchase = 0, vStock = 0;
                foreach (UIElement c in g.Children)
                {
                    if (c is not TextBox tb) continue;
                    switch (tb.Tag as string)
                    {
                        case "name": vName = tb.Text.Trim(); break;
                        case "code": vCode = tb.Text.Trim(); break;
                        case "sale": double.TryParse(tb.Text, out vSale); break;
                        case "purchase": double.TryParse(tb.Text, out vPurchase); break;
                        case "stock": double.TryParse(tb.Text, out vStock); break;
                    }
                }
                if (string.IsNullOrEmpty(vName) || string.IsNullOrEmpty(vCode)) continue;

                // Insert child variation as Master1 item with parent reference
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"INSERT INTO Master1 (Code, Name, MasterType, ItemType, SaleRate, PurchaseRate, MainUnit, 
                    OpeningStock, IsActive, CreatedAt, UpdatedAt, ParentCode)
                    VALUES (@code, @name, 'Item', '0', @sale, @purc, @unit, @stock, 1, datetime('now'), datetime('now'), @parent)";
                cmd.Parameters.AddWithValue("@code", vCode);
                cmd.Parameters.AddWithValue("@name", txtName.Text.Trim() + " - " + vName);
                cmd.Parameters.AddWithValue("@sale", vSale);
                cmd.Parameters.AddWithValue("@purc", vPurchase);
                cmd.Parameters.AddWithValue("@unit", cmbSaleUnit.Text);
                cmd.Parameters.AddWithValue("@stock", vStock);
                cmd.Parameters.AddWithValue("@parent", parentCode);
                cmd.ExecuteNonQuery();
            }
        }

        private void SaveComboItems(SqliteConnection conn, string parentCode)
        {
            double.TryParse(txtComboSalePrice.Text, out double comboSalePrice);
            long outletId = Services.OutletContext.GetSelectedOutletId(_db);

            foreach (UIElement child in comboItemRows.Children)
            {
                if (child is not Border bd || bd.Child is not Grid g) continue;
                long itemId = 0;
                double qty = 0, price = 0, total = 0;
                bool showInInvoice = true;

                // Get itemId from Border.Tag
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
                    VALUES (@id, (SELECT id FROM items WHERE code=@parent LIMIT 1), @item, @qty, @price, @total, @show, 1, 1, datetime('now'), datetime('now'))";
                cmd.Parameters.AddWithValue("@id", LocalTxn.NextLocalId(_db, "combo_items"));
                cmd.Parameters.AddWithValue("@parent", parentCode);
                cmd.Parameters.AddWithValue("@item", itemId);
                cmd.Parameters.AddWithValue("@qty", qty);
                cmd.Parameters.AddWithValue("@price", price);
                cmd.Parameters.AddWithValue("@total", total);
                cmd.Parameters.AddWithValue("@show", showInInvoice ? 1 : 0);
                cmd.ExecuteNonQuery();
            }
        }
    }
}
