using System;
using System.Text.Json.Nodes;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views
{
    public partial class SettingsPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();
        private readonly ApiService _api = new();
        private string _activeTab = "business";

        private static readonly (string Key, string Icon, string Label)[] NavItems =
        {
            ("business",    "🏢", "Business Setting"),
            ("pos",         "🖥️", "POS Setting"),
            ("tax",         "🧮", "Tax Setting"),
            ("invoice",     "🧾", "Invoice Setting"),
            ("zatca",       "🔒", "Zatca Setting"),
            ("email",       "📧", "Email Setting"),
            ("sms",         "💬", "SMS Setting"),
            ("whatsapp",    "💚", "WhatsApp Setting"),
            ("whitelabel",  "🎨", "Whitelabel Setting"),
            ("server",      "🌐", "Server Configure"),
        };

        public SettingsPage() { InitializeComponent(); }
        public SettingsPage(MainDashboard dashboard) : this()
        {
            _dashboard = dashboard;
            BuildNav();
            ShowTab("business");
        }

        // ── Read / Write helpers ──────────────────────────────────────────
        private string G(string col)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = $"SELECT [{col}] FROM companies WHERE id=1";
                var v = cmd.ExecuteScalar();
                return v?.ToString() ?? "";
            }
            catch { return ""; }
        }

        private void S(string col, string value)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = $"UPDATE companies SET [{col}]=@v WHERE id=1";
                cmd.Parameters.AddWithValue("@v", value);
                cmd.ExecuteNonQuery();
                EnqueueCompanySync();
            }
            catch { }
        }

        // ── JSON-column helpers (invoice_configuration, smtp_details,
        //    sms_details, white_label, zatca_configuration, payment_api_setting
        //    — in sab ka data cloud me JSON string ke roop me rehta hai) ──
        private string GJson(string column, string key)
        {
            return GJson(column, new[] { key });
        }

        private string GJson(string column, string[] path)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = $"SELECT [{column}] FROM companies WHERE id=1";
                var raw = cmd.ExecuteScalar()?.ToString();
                if (string.IsNullOrWhiteSpace(raw)) return "";
                JsonNode? node = JsonNode.Parse(raw);
                foreach (var p in path)
                {
                    if (node is not JsonObject obj || obj[p] == null) return "";
                    node = obj[p];
                }
                return node?.ToString() ?? "";
            }
            catch { return ""; }
        }

        private void SJson(string column, string key, string value)
        {
            SJson(column, new[] { key }, value);
        }

        private void SJson(string column, string[] path, string value)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = $"SELECT [{column}] FROM companies WHERE id=1";
                var raw = cmd.ExecuteScalar()?.ToString();

                JsonObject obj;
                if (!string.IsNullOrWhiteSpace(raw))
                {
                    try { obj = JsonNode.Parse(raw) as JsonObject ?? new JsonObject(); }
                    catch { obj = new JsonObject(); }
                }
                else obj = new JsonObject();

                var cur = obj;
                for (int i = 0; i < path.Length - 1; i++)
                {
                    if (cur[path[i]] is not JsonObject next)
                    {
                        next = new JsonObject();
                        cur[path[i]] = next;
                    }
                    cur = next;
                }
                cur[path[^1]] = value ?? "";

                using var upd = conn.CreateCommand();
                upd.CommandText = $"UPDATE companies SET [{column}]=@v WHERE id=1";
                upd.Parameters.AddWithValue("@v", obj.ToJsonString());
                upd.ExecuteNonQuery();
                EnqueueCompanySync();
            }
            catch { }
        }

        private void EnqueueCompanySync()
        {
            // Business settings dono taraf sync hone chahiye: enqueue (dedupe
            // ke saath — baar-baar S()/SJson() call se sirf ek queue entry banti hai)
            // aur sync trigger karo. Payload processing time par local row se
            // build hota hai.
            SyncService.EnqueueSync("companies", 1, "update");
            if (_dashboard != null) _ = _dashboard.TriggerSync();
        }

        // ── Build left nav ────────────────────────────────────────────────
        private void BuildNav()
        {
            navPanel.Children.Clear();
            foreach (var (key, icon, label) in NavItems)
            {
                var btn = new Button
                {
                    Tag = key,
                    Content = new StackPanel
                    {
                        Orientation = Orientation.Horizontal,
                        Children =
                        {
                            new TextBlock { Text = icon, FontSize = 15, VerticalAlignment = VerticalAlignment.Center, Margin = new Thickness(0,0,10,0) },
                            new TextBlock { Text = label, FontSize = 13.5, VerticalAlignment = VerticalAlignment.Center }
                        }
                    },
                    Style = (Style)FindResource("NavBtn")
                };
                btn.Click += Nav_Click;
                navPanel.Children.Add(btn);
            }
        }

        private void Nav_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is string key) ShowTab(key);
        }

        private void ShowTab(string key)
        {
            _activeTab = key;
            foreach (var child in navPanel.Children)
            {
                if (child is Button b)
                    b.Style = (b.Tag?.ToString() == key) ? (Style)FindResource("NavBtnActive") : (Style)FindResource("NavBtn");
            }
            contentPanel.Children.Clear();
            switch (key)
            {
                case "business":  BuildBusiness(); break;
                case "pos":       BuildPOS(); break;
                case "tax":       BuildTax(); break;
                case "invoice":   BuildInvoice(); break;
                case "zatca":     BuildZatca(); break;
                case "email":     BuildEmail(); break;
                case "sms":       BuildSMS(); break;
                case "whatsapp":  BuildWhatsApp(); break;
                case "whitelabel": BuildWhitelabel(); break;
                case "server":    BuildServer(); break;
            }
        }

        // ════════════════════════════════════════════════════════════════════
        //  UI BUILDERS
        // ════════════════════════════════════════════════════════════════════

        private void SectionTitle(string title)
        {
            contentPanel.Children.Add(new TextBlock
            {
                Text = title, FontSize = 17, FontWeight = FontWeights.SemiBold,
                Foreground = new SolidColorBrush(Color.FromRgb(0x1E,0x1B,0x4B)),
                Margin = new Thickness(0,0,0,18), FontFamily = new FontFamily("Inter, Segoe UI")
            });
        }

        private Grid MakeGrid(int rows)
        {
            var g = new Grid();
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            for (int i = 0; i < rows; i++) g.RowDefinitions.Add(new RowDefinition());
            return g;
        }

        private TextBox MakeInput(string label, bool req, string val, int row, int col, Grid grid)
        {
            var sp = new StackPanel { Margin = new Thickness(col == 0 ? 0 : 16, 0, col == 0 ? 16 : 0, 14) };
            var lbl = new StackPanel { Orientation = Orientation.Horizontal };
            lbl.Children.Add(new TextBlock { Text = label, FontSize = 13, FontWeight = FontWeights.SemiBold, Foreground = new SolidColorBrush(Color.FromRgb(0x37,0x41,0x51)), FontFamily = new FontFamily("Inter, Segoe UI"), Margin = new Thickness(0,0,0,6) });
            if (req) lbl.Children.Add(new TextBlock { Text = " *", Foreground = Brushes.Red, FontSize = 13, FontWeight = FontWeights.SemiBold });
            sp.Children.Add(lbl);

            var tb = new TextBox
            {
                Text = val ?? "", Style = (Style)FindResource("TxtField"),
                Tag = label
            };
            sp.Children.Add(tb);

            Grid.SetRow(sp, row); Grid.SetColumn(sp, col); grid.Children.Add(sp);
            return tb;
        }

        private TextBox MakeTextArea(string label, bool req, string val, int row, int col, Grid grid)
        {
            var sp = new StackPanel { Margin = new Thickness(col == 0 ? 0 : 16, 0, col == 0 ? 16 : 0, 14) };
            var lbl = new StackPanel { Orientation = Orientation.Horizontal };
            lbl.Children.Add(new TextBlock { Text = label, FontSize = 13, FontWeight = FontWeights.SemiBold, Foreground = new SolidColorBrush(Color.FromRgb(0x37,0x41,0x51)), FontFamily = new FontFamily("Inter, Segoe UI"), Margin = new Thickness(0,0,0,6) });
            if (req) lbl.Children.Add(new TextBlock { Text = " *", Foreground = Brushes.Red, FontSize = 13, FontWeight = FontWeights.SemiBold });
            sp.Children.Add(lbl);

            var tb = new TextBox
            {
                Text = val ?? "", AcceptsReturn = true, TextWrapping = TextWrapping.Wrap, MinHeight = 72,
                VerticalContentAlignment = VerticalAlignment.Top,
                Background = Brushes.White, BorderBrush = new SolidColorBrush(Color.FromRgb(0xD1,0xD5,0xDB)),
                BorderThickness = new Thickness(1), Padding = new Thickness(10,9,10,9),
                FontSize = 13.5, Foreground = new SolidColorBrush(Color.FromRgb(0x1E,0x1B,0x4B)),
                FontFamily = new FontFamily("Inter, Segoe UI"), Tag = label
            };
            sp.Children.Add(tb);

            Grid.SetRow(sp, row); Grid.SetColumn(sp, col); grid.Children.Add(sp);
            return tb;
        }

        private ComboBox MakeCombo(string label, bool req, string[] opts, string val, int row, int col, Grid grid)
        {
            var sp = new StackPanel { Margin = new Thickness(col == 0 ? 0 : 16, 0, col == 0 ? 16 : 0, 14) };
            var lbl = new StackPanel { Orientation = Orientation.Horizontal };
            lbl.Children.Add(new TextBlock { Text = label, FontSize = 13, FontWeight = FontWeights.SemiBold, Foreground = new SolidColorBrush(Color.FromRgb(0x37,0x41,0x51)), FontFamily = new FontFamily("Inter, Segoe UI"), Margin = new Thickness(0,0,0,6) });
            if (req) lbl.Children.Add(new TextBlock { Text = " *", Foreground = Brushes.Red, FontSize = 13, FontWeight = FontWeights.SemiBold });
            sp.Children.Add(lbl);

            var cmb = new ComboBox { Style = (Style)FindResource("ComboField"), Tag = label };
            foreach (var o in opts) cmb.Items.Add(o);
            if (!string.IsNullOrEmpty(val))
            {
                // Cloud value options me nahi hai (e.g. installment_days=30, default_customer=1)
                // to use append karke select karo — kabhi bhi blindly index 0 mat chuno,
                // warna Save par cloud ki original value galat value se overwrite ho jayegi.
                if (cmb.Items.Contains(val)) cmb.SelectedItem = val;
                else { cmb.Items.Add(val); cmb.SelectedItem = val; }
            }
            else if (cmb.Items.Count > 0) cmb.SelectedIndex = 0;
            sp.Children.Add(cmb);

            Grid.SetRow(sp, row); Grid.SetColumn(sp, col); grid.Children.Add(sp);
            return cmb;
        }

        private ComboBox MakeYesNo(string label, bool req, string val, int row, int col, Grid grid)
        {
            return MakeCombo(label, req, new[] { "Yes", "No" }, val == "1" || val?.ToLower() == "yes" || val?.ToLower() == "enable" ? "Yes" : "No", row, col, grid);
        }

        private void AddCard(StackPanel parent, string title, UIElement content)
        {
            var card = new Border
            {
                Background = Brushes.White, BorderBrush = new SolidColorBrush(Color.FromRgb(0xE5,0xE7,0xEB)),
                BorderThickness = new Thickness(1), CornerRadius = new CornerRadius(12),
                Padding = new Thickness(28, 24, 28, 24), Margin = new Thickness(0,0,0,20)
            };
            var sp = new StackPanel();
            sp.Children.Add(new TextBlock
            {
                Text = title, FontSize = 17, FontWeight = FontWeights.SemiBold,
                Foreground = new SolidColorBrush(Color.FromRgb(0x1E,0x1B,0x4B)),
                Margin = new Thickness(0,0,0,20), FontFamily = new FontFamily("Inter, Segoe UI")
            });
            sp.Children.Add(content);
            card.Child = sp;
            parent.Children.Add(card);
        }

        private Button MakeSaveBtn(Action onClick)
        {
            var btn = new Button
            {
                Content = new StackPanel
                {
                    Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center,
                    Children =
                    {
                        new TextBlock { Text = "✅ ", FontSize = 14, VerticalAlignment = VerticalAlignment.Center },
                        new TextBlock { Text = "Save Changes", FontSize = 14, FontWeight = FontWeights.SemiBold, VerticalAlignment = VerticalAlignment.Center }
                    }
                },
                Style = (Style)FindResource("SaveBtn"),
                HorizontalAlignment = HorizontalAlignment.Right,
                Margin = new Thickness(0,8,0,0), Cursor = Cursors.Hand
            };
            btn.Click += (s, e) => onClick();
            return btn;
        }

        private void OK(string msg = "Settings saved successfully!") => MessageBox.Show(msg, "Success", MessageBoxButton.OK, MessageBoxImage.Information);

        // ════════════════════════════════════════════════════════════════════
        //  BUSINESS SETTING
        // ════════════════════════════════════════════════════════════════════
        private void BuildBusiness()
        {
            var g = MakeGrid(7);
            var tbName   = MakeInput("Business Name", true, G("business_name"), 0, 0, g);
            var tbAddr   = MakeTextArea("Address", true, G("address"), 0, 1, g);
            var tbWeb    = MakeInput("Website", false, G("website"), 1, 0, g);
            var tbEmail  = MakeInput("Email", true, G("email"), 1, 1, g);
            var tbPhone  = MakeInput("Phone", true, G("phone"), 2, 0, g);
            var cDF      = MakeCombo("Date Format", true, new[]{"d/m/Y","m/d/Y","Y/m/d"}, G("date_format"), 2, 1, g);
            var cZone    = MakeCombo("Zone Name", true, new[]{"Asia/Kolkata","Asia/Dhaka","Asia/Dubai","Europe/London","America/New_York"}, G("zone_name"), 3, 0, g);
            var tbCurr   = MakeInput("Currency", true, G("currency"), 3, 1, g);
            var cCP      = MakeCombo("Currency Position", true, new[]{"Before Amount","After Amount"}, G("currency_position"), 4, 0, g);
            var cPr      = MakeCombo("Precision", false, new[]{"1","2","3"}, G("precision"), 4, 1, g);
            var cTS      = MakeCombo("Thousand Separator", false, new[]{",",".","space"}, G("thousands_separator"), 5, 0, g);
            var cDS      = MakeCombo("Decimal Separator", true, new[]{".",",","space"}, G("decimals_separator"), 5, 1, g);
            var cInst    = MakeCombo("Installment Days", true, new[]{"3","7","15","30"}, G("installment_days"), 6, 0, g);
            var cEcom    = MakeCombo("E-Commerce Checker", true, new[]{"Yes","No"}, G("e_commerce_checker"), 6, 1, g);

            AddCard(contentPanel, "Business Setting", g);

            // Item Setting
            var ig = MakeGrid(2);
            var cLoyalty   = MakeCombo("Is Loyalty Enable", true, new[]{"Yes","No"}, G("is_loyalty_enable"), 0, 0, ig);
            var tbMinPt    = MakeInput("Minimum Point To Redeem", true, G("minimum_point_to_redeem"), 0, 1, ig);
            var tbLoyRate  = MakeInput("Loyalty Rate", true, G("loyalty_rate"), 1, 0, ig);
            var tbCodeFrom = MakeInput("Product Code Start From", true, G("product_code_start_from"), 1, 1, ig);

            AddCard(contentPanel, "Item Setting", ig);

            contentPanel.Children.Add(MakeSaveBtn(() =>
            {
                S("business_name", tbName.Text); S("address", tbAddr.Text);
                S("website", tbWeb.Text); S("email", tbEmail.Text);
                S("phone", tbPhone.Text); S("date_format", cDF.SelectedItem?.ToString());
                S("zone_name", cZone.SelectedItem?.ToString()); S("currency", tbCurr.Text);
                S("currency_position", cCP.SelectedItem?.ToString()); S("precision", cPr.SelectedItem?.ToString());
                S("thousands_separator", cTS.SelectedItem?.ToString()); S("decimals_separator", cDS.SelectedItem?.ToString());
                S("installment_days", cInst.SelectedItem?.ToString()); S("e_commerce_checker", cEcom.SelectedItem?.ToString());
                S("is_loyalty_enable", cLoyalty.SelectedItem?.ToString()); S("minimum_point_to_redeem", tbMinPt.Text);
                S("loyalty_rate", tbLoyRate.Text); S("product_code_start_from", tbCodeFrom.Text);
                OK();
            }));
        }

        // ════════════════════════════════════════════════════════════════════
        //  POS SETTING
        // ════════════════════════════════════════════════════════════════════
        private void BuildPOS()
        {
            var g = MakeGrid(6);

            // Total payable type: web "0/1/0.05/0.01/0.5" codes store karta hai
            // (labels nahi) — code<->label dono taraf map karte hain.
            var totalTypes = new (string Code, string Label)[]
            {
                ("0", "None"),
                ("1", "Round to nearest whole number"),
                ("0.05", "Round to nearest 0.05"),
                ("0.01", "Round to nearest 0.1"),
                ("0.5", "Round to nearest 0.5"),
            };
            string curTypeCode = G("pos_total_payable_type");
            string curTypeLabel = totalTypes.FirstOrDefault(t => t.Code == curTypeCode).Label;
            if (string.IsNullOrEmpty(curTypeLabel)) curTypeLabel = "None";

            // Default Customer / Payment: web id store karta hai — local tables se
            // options banao (sirf Name dikhao — "(Id N)" nahi), save par id bhejo.
            var custList = LoadIdNameList("customers");
            var custOpts = custList.Select(c => c.Name).ToArray();
            string curCustOpt = ResolveIdOption(custList, G("default_customer"));

            var payList = LoadIdNameList("payment_methods");
            var payOpts = payList.Select(p => p.Name).ToArray();
            string curPayOpt = ResolveIdOption(payList, G("default_payment"));

            var cLessSale   = MakeCombo("Allow Less Sale", true, new[]{"Yes","No"}, G("allow_less_sale"), 0, 0, g);
            var cDefCust    = MakeCombo("Default Customer", false, custOpts, curCustOpt, 0, 1, g);
            var cDefPay     = MakeCombo("Default Payment", false, payOpts, curPayOpt, 1, 0, g);
            var cTotalType  = MakeCombo("POS Total Payable Type", false, totalTypes.Select(t => t.Label).ToArray(), curTypeLabel, 1, 1, g);
            var cCursor     = MakeCombo("Default Cursor Position", true, new[]{"Search Box","Barcode Box"}, G("default_cursor_position"), 2, 0, g);
            var cDisplay    = MakeCombo("Product Display", true, new[]{"Image View","Box View"}, G("product_display"), 2, 1, g);
            var cKeyboard   = MakeCombo("Onscreen Keyboard Status", true, new[]{"Enable","Disable"}, G("onscreen_keyboard_status"), 3, 0, g);
            var cGrocery    = MakeCombo("Grocery Experience", true, new[]{"Regular","Medicine","Grocery"}, G("grocery_experience"), 3, 1, g);
            var cSMTP       = MakeCombo("SMTP Default Selected in POS", true, new[]{"Yes","No"}, G("smtp_default_selected_in_pos"), 4, 0, g);
            var cSMS        = MakeCombo("SMS Default Selected in POS", true, new[]{"Yes","No"}, G("sms_default_selected_in_pos"), 4, 1, g);
            var cWA         = MakeCombo("Whatsapp Default Selected in POS", true, new[]{"Yes","No"}, G("whatsapp_default_selected_in_pos"), 5, 0, g);
            var cDirect     = MakeCombo("Direct Cart", true, new[]{"Yes","No"}, G("direct_cart"), 5, 1, g);

            AddCard(contentPanel, "POS Setting", g);
            contentPanel.Children.Add(MakeSaveBtn(() =>
            {
                S("allow_less_sale", cLessSale.SelectedItem?.ToString());
                S("default_customer", IdFromSelected(cDefCust, custList, G("default_customer")));
                S("default_payment", IdFromSelected(cDefPay, payList, G("default_payment")));
                string selType = cTotalType.SelectedItem?.ToString() ?? "";
                S("pos_total_payable_type", totalTypes.FirstOrDefault(t => t.Label == selType).Code);
                S("default_cursor_position", cCursor.SelectedItem?.ToString());
                S("product_display", cDisplay.SelectedItem?.ToString());
                S("onscreen_keyboard_status", cKeyboard.SelectedItem?.ToString());
                S("grocery_experience", cGrocery.SelectedItem?.ToString());
                S("smtp_default_selected_in_pos", cSMTP.SelectedItem?.ToString());
                S("sms_default_selected_in_pos", cSMS.SelectedItem?.ToString());
                S("whatsapp_default_selected_in_pos", cWA.SelectedItem?.ToString());
                S("direct_cart", cDirect.SelectedItem?.ToString());
                OK();
            }));
        }

        private List<(long Id, string Name)> LoadIdNameList(string table)
        {
            var list = new List<(long, string)>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = $"SELECT Id, Name FROM \"{table}\" WHERE del_status='Live' ORDER BY Name";
                using var r = cmd.ExecuteReader();
                while (r.Read()) list.Add((r.GetInt64(0), r.GetString(1)));
            }
            catch { }
            return list;
        }

        private static string ResolveIdOption(List<(long Id, string Name)> list, string rawValue)
        {
            if (string.IsNullOrEmpty(rawValue)) return "";
            if (long.TryParse(rawValue, out long id))
            {
                foreach (var x in list)
                    if (x.Id == id) return x.Name;
            }
            return rawValue;
        }

        private static string IdFromSelected(System.Windows.Controls.ComboBox cmb, List<(long Id, string Name)> list, string fallback)
        {
            var sel = cmb.SelectedItem?.ToString();
            if (string.IsNullOrEmpty(sel)) return fallback;
            foreach (var x in list)
                if (sel == x.Name) return x.Id.ToString();
            return sel;
        }

        // ════════════════════════════════════════════════════════════════════
        //  TAX SETTING
        // ════════════════════════════════════════════════════════════════════
        private void BuildTax()
        {
            var g = MakeGrid(2);
            var cCollect    = MakeCombo("Collect Tax", true, new[]{"Yes","No"}, G("collect_tax"), 0, 0, g);
            var tbTitle     = MakeInput("Tax Title", false, G("tax_title"), 0, 1, g);
            var tbRegNo     = MakeInput("Tax Registration No", false, G("tax_registration_no"), 1, 0, g);
            var cGST        = MakeCombo("Enable GST", true, new[]{"Yes","No"}, G("tax_is_gst"), 1, 1, g);

            AddCard(contentPanel, "Tax Setting", g);
            contentPanel.Children.Add(MakeSaveBtn(() =>
            {
                S("collect_tax", cCollect.SelectedItem?.ToString());
                S("tax_title", tbTitle.Text);
                S("tax_registration_no", tbRegNo.Text);
                S("tax_is_gst", cGST.SelectedItem?.ToString());
                OK();
            }));
        }

        // ════════════════════════════════════════════════════════════════════
        //  INVOICE SETTING
        // ════════════════════════════════════════════════════════════════════
        private void BuildInvoice()
        {
            // Invoice Size & Numbering
            var g = MakeGrid(4);
            var cSize       = MakeCombo("Invoice Size", true, new[]{"56mm","80mm","A4 Print","Half A4 Print","Letter Head"}, GJson("invoice_configuration","invoice_format_or_size"), 0, 0, g);
            var cSchema     = MakeCombo("Schema Type", true, new[]{"XXXX","{currentYear}-XXXX"}, GJson("invoice_configuration","schema_type"), 0, 1, g);
            var cNumType    = MakeCombo("Numbering Types", true, new[]{"Sequential","Random"}, GJson("invoice_configuration","inv_numbering_type"), 1, 0, g);
            var cNumDigits  = MakeCombo("Numbering of Digits", true, new[]{"4","5","6","7","8","9","10"}, GJson("invoice_configuration","inv_number_of_digit"), 1, 1, g);
            var tbPrefix    = MakeInput("Prefix", false, GJson("invoice_configuration","inv_prefix"), 2, 0, g);
            var tbStart     = MakeInput("Start From", true, GJson("invoice_configuration","inv_start_from"), 2, 1, g);
            var cLogoShow   = MakeCombo("Invoice Logo Show", false, new[]{"Yes","No"}, G("inv_logo_is_show"), 3, 0, g);
            var cLetterHead = MakeCombo("Show Letterhead", false, new[]{"Yes","No"}, GJson("invoice_configuration","show_letter_head"), 3, 1, g);

            AddCard(contentPanel, "Invoice Size & Numbering", g);

            // Labels
            var lg = MakeGrid(6);
            var tbHeading   = MakeInput("Invoice Heading", true, GJson("invoice_configuration","invoice_heading"), 0, 0, lg);
            var tbNoLabel   = MakeInput("Invoice No Label", true, GJson("invoice_configuration","invoice_no_label"), 0, 1, lg);
            var tbDateLabel = MakeInput("Invoice Date Label", true, GJson("invoice_configuration","invoice_date_label"), 1, 0, lg);
            var tbCustLabel = MakeInput("Customer Label", true, GJson("invoice_configuration","customer_label"), 1, 1, lg);
            var tbItemLabel = MakeInput("Item Label", true, GJson("invoice_configuration","item_label"), 2, 0, lg);
            var tbPriceLbl  = MakeInput("Price Label", true, GJson("invoice_configuration","price_label"), 2, 1, lg);
            var tbQtyLabel  = MakeInput("Quantity Label", true, GJson("invoice_configuration","quantity_label"), 3, 0, lg);
            var tbSubLabel  = MakeInput("Subtotal Label", true, GJson("invoice_configuration","subtotal_label"), 3, 1, lg);
            var tbTotalLbl  = MakeInput("Total Label", true, GJson("invoice_configuration","total_label"), 4, 0, lg);
            var tbTaxLabel  = MakeInput("Tax Label", true, GJson("invoice_configuration","tax_label"), 4, 1, lg);
            var tbPaidLabel = MakeInput("Paid Amount Label", true, GJson("invoice_configuration","paid_amount_label"), 5, 0, lg);
            var tbDueLabel  = MakeInput("Due Amount Label", true, GJson("invoice_configuration","due_amount_label"), 5, 1, lg);

            AddCard(contentPanel, "Invoice Labels", lg);

            // Product Details
            var pg = MakeGrid(3);
            var cBrand      = MakeYesNo("Show Brand", true, GJson("invoice_configuration","show_brand"), 0, 0, pg);
            var cCode       = MakeYesNo("Show Product Code", true, GJson("invoice_configuration","show_product_code"), 0, 1, pg);
            var cIMEI       = MakeYesNo("Show IMEI/Serial", true, GJson("invoice_configuration","show_product_imei_serial_number"), 1, 0, pg);
            var cImage      = MakeYesNo("Show Product Image", true, GJson("invoice_configuration","show_product_image"), 1, 1, pg);
            var cWarranty   = MakeYesNo("Show Warranty Period", true, GJson("invoice_configuration","show_warranty_period"), 2, 0, pg);
            var cWords      = MakeYesNo("Show Total in Words", true, GJson("invoice_configuration","show_total_in_words"), 2, 1, pg);

            AddCard(contentPanel, "Product Details on Invoice", pg);

            // Footer
            var fg = MakeGrid(1);
            var tbFooter = MakeTextArea("Invoice Footer", false, G("invoice_footer"), 0, 0, fg);
            var tbTerms  = MakeTextArea("Terms & Conditions", false, G("term_conditions"), 0, 1, fg);

            AddCard(contentPanel, "Invoice Footer & Terms", fg);

            contentPanel.Children.Add(MakeSaveBtn(() =>
            {
                SJson("invoice_configuration","invoice_format_or_size", cSize.SelectedItem?.ToString());
                SJson("invoice_configuration","schema_type", cSchema.SelectedItem?.ToString());
                SJson("invoice_configuration","inv_numbering_type", cNumType.SelectedItem?.ToString());
                SJson("invoice_configuration","inv_number_of_digit", cNumDigits.SelectedItem?.ToString());
                SJson("invoice_configuration","inv_prefix", tbPrefix.Text); SJson("invoice_configuration","inv_start_from", tbStart.Text);
                S("inv_logo_is_show", cLogoShow.SelectedItem?.ToString());
                SJson("invoice_configuration","show_letter_head", cLetterHead.SelectedItem?.ToString());
                SJson("invoice_configuration","invoice_heading", tbHeading.Text); SJson("invoice_configuration","invoice_no_label", tbNoLabel.Text);
                SJson("invoice_configuration","invoice_date_label", tbDateLabel.Text); SJson("invoice_configuration","customer_label", tbCustLabel.Text);
                SJson("invoice_configuration","item_label", tbItemLabel.Text); SJson("invoice_configuration","price_label", tbPriceLbl.Text);
                SJson("invoice_configuration","quantity_label", tbQtyLabel.Text); SJson("invoice_configuration","subtotal_label", tbSubLabel.Text);
                SJson("invoice_configuration","total_label", tbTotalLbl.Text); SJson("invoice_configuration","tax_label", tbTaxLabel.Text);
                SJson("invoice_configuration","paid_amount_label", tbPaidLabel.Text); SJson("invoice_configuration","due_amount_label", tbDueLabel.Text);
                SJson("invoice_configuration","show_brand", cBrand.SelectedItem?.ToString());
                SJson("invoice_configuration","show_product_code", cCode.SelectedItem?.ToString());
                SJson("invoice_configuration","show_product_imei_serial_number", cIMEI.SelectedItem?.ToString());
                SJson("invoice_configuration","show_product_image", cImage.SelectedItem?.ToString());
                SJson("invoice_configuration","show_warranty_period", cWarranty.SelectedItem?.ToString());
                SJson("invoice_configuration","show_total_in_words", cWords.SelectedItem?.ToString());
                S("invoice_footer", tbFooter.Text); S("term_conditions", tbTerms.Text);
                OK();
            }));
        }

        // ════════════════════════════════════════════════════════════════════
        //  ZATCA SETTING
        // ════════════════════════════════════════════════════════════════════
        private void BuildZatca()
        {
            var root = new StackPanel();

            // ── Phase selector ──
            root.Children.Add(new TextBlock { Text = "Select ZATCA Phase", FontSize = 13, FontWeight = FontWeights.SemiBold, Foreground = new SolidColorBrush(Color.FromRgb(0x37, 0x41, 0x51)), FontFamily = new FontFamily("Inter, Segoe UI"), Margin = new Thickness(0, 0, 0, 6) });
            var cPhase = new ComboBox { Style = (Style)FindResource("ComboField"), Height = 40, FontSize = 13.5, FontFamily = new FontFamily("Inter, Segoe UI"), Margin = new Thickness(0, 0, 0, 16) };
            cPhase.Items.Add("None (Disable ZATCA)");
            cPhase.Items.Add("ZATCA Phase 1");
            cPhase.Items.Add("ZATCA Phase 2");
            string phaseRaw = GJson("zatca_configuration", "zatca_phase");
            cPhase.SelectedItem = phaseRaw switch { "1" => "ZATCA Phase 1", "2" => "ZATCA Phase 2", _ => "None (Disable ZATCA)" };
            root.Children.Add(cPhase);

            // ── Phase 1 & Phase 2 common: business info ──
            var bizPanel = new StackPanel();
            var bg = MakeGrid(2);
            var tbBizEn = MakeInput("Legal Business Name (English)", true, GJson("zatca_configuration", "legal_business_name_english"), 0, 0, bg);
            var tbBizAr = MakeInput("Legal Business Name (Arabic)", true, GJson("zatca_configuration", "legal_business_name_arabic"), 0, 1, bg);
            var tbVAT   = MakeInput("VAT Registration Number", true, GJson("zatca_configuration", "vat_registration_number"), 1, 0, bg);
            var tbAddr  = MakeTextArea("Address", true, GJson("zatca_configuration", "zatca_address"), 1, 1, bg);
            bizPanel.Children.Add(bg);

            // ── Phase 2 only: status + environment + credentials ──
            var p2Panel = new StackPanel();

            // Phase 2 Status
            var sg = MakeGrid(1);
            var cStatus = MakeCombo("ZATCA Phase 2 Status", true, new[] { "Enable", "Disable" }, GJson("zatca_configuration", "zatca_status"), 0, 0, sg);
            p2Panel.Children.Add(sg);

            // Environment Information
            var envBox = new Border
            {
                Background = new SolidColorBrush(Color.FromRgb(0xFF, 0xFB, 0xEB)),
                BorderBrush = new SolidColorBrush(Color.FromRgb(0xFD, 0xE6, 0x8A)),
                BorderThickness = new Thickness(1), CornerRadius = new CornerRadius(8),
                Padding = new Thickness(14, 12, 14, 12), Margin = new Thickness(0, 0, 0, 16)
            };
            envBox.Child = new StackPanel
            {
                Children =
                {
                    new TextBlock { Text = "Environment Information", FontSize = 13.5, FontWeight = FontWeights.SemiBold, Foreground = new SolidColorBrush(Color.FromRgb(0x92, 0x40, 0x0E)), FontFamily = new FontFamily("Inter, Segoe UI"), Margin = new Thickness(0, 0, 0, 6) },
                    new TextBlock { Text = "Instructions\nFillup the below required fields and Click the \"Generate CSR\" button to create Certificate Signing Request (CSR) and Private Key files. After generation, download the CSR file and upload it to your ZATCA account.", FontSize = 12.5, Foreground = new SolidColorBrush(Color.FromRgb(0x92, 0x40, 0x0E)), FontFamily = new FontFamily("Inter, Segoe UI"), TextWrapping = TextWrapping.Wrap, LineHeight = 18 }
                }
            };
            p2Panel.Children.Add(envBox);

            // Credentials
            var credPanel = new StackPanel { Margin = new Thickness(0, 0, 0, 16) };
            credPanel.Children.Add(new TextBlock { Text = "ZATCA Credentials", FontSize = 14, FontWeight = FontWeights.SemiBold, Foreground = new SolidColorBrush(Color.FromRgb(0x1E, 0x1B, 0x4B)), FontFamily = new FontFamily("Inter, Segoe UI"), Margin = new Thickness(0, 0, 0, 4) });
            credPanel.Children.Add(new TextBlock { Text = "Important\nAfter uploading the CSR file to your ZATCA account, you will receive three credentials. Please enter them below:", FontSize = 12.5, Foreground = new SolidColorBrush(Color.FromRgb(0x8E, 0x8E, 0xA1)), FontFamily = new FontFamily("Inter, Segoe UI"), TextWrapping = TextWrapping.Wrap, LineHeight = 18, Margin = new Thickness(0, 0, 0, 12) });
            var cg = MakeGrid(2);
            var tbCompCSID = MakeInput("Compliance CSID", false, GJson("zatca_configuration", "compliance_csid"), 0, 0, cg);
            var tbProdCSID = MakeInput("Production CSID", false, GJson("zatca_configuration", "production_csid"), 0, 1, cg);
            var tbSecret   = MakeInput("ZATCA Secret Key", false, GJson("zatca_configuration", "zatca_secret_key"), 1, 0, cg);
            credPanel.Children.Add(cg);
            p2Panel.Children.Add(credPanel);

            root.Children.Add(bizPanel);
            root.Children.Add(p2Panel);

            // ── Dynamic visibility ──
            void UpdateZatcaVisibility()
            {
                string sel = cPhase.SelectedItem?.ToString() ?? "None (Disable ZATCA)";
                bizPanel.Visibility = sel == "None (Disable ZATCA)" ? Visibility.Collapsed : Visibility.Visible;
                p2Panel.Visibility  = sel == "ZATCA Phase 2" ? Visibility.Visible : Visibility.Collapsed;
            }
            cPhase.SelectionChanged += (s, e) => UpdateZatcaVisibility();
            UpdateZatcaVisibility();

            AddCard(contentPanel, "Zatca Setting", root);
            contentPanel.Children.Add(MakeSaveBtn(() =>
            {
                var phaseSel = cPhase.SelectedItem?.ToString();
                SJson("zatca_configuration", "zatca_phase", phaseSel switch { "ZATCA Phase 1" => "1", "ZATCA Phase 2" => "2", _ => "0" });
                SJson("zatca_configuration", "zatca_status", cStatus.SelectedItem?.ToString());
                SJson("zatca_configuration", "legal_business_name_english", tbBizEn.Text);
                SJson("zatca_configuration", "legal_business_name_arabic", tbBizAr.Text);
                SJson("zatca_configuration", "vat_registration_number", tbVAT.Text);
                SJson("zatca_configuration", "zatca_address", tbAddr.Text);
                SJson("zatca_configuration", "compliance_csid", tbCompCSID.Text);
                SJson("zatca_configuration", "production_csid", tbProdCSID.Text);
                SJson("zatca_configuration", "zatca_secret_key", tbSecret.Text);
                OK();
            }));
        }

        // ════════════════════════════════════════════════════════════════════
        //  EMAIL SETTING
        // ════════════════════════════════════════════════════════════════════
        private void BuildEmail()
        {
            var root = new StackPanel();

            // ── SMTP Enable Status (top, hamesha visible) ──
            var eg = MakeGrid(1);
            var cEnable = MakeCombo("SMTP Enable Status", true, new[]{"Enable","Disable"}, G("smtp_enable_status"), 0, 0, eg);
            root.Children.Add(eg);

            // ── Form (sirf Enable hone par dikhega) ──
            var formPanel = new StackPanel();
            var g = MakeGrid(5);
            var cType   = MakeCombo("SMTP Type", true, new[]{"None","Gmail","Sendinblue"}, G("smtp_type"), 0, 1, g);
            var tbHost  = MakeInput("Host Name", true, GJson("smtp_details","host_name"), 1, 0, g);
            var tbPort  = MakeInput("Port Address", true, GJson("smtp_details","port_address"), 1, 1, g);
            var tbEnc   = MakeInput("Encryption", true, GJson("smtp_details","encryption"), 2, 0, g);
            var tbUser  = MakeInput("User Name", true, GJson("smtp_details","user_name"), 2, 1, g);
            var tbPass  = MakeInput("Password", true, GJson("smtp_details","password"), 3, 0, g);
            var tbFromN = MakeInput("From Name", true, GJson("smtp_details","from_name"), 3, 1, g);
            var tbFromE = MakeInput("From Email", true, GJson("smtp_details","from_email"), 4, 0, g);
            var tbAPI   = MakeInput("API Key", false, GJson("smtp_details","api_key"), 4, 1, g);
            formPanel.Children.Add(g);

            // ── Guide & Rules (sirf Enable hone par) ──
            var guideBox = new Border
            {
                Background = new SolidColorBrush(Color.FromRgb(0xFF, 0xFB, 0xEB)),
                BorderBrush = new SolidColorBrush(Color.FromRgb(0xFD, 0xE6, 0x8A)),
                BorderThickness = new Thickness(1), CornerRadius = new CornerRadius(8),
                Padding = new Thickness(14, 12, 14, 12), Margin = new Thickness(0, 0, 0, 0)
            };
            guideBox.Child = new StackPanel
            {
                Children =
                {
                    new TextBlock { Text = "Guide & Rules Email Setting", FontSize = 13.5, FontWeight = FontWeights.SemiBold, Foreground = new SolidColorBrush(Color.FromRgb(0x92, 0x40, 0x0E)), FontFamily = new FontFamily("Inter, Segoe UI"), Margin = new Thickness(0, 0, 0, 6) },
                    new TextBlock { Text = "1. If you use port 587 then you should to fill up the encryption field with tls or TLS\n2. If you use port 465 then you should to fill up the encryption field with ssl or SSL", FontSize = 12.5, Foreground = new SolidColorBrush(Color.FromRgb(0x92, 0x40, 0x0E)), FontFamily = new FontFamily("Inter, Segoe UI"), TextWrapping = TextWrapping.Wrap, LineHeight = 18 }
                }
            };
            formPanel.Children.Add(guideBox);

            root.Children.Add(formPanel);

            // ── Dynamic visibility: Disable → form chhupao ──
            void UpdateEmailVisibility()
            {
                string sel = cEnable.SelectedItem?.ToString() ?? "Disable";
                formPanel.Visibility = sel == "Enable" ? Visibility.Visible : Visibility.Collapsed;
            }
            cEnable.SelectionChanged += (s, e) => UpdateEmailVisibility();
            UpdateEmailVisibility();

            AddCard(contentPanel, "Email Setting", root);
            contentPanel.Children.Add(MakeSaveBtn(() =>
            {
                S("smtp_enable_status", cEnable.SelectedItem?.ToString());
                S("smtp_type", cType.SelectedItem?.ToString());
                SJson("smtp_details","host_name", tbHost.Text); SJson("smtp_details","port_address", tbPort.Text);
                SJson("smtp_details","encryption", tbEnc.Text); SJson("smtp_details","user_name", tbUser.Text);
                SJson("smtp_details","password", tbPass.Text); SJson("smtp_details","from_name", tbFromN.Text);
                SJson("smtp_details","from_email", tbFromE.Text); SJson("smtp_details","api_key", tbAPI.Text);
                OK();
            }));
        }

        // ════════════════════════════════════════════════════════════════════
        //  SMS SETTING
        // ════════════════════════════════════════════════════════════════════
        private void BuildSMS()
        {
            var root = new StackPanel();

            // ── SMS Status (hamesha visible) ──
            var sg = MakeGrid(1);
            var cEnable = MakeCombo("SMS Status", true, new[]{"Enable","Disable"}, G("sms_enable_status"), 0, 0, sg);
            root.Children.Add(sg);

            // ── Form (sirf Enable hone par) ──
            var formPanel = new StackPanel();

            // Provider selector
            formPanel.Children.Add(new TextBlock { Text = "SMS Service Provider", FontSize = 13, FontWeight = FontWeights.SemiBold, Foreground = new SolidColorBrush(Color.FromRgb(0x37, 0x41, 0x51)), FontFamily = new FontFamily("Inter, Segoe UI"), Margin = new Thickness(0, 0, 0, 6) });
            var cProvider = new ComboBox { Style = (Style)FindResource("ComboField"), Height = 40, FontSize = 13.5, FontFamily = new FontFamily("Inter, Segoe UI"), Margin = new Thickness(0, 0, 16, 0) };
            cProvider.Items.Add("None");
            cProvider.Items.Add("Twilio");
            cProvider.Items.Add("Mobishastra");
            cProvider.Items.Add("MiMSMS");
            cProvider.Items.Add("Text Local");
            cProvider.SelectedItem = G("sms_service_provider");
            formPanel.Children.Add(cProvider);

            // ── Twilio panel: SID / Token / Twilio Number ──
            var twilioPanel = new StackPanel();
            var twg = MakeGrid(2);
            var tbSID     = MakeInput("SID", true, GJson("sms_details", new[]{"twilio","sid"}), 0, 0, twg);
            var tbToken   = MakeInput("Token", true, GJson("sms_details", new[]{"twilio","token"}), 0, 1, twg);
            var tbNumber  = MakeInput("Twilio Number", true, GJson("sms_details", new[]{"twilio","number"}), 1, 0, twg);
            twilioPanel.Children.Add(twg);
            formPanel.Children.Add(twilioPanel);

            // ── Mobishastra panel: Profile ID / Sender ID ──
            var mobiPanel = new StackPanel();
            var mg = MakeGrid(1);
            var tbProfile = MakeInput("Profile ID", true, GJson("sms_details", new[]{"mobishastra","profile_id"}), 0, 0, mg);
            var tbSender  = MakeInput("Sender ID", true, GJson("sms_details", new[]{"mobishastra","sender_id"}), 0, 1, mg);
            mobiPanel.Children.Add(mg);
            formPanel.Children.Add(mobiPanel);

            // ── MiMSMS panel: API Key / Sender ID / Username ──
            var mimPanel = new StackPanel();
            var mmg = MakeGrid(2);
            var tbAPIKey   = MakeInput("API Key", true, GJson("sms_details", new[]{"mim_sms","api_key"}), 0, 0, mmg);
            var tbSenderId = MakeInput("Sender ID", true, GJson("sms_details", new[]{"mim_sms","sender_id"}), 0, 1, mmg);
            var tbUsername = MakeInput("Username", true, GJson("sms_details", new[]{"mim_sms","username"}), 1, 0, mmg);
            mimPanel.Children.Add(mmg);
            formPanel.Children.Add(mimPanel);

            // ── Text Local panel: Profile ID / API Key / Sender ID ──
            var tlPanel = new StackPanel();
            var tlg = MakeGrid(2);
            var tbTLProfile = MakeInput("Profile ID", true, GJson("sms_details", new[]{"text_local","profile_id"}), 0, 0, tlg);
            var tbTLAPIKey  = MakeInput("API Key", true, GJson("sms_details", new[]{"text_local","api_key"}), 0, 1, tlg);
            var tbTLSender  = MakeInput("Sender ID", true, GJson("sms_details", new[]{"text_local","sender_id"}), 1, 0, tlg);
            tlPanel.Children.Add(tlg);
            formPanel.Children.Add(tlPanel);

            // Status line
            var lblStatus = new TextBlock
            {
                FontSize = 12.5, FontFamily = new FontFamily("Inter, Segoe UI"),
                Margin = new Thickness(2, 4, 0, 10), Visibility = Visibility.Collapsed,
                TextWrapping = TextWrapping.Wrap
            };
            formPanel.Children.Add(lblStatus);

            // ── Buttons: Test SMS + Save Changes ──
            var btnRow = new StackPanel { Orientation = Orientation.Horizontal, HorizontalAlignment = HorizontalAlignment.Right, VerticalAlignment = VerticalAlignment.Bottom };
            var btnTest = new Button
            {
                Content = "📱 Test SMS",
                FontSize = 14, FontWeight = FontWeights.SemiBold, FontFamily = new FontFamily("Inter, Segoe UI"),
                Foreground = new SolidColorBrush(Color.FromRgb(0x69, 0x6C, 0xFF)),
                Background = Brushes.White, BorderBrush = new SolidColorBrush(Color.FromRgb(0xC7, 0xD2, 0xFE)),
                BorderThickness = new Thickness(1), Padding = new Thickness(18, 10, 18, 10),
                Cursor = Cursors.Hand, Margin = new Thickness(0, 0, 10, 0),
                HorizontalAlignment = HorizontalAlignment.Right
            };
            btnRow.Children.Add(btnTest);
            btnRow.Children.Add(MakeSaveBtn(() =>
            {
                S("sms_enable_status", cEnable.SelectedItem?.ToString());
                S("sms_service_provider", cProvider.SelectedItem?.ToString());
                SJson("sms_details", new[]{"twilio","sid"}, tbSID.Text); SJson("sms_details", new[]{"twilio","token"}, tbToken.Text);
                SJson("sms_details", new[]{"twilio","number"}, tbNumber.Text);
                SJson("sms_details", new[]{"mobishastra","profile_id"}, tbProfile.Text); SJson("sms_details", new[]{"mobishastra","sender_id"}, tbSender.Text);
                SJson("sms_details", new[]{"mim_sms","api_key"}, tbAPIKey.Text); SJson("sms_details", new[]{"mim_sms","sender_id"}, tbSenderId.Text); SJson("sms_details", new[]{"mim_sms","username"}, tbUsername.Text);
                SJson("sms_details", new[]{"text_local","profile_id"}, tbTLProfile.Text); SJson("sms_details", new[]{"text_local","api_key"}, tbTLAPIKey.Text); SJson("sms_details", new[]{"text_local","sender_id"}, tbTLSender.Text);
                OK();
            }));
            formPanel.Children.Add(btnRow);

            root.Children.Add(formPanel);

            // ── Dynamic visibility ──
            void UpdateSmsVisibility()
            {
                string en = cEnable.SelectedItem?.ToString() ?? "Disable";
                formPanel.Visibility = en == "Enable" ? Visibility.Visible : Visibility.Collapsed;

                string prov = cProvider.SelectedItem?.ToString() ?? "None";
                twilioPanel.Visibility = prov == "Twilio" ? Visibility.Visible : Visibility.Collapsed;
                mobiPanel.Visibility   = prov == "Mobishastra" ? Visibility.Visible : Visibility.Collapsed;
                mimPanel.Visibility    = prov == "MiMSMS" ? Visibility.Visible : Visibility.Collapsed;
                tlPanel.Visibility     = prov == "Text Local" ? Visibility.Visible : Visibility.Collapsed;
            }
            cEnable.SelectionChanged += (s, e) => UpdateSmsVisibility();
            cProvider.SelectionChanged += (s, e) => UpdateSmsVisibility();
            UpdateSmsVisibility();

            // ── Test SMS: selected provider ke required fields validate karta hai ──
            btnTest.Click += (s, e) =>
            {
                string prov = cProvider.SelectedItem?.ToString() ?? "None";
                string missing = "";
                switch (prov)
                {
                    case "Twilio":
                        if (string.IsNullOrWhiteSpace(tbSID.Text)) missing += "SID, ";
                        if (string.IsNullOrWhiteSpace(tbToken.Text)) missing += "Token, ";
                        if (string.IsNullOrWhiteSpace(tbNumber.Text)) missing += "Twilio Number, ";
                        break;
                    case "Mobishastra":
                        if (string.IsNullOrWhiteSpace(tbProfile.Text)) missing += "Profile ID, ";
                        if (string.IsNullOrWhiteSpace(tbSender.Text)) missing += "Sender ID, ";
                        break;
                    case "MiMSMS":
                        if (string.IsNullOrWhiteSpace(tbAPIKey.Text)) missing += "API Key, ";
                        if (string.IsNullOrWhiteSpace(tbSenderId.Text)) missing += "Sender ID, ";
                        if (string.IsNullOrWhiteSpace(tbUsername.Text)) missing += "Username, ";
                        break;
                    case "Text Local":
                        if (string.IsNullOrWhiteSpace(tbTLProfile.Text)) missing += "Profile ID, ";
                        if (string.IsNullOrWhiteSpace(tbTLAPIKey.Text)) missing += "API Key, ";
                        if (string.IsNullOrWhiteSpace(tbTLSender.Text)) missing += "Sender ID, ";
                        break;
                    default:
                        missing = "PROVIDER";
                        break;
                }
                if (missing.Length > 0)
                {
                    lblStatus.Text = "❌ Missing fields: " + missing.TrimEnd(' ', ',');
                    lblStatus.Foreground = Brushes.Red; lblStatus.Visibility = Visibility.Visible;
                    return;
                }
                lblStatus.Text = $"✅ {prov} configuration complete — sab required fields bhare hain. Pehle Save Changes dabayen, phir test SMS bheja ja sakta hai.";
                lblStatus.Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0xA3, 0x4A));
                lblStatus.Visibility = Visibility.Visible;
            };

            AddCard(contentPanel, "SMS Setting", root);
        }

        // ════════════════════════════════════════════════════════════════════
        //  WHATSAPP SETTING
        // ════════════════════════════════════════════════════════════════════
        private void BuildWhatsApp()
        {
            var root = new StackPanel();

            // ── Enable WhatsApp Invoice (hamesha visible) ──
            var eg = MakeGrid(1);
            var cEnable = MakeCombo("Enable WhatsApp Invoice", true, new[]{"Enable","Disable"}, G("whatsapp_invoice_enable_status"), 0, 0, eg);
            root.Children.Add(eg);

            // ── Form (sirf Enable hone par) ──
            var formPanel = new StackPanel();

            // Provider selector
            formPanel.Children.Add(new TextBlock { Text = "WhatsApp Provider", FontSize = 13, FontWeight = FontWeights.SemiBold, Foreground = new SolidColorBrush(Color.FromRgb(0x37, 0x41, 0x51)), FontFamily = new FontFamily("Inter, Segoe UI"), Margin = new Thickness(0, 0, 0, 6) });
            var cProvider = new ComboBox { Style = (Style)FindResource("ComboField"), Height = 40, FontSize = 13.5, FontFamily = new FontFamily("Inter, Segoe UI"), Margin = new Thickness(0, 0, 16, 0) };
            cProvider.Items.Add("None");
            cProvider.Items.Add("RC Soft");
            cProvider.Items.Add("Twilio");
            cProvider.SelectedItem = G("whatsapp_provider");
            formPanel.Children.Add(cProvider);

            // ── RC Soft panel: App Key / Auth Key ──
            var rcPanel = new StackPanel();
            var rg = MakeGrid(1);
            var tbAppKey  = MakeInput("App Key", true, G("whatsapp_app_key"), 0, 0, rg);
            var tbAuthKey = MakeInput("Auth Key", true, G("whatsapp_authkey"), 0, 1, rg);
            rcPanel.Children.Add(rg);
            formPanel.Children.Add(rcPanel);

            // ── Twilio: koi form nahi (Twilio SMS provider ki credentials use hoti hain) ──
            var twilioNote = new TextBlock
            {
                Text = "ℹ️ Twilio WhatsApp ke liye alag credentials ki zaroorat nahi — SMS Setting mein jo Twilio SID / Token / Number bhare hain, wahi use honge.",
                FontSize = 12.5, Foreground = new SolidColorBrush(Color.FromRgb(0x8E, 0x8E, 0xA1)),
                FontFamily = new FontFamily("Inter, Segoe UI"), TextWrapping = TextWrapping.Wrap, Margin = new Thickness(2, 0, 0, 0)
            };
            formPanel.Children.Add(twilioNote);

            // Status line
            var lblStatus = new TextBlock
            {
                FontSize = 12.5, FontFamily = new FontFamily("Inter, Segoe UI"),
                Margin = new Thickness(2, 4, 0, 10), Visibility = Visibility.Collapsed,
                TextWrapping = TextWrapping.Wrap
            };
            formPanel.Children.Add(lblStatus);

            root.Children.Add(formPanel);

            // ── Dynamic visibility ──
            void UpdateWhatsAppVisibility()
            {
                string en = cEnable.SelectedItem?.ToString() ?? "Disable";
                formPanel.Visibility = en == "Enable" ? Visibility.Visible : Visibility.Collapsed;

                string prov = cProvider.SelectedItem?.ToString() ?? "None";
                rcPanel.Visibility      = prov == "RC Soft" ? Visibility.Visible : Visibility.Collapsed;
                twilioNote.Visibility   = prov == "Twilio" ? Visibility.Visible : Visibility.Collapsed;
            }
            cEnable.SelectionChanged += (s, e) => UpdateWhatsAppVisibility();
            cProvider.SelectionChanged += (s, e) => UpdateWhatsAppVisibility();
            UpdateWhatsAppVisibility();

            // ── Test WhatsApp: selected provider ke required fields validate karta hai ──
            var btnTest = new Button
            {
                Content = "📱 Test WhatsApp",
                FontSize = 14, FontWeight = FontWeights.SemiBold, FontFamily = new FontFamily("Inter, Segoe UI"),
                Foreground = new SolidColorBrush(Color.FromRgb(0x69, 0x6C, 0xFF)),
                Background = Brushes.White, BorderBrush = new SolidColorBrush(Color.FromRgb(0xC7, 0xD2, 0xFE)),
                BorderThickness = new Thickness(1), Padding = new Thickness(18, 10, 18, 10),
                Cursor = Cursors.Hand, Margin = new Thickness(0, 0, 10, 0),
                HorizontalAlignment = HorizontalAlignment.Right
            };
            btnTest.Click += (s, e) =>
            {
                string prov = cProvider.SelectedItem?.ToString() ?? "None";
                string missing = "";
                switch (prov)
                {
                    case "RC Soft":
                        if (string.IsNullOrWhiteSpace(tbAppKey.Text)) missing += "App Key, ";
                        if (string.IsNullOrWhiteSpace(tbAuthKey.Text)) missing += "Auth Key, ";
                        break;
                    case "Twilio":
                        lblStatus.Text = "ℹ️ Twilio WhatsApp ke liye alag credentials nahi — SMS Setting mein Twilio SID / Token / Number check karein, phir Save Changes dabayen.";
                        lblStatus.Foreground = new SolidColorBrush(Color.FromRgb(0x92, 0x40, 0x0E));
                        lblStatus.Visibility = Visibility.Visible;
                        return;
                    default:
                        missing = "PROVIDER";
                        break;
                }
                if (missing.Length > 0)
                {
                    lblStatus.Text = "❌ Missing fields: " + missing.TrimEnd(' ', ',');
                    lblStatus.Foreground = Brushes.Red; lblStatus.Visibility = Visibility.Visible;
                    return;
                }
                lblStatus.Text = $"✅ {prov} configuration complete — sab required fields bhare hain. Pehle Save Changes dabayen, phir test WhatsApp bheja ja sakta hai.";
                lblStatus.Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0xA3, 0x4A));
                lblStatus.Visibility = Visibility.Visible;
            };

            var btnRow = new StackPanel { Orientation = Orientation.Horizontal, HorizontalAlignment = HorizontalAlignment.Right, VerticalAlignment = VerticalAlignment.Bottom };
            btnRow.Children.Add(btnTest);
            btnRow.Children.Add(MakeSaveBtn(() =>
            {
                S("whatsapp_invoice_enable_status", cEnable.SelectedItem?.ToString());
                S("whatsapp_provider", cProvider.SelectedItem?.ToString());
                S("whatsapp_app_key", tbAppKey.Text);
                S("whatsapp_authkey", tbAuthKey.Text);
                // Twilio fields ab alag se nahi — SMS Setting ke twilio.* use hote hain
                S("whatsapp_account_sid", G("whatsapp_account_sid"));
                S("whatsapp_auth_token", G("whatsapp_auth_token"));
                S("whatsapp_from_number", G("whatsapp_from_number"));
                OK();
            }));
            formPanel.Children.Add(btnRow);

            AddCard(contentPanel, "WhatsApp Setting", root);
        }

        // ════════════════════════════════════════════════════════════════════
        //  WHITELABEL SETTING
        // ════════════════════════════════════════════════════════════════════
        private void BuildWhitelabel()
        {
            var g = MakeGrid(2);
            var tbName   = MakeInput("Site Name", true, GJson("white_label","site_name"), 0, 0, g);
            var tbFooter = MakeInput("Site Footer", true, GJson("white_label","site_footer"), 0, 1, g);
            var tbTitle  = MakeInput("Site Title", true, GJson("white_label","site_title"), 1, 0, g);
            var tbLink   = MakeInput("Site Link", true, GJson("white_label","site_link"), 1, 1, g);

            // ── Site Logo file picker ──
            var logoPath = GJson("white_label","site_logo");
            var logoRow = MakeFilePicker("Site Logo", logoPath, 2, 0, g, out var logoFileName);
            // ── Site Favicon file picker ──
            var faviconPath = GJson("white_label","site_favicon");
            var faviconRow = MakeFilePicker("Site Favicon", faviconPath, 2, 1, g, out var faviconFileName);

            AddCard(contentPanel, "Whitelabel Setting", g);
            contentPanel.Children.Add(MakeSaveBtn(() =>
            {
                SJson("white_label","site_name", tbName.Text); SJson("white_label","site_footer", tbFooter.Text);
                SJson("white_label","site_title", tbTitle.Text); SJson("white_label","site_link", tbLink.Text);
                SJson("white_label","site_logo", logoFileName.Text == "No file chosen" ? logoPath : logoFileName.Tag?.ToString());
                SJson("white_label","site_favicon", faviconFileName.Text == "No file chosen" ? faviconPath : faviconFileName.Tag?.ToString());
                OK();
            }));
        }

        // File picker field: label + "No file chosen" display + Browse button
        private StackPanel MakeFilePicker(string label, string currentPath, int row, int col, Grid grid, out TextBlock fileNameDisplay)
        {
            var sp = new StackPanel { Margin = new Thickness(col == 0 ? 0 : 16, 0, col == 0 ? 16 : 0, 14) };
            sp.Children.Add(new TextBlock { Text = label, FontSize = 13, FontWeight = FontWeights.SemiBold, Foreground = new SolidColorBrush(Color.FromRgb(0x37, 0x41, 0x51)), FontFamily = new FontFamily("Inter, Segoe UI"), Margin = new Thickness(0, 0, 0, 6) });

            var rowPanel = new Grid();
            rowPanel.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            rowPanel.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

            string display = string.IsNullOrEmpty(currentPath) ? "No file chosen" : System.IO.Path.GetFileName(currentPath);
            fileNameDisplay = new TextBlock
            {
                Text = display, Tag = currentPath,
                FontSize = 13, FontFamily = new FontFamily("Inter, Segoe UI"),
                Foreground = string.IsNullOrEmpty(currentPath) ? new SolidColorBrush(Color.FromRgb(0x9C, 0xA3, 0xAF)) : new SolidColorBrush(Color.FromRgb(0x1E, 0x1B, 0x4B)),
                VerticalAlignment = VerticalAlignment.Center, TextTrimming = TextTrimming.CharacterEllipsis
            };
            Grid.SetColumn(fileNameDisplay, 0);
            rowPanel.Children.Add(fileNameDisplay);

            var btnBrowse = new Button
            {
                Content = "Browse",
                FontSize = 12.5, FontWeight = FontWeights.SemiBold, FontFamily = new FontFamily("Inter, Segoe UI"),
                Foreground = new SolidColorBrush(Color.FromRgb(0x69, 0x6C, 0xFF)),
                Background = Brushes.White, BorderBrush = new SolidColorBrush(Color.FromRgb(0xC7, 0xD2, 0xFE)),
                BorderThickness = new Thickness(1), Padding = new Thickness(14, 7, 14, 7),
                Cursor = Cursors.Hand, Margin = new Thickness(8, 0, 0, 0)
            };
            Grid.SetColumn(btnBrowse, 1);
            var displayTarget = fileNameDisplay; // out parameter lambda mein capture nahi hota
            btnBrowse.Click += (s, e) =>
            {
                var dlg = new Microsoft.Win32.OpenFileDialog
                {
                    Filter = "Image Files|*.jpg;*.jpeg;*.png;*.gif;*.bmp;*.svg|All files|*.*",
                    Title = "Select " + label
                };
                if (dlg.ShowDialog() == true)
                {
                    displayTarget.Text = System.IO.Path.GetFileName(dlg.FileName);
                    displayTarget.Tag = dlg.FileName;
                    displayTarget.Foreground = new SolidColorBrush(Color.FromRgb(0x1E, 0x1B, 0x4B));
                }
            };
            rowPanel.Children.Add(btnBrowse);

            sp.Children.Add(rowPanel);
            Grid.SetRow(sp, row); Grid.SetColumn(sp, col); grid.Children.Add(sp);
            return sp;
        }

        // ════════════════════════════════════════════════════════════════════
        //  PWA SETTING
        // ════════════════════════════════════════════════════════════════════

        // pwa_settings table se read karo (cloud jaisa — separate table, company_id=1)
        private string Gpwa(string col)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = $"SELECT [{col}] FROM pwa_settings WHERE company_id=1 LIMIT 1";
                var v = cmd.ExecuteScalar();
                return v?.ToString() ?? "";
            }
            catch { return ""; }
        }

        private void Spwa(string col, string value)
        {
            try
            {
                using var conn = _db.GetConnection();
                // Ensure row exists
                using (var ins = conn.CreateCommand())
                {
                    ins.CommandText = "INSERT OR IGNORE INTO pwa_settings (company_id) VALUES (1)";
                    ins.ExecuteNonQuery();
                }
                using var cmd = conn.CreateCommand();
                cmd.CommandText = $"UPDATE pwa_settings SET [{col}]=@v WHERE company_id=1";
                cmd.Parameters.AddWithValue("@v", value);
                cmd.ExecuteNonQuery();
                // pwa_settings sync — companies table se alag table hai, lekin cloud pull
                // se auto-update hoti hai; push ke liye companies row ko sync karein
                // (cloud mein pwa_settings alag manage hoti hai, push abhi skip)
            }
            catch { }
        }

        private void BuildPWA()
        {
            var g = MakeGrid(3);
            var tbName    = MakeInput("App Name", true,  Gpwa("app_name"),         0, 0, g);
            var tbShort   = MakeInput("Short Name", true, Gpwa("short_name"),       0, 1, g);
            var tbTheme   = MakeInput("Theme Color", true, Gpwa("theme_color"),     1, 0, g);
            var tbBG      = MakeInput("Background Color", true, Gpwa("background_color"), 1, 1, g);
            var tbStart   = MakeInput("Start URL", true, Gpwa("start_url"),         2, 0, g);

            AddCard(contentPanel, "PWA Setting", g);

            // Note
            contentPanel.Children.Add(new TextBlock
            {
                Text = "Note: PWA settings pwa_settings table mein save hoti hain. Cloud se sync hone par automatically update ho jaayengi.",
                FontSize = 12, Foreground = new SolidColorBrush(Color.FromRgb(0x8E,0x8E,0xA1)),
                FontFamily = new FontFamily("Inter, Segoe UI"), TextWrapping = TextWrapping.Wrap,
                Margin = new Thickness(0,0,0,8)
            });

            contentPanel.Children.Add(MakeSaveBtn(() =>
            {
                Spwa("app_name",         tbName.Text);
                Spwa("short_name",       tbShort.Text);
                Spwa("theme_color",      tbTheme.Text);
                Spwa("background_color", tbBG.Text);
                Spwa("start_url",        tbStart.Text);
                OK();
            }));
        }

        // ════════════════════════════════════════════════════════════════════
        //  SERVER CONFIGURE (cloud sync setup)
        // ════════════════════════════════════════════════════════════════════
        private void BuildServer()
        {
            // Full-width fields — bade font, taaki typing karte waqt saaf dikhe
            var serverForm = new StackPanel();

            // ── Server URL ──
            serverForm.Children.Add(new TextBlock { Text = "Server URL", FontSize = 14, FontWeight = FontWeights.SemiBold, Foreground = new SolidColorBrush(Color.FromRgb(0x37, 0x41, 0x51)), FontFamily = new FontFamily("Inter, Segoe UI"), Margin = new Thickness(0, 0, 0, 6) });
            var tbUrl = new TextBox
            {
                Text = _api.BaseUrl,
                Height = 46, FontSize = 15, FontFamily = new FontFamily("Inter, Segoe UI"),
                Background = Brushes.White, BorderBrush = new SolidColorBrush(Color.FromRgb(0xD1, 0xD5, 0xDB)),
                BorderThickness = new Thickness(1), Padding = new Thickness(12, 0, 12, 0),
                VerticalContentAlignment = VerticalAlignment.Center,
                HorizontalAlignment = HorizontalAlignment.Stretch,
                Margin = new Thickness(0, 0, 0, 16)
            };
            serverForm.Children.Add(tbUrl);

            // ── Server Email ──
            serverForm.Children.Add(new TextBlock { Text = "Server Email", FontSize = 14, FontWeight = FontWeights.SemiBold, Foreground = new SolidColorBrush(Color.FromRgb(0x37, 0x41, 0x51)), FontFamily = new FontFamily("Inter, Segoe UI"), Margin = new Thickness(0, 0, 0, 6) });
            var tbEmail = new TextBox
            {
                Text = _api.GetSetting("server_email") ?? "",
                Height = 46, FontSize = 15, FontFamily = new FontFamily("Inter, Segoe UI"),
                Background = Brushes.White, BorderBrush = new SolidColorBrush(Color.FromRgb(0xD1, 0xD5, 0xDB)),
                BorderThickness = new Thickness(1), Padding = new Thickness(12, 0, 12, 0),
                VerticalContentAlignment = VerticalAlignment.Center,
                HorizontalAlignment = HorizontalAlignment.Stretch,
                Margin = new Thickness(0, 0, 0, 16)
            };
            serverForm.Children.Add(tbEmail);

            // ── Server Password ──
            serverForm.Children.Add(new TextBlock { Text = "Server Password", FontSize = 14, FontWeight = FontWeights.SemiBold, Foreground = new SolidColorBrush(Color.FromRgb(0x37, 0x41, 0x51)), FontFamily = new FontFamily("Inter, Segoe UI"), Margin = new Thickness(0, 0, 0, 6) });
            var pwd = new PasswordBox
            {
                Password = _api.GetSetting("server_password") ?? "",
                Height = 46, FontSize = 15, FontFamily = new FontFamily("Inter, Segoe UI"),
                Background = Brushes.White, BorderBrush = new SolidColorBrush(Color.FromRgb(0xD1, 0xD5, 0xDB)),
                BorderThickness = new Thickness(1), Padding = new Thickness(12, 0, 12, 0),
                VerticalContentAlignment = VerticalAlignment.Center,
                HorizontalAlignment = HorizontalAlignment.Stretch,
                Margin = new Thickness(0, 0, 0, 16)
            };
            serverForm.Children.Add(pwd);

            // Connection status line
            var lblStatus = new TextBlock
            {
                FontSize = 13, FontFamily = new FontFamily("Inter, Segoe UI"),
                Margin = new Thickness(2, 0, 0, 10), Visibility = Visibility.Collapsed,
                TextWrapping = TextWrapping.Wrap
            };
            serverForm.Children.Add(lblStatus);

            // ── Buttons ──
            var btnRow = new StackPanel { Orientation = Orientation.Horizontal, HorizontalAlignment = HorizontalAlignment.Right, VerticalAlignment = VerticalAlignment.Bottom };

            var btnTest = new Button
            {
                Content = "🔌 Test Connection",
                FontSize = 14, FontWeight = FontWeights.SemiBold, FontFamily = new FontFamily("Inter, Segoe UI"),
                Foreground = new SolidColorBrush(Color.FromRgb(0x69, 0x6C, 0xFF)),
                Background = Brushes.White, BorderBrush = new SolidColorBrush(Color.FromRgb(0xC7, 0xD2, 0xFE)),
                BorderThickness = new Thickness(1), Padding = new Thickness(18, 10, 18, 10),
                Cursor = Cursors.Hand, Margin = new Thickness(0, 0, 10, 0),
                HorizontalAlignment = HorizontalAlignment.Right
            };

            var btnSave = new Button
            {
                Content = new StackPanel
                {
                    Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center,
                    Children =
                    {
                        new TextBlock { Text = "🔄 ", FontSize = 14, VerticalAlignment = VerticalAlignment.Center },
                        new TextBlock { Text = "Save & Sync", FontSize = 14, FontWeight = FontWeights.SemiBold, VerticalAlignment = VerticalAlignment.Center }
                    }
                },
                Style = (Style)FindResource("SaveBtn"),
                HorizontalAlignment = HorizontalAlignment.Right,
                Margin = new Thickness(0, 8, 0, 0), Cursor = Cursors.Hand
            };

            btnTest.Click += async (s, e) =>
            {
                if (string.IsNullOrWhiteSpace(tbEmail.Text) || pwd.Password.Length == 0)
                {
                    lblStatus.Text = "Server email aur password bharo pehle.";
                    lblStatus.Foreground = Brushes.Red; lblStatus.Visibility = Visibility.Visible; return;
                }
                btnTest.IsEnabled = false; btnTest.Content = "Testing...";
                try
                {
                    var (ok, msg) = await _api.TestLoginAsync(tbUrl.Text.Trim(), tbEmail.Text.Trim(), pwd.Password);
                    lblStatus.Text = ok ? "✅ Connected successfully." : "❌ Failed: " + msg;
                    lblStatus.Foreground = ok ? new SolidColorBrush(Color.FromRgb(0x16, 0xA3, 0x4A)) : Brushes.Red;
                    lblStatus.Visibility = Visibility.Visible;
                }
                catch (Exception ex) { lblStatus.Text = "❌ Failed: " + ex.Message; lblStatus.Foreground = Brushes.Red; lblStatus.Visibility = Visibility.Visible; }
                finally { btnTest.IsEnabled = true; btnTest.Content = "🔌 Test Connection"; }
            };

            btnSave.Click += async (s, e) =>
            {
                if (string.IsNullOrWhiteSpace(tbEmail.Text) || pwd.Password.Length == 0)
                {
                    lblStatus.Text = "Server email aur password bharo.";
                    lblStatus.Foreground = Brushes.Red; lblStatus.Visibility = Visibility.Visible; return;
                }
                btnSave.IsEnabled = false; btnSave.Content = "Saving & Syncing...";
                try
                {
                    _api.SetCredentials(tbUrl.Text.Trim(), tbEmail.Text.Trim(), pwd.Password);
                    var (ok, msg, _) = await _api.LoginAsync(tbEmail.Text.Trim(), pwd.Password);
                    if (!ok)
                    {
                        lblStatus.Text = "❌ Login failed: " + msg;
                        lblStatus.Foreground = Brushes.Red; lblStatus.Visibility = Visibility.Visible;
                        btnSave.IsEnabled = true; btnSave.Content = "Save & Sync";
                        return;
                    }
                    lblStatus.Text = "✅ Saved to SQLite. Cloud sync start ho raha hai...";
                    lblStatus.Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0xA3, 0x4A));
                    lblStatus.Visibility = Visibility.Visible;
                    btnRow.Children.Remove(btnSave);
                    btnRow.Children.Add(new TextBlock { Text = "Saved ✓", FontSize = 14, FontWeight = FontWeights.SemiBold, Foreground = new SolidColorBrush(Color.FromRgb(0x16, 0xA3, 0x4A)), VerticalAlignment = VerticalAlignment.Center });
                    _ = _dashboard?.TriggerSync();
                }
                catch (Exception ex)
                {
                    lblStatus.Text = "❌ Failed: " + ex.Message;
                    lblStatus.Foreground = Brushes.Red; lblStatus.Visibility = Visibility.Visible;
                    btnSave.IsEnabled = true; btnSave.Content = "Save & Sync";
                }
            };

            btnRow.Children.Add(btnTest);
            btnRow.Children.Add(btnSave);
            serverForm.Children.Add(btnRow);

            AddCard(contentPanel, "Server Configure — Cloud Sync", serverForm);
            contentPanel.Children.Add(new TextBlock
            {
                Text = "Is section se desktop software ko cloud (Laravel) server se connect karein. Server URL, email aur password save hone par SQLite (AppSettings table) mein save ho jaate hain, aur saari details (items, customers, suppliers, sales, employees) server se sync ho jaayengi.",
                FontSize = 12.5, Foreground = new SolidColorBrush(Color.FromRgb(0x8E, 0x8E, 0xA1)),
                FontFamily = new FontFamily("Inter, Segoe UI"), TextWrapping = TextWrapping.Wrap, Margin = new Thickness(4, 0, 4, 0)
            });
        }

        // ── Navigation ────────────────────────────────────────────────────
        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowDashboard();
        }
    }
}
