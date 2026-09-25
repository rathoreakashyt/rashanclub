using System;
using System.Collections.ObjectModel;
using System.Globalization;
using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class PurchaseReturnCreatePage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new DatabaseService();
        private readonly ObservableCollection<PurchaseLineVM> _lines = new();
        private long _editId;

        public PurchaseReturnCreatePage() { InitializeComponent(); }
        public PurchaseReturnCreatePage(MainDashboard dashboard) : this() { _dashboard = dashboard; LoadLookups(); }
        public PurchaseReturnCreatePage(MainDashboard dashboard, long returnId) : this()
        {
            _dashboard = dashboard;
            LoadLookups();
            LoadForEdit(returnId);
        }

        private void LoadForEdit(long id)
        {
            try
            {
                _editId = id;
                lblTitle.Text = "Edit Purchase Return";
                lblSubtitle.Text = "Update the selected purchase return";
                using var conn = _db.GetConnection();
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"SELECT reference_no, pur_ref_no, supplier_id, date, purchase_date, note, return_status, payment_method_id
                                        FROM purchase_returns WHERE Id=@id";
                    cmd.Parameters.AddWithValue("@id", id);
                    using var r = cmd.ExecuteReader();
                    if (!r.Read()) return;
                    txtReference.Text = r["reference_no"]?.ToString() ?? "";
                    txtPurRef.Text = r["pur_ref_no"]?.ToString() ?? "";
                    txtNote.Text = r["note"]?.ToString() ?? "";
                    if (DateTime.TryParse(r["date"]?.ToString(), out DateTime dt)) dpDate.SelectedDate = dt;
                    if (DateTime.TryParse(r["purchase_date"]?.ToString(), out DateTime pd)) dpPurchaseDate.SelectedDate = pd;
                    SelectByTag(cmbSupplier, r["supplier_id"] is long sid ? sid.ToString() : "");
                    SelectByTag(cmbPayment, r["payment_method_id"] is long pm ? pm.ToString() : "");
                    SelectByTag(cmbStatus, r["return_status"]?.ToString() ?? "draft");
                }

                var lines = new List<(long ItemId, string Qty, string Price)>();
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT item_id, return_quantity_amount, unit_price FROM purchase_return_details WHERE pur_return_id=@id";
                    cmd.Parameters.AddWithValue("@id", id);
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                        lines.Add((r["item_id"] is long iid ? iid : 0,
                                   (r["return_quantity_amount"] is double q ? q : 0).ToString(CultureInfo.InvariantCulture),
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
                RecalcTotal();
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

                foreach (var st in new[]
                {
                    ("draft", "Draft"),
                    ("taken_by_sup_pro_not_returned", "Taken By Supplier (Product Not Returned)"),
                    ("taken_by_sup_money_returned", "Taken By Supplier (Money Returned)"),
                    ("taken_by_sup_pro_returned", "Taken By Supplier (Product Returned)")
                })
                    cmbStatus.Items.Add(new ComboBoxItem { Content = st.Item2, Tag = st.Item1 });
                cmbStatus.SelectedIndex = 0;
            }
            catch { }

            dpDate.SelectedDate = DateTime.Today;
            dpPurchaseDate.SelectedDate = DateTime.Today;
            txtReference.Text = LocalTxn.NextReference(_db, "purchase_returns", "PRET-" + DateTime.Now.Year + "-");
            AddLine();
        }

        private void AddLine()
        {
            var line = new PurchaseLineVM();
            using (var conn = _db.GetConnection())
            {
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT ServerId, Code, Name FROM Master1 WHERE MasterType='Item' AND IsActive=1 AND Name<>'' ORDER BY Name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    long id = r["ServerId"] is long s ? s : 0;
                    line.Items.Add(new PurchaseItemVM
                    {
                        Id = id,
                        Code = r["Code"]?.ToString() ?? "",
                        Name = (r["Name"]?.ToString() ?? "") + (id > 0 ? " [" + r["Code"] + "]" : "")
                    });
                }
            }
            _lines.Add(line);
            itemsPanel.ItemsSource = _lines;
            RecalcTotal();
        }

        private void Item_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (sender is ComboBox cb && cb.SelectedItem is PurchaseItemVM it && cb.Tag is PurchaseLineVM line)
            {
                line.ItemId = it.Id;
                line.Selected = it;
            }
        }

        private void Qty_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (sender is TextBox tb && tb.Tag is PurchaseLineVM line) { line.Recalc(); RecalcTotal(); }
        }

        private void Price_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (sender is TextBox tb && tb.Tag is PurchaseLineVM line) { line.Recalc(); RecalcTotal(); }
        }

        private void BtnAddItem_Click(object sender, RoutedEventArgs e) => AddLine();

        private void BtnRemoveItem_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is PurchaseLineVM line) { _lines.Remove(line); RecalcTotal(); }
        }

        private void RecalcTotal()
        {
            double sum = 0;
            foreach (var l in _lines)
            {
                double.TryParse(l.Qty, NumberStyles.Any, CultureInfo.InvariantCulture, out double q);
                double.TryParse(l.Price, NumberStyles.Any, CultureInfo.InvariantCulture, out double p);
                sum += q * p;
            }
            if (lblTotal != null) lblTotal.Text = sum.ToString("N2");
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowPage(new PurchaseReturnListPage(_dashboard!));
        }

        private void BtnSave_Click(object sender, RoutedEventArgs e)
        {
            if (cmbSupplier.SelectedIndex < 0 || !long.TryParse((cmbSupplier.SelectedItem as ComboBoxItem)?.Tag as string ?? "", out long supplierId))
            {
                MessageBox.Show("Please select a supplier.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }
            var valid = new System.Collections.Generic.List<PurchaseLineVM>();
            foreach (var l in _lines)
            {
                double.TryParse(l.Qty, NumberStyles.Any, CultureInfo.InvariantCulture, out double q);
                double.TryParse(l.Price, NumberStyles.Any, CultureInfo.InvariantCulture, out double p);
                if (l.ItemId > 0 && q > 0)
                {
                    l.Qty = q.ToString(CultureInfo.InvariantCulture);
                    l.Price = p.ToString(CultureInfo.InvariantCulture);
                    valid.Add(l);
                }
            }
            if (valid.Count == 0)
            {
                MessageBox.Show("Please add at least one item with quantity.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }

            double total = 0;
            foreach (var l in valid)
            {
                double.TryParse(l.Qty, NumberStyles.Any, CultureInfo.InvariantCulture, out double q);
                double.TryParse(l.Price, NumberStyles.Any, CultureInfo.InvariantCulture, out double p);
                total += q * p;
            }
            long paymentMethodId = 0;
            long.TryParse((cmbPayment.SelectedItem as ComboBoxItem)?.Tag as string ?? "", out paymentMethodId);
            string status = (cmbStatus.SelectedItem as ComboBoxItem)?.Tag as string ?? "draft";

            try
            {
                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();

                long retId = _editId > 0 ? _editId : LocalTxn.NextLocalId(_db, "purchase_returns");
                string date = dpDate.SelectedDate?.ToString("yyyy-MM-dd") ?? LocalTxn.Today();
                string purDate = dpPurchaseDate.SelectedDate?.ToString("yyyy-MM-dd") ?? "";

                if (_editId > 0)
                {
                    using (var old = conn.CreateCommand())
                    {
                        old.CommandText = "SELECT item_id, return_quantity_amount FROM purchase_return_details WHERE pur_return_id=@id";
                        old.Parameters.AddWithValue("@id", _editId);
                        using var r = old.ExecuteReader();
                        var revert = new List<(long Item, double Qty)>();
                        while (r.Read())
                            revert.Add((r["item_id"] is long iid ? iid : 0,
                                        r["return_quantity_amount"] is double qd ? qd : 0));
                        r.Close();
                        foreach (var (item, q) in revert)
                        {
                            using var st = conn.CreateCommand();
                            st.CommandText = "UPDATE Master1 SET CurrentStock = IFNULL(CurrentStock,0) + @q WHERE ServerId=@item AND MasterType='Item'";
                            st.Parameters.AddWithValue("@q", q);
                            st.Parameters.AddWithValue("@item", item);
                            st.ExecuteNonQuery();
                            using var st2 = conn.CreateCommand();
                            st2.CommandText = "UPDATE items SET stock_quantity = IFNULL(stock_quantity,0) + @q, SyncStatus='Local' WHERE ServerId=@item";
                            st2.Parameters.AddWithValue("@q", q);
                            st2.Parameters.AddWithValue("@item", item);
                            st2.ExecuteNonQuery();
                        }
                    }
                    using var up = conn.CreateCommand();
                    up.CommandText = @"UPDATE purchase_returns SET pur_ref_no=@purref, supplier_id=@sup, date=@date, purchase_date=@purdate,
                                            return_status=@status, total_return_amount=@amt, payment_method_id=@pm, note=@note,
                                            del_status='Live', SyncStatus='Local'
                                       WHERE Id=@id";
                    up.Parameters.AddWithValue("@id", retId);
                    up.Parameters.AddWithValue("@purref", (object?)txtPurRef.Text.Trim() ?? "");
                    up.Parameters.AddWithValue("@sup", supplierId);
                    up.Parameters.AddWithValue("@date", date);
                    up.Parameters.AddWithValue("@purdate", purDate);
                    up.Parameters.AddWithValue("@status", status);
                    up.Parameters.AddWithValue("@amt", total);
                    up.Parameters.AddWithValue("@pm", status == "taken_by_sup_money_returned" && paymentMethodId > 0 ? paymentMethodId : (object)DBNull.Value);
                    up.Parameters.AddWithValue("@note", (object?)txtNote.Text.Trim() ?? "");
                    up.ExecuteNonQuery();
                    using var dd = conn.CreateCommand();
                    dd.CommandText = "DELETE FROM purchase_return_details WHERE pur_return_id=@id";
                    dd.Parameters.AddWithValue("@id", retId);
                    dd.ExecuteNonQuery();
                }
                else
                {
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = @"INSERT INTO purchase_returns (Id, reference_no, pur_ref_no, supplier_id, date, purchase_date,
                                            return_status, total_return_amount, payment_method_id, note, del_status, SyncStatus)
                                        VALUES (@id, @ref, @purref, @sup, @date, @purdate, @status, @amt, @pm, @note, 'Live', 'Local')";
                    cmd.Parameters.AddWithValue("@id", retId);
                    cmd.Parameters.AddWithValue("@ref", txtReference.Text.Trim() == "" ? "PRET-" + DateTime.Now.Year + "-" + Math.Abs(retId).ToString("D5") : txtReference.Text.Trim());
                    cmd.Parameters.AddWithValue("@purref", (object?)txtPurRef.Text.Trim() ?? "");
                    cmd.Parameters.AddWithValue("@sup", supplierId);
                    cmd.Parameters.AddWithValue("@date", date);
                    cmd.Parameters.AddWithValue("@purdate", purDate);
                    cmd.Parameters.AddWithValue("@status", status);
                    cmd.Parameters.AddWithValue("@amt", total);
                    cmd.Parameters.AddWithValue("@pm", status == "taken_by_sup_money_returned" && paymentMethodId > 0 ? paymentMethodId : (object)DBNull.Value);
                    cmd.Parameters.AddWithValue("@note", (object?)txtNote.Text.Trim() ?? "");
                    cmd.ExecuteNonQuery();
                }

                foreach (var l in valid)
                {
                    double.TryParse(l.Qty, NumberStyles.Any, CultureInfo.InvariantCulture, out double q);
                    double.TryParse(l.Price, NumberStyles.Any, CultureInfo.InvariantCulture, out double p);
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = @"INSERT INTO purchase_return_details (Id, pur_return_id, item_id, return_quantity_amount, unit_price, total,
                                            return_status, del_status, SyncStatus)
                                        VALUES (@id, @rid, @item, @qty, @price, @total, @status, 'Live', 'Local')";
                    cmd.Parameters.AddWithValue("@id", LocalTxn.NextLocalId(_db, "purchase_return_details"));
                    cmd.Parameters.AddWithValue("@rid", retId);
                    cmd.Parameters.AddWithValue("@item", l.ItemId);
                    cmd.Parameters.AddWithValue("@qty", q);
                    cmd.Parameters.AddWithValue("@price", p);
                    cmd.Parameters.AddWithValue("@total", Math.Round(q * p, 2));
                    cmd.Parameters.AddWithValue("@status", status);
                    cmd.ExecuteNonQuery();

                    using var stock = conn.CreateCommand();
                    stock.CommandText = "UPDATE Master1 SET CurrentStock = MAX(IFNULL(CurrentStock,0) - @q, 0) WHERE ServerId=@item AND MasterType='Item'";
                    stock.Parameters.AddWithValue("@q", q);
                    stock.Parameters.AddWithValue("@item", l.ItemId);
                    stock.ExecuteNonQuery();

                    using var stock2 = conn.CreateCommand();
                    stock2.CommandText = "UPDATE items SET stock_quantity = MAX(IFNULL(stock_quantity,0) - @q, 0), SyncStatus='Local' WHERE ServerId=@item";
                    stock2.Parameters.AddWithValue("@q", q);
                    stock2.Parameters.AddWithValue("@item", l.ItemId);
                    stock2.ExecuteNonQuery();
                }

                txn.Commit();
                // Stock update hua — saare subscribed pages (Stock/Low Stock/Inventory) refresh
                Services.StockEvents.NotifyStockChanged();
                lblMsg.Text = _editId > 0 ? "Purchase return updated. It will sync to the server automatically."
                                          : "Purchase return saved locally. It will sync to the server automatically.";
                _dashboard?.TriggerSync();
                _dashboard?.ShowPage(new PurchaseReturnListPage(_dashboard!));
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }
    }
}
