using System;
using System.Collections.Generic;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public class DenominationRow { public int Sn { get; set; } public long Id { get; set; } public string Amount { get; set; } = ""; public string Description { get; set; } = ""; }

    public partial class DenominationPage : UserControl, ISyncRefreshable
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();
        private List<DenominationRow> _all = new();
        private long _editId;

        public DenominationPage() { InitializeComponent(); }
        public DenominationPage(MainDashboard d) : this() { _dashboard = d; LoadData(); }

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
            _all.Clear();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, value, name, description FROM denominations WHERE del_status IS NULL OR del_status='Live' ORDER BY value ASC";
                using var r = cmd.ExecuteReader();
                int sn = 1;
                while (r.Read())
                    _all.Add(new DenominationRow { Sn = sn++, Id = Convert.ToInt64(r["id"]),
                        Amount = r["value"]?.ToString() ?? r["name"]?.ToString() ?? "",
                        Description = r["description"]?.ToString() ?? "" });
            }
            catch { }
            ApplyFilter();
        }

        private void ApplyFilter()
        {
            string q = txtSearch.Text.Trim().ToLowerInvariant();
            var f = q == "" ? _all : _all.FindAll(x => x.Amount.ToLowerInvariant().Contains(q) || x.Description.ToLowerInvariant().Contains(q));
            itemsList.ItemsSource = f.ToList();
            emptyState.Visibility = f.Count == 0 ? Visibility.Visible : Visibility.Collapsed;
            itemsList.Visibility  = f.Count == 0 ? Visibility.Collapsed : Visibility.Visible;
        }

        private void TxtSearch_TextChanged(object s, TextChangedEventArgs e) { if (txtSearch != null) ApplyFilter(); }
        private void BtnBack_Click(object s, RoutedEventArgs e) => _dashboard?.ShowDashboard();

        private void ShowForm(bool show)
        {
            formPanel.Visibility  = show ? Visibility.Visible : Visibility.Collapsed;
            formDivider.Visibility = show ? Visibility.Visible : Visibility.Collapsed;
            formColumn.Width = show ? new GridLength(380) : new GridLength(0);
        }

        private void BtnAdd_Click(object s, RoutedEventArgs e)
        {
            _editId = 0; txtAmount.Text = ""; txtDescription.Text = "";
            lblFormTitle.Text = "Add Denomination"; ShowForm(true);
        }

        private void BtnEdit_Click(object s, RoutedEventArgs e)
        {
            if ((s as Button)?.Tag is not long id) return;
            _editId = id;
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT value, name, description FROM denominations WHERE id=@id";
                cmd.Parameters.AddWithValue("@id", id);
                using var r = cmd.ExecuteReader();
                if (!r.Read()) return;
                txtAmount.Text = r["value"]?.ToString() ?? r["name"]?.ToString() ?? "";
                txtDescription.Text = r["description"]?.ToString() ?? "";
            }
            catch { }
            lblFormTitle.Text = "Edit Denomination"; ShowForm(true);
        }

        private void BtnFormBack_Click(object s, RoutedEventArgs e) => ShowForm(false);

        private void BtnDelete_Click(object s, RoutedEventArgs e)
        {
            if ((s as Button)?.Tag is not long id) return;
            if (MessageBox.Show("Delete this denomination?", "Confirm", MessageBoxButton.YesNo, MessageBoxImage.Question) != MessageBoxResult.Yes) return;
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "UPDATE denominations SET del_status='Deleted' WHERE id=@id";
                cmd.Parameters.AddWithValue("@id", id);
                cmd.ExecuteNonQuery();
                Services.SyncService.EnqueueSync("denominations", id, "delete");
                _ = _dashboard?.TriggerSync();
                LoadData();
            }
            catch (Exception ex) { MessageBox.Show(ex.Message, "Error"); }
        }

        private void BtnSave_Click(object s, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(txtAmount.Text))
            { MessageBox.Show("Amount required.", "Validation"); return; }
            if (!double.TryParse(txtAmount.Text.Trim(), out double amount))
            { MessageBox.Show("Amount must be a number.", "Validation"); return; }

            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                if (_editId > 0)
                {
                    cmd.CommandText = "UPDATE denominations SET value=@v, name=@v, description=@d, updated_at=datetime('now') WHERE id=@id";
                    cmd.Parameters.AddWithValue("@id", _editId);
                }
                else
                {
                    cmd.CommandText = "INSERT INTO denominations (value, name, description, del_status, created_at, updated_at) VALUES (@v, @v, @d, 'Live', datetime('now'), datetime('now'))";
                }
                cmd.Parameters.AddWithValue("@v", amount);
                cmd.Parameters.AddWithValue("@d", txtDescription.Text.Trim());
                cmd.ExecuteNonQuery();

                long savedId = _editId;
                if (savedId == 0) { using var lid = conn.CreateCommand(); lid.CommandText = "SELECT last_insert_rowid()"; savedId = Convert.ToInt64(lid.ExecuteScalar()); }
                Services.SyncService.EnqueueSync("denominations", savedId, _editId > 0 ? "update" : "insert");
                Services.SyncService.MarkLocalPending("denominations", savedId);
                _ = _dashboard?.TriggerSync();

                ShowForm(false); LoadData();
                MessageBox.Show("Denomination saved!", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
            }
            catch (Exception ex) { MessageBox.Show(ex.Message, "Error"); }
        }
    }
}
