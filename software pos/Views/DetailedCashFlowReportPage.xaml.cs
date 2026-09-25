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
    public partial class DetailedCashFlowReportPage : UserControl
    {
        private readonly MainDashboard _dashboard;
        private readonly DatabaseService _db = new();
        private DataTable _allData = new DataTable();

        public DetailedCashFlowReportPage(MainDashboard dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () => dataGrid.ItemsSource as DataView, "Detailed Cash Flow Report");
            _dashboard = dashboard;
            dpDateFrom.SelectedDate = new DateTime(DateTime.Today.Year, DateTime.Today.Month, 1);
            dpDateTo.SelectedDate = DateTime.Today;
            LoadFilters();
            LoadReport();
        }

        private void LoadFilters()
        {
            var outletList = new System.Collections.Generic.List<ReportComboItem> { new(0, "All Outlets") };
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

            var typeList = new System.Collections.Generic.List<ReportComboItem>
            {
                new(0, "All Types"),
                new(1, "Sale"),
                new(2, "Purchase"),
                new(3, "Purchase Return"),
                new(4, "Sale Return"),
                new(5, "Income"),
                new(6, "Expense")
            };
            cmbType.ItemsSource = typeList;
            cmbType.SelectedIndex = 0;
        }

        private void LoadReport(DateTime? dateFrom = null, DateTime? dateTo = null, string outlet = "", string transType = "", long outletId = 0)
        {
            try
            {
                using var conn = _db.GetConnection();

                string dateCond(string col) => dateFrom.HasValue && dateTo.HasValue
                    ? $" AND {col} BETWEEN '{dateFrom.Value:yyyy-MM-dd}' AND '{dateTo.Value:yyyy-MM-dd}'"
                    : dateFrom.HasValue ? $" AND {col} >= '{dateFrom.Value:yyyy-MM-dd}'"
                    : dateTo.HasValue ? $" AND {col} <= '{dateTo.Value:yyyy-MM-dd}'"
                    : "";
                string outletCond(string tbl) => outletId > 0 ? $" AND {tbl}.outlet_id = {outletId}" : "";

                var dt = new DataTable();
                dt.Columns.Add("sn", typeof(int));
                dt.Columns.Add("date", typeof(string));
                dt.Columns.Add("transaction_type", typeof(string));
                dt.Columns.Add("reference_no", typeof(string));
                dt.Columns.Add("details", typeof(string));
                dt.Columns.Add("outlet", typeof(string));
                dt.Columns.Add("credit", typeof(string));
                dt.Columns.Add("debit", typeof(string));
                dt.Columns.Add("_creditRaw", typeof(double));
                dt.Columns.Add("_debitRaw", typeof(double));
                dt.Columns.Add("_sortDate", typeof(DateTime));

                double totalCredit = 0, totalDebit = 0;
                int sn = 0;

                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $@"
                        SELECT 'Sale' AS type, s.sale_date AS date, s.sale_no AS reference_no,
                            c.name AS party, o.name AS outlet,
                            IFNULL(s.total_payable,0) AS credit, 0 AS debit
                        FROM sales s
                        LEFT JOIN customers c ON c.Id=s.customer_id
                        LEFT JOIN outlets o ON o.Id=s.outlet_id
                        WHERE (s.del_status IS NULL OR s.del_status='Live') {dateCond("s.sale_date")} {outletCond("s")}
                        {(transType == "Sale" ? "" : transType != "All Types" && !string.IsNullOrEmpty(transType) ? " AND 1=0" : "")}

                        UNION ALL

                        SELECT 'Purchase', p.date, p.reference_no,
                            sp.name, o2.name,
                            0, IFNULL(p.grand_total,0)
                        FROM purchases p
                        LEFT JOIN suppliers sp ON sp.Id=p.supplier_id
                        LEFT JOIN outlets o2 ON o2.Id=p.outlet_id
                        WHERE (p.del_status IS NULL OR p.del_status='Live') {dateCond("p.date")} {outletCond("p")}
                        {(transType == "Purchase" ? "" : transType != "All Types" && !string.IsNullOrEmpty(transType) ? " AND 1=0" : "")}

                        UNION ALL

                        SELECT 'Purchase Return', pr.date, pr.reference_no,
                            sp2.name, o3.name,
                            IFNULL(pr.total_return_amount,0), 0
                        FROM purchase_returns pr
                        LEFT JOIN suppliers sp2 ON sp2.Id=pr.supplier_id
                        LEFT JOIN outlets o3 ON o3.Id=pr.outlet_id
                        WHERE (pr.del_status IS NULL OR pr.del_status='Live') {dateCond("pr.date")} {outletCond("pr")}
                        {(transType == "Purchase Return" ? "" : transType != "All Types" && !string.IsNullOrEmpty(transType) ? " AND 1=0" : "")}

                        UNION ALL

                        SELECT 'Sale Return', sr.date, sr.reference_no,
                            c2.name, o4.name,
                            0, IFNULL(sr.total_return_amount,0)
                        FROM sale_returns sr
                        LEFT JOIN customers c2 ON c2.Id=sr.customer_id
                        LEFT JOIN outlets o4 ON o4.Id=sr.outlet_id
                        WHERE (sr.del_status IS NULL OR sr.del_status='Live') {dateCond("sr.date")} {outletCond("sr")}
                        {(transType == "Sale Return" ? "" : transType != "All Types" && !string.IsNullOrEmpty(transType) ? " AND 1=0" : "")}

                        UNION ALL

                        SELECT 'Income', i.date, i.reference_no,
                            IFNULL(ic.name, '-'), o5.name,
                            IFNULL(i.amount,0), 0
                        FROM incomes i
                        LEFT JOIN income_categories ic ON ic.id = i.category_id
                        LEFT JOIN outlets o5 ON o5.Id=i.outlet_id
                        WHERE (i.del_status IS NULL OR i.del_status='Live') {dateCond("i.date")} {outletCond("i")}
                        {(transType == "Income" ? "" : transType != "All Types" && !string.IsNullOrEmpty(transType) ? " AND 1=0" : "")}

                        UNION ALL

                        SELECT 'Expense', e.date, e.reference_no,
                            IFNULL(ec.name, '-'), o6.name,
                            0, IFNULL(e.amount,0)
                        FROM expenses e
                        LEFT JOIN expense_categories ec ON ec.id = e.category_id
                        LEFT JOIN outlets o6 ON o6.Id=e.outlet_id
                        WHERE (e.del_status IS NULL OR e.del_status='Live') {dateCond("e.date")} {outletCond("e")}
                        {(transType == "Expense" ? "" : transType != "All Types" && !string.IsNullOrEmpty(transType) ? " AND 1=0" : "")}

                        ORDER BY date ASC";

                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        sn++;
                        string type = r.IsDBNull(0) ? "-" : r.GetString(0);
                        var date = r.IsDBNull(1) ? DateTime.MinValue : DateTime.Parse(r.GetString(1));
                        string refNo = r.IsDBNull(2) ? "-" : r.GetString(2);
                        string details = r.IsDBNull(3) ? "-" : r.GetString(3);
                        string outletName = r.IsDBNull(4) ? "-" : r.GetString(4);
                        double credit = r.IsDBNull(5) ? 0 : Convert.ToDouble(r.GetValue(5));
                        double debit = r.IsDBNull(6) ? 0 : Convert.ToDouble(r.GetValue(6));

                        string dateStr = date.ToString("dd/MM/yyyy");
                        totalCredit += credit;
                        totalDebit += debit;

                        dt.Rows.Add(sn, dateStr, type, refNo, details, outletName,
                            credit > 0 ? "₹ " + credit.ToString("N2") : "-",
                            debit > 0 ? "₹ " + debit.ToString("N2") : "-",
                            credit, debit, date);
                    }
                }

                _allData = dt;
                dataGrid.ItemsSource = null;
                dataGrid.ItemsSource = dt.DefaultView;

                bool hasData = dt.Rows.Count > 0;
                emptyState.Visibility = hasData ? Visibility.Collapsed : Visibility.Visible;
                dataGrid.Visibility = hasData ? Visibility.Visible : Visibility.Collapsed;
                rowCountBar.Visibility = hasData ? Visibility.Visible : Visibility.Collapsed;

                lblRowCount.Text = $"Showing {dt.Rows.Count} entries | Credit: ₹ {totalCredit:N2} | Debit: ₹ {totalDebit:N2} | Net: ₹ {(totalCredit - totalDebit):N2}";
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
            var outletItem = cmbOutlet.SelectedItem as ReportComboItem;
            string outlet = outletItem?.Name ?? "";
            long outId = outletItem?.Id ?? 0;
            string type = (cmbType.SelectedItem as ReportComboItem)?.Name ?? "";

            LoadReport(from, to, outlet, type, outId);

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

            if (!string.IsNullOrEmpty(outlet) && outlet != "All Outlets")
                filterInfoPanel.Children.Add(new TextBlock
                {
                    Text = "Outlet: " + outlet,
                    FontSize = 11.5, FontFamily = new FontFamily("Inter, Segoe UI"),
                    Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0x65, 0x34)),
                    Margin = new Thickness(0, 0, 16, 0)
                });

            if (!string.IsNullOrEmpty(type) && type != "All Types")
                filterInfoPanel.Children.Add(new TextBlock
                {
                    Text = "Type: " + type,
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
            doc.Blocks.Add(new Paragraph(new Run("Detailed Cash Flow Report")) { FontSize = 16, FontWeight = FontWeights.Bold, Margin = new Thickness(0, 0, 0, 6) });
            if (dpDateFrom.SelectedDate.HasValue)
                doc.Blocks.Add(new Paragraph(new Run($"Period: {dpDateFrom.SelectedDate:dd MMM yyyy} to {dpDateTo.SelectedDate:dd MMM yyyy}")) { Foreground = Brushes.Gray, Margin = new Thickness(0, 0, 0, 12) });

            var tbl = new System.Windows.Documents.Table { CellSpacing = 2 };
            string[] headers = { "SN", "DATE", "TRANSACTION", "REF NO", "DETAILS", "OUTLET", "CREDIT", "DEBIT" };
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
                    rv["sn"].ToString(), rv["date"].ToString(), rv["transaction_type"].ToString(),
                    rv["reference_no"].ToString(), rv["details"].ToString(), rv["outlet"].ToString(),
                    rv["credit"].ToString(), rv["debit"].ToString()
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
            dlg.PrintDocument(pag, "Detailed Cash Flow Report");
        }

        private void BtnExportCsv_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            var dv = dataGrid.ItemsSource as DataView;
            if (dv == null || dv.Count == 0) { MessageBox.Show("No data to export.", "Info"); return; }

            var sfd = new Microsoft.Win32.SaveFileDialog { Filter = "CSV|*.csv", FileName = "DetailedCashFlowReport.csv" };
            if (sfd.ShowDialog() != true) return;

            using var sw = new StreamWriter(sfd.FileName, false, System.Text.Encoding.UTF8);
            sw.WriteLine("SN,DATE,TRANSACTION TYPE,REFERENCE NO,DETAILS,OUTLET,CREDIT,DEBIT");
            foreach (DataRowView rv in dv)
            {
                sw.WriteLine(string.Join(",",
                    rv["sn"], "\"" + rv["date"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["transaction_type"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["reference_no"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["details"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["outlet"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["credit"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["debit"].ToString().Replace("\"", "\"\"") + "\""));
            }
            MessageBox.Show("Exported successfully.", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
        }
    }
}
