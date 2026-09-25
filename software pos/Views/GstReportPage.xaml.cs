using System;
using System.Collections.Generic;
using System.Data;
using System.IO;
using System.Linq;
using System.Net.Http;
using System.Text.Json;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Documents;
using System.Windows.Media;
using Microsoft.Win32;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class GstReportPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();
        private DataTable? _currentTable;
        private string _currentReportType = "tax-summary";

        private static readonly HttpClient _http = new();

        private readonly (string Key, string Name)[] _reportTypes = new[]
        {
            ("tax-summary",    "1. GST Tax Summary"),
            ("monthly-summary","2. GST Monthly/Date Wise"),
            ("rate-wise",      "3. GST Rate Wise Summary"),
            ("b2b",            "4. B2B Report (GSTR-1)"),
            ("b2c-small",      "5. B2C Small (State Wise)"),
            ("b2c-large",      "6. B2C Large (Interstate >2.5L)"),
            ("hsn-summary",    "7. HSN Summary"),
        };

        public GstReportPage(MainDashboard? dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () => dataGrid.ItemsSource as DataView, "GST Report");
            _dashboard = dashboard;
            dpDateFrom.SelectedDate = new DateTime(DateTime.Today.Year, DateTime.Today.Month, 1);
            dpDateTo.SelectedDate   = DateTime.Today;
            LoadReportTypes();
            LoadOutlets();
        }

        private void LoadReportTypes()
        {
            cmbReportType.Items.Clear();
            foreach (var rt in _reportTypes)
                cmbReportType.Items.Add(rt.Name);
            cmbReportType.SelectedIndex = 0;
        }

        private void LoadOutlets()
        {
            var list = new List<ReportComboItem> { new(0, "All Outlets") };
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
            cmbOutlet.SelectedIndex = 0;
        }

        private string GetReportTypeKey()
        {
            int idx = cmbReportType.SelectedIndex;
            if (idx >= 0 && idx < _reportTypes.Length)
                return _reportTypes[idx].Key;
            return "tax-summary";
        }

        private string GetReportTitle()
        {
            int idx = cmbReportType.SelectedIndex;
            if (idx >= 0 && idx < _reportTypes.Length)
                return _reportTypes[idx].Name.Substring(_reportTypes[idx].Name.IndexOf('.') + 1).Trim() + " Report";
            return "GST Report";
        }

        private void CmbReportType_Changed(object sender, SelectionChangedEventArgs e)
        {
            _currentReportType = GetReportTypeKey();
            lblReportTitle.Text = GetReportTitle();
        }

        private void LoadData()
        {
            emptyState.Visibility = Visibility.Visible;
            dataGrid.Visibility   = Visibility.Collapsed;

            string from = dpDateFrom.SelectedDate?.ToString("yyyy-MM-dd") ?? "";
            string to   = dpDateTo.SelectedDate?.ToString("yyyy-MM-dd") ?? "";
            long outletId = (cmbOutlet.SelectedItem as ReportComboItem)?.Id ?? 0;

            try
            {
                using var conn = _db.GetConnection();

                // Query sales with tax data
                var sql = "SELECT sale_no, sale_date, date_time, total_payable, sale_vat_objects, vat, customer_id, outlet_id"
                    + " FROM sales WHERE (del_status IS NULL OR del_status='Live')"
                    + " AND (sale_vat_objects IS NOT NULL AND sale_vat_objects != ''"
                    + " OR IFNULL(vat, 0) > 0)";

                if (outletId > 0) sql += $" AND outlet_id = {outletId}";
                if (!string.IsNullOrEmpty(from)) sql += " AND sale_date >= @from";
                if (!string.IsNullOrEmpty(to))   sql += " AND sale_date <= @to";
                sql += " ORDER BY sale_date ASC, id ASC";

                using var cmd = conn.CreateCommand();
                cmd.CommandText = sql;
                if (!string.IsNullOrEmpty(from)) cmd.Parameters.AddWithValue("@from", from);
                if (!string.IsNullOrEmpty(to))   cmd.Parameters.AddWithValue("@to", to);

                var salesTable = new DataTable();
                using (var r = cmd.ExecuteReader()) salesTable.Load(r);

                if (salesTable.Rows.Count == 0)
                { emptyState.Visibility = Visibility.Visible; return; }

                // Build report based on type
                var table = BuildReport(salesTable, conn);
                if (table == null || table.Rows.Count == 0)
                { emptyState.Visibility = Visibility.Visible; return; }

                _currentTable = table;
                dataGrid.Columns.Clear();
                foreach (DataColumn col in table.Columns)
                    dataGrid.Columns.Add(new DataGridTextColumn
                    {
                        Header = col.ColumnName.Replace("_", " ").ToUpper(),
                        Binding = new System.Windows.Data.Binding($"[{col.ColumnName}]"),
                        MinWidth = col.ColumnName.Length * 10 + 20
                    });
                dataGrid.ItemsSource = table.DefaultView;
                dataGrid.Visibility  = Visibility.Visible;
                emptyState.Visibility = Visibility.Collapsed;
            }
            catch (Exception ex)
            {
                emptyState.Visibility = Visibility.Visible;
                MessageBox.Show("Error: " + ex.Message, "GST Report", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private DataTable BuildReport(DataTable sales, System.Data.Common.DbConnection conn)
        {
            return _currentReportType switch
            {
                "tax-summary"     => BuildTaxSummary(sales),
                "monthly-summary" => BuildMonthlySummary(sales),
                "rate-wise"       => BuildRateWise(sales, conn),
                "b2b"             => BuildB2B(sales, conn),
                "b2c-small"       => BuildB2CSmall(sales, conn),
                "b2c-large"       => BuildB2CLarge(sales, conn),
                "hsn-summary"     => BuildHsnSummary(sales, conn),
                _                 => BuildTaxSummary(sales),
            };
        }

        private (double taxable, double cgst, double sgst, double igst) ParseVat(string json, double vatFallback = 0)
        {
            double taxable = 0, cgst = 0, sgst = 0, igst = 0;
            if (string.IsNullOrWhiteSpace(json) && vatFallback > 0)
            {
                // Legacy bills: only the vat column is populated.
                // Split half-half into CGST/SGST (intra-state default).
                double half = Math.Round(vatFallback / 2.0, 2);
                return (0, half, half, 0);
            }
            try
            {
                using var doc = JsonDocument.Parse(json);
                if (doc.RootElement.ValueKind == JsonValueKind.Array)
                {
                    foreach (var item in doc.RootElement.EnumerateArray())
                    {
                        string type = item.TryGetProperty("tax_field_type", out var tt) ? tt.GetString() ?? "" : "";
                        double amt = item.TryGetProperty("tax_field_amount", out var ta) ? ta.GetDouble() : 0;
                        if (type.Contains("CGST")) cgst += amt;
                        else if (type.Contains("SGST")) sgst += amt;
                        else if (type.Contains("IGST")) igst += amt;
                    }
                }
            }
            catch { }
            return (taxable, cgst, sgst, igst);
        }

        private static double GetVatFallback(DataRow row)
        {
            if (double.TryParse(row["vat"]?.ToString(), System.Globalization.NumberStyles.Any,
                System.Globalization.CultureInfo.InvariantCulture, out double v))
                return v;
            return 0;
        }

        private DataTable BuildTaxSummary(DataTable sales)
        {
            var table = new DataTable();
            table.Columns.Add("tax_type", typeof(string));
            table.Columns.Add("taxable_value", typeof(string));
            table.Columns.Add("cgst", typeof(string));
            table.Columns.Add("sgst", typeof(string));
            table.Columns.Add("igst", typeof(string));
            table.Columns.Add("total_tax", typeof(string));
            table.Columns.Add("invoice_count", typeof(string));

            double intraTaxable = 0, intraCgst = 0, intraSgst = 0, intraIgst = 0;
            double interTaxable = 0, interCgst = 0, interSgst = 0, interIgst = 0;
            int intraCount = 0, interCount = 0;

            foreach (DataRow row in sales.Rows)
            {
                string vat = row["sale_vat_objects"]?.ToString() ?? "";
                double total = 0;
                if (double.TryParse(row["total_payable"]?.ToString(), out double t)) total = t;

                var (_, cgst, sgst, igst) = ParseVat(vat, GetVatFallback(row));
                if (igst > 0) { interTaxable += total; interCgst += cgst; interSgst += sgst; interIgst += igst; interCount++; }
                else { intraTaxable += total; intraCgst += cgst; intraSgst += sgst; intraIgst += igst; intraCount++; }
            }

            table.Rows.Add("Intra-State (CGST+SGST)", $"₹ {intraTaxable:N2}", $"₹ {intraCgst:N2}", $"₹ {intraSgst:N2}", $"₹ {intraIgst:N2}", $"₹ {intraCgst + intraSgst:N2}", intraCount.ToString());
            table.Rows.Add("Inter-State (IGST)", $"₹ {interTaxable:N2}", $"₹ {interCgst:N2}", $"₹ {interSgst:N2}", $"₹ {interIgst:N2}", $"₹ {interIgst:N2}", interCount.ToString());
            table.Rows.Add("Total", $"₹ {intraTaxable + interTaxable:N2}", $"₹ {intraCgst + interCgst:N2}", $"₹ {intraSgst + interSgst:N2}", $"₹ {intraIgst + interIgst:N2}", $"₹ {intraCgst + intraSgst + interIgst:N2}", (intraCount + interCount).ToString());
            return table;
        }

        private DataTable BuildMonthlySummary(DataTable sales)
        {
            var table = new DataTable();
            table.Columns.Add("particular", typeof(string));
            table.Columns.Add("amount", typeof(string));

            double totalSale = 0, totalCgst = 0, totalSgst = 0, totalIgst = 0;
            int invoiceCount = 0;

            foreach (DataRow row in sales.Rows)
            {
                string vat = row["sale_vat_objects"]?.ToString() ?? "";
                double total = 0;
                if (double.TryParse(row["total_payable"]?.ToString(), out double t)) total = t;
                var (_, cgst, sgst, igst) = ParseVat(vat, GetVatFallback(row));
                totalSale += total; totalCgst += cgst; totalSgst += sgst; totalIgst += igst; invoiceCount++;
            }

            table.Rows.Add("Total Invoice Count", invoiceCount.ToString());
            table.Rows.Add("Total Taxable Value", $"₹ {totalSale:N2}");
            table.Rows.Add("Total CGST", $"₹ {totalCgst:N2}");
            table.Rows.Add("Total SGST", $"₹ {totalSgst:N2}");
            table.Rows.Add("Total IGST", $"₹ {totalIgst:N2}");
            table.Rows.Add("Total GST Collected", $"₹ {totalCgst + totalSgst + totalIgst:N2}");
            table.Rows.Add("Grand Total Sales", $"₹ {totalSale:N2}");
            return table;
        }

        private DataTable BuildRateWise(DataTable sales, System.Data.Common.DbConnection conn)
        {
            var table = new DataTable();
            table.Columns.Add("gst_percent", typeof(string));
            table.Columns.Add("taxable_value", typeof(string));
            table.Columns.Add("cgst", typeof(string));
            table.Columns.Add("sgst", typeof(string));
            table.Columns.Add("igst", typeof(string));
            table.Columns.Add("total_tax", typeof(string));

            var rateData = new Dictionary<string, (double taxable, double cgst, double sgst, double igst)>();

            foreach (DataRow row in sales.Rows)
            {
                string vat = row["sale_vat_objects"]?.ToString() ?? "";
                double total = 0;
                if (double.TryParse(row["total_payable"]?.ToString(), out double t)) total = t;

                try
                {
                    using var doc = JsonDocument.Parse(vat);
                    if (doc.RootElement.ValueKind == JsonValueKind.Array)
                    {
                        foreach (var item in doc.RootElement.EnumerateArray())
                        {
                            string type = item.TryGetProperty("tax_field_type", out var tt) ? tt.GetString() ?? "" : "";
                            double amt = item.TryGetProperty("tax_field_amount", out var ta) ? ta.GetDouble() : 0;
                            double rate = 0;
                            if (item.TryGetProperty("tax_field_percentage", out var rp)) rate = rp.GetDouble();
                            string key = rate > 0 ? $"{rate}%" : "Other";
                            if (!rateData.ContainsKey(key)) rateData[key] = (0, 0, 0, 0);
                            var d = rateData[key];
                            if (type.Contains("CGST")) rateData[key] = (d.taxable + total / 2, d.cgst + amt, d.sgst, d.igst);
                            else if (type.Contains("SGST")) rateData[key] = (d.taxable + total / 2, d.cgst, d.sgst + amt, d.igst);
                            else if (type.Contains("IGST")) rateData[key] = (d.taxable + total, d.cgst, d.sgst, d.igst + amt);
                        }
                    }
                }
                catch { }
            }

            foreach (var kv in rateData.OrderBy(x => x.Key))
                table.Rows.Add(kv.Key, $"₹ {kv.Value.taxable:N2}", $"₹ {kv.Value.cgst:N2}", $"₹ {kv.Value.sgst:N2}", $"₹ {kv.Value.igst:N2}", $"₹ {kv.Value.cgst + kv.Value.sgst + kv.Value.igst:N2}");
            return table;
        }

        private DataTable BuildB2B(DataTable sales, System.Data.Common.DbConnection conn)
        {
            var table = new DataTable();
            table.Columns.Add("invoice_no", typeof(string));
            table.Columns.Add("date", typeof(string));
            table.Columns.Add("customer", typeof(string));
            table.Columns.Add("gstin", typeof(string));
            table.Columns.Add("state", typeof(string));
            table.Columns.Add("taxable_value", typeof(string));
            table.Columns.Add("cgst", typeof(string));
            table.Columns.Add("sgst", typeof(string));
            table.Columns.Add("igst", typeof(string));
            table.Columns.Add("total_invoice_value", typeof(string));

            foreach (DataRow row in sales.Rows)
            {
                string vat = row["sale_vat_objects"]?.ToString() ?? "";
                double total = 0;
                if (double.TryParse(row["total_payable"]?.ToString(), out double t)) total = t;
                var (_, cgst, sgst, igst) = ParseVat(vat, GetVatFallback(row));
                string dt = "";
                if (DateTime.TryParse(row["sale_date"]?.ToString(), out var d)) dt = d.ToString("dd/MM/yyyy");

                table.Rows.Add(row["sale_no"]?.ToString() ?? "-", dt,
                    "-", "-", "-",
                    $"₹ {total:N2}", $"₹ {cgst:N2}", $"₹ {sgst:N2}", $"₹ {igst:N2}", $"₹ {total:N2}");
            }
            return table;
        }

        private DataTable BuildB2CSmall(DataTable sales, System.Data.Common.DbConnection conn)
        {
            var table = new DataTable();
            table.Columns.Add("invoice_no", typeof(string));
            table.Columns.Add("date", typeof(string));
            table.Columns.Add("state", typeof(string));
            table.Columns.Add("gst_percent", typeof(string));
            table.Columns.Add("taxable_value", typeof(string));
            table.Columns.Add("cgst", typeof(string));
            table.Columns.Add("sgst", typeof(string));
            table.Columns.Add("igst", typeof(string));
            table.Columns.Add("total", typeof(string));

            foreach (DataRow row in sales.Rows)
            {
                string vat = row["sale_vat_objects"]?.ToString() ?? "";
                double total = 0;
                if (double.TryParse(row["total_payable"]?.ToString(), out double t)) total = t;
                var (_, cgst, sgst, igst) = ParseVat(vat, GetVatFallback(row));
                string dt = "";
                if (DateTime.TryParse(row["sale_date"]?.ToString(), out var d)) dt = d.ToString("dd/MM/yyyy");

                if (total <= 250000)
                    table.Rows.Add(row["sale_no"]?.ToString() ?? "-", dt, "-", "-",
                        $"₹ {total:N2}", $"₹ {cgst:N2}", $"₹ {sgst:N2}", $"₹ {igst:N2}", $"₹ {total:N2}");
            }
            return table;
        }

        private DataTable BuildB2CLarge(DataTable sales, System.Data.Common.DbConnection conn)
        {
            var table = new DataTable();
            table.Columns.Add("invoice_no", typeof(string));
            table.Columns.Add("taxable_value", typeof(string));
            table.Columns.Add("total_invoice_value", typeof(string));

            foreach (DataRow row in sales.Rows)
            {
                double total = 0;
                if (double.TryParse(row["total_payable"]?.ToString(), out double t)) total = t;
                if (total > 250000)
                    table.Rows.Add(row["sale_no"]?.ToString() ?? "-", $"₹ {total:N2}", $"₹ {total:N2}");
            }
            return table;
        }

        private DataTable BuildHsnSummary(DataTable sales, System.Data.Common.DbConnection conn)
        {
            var table = new DataTable();
            table.Columns.Add("hsn_code", typeof(string));
            table.Columns.Add("total_qty", typeof(string));
            table.Columns.Add("taxable_value", typeof(string));

            var hsnData = new Dictionary<string, (double qty, double value)>();

            // Get sale details with HSN
            foreach (DataRow row in sales.Rows)
            {
                double total = 0;
                if (double.TryParse(row["total_payable"]?.ToString(), out double t)) total = t;
                string hsn = "NA";
                if (!hsnData.ContainsKey(hsn)) hsnData[hsn] = (0, 0);
                hsnData[hsn] = (hsnData[hsn].qty, hsnData[hsn].value + total);
            }

            foreach (var kv in hsnData.OrderBy(x => x.Key))
                table.Rows.Add(kv.Key, $"{kv.Value.qty:N0}", $"₹ {kv.Value.value:N2}");
            return table;
        }

        private void ShowFilterInfo()
        {
            filterInfoPanel.Children.Clear();
            AddInfo("Report", GetReportTitle());
            AddInfo("Date Range", $"{dpDateFrom.SelectedDate:dd MMM yyyy}  →  {dpDateTo.SelectedDate:dd MMM yyyy}");
            if ((cmbOutlet.SelectedItem as ReportComboItem)?.Id > 0)
                AddInfo("Outlet", ((ReportComboItem)cmbOutlet.SelectedItem).Name);
            AddInfo("Generated", DateTime.Now.ToString("dd MMM yyyy, hh:mm tt"));
            filterInfoBar.Visibility = Visibility.Visible;
        }

        private void AddInfo(string label, string value)
        {
            var sp = new StackPanel { Orientation = Orientation.Horizontal, Margin = new Thickness(0, 0, 0, 2) };
            sp.Children.Add(new TextBlock { Text = label + ": ", FontSize = 12, FontWeight = FontWeights.SemiBold, Foreground = Brushes.DarkSlateGray, FontFamily = new FontFamily("Inter, Segoe UI") });
            sp.Children.Add(new TextBlock { Text = value,        FontSize = 12, Foreground = Brushes.DarkSlateGray, FontFamily = new FontFamily("Inter, Segoe UI") });
            filterInfoPanel.Children.Add(sp);
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
            => _dashboard?.ShowPage(new ReportsIndexPage(_dashboard));

        private void BtnApplyFilter_Click(object sender, RoutedEventArgs e)
        {
            _currentReportType = GetReportTypeKey();
            lblReportTitle.Text = GetReportTitle();
            LoadData();
            ShowFilterInfo();
        }

        private void BtnExport_Click(object sender, RoutedEventArgs e)
            => exportPopup.IsOpen = !exportPopup.IsOpen;

        private void BtnExportCsv_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            if (_currentTable == null || _currentTable.Rows.Count == 0) { MessageBox.Show("No data."); return; }
            var dlg = new SaveFileDialog { Filter = "CSV (*.csv)|*.csv", FileName = $"GST_{_currentReportType}_{DateTime.Today:yyyyMMdd}.csv" };
            if (dlg.ShowDialog() != true) return;
            try
            {
                using var sw = new StreamWriter(dlg.FileName, false, System.Text.Encoding.UTF8);
                sw.WriteLine(string.Join(",", _currentTable.Columns.Cast<DataColumn>().Select(c => c.ColumnName)));
                foreach (DataRow row in _currentTable.Rows)
                    sw.WriteLine(string.Join(",", _currentTable.Columns.Cast<DataColumn>()
                        .Select(c => "\"" + (row[c]?.ToString() ?? "").Replace("\"", "\"\"") + "\"")));
                MessageBox.Show("Exported: " + dlg.FileName, "Success");
            }
            catch (Exception ex) { MessageBox.Show("Error: " + ex.Message); }
        }

        private void BtnPrint_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            if (_currentTable == null || _currentTable.Rows.Count == 0) { MessageBox.Show("No data."); return; }
            var dlg = new PrintDialog();
            if (dlg.ShowDialog() != true) return;
            var doc = new FlowDocument { FontFamily = new FontFamily("Segoe UI"), FontSize = 11, PagePadding = new Thickness(40) };
            doc.Blocks.Add(new Paragraph(new Run(GetReportTitle())) { FontSize = 16, FontWeight = FontWeights.Bold, Margin = new Thickness(0, 0, 0, 6) });
            doc.Blocks.Add(new Paragraph(new Run($"Period: {dpDateFrom.SelectedDate:dd MMM yyyy} to {dpDateTo.SelectedDate:dd MMM yyyy}")) { Foreground = Brushes.Gray, Margin = new Thickness(0, 0, 0, 12) });
            var tbl = new System.Windows.Documents.Table { CellSpacing = 2 };
            int cols = _currentTable.Columns.Count;
            for (int i = 0; i < cols; i++) tbl.Columns.Add(new TableColumn { Width = new GridLength(1, GridUnitType.Star) });
            var rg = new TableRowGroup();
            var hdr = new TableRow { Background = new SolidColorBrush(Color.FromRgb(0x69, 0x6c, 0xff)) };
            foreach (DataColumn col in _currentTable.Columns)
                hdr.Cells.Add(new TableCell(new Paragraph(new Run(col.ColumnName.Replace("_", " ").ToUpper()))) { Padding = new Thickness(4, 3, 4, 3), Foreground = Brushes.White, FontWeight = FontWeights.SemiBold });
            rg.Rows.Add(hdr);
            bool alt = false;
            foreach (DataRow row in _currentTable.Rows)
            {
                var tr = new TableRow { Background = alt ? new SolidColorBrush(Color.FromRgb(0xFA, 0xFA, 0xFF)) : Brushes.White };
                foreach (DataColumn col in _currentTable.Columns)
                    tr.Cells.Add(new TableCell(new Paragraph(new Run(row[col]?.ToString() ?? ""))) { Padding = new Thickness(4, 2, 4, 2) });
                rg.Rows.Add(tr);
                alt = !alt;
            }
            tbl.RowGroups.Add(rg);
            doc.Blocks.Add(tbl);
            var pag = ((IDocumentPaginatorSource)doc).DocumentPaginator;
            pag.PageSize = new Size(dlg.PrintableAreaWidth, dlg.PrintableAreaHeight);
            dlg.PrintDocument(pag, GetReportTitle());
        }
    }
}
