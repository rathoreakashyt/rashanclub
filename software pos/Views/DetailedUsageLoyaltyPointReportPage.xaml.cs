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
    public partial class DetailedUsageLoyaltyPointReportPage : UserControl
    {
        private readonly MainDashboard _dashboard;
        private readonly DatabaseService _db = new();
        private DataTable _allData = new DataTable();

        public DetailedUsageLoyaltyPointReportPage(MainDashboard dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () => dataGrid.ItemsSource as DataView, "Detailed Usage Loyalty Point Report");
            _dashboard = dashboard;
            dpDateFrom.SelectedDate = new DateTime(DateTime.Today.Year, DateTime.Today.Month, 1);
            dpDateTo.SelectedDate = DateTime.Today;
            LoadFilters();
            LoadReport();
        }

        private void LoadFilters()
        {
            var custList = new System.Collections.Generic.List<ReportComboItem> { new(0, "All Customers") };
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, name FROM customers WHERE (del_status IS NULL OR del_status='Live') ORDER BY name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    custList.Add(new ReportComboItem(r.GetInt64(0), r.GetString(1)));
            }
            catch { }
            cmbCustomer.ItemsSource = custList;
            cmbCustomer.SelectedIndex = 0;

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
        }

        private void LoadReport(DateTime? dateFrom = null, DateTime? dateTo = null, long customerId = 0, string outlet = "", long outletId = 0)
        {
            try
            {
                using var conn = _db.GetConnection();

                string dateCond(string col) => dateFrom.HasValue && dateTo.HasValue
                    ? $" AND {col} BETWEEN '{dateFrom.Value:yyyy-MM-dd}' AND '{dateTo.Value:yyyy-MM-dd}'"
                    : dateFrom.HasValue ? $" AND {col} >= '{dateFrom.Value:yyyy-MM-dd}'"
                    : dateTo.HasValue ? $" AND {col} <= '{dateTo.Value:yyyy-MM-dd}'"
                    : "";
                string custCond = customerId > 0 ? $" AND s.customer_id={customerId}" : "";
                string outletCond = outletId > 0 ? $" AND s.outlet_id = {outletId}" : "";

                var dt = new DataTable();
                dt.Columns.Add("sn", typeof(int));
                dt.Columns.Add("date", typeof(string));
                dt.Columns.Add("sale_no", typeof(string));
                dt.Columns.Add("customer", typeof(string));
                dt.Columns.Add("redeemed_amount", typeof(string));
                dt.Columns.Add("_amountRaw", typeof(double));

                double totalRedeemed = 0;
                int sn = 0;

                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $@"SELECT sp.date, s.sale_no, s.customer_id,
                        IFNULL(sp.usage_point,0) AS points_used
                        FROM sale_payments sp
                        INNER JOIN sales s ON s.Id=sp.sale_id
                        WHERE (sp.del_status IS NULL OR sp.del_status='Live') AND (s.del_status IS NULL OR s.del_status='Live')
                        AND IFNULL(sp.usage_point,0)>0
                        {dateCond("sp.date")} {custCond} {outletCond}
                        ORDER BY sp.date ASC";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        sn++;
                        var date = r.IsDBNull(0) ? DateTime.MinValue : DateTime.Parse(r.GetString(0));
                        string saleNo = r.IsDBNull(1) ? "-" : r.GetString(1);
                        long custId = r.IsDBNull(2) ? 0 : Convert.ToInt64(r.GetValue(2));
                        double pointsUsed = r.IsDBNull(3) ? 0 : Convert.ToDouble(r.GetValue(3));

                        string dateStr = date.ToString("dd/MM/yyyy");

                        // Customer name + phone
                        string custName = "-";
                        if (custId > 0)
                        {
                            using var cc = conn.CreateCommand();
                            cc.CommandText = "SELECT name, phone FROM customers WHERE id=@cid AND (del_status IS NULL OR del_status='Live')";
                            cc.Parameters.AddWithValue("@cid", custId);
                            using var cr = cc.ExecuteReader();
                            if (cr.Read())
                            {
                                custName = cr.IsDBNull(0) ? "-" : cr.GetString(0);
                                if (!cr.IsDBNull(1))
                                {
                                    string phone = cr.GetString(1);
                                    if (!string.IsNullOrEmpty(phone))
                                        custName += " (" + phone + ")";
                                }
                            }
                        }

                        totalRedeemed += pointsUsed;

                        dt.Rows.Add(sn, dateStr, saleNo, custName,
                            pointsUsed.ToString("N0"), pointsUsed);
                    }
                }

                _allData = dt;
                dataGrid.ItemsSource = null;
                dataGrid.ItemsSource = dt.DefaultView;

                bool hasData = dt.Rows.Count > 0;
                emptyState.Visibility = hasData ? Visibility.Collapsed : Visibility.Visible;
                dataGrid.Visibility = hasData ? Visibility.Visible : Visibility.Collapsed;
                rowCountBar.Visibility = hasData ? Visibility.Visible : Visibility.Collapsed;

                lblRowCount.Text = $"Showing {dt.Rows.Count} entries | Total Points Redeemed: {totalRedeemed:N0}";
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
            long custId = (cmbCustomer.SelectedItem as ReportComboItem)?.Id ?? 0;
            var outletItem = cmbOutlet.SelectedItem as ReportComboItem;
            string outlet = outletItem?.Name ?? "";
            long outId = outletItem?.Id ?? 0;

            LoadReport(from, to, custId, outlet, outId);

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

            if (custId > 0)
                filterInfoPanel.Children.Add(new TextBlock
                {
                    Text = "Customer: " + (cmbCustomer.SelectedItem as ReportComboItem)?.Name,
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
            doc.Blocks.Add(new Paragraph(new Run("Detailed Usage Loyalty Point Report")) { FontSize = 16, FontWeight = FontWeights.Bold, Margin = new Thickness(0, 0, 0, 6) });
            if (dpDateFrom.SelectedDate.HasValue)
                doc.Blocks.Add(new Paragraph(new Run($"Period: {dpDateFrom.SelectedDate:dd MMM yyyy} to {dpDateTo.SelectedDate:dd MMM yyyy}")) { Foreground = Brushes.Gray, Margin = new Thickness(0, 0, 0, 12) });

            var tbl = new System.Windows.Documents.Table { CellSpacing = 2 };
            string[] headers = { "SN", "DATE", "SALE NO", "CUSTOMER (PHONE)", "REDEEMED AMOUNT" };
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
                    rv["sn"].ToString(), rv["date"].ToString(), rv["sale_no"].ToString(),
                    rv["customer"].ToString(), rv["redeemed_amount"].ToString()
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
            dlg.PrintDocument(pag, "Detailed Usage Loyalty Point Report");
        }

        private void BtnExportCsv_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            var dv = dataGrid.ItemsSource as DataView;
            if (dv == null || dv.Count == 0) { MessageBox.Show("No data to export.", "Info"); return; }

            var sfd = new Microsoft.Win32.SaveFileDialog { Filter = "CSV|*.csv", FileName = "DetailedUsageLoyaltyPointReport.csv" };
            if (sfd.ShowDialog() != true) return;

            using var sw = new StreamWriter(sfd.FileName, false, System.Text.Encoding.UTF8);
            sw.WriteLine("SN,DATE,SALE NO,CUSTOMER (PHONE),REDEEMED AMOUNT");
            foreach (DataRowView rv in dv)
            {
                sw.WriteLine(string.Join(",",
                    rv["sn"], "\"" + rv["date"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["sale_no"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["customer"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["redeemed_amount"].ToString().Replace("\"", "\"\"") + "\""));
            }
            MessageBox.Show("Exported successfully.", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
        }
    }
}
