using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views;

public partial class PaymentMethodListPage : UserControl, ISyncRefreshable
{
    private List<PaymentMethodRow> _allRows = new();
    private MainDashboard? _dashboard;
    private readonly DatabaseService _db = new DatabaseService();

    public PaymentMethodListPage() { InitializeComponent(); Loaded += OnLoaded; }
    public PaymentMethodListPage(MainDashboard dashboard) : this() { _dashboard = dashboard; }

    private void OnLoaded(object sender, RoutedEventArgs e) => Load();

    // Cloud/Web se change aaya (sync pull) → page khud reload — bina refresh ke
    public void OnSyncPulled()
    {
        Dispatcher.BeginInvoke(new Action(() =>
        {
            if (IsLoaded) Load();
        }), System.Windows.Threading.DispatcherPriority.Background);
    }

    private void Load()
    {
        _allRows.Clear();
        using var conn = _db.GetConnection();
        conn.Open();
        var cmd = conn.CreateCommand();
        cmd.CommandText = @"SELECT id, name, account_type, status, current_balance, is_deletable
                            FROM payment_methods
                            WHERE del_status='Live'
                            ORDER BY sort_id, id";
        using var r = cmd.ExecuteReader();
        int sn = 0;
        while (r.Read())
        {
            sn++;
            _allRows.Add(new PaymentMethodRow
            {
                Id = r.GetInt64(0),
                Sn = sn,
                Name = r.IsDBNull(1) ? "" : r.GetString(1),
                AccountType = r.IsDBNull(2) ? "" : r.GetString(2),
                Status = r.IsDBNull(3) ? "Enable" : r.GetString(3),
                Balance = "INR" + (r.IsDBNull(4) ? "0.00" : r.GetDouble(4).ToString("N2")),
                IsDeletable = r.IsDBNull(5) ? "1" : r.GetString(5)
            });
        }
        ApplyFilter();
    }

    private void ApplyFilter()
    {
        var filtered = _allRows;
        if (!string.IsNullOrWhiteSpace(txtSearch.Text))
        {
            var s = txtSearch.Text.Trim().ToLower();
            filtered = _allRows.Where(r =>
                r.Name.ToLower().Contains(s) ||
                r.AccountType.ToLower().Contains(s) ||
                r.Status.ToLower().Contains(s)).ToList();
        }
        itemsList.ItemsSource = filtered;
        emptyState.Visibility = filtered.Count == 0 ? Visibility.Visible : Visibility.Collapsed;
    }

    private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e) => ApplyFilter();

    private void BtnAdd_Click(object sender, RoutedEventArgs e)
    {
        _dashboard?.ShowPage(new PaymentMethodFormPage(_dashboard));
    }

    private void BtnEdit_Click(object sender, RoutedEventArgs e)
    {
        if (sender is Button btn && btn.Tag is long id)
            _dashboard?.ShowPage(new PaymentMethodFormPage(_dashboard, id));
    }

    private void BtnDelete_Click(object sender, RoutedEventArgs e)
    {
        if (sender is Button btn && btn.Tag is long id)
        {
            var row = _allRows.FirstOrDefault(r => r.Id == id);
            if (row != null && row.IsDeletable == "0")
            {
                MessageBox.Show("This payment method cannot be deleted.", "Info", MessageBoxButton.OK, MessageBoxImage.Information);
                return;
            }

            var result = MessageBox.Show("Delete this payment method?", "Confirm", MessageBoxButton.YesNo, MessageBoxImage.Warning);
            if (result != MessageBoxResult.Yes) return;

            using var conn = _db.GetConnection();
            conn.Open();
            var cmd = conn.CreateCommand();
            cmd.CommandText = "UPDATE payment_methods SET del_status='Deleted' WHERE id=@id";
            cmd.Parameters.AddWithValue("@id", id);
            cmd.ExecuteNonQuery();

            SyncService.EnqueueSync("payment_methods", id, "delete");
            _dashboard?.TriggerSync();
            Load();
        }
    }
}

public class PaymentMethodRow
{
    public long Id { get; set; }
    public int Sn { get; set; }
    public string Name { get; set; } = "";
    public string AccountType { get; set; } = "";
    public string Status { get; set; } = "Enable";
    public string Balance { get; set; } = "INR0.00";
    public string IsDeletable { get; set; } = "1";
}
