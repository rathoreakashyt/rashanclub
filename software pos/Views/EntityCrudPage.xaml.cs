using System;
using System.Collections.Generic;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class EntityCrudPage : UserControl, ISyncRefreshable
    {
        private readonly CrudSpec _spec;
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();
        private readonly List<UIElement> _formInputs = new();   // aligned with _spec.Fields
        private readonly List<Dictionary<string, string>> _comboMaps = new(); // for comboSql fields
        private long _editId;

        public EntityCrudPage(CrudSpec spec, MainDashboard? dashboard, bool startInForm = false)
        {
            InitializeComponent();
            _spec = spec;
            _dashboard = dashboard;
            lblTitle.Text = spec.Title;
            lblSubtitle.Text = spec.Plural;
            BuildGrid();
            BuildForm();
            LoadList();
            if (startInForm) ShowForm(0);
        }

        // ---------- Grid ----------
        private void BuildGrid()
        {
            for (int i = 0; i < _spec.ListColumns.Length; i++)
            {
                grid.Columns.Add(new DataGridTextColumn
                {
                    Header = _spec.ListHeaders[i],
                    Binding = new System.Windows.Data.Binding($"[{i}]"),
                    FontFamily = new FontFamily("Segoe UI"),
                    FontSize = 12.5,
                    Width = i == 0 ? new DataGridLength(1, DataGridLengthUnitType.Star) : DataGridLength.Auto
                });
            }

            var actCol = new DataGridTemplateColumn { Header = "ACTION", Width = 90 };
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
            actCol.CellTemplate = dt;
            grid.Columns.Add(actCol);
        }

        // ---------- Form ----------
        private void BuildForm()
        {
            formFields.Children.Clear();
            _formInputs.Clear();
            _comboMaps.Clear();

            foreach (var f in _spec.Fields)
            {
                // Sneat form label: 12px SemiBold #374151, margin bottom 5
                var label = new TextBlock
                {
                    Text = f.Label,
                    FontSize = 12,
                    FontWeight = FontWeights.SemiBold,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x37, 0x41, 0x51)),
                    FontFamily = new FontFamily("Inter, Segoe UI"),
                    Margin = new Thickness(0, 12, 0, 5)
                };
                formFields.Children.Add(label);

                if (f.Kind == "combo")
                {
                    var cb = new ComboBox { Style = (Style)FindResource("Combo"), Height = 36 };
                    foreach (var o in f.Options ?? new string[0]) cb.Items.Add(o);
                    formFields.Children.Add(cb);
                    _formInputs.Add(cb);
                }
                else if (f.Kind == "comboSql")
                {
                    var cb = new ComboBox { Style = (Style)FindResource("Combo"), Height = 36 };
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
                            string disp = r[f.DispCol]?.ToString() ?? "";
                            map[val] = disp;
                            cb.Items.Add(new ComboBoxItem { Content = disp, Tag = val });
                        }
                    }
                    catch { }
                    _comboMaps.Add(map);
                    formFields.Children.Add(cb);
                    _formInputs.Add(cb);
                }
                else if (f.Kind == "check")
                {
                    var chk = new CheckBox { Content = "Yes", FontSize = 12.5, VerticalAlignment = VerticalAlignment.Center, Height = 28 };
                    formFields.Children.Add(chk);
                    _formInputs.Add(chk);
                }
                else if (f.Kind == "textarea")
                {
                    var tb = new TextBox
                    {
                        Height = 72,
                        AcceptsReturn = true,
                        TextWrapping = TextWrapping.Wrap,
                        VerticalScrollBarVisibility = ScrollBarVisibility.Auto,
                        Style = (Style)FindResource("Input")
                    };
                    formFields.Children.Add(tb);
                    _formInputs.Add(tb);
                }
                else if (f.Kind == "date")
                {
                    var dp = new DatePicker { Style = (Style)FindResource("DateInput"), Height = 36 };
                    formFields.Children.Add(dp);
                    _formInputs.Add(dp);
                }
                else
                {
                    var tb = new TextBox { Height = 36, Style = (Style)FindResource("Input") };
                    formFields.Children.Add(tb);
                    _formInputs.Add(tb);
                }
            }
        }

        public void LoadForEdit(long id)
        {
            ShowForm(id);
        }

        private void ShowForm(long id)
        {
            _editId = id;
            lblFormTitle.Text = id > 0 ? "Edit" : "Add";
            lblFormEntity.Text = _spec.Title;
            listPanel.Visibility = Visibility.Collapsed;
            formPanel.Visibility = Visibility.Visible;

            if (id > 0) LoadIntoForm(id);
        }

        private void LoadIntoForm(long id)
        {
            try
            {
                using var conn = _db.GetConnection();
                var cols = new List<string>();
                foreach (var f in _spec.Fields) cols.Add(f.Column);
                using var cmd = conn.CreateCommand();
                cmd.CommandText = $"SELECT {string.Join(",", cols)} FROM {_spec.Table} WHERE Id=@id";
                cmd.Parameters.AddWithValue("@id", id);
                using var r = cmd.ExecuteReader();
                if (!r.Read()) return;

                for (int i = 0; i < _spec.Fields.Count; i++)
                {
                    var f = _spec.Fields[i];
                    string raw = r[f.Column] is DBNull ? "" : r[f.Column]?.ToString() ?? "";
                    var input = _formInputs[i];
                    switch (f.Kind)
                    {
                        case "check":
                            (input as CheckBox)!.IsChecked = raw == "1" || string.Equals(raw, "true", StringComparison.OrdinalIgnoreCase);
                            break;
                        case "date":
                            if (DateTime.TryParse(raw, out var d)) (input as DatePicker)!.SelectedDate = d;
                            break;
                        case "combo":
                            var cb = input as ComboBox;
                            if (cb != null)
                            {
                                for (int j = 0; j < cb.Items.Count; j++)
                                    if (string.Equals(cb.Items[j]?.ToString(), raw, StringComparison.OrdinalIgnoreCase)) { cb.SelectedIndex = j; break; }
                            }
                            break;
                        case "comboSql":
                            var cbs = input as ComboBox;
                            if (cbs != null)
                            {
                                for (int j = 0; j < cbs.Items.Count; j++)
                                    if (string.Equals((cbs.Items[j] as ComboBoxItem)?.Tag?.ToString(), raw)) { cbs.SelectedIndex = j; break; }
                            }
                            break;
                        default:
                            (input as TextBox)!.Text = raw;
                            break;
                    }
                }
            }
            catch { }
        }

        private string GetFieldValue(int i)
        {
            var f = _spec.Fields[i];
            var input = _formInputs[i];
            switch (f.Kind)
            {
                case "check": return ((input as CheckBox)!.IsChecked == true) ? "1" : "0";
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

        private void BtnSave_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                foreach (var f in _spec.Fields)
                {
                    if (f.Required && string.IsNullOrEmpty(GetFieldValue(_spec.Fields.IndexOf(f))))
                    {
                        MessageBox.Show($"Please fill: {f.Label}", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                        return;
                    }
                }

                using var conn = _db.GetConnection();
                using var tx = conn.BeginTransaction();

                string cols = string.Join(",", _spec.Fields.ConvertAll(f => f.Column));
                string pars = string.Join(",", _spec.Fields.ConvertAll(f => "@c" + _spec.Fields.IndexOf(f)));

                string sql;
                long id = _editId;
                if (id > 0)
                {
                    var sets = new List<string>();
                    for (int i = 0; i < _spec.Fields.Count; i++) sets.Add($"{_spec.Fields[i].Column}=@c{i}");
                    sql = $"UPDATE {_spec.Table} SET {string.Join(",", sets)} WHERE Id=@id";
                }
                else
                {
                    if (!string.IsNullOrEmpty(_spec.RefPrefix) && !string.IsNullOrEmpty(_spec.RefColumn))
                    {
                        cols += $",{_spec.RefColumn}";
                        pars += ",@refno";
                        sql = $"INSERT INTO {_spec.Table} ({cols}) VALUES ({pars})";
                    }
                    else
                    {
                        sql = $"INSERT INTO {_spec.Table} ({cols}) VALUES ({pars})";
                    }
                }

                using var cmd = conn.CreateCommand();
                cmd.CommandText = sql;
                for (int i = 0; i < _spec.Fields.Count; i++)
                    cmd.Parameters.AddWithValue("@c" + i, (object)GetFieldValue(i) ?? DBNull.Value);
                if (id > 0)
                {
                    cmd.Parameters.AddWithValue("@id", id);
                }
                else if (!string.IsNullOrEmpty(_spec.RefPrefix) && !string.IsNullOrEmpty(_spec.RefColumn))
                {
                    cmd.Parameters.AddWithValue("@refno", _spec.RefPrefix + RashanKiDukan.Services.DeviceContext.Short + DateTime.Now.ToString("yyyyMMddHHmmssfff"));
                }
                cmd.ExecuteNonQuery();

                if (id == 0 && !string.IsNullOrEmpty(_spec.AfterInsertSql))
                {
                    using var c2 = conn.CreateCommand();
                    c2.CommandText = "SELECT last_insert_rowid()";
                    long newId = Convert.ToInt64(c2.ExecuteScalar());
                    using var c3 = conn.CreateCommand();
                    c3.CommandText = _spec.AfterInsertSql;
                    c3.Parameters.AddWithValue("@id", newId);
                    c3.ExecuteNonQuery();
                }

                tx.Commit();

                // Enqueue for sync - mark as Local pending
                try
                {
                    long savedId = id;
                    if (savedId == 0)
                    {
                        using var lid = conn.CreateCommand();
                        lid.CommandText = "SELECT last_insert_rowid()";
                        savedId = Convert.ToInt64(lid.ExecuteScalar());
                    }
                    Services.SyncService.EnqueueSync(_spec.Table, savedId, id == 0 ? "insert" : "update");
                    Services.SyncService.MarkLocalPending(_spec.Table, savedId);
                }
                catch { }

                MessageBox.Show(_spec.Title + " saved successfully!", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
                HideForm();
                LoadList();
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
            if (MessageBox.Show($"Delete this {_spec.Title}?", "Confirm Delete", MessageBoxButton.YesNo, MessageBoxImage.Warning) != MessageBoxResult.Yes) return;
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                // Soft delete if del_status column exists, else hard delete
                using var check = conn.CreateCommand();
                check.CommandText = $"SELECT COUNT(*) FROM pragma_table_info('{_spec.Table}') WHERE lower(name)='del_status'";
                bool hasSoftDelete = (long)check.ExecuteScalar() > 0;
                if (hasSoftDelete)
                    cmd.CommandText = $"UPDATE {_spec.Table} SET del_status='Deleted' WHERE Id=@id";
                else
                    cmd.CommandText = $"DELETE FROM {_spec.Table} WHERE Id=@id";
                cmd.Parameters.AddWithValue("@id", id);
                cmd.ExecuteNonQuery();
                Services.SyncService.EnqueueSync(_spec.Table, id, "delete");
                _dashboard?.TriggerSync();
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
                // Soft-deleted rows kabhi list mein nahi dikhne chahiye (FIX: del_status filter)
                using var check = conn.CreateCommand();
                check.CommandText = $"SELECT COUNT(*) FROM pragma_table_info('{_spec.Table}') WHERE lower(name)='del_status'";
                bool hasSoftDelete = (long)check.ExecuteScalar() > 0;
                string where = string.IsNullOrEmpty(_spec.FilterWhere) ? "" : " WHERE " + _spec.FilterWhere;
                if (hasSoftDelete)
                    where = string.IsNullOrEmpty(where)
                        ? " WHERE IFNULL(del_status,'Live') != 'Deleted'"
                        : where + " AND IFNULL(del_status,'Live') != 'Deleted'";
                cmd.CommandText = $"SELECT Id, {string.Join(",", _spec.ListColumns)} FROM {_spec.Table}{where} ORDER BY {_spec.OrderBy}";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    var row = new object[_spec.ListColumns.Length + 1];
                    row[0] = r["Id"]?.ToString() ?? "";
                    for (int i = 0; i < _spec.ListColumns.Length; i++)
                    {
                        string col = _spec.ListColumns[i];
                        string raw = r[col] is DBNull ? "" : r[col]?.ToString() ?? "";
                        // resolve combo display
                        int ci = _spec.Fields.FindIndex(f => f.Column == col && f.Kind == "comboSql");
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
            if (formPanel.Visibility == Visibility.Visible)
            {
                HideForm();
                return;
            }
            if (_dashboard != null) _dashboard.ShowDashboard();
        }
    }
}
