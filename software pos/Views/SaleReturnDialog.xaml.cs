using System;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class SaleReturnDialog : Window
    {
        private readonly DatabaseService _db = new();
        private readonly MainDashboard? _dashboard;
        private List<ReturnSaleRow> _allSales = new();
        private List<ReturnSaleRow> _filteredSales = new();
        private List<ReturnItemRow> _allItems = new();
        private List<ReturnItemRow> _filteredItems = new();
        private List<ReturnItemRow> _productMatches = new();
        private Dictionary<string, double> _returnQtys = new(); // key -> qty
        private bool _leftPanelActive = true;
        private long _selectedSaleId = 0;
        private CustomerInfo? _returnCustomer;

        public SaleReturnDialog(MainDashboard? dashboard = null)
        {
            InitializeComponent();
            _dashboard = dashboard;
            cmbReason.SelectedIndex = 2;
            cmbStock.SelectedIndex = 0;
            cmbRefund.SelectedIndex = 0;
            PreviewKeyDown += Window_PreviewKeyDown;
            Loaded += (s, e) => { LoadSales(); txtSaleSearch.Focus(); };
        }

        // ═══════════ LOAD ═══════════

        private void LoadSales()
        {
            _allSales.Clear();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"
                    SELECT t.rowid*-1 AS id, t.VchNo AS inv, t.VchDate AS date,
                           IFNULL(m.Name, IFNULL((SELECT c.name FROM sales s JOIN customers c ON c.id=s.customer_id WHERE s.invoice_no=t.VchNo LIMIT 1), 'Walk-in')) AS cust,
                           IFNULL(m.Phone, IFNULL((SELECT c.phone FROM sales s JOIN customers c ON c.id=s.customer_id WHERE s.invoice_no=t.VchNo LIMIT 1), '')) AS phone,
                           IFNULL(m.ServerId,0) AS cid,
                           t.Amount AS total, t.VchCode AS vch,
                           CASE WHEN t.VchCode LIKE 'SRV%' THEN t.ServerId
                                ELSE (SELECT s2.ServerId FROM Tran1 s2
                                      WHERE s2.ServerId=t.ServerId AND s2.VchCode LIKE 'SRV%' AND s2.VchCode<>t.VchCode
                                      LIMIT 1)
                           END AS cloud_id
                    FROM Tran1 t LEFT JOIN Master1 m ON m.Code=t.MasterCode1
                    WHERE t.VchType='Sales' AND t.IsCancelled=0
                      AND (t.VchCode LIKE 'SRV%'
                           OR t.ServerId IS NULL OR t.ServerId=0
                           OR NOT EXISTS (SELECT 1 FROM Tran1 s2
                                          WHERE s2.ServerId=t.ServerId AND s2.VchCode LIKE 'SRV%' AND s2.VchCode<>t.VchCode))
                    ORDER BY (CASE WHEN t.VchDate LIKE '__-__-____'
                                  THEN substr(t.VchDate,7,4)||'-'||substr(t.VchDate,4,2)||'-'||substr(t.VchDate,1,2)
                                  ELSE t.VchDate END) DESC, t.rowid DESC
                    LIMIT 300";

                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    _allSales.Add(new ReturnSaleRow
                    {
                        Id = r.GetInt64(0),
                        InvoiceNo = r["inv"]?.ToString() ?? "",
                        Date = r["date"]?.ToString() ?? "",
                        Customer = r["cust"]?.ToString() ?? "",
                        Phone = r["phone"]?.ToString() ?? "",
                        CloudCustomerId = r.IsDBNull(5) ? 0 : r.GetInt64(5),
                        Total = r.IsDBNull(6) ? 0 : r.GetDouble(6),
                        VchCode = r["vch"]?.ToString() ?? "",
                        CloudSaleId = r.IsDBNull(8) ? 0 : r.GetInt64(8)
                    });
                }
            }
            catch { }
            ComputeRemainAmounts();
            ApplySaleFilter();
        }

        /// <summary>Har bill ka remaining returnable amount nikaalta hai (total - already returned).</summary>
        private void ComputeRemainAmounts()
        {
            var returned = new Dictionary<long, Dictionary<long, double>>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT sale_id, item_id, SUM(return_quantity_amount) FROM sale_return_details WHERE sale_id IS NOT NULL GROUP BY sale_id, item_id";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    if (r.IsDBNull(0) || r.IsDBNull(1)) continue;
                    long sid = r.GetInt64(0);
                    if (!returned.TryGetValue(sid, out var map)) { map = new(); returned[sid] = map; }
                    map[r.GetInt64(1)] = r.GetDouble(2);
                }
            }
            catch { }

            var lines = new Dictionary<string, List<(long ItemId, double Qty, double Rate)>>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT t2.VchCode, IFNULL(i.id,0) AS item_id, t2.Quantity, t2.Rate
                                    FROM Tran2 t2 LEFT JOIN items i ON i.code = t2.MasterCode1
                                    WHERE t2.VchCode IN (SELECT VchCode FROM Tran1 WHERE VchType='Sales' AND IsCancelled=0)";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    string vch = r["VchCode"]?.ToString() ?? "";
                    if (!lines.TryGetValue(vch, out var list)) { list = new(); lines[vch] = list; }
                    list.Add((r.IsDBNull(1) ? 0 : r.GetInt64(1), r.GetDouble(2), r.GetDouble(3)));
                }
            }
            catch { }

            foreach (var s in _allSales)
            {
                long sid = s.CloudSaleId > 0 ? s.CloudSaleId : s.Id;
                double remain = 0;
                if (lines.TryGetValue(s.VchCode, out var list))
                {
                    foreach (var (itemId, qty, rate) in list)
                    {
                        double prev = itemId > 0 && returned.TryGetValue(sid, out var map) && map.TryGetValue(itemId, out var p) ? p : 0;
                        remain += Math.Max(0, qty - prev) * rate;
                    }
                }
                s.RemainAmount = remain;
            }
        }

        private void LoadItems(ReturnSaleRow sale)
        {
            _allItems.Clear(); _returnQtys.Clear(); optionsPanel.Visibility = Visibility.Collapsed;
            _selectedSaleId = sale.Id;
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT t2.Id, IFNULL(i.id,0) AS item_id, IFNULL(i.name,t2.Description) AS name,
                                    IFNULL(t2.MasterCode1,'') AS code,
                                    t2.Quantity AS sold_qty, t2.Rate AS price
                                    FROM Tran2 t2
                                    LEFT JOIN items i ON i.code = t2.MasterCode1
                                    WHERE t2.VchCode=@vch ORDER BY t2.SrNo";
                cmd.Parameters.AddWithValue("@vch", sale.VchCode);
                using var r = cmd.ExecuteReader();
                while (r.Read()) _allItems.Add(ReadItemRow(r));
            }
            catch { }
            ApplyAvail(sale);
            _allItems.RemoveAll(i => i.AvailQty <= 0.0001);
            _filteredItems = new List<ReturnItemRow>(_allItems);
            RebuildItemList();
            UpdateStatus();
        }

        private static ReturnItemRow ReadItemRow(SqliteDataReader r) => new()
        {
            DetailId = r.IsDBNull(0) ? 0 : r.GetInt64(0),
            ItemId = r.IsDBNull(1) ? 0 : r.GetInt64(1),
            Name = r["name"]?.ToString() ?? "",
            Code = r["code"]?.ToString() ?? "",
            SoldQty = r.IsDBNull(4) ? 0 : r.GetDouble(4),
            Price = r.IsDBNull(5) ? 0 : r.GetDouble(5)
        };

        /// <summary>Pehle se returned qty minus karke kitna aur return ho sakta hai.</summary>
        private void ApplyAvail(ReturnSaleRow sale)
        {
            foreach (var i in _allItems) i.AvailQty = i.SoldQty;
            long sid = sale.CloudSaleId > 0 ? sale.CloudSaleId : sale.Id;
            if (sid <= 0) return;
            try
            {
                var returned = new Dictionary<long, double>();
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT item_id, SUM(return_quantity_amount) FROM sale_return_details
                                    WHERE sale_id=@sid GROUP BY item_id";
                cmd.Parameters.AddWithValue("@sid", sid);
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    if (!r.IsDBNull(0)) returned[r.GetInt64(0)] = r.IsDBNull(1) ? 0 : r.GetDouble(1);
                foreach (var i in _allItems)
                {
                    double prev = i.ItemId > 0 && returned.ContainsKey(i.ItemId) ? returned[i.ItemId] : 0;
                    i.AvailQty = Math.Max(0, i.SoldQty - prev);
                }
            }
            catch { }
        }

        // ═══════════ FILTER ═══════════

        private void ApplySaleFilter()
        {
            string q = txtSaleSearch.Text.Trim().ToLower();
            _filteredSales = string.IsNullOrEmpty(q) ? new List<ReturnSaleRow>(_allSales)
                : _allSales.Where(s =>
                    s.InvoiceNo.ToLower().Contains(q) ||
                    s.Customer.ToLower().Contains(q) ||
                    s.Phone.Replace(" ", "").Contains(q) ||
                    s.Phone.Replace("+91", "").Contains(q)).ToList();
            RebuildSaleList();
        }

        private void LoadProductMatches(string q)
        {
            _productMatches.Clear();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT i.id, i.code, i.name, IFNULL(i.stock_quantity,0) AS stock, IFNULL(i.sale_price, IFNULL(i.mrp_price,0)) AS price
                                    FROM items i
                                    WHERE (i.del_status IS NULL OR i.del_status != 'Deleted')
                                      AND (@q = '' OR i.name LIKE @q OR i.code LIKE @q)
                                    ORDER BY i.name LIMIT 50";
                cmd.Parameters.AddWithValue("@q", "%" + q + "%");
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    _productMatches.Add(new ReturnItemRow
                    {
                        ItemId = r.GetInt64(0),
                        Code = r["code"]?.ToString() ?? "",
                        Name = r["name"]?.ToString() ?? "",
                        SoldQty = r.IsDBNull(3) ? 0 : r.GetDouble(3),
                        AvailQty = 99999,
                        Price = r.IsDBNull(4) ? 0 : r.GetDouble(4)
                    });
                }
            }
            catch { }
            lstProducts.Items.Clear();
            foreach (var p in _productMatches) lstProducts.Items.Add(BuildProductRow(p));
            if (lstProducts.Items.Count > 0) lstProducts.SelectedIndex = 0;
            popProducts.IsOpen = _productMatches.Count > 0;
        }

        private void AddSelectedProduct()
        {
            int idx = lstProducts.SelectedIndex;
            if (idx < 0 || idx >= _productMatches.Count) return;
            var p = _productMatches[idx];
            string key = KeyOf(p);
            if (!_returnQtys.ContainsKey(key)) _returnQtys[key] = 1;
            if (_allItems.All(i => KeyOf(i) != key)) _allItems.Add(p);
            txtProductSearch.Text = "";
            popProducts.IsOpen = false;
            RebuildCart();
            _leftPanelActive = false;
            lstItems.Focus();
            if (lstItems.Items.Count > 0) lstItems.SelectedIndex = lstItems.Items.Count - 1;
        }

        private void RebuildSaleList()
        {
            int prev = lstSales.SelectedIndex;
            lstSales.Items.Clear();
            foreach (var s in _filteredSales) lstSales.Items.Add(BuildSaleRow(s));
            if (lstSales.Items.Count > 0)
                lstSales.SelectedIndex = Math.Clamp(prev < 0 ? 0 : prev, 0, lstSales.Items.Count - 1);
            UpdateStatus();
        }

        private void RebuildItemList()
        {
            int prev = lstItems.SelectedIndex;
            lstItems.Items.Clear();
            foreach (var i in _filteredItems) lstItems.Items.Add(BuildItemRow(i));
            if (lstItems.Items.Count > 0)
                lstItems.SelectedIndex = Math.Clamp(prev < 0 ? 0 : prev, 0, lstItems.Items.Count - 1);
            UpdateReturnCart();
        }

        // ═══════════ ROW BUILDERS ═══════════

        private FrameworkElement BuildSaleRow(ReturnSaleRow s)
        {
            var g = new Grid { Height = 36 };
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(110) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(80) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(80) });
            g.Children.Add(SCell(0, s.InvoiceNo, "#DC2626", 12, true));
            g.Children.Add(SCell(1, s.Date, "#64748B", 11));
            g.Children.Add(SCell(2, s.Customer, "#1E293B", 12.5, true));
            g.Children.Add(SCell(3, s.RemainAmount.ToString("N0"), "#16A34A", 12, true, right: true));
            return g;
        }

        private FrameworkElement BuildItemRow(ReturnItemRow item)
        {
            string key = KeyOf(item);
            bool selected = _returnQtys.ContainsKey(key) && _returnQtys[key] > 0;
            double retQty = selected ? _returnQtys[key] : 0;
            bool over = retQty > item.AvailQty + 0.0001;

            var g = new Grid { Height = 36 };
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(55) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(70) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(70) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(60) });

            g.Children.Add(SCell(0, item.Name, "#1E293B", 12.5, true));
            g.Children.Add(SCell(1, (item.AvailQty >= 99999 ? item.SoldQty : item.AvailQty).ToString("N2"), "#64748B", 11, center: true));
            g.Children.Add(SCell(2, "Rs." + item.Price.ToString("N2"), "#475569", 11, right: true));

            var qtyBorder = new Border
            {
                Background = new SolidColorBrush(over ? Color.FromRgb(0xFE, 0xE2, 0xE2) : selected ? Color.FromRgb(0xDC, 0xFC, 0xE7) : Color.FromRgb(0xF1, 0xF5, 0xF9)),
                CornerRadius = new CornerRadius(4), Padding = new Thickness(6, 1, 6, 1),
                HorizontalAlignment = HorizontalAlignment.Center, VerticalAlignment = VerticalAlignment.Center
            };
            qtyBorder.Child = new TextBlock
            {
                Text = selected ? retQty.ToString("N2") : "—",
                FontSize = 12, FontWeight = FontWeights.Bold, FontFamily = new FontFamily("Segoe UI"),
                Foreground = new SolidColorBrush(over ? Color.FromRgb(0xDC, 0x26, 0x26) : selected ? Color.FromRgb(0x16, 0xA3, 0x4A) : Color.FromRgb(0x94, 0xA3, 0xB8))
            };
            Grid.SetColumn(qtyBorder, 3); g.Children.Add(qtyBorder);

            var selBorder = new Border
            {
                Background = selected ? new SolidColorBrush(Color.FromRgb(0x16, 0xA3, 0x4A)) : new SolidColorBrush(Color.FromRgb(0xE2, 0xE8, 0xF0)),
                CornerRadius = new CornerRadius(4), Padding = new Thickness(8, 2, 8, 2),
                HorizontalAlignment = HorizontalAlignment.Center, VerticalAlignment = VerticalAlignment.Center
            };
            selBorder.Child = new TextBlock
            {
                Text = selected ? "✓" : "Select",
                FontSize = 11, FontWeight = FontWeights.Bold, FontFamily = new FontFamily("Segoe UI"),
                Foreground = selected ? Brushes.White : new SolidColorBrush(Color.FromRgb(0x64, 0x74, 0x8B))
            };
            Grid.SetColumn(selBorder, 4); g.Children.Add(selBorder);
            return g;
        }

        private FrameworkElement BuildProductRow(ReturnItemRow p)
        {
            var g = new Grid { Height = 36 };
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(100) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(70) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(80) });
            g.Children.Add(SCell(0, p.Name, "#1E293B", 12.5, true));
            g.Children.Add(SCell(1, p.Code, "#64748B", 11));
            g.Children.Add(SCell(2, p.SoldQty.ToString("N2"), "#475569", 11, center: true));
            g.Children.Add(SCell(3, "Rs." + p.Price.ToString("N2"), "#16A34A", 12, true, right: true));
            return g;
        }

        private static TextBlock SCell(int col, string text, string hex, double size, bool bold = false, bool right = false, bool center = false)
        {
            var tb = new TextBlock
            {
                Text = text, FontSize = size, FontFamily = new FontFamily("Segoe UI"),
                FontWeight = bold ? FontWeights.SemiBold : FontWeights.Normal,
                Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString(hex)),
                VerticalAlignment = VerticalAlignment.Center, TextTrimming = TextTrimming.CharacterEllipsis,
                HorizontalAlignment = right ? HorizontalAlignment.Right : center ? HorizontalAlignment.Center : HorizontalAlignment.Left
            };
            Grid.SetColumn(tb, col); return tb;
        }

        // ═══════════ RETURN LOGIC ═══════════

        private static string KeyOf(ReturnItemRow item)
            => (item.ItemId > 0 ? "item" : "detail") + (item.ItemId > 0 ? item.ItemId : item.DetailId);

        private void ToggleItemSelection()
        {
            int idx = lstItems.SelectedIndex;
            if (idx < 0 || idx >= _filteredItems.Count) return;
            var item = _filteredItems[idx];
            string key = KeyOf(item);
            if (_returnQtys.ContainsKey(key))
            {
                _returnQtys.Remove(key);
                if (item.ItemId > 0 && _selectedSaleId == 0) _allItems.RemoveAll(i => KeyOf(i) == key);
            }
            else _returnQtys[key] = item.AvailQty >= 99999 ? 1 : item.AvailQty;
            RebuildCart();
            lstItems.SelectedIndex = Math.Min(idx, lstItems.Items.Count - 1);
        }

        private void AdjustQty()
        {
            int idx = lstItems.SelectedIndex;
            if (idx < 0 || idx >= _filteredItems.Count) return;
            var item = _filteredItems[idx];
            string key = KeyOf(item);
            double current = _returnQtys.ContainsKey(key) ? _returnQtys[key] : 1;

            var dlg = new QuantityWindow(item.Name, item.Code, item.Price, "pcs",
                item.SoldQty, item.AvailQty, 1, current) { Owner = this };

            if (dlg.ShowDialog() == true)
            {
                double newQty = dlg.Quantity;
                if (newQty <= 0)
                {
                    _returnQtys.Remove(key);
                    if (item.ItemId > 0 && _selectedSaleId == 0) _allItems.RemoveAll(i => KeyOf(i) == key);
                }
                else _returnQtys[key] = Math.Min(newQty, item.AvailQty);
                RebuildCart();
                lstItems.SelectedIndex = Math.Max(idx, lstItems.Items.Count - 1);
            }
        }

        private void UpdateReturnCart()
        {
            var selected = _filteredItems.Where(i => _returnQtys.ContainsKey(KeyOf(i)) && _returnQtys[KeyOf(i)] > 0).ToList();
            if (selected.Count == 0) { optionsPanel.Visibility = Visibility.Collapsed; return; }
            optionsPanel.Visibility = Visibility.Visible;
            lblReturnCount.Text = selected.Count + " items";
            lblReturnTotal.Text = selected.Sum(i => _returnQtys[KeyOf(i)] * i.Price).ToString("N2");
        }

        private void RebuildCart()
        {
            _filteredItems = new List<ReturnItemRow>(_allItems);
            RebuildItemList();
        }

        // ═══════════ PROCESS RETURN ═══════════

        private void ProcessReturn()
        {
            bool hasBill = _selectedSaleId != 0;
            ReturnSaleRow? sale = hasBill ? _filteredSales.FirstOrDefault(s => s.Id == _selectedSaleId) : null;

            var toReturn = _filteredItems.Where(i => _returnQtys.ContainsKey(KeyOf(i)) && _returnQtys[KeyOf(i)] > 0).ToList();
            if (toReturn.Count == 0) { MessageBox.Show("Koi item select nahi kiya.", "Sale Return", MessageBoxButton.OK, MessageBoxImage.Warning); return; }

            bool hasProduct = toReturn.Any(i => i.ItemId > 0 && !hasBill);
            if (hasProduct && !EnsureCustomerForProducts())
            {
                MessageBox.Show("Product return ke liye customer add karna zaroori hai.", "Customer Required", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }

            foreach (var i in toReturn)
                if (hasBill && _returnQtys[KeyOf(i)] > i.AvailQty + 0.0001)
                {
                    MessageBox.Show($"{i.Name}: qty available ({i.AvailQty:N2}) se zyada hai!", "Sale Return", MessageBoxButton.OK, MessageBoxImage.Warning);
                    return;
                }

            double total = toReturn.Sum(i => _returnQtys[KeyOf(i)] * i.Price);
            var reason = (cmbReason.SelectedItem as ComboBoxItem)?.Content as string ?? "";
            var stock = (cmbStock.SelectedItem as ComboBoxItem)?.Content as string ?? "";
            var refund = (cmbRefund.SelectedItem as ComboBoxItem)?.Content as string ?? "";
            string note = $"Reason: {reason}; Stock: {stock}; Refund: {refund}";

            string billLine = hasBill
                ? $"Bill: {sale!.InvoiceNo}  |  {sale.Customer}"
                : $"Without Bill  |  Customer: {_returnCustomer?.Name ?? "Walk-in"}";

            string confirm = $"Return process karein?\n\n{billLine}\n" +
                             $"Items: {toReturn.Count}  |  Total: Rs.{total:N2}\n" +
                             $"Refund: {refund}  |  Stock: {stock}";
            if (MessageBox.Show(confirm, "Confirm Return", MessageBoxButton.YesNo, MessageBoxImage.Question) != MessageBoxResult.Yes) return;

            try
            {
                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();
                string refNo = GenerateRefNo(conn);
                string now = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
                string date = DateTime.Now.ToString("yyyy-MM-dd");
                long? fkSaleId = hasBill && sale!.CloudSaleId > 0 ? sale.CloudSaleId : (long?)null;
                long detailSaleId = hasBill ? (sale.CloudSaleId > 0 ? sale.CloudSaleId : sale.Id) : 0;
                long? customerId = hasBill
                    ? (sale.CloudCustomerId > 0 ? sale.CloudCustomerId : GetLocalPayCustomerId(conn, sale.VchCode))
                    : (_returnCustomer != null && _returnCustomer.Id > 0 ? _returnCustomer.Id : (long?)null);

                // Resolve payment_method_id from cmbRefund selection
                long paymentMethodId = ResolvePaymentMethodId(conn, refund);

                var itemsPayload = new List<object>();
                foreach (var i in toReturn)
                {
                    double qty = _returnQtys[KeyOf(i)];
                    if (i.ItemId > 0)
                        itemsPayload.Add(new Dictionary<string, object?>
                        {
                            ["item_id"] = GetCloudItemId(conn, i.ItemId),
                            ["item_code"] = i.Code,
                            ["sale_quantity_amount"] = i.SoldQty,
                            ["return_quantity_amount"] = qty,
                            ["unit_price_in_sale"] = i.Price,
                            ["unit_price_in_return"] = i.Price
                        });
                }

                long returnId;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.Transaction = txn;
                    // Explicit negative Id (LocalTxn.NextLocalId) — prevents collision with server-assigned positive IDs during pull sync
                    cmd.CommandText = @"INSERT INTO sale_returns (Id, reference_no, sale_id, customer_id, date, total_return_amount, paid, due, payment_method_id, note, user_id, outlet_id, company_id, del_status, SyncStatus, created_at, updated_at)
                        VALUES (@id, @ref, @sid, @cid, @date, @total, @total, 0, @pmid, @note, 1, 1, 1, 'Live', 'Local', @now, @now)";
                    cmd.Parameters.AddWithValue("@id", LocalTxn.NextLocalId(_db, "sale_returns"));
                    cmd.Parameters.AddWithValue("@ref", refNo);
                    cmd.Parameters.AddWithValue("@sid", fkSaleId ?? (object)DBNull.Value);
                    cmd.Parameters.AddWithValue("@cid", customerId ?? (object)DBNull.Value);
                    cmd.Parameters.AddWithValue("@date", date);
                    cmd.Parameters.AddWithValue("@total", total);
                    cmd.Parameters.AddWithValue("@pmid", paymentMethodId);
                    cmd.Parameters.AddWithValue("@note", note);
                    cmd.Parameters.AddWithValue("@now", now);
                    cmd.ExecuteNonQuery();
                }
                using (var lid = conn.CreateCommand()) { lid.Transaction = txn; lid.CommandText = "SELECT last_insert_rowid()"; returnId = Convert.ToInt64(lid.ExecuteScalar()); }

                foreach (var i in toReturn)
                {
                    double qty = _returnQtys[KeyOf(i)];
                    using var cmd = conn.CreateCommand();
                    cmd.Transaction = txn;
                    cmd.CommandText = @"INSERT INTO sale_return_details (sale_return_id, sale_id, item_id, sale_quantity_amount, return_quantity_amount, unit_price_in_sale, unit_price_in_return, del_status, user_id, outlet_id, company_id, SyncStatus, created_at, updated_at)
                        VALUES (@rid, @sid, @item, @sq, @rq, @ps, @pr, 'Live', 1, 1, 1, 'Local', @now, @now)";
                    cmd.Parameters.AddWithValue("@rid", returnId);
                    cmd.Parameters.AddWithValue("@sid", i.DetailId != 0 ? detailSaleId : 0);
                    cmd.Parameters.AddWithValue("@item", i.ItemId);
                    cmd.Parameters.AddWithValue("@sq", i.SoldQty);
                    cmd.Parameters.AddWithValue("@rq", qty);
                    cmd.Parameters.AddWithValue("@ps", i.Price);
                    cmd.Parameters.AddWithValue("@pr", i.Price);
                    cmd.Parameters.AddWithValue("@now", now);
                    cmd.ExecuteNonQuery();

                    if (stock == "Sellable" && i.ItemId > 0)
                    {
                        using var up = conn.CreateCommand();
                        up.Transaction = txn;
                        up.CommandText = "UPDATE items SET stock_quantity=IFNULL(stock_quantity,0)+@q, SyncStatus='Local' WHERE id=@id";
                        up.Parameters.AddWithValue("@q", qty);
                        up.Parameters.AddWithValue("@id", i.ItemId);
                        up.ExecuteNonQuery();
                    }
                }

                var payload = new Dictionary<string, object?>
                {
                    ["local_id"] = returnId,
                    ["reference_no"] = refNo,
                    ["sale_id"] = fkSaleId,
                    ["customer_id"] = customerId,
                    ["date"] = date,
                    ["total_return_amount"] = Math.Round(total, 2),
                    ["paid"] = Math.Round(total, 2),
                    ["due"] = 0,
                    ["payment_method_id"] = paymentMethodId,
                    ["note"] = note,
                    ["items"] = itemsPayload
                };
                using (var cmd = conn.CreateCommand())
                {
                    cmd.Transaction = txn;
                    cmd.CommandText = "UPDATE sale_returns SET SyncPayload=@p WHERE id=@id";
                    cmd.Parameters.AddWithValue("@p", JsonSerializer.Serialize(payload));
                    cmd.Parameters.AddWithValue("@id", returnId);
                    cmd.ExecuteNonQuery();
                }

                txn.Commit();
                // Stock update hua — saare subscribed pages (Stock/Low Stock/Inventory) refresh
                Services.StockEvents.NotifyStockChanged();
                // Cloud pe turant sync — web par ~2-3 sec me dikhega
                _ = _dashboard?.TriggerSync();
                MessageBox.Show($"Return processed ho gaya!\n\nRef: {refNo}\nTotal: Rs.{total:N2}\nRefund: {refund}\n\nCloud pe turant sync ho raha hai.",
                                "Success", MessageBoxButton.OK, MessageBoxImage.Information);
                Close();
            }
            catch (Exception ex) { MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error); }
        }

        private static long? GetLocalPayCustomerId(SqliteConnection conn, string vchNo)
        {
            try
            {
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT SyncPayload FROM Tran1 WHERE VchCode=@v LIMIT 1";
                cmd.Parameters.AddWithValue("@v", vchNo);
                var payload = cmd.ExecuteScalar() as string;
                if (string.IsNullOrWhiteSpace(payload)) return null;
                using var doc = JsonDocument.Parse(payload);
                if (doc.RootElement.TryGetProperty("customer_id", out var cid) && cid.ValueKind == JsonValueKind.Number && cid.GetInt64() > 0)
                    return cid.GetInt64();
            }
            catch { }
            return null;
        }

        /// <summary>Local item id → cloud (server) id. Local synced items negative ids
        /// par rakhe hote hain aur asli id ServerId column me hoti hai — return
        /// payload me hamesha server id bhejo warna cloud return reject karta hai.</summary>
        private static long GetCloudItemId(SqliteConnection conn, long localItemId)
        {
            if (localItemId == 0) return 0;
            try
            {
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT ServerId FROM items WHERE id=@id LIMIT 1";
                cmd.Parameters.AddWithValue("@id", localItemId);
                var r = cmd.ExecuteScalar();
                if (r != null && r != DBNull.Value)
                {
                    long sid = Convert.ToInt64(r);
                    if (sid > 0) return sid;
                }
            }
            catch { }
            return localItemId;
        }

        private static string GenerateRefNo(SqliteConnection conn)
        {
            long count = 0;
            try { using var cmd = conn.CreateCommand(); cmd.CommandText = "SELECT COUNT(*) FROM sale_returns"; count = Convert.ToInt64(cmd.ExecuteScalar()); } catch { }
            return "SR-" + DateTime.Now.Year + "-" + (count + 1).ToString("00000");
        }

        /// <summary>Resolve payment method name (from cmbRefund) to its ID in the payment_methods table.</summary>
        private static long ResolvePaymentMethodId(SqliteConnection conn, string refundMethodName)
        {
            if (string.IsNullOrWhiteSpace(refundMethodName)) return 1;
            try
            {
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT Id FROM payment_methods WHERE Name=@name AND IFNULL(del_status,'Live')='Live' LIMIT 1";
                cmd.Parameters.AddWithValue("@name", refundMethodName.Trim());
                var result = cmd.ExecuteScalar();
                if (result != null && result != DBNull.Value) return Convert.ToInt64(result);

                // Fallback: case-insensitive match
                using var cmd2 = conn.CreateCommand();
                cmd2.CommandText = "SELECT Id FROM payment_methods WHERE LOWER(Name)=LOWER(@name) AND IFNULL(del_status,'Live')='Live' LIMIT 1";
                cmd2.Parameters.AddWithValue("@name", refundMethodName.Trim());
                var result2 = cmd2.ExecuteScalar();
                if (result2 != null && result2 != DBNull.Value) return Convert.ToInt64(result2);
            }
            catch { }
            return 1; // Default to Cash if not found
        }

        // ═══════════ STATUS ═══════════

        private void UpdateStatus()
        {
            if (_selectedSaleId == 0 && _allItems.Count > 0)
            {
                lblStatus.Text = $"Return list: {_allItems.Count} item(s)   ·   Space = remove   +/- = qty   Enter = customer & next   Enter = return";
                return;
            }
            if (_selectedSaleId != 0 && _allItems.Count == 0 && _returnQtys.Count == 0)
            {
                lblStatus.Text = "Bill selected - saare items already return ho chuke hain. Naya product add karne ke liye 📦 box me type karo.";
                return;
            }
            int si = lstSales.SelectedIndex;
            if (si >= 0 && si < _filteredSales.Count)
            {
                var s = _filteredSales[si];
                if (_selectedSaleId != 0)
                    lblStatus.Text = $"Bill: {s.InvoiceNo}  {s.Customer}  Remain: Rs.{s.RemainAmount:N2}   ·   Space = item select   +/- = qty   Enter = next   Enter = return";
                else
                    lblStatus.Text = $"Bill: {s.InvoiceNo}  {s.Customer}  Remain: Rs.{s.RemainAmount:N2}   ·   📦 Product box se item add karo   Enter = return";
            }
            else lblStatus.Text = "🧾 Bill search  ·  📦 Product search  ·  ↑↓ = move  Enter = select  Space = item  Enter = return";
        }

        private void UpdateStatus(string msg) => lblStatus.Text = msg;

        // ═══════════ KEYBOARD ═══════════

        private bool IsInOptions() => cmbReason.IsKeyboardFocusWithin || cmbStock.IsKeyboardFocusWithin || cmbRefund.IsKeyboardFocusWithin;

        private bool AnyComboOpen() => cmbReason.IsDropDownOpen || cmbStock.IsDropDownOpen || cmbRefund.IsDropDownOpen;

        private ComboBox FocusedCombo()
        {
            if (cmbReason.IsKeyboardFocusWithin) return cmbReason;
            if (cmbStock.IsKeyboardFocusWithin) return cmbStock;
            if (cmbRefund.IsKeyboardFocusWithin) return cmbRefund;
            return null;
        }

        private void MoveCombo(int dir)
        {
            var cur = FocusedCombo();
            if (cur == cmbReason) (dir > 0 ? cmbStock : cmbRefund).Focus();
            else if (cur == cmbStock) (dir > 0 ? cmbRefund : cmbReason).Focus();
            else if (cur == cmbRefund) (dir > 0 ? cmbReason : cmbStock).Focus();
        }

        private bool HasProductLines() => _allItems.Any(i => i.ItemId > 0 && _selectedSaleId == 0);

        private bool EnsureCustomerForProducts()
        {
            if (!HasProductLines() || _returnCustomer != null) return true;
            var win = new CustomerWindow { Owner = this };
            if (win.ShowDialog() == true && win.SelectedCustomer != null)
            {
                _returnCustomer = win.SelectedCustomer;
                lblStatus.Text = $"Customer: {_returnCustomer.Name}  ·  ←→ = box  ↑↓ = select  Enter = return";
                return true;
            }
            return false;
        }

        private void Window_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            if (IsInOptions() && (e.Key == Key.Left || e.Key == Key.Right))
            {
                var open = FocusedCombo();
                if (open != null) open.IsDropDownOpen = false;
                MoveCombo(e.Key == Key.Right ? 1 : -1);
                e.Handled = true;
                return;
            }

            if (AnyComboOpen()) return; // ComboBox dropdown khula ho to usse handle karne do
            bool shift = Keyboard.IsKeyDown(Key.LeftShift) || Keyboard.IsKeyDown(Key.RightShift);

            // Search box focus ho to sirf Tab/Ctrl+Tab/Escape window level par handle karo;
            // baaki keys (Enter/Up/Down) TextBox ke apne handlers sambhalte hain.
            if (txtSaleSearch.IsKeyboardFocusWithin || txtProductSearch.IsKeyboardFocusWithin)
            {
                if (e.Key == Key.Tab)
                {
                    if (txtSaleSearch.IsKeyboardFocusWithin) OpenProductPicker();
                    else txtSaleSearch.Focus();
                    e.Handled = true;
                }
                else if (e.Key == Key.Escape)
                {
                    if (popProducts.IsOpen) popProducts.IsOpen = false;
                    else Close();
                    e.Handled = true;
                }
                return;
            }

            switch (e.Key)
            {
                case Key.Escape: Close(); e.Handled = true; break;

                case Key.Tab:
                    if (Keyboard.Modifiers.HasFlag(ModifierKeys.Control))
                    {
                        OpenProductPicker();
                        e.Handled = true;
                        break;
                    }
                    if (shift)
                    {
                        if (IsInOptions()) { _leftPanelActive = false; lstItems.Focus(); }
                        else { _leftPanelActive = true; lstSales.Focus(); }
                    }
                    else if (_leftPanelActive && _selectedSaleId != 0) { _leftPanelActive = false; lstItems.Focus(); }
                    else if (!_leftPanelActive && lstItems.IsKeyboardFocusWithin) { cmbReason.Focus(); }
                    else if (cmbReason.IsKeyboardFocusWithin) cmbStock.Focus();
                    else if (cmbStock.IsKeyboardFocusWithin) cmbRefund.Focus();
                    else { _leftPanelActive = true; lstSales.Focus(); }
                    e.Handled = true; break;

                case Key.Up:
                    if (_leftPanelActive)
                    {
                        if (lstSales.SelectedIndex > 0) lstSales.SelectedIndex--;
                        lstSales.ScrollIntoView(lstSales.SelectedItem);
                        e.Handled = true;
                    }
                    else if (lstItems.IsKeyboardFocusWithin)
                    {
                        if (lstItems.SelectedIndex > 0) lstItems.SelectedIndex--;
                        lstItems.ScrollIntoView(lstItems.SelectedItem);
                        e.Handled = true;
                    }
                    else if (IsInOptions()) { } // combo khud Up/Down se selection change karta hai (unhandled pass)
                    break;

                case Key.Down:
                    if (_leftPanelActive)
                    {
                        if (lstSales.SelectedIndex < lstSales.Items.Count - 1) lstSales.SelectedIndex++;
                        lstSales.ScrollIntoView(lstSales.SelectedItem);
                        e.Handled = true;
                    }
                    else if (lstItems.IsKeyboardFocusWithin)
                    {
                        if (lstItems.SelectedIndex < lstItems.Items.Count - 1) lstItems.SelectedIndex++;
                        lstItems.ScrollIntoView(lstItems.SelectedItem);
                        e.Handled = true;
                    }
                    else if (IsInOptions()) { } // combo khud Up/Down se selection change karta hai (unhandled pass)
                    break;

                case Key.Left:
                    if (!_leftPanelActive && lstItems.IsKeyboardFocusWithin) { _leftPanelActive = true; lstSales.Focus(); e.Handled = true; }
                    break;

                case Key.Right:
                    if (_leftPanelActive && _selectedSaleId != 0) { _leftPanelActive = false; lstItems.Focus(); if (lstItems.SelectedIndex < 0 && lstItems.Items.Count > 0) lstItems.SelectedIndex = 0; e.Handled = true; }
                    break;

                case Key.Enter:
                    if (_leftPanelActive)
                    {
                        int i = lstSales.SelectedIndex;
                        if (i >= 0 && i < _filteredSales.Count)
                        {
                            LoadItems(_filteredSales[i]);
                            _leftPanelActive = false; lstItems.Focus();
                            if (lstItems.Items.Count > 0) lstItems.SelectedIndex = 0;
                        }
                    }
                    else if (lstItems.IsKeyboardFocusWithin)
                    {
                        bool any = _returnQtys.Values.Any(v => v > 0);
                        if (any)
                        {
                            if (!EnsureCustomerForProducts()) { UpdateStatus("Customer add nahi kiya - Enter dobara try karo"); e.Handled = true; break; }
                            cmbReason.Focus();
                        }
                        else UpdateStatus("Pehle Space se return items select karo");
                    }
                    else if (IsInOptions()) ProcessReturn();
                    e.Handled = true; break;

                case Key.Space:
                    if (!_leftPanelActive && lstItems.IsKeyboardFocusWithin) { ToggleItemSelection(); e.Handled = true; }
                    else if (IsInOptions()) { var cb = FocusedCombo(); if (cb != null) cb.IsDropDownOpen = true; e.Handled = true; }
                    break;

                case Key.Add:
                case Key.OemPlus:
                case Key.Subtract:
                case Key.OemMinus:
                    if (!_leftPanelActive && lstItems.IsKeyboardFocusWithin) { AdjustQty(); e.Handled = true; }
                    break;

                default:
                    bool letter = e.Key >= Key.A && e.Key <= Key.Z || e.Key >= Key.D0 && e.Key <= Key.D9;
                    if (letter && Keyboard.Modifiers == ModifierKeys.None && !txtSaleSearch.IsKeyboardFocusWithin && !txtProductSearch.IsKeyboardFocusWithin && !IsInOptions())
                        txtSaleSearch.Focus();
                    break;
            }
        }

        // ═══════════ PRODUCT SEARCH (dropdown) ═══════════

        private void TxtProductSearch_TextChanged(object s, TextChangedEventArgs e)
        {
            string q = txtProductSearch.Text.Trim();
            if (q.Length == 0) { LoadProductMatches(""); return; }
            LoadProductMatches(q);
        }

        /// <summary>Product box par focus: invoice selection hatakar saare items (stock + out-of-stock) popup me dikhao.</summary>
        private void OpenProductPicker()
        {
            lstSales.SelectedIndex = -1;
            _leftPanelActive = false;
            LoadProductMatches(txtProductSearch.Text.Trim());
            txtProductSearch.Focus();
        }

        private void TxtProductSearch_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            switch (e.Key)
            {
                case Key.Down:
                    if (popProducts.IsOpen && lstProducts.Items.Count > 0)
                    {
                        if (lstProducts.SelectedIndex < lstProducts.Items.Count - 1) lstProducts.SelectedIndex++;
                        lstProducts.ScrollIntoView(lstProducts.SelectedItem);
                    }
                    e.Handled = true;
                    break;
                case Key.Up:
                    if (popProducts.IsOpen && lstProducts.Items.Count > 0)
                    {
                        if (lstProducts.SelectedIndex > 0) lstProducts.SelectedIndex--;
                        lstProducts.ScrollIntoView(lstProducts.SelectedItem);
                    }
                    e.Handled = true;
                    break;
                case Key.Enter:
                    if (popProducts.IsOpen && lstProducts.SelectedIndex >= 0) AddSelectedProduct();
                    else if (txtProductSearch.Text.Trim().Length > 0) LoadProductMatches(txtProductSearch.Text.Trim());
                    e.Handled = true;
                    break;
                case Key.Escape:
                    popProducts.IsOpen = false;
                    e.Handled = true;
                    break;
            }
        }

        private void LstProducts_MouseDown(object sender, MouseButtonEventArgs e)
        {
            var pos = e.GetPosition(lstProducts);
            for (int i = 0; i < lstProducts.Items.Count; i++)
            {
                var item = lstProducts.ItemContainerGenerator.ContainerFromIndex(i) as ListBoxItem;
                if (item != null && item.IsVisible && pos.Y >= item.TranslatePoint(new Point(0, 0), lstProducts).Y
                    && pos.Y <= item.TranslatePoint(new Point(0, item.ActualHeight), lstProducts).Y)
                {
                    lstProducts.SelectedIndex = i;
                    AddSelectedProduct();
                    return;
                }
            }
        }

        // ═══════════ EVENT HANDLERS ═══════════

        private void TxtSaleSearch_TextChanged(object s, TextChangedEventArgs e) => ApplySaleFilter();
        private void LstSales_SelectionChanged(object s, SelectionChangedEventArgs e) { _leftPanelActive = true; UpdateStatus(); }
        private void LstItems_SelectionChanged(object s, SelectionChangedEventArgs e) { _leftPanelActive = false; }
        private void BtnClose_Click(object s, MouseButtonEventArgs e) => Close();

        private void TxtSaleSearch_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Down)
            {
                if (_filteredSales.Count > 0)
                {
                    _leftPanelActive = true;
                    if (lstSales.SelectedIndex < 0) lstSales.SelectedIndex = 0;
                    lstSales.Focus();
                }
                e.Handled = true;
            }
            else if (e.Key == Key.Enter)
            {
                if (lstSales.SelectedIndex >= 0 && lstSales.SelectedIndex < _filteredSales.Count)
                {
                    LoadItems(_filteredSales[lstSales.SelectedIndex]);
                    _leftPanelActive = false; lstItems.Focus();
                    if (lstItems.Items.Count > 0) lstItems.SelectedIndex = 0;
                }
                e.Handled = true;
            }
            else if (e.Key == Key.Escape) { Close(); e.Handled = true; }
        }

        private void BtnClearReturn_Click(object s, MouseButtonEventArgs e)
        {
            _returnQtys.Clear();
            _allItems.RemoveAll(i => i.ItemId > 0 && _selectedSaleId == 0);
            RebuildCart();
        }

        private void BtnAddQty_Click(object s, MouseButtonEventArgs e) => AdjustQty();
        private void BtnProcessReturn_Click(object s, MouseButtonEventArgs e) => ProcessReturn();
    }

    public class ReturnSaleRow
    {
        public long Id { get; set; }
        public string InvoiceNo { get; set; } = "";
        public string Date { get; set; } = "";
        public string Customer { get; set; } = "";
        public string Phone { get; set; } = "";
        public double Total { get; set; }
        public bool IsCloud => CloudSaleId > 0;
        public string VchCode { get; set; } = "";
        public long CloudCustomerId { get; set; }
        public long CloudSaleId { get; set; }
        public double RemainAmount { get; set; }
    }

    public class ReturnItemRow
    {
        public long DetailId { get; set; }
        public long ItemId { get; set; }
        public string Name { get; set; } = "";
        public string Code { get; set; } = "";
        public double SoldQty { get; set; }
        public double AvailQty { get; set; }
        public double Price { get; set; }
    }
}
