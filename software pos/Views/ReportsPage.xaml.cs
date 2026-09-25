using System.Collections.Generic;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;

namespace RashanKiDukan.Views
{
    public partial class ReportsPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private string _activeCategory = "All";
        private readonly List<Border> _chips = new();

        private sealed class ReportCardModel
        {
            public string Key { get; set; } = "";
            public string Title { get; set; } = "";
            public string Category { get; set; } = "";
            public string Icon { get; set; } = "";
            public SolidColorBrush? IconBg { get; set; }
        }

        private static readonly Dictionary<string, string> CategoryOf = new()
        {
            // Sales
            { "register-report", "Sales" }, { "z-report", "Sales" }, { "daily-summary-report", "Sales" },
            { "sale-report", "Sales" }, { "due-sale-report", "Sales" }, { "final-invoice-due-report", "Sales" },
            { "service-sale-report", "Sales" }, { "combo-service-report", "Sales" }, { "employee-sale-report", "Sales" },
            { "product-sale-report", "Sales" }, { "product-profit-report", "Sales" }, { "detailed-sale-report", "Sales" },
            { "tax-report", "Sales" }, { "sale-return-report", "Sales" },             { "usage-loyalty-point-report", "Sales" }, { "detailed-usage-loyalty-point-report", "Sales" },
            // Purchase
            { "purchase-report", "Purchase" }, { "purchase-return-report", "Purchase" }, { "expense-report", "Purchase" },
            { "income-report", "Purchase" }, { "salary-report", "Purchase" }, { "damage-report", "Purchase" },
            { "supplier-ledger-report", "Purchase" }, { "supplier-balance-report", "Purchase" },
            // Stock
            { "stock-report", "Stock" }, { "low-stock-report", "Stock" }, { "expire-soon-report", "Stock" },
            { "item-tracking-report", "Stock" }, { "price-history-report", "Stock" }, { "warranty-checking-report", "Stock" },
            { "installment-report", "Stock" },             { "installment-due-report", "Stock" },             { "installment-collection-report", "Installment" },             { "detailed-installment-due-report", "Installment" },             { "detailed-item-tracking-report", "Stock" },             { "detailed-price-history-report", "Stock" }, { "detailed-cash-flow-report", "Accounting" },
            // Party
            { "customer-ledger-report", "Party" }, { "customer-balance-report", "Party" }, { "customer-receive-report", "Party" },
            { "available-loyalty-point-report", "Party" }, { "detailed-available-loyalty-point-report", "Party" },             { "scheme-report", "Party" }, { "detailed-scheme-report", "Party" }, { "servicing-report", "Party" },
            // GST
            { "gst-report", "GST" },
            // Accounting
            { "profit-loss-report", "Accounting" }, { "cash-flow-report", "Accounting" }, { "account-balance-report", "Accounting" },
            { "account-statement-report", "Accounting" }, { "balance-sheet-report", "Accounting" }, { "trial-balance-report", "Accounting" },
            { "transaction-history-report", "Accounting" },
            // HR
            { "attendance-report", "HR" },
        };

        private static readonly Dictionary<string, string> IconOf = new()
        {
            { "register-report", "🧾" }, { "z-report", "📊" }, { "daily-summary-report", "📅" }, { "sale-report", "🛒" },
            { "due-sale-report", "💰" }, { "final-invoice-due-report", "🧾" }, { "service-sale-report", "🛠️" },
            { "combo-service-report", "🍱" }, { "employee-sale-report", "🧑‍💼" }, { "product-sale-report", "📉" },
            { "product-profit-report", "📈" }, { "detailed-sale-report", "🔍" }, { "tax-report", "🧮" },
            { "sale-return-report", "↩️" },             { "usage-loyalty-point-report", "⭐" }, { "detailed-usage-loyalty-point-report", "⭐" }, { "purchase-report", "📥" },
            { "purchase-return-report", "↩️" }, { "expense-report", "💸" }, { "income-report", "💰" },
            { "salary-report", "💵" }, { "damage-report", "💥" }, { "supplier-ledger-report", "🏪" },
            { "supplier-balance-report", "⚖️" }, { "stock-report", "📦" }, { "low-stock-report", "⚠️" },
            { "expire-soon-report", "⏳" }, { "item-tracking-report", "🛰️" }, { "price-history-report", "🕓" },
            { "warranty-checking-report", "🛡️" }, { "installment-report", "🏦" },             { "installment-due-report", "📆" },             { "installment-collection-report", "💳" },             { "detailed-installment-due-report", "📋" },             { "detailed-item-tracking-report", "📡" },             { "detailed-price-history-report", "🕓" }, { "detailed-cash-flow-report", "💳" },
            { "customer-ledger-report", "📒" }, { "customer-balance-report", "💼" }, { "customer-receive-report", "💵" },
            { "available-loyalty-point-report", "⭐" }, { "detailed-available-loyalty-point-report", "⭐" },             { "scheme-report", "🎯" }, { "detailed-scheme-report", "🎯" }, { "servicing-report", "🔧" },
            { "gst-report", "🏛️" }, { "profit-loss-report", "💹" }, { "cash-flow-report", "💳" },
            { "account-balance-report", "🏦" }, { "account-statement-report", "📃" }, { "balance-sheet-report", "⚖️" },
            { "trial-balance-report", "🔬" }, { "transaction-history-report", "🕘" }, { "attendance-report", "⏰" },
        };

        private static readonly Dictionary<string, Color> CategoryColor = new()
        {
            { "Sales", Color.FromRgb(0xEE, 0xF2, 0xFF) },
            { "Purchase", Color.FromRgb(0xFF, 0xF7, 0xED) },
            { "Stock", Color.FromRgb(0xF0, 0xF9, 0xFF) },
            { "Party", Color.FromRgb(0xF5, 0xF3, 0xFF) },
            { "GST", Color.FromRgb(0xEC, 0xFD, 0xF5) },
            { "Accounting", Color.FromRgb(0xFF, 0xFB, 0xEB) },
            { "HR", Color.FromRgb(0xFF, 0xF1, 0xF2) },
        };

        public ReportsPage() { InitializeComponent(); BuildChips(); ApplyFilter(); }
        public ReportsPage(MainDashboard dashboard) : this() { _dashboard = dashboard; }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowDashboard();
        }

        private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e)
        {
            txtPlaceholder.Visibility = string.IsNullOrEmpty(txtSearch.Text) ? Visibility.Visible : Visibility.Collapsed;
            ApplyFilter();
        }

        private void BuildChips()
        {
            string[] cats = { "All", "Sales", "Purchase", "Stock", "Party", "GST", "Accounting", "HR" };
            foreach (var cat in cats)
            {
                var chip = new Border
                {
                    CornerRadius = new CornerRadius(16),
                    Padding = new Thickness(16, 7, 16, 7),
                    Margin = new Thickness(0, 0, 8, 0),
                    Cursor = Cursors.Hand,
                    Tag = cat,
                    BorderThickness = new Thickness(1),
                    Child = new TextBlock
                    {
                        Text = cat,
                        FontSize = 12.5,
                        FontWeight = FontWeights.SemiBold,
                        FontFamily = new FontFamily("Segoe UI")
                    }
                };
                chip.MouseLeftButtonUp += (s, _) =>
                {
                    _activeCategory = (string)((Border)s).Tag;
                    RefreshChips();
                    ApplyFilter();
                };
                chipPanel.Children.Add(chip);
                _chips.Add(chip);
            }
            RefreshChips();
        }

        private void RefreshChips()
        {
            foreach (var chip in _chips)
            {
                bool active = (string)chip.Tag == _activeCategory;
                chip.Background = active ? new SolidColorBrush(Color.FromRgb(0x4F, 0x46, 0xE5))
                                         : Brushes.White;
                chip.BorderBrush = active ? new SolidColorBrush(Color.FromRgb(0x4F, 0x46, 0xE5))
                                          : new SolidColorBrush(Color.FromRgb(0xE2, 0xE8, 0xF0));
                if (chip.Child is TextBlock tb)
                    tb.Foreground = active ? Brushes.White
                                           : new SolidColorBrush(Color.FromRgb(0x33, 0x41, 0x55));
            }
        }

        private void ApplyFilter()
        {
            string q = txtSearch.Text?.Trim().ToLowerInvariant() ?? "";
            var items = new List<ReportCardModel>();
            foreach (var kv in ReportRegistry.All)
            {
                if (_activeCategory != "All" && CategoryOf.GetValueOrDefault(kv.Key) != _activeCategory) continue;
                if (q.Length > 0 && !kv.Value.Title.ToLowerInvariant().Contains(q)
                    && !kv.Key.ToLowerInvariant().Contains(q)) continue;
                string cat = CategoryOf.GetValueOrDefault(kv.Key, "Other");
                items.Add(new ReportCardModel
                {
                    Key = kv.Key,
                    Title = kv.Value.Title,
                    Category = cat,
                    Icon = IconOf.GetValueOrDefault(kv.Key, "📊"),
                    IconBg = new SolidColorBrush(CategoryColor.TryGetValue(cat, out var cc) ? cc : Color.FromRgb(0xEE, 0xF2, 0xFF))
                });
            }
            reportItems.ItemsSource = items;
            lblCount.Text = items.Count + (items.Count == 1 ? " report" : " reports");
            lblSubtitle.Text = _activeCategory == "All" ? "All business reports in one place"
                                                        : $"Category: {_activeCategory} · {items.Count} report(s)";
        }

        private void Card_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button b && b.Tag is string key && _dashboard != null)
            {
                if (key == "z-report")
                {
                    _dashboard.ShowPage(new ZReportPage(_dashboard));
                    return;
                }
                if (key == "daily-summary-report")
                {
                    _dashboard.ShowPage(new DailySummaryReportPage(_dashboard));
                    return;
                }
                if (key == "sale-report")
                {
                    _dashboard.ShowPage(new SaleReportPage(_dashboard));
                    return;
                }
                if (key == "due-sale-report")
                {
                    _dashboard.ShowPage(new DueSaleReportPage(_dashboard));
                    return;
                }
                if (key == "final-invoice-due-report")
                {
                    _dashboard.ShowPage(new FinalInvoiceDueReportPage(_dashboard));
                    return;
                }
                if (key == "service-sale-report")
                {
                    _dashboard.ShowPage(new ServiceSaleReportPage(_dashboard));
                    return;
                }
                if (key == "combo-service-sale-report")
                {
                    _dashboard.ShowPage(new ComboServiceSaleReportPage(_dashboard));
                    return;
                }
                if (key == "stock-report")
                {
                    _dashboard.ShowPage(new StockReportPage(_dashboard));
                    return;
                }
                if (key == "low-stock-report")
                {
                    _dashboard.ShowPage(new LowStockReportPage(_dashboard));
                    return;
                }
                if (key == "expire-soon-report")
                {
                    _dashboard.ShowPage(new ExpireSoonReportPage(_dashboard));
                    return;
                }
                if (key == "employee-sale-report")
                {
                    _dashboard.ShowPage(new EmployeeSaleReportPage(_dashboard));
                    return;
                }
                if (key == "customer-receive-report")
                {
                    _dashboard.ShowPage(new CustomerReceiveReportPage(_dashboard));
                    return;
                }
                if (key == "attendance-report")
                {
                    _dashboard.ShowPage(new AttendanceReportPage(_dashboard));
                    return;
                }
                if (key == "product-profit-report")
                {
                    _dashboard.ShowPage(new ProductProfitReportPage(_dashboard));
                    return;
                }
                if (key == "supplier-ledger-report")
                {
                    _dashboard.ShowPage(new SupplierLedgerReportPage(_dashboard));
                    return;
                }
                if (key == "supplier-balance-report")
                {
                    _dashboard.ShowPage(new SupplierBalanceReportPage(_dashboard));
                    return;
                }
                if (key == "customer-ledger-report")
                {
                    _dashboard.ShowPage(new CustomerLedgerReportPage(_dashboard));
                    return;
                }
                if (key == "customer-balance-report")
                {
                    _dashboard.ShowPage(new CustomerBalanceReportPage(_dashboard));
                    return;
                }
                if (key == "servicing-report")
                {
                    _dashboard.ShowPage(new ServicingReportPage(_dashboard));
                    return;
                }
                if (key == "product-sale-report")
                {
                    _dashboard.ShowPage(new ProductSaleReportPage(_dashboard));
                    return;
                }
                if (key == "tax-report")
                {
                    _dashboard.ShowPage(new TaxReportPage(_dashboard));
                    return;
                }
                if (key == "gst-report")
                {
                    _dashboard.ShowPage(new GstReportPage(_dashboard));
                    return;
                }
                if (key == "detailed-sale-report")
                {
                    _dashboard.ShowPage(new DetailedSaleReportPage(_dashboard));
                    return;
                }
                if (key == "profit-loss-report")
                {
                    _dashboard.ShowPage(new ProfitLossReportPage(_dashboard));
                    return;
                }
                if (key == "purchase-report")
                {
                    _dashboard.ShowPage(new PurchaseReportPage(_dashboard));
                    return;
                }
                if (key == "expense-report")
                {
                    _dashboard.ShowPage(new ExpenseReportPage(_dashboard));
                    return;
                }
                if (key == "income-report")
                {
                    _dashboard.ShowPage(new IncomeReportPage(_dashboard));
                    return;
                }
                if (key == "salary-report")
                {
                    _dashboard.ShowPage(new SalaryReportPage(_dashboard));
                    return;
                }
                if (key == "purchase-return-report")
                {
                    _dashboard.ShowPage(new PurchaseReturnReportPage(_dashboard));
                    return;
                }
                if (key == "sale-return-report")
                {
                    _dashboard.ShowPage(new SaleReturnReportPage(_dashboard));
                    return;
                }
                if (key == "damage-report")
                {
                    _dashboard.ShowPage(new DamageReportPage(_dashboard));
                    return;
                }
                if (key == "installment-collection-report")
                {
                    _dashboard.ShowPage(new InstallmentCollectionReportPage(_dashboard));
                    return;
                }
                if (key == "detailed-installment-due-report")
                {
                    _dashboard.ShowPage(new DetailedInstallmentDueReportPage(_dashboard));
                    return;
                }
                if (key == "detailed-item-tracking-report")
                {
                    _dashboard.ShowPage(new DetailedItemTrackingReportPage(_dashboard));
                    return;
                }
                if (key == "detailed-price-history-report")
                {
                    _dashboard.ShowPage(new DetailedPriceHistoryReportPage(_dashboard));
                    return;
                }
                if (key == "detailed-cash-flow-report")
                {
                    _dashboard.ShowPage(new DetailedCashFlowReportPage(_dashboard));
                    return;
                }
                if (key == "detailed-available-loyalty-point-report")
                {
                    _dashboard.ShowPage(new DetailedAvailableLoyaltyPointReportPage(_dashboard));
                    return;
                }
                if (key == "detailed-usage-loyalty-point-report")
                {
                    _dashboard.ShowPage(new DetailedUsageLoyaltyPointReportPage(_dashboard));
                    return;
                }
                if (key == "detailed-scheme-report")
                {
                    _dashboard.ShowPage(new DetailedSchemeReportPage(_dashboard));
                    return;
                }
                _dashboard.ShowPage(new ReportPage(_dashboard, key));
            }
        }
    }
}
