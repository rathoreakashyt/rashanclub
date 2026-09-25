using System.Linq;
using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views;

public partial class RoleListPage : UserControl, ISyncRefreshable
{
    private MainDashboard? _dashboard;
    private readonly DatabaseService _db = new();
    private List<RoleRow> _allRows = new();

    public RoleListPage() { InitializeComponent(); Loaded += OnLoaded; }
    public RoleListPage(MainDashboard dashboard) : this() { _dashboard = dashboard; }

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
        cmd.CommandText = @"SELECT r.id, r.name,
                            (SELECT COUNT(*) FROM role_has_permissions WHERE role_id = r.id) AS perm_count,
                            (SELECT COUNT(*) FROM model_has_roles WHERE role_id = r.id) AS user_count
                            FROM roles r
                            ORDER BY r.id DESC";
        using var r = cmd.ExecuteReader();
        int sn = 1;
        while (r.Read())
        {
            _allRows.Add(new RoleRow
            {
                Id = r.GetInt64(0),
                Sn = sn++,
                Name = r.IsDBNull(1) ? "" : r.GetString(1),
                Permissions = r.GetInt32(2).ToString(),
                Users = r.GetInt32(3).ToString()
            });
        }
        ApplyFilter();
    }

    private void ApplyFilter()
    {
        string q = txtSearch.Text?.Trim().ToLower() ?? "";
        var filtered = string.IsNullOrEmpty(q) ? _allRows :
            _allRows.Where(r => r.Name.ToLower().Contains(q)).ToList();
        itemsList.ItemsSource = filtered.ToList();
    }

    private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e)
    {
        txtPlaceholder.Visibility = string.IsNullOrEmpty(txtSearch.Text) ? Visibility.Visible : Visibility.Collapsed;
        ApplyFilter();
    }

    private void BtnAdd_Click(object sender, RoutedEventArgs e)
    {
        _dashboard?.ShowPage(new AddRolePage(_dashboard));
    }

    private void BtnEdit_Click(object sender, RoutedEventArgs e)
    {
        if (sender is Button btn && btn.Tag is long id)
            _dashboard?.ShowPage(new AddRolePage(_dashboard, id));
    }

    private void BtnDelete_Click(object sender, RoutedEventArgs e)
    {
        if (sender is Button btn && btn.Tag is long id)
        {
            var result = MessageBox.Show("Are you sure you want to delete this role?", "Confirm Delete", MessageBoxButton.YesNo, MessageBoxImage.Warning);
            if (result != MessageBoxResult.Yes) return;

            using var conn = _db.GetConnection();
            conn.Open();
            using var tx = conn.BeginTransaction();
            var delPerms = conn.CreateCommand();
            delPerms.Transaction = tx;
            delPerms.CommandText = "DELETE FROM role_has_permissions WHERE role_id=@id";
            delPerms.Parameters.AddWithValue("@id", id);
            delPerms.ExecuteNonQuery();
            var delRole = conn.CreateCommand();
            delRole.Transaction = tx;
            delRole.CommandText = "DELETE FROM roles WHERE id=@id";
            delRole.Parameters.AddWithValue("@id", id);
            delRole.ExecuteNonQuery();
            tx.Commit();
            Load();
        }
    }
}

public class RoleRow
{
    public long Id { get; set; }
    public int Sn { get; set; }
    public string Name { get; set; } = "";
    public string Permissions { get; set; } = "0";
    public string Users { get; set; } = "0";
}
