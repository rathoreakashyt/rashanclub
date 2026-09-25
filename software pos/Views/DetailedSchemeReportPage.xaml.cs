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
    public partial class DetailedSchemeReportPage : UserControl
    {
        private readonly MainDashboard _dashboard;
        private readonly DatabaseService _db = new();
        private DataTable _allData = new DataTable();

        public DetailedSchemeReportPage(MainDashboard dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () => dataGrid.ItemsSource as DataView, "Detailed Scheme Report");
            _dashboard = dashboard;
            dpDateFrom.SelectedDate = new DateTime(DateTime.Today.Year, DateTime.Today.Month, 1);
            dpDateTo.SelectedDate = DateTime.Today;
            LoadFilters();
            LoadReport();
        }

        private void LoadFilters()
        {
            var typeList = new System.Collections.Generic.List<ReportComboItem>
            {
                new(0, "All Types"),
                new(1, "Discount"),
                new(2, "Coupon Discount"),
                new(3, "Free Item")
            };
            cmbType.ItemsSource = typeList;
            cmbType.SelectedIndex = 0;
        }

        private void LoadReport(DateTime? dateFrom = null, DateTime? dateTo = null, string type = "")
        {
            try
            {
                using var conn = _db.GetConnection();

                string dateCondFrom = dateFrom.HasValue ? $" AND p.start_date >= '{dateFrom.Value:yyyy-MM-dd}'" : "";
                string dateCondTo = dateTo.HasValue ? $" AND p.end_date <= '{dateTo.Value:yyyy-MM-dd}'" : "";
                string typeCond = !string.IsNullOrEmpty(type) && type != "All Types"
                    ? $" AND p.type='{type}'"
                    : "";

                var dt = new DataTable();
                dt.Columns.Add("sn", typeof(int));
                dt.Columns.Add("title", typeof(string));
                dt.Columns.Add("type", typeof(string));
                dt.Columns.Add("scheme_basis", typeof(string));
                dt.Columns.Add("start_date", typeof(string));
                dt.Columns.Add("end_date", typeof(string));
                dt.Columns.Add("start_time", typeof(string));
                dt.Columns.Add("end_time", typeof(string));
                dt.Columns.Add("min_purchase", typeof(string));
                dt.Columns.Add("max_discount", typeof(string));
                dt.Columns.Add("discount", typeof(string));
                dt.Columns.Add("status", typeof(string));

                int sn = 0;

                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $@"SELECT p.title, p.type, p.scheme_basis,
                        p.start_date, p.end_date, p.start_time, p.end_time,
                        IFNULL(p.min_purchase_amount,0), IFNULL(p.max_discount_amount,0),
                        IFNULL(p.discount_value,0), p.status
                        FROM promotions p
                        WHERE (p.del_status IS NULL OR p.del_status='Live')
                        {dateCondFrom} {dateCondTo} {typeCond}
                        ORDER BY p.id DESC";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        sn++;
                        string title = r.IsDBNull(0) ? "-" : r.GetString(0);
                        string typeVal = r.IsDBNull(1) ? "-" : r.GetString(1);
                        string schemeBasis = r.IsDBNull(2) ? "Item" : r.GetString(2);
                        var startDate = r.IsDBNull(3) ? (object)null : r.GetValue(3);
                        var endDate = r.IsDBNull(4) ? (object)null : r.GetValue(4);
                        string startTime = r.IsDBNull(5) ? "-" : r.GetString(5);
                        string endTime = r.IsDBNull(6) ? "-" : r.GetString(6);
                        double minPurchase = r.IsDBNull(7) ? 0 : Convert.ToDouble(r.GetValue(7));
                        double maxDiscount = r.IsDBNull(8) ? 0 : Convert.ToDouble(r.GetValue(8));
                        double discount = r.IsDBNull(9) ? 0 : Convert.ToDouble(r.GetValue(9));
                        string status = r.IsDBNull(10) ? "-" : r.GetString(10);

                        string startDateStr = startDate is DateTime d1 ? d1.ToString("dd/MM/yyyy") : startDate?.ToString() ?? "-";
                        string endDateStr = endDate is DateTime d2 ? d2.ToString("dd/MM/yyyy") : endDate?.ToString() ?? "-";

                        string typeLabel = typeVal switch
                        {
                            "1" => "Discount",
                            "2" => "Coupon Discount",
                            "3" => "Free Item",
                            _ => typeVal
                        };

                        string statusLabel = status switch
                        {
                            "1" => "Active",
                            "0" => "Inactive",
                            _ => status
                        };

                        dt.Rows.Add(sn, title, typeLabel, schemeBasis,
                            startDateStr, endDateStr,
                            string.IsNullOrEmpty(startTime) || startTime == "00:00:00" ? "-" : startTime,
                            string.IsNullOrEmpty(endTime) || endTime == "00:00:00" ? "-" : endTime,
                            "₹ " + minPurchase.ToString("N2"),
                            "₹ " + maxDiscount.ToString("N2"),
                            discount > 0 ? discount.ToString() + (typeVal == "1" || typeVal == "Discount" ? "%" : "") : "-",
                            statusLabel);
                    }
                }

                _allData = dt;
                dataGrid.ItemsSource = null;
                dataGrid.ItemsSource = dt.DefaultView;

                bool hasData = dt.Rows.Count > 0;
                emptyState.Visibility = hasData ? Visibility.Collapsed : Visibility.Visible;
                dataGrid.Visibility = hasData ? Visibility.Visible : Visibility.Collapsed;
                rowCountBar.Visibility = hasData ? Visibility.Visible : Visibility.Collapsed;

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
            string type = (cmbType.SelectedItem as ReportComboItem)?.Name ?? "";

            LoadReport(from, to, type);

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
            if (dv == null || dv.Count == 0) return;

            var doc = new FlowDocument { FontFamily = new FontFamily("Segoe UI"), FontSize = 10.5, PagePadding = new Thickness(30) };
            doc.Blocks.Add(new Paragraph(new Run("Detailed Scheme Report")) { FontSize = 16, FontWeight = FontWeights.Bold, Margin = new Thickness(0, 0, 0, 6) });
            if (dpDateFrom.SelectedDate.HasValue)
                doc.Blocks.Add(new Paragraph(new Run($"Period: {dpDateFrom.SelectedDate:dd MMM yyyy} to {dpDateTo.SelectedDate:dd MMM yyyy}")) { Foreground = Brushes.Gray, Margin = new Thickness(0, 0, 0, 12) });

            var tbl = new System.Windows.Documents.Table { CellSpacing = 2 };
            string[] headers = { "SN", "TITLE", "TYPE", "BASIS", "START", "END", "TIME", "TIME", "MIN PUR", "MAX DISC", "DISC", "STATUS" };
            for (int i = 0; i < headers.Length; i++) tbl.Columns.Add(new TableColumn { Width = new GridLength(1, GridUnitType.Star) });
            var rg = new TableRowGroup();
            var hdr = new TableRow { Background = new SolidColorBrush(Color.FromRgb(0x69, 0x6c, 0xff)) };
            foreach (var h in headers)
                hdr.Cells.Add(new TableCell(new Paragraph(new Run(h))) { Padding = new Thickness(3, 2, 3, 2), Foreground = Brushes.White, FontWeight = FontWeights.SemiBold, FontSize = 9.5 });
            rg.Rows.Add(hdr);
            bool alt = false;
            foreach (DataRowView rv in dv)
            {
                var tr = new TableRow { Background = alt ? new SolidColorBrush(Color.FromRgb(0xFA, 0xFA, 0xFF)) : Brushes.White };
                string[] vals = {
                    rv["sn"].ToString(), rv["title"].ToString(), rv["type"].ToString(),
                    rv["scheme_basis"].ToString(), rv["start_date"].ToString(),
                    rv["end_date"].ToString(), rv["start_time"].ToString(),
                    rv["end_time"].ToString(), rv["min_purchase"].ToString(),
                    rv["max_discount"].ToString(), rv["discount"].ToString(),
                    rv["status"].ToString()
                };
                foreach (var v in vals)
                    tr.Cells.Add(new TableCell(new Paragraph(new Run(v))) { Padding = new Thickness(3, 2, 3, 2), FontSize = 9.5 });
                rg.Rows.Add(tr);
                alt = !alt;
            }
            tbl.RowGroups.Add(rg);
            doc.Blocks.Add(tbl);
            var pag = ((IDocumentPaginatorSource)doc).DocumentPaginator;
            pag.PageSize = new Size(dlg.PrintableAreaWidth, dlg.PrintableAreaHeight);
            dlg.PrintDocument(pag, "Detailed Scheme Report");
        }

        private void BtnExportCsv_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            var dv = dataGrid.ItemsSource as DataView;
            if (dv == null || dv.Count == 0) { MessageBox.Show("No data to export.", "Info"); return; }

            var sfd = new Microsoft.Win32.SaveFileDialog { Filter = "CSV|*.csv", FileName = "DetailedSchemeReport.csv" };
            if (sfd.ShowDialog() != true) return;

            using var sw = new StreamWriter(sfd.FileName);
            sw.WriteLine("SN,TITLE,TYPE,SCHEME BASIS,START DATE,END DATE,START TIME,END TIME,MIN PURCHASE,MAX DISCOUNT,DISCOUNT,STATUS");
            foreach (DataRowView rv in dv)
            {
                sw.WriteLine(string.Join(",",
                    rv["sn"],
                    "\"" + rv["title"].ToString().Replace("\"", "\"\"") + "\"",
                    rv["type"], rv["scheme_basis"],
                    rv["start_date"], rv["end_date"],
                    rv["start_time"], rv["end_time"],
                    rv["min_purchase"], rv["max_discount"],
                    rv["discount"], rv["status"]));
            }
            MessageBox.Show("Exported successfully.", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
        }
    }
}
