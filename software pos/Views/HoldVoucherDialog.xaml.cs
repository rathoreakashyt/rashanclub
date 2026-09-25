using System.Collections.Generic;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class HoldVoucherDialog : Window
    {
        private readonly List<HeldBill> _bills;
        private readonly Database.DatabaseService? _db;
        private List<HeldBill> _filtered = new();
        private int _actionFocus = 0; // 0=Resume, 1=Delete
        public HeldBill? ResumedBill { get; private set; }

        public HoldVoucherDialog(List<HeldBill> bills, Database.DatabaseService? db = null)
        {
            InitializeComponent();
            _bills = bills;
            _db = db;

            // Global key handler — works regardless of which control has focus
            PreviewKeyDown += Window_PreviewKeyDown;

            Loaded += (s, e) =>
            {
                Refresh();
                // Start with list focused so arrow keys work immediately
                if (lstHolds.Items.Count > 0)
                {
                    lstHolds.SelectedIndex = 0;
                    lstHolds.Focus();
                }
                else
                {
                    txtSearch.Focus();
                }
            };
        }

        // ═══ GLOBAL KEY HANDLER ═══

        private void Window_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            switch (e.Key)
            {
                // ── Navigation ──
                case Key.Up:
                    if (lstHolds.SelectedIndex > 0)
                        lstHolds.SelectedIndex--;
                    else if (lstHolds.Items.Count > 0)
                        lstHolds.SelectedIndex = 0;
                    lstHolds.ScrollIntoView(lstHolds.SelectedItem);
                    lstHolds.Focus();
                    e.Handled = true;
                    break;

                case Key.Down:
                    if (lstHolds.SelectedIndex < lstHolds.Items.Count - 1)
                        lstHolds.SelectedIndex++;
                    else if (lstHolds.Items.Count > 0)
                        lstHolds.SelectedIndex = lstHolds.Items.Count - 1;
                    lstHolds.ScrollIntoView(lstHolds.SelectedItem);
                    lstHolds.Focus();
                    e.Handled = true;
                    break;

                // ── Action switch ──
                case Key.Left:
                    _actionFocus = 0;
                    UpdateFooter();
                    e.Handled = true;
                    break;

                case Key.Right:
                    _actionFocus = 1;
                    UpdateFooter();
                    e.Handled = true;
                    break;

                // ── Execute action ──
                case Key.Enter:
                    int idx = lstHolds.SelectedIndex < 0 ? 0 : lstHolds.SelectedIndex;
                    if (_actionFocus == 0) DoResume(idx);
                    else DoDelete(idx);
                    e.Handled = true;
                    break;

                case Key.Delete:
                    DoDelete(lstHolds.SelectedIndex < 0 ? 0 : lstHolds.SelectedIndex);
                    e.Handled = true;
                    break;

                // ── Close ──
                case Key.Escape:
                    DialogResult = false;
                    Close();
                    e.Handled = true;
                    break;

                // ── Typing → go to search ──
                default:
                    // Only if NOT already in search box and it's a printable key
                    if (!txtSearch.IsFocused)
                    {
                        bool isLetter = e.Key >= Key.A && e.Key <= Key.Z;
                        bool isDigit  = e.Key >= Key.D0 && e.Key <= Key.D9
                                     || e.Key >= Key.NumPad0 && e.Key <= Key.NumPad9;
                        bool isSpace  = e.Key == Key.Space;
                        bool noMod    = Keyboard.Modifiers == ModifierKeys.None
                                     || Keyboard.Modifiers == ModifierKeys.Shift;

                        if ((isLetter || isDigit || isSpace) && noMod)
                        {
                            txtSearch.Focus();
                            txtSearch.CaretIndex = txtSearch.Text.Length;
                            // Don't mark handled — let the char go into the TextBox
                        }
                    }
                    break;
            }
        }

        // ═══ BUILD LIST ═══

        private void Refresh()
        {
            string q = txtSearch.Text.Trim().ToLower();
            _filtered = string.IsNullOrEmpty(q)
                ? new List<HeldBill>(_bills)
                : _bills.Where(b =>
                    b.CustomerName.ToLower().Contains(q) ||
                    b.CustomerPhone.ToLower().Contains(q) ||
                    b.Items.Any(i => i.ItemName.ToLower().Contains(q))
                ).ToList();

            lblCount.Text = $"{_filtered.Count} bill{(_filtered.Count == 1 ? "" : "s")}";
            int prevSel = lstHolds.SelectedIndex;

            lstHolds.Items.Clear();
            for (int i = 0; i < _filtered.Count; i++)
                lstHolds.Items.Add(BuildRow(i, _filtered[i]));

            if (lstHolds.Items.Count > 0)
                lstHolds.SelectedIndex = Math.Clamp(prevSel < 0 ? 0 : prevSel, 0, lstHolds.Items.Count - 1);

            lblSearchHint.Visibility = string.IsNullOrEmpty(txtSearch.Text)
                ? Visibility.Visible : Visibility.Collapsed;

            UpdateFooter();
        }

        private FrameworkElement BuildRow(int index, HeldBill b)
        {
            string preview = b.Items.Count > 0
                ? string.Join(", ", b.Items.Take(4).Select(x => x.ItemName)) +
                  (b.Items.Count > 4 ? $"  +{b.Items.Count - 4} more" : "")
                : "(no items)";

            var root = new Grid { Height = 40 };
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(32) });
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(70) });
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(150) });
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(110) });
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(55) });
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(90) });
            root.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(130) });

            root.Children.Add(Cell(0, (index + 1).ToString(), "#94A3B8", 11, center: true));
            root.Children.Add(Cell(1, b.HeldAt.ToString("hh:mm tt"), "#64748B", 12));
            root.Children.Add(Cell(2, b.CustomerName, "#1E293B", 13, bold: true));
            root.Children.Add(Cell(3, string.IsNullOrEmpty(b.CustomerPhone) ? "—" : b.CustomerPhone, "#475569", 12));
            root.Children.Add(Cell(4, preview, "#64748B", 11.5));

            // Count badge
            var countBorder = new Border
            {
                Background = new SolidColorBrush(Color.FromRgb(0xED, 0xE9, 0xFE)),
                CornerRadius = new CornerRadius(4),
                Padding = new Thickness(6, 2, 6, 2),
                HorizontalAlignment = HorizontalAlignment.Center,
                VerticalAlignment = VerticalAlignment.Center
            };
            countBorder.Child = new TextBlock
            {
                Text = b.Items.Count.ToString(),
                FontSize = 12, FontWeight = FontWeights.Bold,
                Foreground = new SolidColorBrush(Color.FromRgb(0x7C, 0x3A, 0xED)),
                FontFamily = new FontFamily("Segoe UI")
            };
            Grid.SetColumn(countBorder, 5);
            root.Children.Add(countBorder);

            root.Children.Add(Cell(6, $"₹{b.GrandTotal:N2}", "#16A34A", 13, bold: true, right: true));

            // Action buttons
            var btnPanel = new StackPanel
            {
                Orientation = Orientation.Horizontal,
                HorizontalAlignment = HorizontalAlignment.Center,
                VerticalAlignment = VerticalAlignment.Center
            };
            btnPanel.Children.Add(ActionBtn("▶ Resume", "#DCFCE7", "#166534", index, isResume: true));
            btnPanel.Children.Add(ActionBtn("✕ Delete", "#FEE2E2", "#991B1B", index, isResume: false));
            Grid.SetColumn(btnPanel, 7);
            root.Children.Add(btnPanel);

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

        private Border ActionBtn(string text, string bg, string fg, int index, bool isResume)
        {
            var btn = new Border
            {
                Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString(bg)),
                CornerRadius = new CornerRadius(5),
                Padding = new Thickness(8, 3, 8, 3),
                Margin = new Thickness(2, 0, 2, 0),
                Cursor = Cursors.Hand,
                VerticalAlignment = VerticalAlignment.Center
            };
            btn.Child = new TextBlock
            {
                Text = text, FontSize = 11.5, FontWeight = FontWeights.SemiBold,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString(fg)),
                FontFamily = new FontFamily("Segoe UI")
            };
            btn.MouseLeftButtonDown += (s, e) =>
            {
                e.Handled = true;
                if (isResume) DoResume(index);
                else DoDelete(index);
            };
            return btn;
        }

        // ═══ ACTIONS ═══

        private void DoResume(int idx)
        {
            if (idx < 0 || idx >= _filtered.Count) return;
            var bill = _filtered[idx];
            _bills.Remove(bill);
            ResumedBill = bill;
            DialogResult = true;
            Close();
        }

        private void DoDelete(int idx)
        {
            if (idx < 0 || idx >= _filtered.Count) return;
            var bill = _filtered[idx];
            if (MessageBox.Show(
                $"Delete held bill?\n\nCustomer: {bill.CustomerName}\nTotal: ₹{bill.GrandTotal:N2}  |  {bill.Items.Count} items",
                "Delete Hold", MessageBoxButton.YesNo, MessageBoxImage.Warning) != MessageBoxResult.Yes) return;
            _bills.Remove(bill);
            // Delete from DB
            if (_db != null && bill.Id > 0)
            {
                try
                {
                    using var conn = _db.GetConnection();
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = "DELETE FROM held_bills WHERE id = @id";
                    cmd.Parameters.AddWithValue("@id", bill.Id);
                    cmd.ExecuteNonQuery();
                }
                catch { }
            }
            int next = idx;
            Refresh();
            if (lstHolds.Items.Count > 0)
            {
                lstHolds.SelectedIndex = Math.Clamp(next, 0, lstHolds.Items.Count - 1);
                lstHolds.Focus();
            }
        }

        private void UpdateFooter()
        {
            int i = lstHolds.SelectedIndex;
            if (i >= 0 && i < _filtered.Count)
            {
                var b = _filtered[i];
                string action = _actionFocus == 0
                    ? "← [▶ Resume]  →Delete"
                    : "←Resume  [✕ Delete] →";
                lblSelected.Text = $"#{i + 1}  {b.CustomerName}  ·  ₹{b.GrandTotal:N2}  ·  {b.Items.Count} items    {action}";
            }
            else
            {
                lblSelected.Text = "↑↓ navigate  ·  Enter resume  ·  Del delete  ·  ← → switch action  ·  Type to search";
            }
        }

        // ═══ EVENT HANDLERS ═══

        private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e) => Refresh();

        private void LstHolds_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            _actionFocus = 0;
            UpdateFooter();
            if (lstHolds.SelectedItem != null)
                lstHolds.ScrollIntoView(lstHolds.SelectedItem);
        }

        private void Header_Click(object sender, RoutedEventArgs e) { }
        private void BtnResume_Click(object sender, MouseButtonEventArgs e) => DoResume(lstHolds.SelectedIndex);
        private void BtnDelete_Click(object sender, MouseButtonEventArgs e) => DoDelete(lstHolds.SelectedIndex);
        private void BtnClose_Click(object sender, MouseButtonEventArgs e) { DialogResult = false; Close(); }

        // Dummy handlers kept for XAML compatibility
        private void TxtSearch_KeyDown(object sender, KeyEventArgs e) { }
        private void LstHolds_KeyDown(object sender, KeyEventArgs e) { }
    }
}
