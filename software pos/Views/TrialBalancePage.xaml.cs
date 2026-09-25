using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views;

public partial class TrialBalancePage : UserControl
{
    private MainDashboard? _dashboard;
    private readonly ApiService _api = new();
    private readonly DatabaseService _db = new DatabaseService();

    public TrialBalancePage() { InitializeComponent(); Loaded += OnLoaded; }
    public TrialBalancePage(MainDashboard dashboard) : this() { _dashboard = dashboard; }

    private void OnLoaded(object sender, RoutedEventArgs e)
    {
        _ = LoadCloudAsync();
    }

    private async void BtnApply_Click(object sender, RoutedEventArgs e) => await LoadCloudAsync();

    private void SetSource(string text, string bg, string fg)
    {
        txtSource.Text = text;
        badgeSource.Background = new System.Windows.Media.SolidColorBrush(HexToColor(bg));
        badgeSource.BorderBrush = new System.Windows.Media.SolidColorBrush(HexToColor(fg));
        txtSource.Foreground = new System.Windows.Media.SolidColorBrush(HexToColor(fg));
    }

    private static System.Windows.Media.Color HexToColor(string hex)
    {
        try { return (System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString(hex); }
        catch { return System.Windows.Media.Colors.Gray; }
    }

    private async Task LoadCloudAsync()
    {
        var dateFrom = dpFrom.SelectedDate?.ToString("yyyy-MM-dd") ?? "";
        var dateTo = dpTo.SelectedDate?.ToString("yyyy-MM-dd") ?? "";

        // Try cloud first
        try
        {
            var data = await _api.GetTrialBalanceAsync(dateFrom, dateTo);
            if (data != null && data.Rows.Count > 0)
            {
                var rows = new List<TrialBalanceRow>();
                foreach (var item in data.Rows)
                    rows.Add(new TrialBalanceRow { Sn = item.Sn, AccountName = item.Title, Debit = FormatINR(item.Debit), Credit = FormatINR(item.Credit) });
                itemsList.ItemsSource = rows;
                txtTotalDebit.Text = FormatINR(data.TotalDebit);
                txtTotalCredit.Text = FormatINR(data.TotalCredit);
                SetSource("Cloud data", "#E8F5E9", "#16A34A");
                return;
            }
        }
        catch { }

        // Fallback to local
        SetSource("Local data (offline)", "#FFF3E0", "#E67E22");
        LoadLocal(dateFrom, dateTo);
    }

    private void LoadLocal(string dateFrom, string dateTo)
    {
        var rows = new List<TrialBalanceRow>();
        decimal totalDebit = 0, totalCredit = 0;
        int sn = 1;
        using var conn = _db.GetConnection();
        conn.Open();

        string dateCond(string col) => dateFrom == "" && dateTo == ""
            ? ""
            : dateFrom == "" ? $" AND {col} <= '{dateTo}'"
            : dateTo == "" ? $" AND {col} >= '{dateFrom}'"
            : $" AND {col} BETWEEN '{dateFrom}' AND '{dateTo}'";

        // 1. Payment methods (opening + in - out)
        var pms = new List<(string Name, decimal Balance)>();
        using (var pmCmd = conn.CreateCommand())
        {
            pmCmd.CommandText = "SELECT id, name, IFNULL(current_balance,0) FROM payment_methods WHERE (del_status IS NULL OR del_status='Live') AND status='Enable' AND IFNULL(account_type,'') != 'Loyalty Point' ORDER BY name";
            using var pmR = pmCmd.ExecuteReader();
            while (pmR.Read())
            {
                long pmId = pmR.GetInt64(0);
                string name = pmR.IsDBNull(1) ? "" : pmR.GetString(1);
                decimal balance = pmR.IsDBNull(2) ? 0m : pmR.GetDecimal(2);
                string[] creditSqls = {
                    $"SELECT IFNULL(SUM(amount),0) FROM sale_payments WHERE payment_id={pmId} AND (del_status IS NULL OR del_status='Live')",
                    $"SELECT IFNULL(SUM(down_payment),0) FROM installment_sales WHERE payment_method_id={pmId} AND (del_status IS NULL OR del_status='Live')",
                    $"SELECT IFNULL(SUM(isd.paid_amount),0) FROM installment_sale_details isd JOIN installment_sales isl ON isl.Id=isd.installment_sale_id WHERE isd.payment_method_id={pmId} AND isd.del_status='Live' AND isd.paid_status IN ('Paid','Partial') AND isl.del_status='Live'",
                    $"SELECT IFNULL(SUM(amount),0) FROM customer_receives WHERE payment_method_id={pmId} AND (del_status IS NULL OR del_status='Live')",
                    $"SELECT IFNULL(SUM(amount),0) FROM incomes WHERE payment_method_id={pmId} AND (del_status IS NULL OR del_status='Live')",
                    $"SELECT IFNULL(SUM(total_return_amount),0) FROM purchase_returns WHERE payment_method_id={pmId} AND (del_status IS NULL OR del_status='Live')",
                    $"SELECT IFNULL(SUM(amount),0) FROM deposit_withdraws WHERE payment_method_id={pmId} AND type='Deposit' AND (del_status IS NULL OR del_status='Live')"
                };
                string[] debitSqls = {
                    $"SELECT IFNULL(SUM(paid),0) FROM sale_returns WHERE payment_method_id={pmId} AND (del_status IS NULL OR del_status='Live')",
                    $"SELECT IFNULL(SUM(amount),0) FROM purchase_payments WHERE payment_id={pmId} AND (del_status IS NULL OR del_status='Live')",
                    $"SELECT IFNULL(SUM(amount),0) FROM supplier_payments WHERE payment_method_id={pmId} AND (del_status IS NULL OR del_status='Live')",
                    $"SELECT IFNULL(SUM(amount),0) FROM expenses WHERE payment_method_id={pmId} AND (del_status IS NULL OR del_status='Live')",
                    $"SELECT IFNULL(SUM(paid_amount),0) FROM servicings WHERE payment_method_id={pmId} AND (del_status IS NULL OR del_status='Live')",
                    $"SELECT IFNULL(SUM(amount),0) FROM deposit_withdraws WHERE payment_method_id={pmId} AND type='Withdraw' AND (del_status IS NULL OR del_status='Live')",
                    $"SELECT IFNULL(SUM(amount),0) FROM salary_payments WHERE payment_method_id={pmId} AND (del_status IS NULL OR del_status='Live')",
                    $"SELECT IFNULL(SUM(amount),0) FROM employee_advance_payments WHERE payment_method_id={pmId} AND (del_status IS NULL OR del_status='Live')"
                };
                foreach (var sql in creditSqls)
                {
                    using var c = conn.CreateCommand();
                    c.CommandText = sql;
                    balance += Convert.ToDecimal(c.ExecuteScalar() ?? 0);
                }
                foreach (var sql in debitSqls)
                {
                    using var c = conn.CreateCommand();
                    c.CommandText = sql;
                    balance -= Convert.ToDecimal(c.ExecuteScalar() ?? 0);
                }
                pms.Add((name, balance));
            }
        }
        foreach (var (name, balance) in pms)
        {
            if (balance > 0) { rows.Add(new TrialBalanceRow { Sn = sn++, AccountName = name, Debit = balance.ToString("N2"), Credit = "" }); totalDebit += balance; }
            else if (balance < 0) { rows.Add(new TrialBalanceRow { Sn = sn++, AccountName = name, Debit = "", Credit = Math.Abs(balance).ToString("N2") }); totalCredit += Math.Abs(balance); }
        }

        // 2. Customer Due (asset)
        var cmd = conn.CreateCommand();
        cmd.CommandText = $"SELECT IFNULL(SUM(due_amount),0) FROM sales WHERE (del_status IS NULL OR del_status='Live'){dateCond("sale_date")}";
        var customerDue = Convert.ToDecimal(cmd.ExecuteScalar() ?? 0);
        if (customerDue > 0) { rows.Add(new TrialBalanceRow { Sn = sn++, AccountName = "Customer Due", Debit = customerDue.ToString("N2"), Credit = "" }); totalDebit += customerDue; }

        // 3. Installment Receivable: per sale (total - down_payment) - SUM(paid_amount)
        decimal installmentReceivable = 0;
        using (var rCmd = conn.CreateCommand())
        {
            rCmd.CommandText = $"SELECT Id, IFNULL(total,0), IFNULL(down_payment,0) FROM installment_sales WHERE (del_status IS NULL OR del_status='Live'){dateCond("date")}";
            using var rR = rCmd.ExecuteReader();
            while (rR.Read())
            {
                long islId = rR.GetInt64(0);
                decimal total = rR.IsDBNull(1) ? 0m : rR.GetDecimal(1);
                decimal down = rR.IsDBNull(2) ? 0m : rR.GetDecimal(2);
                using var pCmd = conn.CreateCommand();
                pCmd.CommandText = $"SELECT IFNULL(SUM(paid_amount),0) FROM installment_sale_details WHERE installment_sale_id={islId} AND del_status='Live'";
                decimal paid = Convert.ToDecimal(pCmd.ExecuteScalar() ?? 0);
                decimal due = total - down - paid;
                if (due > 0) installmentReceivable += due;
            }
        }
        if (installmentReceivable > 0) { rows.Add(new TrialBalanceRow { Sn = sn++, AccountName = "Installment Receivable", Debit = installmentReceivable.ToString("N2"), Credit = "" }); totalDebit += installmentReceivable; }

        // 4. Employee Advance (asset)
        cmd = conn.CreateCommand();
        cmd.CommandText = $"SELECT IFNULL(SUM(amount),0) FROM employee_advance_payments WHERE (del_status IS NULL OR del_status='Live'){dateCond("date")}";
        var employeeAdvance = Convert.ToDecimal(cmd.ExecuteScalar() ?? 0);
        if (employeeAdvance > 0) { rows.Add(new TrialBalanceRow { Sn = sn++, AccountName = "Employee Advance", Debit = employeeAdvance.ToString("N2"), Credit = "" }); totalDebit += employeeAdvance; }

        // 5. Purchase (expense)
        cmd = conn.CreateCommand();
        cmd.CommandText = $"SELECT IFNULL(SUM(amount),0) FROM purchase_payments WHERE (del_status IS NULL OR del_status='Live'){dateCond("date")}";
        var purchase = Convert.ToDecimal(cmd.ExecuteScalar() ?? 0);
        if (purchase > 0) { rows.Add(new TrialBalanceRow { Sn = sn++, AccountName = "Purchase", Debit = purchase.ToString("N2"), Credit = "" }); totalDebit += purchase; }

        // 6. Expense
        cmd = conn.CreateCommand();
        cmd.CommandText = $"SELECT IFNULL(SUM(amount),0) FROM expenses WHERE (del_status IS NULL OR del_status='Live'){dateCond("date")}";
        var expense = Convert.ToDecimal(cmd.ExecuteScalar() ?? 0);
        if (expense > 0) { rows.Add(new TrialBalanceRow { Sn = sn++, AccountName = "Expense", Debit = expense.ToString("N2"), Credit = "" }); totalDebit += expense; }

        // 7. Salary Expense (salary_payments primary; fall back to salary_items for local mirror)
        cmd = conn.CreateCommand();
        cmd.CommandText = $"SELECT IFNULL(SUM(sp.amount),0) FROM salary_payments sp JOIN salaries s ON s.Id=sp.salary_id WHERE (sp.del_status IS NULL OR sp.del_status='Live'){dateCond("s.generated_date")}";
        var salaryExpense = Convert.ToDecimal(cmd.ExecuteScalar() ?? 0);
        if (salaryExpense == 0)
        {
            cmd = conn.CreateCommand();
            cmd.CommandText = $"SELECT IFNULL(SUM(si.salary_amount),0) FROM salary_items si JOIN salaries s ON s.Id=si.salary_id WHERE (s.del_status IS NULL OR s.del_status='Live'){dateCond("s.generated_date")}";
            salaryExpense = Convert.ToDecimal(cmd.ExecuteScalar() ?? 0);
        }
        if (salaryExpense > 0) { rows.Add(new TrialBalanceRow { Sn = sn++, AccountName = "Salary Expense", Debit = salaryExpense.ToString("N2"), Credit = "" }); totalDebit += salaryExpense; }

        // 8. Withdraw
        cmd = conn.CreateCommand();
        cmd.CommandText = $"SELECT IFNULL(SUM(amount),0) FROM deposit_withdraws WHERE type='Withdraw' AND (del_status IS NULL OR del_status='Live'){dateCond("date")}";
        var withdraw = Convert.ToDecimal(cmd.ExecuteScalar() ?? 0);
        if (withdraw > 0) { rows.Add(new TrialBalanceRow { Sn = sn++, AccountName = "Withdraw", Debit = withdraw.ToString("N2"), Credit = "" }); totalDebit += withdraw; }

        // 9. Sales (revenue)
        cmd = conn.CreateCommand();
        cmd.CommandText = $"SELECT IFNULL(SUM(total_payable),0) FROM sales WHERE (del_status IS NULL OR del_status='Live'){dateCond("sale_date")}";
        var sales = Convert.ToDecimal(cmd.ExecuteScalar() ?? 0);
        if (sales > 0) { rows.Add(new TrialBalanceRow { Sn = sn++, AccountName = "Sales", Debit = "", Credit = sales.ToString("N2") }); totalCredit += sales; }

        // 10. Service Income
        cmd = conn.CreateCommand();
        cmd.CommandText = $"SELECT IFNULL(SUM(amount),0) FROM incomes WHERE (del_status IS NULL OR del_status='Live'){dateCond("date")}";
        var serviceIncome = Convert.ToDecimal(cmd.ExecuteScalar() ?? 0);
        if (serviceIncome > 0) { rows.Add(new TrialBalanceRow { Sn = sn++, AccountName = "Service Income", Debit = "", Credit = serviceIncome.ToString("N2") }); totalCredit += serviceIncome; }

        // 11. Supplier Due (liability)
        cmd = conn.CreateCommand();
        cmd.CommandText = $"SELECT IFNULL(SUM(due_amount),0) FROM purchases WHERE (del_status IS NULL OR del_status='Live'){dateCond("date")}";
        var supplierDue = Convert.ToDecimal(cmd.ExecuteScalar() ?? 0);
        if (supplierDue > 0) { rows.Add(new TrialBalanceRow { Sn = sn++, AccountName = "Supplier Due", Debit = "", Credit = supplierDue.ToString("N2") }); totalCredit += supplierDue; }

        // 12. Owner Capital (balancing)
        var ownerCapital = totalDebit - totalCredit;
        if (ownerCapital > 0) { rows.Add(new TrialBalanceRow { Sn = sn++, AccountName = "Owner Capital", Debit = "", Credit = ownerCapital.ToString("N2") }); totalCredit += ownerCapital; }
        else if (ownerCapital < 0) { rows.Add(new TrialBalanceRow { Sn = sn++, AccountName = "Owner Capital", Debit = Math.Abs(ownerCapital).ToString("N2"), Credit = "" }); totalDebit += Math.Abs(ownerCapital); }

        itemsList.ItemsSource = rows;
        txtTotalDebit.Text = totalDebit.ToString("N2");
        txtTotalCredit.Text = totalCredit.ToString("N2");
    }

    private string FormatINR(string value)
    {
        if (string.IsNullOrWhiteSpace(value)) return "";
        value = value.Replace("INR", "").Replace(",", "").Trim();
        decimal.TryParse(value, System.Globalization.NumberStyles.Any, System.Globalization.CultureInfo.InvariantCulture, out var v);
        return v == 0 && value == "" ? "" : v.ToString("N2");
    }

    private void BtnBack_Click(object sender, RoutedEventArgs e)
    {
        _dashboard?.ShowPage(new ReportsPage(_dashboard));
    }
}

public class TrialBalanceRow
{
    public int Sn { get; set; }
    public string AccountName { get; set; } = "";
    public string Debit { get; set; } = "";
    public string Credit { get; set; } = "";
}
