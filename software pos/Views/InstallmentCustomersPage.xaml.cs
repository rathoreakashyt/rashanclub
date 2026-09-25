using System;
using System.Collections.Generic;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class InstallmentCustomersPage : UserControl, ISyncRefreshable
    {
        private readonly DatabaseService _db = new();
        private readonly MainDashboard? _dashboard;
        private List<InstallmentCustomerRow> _allRows = new();
        private List<InstallmentCustomerRow> _filteredRows = new();
        private int _currentPage = 1;
        private const int PageSize = 10;
        private int _totalCount;

        public InstallmentCustomersPage() { InitializeComponent(); }
        public InstallmentCustomersPage(MainDashboard dashboard) : this()
        {
            _dashboard = dashboard;
            LoadData();
        }

        // Cloud/Web se change aaya (sync pull) → page khud reload — bina refresh ke
        public void OnSyncPulled()
        {
            Dispatcher.BeginInvoke(new Action(() =>
            {
                if (IsLoaded) LoadData();
            }), System.Windows.Threading.DispatcherPriority.Background);
        }

        private void LoadData()
        {
            _allRows.Clear();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT c.id, c.name, c.phone, c.email,
                                           c.address AS permanent_address,
                                           c.work_address, c.guarantor_name,
                                           c.guarantor_mobile, c.opening_balance,
                                           c.opening_balance_type
                                    FROM customers c
                                    WHERE c.is_installment_customer='Yes'
                                      AND (c.del_status IS NULL OR c.del_status='Live')
                                    ORDER BY c.id DESC";
                using var r = cmd.ExecuteReader();
                int sn = 1;
                while (r.Read())
                {
                    string phone = r["phone"]?.ToString() ?? "";
                    if (!phone.StartsWith("+91") && phone.Length == 10)
                        phone = "+91-" + phone;

                    double ob = r["opening_balance"] is double v ? v : 0;
                    string obType = r["opening_balance_type"]?.ToString() ?? "Dr";
                    string obDisplay = obType == "Cr"
                        ? $"-₹ {Math.Abs(ob):N2}"
                        : $"₹ {Math.Abs(ob):N2}";

                    _allRows.Add(new InstallmentCustomerRow
                    {
                        Sn = sn++,
                        Id = r["id"] is long id ? id : 0,
                        Name = r["name"]?.ToString() ?? "",
                        Phone = phone,
                        Email = r["email"]?.ToString() ?? "",
                        PermanentAddress = r["permanent_address"]?.ToString() ?? "",
                        WorkAddress = r["work_address"]?.ToString() ?? "",
                        GuarantorName = r["guarantor_name"]?.ToString() ?? "",
                        GuarantorMobile = r["guarantor_mobile"]?.ToString() ?? "",
                        OpeningBalance = obDisplay
                    });
                }

                _totalCount = _allRows.Count;
                ApplyFilter();
            }
            catch
            {
                emptyState.Visibility = Visibility.Visible;
            }
        }

        private void ApplyFilter()
        {
            string q = txtSearch.Text.Trim().ToLowerInvariant();
            _filteredRows = q == ""
                ? new List<InstallmentCustomerRow>(_allRows)
                : _allRows.FindAll(x =>
                    x.Name.ToLowerInvariant().Contains(q) ||
                    x.Phone.ToLowerInvariant().Contains(q) ||
                    x.Email.ToLowerInvariant().Contains(q));

            _totalCount = _filteredRows.Count;
            int totalPages = Math.Max(1, (int)Math.Ceiling((double)_totalCount / PageSize));
            if (_currentPage > totalPages) _currentPage = totalPages;

            int start = (_currentPage - 1) * PageSize;
            int end = Math.Min(start + PageSize, _filteredRows.Count);

            rowsContainer.Children.Clear();
            if (_filteredRows.Count == 0)
            {
                emptyState.Visibility = Visibility.Visible;
                rowsContainer.Children.Add(emptyState);
            }
            else
            {
                emptyState.Visibility = Visibility.Collapsed;
                for (int i = start; i < end; i++)
                    rowsContainer.Children.Add(BuildRow(_filteredRows[i]));
            }

            int from = _totalCount == 0 ? 0 : start + 1;
            lblFooter.Text = $"Showing {from} to {end} of {_totalCount} entries";
            lblTotal.Text = $"{_totalCount} customers";
            btnPrev.IsEnabled = _currentPage > 1;
            btnNext.IsEnabled = _currentPage < totalPages;
            BuildPageButtons(totalPages);
        }

        private Border BuildRow(InstallmentCustomerRow row)
        {
            var g = new Grid { Height = 40 };
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(50) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1.5, GridUnitType.Star) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1.2, GridUnitType.Star) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1.5, GridUnitType.Star) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1.3, GridUnitType.Star) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1.3, GridUnitType.Star) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1.2, GridUnitType.Star) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1.2, GridUnitType.Star) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1.1, GridUnitType.Star) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(100) });

            var cells = new[]
            {
                row.Sn.ToString(), row.Name, row.Phone, row.Email,
                row.PermanentAddress, row.WorkAddress,
                row.GuarantorName, row.GuarantorMobile,
                row.OpeningBalance, ""
            };
            for (int c = 0; c < cells.Length; c++)
            {
                if (c == 9) continue;
                var tb = new TextBlock
                {
                    Text = cells[c],
                    Style = (Style)FindResource("DataCell"),
                    Margin = c == 0 ? new Thickness(16, 0, 0, 0) : new Thickness(0)
                };
                Grid.SetColumn(tb, c);
                g.Children.Add(tb);
            }

            // Action buttons
            var actionPanel = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };
            actionPanel.Margin = new Thickness(0, 0, 16, 0);

            var editBtn = new Button
            {
                Content = "\uE70F",
                FontFamily = new FontFamily("Segoe MDL2 Assets"),
                FontSize = 15,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#6366F1")),
                Background = Brushes.Transparent,
                BorderThickness = new Thickness(0),
                Cursor = Cursors.Hand,
                Padding = new Thickness(4, 2, 4, 2),
                Tag = row.Id
            };
            editBtn.Click += BtnEdit_Click;

            var deleteBtn = new Button
            {
                Content = "\uE74D",
                FontFamily = new FontFamily("Segoe MDL2 Assets"),
                FontSize = 15,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#EF4444")),
                Background = Brushes.Transparent,
                BorderThickness = new Thickness(0),
                Cursor = Cursors.Hand,
                Padding = new Thickness(4, 2, 4, 2),
                Tag = row.Id
            };
            deleteBtn.Click += BtnDelete_Click;

            actionPanel.Children.Add(editBtn);
            actionPanel.Children.Add(deleteBtn);
            Grid.SetColumn(actionPanel, 9);
            g.Children.Add(actionPanel);

            return new Border
            {
                BorderBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#F3F4F6")),
                BorderThickness = new Thickness(0, 0, 0, 1),
                Child = g
            };
        }

        private void BuildPageButtons(int totalPages)
        {
            pageButtons.Items.Clear();
            if (totalPages <= 1) return;
            for (int p = 1; p <= totalPages; p++)
            {
                bool isActive = p == _currentPage;
                var btn = new Button
                {
                    Content = p.ToString(),
                    FontFamily = new FontFamily("Segoe UI"),
                    FontSize = 12,
                    Width = 32,
                    Height = 32,
                    Margin = new Thickness(2, 0, 2, 0),
                    Cursor = Cursors.Hand,
                    Tag = p,
                    Foreground = isActive
                        ? Brushes.White
                        : new SolidColorBrush((Color)ColorConverter.ConvertFromString("#374151")),
                    Background = isActive
                        ? new SolidColorBrush((Color)ColorConverter.ConvertFromString("#6366F1"))
                        : Brushes.Transparent,
                    BorderThickness = new Thickness(0)
                };
                btn.Click += PageBtn_Click;
                pageButtons.Items.Add(btn);
            }
        }

        private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (txtSearch != null) { _currentPage = 1; ApplyFilter(); }
        }

        private void BtnPrev_Click(object sender, RoutedEventArgs e)
        {
            if (_currentPage > 1) { _currentPage--; ApplyFilter(); }
        }

        private void BtnNext_Click(object sender, RoutedEventArgs e)
        {
            int totalPages = Math.Max(1, (int)Math.Ceiling((double)_filteredRows.Count / PageSize));
            if (_currentPage < totalPages) { _currentPage++; ApplyFilter(); }
        }

        private void PageBtn_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is int p) { _currentPage = p; ApplyFilter(); }
        }

        private void BtnAdd_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowPage(new CreateInstallmentCustomerPage(_dashboard!));
        }

        private void BtnEdit_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is long id && id > 0)
            {
                _dashboard?.ShowPage(new CreateInstallmentCustomerPage(_dashboard!, id));
            }
        }

        private void BtnDelete_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is not long id || id <= 0) return;
            var result = MessageBox.Show("Delete this installment customer?", "Confirm",
                MessageBoxButton.YesNo, MessageBoxImage.Question);
            if (result != MessageBoxResult.Yes) return;

            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "UPDATE customers SET del_status='Deleted', updated_at=datetime('now') WHERE id=@id";
                cmd.Parameters.AddWithValue("@id", id);
                cmd.ExecuteNonQuery();

                using var mc = conn.CreateCommand();
                mc.CommandText = @"UPDATE Master1 SET IsActive=0, DelStatus='Deleted',
                                    SyncStatus='Synced', UpdatedAt=datetime('now')
                                    WHERE ServerId=@id AND (MasterType='Party' OR MasterType IS NULL)";
                mc.Parameters.AddWithValue("@id", id);
                mc.ExecuteNonQuery();

                Services.SyncService.EnqueueSync("customers", id, "delete");
                _dashboard?.TriggerSync();
                LoadData();
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }
    }

    public class InstallmentCustomerRow
    {
        public int Sn { get; set; }
        public long Id { get; set; }
        public string Name { get; set; } = "";
        public string Phone { get; set; } = "";
        public string Email { get; set; } = "";
        public string PermanentAddress { get; set; } = "";
        public string WorkAddress { get; set; } = "";
        public string GuarantorName { get; set; } = "";
        public string GuarantorMobile { get; set; } = "";
        public string OpeningBalance { get; set; } = "";
    }
}
