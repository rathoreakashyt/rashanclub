using System;
using System.Collections.Generic;
using System.Data;
using System.Globalization;
using System.Linq;
using System.Text.Json;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views;

public partial class SalaryFormPage : UserControl
{
    private MainDashboard? _dashboard;
    private readonly DatabaseService _db = new();
    private long? _salaryId;
    private readonly List<SalaryItemEditor> _items = new();
    private readonly List<PaymentRow> _payments = new();
    private readonly List<ComboBoxItem> _paymentChoices = new();

    private static readonly string[] MonthNames =
    {
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    };

    public SalaryFormPage() { InitializeComponent(); Loaded += OnLoaded; }
    public SalaryFormPage(MainDashboard dashboard) : this() { _dashboard = dashboard; }
    public SalaryFormPage(MainDashboard dashboard, long salaryId) : this(dashboard) { _salaryId = salaryId; }

    private void OnLoaded(object sender, RoutedEventArgs e)
    {
        LoadMonths();
        LoadPaymentMethods();
        dpGeneratedDate.SelectedDate = DateTime.Today;
        dpGeneratedDate.DisplayDateEnd = DateTime.Today;
        if (_salaryId.HasValue)
        {
            txtTitle.Text = "Update Salary";
            txtCrumb.Text = "Update Salary";
            btnSubmit.Content = "\uE73E  Update";
            LoadForEdit(_salaryId.Value);
        }
        else
        {
            txtYear.Text = DateTime.Today.Year.ToString();
            cmbMonth.SelectedIndex = DateTime.Today.Month - 1;
            txtReferenceNo.Text = GenerateReferenceNo();
            LoadAllEmployees();
        }
    }

    private void LoadMonths()
    {
        cmbMonth.Items.Clear();
        cmbMonth.Items.Add(new ComboBoxItem { Content = "Select Month", Tag = "" });
        for (int i = 0; i < 12; i++)
            cmbMonth.Items.Add(new ComboBoxItem { Content = MonthNames[i], Tag = (i + 1).ToString() });
        cmbMonth.SelectedIndex = 0;
    }

    private void LoadPaymentMethods()
    {
        cmbPaymentMethod.Items.Clear();
        _paymentChoices.Clear();
        try
        {
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "SELECT id, name FROM payment_methods WHERE del_status IS NULL OR del_status='Live' ORDER BY name";
            using var r = cmd.ExecuteReader();
            while (r.Read())
            {
                var item = new ComboBoxItem { Content = r.GetString(1), Tag = r.GetInt64(0).ToString() };
                cmbPaymentMethod.Items.Add(item);
                _paymentChoices.Add(item);
            }
        }
        catch { }
        cmbPaymentMethod.SelectedIndex = -1;
    }

    private string GenerateReferenceNo()
    {
        try
        {
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "SELECT COUNT(*) FROM salaries WHERE del_status IS NULL OR del_status='Live'";
            long count = (long)(cmd.ExecuteScalar() ?? 0L);
            return "SAL-" + (count + 1).ToString("D6");
        }
        catch { return "SAL-" + Guid.NewGuid().ToString("N").Substring(0, 6); }
    }

    // ═══════════ EMPLOYEE ITEMS ═══════════

    private void LoadAllEmployees()
    {
        try
        {
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "SELECT id, COALESCE(name,''), COALESCE(salary,0) FROM employees WHERE (del_status IS NULL OR del_status='Live') AND COALESCE(salary,0) > 0 ORDER BY name";
            using var r = cmd.ExecuteReader();
            while (r.Read())
                AddEmployeeItem(r.GetInt64(0), r.GetString(1), r.GetDouble(2));
        }
        catch (Exception ex)
        {
            MessageBox.Show("Error loading employees: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
        }
        RefreshAdvanceTaken();
    }

    private void AddEmployeeItem(long employeeId, string name, double salaryAmount)
    {
        if (_items.Any(i => i.EmployeeId == employeeId)) return;

        var editor = new SalaryItemEditor(employeeId, name, salaryAmount);
        editor.RemoveClicked += () =>
        {
            _items.Remove(editor);
            itemsContainer.Children.Remove(editor.Root);
            RecalculateTotals();
        };
        editor.ValueChanged += RecalculateTotals;
        _items.Add(editor);
        itemsContainer.Children.Add(editor.Root);
        RecalculateTotals();
    }

    private void RecalculateTotals()
    {
        foreach (var item in _items) item.CalculateNet();
        double total = _items.Sum(i => i.NetSalary);
        txtTotalAmount.Text = total.ToString("F2", CultureInfo.InvariantCulture);
        ValidatePayments();
    }

    /// <summary>Cloud clone: total advance per employee for the chosen year/month.</summary>
    private void RefreshAdvanceTaken()
    {
        if (!int.TryParse(txtYear.Text?.Trim(), out int year)) return;
        if (cmbMonth.SelectedItem is not ComboBoxItem mi || mi.Tag is not string ms || !int.TryParse(ms, out int month)) return;

        string start = $"{year:D4}-{month:D2}-01";
        string end = $"{year:D4}-{month:D2}-{DateTime.DaysInMonth(year, month):D2}";

        var advances = new Dictionary<long, double>();
        try
        {
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = @"SELECT employee_id, COALESCE(SUM(amount),0) FROM employee_advance_payments
                                WHERE (del_status IS NULL OR del_status='Live') AND date BETWEEN @s AND @e
                                GROUP BY employee_id";
            cmd.Parameters.AddWithValue("@s", start);
            cmd.Parameters.AddWithValue("@e", end);
            using var r = cmd.ExecuteReader();
            while (r.Read())
                advances[r.GetInt64(0)] = r.GetDouble(1);
        }
        catch { }

        foreach (var item in _items)
        {
            advances.TryGetValue(item.EmployeeId, out double adv);
            item.SetAdvanceTaken(adv);
        }
        RecalculateTotals();
    }

    // ═══════════ PAYMENTS ═══════════

    private void BtnAddPayment_Click(object sender, RoutedEventArgs e)
    {
        if (cmbPaymentMethod.SelectedItem is not ComboBoxItem item || item.Tag is not string idStr) return;
        long pmId = long.Parse(idStr);
        if (_payments.Any(p => p.PaymentMethodId == pmId))
        {
            ShowStatus("This payment method already added", false);
            return;
        }
        var row = new PaymentRow(pmId, item.Content?.ToString() ?? "Payment");
        row.RemoveClicked += () =>
        {
            _payments.Remove(row);
            paymentsContainer.Children.Remove(row.Root);
            ValidatePayments();
        };
        row.ValueChanged += ValidatePayments;
        _payments.Add(row);
        paymentsContainer.Children.Add(row.Root);
        cmbPaymentMethod.SelectedIndex = -1;
        ValidatePayments();
    }

    private void ValidatePayments()
    {
        if (txtPaymentError == null) return;
        if (_payments.Count == 0)
        {
            txtPaymentError.Text = "Please select at least one payment method.";
            txtPaymentError.Visibility = Visibility.Visible;
            return;
        }

        double totalPayment = _payments.Sum(p => p.Amount);
        double totalAmount = 0;
        double.TryParse(txtTotalAmount.Text, NumberStyles.Any, CultureInfo.InvariantCulture, out totalAmount);

        bool hasZero = _payments.Any(p => p.Amount <= 0);
        if (hasZero)
        {
            txtPaymentError.Text = "Payment method amount cannot be zero.";
            txtPaymentError.Visibility = Visibility.Visible;
        }
        else if (totalPayment > totalAmount)
        {
            txtPaymentError.Text = $"Total payment amount ({totalPayment:F2}) cannot exceed total salary amount ({totalAmount:F2}).";
            txtPaymentError.Visibility = Visibility.Visible;
        }
        else if (totalPayment < totalAmount)
        {
            txtPaymentError.Text = $"Total payment amount must equal total salary amount (Total: {totalAmount:F2}, Payment total: {totalPayment:F2}).";
            txtPaymentError.Visibility = Visibility.Visible;
        }
        else
        {
            txtPaymentError.Visibility = Visibility.Collapsed;
        }
    }

    // ═══════════ EDIT MODE ═══════════

    private void LoadForEdit(long salaryId)
    {
        try
        {
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = @"SELECT reference_no, year, month, COALESCE(generated_date,''), COALESCE(total_amount,0)
                                FROM salaries WHERE id=@id";
            cmd.Parameters.AddWithValue("@id", salaryId);
            using (var r = cmd.ExecuteReader())
            {
                if (!r.Read())
                {
                    ShowStatus("Salary record not found.", false);
                    return;
                }
                txtReferenceNo.Text = r.GetString(0);
                txtYear.Text = r.IsDBNull(1) ? "" : r.GetInt64(1).ToString();
                cmbMonth.SelectedItem = cmbMonth.Items.Cast<ComboBoxItem>()
                    .FirstOrDefault(i => i.Tag?.ToString() == (r.IsDBNull(2) ? "" : r.GetInt64(2).ToString()));
                if (DateTime.TryParse(r.GetString(3), CultureInfo.InvariantCulture, DateTimeStyles.None, out var gd))
                    dpGeneratedDate.SelectedDate = gd;
            }

            using (var itemsCmd = conn.CreateCommand())
            {
                itemsCmd.CommandText = @"SELECT si.id, si.employee_id, COALESCE(e.name,''), COALESCE(si.salary_amount,0),
                                         COALESCE(si.overtime_rate,0), COALESCE(si.overtime_hour,0),
                                         COALESCE(si.additional_amount,0), COALESCE(si.deduction_amount,0),
                                         COALESCE(si.absent_day,0), COALESCE(si.absent_day_amount,0),
                                         COALESCE(si.advance_taken,0), COALESCE(si.note,''), COALESCE(si.net_salary,0)
                                         FROM salary_items si LEFT JOIN employees e ON e.id = si.employee_id
                                         WHERE si.salary_id=@sid AND (si.del_status IS NULL OR si.del_status='Live')
                                         ORDER BY si.id";
                itemsCmd.Parameters.AddWithValue("@sid", salaryId);
                using var ir = itemsCmd.ExecuteReader();
                while (ir.Read())
                {
                    var editor = new SalaryItemEditor(ir.GetInt64(1), ir.GetString(2), ir.GetDouble(3));
                    editor.SetValues(
                        ir.GetDouble(4), ir.GetDouble(5), ir.GetDouble(6), ir.GetDouble(7),
                        ir.GetInt64(8), ir.GetDouble(9), ir.GetDouble(10), ir.GetString(11), ir.GetDouble(12));
                    editor.RemoveClicked += () =>
                    {
                        _items.Remove(editor);
                        itemsContainer.Children.Remove(editor.Root);
                        RecalculateTotals();
                    };
                    editor.ValueChanged += RecalculateTotals;
                    _items.Add(editor);
                    itemsContainer.Children.Add(editor.Root);
                }
            }

            using (var payCmd = conn.CreateCommand())
            {
                payCmd.CommandText = @"SELECT sp.payment_method_id, COALESCE(pm.name,''), COALESCE(sp.amount,0)
                                       FROM salary_payments sp LEFT JOIN payment_methods pm ON pm.id = sp.payment_method_id
                                       WHERE sp.salary_id=@sid AND (sp.del_status IS NULL OR sp.del_status='Live')
                                       ORDER BY sp.id";
                payCmd.Parameters.AddWithValue("@sid", salaryId);
                using var pr = payCmd.ExecuteReader();
                while (pr.Read())
                {
                    var row = new PaymentRow(pr.GetInt64(0), pr.GetString(1));
                    row.SetAmount(pr.GetDouble(2));
                    row.RemoveClicked += () =>
                    {
                        _payments.Remove(row);
                        paymentsContainer.Children.Remove(row.Root);
                        ValidatePayments();
                    };
                    row.ValueChanged += ValidatePayments;
                    _payments.Add(row);
                    paymentsContainer.Children.Add(row.Root);
                }
            }
        }
        catch (Exception ex)
        {
            MessageBox.Show("Error loading salary: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
        }
        RecalculateTotals();
    }

    // ═══════════ SAVE ═══════════

    private void BtnSubmit_Click(object sender, RoutedEventArgs e)
    {
        HideStatus();
        string errors = Validate();
        if (errors != "")
        {
            ShowStatus(errors, false);
            return;
        }

        int year = int.Parse(txtYear.Text.Trim());
        int month = int.Parse((cmbMonth.SelectedItem as ComboBoxItem)!.Tag!.ToString()!);
        string generatedDate = dpGeneratedDate.SelectedDate?.ToString("yyyy-MM-dd") ?? DateTime.Today.ToString("yyyy-MM-dd");
        double totalAmount = _items.Sum(i => i.NetSalary);

        try
        {
            using var conn = _db.GetConnection();
            using var tx = conn.BeginTransaction();

            long salaryId;
            if (_salaryId.HasValue)
            {
                salaryId = _salaryId.Value;
                using (var up = conn.CreateCommand())
                {
                    up.Transaction = tx;
                    up.CommandText = @"UPDATE salaries SET year=@y, month=@m, generated_date=@gd, total_amount=@ta, updated_at=@now WHERE id=@id";
                    up.Parameters.AddWithValue("@y", year);
                    up.Parameters.AddWithValue("@m", month);
                    up.Parameters.AddWithValue("@gd", generatedDate);
                    up.Parameters.AddWithValue("@ta", totalAmount);
                    up.Parameters.AddWithValue("@now", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
                    up.Parameters.AddWithValue("@id", salaryId);
                    up.ExecuteNonQuery();
                }
            }
            else
            {
                using (var ins = conn.CreateCommand())
                {
                    ins.Transaction = tx;
                    ins.CommandText = @"INSERT INTO salaries (reference_no, year, month, generated_date, total_amount, user_id, del_status, created_at, updated_at, SyncStatus)
                                        VALUES (@r, @y, @m, @gd, @ta, @u, 'Live', @now, @now, 'Local');
                                        SELECT last_insert_rowid();";
                    ins.Parameters.AddWithValue("@r", txtReferenceNo.Text.Trim());
                    ins.Parameters.AddWithValue("@y", year);
                    ins.Parameters.AddWithValue("@m", month);
                    ins.Parameters.AddWithValue("@gd", generatedDate);
                    ins.Parameters.AddWithValue("@ta", totalAmount);
                    ins.Parameters.AddWithValue("@u", (object?)_dashboard?.CurrentUser?.Id ?? DBNull.Value);
                    ins.Parameters.AddWithValue("@now", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
                    salaryId = (long)ins.ExecuteScalar()!;
                }
            }

            // Replace items + payments (cloud: hard delete then recreate)
            using (var delItems = conn.CreateCommand())
            {
                delItems.Transaction = tx;
                delItems.CommandText = "UPDATE salary_items SET del_status='Deleted', updated_at=@now WHERE salary_id=@sid AND (del_status IS NULL OR del_status='Live')";
                delItems.Parameters.AddWithValue("@now", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
                delItems.Parameters.AddWithValue("@sid", salaryId);
                delItems.ExecuteNonQuery();
            }
            using (var delPay = conn.CreateCommand())
            {
                delPay.Transaction = tx;
                delPay.CommandText = "UPDATE salary_payments SET del_status='Deleted', updated_at=@now WHERE salary_id=@sid AND (del_status IS NULL OR del_status='Live')";
                delPay.Parameters.AddWithValue("@now", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
                delPay.Parameters.AddWithValue("@sid", salaryId);
                delPay.ExecuteNonQuery();
            }

            foreach (var item in _items)
            {
                using var cmd = conn.CreateCommand();
                cmd.Transaction = tx;
                cmd.CommandText = @"INSERT INTO salary_items (salary_id, employee_id, salary_amount, overtime_rate, overtime_hour, additional_amount,
                                     deduction_amount, absent_day, absent_day_amount, tips, advance_taken, net_salary, note, user_id, del_status, created_at, updated_at)
                                     VALUES (@sid, @e, @sa, @otr, @oth, @ad, @dd, @ab, @aba, 0, @at, @ns, @note, @u, 'Live', @now, @now)";
                cmd.Parameters.AddWithValue("@sid", salaryId);
                cmd.Parameters.AddWithValue("@e", item.EmployeeId);
                cmd.Parameters.AddWithValue("@sa", item.SalaryAmount);
                cmd.Parameters.AddWithValue("@otr", item.OvertimeRate);
                cmd.Parameters.AddWithValue("@oth", item.OvertimeHour);
                cmd.Parameters.AddWithValue("@ad", item.AdditionalAmount);
                cmd.Parameters.AddWithValue("@dd", item.DeductionAmount);
                cmd.Parameters.AddWithValue("@ab", item.AbsentDays);
                cmd.Parameters.AddWithValue("@aba", item.AbsentAmount);
                cmd.Parameters.AddWithValue("@at", item.AdvanceTaken);
                cmd.Parameters.AddWithValue("@ns", item.NetSalary);
                cmd.Parameters.AddWithValue("@note", (object?)item.Note ?? DBNull.Value);
                cmd.Parameters.AddWithValue("@u", (object?)_dashboard?.CurrentUser?.Id ?? DBNull.Value);
                cmd.Parameters.AddWithValue("@now", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
                cmd.ExecuteNonQuery();
            }

            foreach (var pay in _payments)
            {
                using var cmd = conn.CreateCommand();
                cmd.Transaction = tx;
                cmd.CommandText = @"INSERT INTO salary_payments (salary_id, payment_method_id, amount, user_id, del_status, created_at, updated_at)
                                    VALUES (@sid, @pm, @amt, @u, 'Live', @now, @now)";
                cmd.Parameters.AddWithValue("@sid", salaryId);
                cmd.Parameters.AddWithValue("@pm", pay.PaymentMethodId);
                cmd.Parameters.AddWithValue("@amt", pay.Amount);
                cmd.Parameters.AddWithValue("@u", (object?)_dashboard?.CurrentUser?.Id ?? DBNull.Value);
                cmd.Parameters.AddWithValue("@now", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
                cmd.ExecuteNonQuery();
            }

            tx.Commit();

            SyncService.EnqueueSync("salaries", salaryId, _salaryId.HasValue ? "update" : "insert", BuildSyncPayload(year, month, generatedDate, totalAmount));
            SyncService.MarkLocalPending("salaries", salaryId);
            SyncService.EnqueueSync("salary_items", salaryId, _salaryId.HasValue ? "update" : "insert", BuildItemsPayload(salaryId));
            SyncService.EnqueueSync("salary_payments", salaryId, _salaryId.HasValue ? "update" : "insert", BuildPaymentsPayload(salaryId));
            _dashboard?.TriggerSync();

            _dashboard?.ShowPage(new SalaryListPage(_dashboard));
        }
        catch (Exception ex)
        {
            ShowStatus("Error saving salary: " + ex.Message, false);
        }
    }

    private string Validate()
    {
        if (!int.TryParse(txtYear.Text?.Trim(), out int year) || year < 2000 || year > 2100)
            return "The Year must be between 2000 and 2100.";
        if (cmbMonth.SelectedItem is not ComboBoxItem mi || string.IsNullOrEmpty(mi.Tag?.ToString()))
            return "The Month field is required.";
        if (dpGeneratedDate.SelectedDate == null)
            return "The Generation Date is required.";

        int month = int.Parse(mi.Tag.ToString()!);
        if (MonthYearExists(year, month))
            return "Salary for this month and year combination has already been generated.";

        if (_items.Count == 0)
            return "At least one employee salary item is required.";
        foreach (var item in _items)
        {
            if (item.SalaryAmount < 0) return "Salary Amount cannot be negative for " + item.EmployeeName + ".";
            if (item.NetSalary < 0) return "Net Salary cannot be negative for " + item.EmployeeName + ".";
        }

        if (_payments.Count == 0)
            return "Please select at least one payment method.";
        if (_payments.Any(p => p.Amount <= 0))
            return "Payment method amount cannot be zero.";

        double total = _items.Sum(i => i.NetSalary);
        double paid = _payments.Sum(p => p.Amount);
        if (Math.Abs(paid - total) > 0.01)
            return $"Total payment amount must equal total salary amount (Total: {total:F2}, Payment total: {paid:F2}).";
        return "";
    }

    private bool MonthYearExists(int year, int month)
    {
        try
        {
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = @"SELECT COUNT(*) FROM salaries WHERE year=@y AND month=@m AND (del_status IS NULL OR del_status='Live')";
            if (_salaryId.HasValue) cmd.CommandText += " AND id!=@id";
            cmd.Parameters.AddWithValue("@y", year);
            cmd.Parameters.AddWithValue("@m", month);
            if (_salaryId.HasValue) cmd.Parameters.AddWithValue("@id", _salaryId.Value);
            return (long)(cmd.ExecuteScalar() ?? 0L) > 0;
        }
        catch { return false; }
    }

    private string BuildSyncPayload(int year, int month, string generatedDate, double totalAmount)
    {
        var payload = new Dictionary<string, object?>
        {
            ["reference_no"] = txtReferenceNo.Text.Trim(),
            ["year"] = year,
            ["month"] = month,
            ["generated_date"] = generatedDate,
            ["total_amount"] = totalAmount,
            ["items"] = _items.Select(i => (object?)new Dictionary<string, object?>
            {
                ["employee_id"] = i.EmployeeId,
                ["salary_amount"] = i.SalaryAmount,
                ["overtime_rate"] = i.OvertimeRate,
                ["overtime_hour"] = i.OvertimeHour,
                ["additional_amount"] = i.AdditionalAmount,
                ["deduction_amount"] = i.DeductionAmount,
                ["absent_day"] = i.AbsentDays,
                ["absent_day_amount"] = i.AbsentAmount,
                ["advance_taken"] = i.AdvanceTaken,
                ["net_salary"] = i.NetSalary,
                ["note"] = i.Note
            }).ToList(),
            ["payments"] = _payments.Select(p => (object?)new Dictionary<string, object?>
            {
                ["payment_method_id"] = p.PaymentMethodId,
                ["amount"] = p.Amount
            }).ToList()
        };
        return JsonSerializer.Serialize(payload);
    }

    private string BuildItemsPayload(long salaryId)
    {
        var payload = new Dictionary<string, object?>
        {
            ["salary_id"] = salaryId,
            ["items"] = _items.Select(i => (object?)new Dictionary<string, object?>
            {
                ["employee_id"] = i.EmployeeId,
                ["salary_amount"] = i.SalaryAmount,
                ["overtime_rate"] = i.OvertimeRate,
                ["overtime_hour"] = i.OvertimeHour,
                ["additional_amount"] = i.AdditionalAmount,
                ["deduction_amount"] = i.DeductionAmount,
                ["absent_day"] = i.AbsentDays,
                ["absent_day_amount"] = i.AbsentAmount,
                ["advance_taken"] = i.AdvanceTaken,
                ["net_salary"] = i.NetSalary,
                ["note"] = i.Note
            }).ToList()
        };
        return JsonSerializer.Serialize(payload);
    }

    private string BuildPaymentsPayload(long salaryId)
    {
        var payload = new Dictionary<string, object?>
        {
            ["salary_id"] = salaryId,
            ["payments"] = _payments.Select(p => (object?)new Dictionary<string, object?>
            {
                ["payment_method_id"] = p.PaymentMethodId,
                ["amount"] = p.Amount
            }).ToList()
        };
        return JsonSerializer.Serialize(payload);
    }

    // ═══════════ EVENTS ═══════════

    private void CmbMonth_SelectionChanged(object sender, SelectionChangedEventArgs e)
    {
        if (IsLoaded) RefreshAdvanceTaken();
    }

    private void FieldChanged(object sender, TextChangedEventArgs e)
    {
        if (IsLoaded) RefreshAdvanceTaken();
    }

    private void BtnBack_Click(object sender, RoutedEventArgs e)
    {
        _dashboard?.ShowPage(new SalaryListPage(_dashboard));
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

/// <summary>One employee salary row: card with input fields + live net-salary calc.</summary>
public class SalaryItemEditor
{
    public long EmployeeId { get; }
    public string EmployeeName { get; }
    public double SalaryAmount => GetVal(TbSalary);
    public double OvertimeRate => GetVal(TbOvertimeRate);
    public double OvertimeHour => GetVal(TbOvertimeHour);
    public double AdditionalAmount => GetVal(TbAdditional);
    public double DeductionAmount => GetVal(TbDeduction);
    public long AbsentDays => (long)GetVal(TbAbsentDay);
    public double AbsentAmount => GetVal(TbAbsentAmount);
    public double AdvanceTaken { get; private set; }
    public string Note => TbNote.Text?.Trim() ?? "";
    public double NetSalary { get; private set; }

    public Border Root { get; }
    public event Action? ValueChanged;
    public event Action? RemoveClicked;

    private readonly TextBox TbSalary = CreateBox();
    private readonly TextBox TbOvertimeRate = CreateBox("0");
    private readonly TextBox TbOvertimeHour = CreateBox("0");
    private readonly TextBox TbAdditional = CreateBox("0");
    private readonly TextBox TbDeduction = CreateBox("0");
    private readonly TextBox TbAbsentDay = CreateBox("0");
    private readonly TextBox TbAbsentAmount = CreateBox("0");
    private readonly TextBox TbAdvance = CreateBox("0");
    private readonly TextBox TbNote = CreateBox();
    private readonly TextBox TbNet = CreateBox("0");

    public SalaryItemEditor(long employeeId, string name, double salary)
    {
        EmployeeId = employeeId;
        EmployeeName = string.IsNullOrWhiteSpace(name) ? "Employee " + employeeId : name;
        TbSalary.Text = salary.ToString("F2", CultureInfo.InvariantCulture);

        Root = new Border
        {
            Background = new SolidColorBrush(Color.FromRgb(0xFF, 0xFF, 0xFF)),
            CornerRadius = new CornerRadius(10),
            BorderBrush = new SolidColorBrush(Color.FromRgb(0xE5, 0xE7, 0xEB)),
            BorderThickness = new Thickness(1),
            Margin = new Thickness(0, 0, 0, 14)
        };

        var inner = new StackPanel();

        // Card header: employee name + remove
        var header = new Grid { Background = new SolidColorBrush(Color.FromRgb(0xF8, 0xFA, 0xFC)) };
        header.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
        header.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
        var headerName = new TextBlock
        {
            Text = EmployeeName,
            FontSize = 13.5,
            FontWeight = FontWeights.SemiBold,
            Foreground = new SolidColorBrush(Color.FromRgb(0x11, 0x18, 0x27)),
            FontFamily = new FontFamily("Inter, Segoe UI"),
            VerticalAlignment = VerticalAlignment.Center,
            Margin = new Thickness(16, 11, 0, 11)
        };
        Grid.SetColumn(headerName, 0);
        header.Children.Add(headerName);
        var removeBtn = new Button
        {
            Content = "\u2715",
            FontSize = 13,
            Foreground = new SolidColorBrush(Color.FromRgb(0xEF, 0x44, 0x44)),
            Background = Brushes.Transparent,
            BorderThickness = new Thickness(0),
            Cursor = System.Windows.Input.Cursors.Hand,
            Width = 30,
            Height = 30,
            Padding = new Thickness(0),
            HorizontalAlignment = HorizontalAlignment.Right,
            VerticalAlignment = VerticalAlignment.Center,
            Margin = new Thickness(0, 0, 8, 0),
            ToolTip = "Remove Employee"
        };
        removeBtn.Click += (_, _) => RemoveClicked?.Invoke();
        Grid.SetColumn(removeBtn, 1);
        header.Children.Add(removeBtn);
        inner.Children.Add(header);

        // Body grid: label+input pairs
        var body = new Grid { Margin = new Thickness(16, 14, 16, 16) };
        body.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
        body.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
        body.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
        body.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
        body.RowDefinitions.Add(new RowDefinition { Height = GridLength.Auto });
        body.RowDefinitions.Add(new RowDefinition { Height = GridLength.Auto });
        body.RowDefinitions.Add(new RowDefinition { Height = GridLength.Auto });

        AddField(body, 0, 0, "Salary Amount", TbSalary);
        AddField(body, 1, 0, "Overtime Rate", TbOvertimeRate);
        AddField(body, 2, 0, "Overtime Hour", TbOvertimeHour);
        AddField(body, 3, 0, "Additional Amount", TbAdditional);
        AddField(body, 0, 1, "Deduction Amount", TbDeduction);
        AddField(body, 1, 1, "Absent Days", TbAbsentDay);
        AddField(body, 2, 1, "Absent Amount", TbAbsentAmount);
        AddField(body, 3, 1, "Advance Taken", TbAdvance, true);
        AddField(body, 0, 2, "Note", TbNote, span: 3);
        AddField(body, 3, 2, "Net Salary", TbNet, true);

        inner.Children.Add(body);
        Root.Child = inner;

        TbSalary.TextChanged += OnInput;
        TbOvertimeRate.TextChanged += OnInput;
        TbOvertimeHour.TextChanged += OnInput;
        TbAdditional.TextChanged += OnInput;
        TbDeduction.TextChanged += OnInput;
        TbAbsentDay.TextChanged += OnInput;
        TbAbsentAmount.TextChanged += OnInput;
    }

    private void OnInput(object sender, TextChangedEventArgs e) => ValueChanged?.Invoke();

    private static TextBox CreateBox(string initial = "")
    {
        return new TextBox
        {
            Height = 34,
            FontSize = 12.5,
            Padding = new Thickness(10, 5, 10, 5),
            BorderBrush = new SolidColorBrush(Color.FromRgb(0xCB, 0xD5, 0xE1)),
            BorderThickness = new Thickness(1),
            FontFamily = new FontFamily("Inter, Segoe UI"),
            Text = initial
        };
    }

    private static void AddField(Grid grid, int col, int row, string label, TextBox box, bool readonlyBox = false, int span = 1)
    {
        var stack = new StackPanel
        {
            Margin = new Thickness(col > 0 ? 12 : 0, row > 0 ? 14 : 0, 0, 0)
        };
        Grid.SetColumn(stack, col);
        Grid.SetRow(stack, row);
        Grid.SetColumnSpan(stack, span);
        stack.Children.Add(new TextBlock
        {
            Text = label,
            FontSize = 11.5,
            FontWeight = FontWeights.SemiBold,
            Foreground = new SolidColorBrush(Color.FromRgb(0x37, 0x41, 0x51)),
            FontFamily = new FontFamily("Inter, Segoe UI"),
            Margin = new Thickness(0, 0, 0, 5)
        });
        if (readonlyBox)
        {
            box.IsReadOnly = true;
            box.Background = new SolidColorBrush(Color.FromRgb(0xF3, 0xF4, 0xF6));
        }
        stack.Children.Add(box);
        grid.Children.Add(stack);
    }

    private static double GetVal(TextBox box)
    {
        if (box.Text == null) return 0;
        double.TryParse(box.Text.Trim(), NumberStyles.Any, CultureInfo.InvariantCulture, out double v);
        return v;
    }

    public void CalculateNet()
    {
        double overtimeAmount = OvertimeRate * OvertimeHour;
        double absentDeduction = AbsentDays * AbsentAmount;
        double net = SalaryAmount + overtimeAmount + AdditionalAmount - DeductionAmount - absentDeduction - AdvanceTaken;
        NetSalary = Math.Max(0, net);
        TbNet.Text = NetSalary.ToString("F2", CultureInfo.InvariantCulture);
    }

    public void SetAdvanceTaken(double advance)
    {
        AdvanceTaken = advance;
        TbAdvance.Text = advance.ToString("F2", CultureInfo.InvariantCulture);
    }

    public void SetValues(double overtimeRate, double overtimeHour, double additional, double deduction,
                         long absentDays, double absentAmount, double advanceTaken, string note, double netSalary)
    {
        TbOvertimeRate.Text = overtimeRate.ToString("F2", CultureInfo.InvariantCulture);
        TbOvertimeHour.Text = overtimeHour.ToString("F2", CultureInfo.InvariantCulture);
        TbAdditional.Text = additional.ToString("F2", CultureInfo.InvariantCulture);
        TbDeduction.Text = deduction.ToString("F2", CultureInfo.InvariantCulture);
        TbAbsentDay.Text = absentDays.ToString(CultureInfo.InvariantCulture);
        TbAbsentAmount.Text = absentAmount.ToString("F2", CultureInfo.InvariantCulture);
        AdvanceTaken = advanceTaken;
        TbAdvance.Text = advanceTaken.ToString("F2", CultureInfo.InvariantCulture);
        TbNote.Text = note;
        NetSalary = netSalary;
        TbNet.Text = netSalary.ToString("F2", CultureInfo.InvariantCulture);
    }
}

/// <summary>One payment method row: name + amount + remove.</summary>
public class PaymentRow
{
    public long PaymentMethodId { get; }
    public double Amount => GetVal(TbAmount);

    public Border Root { get; }
    public event Action? ValueChanged;
    public event Action? RemoveClicked;

    private readonly TextBox TbAmount = new()
    {
        Height = 34,
        FontSize = 12.5,
        Padding = new Thickness(10, 5, 10, 5),
        BorderBrush = new SolidColorBrush(Color.FromRgb(0xCB, 0xD5, 0xE1)),
        BorderThickness = new Thickness(1),
        FontFamily = new FontFamily("Inter, Segoe UI")
    };

    public PaymentRow(long paymentMethodId, string methodName)
    {
        PaymentMethodId = paymentMethodId;

        var nameLabel = new TextBlock
        {
            Text = methodName,
            FontSize = 12.5,
            FontWeight = FontWeights.SemiBold,
            Foreground = new SolidColorBrush(Color.FromRgb(0x37, 0x41, 0x51)),
            FontFamily = new FontFamily("Inter, Segoe UI"),
            VerticalAlignment = VerticalAlignment.Center,
            Margin = new Thickness(6, 0, 10, 0)
        };
        var removeBtn = new Button
        {
            Content = "\u2715",
            FontSize = 13,
            Foreground = new SolidColorBrush(Color.FromRgb(0xEF, 0x44, 0x44)),
            Background = Brushes.Transparent,
            BorderThickness = new Thickness(0),
            Cursor = System.Windows.Input.Cursors.Hand,
            Width = 30,
            Height = 30,
            Padding = new Thickness(0)
        };
        removeBtn.Click += (_, _) => RemoveClicked?.Invoke();

        var border = new Border
        {
            BorderBrush = new SolidColorBrush(Color.FromRgb(0xE5, 0xE7, 0xEB)),
            BorderThickness = new Thickness(1),
            CornerRadius = new CornerRadius(8),
            Margin = new Thickness(0, 0, 0, 8),
            Padding = new Thickness(10, 6, 6, 6)
        };
        var grid = new Grid();
        grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
        grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(2, GridUnitType.Star) });
        grid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
        Grid.SetColumn(nameLabel, 0);
        Grid.SetColumn(TbAmount, 1);
        Grid.SetColumn(removeBtn, 2);
        TbAmount.Margin = new Thickness(0, 0, 8, 0);
        grid.Children.Add(nameLabel);
        grid.Children.Add(TbAmount);
        grid.Children.Add(removeBtn);

        border.Child = grid;
        Root = border;

        TbAmount.TextChanged += (_, _) => ValueChanged?.Invoke();
    }

    public void SetAmount(double amount) => TbAmount.Text = amount.ToString("F2", CultureInfo.InvariantCulture);

    private static double GetVal(TextBox box)
    {
        if (box.Text == null) return 0;
        double.TryParse(box.Text.Trim(), NumberStyles.Any, CultureInfo.InvariantCulture, out double v);
        return v;
    }
}
