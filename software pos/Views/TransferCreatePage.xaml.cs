using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views;

public partial class TransferCreatePage : UserControl
{
    private readonly long _editId = 0;
    private MainDashboard? _dashboard;
    private readonly DatabaseService _db = new DatabaseService();
    private int _itemCounter = 0;

    public TransferCreatePage() { InitializeComponent(); Loaded += OnLoaded; }
    public TransferCreatePage(MainDashboard dashboard) : this() { _dashboard = dashboard; }
    public TransferCreatePage(MainDashboard dashboard, long editId) : this()
    {
        _dashboard = dashboard;
        _editId = editId;
    }

    private void OnLoaded(object sender, RoutedEventArgs e)
    {
        LoadOutlets();

        if (_editId > 0)
        {
            headerTitle.Text = "Edit Transfer";
            btnSave.Content = "Update Transfer";
            LoadTransfer();
        }
        else
        {
            txtRef.Text = $"TRN-{DateTime.Now:yyyyMMdd}-{new Random().Next(1000, 9999)}";
            AddRow();
        }
    }

    private void LoadOutlets()
    {
        using var conn = _db.GetConnection();
        conn.Open();
        var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT id, name FROM Outlets WHERE del_status='Live' ORDER BY name";
        using var r = cmd.ExecuteReader();
        var outlets = new List<OutletItem>();
        while (r.Read())
            outlets.Add(new OutletItem { Id = r.GetInt64(0), Name = r.GetString(1) });
        cmbFrom.ItemsSource = outlets;
        cmbTo.ItemsSource = outlets;
    }

    private void LoadTransfer()
    {
        using var conn = _db.GetConnection();
        conn.Open();
        var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT reference_no, from_outlet_id, to_outlet_id, note FROM transfers WHERE id=@id";
        cmd.Parameters.AddWithValue("@id", _editId);
        using var r = cmd.ExecuteReader();
        if (r.Read())
        {
            txtRef.Text = r.IsDBNull(0) ? "" : r.GetString(0);
            SetOutlet(cmbFrom, r.IsDBNull(1) ? 0 : r.GetInt64(1));
            SetOutlet(cmbTo, r.IsDBNull(2) ? 0 : r.GetInt64(2));
            txtNote.Text = r.IsDBNull(3) ? "" : r.GetString(3);
        }
        r.Close();

        var cmd2 = conn.CreateCommand();
        cmd2.CommandText = "SELECT item_id, quantity, unit_price FROM transfer_details WHERE transfer_id=@id AND del_status='Live'";
        cmd2.Parameters.AddWithValue("@id", _editId);
        using var r2 = cmd2.ExecuteReader();
        while (r2.Read())
        {
            var itemId = r2.GetInt64(0);
            var qty = r2.IsDBNull(1) ? 0.0 : r2.GetDouble(1);
            var price = r2.IsDBNull(2) ? 0.0 : r2.GetDouble(2);
            AddRow(itemId, qty, price);
        }
    }

    private void SetOutlet(ComboBox cmb, long outletId)
    {
        foreach (var item in cmb.Items)
        {
            if (item is OutletItem o && o.Id == outletId)
            {
                cmb.SelectedItem = item;
                return;
            }
        }
    }

    private void AddRow(long itemId = 0, double qty = 0, double price = 0)
    {
        _itemCounter++;
        var row = new TransferItemRow(_itemCounter, itemId, qty, price);
        row.OnRemove += (s, e) => { itemsPanel.Children.Remove(row); UpdateTotal(); };
        row.OnChanged += (s, e) => UpdateTotal();
        itemsPanel.Children.Add(row);
        UpdateTotal();
    }

    private void UpdateTotal()
    {
        double total = 0;
        foreach (var child in itemsPanel.Children)
        {
            if (child is TransferItemRow row)
                total += row.GetQty();
        }
        txtTotalQty.Text = total.ToString("0.##");
    }

    private void BtnAddRow_Click(object sender, RoutedEventArgs e) => AddRow();

    private void BtnBack_Click(object sender, RoutedEventArgs e)
    {
        _dashboard?.ShowPage(new TransferListPage(_dashboard));
    }

    private void BtnSave_Click(object sender, RoutedEventArgs e)
    {
        if (cmbFrom.SelectedItem is not OutletItem fromOutlet)
        { MessageBox.Show("Select from outlet.", "Validation"); return; }
        if (cmbTo.SelectedItem is not OutletItem toOutlet)
        { MessageBox.Show("Select to outlet.", "Validation"); return; }
        if (fromOutlet.Id == toOutlet.Id)
        { MessageBox.Show("From and To outlets must be different.", "Validation"); return; }
        if (itemsPanel.Children.Count == 0)
        { MessageBox.Show("Add at least one item.", "Validation"); return; }

        using var conn = _db.GetConnection();
        conn.Open();
        using var tx = conn.BeginTransaction();

        try
        {
            long transferId = _editId;

            if (_editId > 0)
            {
                var upd = conn.CreateCommand();
                upd.CommandText = @"UPDATE transfers SET reference_no=@ref, date=@date, from_outlet_id=@from, to_outlet_id=@to, 
                                    note=@note, status='Draft', updated_at=@now WHERE id=@id";
                upd.Parameters.AddWithValue("@ref", txtRef.Text.Trim());
                upd.Parameters.AddWithValue("@date", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
                upd.Parameters.AddWithValue("@from", fromOutlet.Id);
                upd.Parameters.AddWithValue("@to", toOutlet.Id);
                upd.Parameters.AddWithValue("@note", txtNote.Text.Trim());
                upd.Parameters.AddWithValue("@now", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
                upd.Parameters.AddWithValue("@id", _editId);
                upd.ExecuteNonQuery();

                var delOld = conn.CreateCommand();
                delOld.CommandText = "UPDATE transfer_details SET del_status='Deleted' WHERE transfer_id=@id";
                delOld.Parameters.AddWithValue("@id", _editId);
                delOld.ExecuteNonQuery();
            }
            else
            {
                var ins = conn.CreateCommand();
                ins.CommandText = @"INSERT INTO transfers (reference_no, date, from_outlet_id, to_outlet_id, note, status, del_status, created_at, updated_at)
                                    VALUES (@ref, @date, @from, @to, @note, 'Draft', 'Live', @now, @now);
                                    SELECT last_insert_rowid();";
                ins.Parameters.AddWithValue("@ref", txtRef.Text.Trim());
                ins.Parameters.AddWithValue("@date", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
                ins.Parameters.AddWithValue("@from", fromOutlet.Id);
                ins.Parameters.AddWithValue("@to", toOutlet.Id);
                ins.Parameters.AddWithValue("@note", txtNote.Text.Trim());
                ins.Parameters.AddWithValue("@now", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
                transferId = (long)(ins.ExecuteScalar() ?? 0);
            }

            foreach (var child in itemsPanel.Children)
            {
                if (child is TransferItemRow row && row.GetItemId() > 0)
                {
                    var insDetail = conn.CreateCommand();
                    insDetail.CommandText = @"INSERT INTO transfer_details (transfer_id, item_id, quantity, unit_price, total, del_status, created_at, updated_at)
                                              VALUES (@tid, @iid, @qty, @up, @total, 'Live', @now, @now)";
                    insDetail.Parameters.AddWithValue("@tid", transferId);
                    insDetail.Parameters.AddWithValue("@iid", row.GetItemId());
                    insDetail.Parameters.AddWithValue("@qty", row.GetQty());
                    insDetail.Parameters.AddWithValue("@up", row.GetPrice());
                    insDetail.Parameters.AddWithValue("@total", row.GetQty() * row.GetPrice());
                    insDetail.Parameters.AddWithValue("@now", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
                    insDetail.ExecuteNonQuery();
                }
            }

            tx.Commit();

            SyncService.EnqueueSync("transfers", transferId, _editId > 0 ? "update" : "insert");
            foreach (var child in itemsPanel.Children)
            {
                if (child is TransferItemRow row && row.GetItemId() > 0)
                    SyncService.EnqueueSync("transfer_details", row.GetItemId(), _editId > 0 ? "update" : "insert");
            }
            _dashboard?.TriggerSync();

            _dashboard?.ShowPage(new TransferListPage(_dashboard));
        }
        catch (Exception ex)
        {
            tx.Rollback();
            MessageBox.Show($"Error: {ex.Message}", "Error", MessageBoxButton.OK, MessageBoxImage.Error);
        }
    }
}

public class OutletItem
{
    public long Id { get; set; }
    public string Name { get; set; } = "";
}

public class TransferItemRow : UserControl
{
    private readonly ComboBox _cmbItem;
    private readonly TextBox _txtQty;
    private readonly TextBox _txtPrice;

    public event EventHandler? OnRemove;
    public event EventHandler? OnChanged;

    public TransferItemRow(int index, long itemId = 0, double qty = 0, double price = 0)
    {
        Margin = new Thickness(0, 0, 0, 6);

        var border = new Border
        {
            Background = new SolidColorBrush(Color.FromRgb(0xF5, 0xF7, 0xFB)),
            CornerRadius = new CornerRadius(8),
            Padding = new Thickness(12, 10, 12, 10),
            BorderBrush = new SolidColorBrush(Color.FromRgb(0xE3, 0xE8, 0xEF)),
            BorderThickness = new Thickness(1)
        };

        var grid = new Grid();
        grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
        grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(120) });
        grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(120) });
        grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(50) });

        _cmbItem = new ComboBox
        {
            FontSize = 14,
            FontFamily = new FontFamily("Inter, Segoe UI"),
            Foreground = new SolidColorBrush(Color.FromRgb(0x38, 0x45, 0x51)),
            Padding = new Thickness(6, 5, 6, 5),
            DisplayMemberPath = "Name"
        };
        LoadItems(_cmbItem, itemId);

        _txtQty = new TextBox
        {
            FontSize = 14,
            FontFamily = new FontFamily("Inter, Segoe UI"),
            Foreground = new SolidColorBrush(Color.FromRgb(0x38, 0x45, 0x51)),
            Background = Brushes.Transparent,
            BorderThickness = new Thickness(0),
            TextAlignment = TextAlignment.Center,
            Text = qty > 0 ? qty.ToString("0.##") : ""
        };
        _txtQty.TextChanged += (_, _) => OnChanged?.Invoke(this, EventArgs.Empty);

        _txtPrice = new TextBox
        {
            FontSize = 14,
            FontFamily = new FontFamily("Inter, Segoe UI"),
            Foreground = new SolidColorBrush(Color.FromRgb(0x38, 0x45, 0x51)),
            Background = Brushes.Transparent,
            BorderThickness = new Thickness(0),
            TextAlignment = TextAlignment.Center,
            Text = price > 0 ? price.ToString("0.##") : ""
        };

        var btnRemove = new Button
        {
            Content = "\uE711",
            FontFamily = new FontFamily("Segoe MDL2 Assets"),
            FontSize = 14,
            Foreground = new SolidColorBrush(Color.FromRgb(0xFF, 0x3E, 0x1D)),
            Background = new SolidColorBrush(Color.FromRgb(0xFF, 0xEA, 0xE4)),
            BorderThickness = new Thickness(0),
            Width = 34,
            Height = 34,
            Cursor = Cursors.Hand,
            Padding = new Thickness(0),
            HorizontalAlignment = HorizontalAlignment.Center
        };
        btnRemove.Click += (_, _) => OnRemove?.Invoke(this, EventArgs.Empty);

        Grid.SetColumn(_cmbItem, 0);
        Grid.SetColumn(_txtQty, 1);
        Grid.SetColumn(_txtPrice, 2);
        Grid.SetColumn(btnRemove, 3);

        grid.Children.Add(_cmbItem);
        grid.Children.Add(_txtQty);
        grid.Children.Add(_txtPrice);
        grid.Children.Add(btnRemove);

        border.Child = grid;
        Content = border;
    }

    private void LoadItems(ComboBox cmb, long selectedId)
    {
        var db = new DatabaseService();
        using var conn = db.GetConnection();
        conn.Open();
        var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT id, name, COALESCE(code,'') AS code, COALESCE(last_purchase_price, purchase_price, 0) AS unit_price FROM Items WHERE del_status='Live' ORDER BY name";
        using var r = cmd.ExecuteReader();
        while (r.Read())
        {
            var code = r.IsDBNull(2) ? "" : r.GetString(2);
            var item = new ItemLookup
            {
                Id = r.GetInt64(0),
                Name = (r.GetString(1) ?? "") + (code != "" ? " [" + code + "]" : ""),
                Price = r.IsDBNull(3) ? 0 : r.GetDouble(3)
            };
            cmb.Items.Add(item);
            if (item.Id == selectedId) cmb.SelectedItem = item;
        }
        cmb.SelectionChanged += (_, _) => AutoFillPrice();
    }

    private void AutoFillPrice()
    {
        if (_txtPrice == null) return;
        if (_cmbItem.SelectedItem is ItemLookup item)
            _txtPrice.Text = item.Price > 0 ? item.Price.ToString("0.##") : "";
    }

    public long GetItemId()
    {
        if (_cmbItem.SelectedItem is ItemLookup item) return item.Id;
        return 0;
    }

    public double GetQty()
    {
        if (double.TryParse(_txtQty.Text, out var v)) return v;
        return 0;
    }

    public double GetPrice()
    {
        if (double.TryParse(_txtPrice.Text, out var v)) return v;
        return 0;
    }
}

public class ItemLookup
{
    public long Id { get; set; }
    public string Name { get; set; } = "";
    public double Price { get; set; }
}
