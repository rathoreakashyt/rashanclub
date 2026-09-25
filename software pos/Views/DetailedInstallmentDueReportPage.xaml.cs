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
    public partial class DetailedInstallmentDueReportPage : UserControl
    {
        private readonly MainDashboard _dashboard;
        private readonly DatabaseService _db = new();
        private DataTable _allData = new DataTable();

        public DetailedInstallmentDueReportPage(MainDashboard dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () => dataGrid.ItemsSource as DataView, "Detailed Installment Due Report");
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

            var statusList = new System.Collections.Generic.List<ReportComboItem>
            {
                new(0, "All Status"),
                new(1, "Unpaid"),
                new(2, "Partial"),
                new(3, "Paid")
            };
            cmbStatus.ItemsSource = statusList;
            cmbStatus.SelectedIndex = 0;
        }

        private void LoadReport(DateTime? dateFrom = null, DateTime? dateTo = null, string outlet = "",
            long customerId = 0, string status = "", long outletId = 0)
        {
            try
            {
                using var conn = _db.GetConnection();

                string dateCond = dateFrom.HasValue && dateTo.HasValue
                    ? $" AND isl.date BETWEEN '{dateFrom.Value:yyyy-MM-dd}' AND '{dateTo.Value:yyyy-MM-dd}'"
                    : dateFrom.HasValue ? $" AND isl.date >= '{dateFrom.Value:yyyy-MM-dd}'"
                    : dateTo.HasValue ? $" AND isl.date <= '{dateTo.Value:yyyy-MM-dd}'"
                    : "";
                string outletCond = outletId > 0 ? $" AND isl.outlet_id = {outletId}" : "";
                string custCond = customerId > 0 ? $" AND isl.customer_id={customerId}" : "";
                string statusCond = !string.IsNullOrEmpty(status) && status != "All Status"
                    ? $" AND EXISTS (SELECT 1 FROM installment_sale_details isd WHERE isd.installment_sale_id=isl.Id AND isd.paid_status='{status}')"
                    : "";

                var dt = new DataTable();
                dt.Columns.Add("sn", typeof(int));
                dt.Columns.Add("invoice_no", typeof(string));
                dt.Columns.Add("sale_date", typeof(string));
                dt.Columns.Add("customer", typeof(string));
                dt.Columns.Add("product", typeof(string));
                dt.Columns.Add("price", typeof(string));
                dt.Columns.Add("percentage_of_interest", typeof(string));
                dt.Columns.Add("amount_of_interest", typeof(string));
                dt.Columns.Add("total", typeof(string));
                dt.Columns.Add("down_payment", typeof(string));
                dt.Columns.Add("total_installment", typeof(string));
                dt.Columns.Add("total_paid", typeof(string));
                dt.Columns.Add("due_amount", typeof(string));
                dt.Columns.Add("status", typeof(string));
                dt.Columns.Add("_totalRaw", typeof(double));
                dt.Columns.Add("_dueRaw", typeof(double));

                double tTotal = 0, tDownPayment = 0, tTotalInstallment = 0, tTotalPaid = 0, tDue = 0;
                int sn = 0;

                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $@"SELECT isl.reference_no, isl.date, isl.customer_id, isl.item_id,
                        IFNULL(isl.price,0), IFNULL(isl.percentage_of_interest,0), IFNULL(isl.interest_amount,0),
                        IFNULL(isl.total,0), IFNULL(isl.down_payment,0),
                        IFNULL((SELECT SUM(isd2.amount) FROM installment_sale_details isd2 WHERE isd2.installment_sale_id=isl.Id AND (isd2.del_status IS NULL OR isd2.del_status='Live')),0) AS total_installment,
                        IFNULL((SELECT SUM(isd2.paid_amount) FROM installment_sale_details isd2 WHERE isd2.installment_sale_id=isl.Id AND (isd2.del_status IS NULL OR isd2.del_status='Live')),0) AS total_paid,
                        IFNULL(isl.due_amount,0)
                        FROM installment_sales isl
                        WHERE (isl.del_status IS NULL OR isl.del_status='Live')
                        {dateCond} {outletCond} {custCond} {statusCond}
                        ORDER BY isl.date DESC";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        sn++;
                        long custId = r.IsDBNull(2) ? 0 : Convert.ToInt64(r.GetValue(2));
                        long itemId = r.IsDBNull(3) ? 0 : Convert.ToInt64(r.GetValue(3));
                        double price = r.IsDBNull(4) ? 0 : Convert.ToDouble(r.GetValue(4));
                        double pctInterest = r.IsDBNull(5) ? 0 : Convert.ToDouble(r.GetValue(5));
                        double amtInterest = r.IsDBNull(6) ? 0 : Convert.ToDouble(r.GetValue(6));
                        double total = r.IsDBNull(7) ? 0 : Convert.ToDouble(r.GetValue(7));
                        double downPayment = r.IsDBNull(8) ? 0 : Convert.ToDouble(r.GetValue(8));
                        double totalInstallment = r.IsDBNull(9) ? 0 : Convert.ToDouble(r.GetValue(9));
                        double totalPaid = r.IsDBNull(10) ? 0 : Convert.ToDouble(r.GetValue(10));
                        double dueAmount = r.IsDBNull(11) ? 0 : Convert.ToDouble(r.GetValue(11));
                        var saleDate = r.IsDBNull(1) ? (object)null : r.GetValue(1);
                        string invoiceNo = r.IsDBNull(0) ? "-" : r.GetString(0);

                        string dateStr = saleDate is DateTime d ? d.ToString("dd/MM/yyyy") : saleDate?.ToString() ?? "-";

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

                        // Product name
                        string product = "-";
                        if (itemId > 0)
                        {
                            using var ic = conn.CreateCommand();
                            ic.CommandText = "SELECT name FROM items WHERE id=@iid AND (del_status IS NULL OR del_status='Live')";
                            ic.Parameters.AddWithValue("@iid", itemId);
                            var iv = ic.ExecuteScalar();
                            if (iv != null) product = iv.ToString() ?? "-";
                        }

                        // Determine status
                        string rowStatus = "Paid";
                        if (dueAmount > 0 && totalPaid < totalInstallment)
                            rowStatus = "Unpaid";
                        else if (dueAmount > 0)
                            rowStatus = "Partial";

                        tTotal += total;
                        tDownPayment += downPayment;
                        tTotalInstallment += totalInstallment;
                        tTotalPaid += totalPaid;
                        tDue += dueAmount;

                        dt.Rows.Add(sn, invoiceNo, dateStr, custName, product,
                            "₹ " + price.ToString("N2"),
                            pctInterest.ToString("N1") + "%",
                            "₹ " + amtInterest.ToString("N2"),
                            "₹ " + total.ToString("N2"),
                            "₹ " + downPayment.ToString("N2"),
                            "₹ " + totalInstallment.ToString("N2"),
                            "₹ " + totalPaid.ToString("N2"),
                            "₹ " + dueAmount.ToString("N2"),
                            rowStatus,
                            total, dueAmount);
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
            var outletItem = cmbOutlet.SelectedItem as ReportComboItem;
            string outlet = outletItem?.Name ?? "";
            long outId = outletItem?.Id ?? 0;
            long custId = (cmbCustomer.SelectedItem as ReportComboItem)?.Id ?? 0;
            string status = (cmbStatus.SelectedItem as ReportComboItem)?.Name ?? "";

            LoadReport(from, to, outlet, custId, status, outId);

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

            if (custId > 0)
                filterInfoPanel.Children.Add(new TextBlock
                {
                    Text = "Customer: " + (cmbCustomer.SelectedItem as ReportComboItem)?.Name,
                    FontSize = 11.5, FontFamily = new FontFamily("Inter, Segoe UI"),
                    Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0x65, 0x34)),
                    Margin = new Thickness(0, 0, 16, 0)
                });

            if (!string.IsNullOrEmpty(status) && status != "All Status")
                filterInfoPanel.Children.Add(new TextBlock
                {
                    Text = "Status: " + status,
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

            var doc = new FlowDocument { FontFamily = new FontFamily("Segoe UI"), FontSize = 10.5, PagePadding = new Thickness(30) };
            doc.Blocks.Add(new Paragraph(new Run("Detailed Installment Due Report")) { FontSize = 16, FontWeight = FontWeights.Bold, Margin = new Thickness(0, 0, 0, 6) });
            if (dpDateFrom.SelectedDate.HasValue)
                doc.Blocks.Add(new Paragraph(new Run($"Period: {dpDateFrom.SelectedDate:dd MMM yyyy} to {dpDateTo.SelectedDate:dd MMM yyyy}")) { Foreground = Brushes.Gray, Margin = new Thickness(0, 0, 0, 12) });

            var tbl = new System.Windows.Documents.Table { CellSpacing = 2 };
            string[] headers = { "SN", "INVOICE", "DATE", "CUSTOMER", "PRODUCT", "PRICE", "INT%", "INT AMT", "TOTAL", "DOWN PMT", "INST PMT", "PAID", "DUE", "STATUS" };
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
                    rv["sn"].ToString(), rv["invoice_no"].ToString(), rv["sale_date"].ToString(),
                    rv["customer"].ToString(), rv["product"].ToString(),
                    rv["price"].ToString(), rv["percentage_of_interest"].ToString(),
                    rv["amount_of_interest"].ToString(), rv["total"].ToString(),
                    rv["down_payment"].ToString(), rv["total_installment"].ToString(),
                    rv["total_paid"].ToString(), rv["due_amount"].ToString(), rv["status"].ToString()
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
            dlg.PrintDocument(pag, "Detailed Installment Due Report");
        }

        private void BtnExportCsv_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            var dv = dataGrid.ItemsSource as DataView;
            if (dv == null || dv.Count == 0) { MessageBox.Show("No data to export.", "Info"); return; }

            var sfd = new Microsoft.Win32.SaveFileDialog { Filter = "CSV|*.csv", FileName = "DetailedInstallmentDueReport.csv" };
            if (sfd.ShowDialog() != true) return;

            using var sw = new StreamWriter(sfd.FileName, false, System.Text.Encoding.UTF8);
            sw.WriteLine("SN,INVOICE NO,SALE DATE,CUSTOMER,PRODUCT,PRICE,PERCENTAGE OF INTEREST,AMOUNT OF INTEREST,TOTAL,DOWN PAYMENT,TOTAL INSTALLMENT,TOTAL PAID,DUE AMOUNT,STATUS");
            foreach (DataRowView rv in dv)
            {
                sw.WriteLine(string.Join(",",
                    rv["sn"], "\"" + rv["invoice_no"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["sale_date"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["customer"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["product"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["price"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["percentage_of_interest"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["amount_of_interest"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["total"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["down_payment"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["total_installment"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["total_paid"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["due_amount"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["status"].ToString().Replace("\"", "\"\"") + "\""));
            }
            MessageBox.Show("Exported successfully.", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
        }
    }
}
