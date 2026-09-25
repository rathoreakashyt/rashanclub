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
    public partial class DetailedAvailableLoyaltyPointReportPage : UserControl
    {
        private readonly MainDashboard _dashboard;
        private readonly DatabaseService _db = new();
        private DataTable _allData = new DataTable();

        public DetailedAvailableLoyaltyPointReportPage(MainDashboard dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () => dataGrid.ItemsSource as DataView, "Available Loyalty Point Report");
            _dashboard = dashboard;
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
                cmd.CommandText = "SELECT id, name FROM customers WHERE (del_status IS NULL OR del_status='Live') AND IFNULL(loyalty_point,0)>0 ORDER BY name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    custList.Add(new ReportComboItem(r.GetInt64(0), r.GetString(1)));
            }
            catch { }
            cmbCustomer.ItemsSource = custList;
            cmbCustomer.SelectedIndex = 0;

            var sortList = new System.Collections.Generic.List<ReportComboItem>
            {
                new(1, "Points: High to Low"),
                new(2, "Points: Low to High"),
                new(3, "Name: A to Z"),
                new(4, "Name: Z to A")
            };
            cmbSort.ItemsSource = sortList;
            cmbSort.SelectedIndex = 0;
        }

        private void LoadReport(long customerId = 0, double minPoints = 0, int sort = 1)
        {
            try
            {
                using var conn = _db.GetConnection();

                string custCond = customerId > 0 ? $" AND c.id={customerId}" : "";
                string minPtsCond = minPoints > 0 ? $" AND IFNULL(c.loyalty_point,0)>={minPoints}" : "";
                string orderBy = sort switch
                {
                    2 => "ORDER BY c.loyalty_point ASC",
                    3 => "ORDER BY c.name ASC",
                    4 => "ORDER BY c.name DESC",
                    _ => "ORDER BY c.loyalty_point DESC"
                };

                var dt = new DataTable();
                dt.Columns.Add("sn", typeof(int));
                dt.Columns.Add("customer", typeof(string));
                dt.Columns.Add("available_points", typeof(string));
                dt.Columns.Add("_pointsRaw", typeof(double));

                double totalPoints = 0;
                int sn = 0;

                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $@"SELECT c.id, c.name, c.phone, IFNULL(c.loyalty_point,0) AS points
                        FROM customers c
                        WHERE (c.del_status IS NULL OR c.del_status='Live') AND IFNULL(c.loyalty_point,0)>0
                        {custCond} {minPtsCond}
                        {orderBy}";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        sn++;
                        string name = r.IsDBNull(1) ? "-" : r.GetString(1);
                        string phone = r.IsDBNull(2) ? "" : r.GetString(2);
                        double points = r.IsDBNull(3) ? 0 : Convert.ToDouble(r.GetValue(3));

                        string displayName = name;
                        if (!string.IsNullOrEmpty(phone))
                            displayName += " (" + phone + ")";

                        totalPoints += points;

                        dt.Rows.Add(sn, displayName, points.ToString("N0"), points);
                    }
                }

                _allData = dt;
                dataGrid.ItemsSource = null;
                dataGrid.ItemsSource = dt.DefaultView;

                bool hasData = dt.Rows.Count > 0;
                emptyState.Visibility = hasData ? Visibility.Collapsed : Visibility.Visible;
                dataGrid.Visibility = hasData ? Visibility.Visible : Visibility.Collapsed;
                rowCountBar.Visibility = hasData ? Visibility.Visible : Visibility.Collapsed;

                lblRowCount.Text = $"Showing {dt.Rows.Count} customers | Total Points: {totalPoints:N0}";
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
            long custId = (cmbCustomer.SelectedItem as ReportComboItem)?.Id ?? 0;
            double minPts = 0;
            double.TryParse(txtMinPoints.Text, out minPts);
            int sort = (int)((cmbSort.SelectedItem as ReportComboItem)?.Id ?? 1);

            LoadReport(custId, minPts, sort);

            filterInfoBar.Visibility = Visibility.Visible;
            filterInfoPanel.Children.Clear();

            if (custId > 0)
                filterInfoPanel.Children.Add(new TextBlock
                {
                    Text = "Customer: " + (cmbCustomer.SelectedItem as ReportComboItem)?.Name,
                    FontSize = 11.5, FontFamily = new FontFamily("Inter, Segoe UI"),
                    Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0x65, 0x34)),
                    Margin = new Thickness(0, 0, 16, 0)
                });

            if (minPts > 0)
                filterInfoPanel.Children.Add(new TextBlock
                {
                    Text = "Min Points: " + minPts.ToString("N0"),
                    FontSize = 11.5, FontFamily = new FontFamily("Inter, Segoe UI"),
                    Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0x65, 0x34)),
                    Margin = new Thickness(0, 0, 16, 0)
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
            doc.Blocks.Add(new Paragraph(new Run("Detailed Available Loyalty Point Report")) { FontSize = 16, FontWeight = FontWeights.Bold, Margin = new Thickness(0, 0, 0, 6) });
            doc.Blocks.Add(new Paragraph(new Run($"Generated: {DateTime.Now:dd MMM yyyy HH:mm}")) { Foreground = Brushes.Gray, Margin = new Thickness(0, 0, 0, 12) });

            var tbl = new System.Windows.Documents.Table { CellSpacing = 2 };
            string[] headers = { "SN", "CUSTOMER (PHONE)", "AVAILABLE POINTS" };
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
                string[] vals = { rv["sn"].ToString(), rv["customer"].ToString(), rv["available_points"].ToString() };
                foreach (var v in vals)
                    tr.Cells.Add(new TableCell(new Paragraph(new Run(v))) { Padding = new Thickness(4, 2, 4, 2) });
                rg.Rows.Add(tr);
                alt = !alt;
            }
            tbl.RowGroups.Add(rg);
            doc.Blocks.Add(tbl);
            var pag = ((IDocumentPaginatorSource)doc).DocumentPaginator;
            pag.PageSize = new Size(dlg.PrintableAreaWidth, dlg.PrintableAreaHeight);
            dlg.PrintDocument(pag, "Detailed Available Loyalty Point Report");
        }

        private void BtnExportCsv_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            var dv = dataGrid.ItemsSource as DataView;
            if (dv == null || dv.Count == 0) { MessageBox.Show("No data to export.", "Info"); return; }

            var sfd = new Microsoft.Win32.SaveFileDialog { Filter = "CSV|*.csv", FileName = "DetailedAvailableLoyaltyPointReport.csv" };
            if (sfd.ShowDialog() != true) return;

            using var sw = new StreamWriter(sfd.FileName, false, System.Text.Encoding.UTF8);
            sw.WriteLine("SN,CUSTOMER (PHONE),AVAILABLE POINTS");
            foreach (DataRowView rv in dv)
            {
                sw.WriteLine(string.Join(",",
                    rv["sn"],
                    "\"" + rv["customer"].ToString().Replace("\"", "\"\"") + "\"",
                    "\"" + rv["available_points"].ToString().Replace("\"", "\"\"") + "\""));
            }
            MessageBox.Show("Exported successfully.", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
        }
    }
}
