using System;
using System.Collections.Generic;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public class MultipleCurrencyRow { public int Sn{get;set;} public long Id{get;set;} public string Currency{get;set;}=""; public string ConversionRate{get;set;}=""; public string Symbol{get;set;}=""; }

    public partial class MultipleCurrencyPage : UserControl, ISyncRefreshable
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();
        private List<MultipleCurrencyRow> _all = new();
        private long _editId;

        public MultipleCurrencyPage() { InitializeComponent(); }
        public MultipleCurrencyPage(MainDashboard d) : this() { _dashboard = d; LoadData(); }

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
                cmd.CommandText = "SELECT id, name, exchange_rate, symbol FROM multiple_currencies WHERE del_status IS NULL OR del_status='Live' ORDER BY name ASC";
                using var r = cmd.ExecuteReader();
                int sn = 1;
                while (r.Read())
                    _all.Add(new MultipleCurrencyRow {
                        Sn = sn++,
                        Id = Convert.ToInt64(r["id"]),
                        Currency = r["name"]?.ToString() ?? "",
                        ConversionRate = r["exchange_rate"]?.ToString() ?? "",
                        Symbol = r["symbol"]?.ToString() ?? ""
                    });
            }
            catch { }
            ApplyFilter();
        }

        private void ApplyFilter()
        {
            string q = txtSearch.Text.Trim().ToLowerInvariant();
            var f = q == "" ? _all : _all.FindAll(x => x.Currency.ToLowerInvariant().Contains(q));
            itemsList.ItemsSource = f.ToList();
            emptyState.Visibility = f.Count == 0 ? Visibility.Visible : Visibility.Collapsed;
            itemsList.Visibility  = f.Count == 0 ? Visibility.Collapsed : Visibility.Visible;
        }

        private void TxtSearch_TextChanged(object s, TextChangedEventArgs e) { if (txtSearch != null) ApplyFilter(); }
        private void BtnBack_Click(object s, RoutedEventArgs e) => _dashboard?.ShowDashboard();

        private void ShowForm(bool show)
        {
            formPanel.Visibility   = show ? Visibility.Visible : Visibility.Collapsed;
            formDivider.Visibility = show ? Visibility.Visible : Visibility.Collapsed;
            formCol.Width = show ? new GridLength(380) : new GridLength(0);
            divCol.Width  = show ? new GridLength(16)  : new GridLength(0);
        }

        private void BtnAdd_Click(object s, RoutedEventArgs e)
        {
            _editId = 0;
            txtCurrency.Text = ""; txtConversionRate.Text = ""; txtSymbol.Text = "";
            lblFormTitle.Text = "Add Multiple Currency"; ShowForm(true);
        }

        private void BtnEdit_Click(object s, RoutedEventArgs e)
        {
            if ((s as Button)?.Tag is not long id) return;
            _editId = id;
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT name, exchange_rate, symbol FROM multiple_currencies WHERE id=@id";
                cmd.Parameters.AddWithValue("@id", id);
                using var r = cmd.ExecuteReader();
                if (!r.Read()) return;
                txtCurrency.Text = r["name"]?.ToString() ?? "";
                txtConversionRate.Text = r["exchange_rate"]?.ToString() ?? "";
                txtSymbol.Text = r["symbol"]?.ToString() ?? "";
            }
            catch { }
            lblFormTitle.Text = "Edit Multiple Currency"; ShowForm(true);
        }

        private void BtnFormBack_Click(object s, RoutedEventArgs e) => ShowForm(false);

        private void BtnDelete_Click(object s, RoutedEventArgs e)
        {
            if ((s as Button)?.Tag is not long id) return;
            if (MessageBox.Show("Delete this currency?", "Confirm", MessageBoxButton.YesNo, MessageBoxImage.Question) != MessageBoxResult.Yes) return;
            try
            {
                using var conn = _db.GetConnection(); using var cmd = conn.CreateCommand();
                cmd.CommandText = "UPDATE multiple_currencies SET del_status='Deleted' WHERE id=@id";
                cmd.Parameters.AddWithValue("@id", id); cmd.ExecuteNonQuery();
                Services.SyncService.EnqueueSync("multiple_currencies", id, "delete");
                _ = _dashboard?.TriggerSync(); LoadData();
            }
            catch (Exception ex) { MessageBox.Show(ex.Message, "Error"); }
        }

        private void BtnSave_Click(object s, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(txtCurrency.Text)) { MessageBox.Show("Currency required.", "Validation"); return; }
            if (!double.TryParse(txtConversionRate.Text.Trim(), out double rate)) { MessageBox.Show("Conversion rate must be a number.", "Validation"); return; }
            try
            {
                using var conn = _db.GetConnection(); using var cmd = conn.CreateCommand();
                if (_editId > 0)
                {
                    cmd.CommandText = "UPDATE multiple_currencies SET name=@n, exchange_rate=@r, symbol=@sym, updated_at=datetime('now') WHERE id=@id";
                    cmd.Parameters.AddWithValue("@id", _editId);
                }
                else
                    cmd.CommandText = "INSERT INTO multiple_currencies (name, exchange_rate, symbol, del_status, created_at, updated_at) VALUES (@n,@r,@sym,'Live',datetime('now'),datetime('now'))";

                cmd.Parameters.AddWithValue("@n", txtCurrency.Text.Trim());
                cmd.Parameters.AddWithValue("@r", rate);
                cmd.Parameters.AddWithValue("@sym", txtSymbol.Text.Trim());
                cmd.ExecuteNonQuery();

                long savedId = _editId;
                if (savedId == 0) { using var lid = conn.CreateCommand(); lid.CommandText = "SELECT last_insert_rowid()"; savedId = Convert.ToInt64(lid.ExecuteScalar()); }
                Services.SyncService.EnqueueSync("multiple_currencies", savedId, _editId > 0 ? "update" : "insert");
                Services.SyncService.MarkLocalPending("multiple_currencies", savedId);
                _ = _dashboard?.TriggerSync();
                ShowForm(false); LoadData();
                MessageBox.Show("Currency saved!", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
            }
            catch (Exception ex) { MessageBox.Show(ex.Message, "Error"); }
        }
    }
}
