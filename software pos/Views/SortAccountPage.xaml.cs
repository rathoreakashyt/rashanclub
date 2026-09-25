using System.Linq;
using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views;

public partial class SortAccountPage : UserControl
{
    private List<SortAccountRow> _rows = new();
    private MainDashboard? _dashboard;
    private readonly DatabaseService _db = new DatabaseService();

    public SortAccountPage() { InitializeComponent(); Loaded += OnLoaded; }
    public SortAccountPage(MainDashboard dashboard) : this() { _dashboard = dashboard; }

    private void OnLoaded(object sender, RoutedEventArgs e) => Load();

    private void Load()
    {
        _rows.Clear();
        using var conn = _db.GetConnection();
        conn.Open();
        var cmd = conn.CreateCommand();
        cmd.CommandText = @"SELECT id, name, account_type, sort_id FROM payment_methods 
                            WHERE del_status='Live'
                            ORDER BY sort_id ASC, id ASC";
        using var r = cmd.ExecuteReader();
        while (r.Read())
        {
            _rows.Add(new SortAccountRow
            {
                Id = r.GetInt64(0),
                Name = r.IsDBNull(1) ? "" : r.GetString(1),
                AccountType = r.IsDBNull(2) ? "" : r.GetString(2),
                SortId = r.IsDBNull(3) ? 0 : r.GetInt32(3)
            });
        }
        ApplyOrder();
    }

    private void ApplyOrder()
    {
        for (int i = 0; i < _rows.Count; i++)
            _rows[i].OrderNum = i + 1;
        itemsList.ItemsSource = null;
        itemsList.ItemsSource = _rows.ToList();
    }

    private void BtnUp_Click(object sender, RoutedEventArgs e)
    {
        if (sender is Button btn && btn.Tag is long id)
        {
            var idx = _rows.FindIndex(r => r.Id == id);
            if (idx > 0)
            {
                var temp = _rows[idx];
                _rows[idx] = _rows[idx - 1];
                _rows[idx - 1] = temp;
                ApplyOrder();
            }
        }
    }

    private void BtnDown_Click(object sender, RoutedEventArgs e)
    {
        if (sender is Button btn && btn.Tag is long id)
        {
            var idx = _rows.FindIndex(r => r.Id == id);
            if (idx >= 0 && idx < _rows.Count - 1)
            {
                var temp = _rows[idx];
                _rows[idx] = _rows[idx + 1];
                _rows[idx + 1] = temp;
                ApplyOrder();
            }
        }
    }

    private void BtnSave_Click(object sender, RoutedEventArgs e)
    {
        using var conn = _db.GetConnection();
        conn.Open();
        var now = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");

        for (int i = 0; i < _rows.Count; i++)
        {
            var cmd = conn.CreateCommand();
            cmd.CommandText = "UPDATE payment_methods SET sort_id=@sort, updated_at=@now WHERE id=@id";
            cmd.Parameters.AddWithValue("@sort", i + 1);
            cmd.Parameters.AddWithValue("@now", now);
            cmd.Parameters.AddWithValue("@id", _rows[i].Id);
            cmd.ExecuteNonQuery();

            SyncService.EnqueueSync("payment_methods", _rows[i].Id, "update");
        }

        _dashboard?.TriggerSync();
        MessageBox.Show("Order saved successfully!", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
    }

    private void BtnBack_Click(object sender, RoutedEventArgs e)
    {
        _dashboard?.ShowPage(new PaymentMethodListPage(_dashboard));
    }
}

public class SortAccountRow
{
    public long Id { get; set; }
    public string Name { get; set; } = "";
    public string AccountType { get; set; } = "";
    public int SortId { get; set; }
    public int OrderNum { get; set; }
}
