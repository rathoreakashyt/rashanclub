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
    public partial class CustomerBalanceReportPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();
        private DataTable? _currentTable;

        public CustomerBalanceReportPage(MainDashboard? dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () => dataGrid.ItemsSource as DataView, "Customer Balance Report");
            _dashboard = dashboard;
            LoadTypes();
            LoadData();
            ShowFilterInfo();
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
            emptyState.Visibility  = Visibility.Collapsed;
            dataGrid.Visibility    = Visibility.Collapsed;
            totalsBar.Visibility   = Visibility.Collapsed;
            rowCountBar.Visibility = Visibility.Collapsed;

            string type = cmbType.SelectedItem?.ToString() ?? "All";

            try
            {
                using var conn = _db.GetConnection();

                // Get all customers
                var customers = new List<(long id, string name, string phone, double openingBalance, string openingType)>();
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT id, IFNULL(name,''), IFNULL(phone,''), IFNULL(opening_balance,0), IFNULL(opening_balance_type,'Debit')"
                        + " FROM customers WHERE (del_status IS NULL OR del_status='Live') ORDER BY name";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                        customers.Add((r.GetInt64(0), r.GetString(1), r.GetString(2), r.GetDouble(3), r.GetString(4)));
                }

                var table = new DataTable();
                table.Columns.Add("sn", typeof(string));
                table.Columns.Add("customer_name", typeof(string));
                table.Columns.Add("current_balance", typeof(string));

                double totalBalance = 0;
                int idx = 1;

                foreach (var c in customers)
                {
                    // Base opening balance. Customer: Dr opening = customer humara deta hai (+), Cr = hum customer ko dete hain (-).
                    bool isDrOpening = c.openingType == "Dr" || c.openingType == "Debit";
                    double baseOb = isDrOpening ? c.openingBalance : -c.openingBalance;

                    // Total sales (Debit - customer owes us)
                    double totalSales = 0;
                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.CommandText = "SELECT IFNULL(SUM(grand_total),0) FROM sales WHERE customer_id=@cid AND (del_status IS NULL OR del_status='Live')";
                        cmd.Parameters.AddWithValue("@cid", c.id);
                        var o = cmd.ExecuteScalar(); if (o != null) totalSales = Convert.ToDouble(o);
                    }

                    // Total receives (Credit - customer pays us)
                    double totalReceives = 0;
                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.CommandText = "SELECT IFNULL(SUM(amount),0) FROM customer_receives WHERE customer_id=@cid AND (del_status IS NULL OR del_status='Live')";
                        cmd.Parameters.AddWithValue("@cid", c.id);
                        var o = cmd.ExecuteScalar(); if (o != null) totalReceives = Convert.ToDouble(o);
                    }

                    // Total returns (Credit - we return to customer)
                    double totalReturns = 0;
                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.CommandText = "SELECT IFNULL(SUM(total_return_amount),0) FROM sale_returns WHERE customer_id=@cid AND (del_status IS NULL OR del_status='Live')";
                        cmd.Parameters.AddWithValue("@cid", c.id);
                        var o = cmd.ExecuteScalar(); if (o != null) totalReturns = Convert.ToDouble(o);
                    }

                    // Customer balance: positive = Debit (customer owes us).
                    double currentBalance = baseOb + totalSales - totalReceives - totalReturns;

                    // Apply type filter
                    if (type == "Debit" && currentBalance <= 0) continue;
                    if (type == "Credit" && currentBalance >= 0) continue;

                    string customerName = string.IsNullOrEmpty(c.phone) ? c.name : $"{c.name} (+91-{c.phone})";
                    string balLabel = currentBalance >= 0 ? "Debit" : "Credit";

                    table.Rows.Add(idx++.ToString(), customerName, $"₹ {Math.Abs(currentBalance):N2} ({balLabel})");
                    totalBalance += currentBalance;
                }

                // Sort by balance (highest first)
                var rows = table.AsEnumerable().OrderByDescending(r =>
                {
                    string val = r["current_balance"]?.ToString() ?? "0";
                    val = val.Replace("₹", "").Replace(",", "").Trim();
                    int paren = val.IndexOf("(");
                    if (paren >= 0) val = val.Substring(0, paren).Trim();
                    return double.TryParse(val, out double d) ? d : 0;
                }).ToList();

                table.Clear();
                int sn = 1;
                foreach (var row in rows)
                    table.Rows.Add(sn++.ToString(), row["customer_name"], row["current_balance"]);

                _currentTable = table;

                if (table.Rows.Count == 0)
                { emptyState.Visibility = Visibility.Visible; return; }

                dataGrid.ItemsSource    = table.DefaultView;
                dataGrid.Visibility     = Visibility.Visible;
                rowCountBar.Visibility  = Visibility.Visible;
                lblRowCount.Text        = $"Showing {table.Rows.Count} customers";

                lblTotalBalance.Text    = $"₹ {Math.Abs(totalBalance):N2} ({(totalBalance >= 0 ? "Debit" : "Credit")})";
                lblTotalCustomers.Text  = table.Rows.Count.ToString();
                totalsBar.Visibility    = Visibility.Visible;
            }
            catch (Exception ex)
            {
                emptyState.Visibility = Visibility.Visible;
                MessageBox.Show("Error: " + ex.Message, "Customer Balance Report", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void ShowFilterInfo()
        {
            filterInfoPanel.Children.Clear();
            AddInfo("Report", "Customer Balance Report");
            AddInfo("Generated on", DateTime.Now.ToString("dd/MM/yyyy HH:mm:ss"));
            if (_dashboard?.CurrentUser != null)
                AddInfo("Generated by", _dashboard.CurrentUser.FullName);
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
            var dlg = new SaveFileDialog { Filter = "CSV (*.csv)|*.csv", FileName = "CustomerBalanceReport_" + DateTime.Today.ToString("yyyyMMdd") + ".csv" };
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
            doc.Blocks.Add(new Paragraph(new Run("Customer Balance Report")) { FontSize = 16, FontWeight = FontWeights.Bold, Margin = new Thickness(0, 0, 0, 6) });
            doc.Blocks.Add(new Paragraph(new Run($"Generated on: {DateTime.Now:dd/MM/yyyy HH:mm:ss}")) { Foreground = Brushes.Gray, Margin = new Thickness(0, 0, 0, 12) });
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
            dlg.PrintDocument(pag, "Customer Balance Report");
        }
    }
}
