using System;
using System.Collections.Generic;
using System.Data;
using System.Globalization;
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
    public partial class EmployeeSaleReportPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();
        private DataTable? _currentTable;

        public EmployeeSaleReportPage(MainDashboard? dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () => dataGrid.ItemsSource as DataView, "Employee Sale Report");
            _dashboard = dashboard;
            dpDateFrom.SelectedDate = new DateTime(DateTime.Today.Year, DateTime.Today.Month, 1);
            dpDateTo.SelectedDate   = DateTime.Today;
            LoadOutlets();
            LoadEmployees();
            LoadData();
        }

        private void LoadOutlets()
        {
            var list = new List<ReportComboItem> { new(0, "All Outlets") };
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT Id, outlet_name FROM outlets WHERE (del_status IS NULL OR del_status='Live') ORDER BY Id";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add(new ReportComboItem(r.IsDBNull(0) ? 0 : r.GetInt64(0), r.IsDBNull(1) ? "" : r.GetString(1)));
            }
            catch { }
            cmbOutlet.ItemsSource = list;
            cmbOutlet.SelectedIndex = 0;
        }

        private void LoadEmployees()
        {
            var list = new List<ReportComboItem> { new(0, "All Employees") };
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, name FROM users WHERE (del_status IS NULL OR del_status='Live') ORDER BY name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add(new ReportComboItem(r.IsDBNull(0) ? 0 : r.GetInt64(0), r.IsDBNull(1) ? "" : r.GetString(1)));
            }
            catch { }
            cmbEmployee.ItemsSource = list;
            cmbEmployee.SelectedIndex = 0;
        }

        private void LoadData()
        {
            emptyState.Visibility  = Visibility.Collapsed;
            dataGrid.Visibility    = Visibility.Collapsed;
            totalsBar.Visibility   = Visibility.Collapsed;
            rowCountBar.Visibility = Visibility.Collapsed;

            string from = dpDateFrom.SelectedDate?.ToString("yyyy-MM-dd") ?? "1900-01-01";
            string to   = dpDateTo.SelectedDate?.ToString("yyyy-MM-dd") ?? "2099-12-31";

            long outletId   = (cmbOutlet.SelectedItem   as ReportComboItem)?.Id ?? 0;
            long employeeId = (cmbEmployee.SelectedItem as ReportComboItem)?.Id ?? 0;

            // Sale Wise: filter by sales.user_id
            var sql = "SELECT ROW_NUMBER() OVER (ORDER BY s.sale_date DESC, s.Id DESC) AS sn,"
                + " s.sale_no AS invoice_no,"
                + " COALESCE(s.date_time, s.sale_date, s.created_at) AS date_time,"
                + " IFNULL(c.name, 'Walk-in Customer') AS customer,"
                + " (SELECT COUNT(*) FROM sale_details sd WHERE sd.sales_id=s.Id AND (sd.del_status IS NULL OR sd.del_status='Live')) AS items,"
                + " ROUND(IFNULL(s.sub_total, 0), 2) AS subtotal,"
                + " ROUND(IFNULL(u.commission, 0), 2) AS commission_pct,"
                + " ROUND(IFNULL(s.sub_total, 0) * IFNULL(u.commission, 0) / 100, 2) AS commission_amount"
                + " FROM sales s"
                + " LEFT JOIN customers c ON c.Id = s.customer_id"
                + " LEFT JOIN users u ON u.Id = s.user_id"
                + " WHERE s.sale_date BETWEEN @from AND @to"
                + " AND (s.del_status IS NULL OR s.del_status='Live')";

            if (outletId > 0)   sql += $" AND s.outlet_id = {outletId}";
            if (employeeId > 0) sql += $" AND s.user_id = {employeeId}";

            sql += " ORDER BY s.sale_date DESC, s.Id DESC";

            try
            {
                using var conn = _db.GetConnection();
                using var cmd  = conn.CreateCommand();
                cmd.CommandText = sql;
                cmd.Parameters.AddWithValue("@from", from);
                cmd.Parameters.AddWithValue("@to",   to);

                var table = new DataTable();
                using (var r = cmd.ExecuteReader()) table.Load(r);

                // Add formatted commission column for display
                var displayTable = new DataTable();
                displayTable.Columns.Add("sn", typeof(int));
                displayTable.Columns.Add("invoice_no", typeof(string));
                displayTable.Columns.Add("date_time", typeof(string));
                displayTable.Columns.Add("customer", typeof(string));
                displayTable.Columns.Add("items", typeof(int));
                displayTable.Columns.Add("subtotal", typeof(double));
                displayTable.Columns.Add("commission", typeof(string));
                displayTable.Columns.Add("commission_amount", typeof(double));

                double totalSubtotal = 0;
                double totalCommission = 0;

                foreach (DataRow row in table.Rows)
                {
                    double subtotal = row["subtotal"] is double s ? s : 0;
                    double commPct  = row["commission_pct"] is double c ? c : 0;
                    double commAmt  = row["commission_amount"] is double ca ? ca : 0;
                    totalSubtotal += subtotal;
                    totalCommission += commAmt;

                    displayTable.Rows.Add(
                        row["sn"],
                        row["invoice_no"],
                        row["date_time"],
                        row["customer"],
                        row["items"],
                        subtotal,
                        $"{commPct:N2}%",
                        commAmt
                    );
                }

                _currentTable = displayTable;

                if (displayTable.Rows.Count == 0)
                { emptyState.Visibility = Visibility.Visible; return; }

                dataGrid.ItemsSource    = displayTable.DefaultView;
                dataGrid.Visibility     = Visibility.Visible;
                rowCountBar.Visibility  = Visibility.Visible;
                lblRowCount.Text        = $"Showing {displayTable.Rows.Count} entries";

                lblTotalSubtotal.Text   = $"₹ {totalSubtotal:N2}";
                lblTotalCommission.Text = $"₹ {totalCommission:N2}";
                totalsBar.Visibility    = Visibility.Visible;
            }
            catch (Exception ex)
            {
                emptyState.Visibility = Visibility.Visible;
                MessageBox.Show("Error: " + ex.Message, "Employee Sale Report", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void ShowFilterInfo()
        {
            filterInfoPanel.Children.Clear();
            AddInfo("Report", "Employee Sale Report");
            AddInfo("Date Range", $"{dpDateFrom.SelectedDate:dd MMM yyyy}  →  {dpDateTo.SelectedDate:dd MMM yyyy}");
            if ((cmbOutlet.SelectedItem as ReportComboItem)?.Id > 0)
                AddInfo("Outlet", ((ReportComboItem)cmbOutlet.SelectedItem).Name);
            if ((cmbEmployee.SelectedItem as ReportComboItem)?.Id > 0)
                AddInfo("Employee", ((ReportComboItem)cmbEmployee.SelectedItem).Name);
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
            var dlg = new SaveFileDialog { Filter = "CSV (*.csv)|*.csv", FileName = "EmployeeSaleReport_" + DateTime.Today.ToString("yyyyMMdd") + ".csv" };
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
            doc.Blocks.Add(new Paragraph(new Run("Employee Sale Report")) { FontSize = 16, FontWeight = FontWeights.Bold, Margin = new Thickness(0, 0, 0, 6) });
            doc.Blocks.Add(new Paragraph(new Run($"Period: {dpDateFrom.SelectedDate:dd MMM yyyy} to {dpDateTo.SelectedDate:dd MMM yyyy}")) { Foreground = Brushes.Gray, Margin = new Thickness(0, 0, 0, 12) });
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
            dlg.PrintDocument(pag, "Employee Sale Report");
        }
    }
}
