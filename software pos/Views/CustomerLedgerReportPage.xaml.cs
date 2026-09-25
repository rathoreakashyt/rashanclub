using System;
using System.Collections.Generic;
using System.Data;
using System.IO;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Documents;
using System.Windows.Media;
using Microsoft.Win32;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class CustomerLedgerReportPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();
        private DataTable? _currentTable;

        public CustomerLedgerReportPage(MainDashboard? dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () => dataGrid.ItemsSource as DataView, "Customer Ledger Report");
            _dashboard = dashboard;
            LoadCustomers();
            LoadOutlets();
            LoadTypes();
            LoadData();
        }

        private void LoadCustomers()
        {
            var list = new List<ReportComboItem> { new(0, "Select Customer") };
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, name, phone FROM customers WHERE (del_status IS NULL OR del_status='Live') ORDER BY name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    long id = r.IsDBNull(0) ? 0 : r.GetInt64(0);
                    string name = r.IsDBNull(1) ? "" : r.GetString(1);
                    string phone = r.IsDBNull(2) ? "" : r.GetString(2);
                    list.Add(new ReportComboItem(id, string.IsNullOrEmpty(phone) ? name : $"{name} ({phone})"));
                }
            }
            catch { }
            cmbCustomer.ItemsSource = list;
            cmbCustomer.SelectedIndex = 0;
        }

        private void LoadOutlets()
        {
            var list = new List<ReportComboItem> { new(0, "All Outlets") };
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, outlet_name FROM outlets WHERE (del_status IS NULL OR del_status='Live') ORDER BY id";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add(new ReportComboItem(r.IsDBNull(0) ? 0 : r.GetInt64(0), r.IsDBNull(1) ? "" : r.GetString(1)));
            }
            catch { }
            cmbOutlet.ItemsSource = list;
            cmbOutlet.SelectedIndex = 0;
        }

        private void LoadTypes()
        {
            cmbType.Items.Clear();
            cmbType.Items.Add("All");
            cmbType.Items.Add("Debit");
            cmbType.Items.Add("Credit");
            cmbType.SelectedIndex = 0;
        }

        private void LoadData()
        {
            emptyState.Visibility   = Visibility.Collapsed;
            dataGrid.Visibility     = Visibility.Collapsed;
            totalsBar.Visibility    = Visibility.Collapsed;
            openingBar.Visibility   = Visibility.Collapsed;
            closingBar.Visibility   = Visibility.Collapsed;
            rowCountBar.Visibility  = Visibility.Collapsed;

            long customerId = (cmbCustomer.SelectedItem as ReportComboItem)?.Id ?? 0;
            if (customerId <= 0) { emptyState.Visibility = Visibility.Visible; return; }

            string from = dpDateFrom.SelectedDate?.ToString("yyyy-MM-dd") ?? "";
            string to   = dpDateTo.SelectedDate?.ToString("yyyy-MM-dd") ?? "";
            long outletId = (cmbOutlet.SelectedItem as ReportComboItem)?.Id ?? 0;
            string type   = cmbType.SelectedItem?.ToString() ?? "All";

            try
            {
                using var conn = _db.GetConnection();

                // Get customer opening balance
                double openingBalance = 0;
                string openingType = "Debit";
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT IFNULL(opening_balance, 0), IFNULL(opening_balance_type, 'Debit') FROM customers WHERE id=@id";
                    cmd.Parameters.AddWithValue("@id", customerId);
                    using var r = cmd.ExecuteReader();
                    if (r.Read()) { openingBalance = r.IsDBNull(0) ? 0 : r.GetDouble(0); openingType = r.IsDBNull(1) ? "Debit" : r.GetString(1); }
                }

                // Calculate opening balance from transactions before date_from
                double obFromTransactions = 0;
                if (!string.IsNullOrEmpty(from))
                {
                    double salesBefore = 0, receivesBefore = 0, returnsBefore = 0;
                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.CommandText = "SELECT IFNULL(SUM(grand_total),0) FROM sales WHERE customer_id=@cid AND (del_status IS NULL OR del_status='Live') AND sale_date < @from"
                            + (outletId > 0 ? " AND outlet_id=" + outletId : "");
                        cmd.Parameters.AddWithValue("@cid", customerId);
                        cmd.Parameters.AddWithValue("@from", from);
                        var o = cmd.ExecuteScalar(); if (o != null) salesBefore = Convert.ToDouble(o);
                    }
                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.CommandText = "SELECT IFNULL(SUM(amount),0) FROM customer_receives WHERE customer_id=@cid AND (del_status IS NULL OR del_status='Live') AND date < @from"
                            + (outletId > 0 ? " AND outlet_id=" + outletId : "");
                        cmd.Parameters.AddWithValue("@cid", customerId);
                        cmd.Parameters.AddWithValue("@from", from);
                        var o = cmd.ExecuteScalar(); if (o != null) receivesBefore = Convert.ToDouble(o);
                    }
                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.CommandText = "SELECT IFNULL(SUM(total_return_amount),0) FROM sale_returns WHERE customer_id=@cid AND (del_status IS NULL OR del_status='Live') AND date < @from"
                            + (outletId > 0 ? " AND outlet_id=" + outletId : "");
                        cmd.Parameters.AddWithValue("@cid", customerId);
                        cmd.Parameters.AddWithValue("@from", from);
                        var o = cmd.ExecuteScalar(); if (o != null) returnsBefore = Convert.ToDouble(o);
                    }
                    bool isDrOpening = openingType == "Dr" || openingType == "Debit";
                    double baseOb = isDrOpening ? openingBalance : -openingBalance;
                    // Customer ledger: Sales=Credit(+), Receives=Debit(-), Returns=Debit(-)
                    obFromTransactions = baseOb - salesBefore + receivesBefore + returnsBefore;
                }
                else
                {
                    obFromTransactions = (openingType == "Dr" || openingType == "Debit") ? openingBalance : -openingBalance;
                }

                // Collect transactions
                var transactions = new List<(string date, string type, string no, double debit, double credit, string outlet)>();

                // Sales (Credit - customer owes us)
                using (var cmd = conn.CreateCommand())
                {
                    var sql = "SELECT s.sale_date, s.created_at, IFNULL(s.sale_no,'-'), IFNULL(s.grand_total,0), IFNULL(o.outlet_name,'-')"
                        + " FROM sales s LEFT JOIN outlets o ON o.id=s.outlet_id"
                        + " WHERE s.customer_id=@cid AND (s.del_status IS NULL OR s.del_status='Live')";
                    if (!string.IsNullOrEmpty(from)) sql += " AND s.sale_date >= @from";
                    if (!string.IsNullOrEmpty(to))   sql += " AND s.sale_date <= @to";
                    if (outletId > 0) sql += " AND s.outlet_id=" + outletId;
                    sql += " ORDER BY s.sale_date ASC, s.id ASC";
                    cmd.CommandText = sql;
                    cmd.Parameters.AddWithValue("@cid", customerId);
                    if (!string.IsNullOrEmpty(from)) cmd.Parameters.AddWithValue("@from", from);
                    if (!string.IsNullOrEmpty(to))   cmd.Parameters.AddWithValue("@to", to);
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                        transactions.Add((r.GetString(0), "Sale", r.GetString(1), 0, r.GetDouble(2), r.GetString(3)));
                }

                // Customer Receives (Debit - customer pays us)
                using (var cmd = conn.CreateCommand())
                {
                    var sql = "SELECT cr.date, cr.created_at, IFNULL(cr.reference_no,'-'), IFNULL(cr.amount,0), IFNULL(o.outlet_name,'-')"
                        + " FROM customer_receives cr LEFT JOIN outlets o ON o.id=cr.outlet_id"
                        + " WHERE cr.customer_id=@cid AND (cr.del_status IS NULL OR cr.del_status='Live')";
                    if (!string.IsNullOrEmpty(from)) sql += " AND cr.date >= @from";
                    if (!string.IsNullOrEmpty(to))   sql += " AND cr.date <= @to";
                    if (outletId > 0) sql += " AND cr.outlet_id=" + outletId;
                    sql += " ORDER BY cr.date ASC, cr.id ASC";
                    cmd.CommandText = sql;
                    cmd.Parameters.AddWithValue("@cid", customerId);
                    if (!string.IsNullOrEmpty(from)) cmd.Parameters.AddWithValue("@from", from);
                    if (!string.IsNullOrEmpty(to))   cmd.Parameters.AddWithValue("@to", to);
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                        transactions.Add((r.GetString(0), "Payment", r.GetString(1), r.GetDouble(2), 0, r.GetString(3)));
                }

                // Sale Returns (Debit - we return to customer)
                using (var cmd = conn.CreateCommand())
                {
                    var sql = "SELECT sr.date, sr.created_at, IFNULL(sr.reference_no,'-'), IFNULL(sr.total_return_amount,0), IFNULL(o.outlet_name,'-')"
                        + " FROM sale_returns sr LEFT JOIN outlets o ON o.id=sr.outlet_id"
                        + " WHERE sr.customer_id=@cid AND (sr.del_status IS NULL OR sr.del_status='Live')";
                    if (!string.IsNullOrEmpty(from)) sql += " AND sr.date >= @from";
                    if (!string.IsNullOrEmpty(to))   sql += " AND sr.date <= @to";
                    if (outletId > 0) sql += " AND sr.outlet_id=" + outletId;
                    sql += " ORDER BY sr.date ASC, sr.id ASC";
                    cmd.CommandText = sql;
                    cmd.Parameters.AddWithValue("@cid", customerId);
                    if (!string.IsNullOrEmpty(from)) cmd.Parameters.AddWithValue("@from", from);
                    if (!string.IsNullOrEmpty(to))   cmd.Parameters.AddWithValue("@to", to);
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                        transactions.Add((r.GetString(0), "Sale Return", r.GetString(1), r.GetDouble(2), 0, r.GetString(3)));
                }

                // Sort by date
                transactions = transactions.OrderBy(t => t.date).ToList();

                // Apply type filter
                if (type == "Debit")  transactions = transactions.Where(t => t.debit > 0).ToList();
                if (type == "Credit") transactions = transactions.Where(t => t.credit > 0).ToList();

                // Build DataTable
                var table = new DataTable();
                table.Columns.Add("sn", typeof(string));
                table.Columns.Add("date_time", typeof(string));
                table.Columns.Add("transaction_type", typeof(string));
                table.Columns.Add("transaction_no", typeof(string));
                table.Columns.Add("debit", typeof(string));
                table.Columns.Add("credit", typeof(string));
                table.Columns.Add("outlet", typeof(string));

                int idx = 1;
                double runningBalance = obFromTransactions;
                double totalDebit = 0, totalCredit = 0;

                // Opening balance row
                string obDate = !string.IsNullOrEmpty(from) ? DateTime.Parse(from).ToString("dd MMM yyyy") : "Opening Balance";
                table.Rows.Add(idx++.ToString(), obDate + " (Opening Balance)", "Opening Balance", "-",
                    obFromTransactions >= 0 ? $"₹ {obFromTransactions:N2}" : "-",
                    obFromTransactions < 0 ? $"₹ {Math.Abs(obFromTransactions):N2}" : "-",
                    "-");

                foreach (var t in transactions)
                {
                    runningBalance += t.debit - t.credit;
                    totalDebit += t.debit;
                    totalCredit += t.credit;
                    string dt = DateTime.TryParse(t.date, out var parsed) ? parsed.ToString("dd MMM yyyy") : t.date;
                    table.Rows.Add(
                        idx++.ToString(),
                        dt,
                        t.type,
                        t.no,
                        t.debit > 0 ? $"₹ {t.debit:N2}" : "-",
                        t.credit > 0 ? $"₹ {t.credit:N2}" : "-",
                        t.outlet);
                }

                _currentTable = table;

                if (table.Rows.Count <= 1)
                { emptyState.Visibility = Visibility.Visible; dataGrid.Visibility = Visibility.Collapsed; return; }

                dataGrid.ItemsSource   = table.DefaultView;
                dataGrid.Visibility    = Visibility.Visible;
                rowCountBar.Visibility = Visibility.Visible;
                lblRowCount.Text       = $"Showing {table.Rows.Count - 1} transactions";

                // Totals
                lblTotalDebit.Text      = $"₹ {totalDebit:N2}";
                lblTotalCredit.Text     = $"₹ {totalCredit:N2}";
                lblOpeningBalance.Text  = $"₹ {obFromTransactions:N2}";
                lblClosingBalance.Text  = $"₹ {runningBalance:N2}";
                totalsBar.Visibility    = Visibility.Visible;
                openingBar.Visibility   = Visibility.Visible;
                closingBar.Visibility   = Visibility.Visible;
            }
            catch (Exception ex)
            {
                emptyState.Visibility = Visibility.Visible;
                MessageBox.Show("Error: " + ex.Message, "Customer Ledger Report", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void ShowFilterInfo()
        {
            filterInfoPanel.Children.Clear();
            AddInfo("Report", "Customer Ledger Report");
            if ((cmbCustomer.SelectedItem as ReportComboItem)?.Id > 0)
                AddInfo("Customer", ((ReportComboItem)cmbCustomer.SelectedItem).Name);
            if (!string.IsNullOrEmpty(dpDateFrom.SelectedDate?.ToString()))
                AddInfo("Date Range", $"{dpDateFrom.SelectedDate:dd MMM yyyy}  →  {dpDateTo.SelectedDate:dd MMM yyyy}");
            if ((cmbOutlet.SelectedItem as ReportComboItem)?.Id > 0)
                AddInfo("Outlet", ((ReportComboItem)cmbOutlet.SelectedItem).Name);
            if (cmbType.SelectedIndex > 0)
                AddInfo("Type", cmbType.SelectedItem?.ToString() ?? "All");
            AddInfo("Generated", DateTime.Now.ToString("dd MMM yyyy, hh:mm tt"));
            filterInfoBar.Visibility = Visibility.Visible;
        }

        private void AddInfo(string label, string value)
        {
            var sp = new StackPanel { Orientation = Orientation.Horizontal, Margin = new Thickness(0, 0, 0, 2) };
            sp.Children.Add(new TextBlock { Text = label + ": ", FontSize = 12, FontWeight = FontWeights.SemiBold, Foreground = Brushes.DarkSlateGray, FontFamily = new FontFamily("Inter, Segoe UI") });
            sp.Children.Add(new TextBlock { Text = value,        FontSize = 12, Foreground = Brushes.DarkSlateGray, FontFamily = new FontFamily("Inter, Segoe UI") });
            filterInfoPanel.Children.Add(sp);
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
            => _dashboard?.ShowPage(new ReportsIndexPage(_dashboard));

        private void BtnToggleFilter_Click(object sender, RoutedEventArgs e)
            => filterSection.Visibility = filterSection.Visibility == Visibility.Collapsed
               ? Visibility.Visible : Visibility.Collapsed;

        private void BtnApplyFilter_Click(object sender, RoutedEventArgs e)
        {
            if ((cmbCustomer.SelectedItem as ReportComboItem)?.Id <= 0)
            { MessageBox.Show("Please select a customer.", "Validation"); return; }
            filterSection.Visibility = Visibility.Collapsed;
            LoadData();
            ShowFilterInfo();
        }

        private void BtnCancelFilter_Click(object sender, RoutedEventArgs e)
            => filterSection.Visibility = Visibility.Collapsed;

        private void BtnExport_Click(object sender, RoutedEventArgs e)
            => exportPopup.IsOpen = !exportPopup.IsOpen;

        private void BtnExportCsv_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            if (_currentTable == null || _currentTable.Rows.Count == 0) { MessageBox.Show("No data."); return; }
            var dlg = new SaveFileDialog { Filter = "CSV (*.csv)|*.csv", FileName = "CustomerLedgerReport_" + DateTime.Today.ToString("yyyyMMdd") + ".csv" };
            if (dlg.ShowDialog() != true) return;
            try
            {
                using var sw = new StreamWriter(dlg.FileName, false, System.Text.Encoding.UTF8);
                sw.WriteLine(string.Join(",", _currentTable.Columns.Cast<DataColumn>().Select(c => c.ColumnName)));
                foreach (DataRow row in _currentTable.Rows)
                    sw.WriteLine(string.Join(",", _currentTable.Columns.Cast<DataColumn>()
                        .Select(c => "\"" + (row[c]?.ToString() ?? "").Replace("\"", "\"\"") + "\"")));
                MessageBox.Show("Exported: " + dlg.FileName, "Success");
            }
            catch (Exception ex) { MessageBox.Show("Error: " + ex.Message); }
        }

        private void BtnPrint_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            if (_currentTable == null || _currentTable.Rows.Count == 0) { MessageBox.Show("No data."); return; }
            var dlg = new PrintDialog();
            if (dlg.ShowDialog() != true) return;
            var doc = new FlowDocument { FontFamily = new FontFamily("Segoe UI"), FontSize = 11, PagePadding = new Thickness(40) };
            doc.Blocks.Add(new Paragraph(new Run("Customer Ledger Report")) { FontSize = 16, FontWeight = FontWeights.Bold, Margin = new Thickness(0, 0, 0, 6) });
            if ((cmbCustomer.SelectedItem as ReportComboItem)?.Id > 0)
                doc.Blocks.Add(new Paragraph(new Run($"Customer: {((ReportComboItem)cmbCustomer.SelectedItem).Name}")) { Foreground = Brushes.Gray, Margin = new Thickness(0, 0, 0, 12) });
            var tbl = new System.Windows.Documents.Table { CellSpacing = 2 };
            int cols = _currentTable.Columns.Count;
            for (int i = 0; i < cols; i++) tbl.Columns.Add(new TableColumn { Width = new GridLength(1, GridUnitType.Star) });
            var rg = new TableRowGroup();
            var hdr = new TableRow { Background = new SolidColorBrush(Color.FromRgb(0x69, 0x6c, 0xff)) };
            foreach (DataColumn col in _currentTable.Columns)
                hdr.Cells.Add(new TableCell(new Paragraph(new Run(col.ColumnName.Replace("_", " ").ToUpper()))) { Padding = new Thickness(4, 3, 4, 3), Foreground = Brushes.White, FontWeight = FontWeights.SemiBold });
            rg.Rows.Add(hdr);
            bool alt = false;
            foreach (DataRow row in _currentTable.Rows)
            {
                var tr = new TableRow { Background = alt ? new SolidColorBrush(Color.FromRgb(0xFA, 0xFA, 0xFF)) : Brushes.White };
                foreach (DataColumn col in _currentTable.Columns)
                    tr.Cells.Add(new TableCell(new Paragraph(new Run(row[col]?.ToString() ?? ""))) { Padding = new Thickness(4, 2, 4, 2) });
                rg.Rows.Add(tr);
                alt = !alt;
            }
            tbl.RowGroups.Add(rg);
            doc.Blocks.Add(tbl);
            var pag = ((IDocumentPaginatorSource)doc).DocumentPaginator;
            pag.PageSize = new Size(dlg.PrintableAreaWidth, dlg.PrintableAreaHeight);
            dlg.PrintDocument(pag, "Customer Ledger Report");
        }
    }
}
