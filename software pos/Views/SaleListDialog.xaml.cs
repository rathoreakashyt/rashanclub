using System.Collections.Generic;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class SaleListDialog : Window
    {
        private readonly DatabaseService _db = new();
        private List<SaleRow> _all = new();
        private List<SaleRow> _filtered = new();

        public SaleListDialog()
        {
            InitializeComponent();
            PreviewKeyDown += Window_PreviewKeyDown;
            Loaded += (s, e) => { LoadData(); };
        }

        // ═══ DATA ═══

        private void LoadData()
        {
            _all.Clear();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"
                    SELECT t.rowid * -1 AS id,
                           t.VchNo AS invoice_no,
                           t.VchDate AS date,
                           IFNULL((SELECT c.name FROM sales s JOIN customers c ON c.id=s.customer_id WHERE (s.invoice_no=t.VchNo OR s.sale_no=t.VchNo) AND c.name<>'' LIMIT 1),
                                  IFNULL(NULLIF(m.Name,''), 'Walk-in')) AS customer_name,
                           IFNULL((SELECT c.phone FROM sales s JOIN customers c ON c.id=s.customer_id WHERE (s.invoice_no=t.VchNo OR s.sale_no=t.VchNo) AND c.phone<>'' LIMIT 1),
                                  IFNULL(m.Phone, '')) AS phone,
                           t.Amount AS grand_total,
                           t.Amount AS paid_amount,
                           0 AS due_amount,
                           (SELECT COUNT(*) FROM Tran2 t2 WHERE t2.VchCode=t.VchCode) AS item_count,
                           (SELECT GROUP_CONCAT(IFNULL(i.name,t2.Description), ', ')
                            FROM Tran2 t2 LEFT JOIN items i ON i.code=t2.MasterCode1
                            WHERE t2.VchCode=t.VchCode) AS items_preview
                    FROM Tran1 t
                    LEFT JOIN Master1 m ON m.Code = t.MasterCode1
                    WHERE t.VchType='Sales' AND t.IsCancelled=0
                      AND (t.VchCode LIKE 'SRV%'
                           OR t.ServerId IS NULL OR t.ServerId=0
                           OR NOT EXISTS (SELECT 1 FROM Tran1 s2
                                          WHERE s2.ServerId=t.ServerId
                                            AND s2.VchCode LIKE 'SRV%'
                                            AND s2.VchCode<>t.VchCode))
                    ORDER BY (CASE WHEN t.VchDate LIKE '__-__-____'
                                  THEN substr(t.VchDate,7,4)||'-'||substr(t.VchDate,4,2)||'-'||substr(t.VchDate,1,2)
                                  ELSE t.VchDate END) DESC, t.rowid DESC
                    LIMIT 300";

                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    _all.Add(new SaleRow
                    {
                        Id          = r.GetInt64(0),
                        InvoiceNo   = r["invoice_no"]?.ToString() ?? "",
                        Date        = r["date"]?.ToString() ?? "",
                        Customer    = r["customer_name"]?.ToString() ?? "",
                        Phone       = r["phone"]?.ToString() ?? "",
                        GrandTotal  = r.IsDBNull(5) ? 0 : r.GetDouble(5),
                        PaidAmount  = r.IsDBNull(6) ? 0 : r.GetDouble(6),
                        DueAmount   = r.IsDBNull(7) ? 0 : r.GetDouble(7),
                        ItemCount   = r.IsDBNull(8) ? 0 : (int)r.GetInt64(8),
                        ItemPreview = r["items_preview"]?.ToString() ?? ""
                    });
                }
            }
            catch { }

            ApplyFilter();

            if (lstSales.Items.Count > 0)
            {
                lstSales.SelectedIndex = 0;
                lstSales.Focus();
            }
            else
                txtSearch.Focus();
        }

        private void ApplyFilter()
        {
            string q = txtSearch.Text.Trim().ToLower();
            _filtered = string.IsNullOrEmpty(q)
                ? new List<SaleRow>(_all)
                : _all.FindAll(s =>
                    s.InvoiceNo.ToLower().Contains(q) ||
                    s.Customer.ToLower().Contains(q)  ||
                    s.Phone.ToLower().Contains(q)     ||
                    s.Date.ToLower().Contains(q)      ||
                    s.ItemPreview.ToLower().Contains(q) ||
                    s.GrandTotal.ToString("N2").Contains(q));

            lblCount.Text = $"{_filtered.Count} sale{(_filtered.Count == 1 ? "" : "s")}";
            int prevSel = lstSales.SelectedIndex;
            lstSales.Items.Clear();

            for (int i = 0; i < _filtered.Count; i++)
                lstSales.Items.Add(BuildRow(i, _filtered[i]));

            if (lstSales.Items.Count > 0)
                lstSales.SelectedIndex = Math.Clamp(prevSel < 0 ? 0 : prevSel, 0, lstSales.Items.Count - 1);

            lblHint.Visibility = string.IsNullOrEmpty(txtSearch.Text) ? Visibility.Visible : Visibility.Collapsed;
            UpdateFooter();
        }

        private FrameworkElement BuildRow(int index, SaleRow s)
        {
            bool hasDue = s.DueAmount > 0.01;

            var root = new Grid { Height = 40 };
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(32) });
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(110) });
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(90) });
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(170) });
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(70) });
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(100) });
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(80) });

            root.Children.Add(Cell(0, (index + 1).ToString(), "#94A3B8", 11, center: true));
            root.Children.Add(Cell(1, s.InvoiceNo, "#1D4ED8", 12.5, bold: true));
            root.Children.Add(Cell(2, s.Date, "#64748B", 12));
            root.Children.Add(Cell(3, s.Customer, "#1E293B", 13, bold: true));
            root.Children.Add(Cell(4,
                string.IsNullOrEmpty(s.ItemPreview) ? "(no items)" : s.ItemPreview,
                "#64748B", 11.5));

            // Item count badge
            var badge = new Border
            {
                Background = new SolidColorBrush(Color.FromRgb(0xDB, 0xEA, 0xFE)),
                CornerRadius = new CornerRadius(4),
                Padding = new Thickness(6, 2, 6, 2),
                HorizontalAlignment = HorizontalAlignment.Center,
                VerticalAlignment = VerticalAlignment.Center
            };
            badge.Child = new TextBlock
            {
                Text = s.ItemCount.ToString(),
                FontSize = 12, FontWeight = FontWeights.Bold,
                Foreground = new SolidColorBrush(Color.FromRgb(0x1D, 0x4E, 0xD8)),
                FontFamily = new FontFamily("Segoe UI")
            };
            Grid.SetColumn(badge, 5);
            root.Children.Add(badge);

            root.Children.Add(Cell(6, $"₹{s.GrandTotal:N2}", "#16A34A", 13, bold: true, right: true));

            // Status
            string statusText = hasDue ? $"Due ₹{s.DueAmount:N2}" : "Paid";
            string statusBg   = hasDue ? "#FEE2E2" : "#DCFCE7";
            string statusFg   = hasDue ? "#DC2626"  : "#16A34A";
            var statusBorder = new Border
            {
                Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString(statusBg)),
                CornerRadius = new CornerRadius(4),
                Padding = new Thickness(6, 2, 6, 2),
                HorizontalAlignment = HorizontalAlignment.Center,
                VerticalAlignment = VerticalAlignment.Center
            };
            statusBorder.Child = new TextBlock
            {
                Text = statusText, FontSize = 10.5, FontWeight = FontWeights.SemiBold,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString(statusFg)),
                FontFamily = new FontFamily("Segoe UI")
            };
            Grid.SetColumn(statusBorder, 7);
            root.Children.Add(statusBorder);

            return root;
        }

        private static TextBlock Cell(int col, string text, string hex, double size,
            bool bold = false, bool right = false, bool center = false)
        {
            var tb = new TextBlock
            {
                Text = text, FontSize = size,
                FontFamily = new FontFamily("Segoe UI"),
                FontWeight = bold ? FontWeights.SemiBold : FontWeights.Normal,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString(hex)),
                VerticalAlignment = VerticalAlignment.Center,
                TextTrimming = TextTrimming.CharacterEllipsis,
                HorizontalAlignment = right ? HorizontalAlignment.Right
                                    : center ? HorizontalAlignment.Center
                                    : HorizontalAlignment.Left
            };
            Grid.SetColumn(tb, col);
            return tb;
        }

        // ═══ FOOTER ═══

        private void UpdateFooter()
        {
            int i = lstSales.SelectedIndex;
            if (i >= 0 && i < _filtered.Count)
            {
                var s = _filtered[i];
                string due = s.DueAmount > 0.01 ? $"  Due: ₹{s.DueAmount:N2}" : "  ✓ Paid";
                lblSelected.Text = $"#{i + 1}  {s.InvoiceNo}  ·  {s.Customer}  ·  ₹{s.GrandTotal:N2}{due}";
            }
            else
                lblSelected.Text = "↑↓ navigate  ·  Enter view detail  ·  Esc close";
        }

        // ═══ GLOBAL KEYBOARD ═══

        private void Window_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            switch (e.Key)
            {
                case Key.Up:
                    if (lstSales.SelectedIndex > 0) lstSales.SelectedIndex--;
                    else if (lstSales.Items.Count > 0) lstSales.SelectedIndex = 0;
                    lstSales.ScrollIntoView(lstSales.SelectedItem);
                    lstSales.Focus();
                    e.Handled = true; break;

                case Key.Down:
                    if (lstSales.SelectedIndex < lstSales.Items.Count - 1) lstSales.SelectedIndex++;
                    lstSales.ScrollIntoView(lstSales.SelectedItem);
                    lstSales.Focus();
                    e.Handled = true; break;

                case Key.Enter:
                    ViewDetail(lstSales.SelectedIndex);
                    e.Handled = true; break;

                case Key.Escape:
                    Close(); e.Handled = true; break;

                default:
                    if (!txtSearch.IsFocused)
                    {
                        bool printable = (e.Key >= Key.A && e.Key <= Key.Z)
                                      || (e.Key >= Key.D0 && e.Key <= Key.D9)
                                      || e.Key == Key.Space;
                        bool noMod = Keyboard.Modifiers == ModifierKeys.None
                                  || Keyboard.Modifiers == ModifierKeys.Shift;
                        if (printable && noMod)
                        {
                            txtSearch.Focus();
                            txtSearch.CaretIndex = txtSearch.Text.Length;
                        }
                    }
                    break;
            }
        }

        private void ViewDetail(int idx)
        {
            if (idx < 0 || idx >= _filtered.Count) return;
            var s = _filtered[idx];
            string temp = System.IO.Path.Combine(System.IO.Path.GetTempPath(), "Sale_" + Math.Abs(s.Id) + "_" + DateTime.Now.Ticks + ".pdf");

            // If Id is negative (from Tran1 rowid*-1), resolve actual sales.id by invoice_no
            long saleId = s.Id;
            if (saleId < 0)
            {
                try
                {
                    var db = new Database.DatabaseService();
                    using var conn = db.GetConnection();
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = "SELECT id FROM sales WHERE (invoice_no=@inv OR sale_no=@inv) AND (del_status IS NULL OR del_status='Live') LIMIT 1";
                    cmd.Parameters.AddWithValue("@inv", s.InvoiceNo);
                    var val = cmd.ExecuteScalar();
                    if (val != null) saleId = Convert.ToInt64(val);
                }
                catch { }
            }

            if (saleId > 0)
                RashanKiDukan.Services.PdfService.GenerateSalePdf(saleId, temp);
            else
                RashanKiDukan.Services.PdfService.GenerateTranPdf(s.InvoiceNo, temp, "SALE INVOICE");
        }

        // ═══ EVENT HANDLERS ═══

        private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e) => ApplyFilter();
        private void LstSales_SelectionChanged(object sender, SelectionChangedEventArgs e) => UpdateFooter();
        private void BtnView_Click(object sender, MouseButtonEventArgs e) => ViewDetail(lstSales.SelectedIndex);
        private void BtnClose_Click(object sender, MouseButtonEventArgs e) => Close();
    }

    public class SaleRow
    {
        public long   Id          { get; set; }
        public string InvoiceNo   { get; set; } = "";
        public string Date        { get; set; } = "";
        public string Customer    { get; set; } = "";
        public string Phone       { get; set; } = "";
        public double GrandTotal  { get; set; }
        public double PaidAmount  { get; set; }
        public double DueAmount   { get; set; }
        public int    ItemCount   { get; set; }
        public string ItemPreview { get; set; } = "";
    }
}
