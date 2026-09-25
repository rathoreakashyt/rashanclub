using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views;

public partial class AccountBalancePage : UserControl
{
    private MainDashboard? _dashboard;
    private readonly ApiService _api = new();

    public AccountBalancePage() { InitializeComponent(); Loaded += OnLoaded; }
    public AccountBalancePage(MainDashboard dashboard) : this() { _dashboard = dashboard; }

    private async void OnLoaded(object sender, RoutedEventArgs e) => await LoadFromCloud();

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

    private async Task LoadFromCloud()
    {
        try
        {
            var data = await _api.GetAccountBalanceAsync();
            if (data != null && data.Count > 0)
            {
                var rows = new List<AccountBalanceRow>();
                decimal total = 0;
                int sn = 1;
                foreach (var item in data)
                {
                    var balStr = item.Balance.Replace("INR", "").Replace(",", "").Trim();
                    decimal.TryParse(balStr, System.Globalization.NumberStyles.Any, System.Globalization.CultureInfo.InvariantCulture, out var bal);
                    total += bal;
                    rows.Add(new AccountBalanceRow
                    {
                        Sn = sn++,
                        AccountName = item.AccountName,
                        Balance = bal,
                        BalanceDisplay = FormatINR(bal)
                    });
                }
                itemsList.ItemsSource = rows;
                txtTotal.Text = FormatINR(total);
                SetSource("Cloud data", "#E8F5E9", "#16A34A");
                return;
            }
        }
        catch { }

        // Fallback to local calculation if cloud fails
        SetSource("Local data (offline)", "#FFF3E0", "#E67E22");
        LoadLocal();
    }

    private void LoadLocal()
    {
        var rows = new List<AccountBalanceRow>();
        decimal total = 0;
        using var conn = new Database.DatabaseService().GetConnection();
        conn.Open();

        var pmCmd = conn.CreateCommand();
        pmCmd.CommandText = @"SELECT id, name FROM payment_methods 
                              WHERE (del_status IS NULL OR del_status='Live') 
                              AND (status IS NULL OR status='Enable')
                              AND (account_type IS NULL OR account_type != 'Loyalty Point')
                              ORDER BY sort_id, id";
        using var pmReader = pmCmd.ExecuteReader();
        var paymentMethods = new List<(long id, string name)>();
        while (pmReader.Read())
            paymentMethods.Add((pmReader.GetInt64(0), pmReader.IsDBNull(1) ? "" : pmReader.GetString(1)));
        pmReader.Close();

        foreach (var (pmId, pmName) in paymentMethods)
        {
            decimal balance = CalculateBalance(conn, pmId);
            total += balance;
            rows.Add(new AccountBalanceRow
            {
                Sn = rows.Count + 1,
                AccountName = pmName,
                Balance = balance,
                BalanceDisplay = FormatINR(balance)
            });
        }
        itemsList.ItemsSource = rows;
        txtTotal.Text = FormatINR(total);
    }

    private decimal CalculateBalance(System.Data.IDbConnection conn, long pmId)
    {
        decimal b = 0;
        b += SumQ(conn, "SELECT IFNULL(current_balance,0) FROM payment_methods WHERE id=@pm", pmId);
        b += SumQ(conn, "SELECT IFNULL(SUM(amount),0) FROM sale_payments WHERE (del_status IS NULL OR del_status='Live') AND payment_id=@pm", pmId);
        b -= SumQ(conn, "SELECT IFNULL(SUM(paid),0) FROM sale_returns WHERE (del_status IS NULL OR del_status='Live') AND payment_method_id=@pm", pmId);
        b += SumQ(conn, "SELECT IFNULL(SUM(down_payment),0) FROM installment_sales WHERE (del_status IS NULL OR del_status='Live') AND payment_method_id=@pm", pmId);
        b += SumQ(conn, @"SELECT IFNULL(SUM(isd.paid_amount),0) FROM installment_sale_details isd 
                          JOIN installment_sales isl ON isl.Id=isd.installment_sale_id 
                          WHERE (isd.del_status IS NULL OR isd.del_status='Live') 
                          AND (isl.del_status IS NULL OR isl.del_status='Live')
                          AND isd.payment_method_id=@pm AND isd.paid_status IN ('Paid','Partial')", pmId);
        b += SumQ(conn, "SELECT IFNULL(SUM(amount),0) FROM customer_receives WHERE (del_status IS NULL OR del_status='Live') AND payment_method_id=@pm", pmId);
        b += SumQ(conn, "SELECT IFNULL(SUM(amount),0) FROM incomes WHERE (del_status IS NULL OR del_status='Live') AND payment_method_id=@pm", pmId);
        b += SumQ(conn, "SELECT IFNULL(SUM(total_return_amount),0) FROM purchase_returns WHERE (del_status IS NULL OR del_status='Live') AND payment_method_id=@pm", pmId);
        b += SumQ(conn, "SELECT IFNULL(SUM(amount),0) FROM deposit_withdraws WHERE (del_status IS NULL OR del_status='Live') AND payment_method_id=@pm AND type='Deposit'", pmId);
        b -= SumQ(conn, "SELECT IFNULL(SUM(amount),0) FROM purchase_payments WHERE (del_status IS NULL OR del_status='Live') AND payment_id=@pm", pmId);
        b -= SumQ(conn, "SELECT IFNULL(SUM(amount),0) FROM supplier_payments WHERE (del_status IS NULL OR del_status='Live') AND payment_method_id=@pm", pmId);
        b -= SumQ(conn, "SELECT IFNULL(SUM(amount),0) FROM expenses WHERE (del_status IS NULL OR del_status='Live') AND payment_method_id=@pm", pmId);
        b -= SumQ(conn, "SELECT IFNULL(SUM(paid_amount),0) FROM servicings WHERE (del_status IS NULL OR del_status='Live') AND payment_method_id=@pm", pmId);
        b -= SumQ(conn, "SELECT IFNULL(SUM(amount),0) FROM deposit_withdraws WHERE (del_status IS NULL OR del_status='Live') AND payment_method_id=@pm AND type='Withdraw'", pmId);
        b -= SumQ(conn, "SELECT IFNULL(SUM(amount),0) FROM salary_payments WHERE (del_status IS NULL OR del_status='Live') AND payment_method_id=@pm", pmId);
        b -= SumQ(conn, "SELECT IFNULL(SUM(amount),0) FROM employee_advance_payments WHERE (del_status IS NULL OR del_status='Live') AND payment_method_id=@pm", pmId);
        return b;
    }

    private decimal SumQ(System.Data.IDbConnection conn, string sql, long pmId)
    {
        using var cmd = conn.CreateCommand();
        cmd.CommandText = sql;
        cmd.Parameters.Add(new Microsoft.Data.Sqlite.SqliteParameter("@pm", pmId));
        return Convert.ToDecimal(cmd.ExecuteScalar() ?? 0);
    }

    private string FormatINR(decimal amount)
    {
        string prefix = amount < 0 ? "INR-" : "INR";
        return prefix + Math.Abs(amount).ToString("N2");
    }

    private void BtnBack_Click(object sender, RoutedEventArgs e)
    {
        _dashboard?.ShowPage(new ReportsPage(_dashboard));
    }
}

public class AccountBalanceRow
{
    public int Sn { get; set; }
    public string AccountName { get; set; } = "";
    public decimal Balance { get; set; }
    public string BalanceDisplay { get; set; } = "INR0.00";
}
