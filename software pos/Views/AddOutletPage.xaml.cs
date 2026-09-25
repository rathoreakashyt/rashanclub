using System;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views
{
    public partial class AddOutletPage : UserControl
    {
        private readonly DatabaseService _db = new DatabaseService();
        private readonly MainDashboard? _dashboard;
        private readonly bool _isEditMode;
        private readonly int _editId;
        private static readonly FontFamily ProFont = new FontFamily("Inter, Segoe UI");

        public AddOutletPage() { InitializeComponent(); }

        public AddOutletPage(MainDashboard dashboard) : this()
        {
            _dashboard = dashboard;
            LoadStates();
            LoadDefaultValues();
        }

        public AddOutletPage(MainDashboard dashboard, int outletId) : this()
        {
            _dashboard = dashboard;
            _isEditMode = true;
            _editId = outletId;
            cmbStatus.ItemsSource = new[] { "Active", "Inactive" };
            LoadStates();
            LoadOutletData(outletId);
        }

        private void LoadDefaultValues()
        {
            txtOutletCode.Text = GenerateOutletCode();
            cmbStatus.ItemsSource = new[] { "Active", "Inactive" };
            cmbStatus.SelectedIndex = 0;
        }

        private string GenerateOutletCode()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT COUNT(*) FROM outlets WHERE del_status IS NULL OR del_status != 'Deleted'";
                var count = Convert.ToInt64(cmd.ExecuteScalar());
                return (count + 1).ToString("D6");
            }
            catch { return "000001"; }
        }

        private void LoadStates()
        {
            var states = new List<StateItem>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT Id, state_name FROM states ORDER BY state_name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    states.Add(new StateItem
                    {
                        Id = Convert.ToInt32(r["Id"]),
                        Name = r["state_name"]?.ToString() ?? ""
                    });
                }
            }
            catch { }

            cmbState.ItemsSource = states;
            cmbState.DisplayMemberPath = "Name";
            cmbState.SelectedValuePath = "Id";
            SelectDelhi(states);
        }

        private void SelectDelhi(List<StateItem> states)
        {
            var delhi = states.FirstOrDefault(s => string.Equals(s.Name, "Delhi", StringComparison.OrdinalIgnoreCase));
            cmbState.SelectedValue = delhi?.Id ?? (states.Count > 0 ? states[0].Id : (int?)null);
        }

        private void LoadOutletData(int id)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT * FROM outlets WHERE id=@id";
                cmd.Parameters.AddWithValue("@id", id);
                using var r = cmd.ExecuteReader();
                if (r.Read())
                {
                    txtOutletCode.Text = r["outlet_code"]?.ToString() ?? "";
                    txtOutletName.Text = r["outlet_name"]?.ToString() ?? r["name"]?.ToString() ?? "";
                    txtPhone.Text = r["phone"]?.ToString() ?? "";
                    txtEmail.Text = r["email"]?.ToString() ?? "";
                    txtAddress.Text = r["address"]?.ToString() ?? "";

                    if (r["state_id"] != null && r["state_id"] != DBNull.Value)
                    {
                        var stateId = Convert.ToInt32(r["state_id"]);
                        cmbState.SelectedValue = stateId;
                    }

                    var status = r["active_status"]?.ToString() ?? "Active";
                    var match = cmbStatus.Items.Cast<object>().FirstOrDefault(i => string.Equals(i?.ToString(), status, StringComparison.OrdinalIgnoreCase));
                    cmbStatus.SelectedItem = match ?? "Active";
                }
            }
            catch { }
        }

        private void BtnSubmit_Click(object sender, RoutedEventArgs e)
        {
            if (!ValidateForm()) return;

            var outletCode = txtOutletCode.Text.Trim();
            var outletName = txtOutletName.Text.Trim();
            var phone = txtPhone.Text.Trim();
            var email = txtEmail.Text.Trim();
            var address = txtAddress.Text.Trim();
            var stateId = cmbState.SelectedValue is int sid ? sid : 0;
            var status = cmbStatus.SelectedItem?.ToString() ?? "Active";
            var now = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");

            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();

                if (_isEditMode)
                {
                    cmd.CommandText = @"UPDATE outlets SET 
                        outlet_code=@code, outlet_name=@outlet_name, name=@name,
                        phone=@phone, email=@email, address=@address,
                        state_id=@state_id, active_status=@status,
                        updated_at=@now, del_status='Live', SyncStatus='Local'
                        WHERE id=@id";
                    cmd.Parameters.AddWithValue("@id", _editId);
                }
                else
                {
                    cmd.CommandText = @"INSERT INTO outlets 
                        (outlet_code, outlet_name, name, phone, email, address, 
                         state_id, active_status, is_active, created_at, updated_at, del_status, SyncStatus)
                        VALUES (@code, @outlet_name, @name, @phone, @email, @address,
                                @state_id, @status, @is_active, @now, @now, 'Live', 'Local')";
                }

                cmd.Parameters.AddWithValue("@code", outletCode);
                cmd.Parameters.AddWithValue("@outlet_name", outletName);
                cmd.Parameters.AddWithValue("@name", outletName);
                cmd.Parameters.AddWithValue("@phone", phone);
                cmd.Parameters.AddWithValue("@email", email);
                cmd.Parameters.AddWithValue("@address", address);
                cmd.Parameters.AddWithValue("@state_id", stateId);
                cmd.Parameters.AddWithValue("@status", status);
                cmd.Parameters.AddWithValue("@is_active", status == "Active" ? 1 : 0);
                cmd.Parameters.AddWithValue("@now", now);
                cmd.ExecuteNonQuery();

                // Sync cycle (20s) ab outlets ko khud push karega —
                // fire-and-forget push hata diya (offline par retry kabhi nahi hota tha).
                ShowStatus("Outlet saved successfully! It will sync to cloud automatically.", true);

                if (!_isEditMode)
                {
                    ClearForm();
                    txtOutletCode.Text = GenerateOutletCode();
                }
                else
                {
                    // Edit ke baad list pe wapas jao taaki update dikhe
                    _dashboard?.ShowPage(new ListOutletPage(_dashboard!));
                }
            }
            catch (Exception ex)
            {
                ShowStatus("Error: " + ex.Message, false);
            }
        }

        private bool ValidateForm()
        {
            if (string.IsNullOrWhiteSpace(txtOutletCode.Text))
            {
                ShowStatus("Outlet Code is required", false);
                txtOutletCode.Focus();
                return false;
            }
            if (string.IsNullOrWhiteSpace(txtOutletName.Text))
            {
                ShowStatus("Outlet Name is required", false);
                txtOutletName.Focus();
                return false;
            }
            if (string.IsNullOrWhiteSpace(txtPhone.Text))
            {
                ShowStatus("Phone is required", false);
                txtPhone.Focus();
                return false;
            }
            if (cmbState.SelectedValue == null)
            {
                ShowStatus("State is required", false);
                return false;
            }
            if (string.IsNullOrWhiteSpace(txtAddress.Text))
            {
                ShowStatus("Address is required", false);
                txtAddress.Focus();
                return false;
            }
            if (cmbStatus.SelectedItem == null)
            {
                ShowStatus("Status is required", false);
                return false;
            }
            return true;
        }

        private void ClearForm()
        {
            txtOutletName.Text = "";
            txtPhone.Text = "";
            txtEmail.Text = "";
            txtAddress.Text = "";
            if (cmbState.ItemsSource is List<StateItem> states) SelectDelhi(states);
            cmbStatus.SelectedIndex = 0;
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
            if (_dashboard != null)
            {
                _dashboard.ShowPage(new ListOutletPage(_dashboard));
            }
            else
            {
                _dashboard?.ShowDashboard();
            }
        }
    }

    public class StateItem
    {
        public int Id { get; set; }
        public string Name { get; set; } = "";
    }
}
