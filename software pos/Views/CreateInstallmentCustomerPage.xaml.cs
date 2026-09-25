using System.Windows;
using System.Windows.Controls;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class CreateInstallmentCustomerPage : UserControl
    {
        private readonly DatabaseService _db = new();
        private readonly MainDashboard? _dashboard;
        private long _editId = 0;
        private string _nidPath = "", _photoPath = "", _gNidPath = "", _gPhotoPath = "";

        public CreateInstallmentCustomerPage() { InitializeComponent(); }
        public CreateInstallmentCustomerPage(MainDashboard dashboard) : this()
        {
            _dashboard = dashboard;
        }
        public CreateInstallmentCustomerPage(MainDashboard dashboard, long customerId) : this()
        {
            _dashboard = dashboard;
            LoadForEdit(customerId);
        }

        private void LoadForEdit(long id)
        {
            try
            {
                _editId = id;
                lblTitle.Text = "Edit Installment Customer";
                btnSave.Content = "\uE73E  Update";

                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT name, phone, email, address, opening_balance,
                                           opening_balance_type, work_address,
                                           guarantor_name, guarantor_mobile,
                                           guarantor_present_address, guarantor_work_address,
                                           customer_nid, customer_photo,
                                           guarantor_nid, guarantor_photo,
                                           date_of_birth, date_of_anniversary
                                    FROM customers WHERE id=@id";
                cmd.Parameters.AddWithValue("@id", id);
                using var r = cmd.ExecuteReader();
                if (!r.Read()) return;

                txtName.Text            = r["name"]?.ToString() ?? "";
                txtPhone.Text           = r["phone"]?.ToString() ?? "";
                txtEmail.Text           = r["email"]?.ToString() ?? "";
                txtAddress.Text         = r["address"]?.ToString() ?? "";
                txtPermAddress.Text     = r["address"]?.ToString() ?? "";
                txtWorkAddress.Text     = r["work_address"]?.ToString() ?? "";

                double ob = r["opening_balance"] is double v ? v : 0;
                txtOpeningBalance.Text  = ob != 0 ? Math.Abs(ob).ToString("F2") : "";
                string obType = r["opening_balance_type"]?.ToString() ?? "Dr";
                cmbDrCr.SelectedIndex   = obType == "Cr" ? 1 : 0;

                txtGName.Text           = r["guarantor_name"]?.ToString() ?? "";
                txtGMobile.Text         = r["guarantor_mobile"]?.ToString() ?? "";
                txtGPermAddress.Text    = r["guarantor_present_address"]?.ToString() ?? "";
                txtGWorkAddress.Text    = r["guarantor_work_address"]?.ToString() ?? "";

                _nidPath    = r["customer_nid"]?.ToString() ?? "";
                _photoPath  = r["customer_photo"]?.ToString() ?? "";
                _gNidPath   = r["guarantor_nid"]?.ToString() ?? "";
                _gPhotoPath = r["guarantor_photo"]?.ToString() ?? "";
                if (_nidPath != "") lblNID.Text = System.IO.Path.GetFileName(_nidPath);
                if (_photoPath != "") lblPhoto.Text = System.IO.Path.GetFileName(_photoPath);
                if (_gNidPath != "") lblGNID.Text = System.IO.Path.GetFileName(_gNidPath);
                if (_gPhotoPath != "") lblGPhoto.Text = System.IO.Path.GetFileName(_gPhotoPath);

                string dob = r["date_of_birth"]?.ToString() ?? "";
                if (DateTime.TryParse(dob, out var d1)) dtpDOB.SelectedDate = d1;
                string ann = r["date_of_anniversary"]?.ToString() ?? "";
                if (DateTime.TryParse(ann, out var d2)) dtpAnniversary.SelectedDate = d2;
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error loading customer: " + ex.Message, "Error",
                    MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void BtnBrowseNID_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new Microsoft.Win32.OpenFileDialog { Filter = "Image files|*.jpg;*.jpeg;*.png;*.gif|All files|*.*" };
            if (dlg.ShowDialog() == true) { _nidPath = dlg.FileName; lblNID.Text = System.IO.Path.GetFileName(dlg.FileName); }
        }
        private void BtnBrowsePhoto_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new Microsoft.Win32.OpenFileDialog { Filter = "Image files|*.jpg;*.jpeg;*.png;*.gif|All files|*.*" };
            if (dlg.ShowDialog() == true) { _photoPath = dlg.FileName; lblPhoto.Text = System.IO.Path.GetFileName(dlg.FileName); }
        }
        private void BtnBrowseGNID_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new Microsoft.Win32.OpenFileDialog { Filter = "Image files|*.jpg;*.jpeg;*.png;*.gif|All files|*.*" };
            if (dlg.ShowDialog() == true) { _gNidPath = dlg.FileName; lblGNID.Text = System.IO.Path.GetFileName(dlg.FileName); }
        }
        private void BtnBrowseGPhoto_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new Microsoft.Win32.OpenFileDialog { Filter = "Image files|*.jpg;*.jpeg;*.png;*.gif|All files|*.*" };
            if (dlg.ShowDialog() == true) { _gPhotoPath = dlg.FileName; lblGPhoto.Text = System.IO.Path.GetFileName(dlg.FileName); }
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowPage(new InstallmentCustomersPage(_dashboard!));
        }

        private void BtnSave_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(txtName.Text))
            {
                MessageBox.Show("Please enter customer name.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                txtName.Focus(); return;
            }
            if (string.IsNullOrWhiteSpace(txtPhone.Text))
            {
                MessageBox.Show("Please enter phone number.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                txtPhone.Focus(); return;
            }

            try
            {
                double opening = 0;
                double.TryParse(txtOpeningBalance.Text, out opening);
                if (cmbDrCr.SelectedIndex == 1) opening = -opening;

                string phone = txtPhone.Text.Trim();
                if (phone.StartsWith("+91-")) phone = phone[4..];
                else if (phone.StartsWith("+91")) phone = phone[3..];

                string dob = dtpDOB.SelectedDate?.ToString("yyyy-MM-dd") ?? "";
                string ann = dtpAnniversary.SelectedDate?.ToString("yyyy-MM-dd") ?? "";

                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();
                using var cmd = conn.CreateCommand();

                if (_editId > 0)
                {
                    cmd.CommandText = @"UPDATE customers SET
                        name=@name, phone=@phone, email=@email, address=@addr,
                        opening_balance=@ob, opening_balance_type=@obt,
                        work_address=@work, guarantor_name=@gname, guarantor_mobile=@gmobile,
                        guarantor_present_address=@gperm, guarantor_work_address=@gwork,
                        customer_nid=@nid, customer_photo=@photo,
                        guarantor_nid=@gnid, guarantor_photo=@gphoto,
                        date_of_birth=@dob, date_of_anniversary=@ann,
                        is_installment_customer='Yes', updated_at=datetime('now')
                        WHERE id=@id";
                    cmd.Parameters.AddWithValue("@id", _editId);
                }
                else
                {
                    cmd.CommandText = @"INSERT INTO customers
                        (name, phone, email, address, opening_balance, opening_balance_type,
                         work_address, guarantor_name, guarantor_mobile,
                         guarantor_present_address, guarantor_work_address,
                         customer_nid, customer_photo, guarantor_nid, guarantor_photo,
                         date_of_birth, date_of_anniversary,
                         is_installment_customer, del_status, company_id,
                         created_at, updated_at)
                        VALUES
                        (@name,@phone,@email,@addr,@ob,@obt,
                         @work,@gname,@gmobile,@gperm,@gwork,
                         @nid,@photo,@gnid,@gphoto,@dob,@ann,
                         'Yes','Live',1,datetime('now'),datetime('now'))";
                }

                cmd.Parameters.AddWithValue("@name",   txtName.Text.Trim());
                cmd.Parameters.AddWithValue("@phone",  phone);
                cmd.Parameters.AddWithValue("@email",  txtEmail.Text.Trim());
                cmd.Parameters.AddWithValue("@addr",   txtAddress.Text.Trim());
                cmd.Parameters.AddWithValue("@ob",     opening);
                cmd.Parameters.AddWithValue("@obt",    cmbDrCr.Text == "Credit" ? "Cr" : "Dr");
                cmd.Parameters.AddWithValue("@work",   txtWorkAddress.Text.Trim());
                cmd.Parameters.AddWithValue("@gname",  txtGName.Text.Trim());
                cmd.Parameters.AddWithValue("@gmobile",txtGMobile.Text.Trim());
                cmd.Parameters.AddWithValue("@gperm",  txtGPermAddress.Text.Trim());
                cmd.Parameters.AddWithValue("@gwork",  txtGWorkAddress.Text.Trim());
                cmd.Parameters.AddWithValue("@nid",    _nidPath);
                cmd.Parameters.AddWithValue("@photo",  _photoPath);
                cmd.Parameters.AddWithValue("@gnid",   _gNidPath);
                cmd.Parameters.AddWithValue("@gphoto", _gPhotoPath);
                cmd.Parameters.AddWithValue("@dob",    dob);
                cmd.Parameters.AddWithValue("@ann",    ann);
                cmd.ExecuteNonQuery();

                txn.Commit();
                MessageBox.Show("Installment customer saved successfully!", "Success",
                    MessageBoxButton.OK, MessageBoxImage.Information);
                _dashboard?.ShowPage(new InstallmentCustomersPage(_dashboard!));
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }
    }
}
