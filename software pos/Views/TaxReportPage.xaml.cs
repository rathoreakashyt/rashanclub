using System;
using System.Collections.Generic;
using System.Data;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Text.Json;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Documents;
using System.Windows.Media;
using Microsoft.Win32;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class TaxReportPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();
        private DataTable? _currentTable;

        public TaxReportPage(MainDashboard? dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () => dataGrid.ItemsSource as DataView, "Tax Report");
            _dashboard = dashboard;
            dpDateFrom.SelectedDate = new DateTime(DateTime.Today.Year, DateTime.Today.Month, 1);
            dpDateTo.SelectedDate   = DateTime.Today;
            LoadOutlets();
            LoadData();
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

        private void LoadData()
        {
            emptyState.Visibility  = Visibility.Collapsed;
            dataGrid.Visibility    = Visibility.Collapsed;
            totalsBar.Visibility   = Visibility.Collapsed;
            rowCountBar.Visibility = Visibility.Collapsed;

            string from = dpDateFrom.SelectedDate?.ToString("yyyy-MM-dd") ?? "1900-01-01";
            string to   = dpDateTo.SelectedDate?.ToString("yyyy-MM-dd") ?? "2099-12-31";

            long outletId = (cmbOutlet.SelectedItem as ReportComboItem)?.Id ?? 0;

            var sql = "SELECT ROW_NUMBER() OVER (ORDER BY sale_date DESC, Id DESC) AS sn,"
                + " sale_no AS invoice_no,"
                + " COALESCE(date_time, sale_date, created_at) AS date_time,"
                + " ROUND(IFNULL(total_payable, 0), 2) AS total_sale,"
                + " IFNULL(sale_vat_objects, '') AS sale_vat_objects,"
                + " IFNULL(vat, 0) AS vat"
                + " FROM sales"
                + " WHERE (del_status IS NULL OR del_status='Live')"
                + " AND (sale_vat_objects IS NOT NULL AND sale_vat_objects != ''"
                + " OR IFNULL(vat, 0) > 0)";

            if (outletId > 0) sql += $" AND outlet_id = {outletId}";

            sql += " AND sale_date BETWEEN @from AND @to";
            sql += " ORDER BY sale_date DESC, Id DESC";

            try
            {
                using var conn = _db.GetConnection();
                using var cmd  = conn.CreateCommand();
                cmd.CommandText = sql;
                cmd.Parameters.AddWithValue("@from", from);
                cmd.Parameters.AddWithValue("@to",   to);

                var rawTable = new DataTable();
                using (var r = cmd.ExecuteReader()) rawTable.Load(r);

                // Parse JSON and build display table
                var table = new DataTable();
                table.Columns.Add("sn", typeof(string));
                table.Columns.Add("invoice_no", typeof(string));
                table.Columns.Add("date_time", typeof(string));
                table.Columns.Add("total_sale", typeof(string));
                table.Columns.Add("applied_tax_amount", typeof(string));
                table.Columns.Add("total_tax", typeof(string));

                double grandTotalSale = 0, grandTotalTax = 0;

                foreach (DataRow row in rawTable.Rows)
                {
                    double totalSale = 0;
                    if (double.TryParse(row["total_sale"]?.ToString(), NumberStyles.Any, CultureInfo.InvariantCulture, out double ts))
                        totalSale = ts;

                    string appliedTax = "-";
                    double totalTax = 0;

                    double vatFallback = 0;
                    if (double.TryParse(row["vat"]?.ToString(), NumberStyles.Any, CultureInfo.InvariantCulture, out double vf))
                        vatFallback = vf;

                    string vatJson = row["sale_vat_objects"]?.ToString() ?? "";
                    if (string.IsNullOrWhiteSpace(vatJson) && vatFallback > 0)
                    {
                        // Legacy bills: only the vat column is populated.
                        // Split half-half into CGST/SGST (intra-state default).
                        totalTax = Math.Round(vatFallback, 2);
                        double half = Math.Round(vatFallback / 2.0, 2);
                        if (half > 0) appliedTax = $"CGST:{half:N2}, SGST:{half:N2}";
                    }
                    else if (!string.IsNullOrEmpty(vatJson))
                    {
                        try
                        {
                            using var doc = JsonDocument.Parse(vatJson);
                            if (doc.RootElement.ValueKind == JsonValueKind.Array)
                            {
                                var taxParts = new List<string>();
                                foreach (var item in doc.RootElement.EnumerateArray())
                                {
                                    string taxType = item.TryGetProperty("tax_field_type", out var tt) ? tt.GetString() ?? "" : "";
                                    double taxAmount = 0;
                                    if (item.TryGetProperty("tax_field_amount", out var ta))
                                        taxAmount = ta.GetDouble();
                                    totalTax += taxAmount;
                                    if (!string.IsNullOrEmpty(taxType) && taxAmount > 0)
                                        taxParts.Add($"{taxType}:{taxAmount:N2}");
                                }
                                if (taxParts.Count > 0)
                                    appliedTax = string.Join(", ", taxParts);
                            }
                        }
                        catch { appliedTax = "-"; }
                    }

                    grandTotalSale += totalSale;
                    grandTotalTax  += totalTax;

                    string dt = "";
                    if (row["date_time"] != null && DateTime.TryParse(row["date_time"].ToString(), out var parsed))
                        dt = parsed.ToString("dd MMM yyyy, hh:mm tt");

                    table.Rows.Add(
                        row["sn"]?.ToString(),
                        row["invoice_no"]?.ToString() ?? "-",
                        dt,
                        $"₹ {totalSale:N2}",
                        appliedTax,
                        $"₹ {totalTax:N2}");
                }

                _currentTable = table;

                if (table.Rows.Count == 0)
                { emptyState.Visibility = Visibility.Visible; return; }

                dataGrid.ItemsSource    = table.DefaultView;
                dataGrid.Visibility     = Visibility.Visible;
                rowCountBar.Visibility  = Visibility.Visible;
                lblRowCount.Text        = $"Showing {table.Rows.Count} entries";

                lblTotalSale.Text   = $"₹ {grandTotalSale:N2}";
                lblTotalTax.Text    = $"₹ {grandTotalTax:N2}";
                totalsBar.Visibility = Visibility.Visible;
            }
            catch (Exception ex)
            {
                emptyState.Visibility = Visibility.Visible;
                MessageBox.Show("Error: " + ex.Message, "Tax Report", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void ShowFilterInfo()
        {
            filterInfoPanel.Children.Clear();
            AddInfo("Report", "Tax Report");
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

        private void BtnToggleFilter_Click(object sender, RoutedEventArgs e)
            => filterSection.Visibility = filterSection.Visibility == Visibility.Collapsed
               ? Visibility.Visible : Visibility.Collapsed;

        private void BtnApplyFilter_Click(object sender, RoutedEventArgs e)
        {
            filterSection.Visibility = Visibility.Collapsed;
            LoadData();
            ShowFilterInfo();
        }

        private void BtnCancelFilter_Click(object sender, RoutedEventArgs e)
            => filterSection.Visibility = Visibility.Collapsed;

        private void BtnExport_Click(object sender, RoutedEventArgs e)
            => exportPopup.IsOpen = !exportPopup.IsOpen;

        private void BtnExportCsv_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            if (_currentTable == null || _currentTable.Rows.Count == 0) { MessageBox.Show("No data."); return; }
            var dlg = new SaveFileDialog { Filter = "CSV (*.csv)|*.csv", FileName = "TaxReport_" + DateTime.Today.ToString("yyyyMMdd") + ".csv" };
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
            doc.Blocks.Add(new Paragraph(new Run("Tax Report")) { FontSize = 16, FontWeight = FontWeights.Bold, Margin = new Thickness(0, 0, 0, 6) });
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
            dlg.PrintDocument(pag, "Tax Report");
        }
    }
}
