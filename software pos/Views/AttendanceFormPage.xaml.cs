using System;
using System.Globalization;
using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views;

public partial class AttendanceFormPage : UserControl
{
    private MainDashboard? _dashboard;
    private readonly DatabaseService _db = new();
    private long _editId = 0;
    private bool IsEdit => _editId > 0;

    public AttendanceFormPage() { InitializeComponent(); Loaded += OnLoaded; }
    public AttendanceFormPage(MainDashboard dashboard) : this() { _dashboard = dashboard; }
    public AttendanceFormPage(MainDashboard dashboard, long editId) : this(dashboard) { _editId = editId; }

    private void OnLoaded(object sender, RoutedEventArgs e)
    {
        LoadEmployees();
        dpDate.SelectedDate = DateTime.Today;
        btnSubmit.Content = "\uE73E  " + (IsEdit ? "Update" : "Submit");

        if (IsEdit)
        {
            lblTitle.Text = "Update Attendance";
            lblBreadcrumb.Text = "Update Attendance";
            LoadForEdit();
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

    private string GenerateReferenceNo()
    {
        try
        {
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "SELECT COUNT(*) FROM attendances WHERE del_status IS NULL OR del_status='Live'";
            long count = Convert.ToInt64(cmd.ExecuteScalar() ?? 0);
            return "ATT-" + (count + 1).ToString("D4");
        }
        catch { return "ATT-0001"; }
    }

    private void LoadForEdit()
    {
        try
        {
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "SELECT reference_no, date, employee_id, COALESCE(in_time,''), COALESCE(out_time,''), COALESCE(note,'') FROM attendances WHERE id=@id";
            cmd.Parameters.AddWithValue("@id", _editId);
            using var r = cmd.ExecuteReader();
            if (!r.Read()) return;

            txtReferenceNo.Text = r.IsDBNull(0) ? "" : r.GetString(0);
            if (!r.IsDBNull(1) && DateTime.TryParse(r.GetString(1), out var d))
                dpDate.SelectedDate = d;

            string empId = r.IsDBNull(2) ? "" : r.GetInt64(2).ToString();
            for (int i = 0; i < cmbEmployee.Items.Count; i++)
            {
                if ((cmbEmployee.Items[i] as ComboBoxItem)?.Tag?.ToString() == empId)
                { cmbEmployee.SelectedIndex = i; break; }
            }

            string inTime = r.IsDBNull(3) ? "" : r.GetString(3);
            string outTime = r.IsDBNull(4) ? "" : r.GetString(4);
            // Local stores HH:mm:ss; cloud uses H:i — show HH:mm
            txtInTime.Text = ShortenTime(inTime);
            txtOutTime.Text = ShortenTime(outTime);
            txtNote.Text = r.IsDBNull(5) ? "" : r.GetString(5);
        }
        catch (Exception ex)
        {
            MessageBox.Show("Error loading attendance: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
        }
    }

    private static string ShortenTime(string t)
    {
        if (string.IsNullOrWhiteSpace(t)) return "";
        if (DateTime.TryParseExact(t, "HH:mm:ss", CultureInfo.InvariantCulture, DateTimeStyles.None, out var d1))
            return d1.ToString("HH:mm");
        if (DateTime.TryParseExact(t, "HH:mm", CultureInfo.InvariantCulture, DateTimeStyles.None, out var d2))
            return d2.ToString("HH:mm");
        return t;
    }

    private void ShowStatus(string msg, bool success)
    {
        txtStatus.Text = msg;
        if (success)
        {
            statusBanner.Background = new System.Windows.Media.SolidColorBrush(System.Windows.Media.Color.FromRgb(0xDC, 0xFC, 0xE7));
            txtStatus.Foreground = new System.Windows.Media.SolidColorBrush(System.Windows.Media.Color.FromRgb(0x16, 0x65, 0x34));
        }
        else
        {
            statusBanner.Background = new System.Windows.Media.SolidColorBrush(System.Windows.Media.Color.FromRgb(0xFE, 0xE2, 0xE2));
            txtStatus.Foreground = new System.Windows.Media.SolidColorBrush(System.Windows.Media.Color.FromRgb(0xB9, 0x1C, 0x1C));
        }
        statusBanner.Visibility = Visibility.Visible;
    }

    // ═══════════ SAVE (mirrors cloud AttendanceRequest rules) ═══════════

    private void BtnSubmit_Click(object sender, RoutedEventArgs e)
    {
        var refNo = txtReferenceNo.Text.Trim();
        var date = dpDate.SelectedDate?.ToString("yyyy-MM-dd") ?? "";
        var empId = (cmbEmployee.SelectedItem as ComboBoxItem)?.Tag?.ToString() ?? "";
        var inTime = txtInTime.Text.Trim();
        var outTime = txtOutTime.Text.Trim();
        var note = txtNote.Text.Trim();

        // -- Validation (clone of cloud) --
        if (string.IsNullOrEmpty(refNo))
        { ShowStatus("The Reference No is required.", false); return; }
        if (string.IsNullOrEmpty(date))
        { ShowStatus("The Date is required.", false); return; }
        if (dpDate.SelectedDate > DateTime.Today)
        { ShowStatus("The Date must be today or a previous date.", false); return; }
        if (string.IsNullOrEmpty(empId))
        { ShowStatus("Please select an Employee.", false); return; }
        if (string.IsNullOrEmpty(inTime) || !IsValidTime(inTime))
        { ShowStatus("The In Time is required and must be in HH:MM format.", false); return; }
        if (!string.IsNullOrEmpty(outTime) && !IsValidTime(outTime))
        { ShowStatus("The Out Time must be in HH:MM format.", false); return; }
        if (!string.IsNullOrEmpty(outTime) && TimeSpan.Parse(outTime) <= TimeSpan.Parse(inTime))
        { ShowStatus("The Out Time must be after the In Time.", false); return; }
        if (note.Length > 500)
        { ShowStatus("The Note must not be greater than 500 characters.", false); return; }
        if (refNo.Length > 50)
        { ShowStatus("The Reference No must not be greater than 50 characters.", false); return; }

        try
        {
            using var conn = _db.GetConnection();
            conn.Open();

            // Reference unique check (cloud: unique:attendances,reference_no)
            using (var chk = conn.CreateCommand())
            {
                chk.CommandText = "SELECT COUNT(*) FROM attendances WHERE reference_no=@r AND id<>@id AND (del_status IS NULL OR del_status='Live')";
                chk.Parameters.AddWithValue("@r", refNo);
                chk.Parameters.AddWithValue("@id", _editId);
                if (Convert.ToInt64(chk.ExecuteScalar()) > 0)
                { ShowStatus("The Reference No has already been taken.", false); return; }
            }

            var now = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
            long savedId;

            if (IsEdit)
            {
                var cmd = conn.CreateCommand();
                cmd.CommandText = @"UPDATE attendances SET reference_no=@r, date=@d, employee_id=@e, in_time=@i, out_time=@o, note=@n, updated_at=@now WHERE id=@id";
                cmd.Parameters.AddWithValue("@r", refNo);
                cmd.Parameters.AddWithValue("@d", date);
                cmd.Parameters.AddWithValue("@e", long.Parse(empId));
                cmd.Parameters.AddWithValue("@i", string.IsNullOrEmpty(inTime) ? "" : inTime + ":00");
                cmd.Parameters.AddWithValue("@o", string.IsNullOrEmpty(outTime) ? "" : outTime + ":00");
                cmd.Parameters.AddWithValue("@n", note);
                cmd.Parameters.AddWithValue("@now", now);
                cmd.Parameters.AddWithValue("@id", _editId);
                cmd.ExecuteNonQuery();
                savedId = _editId;

                SyncService.EnqueueSync("attendances", savedId, "update");
                SyncService.MarkLocalPending("attendances", savedId);
            }
            else
            {
                var cmd = conn.CreateCommand();
                cmd.CommandText = @"INSERT INTO attendances (reference_no, date, employee_id, in_time, out_time, note, user_id, del_status, created_at, updated_at, SyncStatus)
                                    VALUES (@r, @d, @e, @i, @o, @n, @u, 'Live', @now, @now, 'Local');
                                    SELECT last_insert_rowid();";
                cmd.Parameters.AddWithValue("@r", refNo);
                cmd.Parameters.AddWithValue("@d", date);
                cmd.Parameters.AddWithValue("@e", long.Parse(empId));
                cmd.Parameters.AddWithValue("@i", string.IsNullOrEmpty(inTime) ? "" : inTime + ":00");
                cmd.Parameters.AddWithValue("@o", string.IsNullOrEmpty(outTime) ? "" : outTime + ":00");
                cmd.Parameters.AddWithValue("@n", note);
                cmd.Parameters.AddWithValue("@u", _dashboard?.CurrentUser.Id ?? 0);
                cmd.Parameters.AddWithValue("@now", now);
                savedId = Convert.ToInt64(cmd.ExecuteScalar());

                SyncService.EnqueueSync("attendances", savedId, "create");
                SyncService.MarkLocalPending("attendances", savedId);
            }

            _dashboard?.TriggerSync();
            ShowStatus(IsEdit ? "Attendance updated successfully." : "Attendance created successfully.", true);
            _dashboard?.ShowPage(new AttendanceListPage(_dashboard));
        }
        catch (Exception ex)
        {
            ShowStatus("Error saving: " + ex.Message, false);
        }
    }

    private static bool IsValidTime(string t)
    {
        return DateTime.TryParseExact(t, "HH:mm", CultureInfo.InvariantCulture, DateTimeStyles.None, out _);
    }

    private void BtnBack_Click(object sender, RoutedEventArgs e)
    {
        _dashboard?.ShowPage(new AttendanceListPage(_dashboard));
    }
}
