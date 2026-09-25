using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class CreateCustomerPage : UserControl
    {
        private readonly DatabaseService _db = new DatabaseService();
        private readonly MainDashboard? _dashboard;
        private string? _editCode = null;
        private bool _billByBill = true;
        private bool _defSaleType = false;
        private bool _defPurcType = false;

        public CreateCustomerPage() { InitializeComponent(); }

        public CreateCustomerPage(MainDashboard dashboard) : this()
        {
            _dashboard = dashboard;
        }

        public CreateCustomerPage(MainDashboard dashboard, string partyCode) : this(dashboard)
        {
            _editCode = partyCode;
            LoadParty(partyCode);
        }

        private void LoadParty(string code)
        {
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "SELECT * FROM Master1 WHERE Code=@c AND MasterType='Account'";
            cmd.Parameters.AddWithValue("@c", code);
            using var r = cmd.ExecuteReader();
            if (r.Read())
            {
                txtName.Text = r["Name"]?.ToString() ?? "";
                txtAlias.Text = r["AliasName"]?.ToString() ?? "";
                txtPrintName.Text = r["PrintName"]?.ToString() ?? "";
                txtGSTIN.Text = r["GSTIN"]?.ToString() ?? "";
                txtPAN.Text = r["PAN"]?.ToString() ?? "";
                txtTel.Text = r["Phone"]?.ToString() ?? "";
                txtMobile.Text = r["Mobile"]?.ToString() ?? "";
                txtEmail.Text = r["Email"]?.ToString() ?? "";
                txtAddress.Text = r["Address1"]?.ToString() ?? "";
                txtPinCode.Text = r["PinCode"]?.ToString() ?? "";
                cmbState.Text = r["State"]?.ToString() ?? "Delhi";
                txtOpBal.Text = r["OpeningBalance"]?.ToString() ?? "0";
                txtStation.Text = r["Station"]?.ToString() ?? "";
            }
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowDashboard();
        }

        private void BtnSave_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(txtName.Text))
            {
                MessageBox.Show("Party Name is required!", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
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
                        Name=@name, AliasName=@alias, PrintName=@printname, ParentGroup=@grp,
                        GSTIN=@gstin, PAN=@pan, Phone=@phone, Mobile=@mobile, Email=@email,
                        Address1=@addr, PinCode=@pin, State=@state, Station=@station,
                        OpeningBalance=@opbal, PartyType=@dealertype,
                        UpdatedAt=datetime('now')
                        WHERE Code=@c AND MasterType='Account'";
                    BindParams(cmd, code);
                    cmd.ExecuteNonQuery();
                }
                else
                {
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = @"INSERT INTO Master1 
                        (Code, Name, AliasName, PrintName, MasterType, ParentGroup,
                         GSTIN, PAN, Phone, Mobile, Email, Address1, PinCode, State, Station,
                         OpeningBalance, PartyType, IsActive, CreatedAt, UpdatedAt)
                        VALUES 
                        (@code, @name, @alias, @printname, 'Account', @grp,
                         @gstin, @pan, @phone, @mobile, @email, @addr, @pin, @state, @station,
                         @opbal, @dealertype, 1, datetime('now'), datetime('now'))";
                    BindParams(cmd, code);
                    cmd.ExecuteNonQuery();
                }

                txn.Commit();
                MessageBox.Show(_editCode != null ? "Party updated!" : "Party saved!", "Success",
                    MessageBoxButton.OK, MessageBoxImage.Information);
                _dashboard?.ShowDashboard();
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void BindParams(SqliteCommand cmd, string code)
        {
            cmd.Parameters.AddWithValue("@code", code);
            cmd.Parameters.AddWithValue("@name", txtName.Text.Trim());
            cmd.Parameters.AddWithValue("@alias", txtAlias.Text.Trim());
            cmd.Parameters.AddWithValue("@printname", txtPrintName.Text.Trim());
            cmd.Parameters.AddWithValue("@grp", cmbGroup.Text);
            cmd.Parameters.AddWithValue("@gstin", txtGSTIN.Text.Trim());
            cmd.Parameters.AddWithValue("@pan", txtPAN.Text.Trim());
            cmd.Parameters.AddWithValue("@phone", txtTel.Text.Trim());
            cmd.Parameters.AddWithValue("@mobile", txtMobile.Text.Trim());
            cmd.Parameters.AddWithValue("@email", txtEmail.Text.Trim());
            cmd.Parameters.AddWithValue("@addr", txtAddress.Text.Trim());
            cmd.Parameters.AddWithValue("@pin", txtPinCode.Text.Trim());
            cmd.Parameters.AddWithValue("@state", cmbState.Text);
            cmd.Parameters.AddWithValue("@station", txtStation.Text.Trim());
            cmd.Parameters.AddWithValue("@opbal", double.TryParse(txtOpBal.Text, out double ob) ? ob : 0);
            cmd.Parameters.AddWithValue("@dealertype", cmbDealerType.Text);
        }

        private string GenerateCode(SqliteConnection conn)
        {
            // Device tag ke saath code — 10 counters ek hi code ("P00001") na banayein
            string prefix = "P" + RashanKiDukan.Services.DeviceContext.Short + "-";
            using var cmd = conn.CreateCommand();
            cmd.CommandText = @"SELECT IFNULL(MAX(CAST(SUBSTR(Code, @len) AS INTEGER)),0)+1
                                FROM Master1 WHERE MasterType='Account' AND Code LIKE @p";
            cmd.Parameters.AddWithValue("@len", prefix.Length + 1);
            cmd.Parameters.AddWithValue("@p", prefix + "%");
            long next = (long)cmd.ExecuteScalar();
            return prefix + next.ToString().PadLeft(5, '0');
        }

        private void ToggleBillByBill(object sender, MouseButtonEventArgs e)
        {
            _billByBill = !_billByBill;
            billByBillKnob.HorizontalAlignment = _billByBill ? HorizontalAlignment.Right : HorizontalAlignment.Left;
            ((Border)sender).Background = new System.Windows.Media.SolidColorBrush(
                _billByBill ? System.Windows.Media.Color.FromRgb(34, 197, 94) : System.Windows.Media.Color.FromRgb(209, 213, 219));
        }

        private void ToggleRegulatory(object sender, MouseButtonEventArgs e)
        {
            if (pnlRegulatory.Visibility == Visibility.Visible)
            {
                pnlRegulatory.Visibility = Visibility.Collapsed;
                arrowReg.Text = "\uE096";
            }
            else
            {
                pnlRegulatory.Visibility = Visibility.Visible;
                arrowReg.Text = "\uE094";
            }
        }

        private void ToggleMoreSection(object sender, MouseButtonEventArgs e)
        {
            if (pnlMore.Visibility == Visibility.Visible)
            {
                pnlMore.Visibility = Visibility.Collapsed;
                arrowMore.Text = "\uE096";
            }
            else
            {
                pnlMore.Visibility = Visibility.Visible;
                arrowMore.Text = "\uE094";
            }
        }

        private void ToggleDefSaleType(object sender, MouseButtonEventArgs e)
        {
            _defSaleType = !_defSaleType;
            defSaleTypeKnob.HorizontalAlignment = _defSaleType ? HorizontalAlignment.Right : HorizontalAlignment.Left;
            ((Border)sender).Background = new System.Windows.Media.SolidColorBrush(
                _defSaleType ? System.Windows.Media.Color.FromRgb(59, 130, 246) : System.Windows.Media.Color.FromRgb(209, 213, 219));
        }

        private void ToggleDefPurcType(object sender, MouseButtonEventArgs e)
        {
            _defPurcType = !_defPurcType;
            defPurcTypeKnob.HorizontalAlignment = _defPurcType ? HorizontalAlignment.Right : HorizontalAlignment.Left;
            ((Border)sender).Background = new System.Windows.Media.SolidColorBrush(
                _defPurcType ? System.Windows.Media.Color.FromRgb(59, 130, 246) : System.Windows.Media.Color.FromRgb(209, 213, 219));
        }

        private async void BtnValidateGSTIN_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(txtGSTIN.Text))
            {
                MessageBox.Show("Please enter a GSTIN to validate.", "Validate GSTIN", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }

            var result = await Services.GstValidationService.VerifyGstinOnlineAsync(txtGSTIN.Text.Trim());
            if (result.IsValid && !string.IsNullOrEmpty(result.LegalName))
            {
                string info = $"✅ GSTIN Verified!\n\n" +
                              $"📋 Legal Name: {result.LegalName}\n" +
                              (string.IsNullOrEmpty(result.TradeName) ? "" : $"🏪 Trade Name: {result.TradeName}\n") +
                              $"📍 State: {result.StateName} (Code: {result.StateCode:D2})\n" +
                              $"📌 Status: {result.Status}\n" +
                              (string.IsNullOrEmpty(result.Address) ? "" : $"🏠 Address: {result.Address}");
                MessageBox.Show(info, "GSTIN Verified", MessageBoxButton.OK, MessageBoxImage.Information);
            }
            else if (result.IsValid)
            {
                var offline = Services.GstValidationService.ValidateGstin(txtGSTIN.Text.Trim());
                MessageBox.Show($"✅ GSTIN format valid\n\nState: {offline.StateName}\nPAN: {offline.Pan}\nEntity: {offline.EntityType}\n\n⚠️ Business name not available (no internet/API limit)",
                    "GSTIN Valid", MessageBoxButton.OK, MessageBoxImage.Information);
            }
            else
            {
                MessageBox.Show($"❌ Invalid GSTIN\n\n{result.Message}", "GSTIN Validation Failed",
                    MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }
    }
}
