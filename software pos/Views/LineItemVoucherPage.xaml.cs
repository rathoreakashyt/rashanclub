using System;
using System.Collections.Generic;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class LineItemVoucherPage : UserControl, ISyncRefreshable
    {
        private readonly VoucherSpec _spec;
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();
        private readonly List<UIElement> _headerInputs = new();
        private readonly List<Dictionary<string, string>> _comboMaps = new();
        private long _editId;
        private readonly List<LineRow> _lines = new();

        private class LineRow
        {
            public ComboBox ItemCb = new();
            public TextBox QtyTb = new();
            public TextBox PriceTb = new();
            public TextBlock TotalTb = new();
            public string Qty = "1";
            public string Price = "0";
            public string Total = "0";
        }

        public LineItemVoucherPage(VoucherSpec spec, MainDashboard? dashboard, bool startInForm = false)
        {
            InitializeComponent();
            _spec = spec;
            _dashboard = dashboard;
            lblTitle.Text = spec.Header.Title;
            lblSubtitle.Text = spec.Header.Plural;
            BuildListGrid();
            BuildHeaderForm();
            LoadList();
            if (startInForm) ShowForm(0);
        }

        // ---------- List grid ----------
        private void BuildListGrid()
        {
            for (int i = 0; i < _spec.Header.ListColumns.Length; i++)
            {
                grid.Columns.Add(new DataGridTextColumn
                {
                    Header = _spec.Header.ListHeaders[i],
                    Binding = new System.Windows.Data.Binding($"[{i}]"),
                    FontFamily = new FontFamily("Segoe UI"),
                    FontSize = 12.5,
                    Width = i == 0 ? new DataGridLength(1, DataGridLengthUnitType.Star) : DataGridLength.Auto
                });
            }

            var dt = new DataTemplate();
            var sp = new FrameworkElementFactory(typeof(StackPanel));
            sp.SetValue(StackPanel.OrientationProperty, Orientation.Horizontal);
            sp.SetValue(StackPanel.VerticalAlignmentProperty, VerticalAlignment.Center);

            var editBtn = new FrameworkElementFactory(typeof(Button));
            editBtn.SetValue(Button.ContentProperty, "\uE70F");
            editBtn.SetValue(Button.FontFamilyProperty, new FontFamily("Segoe MDL2 Assets"));
            editBtn.SetValue(Button.FontSizeProperty, 13d);
            editBtn.SetValue(Button.BackgroundProperty, Brushes.Transparent);
            editBtn.SetValue(Button.ForegroundProperty, new SolidColorBrush(Color.FromRgb(0x37, 0x41, 0x51)));
            editBtn.SetValue(Button.BorderThicknessProperty, new Thickness(0));
            editBtn.SetValue(Button.CursorProperty, System.Windows.Input.Cursors.Hand);
            editBtn.SetValue(Button.PaddingProperty, new Thickness(6, 4, 6, 4));
            editBtn.SetValue(Button.TagProperty, new System.Windows.Data.Binding("[#]"));
            editBtn.SetValue(Button.ToolTipProperty, "Edit");
            editBtn.AddHandler(Button.ClickEvent, new RoutedEventHandler(BtnRowEdit_Click));
            sp.AppendChild(editBtn);

            var delBtn = new FrameworkElementFactory(typeof(Button));
            delBtn.SetValue(Button.ContentProperty, "\uE74D");
            delBtn.SetValue(Button.FontFamilyProperty, new FontFamily("Segoe MDL2 Assets"));
            delBtn.SetValue(Button.FontSizeProperty, 13d);
            delBtn.SetValue(Button.BackgroundProperty, Brushes.Transparent);
            delBtn.SetValue(Button.ForegroundProperty, new SolidColorBrush(Color.FromRgb(0xDC, 0x26, 0x26)));
            delBtn.SetValue(Button.BorderThicknessProperty, new Thickness(0));
            delBtn.SetValue(Button.CursorProperty, System.Windows.Input.Cursors.Hand);
            delBtn.SetValue(Button.PaddingProperty, new Thickness(6, 4, 6, 4));
            delBtn.SetValue(Button.TagProperty, new System.Windows.Data.Binding("[#]"));
            delBtn.SetValue(Button.ToolTipProperty, "Delete");
            delBtn.AddHandler(Button.ClickEvent, new RoutedEventHandler(BtnRowDelete_Click));
            sp.AppendChild(delBtn);

            dt.VisualTree = sp;
            grid.Columns.Add(new DataGridTemplateColumn { Header = "ACTION", Width = 90, CellTemplate = dt });
        }

        // ---------- Header form ----------
        private void BuildHeaderForm()
        {
            headerFields.Children.Clear();
            _headerInputs.Clear();
            _comboMaps.Clear();

            foreach (var f in _spec.Header.Fields)
            {
                var panel = new StackPanel { Margin = new Thickness(0, 0, 18, 8) };
                panel.Children.Add(new TextBlock { Text = f.Label, FontSize = 11.5, FontWeight = FontWeights.SemiBold, Foreground = new SolidColorBrush(Color.FromRgb(0x37, 0x41, 0x51)), Margin = new Thickness(0, 0, 0, 3) });

                if (f.Kind == "combo")
                {
                    var cb = new ComboBox { Width = 200, Height = 32, FontSize = 12.5 };
                    foreach (var o in f.Options ?? new string[0]) cb.Items.Add(o);
                    panel.Children.Add(cb);
                    _headerInputs.Add(cb);
                }
                else if (f.Kind == "comboSql")
                {
                    var cb = new ComboBox { Width = 220, Height = 32, FontSize = 12.5 };
                    var map = new Dictionary<string, string>();
                    try
                    {
                        using var conn = _db.GetConnection();
                        using var cmd = conn.CreateCommand();
                        cmd.CommandText = f.Sql ?? "";
                        using var r = cmd.ExecuteReader();
                        while (r.Read())
                        {
                            string val = r[f.ValCol]?.ToString() ?? "";
                            map[val] = r[f.DispCol]?.ToString() ?? "";
                            cb.Items.Add(new ComboBoxItem { Content = r[f.DispCol]?.ToString() ?? "", Tag = val });
                        }
                    }
                    catch { }
                    _comboMaps.Add(map);
                    panel.Children.Add(cb);
                    _headerInputs.Add(cb);
                }
                else if (f.Kind == "date")
                {
                    var dp = new DatePicker { Width = 200, Height = 32, FontSize = 12.5 };
                    dp.SelectedDate = DateTime.Today;
                    panel.Children.Add(dp);
                    _headerInputs.Add(dp);
                }
                else if (f.Kind == "textarea")
                {
                    var tb = new TextBox { Width = 320, Height = 56, FontSize = 12.5, AcceptsReturn = true, TextWrapping = TextWrapping.Wrap };
                    panel.Children.Add(tb);
                    _headerInputs.Add(tb);
                }
                else
                {
                    var tb = new TextBox { Width = 200, Height = 32, FontSize = 12.5 };
                    panel.Children.Add(tb);
                    _headerInputs.Add(tb);
                }
                headerFields.Children.Add(panel);
            }
        }

        private void AddLineRow(string itemId = "", string qty = "1", string price = "0")
        {
            var row = new LineRow();
            var grid = new Grid { Margin = new Thickness(0, 4, 0, 0) };
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(110) });
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(110) });
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(110) });
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(40) });

            row.ItemCb.Width = double.NaN;
            row.ItemCb.Height = 30;
            row.ItemCb.FontSize = 12;
            row.ItemCb.HorizontalAlignment = HorizontalAlignment.Stretch;
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = _spec.ItemComboSql;
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    string val = r["Id"]?.ToString() ?? "";
                    string name = r["name"]?.ToString() ?? "";
                    var ci = new ComboBoxItem { Content = name, Tag = val };
                    row.ItemCb.Items.Add(ci);
                    if (!string.IsNullOrEmpty(itemId) && val == itemId) row.ItemCb.SelectedItem = ci;
                }
            }
            catch { }

            row.QtyTb.Height = 30; row.QtyTb.FontSize = 12; row.QtyTb.Text = qty;
            row.PriceTb.Height = 30; row.PriceTb.FontSize = 12; row.PriceTb.Text = price;
            row.QtyTb.TextChanged += (s, e) => Recalc(row);
            row.PriceTb.TextChanged += (s, e) => Recalc(row);
            row.TotalTb.FontSize = 12.5; row.TotalTb.FontWeight = FontWeights.SemiBold;
            row.TotalTb.HorizontalAlignment = HorizontalAlignment.Right; row.TotalTb.Margin = new Thickness(0, 0, 8, 0);
            row.TotalTb.VerticalAlignment = VerticalAlignment.Center;

            var removeBtn = new Button { Content = "\uE74D", FontFamily = new FontFamily("Segoe MDL2 Assets"), FontSize = 12, Background = Brushes.Transparent, Foreground = new SolidColorBrush(Color.FromRgb(0xDC, 0x26, 0x26)), BorderThickness = new Thickness(0), Cursor = System.Windows.Input.Cursors.Hand, Padding = new Thickness(4, 2, 4, 2) };
            removeBtn.Click += (s, e) => { _lines.Remove(row); linesPanel.Children.Remove(grid); RecalcTotal(); };

            Grid.SetColumn(row.ItemCb, 0);
            Grid.SetColumn(row.QtyTb, 1);
            Grid.SetColumn(row.PriceTb, 2);
            Grid.SetColumn(row.TotalTb, 3);
            Grid.SetColumn(removeBtn, 4);
            grid.Children.Add(row.ItemCb);
            grid.Children.Add(row.QtyTb);
            grid.Children.Add(row.PriceTb);
            grid.Children.Add(row.TotalTb);
            grid.Children.Add(removeBtn);

            _lines.Add(row);
            linesPanel.Children.Add(grid);
            Recalc(row);
        }

        private void Recalc(LineRow row)
        {
            double.TryParse(row.QtyTb.Text, out double qty);
            double.TryParse(row.PriceTb.Text, out double price);
            double total = qty * price;
            row.Qty = qty.ToString("0.###");
            row.Price = price.ToString("0.##");
            row.Total = total.ToString("0.##");
            row.TotalTb.Text = total.ToString("N2");
            RecalcTotal();
        }

        private void RecalcTotal()
        {
            double grand = 0;
            foreach (var l in _lines) { double.TryParse(l.Total, out double t); grand += t; }
            lblGrandTotal.Text = grand.ToString("N2");
        }

        private void BtnAddLine_Click(object sender, RoutedEventArgs e) => AddLineRow();

        // ---------- Form show / load ----------
        private void ShowForm(long id)
        {
            _editId = id;
            lblFormTitle.Text = id > 0 ? "Edit" : "Add";
            lblFormEntity.Text = _spec.Header.Title;
            listPanel.Visibility = Visibility.Collapsed;
            formPanel.Visibility = Visibility.Visible;

            linesPanel.Children.Clear();
            _lines.Clear();

            if (id > 0) LoadIntoForm(id);
            else AddLineRow();
            RecalcTotal();
        }

        private void LoadIntoForm(long id)
        {
            try
            {
                using var conn = _db.GetConnection();
                var cols = new List<string>();
                foreach (var f in _spec.Header.Fields) cols.Add(f.Column);
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = $"SELECT {string.Join(",", cols)} FROM {_spec.Header.Table} WHERE Id=@id";
                    cmd.Parameters.AddWithValue("@id", id);
                    using var r = cmd.ExecuteReader();
                    if (!r.Read()) return;
                    for (int i = 0; i < _spec.Header.Fields.Count; i++)
                    {
                        var f = _spec.Header.Fields[i];
                        string raw = r[f.Column] is DBNull ? "" : r[f.Column]?.ToString() ?? "";
                        var input = _headerInputs[i];
                        if (f.Kind == "date")
                        {
                            if (DateTime.TryParse(raw, out var d)) (input as DatePicker)!.SelectedDate = d;
                        }
                        else if (f.Kind == "combo")
                        {
                            var cb = input as ComboBox;
                            if (cb != null)
                                for (int j = 0; j < cb.Items.Count; j++)
                                    if (string.Equals(cb.Items[j]?.ToString(), raw, StringComparison.OrdinalIgnoreCase)) { cb.SelectedIndex = j; break; }
                        }
                        else if (f.Kind == "comboSql")
                        {
                            var cb = input as ComboBox;
                            if (cb != null)
                                for (int j = 0; j < cb.Items.Count; j++)
                                    if (string.Equals((cb.Items[j] as ComboBoxItem)?.Tag?.ToString(), raw)) { cb.SelectedIndex = j; break; }
                        }
                        else (input as TextBox)!.Text = raw;
                    }
                }

                using (var cmd2 = conn.CreateCommand())
                {
                    cmd2.CommandText = $"SELECT {_spec.ItemCol}, {_spec.QtyCol}, {_spec.PriceCol} FROM {_spec.DetailTable} WHERE {_spec.HeaderFkCol}=@id ORDER BY Id";
                    cmd2.Parameters.AddWithValue("@id", id);
                    using var r2 = cmd2.ExecuteReader();
                    while (r2.Read())
                        AddLineRow(
                            r2[_spec.ItemCol]?.ToString() ?? "",
                            r2[_spec.QtyCol]?.ToString() ?? "1",
                            r2[_spec.PriceCol]?.ToString() ?? "0");
                }
            }
            catch { }
        }

        private string GetHeaderValue(int i)
        {
            var f = _spec.Header.Fields[i];
            var input = _headerInputs[i];
            switch (f.Kind)
            {
                case "date": return (input as DatePicker)!.SelectedDate?.ToString("yyyy-MM-dd") ?? "";
                case "combo":
                    var cb = input as ComboBox;
                    return cb != null && cb.SelectedIndex >= 0 ? cb.SelectedItem?.ToString() ?? "" : "";
                case "comboSql":
                    var cbs = input as ComboBox;
                    return cbs != null && cbs.SelectedIndex >= 0 ? (cbs.SelectedItem as ComboBoxItem)?.Tag?.ToString() ?? "" : "";
                default:
                    return (input as TextBox)!.Text?.Trim() ?? "";
            }
        }

        private void ApplyStock(long itemId, double qty, int direction)
        {
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "UPDATE items SET stock_quantity = MAX(COALESCE(stock_quantity,0) + @q, 0) WHERE Id=@id";
            cmd.Parameters.AddWithValue("@q", qty * direction);
            cmd.Parameters.AddWithValue("@id", itemId);
            cmd.ExecuteNonQuery();
        }

        private void BtnSave_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                var validLines = new List<(long itemId, double qty, double price, double total)>();
                foreach (var l in _lines)
                {
                    if (l.ItemCb.SelectedItem == null) continue;
                    long.TryParse((l.ItemCb.SelectedItem as ComboBoxItem)?.Tag?.ToString(), out long itemId);
                    double.TryParse(l.Qty, out double qty);
                    double.TryParse(l.Price, out double price);
                    if (itemId <= 0) continue;
                    validLines.Add((itemId, qty, price, qty * price));
                }
                if (validLines.Count == 0)
                {
                    MessageBox.Show("Add at least one item line.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                    return;
                }

                using var conn = _db.GetConnection();
                using var tx = conn.BeginTransaction();

                long id = _editId;

                // Revert old stock on edit
                if (id > 0 && _spec.StockEffect != 0)
                {
                    using var old = conn.CreateCommand();
                    old.CommandText = $"SELECT {_spec.ItemCol}, {_spec.QtyCol} FROM {_spec.DetailTable} WHERE {_spec.HeaderFkCol}=@id";
                    old.Parameters.AddWithValue("@id", id);
                    using var or = old.ExecuteReader();
                    var oldItems = new List<(long, double)>();
                    while (or.Read())
                    {
                        long.TryParse(or[_spec.ItemCol]?.ToString(), out long oi);
                        double.TryParse(or[_spec.QtyCol]?.ToString(), out double oq);
                        if (oi > 0) oldItems.Add((oi, oq));
                    }
                    foreach (var (oi, oq) in oldItems) ApplyStock(oi, oq, -_spec.StockEffect);
                }

                string cols = string.Join(",", _spec.Header.Fields.ConvertAll(f => f.Column));
                string pars = string.Join(",", _spec.Header.Fields.ConvertAll(f => "@c" + _spec.Header.Fields.IndexOf(f)));
                string sql;
                if (id > 0)
                {
                    var sets = new List<string>();
                    for (int i = 0; i < _spec.Header.Fields.Count; i++) sets.Add($"{_spec.Header.Fields[i].Column}=@c{i}");
                    if (!string.IsNullOrEmpty(_spec.GrandTotalColumn)) sets.Add($"{_spec.GrandTotalColumn}=@grand");
                    sql = $"UPDATE {_spec.Header.Table} SET {string.Join(",", sets)} WHERE Id=@id";
                }
                else
                {
                    if (!string.IsNullOrEmpty(_spec.Header.RefPrefix) && !string.IsNullOrEmpty(_spec.Header.RefColumn))
                    {
                        cols += $",{_spec.Header.RefColumn}";
                        pars += ",@refno";
                    }
                    if (!string.IsNullOrEmpty(_spec.GrandTotalColumn))
                    {
                        cols += $",{_spec.GrandTotalColumn}";
                        pars += ",@grand";
                    }
                    sql = $"INSERT INTO {_spec.Header.Table} ({cols}) VALUES ({pars})";
                }

                double grand = 0;
                foreach (var (_, _, _, t) in validLines) grand += t;

                using var cmd = conn.CreateCommand();
                cmd.CommandText = sql;
                for (int i = 0; i < _spec.Header.Fields.Count; i++)
                    cmd.Parameters.AddWithValue("@c" + i, (object)GetHeaderValue(i) ?? DBNull.Value);
                if (!string.IsNullOrEmpty(_spec.GrandTotalColumn))
                    cmd.Parameters.AddWithValue("@grand", grand);
                if (id > 0)
                {
                    cmd.Parameters.AddWithValue("@id", id);
                }
                else if (!string.IsNullOrEmpty(_spec.Header.RefPrefix) && !string.IsNullOrEmpty(_spec.Header.RefColumn))
                {
                    cmd.Parameters.AddWithValue("@refno", _spec.Header.RefPrefix + DateTime.Now.ToString("yyyyMMddHHmmss"));
                }
                cmd.ExecuteNonQuery();

                if (id == 0)
                {
                    using var c2 = conn.CreateCommand();
                    c2.CommandText = "SELECT last_insert_rowid()";
                    id = Convert.ToInt64(c2.ExecuteScalar());
                }

                // Ensure quantity column for quotation_details
                if (_spec.EnsureQtyColumn)
                {
                    using var chk = conn.CreateCommand();
                    chk.CommandText = $"SELECT COUNT(*) FROM pragma_table_info('{_spec.DetailTable}') WHERE name='quantity'";
                    if (Convert.ToInt64(chk.ExecuteScalar()) == 0)
                    {
                        using var alt = conn.CreateCommand();
                        alt.CommandText = $"ALTER TABLE {_spec.DetailTable} ADD COLUMN quantity REAL DEFAULT 1";
                        alt.ExecuteNonQuery();
                    }
                }

                // Replace details
                using (var del = conn.CreateCommand())
                {
                    del.CommandText = $"DELETE FROM {_spec.DetailTable} WHERE {_spec.HeaderFkCol}=@id";
                    del.Parameters.AddWithValue("@id", id);
                    del.ExecuteNonQuery();
                }
                foreach (var (itemId, qty, price, total) in validLines)
                {
                    using var ins = conn.CreateCommand();
                    ins.CommandText = $"INSERT INTO {_spec.DetailTable} ({_spec.HeaderFkCol}, {_spec.ItemCol}, {_spec.QtyCol}, {_spec.PriceCol}, {_spec.TotalCol}) VALUES (@h, @i, @q, @p, @t)";
                    ins.Parameters.AddWithValue("@h", id);
                    ins.Parameters.AddWithValue("@i", itemId);
                    ins.Parameters.AddWithValue("@q", qty);
                    ins.Parameters.AddWithValue("@p", price);
                    ins.Parameters.AddWithValue("@t", total);
                    ins.ExecuteNonQuery();
                }

                // Apply new stock effect
                if (_spec.StockEffect != 0)
                    foreach (var (itemId, qty, _, _) in validLines)
                        ApplyStock(itemId, qty, _spec.StockEffect);

                tx.Commit();
                MessageBox.Show(_spec.Header.Title + " saved successfully!", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
                HideForm();
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error saving: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void HideForm()
        {
            formPanel.Visibility = Visibility.Collapsed;
            listPanel.Visibility = Visibility.Visible;
            LoadList();
        }

        private void BtnCancel_Click(object sender, RoutedEventArgs e) => HideForm();
        private void BtnAdd_Click(object sender, RoutedEventArgs e) => ShowForm(0);

        private void BtnRowEdit_Click(object sender, RoutedEventArgs e)
        {
            var btn = sender as Button;
            long.TryParse(btn?.Tag?.ToString(), out var id);
            ShowForm(id);
        }

        private void BtnRowDelete_Click(object sender, RoutedEventArgs e)
        {
            var btn = sender as Button;
            long.TryParse(btn?.Tag?.ToString(), out var id);
            if (id <= 0) return;
            if (MessageBox.Show($"Delete this {_spec.Header.Title}?", "Confirm Delete", MessageBoxButton.YesNo, MessageBoxImage.Warning) != MessageBoxResult.Yes) return;
            try
            {
                using var conn = _db.GetConnection();
                using var tx = conn.BeginTransaction();

                if (_spec.StockEffect != 0)
                {
                    using var old = conn.CreateCommand();
                    old.CommandText = $"SELECT {_spec.ItemCol}, {_spec.QtyCol} FROM {_spec.DetailTable} WHERE {_spec.HeaderFkCol}=@id";
                    old.Parameters.AddWithValue("@id", id);
                    using var or = old.ExecuteReader();
                    var oldItems = new List<(long, double)>();
                    while (or.Read())
                    {
                        long.TryParse(or[_spec.ItemCol]?.ToString(), out long oi);
                        double.TryParse(or[_spec.QtyCol]?.ToString(), out double oq);
                        if (oi > 0) oldItems.Add((oi, oq));
                    }
                    foreach (var (oi, oq) in oldItems) ApplyStock(oi, oq, -_spec.StockEffect);
                }

                using (var d1 = conn.CreateCommand())
                {
                    d1.CommandText = $"DELETE FROM {_spec.DetailTable} WHERE {_spec.HeaderFkCol}=@id";
                    d1.Parameters.AddWithValue("@id", id);
                    d1.ExecuteNonQuery();
                }
                // FIX: soft-delete + sync — hard DELETE header se server pe record
                // zombie ban jata tha (pull wapas re-insert karta). del_status ho
                // to soft delete + pending_sync queue + instant push.
                using var ds = conn.CreateCommand();
                ds.CommandText = $"SELECT COUNT(*) FROM pragma_table_info('{_spec.Header.Table}') WHERE lower(name)='del_status'";
                bool hasSoftDelete = (long)ds.ExecuteScalar() > 0;
                using (var d2 = conn.CreateCommand())
                {
                    if (hasSoftDelete)
                    {
                        d2.CommandText = $"UPDATE {_spec.Header.Table} SET del_status='Deleted' WHERE Id=@id";
                        d2.Parameters.AddWithValue("@id", id);
                    }
                    else
                    {
                        d2.CommandText = $"DELETE FROM {_spec.Header.Table} WHERE Id=@id";
                        d2.Parameters.AddWithValue("@id", id);
                    }
                    d2.ExecuteNonQuery();
                }
                tx.Commit();
                if (hasSoftDelete)
                {
                    Services.SyncService.EnqueueSync(_spec.Header.Table, id, "delete");
                    _dashboard?.TriggerSync();
                }
                LoadList();
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error deleting: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        // Cloud/Web se change aaya (sync pull) → page khud reload — bina refresh ke
        public void OnSyncPulled()
        {
            Dispatcher.BeginInvoke(new Action(() =>
            {
                if (IsLoaded) LoadList();
            }), System.Windows.Threading.DispatcherPriority.Background);
        }

        private void LoadList()
        {
            try
            {
                var rows = new List<object[]>();
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                // Soft-deleted rows list mein nahi (del_status column ho to filter)
                using var check = conn.CreateCommand();
                check.CommandText = $"SELECT COUNT(*) FROM pragma_table_info('{_spec.Header.Table}') WHERE lower(name)='del_status'";
                bool hasSoftDelete = (long)check.ExecuteScalar() > 0;
                string where = string.IsNullOrEmpty(_spec.Header.FilterWhere) ? "" : " WHERE " + _spec.Header.FilterWhere;
                if (hasSoftDelete)
                    where = string.IsNullOrEmpty(where)
                        ? " WHERE IFNULL(del_status,'Live') != 'Deleted'"
                        : where + " AND IFNULL(del_status,'Live') != 'Deleted'";
                cmd.CommandText = $"SELECT Id, {string.Join(",", _spec.Header.ListColumns)} FROM {_spec.Header.Table}{where} ORDER BY {_spec.Header.OrderBy}";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    var row = new object[_spec.Header.ListColumns.Length + 1];
                    row[0] = r["Id"]?.ToString() ?? "";
                    for (int i = 0; i < _spec.Header.ListColumns.Length; i++)
                    {
                        string col = _spec.Header.ListColumns[i];
                        string raw = r[col] is DBNull ? "" : r[col]?.ToString() ?? "";
                        int ci = _spec.Header.Fields.FindIndex(f => f.Column == col && f.Kind == "comboSql");
                        if (ci >= 0 && _comboMaps.Count > ci && _comboMaps[ci].TryGetValue(raw, out var disp))
                            raw = disp;
                        row[i + 1] = raw;
                    }
                    rows.Add(row);
                }
                grid.ItemsSource = rows;
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error loading: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (grid.ItemsSource is not List<object[]> all) return;
            string q = txtSearch.Text?.Trim() ?? "";
            if (string.IsNullOrEmpty(q)) { grid.ItemsSource = all; return; }
            var filtered = new List<object[]>();
            foreach (var row in all)
            {
                for (int i = 1; i < row.Length; i++)
                    if ((row[i]?.ToString() ?? "").Contains(q, StringComparison.OrdinalIgnoreCase)) { filtered.Add(row); break; }
            }
            grid.ItemsSource = filtered;
        }

        private void BtnRefresh_Click(object sender, RoutedEventArgs e) => LoadList();

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            if (formPanel.Visibility == Visibility.Visible) { HideForm(); return; }
            if (_dashboard != null) _dashboard.ShowDashboard();
        }
    }
}
