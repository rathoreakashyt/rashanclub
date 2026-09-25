using System;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class CustomerWindow : Window
    {
        private readonly DatabaseService _db = new();
        private CustomerInfo? _existing;
        private FrameworkElement[] _fields = Array.Empty<FrameworkElement>();

        public CustomerInfo? SelectedCustomer { get; private set; }

        public CustomerWindow()
        {
            InitializeComponent();
            Loaded += (_, _) =>
            {
                LoadStates();
                _fields = new FrameworkElement[] { txtMobile, txtName, txtEmail, txtAddress, cmbState };
                txtMobile.Focus();
                Keyboard.Focus(txtMobile);
            };
        }

        private void LoadStates()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT Id, state_name FROM states WHERE state_name IS NOT NULL AND state_name != '' ORDER BY state_name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    cmbState.Items.Add(new ComboBoxItem
                    {
                        Content = r["state_name"]?.ToString() ?? "",
                        Tag = r["Id"]
                    });
                }
                if (cmbState.Items.Count > 0)
                {
                    cmbState.SelectedIndex = 0;
                    foreach (var item in cmbState.Items.OfType<ComboBoxItem>())
                    {
                        if (string.Equals(item.Content?.ToString(), "Delhi", StringComparison.OrdinalIgnoreCase))
                        {
                            cmbState.SelectedItem = item;
                            break;
                        }
                    }
                }
            }
            catch (Exception ex)
            {
                System.Diagnostics.Debug.WriteLine("LoadStates ERR: " + ex.Message);
            }
        }

        private void SelectState(object? stateId)
        {
            if (stateId == null) return;
            var sid = Convert.ToInt64(stateId);
            foreach (var item in cmbState.Items.OfType<ComboBoxItem>())
            {
                if ((item.Tag is long t && t == sid) || (item.Tag is int ti && ti == sid))
                {
                    cmbState.SelectedItem = item;
                    return;
                }
            }
        }

        private void TxtMobile_PreviewTextInput(object sender, TextCompositionEventArgs e)
        {
            foreach (var c in e.Text)
            {
                if (!char.IsDigit(c))
                {
                    e.Handled = true;
                    return;
                }
            }
        }

        private void TxtMobile_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (txtMobile.Text.Trim().Length >= 10)
                LookupCustomer();
            else if (_existing != null)
                ClearFound();
        }

        private void LookupCustomer()
        {
            var phone = txtMobile.Text.Trim();
            if (string.IsNullOrEmpty(phone)) return;
            try
            {
                using var conn = _db.GetConnection();

                // Step 1: customers table se lookup
                string cName = "", cEmail = "", cPhone = "", cAddr = "";
                double cLoyalty = 0, cWallet = 0;
                long cId = 0;
                object? cStateId = null;
                bool found = false;

                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"SELECT c.id, c.name, c.email, c.phone, c.address, c.state_id,
                            COALESCE(c.loyalty_point,0),
                            (SELECT COALESCE(balance,0) FROM customer_wallets WHERE customer_id=c.id LIMIT 1)
                        FROM customers c
                        WHERE REPLACE(REPLACE(COALESCE(c.phone,''),' ',''),'+91','') = @p
                           OR REPLACE(COALESCE(c.phone,''),' ','') = @p
                        LIMIT 1";
                    cmd.Parameters.AddWithValue("@p", phone);
                    using var r = cmd.ExecuteReader();
                    if (r.Read())
                    {
                        found = true;
                        cId = r.IsDBNull(0) ? 0L : r.GetInt64(0);
                        cName = r.IsDBNull(1) ? "" : r.GetString(1);
                        cEmail = r.IsDBNull(2) ? "" : r.GetString(2);
                        cPhone = r.IsDBNull(3) ? "" : r.GetString(3);
                        cAddr = r.IsDBNull(4) ? "" : r.GetString(4);
                        cStateId = r.IsDBNull(5) ? null : r.GetValue(5);
                        cLoyalty = r.IsDBNull(6) ? 0.0 : r.GetDouble(6);
                        cWallet = r.IsDBNull(7) ? 0.0 : r.GetDouble(7);
                    }
                }

                // Step 2: customers table mein nahi mila — Master1 se dekho
                if (!found)
                {
                    using var cmd2 = conn.CreateCommand();
                    cmd2.CommandText = @"SELECT Code, Name, Phone, Email, Address1
                        FROM Master1
                        WHERE MasterType='Party' AND PartyType IN ('Customer','Both')
                          AND (DelStatus IS NULL OR DelStatus != 'Deleted')
                          AND (REPLACE(REPLACE(COALESCE(Phone,''),' ',''),'+91','') = @p2
                               OR REPLACE(COALESCE(Phone,''),' ','') = @p2)
                        LIMIT 1";
                    cmd2.Parameters.AddWithValue("@p2", phone);
                    using var r2 = cmd2.ExecuteReader();
                    if (r2.Read())
                    {
                        found = true;
                        cId = 0;
                        cName = r2.IsDBNull(1) ? "" : r2.GetString(1);
                        cPhone = r2.IsDBNull(2) ? "" : r2.GetString(2);
                        cEmail = r2.IsDBNull(3) ? "" : r2.GetString(3);
                        cAddr = r2.IsDBNull(4) ? "" : r2.GetString(4);
                    }
                }

                // Step 3: Result handle karo
                if (found)
                {
                    _existing = new CustomerInfo
                    {
                        Id = cId,
                        Name = cName,
                        Phone = cPhone,
                        Email = cEmail,
                        Address = cAddr,
                        WalletBalance = cWallet,
                        LoyaltyPoints = cLoyalty
                    };

                    if (!string.IsNullOrEmpty(cName) && !cName.StartsWith("Customer ")) txtName.Text = cName;
                    if (!string.IsNullOrEmpty(cEmail)) txtEmail.Text = cEmail;
                    if (!string.IsNullOrEmpty(cAddr)) txtAddress.Text = cAddr;
                    SelectState(cStateId);

                    lblWallet.Text = $"\u20b9 {cWallet:N2}";
                    lblLoyalty.Text = $"{cLoyalty:N0} pts";
                    lblStatus.Text = $"\u2713 Customer found \u2014 Loyalty: {cLoyalty:N0} pts | Wallet: \u20b9{cWallet:N2}";
                    lblStatus.Foreground = new System.Windows.Media.SolidColorBrush(System.Windows.Media.Color.FromRgb(22, 163, 74));
                }
                else
                {
                    ClearFound();
                }
            }
            catch (Exception ex)
            {
                System.Diagnostics.Debug.WriteLine("LookupCustomer ERR: " + ex.Message);
                lblStatus.Text = "\u26a0 Lookup error: " + ex.Message;
                lblStatus.Foreground = new System.Windows.Media.SolidColorBrush(System.Windows.Media.Color.FromRgb(185, 28, 28));
            }
        }

        private void ClearFound()
        {
            _existing = null;
            lblWallet.Text = "\u20b9 0.00";
            lblLoyalty.Text = "0 pts";
            lblStatus.Text = "All fields are optional. Mobile number is used to auto-detect existing customer.";
            lblStatus.Foreground = new System.Windows.Media.SolidColorBrush(System.Windows.Media.Color.FromRgb(22, 101, 52));
        }

        private int CurrentIndex()
        {
            for (int i = 0; i < _fields.Length; i++)
            {
                if (_fields[i].IsKeyboardFocusWithin || _fields[i].IsFocused) return i;
            }
            return -1;
        }

        private void MoveNext()
        {
            int idx = CurrentIndex();
            if (idx < 0) idx = 0;
            if (idx >= _fields.Length - 1)
            {
                Confirm();
                return;
            }
            _fields[idx + 1].Focus();
            Keyboard.Focus(_fields[idx + 1]);
        }

        private void MovePrev()
        {
            int idx = CurrentIndex();
            if (idx <= 0) return;
            _fields[idx - 1].Focus();
            Keyboard.Focus(_fields[idx - 1]);
        }

        private void Window_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            switch (e.Key)
            {
                case Key.Escape:
                    DialogResult = false;
                    Close();
                    e.Handled = true;
                    break;

                case Key.Enter:
                    if (CurrentIndex() == 0)
                    {
                        if (txtMobile.Text.Trim().Length >= 10)
                            LookupCustomer();
                        MoveNext();
                    }
                    else
                    {
                        Confirm();
                    }
                    e.Handled = true;
                    break;

                case Key.Tab:
                    MoveNext();
                    e.Handled = true;
                    break;

                case Key.Down:
                    if (!(Keyboard.FocusedElement is ComboBox))
                    {
                        MoveNext();
                        e.Handled = true;
                    }
                    break;

                case Key.Up:
                    if (!(Keyboard.FocusedElement is ComboBox))
                    {
                        MovePrev();
                        e.Handled = true;
                    }
                    break;
            }
        }

        private void Confirm()
        {
            var phone = txtMobile.Text.Trim();
            var name = txtName.Text.Trim();
            var email = txtEmail.Text.Trim();
            var address = txtAddress.Text.Trim();

            if (phone == "" && name == "" && email == "" && address == "")
            {
                DialogResult = false;
                Close();
                return;
            }

            if (name.Equals("Walk-in Customer", StringComparison.OrdinalIgnoreCase))
            {
                MessageBox.Show("\"Walk-in Customer\" naam reserved hai \u2014 dusra naam use karein.",
                    "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                txtName.Focus();
                return;
            }

            // Phone duplicate check — new customer only (_existing == null)
            if (_existing == null && !string.IsNullOrWhiteSpace(phone))
            {
                try
                {
                    using var chk = _db.GetConnection();
                    using var cc = chk.CreateCommand();
                    cc.CommandText = @"SELECT
                        (SELECT COUNT(*) FROM customers
                            WHERE LOWER(TRIM(COALESCE(phone,''))) = LOWER(TRIM(@p))
                              AND (del_status IS NULL OR del_status != 'Deleted')
                              AND LOWER(TRIM(COALESCE(name,''))) != 'walk-in customer')
                        +
                        (SELECT COUNT(*) FROM Master1
                            WHERE MasterType='Party' AND PartyType IN ('Customer','Both')
                              AND (DelStatus IS NULL OR DelStatus != 'Deleted')
                              AND LOWER(TRIM(COALESCE(Phone,''))) = LOWER(TRIM(@p2)))";
                    cc.Parameters.AddWithValue("@p", phone);
                    cc.Parameters.AddWithValue("@p2", phone);
                    long cnt = (long)(cc.ExecuteScalar() ?? 0L);
                    if (cnt > 0)
                    {
                        MessageBox.Show($"Is phone number ({phone}) se already ek customer registered hai.",
                            "Duplicate Phone", MessageBoxButton.OK, MessageBoxImage.Warning);
                        txtMobile.Focus();
                        return;
                    }
                }
                catch { }
            }

            if (_existing != null)
            {
                if (!string.IsNullOrEmpty(name)) _existing.Name = name;
                if (!string.IsNullOrEmpty(email)) _existing.Email = email;
                if (!string.IsNullOrEmpty(address)) _existing.Address = address;

                try
                {
                    using var conn = _db.GetConnection();
                    using var txn = conn.BeginTransaction();

                    if (_existing.Id > 0)
                    {
                        using (var upd = conn.CreateCommand())
                        {
                            upd.Transaction = txn;
                            upd.CommandText = "UPDATE customers SET name=@n, email=@e, address=@a, updated_at=datetime('now'), SyncStatus='Local' WHERE id=@id";
                            upd.Parameters.AddWithValue("@n", _existing.Name);
                            upd.Parameters.AddWithValue("@e", _existing.Email ?? "");
                            upd.Parameters.AddWithValue("@a", _existing.Address ?? "");
                            upd.Parameters.AddWithValue("@id", _existing.Id);
                            upd.ExecuteNonQuery();
                        }
                    }
                    else
                    {
                        // Master1 fallback — customers table mein nahi hai, insert karo
                        using (var ins = conn.CreateCommand())
                        {
                            ins.Transaction = txn;
                            ins.CommandText = @"INSERT INTO customers (name, email, phone, address, state_id, loyalty_point,
                                    del_status, SyncStatus, created_at, updated_at)
                                VALUES (@n, @e, @p, @a, @s, 0, 'Live', 'Local', datetime('now'), datetime('now'));
                                SELECT last_insert_rowid();";
                            ins.Parameters.AddWithValue("@n", _existing.Name);
                            ins.Parameters.AddWithValue("@e", _existing.Email ?? "");
                            ins.Parameters.AddWithValue("@p", _existing.Phone ?? phone);
                            ins.Parameters.AddWithValue("@a", _existing.Address ?? "");
                            ins.Parameters.AddWithValue("@s", (cmbState.SelectedItem as ComboBoxItem)?.Tag ?? DBNull.Value);
                            long newId = Convert.ToInt64(ins.ExecuteScalar());
                            _existing.Id = newId;
                        }
                    }

                    UpsertMaster1(conn, txn, _existing, name, email, address);
                    txn.Commit();
                }
                catch { }

                SelectedCustomer = _existing;
                DialogResult = true;
                Close();
                return;
            }

            // Brand new customer
            try
            {
                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();
                long id;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.Transaction = txn;
                    cmd.CommandText = @"INSERT INTO customers (name, email, phone, address, state_id, loyalty_point,
                            del_status, SyncStatus, created_at, updated_at)
                        VALUES (@n, @e, @p, @a, @s, 0, 'Live', 'Local', datetime('now'), datetime('now'));
                        SELECT last_insert_rowid();";
                    cmd.Parameters.AddWithValue("@n", name);
                    cmd.Parameters.AddWithValue("@e", email);
                    cmd.Parameters.AddWithValue("@p", phone);
                    cmd.Parameters.AddWithValue("@a", address);
                    cmd.Parameters.AddWithValue("@s", (cmbState.SelectedItem as ComboBoxItem)?.Tag ?? DBNull.Value);
                    id = Convert.ToInt64(cmd.ExecuteScalar());
                }

                var fresh = new CustomerInfo
                {
                    Id = id,
                    Name = name,
                    Phone = phone,
                    Email = email,
                    Address = address,
                    WalletBalance = 0,
                    LoyaltyPoints = 0
                };
                UpsertMaster1(conn, txn, fresh, name, email, address);
                txn.Commit();

                SelectedCustomer = fresh;
                DialogResult = true;
                Close();
            }
            catch (Exception ex)
            {
                lblStatus.Text = "Save failed: " + ex.Message;
                lblStatus.Foreground = new System.Windows.Media.SolidColorBrush(System.Windows.Media.Color.FromRgb(185, 28, 28));
            }
        }

        private void UpsertMaster1(SqliteConnection conn, SqliteTransaction txn, CustomerInfo cust, string name, string email, string address)
        {
            string stateName = (cmbState.SelectedItem as ComboBoxItem)?.Content?.ToString() ?? "";

            long serverId = 0;
            using (var f = conn.CreateCommand())
            {
                f.Transaction = txn;
                f.CommandText = "SELECT IFNULL(ServerId,0) FROM customers WHERE id=@id LIMIT 1";
                f.Parameters.AddWithValue("@id", cust.Id);
                var v = f.ExecuteScalar();
                if (v != null) serverId = Convert.ToInt64(v);
            }

            string? existingCode = null;
            if (serverId > 0)
            {
                using var chk = conn.CreateCommand();
                chk.Transaction = txn;
                chk.CommandText = @"SELECT Code FROM Master1
                                    WHERE MasterType='Party' AND PartyType='Customer' AND ServerId=@sid
                                    LIMIT 1";
                chk.Parameters.AddWithValue("@sid", serverId);
                existingCode = chk.ExecuteScalar()?.ToString();
            }
            if (string.IsNullOrEmpty(existingCode) && !string.IsNullOrEmpty(cust.Phone))
            {
                using var chk = conn.CreateCommand();
                chk.Transaction = txn;
                chk.CommandText = @"SELECT Code FROM Master1
                                    WHERE MasterType='Party' AND PartyType='Customer'
                                      AND LOWER(TRIM(IFNULL(Phone,''))) = LOWER(TRIM(@phone))
                                    LIMIT 1";
                chk.Parameters.AddWithValue("@phone", cust.Phone);
                existingCode = chk.ExecuteScalar()?.ToString();
            }

            if (!string.IsNullOrEmpty(existingCode))
            {
                using var upd = conn.CreateCommand();
                upd.Transaction = txn;
                upd.CommandText = @"UPDATE Master1 SET
                    Name=@n, Phone=@p, Email=@e, Address1=@a, State=@s,
                    SyncStatus='Local', SyncVersion=IFNULL(SyncVersion,1)+1, UpdatedAt=datetime('now')
                    WHERE Code=@c";
                upd.Parameters.AddWithValue("@n", name);
                upd.Parameters.AddWithValue("@p", cust.Phone);
                upd.Parameters.AddWithValue("@e", email);
                upd.Parameters.AddWithValue("@a", address);
                upd.Parameters.AddWithValue("@s", stateName);
                upd.Parameters.AddWithValue("@c", existingCode);
                upd.ExecuteNonQuery();
            }
            else
            {
                using var ins = conn.CreateCommand();
                ins.Transaction = txn;
                ins.CommandText = @"INSERT INTO Master1
                    (Code, Name, MasterType, PartyType, Phone, Email, Address1, State,
                     IsActive, SyncStatus, SyncVersion, CreatedAt, UpdatedAt)
                    VALUES
                    (@code, @n, 'Party', 'Customer', @p, @e, @a, @s,
                     1, 'Local', 1, datetime('now'), datetime('now'))";
                ins.Parameters.AddWithValue("@code", "CUS" + DateTime.Now.ToString("yyyyMMddHHmmssfff"));
                ins.Parameters.AddWithValue("@n", name);
                ins.Parameters.AddWithValue("@p", cust.Phone);
                ins.Parameters.AddWithValue("@e", email);
                ins.Parameters.AddWithValue("@a", address);
                ins.Parameters.AddWithValue("@s", stateName);
                ins.ExecuteNonQuery();
            }
        }

        private void BtnOk_Click(object sender, RoutedEventArgs e)
        {
            Confirm();
        }

        private void BtnCancel_Click(object sender, RoutedEventArgs e)
        {
            DialogResult = false;
            Close();
        }
    }

    public class CustomerInfo
    {
        public long Id { get; set; }
        public string Name { get; set; } = "";
        public string Phone { get; set; } = "";
        public string Email { get; set; } = "";
        public string Address { get; set; } = "";
        public double WalletBalance { get; set; }
        public double LoyaltyPoints { get; set; }
    }
}
