using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views;

public partial class DamageListPage : UserControl, ISyncRefreshable
{
    private List<DamageRow> _allRows = new();
    private MainDashboard? _dashboard;
    private readonly DatabaseService _db = new DatabaseService();

    public DamageListPage() { InitializeComponent(); Loaded += OnLoaded; }
    public DamageListPage(MainDashboard dashboard) : this() { _dashboard = dashboard; }

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
        cmd.CommandText = @"SELECT d.id, d.reference_no, d.date, d.total_loss, d.note, d.employee_id,
                                   COALESCE(e.name,'') as emp_name, COALESCE(e.phone,'') as emp_phone
                            FROM damages d
                            LEFT JOIN employees e ON d.employee_id = e.id
                            WHERE d.del_status='Live'
                            ORDER BY d.id DESC";
        using var r = cmd.ExecuteReader();
        int sn = 0;
        while (r.Read())
        {
            sn++;
            var empName = r.IsDBNull(6) ? "" : r.GetString(6);
            var empPhone = r.IsDBNull(7) ? "" : r.GetString(7);
            var person = empName;
            if (!string.IsNullOrWhiteSpace(empPhone))
                person = $"{empName} (+91-{empPhone})";

            _allRows.Add(new DamageRow
            {
                Id = r.GetInt64(0),
                Sn = sn,
                ReferenceNo = r.IsDBNull(1) ? "" : r.GetString(1),
                Date = r.IsDBNull(2) ? "" : ParseDate(r.GetString(2)),
                ResponsiblePerson = person,
                TotalLoss = "INR" + (r.IsDBNull(3) ? "0.00" : r.GetDouble(3).ToString("N2"))
            });
        }
        ApplyFilter();
    }

    private string ParseDate(string raw)
    {
        if (DateTime.TryParse(raw, out var d)) return d.ToString("dd/MM/yyyy");
        return raw;
    }

    private void ApplyFilter()
    {
        var filtered = _allRows;
        if (!string.IsNullOrWhiteSpace(txtSearch.Text))
        {
            var s = txtSearch.Text.Trim().ToLower();
            filtered = _allRows.Where(r =>
                r.ReferenceNo.ToLower().Contains(s) ||
                r.Date.ToLower().Contains(s) ||
                r.ResponsiblePerson.ToLower().Contains(s) ||
                r.TotalLoss.ToLower().Contains(s)).ToList();
        }
        itemsList.ItemsSource = filtered;
        emptyState.Visibility = filtered.Count == 0 ? Visibility.Visible : Visibility.Collapsed;
    }

    private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e) => ApplyFilter();

    private void BtnAdd_Click(object sender, RoutedEventArgs e)
    {
        _dashboard?.ShowPage(new DamageCreatePage(_dashboard));
    }

    private void BtnView_Click(object sender, RoutedEventArgs e)
    {
        if (sender is Button btn && btn.Tag is long id)
            _dashboard?.ShowPage(new DamageCreatePage(_dashboard, id, true));
    }

    private void BtnEdit_Click(object sender, RoutedEventArgs e)
    {
        if (sender is Button btn && btn.Tag is long id)
            _dashboard?.ShowPage(new DamageCreatePage(_dashboard, id));
    }

    private void BtnDelete_Click(object sender, RoutedEventArgs e)
    {
        if (sender is Button btn && btn.Tag is long id)
        {
            var result = MessageBox.Show("Delete this damage record?", "Confirm", MessageBoxButton.YesNo, MessageBoxImage.Warning);
            if (result != MessageBoxResult.Yes) return;

            using var conn = _db.GetConnection();
            conn.Open();

            var cmd = conn.CreateCommand();
            cmd.CommandText = "UPDATE damages SET del_status='Deleted' WHERE id=@id";
            cmd.Parameters.AddWithValue("@id", id);
            cmd.ExecuteNonQuery();

            var cmd2 = conn.CreateCommand();
            cmd2.CommandText = "UPDATE damage_details SET del_status='Deleted' WHERE damage_id=@id";
            cmd2.Parameters.AddWithValue("@id", id);
            cmd2.ExecuteNonQuery();

            SyncService.EnqueueSync("damages", id, "delete");
            _dashboard?.TriggerSync();
            Load();
        }
    }
}

public class DamageRow
{
    public long Id { get; set; }
    public int Sn { get; set; }
    public string ReferenceNo { get; set; } = "";
    public string Date { get; set; } = "";
    public string ResponsiblePerson { get; set; } = "";
    public string TotalLoss { get; set; } = "INR0.00";
}
