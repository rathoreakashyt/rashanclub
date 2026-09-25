using System;
using System.Collections.Generic;
using System.Collections.ObjectModel;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views;

public partial class AddRolePage : UserControl
{
    private MainDashboard? _dashboard;
    private readonly DatabaseService _db = new();
    private long _editRoleId;
    private readonly ObservableCollection<PermissionGroup> _groups = new();

    public AddRolePage(MainDashboard? dashboard, long editRoleId = 0)
    {
        InitializeComponent();
        _dashboard = dashboard;
        _editRoleId = editRoleId;

        LoadPermissions();
        if (_editRoleId > 0) LoadRole();
    }

    private void LoadPermissions()
    {
        _groups.Clear();

        // Try loading from database first
        try
        {
            using var conn = _db.GetConnection();
            conn.Open();
            var cmd = conn.CreateCommand();
            cmd.CommandText = "SELECT id, group_name, name FROM permissions ORDER BY group_name, name";
            using var r = cmd.ExecuteReader();

            var dict = new Dictionary<string, List<PermItem>>();
            while (r.Read())
            {
                long permId = r.GetInt64(0);
                string group = r.IsDBNull(1) ? "Other" : r.GetString(1);
                string name = r.IsDBNull(2) ? "" : r.GetString(2);

                string label = name.Contains('-') ? name.Split('-', 2)[1] : name;
                if (label.Length > 0) label = char.ToUpper(label[0]) + label[1..];

                if (!dict.ContainsKey(group))
                    dict[group] = new List<PermItem>();

                dict[group].Add(new PermItem { Id = permId, Label = label, IsChecked = false });
            }

            if (dict.Count > 0)
            {
                foreach (var kv in dict.OrderBy(x => x.Key))
                {
                    _groups.Add(new PermissionGroup
                    {
                        GroupName = FormatGroupName(kv.Key),
                        Permissions = new ObservableCollection<PermItem>(kv.Value)
                    });
                }
                BuildPermissionGrid();
                return;
            }
        }
        catch { }

        // Fallback: use hardcoded permissions matching the web app image
        LoadHardcodedPermissions();
        BuildPermissionGrid();
    }

    /// <summary>
    /// Complete hardcoded permission set matching the web app "Add Role" page image.
    /// Used as fallback when DB permissions table is empty or missing.
    /// </summary>
    private void LoadHardcodedPermissions()
    {
        int id = 1000; // Start from -1000 to avoid DB ID conflicts

        AddGroup("Accounting", ref id, "Account Balance", "Account Statement", "Balancesheet", "Trial Balance", "Transaction History");
        AddGroup("Attendance", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Booking", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Brand", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Category", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Customer", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Counter", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Customer Receive", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Dashboard", ref id, "Dashboard", "Userhome");
        AddGroup("Damage", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Delivery Partner", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Denomination", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Deposit Withdraw", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Expense", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Expense Category", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Fixed Asset Item", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Fixed Asset Stock In", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Fixed Asset Stock Out", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Income", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Income Category", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Installment Sale", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Item", ref id, "List", "Create", "Edit", "Show", "Destroy", "Import");
        AddGroup("Item Category", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Marketing", ref id, "Email", "Sms", "Whatsapp");
        AddGroup("Multiple Currency", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Outlet", ref id, "List", "Create", "Edit", "Show", "Destroy", "Enter");
        AddGroup("Payment Method", ref id, "List", "Create", "Edit", "Show", "Destroy", "Sort");
        AddGroup("Permission", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Printer", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Promotion", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Purchase", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Purchase Return", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Quotation", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Rack", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Report", ref id,
            "Register Report", "Z Report", "Daily Summary Report", "Sale Report",
            "Due Sale Report", "Final Invoice Due Report", "Service Sale Report",
            "Combo Service Report", "Stock Report", "Low Stock Report",
            "Expire Soon Report", "Employee Sale Report", "Customer Receive Report",
            "Attendance Report", "Product Profit Report", "Supplier Ledger Report",
            "Supplier Balance Report", "Customer Ledger Report", "Customer Balance Report",
            "Servicing Report", "Product Sale Report", "Tax Report",
            "Detailed Sale Report", "Profit Loss Report", "Purchase Report",
            "Expense Report", "Income Report", "Salary Report",
            "Purchase Return Report", "Sale Return Report", "Damage Report",
            "Installment Report", "Installment Due Report", "Item Tracking Report",
            "Price History Report", "Cash Flow Report", "Available Loyalty Point Report",
            "Usage Loyalty Point Report", "Scheme Report");
        AddGroup("Role", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Salary", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Employee Advance Payment", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Sale", ref id, "List", "Create", "Edit", "Show", "Destroy", "Pos");
        AddGroup("Stock", ref id, "Stock", "Low Stock");
        AddGroup("Sale Return", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Servicing", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Setting", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Supplier", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Supplier Payment", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Transfer", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Unit", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("User", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Variation Attribute", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Warranty", ref id, "List", "Create", "Edit", "Show", "Destroy", "Checking");
        AddGroup("Price List", ref id, "List", "Create", "Edit", "Show", "Destroy");
        AddGroup("Saferate", ref id, "List", "Settings", "Wallets");
    }

    private void AddGroup(string groupName, ref int id, params string[] permNames)
    {
        var perms = new ObservableCollection<PermItem>();
        foreach (var name in permNames)
        {
            // Use negative IDs to avoid conflict with real DB permission IDs
            perms.Add(new PermItem { Id = -id--, Label = name, IsChecked = false });
        }
        _groups.Add(new PermissionGroup { GroupName = groupName, Permissions = perms });
    }

    /// <summary>
    /// Build the WrapPanel with modern permission group cards.
    /// Each group is a card with colored accent, bold title, permission count badge and checkboxes.
    /// </summary>
    private void BuildPermissionGrid()
    {
        permissionGrid.Children.Clear();

        string[] accentColors = {
            "#6366F1", "#10B981", "#F59E0B", "#EF4444",
            "#0EA5E9", "#8B5CF6", "#EC4899", "#14B8A6",
            "#F97316", "#84CC16", "#06B6D4", "#A855F7",
            "#F43F5E", "#22C55E", "#EAB308", "#3B82F6"
        };

        var accentBrushes = accentColors
            .Select(c => new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString(c)))
            .ToList();

        int groupIndex = 0;
        foreach (var group in _groups)
        {
            var accent = accentBrushes[groupIndex % accentBrushes.Count];

            var card = new Border
            {
                Style = (Style)FindResource("PermCardStyle")
            };

            var stack = new StackPanel();

            // Title row: colored dot + group name + count badge
            var titleRow = new DockPanel { Margin = new Thickness(0, 0, 0, 8) };

            var dot = new Border
            {
                Width = 8,
                Height = 8,
                CornerRadius = new CornerRadius(4),
                Background = accent,
                VerticalAlignment = VerticalAlignment.Center,
                Margin = new Thickness(0, 0, 7, 0)
            };
            DockPanel.SetDock(dot, Dock.Left);
            titleRow.Children.Add(dot);

            var badge = new Border
            {
                Background = new System.Windows.Media.SolidColorBrush(accent.Color),
                CornerRadius = new CornerRadius(9),
                Padding = new Thickness(6, 1, 6, 2),
                VerticalAlignment = VerticalAlignment.Center,
                HorizontalAlignment = HorizontalAlignment.Right,
                Opacity = 0.9
            };
            badge.Child = new TextBlock
            {
                Text = group.Permissions.Count.ToString(),
                FontSize = 10.5,
                FontWeight = FontWeights.SemiBold,
                Foreground = System.Windows.Media.Brushes.White,
                FontFamily = new System.Windows.Media.FontFamily("Inter, Segoe UI")
            };
            DockPanel.SetDock(badge, Dock.Right);
            titleRow.Children.Add(badge);

            var title = new TextBlock
            {
                Text = group.GroupName,
                FontSize = 13,
                FontWeight = FontWeights.Bold,
                Foreground = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#1E293B")),
                FontFamily = new System.Windows.Media.FontFamily("Inter, Segoe UI"),
                VerticalAlignment = VerticalAlignment.Center,
                TextTrimming = TextTrimming.CharacterEllipsis
            };
            titleRow.Children.Add(title);

            stack.Children.Add(titleRow);

            // Divider
            stack.Children.Add(new Border
            {
                Background = new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#F1F5F9")),
                Height = 1,
                Margin = new Thickness(0, 0, 0, 8)
            });

            // Permission checkboxes
            foreach (var perm in group.Permissions)
            {
                var chk = new CheckBox
                {
                    Content = perm.Label,
                    Tag = perm,
                    IsChecked = perm.IsChecked,
                    Style = (Style)FindResource("ModernCheckBox")
                };
                chk.Checked += PermChk_Changed;
                chk.Unchecked += PermChk_Changed;
                stack.Children.Add(chk);
            }

            card.Child = stack;
            permissionGrid.Children.Add(card);
            groupIndex++;
        }
    }

    private void PermChk_Changed(object sender, RoutedEventArgs e)
    {
        if (sender is CheckBox chk && chk.Tag is PermItem perm)
        {
            perm.IsChecked = chk.IsChecked == true;
        }
    }

    private string FormatGroupName(string key)
    {
        return key.Replace("_", " ").Split(' ').Select(w => char.ToUpper(w[0]) + w[1..]).Aggregate((a, b) => a + " " + b);
    }

    private void LoadRole()
    {
        using var conn = _db.GetConnection();
        conn.Open();
        var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT name FROM roles WHERE id=@id";
        cmd.Parameters.AddWithValue("@id", _editRoleId);
        var name = cmd.ExecuteScalar()?.ToString() ?? "";
        txtRoleName.Text = name;
        txtTitle.Text = "Edit Role";

        var permCmd = conn.CreateCommand();
        permCmd.CommandText = "SELECT permission_id FROM role_has_permissions WHERE role_id=@rid";
        permCmd.Parameters.AddWithValue("@rid", _editRoleId);
        var assigned = new HashSet<long>();
        using (var pr = permCmd.ExecuteReader())
        {
            while (pr.Read())
                assigned.Add(pr.GetInt64(0));
        }

        foreach (var g in _groups)
            foreach (var p in g.Permissions)
                if (assigned.Contains(p.Id)) p.IsChecked = true;

        // Refresh checkboxes in the grid
        RefreshGridCheckboxes();
    }

    private void RefreshGridCheckboxes()
    {
        int groupIndex = 0;
        foreach (var card in permissionGrid.Children)
        {
            if (card is Border border && border.Child is StackPanel stack && groupIndex < _groups.Count)
            {
                var group = _groups[groupIndex];
                // Skip title row (0) and divider (1); checkboxes start at index 2
                for (int i = 2; i < stack.Children.Count && (i - 2) < group.Permissions.Count; i++)
                {
                    if (stack.Children[i] is CheckBox chk && chk.Tag is PermItem perm)
                    {
                        chk.IsChecked = perm.IsChecked;
                    }
                }
            }
            groupIndex++;
        }
    }

    private void ChkSelectAll_Changed(object sender, RoutedEventArgs e)
    {
        if (chkSelectAll == null || _groups == null) return;
        bool val = chkSelectAll.IsChecked == true;

        // Update all checkboxes in grid (which also updates PermItem.IsChecked via PermChk_Changed)
        foreach (var card in permissionGrid.Children)
        {
            if (card is Border border && border.Child is StackPanel stack)
            {
                for (int i = 2; i < stack.Children.Count; i++)
                {
                    if (stack.Children[i] is CheckBox chk)
                        chk.IsChecked = val;
                }
            }
        }
    }

    private void BtnSubmit_Click(object sender, RoutedEventArgs e)
    {
        string roleName = txtRoleName.Text?.Trim() ?? "";
        if (string.IsNullOrEmpty(roleName))
        {
            MessageBox.Show("Please enter a Role Name.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
            return;
        }

        var selectedIds = new List<long>();
        foreach (var g in _groups)
            foreach (var p in g.Permissions)
                if (p.IsChecked) selectedIds.Add(p.Id);

        try
        {
            using var conn = _db.GetConnection();
            conn.Open();
            using var tx = conn.BeginTransaction();

            long roleId = _editRoleId;
            if (roleId > 0)
            {
                var upd = conn.CreateCommand();
                upd.Transaction = tx;
                upd.CommandText = "UPDATE roles SET name=@name WHERE id=@id";
                upd.Parameters.AddWithValue("@name", roleName);
                upd.Parameters.AddWithValue("@id", roleId);
                upd.ExecuteNonQuery();
            }
            else
            {
                var ins = conn.CreateCommand();
                ins.Transaction = tx;
                ins.CommandText = "INSERT INTO roles (name, created_at, updated_at) VALUES (@name, @now, @now) RETURNING id";
                ins.Parameters.AddWithValue("@name", roleName);
                ins.Parameters.AddWithValue("@now", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
                roleId = (long)(ins.ExecuteScalar() ?? 0);
            }

            var del = conn.CreateCommand();
            del.Transaction = tx;
            del.CommandText = "DELETE FROM role_has_permissions WHERE role_id=@rid";
            del.Parameters.AddWithValue("@rid", roleId);
            del.ExecuteNonQuery();

            foreach (var permId in selectedIds)
            {
                var ins = conn.CreateCommand();
                ins.Transaction = tx;
                ins.CommandText = "INSERT INTO role_has_permissions (permission_id, role_id) VALUES (@pid, @rid)";
                ins.Parameters.AddWithValue("@pid", permId);
                ins.Parameters.AddWithValue("@rid", roleId);
                ins.ExecuteNonQuery();
            }

            tx.Commit();
            Services.SyncService.EnqueueSync("roles", roleId, "insert");
            foreach (var permId in selectedIds)
            {
                Services.SyncService.EnqueueSync("role_has_permissions", permId, "insert");
            }
            MessageBox.Show(_editRoleId > 0 ? "Role updated successfully!" : "Role created successfully!", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
            _dashboard?.ShowPage(new RoleListPage(_dashboard!));
        }
        catch (Exception ex)
        {
            MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
        }
    }

    private void BtnBack_Click(object sender, RoutedEventArgs e)
    {
        if (_dashboard != null) _dashboard.ShowPage(new RoleListPage(_dashboard));
    }
}

public class PermissionGroup
{
    public string GroupName { get; set; } = "";
    public ObservableCollection<PermItem> Permissions { get; set; } = new();
}

public class PermItem
{
    public long Id { get; set; }
    public string Label { get; set; } = "";
    public bool IsChecked { get; set; }
}
