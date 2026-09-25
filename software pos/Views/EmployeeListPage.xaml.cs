using System.Linq;
using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views;

public partial class EmployeeListPage : UserControl, ISyncRefreshable
{
    private MainDashboard? _dashboard;
    private readonly DatabaseService _db = new();
    private List<EmployeeRow> _allRows = new();

    public EmployeeListPage() { InitializeComponent(); Loaded += OnLoaded; }
    public EmployeeListPage(MainDashboard dashboard) : this() { _dashboard = dashboard; }

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
        cmd.CommandText = @"SELECT e.id, e.name, COALESCE(e.email,'') AS email, COALESCE(e.phone,'') AS phone,
                            COALESCE(r.name, e.role, '') AS role, COALESCE(o.name,'No Outlet') AS outlet
                            FROM employees e
                            LEFT JOIN roles r ON r.id = CAST(e.role AS INTEGER)
                            LEFT JOIN outlets o ON o.id = CAST(e.outlet_id AS INTEGER)
                            WHERE (e.del_status IS NULL OR e.del_status='Live')
                            ORDER BY e.id DESC";
        using var r = cmd.ExecuteReader();
        int sn = 1;
        while (r.Read())
        {
            _allRows.Add(new EmployeeRow
            {
                Id = r.GetInt64(0),
                Sn = sn++,
                Name = r.IsDBNull(1) ? "" : r.GetString(1),
                Email = r.IsDBNull(2) ? "" : r.GetString(2),
                Phone = r.IsDBNull(3) ? "" : r.GetString(3),
                Designation = r.IsDBNull(4) ? "" : r.GetString(4),
                Outlet = r.IsDBNull(5) ? "" : r.GetString(5)
            });
        }
        ApplyFilter();
    }

    private void ApplyFilter()
    {
        string q = txtSearch.Text?.Trim().ToLower() ?? "";
        var filtered = string.IsNullOrEmpty(q) ? _allRows :
            _allRows.Where(r => r.Name.ToLower().Contains(q) || r.Email.ToLower().Contains(q) ||
                                r.Phone.ToLower().Contains(q) || r.Designation.ToLower().Contains(q) ||
                                r.Outlet.ToLower().Contains(q)).ToList();
        itemsList.ItemsSource = filtered.ToList();
        emptyState.Visibility = filtered.Count == 0 ? Visibility.Visible : Visibility.Collapsed;
    }

    private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e)
    {
        txtPlaceholder.Visibility = string.IsNullOrEmpty(txtSearch.Text) ? Visibility.Visible : Visibility.Collapsed;
        ApplyFilter();
    }

    private void BtnAdd_Click(object sender, RoutedEventArgs e)
    {
        _dashboard?.ShowPage(new AddEmployeePage(_dashboard));
    }

    private void BtnEdit_Click(object sender, RoutedEventArgs e)
    {
        if (sender is Button btn && btn.Tag is long id)
        {
            _dashboard?.ShowPage(new AddEmployeePage(_dashboard, id));
        }
    }

    private void BtnDelete_Click(object sender, RoutedEventArgs e)
    {
        if (sender is Button btn && btn.Tag is long id)
        {
            var result = MessageBox.Show("Are you sure you want to delete this employee?", "Confirm Delete", MessageBoxButton.YesNo, MessageBoxImage.Warning);
            if (result != MessageBoxResult.Yes) return;

            using var conn = _db.GetConnection();
            conn.Open();

            // Only a row that came from the cloud (ServerId set) can be deleted on the
            // server by id. For a never-synced local row we just cancel its pending push.
            long serverId = 0;
            using (var q = conn.CreateCommand())
            {
                q.CommandText = "SELECT COALESCE(ServerId,0) FROM employees WHERE Id=@id";
                q.Parameters.AddWithValue("@id", id);
                var v = q.ExecuteScalar();
                if (v != null && long.TryParse(v.ToString(), out var s)) serverId = s;
            }

            var cmd = conn.CreateCommand();
            cmd.CommandText = "UPDATE employees SET del_status='Deleted', updated_at=@now WHERE id=@id";
            cmd.Parameters.AddWithValue("@now", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
            cmd.Parameters.AddWithValue("@id", id);
            cmd.ExecuteNonQuery();

            if (serverId > 0)
                SyncService.EnqueueSync("users", id, "delete");
            else
                SyncService.RemovePendingSync("users", id);

            _dashboard?.TriggerSync();
            Load();
        }
    }
}

public class EmployeeRow
{
    public long Id { get; set; }
    public int Sn { get; set; }
    public string Name { get; set; } = "";
    public string Email { get; set; } = "";
    public string Phone { get; set; } = "";
    public string Designation { get; set; } = "";
    public string Outlet { get; set; } = "";
}
