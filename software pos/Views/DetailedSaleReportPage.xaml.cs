using System;
using System.Data;
using System.IO;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Documents;
using System.Windows.Media;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;
using RashanKiDukan.Models;

namespace RashanKiDukan.Views
{
    public partial class DetailedSaleReportPage : UserControl
    {
        private readonly MainDashboard _dashboard;
        private readonly DatabaseService _db = new();
        private DataTable _allData = new DataTable();

        public DetailedSaleReportPage(MainDashboard dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () => dataGrid.ItemsSource as DataView, "Detailed Sale Report");
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

        private void LoadReport(DateTime? dateFrom = null, DateTime? dateTo = null, string outlet = "", long employeeId = 0, long outletId = 0)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();

                var sql = @"SELECT s.id AS sale_id, s.date_time, s.sub_total, s.total_discount_amount, s.vat,
                                   s.grand_total, s.paid_amount, s.due_amount, s.user_id,
                                   sd.item_id, sd.qty, sd.menu_price_with_discount,
                                   i.name AS item_name
                            FROM sale_details sd
                            INNER JOIN sales s ON sd.sales_id = s.id
                            LEFT JOIN items i ON sd.item_id = i.id
                            WHERE (sd.del_status IS NULL OR sd.del_status='Live')
                              AND (s.del_status IS NULL OR s.del_status='Live')";

                if (dateFrom.HasValue)
                    { sql += " AND s.date_time >= @df"; cmd.Parameters.AddWithValue("@df", dateFrom.Value); }
                if (dateTo.HasValue)
                    { sql += " AND s.date_time <= @dt"; cmd.Parameters.AddWithValue("@dt", dateTo.Value.AddDays(1).AddSeconds(-1)); }
                if (outletId > 0)
                    { sql += " AND s.outlet_id = @out"; cmd.Parameters.AddWithValue("@out", outletId); }
                if (employeeId > 0)
                    { sql += " AND s.user_id = @emp"; cmd.Parameters.AddWithValue("@emp", employeeId); }

                sql += " ORDER BY s.date_time DESC";
                cmd.CommandText = sql;

                var dt = new DataTable();
                using (var r = cmd.ExecuteReader()) dt.Load(r);

                _allData = dt;
                var deduped = new DataTable();
                deduped.Columns.Add("sn", typeof(int));
                deduped.Columns.Add("invoice_no", typeof(string));
                deduped.Columns.Add("date_time", typeof(string));
                deduped.Columns.Add("total_items", typeof(string));
                deduped.Columns.Add("item", typeof(string));
                deduped.Columns.Add("subtotal", typeof(string));
                deduped.Columns.Add("discount", typeof(string));
                deduped.Columns.Add("tax", typeof(string));
                deduped.Columns.Add("grand_total", typeof(string));
                deduped.Columns.Add("paid_amount", typeof(string));
                deduped.Columns.Add("due_amount", typeof(string));
                deduped.Columns.Add("payment_method", typeof(string));

                double tSubtotal = 0, tDiscount = 0, tTax = 0, tGrand = 0, tPaid = 0, tDue = 0;
                int tItems = 0, sn = 0;

                var seenSales = new System.Collections.Generic.HashSet<long>();
                foreach (DataRow src in dt.Rows)
                {
                    long saleId = Convert.ToInt64(src["sale_id"]);
                    bool isFirst = seenSales.Add(saleId);
                    sn++;

                    int itemQty = Convert.ToInt32(src["qty"]);
                    string itemName = src["item_name"]?.ToString() ?? "";
                    string dtStr = src["date_time"] is DateTime d2 ? d2.ToString("dd/MM/yyyy hh:mm tt") : src["date_time"]?.ToString() ?? "";

                    double saleSubtotal = isFirst && src["sub_total"] != DBNull.Value ? Convert.ToDouble(src["sub_total"]) : 0;
                    double saleDiscount = isFirst && src["total_discount_amount"] != DBNull.Value ? Convert.ToDouble(src["total_discount_amount"]) : 0;
                    double saleTax = isFirst && src["vat"] != DBNull.Value ? Convert.ToDouble(src["vat"]) : 0;
                    double saleGrand = isFirst && src["grand_total"] != DBNull.Value ? Convert.ToDouble(src["grand_total"]) : 0;
                    double salePaid = isFirst && src["paid_amount"] != DBNull.Value ? Convert.ToDouble(src["paid_amount"]) : 0;
                    double saleDue = isFirst && src["due_amount"] != DBNull.Value ? Convert.ToDouble(src["due_amount"]) : 0;

                    if (isFirst)
                    {
                        tSubtotal += saleSubtotal;
                        tDiscount += saleDiscount;
                        tTax += saleTax;
                        tGrand += saleGrand;
                        tPaid += salePaid;
                        tDue += saleDue;
                    }
                    tItems += itemQty;

                    deduped.Rows.Add(
                        sn,
                        "INV" + saleId.ToString("D8"),
                        dtStr,
                        itemQty,
                        itemName,
                        saleSubtotal.ToString("N2"),
                        saleDiscount.ToString("N2"),
                        saleTax.ToString("N2"),
                        saleGrand.ToString("N2"),
                        salePaid.ToString("N2"),
                        saleDue.ToString("N2"),
                        ""
                    );
                }

                dataGrid.ItemsSource = null;
                dataGrid.ItemsSource = deduped.DefaultView;

                bool hasData = deduped.Rows.Count > 0;
                emptyState.Visibility = hasData ? Visibility.Collapsed : Visibility.Visible;
                dataGrid.Visibility = hasData ? Visibility.Visible : Visibility.Collapsed;
                totalsBar.Visibility = hasData ? Visibility.Visible : Visibility.Collapsed;
                rowCountBar.Visibility = hasData ? Visibility.Visible : Visibility.Collapsed;

                lblSubtotal.Text = "₹ " + tSubtotal.ToString("N2");
                lblDiscount.Text = "₹ " + tDiscount.ToString("N2");
                lblTax.Text = "₹ " + tTax.ToString("N2");
                lblGrandTotal.Text = "₹ " + tGrand.ToString("N2");
                lblPaid.Text = "₹ " + tPaid.ToString("N2");
                lblDue.Text = "₹ " + tDue.ToString("N2");
                lblRowCount.Text = $"Total Items: {tItems}   |   Total Rows: {deduped.Rows.Count}";
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
            long outletId = outletItem?.Id ?? 0;
            long empId = (cmbEmployee.SelectedItem as ReportComboItem)?.Id ?? 0;

            LoadReport(from, to, outlet, empId, outletId);

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
            doc.Blocks.Add(new Paragraph(new Run("Detailed Sale Report")) { FontSize = 16, FontWeight = FontWeights.Bold, Margin = new Thickness(0, 0, 0, 6) });
            string period = "";
            if (dpDateFrom.SelectedDate.HasValue)
                period = $"Period: {dpDateFrom.SelectedDate:dd MMM yyyy} to {dpDateTo.SelectedDate:dd MMM yyyy}";
            if (!string.IsNullOrEmpty(period))
                doc.Blocks.Add(new Paragraph(new Run(period)) { Foreground = Brushes.Gray, Margin = new Thickness(0, 0, 0, 12) });

            var tbl = new System.Windows.Documents.Table { CellSpacing = 2 };
            string[] headers = { "SN", "INVOICE NO", "DATE TIME", "ITEMS", "ITEM", "SUBTOTAL", "DISCOUNT", "TAX", "GRAND TOTAL", "PAID", "DUE" };
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
                    rv["sn"].ToString(), rv["invoice_no"].ToString(), rv["date_time"].ToString(),
                    rv["total_items"].ToString(), rv["item"].ToString(),
                    "₹ " + Convert.ToDouble(rv["subtotal"]).ToString("N2"),
                    "₹ " + Convert.ToDouble(rv["discount"]).ToString("N2"),
                    "₹ " + Convert.ToDouble(rv["tax"]).ToString("N2"),
                    "₹ " + Convert.ToDouble(rv["grand_total"]).ToString("N2"),
                    "₹ " + Convert.ToDouble(rv["paid_amount"]).ToString("N2"),
                    "₹ " + Convert.ToDouble(rv["due_amount"]).ToString("N2")
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
            dlg.PrintDocument(pag, "Detailed Sale Report");
        }

        private void BtnExportCsv_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            var dv = dataGrid.ItemsSource as DataView;
            if (dv == null || dv.Count == 0) { MessageBox.Show("No data to export.", "Info"); return; }

            var sfd = new Microsoft.Win32.SaveFileDialog { Filter = "CSV|*.csv", FileName = "DetailedSaleReport.csv" };
            if (sfd.ShowDialog() != true) return;

            using var sw = new StreamWriter(sfd.FileName, false, System.Text.Encoding.UTF8);
            sw.WriteLine("SN,INVOICE NO,DATE TIME,TOTAL ITEMS,ITEM,SUBTOTAL,DISCOUNT,TAX,GRAND TOTAL,PAID AMOUNT,DUE AMOUNT,PAYMENT METHOD");
            foreach (DataRowView rv in dv)
            {
                sw.WriteLine(string.Join(",",
                    rv["sn"], rv["invoice_no"], rv["date_time"], rv["total_items"],
                    "\"" + rv["item"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + Convert.ToDouble(rv["subtotal"]).ToString("N2") + "\"",
                    "\"" + Convert.ToDouble(rv["discount"]).ToString("N2") + "\"",
                    "\"" + Convert.ToDouble(rv["tax"]).ToString("N2") + "\"",
                    "\"" + Convert.ToDouble(rv["grand_total"]).ToString("N2") + "\"",
                    "\"" + Convert.ToDouble(rv["paid_amount"]).ToString("N2") + "\"",
                    "\"" + Convert.ToDouble(rv["due_amount"]).ToString("N2") + "\"",
                    rv["payment_method"]));
            }
            MessageBox.Show("Exported successfully.", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
        }
    }
}
