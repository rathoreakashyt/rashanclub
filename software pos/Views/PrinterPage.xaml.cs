using System;
using System.Collections.Generic;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public class PrinterRow
    {
        public int Sn { get; set; }
        public long Id { get; set; }
        public string Title { get; set; } = "";
        public string PrintingChoice { get; set; } = "";
        public string TypeName { get; set; } = "";
    }

    public partial class PrinterPage : UserControl, ISyncRefreshable
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();
        private List<PrinterRow> _all = new();
        private long _editId;

        // (code, display)
        private static readonly (string Code, string Label)[] InvoicePrintOpts =
        { ("web_browser", "Web Browser"), ("live_server_print", "Direct Print") };
        private static readonly (string Code, string Label)[] TypeOpts =
        { ("windows", "USB Printer"), ("network", "Network") };
        private static readonly string[] OnOff = { "ON", "OFF" };

        public PrinterPage() { InitializeComponent(); InitCombos(); }
        public PrinterPage(MainDashboard d) : this() { _dashboard = d; LoadData(); }

        private void InitCombos()
        {
            cmbInvoicePrint.Items.Clear();
            foreach (var o in InvoicePrintOpts) cmbInvoicePrint.Items.Add(o.Label);
            cmbInvoicePrint.SelectedIndex = 0;

            cmbPrinterType.Items.Clear();
            foreach (var o in TypeOpts) cmbPrinterType.Items.Add(o.Label);
            cmbPrinterType.SelectedIndex = 0;

            cmbFiscal.Items.Clear();
            foreach (var o in OnOff) cmbFiscal.Items.Add(o);
            cmbFiscal.SelectedIndex = 0;

            cmbCashDrawer.Items.Clear();
            foreach (var o in OnOff) cmbCashDrawer.Items.Add(o);
            cmbCashDrawer.SelectedIndex = 0;
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
            _all.Clear();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, title, invoice_print, type FROM printers WHERE del_status IS NULL OR del_status='Live' ORDER BY title ASC";
                using var r = cmd.ExecuteReader();
                int sn = 1;
                while (r.Read())
                {
                    string ip = r["invoice_print"]?.ToString() ?? "";
                    string t  = r["type"]?.ToString() ?? "";
                    string ipLabel = ip == "live_server_print" ? "Direct Print" : ip == "web_browser" ? "Web Browser" : ip;
                    string tLabel  = t == "windows" ? "USB Printer" : t == "network" ? "Network" : t;
                    _all.Add(new PrinterRow { Sn = sn++, Id = Convert.ToInt64(r["id"]),
                        Title = r["title"]?.ToString() ?? "", PrintingChoice = ipLabel, TypeName = tLabel });
                }
            }
            catch { }
            ApplyFilter();
        }

        private void ApplyFilter()
        {
            string q = txtSearch.Text.Trim().ToLowerInvariant();
            var f = q == "" ? _all : _all.FindAll(x => x.Title.ToLowerInvariant().Contains(q));
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
            formCol.Width = show ? new GridLength(420) : new GridLength(0);
            divCol.Width  = show ? new GridLength(16)  : new GridLength(0);
        }

        // Toggle live-server and network panels
        private void CmbInvoicePrint_SelectionChanged(object s, SelectionChangedEventArgs e)
        {
            bool isDirect = cmbInvoicePrint.SelectedIndex == 1; // "Direct Print"
            panelLiveServer.Visibility = isDirect ? Visibility.Visible : Visibility.Collapsed;
            if (!isDirect) panelNetwork.Visibility = Visibility.Collapsed;
            else CmbPrinterType_SelectionChanged(s, null!);
        }

        private void CmbPrinterType_SelectionChanged(object s, SelectionChangedEventArgs e)
        {
            bool isNetwork = cmbPrinterType.SelectedIndex == 1; // "Network"
            panelNetwork.Visibility = isNetwork ? Visibility.Visible : Visibility.Collapsed;
        }

        private void ClearForm()
        {
            txtTitle.Text = ""; txtCharsPerLine.Text = ""; txtPath.Text = "";
            txtIPV4.Text = ""; txtIPAddress.Text = ""; txtPort.Text = "";
            cmbInvoicePrint.SelectedIndex = 0; cmbPrinterType.SelectedIndex = 0;
            cmbFiscal.SelectedIndex = 0; cmbCashDrawer.SelectedIndex = 0;
            panelLiveServer.Visibility = Visibility.Collapsed;
            panelNetwork.Visibility = Visibility.Collapsed;
        }

        private void BtnAdd_Click(object s, RoutedEventArgs e)
        {
            _editId = 0; ClearForm();
            lblFormTitle.Text = "Add Printer"; ShowForm(true);
        }

        private void BtnEdit_Click(object s, RoutedEventArgs e)
        {
            if ((s as Button)?.Tag is not long id) return;
            _editId = id;
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT * FROM printers WHERE id=@id";
                cmd.Parameters.AddWithValue("@id", id);
                using var r = cmd.ExecuteReader();
                if (!r.Read()) return;

                txtTitle.Text = r["title"]?.ToString() ?? "";

                string ip = r["invoice_print"]?.ToString() ?? "web_browser";
                cmbInvoicePrint.SelectedIndex = ip == "live_server_print" ? 1 : 0;

                string t = r["type"]?.ToString() ?? "windows";
                cmbPrinterType.SelectedIndex = t == "network" ? 1 : 0;

                txtCharsPerLine.Text = r["characters_per_line"]?.ToString() ?? "";
                txtPath.Text = r["path"]?.ToString() ?? "";
                txtIPV4.Text = r["print_server_url_invoice"]?.ToString() ?? "";
                txtIPAddress.Text = r["printer_ip_address"]?.ToString() ?? "";
                txtPort.Text = r["printer_port"]?.ToString() ?? "";

                string fiscal = r["fiscal_printer_status"]?.ToString() ?? "OFF";
                cmbFiscal.SelectedIndex = fiscal == "ON" ? 0 : 1;

                string drawer = r["open_cash_drawer_when_printing_invoice"]?.ToString() ?? "OFF";
                cmbCashDrawer.SelectedIndex = drawer == "ON" ? 0 : 1;

                // Trigger panel visibility
                CmbInvoicePrint_SelectionChanged(this, null!);
            }
            catch { }
            lblFormTitle.Text = "Edit Printer"; ShowForm(true);
        }

        private void BtnFormBack_Click(object s, RoutedEventArgs e) => ShowForm(false);

        private void BtnDelete_Click(object s, RoutedEventArgs e)
        {
            if ((s as Button)?.Tag is not long id) return;
            if (MessageBox.Show("Delete this printer?", "Confirm", MessageBoxButton.YesNo, MessageBoxImage.Question) != MessageBoxResult.Yes) return;
            try
            {
                using var conn = _db.GetConnection(); using var cmd = conn.CreateCommand();
                cmd.CommandText = "UPDATE printers SET del_status='Deleted' WHERE id=@id";
                cmd.Parameters.AddWithValue("@id", id); cmd.ExecuteNonQuery();
                Services.SyncService.EnqueueSync("printers", id, "delete");
                _ = _dashboard?.TriggerSync(); LoadData();
            }
            catch (Exception ex) { MessageBox.Show(ex.Message, "Error"); }
        }

        private void BtnSave_Click(object s, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(txtTitle.Text)) { MessageBox.Show("Title required.", "Validation"); return; }

            string invoicePrint = InvoicePrintOpts[cmbInvoicePrint.SelectedIndex >= 0 ? cmbInvoicePrint.SelectedIndex : 0].Code;
            string type         = TypeOpts[cmbPrinterType.SelectedIndex >= 0 ? cmbPrinterType.SelectedIndex : 0].Code;
            string fiscal       = cmbFiscal.SelectedIndex == 0 ? "ON" : "OFF";
            string cashDrawer   = cmbCashDrawer.SelectedIndex == 0 ? "ON" : "OFF";

            try
            {
                using var conn = _db.GetConnection(); using var cmd = conn.CreateCommand();
                if (_editId > 0)
                {
                    cmd.CommandText = @"UPDATE printers SET title=@t, invoice_print=@ip, type=@tp,
                        characters_per_line=@cpl, path=@path, print_server_url_invoice=@ipv4,
                        printer_ip_address=@ipa, printer_port=@port,
                        fiscal_printer_status=@fiscal, open_cash_drawer_when_printing_invoice=@cd,
                        updated_at=datetime('now') WHERE id=@id";
                    cmd.Parameters.AddWithValue("@id", _editId);
                }
                else
                {
                    cmd.CommandText = @"INSERT INTO printers
                        (title, invoice_print, type, characters_per_line, path, print_server_url_invoice,
                         printer_ip_address, printer_port, fiscal_printer_status,
                         open_cash_drawer_when_printing_invoice, del_status, created_at, updated_at)
                        VALUES (@t,@ip,@tp,@cpl,@path,@ipv4,@ipa,@port,@fiscal,@cd,'Live',datetime('now'),datetime('now'))";
                }
                cmd.Parameters.AddWithValue("@t",    txtTitle.Text.Trim());
                cmd.Parameters.AddWithValue("@ip",   invoicePrint);
                cmd.Parameters.AddWithValue("@tp",   type);
                cmd.Parameters.AddWithValue("@cpl",  txtCharsPerLine.Text.Trim());
                cmd.Parameters.AddWithValue("@path", txtPath.Text.Trim());
                cmd.Parameters.AddWithValue("@ipv4", txtIPV4.Text.Trim());
                cmd.Parameters.AddWithValue("@ipa",  txtIPAddress.Text.Trim());
                cmd.Parameters.AddWithValue("@port", txtPort.Text.Trim());
                cmd.Parameters.AddWithValue("@fiscal", fiscal);
                cmd.Parameters.AddWithValue("@cd",   cashDrawer);
                cmd.ExecuteNonQuery();

                long savedId = _editId;
                if (savedId == 0) { using var lid = conn.CreateCommand(); lid.CommandText = "SELECT last_insert_rowid()"; savedId = Convert.ToInt64(lid.ExecuteScalar()); }
                Services.SyncService.EnqueueSync("printers", savedId, _editId > 0 ? "update" : "insert");
                Services.SyncService.MarkLocalPending("printers", savedId);
                _ = _dashboard?.TriggerSync();
                ShowForm(false); LoadData();
                MessageBox.Show("Printer saved!", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
            }
            catch (Exception ex) { MessageBox.Show(ex.Message, "Error"); }
        }
    }
}
