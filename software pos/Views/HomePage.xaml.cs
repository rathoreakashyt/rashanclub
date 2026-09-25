using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views
{
    public partial class HomePage : UserControl
    {
        private readonly DatabaseService _db = new DatabaseService();
        private readonly MainDashboard? _dashboard;

        private static readonly FontFamily ProFont = new FontFamily("Inter, Segoe UI");

        public HomePage() { InitializeComponent(); }
        public HomePage(MainDashboard dashboard) : this() { _dashboard = dashboard; LoadStats(); }

        private void HomePage_Loaded(object sender, RoutedEventArgs e) => LoadStats();

        private void LoadStats()
        {
            outletPanel.Children.Clear();
            try
            {
                using var conn = _db.GetConnection();
                int defaultId = OutletContext.GetDefaultOutletId(conn);
                int selectedId = OutletContext.GetSelectedOutletId(_db);

                double tSales = 0, tToday = 0, tPur = 0, tPL = 0;

                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT o.id,
                           COALESCE(o.outlet_name, o.name, '') AS oname,
                           COALESCE(o.outlet_code, '') AS ocode,
                           COALESCE(o.address, '') AS oaddress,
                           (SELECT IFNULL(SUM(s.grand_total),0) FROM sales s
                                WHERE s.del_status='Live' AND IFNULL(s.outlet_id, @def) = o.id) AS sales_total,
                           (SELECT IFNULL(SUM(s.grand_total),0) FROM sales s
                                WHERE s.del_status='Live' AND s.sale_date=date('now') AND IFNULL(s.outlet_id, @def) = o.id) AS sales_today,
                           (SELECT IFNULL(SUM(p.grand_total),0) FROM purchases p
                                WHERE (p.del_status IS NULL OR p.del_status='Live') AND IFNULL(p.outlet_id, @def) = o.id) AS pur_total,
                           (SELECT IFNULL(SUM(sp.amount),0) FROM sale_payments sp
                                JOIN payment_methods pm ON pm.Id = sp.payment_id
                                WHERE LOWER(pm.name) LIKE '%cash%' AND IFNULL(sp.outlet_id, @def) = o.id) AS cash_total,
                           (SELECT IFNULL(SUM(sp.amount),0) FROM sale_payments sp
                                JOIN payment_methods pm ON pm.Id = sp.payment_id
                                WHERE LOWER(pm.name) NOT LIKE '%cash%' AND IFNULL(sp.outlet_id, @def) = o.id) AS bank_total,
                           (SELECT IFNULL(SUM(cr.amount),0) FROM customer_receives cr
                                WHERE cr.del_status='Live' AND cr.date=date('now') AND IFNULL(cr.outlet_id, @def) = o.id) AS receipt_total,
                           (SELECT IFNULL(SUM(sp2.amount),0) FROM supplier_payments sp2
                                WHERE sp2.del_status='Live' AND sp2.date=date('now') AND IFNULL(sp2.outlet_id, @def) = o.id) AS payment_total,
                           (SELECT IFNULL(SUM(s2.due_amount),0) FROM sales s2
                                WHERE s2.del_status='Live' AND s2.due_amount > 0 AND IFNULL(s2.outlet_id, @def) = o.id) AS receivables,
                           (SELECT IFNULL(SUM(p2.due_amount),0) FROM purchases p2
                                WHERE (p2.del_status IS NULL OR p2.del_status='Live') AND p2.due_amount > 0 AND IFNULL(p2.outlet_id, @def) = o.id) AS payables
                    FROM outlets o
                    WHERE o.del_status IS NULL OR o.del_status != 'Deleted'
                    ORDER BY o.id";
                cmd.Parameters.AddWithValue("@def", defaultId);

                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    int id = Convert.ToInt32(r["id"]);
                    double sales = GetDbl(r["sales_total"]);
                    double today = GetDbl(r["sales_today"]);
                    double pur = GetDbl(r["pur_total"]);
                    double pl = sales - pur;

                    tSales += sales; tToday += today; tPur += pur; tPL += pl;

                    var card = CreateOutletCard(
                        r["oname"].ToString() ?? "",
                        r["ocode"].ToString() ?? "",
                        r["oaddress"].ToString() ?? "",
                        id == selectedId,
                        id, sales, today, pur, pl,
                        GetDbl(r["cash_total"]), GetDbl(r["bank_total"]),
                        GetDbl(r["receipt_total"]), GetDbl(r["payment_total"]),
                        GetDbl(r["receivables"]), GetDbl(r["payables"])
                    );
                    outletPanel.Children.Add(card);
                }

                lblTotalSales.Text = $"₹ {tSales:N2}";
                lblTodaySales.Text = $"₹ {tToday:N2}";
                lblTotalPurchases.Text = $"₹ {tPur:N2}";
                lblTotalPL.Text = $"₹ {tPL:N2}";
                lblTotalPL.Foreground = tPL >= 0
                    ? new SolidColorBrush((Color)ColorConverter.ConvertFromString("#059669"))
                    : new SolidColorBrush((Color)ColorConverter.ConvertFromString("#E11D48"));
            }
            catch (Exception ex)
            {
                try
                {
                    System.IO.File.AppendAllText(
                        System.IO.Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "RashanKiDukan", "homepage_error.log"),
                        $"{DateTime.Now:HH:mm:ss.fff} {ex}\n");
                }
                catch { }
            }
        }

        private static double GetDbl(object val)
        {
            try { return Convert.ToDouble(val); } catch { return 0; }
        }

        private Border CreateOutletCard(string name, string code, string address, bool isSelected,
            int id, double sales, double today, double pur, double pl,
            double cash, double bank, double receipt, double payment, double receivables, double payables)
        {
            var card = new Border
            {
                Width = 330,
                Margin = new Thickness(0, 0, 16, 16),
                Background = Brushes.White,
                BorderBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString(isSelected ? "#059669" : "#E2E8F0")),
                BorderThickness = new Thickness(isSelected ? 2 : 1),
                CornerRadius = new CornerRadius(14),
                Padding = new Thickness(20, 18, 20, 18),
                Effect = new System.Windows.Media.Effects.DropShadowEffect
                {
                    Color = Colors.Black, BlurRadius = 20, ShadowDepth = 2, Opacity = 0.06
                }
            };

            var stack = new StackPanel();

            var head = new StackPanel { Orientation = Orientation.Horizontal, Margin = new Thickness(0, 0, 0, 4) };
            var icon = new Border
            {
                Width = 44, Height = 44,
                Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString(isSelected ? "#ECFDF5" : "#FFFBEB")),
                CornerRadius = new CornerRadius(12), VerticalAlignment = VerticalAlignment.Center
            };
            icon.Child = new TextBlock
            {
                Text = "🏪", FontSize = 22,
                HorizontalAlignment = HorizontalAlignment.Center, VerticalAlignment = VerticalAlignment.Center
            };
            head.Children.Add(icon);

            var nameStack = new StackPanel { Margin = new Thickness(12, 0, 0, 0), VerticalAlignment = VerticalAlignment.Center };
            nameStack.Children.Add(new TextBlock
            {
                Text = name.Length > 24 ? name.Substring(0, 24) + " ..." : name,
                FontSize = 16.5, FontWeight = FontWeights.Bold, Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#1E293B")),
                FontFamily = ProFont, TextWrapping = TextWrapping.Wrap
            });
            nameStack.Children.Add(new TextBlock
            {
                Text = "Code: " + code + (isSelected ? "   •   ✓ CURRENT" : ""),
                FontSize = 11.5, Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString(isSelected ? "#059669" : "#64748B")),
                FontFamily = ProFont, FontWeight = FontWeights.Medium, Margin = new Thickness(0, 2, 0, 0)
            });
            head.Children.Add(nameStack);
            stack.Children.Add(head);

            if (!string.IsNullOrWhiteSpace(address))
            {
                stack.Children.Add(new TextBlock
                {
                    Text = "📍 " + address,
                    FontSize = 11.5, Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#64748B")),
                    FontFamily = ProFont, TextWrapping = TextWrapping.Wrap, Margin = new Thickness(0, 0, 0, 10)
                });
            }

            stack.Children.Add(new Border { Height = 1, Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#E2E8F0")), Margin = new Thickness(0, 0, 0, 10) });

            AddMetric(stack, "Sales (Total)", $"₹ {sales:N2}", "#059669", true);
            AddMetric(stack, "Sales (Today)", $"₹ {today:N2}", "#059669", true);
            AddMetric(stack, "Purchases", $"₹ {pur:N2}", "#E11D48", true);
            AddMetric(stack, "P&L", $"₹ {pl:N2}", pl >= 0 ? "#059669" : "#E11D48", true);
            AddMetric(stack, "Cash", $"₹ {cash:N2}", "#374151", false);
            AddMetric(stack, "Bank", $"₹ {bank:N2}", "#374151", false);
            AddMetric(stack, "Receipt (Today)", $"₹ {receipt:N2}", "#374151", false);
            AddMetric(stack, "Payment (Today)", $"₹ {payment:N2}", "#374151", false);
            AddMetric(stack, "Receivables", $"₹ {receivables:N2}", "#374151", false);
            AddMetric(stack, "Payables", $"₹ {payables:N2}", "#374151", false);

            var enterBtn = new Button
            {
                Content = isSelected ? "✓ Current Outlet" : "➡ Enter Outlet",
                Height = 38, FontSize = 13, FontWeight = FontWeights.SemiBold,
                Foreground = Brushes.White,
                Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString(isSelected ? "#16A34A" : "#6366F1")),
                BorderThickness = new Thickness(0), Cursor = Cursors.Hand, FontFamily = ProFont,
                Margin = new Thickness(0, 12, 0, 0), Tag = id
            };
            enterBtn.Click += BtnEnter_Click;
            stack.Children.Add(enterBtn);

            card.Child = stack;
            return card;
        }

        private static void AddMetric(StackPanel parent, string label, string value, string colorHex, bool strong)
        {
            var row = new Grid { Margin = new Thickness(0, 0, 0, 5) };
            row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            row.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
            row.Children.Add(new TextBlock
            {
                Text = label,
                FontSize = 11.5,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#64748B")),
                FontFamily = ProFont,
                VerticalAlignment = VerticalAlignment.Center
            });
            row.Children.Add(new TextBlock
            {
                Text = value,
                FontSize = strong ? 13.5 : 12,
                FontWeight = strong ? FontWeights.Bold : FontWeights.SemiBold,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString(colorHex)),
                FontFamily = ProFont,
                VerticalAlignment = VerticalAlignment.Center
            });
            Grid.SetColumn(row.Children[1], 1);
            parent.Children.Add(row);
        }

        private void BtnEnter_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is int id)
            {
                OutletContext.SetSelectedOutletId(id);
                _dashboard?.ShowDashboard();
            }
        }
    }
}
