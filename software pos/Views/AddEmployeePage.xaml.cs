using System;
using System.Collections.Generic;
using System.IO;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media.Imaging;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views;

public partial class AddEmployeePage : UserControl
{
    private MainDashboard? _dashboard;
    private readonly DatabaseService _db = new();
    private long _editId;
    private string? _photoPath;

    public AddEmployeePage(MainDashboard? dashboard, long editId = 0)
    {
        InitializeComponent();
        _dashboard = dashboard;
        _editId = editId;
        LoadDropdowns();
        if (_editId > 0) LoadEmployee();
    }

    private void LoadDropdowns()
    {
        using var conn = _db.GetConnection();
        conn.Open();

        // Designation (roles)
        var roleCmd = conn.CreateCommand();
        roleCmd.CommandText = "SELECT id, name FROM roles ORDER BY name";
        using (var r = roleCmd.ExecuteReader())
        {
            while (r.Read())
                cmbDesignation.Items.Add(new ComboEntry { Id = r.GetInt64(0), Name = r.GetString(1) });
        }
        if (cmbDesignation.Items.Count > 0) cmbDesignation.SelectedIndex = 0;

        // Outlet
        var outCmd = conn.CreateCommand();
        outCmd.CommandText = "SELECT id, COALESCE(name,'') || ' ' || COALESCE(outlet_name,'') AS display FROM outlets WHERE del_status IS NULL OR del_status='Live' ORDER BY name";
        using (var r = outCmd.ExecuteReader())
        {
            while (r.Read())
                cmbOutlet.Items.Add(new ComboEntry { Id = r.GetInt64(0), Name = r.GetString(1) });
        }
        if (cmbOutlet.Items.Count > 0) cmbOutlet.SelectedIndex = 0;
    }

    private void LoadEmployee()
    {
        txtTitle.Text = "Edit Employee";
        txtBreadcrumbAction.Text = "Edit Employee";

        using var conn = _db.GetConnection();
        conn.Open();
        var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT name, COALESCE(email,'') AS email, COALESCE(phone,'') AS phone, COALESCE(role,'') AS role, salary, commission, will_login, start_date, end_date, discount_permission_code, discount_amt, outlet_id, COALESCE(photo,'') AS photo FROM employees WHERE id=@id";
        cmd.Parameters.AddWithValue("@id", _editId);
        using var r = cmd.ExecuteReader();
        if (!r.Read()) return;

        txtName.Text = r.IsDBNull(0) ? "" : r.GetString(0);
        txtEmail.Text = r.IsDBNull(1) ? "" : r.GetString(1);
        txtPhone.Text = r.IsDBNull(2) ? "" : r.GetString(2);
        string role = r.IsDBNull(3) ? "" : r.GetString(3);
        txtSalary.Text = r.IsDBNull(4) ? "" : r.GetDouble(4).ToString();
        txtCommission.Text = r.IsDBNull(5) ? "" : r.GetDouble(5).ToString();
        string willLogin = r.IsDBNull(6) ? "Yes" : r.GetString(6);
        if (!r.IsDBNull(7) && DateTime.TryParse(r.GetString(7), out var sd)) dpStartDate.SelectedDate = sd;
        if (!r.IsDBNull(8) && DateTime.TryParse(r.GetString(8), out var ed)) dpEndDate.SelectedDate = ed;
        txtDiscountCode.Text = r.IsDBNull(9) ? "" : r.GetString(9);
        txtDiscountAmt.Text = r.IsDBNull(10) ? "" : r.GetDouble(10).ToString();
        string outletId = r.IsDBNull(11) ? "" : r.GetString(11);
        _photoPath = r.IsDBNull(12) ? "" : r.GetString(12);

        // Select designation (cloud rows store the role id, legacy local rows the name)
        for (int i = 0; i < cmbDesignation.Items.Count; i++)
            if (cmbDesignation.Items[i] is ComboEntry ci &&
                (ci.Id.ToString() == role || ci.Name == role))
            { cmbDesignation.SelectedIndex = i; break; }

        // Select outlet
        if (long.TryParse(outletId, out var oid))
            for (int i = 0; i < cmbOutlet.Items.Count; i++)
                if (cmbOutlet.Items[i] is ComboEntry ci && ci.Id == oid)
                { cmbOutlet.SelectedIndex = i; break; }

        // Will Login
        rbLoginYes.IsChecked = willLogin == "Yes";
        rbLoginNo.IsChecked = willLogin == "No";
        txtPassword.Text = "";
        txtConfirmPassword.Text = "";

        // Photo preview
        if (!string.IsNullOrEmpty(_photoPath) && File.Exists(_photoPath))
        {
            imgPreview.Source = new BitmapImage(new Uri(_photoPath, UriKind.Absolute));
            photoBorder.Visibility = Visibility.Visible;
            txtFileName.Text = Path.GetFileName(_photoPath);
        }
    }

    private void BtnChoosePhoto_Click(object sender, RoutedEventArgs e)
    {
        var dlg = new Microsoft.Win32.OpenFileDialog
        {
            Filter = "Image Files|*.jpg;*.jpeg;*.png;*.gif;*.bmp",
            Title = "Select Employee Photo"
        };
        if (dlg.ShowDialog() == true)
        {
            _photoPath = dlg.FileName;
            txtFileName.Text = Path.GetFileName(_photoPath);
            imgPreview.Source = new BitmapImage(new Uri(_photoPath, UriKind.Absolute));
            photoBorder.Visibility = Visibility.Visible;
        }
    }

    private void BtnRemovePhoto_Click(object sender, RoutedEventArgs e)
    {
        _photoPath = null;
        imgPreview.Source = null;
        photoBorder.Visibility = Visibility.Collapsed;
        txtFileName.Text = "No file chosen";
    }

    private void BtnSubmit_Click(object sender, RoutedEventArgs e)
    {
        // Validation
        if (string.IsNullOrWhiteSpace(txtName.Text))
        { MessageBox.Show("Please enter Name.", "Validation"); return; }
        if (string.IsNullOrWhiteSpace(txtEmail.Text))
        { MessageBox.Show("Please enter Username, Email or Phone.", "Validation"); return; }
        if (string.IsNullOrWhiteSpace(txtPhone.Text))
        { MessageBox.Show("Please enter Phone.", "Validation"); return; }
        if (cmbDesignation.SelectedItem == null)
        { MessageBox.Show("Please select Designation.", "Validation"); return; }
        if (cmbOutlet.SelectedItem == null)
        { MessageBox.Show("Please select Outlet.", "Validation"); return; }

        string roleId = cmbDesignation.SelectedItem is ComboEntry rc ? rc.Id.ToString() : "";
        long outletId = cmbOutlet.SelectedItem is ComboEntry oc ? oc.Id : 0;
        string willLogin = rbLoginYes.IsChecked == true ? "Yes" : "No";
        string newPass = txtPassword.Text ?? "";
        string confirmPass = txtConfirmPassword.Text ?? "";

        if (!string.IsNullOrEmpty(newPass) && newPass != confirmPass)
        { MessageBox.Show("Password and Confirm Password do not match.", "Validation"); return; }
        if (willLogin == "Yes" && string.IsNullOrEmpty(newPass) && _editId == 0)
        { MessageBox.Show("Password is required when the employee will login.", "Validation"); return; }

        try
        {
            using var conn = _db.GetConnection();
            conn.Open();
            using var tx = conn.BeginTransaction();

            // Reuse the stored BCrypt hash when no new password was typed
            string? passwordHash = null;
            if (!string.IsNullOrEmpty(newPass))
                passwordHash = BCrypt.Net.BCrypt.HashPassword(newPass);
            else if (_editId > 0)
            {
                var q = conn.CreateCommand();
                q.Transaction = tx;
                q.CommandText = "SELECT COALESCE(password,'') FROM employees WHERE id=@id";
                q.Parameters.AddWithValue("@id", _editId);
                string s = q.ExecuteScalar() as string ?? "";
                if (!string.IsNullOrEmpty(s)) passwordHash = s;
            }
            else
            {
                // will_login=No — cloud users.password is NOT NULL, so store a random hash
                passwordHash = BCrypt.Net.BCrypt.HashPassword("no-login-" + Guid.NewGuid().ToString("N"));
            }

            string sql;
            if (_editId > 0)
            {
                sql = passwordHash == null
                    ? @"UPDATE employees SET name=@name, email=@email, phone=@phone, role=@role,
                        salary=@salary, commission=@commission, will_login=@will_login,
                        start_date=@start_date, end_date=@end_date,
                        discount_permission_code=@discount_code, discount_amt=@discount_amt,
                        outlet_id=@outlet_id, photo=@photo, updated_at=@now WHERE id=@id"
                    : @"UPDATE employees SET name=@name, email=@email, phone=@phone, role=@role,
                        salary=@salary, commission=@commission, will_login=@will_login,
                        start_date=@start_date, end_date=@end_date,
                        discount_permission_code=@discount_code, discount_amt=@discount_amt,
                        outlet_id=@outlet_id, photo=@photo, password=@password, updated_at=@now WHERE id=@id";
            }
            else
            {
                sql = @"INSERT INTO employees (name, email, phone, role, salary, commission, will_login,
                        start_date, end_date, discount_permission_code, discount_amt,
                        outlet_id, photo, password, del_status, created_at, updated_at)
                        VALUES (@name, @email, @phone, @role, @salary, @commission, @will_login,
                        @start_date, @end_date, @discount_code, @discount_amt,
                        @outlet_id, @photo, @password, 'Live', @now, @now)";
            }

            var cmd = conn.CreateCommand();
            cmd.Transaction = tx;
            cmd.CommandText = sql;
            cmd.Parameters.AddWithValue("@name", txtName.Text?.Trim() ?? "");
            cmd.Parameters.AddWithValue("@email", txtEmail.Text?.Trim() ?? "");
            cmd.Parameters.AddWithValue("@phone", txtPhone.Text?.Trim() ?? "");
            cmd.Parameters.AddWithValue("@role", roleId);
            cmd.Parameters.AddWithValue("@salary", double.TryParse(txtSalary.Text, out var sal) ? sal : 0);
            cmd.Parameters.AddWithValue("@commission", double.TryParse(txtCommission.Text, out var com) ? com : 0);
            cmd.Parameters.AddWithValue("@will_login", willLogin);
            cmd.Parameters.AddWithValue("@start_date", dpStartDate.SelectedDate?.ToString("yyyy-MM-dd") ?? "");
            cmd.Parameters.AddWithValue("@end_date", dpEndDate.SelectedDate?.ToString("yyyy-MM-dd") ?? "");
            cmd.Parameters.AddWithValue("@discount_code", txtDiscountCode.Text?.Trim() ?? "");
            cmd.Parameters.AddWithValue("@discount_amt", double.TryParse(txtDiscountAmt.Text, out var da) ? da : 0);
            cmd.Parameters.AddWithValue("@outlet_id", outletId.ToString());
            cmd.Parameters.AddWithValue("@photo", _photoPath ?? "");
            cmd.Parameters.AddWithValue("@password", passwordHash ?? "");
            cmd.Parameters.AddWithValue("@now", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));

            if (_editId > 0)
                cmd.Parameters.AddWithValue("@id", _editId);

            cmd.ExecuteNonQuery();

            long id = _editId;
            if (id == 0)
            {
                var idCmd = conn.CreateCommand();
                idCmd.Transaction = tx;
                idCmd.CommandText = "SELECT last_insert_rowid()";
                id = Convert.ToInt64(idCmd.ExecuteScalar());
            }
            tx.Commit();

            // Push to cloud via the offline sync queue (Laravel users table)
            var payload = new Dictionary<string, object?>
            {
                ["name"] = txtName.Text?.Trim() ?? "",
                ["email"] = txtEmail.Text?.Trim() ?? "",
                ["phone"] = txtPhone.Text?.Trim() ?? "",
                ["role"] = roleId,
                ["salary"] = double.TryParse(txtSalary.Text, out var sal2) ? sal2 : 0,
                ["commission"] = double.TryParse(txtCommission.Text, out var com2) ? com2 : 0,
                ["outlet_id"] = outletId.ToString(),
                ["will_login"] = willLogin,
                ["start_date"] = dpStartDate.SelectedDate?.ToString("yyyy-MM-dd") ?? "",
                ["end_date"] = dpEndDate.SelectedDate?.ToString("yyyy-MM-dd") ?? "",
                ["discount_permission_code"] = txtDiscountCode.Text?.Trim() ?? "",
                ["discount_amt"] = double.TryParse(txtDiscountAmt.Text, out var da2) ? da2 : 0,
                ["photo"] = _photoPath ?? ""
            };
            if (passwordHash != null) payload["password"] = passwordHash;

            SyncService.EnqueueSync("users", id, _editId > 0 ? "update" : "insert",
                System.Text.Json.JsonSerializer.Serialize(payload));
            SyncService.MarkLocalPending("employees", id);

            MessageBox.Show(_editId > 0 ? "Employee updated successfully!" : "Employee created successfully!", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
            _dashboard?.ShowPage(new EmployeeListPage(_dashboard!));
        }
        catch (Exception ex)
        {
            MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
        }
    }

    private void BtnBack_Click(object sender, RoutedEventArgs e)
    {
        if (_dashboard != null) _dashboard.ShowPage(new EmployeeListPage(_dashboard));
    }
}

public class ComboEntry
{
    public long Id { get; set; }
    public string Name { get; set; } = "";
}
