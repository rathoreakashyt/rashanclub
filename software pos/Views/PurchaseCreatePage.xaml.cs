using System;
using System.Collections.Generic;
using System.Collections.ObjectModel;
using System.Globalization;
using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public class PurchaseItemVM
    {
        public long Id { get; set; }
        public string Name { get; set; } = "";
        public string Code { get; set; } = "";
        public string ItemType { get; set; } = "";
    }

    public class PurchaseLineVM : System.ComponentModel.INotifyPropertyChanged
    {
        public List<PurchaseItemVM> Items { get; set; } = new();
        public long ItemId { get; set; }
        private string _qty = "1";
        private string _price = "0";
        public string Qty { get => _qty; set { _qty = value; OnChanged(nameof(Qty)); Recalc(); } }
        public string Price { get => _price; set { _price = value; OnChanged(nameof(Price)); Recalc(); } }
        public string Total { get; private set; } = "0.00";
        public PurchaseItemVM? Selected { get; set; }

        public void Recalc()
        {
            double.TryParse(Qty, NumberStyles.Any, CultureInfo.InvariantCulture, out double q);
            double.TryParse(Price, NumberStyles.Any, CultureInfo.InvariantCulture, out double p);
            Total = Math.Round(q * p, 2).ToString("N2");
            OnChanged(nameof(Total));
        }

        public event System.ComponentModel.PropertyChangedEventHandler? PropertyChanged;
        private void OnChanged(string n) => PropertyChanged?.Invoke(this, new System.ComponentModel.PropertyChangedEventArgs(n));
    }

    public partial class PurchaseCreatePage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new DatabaseService();
        private readonly ObservableCollection<PurchaseLineVM> _lines = new();
        private long _editId;

        public PurchaseCreatePage() { InitializeComponent(); }
        public PurchaseCreatePage(MainDashboard dashboard) : this() { _dashboard = dashboard; LoadLookups(); }
        public PurchaseCreatePage(MainDashboard dashboard, long purchaseId) : this()
        {
            _dashboard = dashboard;
            LoadLookups();
            LoadForEdit(purchaseId);
        }

        private void LoadForEdit(long id)
        {
            try
            {
                _editId = id;
                lblTitle.Text = "Edit Purchase";
                lblSubtitle.Text = "Update the selected purchase entry";
                using var conn = _db.GetConnection();
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"SELECT reference_no, invoice_no, supplier_id, date, note, discount, paid FROM purchases WHERE Id=@id";
                    cmd.Parameters.AddWithValue("@id", id);
                    using var r = cmd.ExecuteReader();
                    if (!r.Read()) return;
                    txtReference.Text = r["reference_no"]?.ToString() ?? "";
                    txtInvoiceNo.Text = r["invoice_no"]?.ToString() ?? "";
                    txtNote.Text = r["note"]?.ToString() ?? "";
                    txtDiscount.Text = r["discount"]?.ToString() ?? "0";
                    txtPaid.Text = (r["paid"] is double d ? d : 0).ToString(CultureInfo.InvariantCulture);
                    if (DateTime.TryParse(r["date"]?.ToString(), out DateTime dt)) dpDate.SelectedDate = dt;
                    SelectByTag(cmbSupplier, r["supplier_id"] is long sid ? sid.ToString() : "");
                }
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT payment_id FROM purchase_payments WHERE purchase_id=@id ORDER BY Id DESC LIMIT 1";
                    cmd.Parameters.AddWithValue("@id", id);
                    var v = cmd.ExecuteScalar();
                    if (v != null) SelectByTag(cmbPayment, v.ToString());
                }

                var lines = new List<(long ItemId, string Qty, string Price)>();
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT item_id, quantity_amount, unit_price FROM purchase_details WHERE purchase_id=@id";
                    cmd.Parameters.AddWithValue("@id", id);
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                        lines.Add((r["item_id"] is long iid ? iid : 0,
                                   (r["quantity_amount"] is double q ? q : 0).ToString(CultureInfo.InvariantCulture),
                                   (r["unit_price"] is double p ? p : 0).ToString(CultureInfo.InvariantCulture)));
                }
                _lines.Clear();
                foreach (var (itemId, qty, price) in lines)
                {
                    AddLine();
                    var line = _lines[_lines.Count - 1];
                    var it = line.Items.FirstOrDefault(x => x.Id == itemId);
                    if (it != null)
                    {
                        line.ItemId = itemId;
                        line.Selected = it;
                        line.Qty = qty;
                        line.Price = price;
                    }
                }
                if (_lines.Count == 0) AddLine();
                RecalcTotals();
            }
            catch { }
        }

        private static void SelectByTag(ComboBox cb, string tag)
        {
            for (int i = 0; i < cb.Items.Count; i++)
                if ((cb.Items[i] as ComboBoxItem)?.Tag?.ToString() == tag) { cb.SelectedIndex = i; return; }
            if (cb.Items.Count > 0) cb.SelectedIndex = 0;
        }

        private void LoadLookups()
        {
            try
            {
                foreach (var s in Lookups.Suppliers(_db))
                    cmbSupplier.Items.Add(new ComboBoxItem { Content = s.Name, Tag = s.Id.ToString() });

                foreach (var p in Lookups.PaymentMethods(_db))
                    cmbPayment.Items.Add(new ComboBoxItem { Content = p.Name, Tag = p.Id.ToString() });
                cmbPayment.SelectedIndex = 0;
            }
            catch { }

            dpDate.SelectedDate = DateTime.Today;
            txtReference.Text = LocalTxn.NextReference(_db, "purchases", "PUR-");
            AddLine();
        }

        private void AddLine()
        {
            var line = new PurchaseLineVM();
            using (var conn = _db.GetConnection())
            {
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT ServerId, Code, Name, IFNULL(ItemType,'') AS ItemType FROM Master1 WHERE MasterType='Item' AND IsActive=1 AND Name<>'' ORDER BY Name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    long id = r["ServerId"] is long s ? s : 0;
                    line.Items.Add(new PurchaseItemVM
                    {
                        Id = id,
                        Code = r["Code"]?.ToString() ?? "",
                        Name = (r["Name"]?.ToString() ?? "") + (id > 0 ? " [" + r["Code"] + "]" : ""),
                        ItemType = r["ItemType"]?.ToString() ?? ""
                    });
                }
            }
            _lines.Add(line);
            itemsPanel.ItemsSource = _lines;
            RecalcTotals();
        }

        private void Item_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (sender is ComboBox cb && cb.SelectedItem is PurchaseItemVM it && cb.Tag is PurchaseLineVM line)
            {
                line.ItemId = it.Id;
                line.Selected = it;
                if (line.Price == "0" || line.Price == "")
                {
                    using var conn = _db.GetConnection();
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = "SELECT PurchaseRate FROM Master1 WHERE ServerId=@id AND MasterType='Item'";
                    cmd.Parameters.AddWithValue("@id", it.Id);
                    var v = cmd.ExecuteScalar();
                    if (v != null && v is double d)
                        line.Price = d.ToString(CultureInfo.InvariantCulture);
                }
            }
        }

        private void Qty_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (sender is TextBox tb && tb.Tag is PurchaseLineVM line) { line.Recalc(); RecalcTotals(); }
        }

        private void Price_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (sender is TextBox tb && tb.Tag is PurchaseLineVM line) { line.Recalc(); RecalcTotals(); }
        }

        private void BtnAddItem_Click(object sender, RoutedEventArgs e) => AddLine();

        private void BtnRemoveItem_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is PurchaseLineVM line)
            {
                _lines.Remove(line);
                RecalcTotals();
            }
        }

        private void Totals_Changed(object sender, TextChangedEventArgs e) => RecalcTotals();

        private double Subtotal()
        {
            double sum = 0;
            foreach (var l in _lines)
            {
                double.TryParse(l.Qty, NumberStyles.Any, CultureInfo.InvariantCulture, out double q);
                double.TryParse(l.Price, NumberStyles.Any, CultureInfo.InvariantCulture, out double p);
                sum += Math.Round(q * p, 2);
            }
            return Math.Round(sum, 2);
        }

        private static double ParseDiscount(string text, double sub, out string normalized)
        {
            normalized = (text ?? "").Trim().Replace(" ", "");
            if (normalized == "") return 0;
            if (normalized.EndsWith("%") &&
                double.TryParse(normalized.TrimEnd('%'), NumberStyles.Any, CultureInfo.InvariantCulture, out double pct))
                return Math.Round(sub * Math.Clamp(pct, 0, 100) / 100.0, 2);
            if (double.TryParse(normalized, NumberStyles.Any, CultureInfo.InvariantCulture, out double flat))
                return Math.Max(Math.Round(flat, 2), 0);
            return 0;
        }

        private void RecalcTotals()
        {
            double sub = Subtotal();
            double disc = ParseDiscount(txtDiscount?.Text ?? "", sub, out _);
            double grand = Math.Round(sub - disc, 2);
            if (grand < 0) grand = 0;
            double.TryParse(txtPaid?.Text.Trim() ?? "0", NumberStyles.Any, CultureInfo.InvariantCulture, out double paid);
            paid = Math.Clamp(Math.Round(paid, 2), 0, grand);
            if (lblGrandTotal != null) lblGrandTotal.Text = grand.ToString("N2");
            if (lblPaid != null) lblPaid.Text = paid.ToString("N2");
            if (lblDue != null) lblDue.Text = Math.Round(grand - paid, 2).ToString("N2");
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowPage(new PurchaseListPage(_dashboard!));
        }

        private void BtnSave_Click(object sender, RoutedEventArgs e)
        {
            if (cmbSupplier.SelectedIndex < 0 || !long.TryParse((cmbSupplier.SelectedItem as ComboBoxItem)?.Tag as string ?? "", out long supplierId))
            {
                MessageBox.Show("Please select a supplier.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }
            var valid = new List<PurchaseLineVM>();
            for (int i = 0; i < _lines.Count; i++)
            {
                var l = _lines[i];
                if (l.ItemId <= 0) continue;
                if (!double.TryParse(l.Qty, NumberStyles.Any, CultureInfo.InvariantCulture, out double q) || q <= 0)
                {
                    MessageBox.Show($"Line {i + 1}: Please enter a valid quantity (greater than zero).", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                    return;
                }
                if (!double.TryParse(l.Price, NumberStyles.Any, CultureInfo.InvariantCulture, out double p) || p < 0)
                {
                    MessageBox.Show($"Line {i + 1}: Please enter a valid unit price (zero or more).", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                    return;
                }
                l.Qty = q.ToString(CultureInfo.InvariantCulture);
                l.Price = Math.Round(p, 2).ToString(CultureInfo.InvariantCulture);
                valid.Add(l);
            }
            if (valid.Count == 0)
            {
                MessageBox.Show("Please add at least one item with quantity.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }

            double sub = 0;
            foreach (var l in valid)
            {
                double.TryParse(l.Qty, NumberStyles.Any, CultureInfo.InvariantCulture, out double q);
                double.TryParse(l.Price, NumberStyles.Any, CultureInfo.InvariantCulture, out double p);
                sub += Math.Round(q * p, 2);
            }
            sub = Math.Round(sub, 2);

            string discText = txtDiscount.Text.Trim();
            if (discText.StartsWith("-"))
            {
                MessageBox.Show("Discount cannot be negative.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }
            double disc = ParseDiscount(discText, sub, out string discNorm);
            if (discText.Trim().EndsWith("%"))
            {
                if (double.TryParse(discText.Trim().TrimEnd('%'), NumberStyles.Any, CultureInfo.InvariantCulture, out double pct) && pct > 100)
                {
                    MessageBox.Show("Discount percentage cannot exceed 100%.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                    return;
                }
            }
            double grand = Math.Round(sub - disc, 2);
            if (grand < 0) grand = 0;
            double paid = 0;
            double.TryParse(txtPaid.Text.Trim(), NumberStyles.Any, CultureInfo.InvariantCulture, out paid);
            paid = Math.Round(paid, 2);
            if (paid < 0)
            {
                MessageBox.Show("Paid amount cannot be negative.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }
            if (paid > grand)
            {
                MessageBox.Show($"Paid amount is more than the grand total. It has been adjusted to ₹ {grand.ToString("N2")}.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                paid = grand;
            }
            double due = Math.Round(grand - paid, 2);
            long paymentMethodId = 0;
            long.TryParse((cmbPayment.SelectedItem as ComboBoxItem)?.Tag as string ?? "", out paymentMethodId);
            if (paid > 0 && paymentMethodId <= 0)
            {
                MessageBox.Show("Please select a payment method for the paid amount.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }

            try
            {
                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();

                long purId = _editId > 0 ? _editId : LocalTxn.NextLocalId(_db, "purchases");
                string date = dpDate.SelectedDate?.ToString("yyyy-MM-dd") ?? LocalTxn.Today();

                if (_editId > 0)
                {
                    using (var old = conn.CreateCommand())
                    {
                        old.CommandText = "SELECT item_id, quantity_amount FROM purchase_details WHERE purchase_id=@id";
                        old.Parameters.AddWithValue("@id", _editId);
                        using var r = old.ExecuteReader();
                        var revert = new List<(long Item, double Qty)>();
                        while (r.Read())
                            revert.Add((r["item_id"] is long iid ? iid : 0,
                                        r["quantity_amount"] is double qd ? qd : 0));
                        r.Close();
                        foreach (var (item, q) in revert)
                        {
                            using var st = conn.CreateCommand();
                            st.CommandText = "UPDATE Master1 SET CurrentStock = MAX(IFNULL(CurrentStock,0) - @q, 0) WHERE ServerId=@item AND MasterType='Item'";
                            st.Parameters.AddWithValue("@q", q);
                            st.Parameters.AddWithValue("@item", item);
                            st.ExecuteNonQuery();
                            using var st2 = conn.CreateCommand();
                            st2.CommandText = "UPDATE items SET stock_quantity = MAX(IFNULL(stock_quantity,0) - @q, 0), SyncStatus='Local' WHERE ServerId=@item";
                            st2.Parameters.AddWithValue("@q", q);
                            st2.Parameters.AddWithValue("@item", item);
                            st2.ExecuteNonQuery();
                        }
                    }
                    using var up = conn.CreateCommand();
                    up.CommandText = @"UPDATE purchases SET invoice_no=@inv, supplier_id=@sup, date=@date, grand_total=@gt, paid=@paid,
                                            due_amount=@due, note=@note, discount=@disc, del_status='Live', SyncStatus='Local'
                                       WHERE Id=@id";
                    up.Parameters.AddWithValue("@id", purId);
                    up.Parameters.AddWithValue("@inv", (object?)txtInvoiceNo.Text.Trim() ?? "");
                    up.Parameters.AddWithValue("@sup", supplierId);
                    up.Parameters.AddWithValue("@date", date);
                    up.Parameters.AddWithValue("@gt", grand);
                    up.Parameters.AddWithValue("@paid", paid);
                    up.Parameters.AddWithValue("@due", due);
                    up.Parameters.AddWithValue("@note", (object?)txtNote.Text.Trim() ?? "");
                    up.Parameters.AddWithValue("@disc", discNorm == "" ? "0" : discNorm);
                    up.ExecuteNonQuery();
                    using var dd = conn.CreateCommand();
                    dd.CommandText = "DELETE FROM purchase_details WHERE purchase_id=@id";
                    dd.Parameters.AddWithValue("@id", purId);
                    dd.ExecuteNonQuery();
                    using var dp = conn.CreateCommand();
                    dp.CommandText = "DELETE FROM purchase_payments WHERE purchase_id=@id";
                    dp.Parameters.AddWithValue("@id", purId);
                    dp.ExecuteNonQuery();
                }
                else
                {
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = @"INSERT INTO purchases (Id, reference_no, invoice_no, supplier_id, date, other, grand_total, paid, due_amount,
                                            note, discount, del_status, status, SyncStatus)
                                        VALUES (@id, @ref, @inv, @sup, @date, 0, @gt, @paid, @due, @note, @disc, 'Live', 'Pending', 'Local')";
                    cmd.Parameters.AddWithValue("@id", purId);
                    cmd.Parameters.AddWithValue("@ref", txtReference.Text.Trim() == "" ? "PUR-" + Math.Abs(purId).ToString("D5") : txtReference.Text.Trim());
                    cmd.Parameters.AddWithValue("@inv", (object?)txtInvoiceNo.Text.Trim() ?? "");
                    cmd.Parameters.AddWithValue("@sup", supplierId);
                    cmd.Parameters.AddWithValue("@date", date);
                    cmd.Parameters.AddWithValue("@gt", grand);
                    cmd.Parameters.AddWithValue("@paid", paid);
                    cmd.Parameters.AddWithValue("@due", due);
                    cmd.Parameters.AddWithValue("@note", (object?)txtNote.Text.Trim() ?? "");
                    cmd.Parameters.AddWithValue("@disc", discNorm == "" ? "0" : discNorm);
                    cmd.ExecuteNonQuery();
                }

                foreach (var l in valid)
                {
                    double.TryParse(l.Qty, NumberStyles.Any, CultureInfo.InvariantCulture, out double q);
                    double.TryParse(l.Price, NumberStyles.Any, CultureInfo.InvariantCulture, out double p);
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = @"INSERT INTO purchase_details (Id, purchase_id, item_id, item_type, unit_price, quantity_amount, total, del_status, SyncStatus)
                                        VALUES (@id, @pid, @item, @itype, @price, @qty, @total, 'Live', 'Local')";
                    cmd.Parameters.AddWithValue("@id", LocalTxn.NextLocalId(_db, "purchase_details"));
                    cmd.Parameters.AddWithValue("@pid", purId);
                    cmd.Parameters.AddWithValue("@item", l.ItemId);
                    cmd.Parameters.AddWithValue("@itype", (object?)(l.Selected?.ItemType ?? ""));
                    cmd.Parameters.AddWithValue("@price", p);
                    cmd.Parameters.AddWithValue("@qty", q);
                    cmd.Parameters.AddWithValue("@total", Math.Round(q * p, 2));
                    cmd.ExecuteNonQuery();

                    using var stock = conn.CreateCommand();
                    stock.CommandText = "UPDATE Master1 SET CurrentStock = IFNULL(CurrentStock,0) + @q WHERE ServerId=@item AND MasterType='Item'";
                    stock.Parameters.AddWithValue("@q", q);
                    stock.Parameters.AddWithValue("@item", l.ItemId);
                    stock.ExecuteNonQuery();

                    using var stock2 = conn.CreateCommand();
                    stock2.CommandText = "UPDATE items SET stock_quantity = IFNULL(stock_quantity,0) + @q, SyncStatus='Local' WHERE ServerId=@item";
                    stock2.Parameters.AddWithValue("@q", q);
                    stock2.Parameters.AddWithValue("@item", l.ItemId);
                    stock2.ExecuteNonQuery();

                    // Update costing prices (mirrors server PurchaseRepository.updateItemsPurchasePrices):
                    // last_purchase_price = this purchase's unit price, last_three_purchase_avg =
                    // average of per-purchase averages for the item's last 3 purchases.
                    using var price = conn.CreateCommand();
                    price.CommandText = @"UPDATE items SET
                        last_purchase_price = @p,
                        last_three_purchase_avg = (
                            SELECT AVG(avg_price) FROM (
                                SELECT purchase_id, AVG(unit_price) AS avg_price
                                FROM purchase_details
                                WHERE item_id=@item AND (del_status IS NULL OR del_status='Live')
                                GROUP BY purchase_id
                                ORDER BY MAX(purchase_id) DESC LIMIT 3
                            )
                        ),
                        SyncStatus='Local'
                        WHERE ServerId=@item";
                    price.Parameters.AddWithValue("@p", p);
                    price.Parameters.AddWithValue("@item", l.ItemId);
                    price.ExecuteNonQuery();
                }

                if (paid > 0 && paymentMethodId > 0)
                {
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = @"INSERT INTO purchase_payments (Id, purchase_id, payment_id, date, amount, del_status, SyncStatus)
                                        VALUES (@id, @pid, @pm, @date, @amt, 'Live', 'Local')";
                    cmd.Parameters.AddWithValue("@id", LocalTxn.NextLocalId(_db, "purchase_payments"));
                    cmd.Parameters.AddWithValue("@pid", purId);
                    cmd.Parameters.AddWithValue("@pm", paymentMethodId);
                    cmd.Parameters.AddWithValue("@date", date);
                    cmd.Parameters.AddWithValue("@amt", paid);
                    cmd.ExecuteNonQuery();
                }

                txn.Commit();
                // Stock update hua — saare subscribed pages (Stock/Low Stock/Inventory) refresh
                Services.StockEvents.NotifyStockChanged();
                lblMsg.Text = _editId > 0 ? "Purchase updated. It will sync to the server automatically."
                                          : "Purchase saved locally. It will sync to the server automatically.";
                _dashboard?.TriggerSync();
                _dashboard?.ShowPage(new PurchaseListPage(_dashboard!));
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }
    }
}
