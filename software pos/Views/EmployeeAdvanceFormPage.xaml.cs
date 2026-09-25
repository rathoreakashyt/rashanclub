using System;
using System.Collections.Generic;
using System.Globalization;
using System.Text.Json;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views;

public partial class EmployeeAdvanceFormPage : UserControl
{
    private MainDashboard? _dashboard;
    private readonly DatabaseService _db = new();
    private long? _advanceId;
    private bool _viewOnly;

    public EmployeeAdvanceFormPage() { InitializeComponent(); Loaded += OnLoaded; }
    public EmployeeAdvanceFormPage(MainDashboard dashboard) : this() { _dashboard = dashboard; }
    public EmployeeAdvanceFormPage(MainDashboard dashboard, long advanceId, bool viewOnly = false) : this(dashboard)
    {
        _advanceId = advanceId;
        _viewOnly = viewOnly;
    }

    private void OnLoaded(object sender, RoutedEventArgs e)
    {
        dpDate.SelectedDate = DateTime.Today;
        LoadEmployees();
        LoadPaymentMethods();
        if (_advanceId.HasValue)
        {
            if (_viewOnly)
            {
                txtTitle.Text = "Employee Advance Payment Details";
                txtCrumb.Text = "Employee Advance Payment Details";
                btnSubmit.Visibility = Visibility.Collapsed;
            }
            else
            {
                txtTitle.Text = "Update Employee Advance Payment";
                txtCrumb.Text = "Update Employee Advance Payment";
                btnSubmit.Content = "\uE73E  Update";
            }
            LoadForEdit(_advanceId.Value);
        }
        else
        {
            txtReferenceNo.Text = GenerateReferenceNo();
        }
    }

    private void LoadEmployees()
    {
        cmbEmployee.Items.Clear();
        cmbEmployee.Items.Add(new ComboBoxItem { Content = "Select Employee", Tag = "" });
        try
        {
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "SELECT id, COALESCE(name,'') FROM employees WHERE del_status IS NULL OR del_status='Live' ORDER BY name";
            using var r = cmd.ExecuteReader();
            while (r.Read())
                cmbEmployee.Items.Add(new ComboBoxItem { Content = r.GetString(1), Tag = r.GetInt64(0).ToString() });
        }
        catch { }
        cmbEmployee.SelectedIndex = 0;
    }

    private void LoadPaymentMethods()
    {
        cmbPaymentMethod.Items.Clear();
        cmbPaymentMethod.Items.Add(new ComboBoxItem { Content = "Select Payment Method", Tag = "" });
        try
        {
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "SELECT id, COALESCE(name,'') FROM payment_methods WHERE del_status IS NULL OR del_status='Live' ORDER BY name";
            using var r = cmd.ExecuteReader();
            while (r.Read())
                cmbPaymentMethod.Items.Add(new ComboBoxItem { Content = r.GetString(1), Tag = r.GetInt64(0).ToString() });
        }
        catch { }
        cmbPaymentMethod.SelectedIndex = 0;
    }

    private string GenerateReferenceNo()
    {
        try
        {
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "SELECT COUNT(*) FROM employee_advance_payments WHERE del_status IS NULL OR del_status='Live'";
            long count = (long)(cmd.ExecuteScalar() ?? 0L);
            return "EAP-" + (count + 1).ToString("D6");
        }
        catch { return "EAP-" + Guid.NewGuid().ToString("N").Substring(0, 6); }
    }

    private void LoadForEdit(long advanceId)
    {
        try
        {
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = @"SELECT reference_no, COALESCE(date,''), COALESCE(amount,0), employee_id, payment_method_id, COALESCE(note,'')
                                FROM employee_advance_payments WHERE id=@id";
            cmd.Parameters.AddWithValue("@id", advanceId);
            using var r = cmd.ExecuteReader();
            if (!r.Read())
            {
                ShowStatus("Advance payment record not found.", false);
                return;
            }
            txtReferenceNo.Text = r.GetString(0);
            if (DateTime.TryParse(r.GetString(1), CultureInfo.InvariantCulture, DateTimeStyles.None, out var d))
                dpDate.SelectedDate = d;
            txtAmount.Text = r.GetDouble(2).ToString("F2", CultureInfo.InvariantCulture);
            if (!r.IsDBNull(3))
                cmbEmployee.SelectedItem = cmbEmployee.Items.Cast<ComboBoxItem>()
                    .FirstOrDefault(i => i.Tag?.ToString() == r.GetInt64(3).ToString());
            if (!r.IsDBNull(4))
                cmbPaymentMethod.SelectedItem = cmbPaymentMethod.Items.Cast<ComboBoxItem>()
                    .FirstOrDefault(i => i.Tag?.ToString() == r.GetInt64(4).ToString());
            txtNote.Text = r.GetString(5);

            if (_viewOnly)
            {
                txtReferenceNo.IsReadOnly = true;
                dpDate.IsEnabled = false;
                txtAmount.IsReadOnly = true;
                cmbEmployee.IsEnabled = false;
                cmbPaymentMethod.IsEnabled = false;
                txtNote.IsReadOnly = true;
            }
        }
        catch (Exception ex)
        {
            MessageBox.Show("Error loading advance payment: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
        }
    }

    private void BtnSubmit_Click(object sender, RoutedEventArgs e)
    {
        HideStatus();

        if (string.IsNullOrWhiteSpace(txtReferenceNo.Text))
        {
            ShowStatus("The Reference Number is required.", false);
            return;
        }
        if (dpDate.SelectedDate == null)
        {
            ShowStatus("The Date is required.", false);
            return;
        }
        if (!double.TryParse(txtAmount.Text?.Trim(), NumberStyles.Any, CultureInfo.InvariantCulture, out double amount) || amount < 0.01)
        {
            ShowStatus("The Amount must be at least 0.01.", false);
            return;
        }
        if (cmbEmployee.SelectedItem is not ComboBoxItem emp || string.IsNullOrEmpty(emp.Tag?.ToString()))
        {
            ShowStatus("Please select an employee.", false);
            return;
        }
        if (cmbPaymentMethod.SelectedItem is not ComboBoxItem pm || string.IsNullOrEmpty(pm.Tag?.ToString()))
        {
            ShowStatus("Please select a payment method.", false);
            return;
        }
        string note = txtNote.Text?.Trim() ?? "";
        if (note.Length > 1000)
        {
            ShowStatus("The Note must not exceed 1000 characters.", false);
            return;
        }
        if (_advanceId == null && ReferenceNoExists(txtReferenceNo.Text.Trim()))
        {
            ShowStatus("The Reference Number has already been taken.", false);
            return;
        }

        long employeeId = long.Parse(emp.Tag.ToString()!);
        long paymentMethodId = long.Parse(pm.Tag.ToString()!);
        string date = dpDate.SelectedDate!.Value.ToString("yyyy-MM-dd");
        string now = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");

        try
        {
            using var conn = _db.GetConnection();
            long id;
            if (_advanceId.HasValue)
            {
                id = _advanceId.Value;
                using var up = conn.CreateCommand();
                up.CommandText = @"UPDATE employee_advance_payments SET date=@d, amount=@a, note=@n, payment_method_id=@pm, employee_id=@e, updated_at=@now
                                   WHERE id=@id";
                up.Parameters.AddWithValue("@d", date);
                up.Parameters.AddWithValue("@a", amount);
                up.Parameters.AddWithValue("@n", (object?)note ?? DBNull.Value);
                up.Parameters.AddWithValue("@pm", paymentMethodId);
                up.Parameters.AddWithValue("@e", employeeId);
                up.Parameters.AddWithValue("@now", now);
                up.Parameters.AddWithValue("@id", id);
                up.ExecuteNonQuery();
            }
            else
            {
                using var ins = conn.CreateCommand();
                ins.CommandText = @"INSERT INTO employee_advance_payments (reference_no, date, amount, note, payment_method_id, employee_id, user_id, del_status, created_at, updated_at, SyncStatus)
                                    VALUES (@r, @d, @a, @n, @pm, @e, @u, 'Live', @now, @now, 'Local');
                                    SELECT last_insert_rowid();";
                ins.Parameters.AddWithValue("@r", txtReferenceNo.Text.Trim());
                ins.Parameters.AddWithValue("@d", date);
                ins.Parameters.AddWithValue("@a", amount);
                ins.Parameters.AddWithValue("@n", (object?)note ?? DBNull.Value);
                ins.Parameters.AddWithValue("@pm", paymentMethodId);
                ins.Parameters.AddWithValue("@e", employeeId);
                ins.Parameters.AddWithValue("@u", (object?)_dashboard?.CurrentUser?.Id ?? DBNull.Value);
                ins.Parameters.AddWithValue("@now", now);
                id = (long)ins.ExecuteScalar()!;
            }

            SyncService.EnqueueSync("employee_advance_payments", id, _advanceId.HasValue ? "update" : "insert", BuildSyncPayload(amount, date, employeeId, paymentMethodId, note));
            SyncService.MarkLocalPending("employee_advance_payments", id);
            _dashboard?.TriggerSync();

            _dashboard?.ShowPage(new EmployeeAdvanceListPage(_dashboard));
        }
        catch (Exception ex)
        {
            ShowStatus("Error saving advance payment: " + ex.Message, false);
        }
    }

    private bool ReferenceNoExists(string referenceNo)
    {
        try
        {
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "SELECT COUNT(*) FROM employee_advance_payments WHERE reference_no=@r AND (del_status IS NULL OR del_status='Live')";
            cmd.Parameters.AddWithValue("@r", referenceNo);
            return (long)(cmd.ExecuteScalar() ?? 0L) > 0;
        }
        catch { return false; }
    }

    private string BuildSyncPayload(double amount, string date, long employeeId, long paymentMethodId, string note)
    {
        var payload = new Dictionary<string, object?>
        {
            ["reference_no"] = txtReferenceNo.Text.Trim(),
            ["date"] = date,
            ["amount"] = amount,
            ["note"] = note,
            ["payment_method_id"] = paymentMethodId,
            ["employee_id"] = employeeId
        };
        return JsonSerializer.Serialize(payload);
    }

    private void BtnBack_Click(object sender, RoutedEventArgs e)
    {
        _dashboard?.ShowPage(new EmployeeAdvanceListPage(_dashboard));
    }

    private void ShowStatus(string msg, bool success)
    {
        txtStatus.Text = msg;
        statusBanner.Visibility = Visibility.Visible;
        if (success)
        {
            statusBanner.Background = new SolidColorBrush(Color.FromRgb(0xDC, 0xFC, 0xE7));
            txtStatus.Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0x6B, 0x34));
        }
        else
        {
            statusBanner.Background = new SolidColorBrush(Color.FromRgb(0xFE, 0xE2, 0xE2));
            txtStatus.Foreground = new SolidColorBrush(Color.FromRgb(0x99, 0x1B, 0x1B));
        }
    }

    private void HideStatus() => statusBanner.Visibility = Visibility.Collapsed;
}
