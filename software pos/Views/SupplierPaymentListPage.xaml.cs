using System;
using System.Collections.Generic;
using System.Globalization;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public class SupplierPaymentRow
    {
        public int Sn { get; set; }
        public long Id { get; set; }
        public string ReferenceNo { get; set; } = "";
        public string Date { get; set; } = "";
        public string Supplier { get; set; } = "";
        public string Amount { get; set; } = "";
        public string Account { get; set; } = "";
        public string Note { get; set; } = "";
    }

    public partial class SupplierPaymentListPage : UserControl, ISyncRefreshable
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new DatabaseService();
        private List<SupplierPaymentRow> _allRows = new();
        private List<SupplierPaymentRow> _filteredRows = new();
        private int _currentPage = 1;
        private const int PageSize = 10;
        private int _totalCount;

        public SupplierPaymentListPage() { InitializeComponent(); }
        public SupplierPaymentListPage(MainDashboard dashboard) : this() { _dashboard = dashboard; LoadData(); }

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
                cmd.CommandText = @"SELECT p.Id, p.reference_no, p.date, p.amount, p.note,
                                           COALESCE(s.name, (SELECT m.Name FROM Master1 m WHERE m.ServerId = p.supplier_id AND m.MasterType='Party' LIMIT 1), '') AS supplier_name,
                                           COALESCE(s.phone, '') AS supplier_phone,
                                           COALESCE(pm.name, '') AS account
                                    FROM supplier_payments p
                                    LEFT JOIN suppliers s ON s.Id = p.supplier_id
                                    LEFT JOIN payment_methods pm ON pm.Id = p.payment_method_id
                                    WHERE (p.del_status IS NULL OR p.del_status='Live')
                                    ORDER BY p.Id DESC";
                using var r = cmd.ExecuteReader();
                int sn = 1;
                while (r.Read())
                {
                    string name = r["supplier_name"]?.ToString() ?? "";
                    string phone = r["supplier_phone"]?.ToString() ?? "";
                    string supplierDisplay = phone != "" ? $"{name} (+91-{phone})" : name;

                    _allRows.Add(new SupplierPaymentRow
                    {
                        Sn = sn++,
                        Id = Convert.ToInt64(r["Id"]),
                        ReferenceNo = r["reference_no"]?.ToString() ?? "",
                        Date = r["date"]?.ToString() ?? "",
                        Supplier = supplierDisplay,
                        Amount = $"INR{(r["amount"] is double d ? d : 0):N2}",
                        Account = r["account"]?.ToString() ?? "",
                        Note = r["note"]?.ToString() ?? ""
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
                ? new List<SupplierPaymentRow>(_allRows)
                : _allRows.FindAll(x =>
                    x.ReferenceNo.ToLowerInvariant().Contains(q) ||
                    x.Supplier.ToLowerInvariant().Contains(q) ||
                    x.Note.ToLowerInvariant().Contains(q) ||
                    x.Date.ToLowerInvariant().Contains(q));

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
            lblTotal.Text = $"{_totalCount} entries";
            btnPrev.IsEnabled = _currentPage > 1;
            btnNext.IsEnabled = _currentPage < totalPages;
            BuildPageButtons(totalPages);
        }

        private Border BuildRow(SupplierPaymentRow row)
        {
            var g = new Grid { Height = 40 };
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(50) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1.1, GridUnitType.Star) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1.0, GridUnitType.Star) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1.5, GridUnitType.Star) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1.1, GridUnitType.Star) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1.2, GridUnitType.Star) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1.5, GridUnitType.Star) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(100) });

            var cells = new[]
            {
                row.Sn.ToString(), row.ReferenceNo, row.Date, row.Supplier,
                row.Amount, row.Account, row.Note, ""
            };
            for (int c = 0; c < cells.Length; c++)
            {
                if (c == 7) continue;
                var tb = new TextBlock
                {
                    Text = cells[c],
                    Style = (Style)FindResource("DataCell"),
                    Margin = c == 0 ? new Thickness(16, 0, 0, 0) : new Thickness(0)
                };
                Grid.SetColumn(tb, c);
                g.Children.Add(tb);
            }

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
            Grid.SetColumn(actionPanel, 7);
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

        private void BtnBack_Click(object sender, RoutedEventArgs e) { _dashboard?.ShowDashboard(); }

        private void BtnAdd_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowPage(new SupplierPaymentCreatePage(_dashboard!));
        }

        private void BtnEdit_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is long id && id != 0)
                _dashboard?.ShowPage(new SupplierPaymentCreatePage(_dashboard!, id));
        }

        private void BtnDelete_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is not long id || id == 0) return;
            var result = MessageBox.Show("Delete this supplier payment?", "Confirm", MessageBoxButton.YesNo, MessageBoxImage.Question);
            if (result != MessageBoxResult.Yes) return;

            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "UPDATE supplier_payments SET del_status='Deleted', updated_at=datetime('now') WHERE Id=@id";
                cmd.Parameters.AddWithValue("@id", id);
                cmd.ExecuteNonQuery();

                Services.SyncService.EnqueueSync("supplier_payments", id, "delete");
                _dashboard?.TriggerSync();
                LoadData();
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }
    }
}
