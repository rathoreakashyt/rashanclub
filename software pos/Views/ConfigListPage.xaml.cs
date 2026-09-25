using System.Collections.Generic;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public class ConfigRow
    {
        public int Sn { get; set; }
        public long Id { get; set; }
        public string Name { get; set; } = "";
        public string Detail { get; set; } = "";
    }

    public partial class ConfigListPage : UserControl, ISyncRefreshable
    {
        private readonly MainDashboard? _dashboard;
        private readonly ConfigEntity _entity;
        private readonly DatabaseService _db = new DatabaseService();
        private List<ConfigRow> _allRows = new();

        public ConfigListPage() { InitializeComponent(); }
        public ConfigListPage(MainDashboard dashboard, ConfigEntity entity) : this()
        {
            _dashboard = dashboard;
            _entity = entity;
            lblTitle.Text = "List " + ConfigEntityInfo.Plural(entity);
            LoadData();
        }

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
            string table = ConfigEntityInfo.Table(_entity);
            string nameCol = ConfigEntityInfo.NameColumn(_entity);
            string valueCol = _entity == ConfigEntity.Variation ? ", VariationValue" : "";
            try
            {
                using var conn = _db.GetConnection();
                // Deleted rows list me na dikhein (del_status column ho to filter)
                bool hasDelStatus = false;
                using (var chk = conn.CreateCommand())
                {
                    chk.CommandText = $"SELECT COUNT(*) FROM pragma_table_info(\"{table}\") WHERE lower(name)='del_status'";
                    hasDelStatus = Convert.ToInt64(chk.ExecuteScalar()) > 0;
                }
                using var cmd = conn.CreateCommand();
                string descCol = _entity == ConfigEntity.Variation ? "" : ", Description";
                cmd.CommandText = $"SELECT Id, {nameCol}{descCol}{valueCol} FROM {table} " +
                                  (hasDelStatus ? "WHERE (del_status IS NULL OR del_status != 'Deleted') " : "") +
                                  $"ORDER BY {nameCol}";
                using var r = cmd.ExecuteReader();
                int sn = 1;
                var seenNames = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
                while (r.Read())
                {
                    string name = r[nameCol]?.ToString() ?? "";
                    // Skip duplicate names (keep first occurrence — cloud-synced record)
                    if (!seenNames.Add(name)) continue;
                    string detail = _entity == ConfigEntity.Variation
                        ? (r["VariationValue"]?.ToString() ?? "").Replace("\"", "").Trim('[', ']')
                        : (r["Description"]?.ToString() ?? "");
                    _allRows.Add(new ConfigRow
                    {
                        Sn = sn++,
                        Id = r["Id"] is long id ? id : 0,
                        Name = name,
                        Detail = detail
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
                x.Name.ToLowerInvariant().Contains(q) || x.Detail.ToLowerInvariant().Contains(q));
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
            _dashboard?.ShowPage(new ItemConfigurationPage(_dashboard!));
        }

        private void BtnAdd_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowPage(new ConfigEditPage(_dashboard!, _entity));
        }

        private void BtnEdit_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is long id)
                _dashboard?.ShowPage(new ConfigEditPage(_dashboard!, _entity, id));
        }

        private void BtnDelete_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is not long id) return;
            var result = MessageBox.Show("Delete this record?", "Confirm", MessageBoxButton.YesNo, MessageBoxImage.Question);
            if (result != MessageBoxResult.Yes) return;

            try
            {
                using var conn = _db.GetConnection();
                string table = ConfigEntityInfo.Table(_entity);

                // Resolve server id if this row was pulled from (or pushed to) the
                // cloud — locally-created rows keep a negative Id but carry ServerId.
                long serverId = 0;
                using (var get = conn.CreateCommand())
                {
                    get.CommandText = $"SELECT ServerId FROM {table} WHERE Id=@id";
                    get.Parameters.AddWithValue("@id", id);
                    var val = get.ExecuteScalar();
                    if (val != null && long.TryParse(val.ToString(), out var sid)) serverId = sid;
                }

                // Soft delete + mark Local so the pending_sync queue picks it up.
                // Never hard-delete: the row must survive locally until the cloud
                // delete is acknowledged (pull would re-insert it otherwise).
                using var cmd = conn.CreateCommand();
                cmd.CommandText = $"UPDATE {table} SET del_status='Deleted', SyncStatus='Local' WHERE Id=@id";
                cmd.Parameters.AddWithValue("@id", id);
                cmd.ExecuteNonQuery();

                Services.SyncService.EnqueueSync(ConfigEntityInfo.ServerTable(_entity), serverId > 0 ? serverId : id, "delete");
                _dashboard?.TriggerSync();
                LoadData();
            }
            catch (System.Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }
    }
}
