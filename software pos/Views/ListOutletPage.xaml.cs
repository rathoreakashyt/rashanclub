using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views
{
    public partial class ListOutletPage : UserControl, ISyncRefreshable
    {
        private readonly DatabaseService _db = new DatabaseService();
        private readonly MainDashboard? _dashboard;

        private static readonly FontFamily ProFont = new FontFamily("Inter, Segoe UI");
        private static readonly FontFamily IconFont = new FontFamily("Segoe MDL2 Assets");

        public ListOutletPage() { InitializeComponent(); }
        public ListOutletPage(MainDashboard dashboard) : this() { _dashboard = dashboard; LoadOutlets(); }

        // Cloud/Web se change aaya (sync pull) → page khud reload — bina refresh ke
        public void OnSyncPulled()
        {
            Dispatcher.BeginInvoke(new Action(() =>
            {
                if (IsLoaded) LoadOutlets();
            }), System.Windows.Threading.DispatcherPriority.Background);
        }

        private void LoadOutlets()
        {
            outletPanel.Children.Clear();
            try
            {
                int selectedId = OutletContext.GetSelectedOutletId(_db);
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT id, COALESCE(outlet_name, name, '') AS display_name,
                                           COALESCE(outlet_code, '') AS outlet_code,
                                           COALESCE(address, '') AS address,
                                           COALESCE(phone, '') AS phone,
                                           COALESCE(email, '') AS email,
                                           COALESCE(active_status, 'Active') AS active_status
                                    FROM outlets
                                    WHERE del_status IS NULL OR del_status != 'Deleted'
                                    ORDER BY id DESC";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    var card = CreateOutletCard(
                        r["display_name"].ToString() ?? "",
                        r["outlet_code"].ToString() ?? "",
                        r["address"].ToString() ?? "",
                        r["phone"].ToString() ?? "",
                        r["email"].ToString() ?? "",
                        r["active_status"].ToString() ?? "Active",
                        Convert.ToInt32(r["id"]),
                        selectedId
                    );
                    outletPanel.Children.Add(card);
                }
            }
            catch { }
        }

        private Border CreateOutletCard(string name, string code, string address, string phone, string email, string status, int id, int selectedId)
        {
            bool isSelected = id == selectedId;
            var card = new Border
            {
                Width = 350,
                Margin = new Thickness(0, 0, 20, 20),
                Background = Brushes.White,
                BorderBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString(isSelected ? "#16A34A" : "#E2E8F0")),
                BorderThickness = new Thickness(isSelected ? 2 : 1),
                CornerRadius = new CornerRadius(14),
                Padding = new Thickness(26, 22, 26, 22),
                Effect = new System.Windows.Media.Effects.DropShadowEffect
                {
                    Color = isSelected ? Color.FromRgb(0x16, 0xA3, 0x4A) : Colors.Black,
                    BlurRadius = 24,
                    ShadowDepth = 3,
                    Opacity = 0.07
                }
            };

            var stack = new StackPanel();

            // Store Icon
            var iconBorder = new Border
            {
                Width = 60,
                Height = 60,
                Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#FEF3C7")),
                CornerRadius = new CornerRadius(14),
                Margin = new Thickness(0, 0, 0, 18),
                HorizontalAlignment = HorizontalAlignment.Left
            };
            var iconText = new TextBlock
            {
                Text = "🏪",
                FontSize = 30,
                HorizontalAlignment = HorizontalAlignment.Center,
                VerticalAlignment = VerticalAlignment.Center
            };
            iconBorder.Child = iconText;
            stack.Children.Add(iconBorder);

            // Outlet Name
            var nameBlock = new TextBlock
            {
                Text = name.Length > 26 ? name.Substring(0, 26) + " ..." : name,
                FontSize = 21,
                FontWeight = FontWeights.Bold,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#1E293B")),
                FontFamily = ProFont,
                TextWrapping = TextWrapping.Wrap,
                Margin = new Thickness(0, 0, 0, 6)
            };
            stack.Children.Add(nameBlock);

            if (isSelected)
            {
                stack.Children.Add(new TextBlock
                {
                    Text = "✓ CURRENT OUTLET",
                    FontSize = 12,
                    FontWeight = FontWeights.Bold,
                    Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#16A34A")),
                    FontFamily = ProFont,
                    Margin = new Thickness(0, 0, 0, 10)
                });
            }

            // Outlet Code
            var codeBlock = new TextBlock
            {
                Text = "Outlet Code: " + code,
                FontSize = 14.5,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#475569")),
                FontFamily = ProFont,
                FontWeight = FontWeights.Medium,
                Margin = new Thickness(0, 0, 0, 18)
            };
            stack.Children.Add(codeBlock);

            // Status Badge
            bool isActive = string.Equals(status, "Active", StringComparison.OrdinalIgnoreCase);
            var statusBadge = new Border
            {
                Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString(isActive ? "#DCFCE7" : "#FEE2E2")),
                CornerRadius = new CornerRadius(12),
                Padding = new Thickness(10, 4, 10, 4),
                HorizontalAlignment = HorizontalAlignment.Left,
                Margin = new Thickness(0, 0, 0, 18)
            };
            var statusText = new TextBlock
            {
                Text = isActive ? "● Active" : "● Inactive",
                FontSize = 12,
                FontWeight = FontWeights.SemiBold,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString(isActive ? "#15803D" : "#B91C1C")),
                FontFamily = ProFont
            };
            statusBadge.Child = statusText;
            stack.Children.Add(statusBadge);

            // Divider
            var divider = new Border
            {
                Height = 1,
                Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#E2E8F0")),
                Margin = new Thickness(0, 0, 0, 18)
            };
            stack.Children.Add(divider);

            // Address
            if (!string.IsNullOrWhiteSpace(address))
            {
                var addrRow = CreateInfoRow("📍", "Address: " + address);
                stack.Children.Add(addrRow);
            }

            // Phone
            if (!string.IsNullOrWhiteSpace(phone))
            {
                var phoneRow = CreateInfoRow("📞", "Phone: " + phone);
                stack.Children.Add(phoneRow);
            }

            // Email
            if (!string.IsNullOrWhiteSpace(email))
            {
                var emailRow = CreateInfoRow("✉", "Email: " + email);
                stack.Children.Add(emailRow);
            }

            // Buttons Row
            var btnRow = new StackPanel
            {
                Orientation = Orientation.Horizontal,
                Margin = new Thickness(0, 22, 0, 0)
            };

            // Edit Button
            var editBtn = CreateButton("✏ Edit", "#6366F1", 130, id);
            editBtn.Click += BtnEdit_Click;
            btnRow.Children.Add(editBtn);

            // Delete Button
            var deleteBtn = CreateButton("🗑 Delete", "#EF4444", 130, id);
            deleteBtn.Click += BtnDelete_Click;
            btnRow.Children.Add(deleteBtn);

            stack.Children.Add(btnRow);

            // Enter Button
            var enterBtn = CreateButton(isSelected ? "✓ Entered" : "➡ Enter", isSelected ? "#16A34A" : "#6366F1", 0, id);
            enterBtn.Click += BtnEnter_Click;
            stack.Children.Add(enterBtn);

            card.Child = stack;
            return card;
        }

        private Button CreateButton(string content, string colorHex, double width, int id)
        {
            var btn = new Button
            {
                Content = content,
                Height = 40,
                FontSize = 13.5,
                FontWeight = FontWeights.SemiBold,
                Foreground = Brushes.White,
                Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString(colorHex)),
                BorderThickness = new Thickness(0),
                Cursor = Cursors.Hand,
                Margin = width > 0 ? new Thickness(0, 0, 10, 0) : new Thickness(0, 8, 0, 0),
                FontFamily = ProFont,
                Tag = id,
                Padding = new Thickness(16, 0, 16, 0)
            };
            if (width > 0) btn.Width = width;
            return btn;
        }

        private StackPanel CreateInfoRow(string icon, string text)
        {
            var row = new StackPanel
            {
                Orientation = Orientation.Horizontal,
                Margin = new Thickness(0, 0, 0, 12)
            };

            var iconBlock = new TextBlock
            {
                Text = icon,
                FontSize = 15,
                VerticalAlignment = VerticalAlignment.Center,
                Margin = new Thickness(0, 0, 12, 0)
            };
            row.Children.Add(iconBlock);

            var textBlock = new TextBlock
            {
                Text = text,
                FontSize = 13.5,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#64748B")),
                FontFamily = ProFont,
                VerticalAlignment = VerticalAlignment.Center,
                TextWrapping = TextWrapping.Wrap,
                MaxWidth = 270
            };
            row.Children.Add(textBlock);

            return row;
        }

        private void BtnAdd_Click(object sender, RoutedEventArgs e)
        {
            if (_dashboard != null)
            {
                _dashboard.ShowPage(new AddOutletPage(_dashboard));
            }
        }

        private void BtnEdit_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is int id && _dashboard != null)
            {
                _dashboard.ShowPage(new AddOutletPage(_dashboard, id));
            }
        }

        private void BtnDelete_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is int id)
            {
                var result = MessageBox.Show("Delete this outlet?", "Confirm", MessageBoxButton.YesNo, MessageBoxImage.Question);
                if (result != MessageBoxResult.Yes) return;

                try
                {
                    int selectedId = OutletContext.GetSelectedOutletId(_db);
                    using var conn = _db.GetConnection();
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = "UPDATE outlets SET del_status='Deleted', SyncStatus='Local' WHERE id=@id";
                    cmd.Parameters.AddWithValue("@id", id);
                    cmd.ExecuteNonQuery();

                    // Agar selected outlet delete hua to naya default select karo
                    if (id == selectedId)
                    {
                        int fallback = OutletContext.GetDefaultOutletId(conn);
                        OutletContext.SetSelectedOutletId(fallback);
                    }

                    Services.SyncService.EnqueueSync("outlets", id, "delete");
                    _dashboard?.TriggerSync();
                    LoadOutlets();
                }
                catch (Exception ex)
                {
                    MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
                }
            }
        }

        private void BtnEnter_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is int id)
            {
                OutletContext.SetSelectedOutletId(id);
                LoadOutlets();
                _dashboard?.ShowDashboard();
            }
        }
    }
}
