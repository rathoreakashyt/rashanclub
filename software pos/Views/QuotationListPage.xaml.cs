using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views;

public partial class QuotationListPage : UserControl, ISyncRefreshable
{
    private List<QuotationRow> _allRows = new();
    private MainDashboard? _dashboard;
    private readonly DatabaseService _db = new DatabaseService();

    public QuotationListPage() { InitializeComponent(); Loaded += OnLoaded; }
    public QuotationListPage(MainDashboard dashboard) : this() { _dashboard = dashboard; }

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
        cmd.CommandText = @"SELECT q.id, q.reference_no, q.date, q.grand_total, q.customer_id,
                                   COALESCE(c.name,'') as cust_name, COALESCE(c.phone,'') as cust_phone
                            FROM quotations q
                            LEFT JOIN customers c ON q.customer_id = c.id
                            WHERE q.del_status='Live'
                            ORDER BY q.id DESC";
        using var r = cmd.ExecuteReader();
        int sn = 0;
        while (r.Read())
        {
            sn++;
            var name = r.IsDBNull(5) ? "" : r.GetString(5);
            var phone = r.IsDBNull(6) ? "" : r.GetString(6);
            var customer = name;
            if (!string.IsNullOrWhiteSpace(phone))
                customer = $"{name} (+91-{phone})";

            _allRows.Add(new QuotationRow
            {
                Id = r.GetInt64(0),
                Sn = sn,
                ReferenceNo = r.IsDBNull(1) ? "" : r.GetString(1),
                Date = r.IsDBNull(2) ? "" : ParseDate(r.GetString(2)),
                Customer = customer,
                GrandTotal = "INR" + (r.IsDBNull(3) ? "0.00" : r.GetDouble(3).ToString("N2"))
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
                r.Customer.ToLower().Contains(s) ||
                r.GrandTotal.ToLower().Contains(s)).ToList();
        }
        itemsList.ItemsSource = filtered;
        emptyState.Visibility = filtered.Count == 0 ? Visibility.Visible : Visibility.Collapsed;
    }

    private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e) => ApplyFilter();

    private void BtnAdd_Click(object sender, RoutedEventArgs e)
    {
        _dashboard?.ShowPage(new QuotationCreatePage(_dashboard));
    }

    private void BtnView_Click(object sender, RoutedEventArgs e)
    {
        if (sender is Button btn && btn.Tag is long id)
            _dashboard?.ShowPage(new QuotationCreatePage(_dashboard, id, true));
    }

    private void BtnEdit_Click(object sender, RoutedEventArgs e)
    {
        if (sender is Button btn && btn.Tag is long id)
            _dashboard?.ShowPage(new QuotationCreatePage(_dashboard, id));
    }

    private void BtnDelete_Click(object sender, RoutedEventArgs e)
    {
        if (sender is Button btn && btn.Tag is long id)
        {
            var result = MessageBox.Show("Delete this quotation?", "Confirm", MessageBoxButton.YesNo, MessageBoxImage.Warning);
            if (result != MessageBoxResult.Yes) return;

            using var conn = _db.GetConnection();
            conn.Open();

            var cmd = conn.CreateCommand();
            cmd.CommandText = "UPDATE quotations SET del_status='Deleted' WHERE id=@id";
            cmd.Parameters.AddWithValue("@id", id);
            cmd.ExecuteNonQuery();

            var cmd2 = conn.CreateCommand();
            cmd2.CommandText = "UPDATE quotation_details SET del_status='Deleted' WHERE quotation_id=@id";
            cmd2.Parameters.AddWithValue("@id", id);
            cmd2.ExecuteNonQuery();

            SyncService.EnqueueSync("quotations", id, "delete");
            _dashboard?.TriggerSync();
            Load();
        }
    }
}

public class QuotationRow
{
    public long Id { get; set; }
    public int Sn { get; set; }
    public string ReferenceNo { get; set; } = "";
    public string Date { get; set; } = "";
    public string Customer { get; set; } = "";
    public string GrandTotal { get; set; } = "INR0.00";
}
