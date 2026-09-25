using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views;

public partial class QuotationCreatePage : UserControl
{
    private readonly long _editId = 0;
    private readonly bool _viewOnly = false;
    private MainDashboard? _dashboard;
    private readonly DatabaseService _db = new DatabaseService();
    private int _sn = 0;

    public QuotationCreatePage() { InitializeComponent(); Loaded += OnLoaded; }
    public QuotationCreatePage(MainDashboard dashboard) : this() { _dashboard = dashboard; }
    public QuotationCreatePage(MainDashboard dashboard, long editId, bool viewOnly = false) : this()
    {
        _dashboard = dashboard;
        _editId = editId;
        _viewOnly = viewOnly;
    }

    private void OnLoaded(object sender, RoutedEventArgs e)
    {
        txtDate.Text = DateTime.Now.ToString("yyyy-MM-dd");
        LoadCustomers();
        LoadItems();

        if (_editId > 0)
        {
            headerTitle.Text = _viewOnly ? "View Quotation" : "Edit Quotation";
            if (_viewOnly) btnSave.Visibility = Visibility.Collapsed;
            LoadQuotation();
        }
        else
        {
            txtRef.Text = $"QUO-{DateTime.Now:yyyyMMdd}-{new Random().Next(1000, 9999)}";
        }
    }

    private void LoadCustomers()
    {
        using var conn = _db.GetConnection();
        conn.Open();
        var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT id, name FROM customers WHERE del_status='Live' ORDER BY name";
        using var r = cmd.ExecuteReader();
        var list = new List<QuotLookupItem>();
        while (r.Read())
            list.Add(new QuotLookupItem { Id = r.GetInt64(0), Name = r.GetString(1) });
        cmbCustomer.ItemsSource = list;
    }

    private void LoadItems()
    {
        using var conn = _db.GetConnection();
        conn.Open();
        var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT id, name, sale_price FROM Items WHERE del_status='Live' ORDER BY name";
        using var r = cmd.ExecuteReader();
        var list = new List<QuotItemOption>();
        while (r.Read())
            list.Add(new QuotItemOption
            {
                Id = r.GetInt64(0),
                Name = r.GetString(1),
                SalePrice = r.IsDBNull(2) ? 0 : r.GetDouble(2)
            });
        cmbItem.ItemsSource = list;
        cmbItem.SelectionChanged += CmbItem_SelectionChanged;
    }

    private void CmbItem_SelectionChanged(object sender, SelectionChangedEventArgs e)
    {
        if (cmbItem.SelectedItem is QuotItemOption item)
        {
            AddRow(item.Id, item.Name, 1, item.SalePrice);
            cmbItem.SelectedItem = null;
        }
    }

    private void AddRow(long itemId, string itemName, double qty, double price, string desc = "")
    {
        _sn++;
        var row = new QuotItemRow(_sn, itemId, itemName, qty, price, desc, _viewOnly);
        row.OnRemove += (s, e) => { itemsPanel.Children.Remove(row); UpdateTotals(); };
        row.OnChanged += (s, e) => UpdateTotals();
        itemsPanel.Children.Add(row);
        UpdateTotals();
    }

    private void UpdateTotals()
    {
        double subtotal = 0;
        int count = 0;
        foreach (var child in itemsPanel.Children)
        {
            if (child is QuotItemRow row)
            {
                count++;
                subtotal += row.GetTotal();
            }
        }
        txtTotalItem.Text = $"Total Item {count}";

        double discount = 0;
        var discText = txtDiscount.Text.Trim();
        if (!string.IsNullOrEmpty(discText))
        {
            if (discText.Contains('%'))
            {
                var pct = double.TryParse(discText.Replace("%", ""), out var p) ? p : 0;
                discount = subtotal * (pct / 100.0);
            }
            else
            {
                discount = double.TryParse(discText, out var d) ? d : 0;
            }
        }

        txtGrandTotal.Text = (subtotal - discount).ToString("N2");
    }

    private void TxtDiscount_TextChanged(object sender, TextChangedEventArgs e) => UpdateTotals();

    private void LoadQuotation()
    {
        using var conn = _db.GetConnection();
        conn.Open();
        var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT reference_no, date, customer_id, grand_total, note, discount FROM quotations WHERE id=@id";
        cmd.Parameters.AddWithValue("@id", _editId);
        using var r = cmd.ExecuteReader();
        if (r.Read())
        {
            txtRef.Text = r.IsDBNull(0) ? "" : r.GetString(0);
            txtDate.Text = r.IsDBNull(1) ? "" : r.GetString(1);
            SetCombo(cmbCustomer, r.IsDBNull(2) ? 0 : r.GetInt64(2));
            txtGrandTotal.Text = r.IsDBNull(3) ? "0.00" : r.GetDouble(3).ToString("N2");
            txtNote.Text = r.IsDBNull(4) ? "" : r.GetString(4);
            txtDiscount.Text = r.IsDBNull(5) ? "" : r.GetString(5);
        }
        r.Close();

        var cmd2 = conn.CreateCommand();
        cmd2.CommandText = @"SELECT qd.item_id, COALESCE(i.name,'') as item_name, qd.quantity, qd.unit_price, qd.total
                             FROM quotation_details qd
                             LEFT JOIN Items i ON qd.item_id = i.id
                             WHERE qd.quotation_id=@id AND qd.del_status='Live'";
        cmd2.Parameters.AddWithValue("@id", _editId);
        using var r2 = cmd2.ExecuteReader();
        while (r2.Read())
        {
            var itemId = r2.GetInt64(0);
            var name = r2.IsDBNull(1) ? "" : r2.GetString(1);
            var qty = r2.IsDBNull(2) ? 1.0 : r2.GetDouble(2);
            var price = r2.IsDBNull(3) ? 0.0 : r2.GetDouble(3);
            AddRow(itemId, name, qty, price);
        }
    }

    private void SetCombo(ComboBox cmb, long id)
    {
        foreach (var item in cmb.Items)
            if (item is QuotLookupItem li && li.Id == id) { cmb.SelectedItem = item; return; }
    }

    private void BtnBack_Click(object sender, RoutedEventArgs e)
    {
        _dashboard?.ShowPage(new QuotationListPage(_dashboard));
    }

    private void BtnSave_Click(object sender, RoutedEventArgs e)
    {
        if (string.IsNullOrWhiteSpace(txtRef.Text))
        { MessageBox.Show("Reference No is required.", "Validation"); return; }
        if (cmbCustomer.SelectedItem is not QuotLookupItem)
        { MessageBox.Show("Select a customer.", "Validation"); return; }
        if (itemsPanel.Children.Count == 0)
        { MessageBox.Show("Add at least one item.", "Validation"); return; }

        using var conn = _db.GetConnection();
        conn.Open();
        using var tx = conn.BeginTransaction();

        try
        {
            double grandTotal = 0;
            foreach (var child in itemsPanel.Children)
                if (child is QuotItemRow row) grandTotal += row.GetTotal();

            double discount = 0;
            var discText = txtDiscount.Text.Trim();
            if (!string.IsNullOrEmpty(discText))
            {
                if (discText.Contains('%'))
                {
                    var pct = double.TryParse(discText.Replace("%", ""), out var p) ? p : 0;
                    discount = grandTotal * (pct / 100.0);
                }
                else
                    discount = double.TryParse(discText, out var d) ? d : 0;
            }
            grandTotal -= discount;

            long quotId = _editId;
            var now = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
            var cust = (QuotLookupItem)cmbCustomer.SelectedItem;

            if (_editId > 0)
            {
                var upd = conn.CreateCommand();
                upd.CommandText = @"UPDATE quotations SET reference_no=@ref, date=@date, customer_id=@cust, 
                                    grand_total=@gt, note=@note, discount=@disc, updated_at=@now WHERE id=@id";
                upd.Parameters.AddWithValue("@ref", txtRef.Text.Trim());
                upd.Parameters.AddWithValue("@date", txtDate.Text.Trim());
                upd.Parameters.AddWithValue("@cust", cust.Id);
                upd.Parameters.AddWithValue("@gt", grandTotal);
                upd.Parameters.AddWithValue("@note", txtNote.Text.Trim());
                upd.Parameters.AddWithValue("@disc", discText);
                upd.Parameters.AddWithValue("@now", now);
                upd.Parameters.AddWithValue("@id", _editId);
                upd.ExecuteNonQuery();

                var delOld = conn.CreateCommand();
                delOld.CommandText = "UPDATE quotation_details SET del_status='Deleted' WHERE quotation_id=@id";
                delOld.Parameters.AddWithValue("@id", _editId);
                delOld.ExecuteNonQuery();
            }
            else
            {
                var ins = conn.CreateCommand();
                ins.CommandText = @"INSERT INTO quotations (reference_no, date, customer_id, grand_total, note, discount, del_status, created_at, updated_at, SyncStatus)
                                    VALUES (@ref, @date, @cust, @gt, @note, @disc, 'Live', @now, @now, 'Local');
                                    SELECT last_insert_rowid();";
                ins.Parameters.AddWithValue("@ref", txtRef.Text.Trim());
                ins.Parameters.AddWithValue("@date", txtDate.Text.Trim());
                ins.Parameters.AddWithValue("@cust", cust.Id);
                ins.Parameters.AddWithValue("@gt", grandTotal);
                ins.Parameters.AddWithValue("@note", txtNote.Text.Trim());
                ins.Parameters.AddWithValue("@disc", discText);
                ins.Parameters.AddWithValue("@now", now);
                quotId = (long)(ins.ExecuteScalar() ?? 0);
            }

            foreach (var child in itemsPanel.Children)
            {
                if (child is QuotItemRow row && row.GetItemId() > 0)
                {
                    var insDetail = conn.CreateCommand();
                    insDetail.CommandText = @"INSERT INTO quotation_details (quotation_id, item_id, quantity, unit_price, total, del_status, created_at, updated_at)
                                              VALUES (@qid, @iid, @qty, @up, @total, 'Live', @now, @now)";
                    insDetail.Parameters.AddWithValue("@qid", quotId);
                    insDetail.Parameters.AddWithValue("@iid", row.GetItemId());
                    insDetail.Parameters.AddWithValue("@qty", row.GetQty());
                    insDetail.Parameters.AddWithValue("@up", row.GetPrice());
                    insDetail.Parameters.AddWithValue("@total", row.GetTotal());
                    insDetail.Parameters.AddWithValue("@now", now);
                    insDetail.ExecuteNonQuery();
                }
            }

            tx.Commit();

            SyncService.EnqueueSync("quotations", quotId, _editId > 0 ? "update" : "insert");
            foreach (var child in itemsPanel.Children)
            {
                if (child is QuotItemRow row && row.GetItemId() > 0)
                    SyncService.EnqueueSync("quotation_details", row.GetItemId(), _editId > 0 ? "update" : "insert");
            }
            _dashboard?.TriggerSync();

            _dashboard?.ShowPage(new QuotationListPage(_dashboard));
        }
        catch (Exception ex)
        {
            tx.Rollback();
            MessageBox.Show($"Error: {ex.Message}", "Error", MessageBoxButton.OK, MessageBoxImage.Error);
        }
    }
}

public class QuotLookupItem
{
    public long Id { get; set; }
    public string Name { get; set; } = "";
}

public class QuotItemOption
{
    public long Id { get; set; }
    public string Name { get; set; } = "";
    public double SalePrice { get; set; }
}

public class QuotItemRow : UserControl
{
    private readonly TextBox _txtQty;
    private readonly TextBox _txtPrice;
    private readonly TextBlock _txtTotal;
    private readonly long _itemId;

    public event EventHandler? OnRemove;
    public event EventHandler? OnChanged;

    public QuotItemRow(int sn, long itemId, string itemName, double qty, double price, string desc, bool viewOnly)
    {
        _itemId = itemId;
        Margin = new Thickness(0, 0, 0, 1);

        var border = new Border
        {
            Background = new SolidColorBrush(Color.FromRgb(0xF8, 0xF9, 0xFB)),
            BorderBrush = new SolidColorBrush(Color.FromRgb(0xE3, 0xE8, 0xEF)),
            BorderThickness = new Thickness(0, 0, 0, 1),
            Padding = new Thickness(16, 9, 16, 9)
        };

        var grid = new Grid();
        grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(36) });
        grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(2, GridUnitType.Star) });
        grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
        grid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
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
            VerticalAlignment = VerticalAlignment.Center,
            TextTrimming = TextTrimming.CharacterEllipsis
        };

        _txtQty = MakeTextBox(qty > 0 ? qty.ToString("0.##") : "1", viewOnly);
        _txtQty.TextChanged += (_, _) => Recalc();

        _txtPrice = MakeTextBox(price > 0 ? price.ToString("0.##") : "", viewOnly);
        _txtPrice.TextChanged += (_, _) => Recalc();

        _txtTotal = new TextBlock
        {
            Text = (qty * price).ToString("N2"),
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
            Width = 30, Height = 30,
            Cursor = Cursors.Hand,
            Padding = new Thickness(0),
            HorizontalAlignment = HorizontalAlignment.Center,
            Visibility = viewOnly ? Visibility.Collapsed : Visibility.Visible
        };
        btnRemove.Click += (_, _) => OnRemove?.Invoke(this, EventArgs.Empty);

        Grid.SetColumn(lblSn, 0);
        Grid.SetColumn(lblItem, 1);
        Grid.SetColumn(_txtQty, 2);
        Grid.SetColumn(_txtPrice, 3);
        Grid.SetColumn(_txtTotal, 4);
        Grid.SetColumn(btnRemove, 5);

        grid.Children.Add(lblSn);
        grid.Children.Add(lblItem);
        grid.Children.Add(_txtQty);
        grid.Children.Add(_txtPrice);
        grid.Children.Add(_txtTotal);
        grid.Children.Add(btnRemove);

        border.Child = grid;
        Content = border;
    }

    private void Recalc()
    {
        double qty = 0, price = 0;
        double.TryParse(_txtQty.Text, out qty);
        double.TryParse(_txtPrice.Text, out price);
        _txtTotal.Text = (qty * price).ToString("N2");
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
    public double GetPrice() { double.TryParse(_txtPrice.Text, out var v); return v; }
    public double GetTotal() { return GetQty() * GetPrice(); }
}
