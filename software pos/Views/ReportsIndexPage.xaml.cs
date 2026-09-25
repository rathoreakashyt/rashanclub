using System;
using System.Collections.Generic;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Media.Effects;

namespace RashanKiDukan.Views
{
    public partial class ReportsIndexPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private string _activeCategory = "All";
        private string _searchText = "";

        // ── Report meta: key → (emoji, category, iconColor)
        private static readonly Dictionary<string, (string Icon, string Category, string Color)> ReportMeta = new()
        {
            // Sales
            { "register-report",          ("🧾", "Sales",      "#696cff") },
            { "z-report",                  ("📊", "Sales",      "#696cff") },
            { "daily-summary-report",      ("📅", "Sales",      "#696cff") },
            { "sale-report",               ("🛒", "Sales",      "#696cff") },
            { "due-sale-report",           ("💰", "Sales",      "#696cff") },
            { "final-invoice-due-report",  ("🧾", "Sales",      "#696cff") },
            { "service-sale-report",       ("🛠️","Sales",      "#696cff") },
            { "combo-service-report",      ("📦", "Sales",      "#696cff") },
            { "employee-sale-report",      ("👤", "Sales",      "#696cff") },
            { "product-sale-report",       ("📉", "Sales",      "#696cff") },
            { "product-profit-report",     ("📈", "Sales",      "#71dd37") },
            { "detailed-sale-report",      ("🔍", "Sales",      "#696cff") },
            { "tax-report",                ("🧮", "Sales",      "#696cff") },
            { "sale-return-report",        ("↩️", "Sales",      "#696cff") },
            { "usage-loyalty-point-report",("⭐", "Sales",      "#696cff") },
            { "detailed-usage-loyalty-point-report",("⭐", "Sales", "#71dd37") },
            // Purchase
            { "purchase-report",           ("📥", "Purchase",   "#696cff") },
            { "purchase-return-report",    ("↩️", "Purchase",   "#696cff") },
            { "expense-report",            ("💸", "Purchase",   "#ff3e1d") },
            { "income-report",             ("💰", "Purchase",   "#71dd37") },
            { "salary-report",             ("💵", "Purchase",   "#696cff") },
            { "damage-report",             ("💥", "Purchase",   "#ff3e1d") },
            { "supplier-ledger-report",    ("📖", "Purchase",   "#696cff") },
            { "supplier-balance-report",   ("⚖️", "Purchase",   "#696cff") },
            // Stock
            { "stock-report",              ("📦", "Stock",      "#696cff") },
            { "low-stock-report",          ("⚠️", "Stock",      "#ffab00") },
            { "expire-soon-report",        ("⏳", "Stock",      "#ffab00") },
            { "item-tracking-report",      ("📡", "Stock",      "#696cff") },
            { "price-history-report",      ("🕓", "Stock",      "#696cff") },
            { "warranty-checking-report",  ("🛡️","Stock",      "#696cff") },
            { "installment-report",        ("💳", "Stock",      "#696cff") },
            { "installment-due-report",    ("⚠️", "Stock",      "#ffab00") },
            { "installment-collection-report", ("💳", "Installment", "#696cff") },
            { "detailed-installment-due-report", ("📋", "Installment", "#696cff") },
            { "detailed-item-tracking-report", ("📡", "Stock", "#696cff") },
            { "detailed-price-history-report", ("🕓", "Stock", "#696cff") },
            { "detailed-cash-flow-report", ("💳", "Accounting", "#71dd37") },
            // Party
            { "customer-ledger-report",    ("📒", "Party",      "#696cff") },
            { "customer-balance-report",   ("⚖️", "Party",      "#696cff") },
            { "customer-receive-report",   ("💵", "Party",      "#696cff") },
            { "available-loyalty-point-report",("⭐","Party",   "#ffab00") },
            { "detailed-available-loyalty-point-report",("⭐","Party","#71dd37") },
            { "scheme-report",             ("🎯", "Party",      "#696cff") },
            { "detailed-scheme-report",    ("🎯", "Party",      "#71dd37") },
            { "servicing-report",          ("🔧", "Party",      "#696cff") },
            // GST
            { "gst-report",                ("🏛️","GST",        "#696cff") },
            // Accounting
            { "profit-loss-report",        ("💹", "Accounting", "#71dd37") },
            { "cash-flow-report",          ("💳", "Accounting", "#71dd37") },
            { "account-balance-report",    ("🏦", "Accounting", "#696cff") },
            { "account-statement-report",  ("📃", "Accounting", "#696cff") },
            { "balance-sheet-report",      ("⚖️", "Accounting", "#696cff") },
            { "trial-balance-report",      ("🔬", "Accounting", "#696cff") },
            { "transaction-history-report",("🕘", "Accounting", "#696cff") },
            // HR
            { "attendance-report",         ("⏰", "HR",         "#696cff") },
        };

        private static readonly string[] Categories = { "All", "Sales", "Purchase", "Stock", "Party", "GST", "Accounting", "HR" };

        public ReportsIndexPage(MainDashboard? dashboard)
        {
            InitializeComponent();
            _dashboard = dashboard;
            BuildChips();
            RenderCards();
        }

        // ─── Build category chips ───────────────────────────────────────────
        private void BuildChips()
        {
            chipPanel.Children.Clear();
            foreach (var cat in Categories)
            {
                var btn = new Button
                {
                    Content = cat,
                    FontSize = 12.5,
                    FontFamily = new FontFamily("Inter, Segoe UI"),
                    FontWeight = cat == _activeCategory ? FontWeights.SemiBold : FontWeights.Normal,
                    Padding = new Thickness(14, 6, 14, 6),
                    Margin = new Thickness(0, 0, 8, 4),
                    Cursor = Cursors.Hand,
                    Tag = cat,
                    Style = (Style)FindResource("ChipBtn")
                };

                if (cat == _activeCategory)
                {
                    btn.Background = new SolidColorBrush(Color.FromRgb(0x69, 0x6c, 0xff));
                    btn.BorderBrush = new SolidColorBrush(Color.FromRgb(0x69, 0x6c, 0xff));
                    btn.Foreground = new SolidColorBrush(Colors.White);
                }
                else
                {
                    btn.Background = new SolidColorBrush(Colors.White);
                    btn.BorderBrush = new SolidColorBrush(Color.FromRgb(0xDC, 0xDC, 0xEC));
                    btn.Foreground = new SolidColorBrush(Color.FromRgb(0x51, 0x51, 0x5E));
                }

                btn.Click += (s, e) =>
                {
                    _activeCategory = (string)((Button)s).Tag;
                    BuildChips();
                    RenderCards();
                };

                chipPanel.Children.Add(btn);
            }
        }

        // ─── Render cards ───────────────────────────────────────────────────
        private void RenderCards()
        {
            cardPanel.Children.Clear();

            var filtered = ReportRegistry.All
                .Where(kv =>
                {
                    if (_activeCategory != "All")
                    {
                        if (!ReportMeta.TryGetValue(kv.Key, out var m)) return false;
                        if (m.Category != _activeCategory) return false;
                    }
                    if (!string.IsNullOrWhiteSpace(_searchText))
                    {
                        var t = kv.Value.Title.ToLower();
                        if (!t.Contains(_searchText.ToLower())) return false;
                    }
                    return true;
                })
                .ToList();

            int count = filtered.Count;
            lblCount.Text = $"Showing {count} report{(count == 1 ? "" : "s")}";
            noResults.Visibility = count == 0 ? Visibility.Visible : Visibility.Collapsed;

            foreach (var kv in filtered)
            {
                ReportMeta.TryGetValue(kv.Key, out var meta);
                string icon = meta.Icon ?? "📄";
                string iconColor = meta.Color ?? "#696cff";
                cardPanel.Children.Add(BuildCard(kv.Key, icon, kv.Value.Title, iconColor));
            }
        }

        // ─── Build a single card ────────────────────────────────────────────
        private Border BuildCard(string key, string emoji, string title, string hexColor)
        {
            var card = new Border
            {
                Width = 240,
                Margin = new Thickness(0, 0, 16, 16),
                Background = new SolidColorBrush(Colors.White),
                CornerRadius = new CornerRadius(10),
                BorderBrush = new SolidColorBrush(Color.FromRgb(0xEA, 0xEA, 0xEC)),
                BorderThickness = new Thickness(1),
                Cursor = Cursors.Hand,
                Tag = key
            };

            card.Effect = new DropShadowEffect
            {
                BlurRadius = 8,
                ShadowDepth = 1,
                Opacity = 0.07,
                Color = Color.FromRgb(0x8E, 0x8E, 0xA1)
            };

            // Hover
            card.MouseEnter += (s, e) =>
            {
                card.BorderBrush = new SolidColorBrush(Color.FromRgb(0x69, 0x6c, 0xff));
                card.Effect = new DropShadowEffect { BlurRadius = 16, ShadowDepth = 2, Opacity = 0.14, Color = Color.FromRgb(0x69, 0x6c, 0xff) };
            };
            card.MouseLeave += (s, e) =>
            {
                card.BorderBrush = new SolidColorBrush(Color.FromRgb(0xEA, 0xEA, 0xEC));
                card.Effect = new DropShadowEffect { BlurRadius = 8, ShadowDepth = 1, Opacity = 0.07, Color = Color.FromRgb(0x8E, 0x8E, 0xA1) };
            };

            var inner = new StackPanel { Margin = new Thickness(20, 22, 20, 20), HorizontalAlignment = HorizontalAlignment.Center };

            // Icon circle (48px per Sneat spec)
            var color = ParseHex(hexColor);
            var iconBg = Color.FromArgb(30, color.R, color.G, color.B);
            var iconBox = new Border
            {
                Width = 48,
                Height = 48,
                CornerRadius = new CornerRadius(50),
                Background = new SolidColorBrush(iconBg),
                HorizontalAlignment = HorizontalAlignment.Center,
                Margin = new Thickness(0, 0, 0, 14)
            };
            iconBox.Child = new TextBlock
            {
                Text = emoji,
                FontSize = 22,
                HorizontalAlignment = HorizontalAlignment.Center,
                VerticalAlignment = VerticalAlignment.Center
            };
            inner.Children.Add(iconBox);

            // Title
            var titleBlock = new TextBlock
            {
                Text = title,
                FontSize = 14,
                FontWeight = FontWeights.SemiBold,
                Foreground = new SolidColorBrush(Color.FromRgb(0x23, 0x23, 0x2E)),
                FontFamily = new FontFamily("Inter, Segoe UI"),
                TextWrapping = TextWrapping.Wrap,
                TextAlignment = TextAlignment.Center,
                HorizontalAlignment = HorizontalAlignment.Center,
                Margin = new Thickness(0, 0, 0, 16)
            };
            inner.Children.Add(titleBlock);

            // View Report Button
            var viewBtn = new Button
            {
                Content = "View Report",
                FontSize = 12.5,
                FontWeight = FontWeights.SemiBold,
                FontFamily = new FontFamily("Inter, Segoe UI"),
                Foreground = new SolidColorBrush(Colors.White),
                BorderThickness = new Thickness(0),
                Padding = new Thickness(20, 8, 20, 8),
                HorizontalAlignment = HorizontalAlignment.Center,
                Cursor = Cursors.Hand,
                Tag = key,
                Style = (Style)FindResource("PrimaryBtn")
            };
            viewBtn.Click += (s, e) => { e.Handled = true; OpenReport(key); };
            inner.Children.Add(viewBtn);

            card.Child = inner;
            card.MouseLeftButtonUp += (s, e) => OpenReport(key);
            return card;
        }

        // ─── Navigation ────────────────────────────────────────────────────
        private void OpenReport(string key)
        {
            if (_dashboard == null) return;
            switch (key)
            {
                case "z-report":
                    _dashboard.ShowPage(new ZReportPage(_dashboard)); break;
                case "daily-summary-report":
                    _dashboard.ShowPage(new DailySummaryReportPage(_dashboard)); break;
                case "sale-report":
                    _dashboard.ShowPage(new SaleReportPage(_dashboard)); break;
                case "due-sale-report":
                    _dashboard.ShowPage(new DueSaleReportPage(_dashboard)); break;
                case "final-invoice-due-report":
                    _dashboard.ShowPage(new FinalInvoiceDueReportPage(_dashboard)); break;
                case "service-sale-report":
                    _dashboard.ShowPage(new ServiceSaleReportPage(_dashboard)); break;
                case "combo-service-report":
                    _dashboard.ShowPage(new ComboServiceSaleReportPage(_dashboard)); break;
                case "stock-report":
                    _dashboard.ShowPage(new StockReportPage(_dashboard)); break;
                case "low-stock-report":
                    _dashboard.ShowPage(new LowStockReportPage(_dashboard)); break;
                case "expire-soon-report":
                    _dashboard.ShowPage(new ExpireSoonReportPage(_dashboard)); break;
                case "employee-sale-report":
                    _dashboard.ShowPage(new EmployeeSaleReportPage(_dashboard)); break;
                case "customer-receive-report":
                    _dashboard.ShowPage(new CustomerReceiveReportPage(_dashboard)); break;
                case "attendance-report":
                    _dashboard.ShowPage(new AttendanceReportPage(_dashboard)); break;
                case "product-profit-report":
                    _dashboard.ShowPage(new ProductProfitReportPage(_dashboard)); break;
                case "supplier-ledger-report":
                    _dashboard.ShowPage(new SupplierLedgerReportPage(_dashboard)); break;
                case "supplier-balance-report":
                    _dashboard.ShowPage(new SupplierBalanceReportPage(_dashboard)); break;
                case "customer-ledger-report":
                    _dashboard.ShowPage(new CustomerLedgerReportPage(_dashboard)); break;
                case "customer-balance-report":
                    _dashboard.ShowPage(new CustomerBalanceReportPage(_dashboard)); break;
                case "servicing-report":
                    _dashboard.ShowPage(new ServicingReportPage(_dashboard)); break;
                case "product-sale-report":
                    _dashboard.ShowPage(new ProductSaleReportPage(_dashboard)); break;
                case "tax-report":
                    _dashboard.ShowPage(new TaxReportPage(_dashboard)); break;
                case "gst-report":
                    _dashboard.ShowPage(new GstReportPage(_dashboard)); break;
                case "detailed-sale-report":
                    _dashboard.ShowPage(new DetailedSaleReportPage(_dashboard)); break;
                case "profit-loss-report":
                    _dashboard.ShowPage(new ProfitLossReportPage(_dashboard)); break;
                case "purchase-report":
                    _dashboard.ShowPage(new PurchaseReportPage(_dashboard)); break;
                case "expense-report":
                    _dashboard.ShowPage(new ExpenseReportPage(_dashboard)); break;
                case "income-report":
                    _dashboard.ShowPage(new IncomeReportPage(_dashboard)); break;
                case "salary-report":
                    _dashboard.ShowPage(new SalaryReportPage(_dashboard)); break;
                case "purchase-return-report":
                    _dashboard.ShowPage(new PurchaseReturnReportPage(_dashboard)); break;
                case "sale-return-report":
                    _dashboard.ShowPage(new SaleReturnReportPage(_dashboard)); break;
                case "damage-report":
                    _dashboard.ShowPage(new DamageReportPage(_dashboard)); break;
                case "installment-collection-report":
                    _dashboard.ShowPage(new InstallmentCollectionReportPage(_dashboard)); break;
                case "detailed-installment-due-report":
                    _dashboard.ShowPage(new DetailedInstallmentDueReportPage(_dashboard)); break;
                case "detailed-item-tracking-report":
                    _dashboard.ShowPage(new DetailedItemTrackingReportPage(_dashboard)); break;
                case "detailed-price-history-report":
                    _dashboard.ShowPage(new DetailedPriceHistoryReportPage(_dashboard)); break;
                case "detailed-cash-flow-report":
                    _dashboard.ShowPage(new DetailedCashFlowReportPage(_dashboard)); break;
                case "detailed-available-loyalty-point-report":
                    _dashboard.ShowPage(new DetailedAvailableLoyaltyPointReportPage(_dashboard)); break;
                case "detailed-usage-loyalty-point-report":
                    _dashboard.ShowPage(new DetailedUsageLoyaltyPointReportPage(_dashboard)); break;
                case "detailed-scheme-report":
                    _dashboard.ShowPage(new DetailedSchemeReportPage(_dashboard)); break;
                default:
                    _dashboard.ShowPage(new ReportPage(_dashboard, key)); break;
            }
        }

        // ─── Search ─────────────────────────────────────────────────────────
        private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e)
        {
            _searchText = txtSearch.Text;
            txtPlaceholder.Visibility = string.IsNullOrEmpty(_searchText) ? Visibility.Visible : Visibility.Collapsed;
            RenderCards();
        }

        // ─── Helper: parse hex color ─────────────────────────────────────────
        private static Color ParseHex(string hex)
        {
            try
            {
                hex = hex.TrimStart('#');
                if (hex.Length == 6)
                {
                    byte r = Convert.ToByte(hex[..2], 16);
                    byte g = Convert.ToByte(hex.Substring(2, 2), 16);
                    byte b = Convert.ToByte(hex.Substring(4, 2), 16);
                    return Color.FromRgb(r, g, b);
                }
            }
            catch { }
            return Color.FromRgb(0x69, 0x6c, 0xff);
        }
    }
}
