using System.Windows;
using System.Windows.Controls;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class CreateItemWindow : Window
    {
        private readonly DatabaseService _db = new DatabaseService();
        private string? _editCode = null;

        public CreateItemWindow()
        {
            InitializeComponent();
        }

        public CreateItemWindow(string itemCode) : this()
        {
            _editCode = itemCode;
            LoadItem(itemCode);
            SetUpdateMode();
        }

        private void SetUpdateMode()
        {
            btnSave.Content = null;
            btnSave.Content = new StackPanel
            {
                Orientation = Orientation.Horizontal,
                Children =
                {
                    new TextBlock { Text = "UPDATE", VerticalAlignment = VerticalAlignment.Center },
                    new TextBlock { Text = "\uE098", FontFamily = new System.Windows.Media.FontFamily("Segoe MDL2 Assets"), FontSize = 14, Margin = new Thickness(10,0,0,0), VerticalAlignment = VerticalAlignment.Center }
                }
            };
        }

        private void LoadItem(string code)
        {
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "SELECT * FROM Master1 WHERE Code=@c AND MasterType='Item'";
            cmd.Parameters.AddWithValue("@c", code);
            using var r = cmd.ExecuteReader();
            if (r.Read())
            {
                txtName.Text = r["Name"]?.ToString() ?? "";
                txtAlias.Text = r["AliasName"]?.ToString() ?? "";
                txtPrintName.Text = r["PrintName"]?.ToString() ?? "";
                txtSalePrice.Text = r["SaleRate"]?.ToString() ?? "0";
                txtPurcPrice.Text = r["PurchaseRate"]?.ToString() ?? "0";
                txtMRP.Text = r["MRP"]?.ToString() ?? "0";
                txtMinSalePrice.Text = r["MinSalePrice"]?.ToString() ?? "0";
                txtHSN.Text = r["HSNCode"]?.ToString() ?? "";
                txtOpQty.Text = r["CurrentStock"]?.ToString() ?? "0";
                txtOpValue.Text = r["OpeningBalance"]?.ToString() ?? "0";
                txtItemDesc.Text = r["Description"]?.ToString() ?? "";
                cmbUnit.Text = r["Unit"]?.ToString() ?? "NOS";
                cmbGroup.Text = r["ParentGroup"]?.ToString() ?? "General";
                cmbTaxCat.Text = r["TaxCategory"]?.ToString() ?? "GST 0%";
            }
        }

        private void BtnSave_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(txtName.Text))
            {
                MessageBox.Show("Item Name is required!", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                txtName.Focus();
                return;
            }

            try
            {
                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();

                string code = _editCode ?? GenerateCode(conn);

                if (_editCode != null)
                {
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = @"UPDATE Master1 SET 
                        Name=@name, AliasName=@alias, PrintName=@printname, ParentGroup=@grp, Unit=@unit,
                        SaleRate=@sale, PurchaseRate=@purc, MRP=@mrp, MinSalePrice=@minsale,
                        HSNCode=@hsn, TaxCategory=@taxcat, CurrentStock=@opqty, OpeningBalance=@opval,
                        Description=@desc, UpdatedAt=datetime('now')
                        WHERE Code=@c AND MasterType='Item'";
                    BindParameters(cmd, code);
                    cmd.ExecuteNonQuery();
                }
                else
                {
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = @"INSERT INTO Master1 
                        (Code, Name, AliasName, PrintName, MasterType, ParentGroup, Unit, 
                         SaleRate, PurchaseRate, MRP, MinSalePrice, HSNCode, TaxCategory, 
                         CurrentStock, OpeningBalance, Description, IsActive, CreatedAt, UpdatedAt)
                        VALUES 
                        (@code, @name, @alias, @printname, 'Item', @grp, @unit,
                         @sale, @purc, @mrp, @minsale, @hsn, @taxcat,
                         @opqty, @opval, @desc, 1, datetime('now'), datetime('now'))";
                    BindParameters(cmd, code);
                    cmd.ExecuteNonQuery();
                }

                SaveMasterSupport(conn, code);
                txn.Commit();

                MessageBox.Show(_editCode != null ? "Item updated successfully!" : "Item saved successfully!",
                    "Success", MessageBoxButton.OK, MessageBoxImage.Information);
                DialogResult = true;
                Close();
            }
            catch (Exception ex)
            {
                MessageBox.Show($"Error: {ex.Message}", "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void BindParameters(SqliteCommand cmd, string code)
        {
            cmd.Parameters.AddWithValue("@code", code);
            cmd.Parameters.AddWithValue("@name", txtName.Text.Trim());
            cmd.Parameters.AddWithValue("@alias", txtAlias.Text.Trim());
            cmd.Parameters.AddWithValue("@printname", txtPrintName.Text.Trim());
            cmd.Parameters.AddWithValue("@grp", cmbGroup.Text);
            cmd.Parameters.AddWithValue("@unit", cmbUnit.Text);
            cmd.Parameters.AddWithValue("@sale", double.TryParse(txtSalePrice.Text, out double s) ? s : 0);
            cmd.Parameters.AddWithValue("@purc", double.TryParse(txtPurcPrice.Text, out double p) ? p : 0);
            cmd.Parameters.AddWithValue("@mrp", double.TryParse(txtMRP.Text, out double m) ? m : 0);
            cmd.Parameters.AddWithValue("@minsale", double.TryParse(txtMinSalePrice.Text, out double ms) ? ms : 0);
            cmd.Parameters.AddWithValue("@hsn", txtHSN.Text.Trim());
            cmd.Parameters.AddWithValue("@taxcat", cmbTaxCat.Text);
            cmd.Parameters.AddWithValue("@opqty", double.TryParse(txtOpQty.Text, out double oq) ? oq : 0);
            cmd.Parameters.AddWithValue("@opval", double.TryParse(txtOpValue.Text, out double ov) ? ov : 0);
            cmd.Parameters.AddWithValue("@desc", txtItemDesc.Text.Trim());
        }

        private void SaveMasterSupport(SqliteConnection conn, string masterCode)
        {
            using var del = conn.CreateCommand();
            del.CommandText = "DELETE FROM MasterSupport WHERE MasterCode=@mc";
            del.Parameters.AddWithValue("@mc", masterCode);
            del.ExecuteNonQuery();

            var extras = new (string field, string value)[]
            {
                ("SelfVal", txtSelfVal.Text),
                ("SaleDisc", txtSaleDisc.Text),
                ("PurcDisc", txtPurcDisc.Text),
                ("SpecSaleDisc", chkSpecSaleDisc.IsChecked == true ? "1" : "0"),
                ("SpecPurcDisc", chkSpecPurcDisc.IsChecked == true ? "1" : "0"),
                ("SaleMarkup", txtSaleMarkup.Text),
                ("PurcMarkup", txtPurcMarkup.Text),
                ("SpecSaleMarkup", chkSpecSaleMarkup.IsChecked == true ? "1" : "0"),
                ("SpecPurcMarkup", chkSpecPurcMarkup.IsChecked == true ? "1" : "0"),
                ("TaxInclSale", chkTaxInclSale.IsChecked == true ? "1" : "0"),
                ("TaxInclPurc", chkTaxInclPurc.IsChecked == true ? "1" : "0"),
                ("AcctSale", cmbAcctSale.Text),
                ("AcctPurc", cmbAcctPurc.Text),
                ("SkipGST", chkSkipGST.IsChecked == true ? "1" : "0"),
                ("NoStock", chkNoStock.IsChecked == true ? "1" : "0"),
            };

            foreach (var (field, value) in extras)
            {
                using var ins = conn.CreateCommand();
                ins.CommandText = "INSERT INTO MasterSupport (MasterCode, FieldName, FieldValue) VALUES (@mc, @fn, @fv)";
                ins.Parameters.AddWithValue("@mc", masterCode);
                ins.Parameters.AddWithValue("@fn", field);
                ins.Parameters.AddWithValue("@fv", value);
                ins.ExecuteNonQuery();
            }
        }

        private string GenerateCode(SqliteConnection conn)
        {
            // Device tag ke saath code — 10 counters ek hi code ("I00001") na banayein
            string prefix = "I" + RashanKiDukan.Services.DeviceContext.Short + "-";
            using var cmd = conn.CreateCommand();
            cmd.CommandText = @"SELECT IFNULL(MAX(CAST(SUBSTR(Code, @len) AS INTEGER)),0)+1
                                FROM Master1 WHERE MasterType='Item' AND Code LIKE @p";
            cmd.Parameters.AddWithValue("@len", prefix.Length + 1);
            cmd.Parameters.AddWithValue("@p", prefix + "%");
            long next = (long)cmd.ExecuteScalar();
            return prefix + next.ToString().PadLeft(5, '0');
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e) => Close();

        private void BtnTogglePricing_Click(object sender, RoutedEventArgs e)
        {
            if (pnlPricing.Visibility == Visibility.Visible)
            {
                pnlPricing.Visibility = Visibility.Collapsed;
                btnTogglePricing.Content = "\uE097";
            }
            else
            {
                pnlPricing.Visibility = Visibility.Visible;
                btnTogglePricing.Content = "\uE098";
            }
        }

        private void BtnToggleMore_Click(object sender, RoutedEventArgs e)
        {
            if (pnlMore.Visibility == Visibility.Visible)
            {
                pnlMore.Visibility = Visibility.Collapsed;
                btnToggleMore.Content = "\uE097";
            }
            else
            {
                pnlMore.Visibility = Visibility.Visible;
                btnToggleMore.Content = "\uE098";
            }
        }

        private void BtnValidateHSN_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(txtHSN.Text))
            {
                MessageBox.Show("Enter HSN code first.", "Validate HSN", MessageBoxButton.OK, MessageBoxImage.Information);
                return;
            }
            var result = Services.GstValidationService.ValidateHsn(txtHSN.Text.Trim());
            if (result.IsValid)
            {
                string desc = result.Description ?? "Valid format (not in master)";
                string rate = result.GstRate.HasValue ? $"\nGST Rate: {result.GstRate.Value}%" : "";
                MessageBox.Show($"✅ HSN Verified!\n\nCode: {result.Code}\nType: {result.Type}\nDescription: {desc}{rate}",
                    "HSN Valid", MessageBoxButton.OK, MessageBoxImage.Information);

                // Auto-fill tax category if available
                if (result.GstRate.HasValue)
                {
                    string targetRate = $"GST {result.GstRate.Value}%";
                    for (int i = 0; i < cmbTaxCat.Items.Count; i++)
                    {
                        if (cmbTaxCat.Items[i] is ComboBoxItem ci && ci.Content?.ToString() == targetRate)
                        { cmbTaxCat.SelectedIndex = i; break; }
                        if (cmbTaxCat.Items[i]?.ToString() == targetRate)
                        { cmbTaxCat.SelectedIndex = i; break; }
                    }
                }
            }
            else
            {
                MessageBox.Show($"❌ Invalid HSN\n\n{result.Message}", "HSN Validation Failed",
                    MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void BtnSearchItem_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new ItemSearchWindow { Owner = this };
            if (dlg.ShowDialog() == true && !string.IsNullOrEmpty(dlg.SelectedCode))
            {
                _editCode = dlg.SelectedCode;
                LoadItem(dlg.SelectedCode);
                SetUpdateMode();
                MessageBox.Show("Item loaded: " + dlg.SelectedCode + " — edit karke Save dabao.",
                    "Search Item", MessageBoxButton.OK, MessageBoxImage.Information);
            }
        }
    }
}
