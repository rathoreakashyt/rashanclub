using System.Linq;
using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views;

public partial class DepositWithdrawListPage : UserControl, ISyncRefreshable
{
    private MainDashboard? _dashboard;
    private readonly DatabaseService _db = new DatabaseService();
    private List<DepositWithdrawRow> _allRows = new();

    public DepositWithdrawListPage() { InitializeComponent(); Loaded += OnLoaded; }
    public DepositWithdrawListPage(MainDashboard dashboard) : this() { _dashboard = dashboard; }

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
        cmd.CommandText = @"SELECT dw.id, dw.reference_no, dw.date, dw.type, dw.amount, 
                            COALESCE(pm.name,'') AS payment_method, COALESCE(dw.note,'') AS note
                            FROM deposit_withdraws dw 
                            LEFT JOIN payment_methods pm ON pm.id = dw.payment_method_id
                            WHERE (dw.del_status IS NULL OR dw.del_status='Live')
                            ORDER BY dw.id DESC";
        using var r = cmd.ExecuteReader();
        int sn = 1;
        while (r.Read())
        {
            _allRows.Add(new DepositWithdrawRow
            {
                Id = r.GetInt64(0),
                Sn = sn++,
                ReferenceNo = r.IsDBNull(1) ? "" : r.GetString(1),
                Date = r.IsDBNull(2) ? "" : r.GetDateTime(2).ToString("yyyy-MM-dd"),
                Type = r.IsDBNull(3) ? "" : r.GetString(3),
                Amount = r.IsDBNull(4) ? "0.00" : r.GetDecimal(4).ToString("N2"),
                PaymentMethod = r.IsDBNull(5) ? "" : r.GetString(5),
                Note = r.IsDBNull(6) ? "" : r.GetString(6)
            });
        }
        ApplyFilter();
    }

    private void ApplyFilter()
    {
        string q = txtSearch.Text?.Trim().ToLower() ?? "";
        var filtered = string.IsNullOrEmpty(q) ? _allRows :
            _allRows.Where(r => r.ReferenceNo.ToLower().Contains(q) || r.Type.ToLower().Contains(q) ||
                                r.Amount.ToLower().Contains(q) || r.PaymentMethod.ToLower().Contains(q) ||
                                r.Note.ToLower().Contains(q)).ToList();
        itemsList.ItemsSource = filtered.ToList();
    }

    private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e)
    {
        txtPlaceholder.Visibility = string.IsNullOrEmpty(txtSearch.Text) ? Visibility.Visible : Visibility.Collapsed;
        ApplyFilter();
    }

    private void BtnAdd_Click(object sender, RoutedEventArgs e) => _dashboard?.ShowPage(new DepositWithdrawFormPage(_dashboard));

    private void BtnEdit_Click(object sender, RoutedEventArgs e)
    {
        if (sender is Button btn && btn.Tag is long id)
            _dashboard?.ShowPage(new DepositWithdrawFormPage(_dashboard, id));
    }

    private void BtnDelete_Click(object sender, RoutedEventArgs e)
    {
        if (sender is Button btn && btn.Tag is long id)
        {
            var result = MessageBox.Show("Are you sure you want to delete this record?", "Confirm Delete", MessageBoxButton.YesNo, MessageBoxImage.Warning);
            if (result != MessageBoxResult.Yes) return;

            using var conn = _db.GetConnection();
            conn.Open();
            var cmd = conn.CreateCommand();
            cmd.CommandText = "UPDATE deposit_withdraws SET del_status='Deleted', updated_at=@now WHERE id=@id";
            cmd.Parameters.AddWithValue("@now", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
            cmd.Parameters.AddWithValue("@id", id);
            cmd.ExecuteNonQuery();
            SyncService.EnqueueSync("deposit_withdraws", id, "delete");
            _dashboard?.TriggerSync();
            Load();
        }
    }
}

public class DepositWithdrawRow
{
    public long Id { get; set; }
    public int Sn { get; set; }
    public string ReferenceNo { get; set; } = "";
    public string Date { get; set; } = "";
    public string Type { get; set; } = "";
    public string Amount { get; set; } = "0.00";
    public string PaymentMethod { get; set; } = "";
    public string Note { get; set; } = "";
}
