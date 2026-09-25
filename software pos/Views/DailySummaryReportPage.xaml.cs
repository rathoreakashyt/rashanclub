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
    // Row model for the DataGrid
    public class DailySummaryRow
    {
        public string date { get; set; } = "";
        public string section { get; set; } = "";
        public long transactions { get; set; }
        public double amount { get; set; }
    }

    public partial class DailySummaryReportPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();
        private List<DailySummaryRow> _currentRows = new();

        public DailySummaryReportPage(MainDashboard? dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () =>
            {
                var t = new DataTable();
                t.Columns.Add("Date");
                t.Columns.Add("Section");
                t.Columns.Add("Transactions", typeof(long));
                t.Columns.Add("Amount", typeof(double));
                foreach (var r in _currentRows)
                    t.Rows.Add(r.date, r.section, r.transactions, r.amount);
                return t.DefaultView;
            }, "Daily Summary Report");
            _dashboard = dashboard;
            dpDateFrom.SelectedDate = new DateTime(DateTime.Today.Year, DateTime.Today.Month, 1);
            dpDateTo.SelectedDate   = DateTime.Today;
            LoadOutlets();
            LoadData();
        }

        // ── Load Outlets ──────────────────────────────────────────────────
        private void LoadOutlets()
        {
            var list = new List<ReportComboItem> { new(0, "All Outlets") };
            try
            {
                using var conn = _db.GetConnection();
                using var cmd  = conn.CreateCommand();
                cmd.CommandText = "SELECT Id, outlet_name FROM outlets WHERE (del_status IS NULL OR del_status='Live') ORDER BY Id";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add(new ReportComboItem(r.IsDBNull(0) ? 0 : r.GetInt64(0), r.IsDBNull(1) ? "" : r.GetString(1)));
            }
            catch { }
            cmbOutlet.ItemsSource   = list;
            cmbOutlet.SelectedIndex = 0;
        }

        // ── Load Data ─────────────────────────────────────────────────────
        private void LoadData()
        {
            emptyState.Visibility  = Visibility.Collapsed;
            dataGrid.Visibility    = Visibility.Collapsed;
            totalsBar.Visibility   = Visibility.Collapsed;
            rowCountBar.Visibility = Visibility.Collapsed;

            string from     = dpDateFrom.SelectedDate?.ToString("yyyy-MM-dd") ?? "1900-01-01";
            string to       = dpDateTo.SelectedDate?.ToString("yyyy-MM-dd")   ?? "2099-12-31";
            long   outletId = (cmbOutlet.SelectedItem as ReportComboItem)?.Id        ?? 0;

            var rows = new List<DailySummaryRow>();

            try
            {
                using var conn = _db.GetConnection();

                // Helper: execute a grouped query and add rows
                void AddSection(string section, string sql)
                {
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = sql;
                    cmd.Parameters.AddWithValue("@from", from);
                    cmd.Parameters.AddWithValue("@to",   to);
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        rows.Add(new DailySummaryRow
                        {
                            date         = r["date"]?.ToString()  ?? "",
                            section      = section,
                            transactions = r.IsDBNull(1) ? 0 : (long)(r.GetDouble(1)),
                            amount       = r.IsDBNull(2) ? 0 : r.GetDouble(2)
                        });
                    }
                }

                string outletFilter = outletId > 0 ? $" AND outlet_id = {outletId}" : "";

                // 1. Sales
                AddSection("Sale",
                    "SELECT sale_date AS date, COUNT(*) AS cnt, ROUND(IFNULL(SUM(total_payable),0),2) AS amt"
                    + " FROM sales WHERE sale_date BETWEEN @from AND @to"
                    + " AND (del_status IS NULL OR del_status='Live')"
                    + outletFilter
                    + " GROUP BY sale_date ORDER BY sale_date");

                // 2. Purchase
                AddSection("Purchase",
                    "SELECT date, COUNT(*) AS cnt, ROUND(IFNULL(SUM(grand_total),0),2) AS amt"
                    + " FROM purchases WHERE date BETWEEN @from AND @to"
                    + " AND (del_status IS NULL OR del_status='Live')"
                    + " GROUP BY date ORDER BY date");

                // 3. Expense
                AddSection("Expense",
                    "SELECT date, COUNT(*) AS cnt, ROUND(IFNULL(SUM(amount),0),2) AS amt"
                    + " FROM expenses WHERE date BETWEEN @from AND @to"
                    + " AND (del_status IS NULL OR del_status='Live')"
                    + (outletId > 0 ? $" AND outlet_id = {outletId}" : "")
                    + " GROUP BY date ORDER BY date");

                // 4. Income
                AddSection("Income",
                    "SELECT date, COUNT(*) AS cnt, ROUND(IFNULL(SUM(amount),0),2) AS amt"
                    + " FROM incomes WHERE date BETWEEN @from AND @to"
                    + " AND (del_status IS NULL OR del_status='Live')"
                    + " GROUP BY date ORDER BY date");

                // 5. Sale Return
                AddSection("Sale Return",
                    "SELECT date, COUNT(*) AS cnt, ROUND(IFNULL(SUM(total_return_amount),0),2) AS amt"
                    + " FROM sale_returns WHERE date BETWEEN @from AND @to"
                    + " AND (del_status IS NULL OR del_status='Live')"
                    + " GROUP BY date ORDER BY date");

                // 6. Purchase Return
                AddSection("Purchase Return",
                    "SELECT date, COUNT(*) AS cnt, ROUND(IFNULL(SUM(total_return_amount),0),2) AS amt"
                    + " FROM purchase_returns WHERE date BETWEEN @from AND @to"
                    + " AND (del_status IS NULL OR del_status='Live')"
                    + " GROUP BY date ORDER BY date");

                // 7. Customer Receive
                AddSection("Customer Receive",
                    "SELECT date, COUNT(*) AS cnt, ROUND(IFNULL(SUM(amount),0),2) AS amt"
                    + " FROM customer_receives WHERE date BETWEEN @from AND @to"
                    + " AND (del_status IS NULL OR del_status='Live')"
                    + " GROUP BY date ORDER BY date");

                // 8. Installment Collection
                AddSection("Installment Collection",
                    "SELECT paid_date AS date, COUNT(*) AS cnt, ROUND(IFNULL(SUM(paid_amount),0),2) AS amt"
                    + " FROM installment_sale_details"
                    + " WHERE paid_status IN ('Paid','Partial') AND paid_date BETWEEN @from AND @to"
                    + " GROUP BY paid_date ORDER BY paid_date");
            }
            catch (Exception ex)
            {
                emptyState.Visibility = Visibility.Visible;
                MessageBox.Show("Error loading report: " + ex.Message, "Daily Summary", MessageBoxButton.OK, MessageBoxImage.Error);
                return;
            }

            // Sort: date ASC, then section
            _currentRows = rows.OrderBy(r => r.date).ThenBy(r => r.section).ToList();

            if (_currentRows.Count == 0)
            { emptyState.Visibility = Visibility.Visible; return; }

            dataGrid.ItemsSource   = _currentRows;
            dataGrid.Visibility    = Visibility.Visible;
            rowCountBar.Visibility = Visibility.Visible;
            lblRowCount.Text       = $"Showing {_currentRows.Count} entries";
            BuildTotals(_currentRows);
        }

        // ── Build totals ──────────────────────────────────────────────────
        private void BuildTotals(List<DailySummaryRow> rows)
        {
            double sale     = rows.Where(r => r.section == "Sale")     .Sum(r => r.amount);
            double purchase = rows.Where(r => r.section == "Purchase") .Sum(r => r.amount);
            double expense  = rows.Where(r => r.section == "Expense")  .Sum(r => r.amount);

            lblTotalSale.Text     = $"₹ {sale:N2}";
            lblTotalPurchase.Text = $"₹ {purchase:N2}";
            lblTotalExpense.Text  = $"₹ {expense:N2}";
            totalsBar.Visibility  = Visibility.Visible;
        }

        // ── Filter info bar ───────────────────────────────────────────────
        private void ShowFilterInfo()
        {
            filterInfoPanel.Children.Clear();
            AddInfo("Report",     "Daily Summary Report");
            AddInfo("Date Range", $"{dpDateFrom.SelectedDate:dd MMM yyyy}  →  {dpDateTo.SelectedDate:dd MMM yyyy}");
            if ((cmbOutlet.SelectedItem as ReportComboItem)?.Id > 0)
                AddInfo("Outlet", ((ReportComboItem)cmbOutlet.SelectedItem).Name);
            AddInfo("Generated",  DateTime.Now.ToString("dd MMM yyyy, hh:mm tt"));
            filterInfoBar.Visibility = Visibility.Visible;
        }

        private void AddInfo(string label, string value)
        {
            var sp = new StackPanel { Orientation = Orientation.Horizontal, Margin = new Thickness(0, 0, 0, 2) };
            sp.Children.Add(new TextBlock
            {
                Text = label + ": ", FontSize = 12, FontWeight = FontWeights.SemiBold,
                Foreground = Brushes.DarkSlateGray, FontFamily = new FontFamily("Inter, Segoe UI")
            });
            sp.Children.Add(new TextBlock
            {
                Text = value, FontSize = 12,
                Foreground = Brushes.DarkSlateGray, FontFamily = new FontFamily("Inter, Segoe UI")
            });
            filterInfoPanel.Children.Add(sp);
        }

        // ── Event handlers ────────────────────────────────────────────────
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

        // ── CSV Export ────────────────────────────────────────────────────
        private void BtnExportCsv_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            if (_currentRows.Count == 0) { MessageBox.Show("No data to export.", "Info"); return; }

            var dlg = new SaveFileDialog
            {
                Filter   = "CSV (*.csv)|*.csv",
                FileName = "DailySummaryReport_" + DateTime.Today.ToString("yyyyMMdd") + ".csv"
            };
            if (dlg.ShowDialog() != true) return;

            try
            {
                using var sw = new StreamWriter(dlg.FileName, false, System.Text.Encoding.UTF8);
                sw.WriteLine("Date,Section,Transactions,Amount");
                foreach (var row in _currentRows)
                    sw.WriteLine($"\"{row.date}\",\"{row.section}\",{row.transactions},{row.amount:N2}");
                MessageBox.Show("Exported: " + dlg.FileName, "Success", MessageBoxButton.OK, MessageBoxImage.Information);
            }
            catch (Exception ex) { MessageBox.Show("Export failed: " + ex.Message, "Error"); }
        }

        // ── Print ─────────────────────────────────────────────────────────
        private void BtnPrint_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            if (_currentRows.Count == 0) { MessageBox.Show("No data to print.", "Info"); return; }

            var dlg = new PrintDialog();
            if (dlg.ShowDialog() != true) return;

            var doc = new FlowDocument
            {
                FontFamily  = new FontFamily("Segoe UI"),
                FontSize    = 11,
                PagePadding = new Thickness(40)
            };

            doc.Blocks.Add(new Paragraph(new Run("Daily Summary Report"))
            { FontSize = 16, FontWeight = FontWeights.Bold, Margin = new Thickness(0, 0, 0, 6) });

            doc.Blocks.Add(new Paragraph(new Run(
                $"Period: {dpDateFrom.SelectedDate:dd MMM yyyy} to {dpDateTo.SelectedDate:dd MMM yyyy}"))
            { Foreground = Brushes.Gray, Margin = new Thickness(0, 0, 0, 12) });

            // Build print table
            var tbl = new System.Windows.Documents.Table { CellSpacing = 2 };
            tbl.Columns.Add(new TableColumn { Width = new GridLength(90) });
            tbl.Columns.Add(new TableColumn { Width = new GridLength(1, GridUnitType.Star) });
            tbl.Columns.Add(new TableColumn { Width = new GridLength(90) });
            tbl.Columns.Add(new TableColumn { Width = new GridLength(110) });

            var rg = new TableRowGroup();

            // Header
            var hdr = new TableRow { Background = new SolidColorBrush(Color.FromRgb(0x69, 0x6c, 0xff)) };
            foreach (string h in new[] { "DATE", "SECTION", "TRANSACTIONS", "AMOUNT" })
                hdr.Cells.Add(new TableCell(new Paragraph(new Run(h)))
                { Padding = new Thickness(6, 4, 6, 4), Foreground = Brushes.White, FontWeight = FontWeights.SemiBold });
            rg.Rows.Add(hdr);

            bool alt = false;
            foreach (var row in _currentRows)
            {
                var tr = new TableRow
                {
                    Background = alt
                        ? new SolidColorBrush(Color.FromRgb(0xFA, 0xFA, 0xFF))
                        : Brushes.White
                };
                tr.Cells.Add(new TableCell(new Paragraph(new Run(row.date)))         { Padding = new Thickness(6, 3, 6, 3) });
                tr.Cells.Add(new TableCell(new Paragraph(new Run(row.section)))      { Padding = new Thickness(6, 3, 6, 3) });
                tr.Cells.Add(new TableCell(new Paragraph(new Run(row.transactions.ToString()))) { Padding = new Thickness(6, 3, 6, 3) });
                tr.Cells.Add(new TableCell(new Paragraph(new Run($"₹ {row.amount:N2}")))       { Padding = new Thickness(6, 3, 6, 3) });
                rg.Rows.Add(tr);
                alt = !alt;
            }
            tbl.RowGroups.Add(rg);
            doc.Blocks.Add(tbl);

            var pag = ((IDocumentPaginatorSource)doc).DocumentPaginator;
            pag.PageSize = new System.Windows.Size(dlg.PrintableAreaWidth, dlg.PrintableAreaHeight);
            dlg.PrintDocument(pag, "Daily Summary Report");
        }
    }
}
