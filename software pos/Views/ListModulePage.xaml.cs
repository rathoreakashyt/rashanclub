using System;
using System.Collections.Generic;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class ListModulePage : UserControl, ISyncRefreshable
    {
        private readonly DatabaseService _db = new DatabaseService();
        private readonly MainDashboard? _dashboard;

        public ListModulePage() { InitializeComponent(); Loaded += (_, __) => LoadModules(); }
        public ListModulePage(MainDashboard dashboard) : this() { _dashboard = dashboard; }

        // ═══════════════════════════════════════════════════════════════════
        // LOAD MODULES
        // ═══════════════════════════════════════════════════════════════════

        // Cloud/Web se change aaya (sync pull) → page khud reload — bina refresh ke
        public void OnSyncPulled()
        {
            Dispatcher.BeginInvoke(new Action(() =>
            {
                if (IsLoaded) LoadModules();
            }), System.Windows.Threading.DispatcherPriority.Background);
        }

        private void LoadModules()
        {
            modulesList.Children.Clear();
            var modules = GetAllModules();

            txtCount.Text = modules.Count.ToString();

            if (modules.Count == 0)
            {
                txtEmpty.Visibility = Visibility.Visible;
                modulesList.Children.Add(txtEmpty);
                return;
            }

            txtEmpty.Visibility = Visibility.Collapsed;

            foreach (var mod in modules)
            {
                modulesList.Children.Add(CreateModuleCard(mod));
            }
        }

        // ═══════════════════════════════════════════════════════════════════
        // MODULE CARD UI
        // ═══════════════════════════════════════════════════════════════════

        private Border CreateModuleCard(ModuleInfo mod)
        {
            var card = new Border
            {
                Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#FAFAFA")),
                CornerRadius = new CornerRadius(8),
                BorderBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#E5E7EB")),
                BorderThickness = new Thickness(1),
                Padding = new Thickness(20, 16, 20, 16),
                Margin = new Thickness(0, 0, 0, 12)
            };

            var grid = new Grid();
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            grid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

            // Left: Module info
            var infoPanel = new StackPanel();

            // Name + Version
            var nameRow = new StackPanel { Orientation = Orientation.Horizontal };
            nameRow.Children.Add(new TextBlock
            {
                Text = mod.Name,
                FontFamily = new FontFamily("Segoe UI"),
                FontSize = 15,
                FontWeight = FontWeights.SemiBold,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#1F2937"))
            });
            nameRow.Children.Add(new Border
            {
                Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#DBEAFE")),
                CornerRadius = new CornerRadius(4),
                Padding = new Thickness(6, 2, 6, 2),
                Margin = new Thickness(8, 0, 0, 0),
                VerticalAlignment = VerticalAlignment.Center,
                Child = new TextBlock
                {
                    Text = $"v{mod.Version}",
                    FontFamily = new FontFamily("Segoe UI"),
                    FontSize = 11,
                    Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#1D4ED8"))
                }
            });
            infoPanel.Children.Add(nameRow);

            // Description
            if (!string.IsNullOrWhiteSpace(mod.Description))
            {
                infoPanel.Children.Add(new TextBlock
                {
                    Text = mod.Description,
                    FontFamily = new FontFamily("Segoe UI"),
                    FontSize = 13,
                    Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#6B7280")),
                    Margin = new Thickness(0, 4, 0, 0),
                    TextWrapping = TextWrapping.Wrap
                });
            }

            // Author + Install Date
            var metaRow = new StackPanel { Orientation = Orientation.Horizontal, Margin = new Thickness(0, 8, 0, 0) };
            metaRow.Children.Add(new TextBlock
            {
                Text = $"👤 {mod.Author}",
                FontFamily = new FontFamily("Segoe UI"),
                FontSize = 12,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#9CA3AF")),
                Margin = new Thickness(0, 0, 16, 0)
            });
            metaRow.Children.Add(new TextBlock
            {
                Text = $"📅 {mod.InstalledAt}",
                FontFamily = new FontFamily("Segoe UI"),
                FontSize = 12,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#9CA3AF"))
            });
            infoPanel.Children.Add(metaRow);

            Grid.SetColumn(infoPanel, 0);
            grid.Children.Add(infoPanel);

            // Right: Enable/Disable toggle button
            var toggleBtn = new Button
            {
                Content = mod.IsEnabled ? "Enabled" : "Disabled",
                Style = (Style)FindResource(mod.IsEnabled ? "BtnEnable" : "BtnDisable"),
                Tag = mod.Id,
                VerticalAlignment = VerticalAlignment.Center,
                MinWidth = 80
            };
            toggleBtn.Click += BtnToggle_Click;
            Grid.SetColumn(toggleBtn, 1);
            grid.Children.Add(toggleBtn);

            card.Child = grid;
            return card;
        }

        // ═══════════════════════════════════════════════════════════════════
        // TOGGLE ENABLE/DISABLE
        // ═══════════════════════════════════════════════════════════════════

        private void BtnToggle_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is long moduleId)
            {
                ToggleModuleEnabled(moduleId);
                LoadModules(); // Refresh list
                _dashboard?.RebuildMenu(); // Refresh sidebar menu
            }
        }

        private void ToggleModuleEnabled(long id)
        {
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = @"UPDATE installed_modules 
                                SET is_enabled = CASE WHEN is_enabled = 1 THEN 0 ELSE 1 END,
                                    updated_at = @now
                                WHERE id = @id";
            cmd.Parameters.AddWithValue("@id", id);
            cmd.Parameters.AddWithValue("@now", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
            cmd.ExecuteNonQuery();
            Services.SyncService.EnqueueSync("installed_modules", id, "update");
        }

        // ═══════════════════════════════════════════════════════════════════
        // NAVIGATION
        // ═══════════════════════════════════════════════════════════════════

        private void BtnAddModule_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowPage(new AddModulePage(_dashboard));
        }

        // ═══════════════════════════════════════════════════════════════════
        // DATA ACCESS
        // ═══════════════════════════════════════════════════════════════════

        private List<ModuleInfo> GetAllModules()
        {
            var list = new List<ModuleInfo>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, name, description, version, author, is_enabled, installed_at FROM installed_modules ORDER BY installed_at DESC";
                using var reader = cmd.ExecuteReader();
                while (reader.Read())
                {
                    list.Add(new ModuleInfo
                    {
                        Id = reader.GetInt64(0),
                        Name = reader.IsDBNull(1) ? "" : reader.GetString(1),
                        Description = reader.IsDBNull(2) ? "" : reader.GetString(2),
                        Version = reader.IsDBNull(3) ? "1.0.0" : reader.GetString(3),
                        Author = reader.IsDBNull(4) ? "Unknown" : reader.GetString(4),
                        IsEnabled = !reader.IsDBNull(5) && reader.GetInt64(5) == 1,
                        InstalledAt = reader.IsDBNull(6) ? "" : reader.GetString(6)
                    });
                }
            }
            catch { /* Table may not exist yet on first run */ }
            return list;
        }

        private class ModuleInfo
        {
            public long Id { get; set; }
            public string Name { get; set; } = "";
            public string Description { get; set; } = "";
            public string Version { get; set; } = "1.0.0";
            public string Author { get; set; } = "Unknown";
            public bool IsEnabled { get; set; } = true;
            public string InstalledAt { get; set; } = "";
        }
    }
}
