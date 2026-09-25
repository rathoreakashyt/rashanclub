using System.Collections.Generic;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;

namespace RashanKiDukan.Views
{
    public partial class HoldVoucherPage : UserControl
    {
        private readonly POSPage _pos;
        private readonly List<HeldBill> _bills;

        public HoldVoucherPage(POSPage pos, List<HeldBill> bills)
        {
            InitializeComponent();
            _pos = pos;
            _bills = bills;
            Render();
        }

        private void Render()
        {
            holdList.Children.Clear();
            lblCount.Text = $"{_bills.Count} bill{(_bills.Count == 1 ? "" : "s")}";

            if (_bills.Count == 0)
            {
                var empty = new StackPanel
                {
                    HorizontalAlignment = HorizontalAlignment.Center,
                    Margin = new Thickness(0, 60, 0, 0)
                };
                empty.Children.Add(new TextBlock
                {
                    Text = "\uE894",
                    FontFamily = new FontFamily("Segoe MDL2 Assets"),
                    FontSize = 40,
                    Foreground = new SolidColorBrush(Color.FromRgb(0xD1, 0xD5, 0xDB)),
                    HorizontalAlignment = HorizontalAlignment.Center
                });
                empty.Children.Add(new TextBlock
                {
                    Text = "No held bills",
                    FontSize = 15,
                    FontWeight = FontWeights.SemiBold,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x6B, 0x72, 0x80)),
                    FontFamily = new FontFamily("Inter, Segoe UI"),
                    HorizontalAlignment = HorizontalAlignment.Center,
                    Margin = new Thickness(0, 8, 0, 0)
                });
                empty.Children.Add(new TextBlock
                {
                    Text = "Press F7 on POS to hold a bill",
                    FontSize = 13,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x9C, 0xA3, 0xAF)),
                    FontFamily = new FontFamily("Inter, Segoe UI"),
                    HorizontalAlignment = HorizontalAlignment.Center,
                    Margin = new Thickness(0, 4, 0, 0)
                });
                holdList.Children.Add(empty);
                return;
            }

            for (int i = 0; i < _bills.Count; i++)
            {
                var bill = _bills[i];
                int index = i;

                var card = new Border
                {
                    Background = Brushes.White,
                    BorderBrush = new SolidColorBrush(Color.FromRgb(0xE5, 0xE7, 0xEB)),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(10),
                    Margin = new Thickness(0, 0, 0, 10),
                    Padding = new Thickness(20, 16, 20, 16)
                };

                var grid = new Grid();
                grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                grid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                // Left: info
                var info = new StackPanel();

                var custText = new TextBlock
                {
                    Text = bill.CustomerName,
                    FontSize = 15,
                    FontWeight = FontWeights.SemiBold,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x11, 0x18, 0x27)),
                    FontFamily = new FontFamily("Inter, Segoe UI"),
                    Margin = new Thickness(0, 0, 0, 6)
                };
                info.Children.Add(custText);

                var timeText = new TextBlock
                {
                    Text = $"Held at {bill.HeldAt:hh:mm tt}  \u00B7  {bill.Items.Count} item{(bill.Items.Count == 1 ? "" : "s")}",
                    FontSize = 12,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x9C, 0xA3, 0xAF)),
                    FontFamily = new FontFamily("Inter, Segoe UI"),
                    Margin = new Thickness(0, 0, 0, 8)
                };
                info.Children.Add(timeText);

                int preview = System.Math.Min(3, bill.Items.Count);
                for (int j = 0; j < preview; j++)
                {
                    var it = bill.Items[j];
                    info.Children.Add(new TextBlock
                    {
                        Text = $"  \u2022 {it.ItemName}  \u00D7{it.Qty:N2}  =  \u20B9{it.Amount:N2}",
                        FontSize = 12,
                        Foreground = new SolidColorBrush(Color.FromRgb(0x6B, 0x72, 0x80)),
                        FontFamily = new FontFamily("Inter, Segoe UI"),
                        Margin = new Thickness(0, 1, 0, 1)
                    });
                }
                if (bill.Items.Count > 3)
                    info.Children.Add(new TextBlock
                    {
                        Text = $"  + {bill.Items.Count - 3} more item(s)...",
                        FontSize = 11,
                        Foreground = new SolidColorBrush(Color.FromRgb(0x9C, 0xA3, 0xAF)),
                        FontFamily = new FontFamily("Inter, Segoe UI"),
                        Margin = new Thickness(0, 2, 0, 0)
                    });

                Grid.SetColumn(info, 0);

                // Right: total + buttons
                var right = new StackPanel
                {
                    HorizontalAlignment = HorizontalAlignment.Right,
                    VerticalAlignment = VerticalAlignment.Center,
                    MinWidth = 140
                };

                right.Children.Add(new TextBlock
                {
                    Text = $"\u20B9 {bill.GrandTotal:N2}",
                    FontSize = 20,
                    FontWeight = FontWeights.Bold,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x11, 0x18, 0x27)),
                    FontFamily = new FontFamily("Inter, Segoe UI"),
                    TextAlignment = TextAlignment.Right,
                    Margin = new Thickness(0, 0, 0, 10)
                });

                // Resume button
                var resumeBtn = new Border
                {
                    Background = new SolidColorBrush(Color.FromRgb(0x16, 0xA3, 0x4A)),
                    CornerRadius = new CornerRadius(7),
                    Padding = new Thickness(16, 8, 16, 8),
                    Cursor = System.Windows.Input.Cursors.Hand,
                    Margin = new Thickness(0, 0, 0, 6),
                    HorizontalAlignment = HorizontalAlignment.Right
                };
                resumeBtn.Child = new TextBlock
                {
                    Text = "\u25B6  Resume",
                    FontSize = 13,
                    FontWeight = FontWeights.SemiBold,
                    Foreground = Brushes.White,
                    FontFamily = new FontFamily("Inter, Segoe UI")
                };
                resumeBtn.MouseLeftButtonDown += (s, e) => ResumeBill(index);
                right.Children.Add(resumeBtn);

                // Delete button
                var delBtn = new Border
                {
                    Background = new SolidColorBrush(Color.FromRgb(0xFE, 0xF2, 0xF2)),
                    CornerRadius = new CornerRadius(7),
                    Padding = new Thickness(16, 6, 16, 6),
                    Cursor = System.Windows.Input.Cursors.Hand,
                    HorizontalAlignment = HorizontalAlignment.Right
                };
                delBtn.Child = new TextBlock
                {
                    Text = "Delete",
                    FontSize = 12,
                    FontWeight = FontWeights.SemiBold,
                    Foreground = new SolidColorBrush(Color.FromRgb(0xEF, 0x44, 0x44)),
                    FontFamily = new FontFamily("Inter, Segoe UI")
                };
                delBtn.MouseLeftButtonDown += (s, e) => DeleteBill(index);
                right.Children.Add(delBtn);

                Grid.SetColumn(right, 1);
                grid.Children.Add(info);
                grid.Children.Add(right);
                card.Child = grid;
                holdList.Children.Add(card);
            }
        }

        private void ResumeBill(int index)
        {
            if (index < 0 || index >= _bills.Count) return;
            _pos.RestoreHeldBill(_bills[index]);
            _bills.RemoveAt(index);
            _pos.ShowPOSView();
        }

        private void DeleteBill(int index)
        {
            if (index < 0 || index >= _bills.Count) return;
            var bill = _bills[index];
            if (MessageBox.Show(
                $"Delete held bill for {bill.CustomerName}?\n\u20B9 {bill.GrandTotal:N2} \u2014 {bill.Items.Count} items",
                "Delete Hold", MessageBoxButton.YesNo, MessageBoxImage.Warning) != MessageBoxResult.Yes) return;
            _bills.RemoveAt(index);
            Render();
        }

        private void BtnClearAll_Click(object sender, RoutedEventArgs e)
        {
            if (_bills.Count == 0) return;
            if (MessageBox.Show($"Delete ALL {_bills.Count} held bills?",
                "Clear All", MessageBoxButton.YesNo, MessageBoxImage.Warning) != MessageBoxResult.Yes) return;
            _bills.Clear();
            Render();
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _pos.ShowPOSView();
        }
    }

    public class HeldBill
    {
        public long Id { get; set; } // DB primary key (0 = not yet persisted)
        public string CustomerName { get; set; } = "Walk-in Customer";
        public string CustomerPhone { get; set; } = "";
        public int CustomerId { get; set; } = 1;
        public System.DateTime HeldAt { get; set; } = System.DateTime.Now;
        public List<BusyCartItem> Items { get; set; } = new();
        public double GrandTotal { get; set; }
    }

    /// <summary>DTO for serializing cart items to JSON for held_bills persistence.</summary>
    public class HeldBillItemDto
    {
        public int SrNo { get; set; }
        public string ItemCode { get; set; } = "";
        public string ItemName { get; set; } = "";
        public string HSNCode { get; set; } = "";
        public double MRP { get; set; }
        public double Qty { get; set; }
        public string Unit { get; set; } = "";
        public double ListPrice { get; set; }
        public double Disc { get; set; }
        public double TotDisc { get; set; }
        public double Price { get; set; }
        public double Amount { get; set; }
        public double TaxPerc { get; set; }
        public double TaxableAmount { get; set; }
        public bool HasScheme { get; set; }
        public string AppliedSchemeText { get; set; } = "";
    }
}
