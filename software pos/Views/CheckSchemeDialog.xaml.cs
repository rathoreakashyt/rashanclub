using System;
using System.Collections.Generic;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class CheckSchemeDialog : Window
    {
        private readonly List<SchemeCheckRow> _rows;
        private readonly List<Border> _cards = new();
        private SchemeApplyResult? _result;
        private int _selRow = -1;
        private int _selBtn = 0; // 0 = Apply, 1 = Quit

        private CheckSchemeDialog(List<SchemeCheckRow> rows)
        {
            InitializeComponent();
            _rows = rows;
            Loaded += (s, e) => { Focus(); };
            BuildView(rows);
            SelectRow(rows.FindIndex(r => r.StatusOk));
            UpdateBtnSelection();
        }

        /// <summary>Cart ke products par applicable schemes ka popup dikhao. Apply par result return hota hai.</summary>
        public static SchemeApplyResult? Show(DatabaseService db, List<BusyCartItem> cart, Window owner, List<string>? appliedTitles = null)
        {
            var rows = BuildRows(db, cart, appliedTitles ?? new List<string>());
            var dlg = new CheckSchemeDialog(rows) { Owner = owner };
            return dlg.ShowDialog() == true ? dlg._result : null;
        }

        /// <summary>
        /// Silent auto-apply — dialog khole bina pehli applicable scheme return karta hai.
        /// Tier pricing wali schemes bhi handle hoti hain (lowest tier auto-select).
        /// </summary>
        public static SchemeApplyResult? AutoApply(DatabaseService db, List<BusyCartItem> cart, List<string> appliedTitles)
        {
            var rows = BuildRows(db, cart, appliedTitles);
            var applicable = rows.FirstOrDefault(r => r.StatusOk && r.StatusText != "ALREADY APPLIED");
            if (applicable == null) return null;
            return new SchemeApplyResult
            {
                Title        = applicable.Title,
                Benefit      = applicable.Benefit,
                PromoType    = applicable.PromoType,
                DiscountKind = applicable.DiscountKind,
                DiscountAmt  = applicable.DiscountAmt,
                ItemCodes    = applicable.AppliedItemCodes.ToList(),
                FreeItem     = applicable.FreeItem,
                FreeQty      = applicable.FreeQty,
                TierPricing  = applicable.TierPricing,
                BuyItemPrice = applicable.BuyItemPrice
            };
        }

        private void BuildView(List<SchemeCheckRow> rows)
        {
            rowsPanel.Children.Clear();

            if (rows.Count == 0)
            {
                lblSummary.Text = "Cart me abhi koi scheme applicable nahi mili";
                rowsPanel.Children.Add(new Border
                {
                    Background = new SolidColorBrush(Color.FromRgb(0xFF, 0xFB, 0xEB)),
                    CornerRadius = new CornerRadius(8),
                    BorderBrush = new SolidColorBrush(Color.FromRgb(0xFD, 0xE6, 0x8A)),
                    BorderThickness = new Thickness(1),
                    Padding = new Thickness(16, 24, 16, 24),
                    Child = new StackPanel
                    {
                        Children =
                        {
                            new TextBlock
                            {
                                Text = "Cart me koi scheme applicable nahi mili",
                                FontSize = 14, FontWeight = FontWeights.Bold,
                                Foreground = new SolidColorBrush(Color.FromRgb(0x92, 0x40, 0x0E)),
                                FontFamily = new FontFamily("Segoe UI"),
                                HorizontalAlignment = HorizontalAlignment.Center
                            },
                            new TextBlock
                            {
                                Text = "Pehle products cart me add karein — jo items par scheme (promotion) active hogi, wo yahan dikhegi.",
                                FontSize = 12,
                                Foreground = new SolidColorBrush(Color.FromRgb(0x92, 0x40, 0x0E)),
                                FontFamily = new FontFamily("Segoe UI"),
                                TextWrapping = TextWrapping.Wrap,
                                HorizontalAlignment = HorizontalAlignment.Center,
                                Margin = new Thickness(0, 6, 0, 0)
                            }
                        }
                    }
                });
                return;
            }

            int applicable = rows.Count(r => r.StatusOk);
            int alreadyApplied = rows.Count(r => r.StatusText == "ALREADY APPLIED");
            if (alreadyApplied > 0 && applicable > 0)
                lblSummary.Text = $"Cart me {rows.Count} scheme mili — {applicable} applicable, {alreadyApplied} already applied";
            else if (alreadyApplied > 0 && applicable == 0)
                lblSummary.Text = $"Cart me {rows.Count} scheme mili — {alreadyApplied} already applied, koi nayi scheme applicable nahi";
            else if (applicable > 0)
                lblSummary.Text = $"Cart me {rows.Count} scheme mili — {applicable} abhi applicable";
            else
                lblSummary.Text = $"Cart ke items par {rows.Count} scheme hai, lekin abhi koi applicable nahi (dates / min purchase / qty check karein)";

            for (int i = 0; i < rows.Count; i++)
            {
                var card = BuildRowCard(rows[i], i);
                _cards.Add(card);
                rowsPanel.Children.Add(card);
            }
        }

        private Border BuildRowCard(SchemeCheckRow row, int index)
        {
            var title = new TextBlock
            {
                Text = row.Title,
                FontSize = 14,
                FontWeight = FontWeights.Bold,
                Foreground = new SolidColorBrush(Color.FromRgb(0x1E, 0x29, 0x3B)),
                FontFamily = new FontFamily("Segoe UI"),
                VerticalAlignment = VerticalAlignment.Center,
                TextWrapping = TextWrapping.Wrap
            };

            var pillColor = row.StatusText == "ALREADY APPLIED"
                ? Color.FromRgb(0x64, 0x74, 0x8B) // Slate grey for already applied
                : row.StatusOk
                ? Color.FromRgb(0x16, 0xA3, 0x4A)
                : row.StatusWarn
                    ? Color.FromRgb(0xD9, 0x77, 0x06)
                    : Color.FromRgb(0xDC, 0x26, 0x26);

            var pill = new Border
            {
                Background = new SolidColorBrush(pillColor),
                CornerRadius = new CornerRadius(4),
                Padding = new Thickness(8, 3, 8, 3),
                VerticalAlignment = VerticalAlignment.Center,
                Margin = new Thickness(10, 0, 0, 0),
                Child = new TextBlock
                {
                    Text = row.StatusText,
                    FontSize = 11,
                    FontWeight = FontWeights.Bold,
                    Foreground = Brushes.White,
                    FontFamily = new FontFamily("Segoe UI")
                }
            };

            var head = new Grid { Margin = new Thickness(0, 0, 0, 6) };
            head.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            head.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
            Grid.SetColumn(title, 0);
            Grid.SetColumn(pill, 1);
            head.Children.Add(title);
            head.Children.Add(pill);

            var body = new StackPanel();
            if (!string.IsNullOrEmpty(row.ItemsLine))
                body.Children.Add(new TextBlock
                {
                    Text = row.ItemsLine,
                    FontSize = 12,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x64, 0x74, 0x8B)),
                    FontFamily = new FontFamily("Segoe UI"),
                    TextWrapping = TextWrapping.Wrap,
                    Margin = new Thickness(0, 0, 0, 4)
                });
            body.Children.Add(new TextBlock
            {
                Text = row.Benefit,
                FontSize = 13,
                FontWeight = FontWeights.SemiBold,
                Foreground = new SolidColorBrush(Color.FromRgb(0x0F, 0x76, 0x6E)),
                FontFamily = new FontFamily("Segoe UI"),
                TextWrapping = TextWrapping.Wrap
            });
            if (!string.IsNullOrEmpty(row.Note))
                body.Children.Add(new TextBlock
                {
                    Text = row.Note,
                    FontSize = 11.5,
                    Foreground = row.StatusWarn
                        ? new SolidColorBrush(Color.FromRgb(0x92, 0x40, 0x0E))
                        : new SolidColorBrush(Color.FromRgb(0x94, 0xA3, 0xB8)),
                    FontFamily = new FontFamily("Segoe UI"),
                    TextWrapping = TextWrapping.Wrap,
                    Margin = new Thickness(0, 4, 0, 0)
                });

            var card = new StackPanel();
            card.Children.Add(head);
            card.Children.Add(body);

            var box = new Border
            {
                Background = Brushes.White,
                CornerRadius = new CornerRadius(8),
                BorderBrush = new SolidColorBrush(Color.FromRgb(0xE2, 0xE8, 0xF0)),
                BorderThickness = new Thickness(1),
                Padding = new Thickness(14, 12, 14, 12),
                Margin = new Thickness(0, 0, 0, 10),
                Child = card,
                Cursor = Cursors.Hand,
                Tag = index
            };
            box.MouseLeftButtonDown += (s, e) => { if (s is Border b && b.Tag is int i) SelectRow(i); };
            return box;
        }

        // ═══════════ SELECTION & KEYBOARD NAV ═══════════

        private void SelectRow(int index)
        {
            _selRow = index;
            for (int i = 0; i < _cards.Count; i++)
            {
                var c = _cards[i];
                bool sel = i == index;
                c.BorderBrush = new SolidColorBrush(sel ? Color.FromRgb(0x0F, 0x76, 0x6E) : Color.FromRgb(0xE2, 0xE8, 0xF0));
                c.BorderThickness = new Thickness(sel ? 2 : 1);
                c.Background = new SolidColorBrush(sel ? Color.FromRgb(0xF0, 0xFD, 0xFA) : Colors.White);
                if (sel) c.BringIntoView();
            }
            UpdateBtnSelection();
        }

        private void UpdateBtnSelection()
        {
            var teal = new SolidColorBrush(Color.FromRgb(0x0F, 0x76, 0x6E));
            var tealDark = new SolidColorBrush(Color.FromRgb(0x11, 0x5E, 0x59));
            var red = new SolidColorBrush(Color.FromRgb(0xDC, 0x26, 0x26));
            var redDark = new SolidColorBrush(Color.FromRgb(0xB9, 0x1C, 0x1C));

            if (_selBtn == 0)
            {
                btnApply.Background = teal;
                btnApply.BorderBrush = tealDark;
                btnApply.BorderThickness = new Thickness(2);
                btnQuit.Background = Brushes.White;
                btnQuit.BorderBrush = new SolidColorBrush(Color.FromRgb(0xCB, 0xD5, 0xE1));
                btnQuit.BorderThickness = new Thickness(1);
            }
            else
            {
                btnQuit.Background = red;
                btnQuit.BorderBrush = redDark;
                btnQuit.BorderThickness = new Thickness(2);
                btnApply.Background = Brushes.White;
                btnApply.BorderBrush = new SolidColorBrush(Color.FromRgb(0xCB, 0xD5, 0xE1));
                btnApply.BorderThickness = new Thickness(1);
            }

            bool canApply = _selRow >= 0 && _selRow < _rows.Count && _rows[_selRow].StatusOk;
            btnApply.Opacity = canApply ? 1 : 0.45;
        }

        private void ApplySelected()
        {
            if (_selRow < 0 || _selRow >= _rows.Count) return;
            var row = _rows[_selRow];

            // If scheme is already applied, ask if user wants to quit it
            if (row.StatusText == "ALREADY APPLIED")
            {
                var res = MessageBox.Show(
                    $"Scheme Quit karna chahte hain?\n\n\"{row.Title}\" — yeh scheme is bill me pehle se apply hai.\nQuit karne par scheme items/discount cart se remove ho jayega.",
                    "Quit Scheme",
                    MessageBoxButton.YesNo,
                    MessageBoxImage.Question,
                    MessageBoxResult.No);

                if (res == MessageBoxResult.Yes)
                {
                    _result = new SchemeApplyResult
                    {
                        Title = row.Title,
                        IsQuit = true,
                        ItemCodes = row.AppliedItemCodes.ToList(),
                        FreeItem = row.FreeItem
                    };
                    DialogResult = true;
                    Close();
                }
                return;
            }

            if (!row.StatusOk) return;
            if (row.AppliedItemCodes.Count == 0 && row.FreeItem == null) return;

            _result = new SchemeApplyResult
            {
                Title = row.Title,
                Benefit = row.Benefit,
                PromoType = row.PromoType,
                DiscountKind = row.DiscountKind,
                DiscountAmt = row.DiscountAmt,
                ItemCodes = row.AppliedItemCodes.ToList(),
                FreeItem = row.FreeItem,
                FreeQty = row.FreeQty,
                TierPricing = row.TierPricing,
                BuyItemPrice = row.BuyItemPrice
            };
            DialogResult = true;
            Close();
        }

        private void BtnApply_Click(object sender, MouseButtonEventArgs e) { _selBtn = 0; UpdateBtnSelection(); ApplySelected(); }
        private void BtnQuit_Click(object sender, MouseButtonEventArgs e) => Close();
        private void BtnClose_Click(object sender, MouseButtonEventArgs e) => Close();

        protected override void OnKeyDown(KeyEventArgs e)
        {
            base.OnKeyDown(e);
            switch (e.Key)
            {
                case Key.Up:
                    if (_rows.Count > 0)
                        SelectRow(_selRow <= 0 ? _rows.Count - 1 : _selRow - 1);
                    e.Handled = true; break;
                case Key.Down:
                    if (_rows.Count > 0)
                        SelectRow(_selRow < 0 ? 0 : Math.Min(_rows.Count - 1, _selRow + 1));
                    e.Handled = true; break;
                case Key.Left: _selBtn = 1; UpdateBtnSelection(); e.Handled = true; break;
                case Key.Right: _selBtn = 0; UpdateBtnSelection(); e.Handled = true; break;
                case Key.Enter:
                    if (_selBtn == 0) ApplySelected(); else Close();
                    e.Handled = true; break;
                case Key.Escape: e.Handled = true; Close(); break;
            }
        }

        // ═══════════ SCHEME CHECK LOGIC ═══════════

        private sealed class Promo
        {
            public long Id;
            public string Name = "", Title = "", Type = "", SchemeBasis = "item", DiscountType = "", Discount = "";
            public double DiscountValue, MinPurchase, MaxDiscount, BillLevelDiscount;
            public string BillLevelDiscountType = "";
            public long ItemId, Qty, GetItemId, GetQty;
            public string ApplicableItems = "", Status = "", StartDate = "", EndDate = "", StartTime = "", EndTime = "", CouponCode = "";
            public string TierPricing = ""; // JSON: [{"qty":1,"custom_price":70},{"qty":2,"percent":100}]
        }

        private static List<SchemeCheckRow> BuildRows(DatabaseService db, List<BusyCartItem> cart, List<string> appliedTitles)
        {
            var rows = new List<SchemeCheckRow>();
            if (cart == null || cart.Count == 0) return rows;

            try
            {
                string today = DateTime.Today.ToString("yyyy-MM-dd");
                string now = DateTime.Now.ToString("HH:mm");

                using var conn = db.GetConnection();

                // cart code -> (item id, name)
                var idMap = new Dictionary<string, (long Id, string Name)>();
                var codes = cart.Select(c => c.ItemCode?.Trim() ?? "").Where(c => c.Length > 0).Distinct().ToList();
                if (codes.Count > 0)
                {
                    var prm = string.Join(",", codes.Select((_, i) => "@c" + i));
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = $"SELECT Id, code, name FROM items WHERE code IN ({prm})";
                    for (int i = 0; i < codes.Count; i++) cmd.Parameters.AddWithValue("@c" + i, codes[i]);
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        long id = r.GetInt64(0);
                        string code = (r.GetString(1) ?? "").ToLower();
                        idMap[code] = (id, r.GetString(2) ?? "");
                    }
                }

                var promos = new List<Promo>();
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"SELECT Id,
                            IFNULL(name,''), IFNULL(type,''), IFNULL(scheme_basis,'item'), IFNULL(discount_type,''),
                            IFNULL(discount,''), IFNULL(discount_value,0), IFNULL(min_purchase_amount,0),
                            IFNULL(max_discount_amount,0), IFNULL(bill_level_discount,0), IFNULL(bill_level_discount_type,''),
                            IFNULL(item_id,0), IFNULL(qty,0), IFNULL(get_item_id,0), IFNULL(get_qty,0),
                            IFNULL(applicable_items,''), IFNULL(status,''), IFNULL(start_date,''), IFNULL(end_date,''),
                            IFNULL(start_time,''), IFNULL(end_time,''), IFNULL(coupon_code,''), IFNULL(tier_percentages,''),
                            IFNULL(title,'')
                        FROM promotions WHERE del_status='Live'";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        promos.Add(new Promo
                        {
                            Id = r.GetInt64(0),
                            Name = r.GetString(1) ?? "",
                            Type = r.GetString(2) ?? "",
                            SchemeBasis = r.GetString(3) ?? "item",
                            DiscountType = r.GetString(4) ?? "",
                            Discount = r.GetString(5) ?? "",
                            DiscountValue = r.GetDouble(6),
                            MinPurchase = r.GetDouble(7),
                            MaxDiscount = r.GetDouble(8),
                            BillLevelDiscount = r.GetDouble(9),
                            BillLevelDiscountType = r.GetString(10) ?? "",
                            ItemId = r.GetInt64(11),
                            Qty = r.GetInt64(12),
                            GetItemId = r.GetInt64(13),
                            GetQty = r.GetInt64(14),
                            ApplicableItems = r.GetString(15) ?? "",
                            Status = r.GetString(16) ?? "",
                            StartDate = r.GetString(17) ?? "",
                            EndDate = r.GetString(18) ?? "",
                            StartTime = r.GetString(19) ?? "",
                            EndTime = r.GetString(20) ?? "",
                            CouponCode = r.GetString(21) ?? "",
                            TierPricing = r.GetString(22) ?? "",
                            Title = r.GetString(23) ?? ""
                        });
                    }
                }

                // extra item names (combo free items)
                var wantedIds = promos.Where(p => p.GetItemId > 0).Select(p => p.GetItemId).Distinct().ToList();
                var nameById = new Dictionary<long, string>();
                if (wantedIds.Count > 0)
                {
                    var prm = string.Join(",", wantedIds.Select((_, i) => "@i" + i));
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = $"SELECT Id, name FROM items WHERE Id IN ({prm})";
                    for (int i = 0; i < wantedIds.Count; i++) cmd.Parameters.AddWithValue("@i" + i, wantedIds[i]);
                    using var r = cmd.ExecuteReader();
                    while (r.Read()) nameById[r.GetInt64(0)] = r.GetString(1) ?? "";
                }
                foreach (var kv in idMap.Values) nameById[kv.Id] = kv.Name;

                double subtotal = cart.Sum(c => c.Amount);

                foreach (var p in promos)
                {
                    bool isEnabled = p.Status == "1" || p.Status.Equals("Active", StringComparison.OrdinalIgnoreCase);
                    bool dateOk = (string.IsNullOrEmpty(p.StartDate) || string.CompareOrdinal(p.StartDate, today) <= 0)
                              && (string.IsNullOrEmpty(p.EndDate) || string.CompareOrdinal(p.EndDate, today) >= 0);
                    bool timeOk = (string.IsNullOrEmpty(p.StartTime) || string.CompareOrdinal(p.StartTime, now) <= 0)
                              && (string.IsNullOrEmpty(p.EndTime) || string.CompareOrdinal(p.EndTime, now) >= 0);
                    bool dateActive = isEnabled && dateOk && timeOk;

                    var applicableIds = ParseIds(p.ApplicableItems);
                    if (applicableIds.Count == 0 && p.ItemId > 0) applicableIds.Add(p.ItemId);

                    if (p.SchemeBasis == "bill")
                    {
                        bool minOk = p.MinPurchase <= 0 || subtotal >= p.MinPurchase;
                        string desc = DescribeDiscount(p);
                        var row = new SchemeCheckRow
                        {
                            Title = TitleOf(p),
                            PromoType = p.Type,
                            ItemsLine = "Bill par applicable (scheme basis: Bill)",
                            Benefit = desc,
                            StatusOk = dateActive && minOk,
                            StatusWarn = dateActive && !minOk,
                            StatusText = dateActive
                                ? (minOk ? "APPLICABLE" : "MIN PURCHASE")
                                : "EXPIRED / INACTIVE",
                            Note = minOk
                                ? ""
                                : $"Min purchase ₹{p.MinPurchase:N0} chahiye — abhi bill subtotal ₹{subtotal:N2} hai"
                        };
                        rows.Add(row);
                        continue;
                    }

                    // item-level: cart ke items se match karo
                    var matched = cart.Where(c =>
                    {
                        if (string.IsNullOrEmpty(c.ItemCode)) return false;
                        return idMap.TryGetValue(c.ItemCode.Trim().ToLower(), out var m) && applicableIds.Contains(m.Id);
                    }).ToList();
                    if (matched.Count == 0) continue;

                    string itemNames = string.Join(", ", matched.Select(c => c.ItemName).Where(n => !string.IsNullOrEmpty(n)).Distinct());

                    var rr = new SchemeCheckRow
                    {
                        Title = TitleOf(p),
                        PromoType = p.Type,
                        ItemsLine = $"Lagega: {itemNames}",
                        AppliedItemCodes = matched.Select(c => c.ItemCode).ToList()
                    };

                    if (p.Type == "3")
                    {
                        long buyQty = Math.Max(1, p.Qty);
                        long getQty = Math.Max(1, p.GetQty);
                        string getItemName = nameById.TryGetValue(p.GetItemId, out var gn) && !string.IsNullOrEmpty(gn)
                            ? gn : "Free Item";
                        double buyCartQty = matched.Sum(c => c.Qty);
                        long freeQty = (long)(Math.Floor(buyCartQty / buyQty) * getQty);

                        if (p.GetItemId > 0)
                        {
                            try
                            {
                                using var c2 = conn.CreateCommand();
                                c2.CommandText = @"SELECT i.code, i.name, IFNULL(i.hsn_code,''), IFNULL(i.mrp_price,0), IFNULL(i.sale_price,0),
                                        IFNULL(u.UnitName,''), IFNULL(t.tax_rate,0), IFNULL(i.type,'')
                                    FROM items i
                                    LEFT JOIN units u ON i.sale_unit_id = u.Id
                                    LEFT JOIN taxs t ON i.applicable_tax_id = t.id
                                    WHERE i.Id = @id";
                                c2.Parameters.AddWithValue("@id", p.GetItemId);
                                using var r2 = c2.ExecuteReader();
                                if (r2.Read())
                                {
                                    rr.FreeItem = new FreeItemInfo
                                    {
                                        Code = r2.GetString(0) ?? "",
                                        Name = r2.GetString(1) ?? getItemName,
                                        Hsn = r2.GetString(2) ?? "",
                                        Mrp = r2.GetDouble(3),
                                        Rate = r2.GetDouble(4),
                                        Unit = r2.GetString(5) ?? "",
                                        TaxPerc = r2.GetDouble(6),
                                        Type = r2.GetString(7) ?? ""
                                    };
                                }
                            }
                            catch { }
                        }
                        rr.FreeQty = freeQty;

                        rr.Benefit = $"Buy {buyQty} → Get {getQty} {getItemName} FREE";
                        rr.StatusOk = dateActive && freeQty > 0;
                        rr.StatusWarn = dateActive && freeQty <= 0;
                        rr.StatusText = dateActive ? (freeQty > 0 ? "APPLICABLE" : "QTY NOT MET") : "EXPIRED / INACTIVE";
                        rr.Note = freeQty > 0
                            ? $"Abhi cart me {buyCartQty:0.##} {itemNames} hai → {freeQty} {getItemName} FREE milega"
                            : $"Cart me {buyCartQty:0.##} qty hai — {buyQty} qty par {getQty} FREE milega";

                        // Tier pricing for partial qty (e.g. buy 1 pay ₹70 instead of ₹50)
                        if (!string.IsNullOrWhiteSpace(p.TierPricing))
                        {
                            rr.TierPricing = p.TierPricing;
                            rr.BuyItemPrice = matched.Count > 0 ? matched[0].ListPrice : 0;
                            // Also mark applicable even if freeQty is 0 (partial qty scenario)
                            if (dateActive && !rr.StatusOk)
                            {
                                rr.StatusOk = true;
                                rr.StatusText = "APPLICABLE (Partial)";
                                rr.Note = $"Tier Pricing available — 1 item bhi le sakte hain discounted price par";
                            }
                            // Enhance benefit text with tier info
                            try
                            {
                                var tiers = System.Text.Json.JsonSerializer.Deserialize<List<System.Text.Json.JsonElement>>(p.TierPricing);
                                if (tiers != null && tiers.Count > 0)
                                {
                                    var tierLines = new List<string>();
                                    foreach (var t in tiers)
                                    {
                                        int tq = t.TryGetProperty("qty", out var qv) ? qv.GetInt32() : 0;
                                        if (t.TryGetProperty("custom_price", out var cp))
                                            tierLines.Add($"{tq} le → ₹{cp.GetDouble():N0}/item");
                                        else if (t.TryGetProperty("percent", out var pv))
                                            tierLines.Add($"{tq} le → {pv.GetDouble():0.##}% price");
                                    }
                                    if (tierLines.Count > 0)
                                        rr.Benefit += $"  |  Partial: {string.Join(", ", tierLines)}";
                                }
                            }
                            catch { }
                        }
                    }
                    else
                    {
                        string desc = DescribeDiscount(p);
                        rr.Benefit = desc;

                        double save = 0;
                        var (kind, amt) = DiscountParts(p);
                        rr.DiscountKind = kind;
                        rr.DiscountAmt = amt;
                        if (kind == "percentage")
                        {
                            double lineTotal = matched.Sum(c => c.Amount);
                            save = lineTotal * amt / 100.0;
                        }
                        else
                        {
                            save = matched.Count * amt; // flat per line
                        }
                        if (p.Type == "2" && !string.IsNullOrEmpty(p.CouponCode))
                            desc += $"  ·  Coupon: {p.CouponCode}";

                        rr.StatusOk = dateActive;
                        rr.StatusText = dateActive ? "APPLICABLE" : "EXPIRED / INACTIVE";
                        rr.Note = dateActive
                            ? $"Is bill me is scheme se ≈ ₹{save:N2} ka fayda"
                            : $"Scheme window: {p.StartDate} → {p.EndDate}";
                        rr.Benefit = desc;
                    }

                    // Check if this scheme is already applied in cart
                    bool alreadyApplied = false;

                    // Check 1: The scheme's target item already has HasScheme = true in cart
                    alreadyApplied = cart.Any(c =>
                        c.HasScheme && !string.IsNullOrEmpty(c.ItemCode) &&
                        rr.AppliedItemCodes.Any(ac => string.Equals(ac?.Trim(), c.ItemCode?.Trim(), StringComparison.OrdinalIgnoreCase)));

                    // Check 2: Free item (₹0) already in cart (for Buy X Get Y with separate free item)
                    if (!alreadyApplied && rr.FreeItem != null)
                    {
                        alreadyApplied = cart.Any(c =>
                            c.HasScheme && c.Price == 0 &&
                            string.Equals(c.ItemCode?.Trim(), rr.FreeItem.Code?.Trim(), StringComparison.OrdinalIgnoreCase));
                    }

                    if (alreadyApplied)
                    {
                        rr.StatusOk = false;
                        rr.StatusWarn = false;
                        rr.StatusText = "ALREADY APPLIED";
                        rr.Note = "Yeh scheme is bill me pehle se apply ho chuki hai";
                    }

                    rows.Add(rr);
                }
            }
            catch { }

            return rows;
        }

        private static string TitleOf(Promo p)
        {
            if (!string.IsNullOrWhiteSpace(p.Title)) return p.Title.Trim();
            if (!string.IsNullOrWhiteSpace(p.Name)) return p.Name.Trim();
            if (p.Type == "3") return "Combo Offer";
            if (p.Type == "2") return "Coupon Discount";
            return "Item Discount";
        }

        private static (string kind, double amount) DiscountParts(Promo p)
        {
            string d = p.Discount ?? "";
            if (d.Trim().EndsWith("%", StringComparison.Ordinal))
            {
                double pct = 0;
                double.TryParse(d.Trim().TrimEnd('%'), out pct);
                return ("percentage", pct);
            }
            double num = 0;
            if (double.TryParse(d.Trim(), out num) && num != 0) return (IsPercentageType(p) ? "percentage" : "flat", num);
            if (IsPercentageType(p)) return ("percentage", p.DiscountValue);
            return ("flat", p.DiscountValue);
        }

        private static bool IsPercentageType(Promo p)
        {
            string t = (p.DiscountType ?? "").ToLower();
            return t == "percentage" || t == "percent" || t == "%";
        }

        private static string DescribeDiscount(Promo p)
        {
            var (kind, amt) = DiscountParts(p);
            if (kind == "percentage") return $"{amt:0.##}% discount";
            return $"₹{amt:N2} flat discount";
        }

        private static List<long> ParseIds(string json)
        {
            var list = new List<long>();
            if (string.IsNullOrWhiteSpace(json)) return list;
            try
            {
                var trimmed = json.Trim();
                if (trimmed.StartsWith("["))
                {
                    foreach (var tok in trimmed.Trim('[', ']').Split(','))
                        if (long.TryParse(tok.Trim().Trim('"', '\''), out long v)) list.Add(v);
                }
                else if (long.TryParse(trimmed, out long single))
                {
                    list.Add(single);
                }
            }
            catch { }
            return list;
        }
    }

    public class SchemeApplyResult
    {
        public string Title = "";
        public string Benefit = "";
        public string PromoType = "";
        public string DiscountKind = "";
        public double DiscountAmt = 0;
        public double MaxDiscount = 0; // cap on discount amount
        public List<string> ItemCodes = new();
        public FreeItemInfo? FreeItem = null;
        public long FreeQty = 0;
        public bool IsQuit = false;
        public string TierPricing = ""; // JSON tier data for partial qty pricing
        public double BuyItemPrice = 0; // ListPrice of the buy item
        public string ApplicableItems = ""; // comma-separated item IDs that coupon applies to (empty = all items)
    }

    public class FreeItemInfo
    {
        public string Code = "", Name = "", Hsn = "", Unit = "", Type = "";
        public double Mrp, Rate, TaxPerc;
    }

    public class SchemeCheckRow
    {
        public string Title = "";
        public string ItemsLine = "";
        public string Benefit = "";
        public string Note = "";
        public bool StatusOk = true;
        public bool StatusWarn = false;
        public string StatusText = "";
        public string PromoType = "";
        public string DiscountKind = "";
        public double DiscountAmt = 0;
        public List<string> AppliedItemCodes = new();
        public FreeItemInfo? FreeItem = null;
        public long FreeQty = 0;
        public string TierPricing = ""; // JSON tier data for partial qty pricing
        public double BuyItemPrice = 0; // ListPrice of the buy item (for tier calculation)
    }
}
