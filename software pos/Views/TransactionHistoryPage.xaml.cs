using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;
using RashanKiDukan.Services;
using Microsoft.Data.Sqlite;

namespace RashanKiDukan.Views;

public partial class TransactionHistoryPage : UserControl
{
    private MainDashboard? _dashboard;
    private readonly ApiService _api = new();
    private readonly DatabaseService _db = new DatabaseService();

    public TransactionHistoryPage() { InitializeComponent(); Loaded += OnLoaded; }
    public TransactionHistoryPage(MainDashboard dashboard) : this() { _dashboard = dashboard; }

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
            var data = await _api.GetTransactionHistoryAsync(pm.Id, dateFrom, dateTo);
            if (data != null)
            {
                var rows = new List<TransactionHistoryRow>();
                int sn = 1;
                foreach (var item in data)
                    rows.Add(new TransactionHistoryRow
                    {
                        Sn = sn++,
                        Date = item.Date,
                        ReferenceNo = item.ReferenceNo,
                        Type = item.Type,
                        PaymentMethod = item.PaymentMethod,
                        Amount = FormatINR(ParseDecimal(item.Amount)),
                        CreatedAt = item.CreatedAt
                    });
                itemsList.ItemsSource = rows;
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
        var rows = new List<TransactionHistoryRow>();
        int sn = 1;
        using var conn = _db.GetConnection();
        conn.Open();

        string[] queries = {
            @"SELECT s.sale_date, s.invoice_no, 'Sale' AS type,
                 COALESCE((SELECT pm.name FROM sale_payments sp JOIN payment_methods pm ON pm.id=sp.payment_id
                           WHERE sp.sale_id=s.id AND (sp.del_status IS NULL OR sp.del_status='Live') LIMIT 1),'') AS pm,
                 IFNULL(s.grand_total,0), COALESCE(s.created_at,'')
              FROM sales s
              WHERE EXISTS (SELECT 1 FROM sale_payments sp WHERE sp.sale_id=s.id AND sp.payment_id=@pm AND (sp.del_status IS NULL OR sp.del_status='Live'))
              AND s.sale_date>=@from AND s.sale_date<=@to AND (s.del_status IS NULL OR s.del_status='Live')",
            @"SELECT p.date, p.reference_no, 'Purchase', COALESCE(s.name,''), IFNULL(p.grand_total,0), COALESCE(p.created_at,'')
              FROM purchases p LEFT JOIN suppliers s ON s.id=p.supplier_id
              WHERE p.payment_method_id=@pm AND p.date>=@from AND p.date<=@to AND (p.del_status IS NULL OR p.del_status='Live')",
            @"SELECT e.date, e.reference_no, 'Expense', COALESCE(pm.name,''), IFNULL(e.amount,0), COALESCE(e.created_at,'')
              FROM expenses e LEFT JOIN payment_methods pm ON pm.id=e.payment_method_id
              WHERE e.payment_method_id=@pm AND e.date>=@from AND e.date<=@to AND (e.del_status IS NULL OR e.del_status='Live')",
            @"SELECT i.date, i.reference_no, 'Income', COALESCE(pm.name,''), IFNULL(i.amount,0), COALESCE(i.created_at,'')
              FROM incomes i LEFT JOIN payment_methods pm ON pm.id=i.payment_method_id
              WHERE i.payment_method_id=@pm AND i.date>=@from AND i.date<=@to AND (i.del_status IS NULL OR i.del_status='Live')",
            @"SELECT dw.date, dw.reference_no, dw.type, COALESCE(pm.name,''), IFNULL(dw.amount,0), COALESCE(dw.created_at,'')
              FROM deposit_withdraws dw LEFT JOIN payment_methods pm ON pm.id=dw.payment_method_id
              WHERE dw.payment_method_id=@pm AND dw.date>=@from AND dw.date<=@to AND (dw.del_status IS NULL OR dw.del_status='Live')"
        };

        foreach (var sql in queries)
        {
            using var cmd = conn.CreateCommand();
            cmd.CommandText = sql;
            cmd.Parameters.Add(new SqliteParameter("@pm", pmId));
            cmd.Parameters.Add(new SqliteParameter("@from", dateFrom));
            cmd.Parameters.Add(new SqliteParameter("@to", dateTo));
            using var r = cmd.ExecuteReader();
            while (r.Read())
                rows.Add(new TransactionHistoryRow
                {
                    Sn = sn++,
                    Date = r.IsDBNull(0) ? "" : r.GetDateTime(0).ToString("yyyy-MM-dd"),
                    ReferenceNo = r.IsDBNull(1) ? "" : r.GetString(1),
                    Type = r.IsDBNull(2) ? "" : r.GetString(2),
                    PaymentMethod = r.IsDBNull(3) ? "" : r.GetString(3),
                    Amount = FormatINR(r.IsDBNull(4) ? 0m : r.GetDecimal(4)),
                    CreatedAt = r.IsDBNull(5) ? "" : r.GetString(5)
                });
        }
        rows = rows.OrderByDescending(r => r.Date).ToList();
        for (int i = 0; i < rows.Count; i++) rows[i].Sn = i + 1;
        itemsList.ItemsSource = rows;
    }

    private decimal ParseDecimal(string s)
    {
        s = s.Replace("INR", "").Replace(",", "").Trim();
        decimal.TryParse(s, System.Globalization.NumberStyles.Any, System.Globalization.CultureInfo.InvariantCulture, out var v);
        return v;
    }

    private string FormatINR(decimal amount)
    {
        string prefix = amount < 0 ? "INR-" : "INR";
        return prefix + Math.Abs(amount).ToString("N2");
    }

    private void BtnBack_Click(object sender, RoutedEventArgs e) => _dashboard?.ShowPage(new ReportsPage(_dashboard));
}

public class TransactionHistoryRow
{
    public int Sn { get; set; }
    public string Date { get; set; } = "";
    public string ReferenceNo { get; set; } = "";
    public string Type { get; set; } = "";
    public string PaymentMethod { get; set; } = "";
    public string Amount { get; set; } = "INR0.00";
    public string CreatedAt { get; set; } = "";
}
