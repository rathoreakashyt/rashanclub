using System.Collections.Generic;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public class ExpenseRow
    {
        public int Sn { get; set; }
        public long Id { get; set; }
        public string ReferenceNo { get; set; } = "";
        public string Date { get; set; } = "";
        public string Category { get; set; } = "";
        public string Amount { get; set; } = "";
        public string Account { get; set; } = "";
        public string Responsible { get; set; } = "";
        public string Note { get; set; } = "";
    }

    public partial class ExpenseListPage : UserControl, ISyncRefreshable
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new DatabaseService();
        private List<ExpenseRow> _allRows = new();

        public ExpenseListPage() { InitializeComponent(); }
        public ExpenseListPage(MainDashboard dashboard) : this() { _dashboard = dashboard; LoadData(); }

        // Cloud/Web se change aaya (sync pull) → page khud reload — bina refresh ke
        public void OnSyncPulled()
        {
            Dispatcher.BeginInvoke(new Action(() =>
            {
                if (IsLoaded) LoadData();
            }), System.Windows.Threading.DispatcherPriority.Background);
        }

        private void LoadData()
        {
            _allRows.Clear();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT e.Id, e.reference_no, e.date, e.amount, e.note,
                                           COALESCE(c.name, '') AS category,
                                           COALESCE(pm.name, '') AS account
                                    FROM expenses e
                                    LEFT JOIN ExpenseCategories c ON c.Id = e.category_id
                                    LEFT JOIN payment_methods pm ON pm.Id = e.payment_method_id
                                    WHERE (e.del_status IS NULL OR e.del_status='Live')
                                    ORDER BY e.Id DESC";
                using var r = cmd.ExecuteReader();
                int sn = 1;
                while (r.Read())
                {
                    _allRows.Add(new ExpenseRow
                    {
                        Sn = sn++,
                        Id = r["Id"] is long id ? id : 0,
                        ReferenceNo = r["reference_no"]?.ToString() ?? "",
                        Date = r["date"]?.ToString() ?? "",
                        Category = r["category"]?.ToString() ?? "",
                        Amount = $"₹ {(r["amount"] is double d ? d : 0):N2}",
                        Account = r["account"]?.ToString() ?? "",
                        Responsible = "",
                        Note = r["note"]?.ToString() ?? ""
                    });
                }
            }
            catch { }
            ApplyFilter();
        }

        private void ApplyFilter()
        {
            string q = txtSearch.Text.Trim().ToLowerInvariant();
            var filtered = q == "" ? _allRows : _allRows.FindAll(x =>
                x.ReferenceNo.ToLowerInvariant().Contains(q) || x.Category.ToLowerInvariant().Contains(q) || x.Note.ToLowerInvariant().Contains(q));
            itemsList.ItemsSource = filtered.ToList();
            emptyState.Visibility = filtered.Count == 0 ? Visibility.Visible : Visibility.Collapsed;
            itemsList.Visibility = filtered.Count == 0 ? Visibility.Collapsed : Visibility.Visible;
        }

        private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (txtSearch != null) ApplyFilter();
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowDashboard();
        }

        private void BtnAdd_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowPage(new ExpenseCreatePage(_dashboard!));
        }

        private void BtnEdit_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is long id)
                _dashboard?.ShowPage(new ExpenseCreatePage(_dashboard!, id));
        }

        private void BtnDelete_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is not long id) return;
            var result = MessageBox.Show("Delete this expense?", "Confirm", MessageBoxButton.YesNo, MessageBoxImage.Question);
            if (result != MessageBoxResult.Yes) return;

            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "UPDATE expenses SET del_status='Deleted', updated_at=datetime('now') WHERE Id=@id";
                cmd.Parameters.AddWithValue("@id", id);
                cmd.ExecuteNonQuery();
                Services.SyncService.EnqueueSync("expenses", id, "delete");
                _dashboard?.TriggerSync();
                LoadData();
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }
    }
}
