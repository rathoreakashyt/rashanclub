using System;
using System.Collections.Generic;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class SuppliersListPage : UserControl, ISyncRefreshable
    {
        private readonly DatabaseService _db = new DatabaseService();
        private readonly MainDashboard? _dashboard;
        private string _currentFilter = "All";
        private List<SupplierListItem> _allRows = new();

        public SuppliersListPage() { InitializeComponent(); }
        public SuppliersListPage(MainDashboard dashboard) : this() { _dashboard = dashboard; LoadSuppliers(); }

        // Cloud/Web se change aaya (sync pull) → page khud reload — bina refresh ke
        public void OnSyncPulled()
        {
            Dispatcher.BeginInvoke(new Action(() =>
            {
                if (IsLoaded) LoadSuppliers();
            }), System.Windows.Threading.DispatcherPriority.Background);
        }

        private void LoadSuppliers()
        {
            _allRows.Clear();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                string where = "WHERE m.MasterType='Party' AND m.PartyType IN ('Supplier','Both') AND (m.DelStatus IS NULL OR m.DelStatus != 'Deleted')";
                if (_currentFilter == "Active") where += " AND m.IsActive=1";
                else if (_currentFilter == "Inactive") where += " AND m.IsActive=0";

                cmd.CommandText = $@"SELECT m.Code, m.Name, COALESCE(m.ContactPerson,'') AS contact_person,
                                            COALESCE(NULLIF(m.Phone,''), NULLIF(m.Mobile,''), '') AS phone,
                                            COALESCE(m.OpeningBalance,0) AS opening_balance,
                                            COALESCE(m.DrCr,'Dr') AS balance_type,
                                            COALESCE((SELECT SUM(due_amount) FROM purchases p
                                                      WHERE p.supplier_id = m.ServerId AND (p.del_status IS NULL OR p.del_status='Live')),0) AS due,
                                            COALESCE((SELECT SUM(amount) FROM supplier_payments sp
                                                      WHERE sp.supplier_id = m.ServerId AND (sp.del_status IS NULL OR sp.del_status='Live')),0) AS paid,
                                            COALESCE((SELECT SUM(total_return_amount) FROM purchase_returns pr
                                                      WHERE pr.supplier_id = m.ServerId AND (pr.del_status IS NULL OR pr.del_status='Live')),0) AS ret
                                     FROM Master1 m {where} ORDER BY m.Name";

                using var r = cmd.ExecuteReader();
                int sn = 1;
                while (r.Read())
                {
                    double due = r["due"] is double dd ? dd : 0;
                    double paid = r["paid"] is double pd ? pd : 0;
                    double ret = r["ret"] is double rd ? rd : 0;
                    double opening = r["opening_balance"] is double ob ? ob : 0;
                    string obt = r["balance_type"]?.ToString() ?? "Dr";
                    // Supplier: positive balance = hum supplier ke paise dete hain (Credit),
                    // negative = supplier humara paisa deta hai (Debit).
                    // Dr opening = supplier humara paisa deta hai -> payable ghatta hai.
                    // Cr opening = hum supplier ko dete hain -> payable badhta hai.
                    double balance = obt == "Cr" || obt == "Credit"
                        ? (due - paid) + opening - ret
                        : (due - paid) - opening - ret;
                    string type = balance >= 0 ? "Credit" : "Debit";
                    string display = balance == 0
                        ? "₹ 0.00"
                        : $"₹ {Math.Abs(balance):N2} ({type})";

                    _allRows.Add(new SupplierListItem
                    {
                        Sn = sn++,
                        Code = r["Code"]?.ToString() ?? "",
                        Name = r["Name"]?.ToString() ?? "",
                        ContactPerson = r["contact_person"]?.ToString() ?? "",
                        Phone = r["phone"]?.ToString() ?? "",
                        CurrentBalance = display
                    });
                }

                ApplyFilter();

                using var cAll = conn.CreateCommand(); cAll.CommandText = "SELECT COUNT(*) FROM Master1 WHERE MasterType='Party' AND PartyType IN ('Supplier','Both') AND (DelStatus IS NULL OR DelStatus != 'Deleted')"; tabAll.Content = $"All ({(long)cAll.ExecuteScalar()})";
                using var cAct = conn.CreateCommand(); cAct.CommandText = "SELECT COUNT(*) FROM Master1 WHERE MasterType='Party' AND PartyType IN ('Supplier','Both') AND IsActive=1 AND (DelStatus IS NULL OR DelStatus != 'Deleted')"; tabActive.Content = $"Active ({(long)cAct.ExecuteScalar()})";
                using var cInact = conn.CreateCommand(); cInact.CommandText = "SELECT COUNT(*) FROM Master1 WHERE MasterType='Party' AND PartyType IN ('Supplier','Both') AND IsActive=0 AND (DelStatus IS NULL OR DelStatus != 'Deleted')"; tabInactive.Content = $"Inactive ({(long)cInact.ExecuteScalar()})";

                using var cTotal = conn.CreateCommand(); cTotal.CommandText = "SELECT COUNT(*) FROM Master1 WHERE MasterType='Party' AND PartyType IN ('Supplier','Both') AND (DelStatus IS NULL OR DelStatus != 'Deleted')";
                lblTotal.Text = $"{(long)cTotal.ExecuteScalar()} suppliers";
            }
            catch { }
        }

        private void ApplyFilter()
        {
            string q = txtSearch.Text.Trim().ToLowerInvariant();
            var filtered = q == "" ? _allRows : _allRows.FindAll(x =>
                x.Name.ToLowerInvariant().Contains(q) || x.Phone.ToLowerInvariant().Contains(q) || x.ContactPerson.ToLowerInvariant().Contains(q));
            itemsList.ItemsSource = filtered.ToList();
            emptyState.Visibility = filtered.Count == 0 ? Visibility.Visible : Visibility.Collapsed;
            itemsList.Visibility = filtered.Count == 0 ? Visibility.Collapsed : Visibility.Visible;
        }

        private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (txtSearch != null) ApplyFilter();
        }

        private void Tab_Click(object sender, RoutedEventArgs e)
        {
            if (tabAll.IsChecked == true) _currentFilter = "All";
            else if (tabActive.IsChecked == true) _currentFilter = "Active";
            else if (tabInactive.IsChecked == true) _currentFilter = "Inactive";
            LoadSuppliers();
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e) { _dashboard?.ShowDashboard(); }

        private void BtnAddSupplier_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowPage(new CreateSupplierPage(_dashboard!));
        }

        private void BtnEdit_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is string code && code != "")
            {
                _dashboard?.ShowPage(new CreateSupplierPage(_dashboard!, code));
            }
        }

        private void BtnDelete_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is not string code || code == "") return;
            var result = MessageBox.Show("Delete this supplier?", "Confirm", MessageBoxButton.YesNo, MessageBoxImage.Question);
            if (result != MessageBoxResult.Yes) return;

            try
            {
                using var conn = _db.GetConnection();
                using var tx = conn.BeginTransaction();

                long serverId = 0;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.Transaction = tx;
                    cmd.CommandText = "SELECT ServerId FROM Master1 WHERE Code=@code AND MasterType='Party'";
                    cmd.Parameters.AddWithValue("@code", code);
                    var v = cmd.ExecuteScalar();
                    if (v is long sid) serverId = sid;
                }

                using (var cmd = conn.CreateCommand())
                {
                    cmd.Transaction = tx;
                    cmd.CommandText = @"UPDATE Master1 SET IsActive=0, DelStatus='Deleted',
                                        SyncStatus='Synced', UpdatedAt=datetime('now')
                                        WHERE Code=@code AND MasterType='Party'";
                    cmd.Parameters.AddWithValue("@code", code);
                    cmd.ExecuteNonQuery();
                }

                if (serverId > 0)
                {
                    using var sc = conn.CreateCommand();
                    sc.Transaction = tx;
                    sc.CommandText = "UPDATE suppliers SET del_status='Deleted', updated_at=datetime('now') WHERE ServerId=@sid";
                    sc.Parameters.AddWithValue("@sid", serverId);
                    sc.ExecuteNonQuery();
                }

                tx.Commit();
                if (serverId > 0)
                    Services.SyncService.EnqueueSync("suppliers", serverId, "delete");
                _dashboard?.TriggerSync();
                LoadSuppliers();
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }
    }

    public class SupplierListItem
    {
        public int Sn { get; set; }
        public string Code { get; set; } = "";
        public string Name { get; set; } = "";
        public string ContactPerson { get; set; } = "";
        public string Phone { get; set; } = "";
        public string CurrentBalance { get; set; } = "";
    }
}
