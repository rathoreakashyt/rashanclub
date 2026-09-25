using System;
using System.Collections.Generic;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;

namespace RashanKiDukan.Views
{
    public partial class TierPricingWindow : Window
    {
        private readonly List<TierOption> _options;
        private int _selectedIndex = 0;

        public int SelectedQty { get; private set; } = 0;

        public TierPricingWindow(string schemeTitle, string itemName, double listPrice, List<TierOption> options)
        {
            InitializeComponent();
            _options = options;

            lblSchemeTitle.Text = schemeTitle;
            lblItemName.Text = itemName;
            lblOriginalPrice.Text = $"₹{listPrice:N2}";

            BuildOptions(listPrice);
            SelectOption(0);

            Loaded += (s, e) => { Focus(); };
        }

        private void BuildOptions(double listPrice)
        {
            tierOptionsPanel.Children.Clear();

            for (int i = 0; i < _options.Count; i++)
            {
                var opt = _options[i];
                int idx = i;

                var card = new Border
                {
                    CornerRadius = new CornerRadius(10),
                    Padding = new Thickness(14, 12, 14, 12),
                    Margin = new Thickness(0, 0, 0, 8),
                    Cursor = Cursors.Hand,
                    BorderThickness = new Thickness(2),
                    BorderBrush = new SolidColorBrush(Color.FromRgb(0xE2, 0xE8, 0xF0)),
                    Background = Brushes.White,
                    Tag = idx
                };

                var grid = new Grid();
                grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                grid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                // Left: qty and per-item price
                var left = new StackPanel();
                left.Children.Add(new TextBlock
                {
                    Text = $"{opt.Qty} item{(opt.Qty > 1 ? "s" : "")}",
                    FontSize = 14,
                    FontWeight = FontWeights.Bold,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x1E, 0x29, 0x3B)),
                    FontFamily = new FontFamily("Segoe UI")
                });
                left.Children.Add(new TextBlock
                {
                    Text = $"₹{opt.PerItemPrice:N2}/item",
                    FontSize = 12,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x64, 0x74, 0x8B)),
                    FontFamily = new FontFamily("Segoe UI"),
                    Margin = new Thickness(0, 2, 0, 0)
                });

                // Right: total price and saving
                var right = new StackPanel { HorizontalAlignment = HorizontalAlignment.Right };
                right.Children.Add(new TextBlock
                {
                    Text = $"₹{opt.TotalPrice:N2}",
                    FontSize = 16,
                    FontWeight = FontWeights.Bold,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0xA3, 0x4A)),
                    FontFamily = new FontFamily("Segoe UI"),
                    HorizontalAlignment = HorizontalAlignment.Right
                });
                double saving = (listPrice * opt.Qty) - opt.TotalPrice;
                if (saving > 0)
                {
                    right.Children.Add(new TextBlock
                    {
                        Text = $"Save ₹{saving:N0}",
                        FontSize = 11,
                        FontWeight = FontWeights.SemiBold,
                        Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0xA3, 0x4A)),
                        FontFamily = new FontFamily("Segoe UI"),
                        HorizontalAlignment = HorizontalAlignment.Right,
                        Margin = new Thickness(0, 2, 0, 0)
                    });
                }

                Grid.SetColumn(left, 0);
                Grid.SetColumn(right, 1);
                grid.Children.Add(left);
                grid.Children.Add(right);

                card.Child = grid;
                card.MouseLeftButtonDown += (s, e) =>
                {
                    if (s is Border b && b.Tag is int ti) SelectOption(ti);
                };

                tierOptionsPanel.Children.Add(card);
            }
        }

        private void SelectOption(int index)
        {
            if (index < 0 || index >= _options.Count) return;
            _selectedIndex = index;
            var opt = _options[index];
            SelectedQty = opt.Qty;

            // Update visual selection
            for (int i = 0; i < tierOptionsPanel.Children.Count; i++)
            {
                if (tierOptionsPanel.Children[i] is Border card)
                {
                    bool sel = i == index;
                    card.Background = new SolidColorBrush(sel ? Color.FromRgb(0xEE, 0xF2, 0xFF) : Colors.White);
                    card.BorderBrush = new SolidColorBrush(sel ? Color.FromRgb(0x4F, 0x46, 0xE5) : Color.FromRgb(0xE2, 0xE8, 0xF0));
                }
            }

            // Update summary
            summaryBorder.Visibility = Visibility.Visible;
            lblSummary.Text = $"{opt.Qty} item → Total ₹{opt.TotalPrice:N2}";
            double saving = (opt.OriginalTotal) - opt.TotalPrice;
            lblSaving.Text = saving > 0 ? $"You save ₹{saving:N0} on this purchase!" : "";
        }

        protected override void OnKeyDown(KeyEventArgs e)
        {
            base.OnKeyDown(e);
            switch (e.Key)
            {
                case Key.Up:
                    SelectOption(Math.Max(0, _selectedIndex - 1));
                    e.Handled = true; break;
                case Key.Down:
                    SelectOption(Math.Min(_options.Count - 1, _selectedIndex + 1));
                    e.Handled = true; break;
                case Key.Enter:
                    DialogResult = true;
                    Close();
                    e.Handled = true; break;
                case Key.Escape:
                    Close();
                    e.Handled = true; break;
            }
        }

        private void BtnApply_Click(object sender, MouseButtonEventArgs e)
        {
            DialogResult = true;
            Close();
        }

        private void BtnCancel_Click(object sender, MouseButtonEventArgs e) => Close();
        private void BtnClose_Click(object sender, RoutedEventArgs e) => Close();
    }

    public class TierOption
    {
        public int Qty { get; set; }
        public double TotalPrice { get; set; }
        public double PerItemPrice { get; set; }
        public double OriginalTotal { get; set; }
    }
}
