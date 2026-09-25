using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views;

public partial class TransferListPage : UserControl, ISyncRefreshable
{
    private const int PageSize = 25;
    private int _currentPage = 1;
    private string _search = "";
    private List<TransferRow> _allRows = new();
    private MainDashboard? _dashboard;
    private readonly DatabaseService _db = new DatabaseService();

    public TransferListPage() { InitializeComponent(); Loaded += OnLoaded; }
    public TransferListPage(MainDashboard dashboard) : this() { _dashboard = dashboard; }

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
        cmd.CommandText = @"SELECT t.id, t.reference_no, t.date, t.from_outlet_id, t.to_outlet_id, t.status,
                                   COALESCE(o1.name,'') as from_name, COALESCE(o2.name,'') as to_name
                            FROM transfers t
                            LEFT JOIN Outlets o1 ON t.from_outlet_id = o1.id
                            LEFT JOIN Outlets o2 ON t.to_outlet_id = o2.id
                            WHERE t.del_status='Live'
                            ORDER BY t.id DESC";
        using var r = cmd.ExecuteReader();
        while (r.Read())
        {
            var status = r.IsDBNull(5) ? "Draft" : r.GetString(5);
            if (string.IsNullOrWhiteSpace(status)) status = "Draft";
            _allRows.Add(new TransferRow
            {
                Id = r.GetInt64(0),
                ReferenceNo = r.IsDBNull(1) ? "" : r.GetString(1),
                Date = r.IsDBNull(2) ? "" : ParseDateShort(r.GetString(2)),
                FromOutlet = r.IsDBNull(6) ? "Outlet " + r.GetInt64(3) : r.GetString(6),
                ToOutlet = r.IsDBNull(7) ? "Outlet " + r.GetInt64(4) : r.GetString(7),
                Status = status,
                Sn = 0
            });
        }

        ApplyFilter();
    }

    private string ParseDateShort(string raw)
    {
        if (DateTime.TryParse(raw, out var d)) return d.ToString("dd-MMM-yyyy");
        return raw;
    }

    private void ApplyFilter()
    {
        var filtered = _allRows;
        if (!string.IsNullOrWhiteSpace(_search))
        {
            var s = _search.ToLower();
            filtered = _allRows.Where(r =>
                r.ReferenceNo.ToLower().Contains(s) ||
                r.FromOutlet.ToLower().Contains(s) ||
                r.ToOutlet.ToLower().Contains(s) ||
                r.Status.ToLower().Contains(s) ||
                r.Date.ToLower().Contains(s)).ToList();
        }

        var total = filtered.Count;
        var totalPages = Math.Max(1, (int)Math.Ceiling((double)total / PageSize));
        if (_currentPage > totalPages) _currentPage = totalPages;

        var paged = filtered.Skip((_currentPage - 1) * PageSize).Take(PageSize).ToList();
        for (int i = 0; i < paged.Count; i++)
            paged[i].Sn = (_currentPage - 1) * PageSize + i + 1;

        itemsList.ItemsSource = paged;
        emptyState.Visibility = paged.Count == 0 ? Visibility.Visible : Visibility.Collapsed;

        txtInfo.Text = $"Showing {paged.Count} of {total} transfers";
        txtPage.Text = $"Page {_currentPage} / {totalPages}";
        btnPrev.IsEnabled = _currentPage > 1;
        btnNext.IsEnabled = _currentPage < totalPages;

        UpdateStats(filtered);
    }

    private void UpdateStats(List<TransferRow> rows)
    {
        statTotal.Text = rows.Count.ToString();
        statDraft.Text = rows.Count(r => r.Status == "Draft").ToString();
        statSent.Text = rows.Count(r => r.Status == "Sent").ToString();
        statReceived.Text = rows.Count(r => r.Status == "Received").ToString();
    }

    private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e)
    {
        _search = txtSearch.Text.Trim();
        _currentPage = 1;
        ApplyFilter();
    }

    private void BtnPrev_Click(object sender, RoutedEventArgs e) { _currentPage--; ApplyFilter(); }
    private void BtnNext_Click(object sender, RoutedEventArgs e) { _currentPage++; ApplyFilter(); }

    private void BtnAdd_Click(object sender, RoutedEventArgs e)
    {
        _dashboard?.ShowPage(new TransferCreatePage(_dashboard));
    }

    private void BtnEdit_Click(object sender, RoutedEventArgs e)
    {
        if (sender is Button btn && btn.Tag is long id)
            _dashboard?.ShowPage(new TransferCreatePage(_dashboard, id));
    }

    private void BtnDelete_Click(object sender, RoutedEventArgs e)
    {
        if (sender is Button btn && btn.Tag is long id)
        {
            var result = MessageBox.Show("Delete this transfer?", "Confirm", MessageBoxButton.YesNo, MessageBoxImage.Warning);
            if (result != MessageBoxResult.Yes) return;

            using var conn = _db.GetConnection();
            conn.Open();

            var cmd = conn.CreateCommand();
            cmd.CommandText = "UPDATE transfers SET del_status='Deleted' WHERE id=@id";
            cmd.Parameters.AddWithValue("@id", id);
            cmd.ExecuteNonQuery();

            var cmd2 = conn.CreateCommand();
            cmd2.CommandText = "UPDATE transfer_details SET del_status='Deleted' WHERE transfer_id=@id";
            cmd2.Parameters.AddWithValue("@id", id);
            cmd2.ExecuteNonQuery();

            SyncService.EnqueueSync("transfers", id, "delete");
            _dashboard?.TriggerSync();

            Load();
        }
    }
}

public class TransferRow
{
    public long Id { get; set; }
    public string ReferenceNo { get; set; } = "";
    public string Date { get; set; } = "";
    public string FromOutlet { get; set; } = "";
    public string ToOutlet { get; set; } = "";
    public string Status { get; set; } = "Draft";
    public int Sn { get; set; }
}
