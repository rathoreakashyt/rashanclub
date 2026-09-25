using System.Windows;
using System.Windows.Controls;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class CreateCustomerV2Page : UserControl
    {
        private readonly DatabaseService _db = new DatabaseService();
        private readonly MainDashboard? _dashboard;
        private string _editCode = "";

        public CreateCustomerV2Page() { InitializeComponent(); }
        public CreateCustomerV2Page(MainDashboard dashboard) : this()
        {
            _dashboard = dashboard;
            LoadStates();
        }
        public CreateCustomerV2Page(MainDashboard dashboard, string code) : this()
        {
            _dashboard = dashboard;
            LoadStates();
            LoadForEdit(code);
        }

        private void LoadStates()
        {
            var states = new (string name, string code)[]
            {
                ("Andhra Pradesh","01"),("Arunachal Pradesh","12"),("Assam","18"),("Bihar","10"),
                ("Chhattisgarh","22"),("Goa","30"),("Gujarat","24"),("Haryana","06"),
                ("Himachal Pradesh","02"),("Jharkhand","20"),("Karnataka","29"),("Kerala","32"),
                ("Madhya Pradesh","23"),("Maharashtra","27"),("Manipur","14"),("Meghalaya","17"),
                ("Mizoram","15"),("Nagaland","13"),("Odisha","21"),("Punjab","03"),
                ("Rajasthan","08"),("Sikkim","11"),("Tamil Nadu","33"),("Telangana","36"),
                ("Tripura","16"),("Uttar Pradesh","09"),("Uttarakhand","05"),("West Bengal","19"),
                ("Delhi","07"),("Jammu & Kashmir","01"),("Ladakh","38"),
            };
            foreach (var s in states)
                cmbState.Items.Add(new ComboBoxItem { Content = $"{s.name} ({s.code})" });
            cmbState.SelectedIndex = 28;
        }

        private void LoadForEdit(string code)
        {
            try
            {
                _editCode = code;
                if (lblTitle != null) lblTitle.Text = "Edit Customer";
                if (btnSave != null) btnSave.Content = "\uE73E  Update";

                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT Name, Phone, Email, Address, State, PinCode, GSTIN,
                                           OpeningBalance, DrCr, CreditLimit, BusinessType,
                                           SameOrDiffState, DateOfBirth, DateOfAnniversary
                                    FROM Master1 WHERE Code=@code";
                cmd.Parameters.AddWithValue("@code", code);
                using var r = cmd.ExecuteReader();
                if (!r.Read()) return;

                txtName.Text        = r["Name"]?.ToString() ?? "";
                txtPhone.Text       = r["Phone"]?.ToString() ?? "";
                txtEmail.Text       = r["Email"]?.ToString() ?? "";
                txtAddress.Text     = r["Address1"] is string a1 && !string.IsNullOrEmpty(a1)
                    ? a1 : r["Address"]?.ToString() ?? "";
                txtGSTIN.Text       = r["GSTIN"]?.ToString() ?? "";
                txtOpeningBalance.Text = r["OpeningBalance"] is double ob && ob != 0
                    ? Math.Abs(ob).ToString("F2") : "";
                SelectByText(cmbDrCr, r["DrCr"]?.ToString() ?? "Dr");
                txtCreditLimit.Text = r["CreditLimit"] is double cl && cl != 0
                    ? cl.ToString("F2") : "";
                SelectByText(cmbBusinessType, r["BusinessType"]?.ToString() ?? "B2C");
                SelectByText(cmbSameState, r["SameOrDiffState"]?.ToString() ?? "Same State");
                SelectByText(cmbState, r["State"]?.ToString() ?? "");

                string dob = r["DateOfBirth"]?.ToString() ?? "";
                if (DateTime.TryParse(dob, out var d1)) dtpDOB.SelectedDate = d1;
                string ann = r["DateOfAnniversary"]?.ToString() ?? "";
                if (DateTime.TryParse(ann, out var d2)) dtpAnniversary.SelectedDate = d2;
            }
            catch { }
        }

        private static void SelectByText(ComboBox cb, string text)
        {
            if (string.IsNullOrEmpty(text)) return;
            for (int i = 0; i < cb.Items.Count; i++)
            {
                var content = (cb.Items[i] as ComboBoxItem)?.Content?.ToString() ?? "";
                if (content.StartsWith(text, System.StringComparison.OrdinalIgnoreCase)
                    || string.Equals(content, text, System.StringComparison.OrdinalIgnoreCase))
                {
                    cb.SelectedIndex = i;
                    return;
                }
            }
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowPage(new CustomersListPage(_dashboard!));
        }

        private void BtnSave_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(txtName.Text))
            {
                MessageBox.Show("Please enter customer name.", "Validation",
                    MessageBoxButton.OK, MessageBoxImage.Warning);
                txtName.Focus();
                return;
            }

            // Walk-in Customer naam block — system mein sirf ek hoga
            if (txtName.Text.Trim().Equals("Walk-in Customer", StringComparison.OrdinalIgnoreCase))
            {
                MessageBox.Show("\"Walk-in Customer\" naam reserved hai — dusra naam use karein.", "Validation",
                    MessageBoxButton.OK, MessageBoxImage.Warning);
                txtName.Focus();
                return;
            }

            if (string.IsNullOrWhiteSpace(txtPhone.Text))
            {
                MessageBox.Show("Please enter phone number.", "Validation",
                    MessageBoxButton.OK, MessageBoxImage.Warning);
                txtPhone.Focus();
                return;
            }

            // Phone duplicate check (new customer only)
            if (string.IsNullOrEmpty(_editCode))
            {
                string checkPhone = txtPhone.Text.Trim();
                if (checkPhone.StartsWith("+91-")) checkPhone = checkPhone[4..];
                else if (checkPhone.StartsWith("+91")) checkPhone = checkPhone[3..];
                try
                {
                    using var checkConn = _db.GetConnection();
                    using var checkCmd = checkConn.CreateCommand();
                    checkCmd.CommandText = @"SELECT COUNT(*) FROM Master1
                        WHERE MasterType='Party' AND PartyType IN ('Customer','Both')
                          AND LOWER(TRIM(COALESCE(Phone,''))) = LOWER(TRIM(@p))
                          AND (DelStatus IS NULL OR DelStatus != 'Deleted')";
                    checkCmd.Parameters.AddWithValue("@p", checkPhone);
                    long cnt = (long)(checkCmd.ExecuteScalar() ?? 0L);
                    if (cnt > 0)
                    {
                        MessageBox.Show($"Is phone number ({checkPhone}) se already ek customer registered hai.\nDuplicate customer save nahi hoga.",
                            "Duplicate Phone", MessageBoxButton.OK, MessageBoxImage.Warning);
                        txtPhone.Focus();
                        return;
                    }
                }
                catch { }
            }

            // ═══ ENTERPRISE: GSTIN Validation with checksum ═══
            if (!string.IsNullOrWhiteSpace(txtGSTIN.Text))
            {
                var gstResult = Services.GstValidationService.ValidateGstin(txtGSTIN.Text);
                if (!gstResult.IsValid)
                {
                    var answer = MessageBox.Show($"⚠️ GSTIN Validation Failed:\n{gstResult.Message}\n\nSave anyway?",
                        "GST Validation", MessageBoxButton.YesNo, MessageBoxImage.Warning);
                    if (answer == MessageBoxResult.No)
                    {
                        txtGSTIN.Focus();
                        return;
                    }
                }
                // Auto-fill state from GSTIN if not already set
                if (gstResult.IsValid && gstResult.StateCode > 0 && cmbState.SelectedIndex <= 0)
                {
                    SelectByText(cmbState, gstResult.StateName);
                }
            }

            try
            {
                double opening = 0;
                double.TryParse(txtOpeningBalance.Text, out opening);
                if (cmbDrCr.SelectedIndex == 1) opening = -opening;
                double creditLimit = 0;
                double.TryParse(txtCreditLimit.Text, out creditLimit);

                string phone = txtPhone.Text.Trim();
                if (phone.StartsWith("+91-")) phone = phone[4..];
                else if (phone.StartsWith("+91")) phone = phone[3..];

                string dob = dtpDOB.SelectedDate?.ToString("yyyy-MM-dd") ?? "";
                string ann = dtpAnniversary.SelectedDate?.ToString("yyyy-MM-dd") ?? "";

                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();

                string code = _editCode;
                if (_editCode != "")
                {
                    using var cmd = conn.CreateCommand();
                    cmd.Transaction = txn;
                    cmd.CommandText = @"UPDATE Master1 SET
                        Name=@name, Phone=@phone, Email=@email, Address=@addr, Address1=@addr,
                        State=@state, GSTIN=@gstin, OpeningBalance=@bal, DrCr=@drcr,
                        CreditLimit=@cl, BusinessType=@btype, SameOrDiffState=@sstate,
                        DateOfBirth=@dob, DateOfAnniversary=@ann,
                        IsActive=1, UpdatedAt=datetime('now'), SyncStatus='Local',
                        SyncVersion=IFNULL(SyncVersion,1)+1
                        WHERE Code=@code";
                    cmd.Parameters.AddWithValue("@code", _editCode);
                    cmd.Parameters.AddWithValue("@name",   txtName.Text.Trim());
                    cmd.Parameters.AddWithValue("@phone",  phone);
                    cmd.Parameters.AddWithValue("@email",  txtEmail.Text.Trim());
                    cmd.Parameters.AddWithValue("@addr",   txtAddress.Text.Trim());
                    cmd.Parameters.AddWithValue("@state",  cmbState.Text);
                    cmd.Parameters.AddWithValue("@gstin",  txtGSTIN.Text.Trim());
                    cmd.Parameters.AddWithValue("@bal",    opening);
                    cmd.Parameters.AddWithValue("@drcr",   cmbDrCr.Text == "Credit" ? "Cr" : "Dr");
                    cmd.Parameters.AddWithValue("@cl",     creditLimit);
                    cmd.Parameters.AddWithValue("@btype",  cmbBusinessType.Text);
                    cmd.Parameters.AddWithValue("@sstate", cmbSameState.Text);
                    cmd.Parameters.AddWithValue("@dob",    dob);
                    cmd.Parameters.AddWithValue("@ann",    ann);
                    cmd.ExecuteNonQuery();
                }
                else
                {
                    code = "CUS" + DateTime.Now.ToString("yyyyMMddHHmmssfff");
                    using var cmd = conn.CreateCommand();
                    cmd.Transaction = txn;
                    cmd.CommandText = @"INSERT INTO Master1
                        (Code, Name, MasterType, PartyType, Phone, Email, Address, Address1, State, GSTIN,
                         OpeningBalance, DrCr, CreditLimit, BusinessType, SameOrDiffState,
                         DateOfBirth, DateOfAnniversary, IsActive, CreatedAt, UpdatedAt, SyncStatus)
                        VALUES
                        (@code, @name, 'Party', 'Customer', @phone, @email, @addr, @addr, @state, @gstin,
                         @bal, @drcr, @cl, @btype, @sstate, @dob, @ann,
                         1, datetime('now'), datetime('now'), 'Local')";
                    cmd.Parameters.AddWithValue("@code", code);
                    cmd.Parameters.AddWithValue("@name",   txtName.Text.Trim());
                    cmd.Parameters.AddWithValue("@phone",  phone);
                    cmd.Parameters.AddWithValue("@email",  txtEmail.Text.Trim());
                    cmd.Parameters.AddWithValue("@addr",   txtAddress.Text.Trim());
                    cmd.Parameters.AddWithValue("@state",  cmbState.Text);
                    cmd.Parameters.AddWithValue("@gstin",  txtGSTIN.Text.Trim());
                    cmd.Parameters.AddWithValue("@bal",    opening);
                    cmd.Parameters.AddWithValue("@drcr",   cmbDrCr.Text == "Credit" ? "Cr" : "Dr");
                    cmd.Parameters.AddWithValue("@cl",     creditLimit);
                    cmd.Parameters.AddWithValue("@btype",  cmbBusinessType.Text);
                    cmd.Parameters.AddWithValue("@sstate", cmbSameState.Text);
                    cmd.Parameters.AddWithValue("@dob",    dob);
                    cmd.Parameters.AddWithValue("@ann",    ann);
                    cmd.ExecuteNonQuery();
                }

                UpsertCustomerMirror(conn, txn, code, phone, opening, creditLimit, dob, ann);

                txn.Commit();
                MessageBox.Show("Customer saved successfully!", "Success",
                    MessageBoxButton.OK, MessageBoxImage.Information);

                _dashboard?.ShowPage(new CustomersListPage(_dashboard!));
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error",
                    MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        /// <summary>
        /// Keep the customers mirror table in sync with the Master1 row so POS /
        /// F2 lookups (which read `customers`) and sales customer_id resolution
        /// find the customer. Match by ServerId first (already-synced row), then
        /// by phone, then insert fresh. All paths mark SyncStatus='Local' so the
        /// next pull adopts / push marks it Synced.
        /// </summary>
        private void UpsertCustomerMirror(SqliteConnection conn, SqliteTransaction txn, string code, string phone,
            double opening, double creditLimit, string dob, string ann)
        {
            long serverId = 0;
            using (var f = conn.CreateCommand())
            {
                f.Transaction = txn;
                f.CommandText = "SELECT IFNULL(ServerId,0) FROM Master1 WHERE Code=@code LIMIT 1";
                f.Parameters.AddWithValue("@code", code);
                var v = f.ExecuteScalar();
                if (v != null) serverId = Convert.ToInt64(v);
            }

            long stId = 0;
            string stateName = cmbState.Text.Split('(')[0].Trim();
            if (!string.IsNullOrEmpty(stateName))
            {
                using var s = conn.CreateCommand();
                s.Transaction = txn;
                s.CommandText = "SELECT Id FROM states WHERE state_name=@n LIMIT 1";
                s.Parameters.AddWithValue("@n", stateName);
                var sv = s.ExecuteScalar();
                if (sv != null) stId = Convert.ToInt64(sv);
            }

            string mirrorSql = @"UPDATE customers SET
                name=@name, phone=@phone, email=@email, address=@addr, gst_number=@gstin,
                opening_balance=@bal, opening_balance_type=@drcr, credit_limit=@cl,
                customer_type=@btype, business_type=@btype, same_or_diff_state=@sstate,
                date_of_birth=@dob, date_of_anniversary=@ann, state_id=@stid,
                SyncStatus='Local', updated_at=datetime('now')
                WHERE {0}";

            void BindMirror(Microsoft.Data.Sqlite.SqliteCommand c)
            {
                c.Transaction = txn;
                c.Parameters.AddWithValue("@name", txtName.Text.Trim());
                c.Parameters.AddWithValue("@phone", phone);
                c.Parameters.AddWithValue("@email", txtEmail.Text.Trim());
                c.Parameters.AddWithValue("@addr", txtAddress.Text.Trim());
                c.Parameters.AddWithValue("@gstin", txtGSTIN.Text.Trim());
                c.Parameters.AddWithValue("@bal", opening);
                c.Parameters.AddWithValue("@drcr", cmbDrCr.Text == "Credit" ? "Cr" : "Dr");
                c.Parameters.AddWithValue("@cl", creditLimit);
                c.Parameters.AddWithValue("@btype", cmbBusinessType.Text);
                c.Parameters.AddWithValue("@sstate", cmbSameState.Text);
                c.Parameters.AddWithValue("@dob", dob);
                c.Parameters.AddWithValue("@ann", ann);
                c.Parameters.AddWithValue("@stid", stId > 0 ? (object)stId : DBNull.Value);
            }

            int rows = 0;
            if (serverId > 0)
            {
                using var u = conn.CreateCommand();
                BindMirror(u);
                u.CommandText = string.Format(mirrorSql, "ServerId=@sid AND del_status='Live'");
                u.Parameters.AddWithValue("@sid", serverId);
                rows = u.ExecuteNonQuery();
            }
            if (rows == 0 && !string.IsNullOrEmpty(phone))
            {
                using var u = conn.CreateCommand();
                BindMirror(u);
                u.CommandText = string.Format(mirrorSql,
                    "(IFNULL(ServerId,0)=0 OR ServerId=@sid2) AND del_status='Live' AND LOWER(TRIM(IFNULL(Phone,''))) = LOWER(TRIM(@phone))");
                u.Parameters.AddWithValue("@sid2", serverId);
                u.Parameters.AddWithValue("@phone", phone);
                rows = u.ExecuteNonQuery();
            }
            if (rows == 0)
            {
                using var i = conn.CreateCommand();
                i.Transaction = txn;
                i.CommandText = @"INSERT INTO customers
                    (name, email, phone, address, gst_number, opening_balance, opening_balance_type,
                     credit_limit, customer_type, business_type, same_or_diff_state, date_of_birth,
                     date_of_anniversary, state_id, del_status, SyncStatus, created_at, updated_at)
                    VALUES
                    (@name,@email,@phone,@addr,@gstin,@bal,@drcr,@cl,@btype,@btype,@sstate,@dob,@ann,
                     @stid,'Live','Local',datetime('now'),datetime('now'))";
                BindMirror(i);
                i.ExecuteNonQuery();
            }
        }

        // ═══ GSTIN Live Validation ═══
        private async void TxtGSTIN_TextChanged(object sender, System.Windows.Controls.TextChangedEventArgs e)
        {
            string gstin = txtGSTIN.Text.Trim();
            if (string.IsNullOrEmpty(gstin))
            {
                lblGstinStatus.Text = "15 character Indian GSTIN";
                lblGstinStatus.Foreground = new System.Windows.Media.SolidColorBrush(
                    (System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#9CA3AF"));
                return;
            }
            if (gstin.Length < 15)
            {
                lblGstinStatus.Text = $"⏳ {gstin.Length}/15 characters...";
                lblGstinStatus.Foreground = new System.Windows.Media.SolidColorBrush(
                    (System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#D97706"));
                return;
            }
            // Offline validate first (instant)
            var result = Services.GstValidationService.ValidateGstin(gstin);
            if (!result.IsValid)
            {
                lblGstinStatus.Text = $"❌ {result.Message}";
                lblGstinStatus.Foreground = new System.Windows.Media.SolidColorBrush(
                    (System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#E11D48"));
                return;
            }

            // Show offline result immediately
            lblGstinStatus.Text = $"🔄 Fetching business name...";
            lblGstinStatus.Foreground = new System.Windows.Media.SolidColorBrush(
                (System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#4F46E5"));
            if (result.StateCode > 0) SelectByText(cmbState, result.StateName);

            // Online lookup (async — fetch business name)
            try
            {
                var online = await Services.GstValidationService.VerifyGstinOnlineAsync(gstin);
                // Check if user hasn't changed the text while we were fetching
                if (txtGSTIN.Text.Trim().ToUpper() != gstin.ToUpper()) return;

                if (!string.IsNullOrEmpty(online.LegalName))
                {
                    string display = !string.IsNullOrEmpty(online.TradeName) && online.TradeName != online.LegalName
                        ? $"{online.TradeName} ({online.LegalName})"
                        : online.LegalName;
                    lblGstinStatus.Text = $"✅ {display} — {online.StateName} | {online.Status}";
                    lblGstinStatus.Foreground = new System.Windows.Media.SolidColorBrush(
                        (System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#059669"));

                    // Auto-fill customer name if empty
                    if (string.IsNullOrWhiteSpace(txtName.Text))
                        txtName.Text = online.DisplayName;
                }
                else
                {
                    // Online unavailable — show offline result
                    lblGstinStatus.Text = $"✅ Valid — {result.StateName} | {result.EntityType} (name unavailable offline)";
                    lblGstinStatus.Foreground = new System.Windows.Media.SolidColorBrush(
                        (System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#059669"));
                }
            }
            catch
            {
                // No internet — show offline result
                if (txtGSTIN.Text.Trim().ToUpper() == gstin.ToUpper())
                {
                    lblGstinStatus.Text = $"✅ Valid — {result.StateName} | {result.EntityType} (offline)";
                    lblGstinStatus.Foreground = new System.Windows.Media.SolidColorBrush(
                        (System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#059669"));
                }
            }
        }

        private async void BtnValidateGstin_Click(object sender, RoutedEventArgs e)
        {
            string gstin = txtGSTIN.Text.Trim();
            if (string.IsNullOrWhiteSpace(gstin))
            {
                MessageBox.Show("Please enter GSTIN first.", "Validate", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }

            // Show loading
            lblGstinStatus.Text = "🔄 Verifying online...";
            lblGstinStatus.Foreground = new System.Windows.Media.SolidColorBrush(
                (System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#4F46E5"));
            btnValidateGstin.IsEnabled = false;

            try
            {
                var result = await Services.GstValidationService.VerifyGstinOnlineAsync(gstin);

                if (result.IsValid && !string.IsNullOrEmpty(result.LegalName))
                {
                    string info = $"✅ GSTIN Verified!\n\n" +
                                  $"📋 Legal Name: {result.LegalName}\n" +
                                  (string.IsNullOrEmpty(result.TradeName) ? "" : $"🏪 Trade Name: {result.TradeName}\n") +
                                  $"📍 State: {result.StateName} (Code: {result.StateCode:D2})\n" +
                                  $"📌 Status: {result.Status}\n" +
                                  (string.IsNullOrEmpty(result.Address) ? "" : $"🏠 Address: {result.Address}\n");

                    MessageBox.Show(info, "GSTIN Verified ✅", MessageBoxButton.OK, MessageBoxImage.Information);

                    lblGstinStatus.Text = $"✅ {result.LegalName}" +
                        (!string.IsNullOrEmpty(result.TradeName) && result.TradeName != result.LegalName
                            ? $" ({result.TradeName})" : "") +
                        $" — {result.StateName}";
                    lblGstinStatus.Foreground = new System.Windows.Media.SolidColorBrush(
                        (System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#059669"));

                    // Auto-fill state
                    if (result.StateCode > 0) SelectByText(cmbState, result.StateName);

                    // Auto-fill name if empty
                    if (string.IsNullOrWhiteSpace(txtName.Text) && !string.IsNullOrEmpty(result.DisplayName))
                        txtName.Text = result.DisplayName;
                }
                else if (result.IsValid)
                {
                    // Offline valid but online couldn't fetch name
                    var offlineResult = Services.GstValidationService.ValidateGstin(gstin);
                    MessageBox.Show($"✅ GSTIN format valid (offline)\n\nState: {offlineResult.StateName}\nPAN: {offlineResult.Pan}\nEntity: {offlineResult.EntityType}\n\n⚠️ Online lookup unavailable — business name not fetched.",
                        "GSTIN Valid (Offline)", MessageBoxButton.OK, MessageBoxImage.Information);
                    lblGstinStatus.Text = $"✅ Valid — {offlineResult.StateName} | {offlineResult.EntityType} (name unavailable)";
                    lblGstinStatus.Foreground = new System.Windows.Media.SolidColorBrush(
                        (System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#D97706"));
                }
                else
                {
                    MessageBox.Show($"❌ Invalid GSTIN\n\n{result.Message}", "Validation Failed",
                        MessageBoxButton.OK, MessageBoxImage.Error);
                    lblGstinStatus.Text = $"❌ {result.Message}";
                    lblGstinStatus.Foreground = new System.Windows.Media.SolidColorBrush(
                        (System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#E11D48"));
                }
            }
            catch (Exception ex)
            {
                lblGstinStatus.Text = $"⚠️ Error: {ex.Message}";
                lblGstinStatus.Foreground = new System.Windows.Media.SolidColorBrush(
                    (System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#E11D48"));
            }
            finally
            {
                btnValidateGstin.IsEnabled = true;
            }
        }
    }
}
