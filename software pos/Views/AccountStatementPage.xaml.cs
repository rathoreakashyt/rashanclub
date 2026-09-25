using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;
using RashanKiDukan.Services;
using Microsoft.Data.Sqlite;

namespace RashanKiDukan.Views;

public partial class AccountStatementPage : UserControl
{
    private MainDashboard? _dashboard;
    private readonly ApiService _api = new();
    private readonly DatabaseService _db = new DatabaseService();

    public AccountStatementPage() { InitializeComponent(); Loaded += OnLoaded; }
    public AccountStatementPage(MainDashboard dashboard) : this() { _dashboard = dashboard; }

    private void OnLoaded(object sender, RoutedEventArgs e)
    {
        LoadPaymentMethods();
        dpFrom.SelectedDate = DateTime.Today.AddMonths(-1);
        dpTo.SelectedDate = DateTime.Today;
    }

    private void LoadPaymentMethods()
    {
        var methods = new List<PaymentMethodItem>();
        using var conn = _db.GetConnection();
        conn.Open();
        var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT id, name FROM payment_methods WHERE (del_status IS NULL OR del_status='Live') AND status='Enable' ORDER BY name";
        using var r = cmd.ExecuteReader();
        while (r.Read())
            methods.Add(new PaymentMethodItem { Id = r.GetInt64(0), Name = r.IsDBNull(1) ? "" : r.GetString(1) });
        cmbPaymentMethod.ItemsSource = methods;
        if (methods.Count > 0) cmbPaymentMethod.SelectedIndex = 0;
    }

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

    private async void BtnApply_Click(object sender, RoutedEventArgs e)
    {
        if (cmbPaymentMethod.SelectedItem is not PaymentMethodItem pm)
        { MessageBox.Show("Please select a Payment Method.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning); return; }

        var dateFrom = dpFrom.SelectedDate?.ToString("yyyy-MM-dd") ?? "";
        var dateTo = dpTo.SelectedDate?.ToString("yyyy-MM-dd") ?? "";

        // Try cloud first
        try
        {
            var data = await _api.GetAccountStatementAsync(pm.Id, dateFrom, dateTo);
            if (data != null && data.Count > 0)
            {
                var rows = new List<AccountStatementRow>();
                decimal totalDebit = 0, totalCredit = 0, lastBalance = 0;
                foreach (var item in data)
                {
                    decimal.TryParse(item.Debit.Replace("INR", "").Replace(",", ""), System.Globalization.NumberStyles.Any, System.Globalization.CultureInfo.InvariantCulture, out var dr);
                    decimal.TryParse(item.Credit.Replace("INR", "").Replace(",", ""), System.Globalization.NumberStyles.Any, System.Globalization.CultureInfo.InvariantCulture, out var cr);
                    decimal.TryParse(item.Balance.Replace("INR", "").Replace(",", ""), System.Globalization.NumberStyles.Any, System.Globalization.CultureInfo.InvariantCulture, out var bal);
                    totalDebit += dr;
                    totalCredit += cr;
                    lastBalance = bal;
                    rows.Add(new AccountStatementRow
                    {
                        Sn = item.Sn,
                        Date = item.Date,
                        Title = item.Title,
                        Debit = dr > 0 ? FormatINR(dr) : "",
                        Credit = cr > 0 ? FormatINR(cr) : "",
                        Balance = FormatINR(bal),
                        AddedDateTime = item.AddedDateTime
                    });
                }
                itemsList.ItemsSource = rows;
                txtTotalDebit.Text = FormatINR(totalDebit);
                txtTotalCredit.Text = FormatINR(totalCredit);
                txtClosing.Text = FormatINR(lastBalance);
                SetSource("Cloud data", "#E8F5E9", "#16A34A");
                return;
            }
        }
        catch { }

        // Fallback to local
        SetSource("Local data (offline)", "#FFF3E0", "#E67E22");
        LoadLocal(pm.Id, dateFrom, dateTo);
    }

    private void LoadLocal(long pmId, string dateFrom, string dateTo)
    {
        var rows = new List<AccountStatementRow>();
        decimal totalDebit = 0, totalCredit = 0, balance = 0;
        using var conn = _db.GetConnection();
        conn.Open();

        decimal openingBalance = ComputeOpeningBalance(conn, pmId, dateFrom);

        var cmd = conn.CreateCommand();
        cmd.CommandText = @"SELECT e.date, e.reference_no, 'Expense' AS type, IFNULL(e.amount,0) AS amount, COALESCE(e.created_at,'') AS created_at
                            FROM expenses e WHERE e.payment_method_id=@pm AND e.date>=@from AND e.date<=@to
                            AND (e.del_status IS NULL OR e.del_status='Live')
                            UNION ALL
                            SELECT i.date, i.reference_no, 'Income', IFNULL(i.amount,0), COALESCE(i.created_at,'')
                            FROM incomes i WHERE i.payment_method_id=@pm AND i.date>=@from AND i.date<=@to
                            AND (i.del_status IS NULL OR i.del_status='Live')
                            UNION ALL
                            SELECT dw.date, dw.reference_no, dw.type, IFNULL(dw.amount,0), COALESCE(dw.created_at,'')
                            FROM deposit_withdraws dw WHERE dw.payment_method_id=@pm AND dw.date>=@from AND dw.date<=@to
                            AND (dw.del_status IS NULL OR dw.del_status='Live')
                            ORDER BY date ASC";
        cmd.Parameters.Add(new SqliteParameter("@pm", pmId));
        cmd.Parameters.Add(new SqliteParameter("@from", dateFrom));
        cmd.Parameters.Add(new SqliteParameter("@to", dateTo));

        int sn = 1;
        using (var r = cmd.ExecuteReader())
            while (r.Read())
            {
                var date = r.IsDBNull(0) ? "" : r.GetDateTime(0).ToString("yyyy-MM-dd");
                var refNo = r.IsDBNull(1) ? "" : r.GetString(1);
                var type = r.IsDBNull(2) ? "" : r.GetString(2);
                var amount = r.IsDBNull(3) ? 0m : r.GetDecimal(3);
                var created = r.IsDBNull(4) ? "" : r.GetString(4);
                string debit = "", credit = "";
                if (type == "Expense" || type == "Withdraw") { debit = FormatINR(amount); totalDebit += amount; balance -= amount; }
                else { credit = FormatINR(amount); totalCredit += amount; balance += amount; }
                rows.Add(new AccountStatementRow
                {
                    Sn = sn++,
                    Date = date,
                    Title = $"{type} - {refNo}",
                    Debit = debit,
                    Credit = credit,
                    Balance = FormatINR(openingBalance + balance),
                    AddedDateTime = created
                });
            }

        if (rows.Count > 0)
            rows.Insert(0, new AccountStatementRow { Sn = 0, Date = dateFrom, Title = "Opening Balance", Debit = "", Credit = "", Balance = FormatINR(openingBalance), AddedDateTime = "" });

        itemsList.ItemsSource = rows;
        txtTotalDebit.Text = FormatINR(totalDebit);
        txtTotalCredit.Text = FormatINR(totalCredit);
        txtClosing.Text = FormatINR(openingBalance + balance);
    }

    private decimal ComputeOpeningBalance(System.Data.IDbConnection conn, long pmId, string dateBefore)
    {
        decimal balance = 0;
        string[] creditTables = {
            "SELECT IFNULL(SUM(paid_amount),0) FROM sales WHERE payment_method_id=@pm AND del_status='Live' AND sale_date<@d",
            "SELECT IFNULL(SUM(down_payment),0) FROM installment_sales WHERE payment_method_id=@pm AND del_status='Live' AND date<@d",
            "SELECT IFNULL(SUM(paid_amount),0) FROM installment_sale_details isd JOIN installment_sales isl ON isl.Id=isd.installment_sale_id WHERE isl.payment_method_id=@pm AND isl.del_status='Live' AND isd.payment_date<@d",
            "SELECT IFNULL(SUM(amount),0) FROM customer_receives WHERE payment_method_id=@pm AND del_status='Live' AND date<@d",
            "SELECT IFNULL(SUM(amount),0) FROM incomes WHERE payment_method_id=@pm AND del_status='Live' AND date<@d",
            "SELECT IFNULL(SUM(total_return_amount),0) FROM purchase_returns WHERE payment_method_id=@pm AND del_status='Live' AND date<@d",
            "SELECT IFNULL(SUM(amount),0) FROM deposit_withdraws WHERE payment_method_id=@pm AND del_status='Live' AND type='Deposit' AND date<@d"
        };
        string[] debitTables = {
            "SELECT IFNULL(SUM(paid_amount),0) FROM sale_returns WHERE payment_method_id=@pm AND del_status='Live' AND date<@d",
            "SELECT IFNULL(SUM(amount),0) FROM purchase_payments WHERE payment_method_id=@pm AND del_status='Live' AND date<@d",
            "SELECT IFNULL(SUM(amount),0) FROM supplier_payments WHERE payment_method_id=@pm AND del_status='Live' AND date<@d",
            "SELECT IFNULL(SUM(amount),0) FROM expenses WHERE payment_method_id=@pm AND del_status='Live' AND date<@d",
            "SELECT IFNULL(SUM(amount),0) FROM deposit_withdraws WHERE payment_method_id=@pm AND del_status='Live' AND type='Withdraw' AND date<@d"
        };
        foreach (var sql in creditTables)
        {
            using var cmd = conn.CreateCommand();
            cmd.CommandText = sql;
            cmd.Parameters.Add(new SqliteParameter("@pm", pmId));
            cmd.Parameters.Add(new SqliteParameter("@d", dateBefore));
            balance += Convert.ToDecimal(cmd.ExecuteScalar() ?? 0);
        }
        foreach (var sql in debitTables)
        {
            using var cmd = conn.CreateCommand();
            cmd.CommandText = sql;
            cmd.Parameters.Add(new SqliteParameter("@pm", pmId));
            cmd.Parameters.Add(new SqliteParameter("@d", dateBefore));
            balance -= Convert.ToDecimal(cmd.ExecuteScalar() ?? 0);
        }
        return balance;
    }

    private string FormatINR(decimal amount)
    {
        string prefix = amount < 0 ? "INR-" : "INR";
        return prefix + Math.Abs(amount).ToString("N2");
    }

    private void BtnBack_Click(object sender, RoutedEventArgs e) => _dashboard?.ShowPage(new ReportsPage(_dashboard));
}

public class AccountStatementRow
{
    public int Sn { get; set; }
    public string Date { get; set; } = "";
    public string Title { get; set; } = "";
    public string Debit { get; set; } = "";
    public string Credit { get; set; } = "";
    public string Balance { get; set; } = "INR0.00";
    public string AddedDateTime { get; set; } = "";
}
