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
    public partial class InstallmentCollectionReportPage : UserControl
    {
        private readonly MainDashboard _dashboard;
        private readonly DatabaseService _db = new();
        private DataTable _allData = new DataTable();

        public InstallmentCollectionReportPage(MainDashboard dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () => dataGrid.ItemsSource as DataView, "Installment Collection Report");
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
        }

        private void LoadReport(DateTime? dateFrom = null, DateTime? dateTo = null, string outlet = "", long customerId = 0, long outletId = 0)
        {
            try
            {
                using var conn = _db.GetConnection();

                string dateCond(string col) => dateFrom.HasValue && dateTo.HasValue
                    ? $" AND {col} BETWEEN '{dateFrom.Value:yyyy-MM-dd}' AND '{dateTo.Value:yyyy-MM-dd}'"
                    : dateFrom.HasValue ? $" AND {col} >= '{dateFrom.Value:yyyy-MM-dd}'"
                    : dateTo.HasValue ? $" AND {col} <= '{dateTo.Value:yyyy-MM-dd}'"
                    : "";
                string outletCond = outletId > 0 ? $" AND isl.outlet_id = {outletId}" : "";
                string custCond = customerId > 0 ? $" AND isl.customer_id={customerId}" : "";

                var dt = new DataTable();
                dt.Columns.Add("sn", typeof(int));
                dt.Columns.Add("invoice_no", typeof(string));
                dt.Columns.Add("date", typeof(string));
                dt.Columns.Add("customer", typeof(string));
                dt.Columns.Add("product", typeof(string));
                dt.Columns.Add("amount_of_installment", typeof(string));
                dt.Columns.Add("installment_date", typeof(string));
                dt.Columns.Add("paid_amount", typeof(string));
                dt.Columns.Add("paid_date", typeof(string));
                dt.Columns.Add("paid_status", typeof(string));
                dt.Columns.Add("_amtRaw", typeof(double));
                dt.Columns.Add("_paidRaw", typeof(double));

                double tInstallmentAmt = 0, tPaidAmt = 0;
                int sn = 0;

                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $@"SELECT isd.id, isl.reference_no, isl.date, isl.customer_id, isl.item_id,
                        isd.amount, isd.payment_date, isd.paid_amount, isd.paid_date, isd.paid_status
                        FROM installment_sale_details isd
                        INNER JOIN installment_sales isl ON isd.installment_sale_id=isl.id
                        WHERE (isd.del_status IS NULL OR isd.del_status='Live') AND (isl.del_status IS NULL OR isl.del_status='Live')
                        {dateCond("isd.payment_date")}
                        {outletCond}
                        {custCond}
                        ORDER BY isd.payment_date ASC";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        sn++;
                        long custId = r.IsDBNull(3) ? 0 : Convert.ToInt64(r.GetValue(3));
                        long itemId = r.IsDBNull(4) ? 0 : Convert.ToInt64(r.GetValue(4));
                        double amtInstallment = r.IsDBNull(5) ? 0 : Convert.ToDouble(r.GetValue(5));
                        var payDate = r.IsDBNull(6) ? (object)null : r.GetValue(6);
                        double paidAmt = r.IsDBNull(7) ? 0 : Convert.ToDouble(r.GetValue(7));
                        var paidDateVal = r.IsDBNull(8) ? (object)null : r.GetValue(8);
                        string paidStatus = r.IsDBNull(9) ? "Unpaid" : r.GetString(9);
                        string invoiceNo = r.IsDBNull(1) ? "-" : r.GetString(1);
                        var saleDate = r.IsDBNull(2) ? (object)null : r.GetValue(2);

                        string dateStr = saleDate is DateTime d1 ? d1.ToString("dd/MM/yyyy") : saleDate?.ToString() ?? "-";
                        string instDateStr = payDate is DateTime d2 ? d2.ToString("dd/MM/yyyy") : payDate?.ToString() ?? "-";
                        string paidDateStr = paidDateVal is DateTime d3 ? d3.ToString("dd/MM/yyyy") : paidDateVal?.ToString() ?? "-";

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

                        tInstallmentAmt += amtInstallment;
                        tPaidAmt += paidAmt;

                        dt.Rows.Add(sn, invoiceNo, dateStr, custName, product,
                            amtInstallment > 0 ? "₹ " + amtInstallment.ToString("N2") : "-",
                            instDateStr,
                            paidAmt > 0 ? "₹ " + paidAmt.ToString("N2") : "-",
                            paidDateStr, paidStatus,
                            amtInstallment, paidAmt);
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

            LoadReport(from, to, outlet, custId, outId);

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
            doc.Blocks.Add(new Paragraph(new Run("Installment Collection Report")) { FontSize = 16, FontWeight = FontWeights.Bold, Margin = new Thickness(0, 0, 0, 6) });
            if (dpDateFrom.SelectedDate.HasValue)
                doc.Blocks.Add(new Paragraph(new Run($"Period: {dpDateFrom.SelectedDate:dd MMM yyyy} to {dpDateTo.SelectedDate:dd MMM yyyy}")) { Foreground = Brushes.Gray, Margin = new Thickness(0, 0, 0, 12) });

            var tbl = new System.Windows.Documents.Table { CellSpacing = 2 };
            string[] headers = { "SN", "INVOICE", "DATE", "CUSTOMER", "PRODUCT", "INST AMOUNT", "INST DATE", "PAID AMT", "PAID DATE", "STATUS" };
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
                    rv["sn"].ToString(), rv["invoice_no"].ToString(), rv["date"].ToString(),
                    rv["customer"].ToString(), rv["product"].ToString(),
                    rv["amount_of_installment"].ToString(), rv["installment_date"].ToString(),
                    rv["paid_amount"].ToString(), rv["paid_date"].ToString(), rv["paid_status"].ToString()
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
            dlg.PrintDocument(pag, "Installment Collection Report");
        }

        private void BtnExportCsv_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            var dv = dataGrid.ItemsSource as DataView;
            if (dv == null || dv.Count == 0) { MessageBox.Show("No data to export.", "Info"); return; }

            var sfd = new Microsoft.Win32.SaveFileDialog { Filter = "CSV|*.csv", FileName = "InstallmentCollectionReport.csv" };
            if (sfd.ShowDialog() != true) return;

            using var sw = new StreamWriter(sfd.FileName, false, System.Text.Encoding.UTF8);
            sw.WriteLine("SN,INVOICE NO,DATE,CUSTOMER,PRODUCT,AMOUNT OF INSTALLMENT,INSTALLMENT DATE,PAID AMOUNT,PAID DATE,PAID STATUS");
            foreach (DataRowView rv in dv)
            {
                sw.WriteLine(string.Join(",",
                    rv["sn"], "\"" + rv["invoice_no"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["date"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["customer"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["product"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["amount_of_installment"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["installment_date"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["paid_amount"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["paid_date"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["paid_status"].ToString().Replace("\"", "\"\"") + "\""));
            }
            MessageBox.Show("Exported successfully.", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
        }
    }
}
