using System;
using System.Collections.Generic;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public class CounterRow
    {
        public int Sn { get; set; }
        public long Id { get; set; }
        public string Name { get; set; } = "";
        public string OutletName { get; set; } = "";
        public string PrinterName { get; set; } = "";
    }

    public partial class CounterPage : UserControl, ISyncRefreshable
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();
        private List<CounterRow> _all = new();
        private long _editId;

        // Dropdown data: (Id, DisplayName)
        private List<(long Id, string Name)> _outlets = new();
        private List<(long Id, string Name)> _printers = new();

        public CounterPage() { InitializeComponent(); }
        public CounterPage(MainDashboard d) : this()
        {
            _dashboard = d;
            LoadDropdowns();
            LoadData();
        }

        // ── Dropdown loaders ──────────────────────────────────────────────
        private void LoadDropdowns()
        {
            _outlets.Clear();
            _printers.Clear();
            try
            {
                using var conn = _db.GetConnection();

                // Outlets
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT id, COALESCE(outlet_name, name, '') as n FROM outlets WHERE del_status IS NULL OR del_status='Live' ORDER BY n ASC";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                        _outlets.Add((Convert.ToInt64(r["id"]), r["n"]?.ToString() ?? ""));
                }

                // Printers
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT id, name FROM printers WHERE del_status IS NULL OR del_status='Live' ORDER BY name ASC";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                        _printers.Add((Convert.ToInt64(r["id"]), r["name"]?.ToString() ?? ""));
                }
            }
            catch { }

            // Populate outlet combo
            cmbOutlet.Items.Clear();
            cmbOutlet.Items.Add("-- Select Outlet --");
            foreach (var o in _outlets) cmbOutlet.Items.Add(o.Name);
            cmbOutlet.SelectedIndex = 0;

            // Populate printer combo
            cmbPrinter.Items.Clear();
            cmbPrinter.Items.Add("-- Select Printer --");
            foreach (var p in _printers) cmbPrinter.Items.Add(p.Name);
            cmbPrinter.SelectedIndex = 0;
        }

        // ── List loading ──────────────────────────────────────────────────
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
                    cmd.CommandText = @"
                    SELECT c.id, c.name,
                           COALESCE(o.outlet_name, o.name, '') AS outlet_name,
                           COALESCE(p.name, '') AS printer_name
                    FROM counters c
                    LEFT JOIN outlets o ON o.id = c.outlet_id
                    LEFT JOIN printers p ON p.id = c.printer_id
                    WHERE c.del_status IS NULL OR c.del_status='Live'
                    ORDER BY c.name ASC";
                using var r = cmd.ExecuteReader();
                int sn = 1;
                while (r.Read())
                    _all.Add(new CounterRow
                    {
                        Sn = sn++,
                        Id = Convert.ToInt64(r["id"]),
                        Name = r["name"]?.ToString() ?? "",
                        OutletName = r["outlet_name"]?.ToString() ?? "",
                        PrinterName = r["printer_name"]?.ToString() ?? ""
                    });
            }
            catch { }
            ApplyFilter();
        }

        private void ApplyFilter()
        {
            string q = txtSearch.Text.Trim().ToLowerInvariant();
            var f = q == "" ? _all : _all.FindAll(x =>
                x.Name.ToLowerInvariant().Contains(q) ||
                x.OutletName.ToLowerInvariant().Contains(q));
            itemsList.ItemsSource = f.ToList();
            emptyState.Visibility = f.Count == 0 ? Visibility.Visible : Visibility.Collapsed;
            itemsList.Visibility  = f.Count == 0 ? Visibility.Collapsed : Visibility.Visible;
        }

        private void TxtSearch_TextChanged(object s, TextChangedEventArgs e) { if (txtSearch != null) ApplyFilter(); }
        private void BtnBack_Click(object s, RoutedEventArgs e) => _dashboard?.ShowDashboard();

        // ── Form show/hide ────────────────────────────────────────────────
        private void ShowForm(bool show)
        {
            formPanel.Visibility   = show ? Visibility.Visible : Visibility.Collapsed;
            formDivider.Visibility = show ? Visibility.Visible : Visibility.Collapsed;
            formCol.Width = show ? new GridLength(400) : new GridLength(0);
            divCol.Width  = show ? new GridLength(16)  : new GridLength(0);
        }

        private void SetComboById(ComboBox cmb, List<(long Id, string Name)> list, object? rawId)
        {
            if (rawId == null || rawId == DBNull.Value || !long.TryParse(rawId.ToString(), out long id))
            { cmb.SelectedIndex = 0; return; }
            int idx = list.FindIndex(x => x.Id == id);
            cmb.SelectedIndex = idx >= 0 ? idx + 1 : 0; // +1 for placeholder
        }

        private long GetSelectedId(ComboBox cmb, List<(long Id, string Name)> list)
        {
            int sel = cmb.SelectedIndex - 1;
            return sel >= 0 && sel < list.Count ? list[sel].Id : 0;
        }

        // ── Navigation ────────────────────────────────────────────────────
        private void BtnAdd_Click(object s, RoutedEventArgs e)
        {
            _editId = 0;
            txtName.Text = ""; txtDescription.Text = "";
            cmbOutlet.SelectedIndex = 0; cmbPrinter.SelectedIndex = 0;
            lblFormTitle.Text = "Add Counter"; ShowForm(true);
        }

        private void BtnEdit_Click(object s, RoutedEventArgs e)
        {
            if ((s as Button)?.Tag is not long id) return;
            _editId = id;
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT name, outlet_id, printer_id, description FROM counters WHERE id=@id";
                cmd.Parameters.AddWithValue("@id", id);
                using var r = cmd.ExecuteReader();
                if (!r.Read()) return;
                txtName.Text = r["name"]?.ToString() ?? "";
                txtDescription.Text = r["description"]?.ToString() ?? "";
                SetComboById(cmbOutlet,  _outlets,  r["outlet_id"]);
                SetComboById(cmbPrinter, _printers, r["printer_id"]);
            }
            catch { }
            lblFormTitle.Text = "Edit Counter"; ShowForm(true);
        }

        private void BtnFormBack_Click(object s, RoutedEventArgs e) => ShowForm(false);

        private void BtnDelete_Click(object s, RoutedEventArgs e)
        {
            if ((s as Button)?.Tag is not long id) return;
            if (MessageBox.Show("Delete this counter?", "Confirm", MessageBoxButton.YesNo, MessageBoxImage.Question) != MessageBoxResult.Yes) return;
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "UPDATE counters SET del_status='Deleted' WHERE id=@id";
                cmd.Parameters.AddWithValue("@id", id); cmd.ExecuteNonQuery();
                Services.SyncService.EnqueueSync("counters", id, "delete");
                _ = _dashboard?.TriggerSync(); LoadData();
            }
            catch (Exception ex) { MessageBox.Show(ex.Message, "Error"); }
        }

        // ── Save ──────────────────────────────────────────────────────────
        private void BtnSave_Click(object s, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(txtName.Text)) { MessageBox.Show("Name required.", "Validation"); return; }
            long outletId = GetSelectedId(cmbOutlet, _outlets);
            if (outletId == 0) { MessageBox.Show("Outlet required.", "Validation"); return; }
            long printerId = GetSelectedId(cmbPrinter, _printers); // optional

            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                if (_editId > 0)
                {
                    cmd.CommandText = @"UPDATE counters SET name=@n, outlet_id=@oid, printer_id=@pid,
                        description=@d, updated_at=datetime('now') WHERE id=@id";
                    cmd.Parameters.AddWithValue("@id", _editId);
                }
                else
                {
                    cmd.CommandText = @"INSERT INTO counters (name, outlet_id, printer_id, description,
                        del_status, created_at, updated_at)
                        VALUES (@n,@oid,@pid,@d,'Live',datetime('now'),datetime('now'))";
                }
                cmd.Parameters.AddWithValue("@n",   txtName.Text.Trim());
                cmd.Parameters.AddWithValue("@oid", outletId);
                cmd.Parameters.AddWithValue("@pid", printerId > 0 ? (object)printerId : DBNull.Value);
                cmd.Parameters.AddWithValue("@d",   txtDescription.Text.Trim());
                cmd.ExecuteNonQuery();

                long savedId = _editId;
                if (savedId == 0)
                {
                    using var lid = conn.CreateCommand();
                    lid.CommandText = "SELECT last_insert_rowid()";
                    savedId = Convert.ToInt64(lid.ExecuteScalar());
                }
                Services.SyncService.EnqueueSync("counters", savedId, _editId > 0 ? "update" : "insert");
                Services.SyncService.MarkLocalPending("counters", savedId);
                _ = _dashboard?.TriggerSync();
                ShowForm(false); LoadData();
                MessageBox.Show("Counter saved!", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
            }
            catch (Exception ex) { MessageBox.Show(ex.Message, "Error"); }
        }
    }
}
