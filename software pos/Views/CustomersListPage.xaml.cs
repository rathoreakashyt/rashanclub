using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;
using Microsoft.Win32;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class CustomersListPage : UserControl, ISyncRefreshable
    {
        private readonly DatabaseService _db = new();
        private readonly MainDashboard? _dashboard;
        private string _currentFilter = "All";
        private string _searchQuery = "";
        private List<CustomerListItem> _allRows = new();
        private List<CustomerListItem> _filteredRows = new();
        private int _currentPage = 1;
        private const int PageSize = 10;

        public CustomersListPage() { InitializeComponent(); }
        public CustomersListPage(MainDashboard dashboard) : this() { _dashboard = dashboard; LoadCustomers(); }

        // ═══ DATA LOADING (real SQLite) ═══

        // Cloud/Web se change aaya (sync pull) → page khud reload — bina refresh ke
        public void OnSyncPulled()
        {
            Dispatcher.BeginInvoke(new Action(() =>
            {
                if (IsLoaded) LoadCustomers();
            }), System.Windows.Threading.DispatcherPriority.Background);
        }

        private void LoadCustomers()
        {
            _allRows.Clear();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                string where = "WHERE m.MasterType='Party' AND m.PartyType IN ('Customer','Both')" +
                    " AND (m.DelStatus IS NULL OR m.DelStatus != 'Deleted')" +
                    " AND LOWER(TRIM(m.Name)) != 'walk-in customer'";
                if (_currentFilter == "Active") where += " AND m.IsActive=1";
                else if (_currentFilter == "Inactive") where += " AND m.IsActive=0";

                cmd.CommandText = $@"SELECT m.Code, m.Name, COALESCE(NULLIF(m.Phone,''), NULLIF(m.Mobile,''), '') AS phone,
                                            COALESCE(m.Email,'') AS email,
                                            COALESCE(m.OpeningBalance,0) AS opening_balance,
                                            COALESCE(m.DrCr,'Dr') AS balance_type,
                                            m.IsActive,
                                            COALESCE((SELECT SUM(due_amount) FROM sales s
                                                      WHERE s.customer_id = m.ServerId AND (s.del_status IS NULL OR s.del_status='Live')),0) AS due,
                                            COALESCE((SELECT SUM(amount) FROM customer_receives cr
                                                      WHERE cr.customer_id = m.ServerId AND (cr.del_status IS NULL OR cr.del_status='Live')),0) AS received,
                                            COALESCE((SELECT SUM(due) FROM sale_returns sr
                                                      WHERE sr.customer_id = m.ServerId AND (sr.del_status IS NULL OR sr.del_status='Live')),0) AS ret
                                     FROM Master1 m {where} ORDER BY m.Name";

                using var r = cmd.ExecuteReader();
                int sn = 1;
                while (r.Read())
                {
                    double due = r["due"] is double dd ? dd : 0;
                    double received = r["received"] is double rd ? rd : 0;
                    double ret = r["ret"] is double td ? td : 0;
                    double opening = r["opening_balance"] is double ob ? ob : 0;
                    string obt = r["balance_type"]?.ToString() ?? "Dr";
                    double balance = obt == "Cr" || obt == "Credit"
                        ? -opening - received + due - ret
                        : opening - received + due - ret;
                    string type = balance >= 0 ? "Debit" : "Credit";
                    bool active = r["IsActive"] is long a ? a == 1 : true;

                    _allRows.Add(new CustomerListItem
                    {
                        Sn = sn++,
                        Code = r["Code"]?.ToString() ?? "",
                        Name = r["Name"]?.ToString() ?? "",
                        Phone = r["phone"]?.ToString() ?? "",
                        Email = r["email"]?.ToString() ?? "",
                        OpeningBalance = $"₹{Math.Abs(opening):N2}",
                        CurrentBalance = $"₹{Math.Abs(balance):N2} ({type})",
                        BalanceType = type,
                        BalanceRaw = balance,
                        Status = active ? "Active" : "Inactive"
                    });
                }

                ApplyFilter();

                string notDeleted = " AND (DelStatus IS NULL OR DelStatus != 'Deleted') AND LOWER(TRIM(Name)) != 'walk-in customer'";
                using var cAll = conn.CreateCommand(); cAll.CommandText = "SELECT COUNT(*) FROM Master1 WHERE MasterType='Party' AND PartyType IN ('Customer','Both')" + notDeleted; tabAll.Content = $"All ({(long)cAll.ExecuteScalar()})";
                using var cAct = conn.CreateCommand(); cAct.CommandText = "SELECT COUNT(*) FROM Master1 WHERE MasterType='Party' AND PartyType IN ('Customer','Both') AND IsActive=1" + notDeleted; tabActive.Content = $"Active ({(long)cAct.ExecuteScalar()})";
                using var cInact = conn.CreateCommand(); cInact.CommandText = "SELECT COUNT(*) FROM Master1 WHERE MasterType='Party' AND PartyType IN ('Customer','Both') AND IsActive=0" + notDeleted; tabInactive.Content = $"Inactive ({(long)cInact.ExecuteScalar()})";

                using var cTotal = conn.CreateCommand(); cTotal.CommandText = "SELECT COUNT(*) FROM Master1 WHERE MasterType='Party' AND PartyType IN ('Customer','Both')" + notDeleted;
                lblTotal.Text = $"{(long)cTotal.ExecuteScalar()} customers";
            }
            catch { }
        }

        // ═══ FILTERS ═══

        private void LoadFilterOptions()
        {
            cmbBalance.Items.Clear();
            cmbBalance.Items.Add("All Balances");
            cmbBalance.Items.Add("Debit");
            cmbBalance.Items.Add("Credit");
            cmbBalance.SelectedIndex = 0;
        }

        private void ApplyFilter()
        {
            string q = _searchQuery.ToLowerInvariant();
            string balF = cmbBalance?.SelectedItem?.ToString() ?? "All Balances";

            _filteredRows = _allRows.Where(x =>
                (q == "" ||
                 x.Name.ToLowerInvariant().Contains(q) ||
                 x.Phone.ToLowerInvariant().Contains(q) ||
                 x.Email.ToLowerInvariant().Contains(q)) &&
                (balF == "All Balances" || x.BalanceType == balF)
            ).ToList();

            int totalPages = Math.Max(1, (int)Math.Ceiling((double)_filteredRows.Count / PageSize));
            if (_currentPage > totalPages) _currentPage = totalPages;

            int start = (_currentPage - 1) * PageSize;
            int end = Math.Min(start + PageSize, _filteredRows.Count);
            var pageItems = _filteredRows.GetRange(start, Math.Max(0, end - start));

            dgCustomers.ItemsSource = pageItems;
            bool empty = _filteredRows.Count == 0;
            dgCustomers.Visibility = empty ? Visibility.Collapsed : Visibility.Visible;
            emptyState.Visibility = empty ? Visibility.Visible : Visibility.Collapsed;

            lblShowing.Text = _filteredRows.Count == 0
                ? "Showing 0 entries"
                : $"Showing {start + 1} to {end} of {_filteredRows.Count} entries";

            double debit = _filteredRows.Where(x => x.BalanceType == "Debit").Sum(x => x.BalanceRaw);
            double credit = _filteredRows.Where(x => x.BalanceType == "Credit").Sum(x => Math.Abs(x.BalanceRaw));
            lblCustCount.Text = _filteredRows.Count.ToString();
            lblDebitTotal.Text = $"₹{debit:N2}";
            lblCreditTotal.Text = $"₹{credit:N2}";

            BuildPageButtons(totalPages);
        }

        private void BuildPageButtons(int totalPages)
        {
            pageButtons.Items.Clear();
            for (int p = 1; p <= totalPages; p++)
            {
                var btn = new Button
                {
                    Content = p.ToString(),
                    FontSize = 12,
                    FontFamily = new FontFamily("Inter, Segoe UI"),
                    FontWeight = p == _currentPage ? FontWeights.SemiBold : FontWeights.Normal,
                    Padding = new Thickness(10, 5, 10, 5),
                    Cursor = System.Windows.Input.Cursors.Hand,
                    Margin = new Thickness(2, 0, 2, 0),
                    MinWidth = 34,
                    BorderThickness = new Thickness(1),
                    BorderBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#D1D5DB")),
                    Background = p == _currentPage
                        ? new SolidColorBrush((Color)ColorConverter.ConvertFromString("#696CFF"))
                        : new SolidColorBrush(Colors.White),
                    Foreground = p == _currentPage
                        ? new SolidColorBrush(Colors.White)
                        : new SolidColorBrush((Color)ColorConverter.ConvertFromString("#374151"))
                };
                btn.Template = CreatePageButtonTemplate();
                int pageNum = p;
                btn.Click += (s, e) => { _currentPage = pageNum; ApplyFilter(); };
                pageButtons.Items.Add(btn);
            }
            btnPrev.IsEnabled = _currentPage > 1;
            btnNext.IsEnabled = _currentPage < totalPages;
            btnPrev.Opacity = btnPrev.IsEnabled ? 1 : 0.5;
            btnNext.Opacity = btnNext.IsEnabled ? 1 : 0.5;
        }

        private static ControlTemplate CreatePageButtonTemplate()
        {
            var template = new ControlTemplate(typeof(Button));
            var border = new FrameworkElementFactory(typeof(Border));
            border.SetValue(Border.BackgroundProperty, new TemplateBindingExtension(Button.BackgroundProperty));
            border.SetValue(Border.BorderBrushProperty, new TemplateBindingExtension(Button.BorderBrushProperty));
            border.SetValue(Border.BorderThicknessProperty, new TemplateBindingExtension(Button.BorderThicknessProperty));
            border.SetValue(Border.CornerRadiusProperty, new CornerRadius(7));
            border.SetValue(Border.PaddingProperty, new TemplateBindingExtension(Button.PaddingProperty));
            var content = new FrameworkElementFactory(typeof(ContentPresenter));
            content.SetValue(ContentPresenter.VerticalAlignmentProperty, VerticalAlignment.Center);
            content.SetValue(ContentPresenter.HorizontalAlignmentProperty, HorizontalAlignment.Center);
            border.AppendChild(content);
            template.VisualTree = border;

            var hoverTrigger = new Trigger { Property = Control.IsMouseOverProperty, Value = true };
            hoverTrigger.Setters.Add(new Setter(Border.BackgroundProperty, new SolidColorBrush((Color)ColorConverter.ConvertFromString("#EEF2FF")), border.Name));
            template.Triggers.Add(hoverTrigger);
            template.Seal();
            return template;
        }

        // ═══ EVENT HANDLERS ═══

        private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (txtSearch == null) return;
            _searchQuery = txtSearch.Text.Trim();
            _currentPage = 1;
            ApplyFilter();
        }

        private void Tab_Click(object sender, RoutedEventArgs e)
        {
            if (tabAll.IsChecked == true) _currentFilter = "All";
            else if (tabActive.IsChecked == true) _currentFilter = "Active";
            else if (tabInactive.IsChecked == true) _currentFilter = "Inactive";
            _currentPage = 1;
            LoadCustomers();
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e) { _dashboard?.ShowDashboard(); }

        private void BtnAddCustomer_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowPage(new CreateCustomerV2Page(_dashboard!));
        }

        private void BtnToggleFilter_Click(object sender, RoutedEventArgs e)
        {
            if (filterCard.Visibility == Visibility.Collapsed)
            {
                LoadFilterOptions();
                filterCard.Visibility = Visibility.Visible;
            }
            else filterCard.Visibility = Visibility.Collapsed;
        }

        private void BtnApplyFilter_Click(object sender, RoutedEventArgs e) { _currentPage = 1; ApplyFilter(); }

        private void BtnClearFilter_Click(object sender, RoutedEventArgs e)
        {
            if (cmbBalance.Items.Count > 0) cmbBalance.SelectedIndex = 0;
            _currentPage = 1;
            ApplyFilter();
        }

        private void BtnPagePrev(object sender, RoutedEventArgs e) { if (_currentPage > 1) { _currentPage--; ApplyFilter(); } }
        private void BtnPageNext(object sender, RoutedEventArgs e)
        {
            int totalPages = Math.Max(1, (int)Math.Ceiling((double)_filteredRows.Count / PageSize));
            if (_currentPage < totalPages) { _currentPage++; ApplyFilter(); }
        }

        private void BtnCsvExport_Click(object sender, RoutedEventArgs e)
        {
            if (_filteredRows.Count == 0) { MessageBox.Show("No data to export.", "Export", MessageBoxButton.OK, MessageBoxImage.Information); return; }

            var dlg = new SaveFileDialog
            {
                Title = "Export Customers",
                Filter = "CSV files (*.csv)|*.csv",
                FileName = "Customers_" + DateTime.Now.ToString("yyyyMMdd_HHmm") + ".csv"
            };
            if (dlg.ShowDialog() != true) return;

            try
            {
                var sb = new System.Text.StringBuilder();
                sb.AppendLine("SN,Name,Phone,Email,Opening Balance,Current Balance,Status");
                foreach (var x in _filteredRows)
                {
                    sb.AppendLine(string.Join(",",
                        Csv(x.Sn.ToString()), Csv(x.Name), Csv(x.Phone), Csv(x.Email),
                        Csv(x.OpeningBalance), Csv(x.CurrentBalance), Csv(x.Status)));
                }
                File.WriteAllText(dlg.FileName, sb.ToString(), System.Text.Encoding.UTF8);
                MessageBox.Show("Export complete.\n" + dlg.FileName, "Export", MessageBoxButton.OK, MessageBoxImage.Information);
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Export", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private static string Csv(string v) => "\"" + v.Replace("\"", "\"\"") + "\"";

        private void BtnEdit_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is string code && code != "")
            {
                _dashboard?.ShowPage(new CreateCustomerV2Page(_dashboard!, code));
            }
        }

        private void BtnDelete_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is not string code || code == "") return;
            var result = MessageBox.Show("Delete this customer?", "Confirm", MessageBoxButton.YesNo, MessageBoxImage.Question);
            if (result != MessageBoxResult.Yes) return;

            try
            {
                using var conn = _db.GetConnection();
                long serverId = 0;
                using (var f = conn.CreateCommand())
                {
                    f.CommandText = "SELECT ServerId FROM Master1 WHERE Code=@code";
                    f.Parameters.AddWithValue("@code", code);
                    serverId = f.ExecuteScalar() is long s ? s : 0;
                }

                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"UPDATE Master1 SET IsActive=0, DelStatus='Deleted', UpdatedAt=datetime('now') WHERE Code=@code";
                cmd.Parameters.AddWithValue("@code", code);
                cmd.ExecuteNonQuery();

                if (serverId > 0)
                {
                    using var cc = conn.CreateCommand();
                    cc.CommandText = @"UPDATE customers SET del_status='Deleted', updated_at=datetime('now')
                                      WHERE id=@sid OR ServerId=@sid";
                    cc.Parameters.AddWithValue("@sid", serverId);
                    cc.ExecuteNonQuery();

                    Services.SyncService.EnqueueSync("customers", serverId, "delete");
                    _dashboard?.TriggerSync();
                }
                else
                {
                    // Never-synced local customer (ServerId=0): also remove the
                    // mirror row so F2/POS lookups don't show a deleted customer.
                    // Match by phone/name (no ServerId to match on).
                    string phone = "";
                    using (var fp = conn.CreateCommand())
                    {
                        fp.CommandText = "SELECT IFNULL(Phone,'') FROM Master1 WHERE Code=@code";
                        fp.Parameters.AddWithValue("@code", code);
                        phone = fp.ExecuteScalar()?.ToString() ?? "";
                    }
                    if (!string.IsNullOrEmpty(phone))
                    {
                        using var cc = conn.CreateCommand();
                        cc.CommandText = @"UPDATE customers SET del_status='Deleted', updated_at=datetime('now')
                                          WHERE (IFNULL(ServerId,0)=0 OR ServerId IS NULL) AND del_status='Live' AND LOWER(TRIM(IFNULL(Phone,''))) = LOWER(TRIM(@p))";
                        cc.Parameters.AddWithValue("@p", phone);
                        cc.ExecuteNonQuery();
                    }
                }

                LoadCustomers();
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }
    }

    public class CustomerListItem
    {
        public int Sn { get; set; }
        public string Code { get; set; } = "";
        public string Name { get; set; } = "";
        public string Phone { get; set; } = "";
        public string Email { get; set; } = "";
        public string OpeningBalance { get; set; } = "";
        public string CurrentBalance { get; set; } = "";
        public string BalanceType { get; set; } = "Debit";
        public double BalanceRaw { get; set; }
        public string Status { get; set; } = "Active";

        public Brush StatusBg => Status == "Active"
            ? new SolidColorBrush((Color)ColorConverter.ConvertFromString("#E8FFD6"))
            : new SolidColorBrush((Color)ColorConverter.ConvertFromString("#F1F1F4"));

        public Brush StatusFg => Status == "Active"
            ? new SolidColorBrush((Color)ColorConverter.ConvertFromString("#4CAF50"))
            : new SolidColorBrush((Color)ColorConverter.ConvertFromString("#8E8EA1"));
    }
}
