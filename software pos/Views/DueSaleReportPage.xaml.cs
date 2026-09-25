using System;
using System.Globalization;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Documents;
using System.Windows.Media;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class DueSaleReportPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();

        private string _dateFrom = "";
        private string _dateTo = "";
        private long _outletId;
        private string _outletName = "";
        private long _customerId;
        private string _customerName = "";
        private int _index;
        private double _tPayable, _tPaid, _tDue;

        public DueSaleReportPage(MainDashboard? dashboard)
        {
            InitializeComponent();
            ReportPdfHelper.AddButton(btnExport, "📄  PDF", () =>
            {
                var t = new System.Data.DataTable();
                foreach (var hdr in new[] { "SN", "Invoice No", "Date Time", "Customer", "Items", "Total Payable", "Paid", "Due" })
                    t.Columns.Add(hdr);
                foreach (var child in tableBody.Children)
                {
                    if (child is not Border b || b.Child is not Grid row) continue;
                    var cells = new string[8];
                    foreach (var cell in row.Children)
                        if (cell is TextBlock tb)
                            cells[Grid.GetColumn(tb)] = tb.Text;
                    t.Rows.Add(cells);
                }
                ReportPdfHelper.Export(t.DefaultView, "Due Sale Report");
            });
            _dashboard = dashboard;
            LoadOutlets();
            LoadCustomers();
        }

        private void LoadOutlets()
        {
            cmbOutlet.Items.Clear();
            cmbOutlet.Items.Add(new ComboBoxItem { Content = "All Outlets", Tag = 0L });
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "SELECT Id, outlet_name FROM outlets WHERE (del_status IS NULL OR del_status='Live') ORDER BY Id";
            using var r = cmd.ExecuteReader();
            while (r.Read())
            {
                long id = r.IsDBNull(0) ? 0 : r.GetInt64(0);
                string name = r.IsDBNull(1) ? "Outlet " + id : r.GetString(1);
                cmbOutlet.Items.Add(new ComboBoxItem { Content = name, Tag = id });
            }
            cmbOutlet.SelectedIndex = 0;
        }

        private void LoadCustomers()
        {
            cmbCustomer.Items.Clear();
            cmbCustomer.Items.Add(new ComboBoxItem { Content = "All Customers", Tag = 0L });
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "SELECT Id, name FROM customers WHERE (del_status IS NULL OR del_status='Live') ORDER BY name";
            using var r = cmd.ExecuteReader();
            while (r.Read())
            {
                long id = r.IsDBNull(0) ? 0 : r.GetInt64(0);
                string name = r.IsDBNull(1) ? "Customer " + id : r.GetString(1);
                cmbCustomer.Items.Add(new ComboBoxItem { Content = name, Tag = id });
            }
            cmbCustomer.SelectedIndex = 0;
        }

        private static string Fmt(double v) => v.ToString("0.00", CultureInfo.InvariantCulture);
        private static string GetS(SqliteDataReader r, int i) => r.IsDBNull(i) ? "" : r.GetString(i);
        private static double GetD(SqliteDataReader r, int i) => r.IsDBNull(i) ? 0 : r.GetDouble(i);

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            if (_dashboard != null) _dashboard.ShowPage(new ReportsIndexPage(_dashboard));
        }

        private void BtnFilter_Click(object sender, RoutedEventArgs e)
        {
            filterSection.Visibility = filterSection.Visibility == Visibility.Visible ? Visibility.Collapsed : Visibility.Visible;
        }

        private void BtnCancelFilter_Click(object sender, RoutedEventArgs e)
        {
            filterSection.Visibility = Visibility.Collapsed;
        }

        private void BtnApplyFilter_Click(object sender, RoutedEventArgs e)
        {
            _dateFrom = dpDateFrom.SelectedDate?.ToString("yyyy-MM-dd") ?? "";
            _dateTo = dpDateTo.SelectedDate?.ToString("yyyy-MM-dd") ?? "";

            if (cmbOutlet.SelectedItem is ComboBoxItem oItem && oItem.Tag is long oId)
            {
                _outletId = oId;
                _outletName = oId == 0 ? "" : oItem.Content?.ToString() ?? "";
            }
            if (cmbCustomer.SelectedItem is ComboBoxItem cItem && cItem.Tag is long cId)
            {
                _customerId = cId;
                _customerName = cId == 0 ? "" : cItem.Content?.ToString() ?? "";
            }

            filterSection.Visibility = Visibility.Collapsed;
            LoadData();
        }

        private void LoadData()
        {
            _index = 1;
            _tPayable = _tPaid = _tDue = 0;

            tableBody.Children.Clear();
            txtTotalPayable.Text = "0.00";
            txtTotalPaid.Text = "0.00";
            txtTotalDue.Text = "0.00";

            RenderFilterInfo();
            emptyState.Visibility = Visibility.Collapsed;
            tableContent.Visibility = Visibility.Visible;

            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();

            string sql = @"SELECT s.sale_no,
                           CASE WHEN s.date_time IS NOT NULL AND s.date_time != '' THEN s.date_time ELSE s.sale_date END,
                           IFNULL(c.name,'Walk-in Customer'), IFNULL(c.phone,''),
                           (SELECT COUNT(*) FROM sale_details sd WHERE sd.sales_id = s.Id AND (sd.del_status IS NULL OR sd.del_status='Live')),
                           IFNULL(s.total_payable,0), IFNULL(s.paid_amount,0), IFNULL(s.due_amount,0)
                           FROM sales s LEFT JOIN customers c ON c.Id = s.customer_id
                           WHERE (s.del_status IS NULL OR s.del_status='Live') AND s.due_amount > 0";

            if (!string.IsNullOrEmpty(_dateFrom))
                sql += " AND s.sale_date >= @df";
            if (!string.IsNullOrEmpty(_dateTo))
                sql += " AND s.sale_date <= @dt";
            if (_outletId > 0)
                sql += " AND s.outlet_id = @o";
            if (_customerId > 0)
                sql += " AND s.customer_id = @c";

            sql += " ORDER BY s.date_time DESC, s.Id DESC";

            cmd.CommandText = sql;
            if (!string.IsNullOrEmpty(_dateFrom)) cmd.Parameters.AddWithValue("@df", _dateFrom);
            if (!string.IsNullOrEmpty(_dateTo)) cmd.Parameters.AddWithValue("@dt", _dateTo);
            if (_outletId > 0) cmd.Parameters.AddWithValue("@o", _outletId);
            if (_customerId > 0) cmd.Parameters.AddWithValue("@c", _customerId);

            using var r = cmd.ExecuteReader();
            while (r.Read())
            {
                string phone = GetS(r, 3);
                string customer = string.IsNullOrEmpty(phone) ? GetS(r, 2) : GetS(r, 2) + " (" + phone + ")";
                string dateTime = "";
                if (!r.IsDBNull(1))
                {
                    string raw = r.GetString(1);
                    if (raw.Contains(" ") && DateTime.TryParseExact(raw, "yyyy-MM-dd HH:mm:ss", CultureInfo.InvariantCulture, DateTimeStyles.None, out var dt))
                        dateTime = dt.ToString("dd MMM yyyy, hh:mm tt");
                    else if (DateTime.TryParseExact(raw, "yyyy-MM-dd", CultureInfo.InvariantCulture, DateTimeStyles.None, out var dt2))
                        dateTime = dt2.ToString("dd MMM yyyy");
                    else
                        dateTime = raw;
                }

                double payable = GetD(r, 5);
                double paid = GetD(r, 6);
                double due = GetD(r, 7);
                int items = r.IsDBNull(4) ? 0 : (int)r.GetInt64(4);

                _tPayable += payable;
                _tPaid += paid;
                _tDue += due;

                AddRow(GetS(r, 0), dateTime, customer, items.ToString(), Fmt(payable), Fmt(paid), Fmt(due));
            }

            txtTotalPayable.Text = Fmt(_tPayable);
            txtTotalPaid.Text = Fmt(_tPaid);
            txtTotalDue.Text = Fmt(_tDue);
        }

        private void RenderFilterInfo()
        {
            filterInfoBorder.Visibility = Visibility.Visible;
            filterInfoPanel.Children.Clear();
            if (!string.IsNullOrEmpty(_outletName))
                AddInfoLine("Outlet: " + _outletName);
            if (!string.IsNullOrEmpty(_customerName))
                AddInfoLine("Customer: " + _customerName);
            if (!string.IsNullOrEmpty(_dateFrom) || !string.IsNullOrEmpty(_dateTo))
                AddInfoLine("Date Range: " + (string.IsNullOrEmpty(_dateFrom) ? "..." : DateTime.ParseExact(_dateFrom, "yyyy-MM-dd", CultureInfo.InvariantCulture).ToString("dd MMM yyyy")) + " to " + (string.IsNullOrEmpty(_dateTo) ? "..." : DateTime.ParseExact(_dateTo, "yyyy-MM-dd", CultureInfo.InvariantCulture).ToString("dd MMM yyyy")));
            AddInfoLine("Generated on: " + DateTime.Now.ToString("dd MMM yyyy, hh:mm tt"));
            string user = _dashboard?.CurrentUser?.FullName ?? "";
            if (!string.IsNullOrEmpty(user)) AddInfoLine("Generated by: " + user);
        }

        private void AddInfoLine(string text)
        {
            filterInfoPanel.Children.Add(new TextBlock
            {
                Text = text,
                FontSize = 12,
                Foreground = new SolidColorBrush(Color.FromRgb(0x37, 0x41, 0x51)),
                FontFamily = new FontFamily("Inter, Segoe UI"),
                Margin = new Thickness(0, 2, 0, 2)
            });
        }

        private void AddRow(string invoice, string dateTime, string customer, string items, string payable, string paid, string due)
        {
            var border = new Border
            {
                BorderBrush = new SolidColorBrush(Color.FromRgb(0xE5, 0xE7, 0xEB)),
                BorderThickness = new Thickness(0, 0, 0, 1)
            };
            var row = new Grid();
            for (int i = 0; i < 8; i++)
                row.ColumnDefinitions.Add(new ColumnDefinition { Width = GetColWidth(i) });

            AddCell(row, 0, (_index++).ToString());
            AddCell(row, 1, invoice);
            AddCell(row, 2, dateTime);
            AddCell(row, 3, customer);
            AddCell(row, 4, items, true);
            AddCell(row, 5, payable, true);
            AddCell(row, 6, paid, true);
            AddCell(row, 7, due, true);

            border.Child = row;
            tableBody.Children.Add(border);
        }

        private static void AddCell(Grid row, int col, string text, bool rightAlign = false)
        {
            var cell = new TextBlock
            {
                Text = text,
                FontSize = 12,
                Foreground = new SolidColorBrush(Color.FromRgb(0x37, 0x41, 0x51)),
                FontFamily = new FontFamily("Inter, Segoe UI"),
                Padding = new Thickness(8, 5, 8, 5),
                TextAlignment = rightAlign ? TextAlignment.Right : TextAlignment.Left,
                TextWrapping = TextWrapping.Wrap
            };
            Grid.SetColumn(cell, col);
            row.Children.Add(cell);
        }

        private static GridLength GetColWidth(int i)
        {
            return i switch
            {
                0 => new GridLength(50),
                1 => new GridLength(130),
                2 => new GridLength(130),
                3 => new GridLength(1, GridUnitType.Star),
                4 => new GridLength(60),
                _ => new GridLength(100)
            };
        }

        private void BtnExport_Click(object sender, RoutedEventArgs e)
        {
            if (tableBody.Children.Count == 0)
            {
                MessageBox.Show("Please apply a filter first.", "Due Sale Report", MessageBoxButton.OK, MessageBoxImage.Information);
                return;
            }
            var dlg = new PrintDialog();
            if (dlg.ShowDialog() != true) return;

            var doc = new FlowDocument
            {
                PagePadding = new Thickness(30),
                FontFamily = new FontFamily("Segoe UI"),
                FontSize = 11
            };
            doc.Blocks.Add(new Paragraph(new Run("Due Sale Report")) { FontSize = 18, FontWeight = FontWeights.Bold, TextAlignment = TextAlignment.Center });
            doc.Blocks.Add(new Paragraph(new Run("Generated: " + DateTime.Now.ToString("dd MMM yyyy, hh:mm tt"))) { FontSize = 10, Foreground = Brushes.Gray, TextAlignment = TextAlignment.Center });

            foreach (var child in tableBody.Children)
            {
                if (child is not Border b || b.Child is not Grid row) continue;
                string[] cells = new string[8];
                foreach (var cell in row.Children)
                {
                    if (cell is TextBlock tb)
                        cells[Grid.GetColumn(tb)] = tb.Text;
                }
                string line = string.Join(" | ", cells);
                doc.Blocks.Add(new Paragraph(new Run(line)) { Margin = new Thickness(0, 1, 0, 1) });
            }

            string footer = "TOTAL: " + Fmt(_tPayable) + " | " + Fmt(_tPaid) + " | " + Fmt(_tDue);
            doc.Blocks.Add(new Paragraph(new Run(footer)) { FontWeight = FontWeights.Bold, TextAlignment = TextAlignment.Right });

            dlg.PrintDocument(((IDocumentPaginatorSource)doc).DocumentPaginator, "Due Sale Report");
        }
    }
}
