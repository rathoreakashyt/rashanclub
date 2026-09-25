using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views;

public partial class DamageCreatePage : UserControl
{
    private readonly long _editId = 0;
    private readonly bool _viewOnly = false;
    private MainDashboard? _dashboard;
    private readonly DatabaseService _db = new DatabaseService();
    private int _sn = 0;

    public DamageCreatePage() { InitializeComponent(); Loaded += OnLoaded; }
    public DamageCreatePage(MainDashboard dashboard) : this() { _dashboard = dashboard; }
    public DamageCreatePage(MainDashboard dashboard, long editId, bool viewOnly = false) : this()
    {
        _dashboard = dashboard;
        _editId = editId;
        _viewOnly = viewOnly;
    }

    private void OnLoaded(object sender, RoutedEventArgs e)
    {
        txtDate.Text = DateTime.Now.ToString("yyyy-MM-dd");
        LoadEmployees();
        LoadItems();

        if (_editId > 0)
        {
            headerTitle.Text = _viewOnly ? "View Damage" : "Edit Damage";
            if (_viewOnly) btnSave.Visibility = Visibility.Collapsed;
            LoadDamage();
        }
        else
        {
            txtRef.Text = $"DMG-{DateTime.Now:yyyyMMdd}-{new Random().Next(1000, 9999)}";
        }
    }

    private void LoadEmployees()
    {
        using var conn = _db.GetConnection();
        conn.Open();
        var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT id, name FROM employees WHERE del_status='Live' ORDER BY name";
        using var r = cmd.ExecuteReader();
        var list = new List<LookupItem>();
        while (r.Read())
            list.Add(new LookupItem { Id = r.GetInt64(0), Name = r.GetString(1) });
        cmbEmployee.ItemsSource = list;
    }

    private void LoadItems()
    {
        using var conn = _db.GetConnection();
        conn.Open();
        var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT id, name FROM Items WHERE del_status='Live' ORDER BY name";
        using var r = cmd.ExecuteReader();
        var list = new List<LookupItem>();
        while (r.Read())
            list.Add(new LookupItem { Id = r.GetInt64(0), Name = r.GetString(1) });
        cmbItem.ItemsSource = list;
        cmbItem.SelectionChanged += CmbItem_SelectionChanged;
    }

    private void CmbItem_SelectionChanged(object sender, SelectionChangedEventArgs e)
    {
        if (cmbItem.SelectedItem is LookupItem item)
        {
            AddItemRow(item.Id, item.Name);
            cmbItem.SelectedItem = null;
        }
    }

    private void AddItemRow(long itemId, string itemName, double qty = 0, double amount = 0, string imei = "")
    {
        _sn++;
        var row = new DamageItemRow(_sn, itemId, itemName, qty, amount, imei, _viewOnly);
        row.OnRemove += (s, e) => { itemsPanel.Children.Remove(row); UpdateTotals(); };
        row.OnChanged += (s, e) => UpdateTotals();
        itemsPanel.Children.Add(row);
        UpdateTotals();
    }

    private void UpdateTotals()
    {
        double total = 0;
        int count = 0;
        foreach (var child in itemsPanel.Children)
        {
            if (child is DamageItemRow row)
            {
                count++;
                total += row.GetTotal();
            }
        }
        txtTotalItem.Text = $"Total Item {count}";
        txtTotalLoss.Text = total.ToString("N2");
    }

    private void LoadDamage()
    {
        using var conn = _db.GetConnection();
        conn.Open();
        var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT reference_no, date, employee_id, total_loss, note FROM damages WHERE id=@id";
        cmd.Parameters.AddWithValue("@id", _editId);
        using var r = cmd.ExecuteReader();
        if (r.Read())
        {
            txtRef.Text = r.IsDBNull(0) ? "" : r.GetString(0);
            txtDate.Text = r.IsDBNull(1) ? "" : r.GetString(1);
            SetCombo(cmbEmployee, r.IsDBNull(2) ? 0 : r.GetInt64(2));
            txtTotalLoss.Text = r.IsDBNull(3) ? "0.00" : r.GetDouble(3).ToString("N2");
            txtNote.Text = r.IsDBNull(4) ? "" : r.GetString(4);
        }
        r.Close();

        var cmd2 = conn.CreateCommand();
        cmd2.CommandText = @"SELECT dd.item_id, COALESCE(i.name,'') as item_name, dd.damage_quantity, dd.loss_amount
                             FROM damage_details dd
                             LEFT JOIN Items i ON dd.item_id = i.id
                             WHERE dd.damage_id=@id AND dd.del_status='Live'";
        cmd2.Parameters.AddWithValue("@id", _editId);
        using var r2 = cmd2.ExecuteReader();
        while (r2.Read())
        {
            var itemId = r2.GetInt64(0);
            var name = r2.IsDBNull(1) ? "" : r2.GetString(1);
            var qty = r2.IsDBNull(2) ? 0.0 : r2.GetDouble(2);
            var amt = r2.IsDBNull(3) ? 0.0 : r2.GetDouble(3);
            AddItemRow(itemId, name, qty, amt);
        }
    }

    private void SetCombo(ComboBox cmb, long id)
    {
        foreach (var item in cmb.Items)
            if (item is LookupItem li && li.Id == id) { cmb.SelectedItem = item; return; }
    }

    private void BtnBack_Click(object sender, RoutedEventArgs e)
    {
        _dashboard?.ShowPage(new DamageListPage(_dashboard));
    }

    private void BtnSave_Click(object sender, RoutedEventArgs e)
    {
        if (string.IsNullOrWhiteSpace(txtRef.Text))
        { MessageBox.Show("Reference No is required.", "Validation"); return; }
        if (string.IsNullOrWhiteSpace(txtDate.Text))
        { MessageBox.Show("Date is required.", "Validation"); return; }
        if (cmbEmployee.SelectedItem is not LookupItem emp)
        { MessageBox.Show("Select an employee.", "Validation"); return; }

        using var conn = _db.GetConnection();
        conn.Open();
        using var tx = conn.BeginTransaction();

        try
        {
            double totalLoss = 0;
            foreach (var child in itemsPanel.Children)
                if (child is DamageItemRow row) totalLoss += row.GetTotal();

            long damageId = _editId;
            var now = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");

            if (_editId > 0)
            {
                var upd = conn.CreateCommand();
                upd.CommandText = @"UPDATE damages SET reference_no=@ref, date=@date, employee_id=@emp, 
                                    total_loss=@loss, note=@note, updated_at=@now WHERE id=@id";
                upd.Parameters.AddWithValue("@ref", txtRef.Text.Trim());
                upd.Parameters.AddWithValue("@date", txtDate.Text.Trim());
                upd.Parameters.AddWithValue("@emp", emp.Id);
                upd.Parameters.AddWithValue("@loss", totalLoss);
                upd.Parameters.AddWithValue("@note", txtNote.Text.Trim());
                upd.Parameters.AddWithValue("@now", now);
                upd.Parameters.AddWithValue("@id", _editId);
                upd.ExecuteNonQuery();

                var delOld = conn.CreateCommand();
                delOld.CommandText = "UPDATE damage_details SET del_status='Deleted' WHERE damage_id=@id";
                delOld.Parameters.AddWithValue("@id", _editId);
                delOld.ExecuteNonQuery();
            }
            else
            {
                var ins = conn.CreateCommand();
                ins.CommandText = @"INSERT INTO damages (reference_no, date, employee_id, total_loss, note, del_status, created_at, updated_at)
                                    VALUES (@ref, @date, @emp, @loss, @note, 'Live', @now, @now);
                                    SELECT last_insert_rowid();";
                ins.Parameters.AddWithValue("@ref", txtRef.Text.Trim());
                ins.Parameters.AddWithValue("@date", txtDate.Text.Trim());
                ins.Parameters.AddWithValue("@emp", emp.Id);
                ins.Parameters.AddWithValue("@loss", totalLoss);
                ins.Parameters.AddWithValue("@note", txtNote.Text.Trim());
                ins.Parameters.AddWithValue("@now", now);
                damageId = (long)(ins.ExecuteScalar() ?? 0);
            }

            foreach (var child in itemsPanel.Children)
            {
                if (child is DamageItemRow row && row.GetItemId() > 0)
                {
                    var insDetail = conn.CreateCommand();
                    insDetail.CommandText = @"INSERT INTO damage_details (damage_id, item_id, damage_quantity, loss_amount, total_amount, del_status, created_at, updated_at)
                                              VALUES (@did, @iid, @qty, @amt, @total, 'Live', @now, @now)";
                    insDetail.Parameters.AddWithValue("@did", damageId);
                    insDetail.Parameters.AddWithValue("@iid", row.GetItemId());
                    insDetail.Parameters.AddWithValue("@qty", row.GetQty());
                    insDetail.Parameters.AddWithValue("@amt", row.GetAmount());
                    insDetail.Parameters.AddWithValue("@total", row.GetTotal());
                    insDetail.Parameters.AddWithValue("@now", now);
                    insDetail.ExecuteNonQuery();
                }
            }

            tx.Commit();

            SyncService.EnqueueSync("damages", damageId, _editId > 0 ? "update" : "insert");
            foreach (var child in itemsPanel.Children)
            {
                if (child is DamageItemRow row && row.GetItemId() > 0)
                    SyncService.EnqueueSync("damage_details", row.GetItemId(), _editId > 0 ? "update" : "insert");
            }
            _dashboard?.TriggerSync();

            _dashboard?.ShowPage(new DamageListPage(_dashboard));
        }
        catch (Exception ex)
        {
            tx.Rollback();
            MessageBox.Show($"Error: {ex.Message}", "Error", MessageBoxButton.OK, MessageBoxImage.Error);
        }
    }
}

public class LookupItem
{
    public long Id { get; set; }
    public string Name { get; set; } = "";
}

public class DamageItemRow : UserControl
{
    private readonly TextBox _txtQty;
    private readonly TextBox _txtAmount;
    private readonly TextBlock _txtTotal;
    private readonly long _itemId;

    public event EventHandler? OnRemove;
    public event EventHandler? OnChanged;

    public DamageItemRow(int sn, long itemId, string itemName, double qty, double amount, string imei, bool viewOnly)
    {
        _itemId = itemId;
        Margin = new Thickness(0, 0, 0, 1);

        var border = new Border
        {
            Background = new SolidColorBrush(Color.FromRgb(0xF8, 0xF9, 0xFB)),
            BorderBrush = new SolidColorBrush(Color.FromRgb(0xE3, 0xE8, 0xEF)),
            BorderThickness = new Thickness(0, 0, 0, 1),
            Padding = new Thickness(16, 10, 16, 10)
        };

        var grid = new Grid();
        grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(40) });
        grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1.5, GridUnitType.Star) });
        grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1.5, GridUnitType.Star) });
        grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
        grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1.2, GridUnitType.Star) });
        grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
        grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(60) });

        var lblSn = new TextBlock
        {
            Text = sn.ToString(),
            FontSize = 13,
            Foreground = new SolidColorBrush(Color.FromRgb(0x37, 0x41, 0x51)),
            FontFamily = new FontFamily("Inter, Segoe UI"),
            VerticalAlignment = VerticalAlignment.Center
        };

        var lblItem = new TextBlock
        {
            Text = itemName,
            FontSize = 13,
            Foreground = new SolidColorBrush(Color.FromRgb(0x38, 0x45, 0x51)),
            FontFamily = new FontFamily("Inter, Segoe UI"),
            VerticalAlignment = VerticalAlignment.Center
        };

        var lblImei = new TextBlock
        {
            Text = imei,
            FontSize = 13,
            Foreground = new SolidColorBrush(Color.FromRgb(0x5F, 0x6A, 0x7D)),
            FontFamily = new FontFamily("Inter, Segoe UI"),
            VerticalAlignment = VerticalAlignment.Center
        };

        _txtQty = MakeTextBox(qty > 0 ? qty.ToString("0.##") : "", viewOnly);
        _txtQty.TextChanged += (_, _) => Recalc();

        _txtAmount = MakeTextBox(amount > 0 ? amount.ToString("0.##") : "", viewOnly);
        _txtAmount.TextChanged += (_, _) => Recalc();

        _txtTotal = new TextBlock
        {
            Text = (qty * amount).ToString("N2"),
            FontSize = 13,
            FontWeight = FontWeights.SemiBold,
            Foreground = new SolidColorBrush(Color.FromRgb(0x38, 0x45, 0x51)),
            FontFamily = new FontFamily("Inter, Segoe UI"),
            VerticalAlignment = VerticalAlignment.Center,
            HorizontalAlignment = HorizontalAlignment.Center
        };

        var btnRemove = new Button
        {
            Content = "\uE711",
            FontFamily = new FontFamily("Segoe MDL2 Assets"),
            FontSize = 14,
            Foreground = new SolidColorBrush(Color.FromRgb(0xFF, 0x3E, 0x1D)),
            Background = new SolidColorBrush(Color.FromRgb(0xFF, 0xEA, 0xE4)),
            BorderThickness = new Thickness(0),
            Width = 32, Height = 32,
            Cursor = Cursors.Hand,
            Padding = new Thickness(0),
            HorizontalAlignment = HorizontalAlignment.Center,
            Visibility = viewOnly ? Visibility.Collapsed : Visibility.Visible
        };
        btnRemove.Click += (_, _) => OnRemove?.Invoke(this, EventArgs.Empty);

        Grid.SetColumn(lblSn, 0);
        Grid.SetColumn(lblItem, 1);
        Grid.SetColumn(lblImei, 2);
        Grid.SetColumn(_txtQty, 3);
        Grid.SetColumn(_txtAmount, 4);
        Grid.SetColumn(_txtTotal, 5);
        Grid.SetColumn(btnRemove, 6);

        grid.Children.Add(lblSn);
        grid.Children.Add(lblItem);
        grid.Children.Add(lblImei);
        grid.Children.Add(_txtQty);
        grid.Children.Add(_txtAmount);
        grid.Children.Add(_txtTotal);
        grid.Children.Add(btnRemove);

        border.Child = grid;
        Content = border;
    }

    private void Recalc()
    {
        double qty = 0, amt = 0;
        double.TryParse(_txtQty.Text, out qty);
        double.TryParse(_txtAmount.Text, out amt);
        _txtTotal.Text = (qty * amt).ToString("N2");
        OnChanged?.Invoke(this, EventArgs.Empty);
    }

    private TextBox MakeTextBox(string text, bool readOnly)
    {
        return new TextBox
        {
            FontSize = 13,
            FontFamily = new FontFamily("Inter, Segoe UI"),
            Foreground = new SolidColorBrush(Color.FromRgb(0x38, 0x45, 0x51)),
            Background = Brushes.Transparent,
            BorderThickness = new Thickness(0),
            TextAlignment = TextAlignment.Center,
            Text = text,
            IsReadOnly = readOnly
        };
    }

    public long GetItemId() => _itemId;
    public double GetQty() { double.TryParse(_txtQty.Text, out var v); return v; }
    public double GetAmount() { double.TryParse(_txtAmount.Text, out var v); return v; }
    public double GetTotal() { return GetQty() * GetAmount(); }
}
