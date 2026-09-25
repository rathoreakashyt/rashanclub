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
    public partial class ProfitLossReportPage : UserControl
    {
        private readonly MainDashboard _dashboard;
        private readonly DatabaseService _db = new();
        private DataTable _allData = new DataTable();

        public ProfitLossReportPage(MainDashboard dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () => dataGrid.ItemsSource as DataView, "Profit / Loss Report");
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

            cmbCostingMethod.Items.Clear();
            cmbCostingMethod.Items.Add(new ReportComboItem(0, "Last Purchase Price"));
            cmbCostingMethod.Items.Add(new ReportComboItem(0, "Last 3 Purchase AVG"));
            cmbCostingMethod.SelectedIndex = 0;
        }

        private void LoadReport(DateTime? dateFrom = null, DateTime? dateTo = null, string outlet = "", string costingMethod = "Last Purchase Price", long outletId = 0)
        {
            try
            {
                using var conn = _db.GetConnection();

                // Build date/outlet filter conditions
                string dateCol(string col) => dateFrom.HasValue && dateTo.HasValue
                    ? $" AND {col} BETWEEN '{dateFrom.Value:yyyy-MM-dd}' AND '{dateTo.Value:yyyy-MM-dd}'"
                    : dateFrom.HasValue ? $" AND {col} >= '{dateFrom.Value:yyyy-MM-dd}'"
                    : dateTo.HasValue ? $" AND {col} <= '{dateTo.Value:yyyy-MM-dd}'"
                    : "";
                string outletCond(string tbl) => outletId > 0 ? $" AND {tbl}.outlet_id = {outletId}" : "";

                bool useAvg = costingMethod.Contains("AVG", StringComparison.OrdinalIgnoreCase);
                string costingCol = useAvg ? "IFNULL(i.last_three_purchase_avg, IFNULL(i.last_purchase_price, 0))" : "IFNULL(i.last_purchase_price, 0)";

                // 1. Total Sales (Paid & Unpaid) (Incl. Tax & Discount)
                double totalSales = 0;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $"SELECT IFNULL(SUM(total_payable),0) FROM sales WHERE (del_status IS NULL OR del_status='Live'){dateCol("sale_date")}{outletCond("sales")}";
                    totalSales = Convert.ToDouble(cmd.ExecuteScalar() ?? 0);
                }

                // 2. Total Cost of Sale
                double totalCostOfSale = 0;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $@"SELECT IFNULL(SUM(sd.qty * {costingCol}),0)
                        FROM sale_details sd
                        INNER JOIN sales s ON sd.sales_id=s.id
                        LEFT JOIN items i ON sd.item_id=i.id
                        WHERE (sd.del_status IS NULL OR sd.del_status='Live') AND (s.del_status IS NULL OR s.del_status='Live'){dateCol("s.sale_date")}{outletCond("sd")}";
                    totalCostOfSale = Convert.ToDouble(cmd.ExecuteScalar() ?? 0);
                }

                // 3. Tax
                double totalTax = 0;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $"SELECT IFNULL(SUM(vat),0) FROM sales WHERE (del_status IS NULL OR del_status='Live'){dateCol("sale_date")}{outletCond("sales")}";
                    totalTax = Convert.ToDouble(cmd.ExecuteScalar() ?? 0);
                }

                // 4. Delivery/Service
                double totalDeliveryService = 0;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $"SELECT IFNULL(SUM(delivery_charge),0) FROM sales WHERE (del_status IS NULL OR del_status='Live'){dateCol("sale_date")}{outletCond("sales")}";
                    totalDeliveryService = Convert.ToDouble(cmd.ExecuteScalar() ?? 0);
                }

                // 5. Discount
                double totalDiscount = 0;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $"SELECT IFNULL(SUM(total_discount_amount),0) FROM sales WHERE (del_status IS NULL OR del_status='Live'){dateCol("sale_date")}{outletCond("sales")}";
                    totalDiscount = Convert.ToDouble(cmd.ExecuteScalar() ?? 0);
                }

                // 6. Installment Sale
                double totalInstallmentSale = 0;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $@"SELECT IFNULL(SUM(
                        IFNULL(total,0) + IFNULL(shipping_other,0) + IFNULL(interest_amount,0) - IFNULL(discount_amount,0)
                    ),0) FROM installment_sales WHERE (del_status IS NULL OR del_status='Live'){dateCol("date")}{outletCond("installment_sales")}";
                    totalInstallmentSale = Convert.ToDouble(cmd.ExecuteScalar() ?? 0);
                }

                // 7. Income
                double totalIncome = 0;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $"SELECT IFNULL(SUM(amount),0) FROM incomes WHERE (del_status IS NULL OR del_status='Live'){dateCol("date")}{outletCond("incomes")}";
                    totalIncome = Convert.ToDouble(cmd.ExecuteScalar() ?? 0);
                }

                // 8. Sale Return
                double totalSaleReturn = 0;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $"SELECT IFNULL(SUM(total_return_amount),0) FROM sale_returns WHERE (del_status IS NULL OR del_status='Live'){dateCol("date")}{outletCond("sale_returns")}";
                    totalSaleReturn = Convert.ToDouble(cmd.ExecuteScalar() ?? 0);
                }

                // 9. Cost Of Sale Return
                double totalCostOfSaleReturn = 0;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $@"SELECT IFNULL(SUM(srd.return_quantity_amount * {costingCol}),0)
                        FROM sale_return_details srd
                        INNER JOIN sale_returns sr ON srd.sale_return_id=sr.id
                        LEFT JOIN items i ON srd.item_id=i.id
                        WHERE (srd.del_status IS NULL OR srd.del_status='Live') AND (sr.del_status IS NULL OR sr.del_status='Live'){dateCol("sr.date")}{outletCond("srd")}";
                    totalCostOfSaleReturn = Convert.ToDouble(cmd.ExecuteScalar() ?? 0);
                }

                // 10. Servicing
                double totalServicing = 0;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $"SELECT IFNULL(SUM(servicing_charge),0) FROM servicings WHERE (del_status IS NULL OR del_status='Live'){dateCol("date")}{outletCond("servicings")}";
                    totalServicing = Convert.ToDouble(cmd.ExecuteScalar() ?? 0);
                }

                // 11. Gross Profit = (1+4+6+7+10) - (2+3+5+8+9)
                double grossProfit = (totalSales + totalDeliveryService + totalInstallmentSale + totalIncome + totalServicing)
                                   - (totalCostOfSale + totalTax + totalDiscount + totalSaleReturn + totalCostOfSaleReturn);

                // 12. Total Salaries
                double totalSalaries = 0;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $"SELECT IFNULL(SUM(total_amount),0) FROM salaries WHERE (del_status IS NULL OR del_status='Live'){dateCol("generated_date")}";
                    totalSalaries = Convert.ToDouble(cmd.ExecuteScalar() ?? 0);
                }
                if (totalSalaries == 0)
                {
                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.CommandText = $"SELECT IFNULL(SUM(si.salary_amount),0) FROM salary_items si INNER JOIN salaries s ON si.salary_id=s.id WHERE (si.del_status IS NULL OR si.del_status='Live'){dateCol("s.generated_date")}";
                        totalSalaries = Convert.ToDouble(cmd.ExecuteScalar() ?? 0);
                    }
                }

                // 13. Expense
                double totalExpense = 0;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $"SELECT IFNULL(SUM(amount),0) FROM expenses WHERE (del_status IS NULL OR del_status='Live'){dateCol("date")}{outletCond("expenses")}";
                    totalExpense = Convert.ToDouble(cmd.ExecuteScalar() ?? 0);
                }

                // 14. Net Profit = (11) - (12+13)
                double netProfit = grossProfit - (totalSalaries + totalExpense);

                // Build rows
                var tbl = new DataTable();
                tbl.Columns.Add("sn", typeof(int));
                tbl.Columns.Add("description", typeof(string));
                tbl.Columns.Add("amount", typeof(string));
                tbl.Columns.Add("_isBold", typeof(bool));
                tbl.Columns.Add("_amountRaw", typeof(double));

                int sn = 0;
                tbl.Rows.Add(++sn, "Total Sales (Paid & Unpaid) (Incl. Tax & Discount)", "₹ " + totalSales.ToString("N2"), false, totalSales);
                tbl.Rows.Add(++sn, "Total Cost of Sale", "₹ " + totalCostOfSale.ToString("N2"), false, totalCostOfSale);
                tbl.Rows.Add(++sn, "Tax", "₹ " + totalTax.ToString("N2"), false, totalTax);
                tbl.Rows.Add(++sn, "Delivery/Service", "₹ " + totalDeliveryService.ToString("N2"), false, totalDeliveryService);
                tbl.Rows.Add(++sn, "Discount", "₹ " + totalDiscount.ToString("N2"), false, totalDiscount);
                tbl.Rows.Add(++sn, "Installment Sale (Incl. (Delivery Charge + Percentage of Interest) - Discount)", "₹ " + totalInstallmentSale.ToString("N2"), false, totalInstallmentSale);
                tbl.Rows.Add(++sn, "Income", "₹ " + totalIncome.ToString("N2"), false, totalIncome);
                tbl.Rows.Add(++sn, "Sale Return", "₹ " + totalSaleReturn.ToString("N2"), false, totalSaleReturn);
                tbl.Rows.Add(++sn, "Cost Of Sale Return", "₹ " + totalCostOfSaleReturn.ToString("N2"), false, totalCostOfSaleReturn);
                tbl.Rows.Add(++sn, "Servicing", "₹ " + totalServicing.ToString("N2"), false, totalServicing);
                tbl.Rows.Add(++sn, "Gross Profit (1+4+6+7+10) - (2+3+5+8+9)", "₹ " + grossProfit.ToString("N2"), true, grossProfit);
                tbl.Rows.Add(++sn, "Total Salaries", "₹ " + totalSalaries.ToString("N2"), false, totalSalaries);
                tbl.Rows.Add(++sn, "Expense", "₹ " + totalExpense.ToString("N2"), false, totalExpense);
                tbl.Rows.Add(++sn, "Net Profit (11) - (12+13)", "₹ " + netProfit.ToString("N2"), true, netProfit);

                _allData = tbl;

                dataGrid.ItemsSource = null;
                dataGrid.ItemsSource = tbl.DefaultView;

                bool hasData = tbl.Rows.Count > 0;
                emptyState.Visibility = hasData ? Visibility.Collapsed : Visibility.Visible;
                dataGrid.Visibility = hasData ? Visibility.Visible : Visibility.Collapsed;
                rowCountBar.Visibility = hasData ? Visibility.Visible : Visibility.Collapsed;
                footerInfoBar.Visibility = hasData ? Visibility.Visible : Visibility.Collapsed;

                // Apply bold styling to rows 11 and 14
                dataGrid.LoadingRow += DataGrid_LoadingRow;

                lblRowCount.Text = $"Costing Method: {costingMethod}";

                // Filter info bar
                filterInfoBar.Visibility = Visibility.Visible;
                filterInfoPanel.Children.Clear();

                filterInfoPanel.Children.Add(new TextBlock
                {
                    Text = "Profit Loss Report",
                    FontSize = 12, FontWeight = FontWeights.SemiBold,
                    FontFamily = new FontFamily("Inter, Segoe UI"),
                    Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0x65, 0x34)),
                    Margin = new Thickness(0, 0, 0, 4)
                });
                filterInfoPanel.Children.Add(new TextBlock
                {
                    Text = "Costing Method: " + costingMethod,
                    FontSize = 11.5, FontFamily = new FontFamily("Inter, Segoe UI"),
                    Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0x65, 0x34)),
                    Margin = new Thickness(0, 0, 0, 2)
                });
                if (dateFrom.HasValue || dateTo.HasValue)
                    filterInfoPanel.Children.Add(new TextBlock
                    {
                        Text = "Generated on: " + DateTime.Now.ToString("dd/MM/yyyy HH:mm:ss"),
                        FontSize = 11.5, FontFamily = new FontFamily("Inter, Segoe UI"),
                        Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0x65, 0x34)),
                        Margin = new Thickness(0, 0, 0, 2)
                    });
                if (_dashboard?.CurrentUser != null)
                    filterInfoPanel.Children.Add(new TextBlock
                    {
                        Text = "Generated by: " + _dashboard.CurrentUser.FullName,
                        FontSize = 11.5, FontFamily = new FontFamily("Inter, Segoe UI"),
                        Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0x65, 0x34))
                    });

                // Footer totals
                footerInfoPanel.Children.Clear();
                var footerWrap = new WrapPanel { HorizontalAlignment = HorizontalAlignment.Right };

                void AddFooterBox(string label, string value, string fgColor)
                {
                    var border = new Border
                    {
                        Background = new SolidColorBrush(Color.FromRgb(0xEE, 0xF2, 0xFF)),
                        BorderBrush = new SolidColorBrush(Color.FromRgb(0xC7, 0xD2, 0xFE)),
                        BorderThickness = new System.Windows.Thickness(1),
                        CornerRadius = new System.Windows.CornerRadius(6),
                        Padding = new System.Windows.Thickness(10, 5, 10, 5),
                        Margin = new System.Windows.Thickness(0, 0, 6, 0)
                    };
                    var sp = new StackPanel { Orientation = Orientation.Horizontal };
                    sp.Children.Add(new TextBlock
                    {
                        Text = label + ": ", FontSize = 11, FontWeight = FontWeights.SemiBold,
                        Foreground = new SolidColorBrush(Color.FromRgb(0x55, 0x65, 0x81)),
                        FontFamily = new FontFamily("Inter, Segoe UI")
                    });
                    sp.Children.Add(new TextBlock
                    {
                        Text = value, FontSize = 12, FontWeight = FontWeights.Bold,
                        Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString(fgColor)),
                        FontFamily = new FontFamily("Inter, Segoe UI")
                    });
                    border.Child = sp;
                    footerWrap.Children.Add(border);
                }

                AddFooterBox("GROSS PROFIT", "₹ " + grossProfit.ToString("N2"), grossProfit >= 0 ? "#16A34A" : "#DC2626");
                AddFooterBox("NET PROFIT", "₹ " + netProfit.ToString("N2"), netProfit >= 0 ? "#16A34A" : "#DC2626");

                footerInfoPanel.Children.Add(footerWrap);
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void DataGrid_LoadingRow(object? sender, DataGridRowEventArgs e)
        {
            if (e.Row.Item is DataRowView row)
            {
                if (row["_isBold"] is bool isBold && isBold)
                {
                    e.Row.FontWeight = FontWeights.Bold;
                    e.Row.Background = new SolidColorBrush(Color.FromRgb(0xEE, 0xF2, 0xFF));
                }
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
            string costing = (cmbCostingMethod.SelectedItem as ReportComboItem)?.Name ?? "Last Purchase Price";

            LoadReport(from, to, outlet, costing, outId);

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
            doc.Blocks.Add(new Paragraph(new Run("Profit Loss Report")) { FontSize = 16, FontWeight = FontWeights.Bold, Margin = new Thickness(0, 0, 0, 6) });
            if (dpDateFrom.SelectedDate.HasValue)
                doc.Blocks.Add(new Paragraph(new Run($"Period: {dpDateFrom.SelectedDate:dd MMM yyyy} to {dpDateTo.SelectedDate:dd MMM yyyy}")) { Foreground = Brushes.Gray, Margin = new Thickness(0, 0, 0, 12) });

            var tbl = new System.Windows.Documents.Table { CellSpacing = 2 };
            string[] headers = { "SN", "DESCRIPTION", "AMOUNT" };
            for (int i = 0; i < headers.Length; i++) tbl.Columns.Add(new TableColumn { Width = new GridLength(1, GridUnitType.Star) });
            var rg = new TableRowGroup();
            var hdr = new TableRow { Background = new SolidColorBrush(Color.FromRgb(0x69, 0x6c, 0xff)) };
            foreach (var h in headers)
                hdr.Cells.Add(new TableCell(new Paragraph(new Run(h))) { Padding = new Thickness(4, 3, 4, 3), Foreground = Brushes.White, FontWeight = FontWeights.SemiBold });
            rg.Rows.Add(hdr);
            bool alt = false;
            foreach (DataRowView rv in dv)
            {
                bool isBold = rv["_isBold"] is bool b && b;
                var tr = new TableRow
                {
                    Background = isBold ? new SolidColorBrush(Color.FromRgb(0xEE, 0xF2, 0xFF)) : (alt ? new SolidColorBrush(Color.FromRgb(0xFA, 0xFA, 0xFF)) : Brushes.White)
                };
                string[] vals = { rv["sn"].ToString(), rv["description"].ToString(), rv["amount"].ToString() };
                foreach (var v in vals)
                {
                    var cell = new TableCell(new Paragraph(new Run(v))) { Padding = new Thickness(4, 2, 4, 2) };
                    if (isBold) cell.FontWeight = FontWeights.Bold;
                    tr.Cells.Add(cell);
                }
                rg.Rows.Add(tr);
                alt = !alt;
            }
            tbl.RowGroups.Add(rg);
            doc.Blocks.Add(tbl);
            var pag = ((IDocumentPaginatorSource)doc).DocumentPaginator;
            pag.PageSize = new Size(dlg.PrintableAreaWidth, dlg.PrintableAreaHeight);
            dlg.PrintDocument(pag, "Profit Loss Report");
        }

        private void BtnExportCsv_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            var dv = dataGrid.ItemsSource as DataView;
            if (dv == null || dv.Count == 0) { MessageBox.Show("No data to export.", "Info"); return; }

            var sfd = new Microsoft.Win32.SaveFileDialog { Filter = "CSV|*.csv", FileName = "ProfitLossReport.csv" };
            if (sfd.ShowDialog() != true) return;

            using var sw = new StreamWriter(sfd.FileName, false, System.Text.Encoding.UTF8);
            sw.WriteLine("SN,DESCRIPTION,AMOUNT");
            foreach (DataRowView rv in dv)
            {
                sw.WriteLine(string.Join(",",
                    rv["sn"],
                    "\"" + rv["description"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["amount"].ToString().Replace("\"", "\"\"") + "\""));
            }
            MessageBox.Show("Exported successfully.", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
        }
    }
}
