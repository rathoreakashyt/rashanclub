using System;
using System.Collections.Generic;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class CouponWindow : Window
    {
        private readonly DatabaseService _db;
        private readonly List<BusyCartItem> _cart;
        private readonly List<CouponInfo> _allCoupons = new();
        private readonly List<Border> _cards = new();
        private int _selectedIndex = -1;

        public SchemeApplyResult? Result { get; private set; }

        public CouponWindow(DatabaseService db, List<BusyCartItem> cart)
        {
            InitializeComponent();
            _db = db;
            _cart = cart;
            LoadCoupons();
            BuildList(_allCoupons);
            Loaded += (s, e) => { txtCouponCode.Focus(); };
        }

        private void LoadCoupons()
        {
            try
            {
                string today = DateTime.Today.ToString("yyyy-MM-dd");
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT Id, IFNULL(title,''), IFNULL(coupon_code,''), IFNULL(discount,''),
                        IFNULL(discount_value,0), IFNULL(discount_type,''), IFNULL(min_purchase_amount,0),
                        IFNULL(max_discount_amount,0), IFNULL(applicable_items,''), IFNULL(scheme_basis,'item')
                    FROM promotions
                    WHERE del_status='Live' AND IFNULL(type,'')='2'
                      AND IFNULL(coupon_code,'') != ''
                      AND (start_date IS NULL OR start_date='' OR start_date <= @d)
                      AND (end_date IS NULL OR end_date='' OR end_date >= @d)";
                cmd.Parameters.AddWithValue("@d", today);
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    var discount = r.GetString(3);
                    var discountValue = r.GetDouble(4);
                    var discountType = r.GetString(5);
                    string kind = "flat";
                    double amt = discountValue;
                    if (discount.Trim().EndsWith("%") || discountType.ToLower().Contains("percent"))
                    {
                        kind = "percentage";
                        if (discount.Trim().EndsWith("%"))
                            double.TryParse(discount.Trim().TrimEnd('%'), out amt);
                    }
                    else if (double.TryParse(discount.Trim(), out double parsed) && parsed > 0)
                    {
                        amt = parsed;
                    }

                    _allCoupons.Add(new CouponInfo
                    {
                        Id = r.GetInt64(0),
                        Title = r.GetString(1),
                        Code = r.GetString(2),
                        DiscountKind = kind,
                        DiscountAmt = amt,
                        MinPurchase = r.GetDouble(6),
                        MaxDiscount = r.GetDouble(7),
                        ApplicableItems = r.GetString(8),
                        SchemeBasis = r.GetString(9)
                    });
                }
            }
            catch { }
        }

        private void BuildList(List<CouponInfo> coupons)
        {
            couponListPanel.Children.Clear();
            _cards.Clear();
            _selectedIndex = -1;

            if (coupons.Count == 0)
            {
                couponListPanel.Children.Add(new TextBlock
                {
                    Text = "Koi coupon available nahi hai",
                    FontSize = 12, Foreground = new SolidColorBrush(Color.FromRgb(0x94, 0xA3, 0xB8)),
                    FontFamily = new FontFamily("Segoe UI"),
                    HorizontalAlignment = HorizontalAlignment.Center,
                    Margin = new Thickness(0, 16, 0, 16)
                });
                return;
            }

            double subtotal = _cart.Sum(c => c.Amount);
            int firstApplicable = -1;

            for (int i = 0; i < coupons.Count; i++)
            {
                var c = coupons[i];
                int idx = i;

                // Check if coupon is applicable
                bool isApplicable = true;
                string reason = "";
                if (c.MinPurchase > 0 && subtotal < c.MinPurchase)
                {
                    isApplicable = false;
                    reason = $"Min ₹{c.MinPurchase:N0} (abhi ₹{subtotal:N0})";
                }

                if (firstApplicable < 0 && isApplicable) firstApplicable = i;

                var card = new Border
                {
                    CornerRadius = new CornerRadius(8),
                    Padding = new Thickness(12, 10, 12, 10),
                    Margin = new Thickness(0, 0, 0, 6),
                    Cursor = isApplicable ? Cursors.Hand : Cursors.Arrow,
                    BorderThickness = new Thickness(1.5),
                    BorderBrush = new SolidColorBrush(isApplicable
                        ? Color.FromRgb(0xBB, 0xF7, 0xD0)  // green border
                        : Color.FromRgb(0xE2, 0xE8, 0xF0)), // grey border
                    Background = new SolidColorBrush(isApplicable
                        ? Color.FromRgb(0xF0, 0xFD, 0xF4)  // light green bg
                        : Color.FromRgb(0xF8, 0xFA, 0xFC)), // grey bg
                    Opacity = isApplicable ? 1.0 : 0.6,
                    Tag = idx
                };

                var grid = new Grid();
                grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                grid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                // Left: code + title
                var left = new StackPanel();
                left.Children.Add(new TextBlock
                {
                    Text = c.Code,
                    FontSize = 14, FontWeight = FontWeights.Bold,
                    Foreground = new SolidColorBrush(isApplicable
                        ? Color.FromRgb(0x0F, 0x76, 0x6E)
                        : Color.FromRgb(0x94, 0xA3, 0xB8)),
                    FontFamily = new FontFamily("Segoe UI, Consolas")
                });
                if (!string.IsNullOrWhiteSpace(c.Title))
                    left.Children.Add(new TextBlock
                    {
                        Text = c.Title,
                        FontSize = 11.5,
                        Foreground = new SolidColorBrush(isApplicable
                            ? Color.FromRgb(0x64, 0x74, 0x8B)
                            : Color.FromRgb(0xBD, 0xC5, 0xD1)),
                        FontFamily = new FontFamily("Segoe UI"), Margin = new Thickness(0, 2, 0, 0)
                    });
                if (!isApplicable && !string.IsNullOrEmpty(reason))
                    left.Children.Add(new TextBlock
                    {
                        Text = $"⚠ {reason}",
                        FontSize = 10.5,
                        Foreground = new SolidColorBrush(Color.FromRgb(0xD9, 0x77, 0x06)),
                        FontFamily = new FontFamily("Segoe UI"), Margin = new Thickness(0, 2, 0, 0)
                    });

                // Right: discount info
                var right = new StackPanel { HorizontalAlignment = HorizontalAlignment.Right, VerticalAlignment = VerticalAlignment.Center };
                string discText = c.DiscountKind == "percentage" ? $"{c.DiscountAmt:0.##}% Off" : $"₹{c.DiscountAmt:N0} Off";
                right.Children.Add(new TextBlock
                {
                    Text = discText,
                    FontSize = 13, FontWeight = FontWeights.Bold,
                    Foreground = new SolidColorBrush(isApplicable
                        ? Color.FromRgb(0x16, 0xA3, 0x4A)
                        : Color.FromRgb(0xBD, 0xC5, 0xD1)),
                    FontFamily = new FontFamily("Segoe UI"),
                    HorizontalAlignment = HorizontalAlignment.Right
                });
                if (isApplicable)
                    right.Children.Add(new TextBlock
                    {
                        Text = "✔ Applicable",
                        FontSize = 10, FontWeight = FontWeights.SemiBold,
                        Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0xA3, 0x4A)),
                        FontFamily = new FontFamily("Segoe UI"),
                        HorizontalAlignment = HorizontalAlignment.Right,
                        Margin = new Thickness(0, 2, 0, 0)
                    });

                Grid.SetColumn(left, 0);
                Grid.SetColumn(right, 1);
                grid.Children.Add(left);
                grid.Children.Add(right);
                card.Child = grid;

                card.MouseLeftButtonDown += (s, e) =>
                {
                    if (s is Border b && b.Tag is int ti) { SelectIndex(ti); ApplySelected(); }
                };

                _cards.Add(card);
                couponListPanel.Children.Add(card);
            }

            if (firstApplicable >= 0) SelectIndex(firstApplicable);
        }

        private void SelectIndex(int index)
        {
            if (index < 0 || index >= _cards.Count) return;
            _selectedIndex = index;
            for (int i = 0; i < _cards.Count; i++)
            {
                bool sel = i == index;
                _cards[i].Background = new SolidColorBrush(sel ? Color.FromRgb(0xF0, 0xFD, 0xFA) : Colors.White);
                _cards[i].BorderBrush = new SolidColorBrush(sel ? Color.FromRgb(0x0F, 0x76, 0x6E) : Color.FromRgb(0xE2, 0xE8, 0xF0));
            }
        }

        private void TxtCouponCode_TextChanged(object sender, TextChangedEventArgs e)
        {
            string query = txtCouponCode.Text.Trim().ToLower();
            if (string.IsNullOrEmpty(query))
            {
                BuildList(_allCoupons);
                return;
            }
            var filtered = _allCoupons.Where(c =>
                c.Code.ToLower().Contains(query) ||
                c.Title.ToLower().Contains(query)).ToList();
            BuildList(filtered);
        }

        private void ApplySelected()
        {
            // Try typed code first
            string typed = txtCouponCode.Text.Trim();
            CouponInfo? coupon = null;

            if (!string.IsNullOrEmpty(typed))
                coupon = _allCoupons.FirstOrDefault(c =>
                    string.Equals(c.Code.Trim(), typed, StringComparison.OrdinalIgnoreCase));

            // Fallback to selected from list
            if (coupon == null && _selectedIndex >= 0 && _selectedIndex < _allCoupons.Count)
            {
                var filtered = GetFilteredList();
                if (_selectedIndex < filtered.Count)
                    coupon = filtered[_selectedIndex];
            }

            if (coupon == null)
            {
                lblStatus.Text = "❌ Invalid coupon code";
                lblStatus.Foreground = new SolidColorBrush(Color.FromRgb(0xDC, 0x26, 0x26));
                return;
            }

            // Check min purchase
            double subtotal = _cart.Sum(c => c.Amount);
            if (coupon.MinPurchase > 0 && subtotal < coupon.MinPurchase)
            {
                lblStatus.Text = $"❌ Min purchase ₹{coupon.MinPurchase:N0} chahiye (abhi ₹{subtotal:N0})";
                lblStatus.Foreground = new SolidColorBrush(Color.FromRgb(0xDC, 0x26, 0x26));
                return;
            }

            // Build result
            string title = !string.IsNullOrWhiteSpace(coupon.Title) ? coupon.Title : coupon.Code;
            Result = new SchemeApplyResult
            {
                Title = $"Coupon: {title}",
                Benefit = coupon.DiscountKind == "percentage" ? $"{coupon.DiscountAmt}% Off" : $"₹{coupon.DiscountAmt} Off",
                PromoType = "2",
                DiscountKind = coupon.DiscountKind,
                DiscountAmt = coupon.DiscountAmt,
                MaxDiscount = coupon.MaxDiscount,
                ApplicableItems = coupon.ApplicableItems,
                ItemCodes = _cart.Where(c => !string.IsNullOrEmpty(c.ItemCode)).Select(c => c.ItemCode).ToList()
            };

            DialogResult = true;
            Close();
        }

        private List<CouponInfo> GetFilteredList()
        {
            string query = txtCouponCode.Text.Trim().ToLower();
            if (string.IsNullOrEmpty(query)) return _allCoupons;
            return _allCoupons.Where(c =>
                c.Code.ToLower().Contains(query) || c.Title.ToLower().Contains(query)).ToList();
        }

        protected override void OnKeyDown(KeyEventArgs e)
        {
            base.OnKeyDown(e);
            var filtered = GetFilteredList();
            switch (e.Key)
            {
                case Key.Up:
                    if (filtered.Count > 0)
                        SelectIndex(Math.Max(0, _selectedIndex - 1));
                    e.Handled = true; break;
                case Key.Down:
                    if (filtered.Count > 0)
                        SelectIndex(Math.Min(filtered.Count - 1, _selectedIndex + 1));
                    e.Handled = true; break;
                case Key.Enter:
                    ApplySelected();
                    e.Handled = true; break;
                case Key.Escape:
                    Close();
                    e.Handled = true; break;
            }
        }

        private void BtnApply_Click(object sender, MouseButtonEventArgs e) => ApplySelected();
        private void BtnClose_Click(object sender, RoutedEventArgs e) => Close();
    }

    public class CouponInfo
    {
        public long Id;
        public string Title = "", Code = "", DiscountKind = "flat", ApplicableItems = "", SchemeBasis = "item";
        public double DiscountAmt, MinPurchase, MaxDiscount;
    }
}
