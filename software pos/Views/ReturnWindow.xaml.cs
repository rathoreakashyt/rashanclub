using System;
using System.Collections.Generic;
using System.Collections.ObjectModel;
using System.Globalization;
using System.Text.Json;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class ReturnWindow : Window
    {
        private readonly DatabaseService _db = new();

        private class BillRow
        {
            public string VchCode { get; set; } = "";
            public string VchNo { get; set; } = "";
            public string VchDate { get; set; } = "";
            public double Amount { get; set; }
            public string PaymentMode { get; set; } = "";
            public long ServerId { get; set; }
        }

        private class ReturnLine
        {
            public long ItemId { get; set; }
            public string Name { get; set; } = "";
            public string Code { get; set; } = "";
            public double SoldQty { get; set; }
            public double ReturnedQty { get; set; }
            public double AvailQty { get; set; }
            public double UnitPrice { get; set; }
            public double Amount => Math.Round(ReturnQty * UnitPrice, 2);

            private double _returnQty;
            public double ReturnQty
            {
                get => _returnQty;
                set => _returnQty = value < 0 ? 0 : value;
            }
        }

        private readonly ObservableCollection<BillRow> _bills = new();
        private readonly ObservableCollection<ReturnLine> _lines = new();
        private BillRow? _current;

        public ReturnWindow()
        {
            InitializeComponent();
            dgBills.ItemsSource = _bills;
            dgItems.ItemsSource = _lines;
        }

        private void Window_Loaded(object sender, RoutedEventArgs e)
        {
            LoadBills("");
            txtSearch.Focus();
            Keyboard.Focus(txtSearch);
        }

        // ═══════════ 1. BILL SEARCH ═══════════

        private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e)
        {
            LoadBills(txtSearch.Text.Trim());
        }

        private void TxtSearch_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Down && _bills.Count > 0)
            {
                dgBills.SelectedIndex = 0;
                dgBills.Focus();
                e.Handled = true;
            }
            else if (e.Key == Key.Enter && _bills.Count > 0)
            {
                SelectBill(dgBills.SelectedItem as BillRow);
                e.Handled = true;
            }
        }

        private void DgBills_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter)
            {
                SelectBill(dgBills.SelectedItem as BillRow);
                e.Handled = true;
            }
            else if (e.Key == Key.Escape)
            {
                txtSearch.Focus();
                txtSearch.SelectAll();
                e.Handled = true;
            }
        }

        private void DgBills_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (dgBills.SelectedItem is BillRow bill && !ReferenceEquals(bill, _current))
            {
                LoadBillItems(bill);
            }
        }

        private void LoadBills(string filter)
        {
            _bills.Clear();
            lblBillCount.Text = string.IsNullOrWhiteSpace(filter)
                ? "Latest bill auto-selected — type karo, live search hoga..."
                : $"'{filter}' se milne wale bills...";
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                if (string.IsNullOrWhiteSpace(filter))
                {
                    cmd.CommandText = @"SELECT VchCode, VchNo, VchDate, Amount, PaymentMode, ServerId
                                        FROM Tran1
                                        WHERE VchType='Sales' AND ServerId IS NOT NULL AND ServerId > 0
                                          AND (SyncStatus IS NULL OR SyncStatus != 'PushError')
                                        ORDER BY CreatedAt DESC LIMIT 60";
                }
                else
                {
                    cmd.CommandText = @"SELECT VchCode, VchNo, VchDate, Amount, PaymentMode, ServerId
                                        FROM Tran1
                                        WHERE VchType='Sales' AND ServerId IS NOT NULL AND ServerId > 0
                                          AND (SyncStatus IS NULL OR SyncStatus != 'PushError')
                                          AND (VchNo LIKE @f OR VchCode LIKE @f)
                                        ORDER BY CreatedAt DESC LIMIT 60";
                    cmd.Parameters.AddWithValue("@f", "%" + filter + "%");
                }
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    _bills.Add(new BillRow
                    {
                        VchCode = r["VchCode"]?.ToString() ?? "",
                        VchNo = r["VchNo"]?.ToString() ?? "",
                        VchDate = r["VchDate"]?.ToString() ?? "",
                        Amount = Convert.ToDouble(r["Amount"] ?? 0),
                        PaymentMode = r["PaymentMode"]?.ToString() ?? "",
                        ServerId = Convert.ToInt64(r["ServerId"] ?? 0)
                    });
                }

                // Default: latest (first) bill auto-select karo — products turant dikhen
                if (_bills.Count > 0 && !ReferenceEquals(dgBills.SelectedItem, _bills[0]))
                {
                    _current = null;
                    dgBills.SelectedIndex = 0;
                    dgBills.SelectedItem = _bills[0];
                }
                else if (_bills.Count == 0)
                {
                    _current = null;
                    _lines.Clear();
                    lblBillInfo.Text = "Koi bill nahi mila...";
                    UpdateTotal();
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show("Bills load error: " + ex.Message, "Return", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void SelectBill(BillRow? bill)
        {
            if (bill == null && _bills.Count > 0) bill = _bills[0];
            if (bill == null) return;
            if (!ReferenceEquals(bill, _current))
            {
                dgBills.SelectedItem = bill;
                LoadBillItems(bill);
            }
            txtCustName.Focus();
            txtCustName.SelectAll();
        }

        // ═══════════ 2. RETURNING PERSON ═══════════

        private void CustField_KeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key != Key.Enter) return;
            e.Handled = true;
            if (ReferenceEquals(sender, txtCustName)) { txtCustPhone.Focus(); txtCustPhone.SelectAll(); }
            else if (ReferenceEquals(sender, txtCustPhone)) { txtCustEmail.Focus(); txtCustEmail.SelectAll(); }
            else { FocusFirstQty(); }
        }

        // ═══════════ 3. ITEMS ═══════════

        private void MaxAll_Click(object sender, RoutedEventArgs e)
        {
            foreach (var line in _lines) line.ReturnQty = line.AvailQty;
            UpdateTotal();
            FocusFirstQty();
        }

        private void FocusFirstQty()
        {
            if (_lines.Count == 0) return;
            dgItems.SelectedIndex = 0;
            dgItems.ScrollIntoView(dgItems.Items[0]);
            dgItems.CurrentCell = new DataGridCellInfo(dgItems.Items[0], colQty);
            dgItems.Focus();
            dgItems.BeginEdit();
        }

        private void QtyBox_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter)
            {
                e.Handled = true;
                dgItems.CommitEdit(DataGridEditingUnit.Row, true);
                int idx = dgItems.Items.IndexOf(dgItems.SelectedItem);
                if (idx >= 0 && idx < _lines.Count - 1)
                {
                    dgItems.SelectedIndex = idx + 1;
                    dgItems.ScrollIntoView(dgItems.Items[idx + 1]);
                    dgItems.CurrentCell = new DataGridCellInfo(dgItems.Items[idx + 1], colQty);
                    dgItems.BeginEdit();
                }
                else
                {
                    txtNote.Focus();
                    txtNote.SelectAll();
                }
            }
            else if (e.Key == Key.Escape)
            {
                dgItems.CancelEdit(DataGridEditingUnit.Row);
                e.Handled = true;
            }
        }

        private void Qty_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (sender is TextBox tb && tb.DataContext is ReturnLine line)
            {
                line.ReturnQty = double.TryParse(tb.Text, NumberStyles.Any, CultureInfo.InvariantCulture, out var qty) ? qty : 0;
                UpdateTotal();
            }
        }

        private void UpdateTotal()
        {
            double total = 0;
            foreach (var line in _lines) total += line.Amount;
            lblTotal.Text = $"Refund Total: Rs.{total:N2}";
            dgItems.Items.Refresh();
        }

        // ═══════════ SAVE ═══════════

        private void Note_KeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter)
            {
                e.Handled = true;
                SaveReturn();
            }
        }

        private void Window_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key != Key.Escape) return;
            var focused = Keyboard.FocusedElement as DependencyObject;
            if (focused != null && IsInside(focused, dgItems))
            {
                // cancel current edit, window band rahega
                return;
            }
            DialogResult = true;
            Close();
            e.Handled = true;
        }

        private static bool IsInside(DependencyObject child, DependencyObject root)
        {
            var cur = child;
            while (cur != null)
            {
                if (ReferenceEquals(cur, root)) return true;
                cur = VisualTreeHelper.GetParent(cur);
            }
            return false;
        }

        private void Save_Click(object sender, RoutedEventArgs e)
        {
            SaveReturn();
        }

        private void SaveReturn()
        {
            if (_current == null)
            {
                MessageBox.Show("Pehle bill select karein.", "Return", MessageBoxButton.OK, MessageBoxImage.Warning);
                txtSearch.Focus();
                return;
            }

            var validLines = new List<ReturnLine>();
            foreach (var line in _lines)
            {
                if (line.ReturnQty <= 0) continue;
                if (line.ReturnQty > line.AvailQty + 0.0001)
                {
                    MessageBox.Show($"{line.Name}: return qty ({line.ReturnQty:N2}) available ({line.AvailQty:N2}) se zyada hai!",
                                    "Return", MessageBoxButton.OK, MessageBoxImage.Warning);
                    return;
                }
                validLines.Add(line);
            }

            if (validLines.Count == 0)
            {
                MessageBox.Show("Kisi item ki return qty 0 se zyada karein.", "Return", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }

            var name = txtCustName.Text.Trim();
            var phone = txtCustPhone.Text.Trim();
            var email = txtCustEmail.Text.Trim();

            long? customerId = ResolveCustomerId(name, phone, email);
            var note = txtNote.Text.Trim();
            if (customerId == null && (name.Length > 0 || phone.Length > 0 || email.Length > 0))
            {
                var by = "Return by: " + (name.Length > 0 ? name : "(naam nahi)") +
                         (phone.Length > 0 ? " " + phone : "") +
                         (email.Length > 0 ? " " + email : "");
                note = note.Length > 0 ? by + " | " + note : by;
            }

            try
            {
                double total = 0;
                foreach (var l in validLines) total += l.Amount;

                string date = DateTime.Now.ToString("yyyy-MM-dd");
                string refNo = GenerateRefNo();
                string now = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");

                var items = new List<object>();
                foreach (var l in validLines)
                {
                    items.Add(new Dictionary<string, object?>
                    {
                        ["item_id"] = l.ItemId,
                        ["sale_quantity_amount"] = l.SoldQty,
                        ["return_quantity_amount"] = l.ReturnQty,
                        ["unit_price_in_sale"] = l.UnitPrice,
                        ["unit_price_in_return"] = l.UnitPrice
                    });
                }

                var payload = new Dictionary<string, object?>
                {
                    ["reference_no"] = refNo,
                    ["sale_id"] = _current.ServerId,
                    ["customer_id"] = customerId,
                    ["date"] = date,
                    ["total_return_amount"] = Math.Round(total, 2),
                    ["paid"] = Math.Round(total, 2),
                    ["due"] = 0,
                    ["payment_method_id"] = 1,
                    ["note"] = note.Length > 0 ? note : null,
                    ["items"] = items
                };

                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();

                long returnId;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.Transaction = txn;
                    // Explicit negative Id (LocalTxn.NextLocalId) — prevents collision with server-assigned positive IDs during pull sync
                    cmd.CommandText = @"INSERT INTO sale_returns (Id, reference_no, sale_id, customer_id, date, total_return_amount, paid, due, payment_method_id, note, user_id, outlet_id, company_id, del_status, created_at, updated_at, SyncStatus)
                        VALUES (@id, @ref, @sale, @cust, @date, @total, @paid, @due, 1, @note, 1, 1, 1, 'Live', @now, @now, 'Local')";
                    cmd.Parameters.AddWithValue("@id", LocalTxn.NextLocalId(_db, "sale_returns"));
                    cmd.Parameters.AddWithValue("@ref", refNo);
                    cmd.Parameters.AddWithValue("@sale", _current.ServerId);
                    cmd.Parameters.AddWithValue("@cust", customerId ?? (object)DBNull.Value);
                    cmd.Parameters.AddWithValue("@date", date);
                    cmd.Parameters.AddWithValue("@total", Math.Round(total, 2));
                    cmd.Parameters.AddWithValue("@paid", Math.Round(total, 2));
                    cmd.Parameters.AddWithValue("@due", 0);
                    cmd.Parameters.AddWithValue("@note", note.Length > 0 ? note : DBNull.Value);
                    cmd.Parameters.AddWithValue("@now", now);
                    cmd.ExecuteNonQuery();
                }

                using (var cmd = conn.CreateCommand())
                {
                    cmd.Transaction = txn;
                    cmd.CommandText = "SELECT last_insert_rowid()";
                    returnId = Convert.ToInt64(cmd.ExecuteScalar());
                }

                payload["local_id"] = returnId;
                var payloadJson = JsonSerializer.Serialize(payload);

                using (var cmd = conn.CreateCommand())
                {
                    cmd.Transaction = txn;
                    cmd.CommandText = "UPDATE sale_returns SET SyncPayload=@p WHERE id=@id";
                    cmd.Parameters.AddWithValue("@p", payloadJson);
                    cmd.Parameters.AddWithValue("@id", returnId);
                    cmd.ExecuteNonQuery();
                }

                foreach (var l in validLines)
                {
                    using var cmd = conn.CreateCommand();
                    cmd.Transaction = txn;
                    cmd.CommandText = @"INSERT INTO sale_return_details (sale_return_id, sale_id, item_id, sale_quantity_amount, return_quantity_amount, unit_price_in_sale, unit_price_in_return, user_id, outlet_id, company_id, del_status, SyncStatus, created_at, updated_at)
                        VALUES (@rid, @sale, @item, @sq, @rq, @ps, @pr, 1, 1, 1, 'Live', 'Local', @now, @now)";
                    cmd.Parameters.AddWithValue("@rid", returnId);
                    cmd.Parameters.AddWithValue("@sale", _current.ServerId);
                    cmd.Parameters.AddWithValue("@item", l.ItemId);
                    cmd.Parameters.AddWithValue("@sq", l.SoldQty);
                    cmd.Parameters.AddWithValue("@rq", l.ReturnQty);
                    cmd.Parameters.AddWithValue("@ps", l.UnitPrice);
                    cmd.Parameters.AddWithValue("@pr", l.UnitPrice);
                    cmd.Parameters.AddWithValue("@now", now);
                    cmd.ExecuteNonQuery();
                }

                txn.Commit();

                MessageBox.Show($"Return saved: {refNo}\nRefund: Rs.{total:N2}\nBill: {_current.VchNo}\n\nAuto-sync se cloud pe push hoga (~20-40 sec).",
                                "Return", MessageBoxButton.OK, MessageBoxImage.Information);
                DialogResult = true;
                Close();
            }
            catch (Exception ex)
            {
                MessageBox.Show("Return save error: " + ex.Message, "Return", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        /// <summary>
        /// Sale ki customer id (cloud id) wapas karo agar naam/phone/email bilkul match kare.
        /// Naye (unsynced) customer ke liye null — note me details chale jati hain,
        /// cloud sale ke apne customer par fallback karega.
        /// </summary>
        private long? ResolveCustomerId(string name, string phone, string email)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                var clauses = new List<string>();
                var ps = new List<(string n, object v)>();

                if (!string.IsNullOrWhiteSpace(phone))
                {
                    var norm = phone.Replace(" ", "").Replace("+91", "").TrimStart('0');
                    if (norm.Length >= 6)
                    {
                        clauses.Add("REPLACE(REPLACE(COALESCE(c.phone,''),' ',''),'+91','') LIKE @p");
                        ps.Add(("@p", "%" + norm));
                    }
                }
                if (string.IsNullOrWhiteSpace(phone) && !string.IsNullOrWhiteSpace(name))
                {
                    clauses.Add("LOWER(TRIM(COALESCE(c.name,''))) = LOWER(@n)");
                    ps.Add(("@n", name.Trim()));
                }
                if (clauses.Count == 0) return null;

                cmd.CommandText = @"SELECT c.id
                    FROM customers c
                    JOIN Master1 m ON m.ServerId = c.id AND m.MasterType='Party'
                                     AND m.PartyType IN ('Customer','Both') AND m.SyncStatus='Synced'
                    WHERE (c.del_status IS NULL OR c.del_status='Live') AND (" + string.Join(" OR ", clauses) + @")
                    ORDER BY c.id LIMIT 1";
                foreach (var (n, v) in ps) cmd.Parameters.AddWithValue(n, v);
                var val = cmd.ExecuteScalar();
                if (val != null && long.TryParse(val.ToString(), out long id) && id > 0) return id;
            }
            catch { }
            return null;
        }

        private string GenerateRefNo()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT COUNT(*) FROM sale_returns";
                long count = Convert.ToInt64(cmd.ExecuteScalar());
                return "SR-" + DateTime.Now.Year + "-" + (count + 1).ToString("00000");
            }
            catch
            {
                return "SR-" + DateTime.Now.Year + "-00001";
            }
        }

        // ═══════════ LOAD HELPERS ═══════════

        private void LoadBillItems(BillRow bill)
        {
            _current = bill;
            _lines.Clear();
            lblBillInfo.Text = $"{bill.VchNo}   |   {bill.VchDate}   |   Rs.{bill.Amount:N2}   |   {bill.PaymentMode}";
            SetCustomerFromSale(bill.VchCode);

            var returned = new Dictionary<long, double>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT srd.item_id, SUM(srd.return_quantity_amount)
                                    FROM sale_return_details srd
                                    JOIN sale_returns sr ON sr.id = srd.sale_return_id
                                    WHERE srd.sale_id = @sid AND (sr.del_status IS NULL OR sr.del_status='Live')
                                    GROUP BY srd.item_id";
                cmd.Parameters.AddWithValue("@sid", bill.ServerId);
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    long itemId = Convert.ToInt64(r[0] ?? 0);
                    double qty = Convert.ToDouble(r[1] ?? 0);
                    if (itemId > 0) returned[itemId] = qty;
                }
            }
            catch { }

            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT t2.MasterCode1, t2.Description, t2.Quantity, t2.Rate, i.id
                                    FROM Tran2 t2
                                    LEFT JOIN items i ON i.code = t2.MasterCode1
                                    WHERE t2.VchCode = @vch AND t2.Quantity > 0
                                    ORDER BY t2.SrNo";
                cmd.Parameters.AddWithValue("@vch", bill.VchCode);
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    string code = r["MasterCode1"]?.ToString() ?? "";
                    string name = r["Description"]?.ToString() ?? code;
                    double qty = Convert.ToDouble(r["Quantity"] ?? 0);
                    double rate = Convert.ToDouble(r["Rate"] ?? 0);
                    long itemId = r["id"] is DBNull ? 0 : Convert.ToInt64(r["id"]);

                    if (itemId == 0)
                    {
                        MessageBox.Show($"Item '{code}' cloud DB me nahi mila - return nahi ho sakta.\n(Pahle item sync hona chahiye)",
                                        "Return", MessageBoxButton.OK, MessageBoxImage.Warning);
                        continue;
                    }

                    double returnedQty = returned.TryGetValue(itemId, out var rq) ? rq : 0;
                    double avail = Math.Max(0, qty - returnedQty);
                    _lines.Add(new ReturnLine
                    {
                        ItemId = itemId,
                        Name = name,
                        Code = code,
                        SoldQty = qty,
                        ReturnedQty = returnedQty,
                        AvailQty = avail,
                        UnitPrice = rate
                    });
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show("Items load error: " + ex.Message, "Return", MessageBoxButton.OK, MessageBoxImage.Error);
            }
            UpdateTotal();
        }

        private void SetCustomerFromSale(string vchCode)
        {
            txtCustName.Text = txtCustPhone.Text = txtCustEmail.Text = "";
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT SyncPayload FROM Tran1 WHERE VchCode=@c";
                cmd.Parameters.AddWithValue("@c", vchCode);
                var payload = cmd.ExecuteScalar() as string;
                if (string.IsNullOrWhiteSpace(payload)) return;

                using var doc = JsonDocument.Parse(payload);
                if (!doc.RootElement.TryGetProperty("customer_id", out var cid) || cid.ValueKind != JsonValueKind.Number || cid.GetInt64() <= 0)
                    return;

                using var c = conn.CreateCommand();
                c.CommandText = @"SELECT name, phone, email FROM customers WHERE id=@cid AND (del_status IS NULL OR del_status='Live') LIMIT 1";
                c.Parameters.AddWithValue("@cid", cid.GetInt64());
                using var r = c.ExecuteReader();
                if (r.Read())
                {
                    txtCustName.Text = r["name"]?.ToString() ?? "";
                    txtCustPhone.Text = r["phone"]?.ToString() ?? "";
                    txtCustEmail.Text = r["email"]?.ToString() ?? "";
                }
            }
            catch { }
        }
    }
}
