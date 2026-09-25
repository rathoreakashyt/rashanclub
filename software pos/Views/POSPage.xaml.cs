using System.Collections.Generic;
using System.Collections.ObjectModel;
using System.Linq;
using System.Net.NetworkInformation;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Threading;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;
using RashanKiDukan.Models;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views
{
    public partial class POSPage : UserControl
    {
        private readonly DatabaseService _db = new DatabaseService();
        private readonly MainDashboard? _dashboard;
        private readonly User? _currentUser;
        private readonly ObservableCollection<BusyCartItem> _cart = new();
        private readonly DispatcherTimer _clockTimer;
        // Hold Voucher list — shared across POSPage and HoldVoucherPage
        private readonly List<HeldBill> _heldBills = new();
        private int _srNo = 0;
        private string _lastSavedSaleNo = "";
        private long _lastSavedSaleId;
        private string _lastAddedItemName = "";
        private double _lastAddedQty = 1;
        private double _lastAddedRate = 0;
        private double _lastAddedDisc = 0;

        // Sale type flags
        private bool _isTaxInclusive = false;
        private bool _isInterState = false;

        // Current customer selected via F2
        private CustomerInfo? _currentCustomer;

        private bool _isSaving = false; // re-entrancy guard for SaveBillCore (prevents duplicate sale on retry)

        // Last saved invoice for reprint
        private string _lastSavedVchCode = "";
        private List<BusyCartItem> _lastSavedItems = new();
        private double _lastSavedGrandTotal = 0;
        private string _lastSavedPaymentMode = "";

        // Last saved invoice customer (for F9 send message — independent of current selection)
        private string _lastSavedCustomerName = "";
        private string _lastSavedCustomerPhone = "";
        private string _lastSavedCustomerEmail = "";

        // Keyboard navigation state (Excel-style): -1 = browse, >=0 = qty mode row index
        private int _qtyEditRow = -1;

        // Scheme (Buy X Get Y) state — prevents duplicate free items on repeated Ctrl+C
        private bool _schemeApplied = false;
        private string _schemeAppliedTitle = "";

        // Coupon discount — applied at bill level, not on individual items
        private double _couponDiscount = 0;
        private string _couponCode = "";

        // Internet connectivity check
        private readonly DispatcherTimer _netCheckTimer;
        private bool _lastOnlineStatus = true;
        // Customer search debounce — DB hit sirf 300ms baad
        private readonly DispatcherTimer _customerDebounce;

        public POSPage() { InitializeComponent(); Loaded += POSPage_Loaded; _clockTimer = new DispatcherTimer(); _netCheckTimer = new DispatcherTimer(); _customerDebounce = new DispatcherTimer { Interval = TimeSpan.FromMilliseconds(150) }; _customerDebounce.Tick += (_, _) => { _customerDebounce.Stop(); FindCustomerMatch(); }; }
        public POSPage(MainDashboard dashboard, User? currentUser = null) : this()
        {
            _dashboard = dashboard;
            _currentUser = currentUser;
            dgCart.ItemsSource = _cart;
            _currentCustomer = new CustomerInfo { Id = 1, Name = "Walk-in Customer" };
            SetSalesmanLabel();
            LoadOutletLabels();
            UpdateTotals();
            StartClock();
            StartNetCheck();
            LoadHeldBillsFromDb();
        }

        private void SetSalesmanLabel()
        {
            string displayName = _currentUser?.FullName ?? _currentUser?.Username ?? "POS";

            string counterName = "";
            if (_currentUser != null)
            {
                counterName = GetCounterName(_currentUser.Id);
            }

            lblSalesman.Text = string.IsNullOrEmpty(counterName)
                ? displayName
                : $"{displayName} - {counterName}";

            if (lblOutletName != null)
                lblOutletName.Text = string.IsNullOrEmpty(counterName) ? "Rashan Ki Dukan" : counterName;
        }

        // ═══════ CUSTOMER ENTRY (replaces search bar) ═══════

        private CustomerInfo? _matchedCustomer;

        private void TxtCustomer_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (txtCustomerPlaceholder != null)
                txtCustomerPlaceholder.Visibility = string.IsNullOrEmpty(txtCustomer.Text)
                    ? Visibility.Visible : Visibility.Collapsed;
            // Debounce: 300ms ke baad DB hit karo, har keystroke pe nahi
            _customerDebounce.Stop();
            _customerDebounce.Start();
        }

        // Sirf digits — +91 prefix fix hai aur mobile number 10 digit ka hi ho sakta hai
        private void TxtCustomer_PreviewTextInput(object sender, TextCompositionEventArgs e)
        {
            e.Handled = !e.Text.All(char.IsDigit);
        }

        private void TxtCustomer_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter)
            {
                e.Handled = true;
                // Pending lookup rok do — warna Name cell me typing ke doran woh chal ke naam wipe kar deta hai
                _customerDebounce.Stop();
                string query = txtCustomer.Text.Trim();

                // Number hi nahi diya → single Enter = Walk-in Customer, phir aage badho
                if (string.IsNullOrEmpty(query))
                {
                    _currentCustomer = new CustomerInfo { Id = 1, Name = "Walk-in Customer" };
                    UpdateBottomCustomer();
                    HideCustomerMatch();
                    OpenActionPopup();
                    return;
                }

                // Number diya → Enter dabate hi shift Name cell par
                var exact = FindCustomerByPhone(query);
                if (exact != null)
                {
                    // Registered → naam / wallet / loyalty auto-fill
                    _currentCustomer = exact;
                    UpdateBottomCustomer();
                    HideCustomerMatch();
                }
                else
                {
                    // Naya number → naam optional; default Walk-in Customer with this number
                    _currentCustomer = new CustomerInfo { Id = 1, Name = "Walk-in Customer", Phone = query };
                    txtCustomerName.Clear();
                    SetCustomerWalletLoyalty(0, 0, false);
                    txtCustomerName.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#64748B"));
                    txtCustomerName.FontWeight = FontWeights.Normal;
                    HideCustomerMatch();
                }
                txtCustomerName.Focus();
                txtCustomerName.CaretIndex = txtCustomerName.Text.Length;
            }
            else if (e.Key == Key.Escape)
            {
                e.Handled = true;
                txtCustomer.Clear();
                HideCustomerMatch();
                Focus();
            }
        }

        // Name cell — typing shuru hote hi placeholder chhup jaye
        private void TxtCustomerName_TextChanged(object sender, TextChangedEventArgs e)
        {
            ToggleCustomerNamePlaceholder();
        }

        private void ToggleCustomerNamePlaceholder()
        {
            if (txtCustomerNamePlaceholder == null) return;
            txtCustomerNamePlaceholder.Visibility = string.IsNullOrWhiteSpace(txtCustomerName.Text)
                ? Visibility.Visible : Visibility.Collapsed;
        }

        // Name cell — Enter se customer commit (naam diya to wahi, warna Walk-in), Esc se wapas number par
        private void TxtCustomerName_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter)
            {
                e.Handled = true;
                string typed = txtCustomerName.Text.Trim();
                string phone = txtCustomer.Text.Trim();

                if (_currentCustomer != null && _currentCustomer.Id > 1)
                {
                    // Registered customer — naam badla ho to DB me bhi update
                    if (!string.IsNullOrEmpty(typed) && typed != _currentCustomer.Name)
                    {
                        UpdateCustomerInDb(_currentCustomer.Id, typed, _currentCustomer.Phone);
                        _currentCustomer.Name = typed;
                    }
                }
                else if (!string.IsNullOrEmpty(typed) && !string.IsNullOrEmpty(phone))
                {
                    // Naya number + naam → DB me naya customer ban jaye
                    var fresh = CreateCustomerFromPos(typed, phone);
                    _currentCustomer = fresh ?? new CustomerInfo { Id = 1, Name = typed, Phone = phone };
                }
                else
                {
                    // Naam nahi diya → Walk-in with number
                    _currentCustomer = new CustomerInfo { Id = 1, Name = "Walk-in Customer", Phone = phone };
                }

                UpdateBottomCustomer();
                HideCustomerMatch();
                OpenActionPopup();
            }
            else if (e.Key == Key.Escape)
            {
                e.Handled = true;
                txtCustomer.Focus();
            }
        }

        private void FindCustomerMatch()
        {
            // Name cell par typing ho rahi ho to live preview mat chhedo
            if (txtCustomerName != null && txtCustomerName.IsFocused) return;

            string query = txtCustomer.Text.Trim();
            if (query.Length == 0)
            {
                _matchedCustomer = null;
                HideCustomerMatch();
                UpdateCustomerInfoCells();
                return;
            }
            var c = FindCustomerByPhone(query);
            if (c != null)
            {
                _matchedCustomer = c;

                // Naam + wallet + loyalty immediately fill karo (partial ya full match)
                txtCustomerName.Text = string.IsNullOrEmpty(c.Name) ? c.Phone : c.Name;
                txtCustomerName.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#15803D"));
                txtCustomerName.FontWeight = FontWeights.SemiBold;
                SetCustomerWalletLoyalty(c.WalletBalance, c.LoyaltyPoints, true);
                ToggleCustomerNamePlaceholder();

                // 10 digit complete = auto commit customer
                if (query.Length == 10)
                {
                    _currentCustomer = c;
                    UpdateBottomCustomer();
                    HideCustomerMatch();
                    return;
                }

                // Partial — small hint dikhao
                txtCustomerMatch.Text = (string.IsNullOrEmpty(c.Name) ? c.Phone : c.Name) + "  —  Enter to confirm";
                customerMatchBox.Visibility = Visibility.Visible;
            }
            else
            {
                _matchedCustomer = null;
                HideCustomerMatch();
                UpdateCustomerInfoCells();
            }
        }

        private void HideCustomerMatch()
        {
            if (customerMatchBox != null) customerMatchBox.Visibility = Visibility.Collapsed;
        }

        // Excel customer cells — Name / Wallet / Loyalty ko _currentCustomer se sync karta hai
        private void UpdateCustomerInfoCells()
        {
            if (txtCustomerName == null) return;
            bool registered = _currentCustomer != null && _currentCustomer.Id > 1;
            if (registered)
            {
                string name = string.IsNullOrEmpty(_currentCustomer.Name) ? _currentCustomer.Phone : _currentCustomer.Name;
                txtCustomerName.Text = name;
                txtCustomerName.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#15803D"));
                txtCustomerName.FontWeight = FontWeights.SemiBold;
                SetCustomerWalletLoyalty(_currentCustomer.WalletBalance, _currentCustomer.LoyaltyPoints, true);
            }
            else
            {
                // Walk-in — naam diya gaya ho to wahi dikhao, warna placeholder hi rahe
                string walkInName = (_currentCustomer == null || _currentCustomer.Name == "Walk-in Customer")
                    ? ""
                    : _currentCustomer.Name;
                txtCustomerName.Text = walkInName;
                txtCustomerName.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#64748B"));
                txtCustomerName.FontWeight = FontWeights.Normal;
                SetCustomerWalletLoyalty(0, 0, false);
            }
            ToggleCustomerNamePlaceholder();
        }

        // Wallet / Loyalty cells (F2 Add Customer page jaise values)
        private void SetCustomerWalletLoyalty(double wallet, double loyalty, bool registered)
        {
            if (lblCustWallet != null)
            {
                lblCustWallet.Text = $"₹ {wallet:N2}";
                lblCustWallet.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString(registered ? "#15803D" : "#94A3B8"));
            }
            if (lblCustLoyalty != null)
            {
                lblCustLoyalty.Text = $"{loyalty:N0} pts";
                lblCustLoyalty.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString(registered ? "#2563EB" : "#94A3B8"));
            }
        }

        // Mobile number ko 10-digit canonical form me laao (+91 / 0 / spaces / dashes hata ke)
        private static string NormalizePhone(string? phone)
        {
            if (string.IsNullOrWhiteSpace(phone)) return "";
            var digits = new string(phone.Where(char.IsDigit).ToArray());
            if (digits.Length > 10) digits = digits.Substring(digits.Length - 10);
            return digits;
        }

        // DUPLICATE SECURITY: same mobile number (kisi bhi format me) pe pehle se customer hai?
        private CustomerInfo? FindExistingCustomerByPhone(string phone)
        {
            string norm = NormalizePhone(phone);
            if (norm.Length < 4) return null;
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT c.id, c.name, c.phone,
                                           IFNULL(w.balance, 0) AS wb,
                                           IFNULL(c.loyalty_point, 0) AS lp
                                    FROM customers c
                                    LEFT JOIN customer_wallets w ON w.customer_id = c.id
                                    WHERE REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(c.phone,''),' ',''),'-',''),'+',''),')','') LIKE @p
                                    ORDER BY c.id DESC LIMIT 1";
                cmd.Parameters.AddWithValue("@p", "%" + norm);
                using var r = cmd.ExecuteReader();
                if (r.Read())
                {
                    return new CustomerInfo
                    {
                        Id = r.IsDBNull(0) ? 1 : r.GetInt64(0),
                        Name = r.IsDBNull(1) ? "" : r.GetString(1),
                        Phone = r.IsDBNull(2) ? "" : r.GetString(2),
                        WalletBalance = r.IsDBNull(3) ? 0 : r.GetDouble(3),
                        LoyaltyPoints = r.IsDBNull(4) ? 0 : r.GetDouble(4)
                    };
                }
            }
            catch (Exception ex) { Services.LogService.Error("FindExistingCustomerByPhone failed", ex); }
            return null;
        }

        // Naya customer POS se hi ban jaye (F2 Add Customer page jaisa hi insert + Master1 mirror)
        // DUPLICATE SECURITY: same mobile already database me ho to naya row nahi banega
        private CustomerInfo? CreateCustomerFromPos(string name, string phone)
        {
            var duplicate = FindExistingCustomerByPhone(phone);
            if (duplicate != null)
            {
                // Pehle se hai — naya insert nahi; naam khali tha to bhar do
                if (string.IsNullOrWhiteSpace(duplicate.Name) && !string.IsNullOrWhiteSpace(name))
                {
                    UpdateCustomerInDb(duplicate.Id, name, duplicate.Phone);
                    duplicate.Name = name;
                }
                Services.LogService.Info($"Duplicate customer blocked for phone {NormalizePhone(phone)} — existing id {duplicate.Id} reuse kiya");
                return duplicate;
            }
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
                        VALUES (@n, '', @p, '', NULL, 0, 'Live', 'Local', datetime('now'), datetime('now'));
                        SELECT last_insert_rowid();";
                    cmd.Parameters.AddWithValue("@n", name);
                    cmd.Parameters.AddWithValue("@p", phone);
                    id = Convert.ToInt64(cmd.ExecuteScalar());
                }

                // Cloud push ke liye Master1 me mirror (CustomerWindow jaisa)
                // DUPLICATE SECURITY: same phone ka CUS mirror pehle se ho to dobara insert nahi
                using (var chk = conn.CreateCommand())
                {
                    chk.Transaction = txn;
                    chk.CommandText = @"SELECT Code FROM Master1
                        WHERE MasterType='Party' AND PartyType='Customer'
                          AND REPLACE(REPLACE(REPLACE(IFNULL(Phone,''),' ',''),'-',''),'+','') LIKE @p
                        LIMIT 1";
                    chk.Parameters.AddWithValue("@p", "%" + NormalizePhone(phone));
                    var existingCode = chk.ExecuteScalar()?.ToString();
                    if (!string.IsNullOrEmpty(existingCode))
                    {
                        Services.LogService.Info($"Master1 mirror already exists for phone {NormalizePhone(phone)} — reuse {existingCode}");
                        txn.Commit();
                        return new CustomerInfo { Id = id, Name = name, Phone = phone, WalletBalance = 0, LoyaltyPoints = 0 };
                    }
                }

                using (var ins = conn.CreateCommand())
                {
                    ins.Transaction = txn;
                    ins.CommandText = @"INSERT INTO Master1
                        (Code, Name, MasterType, PartyType, Phone, Email, Address1, State,
                         IsActive, SyncStatus, SyncVersion, CreatedAt, UpdatedAt)
                        VALUES
                        (@code, @n, 'Party', 'Customer', @p, '', '', '',
                         1, 'Local', 1, datetime('now'), datetime('now'))";
                    ins.Parameters.AddWithValue("@code", "CUS" + DateTime.Now.ToString("yyyyMMddHHmmssfff"));
                    ins.Parameters.AddWithValue("@n", name);
                    ins.Parameters.AddWithValue("@p", phone);
                    ins.ExecuteNonQuery();
                }

                txn.Commit();
                return new CustomerInfo { Id = id, Name = name, Phone = phone, WalletBalance = 0, LoyaltyPoints = 0 };
            }
            catch (Exception ex)
            {
                Services.LogService.Error("CreateCustomerFromPos failed", ex);
                return null;
            }
        }

        // Registered customer ka naam edit hone par DB (aur Master1) me update
        private void UpdateCustomerInDb(long id, string name, string phone)
        {
            try
            {
                using var conn = _db.GetConnection();
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "UPDATE customers SET name=@n, updated_at=datetime('now'), SyncStatus='Local' WHERE id=@id";
                    cmd.Parameters.AddWithValue("@n", name);
                    cmd.Parameters.AddWithValue("@id", id);
                    cmd.ExecuteNonQuery();
                }
                using (var m = conn.CreateCommand())
                {
                    m.CommandText = @"UPDATE Master1 SET Name=@n, SyncStatus='Local',
                            SyncVersion=IFNULL(SyncVersion,1)+1, UpdatedAt=datetime('now')
                        WHERE MasterType='Party' AND PartyType='Customer'
                          AND LOWER(TRIM(IFNULL(Phone,''))) = LOWER(TRIM(@p))";
                    m.Parameters.AddWithValue("@n", name);
                    m.Parameters.AddWithValue("@p", phone ?? "");
                    m.ExecuteNonQuery();
                }
            }
            catch (Exception ex)
            {
                Services.LogService.Error("UpdateCustomerInDb failed", ex);
            }
        }

        private CustomerInfo? FindCustomerByPhone(string phone)
        {
            if (string.IsNullOrWhiteSpace(phone)) return null;
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                // customers.phone index is already created by DatabaseService.CreateIndexes
                // wallet column ka naam 'balance' hai (wallet_balance nahi) — LEFT JOIN subquery se fast
                cmd.CommandText = @"SELECT c.id, c.name, c.phone,
                                           IFNULL(w.balance, 0) AS wb,
                                           IFNULL(c.loyalty_point, 0) AS lp
                                    FROM customers c
                                    LEFT JOIN customer_wallets w ON w.customer_id = c.id
                                    WHERE (   c.phone LIKE @p
                                           OR c.phone LIKE @p91
                                           OR REPLACE(REPLACE(REPLACE(c.phone,'+91',''),' ',''),'-','') LIKE @p)
                                      AND LOWER(TRIM(COALESCE(c.name,''))) != 'walk-in customer'
                                    ORDER BY c.id DESC LIMIT 1";
                cmd.Parameters.AddWithValue("@p", phone + "%");
                cmd.Parameters.AddWithValue("@p91", "+91" + phone + "%");
                using var r = cmd.ExecuteReader();
                if (r.Read())
                {
                    return new CustomerInfo
                    {
                        Id = r.IsDBNull(0) ? 1 : r.GetInt64(0),
                        Name = r.IsDBNull(1) ? "" : r.GetString(1),
                        Phone = r.IsDBNull(2) ? "" : r.GetString(2),
                        WalletBalance = r.IsDBNull(3) ? 0 : r.GetDouble(3),
                        LoyaltyPoints = r.IsDBNull(4) ? 0 : r.GetDouble(4)
                    };
                }
            }
            catch (Exception ex) { Services.LogService.Error("FindCustomerByPhone failed", ex); }
            // Fallback: number DB me +91 / 0 prefix ya spaces ke saath pada ho to bhi match ho jaye
            return FindExistingCustomerByPhone(phone);
        }

        // ═══════ POST-CUSTOMER ACTION POPUP: Scan Barcode (default) / Add Item ═══════

        private void OpenActionPopup()
        {
            var popup = new ScanActionPopup { Owner = Window.GetWindow(this) };
            popup.ShowDialog();
            if (popup.Result == ScanAction.ScanBarcode)
            {
                StartBarcodeScan();
            }
            else if (popup.Result == ScanAction.AddItem)
            {
                Focus();
                OpenItemList();
            }
            else
            {
                Focus();
            }
        }

        // Scan mode — keyboard-wedge barcode scanner input adds products straight to cart
        private void StartBarcodeScan()
        {
            var win = new BarcodeScanWindow(code =>
            {
                var exact = SearchItemExact(code);
                if (exact == null) return "";
                ApplyCartItem(exact, 1);
                return exact.Name;
            })
            { Owner = Window.GetWindow(this) };
            win.ShowDialog();
            Focus();
        }

        private ItemLookupRow? SearchItemExact(string query)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT i.id, i.name, i.code, i.sale_price,
                    IFNULL(NULLIF(i.mrp_price,0), (SELECT m.MRP FROM Master1 m WHERE m.MasterType='Item' AND lower(m.Code)=lower(i.code) LIMIT 1)) AS mrp_price,
                    i.stock_quantity,
                    i.hsn_code, i.unit_type, i.conversion_rate, i.tax_string, i.applicable_tax_id, i.type,
                    c.Name as CategoryName, u.UnitName, t.tax_rate as TaxRate,
                    (SELECT m.TaxCategory FROM Master1 m WHERE lower(m.Code)=lower(i.code) LIMIT 1) as M1TaxCat
                    FROM items i
                    LEFT JOIN (SELECT v.item_id, SUM(CASE WHEN v.type=1 THEN v.stock_quantity ELSE -v.stock_quantity END) AS qty
                               FROM view_stock_detail v WHERE v.del_status IS NULL OR v.del_status='Live' GROUP BY v.item_id) s
                           ON s.item_id = i.id
                    LEFT JOIN item_categories c ON i.category_id = c.Id
                    LEFT JOIN units u ON i.sale_unit_id = u.Id
                    LEFT JOIN taxs t ON i.applicable_tax_id = t.id
                    WHERE (i.del_status IS NULL OR i.del_status != 'Deleted')
                      AND (lower(i.code) = lower(@q) OR lower(i.hsn_code) = lower(@q))
                      AND COALESCE(s.qty, IFNULL(i.stock_quantity,0)) > 0
                    LIMIT 1";
                cmd.Parameters.AddWithValue("@q", query);
                using var r = cmd.ExecuteReader();
                if (r.Read())
                {
                    var salePrice = r["sale_price"] != DBNull.Value ? Math.Round(Convert.ToDouble(r["sale_price"]), 2) : 0;
                    var mrp = r["mrp_price"] != DBNull.Value ? Math.Round(Convert.ToDouble(r["mrp_price"]), 2) : salePrice;
                    var stock = r["stock_quantity"] != DBNull.Value ? Convert.ToDouble(r["stock_quantity"]) : 0;
                    var tax = r["TaxRate"] != DBNull.Value ? Convert.ToDouble(r["TaxRate"]) : 0;
                    if (tax == 0) tax = ParseTaxString(r["tax_string"]?.ToString() ?? "");
                    if (tax == 0) tax = ParseTaxString(r["M1TaxCat"]?.ToString() ?? "");
                    // Final fallback: derive GST rate from HSN code
                    var hsnCode = r["hsn_code"]?.ToString() ?? "";
                    if (tax == 0 && !string.IsNullOrEmpty(hsnCode))
                    {
                        var hsnRate = Services.GstValidationService.GetGstRateForHsn(hsnCode);
                        if (hsnRate.HasValue) tax = hsnRate.Value;
                    }
                    var unitType = r["unit_type"]?.ToString() ?? "";
                    var conversion = r["conversion_rate"] != DBNull.Value ? Convert.ToDouble(r["conversion_rate"]) : 0;
                    var packageSize = unitType == "2" && conversion > 0 ? conversion : 1;

                    return new ItemLookupRow
                    {
                        Name = r["name"]?.ToString() ?? "",
                        Code = r["code"]?.ToString() ?? "",
                        Category = r["CategoryName"]?.ToString() ?? "",
                        Unit = r["UnitName"]?.ToString() ?? "",
                        Hsn = r["hsn_code"]?.ToString() ?? "",
                        SalePrice = $"Rs.{salePrice:N2}",
                        Stock = $"{stock:0.##}",
                        RateValue = salePrice,
                        MrpValue = mrp,
                        StockValue = stock,
                        TaxPerc = tax,
                        PackageSize = packageSize,
                        MaxQty = packageSize > 0 ? Math.Floor(stock / packageSize) : stock,
                        Type = r["type"]?.ToString() ?? ""
                    };
                }
            }
            catch (Exception ex) { Services.LogService.Error("SearchItemExact failed", ex); }
            return null;
        }

        private static double ParseTaxString(string s)
        {
            if (string.IsNullOrWhiteSpace(s)) return 0;
            var cleaned = System.Text.RegularExpressions.Regex.Replace(s, "[^0-9.]", "");
            return double.TryParse(cleaned, out var v) ? v : 0;
        }

        /// <summary>
        /// Search for a parent product that has variant children.
        /// Returns list of variants if the search matches a parent name.
        /// e.g., "ashirwad atta" → returns [5kg, 10kg, 20kg] variants
        /// </summary>
        private bool IsVariationParent(string code)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT type FROM items WHERE lower(code)=lower(@c) AND (del_status IS NULL OR del_status!='Deleted') LIMIT 1";
                cmd.Parameters.AddWithValue("@c", code);
                return cmd.ExecuteScalar()?.ToString() == "Variation_Product";
            }
            catch { return false; }
        }

        private List<VariantDisplayItem>? SearchVariantChildren(string query)
        {
            try
            {
                using var conn = _db.GetConnection();

                // Find parent items matching the query that have children (by parent_id or code prefix)
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT DISTINCT p.id, p.name, p.code
                    FROM items p
                    WHERE (p.del_status IS NULL OR p.del_status != 'Deleted')
                      AND (p.type = 'Variation_Product'
                           OR ((p.parent_id IS NULL OR p.parent_id = 0 OR p.parent_id = '')
                               AND IFNULL(p.type,'') != '0'
                               AND EXISTS (SELECT 1 FROM items c WHERE c.parent_id = p.id AND (c.del_status IS NULL OR c.del_status != 'Deleted'))
                              )
                      )
                      AND (lower(p.name) LIKE lower(@q))
                    LIMIT 1";
                cmd.Parameters.AddWithValue("@q", "%" + query + "%");
                using var pr = cmd.ExecuteReader();
                if (!pr.Read()) return null;

                long parentId = pr.GetInt64(0);
                string parentName = pr.GetString(1);
                string parentCode = pr.IsDBNull(2) ? "" : pr.GetString(2);
                pr.Close();

                // Get all children of this parent (by parent_id OR code prefix)
                using var childCmd = conn.CreateCommand();
                childCmd.CommandText = @"SELECT i.id, i.name, i.code, i.sale_price, i.mrp_price, i.stock_quantity,
                    i.hsn_code, i.unit_type, i.conversion_rate, i.tax_string, i.applicable_tax_id,
                    c.Name as CategoryName, u.UnitName
                    FROM items i
                    LEFT JOIN item_categories c ON i.category_id = c.Id
                    LEFT JOIN units u ON i.sale_unit_id = u.Id
                    WHERE (i.del_status IS NULL OR i.del_status != 'Deleted')
                      AND (i.parent_id = @pid
                           OR (i.type='Variation_Product' AND i.code LIKE @codeprefix)
                           OR (i.type='0' AND i.parent_id = @pid))
                    ORDER BY i.sale_price";
                childCmd.Parameters.AddWithValue("@pid", parentId);
                childCmd.Parameters.AddWithValue("@codeprefix", parentCode + "-%");
                using var cr = childCmd.ExecuteReader();

                var variants = new List<VariantDisplayItem>();
                while (cr.Read())
                {
                    var salePrice = cr["sale_price"] != System.DBNull.Value ? System.Math.Round(System.Convert.ToDouble(cr["sale_price"]), 2) : 0;
                    var mrp = cr["mrp_price"] != System.DBNull.Value ? System.Math.Round(System.Convert.ToDouble(cr["mrp_price"]), 2) : salePrice;
                    var stock = cr["stock_quantity"] != System.DBNull.Value ? System.Convert.ToDouble(cr["stock_quantity"]) : 0;
                    var tax = ParseTaxString(cr["tax_string"]?.ToString() ?? "");
                    var unitType = cr["unit_type"]?.ToString() ?? "";
                    var conversion = cr["conversion_rate"] != System.DBNull.Value ? System.Convert.ToDouble(cr["conversion_rate"]) : 0;
                    var packageSize = unitType == "2" && conversion > 0 ? conversion : 1;

                    variants.Add(new VariantDisplayItem
                    {
                        Name = cr["name"]?.ToString() ?? "",
                        Code = cr["code"]?.ToString() ?? "",
                        PriceDisplay = $"₹{salePrice:N2}",
                        MrpDisplay = mrp > salePrice ? $"₹{mrp:N2}" : "",
                        StockDisplay = $"Stock: {stock:0.##}",
                        ParentName = parentName,
                        LookupRow = new ItemLookupRow
                        {
                            Name = parentName + " - " + (cr["name"]?.ToString() ?? ""),
                            Code = cr["code"]?.ToString() ?? "",
                            Category = cr["CategoryName"]?.ToString() ?? "",
                            Unit = cr["UnitName"]?.ToString() ?? "",
                            Hsn = cr["hsn_code"]?.ToString() ?? "",
                            SalePrice = $"Rs.{salePrice:N2}",
                            Stock = $"{stock:0.##}",
                            RateValue = salePrice,
                            MrpValue = mrp,
                            StockValue = stock,
                            TaxPerc = tax,
                            PackageSize = packageSize,
                            MaxQty = packageSize > 0 ? System.Math.Floor(stock / packageSize) : stock
                        }
                    });
                }

                return variants.Count > 0 ? variants : null;
            }
            catch { return null; }
        }

        // ═══════ USER SECTION CLICK ═══════

        private void BtnUserSection_Click(object sender, MouseButtonEventArgs e)
        {
            bool isAdmin = _currentUser != null &&
                string.Equals(_currentUser.Role, "Admin", StringComparison.OrdinalIgnoreCase);

            if (isAdmin)
            {
                // Admin → back to dashboard
                Shutdown();
                _dashboard?.ShowDashboard();
            }
            else
            {
                // Salesman/Operator → Exit confirmation
                var result = MessageBox.Show(
                    $"Logged in as: {lblSalesman.Text}\n\nDo you want to logout and exit?",
                    "Exit / Logout",
                    MessageBoxButton.YesNo,
                    MessageBoxImage.Question);
                if (result == MessageBoxResult.Yes)
                {
                    Shutdown();
                    var auth = new AuthService(_db);
                    auth.ClearSession();
                    var login = new LoginWindow();
                    login.Show();
                    Window.GetWindow(this)?.Close();
                }
            }
        }

        private string GetCounterName(int userId)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT o.name
                                   FROM employees e
                                   JOIN outlets o ON o.id = CAST(e.outlet_id AS INTEGER)
                                   WHERE e.id = @uid AND e.outlet_id IS NOT NULL AND e.outlet_id <> ''
                                   LIMIT 1";
                cmd.Parameters.AddWithValue("@uid", userId);
                var result = cmd.ExecuteScalar();
                return result?.ToString() ?? "";
            }
            catch { return ""; }
        }

        // Is machine/counter ka unique code (C1, C2... outlet id se) — invoice number me use hota hai
        private string GetCounterCode() => InvoiceNumberService.GetCounterCode(_db, _currentUser?.Id);

        // Cloud (POSController::generateSaleNo) jaisa: SALE-{year}-{counter}-{000001}
        // Local DB ka max number dekhkar next nikalta hai — har counter ki apni series, kabhi duplicate nahi
        private string GenerateSaleNo() => InvoiceNumberService.GenerateSaleNo(_db, _currentUser?.Id);

        private void StartClock()
        {
            _clockTimer.Interval = TimeSpan.FromSeconds(1);
            _clockTimer.Tick += (s, e) =>
            {
                lblDate.Text = DateTime.Now.ToString("ddd, dd MMM yyyy");
                lblTime.Text = DateTime.Now.ToString("hh:mm:ss tt");
            };
            _clockTimer.Start();
            lblDate.Text = DateTime.Now.ToString("ddd, dd MMM yyyy");
            lblTime.Text = DateTime.Now.ToString("hh:mm:ss tt");
        }

        private void StartNetCheck()
        {
            _netCheckTimer.Interval = TimeSpan.FromSeconds(30); // was 5s — UI thread waste
            _netCheckTimer.Tick += (s, e) => CheckConnectivity();
            _netCheckTimer.Start();
            CheckConnectivity();
        }

        private void CheckConnectivity()
        {
            try
            {
                bool online = NetworkInterface.GetIsNetworkAvailable();
                if (online == _lastOnlineStatus) return;
                _lastOnlineStatus = online;

                if (online)
                {
                    if (statusDot != null) statusDot.Fill = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#16A34A"));
                    if (lblOnlineStatus != null) { lblOnlineStatus.Text = "POS Online"; lblOnlineStatus.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#16A34A")); }
                }
                else
                {
                    if (statusDot != null) statusDot.Fill = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#DC2626"));
                    if (lblOnlineStatus != null) { lblOnlineStatus.Text = "POS Offline"; lblOnlineStatus.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#DC2626")); }
                }
            }
            catch { }
        }

        private void BtnResync_Click(object sender, MouseButtonEventArgs e)
        {
            try
            {
                _dashboard?.TriggerSync();
                if (lblSyncIcon != null) lblSyncIcon.Text = "⟳";
                if (lblSyncStatus != null) { lblSyncStatus.Text = "Syncing..."; lblSyncStatus.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#D97706")); }
                if (syncStatusBorder != null) syncStatusBorder.Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#FFFBEB"));

                // Reset after 3 seconds
                var resetTimer = new DispatcherTimer { Interval = TimeSpan.FromSeconds(3) };
                resetTimer.Tick += (s, ev) =>
                {
                    resetTimer.Stop();
                    if (lblSyncIcon != null) lblSyncIcon.Text = "🔄";
                    if (lblSyncStatus != null) { lblSyncStatus.Text = "Synced"; lblSyncStatus.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#16A34A")); }
                    if (syncStatusBorder != null) syncStatusBorder.Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#F0FDF4"));
                };
                resetTimer.Start();
            }
            catch { }
        }

        /// <summary>Top-left branch label + user-card outlet name — current selected outlet se.</summary>
        private void LoadOutletLabels()
        {
            try
            {
                int outletId = Services.OutletContext.GetSelectedOutletId(_db);
                string outletName = Services.OutletContext.GetOutletName(outletId);

                if (lblBranchName != null)
                    lblBranchName.Text = outletName;
                if (lblOutletName != null)
                    lblOutletName.Text = outletName;
            }
            catch (Exception ex) { Services.LogService.Error("LoadOutletLabels failed", ex); }
        }

        private void POSPage_Loaded(object sender, RoutedEventArgs e)
        {
            txtCustomer.Focus();
        }

        // ═══════ EXCEL-STYLE GRID NAVIGATION ═══════
        // ↑↓ move rows | ←→ enter qty mode | Enter open qty popup | Del remove line | Esc exit qty mode

        private void POS_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            // Don't intercept keys when customer cells (No. / Name) have focus
            if (txtCustomer != null && txtCustomer.IsFocused) return;
            if (txtCustomerName != null && txtCustomerName.IsFocused) return;

            if (e.Key == Key.Up || e.Key == Key.Down ||
                e.Key == Key.Left || e.Key == Key.Right ||
                e.Key == Key.Enter || e.Key == Key.Escape ||
                e.Key == Key.Delete ||
                (e.Key == Key.D && (Keyboard.Modifiers & ModifierKeys.Control) == ModifierKeys.Control))
            {
                HandleGridKey(e);
            }
        }

        private void DgCart_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            HandleGridKey(e);
        }

        private void HandleGridKey(KeyEventArgs e)
        {
            if (_cart.Count == 0)
            {
                if (e.Key == Key.Left || e.Key == Key.Right || e.Key == Key.Enter)
                    e.Handled = true;
                return;
            }

            switch (e.Key)
            {
                case Key.Up:
                    SetQtyMode(-1);
                    if (dgCart.SelectedIndex > 0)
                        dgCart.SelectedIndex--;
                    else if (dgCart.SelectedIndex < 0 && _cart.Count > 0)
                        dgCart.SelectedIndex = _cart.Count - 1;
                    dgCart.ScrollIntoView(dgCart.SelectedItem);
                    e.Handled = true;
                    break;
                case Key.Down:
                    SetQtyMode(-1);
                    if (dgCart.SelectedIndex < _cart.Count - 1)
                        dgCart.SelectedIndex++;
                    else if (dgCart.SelectedIndex < 0 && _cart.Count > 0)
                        dgCart.SelectedIndex = 0;
                    dgCart.ScrollIntoView(dgCart.SelectedItem);
                    e.Handled = true;
                    break;

                case Key.Left:
                case Key.Right:
                    if (dgCart.SelectedIndex < 0)
                        dgCart.SelectedIndex = _cart.Count - 1;
                    if (dgCart.SelectedIndex >= 0 && dgCart.SelectedIndex < _cart.Count)
                    {
                        SetQtyMode(dgCart.SelectedIndex);
                    }
                    e.Handled = true;
                    break;

                case Key.Enter:
                    {
                        int targetRow = _qtyEditRow >= 0 ? _qtyEditRow : dgCart.SelectedIndex;
                        if (targetRow >= 0 && targetRow < _cart.Count)
                        {
                            e.Handled = true;
                            var selectedItem = _cart[targetRow];
                            if (selectedItem.HasScheme)
                            {
                                ExitSchemePrompt(targetRow);
                            }
                            else
                            {
                                // No popup — Enter = qty +1 inline
                                IncrementCartQty(targetRow, +1);
                            }
                        }
                    }
                    break;

                case Key.OemMinus:
                case Key.Subtract:
                    {
                        int targetRow = _qtyEditRow >= 0 ? _qtyEditRow : dgCart.SelectedIndex;
                        if (targetRow >= 0 && targetRow < _cart.Count)
                        {
                            e.Handled = true;
                            IncrementCartQty(targetRow, -1);
                        }
                    }
                    break;

                case Key.Escape:
                    if (_qtyEditRow >= 0)
                    {
                        SetQtyMode(-1);
                        e.Handled = true;
                    }
                    break;

                case Key.Delete:
                case Key.D when (Keyboard.Modifiers & ModifierKeys.Control) == ModifierKeys.Control:
                    e.Handled = true;
                    DeleteSelectedLine();
                    SetQtyMode(-1);
                    FocusGrid();
                    break;
            }
        }

        private void SetQtyMode(int index)
        {
            _qtyEditRow = index;
            for (int i = 0; i < _cart.Count; i++)
                _cart[i].IsQtyMode = (i == index);
            UpdateGridHint();
        }

        private void DgCart_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            // Keep the selected row in view while navigating with arrows
            if (dgCart.SelectedItem != null)
                dgCart.ScrollIntoView(dgCart.SelectedItem);
        }

        private void DgCart_MouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            // Mouse click: clear qty mode so arrows navigate again
            SetQtyMode(-1);
        }

        private void UpdateGridHint()
        {
            if (lblGridHint == null) return;
            if (_qtyEditRow >= 0 && _qtyEditRow < _cart.Count)
            {
                var it = _cart[_qtyEditRow];
                lblGridHint.Text = $"QTY MODE: {it.ItemName} ×{it.Qty:0.##} — Enter = change, Esc = exit";
            }
            else
            {
                lblGridHint.Text = _cart.Count > 0 ? $"{_cart.Count} items — Enter = qty" : "";
            }
        }

        private void FocusGrid()
        {
            if (dgCart != null)
            {
                dgCart.Focus();
                if (dgCart.SelectedIndex < 0 && _cart.Count > 0)
                    dgCart.SelectedIndex = 0;
                Keyboard.Focus(dgCart);
            }
        }

        private void IncrementCartQty(int index, double delta)
        {
            if (index < 0 || index >= _cart.Count) return;
            var item = _cart[index];
            double newQty = item.Qty + delta;
            if (newQty <= 0)
            {
                _cart.Remove(item);
                ReNumberCart();
                UpdateTotals();
                return;
            }
            double stock = GetItemStock(item.ItemCode);
            if (delta > 0 && newQty > Math.Max(stock, item.Qty))
                newQty = Math.Max(stock, item.Qty); // cap silently
            item.Qty = newQty;
            item.Amount = Math.Round(item.Price * newQty, 2);
            item.TotDisc = Math.Round(item.Disc * newQty, 2);
            item.TaxableAmount = _isTaxInclusive && item.TaxPerc > 0
                ? Math.Round(item.Amount / (1 + item.TaxPerc / 100.0), 2)
                : item.Amount;
            UpdateTotals();
            dgCart.SelectedIndex = index;
            FocusGrid();
        }

        private void OpenQtyForRow(int index)
        {
            if (index < 0 || index >= _cart.Count) return;
            var item = _cart[index];

            double stock = GetItemStock(item.ItemCode);
            double maxQty = Math.Max(stock, item.Qty);
            double inCart = item.Qty;

            var qtyWin = new QuantityWindow(item.ItemName, item.ItemCode, item.Price, item.Unit, stock, maxQty, 1, inCart)
            {
                Owner = Window.GetWindow(this)
            };

            if (qtyWin.ShowDialog() == true)
            {
                if (qtyWin.Quantity <= 0)
                {
                    _cart.Remove(item);
                    ReNumberCart();
                }
                else
                {
                    item.Qty = qtyWin.Quantity;
                    item.Amount = Math.Round(item.Price * item.Qty, 2);
                    item.TotDisc = Math.Round(item.Disc * item.Qty, 2);
                    item.TaxableAmount = _isTaxInclusive && item.TaxPerc > 0
                        ? Math.Round(item.Amount / (1 + item.TaxPerc / 100.0), 2)
                        : item.Amount;
                    UpdateTotals();
                }
                if (_cart.Count > 0)
                {
                    dgCart.SelectedIndex = Math.Min(index, _cart.Count - 1);
                    FocusGrid();
                }
            }
            else
            {
                FocusGrid();
            }
            SetQtyMode(-1);
        }

        private double GetItemStock(string code)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT IFNULL(stock_quantity,0) FROM items WHERE lower(code)=lower(@c) LIMIT 1";
                cmd.Parameters.AddWithValue("@c", code);
                var r = cmd.ExecuteScalar();
                return r != null ? Convert.ToDouble(r) : 0;
            }
            catch (Exception ex) { Services.LogService.Error("GetItemStock failed", ex); return 0; }
        }

        // ═══════ KEYBOARD SHORTCUTS ═══════

        protected override void OnPreviewKeyDown(KeyEventArgs e)
        {
            switch (e.Key)
            {
                case Key.C when (Keyboard.Modifiers & ModifierKeys.Control) == ModifierKeys.Control:
                    OpenCheckScheme(); e.Handled = true; break;
                case Key.H when (Keyboard.Modifiers & ModifierKeys.Control) == ModifierKeys.Control:
                    OpenHoldList(); e.Handled = true; break;
                case Key.S when (Keyboard.Modifiers & ModifierKeys.Control) == ModifierKeys.Control:
                    e.Handled = true; break; // Ctrl+S disabled for POS user
                case Key.K when (Keyboard.Modifiers & ModifierKeys.Control) == ModifierKeys.Control:
                    OpenCouponPopup(); e.Handled = true; break;
                case Key.K when Keyboard.Modifiers == ModifierKeys.Alt:
                    OpenShiftSettlement(); e.Handled = true; break;
                case Key.P when (Keyboard.Modifiers & ModifierKeys.Control) == ModifierKeys.Control:
                    e.Handled = true; break; // Ctrl+P disabled
                case Key.F8:
                    ReprintLastInvoice(); e.Handled = true; break;
                case Key.F11:
                    OpenShiftDialog(); e.Handled = true; break;
            }
            if (!e.Handled) base.OnKeyDown(e);
        }

        // ═══════ SIDEBAR MOUSE CLICK HANDLERS ═══════

        private void BtnListItem_Click(object sender, MouseButtonEventArgs e) => OpenItemList();
        private void BtnAddItem_Click(object sender, MouseButtonEventArgs e) => OpenItemList();
        private void BtnAddCustomer_Click(object sender, MouseButtonEventArgs e) => OpenCustomerWindow();
        private void BtnCalc_Click(object sender, MouseButtonEventArgs e) => OpenCalculator();
        private void BtnPayment_Click(object sender, MouseButtonEventArgs e) => OpenPaymentWindow();
        private void BtnQuickCash_Click(object sender, MouseButtonEventArgs e) => QuickCash();
        private void BtnRegister_Click(object sender, MouseButtonEventArgs e) => OpenRegister();
        private void BtnShift_Click(object sender, MouseButtonEventArgs e) => OpenShiftDialog();
        private void BtnReturn_Click(object sender, MouseButtonEventArgs e) => OpenReturnWindow();
        private void BtnRePrint_Click(object sender, MouseButtonEventArgs e) => RePrint();
        private void BtnSendMsg_Click(object sender, MouseButtonEventArgs e) => SendLastBill();
        private void BtnHoldVoucher_Click(object sender, MouseButtonEventArgs e) => HoldCurrentBill();
        private void BtnOpenHoldList_Click(object sender, MouseButtonEventArgs e) => OpenHoldList();
        // BtnOpenSaleList_Click removed — sale list hidden from POS user
        private void BtnOpenReturnList_Click(object sender, MouseButtonEventArgs e) => OpenReturnList();
        private void BtnCheckScheme_Click(object sender, MouseButtonEventArgs e) => OpenCheckScheme();
        private void BtnApplyCoupon_Click(object sender, MouseButtonEventArgs e) => OpenCouponPopup();

        // ═══════ CORE ACTIONS ═══════

        public void OpenItemList()
        {
            var window = new ItemLookupWindow(GetCartQtys()) { Owner = Window.GetWindow(this) };
            if (window.ShowDialog() == true && window.SelectedItem != null)
            {
                ApplyCartItem(window.SelectedItem, window.SelectedQuantity);
                SetQtyMode(-1);
                dgCart.SelectedIndex = 0;
                FocusGrid();
            }
        }

        public void OpenCustomerWindow()
        {
            var window = new CustomerWindow { Owner = Window.GetWindow(this) };
            if (window.ShowDialog() == true && window.SelectedCustomer != null)
            {
                _currentCustomer = window.SelectedCustomer;
                UpdateBottomCustomer();
                ShowInfo($"Customer: {(string.IsNullOrEmpty(_currentCustomer.Name) ? _currentCustomer.Phone : _currentCustomer.Name)}\nMobile: {_currentCustomer.Phone}\nWallet: ₹{_currentCustomer.WalletBalance:N2}\nLoyalty: {_currentCustomer.LoyaltyPoints:N0} pts");
            }
        }

        private void UpdateBottomCustomer()
        {
            bool registered = _currentCustomer != null && _currentCustomer.Id > 1;

            if (lblF2Customer != null)
            {
                string name = string.IsNullOrEmpty(_currentCustomer?.Name) ? (_currentCustomer?.Phone ?? "") : _currentCustomer!.Name;
                lblF2Customer.Text = registered ? $"👤 {name}" : "F2 Add Customer";
            }

            if (!registered)
            {
                UpdateCustomerInfoCells();
                return;
            }

            // Refresh wallet + loyalty from DB (wallet/loyalty cells ke liye)
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT COALESCE(c.loyalty_point,0) AS lp, COALESCE(w.balance,0) AS wb
                    FROM customers c LEFT JOIN customer_wallets w ON w.customer_id=c.id
                    WHERE c.id=@id LIMIT 1";
                cmd.Parameters.AddWithValue("@id", _currentCustomer.Id);
                using var r = cmd.ExecuteReader();
                if (r.Read())
                {
                    _currentCustomer.LoyaltyPoints = Convert.ToDouble(r["lp"]);
                    _currentCustomer.WalletBalance = Convert.ToDouble(r["wb"]);
                }
            }
            catch { }
            UpdateCustomerInfoCells();
        }

        private void ResetCustomerAfterSale()
        {
            _currentCustomer = new CustomerInfo { Id = 1, Name = "Walk-in Customer" };
            if (lblF2Customer != null) lblF2Customer.Text = "F2 Add Customer";
            if (txtCustomer != null) txtCustomer.Clear();
            if (txtCustomerPlaceholder != null) txtCustomerPlaceholder.Visibility = Visibility.Visible;
            UpdateCustomerInfoCells();
            HideCustomerMatch();
        }

        // ═══════ F7 HOLD VOUCHER ═══════

        public void HoldCurrentBill()
        {
            if (_cart.Count == 0) { ShowInfo("Cart is empty. Add items first."); return; }

            // If no customer selected (still walk-in id=1), ask to add customer
            if (_currentCustomer == null || _currentCustomer.Id == 1)
            {
                var res = MessageBox.Show(
                    "No customer selected.\nDo you want to add a customer before holding?",
                    "Hold Voucher", MessageBoxButton.YesNo, MessageBoxImage.Question);
                if (res == MessageBoxResult.Yes)
                {
                    var win = new CustomerWindow { Owner = Window.GetWindow(this) };
                    if (win.ShowDialog() == true && win.SelectedCustomer != null)
                    {
                        _currentCustomer = win.SelectedCustomer;
                        UpdateBottomCustomer();
                    }
                }
            }

            // Create hold bill
            double total = CalculateGrandTotal();
            var held = new HeldBill
            {
                CustomerName = _currentCustomer?.Name ?? "Walk-in Customer",
                CustomerPhone = _currentCustomer?.Phone ?? "",
                CustomerId = (int)(_currentCustomer?.Id ?? 1),
                HeldAt = DateTime.Now,
                Items = new List<BusyCartItem>(_cart),
                GrandTotal = total
            };
            _heldBills.Add(held);
            SaveHeldBillToDb(held);

            // Update badge count
            UpdateHoldBadge();

            // Clear cart
            _cart.Clear(); _srNo = 0; _schemeApplied = false; _schemeAppliedTitle = ""; _couponDiscount = 0; _couponCode = "";
            _currentCustomer = new CustomerInfo { Id = 1, Name = "Walk-in Customer" };
            UpdateBottomCustomer();
            UpdateTotals();

            // Popup message
            MessageBox.Show(
                $"✅ Bill Held Successfully!\n\n" +
                $"Customer: {held.CustomerName}\n" +
                $"Items: {held.Items.Count}  |  Total: ₹{held.GrandTotal:N2}\n\n" +
                $"Total holds: {_heldBills.Count}  |  Press Ctrl+H to view hold list.",
                "Bill On Hold", MessageBoxButton.OK, MessageBoxImage.Information);
        }

        private void OpenHoldList()
        {
            var dlg = new HoldVoucherDialog(_heldBills, _db) { Owner = Window.GetWindow(this) };
            if (dlg.ShowDialog() == true && dlg.ResumedBill != null)
                RestoreHeldBill(dlg.ResumedBill);
            UpdateHoldBadge();
            Focus();
        }

        private void OpenSaleList()
        {
            var dlg = new SaleListDialog { Owner = Window.GetWindow(this) };
            dlg.ShowDialog();
            Focus();
        }

        private void ReprintLastInvoice()
        {
            long saleId = _lastSavedSaleId;
            // Fallback: get last sale from DB if no in-memory sale
            if (saleId <= 0)
            {
                try
                {
                    using var conn = _db.GetConnection();
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = "SELECT id FROM sales WHERE (del_status IS NULL OR del_status='Live') ORDER BY id DESC LIMIT 1";
                    var val = cmd.ExecuteScalar();
                    if (val != null) saleId = Convert.ToInt64(val);
                }
                catch { }
            }
            if (saleId <= 0)
            {
                ShowInfo("No invoice to reprint. Make a sale first.");
                return;
            }
            string temp = System.IO.Path.Combine(System.IO.Path.GetTempPath(), "Sale_" + saleId + "_" + DateTime.Now.Ticks + ".pdf");
            Services.PdfService.GenerateSalePdf(saleId, temp);
        }

        private void OpenReturnList()
        {
            var dlg = new ReturnListDialog(_dashboard) { Owner = Window.GetWindow(this) };
            dlg.ShowDialog();
            Focus();
        }

        // Called by HoldVoucherPage to restore a bill back into cart
        public void RestoreHeldBill(HeldBill bill)
        {
            _cart.Clear(); _srNo = 0; _schemeApplied = false; _schemeAppliedTitle = ""; _couponDiscount = 0; _couponCode = "";
            foreach (var item in bill.Items)
            {
                _srNo++;
                item.SrNo = _srNo;
                _cart.Add(item);
            }
            _currentCustomer = new CustomerInfo
            {
                Id = bill.CustomerId,
                Name = bill.CustomerName,
                Phone = bill.CustomerPhone
            };
            UpdateBottomCustomer();
            UpdateTotals();
            DeleteHeldBillFromDb(bill);
            UpdateHoldBadge();
        }

        // Called by HoldVoucherPage to navigate back to POS view
        public void ShowPOSView()
        {
            if (_dashboard != null)
                _dashboard.ShowPage(this);
        }

        private void UpdateHoldBadge()
        {
            int count = _heldBills.Count;
            // Only bottom bar badge shows count — sidebar F7 button has no count
            if (holdListCountBadge != null)
                holdListCountBadge.Visibility = count > 0 ? Visibility.Visible : Visibility.Collapsed;
            if (lblHoldListCount != null) lblHoldListCount.Text = count.ToString();
        }

        // ═══════ HELD BILL DB PERSISTENCE ═══════

        /// <summary>Load all held bills from SQLite on app startup.</summary>
        private void LoadHeldBillsFromDb()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, customer_id, customer_name, customer_phone, items_json, held_at, note FROM held_bills ORDER BY id";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    var bill = new HeldBill
                    {
                        Id = r.GetInt64(0),
                        CustomerId = r.IsDBNull(1) ? 1 : r.GetInt32(1),
                        CustomerName = r.IsDBNull(2) ? "Walk-in Customer" : r.GetString(2),
                        CustomerPhone = r.IsDBNull(3) ? "" : r.GetString(3),
                        HeldAt = r.IsDBNull(5) ? DateTime.Now : DateTime.TryParse(r.GetString(5), out var dt) ? dt : DateTime.Now
                    };

                    // Deserialize items from JSON
                    string itemsJson = r.IsDBNull(4) ? "[]" : r.GetString(4);
                    try
                    {
                        var items = System.Text.Json.JsonSerializer.Deserialize<List<HeldBillItemDto>>(itemsJson);
                        if (items != null)
                        {
                            bill.Items = items.Select(dto => new BusyCartItem
                            {
                                SrNo = dto.SrNo,
                                ItemCode = dto.ItemCode ?? "",
                                ItemName = dto.ItemName ?? "",
                                HSNCode = dto.HSNCode ?? "",
                                MRP = dto.MRP,
                                Qty = dto.Qty,
                                Unit = dto.Unit ?? "",
                                ListPrice = dto.ListPrice,
                                Disc = dto.Disc,
                                TotDisc = dto.TotDisc,
                                Price = dto.Price,
                                Amount = dto.Amount,
                                TaxPerc = dto.TaxPerc,
                                TaxableAmount = dto.TaxableAmount,
                                HasScheme = dto.HasScheme,
                                AppliedSchemeText = dto.AppliedSchemeText ?? ""
                            }).ToList();
                        }
                    }
                    catch { bill.Items = new List<BusyCartItem>(); }

                    bill.GrandTotal = bill.Items.Sum(i => i.Amount - i.TotDisc);
                    _heldBills.Add(bill);
                }
            }
            catch { }
            UpdateHoldBadge();
        }

        /// <summary>Persist a held bill to SQLite. Sets bill.Id to the new row id.</summary>
        private void SaveHeldBillToDb(HeldBill bill)
        {
            try
            {
                // Serialize cart items to JSON
                var dtos = bill.Items.Select(i => new HeldBillItemDto
                {
                    SrNo = i.SrNo,
                    ItemCode = i.ItemCode,
                    ItemName = i.ItemName,
                    HSNCode = i.HSNCode,
                    MRP = i.MRP,
                    Qty = i.Qty,
                    Unit = i.Unit,
                    ListPrice = i.ListPrice,
                    Disc = i.Disc,
                    TotDisc = i.TotDisc,
                    Price = i.Price,
                    Amount = i.Amount,
                    TaxPerc = i.TaxPerc,
                    TaxableAmount = i.TaxableAmount,
                    HasScheme = i.HasScheme,
                    AppliedSchemeText = i.AppliedSchemeText
                }).ToList();
                string itemsJson = System.Text.Json.JsonSerializer.Serialize(dtos);

                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"INSERT INTO held_bills (customer_id, customer_name, customer_phone, items_json, held_at, note)
                    VALUES (@cid, @cname, @cphone, @items, @held, @note)";
                cmd.Parameters.AddWithValue("@cid", bill.CustomerId);
                cmd.Parameters.AddWithValue("@cname", bill.CustomerName);
                cmd.Parameters.AddWithValue("@cphone", bill.CustomerPhone ?? "");
                cmd.Parameters.AddWithValue("@items", itemsJson);
                cmd.Parameters.AddWithValue("@held", bill.HeldAt.ToString("yyyy-MM-dd HH:mm:ss"));
                cmd.Parameters.AddWithValue("@note", (object?)null ?? DBNull.Value);
                cmd.ExecuteNonQuery();

                // Get the inserted row id
                using var idCmd = conn.CreateCommand();
                idCmd.CommandText = "SELECT last_insert_rowid()";
                bill.Id = (long)idCmd.ExecuteScalar();
            }
            catch { }
        }

        /// <summary>Delete a held bill from SQLite by its DB id.</summary>
        private void DeleteHeldBillFromDb(HeldBill bill)
        {
            if (bill.Id <= 0) return;
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "DELETE FROM held_bills WHERE id = @id";
                cmd.Parameters.AddWithValue("@id", bill.Id);
                cmd.ExecuteNonQuery();
            }
            catch { }
        }

        public void OpenCalculator()
        {
            new CalculatorWindow { Owner = Window.GetWindow(this) }.ShowDialog();
        }

        public void OpenPaymentWindow()
        {
            if (!EnsureRegisterOpen()) return;
            if (_cart.Count == 0) { ShowInfo("Cart is empty. Add items first."); return; }
            var window = new PaymentWindow(CalculateGrandTotal(), _currentCustomer) { Owner = Window.GetWindow(this) };
            if (window.ShowDialog() == true && window.Payments.Count > 0)
                SaveBillCore(window.Payments, window.PrimaryMode, window.ChangeAmount);
        }

        public void QuickCash()
        {
            if (!EnsureRegisterOpen()) return;
            if (_cart.Count == 0) { ShowInfo("Cart is empty. Add items first."); return; }
            // SaveBillCore handles the cash collection update (via the Cash payment) — do NOT add here or it double-counts.
            SaveBillInternal("Cash");
            RePrint();
        }

        public void OpenReturnWindow()
        {
            var dlg = new SaleReturnDialog(_dashboard) { Owner = Window.GetWindow(this) };
            dlg.ShowDialog();
            Focus();
        }

        // F10 — Cash Register. Register band ho to COMPACT opening-cash popup
        // (Cash/UPI/Card, Up/Down navigation, Enter = open). Open ho to kuch nahi.
        public void OpenRegister()
        {
            if (IsRegisterOpen())
            {
                Focus();
                return;
            }

            var dlg = new OpenRegisterDialog(_db, _currentUser) { Owner = Window.GetWindow(this) };
            dlg.ShowDialog();
            Focus();
        }

        // Alt+K — Day Close / Shift Settlement (Z-Report)
        // Cashier submitted cash / advances / vouchers fill karta hai,
        // real-time shortage/excess dikhta hai, print par thermal PDF.
        public void OpenShiftSettlement()
        {
            var dlg = new ShiftSettlementDialog(_db, _currentUser) { Owner = Window.GetWindow(this) };
            dlg.ShowDialog();
            Focus();
        }

        // F11 — Shift Close / Change Shift popup
        public void OpenShiftDialog()
        {
            // F11 GUARD: register open nahi hai (F10 se open nahi kiya) to
            // Close Register ka popup NAHI khulega — pehle F10 se register open karo
            if (!IsRegisterOpen())
            {
                MessageBox.Show(
                    "Register band hai — Close Register karne ke liye pehle F10 se Register open karein (opening cash enter karein).",
                    "Register Required",
                    MessageBoxButton.OK, MessageBoxImage.Warning);
                Focus();
                return;
            }

            // ═══ COMPACT CLOSE REGISTER FLOW ═══
            // Up/Down navigation, value entry, Enter → register close + Z-Report print.
            // Counter person ko expected/match kuch nahi dikhta — sab printout par.
            var dlg = new CloseRegisterDialog(_db, _currentUser) { Owner = Window.GetWindow(this) };
            dlg.ShowDialog();

            if (!dlg.Printed || dlg.Settlement == null) { Focus(); return; }

            // Register ab close karo — closing time + submitted amount DB me
            CloseRegisterInDb(dlg.Settlement);

            Focus();
        }

        /// <summary>Register close — register_status=0 + closing balance/time + settlement snapshot.</summary>
        private void CloseRegisterInDb(ShiftSettlementService.SettlementData? s)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"UPDATE registers SET
                        register_status=0,
                        closing_balance=@cb,
                        closing_balance_date_time=@cdt,
                        updated_at=datetime('now')
                    WHERE user_id=@u AND outlet_id=1 AND company_id=@c
                      AND del_status='Live' AND register_status=1";
                cmd.Parameters.AddWithValue("@cb", s?.CashierSubmittedCash ?? 0);
                cmd.Parameters.AddWithValue("@cdt", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
                cmd.Parameters.AddWithValue("@u", _currentUser?.Id ?? 1);
                cmd.Parameters.AddWithValue("@c", _currentUser?.CompanyId ?? 1);
                cmd.ExecuteNonQuery();
            }
            catch (Exception ex) { Services.LogService.Error("CloseRegisterInDb failed", ex); }
        }

        /// <summary>Checks if register is currently open for this user.</summary>
        private bool IsRegisterOpen()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT COUNT(*) FROM registers
                    WHERE user_id=@u AND outlet_id=1 AND company_id=@c
                      AND del_status='Live' AND register_status=1";
                cmd.Parameters.AddWithValue("@u", _currentUser?.Id ?? 1);
                cmd.Parameters.AddWithValue("@c", _currentUser?.CompanyId ?? 1);
                var count = cmd.ExecuteScalar();
                return count != null && Convert.ToInt64(count) > 0;
            }
            catch { return false; }
        }

        /// <summary>If register not open, shows warning and opens register dialog. Returns true if register is open.</summary>
        private bool EnsureRegisterOpen()
        {
            if (IsRegisterOpen()) return true;

            var res = MessageBox.Show(
                "Register band hai — billing shuru karne ke liye pehle Register open karein.\n\nAbhi Register open karein?",
                "Register Required",
                MessageBoxButton.YesNo,
                MessageBoxImage.Warning,
                MessageBoxResult.Yes);

            if (res == MessageBoxResult.Yes)
                OpenRegister();

            return IsRegisterOpen();
        }

        // Ctrl+C — cart ke products par applicable schemes ka popup
        public void OpenCheckScheme()
        {
            // ═══ BC MEMBER CHECK: Skip schemes/promotions for Business Club members ═══
            if (_currentCustomer != null && _currentCustomer.Id > 1 && IsBusinessClubMember(_currentCustomer.Id))
            {
                ShowInfo("Business Club member — schemes/promotions are not applicable.\nProfit share will be credited automatically.");
                return;
            }

            var owner = Window.GetWindow(this);
            // Pass already-applied scheme titles so dialog shows them as "ALREADY APPLIED"
            var appliedTitles = _cart
                .Where(c => c.HasScheme && !string.IsNullOrEmpty(c.AppliedSchemeText))
                .Select(c => c.AppliedSchemeText)
                .ToList();
            var result = CheckSchemeDialog.Show(_db, _cart.ToList(), owner!, appliedTitles);
            if (result != null)
            {
                if (result.IsQuit)
                    QuitSchemeFromCart(result.ItemCodes, result.FreeItem);
                else
                    ApplySchemeResult(result);
            }
            Focus();
        }

        /// <summary>
        /// Item add hote hi silently check karo — agar applicable scheme mili to auto-apply.
        /// BC members ke liye skip. Already applied schemes dobara nahi lagate.
        /// </summary>
        private void TryAutoApplyScheme()
        {
            try
            {
                // BC member = no promotions
                if (_currentCustomer != null && _currentCustomer.Id > 1 && IsBusinessClubMember(_currentCustomer.Id))
                    return;

                // Already applied scheme titles collect karo
                var appliedTitles = _cart
                    .Where(c => c.HasScheme && !string.IsNullOrEmpty(c.AppliedSchemeText))
                    .Select(c => c.AppliedSchemeText)
                    .ToList();

                var result = CheckSchemeDialog.AutoApply(_db, _cart.ToList(), appliedTitles);
                if (result == null) return;

                // Tier pricing wali scheme — lowest tier auto-select (no popup)
                if (!string.IsNullOrWhiteSpace(result.TierPricing))
                {
                    // Tier schemes ke liye manual Ctrl+C use karo (customer choice chahiye)
                    // Sirf toast dikhao
                    ShowInfo($"🏷️ Scheme available: {result.Title} — Ctrl+C dabao ya apply karein");
                    return;
                }

                // Direct apply — free item ya discount
                ApplySchemeResult(result);

                // Cart mein scheme lag gayi — toast dikhao
                ShowInfo($"✔ Auto-applied: {result.Title}");
            }
            catch { }
        }

        // Ctrl+K — Coupon code apply popup
        public void OpenCouponPopup()
        {
            // ═══ BC MEMBER CHECK: Skip coupons for Business Club members ═══
            if (_currentCustomer != null && _currentCustomer.Id > 1 && IsBusinessClubMember(_currentCustomer.Id))
            {
                ShowInfo("Business Club member — coupons/promotions are not applicable.\nProfit share will be credited automatically.");
                return;
            }

            if (_cart.Count == 0 || _cart.All(c => string.IsNullOrEmpty(c.ItemCode)))
            {
                ShowInfo("Pehle cart me items add karein — phir coupon apply karein.");
                return;
            }
            var owner = Window.GetWindow(this);
            var win = new CouponWindow(_db, _cart.ToList()) { Owner = owner };
            if (win.ShowDialog() == true && win.Result != null)
            {
                var r = win.Result;
                // Determine which cart items the coupon applies to
                // If ApplicableItems is set (comma-separated item IDs), filter cart items
                double subtotal;
                if (!string.IsNullOrWhiteSpace(r.ApplicableItems))
                {
                    var applicableIds = r.ApplicableItems
                        .Split(',', StringSplitOptions.RemoveEmptyEntries)
                        .Select(id => id.Trim())
                        .Where(id => !string.IsNullOrEmpty(id))
                        .ToHashSet(StringComparer.OrdinalIgnoreCase);

                    // Match cart items by item ID (lookup from DB) or by item code
                    subtotal = _cart
                        .Where(c => !string.IsNullOrEmpty(c.ItemCode) && IsItemApplicable(c.ItemCode, applicableIds))
                        .Sum(c => c.Amount);
                }
                else
                {
                    // Bill-level: apply to full cart
                    subtotal = _cart.Sum(c => c.Amount);
                }

                double couponAmt = 0;
                if (r.DiscountKind == "percentage")
                    couponAmt = Math.Round(subtotal * r.DiscountAmt / 100.0, 2);
                else
                    couponAmt = Math.Round(Math.Min(r.DiscountAmt, subtotal), 2);

                // Enforce MaxDiscount cap
                if (r.MaxDiscount > 0)
                    couponAmt = Math.Min(couponAmt, r.MaxDiscount);

                _couponDiscount = couponAmt;
                _couponCode = r.Title;
                UpdateTotals();
            }
            Focus();
        }

        /// <summary>
        /// Check if a cart item (by code) matches any of the applicable item IDs from the coupon.
        /// Matches by item ID (looked up from DB) or by item code directly.
        /// </summary>
        private bool IsItemApplicable(string itemCode, HashSet<string> applicableIds)
        {
            // Direct code match
            if (applicableIds.Contains(itemCode)) return true;

            // Lookup item ID from DB and check
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id FROM items WHERE lower(code)=lower(@c) LIMIT 1";
                cmd.Parameters.AddWithValue("@c", itemCode);
                var result = cmd.ExecuteScalar();
                if (result != null)
                {
                    string idStr = result.ToString() ?? "";
                    return applicableIds.Contains(idStr);
                }
            }
            catch { }
            return false;
        }

        /// <summary>Removes scheme effects only for the specified scheme (called when user quits from dialog)</summary>
        private void QuitSchemeFromCart(List<string> schemeCodes, FreeItemInfo? freeItem = null)
        {
            // 1) Remove FREE items added by this scheme
            // Free item could be a different product (e.g. Surf Excel free with Atta purchase)
            for (int i = _cart.Count - 1; i >= 0; i--)
            {
                var c = _cart[i];
                if (!c.HasScheme || c.Price != 0 || !c.AppliedSchemeText.StartsWith("✔ FREE")) continue;

                bool shouldRemove = false;
                // Match by free item code if provided
                if (freeItem != null && !string.IsNullOrEmpty(freeItem.Code))
                    shouldRemove = string.Equals(c.ItemCode?.Trim(), freeItem.Code.Trim(), StringComparison.OrdinalIgnoreCase);
                // Also match if free item code matches any of the scheme's buy items (buy 1 get 1 same item)
                if (!shouldRemove)
                    shouldRemove = schemeCodes.Any(sc => string.Equals(sc?.Trim(), c.ItemCode?.Trim(), StringComparison.OrdinalIgnoreCase));

                if (shouldRemove)
                    _cart.RemoveAt(i);
            }

            // 2) Restore the buy items to original price and qty
            foreach (var c in _cart)
            {
                if (!c.HasScheme || string.IsNullOrEmpty(c.ItemCode)) continue;
                if (c.AppliedSchemeText.Contains("custom price")) continue;

                bool isSchemeItem = schemeCodes.Any(sc =>
                    string.Equals(sc?.Trim(), c.ItemCode?.Trim(), StringComparison.OrdinalIgnoreCase));
                if (!isSchemeItem) continue;

                // Restore qty if tier pricing changed it
                if (c.OriginalQtyBeforeScheme > 0)
                    c.Qty = c.OriginalQtyBeforeScheme;
                c.OriginalQtyBeforeScheme = 0;

                // Restore price
                c.Disc = 0;
                c.TotDisc = 0;
                c.Price = c.ListPrice;
                c.Amount = Math.Round(c.ListPrice * c.Qty, 2);
                c.TaxableAmount = _isTaxInclusive && c.TaxPerc > 0
                    ? Math.Round(c.Amount / (1 + c.TaxPerc / 100.0), 2)
                    : c.Amount;
                c.HasScheme = false;
                c.AppliedSchemeText = "";
            }

            ReNumberCart();
            UpdateTotals();
        }

        /// <summary>Shows Quit Scheme confirmation popup. On confirm, removes only this item's scheme from cart.</summary>
        private void ExitSchemePrompt(int index)
        {
            if (index < 0 || index >= _cart.Count) return;
            var item = _cart[index];

            var res = MessageBox.Show(
                $"Scheme Quit karna chahte hain?\n\n\"{item.ItemName}\" ki scheme remove ho jayegi.\nBaad me dobara scheme apply kar sakte hain.",
                "Quit Scheme",
                MessageBoxButton.YesNo,
                MessageBoxImage.Question,
                MessageBoxResult.No);

            if (res == MessageBoxResult.Yes)
            {
                string itemCode = item.ItemCode?.Trim() ?? "";

                // If this is a FREE item (₹0), remove it and clear scheme from its parent buy item
                if (item.Price == 0 && item.AppliedSchemeText.StartsWith("✔ FREE"))
                {
                    _cart.RemoveAt(index);
                    // Also clear HasScheme from buy items that reference this free item's scheme
                    // (we don't know exact parent, so clear any HasScheme items with same scheme text pattern)
                }
                else
                {
                    // This is a buy item with scheme applied — restore it
                    string schemeText = item.AppliedSchemeText; // save before clearing

                    if (item.OriginalQtyBeforeScheme > 0)
                        item.Qty = item.OriginalQtyBeforeScheme;
                    item.OriginalQtyBeforeScheme = 0;

                    item.Disc = 0;
                    item.TotDisc = 0;
                    item.Price = item.ListPrice;
                    item.Amount = Math.Round(item.ListPrice * item.Qty, 2);
                    item.TaxableAmount = _isTaxInclusive && item.TaxPerc > 0
                        ? Math.Round(item.Amount / (1 + item.TaxPerc / 100.0), 2)
                        : item.Amount;
                    item.HasScheme = false;
                    item.AppliedSchemeText = "";

                    // Remove FREE items that belong to the same scheme (match by buy item code in tag)
                    // Free item text format: "✔ FREE — Combo Offer [ATTA-ASH-5KG]"
                    for (int i = _cart.Count - 1; i >= 0; i--)
                    {
                        var c = _cart[i];
                        if (c.HasScheme && c.Price == 0 && c.AppliedSchemeText.StartsWith("✔ FREE"))
                        {
                            // Check if the free item's tag contains this buy item's code
                            if (c.AppliedSchemeText.Contains(itemCode, StringComparison.OrdinalIgnoreCase))
                            {
                                _cart.RemoveAt(i);
                            }
                        }
                    }
                }

                ReNumberCart();
                UpdateTotals();
            }
            SetQtyMode(-1);
            FocusGrid();
        }

        // Ctrl+P / Scheme % button — selected cart line par custom % price override
        // (Buy X Get Y scheme mein agar customer ne poori qty nahi li, toh shopkeeper
        //  List Price ka apna custom % laga sakta hai, e.g. 60% ya 70%)
        private void BtnSchemePercent_Click(object sender, MouseButtonEventArgs e) => OpenSchemePercent();

        public void OpenSchemePercent()
        {
            if (dgCart.SelectedItem is not BusyCartItem item || string.IsNullOrEmpty(item.ItemCode))
            {
                ShowInfo("Pehle cart mein koi item select karein — phir Scheme % lagayein.");
                return;
            }

            var owner = Window.GetWindow(this);
            var win = new SchemePercentWindow(item.ItemName, item.ItemCode, item.ListPrice, item.Qty)
            {
                Owner = owner
            };

            if (win.ShowDialog() == true)
            {
                ApplyCustomSchemePercent(item, win.Percentage);
            }
            Focus();
        }

        // Item ka price = ListPrice × pct% — Amount/TaxableAmount recompute
        private void ApplyCustomSchemePercent(BusyCartItem item, double pct)
        {
            if (item == null || string.IsNullOrEmpty(item.ItemCode)) return;
            pct = Math.Clamp(pct, 0, 100);

            double newPrice = Math.Round(item.ListPrice * pct / 100.0, 2);
            item.Disc = Math.Round(Math.Max(0, item.ListPrice - newPrice), 2);
            item.Price = newPrice;
            item.Amount = Math.Round(newPrice * item.Qty, 2);
            item.TotDisc = Math.Round(item.Disc * item.Qty, 2);
            item.TaxableAmount = _isTaxInclusive && item.TaxPerc > 0
                ? Math.Round(item.Amount / (1 + item.TaxPerc / 100.0), 2)
                : item.Amount;
            item.HasScheme = true;
            item.AppliedSchemeText = $"✔ Scheme {pct:0.##}% — custom price (Buy X Get Y)";

            UpdateTotals();
        }

        // ═══════ TIER PRICING — Partial Qty Scheme ═══════

        /// <summary>
        /// Tier pricing: Buy 1 Get 1 FREE scheme me agar customer partial qty chahta hai,
        /// toh tier ke hisab se price lagega. custom_price = TOTAL price for that qty.
        /// e.g. qty:1 custom_price:200 → 1 item ka total ₹200
        ///      qty:2 custom_price:250 → 2 items ka total ₹250 (₹125 each)
        /// </summary>
        private void ApplyTierPricing(SchemeApplyResult r)
        {
            // Parse tiers from JSON
            List<(int qty, double customPrice, double percent)> tiers = new();
            try
            {
                var elements = System.Text.Json.JsonSerializer.Deserialize<List<System.Text.Json.JsonElement>>(r.TierPricing);
                if (elements != null)
                {
                    foreach (var el in elements)
                    {
                        int tq = el.TryGetProperty("qty", out var qv) ? qv.GetInt32() : 0;
                        double cp = el.TryGetProperty("custom_price", out var cpv) ? cpv.GetDouble() : 0;
                        double pct = el.TryGetProperty("percent", out var pv) ? pv.GetDouble() : 0;
                        if (tq > 0) tiers.Add((tq, cp, pct));
                    }
                }
            }
            catch { }

            if (tiers.Count == 0)
            {
                ApplySchemeResultNormal(r);
                return;
            }

            // Find the matched cart item
            var cartItem = _cart.FirstOrDefault(c => r.ItemCodes.Any(ic =>
                string.Equals(ic.Trim(), c.ItemCode?.Trim(), StringComparison.OrdinalIgnoreCase)));
            if (cartItem == null) { ApplySchemeResultNormal(r); return; }

            double listPrice = cartItem.ListPrice;

            // Build tier options for the popup
            var tierOptions = new List<TierOption>();
            foreach (var (qty, customPrice, percent) in tiers.OrderBy(t => t.qty))
            {
                double totalPrice = customPrice > 0 ? customPrice : Math.Round(listPrice * qty * percent / 100.0, 2);
                double perItem = Math.Round(totalPrice / qty, 2);
                tierOptions.Add(new TierOption
                {
                    Qty = qty,
                    TotalPrice = totalPrice,
                    PerItemPrice = perItem,
                    OriginalTotal = listPrice * qty
                });
            }

            // Show modern tier pricing popup
            var owner = Window.GetWindow(this);
            var tierWin = new TierPricingWindow(r.Title, cartItem.ItemName, listPrice, tierOptions)
            {
                Owner = owner
            };

            if (tierWin.ShowDialog() != true) return;
            int userQty = tierWin.SelectedQty;
            if (userQty <= 0) return;

            // Find matching tier (closest tier where userQty >= tier.qty)
            var matchedTier = tiers.OrderByDescending(t => t.qty).FirstOrDefault(t => userQty >= t.qty);
            if (matchedTier.qty == 0)
            {
                ShowInfo("Is qty ke liye koi tier price set nahi hai.");
                return;
            }

            // Calculate total price for the selected qty
            double totalTierPrice = matchedTier.customPrice > 0
                ? matchedTier.customPrice
                : Math.Round(listPrice * matchedTier.qty * matchedTier.percent / 100.0, 2);
            double pricePerItem = Math.Round(totalTierPrice / matchedTier.qty, 2);

            // Save original qty before scheme changes it (for restore on quit)
            cartItem.OriginalQtyBeforeScheme = cartItem.Qty;

            // How many items already in cart for this product?
            double currentQtyInCart = cartItem.Qty;
            double needToAdd = userQty - currentQtyInCart;

            // If user wants more than what's in cart, increase qty
            if (needToAdd > 0)
            {
                cartItem.Qty = userQty;
            }
            // If user wants less (e.g. 1 item but 2 in cart), set to user's qty
            else if (userQty < currentQtyInCart)
            {
                cartItem.Qty = userQty;
            }

            // Apply tier price
            // Disc = TotDisc = total saving (same value both columns)
            // Amount = original total, Grand Total = Amount - TotDisc = tier price
            double totalSavingAmt = Math.Round((listPrice - pricePerItem) * cartItem.Qty, 2);
            cartItem.Price = pricePerItem;
            cartItem.Disc = totalSavingAmt; // total saving
            cartItem.TotDisc = totalSavingAmt; // total saving (same)
            cartItem.Amount = Math.Round(listPrice * cartItem.Qty, 2); // original total
            cartItem.TaxableAmount = _isTaxInclusive && cartItem.TaxPerc > 0
                ? Math.Round((cartItem.Amount - cartItem.TotDisc) / (1 + cartItem.TaxPerc / 100.0), 2)
                : cartItem.Amount - cartItem.TotDisc;
            cartItem.HasScheme = true;
            cartItem.AppliedSchemeText = $"✔ {r.Title} — {(int)cartItem.Qty} qty @ ₹{totalTierPrice:N2} (Save ₹{totalSavingAmt:N0})";

            _schemeApplied = true;
            _schemeAppliedTitle = r.Title;
            UpdateTotals();
        }

        /// <summary>Normal scheme apply (without tier pricing intervention)</summary>
        private void ApplySchemeResultNormal(SchemeApplyResult r)
        {
            // Build a tag from buy item codes for scheme identification
            string buyTag = string.Join(",", r.ItemCodes.Select(c => c?.Trim()).Where(c => !string.IsNullOrEmpty(c)));

            if (r.FreeItem != null && r.FreeQty > 0)
            {
                string freeCode = r.FreeItem.Code;
                string freeName = r.FreeItem.Name;
                string freeHsn  = r.FreeItem.Hsn;
                double freeMrp  = r.FreeItem.Mrp;
                string freeUnit = r.FreeItem.Unit;
                double freeTax  = r.FreeItem.TaxPerc;

                // ── Variation_Product: customer ko flavour/variant choose karne do ──
                if (r.FreeItem.Type == "Variation_Product")
                {
                    try
                    {
                        var variants = new List<VariantDisplayItem>();
                        using var conn = _db.GetConnection();
                        using var cmd = conn.CreateCommand();
                        cmd.CommandText = @"SELECT i.code, i.name, IFNULL(i.hsn_code,''), IFNULL(i.mrp_price,0),
                                                   IFNULL(i.sale_price,0), IFNULL(u.UnitName,''), IFNULL(t.tax_rate,0)
                                            FROM items i
                                            LEFT JOIN units u ON i.sale_unit_id = u.Id
                                            LEFT JOIN taxs t ON i.applicable_tax_id = t.id
                                            WHERE i.parent_id = (SELECT id FROM items WHERE code=@c LIMIT 1)
                                              AND (i.del_status IS NULL OR i.del_status='Live')
                                            ORDER BY i.name";
                        cmd.Parameters.AddWithValue("@c", freeCode);
                        using var rdr = cmd.ExecuteReader();
                        while (rdr.Read())
                        {
                            variants.Add(new VariantDisplayItem
                            {
                                Name = rdr.GetString(1) ?? "",
                                Code = rdr.GetString(0) ?? "",
                                PriceDisplay = "FREE",
                                MrpDisplay = $"MRP ₹{rdr.GetDouble(3):N2}",
                                StockDisplay = "",
                                LookupRow = new ItemLookupRow
                                {
                                    Code = rdr.GetString(0) ?? "",
                                    Name = rdr.GetString(1) ?? "",
                                    Hsn  = rdr.GetString(2) ?? "",
                                    MrpValue = rdr.GetDouble(3),
                                    RateValue = 0,
                                    Unit = rdr.GetString(5) ?? "",
                                    TaxPerc = rdr.GetDouble(6)
                                }
                            });
                        }

                        if (variants.Count > 0)
                        {
                            var owner = Window.GetWindow(this);
                            var varWin = new VariantSelectionWindow($"{freeName} — Flavour / Variant chunein", variants)
                            { Owner = owner };
                            if (varWin.ShowDialog() == true && varWin.SelectedVariant != null)
                            {
                                var sv = varWin.SelectedVariant;
                                freeCode = sv.Code;
                                freeName = sv.Name;
                                freeHsn  = sv.Hsn;
                                freeMrp  = sv.MrpValue;
                                freeUnit = sv.Unit;
                                freeTax  = sv.TaxPerc;
                            }
                        }
                    }
                    catch { }
                }

                _srNo++;
                _cart.Add(new BusyCartItem
                {
                    SrNo = _srNo,
                    ItemCode = freeCode,
                    ItemName = freeName,
                    HSNCode  = freeHsn,
                    MRP      = Math.Round(freeMrp, 2),
                    Qty      = r.FreeQty,
                    Unit     = freeUnit,
                    ListPrice = 0,
                    Disc = 0, TotDisc = 0,
                    Price = 0, Amount = 0,
                    TaxPerc = freeTax,
                    TaxableAmount = 0,
                    AppliedSchemeText = $"✔ FREE — {r.Title} [{buyTag}]",
                    HasScheme = true
                });
                ReNumberCart();
                _schemeApplied = true;
                _schemeAppliedTitle = r.Title;
            }

            bool isDiscount = r.DiscountKind == "percentage" || r.DiscountKind == "flat";
            if (isDiscount)
            {
                // Get all matched items
                var matchedItems = _cart.Where(item =>
                    !string.IsNullOrEmpty(item.ItemCode) &&
                    r.ItemCodes.Any(c => string.Equals(c.Trim(), item.ItemCode.Trim(), StringComparison.OrdinalIgnoreCase))
                ).ToList();

                if (r.DiscountKind == "percentage")
                {
                    // Percentage: apply to each item individually
                    foreach (var item in matchedItems)
                    {
                        double discPerUnit = item.ListPrice * r.DiscountAmt / 100.0;
                        discPerUnit = Math.Max(0, Math.Min(item.ListPrice, discPerUnit));
                        item.Disc = Math.Round(discPerUnit, 2);
                        item.TotDisc = Math.Round(discPerUnit * item.Qty, 2);
                        item.Price = Math.Round(Math.Max(0, item.ListPrice - discPerUnit), 2);
                        item.Amount = Math.Round(item.ListPrice * item.Qty, 2);
                        item.TaxableAmount = _isTaxInclusive && item.TaxPerc > 0
                            ? Math.Round((item.Amount - item.TotDisc) / (1 + item.TaxPerc / 100.0), 2)
                            : item.Amount - item.TotDisc;
                        item.AppliedSchemeText = $"✔ {r.Title}";
                        item.HasScheme = true;
                    }
                }
                else
                {
                    // Flat: distribute total discount proportionally across items
                    double totalBillAmount = matchedItems.Sum(i => i.ListPrice * i.Qty);
                    double totalFlatDisc = Math.Min(r.DiscountAmt, totalBillAmount); // cap at bill total

                    foreach (var item in matchedItems)
                    {
                        double itemTotal = item.ListPrice * item.Qty;
                        // Proportional share of the flat discount
                        double itemDisc = totalBillAmount > 0
                            ? Math.Round(totalFlatDisc * (itemTotal / totalBillAmount), 2)
                            : 0;
                        double discPerUnit = item.Qty > 0 ? Math.Round(itemDisc / item.Qty, 2) : 0;

                        item.Disc = itemDisc;
                        item.TotDisc = itemDisc;
                        item.Price = Math.Round(Math.Max(0, item.ListPrice - discPerUnit), 2);
                        item.Amount = Math.Round(item.ListPrice * item.Qty, 2);
                        item.TaxableAmount = _isTaxInclusive && item.TaxPerc > 0
                            ? Math.Round((item.Amount - item.TotDisc) / (1 + item.TaxPerc / 100.0), 2)
                            : item.Amount - item.TotDisc;
                        item.AppliedSchemeText = $"✔ {r.Title}";
                        item.HasScheme = true;
                    }
                }
            }
            else
            {
                // No discount — just mark as scheme applied
                foreach (var item in _cart)
                {
                    if (string.IsNullOrEmpty(item.ItemCode)) continue;
                    if (!r.ItemCodes.Any(c => string.Equals(c.Trim(), item.ItemCode.Trim(), StringComparison.OrdinalIgnoreCase))) continue;
                    item.AppliedSchemeText = $"✔ {r.Title}";
                    item.HasScheme = true;
                }
            }
            UpdateTotals();
        }

        // ═══════ APPLIED SCHEME → CART ═══════

        private void ApplySchemeResult(SchemeApplyResult r)
        {
            // ═══ TIER PRICING: Partial qty handling ═══
            // If scheme has tier pricing, ask user how many items customer wants
            if (!string.IsNullOrWhiteSpace(r.TierPricing) && r.PromoType == "3")
            {
                ApplyTierPricing(r);
                return;
            }

            // Normal scheme apply (free item / discount)
            ApplySchemeResultNormal(r);
        }

        public void RePrint()
        {
            if (_lastSavedSaleId <= 0 && (string.IsNullOrEmpty(_lastSavedVchCode) || _lastSavedItems.Count == 0))
            {
                ShowInfo("No invoice to reprint.\nComplete a sale first.");
                return;
            }
            ReprintLastInvoice();
        }

        // ═══════ CART OPERATIONS ═══════

        private Dictionary<string, double> GetCartQtys()
        {
            var map = new Dictionary<string, double>();
            foreach (var item in _cart)
            {
                if (string.IsNullOrEmpty(item.ItemCode)) continue;
                var key = item.ItemCode.ToLower();
                map[key] = map.ContainsKey(key) ? map[key] + item.Qty : item.Qty;
            }
            return map;
        }

        private void ApplyCartItem(ItemLookupRow item, double qty)
        {
            if (item == null) return;

            // ═══ COMBO PRODUCT: expand into child items ═══
            if (item.Type == "Combo_Product")
            {
                ExpandComboToCart(item, qty);
                return;
            }

            // ═══ ENTERPRISE: Stock check — don't allow adding out-of-stock items ═══
            if (item.StockValue <= 0)
            {
                MessageBox.Show($"⚠️ '{item.Name}' is OUT OF STOCK.\n\nCurrent stock: 0\nCannot add to cart.",
                    "Out of Stock", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }

            // Check if requested qty exceeds available stock
            double alreadyInCart = 0;
            var inCartItem = _cart.FirstOrDefault(c => string.Equals(c.ItemCode, item.Code, StringComparison.OrdinalIgnoreCase));
            if (inCartItem != null) alreadyInCart = inCartItem.Qty;
            if (alreadyInCart + qty > item.StockValue)
            {
                MessageBox.Show($"⚠️ '{item.Name}' me sirf {item.StockValue:0.##} stock available hai.\n\nCart me pehle se {alreadyInCart:0.##} hai. Aur {item.StockValue - alreadyInCart:0.##} add kar sakte hain.",
                    "Insufficient Stock", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }

                // ── BARCODE SCAN: same item dobara scan = qty++ (no popup) ──
                var existing2 = _cart.FirstOrDefault(c =>
                    string.Equals(c.ItemCode, item.Code, StringComparison.OrdinalIgnoreCase));
                if (existing2 != null)
                {
                    double newQty = existing2.Qty + qty;
                    if (newQty > item.StockValue)
                    {
                        // silent — just cap at stock
                        newQty = item.StockValue;
                    }
                    existing2.Qty = newQty;
                    existing2.Amount = Math.Round(existing2.Price * newQty, 2);
                    existing2.TotDisc = Math.Round(existing2.Disc * newQty, 2);
                    existing2.TaxableAmount = _isTaxInclusive && existing2.TaxPerc > 0
                        ? Math.Round(existing2.Amount / (1 + existing2.TaxPerc / 100.0), 2)
                        : existing2.Amount;
                    UpdateTotals();
                    return;
                }

            if (qty <= 0) return;
            double rate = Math.Round(item.RateValue, 2);
            if (rate <= 0) { ShowInfo($"'{item.Name}' ki rate {rate:N2} hai — 0 ya negative price add nahi kiya ja sakta."); return; }
            _srNo++;
            var amount = Math.Round(rate * qty, 2);
            _cart.Add(new BusyCartItem
            {
                SrNo = _srNo,
                ItemCode = item.Code,
                ItemName = item.Name,
                HSNCode = item.Hsn,
                MRP = Math.Round(item.MrpValue, 2),
                Qty = qty,
                Unit = item.Unit,
                ListPrice = rate,
                Disc = 0,
                TotDisc = 0,
                Price = rate,
                Amount = amount,
                TaxPerc = item.TaxPerc,
                TaxableAmount = _isTaxInclusive && item.TaxPerc > 0
                    ? Math.Round(amount / (1 + item.TaxPerc / 100.0), 2)
                    : amount,
                Category = item.Category
            });
            UpdateTotals();

            // ── AUTO-SCHEME: item add hote hi silently applicable scheme check ──
            TryAutoApplyScheme();
        }

        private void ExpandComboToCart(ItemLookupRow combo, double comboQty)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT ci.item_id, ci.quantity, ci.amount,
                    i.name, i.code, i.mrp_price, i.hsn_code, i.tax_string,
                    c.Name as CategoryName, u.UnitName
                    FROM combo_items ci
                    LEFT JOIN items i ON i.id = ci.item_id
                    LEFT JOIN item_categories c ON i.category_id = c.Id
                    LEFT JOIN units u ON i.sale_unit_id = u.Id
                    WHERE ci.combo_item_id = (SELECT id FROM items WHERE code=@code LIMIT 1)
                      AND (i.del_status IS NULL OR i.del_status != 'Deleted')";
                cmd.Parameters.AddWithValue("@code", combo.Code);
                using var r = cmd.ExecuteReader();
                bool addedAny = false;
                while (r.Read())
                {
                    var childName = r["name"]?.ToString() ?? "";
                    var childCode = r["code"]?.ToString() ?? "";
                    var childPrice = r["amount"] != DBNull.Value ? Math.Round(Convert.ToDouble(r["amount"]), 2) : 0;
                    var childQty = r["quantity"] != DBNull.Value ? Convert.ToDouble(r["quantity"]) : 1;
                    var childMrp = r["mrp_price"] != DBNull.Value ? Math.Round(Convert.ToDouble(r["mrp_price"]), 2) : childPrice;
                    var tax = ParseTaxString(r["tax_string"]?.ToString() ?? "");
                    var unit = r["UnitName"]?.ToString() ?? "";
                    var hsn = r["hsn_code"]?.ToString() ?? "";

                    double finalQty = childQty * comboQty;

                    var existing = _cart.FirstOrDefault(c =>
                        string.Equals(c.ItemCode, childCode, StringComparison.OrdinalIgnoreCase));
                    if (existing != null)
                    {
                        existing.Qty += finalQty;
                        existing.Amount = Math.Round(existing.Price * existing.Qty, 2);
                        existing.TotDisc = Math.Round(existing.Disc * existing.Qty, 2);
                        existing.TaxableAmount = _isTaxInclusive && existing.TaxPerc > 0
                            ? Math.Round(existing.Amount / (1 + existing.TaxPerc / 100.0), 2)
                            : existing.Amount;
                    }
                    else
                    {
                        _srNo++;
                        var amount = Math.Round(childPrice * finalQty, 2);
                        _cart.Add(new BusyCartItem
                        {
                            SrNo = _srNo,
                            ItemCode = childCode,
                            ItemName = childName + " (Combo)",
                            HSNCode = hsn,
                            MRP = childMrp,
                            Qty = finalQty,
                            Unit = unit,
                            ListPrice = childPrice,
                            Disc = 0,
                            TotDisc = 0,
                            Price = childPrice,
                            Amount = amount,
                            TaxPerc = tax,
                            TaxableAmount = _isTaxInclusive && tax > 0
                                ? Math.Round(amount / (1 + tax / 100.0), 2)
                                : amount
                        });
                    }
                    addedAny = true;
                }
                if (!addedAny)
                {
                    MessageBox.Show($"⚠️ Combo '{combo.Name}' me koi child item nahi mila.\n\nPlease combo items setup karein.",
                        "Combo Empty", MessageBoxButton.OK, MessageBoxImage.Warning);
                    return;
                }
                UpdateTotals();
            }
            catch (Exception ex)
            {
                MessageBox.Show($"Combo load error: {ex.Message}", "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void DeleteSelectedLine()
        {
            if (dgCart.SelectedItem is BusyCartItem selected)
                _cart.Remove(selected);
            else if (_cart.Count > 0)
                _cart.RemoveAt(_cart.Count - 1);
            ReNumberCart();
        }

        private void ReNumberCart()
        {
            int i = 1;
            foreach (var item in _cart) item.SrNo = i++;
            _srNo = _cart.Count;
            UpdateTotals();
        }

        private void UpdateTotals()
        {
            double totalQty = 0, totalAmount = 0, totalListPrice = 0, totalDisc = 0, totalTax = 0;
            int itemCount = _cart.Count;
            foreach (var item in _cart)
            {
                totalQty      += item.Qty;
                totalAmount   += item.Amount;
                totalListPrice += item.ListPrice * item.Qty;
                totalDisc     += item.TotDisc;
                totalTax      += item.TaxableAmount * item.TaxPerc / 100.0;
            }
            double grandTotal = _isTaxInclusive
                ? totalAmount - totalDisc - _couponDiscount
                : totalAmount - totalDisc + totalTax - _couponDiscount;
            grandTotal = Math.Max(0, grandTotal);

            // ── CASH ROUND OFF: total ko nearest ₹1 tak (sirf cash me coins bachane ke liye) ──
            double exactTotal = grandTotal;
            grandTotal = Math.Round(grandTotal, MidpointRounding.AwayFromZero);
            double roundOff = Math.Round(grandTotal - exactTotal, 2);
            double subTotal   = totalAmount;

            // Cart footer (bottom of datagrid)
            if (lblTotalItemCount  != null) lblTotalItemCount.Text  = itemCount.ToString();
            if (lblSumQtyRight     != null) lblSumQtyRight.Text     = totalQty.ToString("0.##");
            if (lblSubTotal        != null) lblSubTotal.Text        = $"₹ {subTotal:N2}";
            if (lblTotalDiscount   != null) lblTotalDiscount.Text   = $"₹ {totalDisc:N2}";
            if (lblTotalTax        != null) lblTotalTax.Text        = $"₹ {totalTax:N2}";

            // Coupon discount line
            if (couponDiscRow != null)
            {
                if (_couponDiscount > 0)
                {
                    couponDiscRow.Visibility = Visibility.Visible;
                    lblCouponLabel.Text = $"🎟️ {_couponCode}";
                    lblCouponDiscount.Text = $"- ₹ {_couponDiscount:N2}";
                }
                else
                {
                    couponDiscRow.Visibility = Visibility.Collapsed;
                }
            }

            // Round Off UI: sirf dikhao jab 0 nahi ho
            if (roundOffRow != null && lblRoundOff != null)
            {
                if (Math.Abs(roundOff) > 0.001)
                {
                    roundOffRow.Visibility = Visibility.Visible;
                    lblRoundOff.Text = (roundOff > 0 ? "+ ₹ " : "- ₹ ") + Math.Abs(roundOff).ToString("N2");
                }
                else
                {
                    roundOffRow.Visibility = Visibility.Collapsed;
                }
            }

            if (lblGrandTotalRight != null) lblGrandTotalRight.Text = $"₹ {grandTotal:N2}";
            if (lblPayAmount       != null) lblPayAmount.Text       = $"₹ {grandTotal:N2}";
            if (emptyStatePanel    != null) emptyStatePanel.Visibility = itemCount > 0 ? Visibility.Collapsed : Visibility.Visible;
        }

        private double CalculateGrandTotal()
        {
            double totalAmount = _cart.Sum(x => x.Amount);
            double totalDisc = _cart.Sum(x => x.TotDisc);
            double totalTax = _cart.Sum(x => x.TaxableAmount * x.TaxPerc / 100.0);
            double gt = _isTaxInclusive
                ? Math.Round(totalAmount - totalDisc - _couponDiscount, 2)
                : Math.Round(totalAmount - totalDisc + totalTax - _couponDiscount, 2);
            return Math.Max(0, gt);
        }

        // ═══════ HOLD / UNHOLD (legacy) ═══════

        private void BtnHold_Click(object sender, RoutedEventArgs e) => HoldCurrentBill();

        private void UnholdBill()
        {
            if (_heldBills.Count == 0) { ShowInfo("No held bills to restore."); return; }
            var lastHeld = _heldBills[_heldBills.Count - 1];
            _heldBills.RemoveAt(_heldBills.Count - 1);
            RestoreHeldBill(lastHeld);
        }

        // ═══════ SAVE BILL ═══════

        private void SaveBillInternal(string paymentMode)
        {
            SaveBillCore(new List<PaymentLine> { new PaymentLine(paymentMode, CalculateGrandTotal(), 0) }, paymentMode, 0);
        }

        private void SaveBillCore(List<PaymentLine> payments, string paymentMode, double changeAmount)
        {
            if (_isSaving) return;
            _isSaving = true;
            try
            {
                if (_cart.Count == 0) { ShowInfo("Cart is empty!"); return; }
                try
                {
                double grandTotal = CalculateGrandTotal();
                // ═══ SAVINGS FEATURE ═══
                // mrp_total = sab items ka MRP value (qty × MRP), savings = MRP total - bill amount.
                // Har bill ke sath database mein store hota hai (local sales + cloud push).
                double mrpTotal = _cart.Sum(x => x.MRP * x.Qty);
                double savings = Math.Max(0, mrpTotal - grandTotal);
                string saleNo = GenerateSaleNo();
                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();
                string vchCode = InvoiceNumberService.MakeVchCode(_db, _currentUser?.Id);
                _lastSavedVchCode = vchCode;
                _lastSavedSaleNo = saleNo;
                _lastSavedItems = new List<BusyCartItem>(_cart);
                _lastSavedGrandTotal = grandTotal;
                _lastSavedPaymentMode = paymentMode;
                _lastSavedCustomerName = _currentCustomer?.Name ?? "Walk-in Customer";
                _lastSavedCustomerPhone = _currentCustomer?.Phone ?? "";
                _lastSavedCustomerEmail = _currentCustomer?.Email ?? "";

                string syncPayload = BuildSyncPayload(vchCode, paymentMode, grandTotal, payments, changeAmount);

                // Cash round-off: exact total vs rounded total ka difference
                double exactTotal = _isTaxInclusive
                    ? _cart.Sum(x => x.Amount) - _cart.Sum(x => x.TotDisc) - _couponDiscount
                    : _cart.Sum(x => x.Amount) - _cart.Sum(x => x.TotDisc) + _cart.Sum(x => x.TaxableAmount * x.TaxPerc / 100.0) - _couponDiscount;
                exactTotal = Math.Max(0, exactTotal);
                double rounding = Math.Round(grandTotal - exactTotal, 2);

                // Tran1 (legacy Busy-style voucher header)
                using (var cmd = conn.CreateCommand())
                {
                    // Get customer code from Master1 for Tran1 linkage
                    string customerCode = "";
                    if (_currentCustomer != null && _currentCustomer.Id > 1)
                    {
                        using var cc = conn.CreateCommand();
                        cc.CommandText = "SELECT Code FROM Master1 WHERE MasterType='Party' AND ServerId=@id LIMIT 1";
                        cc.Parameters.AddWithValue("@id", _currentCustomer.Id);
                        customerCode = cc.ExecuteScalar()?.ToString() ?? "";
                        if (string.IsNullOrEmpty(customerCode))
                        {
                            // Try by phone
                            cc.CommandText = "SELECT Code FROM Master1 WHERE MasterType='Party' AND Phone=@p LIMIT 1";
                            cc.Parameters.Clear();
                            cc.Parameters.AddWithValue("@p", _currentCustomer.Phone ?? "");
                            customerCode = cc.ExecuteScalar()?.ToString() ?? "";
                        }
                        if (string.IsNullOrEmpty(customerCode))
                        {
                            // Customer not in Master1 — create entry to satisfy FK
                            customerCode = $"CUST-{_currentCustomer.Id}";
                            using var ins = conn.CreateCommand();
                            ins.CommandText = @"INSERT OR IGNORE INTO Master1 (Code, Name, Phone, MasterType, IsActive, CreatedAt, UpdatedAt)
                                VALUES (@code, @name, @phone, 'Party', 1, datetime('now'), datetime('now'))";
                            ins.Parameters.AddWithValue("@code", customerCode);
                            ins.Parameters.AddWithValue("@name", _currentCustomer.Name ?? "Customer");
                            ins.Parameters.AddWithValue("@phone", _currentCustomer.Phone ?? "");
                            ins.ExecuteNonQuery();
                        }
                    }

                    cmd.CommandText = @"INSERT INTO Tran1 (VchCode, VchType, VchNo, VchDate, MasterCode1, Narration, Amount, PaymentMode, SyncPayload, SyncStatus, IsCancelled, CreatedAt, UpdatedAt)
                        VALUES (@code,'Sales',@vno,@vdate,@custcode,@narr,@amt,@pmode,@payload,'Local',0,datetime('now'),datetime('now'))";
                    cmd.Parameters.AddWithValue("@code", vchCode);
                    cmd.Parameters.AddWithValue("@custcode", string.IsNullOrEmpty(customerCode) ? (object)DBNull.Value : customerCode);
                    cmd.Parameters.AddWithValue("@vno", saleNo);
                    cmd.Parameters.AddWithValue("@vdate", DateTime.Now.ToString("dd-MM-yyyy"));
                    cmd.Parameters.AddWithValue("@narr", $"POS Sale - {paymentMode}");
                    cmd.Parameters.AddWithValue("@amt", grandTotal);
                    cmd.Parameters.AddWithValue("@pmode", paymentMode);
                    cmd.Parameters.AddWithValue("@payload", syncPayload);
                    cmd.ExecuteNonQuery();
                }

                // sales table (Laravel mirror — used by SaleListDialog, reports, sync)
                long saleId = 0;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"INSERT INTO sales
                        (invoice_no, sale_no, sale_date, date_time, customer_id,
                         sub_total, paid_amount, grand_total, mrp_total, savings, due_amount,
                         disc, vat, total_payable, sale_vat_objects, note,
                         user_id, outlet_id, company_id, coupon_code, coupon_discount, rounding,
                         del_status, SyncStatus, created_at, updated_at)
                        VALUES
                        (@inv,@inv,@date,datetime('now'),@cid,
                         @sub,@paid,@total,@mrp,@sv,@due,
                         @disc,@tax,@total,@vatobj,@note,
                         @uid, @outlet, @compid, @couponcode, @coupondiscount, @rounding,
                         'Live','Local',datetime('now'),datetime('now'))";
                    cmd.Parameters.AddWithValue("@inv", saleNo);
                    cmd.Parameters.AddWithValue("@date", DateTime.Now.ToString("yyyy-MM-dd"));
                    cmd.Parameters.AddWithValue("@cid", _currentCustomer?.Id > 1 ? (object)_currentCustomer.Id : DBNull.Value);
                    cmd.Parameters.AddWithValue("@sub", _cart.Sum(x => x.Amount));
                    cmd.Parameters.AddWithValue("@paid", grandTotal);
                    cmd.Parameters.AddWithValue("@total", grandTotal);
                    cmd.Parameters.AddWithValue("@mrp", Math.Round(mrpTotal, 2));
                    cmd.Parameters.AddWithValue("@sv", Math.Round(savings, 2));
                    cmd.Parameters.AddWithValue("@due", 0.0);
                    cmd.Parameters.AddWithValue("@disc", _cart.Sum(x => x.TotDisc) + _couponDiscount);
                    cmd.Parameters.AddWithValue("@tax", _cart.Sum(x => x.TaxableAmount * x.TaxPerc / 100.0));
                    cmd.Parameters.AddWithValue("@vatobj", BuildSaleVatObject() ?? (object)DBNull.Value);
                    cmd.Parameters.AddWithValue("@note", $"POS - {paymentMode}");
                    cmd.Parameters.AddWithValue("@uid", _currentUser?.Id ?? 1);
                    cmd.Parameters.AddWithValue("@outlet", RashanKiDukan.Services.OutletContext.GetSelectedOutletId(_db));
                    cmd.Parameters.AddWithValue("@compid", _currentUser?.CompanyId > 0 ? _currentUser.CompanyId : 1);
                    cmd.Parameters.AddWithValue("@couponcode", string.IsNullOrEmpty(_couponCode) ? (object)DBNull.Value : _couponCode);
                    cmd.Parameters.AddWithValue("@coupondiscount", _couponDiscount);
                    cmd.Parameters.AddWithValue("@rounding", rounding);
                    cmd.ExecuteNonQuery();
                    using var lid = conn.CreateCommand();
                    lid.CommandText = "SELECT last_insert_rowid()";
                    saleId = (long)lid.ExecuteScalar();
                }
                _lastSavedSaleId = saleId;

                // sale_details (Laravel mirror)
                long sdBaseId = LocalTxn.NextLocalId(_db, "sale_details");
                foreach (var item in _cart)
                {
                    long detailId = sdBaseId;
                    sdBaseId--;
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = @"INSERT INTO sale_details
                        (Id, sales_id, item_id, qty, menu_unit_price, menu_price_without_discount,
                         menu_price_with_discount, discount_amount, menu_vat_percentage,
                         item_tax_amount, del_status, SyncStatus, created_at, updated_at)
                        SELECT @id, @sid, id, @qty, @price, @price,
                               @price - @disc, @disc, @tax,
                               @taxamt, 'Live', 'Local', datetime('now'), datetime('now')
                        FROM items WHERE code=@code LIMIT 1";
                    cmd.Parameters.AddWithValue("@id", detailId);
                    cmd.Parameters.AddWithValue("@sid", saleId);
                    cmd.Parameters.AddWithValue("@qty", item.Qty);
                    cmd.Parameters.AddWithValue("@price", item.Price);
                    cmd.Parameters.AddWithValue("@disc", item.Disc);
                    cmd.Parameters.AddWithValue("@tax", item.TaxPerc);
                    cmd.Parameters.AddWithValue("@taxamt", Math.Round(item.TaxableAmount * item.TaxPerc / 100.0, 2));
                    cmd.Parameters.AddWithValue("@code", item.ItemCode);
                    int rowsAffected = cmd.ExecuteNonQuery();

                    // If item doesn't exist in items table (Master1-only), do direct INSERT with NULL item_id
                    if (rowsAffected == 0)
                    {
                        using var fallback = conn.CreateCommand();
                        fallback.CommandText = @"INSERT INTO sale_details
                            (Id, sales_id, item_id, qty, menu_unit_price, menu_price_without_discount,
                             menu_price_with_discount, discount_amount, menu_vat_percentage,
                             item_tax_amount, del_status, SyncStatus, created_at, updated_at)
                            VALUES (@id, @sid, NULL, @qty, @price, @price,
                                   @price - @disc, @disc, @tax,
                                   @taxamt, 'Live', 'Local', datetime('now'), datetime('now'))";
                        fallback.Parameters.AddWithValue("@id", detailId);
                        fallback.Parameters.AddWithValue("@sid", saleId);
                        fallback.Parameters.AddWithValue("@qty", item.Qty);
                        fallback.Parameters.AddWithValue("@price", item.Price);
                        fallback.Parameters.AddWithValue("@disc", item.Disc);
                        fallback.Parameters.AddWithValue("@tax", item.TaxPerc);
                        fallback.Parameters.AddWithValue("@taxamt", Math.Round(item.TaxableAmount * item.TaxPerc / 100.0, 2));
                        fallback.ExecuteNonQuery();
                    }
                }

                // Get base negative ID for sale_payments
                long spBaseId = LocalTxn.NextLocalId(_db, "sale_payments");

                // sale_payments (Laravel mirror)
                foreach (var p in payments)
                {
                    // Resolve payment method ID — match by name or alias
                    long payMethodId = 1; // default Cash
                    using (var pmCmd = conn.CreateCommand())
                    {
                        string pName = p.Name?.Trim() ?? "Cash";
                        pmCmd.CommandText = @"SELECT id FROM payment_methods 
                            WHERE LOWER(name)=LOWER(@n) 
                               OR (LOWER(@n)='upi' AND LOWER(name)='online')
                               OR (LOWER(@n)='online' AND LOWER(name)='upi')
                               OR (LOWER(@n)='credit card' AND LOWER(name)='card')
                               OR (LOWER(@n)='debit card' AND LOWER(name)='card')
                               OR (LOWER(@n)='bank transfer' AND LOWER(name)='online')
                            LIMIT 1";
                        pmCmd.Parameters.AddWithValue("@n", pName);
                        var pmVal = pmCmd.ExecuteScalar();
                        if (pmVal != null) payMethodId = Convert.ToInt64(pmVal);
                    }

                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = @"INSERT INTO sale_payments
                        (Id, sale_id, payment_id, date, amount, del_status, SyncStatus, created_at, updated_at)
                        VALUES (@id, @sid, @pmid, date('now'), @amt, 'Live', 'Local', datetime('now'), datetime('now'))";
                    cmd.Parameters.AddWithValue("@id", spBaseId);
                    cmd.Parameters.AddWithValue("@sid", saleId);
                    cmd.Parameters.AddWithValue("@pmid", payMethodId);
                    cmd.Parameters.AddWithValue("@amt", p.Amount);
                    cmd.ExecuteNonQuery();
                    spBaseId--; // next payment gets next negative ID
                }

                // Stock deduction — reduce stock_quantity for each sold item
                foreach (var item in _cart)
                {
                    if (string.IsNullOrEmpty(item.ItemCode) || item.Qty <= 0) continue;
                    if (item.Price == 0 && item.HasScheme) continue; // FREE scheme items — don't deduct twice
                    using var stk = conn.CreateCommand();
                    stk.CommandText = "UPDATE items SET stock_quantity = MAX(IFNULL(stock_quantity,0) - @qty, 0), SyncStatus='Local' WHERE LOWER(code) = LOWER(@code)";
                    stk.Parameters.AddWithValue("@qty", item.Qty);
                    stk.Parameters.AddWithValue("@code", item.ItemCode);
                    stk.ExecuteNonQuery();
                }

                // Tran2 (legacy line items)
                foreach (var item in _cart)
                {
                    // Ensure item code exists in Master1 for FK constraint
                    using (var chk = conn.CreateCommand())
                    {
                        chk.CommandText = "SELECT COUNT(*) FROM Master1 WHERE Code=@code AND MasterType='Item'";
                        chk.Parameters.AddWithValue("@code", item.ItemCode);
                        if (Convert.ToInt64(chk.ExecuteScalar()) == 0)
                        {
                            // Create Master1 entry for this item
                            using var ins = conn.CreateCommand();
                            ins.CommandText = @"INSERT OR IGNORE INTO Master1 (Code, Name, MasterType, SaleRate, CurrentStock, IsActive, CreatedAt, UpdatedAt)
                                VALUES (@code, @name, 'Item', @rate, @stock, 1, datetime('now'), datetime('now'))";
                            ins.Parameters.AddWithValue("@code", item.ItemCode);
                            ins.Parameters.AddWithValue("@name", item.ItemName);
                            ins.Parameters.AddWithValue("@rate", item.Price);
                            ins.Parameters.AddWithValue("@stock", item.Qty);
                            ins.ExecuteNonQuery();
                        }
                    }

                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = @"INSERT INTO Tran2 (VchCode,SrNo,MasterCode1,Description,Quantity,Unit,Rate,Amount,DiscountPercent,DiscountAmount,TaxableAmount)
                        VALUES (@vch,@sr,@item,@desc,@qty,@unit,@rate,@amt,@discp,@disca,@taxable)";
                    cmd.Parameters.AddWithValue("@vch", vchCode);
                    cmd.Parameters.AddWithValue("@sr", item.SrNo);
                    cmd.Parameters.AddWithValue("@item", item.ItemCode);
                    cmd.Parameters.AddWithValue("@desc", item.ItemName);
                    cmd.Parameters.AddWithValue("@qty", item.Qty);
                    cmd.Parameters.AddWithValue("@unit", item.Unit);
                    cmd.Parameters.AddWithValue("@rate", item.Price);
                    cmd.Parameters.AddWithValue("@amt", item.Amount);
                    cmd.Parameters.AddWithValue("@discp", item.Disc);
                    cmd.Parameters.AddWithValue("@disca", item.TotDisc);
                    cmd.Parameters.AddWithValue("@taxable", item.TaxableAmount);
                    cmd.ExecuteNonQuery();
                }

                // Wallet / Loyalty deductions
                if (_currentCustomer != null)
                {
                    double walletAmt = payments.Where(p => p.Name == "Wallet").Sum(p => p.Amount);
                    int loyaltyPts = payments.Where(p => p.Name == "Loyalty Point").Sum(p => p.UsagePoints);
                    if (walletAmt > 0)
                    {
                        if (!DeductWallet(conn, vchCode, walletAmt))
                        {
                            // Wallet deduction failed — rollback transaction and abort sale
                            ShowInfo("Sale cancelled: Wallet deduction failed.\nInsufficient balance or concurrent modification.");
                            return;
                        }
                    }
                    if (loyaltyPts > 0)
                    {
                        using var cmd = conn.CreateCommand();
                        cmd.CommandText = "UPDATE customers SET loyalty_point=IFNULL(loyalty_point,0)-@pts, LoyaltySyncPending=1 WHERE id=@cid";
                        cmd.Parameters.AddWithValue("@pts", loyaltyPts);
                        cmd.Parameters.AddWithValue("@cid", _currentCustomer.Id);
                        cmd.ExecuteNonQuery();
                    }
                }


                // ═══ Loyalty Point EARNING — credit points to customer based on items purchased ═══
                // Skip loyalty for Business Club members — they get profit share instead
                if (_currentCustomer != null && _currentCustomer.Id > 1 && !IsBusinessClubMember(_currentCustomer.Id))
                {
                    int totalEarnedPoints = 0;
                    foreach (var item in _cart)
                    {
                        int itemLoyalty = 0;
                        // Try items table first (loyalty_point column)
                        using (var cmd = conn.CreateCommand())
                        {
                            cmd.CommandText = "SELECT IFNULL(loyalty_point,0) FROM items WHERE lower(code)=lower(@code) LIMIT 1";
                            cmd.Parameters.AddWithValue("@code", item.ItemCode);
                            var result = cmd.ExecuteScalar();
                            if (result != null && result != DBNull.Value)
                                itemLoyalty = Convert.ToInt32(result);
                        }
                        // Fallback: check Master1 table (LoyaltyPoint column)
                        if (itemLoyalty == 0)
                        {
                            using var cmd = conn.CreateCommand();
                            cmd.CommandText = "SELECT IFNULL(LoyaltyPoint,0) FROM Master1 WHERE lower(Code)=lower(@code) LIMIT 1";
                            cmd.Parameters.AddWithValue("@code", item.ItemCode);
                            var result = cmd.ExecuteScalar();
                            if (result != null && result != DBNull.Value)
                                itemLoyalty = Convert.ToInt32(result);
                        }
                        totalEarnedPoints += itemLoyalty * (int)item.Qty;
                    }
                    if (totalEarnedPoints > 0)
                    {
                        using var cmd = conn.CreateCommand();
                        cmd.CommandText = "UPDATE customers SET loyalty_point=IFNULL(loyalty_point,0)+@pts, LoyaltySyncPending=1 WHERE id=@cid";
                        cmd.Parameters.AddWithValue("@pts", totalEarnedPoints);
                        cmd.Parameters.AddWithValue("@cid", _currentCustomer.Id);
                        cmd.ExecuteNonQuery();
                    }
                }

                // ═══ BUSINESS CLUB: Credit profit share to customer wallet ═══
                if (_currentCustomer != null && _currentCustomer.Id > 1 && grandTotal > 0)
                {
                    CreditBusinessClubProfit(conn, _currentCustomer.Id, saleId, grandTotal);
                }

                txn.Commit();
                _cart.Clear(); _srNo = 0; _schemeApplied = false; _schemeAppliedTitle = ""; _couponDiscount = 0; _couponCode = "";
                UpdateTotals();

                // Open PDF invoice in viewer (direct print from there)
                // Sale is already committed — PDF failure must NOT look like a save failure
                // (otherwise the operator re-saves and creates a duplicate sale).
                if (_lastSavedSaleId > 0)
                {
                    try
                    {
                        string temp = System.IO.Path.Combine(System.IO.Path.GetTempPath(), "Sale_" + _lastSavedSaleId + "_" + DateTime.Now.Ticks + ".pdf");
                        Services.PdfService.GenerateSalePdf(_lastSavedSaleId, temp);
                    }
                    catch (Exception pdfEx)
                    {
                        Services.LogService.Error("PDF generation failed after sale commit", pdfEx);
                        ShowInfo("Bill saved successfully. PDF generation failed: " + pdfEx.Message);
                    }
                }
            }
            catch (Exception ex) { Services.LogService.Error("SaveBillCore failed", ex); ShowInfo("Error saving bill: " + ex.Message); }
            finally
            {
                // Always reset customer after sale - F2 button must show "F2 Add Customer"
                if (_cart.Count == 0)
                {
                    ResetCustomerAfterSale();
                    UpdateBottomCustomer();
                }
            }
            }
            finally { _isSaving = false; }
        }

        private void ShowSendBillWindow(double changeAmount = 0)
        {
            if (string.IsNullOrEmpty(_lastSavedVchCode) || _lastSavedItems.Count == 0) return;
            var win = new SendBillWindow(
                _lastSavedCustomerName,
                _lastSavedCustomerPhone,
                _lastSavedCustomerEmail,
                _lastSavedSaleNo,
                _lastSavedPaymentMode,
                _lastSavedGrandTotal,
                _lastSavedItems,
                changeAmount)
            { Owner = Window.GetWindow(this) };
            win.ShowDialog();
        }

        // F9 — last saved bill ko customer ko message karo (WhatsApp / Email / SMS)
        public void SendLastBill()
        {
            if (string.IsNullOrEmpty(_lastSavedVchCode) || _lastSavedItems.Count == 0)
            {
                ShowInfo("No invoice to send.\nComplete a sale first.");
                return;
            }
            ShowSendBillWindow(0);
        }

        /// <summary>
        /// Deducts wallet balance atomically. Returns false if insufficient balance
        /// or if a concurrent update changed the balance (stale read protection).
        /// Uses UPDATE ... WHERE balance >= @amt to prevent negative balance across counters.
        /// </summary>
        private bool DeductWallet(SqliteConnection conn, string vchCode, double amount)
        {
            if (_currentCustomer == null) return false;
            long walletId = 0; double balanceBefore = 0; string updatedAt = "";
            using (var cmd = conn.CreateCommand())
            {
                cmd.CommandText = "SELECT id, IFNULL(balance,0), IFNULL(updated_at,'') FROM customer_wallets WHERE customer_id=@cid";
                cmd.Parameters.AddWithValue("@cid", _currentCustomer.Id);
                using var r = cmd.ExecuteReader();
                if (r.Read())
                {
                    walletId = r.GetInt64(0);
                    balanceBefore = r.GetDouble(1);
                    updatedAt = r.GetString(2);
                }
            }
            if (walletId == 0) return false;

            // Pre-check: reject if balance is insufficient
            if (balanceBefore < amount)
            {
                ShowInfo($"Insufficient wallet balance.\nAvailable: ₹{balanceBefore:F2}, Required: ₹{amount:F2}");
                return false;
            }

            // Atomic update with balance guard and version/timestamp check to prevent
            // stale reads when multiple counters deduct simultaneously.
            int rowsAffected;
            using (var cmd = conn.CreateCommand())
            {
                cmd.CommandText = @"UPDATE customer_wallets 
                    SET balance = IFNULL(balance,0) - @amt, 
                        total_redeemed = IFNULL(total_redeemed,0) + @amt,
                        updated_at = datetime('now')
                    WHERE id = @wid 
                      AND IFNULL(balance,0) >= @amt 
                      AND IFNULL(updated_at,'') = @prevUpdated";
                cmd.Parameters.AddWithValue("@amt", amount);
                cmd.Parameters.AddWithValue("@wid", walletId);
                cmd.Parameters.AddWithValue("@prevUpdated", updatedAt);
                rowsAffected = cmd.ExecuteNonQuery();
            }

            // If no rows updated, another counter modified the wallet concurrently
            if (rowsAffected == 0)
            {
                ShowInfo("Wallet balance was modified by another counter.\nPlease retry the payment.");
                return false;
            }

            // Record wallet transaction
            using (var cmd = conn.CreateCommand())
            {
                cmd.CommandText = @"INSERT INTO wallet_transactions (wallet_id,customer_id,sale_id,type,amount,balance_before,balance_after,description,transaction_date,company_id,del_status,created_at,updated_at,SyncStatus)
                    VALUES (@wid,@cid,NULL,'redeem',@amt,@bb,@ba,@desc,date('now'),1,'Live',datetime('now'),datetime('now'),'Local')";
                cmd.Parameters.AddWithValue("@wid", walletId);
                cmd.Parameters.AddWithValue("@cid", _currentCustomer.Id);
                cmd.Parameters.AddWithValue("@amt", amount);
                cmd.Parameters.AddWithValue("@bb", balanceBefore);
                cmd.Parameters.AddWithValue("@ba", balanceBefore - amount);
                cmd.Parameters.AddWithValue("@desc", $"POS payment for {vchCode}");
                cmd.ExecuteNonQuery();
            }
            return true;
        }

        /// <summary>
        /// Business Club: After each sale, credit profit share % to member's earned_balance.
        /// Logic: Check if customer is an active member in business_club_members table,
        /// calculate profit (sale_price - purchase_price) * qty for each item,
        /// then credit profit_share_percentage% of total profit to earned_balance.
        /// The locked_balance should NEVER change here.
        /// </summary>
        private void CreditBusinessClubProfit(Microsoft.Data.Sqlite.SqliteConnection conn, long customerId, long saleId, double grandTotal)
        {
            try
            {
                // Check if Business Club is active
                double profitSharePercentage = 0;
                string isActive = "no";
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT COALESCE(profit_share_percentage, profit_percentage, 0), COALESCE(is_active, 'yes') FROM business_club_settings WHERE (del_status IS NULL OR del_status='Live') LIMIT 1";
                    using var r = cmd.ExecuteReader();
                    if (!r.Read()) return; // No settings = feature disabled
                    profitSharePercentage = r.IsDBNull(0) ? 0 : r.GetDouble(0);
                    isActive = r.IsDBNull(1) ? "no" : r.GetString(1);
                }

                if (profitSharePercentage <= 0) return;
                if (isActive != "yes" && isActive != "1" && isActive != "true") return;

                // Check if customer is a registered ACTIVE member in business_club_members
                string memberId = "";
                double currentEarnedBalance = 0;
                double currentTotalEarned = 0;
                double depositAmount = 0;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"SELECT member_id, COALESCE(earned_balance, 0), COALESCE(total_earned, 0), COALESCE(membership_amount, 0) 
                        FROM business_club_members 
                        WHERE customer_id = @cid 
                          AND status = 'active' 
                          AND (del_status IS NULL OR del_status = 'Live') 
                        LIMIT 1";
                    cmd.Parameters.AddWithValue("@cid", customerId);
                    using var r = cmd.ExecuteReader();
                    if (!r.Read()) return; // Customer is NOT a BC member — skip
                    memberId = r.IsDBNull(0) ? "" : r.GetString(0);
                    currentEarnedBalance = r.GetDouble(1);
                    currentTotalEarned = r.GetDouble(2);
                    depositAmount = r.GetDouble(3);
                }

                if (string.IsNullOrEmpty(memberId)) return;

                // RULE: Monthly bill total capped at deposit amount for profit
                // Deposit 10k → profit only on first 10k of monthly shopping
                double billedThisMonth = 0;
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"SELECT COALESCE(SUM(grand_total), 0) FROM sales 
                        WHERE customer_id = @cid AND (del_status IS NULL OR del_status='Live')
                          AND sale_date >= @mstart AND sale_date <= @mend AND id != @sid";
                    cmd.Parameters.AddWithValue("@cid", customerId);
                    cmd.Parameters.AddWithValue("@mstart", DateTime.Now.ToString("yyyy-MM-01"));
                    cmd.Parameters.AddWithValue("@mend", DateTime.Now.ToString("yyyy-MM-") + DateTime.DaysInMonth(DateTime.Now.Year, DateTime.Now.Month));
                    cmd.Parameters.AddWithValue("@sid", saleId);
                    billedThisMonth = Convert.ToDouble(cmd.ExecuteScalar() ?? 0);
                }

                if (billedThisMonth >= depositAmount) return; // Monthly shopping cap reached

                double remainingCap = depositAmount - billedThisMonth;
                double qualifyingAmount = Math.Min(grandTotal, remainingCap);
                if (qualifyingAmount <= 0) return;

                // Calculate profit for this sale from cart items
                double totalProfit = 0;
                foreach (var item in _lastSavedItems)
                {
                    double purchasePrice = 0;
                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.CommandText = "SELECT COALESCE(purchase_price, 0) FROM items WHERE lower(code) = lower(@code) LIMIT 1";
                        cmd.Parameters.AddWithValue("@code", item.ItemCode ?? "");
                        var val = cmd.ExecuteScalar();
                        if (val != null && val != DBNull.Value) purchasePrice = Convert.ToDouble(val);
                    }
                    double itemProfit = (item.Price - purchasePrice) * item.Qty;
                    if (itemProfit > 0) totalProfit += itemProfit;
                }

                if (totalProfit <= 0) return;

                // If only partial bill qualifies, scale profit proportionally
                if (qualifyingAmount < grandTotal && grandTotal > 0)
                {
                    totalProfit = totalProfit * (qualifyingAmount / grandTotal);
                }

                double creditAmount = Math.Round(totalProfit * profitSharePercentage / 100.0, 2);
                if (creditAmount <= 0) return;

                double newEarnedBalance = currentEarnedBalance + creditAmount;
                double newTotalEarned = currentTotalEarned + creditAmount;

                // Update earned_balance in business_club_members (locked_balance NEVER changes)
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"UPDATE business_club_members 
                        SET earned_balance = @newBal, 
                            total_earned = @newTotal,
                            updated_at = datetime('now')
                        WHERE member_id = @mid AND customer_id = @cid";
                    cmd.Parameters.AddWithValue("@newBal", newEarnedBalance);
                    cmd.Parameters.AddWithValue("@newTotal", newTotalEarned);
                    cmd.Parameters.AddWithValue("@mid", memberId);
                    cmd.Parameters.AddWithValue("@cid", customerId);
                    cmd.ExecuteNonQuery();
                }

                // Insert transaction record into business_club_transactions
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"INSERT INTO business_club_transactions 
                        (member_id, customer_id, sale_id, type, amount, balance_before, balance_after, description, transaction_date, company_id, del_status, created_at, updated_at, SyncStatus)
                        VALUES (@mid, @cid, @sid, 'profit_credit', @amt, @bb, @ba, @desc, date('now'), 1, 'Live', datetime('now'), datetime('now'), 'Local')";
                    cmd.Parameters.AddWithValue("@mid", memberId);
                    cmd.Parameters.AddWithValue("@cid", customerId);
                    cmd.Parameters.AddWithValue("@sid", saleId);
                    cmd.Parameters.AddWithValue("@amt", creditAmount);
                    cmd.Parameters.AddWithValue("@bb", currentEarnedBalance);
                    cmd.Parameters.AddWithValue("@ba", newEarnedBalance);
                    cmd.Parameters.AddWithValue("@desc", $"Profit share from sale #{saleId}");
                    cmd.ExecuteNonQuery();
                }

                // Also update old customer_wallets for backward compatibility (if exists)
                try
                {
                    long walletId = 0;
                    double walletBalance = 0;
                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.CommandText = "SELECT id, COALESCE(balance,0) FROM customer_wallets WHERE customer_id=@cid AND (del_status IS NULL OR del_status='Live') LIMIT 1";
                        cmd.Parameters.AddWithValue("@cid", customerId);
                        using var r = cmd.ExecuteReader();
                        if (r.Read()) { walletId = r.GetInt64(0); walletBalance = r.GetDouble(1); }
                    }
                    if (walletId == 0)
                    {
                        using var cmd = conn.CreateCommand();
                        cmd.CommandText = @"INSERT INTO customer_wallets (customer_id, company_id, total_earned, balance, total_redeemed, del_status, created_at, updated_at)
                            VALUES (@cid, 1, 0, 0, 0, 'Live', datetime('now'), datetime('now'));
                            SELECT last_insert_rowid();";
                        cmd.Parameters.AddWithValue("@cid", customerId);
                        walletId = Convert.ToInt64(cmd.ExecuteScalar());
                        walletBalance = 0;
                    }
                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.CommandText = @"UPDATE customer_wallets SET balance = IFNULL(balance,0) + @amt, total_earned = IFNULL(total_earned,0) + @amt, updated_at = datetime('now') WHERE id = @wid";
                        cmd.Parameters.AddWithValue("@amt", creditAmount);
                        cmd.Parameters.AddWithValue("@wid", walletId);
                        cmd.ExecuteNonQuery();
                    }
                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.CommandText = @"INSERT INTO wallet_transactions (wallet_id, customer_id, sale_id, type, amount, balance_before, balance_after, description, transaction_date, company_id, del_status, created_at, updated_at, SyncStatus)
                            VALUES (@wid, @cid, @sid, 'credit', @amt, @bb, @ba, @desc, date('now'), 1, 'Live', datetime('now'), datetime('now'), 'Local')";
                        cmd.Parameters.AddWithValue("@wid", walletId);
                        cmd.Parameters.AddWithValue("@cid", customerId);
                        cmd.Parameters.AddWithValue("@sid", saleId);
                        cmd.Parameters.AddWithValue("@amt", creditAmount);
                        cmd.Parameters.AddWithValue("@bb", walletBalance);
                        cmd.Parameters.AddWithValue("@ba", walletBalance + creditAmount);
                        cmd.Parameters.AddWithValue("@desc", $"Profit share from sale #{saleId}");
                        cmd.ExecuteNonQuery();
                    }
                }
                catch { /* backward compat - non-critical */ }
            }
            catch (Exception ex)
            {
                Services.LogService.Error("CreditBusinessClubProfit failed", ex);
            }
        }

        /// <summary>
        /// Checks if the given customer is an active Business Club member.
        /// Used to skip schemes/promotions/loyalty for BC members.
        /// </summary>
        private bool IsBusinessClubMember(long customerId)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT COUNT(*) FROM business_club_members 
                    WHERE customer_id = @cid AND status = 'active' 
                    AND (del_status IS NULL OR del_status = 'Live')";
                cmd.Parameters.AddWithValue("@cid", customerId);
                return (long)(cmd.ExecuteScalar() ?? 0L) > 0;
            }
            catch { return false; }
        }

        private string BuildSyncPayload(string vchCode, string paymentMode, double grandTotal,
            List<PaymentLine>? payments = null, double changeAmount = 0)
        {
            double subTotal = _cart.Sum(x => x.Price * x.Qty);
            double totalDisc = _cart.Sum(x => x.TotDisc);
            double totalTax = _cart.Sum(x => x.TaxableAmount * x.TaxPerc / 100.0);

            var items = _cart.Select(item => (object)new Dictionary<string, object?>
            {
                ["item_code"] = item.ItemCode,
                ["qty"] = item.Qty,
                ["menu_unit_price"] = item.Price,
                ["menu_vat_percentage"] = item.TaxPerc,
                ["item_tax_amount"] = Math.Round(item.TaxableAmount * item.TaxPerc / 100.0, 2),
                ["discount_amount"] = item.TotDisc,
                ["discount_type"] = "fixed",
                ["menu_taxes"] = BuildItemMenuTaxes(item)
            }).ToList();

            var payList = payments != null && payments.Count > 0
                ? payments.Select(p =>
                {
                    var d = new Dictionary<string, object?> { ["payment_name"] = p.Name, ["amount"] = p.Amount };
                    if (p.Name == "Loyalty Point") d["usage_point"] = p.UsagePoints;
                    return (object)d;
                }).ToList()
                : new List<object> { new Dictionary<string, object?> { ["payment_name"] = paymentMode, ["amount"] = grandTotal } };

            var payload = new Dictionary<string, object?>
            {
                ["local_id"] = vchCode,
                ["invoice_no"] = _lastSavedSaleNo,
                ["sale_date"] = DateTime.Now.ToString("dd-MM-yyyy"),
                ["date_time"] = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"),
                ["customer_id"] = CloudCustomerId(_currentCustomer),
                ["sub_total"] = Math.Round(subTotal, 2),
                ["given_amount"] = Math.Round(payments?.Sum(p => p.Amount) ?? grandTotal, 2),
                ["paid_amount"] = grandTotal,
                ["change_amount"] = Math.Round(changeAmount, 2),
                ["disc"] = Math.Round(totalDisc, 2),
                ["vat"] = Math.Round(totalTax, 2),
                ["total_payable"] = grandTotal,
                ["grand_total"] = grandTotal,
                ["mrp_total"] = Math.Round(_cart.Sum(x => x.MRP * x.Qty), 2),
                ["savings"] = Math.Round(Math.Max(0, _cart.Sum(x => x.MRP * x.Qty) - grandTotal), 2),
                ["sale_vat_objects"] = BuildSaleVatObject(),
                ["note"] = $"POS Sale - {paymentMode}",
                ["items"] = items,
                ["payments"] = payList
            };
            return System.Text.Json.JsonSerializer.Serialize(payload);
        }

        // ═══════ GST TAX BREAKDOWN (CGST/SGST intra-state, IGST inter-state) ═══════
        // Mirrors the server's GstTaxService: splits each taxable line into
        // CGST/SGST (half-half) for intra-state, or IGST for inter-state.
        // sale_vat_objects is what the GST/Tax reports (desktop + server) consume.

        private List<object> BuildItemMenuTaxes(BusyCartItem item)
        {
            var list = new List<object>();
            double taxAmt = item.TaxableAmount * item.TaxPerc / 100.0;
            if (taxAmt <= 0) return list;
            double rate = Math.Round(item.TaxPerc, 2);
            if (IsInterState())
            {
                list.Add(new Dictionary<string, object?>
                {
                    ["tax_field_type"] = "IGST",
                    ["tax_field_name"] = "IGST",
                    ["tax_field_amount"] = Math.Round(taxAmt, 2),
                    ["tax_field_percentage"] = rate
                });
            }
            else
            {
                double half = Math.Round(taxAmt / 2.0, 2);
                double halfRate = Math.Round(rate / 2.0, 2);
                list.Add(new Dictionary<string, object?>
                {
                    ["tax_field_type"] = "CGST",
                    ["tax_field_name"] = "CGST",
                    ["tax_field_amount"] = half,
                    ["tax_field_percentage"] = halfRate
                });
                list.Add(new Dictionary<string, object?>
                {
                    ["tax_field_type"] = "SGST",
                    ["tax_field_name"] = "SGST",
                    ["tax_field_amount"] = half,
                    ["tax_field_percentage"] = halfRate
                });
            }
            return list;
        }

        private string? BuildSaleVatObject()
        {
            var parts = new List<object>();
            foreach (var item in _cart)
                parts.AddRange(BuildItemMenuTaxes(item));
            return parts.Count > 0 ? System.Text.Json.JsonSerializer.Serialize(parts) : null;
        }

        private bool IsInterState()
        {
            try
            {
                using var conn = _db.GetConnection();
                long outletId = RashanKiDukan.Services.OutletContext.GetSelectedOutletId(_db);
                string outletState = "";
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT IFNULL((SELECT state_code FROM states WHERE id = o.state_id), '') FROM outlets o WHERE id=@id";
                    cmd.Parameters.AddWithValue("@id", outletId);
                    outletState = cmd.ExecuteScalar()?.ToString() ?? "";
                }
                if (_currentCustomer == null || _currentCustomer.Id <= 1)
                    return false; // Walk-in = intra-state
                string custState = "";
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT IFNULL((SELECT state_code FROM states WHERE id = c.state_id), '') FROM customers c WHERE id=@id";
                    cmd.Parameters.AddWithValue("@id", _currentCustomer.Id);
                    custState = cmd.ExecuteScalar()?.ToString() ?? "";
                }
                if (string.IsNullOrEmpty(outletState) || string.IsNullOrEmpty(custState))
                    return false;
                return !outletState.Equals(custState, StringComparison.OrdinalIgnoreCase);
            }
            catch { return false; }
        }

        private long? CloudCustomerId(CustomerInfo? c)
        {
            if (c == null || c.Id <= 0) return null;
            try
            {
                using var conn = _db.GetConnection();
                // Check customers table first (has ServerId from sync)
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT IFNULL(ServerId, id) FROM customers WHERE id=@id AND del_status='Live' LIMIT 1";
                    cmd.Parameters.AddWithValue("@id", c.Id);
                    var val = cmd.ExecuteScalar();
                    if (val != null && long.TryParse(val.ToString(), out long sid) && sid > 0) return sid;
                }
                // Fallback: Master1
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT ServerId FROM Master1 WHERE MasterType='Party' AND PartyType IN ('Customer','Both') AND ServerId=@id AND SyncStatus='Synced' LIMIT 1";
                    cmd.Parameters.AddWithValue("@id", c.Id);
                    var val = cmd.ExecuteScalar();
                    if (val != null && long.TryParse(val.ToString(), out long sid) && sid > 0) return sid;
                }
            }
            catch { }
            // Return local ID as fallback (server will resolve by mapping)
            return c.Id > 1 ? c.Id : null;
        }

        // ═══════ UTILITY ═══════

        private void ClearBill()
        {
            if (_cart.Count == 0)
            {
                if (_heldBills.Count > 0) UnholdBill();
                else BtnBack_Click(new object(), new RoutedEventArgs());
                return;
            }
            if (MessageBox.Show("Clear entire bill?\nAll items will be removed.", "Confirm Clear",
                MessageBoxButton.YesNo, MessageBoxImage.Question) == MessageBoxResult.Yes)
            { _cart.Clear(); _srNo = 0; _schemeApplied = false; _schemeAppliedTitle = ""; _couponDiscount = 0; _couponCode = ""; UpdateTotals(); }
        }

        private void ShowInfo(string msg) =>
            MessageBox.Show(msg, "Rashan Ki Dukan", MessageBoxButton.OK, MessageBoxImage.Information);

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            if (_cart.Count > 0)
            {
                if (MessageBox.Show("Clear bill and exit POS?", "Confirm Exit",
                    MessageBoxButton.YesNo, MessageBoxImage.Question) != MessageBoxResult.Yes) return;
            }
            Shutdown();
            _dashboard?.ShowDashboard();
        }

        /// <summary>
        /// MainDashboard calls Shutdown() before disposing/replacing this page.
        /// Stops the clock timer and network-check timer so they don't leak and
        /// keep firing when a new POSPage instance is created on navigation.
        /// </summary>
        public void Shutdown()
        {
            if (_clockTimer != null) _clockTimer.Stop();
            if (_netCheckTimer != null) _netCheckTimer.Stop();
        }
    }

    // ═══════ MODEL CLASSES ═══════

    public class BusyCartItem : System.ComponentModel.INotifyPropertyChanged
    {
        private int _srNo; private string _itemCode = ""; private string _itemName = "";
        private string _hsnCode = ""; private double _mrp; private double _qty;
        private string _unit = ""; private double _listPrice; private double _disc;
        private double _totDisc; private double _price; private double _amount;
        private double _taxPerc; private double _taxableAmount; private bool _isQtyMode;
        private string _appliedSchemeText = "";
        private bool _hasScheme;
        private double _originalQtyBeforeScheme = 0; // stores qty before tier pricing changed it
        private string _category = "";

        public event System.ComponentModel.PropertyChangedEventHandler? PropertyChanged;
        private void Raise([System.Runtime.CompilerServices.CallerMemberName] string? name = null)
            => PropertyChanged?.Invoke(this, new System.ComponentModel.PropertyChangedEventArgs(name));

        public int SrNo { get => _srNo; set { _srNo = value; Raise(); } }
        public string ItemCode { get => _itemCode; set { _itemCode = value; Raise(); } }
        public string ItemName { get => _itemName; set { _itemName = value; Raise(); } }
        public string HSNCode { get => _hsnCode; set { _hsnCode = value; Raise(); } }
        public double MRP { get => _mrp; set { _mrp = value; Raise(); } }
        public double Qty { get => _qty; set { _qty = value; Raise(); } }
        public string Unit { get => _unit; set { _unit = value; Raise(); } }
        public double ListPrice { get => _listPrice; set { _listPrice = value; Raise(); } }
        public double Disc { get => _disc; set { _disc = value; Raise(); } }
        public double TotDisc { get => _totDisc; set { _totDisc = value; Raise(); } }
        public double Price { get => _price; set { _price = value; Raise(); } }
        public double Amount { get => _amount; set { _amount = value; Raise(); } }
        public double TaxPerc { get => _taxPerc; set { _taxPerc = value; Raise(); Raise(nameof(TaxAmount)); } }
        public double TaxableAmount { get => _taxableAmount; set { _taxableAmount = value; Raise(); Raise(nameof(TaxAmount)); } }
        /// <summary>Tax amount in ₹ (TaxableAmount × TaxPerc / 100)</summary>
        public double TaxAmount => Math.Round(_taxableAmount * _taxPerc / 100.0, 2);
        public bool IsQtyMode { get => _isQtyMode; set { _isQtyMode = value; Raise(); } }
        public string AppliedSchemeText { get => _appliedSchemeText; set { _appliedSchemeText = value; Raise(); } }
        public bool HasScheme { get => _hasScheme; set { _hasScheme = value; Raise(); } }
        public double OriginalQtyBeforeScheme { get => _originalQtyBeforeScheme; set { _originalQtyBeforeScheme = value; } }
        public string Category { get => _category; set { _category = value; Raise(); } }
        /// <summary>Display label: "Category | GST 5%" style</summary>
        public string CategoryTaxLabel => string.IsNullOrEmpty(_category)
            ? (_taxPerc > 0 ? $"GST {_taxPerc}%" : "")
            : (_taxPerc > 0 ? $"{_category} | GST {_taxPerc}%" : _category);
    }

    public class BusyItemInfo
    {
        public string Code { get; set; } = "";
        public string Name { get; set; } = "";
        public string HSNCode { get; set; } = "";
        public double MRP { get; set; }
        public double Rate { get; set; }
        public string TaxCat { get; set; } = "";
        public string Unit { get; set; } = "";
    }
}

