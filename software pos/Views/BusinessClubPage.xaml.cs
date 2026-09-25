using System;
using System.Collections.Generic;
using System.Data;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Documents;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Shapes;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class BusinessClubPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();
        private List<WalletRow> _allWallets = new();
        private int _profileCustomerId;
        private int _lookedUpCustomerId;

        public BusinessClubPage(MainDashboard? dashboard) : this(dashboard, "dashboard") { }

        public BusinessClubPage(MainDashboard? dashboard, string initialTab)
        {
            InitializeComponent();
            _dashboard = dashboard;
            LoadDashboard();
            LoadSettings();
            LoadWallets();
            LoadCustomersForRegister();
            SelectTab(initialTab);
        }

        private void SelectTab(string tab)
        {
            try
            {
                if (string.IsNullOrEmpty(tab)) return;
                switch (tab.ToLowerInvariant())
                {
                    case "settings": tabSettings.IsChecked = true; break;
                    case "wallets": tabWallets.IsChecked = true; break;
                    case "register": tabRegister.IsChecked = true; break;
                    default: tabDashboard.IsChecked = true; break;
                }
                UpdatePanels();
            }
            catch { }
        }

        private void UpdatePanels()
        {
            if (panelDashboard == null || panelSettings == null || panelWallets == null || panelProfile == null || panelRegister == null) return;
            panelDashboard.Visibility = tabDashboard.IsChecked == true ? Visibility.Visible : Visibility.Collapsed;
            panelSettings.Visibility = tabSettings.IsChecked == true ? Visibility.Visible : Visibility.Collapsed;
            panelWallets.Visibility = tabWallets.IsChecked == true ? Visibility.Visible : Visibility.Collapsed;
            panelRegister.Visibility = tabRegister.IsChecked == true ? Visibility.Visible : Visibility.Collapsed;
            panelProfile.Visibility = Visibility.Collapsed;
        }

        #region Tab Navigation

        private void Tab_Checked(object sender, RoutedEventArgs e)
        {
            // XAML load ke dauran tabDashboard.IsChecked="True" fire hota hai jab
            // baaki tabs abhi null hote hain — us waqt skip karo (InitializeComponent baad me chalta hai).
            if (tabWallets == null || tabRegister == null || panelDashboard == null) return;

            UpdatePanels();

            if (tabDashboard.IsChecked == true) LoadDashboard();
            if (tabWallets.IsChecked == true) LoadWallets();
            if (tabRegister.IsChecked == true) LoadCustomersForRegister();
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            if (_dashboard != null) _dashboard.ShowDashboard();
        }

        #endregion

        #region Dashboard Tab

        private void LoadDashboard()
        {
            try
            {
                using var conn = _db.GetConnection();

                // Get current month boundaries
                var now = DateTime.Now;
                var startOfMonth = new DateTime(now.Year, now.Month, 1).ToString("yyyy-MM-dd");
                var startOfPrevMonth = new DateTime(now.Year, now.Month, 1).AddMonths(-1).ToString("yyyy-MM-dd");
                var endOfPrevMonth = new DateTime(now.Year, now.Month, 1).AddDays(-1).ToString("yyyy-MM-dd");

                // Total Active Members from business_club_members
                long totalMembers = 0;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT COUNT(*) FROM business_club_members WHERE status='active' AND (del_status IS NULL OR del_status='Live')";
                    totalMembers = (long)(cmd.ExecuteScalar() ?? 0L);
                }

                // Fallback: if no members in new table, try customer_wallets for backward compat
                if (totalMembers == 0)
                {
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = "SELECT COUNT(*) FROM customer_wallets WHERE (del_status IS NULL OR del_status='Live')";
                    totalMembers = (long)(cmd.ExecuteScalar() ?? 0L);
                }
                lblTotalWallets.Text = totalMembers.ToString();

                // Total Balance Held (earned_balance across all members)
                double totalBalance = 0;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT IFNULL(SUM(earned_balance),0) FROM business_club_members WHERE status='active' AND (del_status IS NULL OR del_status='Live')";
                    totalBalance = Convert.ToDouble(cmd.ExecuteScalar() ?? 0.0);
                    // Fallback to old table if zero
                    if (totalBalance == 0)
                    {
                        using var cmd2 = conn.CreateCommand();
                        cmd2.CommandText = "SELECT IFNULL(SUM(balance),0) FROM customer_wallets WHERE (del_status IS NULL OR del_status='Live')";
                        totalBalance = Convert.ToDouble(cmd2.ExecuteScalar() ?? 0.0);
                    }
                    lblTotalBalance.Text = "₹" + totalBalance.ToString("N2");
                }

                // Total Earned
                double totalRewarded = 0;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT IFNULL(SUM(total_earned),0) FROM business_club_members WHERE status='active' AND (del_status IS NULL OR del_status='Live')";
                    totalRewarded = Convert.ToDouble(cmd.ExecuteScalar() ?? 0.0);
                    if (totalRewarded == 0)
                    {
                        using var cmd2 = conn.CreateCommand();
                        cmd2.CommandText = "SELECT IFNULL(SUM(total_earned),0) FROM customer_wallets WHERE (del_status IS NULL OR del_status='Live')";
                        totalRewarded = Convert.ToDouble(cmd2.ExecuteScalar() ?? 0.0);
                    }
                    lblTotalRewarded.Text = "₹" + totalRewarded.ToString("N2");
                }

                // Total Redeemed
                double totalRedeemed = 0;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT IFNULL(SUM(total_redeemed),0) FROM business_club_members WHERE status='active' AND (del_status IS NULL OR del_status='Live')";
                    totalRedeemed = Convert.ToDouble(cmd.ExecuteScalar() ?? 0.0);
                    if (totalRedeemed == 0)
                    {
                        using var cmd2 = conn.CreateCommand();
                        cmd2.CommandText = "SELECT IFNULL(SUM(total_redeemed),0) FROM customer_wallets WHERE (del_status IS NULL OR del_status='Live')";
                        totalRedeemed = Convert.ToDouble(cmd2.ExecuteScalar() ?? 0.0);
                    }
                    lblTotalRedeemed.Text = "₹" + totalRedeemed.ToString("N2");
                }

                // Percentage changes - members joined this month vs last month
                long membersThisMonth = 0, membersPrevMonth = 0;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $"SELECT COUNT(*) FROM business_club_members WHERE joined_at >= '{startOfMonth}' AND (del_status IS NULL OR del_status='Live')";
                    membersThisMonth = (long)(cmd.ExecuteScalar() ?? 0L);
                }
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $"SELECT COUNT(*) FROM business_club_members WHERE joined_at >= '{startOfPrevMonth}' AND joined_at < '{startOfMonth}' AND (del_status IS NULL OR del_status='Live')";
                    membersPrevMonth = (long)(cmd.ExecuteScalar() ?? 0L);
                }

                lblWalletsChange.Text = GetPercentChangeText(membersThisMonth, membersPrevMonth);

                // Balance change - credits this month vs prev month from business_club_transactions
                double creditsThisMonth = 0, creditsPrevMonth = 0;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $"SELECT IFNULL(SUM(amount),0) FROM business_club_transactions WHERE type='profit_credit' AND transaction_date >= '{startOfMonth}' AND (del_status IS NULL OR del_status='Live')";
                    creditsThisMonth = Convert.ToDouble(cmd.ExecuteScalar() ?? 0.0);
                }
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $"SELECT IFNULL(SUM(amount),0) FROM business_club_transactions WHERE type='profit_credit' AND transaction_date >= '{startOfPrevMonth}' AND transaction_date < '{startOfMonth}' AND (del_status IS NULL OR del_status='Live')";
                    creditsPrevMonth = Convert.ToDouble(cmd.ExecuteScalar() ?? 0.0);
                }
                lblBalanceChange.Text = GetPercentChangeText(creditsThisMonth, creditsPrevMonth);
                lblRewardedChange.Text = GetPercentChangeText(creditsThisMonth, creditsPrevMonth);

                // Redeemed change
                double redeemsThisMonth = 0, redeemsPrevMonth = 0;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $"SELECT IFNULL(SUM(amount),0) FROM business_club_transactions WHERE type='redemption' AND transaction_date >= '{startOfMonth}' AND (del_status IS NULL OR del_status='Live')";
                    redeemsThisMonth = Convert.ToDouble(cmd.ExecuteScalar() ?? 0.0);
                }
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $"SELECT IFNULL(SUM(amount),0) FROM business_club_transactions WHERE type='redemption' AND transaction_date >= '{startOfPrevMonth}' AND transaction_date < '{startOfMonth}' AND (del_status IS NULL OR del_status='Live')";
                    redeemsPrevMonth = Convert.ToDouble(cmd.ExecuteScalar() ?? 0.0);
                }
                lblRedeemedChange.Text = GetPercentChangeText(redeemsThisMonth, redeemsPrevMonth);

                // Admin Settings Preview
                LoadSettingsPreview(conn);

                // Top 5 Earning Business Partners
                LoadTopEarners(conn);

                // Club Membership Growth
                double growthPct = membersPrevMonth > 0 ? ((double)membersThisMonth / membersPrevMonth * 100) : (membersThisMonth > 0 ? 100 : 0);
                lblGrowthPercent.Text = (growthPct >= 0 ? "+" : "") + growthPct.ToString("N1") + "%";
                lblGrowthPercent.Foreground = new SolidColorBrush(growthPct >= 0 ? (Color)ColorConverter.ConvertFromString("#10B981") : (Color)ColorConverter.ConvertFromString("#EF4444"));
                lblGrowthText.Text = $"{membersThisMonth} new joins this month";
                lblGrowthTotal.Text = $"Total: {totalMembers} members";

                // Recent Club Transactions
                LoadRecentTransactions(conn);
            }
            catch { }
        }

        private void LoadSettingsPreview(Microsoft.Data.Sqlite.SqliteConnection conn)
        {
            try
            {
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT * FROM business_club_settings LIMIT 1";
                using var r = cmd.ExecuteReader();
                if (r.Read())
                {
                    // Try new fields first, fall back to old field names
                    double profitPct = 0;
                    try { profitPct = Convert.ToDouble(r["profit_share_percentage"]); } catch { try { profitPct = Convert.ToDouble(r["profit_percentage"]); } catch { } }
                    lblSettingsProfit.Text = profitPct.ToString() + "%";

                    int redemptionDay = 1;
                    try { redemptionDay = Convert.ToInt32(r["redemption_day"]); } catch { try { redemptionDay = Convert.ToInt32(r["redemption_date"]); } catch { } }
                    lblSettingsRedemption.Text = "Day " + redemptionDay;

                    double membershipAmt = 0;
                    try { membershipAmt = Convert.ToDouble(r["membership_amount"]); } catch { }
                    lblSettingsMinPurchase.Text = "₹" + membershipAmt.ToString("N0");

                    double minBill = 0;
                    try { minBill = Convert.ToDouble(r["minimum_bill_amount"]); } catch { }
                    lblSettingsMinBill.Text = "₹" + minBill.ToString("N0");

                    lblSettingsClubName.Text = r["company_name"]?.ToString() ?? "-";
                }
            }
            catch { }
        }

        private void LoadTopEarners(Microsoft.Data.Sqlite.SqliteConnection conn)
        {
            panelTopEarners.Children.Clear();
            try
            {
                using var cmd = conn.CreateCommand();
                // Query from business_club_members joined with customers
                cmd.CommandText = @"SELECT c.name, IFNULL(m.total_earned, 0) as earned 
                    FROM business_club_members m 
                    LEFT JOIN customers c ON c.id = m.customer_id 
                    WHERE m.status = 'active' AND (m.del_status IS NULL OR m.del_status='Live') AND m.total_earned > 0
                    ORDER BY m.total_earned DESC LIMIT 5";
                using var r = cmd.ExecuteReader();
                int rank = 1;
                while (r.Read())
                {
                    var name = r["name"]?.ToString() ?? "Unknown";
                    var earned = Convert.ToDouble(r["earned"]);
                    AddEarnerRow(name, earned);
                    rank++;
                }
                r.Close();

                // Fallback: if no members in new table, try customer_wallets
                if (rank == 1)
                {
                    using var cmd2 = conn.CreateCommand();
                    cmd2.CommandText = @"SELECT c.name, IFNULL(w.total_earned,0) as earned 
                        FROM customer_wallets w 
                        LEFT JOIN customers c ON c.id = w.customer_id 
                        WHERE (w.del_status IS NULL OR w.del_status='Live') AND w.total_earned > 0
                        ORDER BY w.total_earned DESC LIMIT 5";
                    using var r2 = cmd2.ExecuteReader();
                    while (r2.Read())
                    {
                        var name = r2["name"]?.ToString() ?? "Unknown";
                        var earned = Convert.ToDouble(r2["earned"]);
                        AddEarnerRow(name, earned);
                        rank++;
                    }
                }

                if (rank == 1)
                {
                    panelTopEarners.Children.Add(new TextBlock { Text = "No data yet", FontSize = 12, Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#9CA3AF")) });
                }
            }
            catch { }
        }

        private void AddEarnerRow(string name, double earned)
        {
            var row = new Grid { Margin = new Thickness(0, 0, 0, 8) };
            row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Auto) });
            row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Auto) });

            // Avatar circle
            var avatar = new Border
            {
                Width = 30, Height = 30, CornerRadius = new CornerRadius(15),
                Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#EEF2FF")),
                Margin = new Thickness(0, 0, 10, 0)
            };
            var avatarText = new TextBlock
            {
                Text = name.Length > 0 ? name[0].ToString().ToUpper() : "?",
                FontSize = 12, FontWeight = FontWeights.Bold,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#6366F1")),
                HorizontalAlignment = HorizontalAlignment.Center,
                VerticalAlignment = VerticalAlignment.Center
            };
            avatar.Child = avatarText;
            Grid.SetColumn(avatar, 0);
            row.Children.Add(avatar);

            var nameBlock = new TextBlock
            {
                Text = name, FontSize = 12, Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#374151")),
                VerticalAlignment = VerticalAlignment.Center
            };
            Grid.SetColumn(nameBlock, 1);
            row.Children.Add(nameBlock);

            var earnedBlock = new TextBlock
            {
                Text = "₹" + earned.ToString("N2"), FontSize = 12, FontWeight = FontWeights.SemiBold,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#10B981")),
                VerticalAlignment = VerticalAlignment.Center
            };
            Grid.SetColumn(earnedBlock, 2);
            row.Children.Add(earnedBlock);

            panelTopEarners.Children.Add(row);
        }

        private void LoadRecentTransactions(Microsoft.Data.Sqlite.SqliteConnection conn)
        {
            try
            {
                var list = new List<RecentTransactionRow>();

                // Query business_club_transactions (new table)
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"SELECT bct.transaction_date, c.name, bct.type, 
                        IFNULL(s.total_payable, 0) as sale_amount,
                        bct.amount, bct.balance_after
                        FROM business_club_transactions bct
                        LEFT JOIN customers c ON c.id = bct.customer_id
                        LEFT JOIN sales s ON s.id = bct.sale_id
                        WHERE (bct.del_status IS NULL OR bct.del_status='Live')
                        ORDER BY bct.id DESC LIMIT 20";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        var type = r["type"]?.ToString() ?? "profit_credit";
                        string typeLabel = type switch
                        {
                            "profit_credit" => "Credit",
                            "redemption" => "Redeem",
                            "membership_deposit" => "Deposit",
                            _ => "Credit"
                        };
                        string bgColor = type == "redemption" ? "#FEE2E2" : "#DCFCE7";
                        string fgColor = type == "redemption" ? "#991B1B" : "#166534";

                        list.Add(new RecentTransactionRow
                        {
                            Date = ParseDate(r["transaction_date"]?.ToString()),
                            CustomerName = r["name"]?.ToString() ?? "Unknown",
                            Type = typeLabel,
                            TypeBg = new SolidColorBrush((Color)ColorConverter.ConvertFromString(bgColor)),
                            TypeFg = new SolidColorBrush((Color)ColorConverter.ConvertFromString(fgColor)),
                            SaleAmount = "₹" + Convert.ToDouble(r["sale_amount"]).ToString("N2"),
                            Amount = "₹" + Convert.ToDouble(r["amount"]).ToString("N2"),
                            Balance = "₹" + Convert.ToDouble(r["balance_after"]).ToString("N2")
                        });
                    }
                }

                // Fallback: if no business_club_transactions, try wallet_transactions
                if (list.Count == 0)
                {
                    using var cmd2 = conn.CreateCommand();
                    cmd2.CommandText = @"SELECT wt.transaction_date, c.name, wt.type, 
                        IFNULL(s.total_payable, 0) as sale_amount,
                        wt.amount, wt.balance_after
                        FROM wallet_transactions wt
                        LEFT JOIN customers c ON c.id = wt.customer_id
                        LEFT JOIN sales s ON s.id = wt.sale_id
                        WHERE (wt.del_status IS NULL OR wt.del_status='Live')
                        ORDER BY wt.id DESC LIMIT 20";
                    using var r2 = cmd2.ExecuteReader();
                    while (r2.Read())
                    {
                        var type = r2["type"]?.ToString() ?? "credit";
                        list.Add(new RecentTransactionRow
                        {
                            Date = ParseDate(r2["transaction_date"]?.ToString()),
                            CustomerName = r2["name"]?.ToString() ?? "Unknown",
                            Type = type == "credit" ? "Credit" : "Redeem",
                            TypeBg = new SolidColorBrush((Color)ColorConverter.ConvertFromString(type == "credit" ? "#DCFCE7" : "#FEE2E2")),
                            TypeFg = new SolidColorBrush((Color)ColorConverter.ConvertFromString(type == "credit" ? "#166534" : "#991B1B")),
                            SaleAmount = "₹" + Convert.ToDouble(r2["sale_amount"]).ToString("N2"),
                            Amount = "₹" + Convert.ToDouble(r2["amount"]).ToString("N2"),
                            Balance = "₹" + Convert.ToDouble(r2["balance_after"]).ToString("N2")
                        });
                    }
                }

                gridRecentTransactions.ItemsSource = list;
            }
            catch { }
        }

        private string GetPercentChangeText(double current, double previous)
        {
            if (previous == 0)
                return current > 0 ? "+100% from last month" : "0% from last month";
            double pct = ((current - previous) / previous) * 100;
            string sign = pct >= 0 ? "+" : "";
            return $"{sign}{pct:N1}% from last month";
        }

        private string ParseDate(string? dateStr)
        {
            if (string.IsNullOrEmpty(dateStr)) return "-";
            if (DateTime.TryParse(dateStr, out DateTime dt))
                return dt.ToString("dd MMM yyyy");
            return dateStr;
        }

        #endregion

        #region Settings Tab

        private void LoadSettings()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT * FROM business_club_settings LIMIT 1";
                using var r = cmd.ExecuteReader();
                if (r.Read())
                {
                    txtClubName.Text = r["company_name"]?.ToString() ?? "";

                    // New fields: profit_share_percentage, membership_amount, redemption_day, is_active
                    // Fallback to old field names for backward compat
                    double profitPct = 0;
                    try { profitPct = Convert.ToDouble(r["profit_share_percentage"]); } catch { try { profitPct = Convert.ToDouble(r["profit_percentage"]); } catch { } }
                    txtPointValue.Text = profitPct.ToString();

                    int redemptionDay = 1;
                    try { redemptionDay = Convert.ToInt32(r["redemption_day"]); } catch { try { redemptionDay = Convert.ToInt32(r["redemption_date"]); } catch { } }
                    txtRedemptionDate.Text = redemptionDay.ToString();

                    double membershipAmt = 0;
                    try { membershipAmt = Convert.ToDouble(r["membership_amount"]); } catch { try { membershipAmt = Convert.ToDouble(r["min_purchase_amount"]); } catch { } }
                    txtMinAmount.Text = membershipAmt.ToString();

                    double minBill = 0;
                    try { minBill = Convert.ToDouble(r["minimum_bill_amount"]); } catch { }
                    txtMinBillAmount.Text = minBill.ToString();

                    txtPartnerName.Text = r["business_partner_name"]?.ToString() ?? "";
                    txtPhone.Text = r["phone"]?.ToString() ?? "";
                    txtEmail.Text = r["email"]?.ToString() ?? "";
                    txtAddress.Text = r["address"]?.ToString() ?? "";
                }
            }
            catch { }
        }

        private void BtnSaveSettings_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var check = conn.CreateCommand();
                check.CommandText = "SELECT COUNT(*) FROM business_club_settings";
                long cnt = (long)(check.ExecuteScalar() ?? 0L);

                double profitSharePct = double.TryParse(txtPointValue.Text, out double p) ? p : 1;
                double membershipAmount = double.TryParse(txtMinAmount.Text, out double m) ? m : 0;
                double minBill = double.TryParse(txtMinBillAmount.Text, out double mb) ? mb : 0;
                int redemptionDay = int.TryParse(txtRedemptionDate.Text, out int rd) ? rd : 1;

                if (cnt == 0)
                {
                    using var ins = conn.CreateCommand();
                    ins.CommandText = @"INSERT INTO business_club_settings 
                        (company_name, business_partner_name, phone, email, address, 
                         profit_percentage, profit_share_percentage, redemption_date, redemption_day,
                         min_purchase_amount, minimum_bill_amount, membership_amount, is_active,
                         company_id, del_status, created_at, updated_at) 
                        VALUES (@n, @bp, @ph, @em, @ad, @p, @p, @rd, @rd, @m, @mb, @m, 'yes', 1, 'Live', datetime('now'), datetime('now'))";
                    ins.Parameters.AddWithValue("@n", txtClubName.Text.Trim());
                    ins.Parameters.AddWithValue("@bp", txtPartnerName.Text.Trim());
                    ins.Parameters.AddWithValue("@ph", txtPhone.Text.Trim());
                    ins.Parameters.AddWithValue("@em", txtEmail.Text.Trim());
                    ins.Parameters.AddWithValue("@ad", txtAddress.Text.Trim());
                    ins.Parameters.AddWithValue("@p", profitSharePct);
                    ins.Parameters.AddWithValue("@rd", redemptionDay);
                    ins.Parameters.AddWithValue("@m", membershipAmount);
                    ins.Parameters.AddWithValue("@mb", minBill);
                    ins.ExecuteNonQuery();
                    Services.SyncService.EnqueueSync("business_club_settings", 1, "insert");
                }
                else
                {
                    using var upd = conn.CreateCommand();
                    upd.CommandText = @"UPDATE business_club_settings SET 
                        company_name=@n, business_partner_name=@bp, phone=@ph, email=@em, address=@ad, 
                        profit_percentage=@p, profit_share_percentage=@p, 
                        redemption_date=@rd, redemption_day=@rd,
                        min_purchase_amount=@m, minimum_bill_amount=@mb, membership_amount=@m,
                        is_active='yes', updated_at=datetime('now')";
                    upd.Parameters.AddWithValue("@n", txtClubName.Text.Trim());
                    upd.Parameters.AddWithValue("@bp", txtPartnerName.Text.Trim());
                    upd.Parameters.AddWithValue("@ph", txtPhone.Text.Trim());
                    upd.Parameters.AddWithValue("@em", txtEmail.Text.Trim());
                    upd.Parameters.AddWithValue("@ad", txtAddress.Text.Trim());
                    upd.Parameters.AddWithValue("@p", profitSharePct);
                    upd.Parameters.AddWithValue("@rd", redemptionDay);
                    upd.Parameters.AddWithValue("@m", membershipAmount);
                    upd.Parameters.AddWithValue("@mb", minBill);
                    upd.ExecuteNonQuery();
                    Services.SyncService.EnqueueSync("business_club_settings", 1, "update");
                }
                MessageBox.Show("Settings saved!", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
                LoadDashboard();
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        #endregion

        #region Wallets Tab

        private void LoadWallets()
        {
            try
            {
                using var conn = _db.GetConnection();

                using var cmd = conn.CreateCommand();
                // Query business_club_members joined with customers
                cmd.CommandText = @"SELECT m.customer_id, c.name, c.phone,
                    IFNULL(m.total_earned, 0) as total_earned,
                    IFNULL(m.earned_balance, 0) as balance,
                    IFNULL(m.total_redeemed, 0) as total_redeemed,
                    m.member_id, m.status, IFNULL(m.membership_amount, 0) as membership_amount,
                    IFNULL(m.locked_balance, 0) as locked_balance
                    FROM business_club_members m
                    LEFT JOIN customers c ON c.id = m.customer_id
                    WHERE m.status = 'active' AND (m.del_status IS NULL OR m.del_status='Live')
                    ORDER BY m.total_earned DESC, m.id DESC";

                using var r = cmd.ExecuteReader();
                _allWallets.Clear();
                int sn = 1;
                bool hasData = false;
                while (r.Read())
                {
                    hasData = true;
                    _allWallets.Add(new WalletRow
                    {
                        SN = sn++,
                        CustomerId = Convert.ToInt32(r["customer_id"]),
                        CustomerName = r["name"]?.ToString() ?? "Unknown",
                        Phone = r["phone"]?.ToString() ?? "-",
                        TotalEarned = "₹" + Convert.ToDouble(r["total_earned"]).ToString("N2"),
                        Balance = "₹" + Convert.ToDouble(r["balance"]).ToString("N2"),
                        TotalRedeemed = "₹" + Convert.ToDouble(r["total_redeemed"]).ToString("N2")
                    });
                }
                r.Close();

                // Fallback: if no records in new table, query old customer_wallets
                if (!hasData)
                {
                    using var cmd2 = conn.CreateCommand();
                    cmd2.CommandText = @"SELECT c.id as customer_id, c.name, c.phone,
                        IFNULL(w.total_earned, 0) as total_earned,
                        IFNULL(w.balance, 0) as balance,
                        IFNULL(w.total_redeemed, 0) as total_redeemed
                        FROM customers c
                        LEFT JOIN customer_wallets w ON w.customer_id = c.id AND (w.del_status IS NULL OR w.del_status='Live')
                        WHERE (c.del_status IS NULL OR c.del_status='Live')
                        AND c.name IS NOT NULL AND c.name != ''
                        AND IFNULL(w.total_earned, 0) > 0
                        ORDER BY IFNULL(w.total_earned, 0) DESC, c.id DESC";
                    using var r2 = cmd2.ExecuteReader();
                    while (r2.Read())
                    {
                        _allWallets.Add(new WalletRow
                        {
                            SN = sn++,
                            CustomerId = Convert.ToInt32(r2["customer_id"]),
                            CustomerName = r2["name"]?.ToString() ?? "Unknown",
                            Phone = r2["phone"]?.ToString() ?? "-",
                            TotalEarned = "₹" + Convert.ToDouble(r2["total_earned"]).ToString("N2"),
                            Balance = "₹" + Convert.ToDouble(r2["balance"]).ToString("N2"),
                            TotalRedeemed = "₹" + Convert.ToDouble(r2["total_redeemed"]).ToString("N2")
                        });
                    }
                }

                gridWallets.ItemsSource = _allWallets.ToList();
                lblWalletCount.Text = $"{_allWallets.Count} members";
            }
            catch (Exception ex)
            {
                lblWalletCount.Text = "Error: " + ex.Message;
            }
        }

        private void TxtWalletSearch_TextChanged(object sender, TextChangedEventArgs e)
        {
            var query = txtWalletSearch.Text.Trim().ToLower();
            if (string.IsNullOrEmpty(query))
            {
                gridWallets.ItemsSource = _allWallets.ToList();
                lblWalletCount.Text = $"{_allWallets.Count} members";
            }
            else
            {
                var filtered = _allWallets.Where(w =>
                    w.CustomerName.ToLower().Contains(query) ||
                    w.Phone.ToLower().Contains(query)).ToList();
                gridWallets.ItemsSource = filtered;
                lblWalletCount.Text = $"{filtered.Count} members";
            }
        }

        #endregion

        #region Customer Profile

        private void BtnViewProfile_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag != null)
            {
                int customerId = Convert.ToInt32(btn.Tag);
                ShowCustomerProfile(customerId);
            }
        }

        private void BtnBackToWallets_Click(object sender, RoutedEventArgs e)
        {
            panelProfile.Visibility = Visibility.Collapsed;
            panelWallets.Visibility = Visibility.Visible;
        }

        private void ShowCustomerProfile(int customerId)
        {
            _profileCustomerId = customerId;
            panelWallets.Visibility = Visibility.Collapsed;
            panelProfile.Visibility = Visibility.Visible;

            try
            {
                using var conn = _db.GetConnection();

                // Load customer info
                string customerName = "Unknown", customerPhone = "-";
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT name, phone FROM customers WHERE id = @id";
                    cmd.Parameters.AddWithValue("@id", customerId);
                    using var r = cmd.ExecuteReader();
                    if (r.Read())
                    {
                        customerName = r["name"]?.ToString() ?? "Unknown";
                        customerPhone = r["phone"]?.ToString() ?? "-";
                    }
                }
                lblProfileName.Text = customerName;
                lblProfilePhone.Text = customerPhone;
                lblProfileAvatar.Text = customerName.Length > 0 ? customerName[0].ToString().ToUpper() : "?";

                // Load member info from business_club_members
                double earnedBalance = 0, totalEarned = 0, totalRedeemed = 0, lockedBalance = 0, membershipAmount = 0;
                string memberId = "", memberStatus = "";
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"SELECT member_id, IFNULL(earned_balance,0) as earned_balance, 
                        IFNULL(total_earned,0) as total_earned, IFNULL(total_redeemed,0) as total_redeemed,
                        IFNULL(locked_balance,0) as locked_balance, IFNULL(membership_amount,0) as membership_amount,
                        COALESCE(status,'active') as status
                        FROM business_club_members 
                        WHERE customer_id = @id AND (del_status IS NULL OR del_status='Live')
                        LIMIT 1";
                    cmd.Parameters.AddWithValue("@id", customerId);
                    using var r = cmd.ExecuteReader();
                    if (r.Read())
                    {
                        memberId = r["member_id"]?.ToString() ?? "";
                        earnedBalance = Convert.ToDouble(r["earned_balance"]);
                        totalEarned = Convert.ToDouble(r["total_earned"]);
                        totalRedeemed = Convert.ToDouble(r["total_redeemed"]);
                        lockedBalance = Convert.ToDouble(r["locked_balance"]);
                        membershipAmount = Convert.ToDouble(r["membership_amount"]);
                        memberStatus = r["status"]?.ToString() ?? "active";
                    }
                    else
                    {
                        // Fallback: try customer_wallets
                        using var cmd2 = conn.CreateCommand();
                        cmd2.CommandText = "SELECT IFNULL(balance,0) as balance, IFNULL(total_earned,0) as total_earned, IFNULL(total_redeemed,0) as total_redeemed FROM customer_wallets WHERE customer_id = @id AND (del_status IS NULL OR del_status='Live')";
                        cmd2.Parameters.AddWithValue("@id", customerId);
                        using var r2 = cmd2.ExecuteReader();
                        if (r2.Read())
                        {
                            earnedBalance = Convert.ToDouble(r2["balance"]);
                            totalEarned = Convert.ToDouble(r2["total_earned"]);
                            totalRedeemed = Convert.ToDouble(r2["total_redeemed"]);
                        }
                    }
                }
                lblProfileBalance.Text = "₹" + earnedBalance.ToString("N2");
                lblProfileEarned.Text = "Earned: ₹" + totalEarned.ToString("N2");
                lblProfileRedeemed.Text = "Redeemed: ₹" + totalRedeemed.ToString("N2");

                // Spend Tracker - monthly spend vs membership amount
                double targetAmount = membershipAmount > 0 ? membershipAmount : 10000;
                var startOfMonth = new DateTime(DateTime.Now.Year, DateTime.Now.Month, 1).ToString("yyyy-MM-dd");
                double monthlySpend = 0;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $"SELECT IFNULL(SUM(total_payable),0) FROM sales WHERE customer_id = @id AND sale_date >= '{startOfMonth}' AND (del_status IS NULL OR del_status='Live')";
                    cmd.Parameters.AddWithValue("@id", customerId);
                    monthlySpend = Convert.ToDouble(cmd.ExecuteScalar() ?? 0.0);
                }

                double spendPercent = targetAmount > 0 ? Math.Min((monthlySpend / targetAmount) * 100, 100) : 0;
                lblSpendPercent.Text = spendPercent.ToString("N0") + "%";
                lblSpendInfo.Text = $"₹{monthlySpend:N0} / ₹{targetAmount:N0} target";

                // Update progress ring
                double circumference = Math.PI * 120;
                double dashLength = (spendPercent / 100.0) * circumference;
                double gapLength = circumference - dashLength;
                double relDash = dashLength / 10.0;
                double relGap = gapLength / 10.0;
                progressRing.StrokeDashArray = new DoubleCollection { relDash, relGap };

                // Recent Profit Shares (last 10 credit transactions)
                LoadProfitShares(conn, customerId);

                // Recent Sales
                LoadRecentSales(conn, customerId);

                // Full Transaction History
                LoadProfileTransactions(conn, customerId);
            }
            catch (Exception ex)
            {
                lblProfileName.Text = "Error: " + ex.Message;
            }
        }

        private void LoadProfitShares(Microsoft.Data.Sqlite.SqliteConnection conn, int customerId)
        {
            panelProfitShares.Children.Clear();
            try
            {
                using var cmd = conn.CreateCommand();
                // Query from business_club_transactions
                cmd.CommandText = @"SELECT transaction_date, amount, description FROM business_club_transactions 
                    WHERE customer_id = @id AND type='profit_credit' AND (del_status IS NULL OR del_status='Live')
                    ORDER BY id DESC LIMIT 10";
                cmd.Parameters.AddWithValue("@id", customerId);
                using var r = cmd.ExecuteReader();
                bool hasData = false;
                while (r.Read())
                {
                    hasData = true;
                    var row = new Grid { Margin = new Thickness(0, 0, 0, 6) };
                    row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                    row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Auto) });

                    var left = new StackPanel();
                    left.Children.Add(new TextBlock
                    {
                        Text = "+" + "₹" + Convert.ToDouble(r["amount"]).ToString("N2"),
                        FontSize = 12, FontWeight = FontWeights.SemiBold,
                        Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#10B981"))
                    });
                    left.Children.Add(new TextBlock
                    {
                        Text = r["description"]?.ToString() ?? "",
                        FontSize = 10.5,
                        Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#9CA3AF"))
                    });
                    Grid.SetColumn(left, 0);
                    row.Children.Add(left);

                    var dateBlock = new TextBlock
                    {
                        Text = ParseDate(r["transaction_date"]?.ToString()),
                        FontSize = 10.5, VerticalAlignment = VerticalAlignment.Center,
                        Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#6B7280"))
                    };
                    Grid.SetColumn(dateBlock, 1);
                    row.Children.Add(dateBlock);

                    panelProfitShares.Children.Add(row);

                    // Divider
                    panelProfitShares.Children.Add(new Border
                    {
                        Height = 1, Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#F3F4F6")),
                        Margin = new Thickness(0, 2, 0, 2)
                    });
                }

                // Fallback: if no data in new table, try wallet_transactions
                if (!hasData)
                {
                    using var cmd2 = conn.CreateCommand();
                    cmd2.CommandText = @"SELECT transaction_date, amount, description FROM wallet_transactions 
                        WHERE customer_id = @id AND type='credit' AND (del_status IS NULL OR del_status='Live')
                        ORDER BY id DESC LIMIT 10";
                    cmd2.Parameters.AddWithValue("@id", customerId);
                    using var r2 = cmd2.ExecuteReader();
                    while (r2.Read())
                    {
                        hasData = true;
                        var row = new Grid { Margin = new Thickness(0, 0, 0, 6) };
                        row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                        row.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Auto) });

                        var left = new StackPanel();
                        left.Children.Add(new TextBlock
                        {
                            Text = "+" + "₹" + Convert.ToDouble(r2["amount"]).ToString("N2"),
                            FontSize = 12, FontWeight = FontWeights.SemiBold,
                            Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#10B981"))
                        });
                        left.Children.Add(new TextBlock
                        {
                            Text = r2["description"]?.ToString() ?? "",
                            FontSize = 10.5,
                            Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#9CA3AF"))
                        });
                        Grid.SetColumn(left, 0);
                        row.Children.Add(left);

                        var dateBlock = new TextBlock
                        {
                            Text = ParseDate(r2["transaction_date"]?.ToString()),
                            FontSize = 10.5, VerticalAlignment = VerticalAlignment.Center,
                            Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#6B7280"))
                        };
                        Grid.SetColumn(dateBlock, 1);
                        row.Children.Add(dateBlock);

                        panelProfitShares.Children.Add(row);
                        panelProfitShares.Children.Add(new Border
                        {
                            Height = 1, Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#F3F4F6")),
                            Margin = new Thickness(0, 2, 0, 2)
                        });
                    }
                }

                if (!hasData)
                {
                    panelProfitShares.Children.Add(new TextBlock { Text = "No profit shares yet", FontSize = 12, Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#9CA3AF")) });
                }
            }
            catch { }
        }

        private void LoadRecentSales(Microsoft.Data.Sqlite.SqliteConnection conn, int customerId)
        {
            panelRecentSales.Children.Clear();
            try
            {
                using var cmd2 = conn.CreateCommand();
                cmd2.CommandText = @"SELECT s.id, s.invoice_no, s.sale_date, 
                    IFNULL(s.total_payable, IFNULL(s.grand_total, 0)) as total_payable, 
                    IFNULL(s.paid_amount, 0) as paid, 
                    IFNULL(s.due_amount, 0) as due_amount
                    FROM sales s
                    WHERE s.customer_id = @id AND (s.del_status IS NULL OR s.del_status='Live')
                    ORDER BY s.id DESC";
                cmd2.Parameters.AddWithValue("@id", customerId);
                using var r2 = cmd2.ExecuteReader();
                bool hasData = false;
                var sales = new List<(int id, string invoiceNo, string date, double total, double paid, double due)>();
                while (r2.Read())
                {
                    hasData = true;
                    sales.Add((
                        Convert.ToInt32(r2["id"]),
                        r2["invoice_no"]?.ToString() ?? "-",
                        r2["sale_date"]?.ToString() ?? "",
                        Convert.ToDouble(r2["total_payable"]),
                        Convert.ToDouble(r2["paid"]),
                        Convert.ToDouble(r2["due_amount"])
                    ));
                }
                r2.Close();

                if (!hasData)
                {
                    panelRecentSales.Children.Add(new TextBlock { Text = "No purchases yet", FontSize = 12, Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#9CA3AF")) });
                    return;
                }

                // Total stats
                double totalPurchase = sales.Sum(s => s.total);
                double totalDue = sales.Sum(s => s.due);
                var statsRow = new StackPanel { Orientation = Orientation.Horizontal, Margin = new Thickness(0, 0, 0, 10) };
                statsRow.Children.Add(new TextBlock
                {
                    Text = $"Total: ₹{totalPurchase:N2}",
                    FontSize = 12, FontWeight = FontWeights.Bold, Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#111827")),
                    Margin = new Thickness(0, 0, 16, 0)
                });
                statsRow.Children.Add(new TextBlock
                {
                    Text = $"Bills: {sales.Count}",
                    FontSize = 12, Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#6B7280")),
                    Margin = new Thickness(0, 0, 16, 0)
                });
                if (totalDue > 0)
                {
                    statsRow.Children.Add(new TextBlock
                    {
                        Text = $"Due: ₹{totalDue:N2}",
                        FontSize = 12, FontWeight = FontWeights.SemiBold, Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#EF4444"))
                    });
                }
                panelRecentSales.Children.Add(statsRow);

                panelRecentSales.Children.Add(new Border { Height = 1, Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#E5E7EB")), Margin = new Thickness(0, 4, 0, 8) });

                foreach (var sale in sales.Take(20))
                {
                    var saleHeader = new Grid { Margin = new Thickness(0, 0, 0, 4) };
                    saleHeader.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                    saleHeader.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Auto) });

                    var leftPanel = new StackPanel();
                    leftPanel.Children.Add(new TextBlock
                    {
                        Text = $"#{sale.invoiceNo}  •  {ParseDate(sale.date)}",
                        FontSize = 11.5, FontWeight = FontWeights.SemiBold, Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#374151"))
                    });
                    Grid.SetColumn(leftPanel, 0);
                    saleHeader.Children.Add(leftPanel);

                    var amountBlock = new TextBlock
                    {
                        Text = "₹" + sale.total.ToString("N2"),
                        FontSize = 12, FontWeight = FontWeights.Bold, VerticalAlignment = VerticalAlignment.Center,
                        Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#111827"))
                    };
                    Grid.SetColumn(amountBlock, 1);
                    saleHeader.Children.Add(amountBlock);
                    panelRecentSales.Children.Add(saleHeader);

                    using var itemCmd = conn.CreateCommand();
                    itemCmd.CommandText = @"SELECT i.name, sd.qty, sd.menu_unit_price as unit_price
                        FROM sale_details sd
                        LEFT JOIN items i ON i.id = sd.item_id
                        WHERE sd.id IN (
                            SELECT MIN(sd2.id) FROM sale_details sd2 
                            WHERE sd2.sales_id = @sid AND (sd2.del_status IS NULL OR sd2.del_status='Live')
                            GROUP BY sd2.item_id
                        )";
                    itemCmd.Parameters.AddWithValue("@sid", sale.id);
                    using var ir = itemCmd.ExecuteReader();
                    while (ir.Read())
                    {
                        var itemName = ir["name"]?.ToString() ?? "Item";
                        var qty = Convert.ToDouble(ir["qty"]);
                        var unitPrice = Convert.ToDouble(ir["unit_price"]);
                        var subTotal = qty * unitPrice;

                        var itemRow = new TextBlock
                        {
                            Text = $"   • {itemName}  ×{qty:N0}  @₹{unitPrice:N2}  = ₹{subTotal:N2}",
                            FontSize = 11, Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#6B7280")),
                            Margin = new Thickness(8, 1, 0, 1)
                        };
                        panelRecentSales.Children.Add(itemRow);
                    }

                    panelRecentSales.Children.Add(new Border
                    {
                        Height = 1, Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#F3F4F6")),
                        Margin = new Thickness(0, 6, 0, 6)
                    });
                }

                if (sales.Count > 20)
                {
                    panelRecentSales.Children.Add(new TextBlock
                    {
                        Text = $"... and {sales.Count - 20} more bills",
                        FontSize = 11, Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#9CA3AF")),
                        Margin = new Thickness(0, 4, 0, 0)
                    });
                }
            }
            catch { }
        }

        private void LoadProfileTransactions(Microsoft.Data.Sqlite.SqliteConnection conn, int customerId)
        {
            try
            {
                var list = new List<ProfileTransactionRow>();

                // Query from business_club_transactions first
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"SELECT transaction_date, type, amount, balance_after, description 
                        FROM business_club_transactions 
                        WHERE customer_id = @id AND (del_status IS NULL OR del_status='Live')
                        ORDER BY id DESC";
                    cmd.Parameters.AddWithValue("@id", customerId);
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        var type = r["type"]?.ToString() ?? "profit_credit";
                        string typeLabel = type switch
                        {
                            "profit_credit" => "Credit",
                            "redemption" => "Redeem",
                            "membership_deposit" => "Deposit",
                            _ => type
                        };
                        list.Add(new ProfileTransactionRow
                        {
                            Date = ParseDate(r["transaction_date"]?.ToString()),
                            Type = typeLabel,
                            Amount = "₹" + Convert.ToDouble(r["amount"]).ToString("N2"),
                            BalanceAfter = "₹" + Convert.ToDouble(r["balance_after"]).ToString("N2"),
                            Description = r["description"]?.ToString() ?? ""
                        });
                    }
                }

                // Fallback: if no data in new table, try wallet_transactions
                if (list.Count == 0)
                {
                    using var cmd2 = conn.CreateCommand();
                    cmd2.CommandText = @"SELECT transaction_date, type, amount, balance_after, description 
                        FROM wallet_transactions 
                        WHERE customer_id = @id AND (del_status IS NULL OR del_status='Live')
                        ORDER BY id DESC";
                    cmd2.Parameters.AddWithValue("@id", customerId);
                    using var r2 = cmd2.ExecuteReader();
                    while (r2.Read())
                    {
                        list.Add(new ProfileTransactionRow
                        {
                            Date = ParseDate(r2["transaction_date"]?.ToString()),
                            Type = (r2["type"]?.ToString() ?? "credit") == "credit" ? "Credit" : "Redeem",
                            Amount = "₹" + Convert.ToDouble(r2["amount"]).ToString("N2"),
                            BalanceAfter = "₹" + Convert.ToDouble(r2["balance_after"]).ToString("N2"),
                            Description = r2["description"]?.ToString() ?? ""
                        });
                    }
                }

                gridProfileTransactions.ItemsSource = list;
            }
            catch { }
        }

        private void BtnRedeem_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                using var conn = _db.GetConnection();

                // ── Load member info ─────────────────────────────────────────────
                double currentEarnedBalance = 0;
                string memberId = "";
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"SELECT member_id, IFNULL(earned_balance, 0) as earned_balance 
                        FROM business_club_members 
                        WHERE customer_id = @id AND status = 'active' AND (del_status IS NULL OR del_status='Live')
                        LIMIT 1";
                    cmd.Parameters.AddWithValue("@id", _profileCustomerId);
                    using var r = cmd.ExecuteReader();
                    if (r.Read())
                    {
                        memberId = r["member_id"]?.ToString() ?? "";
                        currentEarnedBalance = Convert.ToDouble(r["earned_balance"]);
                    }
                }

                // ── Fallback: old customer_wallets ───────────────────────────────
                if (string.IsNullOrEmpty(memberId))
                {
                    int walletId = 0;
                    double walletBalance = 0;
                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.CommandText = "SELECT id, IFNULL(balance,0) as balance FROM customer_wallets WHERE customer_id = @id AND (del_status IS NULL OR del_status='Live')";
                        cmd.Parameters.AddWithValue("@id", _profileCustomerId);
                        using var r = cmd.ExecuteReader();
                        if (r.Read()) { walletId = Convert.ToInt32(r["id"]); walletBalance = Convert.ToDouble(r["balance"]); }
                    }
                    if (walletBalance <= 0) { MessageBox.Show("No balance to redeem.", "Info", MessageBoxButton.OK, MessageBoxImage.Information); return; }

                    // Partial amount support for legacy wallet too
                    double redeemAmt = walletBalance;
                    string rawTxt = txtRedeemAmount?.Text?.Trim() ?? "";
                    if (!string.IsNullOrEmpty(rawTxt) && double.TryParse(rawTxt, out double parsed) && parsed > 0)
                        redeemAmt = Math.Min(parsed, walletBalance);

                    if (redeemAmt <= 0) { MessageBox.Show("Valid amount daalein.", "Info", MessageBoxButton.OK, MessageBoxImage.Information); return; }

                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.CommandText = @"INSERT INTO wallet_transactions (wallet_id, customer_id, type, amount, balance_before, balance_after, description, transaction_date, company_id, del_status, SyncStatus)
                            VALUES (@wid, @cid, 'redeem', @amount, @before, @after, 'Partial wallet redemption', datetime('now'), 1, 'Live', 'Local')";
                        cmd.Parameters.AddWithValue("@wid", walletId);
                        cmd.Parameters.AddWithValue("@cid", _profileCustomerId);
                        cmd.Parameters.AddWithValue("@amount", redeemAmt);
                        cmd.Parameters.AddWithValue("@before", walletBalance);
                        cmd.Parameters.AddWithValue("@after", walletBalance - redeemAmt);
                        cmd.ExecuteNonQuery();
                    }
                    using (var cmd = conn.CreateCommand())
                    {
                        // Fix: balance properly reduce karo (was = 0 bug)
                        cmd.CommandText = "UPDATE customer_wallets SET balance = MAX(0, IFNULL(balance,0) - @amount), total_redeemed = IFNULL(total_redeemed,0) + @amount, updated_at = datetime('now') WHERE customer_id = @id";
                        cmd.Parameters.AddWithValue("@amount", redeemAmt);
                        cmd.Parameters.AddWithValue("@id", _profileCustomerId);
                        cmd.ExecuteNonQuery();
                    }
                    if (txtRedeemAmount != null) txtRedeemAmount.Text = "";
                    MessageBox.Show($"₹{redeemAmt:N2} redeemed successfully!", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
                    ShowCustomerProfile(_profileCustomerId);
                    return;
                }

                if (currentEarnedBalance <= 0)
                {
                    MessageBox.Show("No earned balance to redeem.", "Info", MessageBoxButton.OK, MessageBoxImage.Information);
                    return;
                }

                // ── Partial amount: user input ya full balance ───────────────────
                double redeemAmount = currentEarnedBalance; // default: full
                string rawInput = txtRedeemAmount?.Text?.Trim() ?? "";
                if (!string.IsNullOrEmpty(rawInput))
                {
                    if (!double.TryParse(rawInput, out double enteredAmount) || enteredAmount <= 0)
                    {
                        MessageBox.Show("Valid amount daalein.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                        return;
                    }
                    if (enteredAmount > currentEarnedBalance)
                    {
                        MessageBox.Show($"Amount available balance se zyada hai.\nAvailable: ₹{currentEarnedBalance:N2}", "Insufficient Balance", MessageBoxButton.OK, MessageBoxImage.Warning);
                        return;
                    }
                    redeemAmount = enteredAmount;
                }

                // ── Redemption day check ─────────────────────────────────────────
                int redemptionDay = 1;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT COALESCE(redemption_day, redemption_date, 1) FROM business_club_settings LIMIT 1";
                    var val = cmd.ExecuteScalar();
                    if (val != null && val != DBNull.Value) redemptionDay = Convert.ToInt32(val);
                }

                if (DateTime.Now.Day != redemptionDay)
                {
                    var confirmResult = MessageBox.Show(
                        $"Redemption typically day {redemptionDay} ko hoti hai.\nAaj (day {DateTime.Now.Day}) redeem karna chahte hain?",
                        "Confirm Early Redemption", MessageBoxButton.YesNo, MessageBoxImage.Question);
                    if (confirmResult != MessageBoxResult.Yes) return;
                }

                // ── Confirm amount ───────────────────────────────────────────────
                var confirm = MessageBox.Show(
                    $"₹{redeemAmount:N2} redeem karein?\n(Remaining after: ₹{currentEarnedBalance - redeemAmount:N2})",
                    "Confirm Redemption", MessageBoxButton.YesNo, MessageBoxImage.Question);
                if (confirm != MessageBoxResult.Yes) return;

                double newBalance = currentEarnedBalance - redeemAmount;

                // ── Insert transaction ───────────────────────────────────────────
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"INSERT INTO business_club_transactions 
                        (member_id, customer_id, type, amount, balance_before, balance_after, description, transaction_date, company_id, del_status, created_at, updated_at, SyncStatus)
                        VALUES (@mid, @cid, 'redemption', @amount, @before, @after, @desc, date('now'), 1, 'Live', datetime('now'), datetime('now'), 'Local')";
                    cmd.Parameters.AddWithValue("@mid", memberId);
                    cmd.Parameters.AddWithValue("@cid", _profileCustomerId);
                    cmd.Parameters.AddWithValue("@amount", redeemAmount);
                    cmd.Parameters.AddWithValue("@before", currentEarnedBalance);
                    cmd.Parameters.AddWithValue("@after", newBalance);
                    cmd.Parameters.AddWithValue("@desc", $"Profit redeemed - ₹{redeemAmount:N2}");
                    cmd.ExecuteNonQuery();
                }

                // ── Update business_club_members ─────────────────────────────────
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"UPDATE business_club_members 
                        SET earned_balance = @newBal, 
                            total_redeemed = IFNULL(total_redeemed, 0) + @amount, 
                            updated_at = datetime('now') 
                        WHERE member_id = @mid AND customer_id = @cid";
                    cmd.Parameters.AddWithValue("@newBal", newBalance);
                    cmd.Parameters.AddWithValue("@amount", redeemAmount);
                    cmd.Parameters.AddWithValue("@mid", memberId);
                    cmd.Parameters.AddWithValue("@cid", _profileCustomerId);
                    cmd.ExecuteNonQuery();
                }

                // ── customer_wallets backward compat (proportional reduce, not = 0) ──
                try
                {
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = "UPDATE customer_wallets SET balance = MAX(0, IFNULL(balance,0) - @amount), total_redeemed = IFNULL(total_redeemed,0) + @amount, updated_at = datetime('now') WHERE customer_id = @id AND (del_status IS NULL OR del_status='Live')";
                    cmd.Parameters.AddWithValue("@amount", redeemAmount);
                    cmd.Parameters.AddWithValue("@id", _profileCustomerId);
                    cmd.ExecuteNonQuery();
                }
                catch { /* backward compat - non-critical */ }

                // ── Sync to cloud ────────────────────────────────────────────────
                // business_club_transactions + business_club_members dono sync honge
                Services.SyncService.EnqueueSync("business_club_transactions", 0, "insert");
                Services.SyncService.EnqueueSync("business_club_members", 0, "update");

                if (txtRedeemAmount != null) txtRedeemAmount.Text = "";
                MessageBox.Show($"₹{redeemAmount:N2} redeemed successfully!\nRemaining balance: ₹{newBalance:N2}", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
                ShowCustomerProfile(_profileCustomerId);
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        /// <summary>Redeem amount input: sirf numbers aur ek decimal point allow karo.</summary>
        private void RedeemAmount_PreviewTextInput(object sender, System.Windows.Input.TextCompositionEventArgs e)
        {
            foreach (char c in e.Text)
                if (!char.IsDigit(c) && c != '.') { e.Handled = true; return; }
            if (sender is System.Windows.Controls.TextBox tb && tb.Text.Contains('.') && e.Text.Contains('.'))
                e.Handled = true;
        }

        #endregion

        #region Register Member Tab

        private void LoadCustomersForRegister()
        {
            // Ab koi dropdown nahi — mobile/name/email se lookup hota hai.
        }

        private void TxtRegMobile_TextChanged(object sender, TextChangedEventArgs e)
        {
            try
            {
                string raw = txtRegMobile.Text;
                string digits = new string(raw.Where(char.IsDigit).ToArray());
                if (digits.Length > 10) digits = digits.Substring(0, 10);
                if (digits != raw)
                {
                    txtRegMobile.Text = digits;
                    txtRegMobile.CaretIndex = digits.Length;
                }
                string phone = digits;
                if (phone.Length < 5)
                {
                    lblRegCustomerFound.Visibility = Visibility.Collapsed;
                    return;
                }

                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT id, name, email FROM customers 
                    WHERE phone = @ph AND (del_status IS NULL OR del_status='Live') LIMIT 1";
                cmd.Parameters.AddWithValue("@ph", phone);
                using var r = cmd.ExecuteReader();
                if (r.Read())
                {
                    _lookedUpCustomerId = Convert.ToInt32(r["id"]);
                    if (string.IsNullOrWhiteSpace(txtRegName.Text))
                        txtRegName.Text = r["name"]?.ToString() ?? "";
                    if (string.IsNullOrWhiteSpace(txtRegEmail.Text))
                        txtRegEmail.Text = r["email"]?.ToString() ?? "";
                    lblRegCustomerFound.Text = $"Customer mil gaya: {r["name"]} (ID {r["id"]}) — isi member banega.";
                    lblRegCustomerFound.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#10B981"));
                    lblRegCustomerFound.Visibility = Visibility.Visible;
                }
                else
                {
                    _lookedUpCustomerId = 0;
                    lblRegCustomerFound.Text = "Koi existing customer nahi mila — naya customer bhi ban jayega.";
                    lblRegCustomerFound.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#F59E0B"));
                    lblRegCustomerFound.Visibility = Visibility.Visible;
                }
            }
            catch { }
        }

        private void MobileOnly_PreviewTextInput(object sender, TextCompositionEventArgs e)
        {
            foreach (char c in e.Text)
                if (!char.IsDigit(c)) { e.Handled = true; return; }
            if (sender is TextBox tb && tb.Text.Length >= 10) e.Handled = true;
        }

        private void MobileOnly_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Space) e.Handled = true;
        }

        private void DecimalOnly_PreviewTextInput(object sender, TextCompositionEventArgs e)
        {
            foreach (char c in e.Text)
                if (!char.IsDigit(c) && c != '.') { e.Handled = true; return; }
            if (sender is TextBox tb && tb.Text.Contains('.') && e.Text.Contains('.')) e.Handled = true;
        }

        private void DecimalOnly_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (sender is not TextBox tb) return;
            string clean = new string(tb.Text.Where(c => char.IsDigit(c) || c == '.').ToArray());
            int dot = clean.IndexOf('.');
            if (dot >= 0)
                clean = clean.Substring(0, dot + 1) + new string(clean.Substring(dot + 1).Where(char.IsDigit).ToArray());
            if (clean != tb.Text)
            {
                int pos = tb.CaretIndex;
                tb.Text = clean;
                tb.CaretIndex = Math.Min(pos, clean.Length);
            }
        }

        private void BtnRegisterMember_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                string phone = txtRegMobile.Text.Trim();
                string name = txtRegName.Text.Trim();
                string email = txtRegEmail.Text.Trim();

                if (string.IsNullOrEmpty(phone) || phone.Length < 5)
                {
                    MessageBox.Show("Mobile number daalein (kam se kam 5 digit).", "Info", MessageBoxButton.OK, MessageBoxImage.Information);
                    return;
                }
                if (string.IsNullOrEmpty(name))
                {
                    MessageBox.Show("Customer ka naam daalein.", "Info", MessageBoxButton.OK, MessageBoxImage.Information);
                    return;
                }
                if (!double.TryParse(txtRegisterAmount.Text, out double amount) || amount < 0)
                {
                    MessageBox.Show("Membership amount sahi daalein.", "Info", MessageBoxButton.OK, MessageBoxImage.Information);
                    return;
                }

                using var conn = _db.GetConnection();

                // 1) Existing customer by phone?
                int customerId = _lookedUpCustomerId;
                if (customerId <= 0)
                {
                    using var find = conn.CreateCommand();
                    find.CommandText = "SELECT id FROM customers WHERE phone = @ph AND (del_status IS NULL OR del_status='Live') LIMIT 1";
                    find.Parameters.AddWithValue("@ph", phone);
                    var found = find.ExecuteScalar();
                    if (found != null) customerId = Convert.ToInt32(found);
                }

                // 2) Nahi mila → naya customer banao
                if (customerId <= 0)
                {
                    using var ins = conn.CreateCommand();
                    ins.CommandText = @"INSERT INTO customers 
                        (name, phone, email, company_id, del_status, created_at, updated_at, SyncStatus)
                        VALUES (@n, @ph, @em, 1, 'Live', datetime('now'), datetime('now'), 'Local')";
                    ins.Parameters.AddWithValue("@n", name);
                    ins.Parameters.AddWithValue("@ph", phone);
                    ins.Parameters.AddWithValue("@em", string.IsNullOrEmpty(email) ? DBNull.Value : (object)email);
                    ins.ExecuteNonQuery();
                    using var getid = conn.CreateCommand();
                    getid.CommandText = "SELECT last_insert_rowid()";
                    customerId = Convert.ToInt32(getid.ExecuteScalar());
                    Services.SyncService.EnqueueSync("customers", customerId, "insert");
                }
                else
                {
                    // Existing customer — naam/email update kar do agar khaali nahi hai
                    try
                    {
                        using var upd = conn.CreateCommand();
                        upd.CommandText = @"UPDATE customers SET name = @n, 
                            email = COALESCE(@em, email), updated_at = datetime('now') WHERE id = @cid";
                        upd.Parameters.AddWithValue("@n", name);
                        upd.Parameters.AddWithValue("@em", string.IsNullOrEmpty(email) ? DBNull.Value : (object)email);
                        upd.Parameters.AddWithValue("@cid", customerId);
                        upd.ExecuteNonQuery();
                        Services.SyncService.EnqueueSync("customers", customerId, "update");
                    }
                    catch { }
                }

                // Already a member?
                using (var check = conn.CreateCommand())
                {
                    check.CommandText = @"SELECT COUNT(*) FROM business_club_members 
                        WHERE customer_id = @cid AND status='active' AND (del_status IS NULL OR del_status='Live')";
                    check.Parameters.AddWithValue("@cid", customerId);
                    if ((long)(check.ExecuteScalar() ?? 0L) > 0)
                    {
                        MessageBox.Show("Ye customer pehle se active member hai.", "Info", MessageBoxButton.OK, MessageBoxImage.Information);
                        return;
                    }
                }

                // Next member sequence
                long nextSeq = 1;
                using (var seq = conn.CreateCommand())
                {
                    seq.CommandText = "SELECT IFNULL(MAX(id),0)+1 FROM business_club_members";
                    nextSeq = (long)(seq.ExecuteScalar() ?? 1L);
                }
                string memberId = "BC-" + nextSeq.ToString("D5");

                // Insert member
                long newMemberId;
                using (var ins = conn.CreateCommand())
                {
                    ins.CommandText = @"INSERT INTO business_club_members 
                        (member_id, customer_id, company_id, membership_amount, locked_balance, earned_balance, total_earned, total_redeemed, status, joined_at, del_status, created_at, updated_at, SyncStatus)
                        VALUES (@mid, @cid, 1, @amt, @amt, 0, 0, 0, 'active', datetime('now'), 'Live', datetime('now'), datetime('now'), 'Local')";
                    ins.Parameters.AddWithValue("@mid", memberId);
                    ins.Parameters.AddWithValue("@cid", customerId);
                    ins.Parameters.AddWithValue("@amt", amount);
                    ins.ExecuteNonQuery();
                }
                using (var getid = conn.CreateCommand())
                {
                    getid.CommandText = "SELECT last_insert_rowid()";
                    newMemberId = (long)(getid.ExecuteScalar() ?? 0L);
                }

                // Membership deposit transaction
                long newTxId;
                using (var tx = conn.CreateCommand())
                {
                    tx.CommandText = @"INSERT INTO business_club_transactions 
                        (member_id, customer_id, type, amount, balance_before, balance_after, description, transaction_date, company_id, del_status, created_at, updated_at, SyncStatus)
                        VALUES (@mid, @cid, 'membership_deposit', @amt, 0, @amt, 'Membership registration', date('now'), 1, 'Live', datetime('now'), datetime('now'), 'Local')";
                    tx.Parameters.AddWithValue("@mid", newMemberId);
                    tx.Parameters.AddWithValue("@cid", customerId);
                    tx.Parameters.AddWithValue("@amt", amount);
                    tx.ExecuteNonQuery();
                }
                using (var getid = conn.CreateCommand())
                {
                    getid.CommandText = "SELECT last_insert_rowid()";
                    newTxId = (long)(getid.ExecuteScalar() ?? 0L);
                }

                // Also ensure a wallet row exists for backward compat
                try
                {
                    using var w = conn.CreateCommand();
                    w.CommandText = @"INSERT OR IGNORE INTO customer_wallets (customer_id, balance, total_earned, total_redeemed, company_id, del_status, updated_at)
                        VALUES (@cid, 0, 0, 0, 1, 'Live', datetime('now'))";
                    w.Parameters.AddWithValue("@cid", customerId);
                    w.ExecuteNonQuery();
                }
                catch { }

                // Push to cloud via pending sync queue (generic push-entity handles it)
                Services.SyncService.EnqueueSync("business_club_members", newMemberId, "insert");
                Services.SyncService.EnqueueSync("business_club_transactions", newTxId, "insert");

                LoadWallets();
                MessageBox.Show($"Member {memberId} register ho gaya!", "Success", MessageBoxButton.OK, MessageBoxImage.Information);

                // Form clear
                txtRegMobile.Clear();
                txtRegName.Clear();
                txtRegEmail.Clear();
                txtRegisterAmount.Text = "0";
                _lookedUpCustomerId = 0;
                lblRegCustomerFound.Visibility = Visibility.Collapsed;
            }
            catch (Exception ex)
            {
                MessageBox.Show("Register error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        #endregion

        #region ID Card Print

        private void BtnPrintIdCard_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                if (_profileCustomerId <= 0) return;

                string name = lblProfileName.Text, phone = lblProfilePhone.Text;
                string balance = lblProfileBalance.Text;
                string memberId = "";
                string joined = "";
                using (var conn = _db.GetConnection())
                {
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = @"SELECT member_id, joined_at, IFNULL(membership_amount,0) as membership_amount 
                        FROM business_club_members 
                        WHERE customer_id = @cid AND (del_status IS NULL OR del_status='Live') LIMIT 1";
                    cmd.Parameters.AddWithValue("@cid", _profileCustomerId);
                    using var r = cmd.ExecuteReader();
                    if (r.Read())
                    {
                        memberId = r["member_id"]?.ToString() ?? "";
                        joined = r["joined_at"]?.ToString() ?? "";
                    }
                }

                string clubName = "Business Club";
                using (var conn = _db.GetConnection())
                {
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = "SELECT company_name FROM business_club_settings LIMIT 1";
                    var v = cmd.ExecuteScalar();
                    if (v != null && !string.IsNullOrEmpty(v.ToString())) clubName = v.ToString();
                }

                PrintIdCard(clubName, memberId, name, phone, balance, joined);
            }
            catch (Exception ex)
            {
                MessageBox.Show("Print error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private static void PrintIdCard(string clubName, string memberId, string name, string phone, string balance, string joined)
        {
            var dlg = new PrintDialog();
            if (dlg.ShowDialog() != true) return;

            var flowDoc = new FlowDocument
            {
                PageWidth = 54 * 96 / 25.4,   // 54mm ID card width
                PageHeight = 86 * 96 / 25.4,  // 86mm height
                PagePadding = new Thickness(16),
                FontFamily = new FontFamily("Segoe UI"),
                FontSize = 11
            };

            var card = new Border
            {
                BorderBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#6366F1")),
                BorderThickness = new Thickness(2),
                CornerRadius = new CornerRadius(8),
                Padding = new Thickness(12),
                Background = new SolidColorBrush(Colors.White)
            };

            var stack = new StackPanel();

            stack.Children.Add(new TextBlock
            {
                Text = clubName,
                FontSize = 15,
                FontWeight = FontWeights.Bold,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#1E1B4B")),
                HorizontalAlignment = HorizontalAlignment.Center,
                TextAlignment = TextAlignment.Center
            });
            stack.Children.Add(new TextBlock
            {
                Text = "BUSINESS CLUB MEMBER",
                FontSize = 9,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#6366F1")),
                HorizontalAlignment = HorizontalAlignment.Center,
                Margin = new Thickness(0, 0, 0, 8)
            });

            // Member ID box
            var idBox = new Border
            {
                Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#EEF2FF")),
                CornerRadius = new CornerRadius(6),
                Padding = new Thickness(8),
                Margin = new Thickness(0, 0, 0, 8)
            };
            var idStack = new StackPanel();
            idStack.Children.Add(new TextBlock
            {
                Text = memberId,
                FontSize = 17,
                FontWeight = FontWeights.Bold,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#1E1B4B")),
                HorizontalAlignment = HorizontalAlignment.Center
            });
            idStack.Children.Add(new TextBlock
            {
                Text = "MEMBER ID",
                FontSize = 8,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#6B7280")),
                HorizontalAlignment = HorizontalAlignment.Center
            });
            idBox.Child = idStack;
            stack.Children.Add(idBox);

            stack.Children.Add(new TextBlock
            {
                Text = name,
                FontSize = 14,
                FontWeight = FontWeights.SemiBold,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#111827")),
                HorizontalAlignment = HorizontalAlignment.Center,
                Margin = new Thickness(0, 2, 0, 2)
            });
            stack.Children.Add(new TextBlock
            {
                Text = phone,
                FontSize = 10,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#6B7280")),
                HorizontalAlignment = HorizontalAlignment.Center,
                Margin = new Thickness(0, 0, 0, 8)
            });

            stack.Children.Add(new Border
            {
                Height = 1,
                Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#E5E7EB")),
                Margin = new Thickness(0, 0, 0, 8)
            });

            var row = new Grid();
            row.ColumnDefinitions.Add(new ColumnDefinition());
            row.ColumnDefinitions.Add(new ColumnDefinition());
            var left = new StackPanel();
            left.Children.Add(new TextBlock { Text = "BALANCE", FontSize = 8, Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#9CA3AF")) });
            left.Children.Add(new TextBlock { Text = balance, FontSize = 12, FontWeight = FontWeights.Bold, Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#10B981")) });
            var right = new StackPanel();
            right.Children.Add(new TextBlock { Text = "JOINED", FontSize = 8, Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#9CA3AF")) });
            right.Children.Add(new TextBlock
            {
                Text = string.IsNullOrEmpty(joined) ? "-" : (DateTime.TryParse(joined, out var dt) ? dt.ToString("dd MMM yyyy") : joined),
                FontSize = 12,
                FontWeight = FontWeights.SemiBold,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#111827"))
            });
            Grid.SetColumn(left, 0);
            Grid.SetColumn(right, 1);
            row.Children.Add(left);
            row.Children.Add(right);
            stack.Children.Add(row);

            card.Child = stack;
            flowDoc.Blocks.Add(new BlockUIContainer(card));

            var paginator = ((IDocumentPaginatorSource)flowDoc).DocumentPaginator;
            dlg.PrintDocument(paginator, "Business Club ID Card");
        }

        #endregion

        #region Data Models

        private class CustomerOption
        {
            public int Id { get; set; }
            public string Display { get; set; } = "";
        }

        private class WalletRow
        {
            public int SN { get; set; }
            public int CustomerId { get; set; }
            public string CustomerName { get; set; } = "";
            public string Phone { get; set; } = "";
            public string TotalEarned { get; set; } = "";
            public string Balance { get; set; } = "";
            public string TotalRedeemed { get; set; } = "";
        }

        private class RecentTransactionRow
        {
            public string Date { get; set; } = "";
            public string CustomerName { get; set; } = "";
            public string Type { get; set; } = "";
            public SolidColorBrush TypeBg { get; set; } = new SolidColorBrush(Colors.Transparent);
            public SolidColorBrush TypeFg { get; set; } = new SolidColorBrush(Colors.Black);
            public string SaleAmount { get; set; } = "";
            public string Amount { get; set; } = "";
            public string Balance { get; set; } = "";
        }

        private class ProfileTransactionRow
        {
            public string Date { get; set; } = "";
            public string Type { get; set; } = "";
            public string Amount { get; set; } = "";
            public string BalanceAfter { get; set; } = "";
            public string Description { get; set; } = "";
        }

        #endregion
    }
}
