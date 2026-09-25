using System.Collections.Generic;
using System.Text.Json;
using System.Windows;
using System.Windows.Controls;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class ConfigEditPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly ConfigEntity _entity;
        private long _editId;
        private readonly DatabaseService _db = new DatabaseService();

        public ConfigEditPage() { InitializeComponent(); }

        public ConfigEditPage(MainDashboard dashboard, ConfigEntity entity, long editId = 0) : this()
        {
            _dashboard = dashboard;
            _entity = entity;
            _editId = editId;

            string title = ConfigEntityInfo.Title(entity);
            lblTitle.Text = editId > 0 ? "Edit " + title : "Add " + title;
            lblName.Text = _entity == ConfigEntity.Unit
                ? "Unit Name *"
                : (_entity == ConfigEntity.Variation ? "Variation Name *" : "Name *");

            if (_entity == ConfigEntity.Variation)
            {
                valuePanel.Visibility = Visibility.Visible;
                descPanel.Visibility = Visibility.Collapsed;
                AddValueRow("");
            }

            if (editId > 0) LoadRecord();
        }

        private void LoadRecord()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = _entity == ConfigEntity.Variation
                    ? $"SELECT {ConfigEntityInfo.NameColumn(_entity)}, VariationValue FROM {ConfigEntityInfo.Table(_entity)} WHERE Id=@id"
                    : $"SELECT {ConfigEntityInfo.NameColumn(_entity)}, Description FROM {ConfigEntityInfo.Table(_entity)} WHERE Id=@id";
                cmd.Parameters.AddWithValue("@id", _editId);
                using var r = cmd.ExecuteReader();
                if (!r.Read()) return;

                txtName.Text = r[ConfigEntityInfo.NameColumn(_entity)]?.ToString() ?? "";

                if (_entity == ConfigEntity.Variation)
                {
                    valueList.Children.Clear();
                    string raw = r["VariationValue"]?.ToString() ?? "[]";
                    try
                    {
                        using var doc = JsonDocument.Parse(raw == "" ? "[]" : raw);
                        var values = JsonSerializer.Deserialize<List<string>>(doc.RootElement.GetRawText());
                        if (values == null || values.Count == 0) AddValueRow("");
                        foreach (var v in values) AddValueRow(v);
                    }
                    catch { AddValueRow(""); }
                }
                else
                {
                    txtDescription.Text = r["Description"]?.ToString() ?? "";
                }
            }
            catch { }
        }

        private void AddValueRow(string value)
        {
            var panel = new StackPanel { Orientation = Orientation.Horizontal, Margin = new Thickness(0, 0, 0, 8) };
            var box = new TextBox { Text = value, Style = (Style)FindResource("InputStyle"), Width = 280 };
            var remove = new Button
            {
                Content = "\uE74D",
                FontFamily = new System.Windows.Media.FontFamily("Segoe MDL2 Assets"),
                FontSize = 14,
                Background = System.Windows.Media.Brushes.Transparent,
                Foreground = new System.Windows.Media.SolidColorBrush(System.Windows.Media.Color.FromRgb(220, 38, 38)),
                BorderThickness = new Thickness(0),
                Padding = new Thickness(10, 4, 10, 4),
                Cursor = System.Windows.Input.Cursors.Hand,
                VerticalAlignment = VerticalAlignment.Center,
                Margin = new Thickness(8, 0, 0, 0)
            };
            remove.Click += (_, _) => valueList.Children.Remove(panel);
            panel.Children.Add(box);
            panel.Children.Add(remove);
            valueList.Children.Add(panel);
        }

        private void BtnAddValue_Click(object sender, RoutedEventArgs e) => AddValueRow("");

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowPage(new ConfigListPage(_dashboard!, _entity));
        }

        private void BtnSave_Click(object sender, RoutedEventArgs e)
        {
            string name = txtName.Text.Trim();
            if (name == "")
            {
                MessageBox.Show("Please enter a name.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }

            try
            {
                string table = ConfigEntityInfo.Table(_entity);
                string nameCol = ConfigEntityInfo.NameColumn(_entity);
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();

                if (_entity == ConfigEntity.Variation)
                {
                    var values = new List<string>();
                    foreach (var child in valueList.Children)
                    {
                        if (child is StackPanel panel && panel.Children[0] is TextBox box)
                        {
                            string v = box.Text.Trim();
                            if (v != "") values.Add(v);
                        }
                    }
                    string json = JsonSerializer.Serialize(values);

                    if (_editId > 0)
                    {
                        cmd.CommandText = $"UPDATE {table} SET VariationName=@n, VariationValue=@v, SyncStatus='Local' WHERE Id=@id";
                    }
                    else
                    {
                        cmd.CommandText = $"INSERT INTO {table} (Id, VariationName, VariationValue, SyncStatus) VALUES (@id, @n, @v, 'Local')";
                        cmd.Parameters.AddWithValue("@id", NextLocalId(conn, table));
                    }
                    cmd.Parameters.AddWithValue("@n", name);
                    cmd.Parameters.AddWithValue("@v", json);
                }
                else
                {
                    string desc = txtDescription.Text.Trim();
                    if (_editId > 0)
                    {
                        cmd.CommandText = $"UPDATE {table} SET {nameCol}=@n, Description=@d, SyncStatus='Local' WHERE Id=@id";
                    }
                    else
                    {
                        // Check if record with same name already exists (cloud-synced or local)
                        using var chk = conn.CreateCommand();
                        chk.CommandText = $"SELECT Id FROM {table} WHERE {nameCol}=@n AND (del_status IS NULL OR del_status != 'Deleted') LIMIT 1";
                        chk.Parameters.AddWithValue("@n", name);
                        var existingId = chk.ExecuteScalar();
                        if (existingId is long existId && existId != 0)
                        {
                            // Update existing record instead of creating duplicate
                            _editId = existId;
                            cmd.CommandText = $"UPDATE {table} SET {nameCol}=@n, Description=@d, SyncStatus='Local' WHERE Id=@id";
                        }
                        else
                        {
                            cmd.CommandText = $"INSERT INTO {table} (Id, {nameCol}, Description, SyncStatus) VALUES (@id, @n, @d, 'Local')";
                            cmd.Parameters.AddWithValue("@id", NextLocalId(conn, table));
                        }
                    }
                    cmd.Parameters.AddWithValue("@n", name);
                    cmd.Parameters.AddWithValue("@d", desc);
                }
                if (_editId > 0) cmd.Parameters.AddWithValue("@id", _editId);
                cmd.ExecuteNonQuery();

                if (_entity == ConfigEntity.ExpenseCategory)
                {
                    long savedId = _editId > 0 ? _editId : (long)cmd.Parameters["@id"].Value!;
                    Services.SyncService.EnqueueSync("expense_categories", savedId, _editId > 0 ? "update" : "insert");
                    _dashboard?.TriggerSync();
                }

                MessageBox.Show("Saved successfully!", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
                _dashboard?.ShowPage(new ConfigListPage(_dashboard!, _entity));
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private static long NextLocalId(SqliteConnection conn, string table)
        {
            using var cmd = conn.CreateCommand();
            cmd.CommandText = $"SELECT MIN(Id) FROM {table} WHERE Id < 0";
            var min = cmd.ExecuteScalar();
            long next = (min is long m) ? m - 1 : -1;
            return next;
        }
    }
}
