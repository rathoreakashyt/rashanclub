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
    public partial class ZReportPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();

        public ZReportPage(MainDashboard? dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () =>
            {
                var t = new DataTable();
                t.Columns.Add("Section");
                t.Columns.Add("Label");
                t.Columns.Add("Value 1");
                t.Columns.Add("Value 2");
                void AddPnl(string section, StackPanel pnl)
                {
                    foreach (UIElement el in pnl.Children)
                    {
                        if (el is Border b && b.Child is Grid g && g.Children.Count >= 2)
                        {
                            t.Rows.Add(section,
                                (g.Children[0] as TextBlock)?.Text ?? "",
                                (g.Children[1] as TextBlock)?.Text ?? "",
                                g.Children.Count >= 3 ? (g.Children[2] as TextBlock)?.Text ?? "" : "");
                        }
                    }
                }
                AddPnl("Sales and Taxes", pnlSalesTaxes);
                AddPnl("Payment Methods", pnlPaymentMethods);
                AddPnl("Item Wise Sales", pnlItemWise);
                AddPnl("Expense", pnlExpense);
                AddPnl("Supplier Payment", pnlSupplierPayment);
                AddPnl("Customer Receives", pnlCustomerReceives);
                AddPnl("Purchase Paid", pnlPurchasePaid);
                AddPnl("In Hand Summary", pnlInHandSummary);
                return t.DefaultView;
            }, "Z Report");
            _dashboard = dashboard;
            dpDate.SelectedDate = DateTime.Today;
            LoadOutlets();
        }

        private void LoadOutlets()
        {
            var list = new List<ReportComboItem>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT Id, outlet_name FROM outlets WHERE (del_status IS NULL OR del_status='Live') ORDER BY Id";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add(new ReportComboItem(r.IsDBNull(0) ? 0 : r.GetInt64(0), r.IsDBNull(1) ? "" : r.GetString(1)));
            }
            catch { }
            cmbOutlet.ItemsSource = list;
            if (list.Count > 0) cmbOutlet.SelectedIndex = 0;
        }

        // ── Event handlers ────────────────────────────────────────────────
        private void BtnBack_Click(object sender, RoutedEventArgs e)
            => _dashboard?.ShowPage(new ReportsIndexPage(_dashboard));

        private void BtnToggleFilter_Click(object sender, RoutedEventArgs e)
            => filterSection.Visibility = filterSection.Visibility == Visibility.Collapsed
               ? Visibility.Visible : Visibility.Collapsed;

        private void BtnApplyFilter_Click(object sender, RoutedEventArgs e)
        {
            if (dpDate.SelectedDate == null) { MessageBox.Show("Please select a date.", "Validation"); return; }
            if (cmbOutlet.SelectedItem == null) { MessageBox.Show("Please select an outlet.", "Validation"); return; }
            filterSection.Visibility = Visibility.Collapsed;
            LoadZReport();
        }

        private void BtnCancelFilter_Click(object sender, RoutedEventArgs e)
            => filterSection.Visibility = Visibility.Collapsed;

        private void BtnExport_Click(object sender, RoutedEventArgs e)
            => exportPopup.IsOpen = !exportPopup.IsOpen;

        // ── Load Z Report sections ────────────────────────────────────────
        private void LoadZReport()
        {
            string date = dpDate.SelectedDate!.Value.ToString("yyyy-MM-dd");
            var outlet  = (ReportComboItem)cmbOutlet.SelectedItem!;
            long outletId = outlet.Id;

            emptyState.Visibility = Visibility.Collapsed;
            zContent.Visibility   = Visibility.Visible;

            ShowFilterInfo(date, outlet.Name);

            try
            {
                using var conn = _db.GetConnection();

                // ── 1. Sales and Taxes Summary ────────────────────────────
                pnlSalesTaxes.Children.Clear();
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT COUNT(*) AS sales_count,"
                        + " ROUND(IFNULL(SUM(sub_total),0),2) AS total_items,"
                        + " ROUND(IFNULL(SUM(total_discount_amount),0),2) AS total_discount,"
                        + " ROUND(IFNULL(SUM(vat),0),2) AS total_tax,"
                        + " ROUND(IFNULL(SUM(delivery_charge),0),2) AS delivery_charge,"
                        + " ROUND(IFNULL(SUM(total_payable),0),2) AS total_payable,"
                        + " ROUND(IFNULL(SUM(paid_amount),0),2) AS total_paid,"
                        + " ROUND(IFNULL(SUM(due_amount),0),2) AS total_due"
                        + " FROM sales WHERE sale_date=@date"
                        + (outletId > 0 ? $" AND outlet_id={outletId}" : "")
                        + " AND (del_status IS NULL OR del_status='Live')";
                    cmd.Parameters.AddWithValue("@date", date);
                    using var r = cmd.ExecuteReader();
                    if (r.Read())
                    {
                        AddSectionRow(pnlSalesTaxes, "Total Transactions", r["sales_count"]?.ToString() ?? "0", false);
                        AddSectionRow(pnlSalesTaxes, "Item Sales", Fmt(r["total_items"]));
                        AddSectionRow(pnlSalesTaxes, "Discount",   Fmt(r["total_discount"]));
                        AddSectionRow(pnlSalesTaxes, "Tax (VAT)",  Fmt(r["total_tax"]));
                        AddSectionRow(pnlSalesTaxes, "Delivery Charge", Fmt(r["delivery_charge"]));
                        AddSectionRow(pnlSalesTaxes, "Total Payable",   Fmt(r["total_payable"]), isBold: true);
                        AddSectionRow(pnlSalesTaxes, "Total Paid",      Fmt(r["total_paid"]));
                        AddSectionRow(pnlSalesTaxes, "Total Due",       Fmt(r["total_due"]));
                    }
                }

                // ── 2. Payment Method Breakdown ───────────────────────────
                pnlPaymentMethods.Children.Clear();
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT pm.name AS method, ROUND(IFNULL(SUM(sp.amount),0),2) AS amount"
                        + " FROM sale_payments sp"
                        + " LEFT JOIN payment_methods pm ON pm.Id=sp.payment_id"
                        + " LEFT JOIN sales s ON s.Id=sp.sale_id"
                        + " WHERE s.sale_date=@date"
                        + (outletId > 0 ? $" AND s.outlet_id={outletId}" : "")
                        + " AND (sp.del_status IS NULL OR sp.del_status='Live')"
                        + " AND (s.del_status IS NULL OR s.del_status='Live')"
                        + " GROUP BY pm.name ORDER BY amount DESC";
                    cmd.Parameters.AddWithValue("@date", date);
                    using var r = cmd.ExecuteReader();
                    bool any = false;
                    while (r.Read()) { AddSectionRow(pnlPaymentMethods, r["method"]?.ToString() ?? "-", Fmt(r["amount"])); any = true; }
                    if (!any) AddSectionRow(pnlPaymentMethods, "No payment records", "—", isMoney: false);
                }

                // ── 3. Item Wise Sales ────────────────────────────────────
                pnlItemWise.Children.Clear();
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT i.name AS item,"
                        + " ROUND(IFNULL(SUM(sd.qty),0),2) AS total_qty,"
                        + " ROUND(IFNULL(SUM(sd.qty*IFNULL(sd.menu_price_with_discount,sd.menu_unit_price)),0),2) AS total_amount"
                        + " FROM sale_details sd"
                        + " LEFT JOIN items i ON i.Id=sd.item_id"
                        + " LEFT JOIN sales s ON s.Id=sd.sales_id"
                        + " WHERE s.sale_date=@date"
                        + (outletId > 0 ? $" AND s.outlet_id={outletId}" : "")
                        + " AND (sd.del_status IS NULL OR sd.del_status='Live') AND (s.del_status IS NULL OR s.del_status='Live')"
                        + " GROUP BY i.name ORDER BY total_amount DESC LIMIT 50";
                    cmd.Parameters.AddWithValue("@date", date);
                    using var r = cmd.ExecuteReader();
                    // Header
                    AddSectionHeader(pnlItemWise, "Item", "Qty", "Amount");
                    bool any = false;
                    while (r.Read())
                    {
                        AddSectionRow3(pnlItemWise,
                            r["item"]?.ToString() ?? "-",
                            r["total_qty"]?.ToString() ?? "0",
                            Fmt(r["total_amount"]));
                        any = true;
                    }
                    if (!any) AddSectionRow(pnlItemWise, "No item sales", "—", isMoney: false);
                }

                // ── 4. Expense ────────────────────────────────────────────
                pnlExpense.Children.Clear();
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT ec.name AS category, ROUND(IFNULL(SUM(e.amount),0),2) AS amount"
                        + " FROM expenses e LEFT JOIN expense_categories ec ON ec.Id=e.category_id"
                        + " WHERE e.date=@date"
                        + (outletId > 0 ? $" AND e.outlet_id={outletId}" : "")
                        + " AND (e.del_status IS NULL OR e.del_status='Live')"
                        + " GROUP BY ec.name ORDER BY amount DESC";
                    cmd.Parameters.AddWithValue("@date", date);
                    using var r = cmd.ExecuteReader();
                    double total = 0;
                    bool any = false;
                    while (r.Read())
                    {
                        double amt = ToDouble(r["amount"]);
                        AddSectionRow(pnlExpense, r["category"]?.ToString() ?? "-", Fmt(r["amount"]));
                        total += amt; any = true;
                    }
                    if (any) AddSectionRow(pnlExpense, "Total Expense", Fmt(total), isBold: true);
                    else AddSectionRow(pnlExpense, "No expenses", "—", isMoney: false);
                }

                // ── 5. Supplier Payment ───────────────────────────────────
                pnlSupplierPayment.Children.Clear();
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT s.name AS supplier, ROUND(IFNULL(SUM(sp.amount),0),2) AS amount"
                        + " FROM supplier_payments sp LEFT JOIN suppliers s ON s.Id=sp.supplier_id"
                        + " WHERE sp.date=@date AND (sp.del_status IS NULL OR sp.del_status='Live')"
                        + " GROUP BY s.name ORDER BY amount DESC";
                    cmd.Parameters.AddWithValue("@date", date);
                    using var r = cmd.ExecuteReader();
                    bool any = false;
                    while (r.Read()) { AddSectionRow(pnlSupplierPayment, r["supplier"]?.ToString() ?? "-", Fmt(r["amount"])); any = true; }
                    if (!any) AddSectionRow(pnlSupplierPayment, "No supplier payments", "—", isMoney: false);
                }

                // ── 6. Customer Due Receives ──────────────────────────────
                pnlCustomerReceives.Children.Clear();
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT c.name AS customer, ROUND(IFNULL(SUM(cr.amount),0),2) AS amount"
                        + " FROM customer_receives cr LEFT JOIN customers c ON c.Id=cr.customer_id"
                        + " WHERE cr.date=@date AND (cr.del_status IS NULL OR cr.del_status='Live')"
                        + " GROUP BY c.name ORDER BY amount DESC";
                    cmd.Parameters.AddWithValue("@date", date);
                    using var r = cmd.ExecuteReader();
                    bool any = false;
                    while (r.Read()) { AddSectionRow(pnlCustomerReceives, r["customer"]?.ToString() ?? "-", Fmt(r["amount"])); any = true; }
                    if (!any) AddSectionRow(pnlCustomerReceives, "No customer receives", "—", isMoney: false);
                }

                // ── 7. Purchase Paid ──────────────────────────────────────
                pnlPurchasePaid.Children.Clear();
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT s.name AS supplier, ROUND(IFNULL(SUM(p.paid),0),2) AS paid"
                        + " FROM purchases p LEFT JOIN suppliers s ON s.Id=p.supplier_id"
                        + " WHERE p.date=@date AND (p.del_status IS NULL OR p.del_status='Live')"
                        + " GROUP BY s.name ORDER BY paid DESC";
                    cmd.Parameters.AddWithValue("@date", date);
                    using var r = cmd.ExecuteReader();
                    bool any = false;
                    while (r.Read()) { AddSectionRow(pnlPurchasePaid, r["supplier"]?.ToString() ?? "-", Fmt(r["paid"])); any = true; }
                    if (!any) AddSectionRow(pnlPurchasePaid, "No purchases", "—", isMoney: false);
                }

                // ── 8. In Hand Summary ────────────────────────────────────
                pnlInHandSummary.Children.Clear();
                using (var cmd = conn.CreateCommand())
                {
                    // Opening + Sale Paid - Expense - SupplierPayment - PurchasePaid + CustomerReceive
                    double salePaid = 0, expense = 0, supplierPmt = 0, customerRec = 0;

                    cmd.CommandText = "SELECT ROUND(IFNULL(SUM(paid_amount),0),2) FROM sales WHERE sale_date=@date"
                        + (outletId > 0 ? $" AND outlet_id={outletId}" : "")
                        + " AND (del_status IS NULL OR del_status='Live')";
                    cmd.Parameters.AddWithValue("@date", date);
                    salePaid = ToDouble(cmd.ExecuteScalar());

                    cmd.CommandText = "SELECT ROUND(IFNULL(SUM(amount),0),2) FROM expenses WHERE date=@date"
                        + (outletId > 0 ? $" AND outlet_id={outletId}" : "")
                        + " AND (del_status IS NULL OR del_status='Live')";
                    expense = ToDouble(cmd.ExecuteScalar());

                    cmd.CommandText = "SELECT ROUND(IFNULL(SUM(amount),0),2) FROM supplier_payments WHERE date=@date AND (del_status IS NULL OR del_status='Live')";
                    supplierPmt = ToDouble(cmd.ExecuteScalar());

                    cmd.CommandText = "SELECT ROUND(IFNULL(SUM(amount),0),2) FROM customer_receives WHERE date=@date AND (del_status IS NULL OR del_status='Live')";
                    customerRec = ToDouble(cmd.ExecuteScalar());

                    double netInHand = salePaid + customerRec - expense - supplierPmt;

                    AddSectionRow(pnlInHandSummary, "Sale Collection (Cash)",    Fmt(salePaid));
                    AddSectionRow(pnlInHandSummary, "Customer Due Receives",     Fmt(customerRec));
                    AddSectionRow(pnlInHandSummary, "Expense",                   $"- ₹ {expense:N2}");
                    AddSectionRow(pnlInHandSummary, "Supplier Payment",          $"- ₹ {supplierPmt:N2}");
                    AddSectionRow(pnlInHandSummary, "Net In Hand",               Fmt(netInHand), isBold: true);
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error loading Z Report: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        // ── UI helpers ────────────────────────────────────────────────────
        private static void AddSectionRow(StackPanel panel, string label, string value,
            bool isMoney = true, bool isBold = false)
        {
            var border = new Border
            {
                BorderBrush = new SolidColorBrush(Color.FromRgb(0xEA, 0xEA, 0xEC)),
                BorderThickness = new Thickness(0, 0, 0, 1),
                Padding = new Thickness(16, 9, 16, 9)
            };
            var grid = new Grid();
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

            var lbl = new TextBlock
            {
                Text = label, FontSize = 13, FontFamily = new FontFamily("Inter, Segoe UI"),
                Foreground = new SolidColorBrush(Color.FromRgb(0x37, 0x41, 0x51)),
                FontWeight = isBold ? FontWeights.SemiBold : FontWeights.Normal
            };
            Grid.SetColumn(lbl, 0);
            grid.Children.Add(lbl);

            var val = new TextBlock
            {
                Text = isMoney && !value.StartsWith("-") && !value.StartsWith("—") ? $"₹ {value}" : value,
                FontSize = 13, FontFamily = new FontFamily("Inter, Segoe UI"),
                Foreground = isBold
                    ? new SolidColorBrush(Color.FromRgb(0x43, 0x38, 0xCA))
                    : new SolidColorBrush(Color.FromRgb(0x37, 0x41, 0x51)),
                FontWeight = isBold ? FontWeights.Bold : FontWeights.Normal,
                TextAlignment = TextAlignment.Right
            };
            Grid.SetColumn(val, 1);
            grid.Children.Add(val);

            border.Child = grid;
            panel.Children.Add(border);
        }

        private static void AddSectionHeader(StackPanel panel, string col1, string col2, string col3)
        {
            var border = new Border
            {
                Background = new SolidColorBrush(Color.FromRgb(0xF5, 0xF5, 0xFA)),
                BorderBrush = new SolidColorBrush(Color.FromRgb(0xEA, 0xEA, 0xEC)),
                BorderThickness = new Thickness(0, 0, 0, 1),
                Padding = new Thickness(16, 8, 16, 8)
            };
            var grid = new Grid();
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(80) });
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(100) });

            void AddHdr(string text, int col, TextAlignment align = TextAlignment.Left)
            {
                var tb = new TextBlock { Text = text, FontSize = 11.5, FontWeight = FontWeights.SemiBold,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x37, 0x41, 0x51)),
                    FontFamily = new FontFamily("Inter, Segoe UI"), TextAlignment = align };
                Grid.SetColumn(tb, col);
                grid.Children.Add(tb);
            }
            AddHdr(col1, 0); AddHdr(col2, 1, TextAlignment.Right); AddHdr(col3, 2, TextAlignment.Right);
            border.Child = grid;
            panel.Children.Add(border);
        }

        private static void AddSectionRow3(StackPanel panel, string c1, string c2, string c3)
        {
            var border = new Border
            {
                BorderBrush = new SolidColorBrush(Color.FromRgb(0xEA, 0xEA, 0xEC)),
                BorderThickness = new Thickness(0, 0, 0, 1),
                Padding = new Thickness(16, 8, 16, 8)
            };
            var grid = new Grid();
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(80) });
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(100) });

            void Add(string text, int col, TextAlignment align = TextAlignment.Left)
            {
                var tb = new TextBlock { Text = text, FontSize = 13, FontFamily = new FontFamily("Inter, Segoe UI"),
                    Foreground = new SolidColorBrush(Color.FromRgb(0x37, 0x41, 0x51)), TextAlignment = align };
                Grid.SetColumn(tb, col);
                grid.Children.Add(tb);
            }
            Add(c1, 0); Add(c2, 1, TextAlignment.Right); Add($"₹ {c3}", 2, TextAlignment.Right);
            border.Child = grid;
            panel.Children.Add(border);
        }

        private void ShowFilterInfo(string date, string outletName)
        {
            filterInfoPanel.Children.Clear();
            AddInfo("Report", "Z Report");
            AddInfo("Date", DateTime.Parse(date).ToString("dd MMM yyyy"));
            AddInfo("Outlet", outletName);
            AddInfo("Generated", DateTime.Now.ToString("dd MMM yyyy, hh:mm tt"));
            filterInfoBar.Visibility = Visibility.Visible;
        }

        private void AddInfo(string label, string value)
        {
            var sp = new StackPanel { Orientation = Orientation.Horizontal, Margin = new Thickness(0, 0, 0, 2) };
            sp.Children.Add(new TextBlock { Text = label + ": ", FontSize = 12, FontWeight = FontWeights.SemiBold,
                Foreground = Brushes.DarkSlateGray, FontFamily = new FontFamily("Inter, Segoe UI") });
            sp.Children.Add(new TextBlock { Text = value, FontSize = 12,
                Foreground = Brushes.DarkSlateGray, FontFamily = new FontFamily("Inter, Segoe UI") });
            filterInfoPanel.Children.Add(sp);
        }

        private static string Fmt(object? v)
        {
            if (v == null || v == DBNull.Value) return "0.00";
            double.TryParse(v.ToString(), NumberStyles.Any, CultureInfo.InvariantCulture, out double d);
            return $"{Math.Round(d, 2):N2}";
        }

        private static double ToDouble(object? v)
        {
            if (v == null || v == DBNull.Value) return 0;
            double.TryParse(v.ToString(), NumberStyles.Any, CultureInfo.InvariantCulture, out double d);
            return d;
        }

        // ── Export / Print ─────────────────────────────────────────────────
        private void BtnExportCsv_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            var dlg = new SaveFileDialog { Filter = "CSV (*.csv)|*.csv", FileName = "ZReport_" + DateTime.Today.ToString("yyyyMMdd") + ".csv" };
            if (dlg.ShowDialog() != true) return;
            try
            {
                using var sw = new StreamWriter(dlg.FileName, false, System.Text.Encoding.UTF8);
                sw.WriteLine("Section,Label,Value");
                void WritePnl(string section, StackPanel pnl)
                {
                    foreach (UIElement el in pnl.Children)
                    {
                        if (el is Border b && b.Child is Grid g)
                        {
                            if (g.Children.Count >= 3)
                            {
                                string lbl = (g.Children[0] as TextBlock)?.Text ?? "";
                                string v1 = (g.Children[1] as TextBlock)?.Text ?? "";
                                string v2 = (g.Children[2] as TextBlock)?.Text ?? "";
                                sw.WriteLine($"\"{section}\",\"{lbl}\",\"{v1}\",\"{v2}\"");
                            }
                            else
                            {
                                string lbl = (g.Children[0] as TextBlock)?.Text ?? "";
                                string val = (g.Children[1] as TextBlock)?.Text ?? "";
                                sw.WriteLine($"\"{section}\",\"{lbl}\",\"{val}\",\"\"");
                            }
                        }
                    }
                }
                WritePnl("Sales and Taxes", pnlSalesTaxes);
                WritePnl("Payment Methods", pnlPaymentMethods);
                WritePnl("Item Wise Sales", pnlItemWise);
                WritePnl("Expense",         pnlExpense);
                WritePnl("Supplier Payment",pnlSupplierPayment);
                WritePnl("Customer Receives",pnlCustomerReceives);
                WritePnl("Purchase Paid",   pnlPurchasePaid);
                WritePnl("In Hand Summary", pnlInHandSummary);
                MessageBox.Show("Exported: " + dlg.FileName, "Success");
            }
            catch (Exception ex) { MessageBox.Show("Error: " + ex.Message); }
        }

        private void BtnPrint_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            var dlg = new PrintDialog();
            if (dlg.ShowDialog() != true) return;
            var doc = new FlowDocument { FontFamily = new FontFamily("Segoe UI"), FontSize = 11, PagePadding = new Thickness(40) };
            doc.Blocks.Add(new Paragraph(new Run("Z Report")) { FontSize = 18, FontWeight = FontWeights.Bold });
            doc.Blocks.Add(new Paragraph(new Run($"Date: {dpDate.SelectedDate:dd MMM yyyy}  |  Outlet: {(cmbOutlet.SelectedItem as ReportComboItem)?.Name}")) { Foreground = Brushes.Gray });

            void AddSection(string title, StackPanel pnl)
            {
                doc.Blocks.Add(new Paragraph(new Run(title)) { FontSize = 13, FontWeight = FontWeights.Bold, Margin = new Thickness(0, 12, 0, 4) });
                foreach (UIElement el in pnl.Children)
                {
                    if (el is Border b && b.Child is Grid g)
                    {
                        if (g.Children.Count >= 3)
                        {
                            string lbl = (g.Children[0] as TextBlock)?.Text ?? "";
                            string v1 = (g.Children[1] as TextBlock)?.Text ?? "";
                            string v2 = (g.Children[2] as TextBlock)?.Text ?? "";
                            doc.Blocks.Add(new Paragraph(new Run($"{lbl}: {v1}  {v2}")) { Margin = new Thickness(0, 1, 0, 1) });
                        }
                        else
                        {
                            string lbl = (g.Children[0] as TextBlock)?.Text ?? "";
                            string val = (g.Children[1] as TextBlock)?.Text ?? "";
                            doc.Blocks.Add(new Paragraph(new Run($"{lbl}: {val}")) { Margin = new Thickness(0, 1, 0, 1) });
                        }
                    }
                }
            }
            AddSection("Sales and Taxes Summary", pnlSalesTaxes);
            AddSection("Payment Method Breakdown", pnlPaymentMethods);
            AddSection("Item Wise Sales", pnlItemWise);
            AddSection("Expense",         pnlExpense);
            AddSection("Supplier Payment",pnlSupplierPayment);
            AddSection("Customer Receives",pnlCustomerReceives);
            AddSection("In Hand Summary", pnlInHandSummary);

            var pag = ((IDocumentPaginatorSource)doc).DocumentPaginator;
            pag.PageSize = new System.Windows.Size(dlg.PrintableAreaWidth, dlg.PrintableAreaHeight);
            dlg.PrintDocument(pag, "Z Report");
        }
    }
}
