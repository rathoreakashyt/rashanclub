using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views;

public partial class BalanceSheetPage : UserControl
{
    private MainDashboard? _dashboard;
    private readonly ApiService _api = new();
    private readonly DatabaseService _db = new DatabaseService();

    public BalanceSheetPage() { InitializeComponent(); Loaded += OnLoaded; }
    public BalanceSheetPage(MainDashboard dashboard) : this() { _dashboard = dashboard; }

    private void OnLoaded(object sender, RoutedEventArgs e) => _ = LoadCloudAsync();

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
        // Try cloud first
        try
        {
            var data = await _api.GetBalanceSheetAsync();
            if (data != null && data.Assets.Count + data.Liabilities.Count > 0)
            {
                var rows = new List<BalanceSheetRow>();
                rows.Add(new BalanceSheetRow { Sn = 0, Title = "Assets", Amount = "", IsSection = true });
                foreach (var item in data.Assets)
                    rows.Add(new BalanceSheetRow { Sn = item.Sn, Title = item.Title, Amount = FormatINR(item.Amount), IsSection = false });
                rows.Add(new BalanceSheetRow { Sn = 0, Title = "Total Assets:", Amount = FormatINR(data.TotalAssets), IsSection = true });

                rows.Add(new BalanceSheetRow { Sn = 0, Title = "Liabilities", Amount = "", IsSection = true });
                foreach (var item in data.Liabilities)
                    rows.Add(new BalanceSheetRow { Sn = item.Sn, Title = item.Title, Amount = FormatINR(item.Amount), IsSection = false });
                rows.Add(new BalanceSheetRow { Sn = 0, Title = "Total Liabilities:", Amount = FormatINR(data.TotalLiabilities), IsSection = true });

                rows.Add(new BalanceSheetRow { Sn = 0, Title = "Summary", Amount = "", IsSection = true });
                rows.Add(new BalanceSheetRow { Sn = 0, Title = "Total Assets:", Amount = FormatINR(data.TotalAssets), IsSection = true });
                rows.Add(new BalanceSheetRow { Sn = 0, Title = "Total Liabilities:", Amount = FormatINR(data.TotalLiabilities), IsSection = true });
                rows.Add(new BalanceSheetRow { Sn = 0, Title = "Net Worth:", Amount = FormatINR(data.NetWorth), IsSection = true });

                itemsList.ItemsSource = rows;
                SetSource("Cloud data", "#E8F5E9", "#16A34A");
                return;
            }
        }
        catch { }

        // Fallback to local
        SetSource("Local data (offline)", "#FFF3E0", "#E67E22");
        LoadLocal();
    }

    private void LoadLocal()
    {
        var rows = new List<BalanceSheetRow>();
        int sn = 1;
        using var conn = _db.GetConnection();
        conn.Open();

        // ASSETS
        rows.Add(new BalanceSheetRow { Sn = 0, Title = "Assets", Amount = "", IsSection = true });

        // Stock Value
        var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT IFNULL(SUM(stock_quantity * purchase_price),0) FROM items WHERE (del_status IS NULL OR del_status='Live')";
        var stockValue = Convert.ToDecimal(cmd.ExecuteScalar() ?? 0);
        rows.Add(new BalanceSheetRow { Sn = sn++, Title = "Current Stock Value", Amount = stockValue.ToString("N2"), IsSection = false });

        // Customer Due
        cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT IFNULL(SUM(due_amount),0) FROM sales WHERE (del_status IS NULL OR del_status='Live')";
        var customerDue = Convert.ToDecimal(cmd.ExecuteScalar() ?? 0);
        rows.Add(new BalanceSheetRow { Sn = sn++, Title = "Customer Due (Accounts Receivable)", Amount = customerDue.ToString("N2"), IsSection = false });

        // Payment Method Balances
        cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT IFNULL(SUM(current_balance),0) FROM payment_methods WHERE (del_status IS NULL OR del_status='Live')";
        var pmBalance = Convert.ToDecimal(cmd.ExecuteScalar() ?? 0);
        rows.Add(new BalanceSheetRow { Sn = sn++, Title = "Payment Method Balances (Cash/Bank)", Amount = pmBalance.ToString("N2"), IsSection = false });

        decimal totalAssets = stockValue + customerDue + pmBalance;
        rows.Add(new BalanceSheetRow { Sn = 0, Title = "Total Assets:", Amount = totalAssets.ToString("N2"), IsSection = true });

        // LIABILITIES
        rows.Add(new BalanceSheetRow { Sn = 0, Title = "Liabilities", Amount = "", IsSection = true });

        cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT IFNULL(SUM(due_amount),0) FROM purchases WHERE (del_status IS NULL OR del_status='Live')";
        var supplierDue = Convert.ToDecimal(cmd.ExecuteScalar() ?? 0);
        rows.Add(new BalanceSheetRow { Sn = sn++, Title = "Supplier Due (Accounts Payable)", Amount = supplierDue.ToString("N2"), IsSection = false });

        decimal totalLiabilities = supplierDue;
        rows.Add(new BalanceSheetRow { Sn = 0, Title = "Total Liabilities:", Amount = totalLiabilities.ToString("N2"), IsSection = true });

        // SUMMARY
        rows.Add(new BalanceSheetRow { Sn = 0, Title = "Summary", Amount = "", IsSection = true });
        rows.Add(new BalanceSheetRow { Sn = 0, Title = "Total Assets:", Amount = totalAssets.ToString("N2"), IsSection = true });
        rows.Add(new BalanceSheetRow { Sn = 0, Title = "Total Liabilities:", Amount = totalLiabilities.ToString("N2"), IsSection = true });
        rows.Add(new BalanceSheetRow { Sn = 0, Title = "Net Worth:", Amount = (totalAssets - totalLiabilities).ToString("N2"), IsSection = true });

        itemsList.ItemsSource = rows;
    }

    private string FormatINR(string value)
    {
        if (string.IsNullOrWhiteSpace(value)) return "";
        value = value.Replace("INR", "").Replace(",", "").Trim();
        decimal.TryParse(value, System.Globalization.NumberStyles.Any, System.Globalization.CultureInfo.InvariantCulture, out var v);
        return v.ToString("N2");
    }

    private void BtnBack_Click(object sender, RoutedEventArgs e)
    {
        _dashboard?.ShowPage(new ReportsPage(_dashboard));
    }
}

public class BalanceSheetRow
{
    public int Sn { get; set; }
    public string SnText => Sn > 0 ? Sn.ToString() : "";
    public string Title { get; set; } = "";
    public string Amount { get; set; } = "";
    public bool IsSection { get; set; }
}
