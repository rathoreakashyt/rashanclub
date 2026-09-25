using System;
using System.Collections.Generic;
using System.Data;
using System.IO;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Documents;
using System.Windows.Media;
using RashanKiDukan.Database;
using RashanKiDukan.Models;

namespace RashanKiDukan.Views
{
    public partial class SalaryReportPage : UserControl
    {
        private readonly MainDashboard _dashboard;
        private readonly DatabaseService _db = new();
        private DataTable _allData = new DataTable();

        private static readonly string[] MonthNames = {
            "", "January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
        };

        public SalaryReportPage(MainDashboard dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () => dataGrid.ItemsSource as DataView, "Salary Report");
            _dashboard = dashboard;
            LoadFilters();
            LoadReport();
        }

        private void LoadFilters()
        {
            // Months
            var monthItems = new List<ReportComboItem>();
            monthItems.Add(new ReportComboItem(0, "All Months"));
            for (int m = 1; m <= 12; m++)
                monthItems.Add(new ReportComboItem(m, MonthNames[m]));
            cmbFromMonth.ItemsSource = monthItems;
            cmbFromMonth.SelectedIndex = 0;
            cmbToMonth.ItemsSource = new List<ReportComboItem>(monthItems);
            cmbToMonth.SelectedIndex = 0;

            // Years
            int currentYear = DateTime.Today.Year;
            var yearItems = new List<ReportComboItem>();
            yearItems.Add(new ReportComboItem(0, "All Years"));
            for (int y = currentYear; y >= currentYear - 5; y--)
                yearItems.Add(new ReportComboItem(y, y.ToString()));
            cmbFromYear.ItemsSource = yearItems;
            cmbFromYear.SelectedIndex = 0;
            cmbToYear.ItemsSource = new List<ReportComboItem>(yearItems);
            cmbToYear.SelectedIndex = 0;

            // Outlets
            var outletList = new List<ReportComboItem> { new(0, "All Outlets") };
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, IFNULL(outlet_name,name) FROM outlets WHERE (del_status IS NULL OR del_status='Live') ORDER BY id";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    outletList.Add(new ReportComboItem(r.GetInt64(0), r.GetString(1)));
            }
            catch { }
            cmbOutlet.ItemsSource = outletList;
            cmbOutlet.SelectedIndex = 0;
        }

        private void LoadReport(int fromMonth = 0, int toMonth = 0, int fromYear = 0, int toYear = 0, string outlet = "", long outletId = 0)
        {
            try
            {
                using var conn = _db.GetConnection();

                // Build year filter
                string yearCond = "";
                if (fromYear > 0 && toYear > 0 && fromYear == toYear)
                    yearCond = $" AND s.year={fromYear}";
                else if (fromYear > 0 && toYear > 0)
                    yearCond = $" AND s.year BETWEEN {fromYear} AND {toYear}";
                else if (fromYear > 0)
                    yearCond = $" AND s.year>={fromYear}";
                else if (toYear > 0)
                    yearCond = $" AND s.year<={toYear}";

                // Build month filter (within year range)
                string monthCond = "";
                if (fromMonth > 0 || toMonth > 0)
                {
                    if (fromYear > 0 && toYear > 0 && fromYear == toYear)
                    {
                        if (fromMonth > 0) monthCond += $" AND s.month>={fromMonth}";
                        if (toMonth > 0) monthCond += $" AND s.month<={toMonth}";
                    }
                    else if (fromYear > 0)
                    {
                        if (fromMonth > 0) monthCond += $" AND s.month>={fromMonth}";
                    }
                    else if (toYear > 0)
                    {
                        if (toMonth > 0) monthCond += $" AND s.month<={toMonth}";
                    }
                    else
                    {
                        if (fromMonth > 0) monthCond += $" AND s.month>={fromMonth}";
                        if (toMonth > 0) monthCond += $" AND s.month<={toMonth}";
                    }
                }

                // Outlet filter — through salary_items -> employees.outlet_id
                string outletCond = "";
                if (outletId > 0)
                {
                    outletCond = $@" AND s.id IN (
                        SELECT si.salary_id FROM salary_items si
                        INNER JOIN employees u ON si.employee_id=u.id
                        WHERE (si.del_status IS NULL OR si.del_status='Live') AND CAST(u.outlet_id AS INTEGER) = {outletId}
                    )";
                }

                var dt = new DataTable();
                dt.Columns.Add("sn", typeof(int));
                dt.Columns.Add("reference_no", typeof(string));
                dt.Columns.Add("date_time", typeof(string));
                dt.Columns.Add("year", typeof(string));
                dt.Columns.Add("month", typeof(string));
                dt.Columns.Add("amount", typeof(double));
                dt.Columns.Add("payment_method", typeof(string));
                dt.Columns.Add("_amountRaw", typeof(double));

                double tAmount = 0;
                int sn = 0;

                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $@"SELECT s.id, s.reference_no, s.generated_date, s.year, s.month, s.total_amount
                        FROM salaries s
                        WHERE (s.del_status IS NULL OR s.del_status='Live')
                        {yearCond}{monthCond}{outletCond}
                        ORDER BY s.year DESC, s.month DESC, s.generated_date DESC, s.id DESC";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        sn++;
                        long id = r.IsDBNull(0) ? 0 : r.GetInt64(0);
                        string refNo = r.IsDBNull(1) ? "-" : r.GetString(1);
                        var genDate = r.IsDBNull(2) ? (object)null : r.GetValue(2);
                        int year = r.IsDBNull(3) ? 0 : Convert.ToInt32(r.GetValue(3));
                        int month = r.IsDBNull(4) ? 0 : Convert.ToInt32(r.GetValue(4));
                        double amount = r.IsDBNull(5) ? 0 : Convert.ToDouble(r.GetValue(5));

                        string dtStr = genDate is DateTime d ? d.ToString("dd/MM/yyyy hh:mm tt") : genDate?.ToString() ?? "";
                        string monthName = (month >= 1 && month <= 12) ? MonthNames[month] : month.ToString();

                        // Payment method from salary_payments
                        string payMethod = "-";
                        try
                        {
                            using var pc = conn.CreateCommand();
                            pc.CommandText = $@"SELECT DISTINCT pm.name FROM salary_payments sp
                                LEFT JOIN payment_methods pm ON sp.payment_method_id=pm.id
                                WHERE sp.salary_id={id} AND (sp.del_status IS NULL OR sp.del_status='Live')";
                            using var pr = pc.ExecuteReader();
                            var methods = new List<string>();
                            while (pr.Read())
                            {
                                if (!pr.IsDBNull(0))
                                    methods.Add(pr.GetString(0));
                            }
                            if (methods.Count > 0)
                                payMethod = string.Join(", ", methods);
                        }
                        catch { }

                        tAmount += amount;
                        dt.Rows.Add(sn, refNo, dtStr, year > 0 ? year.ToString() : "-", monthName, amount, payMethod, amount);
                    }
                }

                _allData = dt;
                dataGrid.ItemsSource = null;
                dataGrid.ItemsSource = dt.DefaultView;

                bool hasData = dt.Rows.Count > 0;
                emptyState.Visibility = hasData ? Visibility.Collapsed : Visibility.Visible;
                dataGrid.Visibility = hasData ? Visibility.Visible : Visibility.Collapsed;
                totalsBar.Visibility = hasData ? Visibility.Visible : Visibility.Collapsed;
                rowCountBar.Visibility = hasData ? Visibility.Visible : Visibility.Collapsed;

                lblTotal.Text = "₹ " + tAmount.ToString("N2");
                lblRowCount.Text = $"Showing {dt.Rows.Count} entries";
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            if (_dashboard != null)
                _dashboard.ShowPage(new ReportsPage(_dashboard));
        }

        private void BtnToggleFilter_Click(object sender, RoutedEventArgs e)
        {
            filterSection.Visibility = filterSection.Visibility == Visibility.Visible
                ? Visibility.Collapsed : Visibility.Visible;
        }

        private void BtnApplyFilter_Click(object sender, RoutedEventArgs e)
        {
            int fromM = (int)((cmbFromMonth.SelectedItem as ReportComboItem)?.Id ?? 0);
            int toM = (int)((cmbToMonth.SelectedItem as ReportComboItem)?.Id ?? 0);
            int fromY = (int)((cmbFromYear.SelectedItem as ReportComboItem)?.Id ?? 0);
            int toY = (int)((cmbToYear.SelectedItem as ReportComboItem)?.Id ?? 0);
            var outletItem = cmbOutlet.SelectedItem as ReportComboItem;
            string outlet = outletItem?.Name ?? "";
            long outId = outletItem?.Id ?? 0;

            LoadReport(fromM, toM, fromY, toY, outlet, outId);

            filterInfoBar.Visibility = Visibility.Visible;
            filterInfoPanel.Children.Clear();

            string period = "";
            if (fromM > 0 || toM > 0 || fromY > 0 || toY > 0)
            {
                string fMonth = fromM > 0 ? MonthNames[fromM] : "";
                string tMonth = toM > 0 ? MonthNames[toM] : "";
                string fYear = fromY > 0 ? fromY.ToString() : "";
                string tYear = toY > 0 ? toY.ToString() : "";

                if (!string.IsNullOrEmpty(fMonth) || !string.IsNullOrEmpty(tMonth))
                    period = (fMonth + (!string.IsNullOrEmpty(fYear) ? " " + fYear : "")).Trim()
                           + (string.IsNullOrEmpty(period) ? "" : " – ")
                           + (tMonth + (!string.IsNullOrEmpty(tYear) ? " " + tYear : "")).Trim();
                else if (!string.IsNullOrEmpty(fYear) || !string.IsNullOrEmpty(tYear))
                    period = fYear + (!string.IsNullOrEmpty(tYear) && tYear != fYear ? " – " + tYear : "");
            }

            if (!string.IsNullOrEmpty(period))
                filterInfoPanel.Children.Add(new TextBlock
                {
                    Text = "Period: " + period,
                    FontSize = 11.5, FontFamily = new FontFamily("Inter, Segoe UI"),
                    Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0x65, 0x34)),
                    Margin = new Thickness(0, 0, 16, 0)
                });

            if (!string.IsNullOrEmpty(outlet) && outlet != "All Outlets")
                filterInfoPanel.Children.Add(new TextBlock
                {
                    Text = "Outlet: " + outlet,
                    FontSize = 11.5, FontFamily = new FontFamily("Inter, Segoe UI"),
                    Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0x65, 0x34))
                });

            filterSection.Visibility = Visibility.Collapsed;
        }

        private void BtnCancelFilter_Click(object sender, RoutedEventArgs e)
        {
            filterSection.Visibility = Visibility.Collapsed;
        }

        private void BtnExport_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = !exportPopup.IsOpen;
        }

        private void BtnPrint_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            var dlg = new PrintDialog();
            if (dlg.ShowDialog() != true) return;

            var dv = dataGrid.ItemsSource as DataView;
            if (dv == null || dv.Count == 0) { MessageBox.Show("No data to print.", "Info"); return; }

            var doc = new FlowDocument { FontFamily = new FontFamily("Segoe UI"), FontSize = 11, PagePadding = new Thickness(40) };
            doc.Blocks.Add(new Paragraph(new Run("Salary Report")) { FontSize = 16, FontWeight = FontWeights.Bold, Margin = new Thickness(0, 0, 0, 6) });

            var tbl = new System.Windows.Documents.Table { CellSpacing = 2 };
            string[] headers = { "SN", "REF NO", "DATE TIME", "YEAR", "MONTH", "AMOUNT", "PAYMENT METHOD" };
            for (int i = 0; i < headers.Length; i++) tbl.Columns.Add(new TableColumn { Width = new GridLength(1, GridUnitType.Star) });
            var rg = new TableRowGroup();
            var hdr = new TableRow { Background = new SolidColorBrush(Color.FromRgb(0x69, 0x6c, 0xff)) };
            foreach (var h in headers)
                hdr.Cells.Add(new TableCell(new Paragraph(new Run(h))) { Padding = new Thickness(4, 3, 4, 3), Foreground = Brushes.White, FontWeight = FontWeights.SemiBold });
            rg.Rows.Add(hdr);
            bool alt = false;
            foreach (DataRowView rv in dv)
            {
                var tr = new TableRow { Background = alt ? new SolidColorBrush(Color.FromRgb(0xFA, 0xFA, 0xFF)) : Brushes.White };
                string[] vals = {
                    rv["sn"].ToString(), rv["reference_no"].ToString(), rv["date_time"].ToString(),
                    rv["year"].ToString(), rv["month"].ToString(),
                    "₹ " + Convert.ToDouble(rv["amount"]).ToString("N2"),
                    rv["payment_method"].ToString()
                };
                foreach (var v in vals)
                    tr.Cells.Add(new TableCell(new Paragraph(new Run(v))) { Padding = new Thickness(4, 2, 4, 2) });
                rg.Rows.Add(tr);
                alt = !alt;
            }
            tbl.RowGroups.Add(rg);
            doc.Blocks.Add(tbl);
            var pag = ((IDocumentPaginatorSource)doc).DocumentPaginator;
            pag.PageSize = new Size(dlg.PrintableAreaWidth, dlg.PrintableAreaHeight);
            dlg.PrintDocument(pag, "Salary Report");
        }

        private void BtnExportCsv_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            var dv = dataGrid.ItemsSource as DataView;
            if (dv == null || dv.Count == 0) { MessageBox.Show("No data to export.", "Info"); return; }

            var sfd = new Microsoft.Win32.SaveFileDialog { Filter = "CSV|*.csv", FileName = "SalaryReport.csv" };
            if (sfd.ShowDialog() != true) return;

            using var sw = new StreamWriter(sfd.FileName, false, System.Text.Encoding.UTF8);
            sw.WriteLine("SN,REFERENCE NO,DATE TIME,YEAR,MONTH,AMOUNT,PAYMENT METHOD");
            foreach (DataRowView rv in dv)
            {
                sw.WriteLine(string.Join(",",
                    rv["sn"], rv["reference_no"], "\"" + rv["date_time"].ToString().Replace("\"", "\"\"") + "\"",
                    rv["year"], rv["month"],
                    "\"" + Convert.ToDouble(rv["amount"]).ToString("N2") + "\"",
                    "\"" + rv["payment_method"].ToString().Replace("\"", "\"\"") + "\""));
            }
            MessageBox.Show("Exported successfully.", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
        }
    }
}
