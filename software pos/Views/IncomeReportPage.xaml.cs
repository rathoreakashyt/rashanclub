using System;
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
    public partial class IncomeReportPage : UserControl
    {
        private readonly MainDashboard _dashboard;
        private readonly DatabaseService _db = new();
        private DataTable _allData = new DataTable();

        public IncomeReportPage(MainDashboard dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () => dataGrid.ItemsSource as DataView, "Income Report");
            _dashboard = dashboard;
            dpDateFrom.SelectedDate = new DateTime(DateTime.Today.Year, DateTime.Today.Month, 1);
            dpDateTo.SelectedDate = DateTime.Today;
            LoadFilters();
            LoadReport();
        }

        private void LoadFilters()
        {
            var catList = new System.Collections.Generic.List<ReportComboItem> { new(0, "All Categories") };
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, name FROM income_categories WHERE (del_status IS NULL OR del_status='Live') ORDER BY name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    catList.Add(new ReportComboItem(r.GetInt64(0), r.GetString(1)));
            }
            catch { }
            cmbCategory.ItemsSource = catList;
            cmbCategory.SelectedIndex = 0;

            var empList = new System.Collections.Generic.List<ReportComboItem> { new(0, "All Employees") };
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, name FROM employees WHERE (del_status IS NULL OR del_status='Live') ORDER BY name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    empList.Add(new ReportComboItem(r.GetInt64(0), r.GetString(1)));
            }
            catch { }
            cmbEmployee.ItemsSource = empList;
            cmbEmployee.SelectedIndex = 0;
        }

        private void LoadReport(DateTime? dateFrom = null, DateTime? dateTo = null, long categoryId = 0, long employeeId = 0)
        {
            try
            {
                using var conn = _db.GetConnection();

                string dateCond(string col) => dateFrom.HasValue && dateTo.HasValue
                    ? $" AND {col} BETWEEN '{dateFrom.Value:yyyy-MM-dd}' AND '{dateTo.Value:yyyy-MM-dd}'"
                    : dateFrom.HasValue ? $" AND {col} >= '{dateFrom.Value:yyyy-MM-dd}'"
                    : dateTo.HasValue ? $" AND {col} <= '{dateTo.Value:yyyy-MM-dd}'"
                    : "";
                string catCond = categoryId > 0 ? $" AND i.category_id={categoryId}" : "";
                string empCond = employeeId > 0 ? $" AND i.employee_id={employeeId}" : "";

                var dt = new DataTable();
                dt.Columns.Add("sn", typeof(int));
                dt.Columns.Add("reference_no", typeof(string));
                dt.Columns.Add("date_time", typeof(string));
                dt.Columns.Add("amount", typeof(double));
                dt.Columns.Add("category", typeof(string));
                dt.Columns.Add("responsible_person", typeof(string));
                dt.Columns.Add("_amountRaw", typeof(double));

                double tAmount = 0;
                int sn = 0;

                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $@"SELECT i.id, i.reference_no, i.date, i.amount, i.category_id, i.employee_id
                        FROM incomes i
                        WHERE (i.del_status IS NULL OR i.del_status='Live')
                        {dateCond("i.date")}
                        {catCond}
                        {empCond}
                        ORDER BY i.date DESC, i.id DESC";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        sn++;
                        long id = r.IsDBNull(0) ? 0 : r.GetInt64(0);
                        string refNo = r.IsDBNull(1) ? "-" : r.GetString(1);
                        var dateVal = r.IsDBNull(2) ? (object)null : r.GetValue(2);
                        double amount = r.IsDBNull(3) ? 0 : Convert.ToDouble(r.GetValue(3));
                        long catId = r.IsDBNull(4) ? 0 : Convert.ToInt64(r.GetValue(4));
                        long empId = r.IsDBNull(5) ? 0 : Convert.ToInt64(r.GetValue(5));

                        string dtStr = "";
                        if (dateVal is DateTime d)
                            dtStr = d.ToString("dd/MM/yyyy hh:mm tt");
                        else if (dateVal?.ToString() is string s && !string.IsNullOrEmpty(s))
                            dtStr = s;

                        // Category name
                        string catName = "-";
                        if (catId > 0)
                        {
                            using var cc = conn.CreateCommand();
                            cc.CommandText = "SELECT name FROM income_categories WHERE id=@cid AND (del_status IS NULL OR del_status='Live')";
                            cc.Parameters.AddWithValue("@cid", catId);
                            var cv = cc.ExecuteScalar();
                            if (cv != null) catName = cv.ToString() ?? "-";
                        }

                        // Responsible person (employee) — name + phone
                        string person = "-";
                        if (empId > 0)
                        {
                            using var ec = conn.CreateCommand();
                            ec.CommandText = "SELECT name, phone FROM employees WHERE id=@eid";
                            ec.Parameters.AddWithValue("@eid", empId);
                            using var er = ec.ExecuteReader();
                            if (er.Read())
                            {
                                person = er.IsDBNull(0) ? "-" : er.GetString(0);
                                if (!er.IsDBNull(1))
                                {
                                    string phone = er.GetString(1);
                                    if (!string.IsNullOrEmpty(phone))
                                        person += " (" + phone + ")";
                                }
                            }
                        }

                        tAmount += amount;
                        dt.Rows.Add(sn, refNo, dtStr, amount, catName, person, amount);
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
            DateTime? from = dpDateFrom.SelectedDate;
            DateTime? to = dpDateTo.SelectedDate;
            long catId = (cmbCategory.SelectedItem as ReportComboItem)?.Id ?? 0;
            long empId = (cmbEmployee.SelectedItem as ReportComboItem)?.Id ?? 0;

            LoadReport(from, to, catId, empId);

            filterInfoBar.Visibility = Visibility.Visible;
            filterInfoPanel.Children.Clear();

            if (from.HasValue)
                filterInfoPanel.Children.Add(new TextBlock
                {
                    Text = "Date: " + from.Value.ToString("dd/MM/yyyy") + (to.HasValue ? " – " + to.Value.ToString("dd/MM/yyyy") : ""),
                    FontSize = 11.5, FontFamily = new FontFamily("Inter, Segoe UI"),
                    Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0x65, 0x34)),
                    Margin = new Thickness(0, 0, 16, 0)
                });

            if (catId > 0)
                filterInfoPanel.Children.Add(new TextBlock
                {
                    Text = "Category: " + (cmbCategory.SelectedItem as ReportComboItem)?.Name,
                    FontSize = 11.5, FontFamily = new FontFamily("Inter, Segoe UI"),
                    Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0x65, 0x34)),
                    Margin = new Thickness(0, 0, 16, 0)
                });

            if (empId > 0)
                filterInfoPanel.Children.Add(new TextBlock
                {
                    Text = "Employee: " + (cmbEmployee.SelectedItem as ReportComboItem)?.Name,
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
            doc.Blocks.Add(new Paragraph(new Run("Income Report")) { FontSize = 16, FontWeight = FontWeights.Bold, Margin = new Thickness(0, 0, 0, 6) });
            if (dpDateFrom.SelectedDate.HasValue)
                doc.Blocks.Add(new Paragraph(new Run($"Period: {dpDateFrom.SelectedDate:dd MMM yyyy} to {dpDateTo.SelectedDate:dd MMM yyyy}")) { Foreground = Brushes.Gray, Margin = new Thickness(0, 0, 0, 12) });

            var tbl = new System.Windows.Documents.Table { CellSpacing = 2 };
            string[] headers = { "SN", "REF NO", "DATE TIME", "AMOUNT", "INCOME CATEGORY", "RESPONSIBLE PERSON" };
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
                    "₹ " + Convert.ToDouble(rv["amount"]).ToString("N2"),
                    rv["category"].ToString(), rv["responsible_person"].ToString()
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
            dlg.PrintDocument(pag, "Income Report");
        }

        private void BtnExportCsv_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            var dv = dataGrid.ItemsSource as DataView;
            if (dv == null || dv.Count == 0) { MessageBox.Show("No data to export.", "Info"); return; }

            var sfd = new Microsoft.Win32.SaveFileDialog { Filter = "CSV|*.csv", FileName = "IncomeReport.csv" };
            if (sfd.ShowDialog() != true) return;

            using var sw = new StreamWriter(sfd.FileName, false, System.Text.Encoding.UTF8);
            sw.WriteLine("SN,REFERENCE NO,DATE TIME,AMOUNT,INCOME CATEGORY,RESPONSIBLE PERSON");
            foreach (DataRowView rv in dv)
            {
                sw.WriteLine(string.Join(",",
                    rv["sn"], rv["reference_no"], "\"" + rv["date_time"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + Convert.ToDouble(rv["amount"]).ToString("N2") + "\"",
                    "\"" + rv["category"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["responsible_person"].ToString().Replace("\"", "\"\"") + "\""));
            }
            MessageBox.Show("Exported successfully.", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
        }
    }
}
