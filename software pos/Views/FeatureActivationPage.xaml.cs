using System;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views
{
    public partial class FeatureActivationPage : UserControl, ISyncRefreshable
    {
        private readonly MainDashboard? _dashboard;
        private readonly ApiService _api = new();
        private readonly Dictionary<string, bool> _state = new(StringComparer.OrdinalIgnoreCase);
        private List<FeatureActivationItem> _allItems = new();
        private string _searchQuery = "";

        public FeatureActivationPage() { InitializeComponent(); }
        public FeatureActivationPage(MainDashboard dashboard) : this()
        {
            _dashboard = dashboard;
            Loaded += async (_, _) => await LoadAsync();
        }

        public void OnSyncPulled()
        {
            try { ReloadLocal(); } catch { }
        }

        private async Task LoadAsync()
        {
            SetStatus("Loading...");
            var (ok, _, data) = await _api.GetFeatureActivationsAsync();
            if (ok && data != null)
            {
                try
                {
                    FeatureActivationService.SaveFromJson(data);
                    SetStatus("Cloud se latest settings load hui.");
                }
                catch (Exception ex)
                {
                    System.Diagnostics.Debug.WriteLine($"FeatureActivation parse error: {ex.Message}");
                    SetStatus("Data parse error — local copy dikhaya ja raha hai.");
                }
            }
            else
            {
                SetStatus("Server se load nahi ho paya — local copy dikhaya ja raha hai.");
            }
            ReloadLocal();
        }

        private void ReloadLocal()
        {
            _allItems = FeatureActivationService.GetAllItems();
            _state.Clear();
            foreach (var item in _allItems)
            {
                _state[item.FeatureKey] = item.IsActive;
            }
            RenderGroups();
        }

        private void RenderGroups()
        {
            pnlGroups.Children.Clear();
            var query = _searchQuery.Trim();
            var filtered = _allItems
                .Where(i => string.IsNullOrEmpty(query)
                    || i.FeatureName.IndexOf(query, StringComparison.OrdinalIgnoreCase) >= 0
                    || i.FeatureKey.IndexOf(query, StringComparison.OrdinalIgnoreCase) >= 0
                    || i.FeatureGroup.IndexOf(query, StringComparison.OrdinalIgnoreCase) >= 0)
                .ToList();

            var groups = filtered.GroupBy(i => i.FeatureGroup).OrderBy(g => g.Key);
            foreach (var group in groups)
            {
                var card = BuildGroupCard(group.Key, group.ToList());
                pnlGroups.Children.Add(card);
            }

            if (_allItems.Count == 0)
            {
                SetStatus("Koi feature data nahi mila — Refresh button dabakar cloud se load karein.");
            }
            else
            {
                SetStatus($"Total {_allItems.Count} features — search result me {filtered.Count} dikh rahe hain.");
            }
        }

        private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e)
        {
            _searchQuery = txtSearch.Text;
            txtSearchHint.Visibility = string.IsNullOrEmpty(txtSearch.Text)
                ? Visibility.Visible
                : Visibility.Collapsed;
            RenderGroups();
        }

        private Border BuildGroupCard(string groupName, List<FeatureActivationItem> items)
        {
            var card = new Border
            {
                Background = System.Windows.Media.Brushes.White,
                BorderBrush = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#E5E7EB")),
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(10),
                Margin = new Thickness(0, 0, 0, 14),
                Padding = new Thickness(18, 14, 18, 16)
            };

            var root = new StackPanel();

            var header = new TextBlock
            {
                Text = TitleCase(groupName),
                FontSize = 14,
                FontWeight = FontWeights.SemiBold,
                Foreground = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#1E1B4B")),
                Margin = new Thickness(0, 0, 0, 6)
            };
            root.Children.Add(header);

            var sep = new Border
            {
                Height = 1,
                Background = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#F1F5F9")),
                Margin = new Thickness(0, 0, 0, 10)
            };
            root.Children.Add(sep);

            foreach (var item in items)
            {
                var box = new CheckBox
                {
                    Style = (Style)FindResource("ToggleSwitch"),
                    IsChecked = _state.TryGetValue(item.FeatureKey, out var on) ? on : item.IsActive,
                    Margin = new Thickness(0, 6, 0, 6)
                };
                box.Content = new TextBlock
                {
                    Text = item.FeatureName,
                    FontSize = 13,
                    Foreground = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#374151"))
                };
                box.Tag = item.FeatureKey;
                box.Checked += OnToggleChanged;
                box.Unchecked += OnToggleChanged;
                root.Children.Add(box);
            }

            card.Child = root;
            return card;
        }

        private static string TitleCase(string s)
        {
            if (string.IsNullOrEmpty(s)) return "General";
            return System.Globalization.CultureInfo.InvariantCulture.TextInfo.ToTitleCase(s.Replace('_', ' '));
        }

        // Toggle par hi local me turant apply — menu/submenu instant hide/visible.
        // Server request SIRF Save par jayega (single request).
        private void OnToggleChanged(object sender, RoutedEventArgs e)
        {
            try
            {
                if (sender is CheckBox cb && cb.Tag is string key)
                {
                    bool on = cb.IsChecked == true;
                    _state[key] = on;
                    FeatureActivationService.UpdateLocal(new List<(string FeatureKey, bool IsActive)> { (key, on) });
                    FeatureActivationService.MarkPending(key);
                    _dashboard?.RefreshFeatureActivation();
                    SetStatus("Menu par turant apply ho gaya — Save dabane par cloud me bhi update hoga.");
                }
            }
            catch (Exception ex)
            {
                System.Diagnostics.Debug.WriteLine($"Toggle apply error: {ex.Message}");
            }
        }

        private async void BtnSave_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                btnSave.IsEnabled = false;
                var updates = _state
                    .Select(kv => (Key: kv.Key, IsActive: kv.Value))
                    .ToList();

                FeatureActivationService.UpdateLocal(updates);

                var payload = updates
                    .Select(u => (object)new { feature_key = u.Key, is_active = u.IsActive })
                    .ToList();

                var (ok, msg) = await _api.UpdateFeatureActivationsAsync(payload);
                if (ok)
                {
                    FeatureActivationService.ClearPending();
                    SetStatus($"Saved — {updates.Count} features update hue. Cloud + software dono par apply ho gaya.");
                    _dashboard?.RefreshFeatureActivation();
                }
                else
                {
                    SetStatus("Cloud save fail hua (" + msg + ") — local save ho gaya, dobara try karein.");
                }
            }
            catch (Exception ex)
            {
                SetStatus("Save error: " + ex.Message);
            }
            finally
            {
                btnSave.IsEnabled = true;
            }
        }

        private async void BtnRefresh_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                SetStatus("Cloud se refresh ho raha hai...");
                await LoadAsync();
            }
            catch (Exception ex)
            {
                SetStatus("Refresh error: " + ex.Message);
            }
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e) => _dashboard?.ShowDashboard();

        private void SetStatus(string msg) => lblStatus.Text = msg;
    }
}