using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using Microsoft.Win32;
using RashanKiDukan.Database;
using RashanKiDukan.Models;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views;

public partial class UpdateProfilePage : UserControl
{
    private readonly MainDashboard _dashboard;
    private readonly DatabaseService _db = new();
    private readonly User _user;
    private long _employeeId;
    private string? _photoPath;

    public UpdateProfilePage(MainDashboard dashboard)
    {
        InitializeComponent();
        _dashboard = dashboard;
        _user = dashboard.CurrentUser;
        txtLoggedInAs.Text = "Logged in as: " + (_user.FullName ?? _user.Username ?? "");
        Loaded += (_, _) => LoadProfile();
    }

    // ─────────────────────────── LOADING ───────────────────────────

    private void ResolveEmployee()
    {
        using var conn = _db.GetConnection();
        conn.Open();

        // 1) Same id in employees table (employee-account login)
        var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT id FROM employees WHERE id=@id AND (del_status IS NULL OR del_status='Live') LIMIT 1";
        cmd.Parameters.AddWithValue("@id", _user.Id);
        var v = cmd.ExecuteScalar();
        if (TryParseId(v, out var eid)) { _employeeId = eid; return; }

        // 2) Fallback: match by name or email (local admin-account login)
        cmd = conn.CreateCommand();
        cmd.CommandText = @"SELECT id FROM employees
                            WHERE (LOWER(COALESCE(name,''))=LOWER(@n) OR LOWER(COALESCE(email,''))=LOWER(@u))
                              AND (del_status IS NULL OR del_status='Live') LIMIT 1";
        cmd.Parameters.AddWithValue("@n", _user.FullName ?? "");
        cmd.Parameters.AddWithValue("@u", _user.Username ?? "");
        v = cmd.ExecuteScalar();
        _employeeId = TryParseId(v, out eid) ? eid : 0;
    }

    private void LoadProfile()
    {
        ResolveEmployee();
        if (_employeeId == 0)
        {
            ShowStatus("No employee profile found for the logged in user. Contact your administrator.", false);
            btnSaveProfile.IsEnabled = btnSavePassword.IsEnabled = btnSaveSecurity.IsEnabled = false;
            btnChoosePhoto.IsEnabled = btnPreviewPhoto.IsEnabled = btnRemovePhoto.IsEnabled = false;
            return;
        }

        using var conn = _db.GetConnection();
        conn.Open();
        var cmd = conn.CreateCommand();
        cmd.CommandText = @"SELECT name, COALESCE(email,''), COALESCE(phone,''), COALESCE(photo,''),
                            COALESCE(question,'') FROM employees WHERE id=@id";
        cmd.Parameters.AddWithValue("@id", _employeeId);
        using var r = cmd.ExecuteReader();
        if (!r.Read())
        {
            ShowStatus("No employee profile found.", false);
            return;
        }

        txtName.Text = r.GetString(0);
        txtEmail.Text = r.GetString(1);
        txtPhone.Text = r.GetString(2);
        _photoPath = r.GetString(3);
        ApplyPhoto();

        string q = r.GetString(4);
        for (int i = 0; i < cmbQuestion.Items.Count; i++)
        {
            if (cmbQuestion.Items[i] is ComboBoxItem cbi &&
                string.Equals(cbi.Content?.ToString(), q, StringComparison.OrdinalIgnoreCase))
            {
                cmbQuestion.SelectedIndex = i;
                break;
            }
        }
    }

    // ─────────────────────────── SAVE PROFILE ───────────────────────────

    private void BtnUpdateProfile_Click(object sender, RoutedEventArgs e)
    {
        if (_employeeId == 0) return;
        if (string.IsNullOrWhiteSpace(txtName.Text) ||
            string.IsNullOrWhiteSpace(txtEmail.Text) ||
            string.IsNullOrWhiteSpace(txtPhone.Text))
        {
            ShowStatus("Name, Email and Phone are required.", false);
            return;
        }

        try
        {
            using var conn = _db.GetConnection();
            conn.Open();
            var cmd = conn.CreateCommand();
            cmd.CommandText = "UPDATE employees SET name=@n, email=@e, phone=@p, photo=@ph, updated_at=@now WHERE id=@id";
            cmd.Parameters.AddWithValue("@n", txtName.Text.Trim());
            cmd.Parameters.AddWithValue("@e", txtEmail.Text.Trim());
            cmd.Parameters.AddWithValue("@p", txtPhone.Text.Trim());
            cmd.Parameters.AddWithValue("@ph", _photoPath ?? "");
            cmd.Parameters.AddWithValue("@now", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
            cmd.Parameters.AddWithValue("@id", _employeeId);
            cmd.ExecuteNonQuery();

            SyncService.EnqueueSync("users", _employeeId, "update", BuildSyncPayload());
            SyncService.MarkLocalPending("employees", _employeeId);

            ShowStatus("Profile updated successfully. Changes will sync to the server.", true);
        }
        catch (Exception ex)
        {
            ShowStatus("Error: " + ex.Message, false);
        }
    }

    // ─────────────────────────── CHANGE PASSWORD ───────────────────────────

    private void BtnChangePassword_Click(object sender, RoutedEventArgs e)
    {
        if (_employeeId == 0) return;

        string current = pwdCurrent.Password ?? "";
        string np = pwdNew.Password ?? "";
        string cp = pwdConfirm.Password ?? "";

        if (string.IsNullOrEmpty(current) || string.IsNullOrEmpty(np) || string.IsNullOrEmpty(cp))
        {
            ShowStatus("All password fields are required.", false);
            return;
        }
        if (np != cp)
        {
            ShowStatus("New Password and Confirm New Password do not match.", false);
            return;
        }
        if (np.Length < 6)
        {
            ShowStatus("New password must be at least 6 characters long.", false);
            return;
        }

        try
        {
            using var conn = _db.GetConnection();
            conn.Open();

            var q = conn.CreateCommand();
            q.CommandText = "SELECT COALESCE(password,'') FROM employees WHERE id=@id";
            q.Parameters.AddWithValue("@id", _employeeId);
            string stored = q.ExecuteScalar() as string ?? "";

            if (!string.IsNullOrEmpty(stored) && !BCrypt.Net.BCrypt.Verify(current, stored))
            {
                ShowStatus("Current password is incorrect.", false);
                return;
            }

            string newHash = BCrypt.Net.BCrypt.HashPassword(np);
            var cmd = conn.CreateCommand();
            cmd.CommandText = "UPDATE employees SET password=@h, updated_at=@now WHERE id=@id";
            cmd.Parameters.AddWithValue("@h", newHash);
            cmd.Parameters.AddWithValue("@now", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
            cmd.Parameters.AddWithValue("@id", _employeeId);
            cmd.ExecuteNonQuery();

            SyncService.EnqueueSync("users", _employeeId, "update", BuildSyncPayload(newHash));
            SyncService.MarkLocalPending("employees", _employeeId);

            pwdCurrent.Password = "";
            pwdNew.Password = "";
            pwdConfirm.Password = "";
            ShowStatus("Password changed successfully. It will sync to the server.", true);
        }
        catch (Exception ex)
        {
            ShowStatus("Error: " + ex.Message, false);
        }
    }

    // ─────────────────────────── SECURITY SETTINGS ───────────────────────────

    private void BtnUpdateSecurity_Click(object sender, RoutedEventArgs e)
    {
        if (_employeeId == 0) return;

        string q = (cmbQuestion.SelectedItem as ComboBoxItem)?.Content?.ToString() ?? "";
        string ans = txtAnswer.Text?.Trim() ?? "";
        if (string.IsNullOrEmpty(q) || string.IsNullOrEmpty(ans))
        {
            ShowStatus("Please select a security question and enter an answer.", false);
            return;
        }

        try
        {
            using var conn = _db.GetConnection();
            conn.Open();
            var cmd = conn.CreateCommand();
            cmd.CommandText = "UPDATE employees SET question=@q, answer=@a, updated_at=@now WHERE id=@id";
            cmd.Parameters.AddWithValue("@q", q);
            cmd.Parameters.AddWithValue("@a", ans);
            cmd.Parameters.AddWithValue("@now", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
            cmd.Parameters.AddWithValue("@id", _employeeId);
            cmd.ExecuteNonQuery();

            SyncService.EnqueueSync("users", _employeeId, "update", BuildSyncPayload());
            SyncService.MarkLocalPending("employees", _employeeId);

            ShowStatus("Security settings updated successfully. They will sync to the server.", true);
        }
        catch (Exception ex)
        {
            ShowStatus("Error: " + ex.Message, false);
        }
    }

    // ─────────────────────────── PHOTO ───────────────────────────

    private void BtnChoosePhoto_Click(object sender, RoutedEventArgs e)
    {
        var dlg = new OpenFileDialog
        {
            Title = "Choose Photo",
            Filter = "Image files (*.jpg;*.jpeg;*.png;*.webp;*.bmp)|*.jpg;*.jpeg;*.png;*.webp;*.bmp|All files (*.*)|*.*"
        };
        if (dlg.ShowDialog() == true)
        {
            _photoPath = dlg.FileName;
            ApplyPhoto();
        }
    }

    private void BtnPreviewPhoto_Click(object sender, RoutedEventArgs e)
    {
        if (!string.IsNullOrEmpty(_photoPath) && File.Exists(_photoPath))
        {
            try { Process.Start(new ProcessStartInfo(_photoPath) { UseShellExecute = true }); }
            catch { }
        }
    }

    private void BtnRemovePhoto_Click(object sender, RoutedEventArgs e)
    {
        _photoPath = "";
        ApplyPhoto();
    }

    private void ApplyPhoto()
    {
        if (!string.IsNullOrEmpty(_photoPath) && File.Exists(_photoPath))
        {
            try
            {
                imgPreview.Source = new BitmapImage(new Uri(_photoPath, UriKind.Absolute));
                imgPreview.Visibility = Visibility.Visible;
                txtNoPhoto.Visibility = Visibility.Collapsed;
                btnPreviewPhoto.Visibility = Visibility.Visible;
                btnRemovePhoto.Visibility = Visibility.Visible;
                return;
            }
            catch { }
        }

        imgPreview.Source = null;
        imgPreview.Visibility = Visibility.Collapsed;
        txtNoPhoto.Visibility = Visibility.Visible;
        btnPreviewPhoto.Visibility = Visibility.Collapsed;
        btnRemovePhoto.Visibility = Visibility.Collapsed;
    }

    // ─────────────────────────── SYNC PAYLOAD ───────────────────────────

    /// <summary>
    /// Builds a cloud-shaped "users" payload from the stored employee row plus
    /// the current form values. Optional password hash is included for changes.
    /// </summary>
    private string BuildSyncPayload(string? passwordHash = null)
    {
        string role = "", outlet = "", willLogin = "No", sd = "", ed = "", discCode = "";
        double salary = 0, commission = 0, discAmt = 0;

        using (var conn = _db.GetConnection())
        {
            conn.Open();
            var cmd = conn.CreateCommand();
            cmd.CommandText = @"SELECT COALESCE(role,''), COALESCE(salary,0), COALESCE(commission,0), COALESCE(outlet_id,''),
                                COALESCE(will_login,'No'), COALESCE(start_date,''), COALESCE(end_date,''),
                                COALESCE(discount_permission_code,''), COALESCE(discount_amt,0)
                                FROM employees WHERE id=@id";
            cmd.Parameters.AddWithValue("@id", _employeeId);
            using var r = cmd.ExecuteReader();
            if (r.Read())
            {
                role = r.IsDBNull(0) ? "" : r.GetString(0);
                salary = r.IsDBNull(1) ? 0 : r.GetDouble(1);
                commission = r.IsDBNull(2) ? 0 : r.GetDouble(2);
                outlet = r.IsDBNull(3) ? "" : r.GetString(3);
                willLogin = r.IsDBNull(4) ? "No" : r.GetString(4);
                sd = r.IsDBNull(5) ? "" : r.GetString(5);
                ed = r.IsDBNull(6) ? "" : r.GetString(6);
                discCode = r.IsDBNull(7) ? "" : r.GetString(7);
                discAmt = r.IsDBNull(8) ? 0 : r.GetDouble(8);
            }
        }

        var payload = new Dictionary<string, object?>
        {
            ["name"] = txtName.Text?.Trim() ?? "",
            ["email"] = txtEmail.Text?.Trim() ?? "",
            ["phone"] = txtPhone.Text?.Trim() ?? "",
            ["role"] = role,
            ["salary"] = salary,
            ["commission"] = commission,
            ["outlet_id"] = outlet,
            ["will_login"] = willLogin,
            ["start_date"] = sd,
            ["end_date"] = ed,
            ["discount_permission_code"] = discCode,
            ["discount_amt"] = discAmt,
            ["photo"] = _photoPath ?? "",
            ["question"] = (cmbQuestion.SelectedItem as ComboBoxItem)?.Content?.ToString() ?? "",
            ["answer"] = txtAnswer.Text?.Trim() ?? ""
        };
        if (passwordHash != null) payload["password"] = passwordHash;

        return System.Text.Json.JsonSerializer.Serialize(payload);
    }

    // ─────────────────────────── HELPERS ───────────────────────────

    private static bool TryParseId(object? v, out long id)
    {
        id = 0;
        return v != null && long.TryParse(v.ToString(), out id);
    }

    private void ShowStatus(string msg, bool success)
    {
        txtStatus.Text = msg;
        if (success)
        {
            statusBanner.Background = new SolidColorBrush(Color.FromRgb(0xDC, 0xFC, 0xE7));
            txtStatus.Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0x65, 0x34));
        }
        else
        {
            statusBanner.Background = new SolidColorBrush(Color.FromRgb(0xFE, 0xE2, 0xE2));
            txtStatus.Foreground = new SolidColorBrush(Color.FromRgb(0xB9, 0x1C, 0x1C));
        }
        statusBanner.Visibility = Visibility.Visible;
    }
}
