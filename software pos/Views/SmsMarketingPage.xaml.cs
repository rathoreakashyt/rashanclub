using System;
using System.Collections.Generic;
using System.Data;
using System.Diagnostics;
using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class SmsMarketingPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();
        private int _birthdayCount, _anniversaryCount, _eligibleCount;
        private readonly List<(long Id, string Name, string Phone)> _phoneCustomers = new();

        public SmsMarketingPage(MainDashboard? dashboard)
        {
            InitializeComponent();
            _dashboard = dashboard;
            txtBirthdayMessage.TextChanged += (s, e) => { if (runBirthdayCharCount != null) runBirthdayCharCount.Text = (txtBirthdayMessage.Text?.Length ?? 0).ToString(); };
            txtAnniversaryMessage.TextChanged += (s, e) => { if (runAnniversaryCharCount != null) runAnniversaryCharCount.Text = (txtAnniversaryMessage.Text?.Length ?? 0).ToString(); };
            txtCustomMessage.TextChanged += (s, e) => { if (runCustomCharCount != null) runCustomCharCount.Text = (txtCustomMessage.Text?.Length ?? 0).ToString(); };
            LoadData();
        }

        private void LoadData()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, name, COALESCE(phone,'') AS phone, COALESCE(dob,'') AS dob, COALESCE(anniversary,'') AS anniversary FROM customers WHERE (del_status IS NULL OR del_status='Live') AND phone IS NOT NULL AND phone != '' ORDER BY name";
                var table = new DataTable();
                using (var r = cmd.ExecuteReader()) table.Load(r);

                var todayMd = DateTime.Now.ToString("MM-dd");
                _birthdayCount = 0;
                _anniversaryCount = 0;
                _phoneCustomers.Clear();

                foreach (DataRow row in table.Rows)
                {
                    long id = Convert.ToInt64(row["id"]);
                    string name = row["name"].ToString()!;
                    string phone = row["phone"].ToString()!;
                    string dob = row["dob"].ToString()!;
                    string wedding = row["anniversary"].ToString()!;

                    _phoneCustomers.Add((id, name, phone));

                    if (!string.IsNullOrEmpty(dob))
                    {
                        var parsed = ParseMonthDay(dob);
                        if (parsed == todayMd) _birthdayCount++;
                    }
                    if (!string.IsNullOrEmpty(wedding))
                    {
                        var parsed = ParseMonthDay(wedding);
                        if (parsed == todayMd) _anniversaryCount++;
                    }
                }

                _eligibleCount = _phoneCustomers.Count;
                txtBirthdayCount.Text = _birthdayCount.ToString();
                txtAnniversaryCount.Text = _anniversaryCount.ToString();
                txtEligibleCount.Text = _eligibleCount.ToString();
                runBirthdayRecipients.Text = _birthdayCount.ToString();
                runAnniversaryRecipients.Text = _anniversaryCount.ToString();

                // Load recommended messages
                txtBirthdayMessage.Text = "Happy Birthday! We're wishing you a wonderful day. Thank you for being a valued customer.";
                txtAnniversaryMessage.Text = "Congratulations on your anniversary! We appreciate your continued support.";

                // Populate custom recipients list
                foreach (var c in _phoneCustomers)
                {
                    var chk = new CheckBox { Content = $"{c.Name} ({c.Phone})", FontSize = 12, Foreground = new System.Windows.Media.SolidColorBrush(System.Windows.Media.Color.FromRgb(0x37, 0x41, 0x51)), Margin = new Thickness(4, 4, 4, 4), Tag = c.Id };
                    chk.Checked += Recipient_Checked;
                    chk.Unchecked += Recipient_Checked;
                    lstCustomers.Items.Add(chk);
                }

                UpdateCustomCount();
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error loading data: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private string? ParseMonthDay(string dateStr)
        {
            if (string.IsNullOrWhiteSpace(dateStr)) return null;
            dateStr = dateStr.Trim();
            string[] formats = { "yyyy-MM-dd", "dd-MM-yyyy", "dd/MM/yyyy", "MM/dd/yyyy", "dd.MM.yyyy" };
            foreach (var fmt in formats)
            {
                if (DateTime.TryParseExact(dateStr, fmt, System.Globalization.CultureInfo.InvariantCulture, System.Globalization.DateTimeStyles.None, out var dt))
                    return dt.ToString("MM-dd");
            }
            if (DateTime.TryParse(dateStr, out var parsed))
                return parsed.ToString("MM-dd");
            return null;
        }

        private void Recipient_Checked(object sender, RoutedEventArgs e) => UpdateCustomCount();

        private void ChkAll_Changed(object sender, RoutedEventArgs e)
        {
            if (lstCustomers == null) return;
            bool isChecked = chkAllCustomers.IsChecked == true;
            foreach (CheckBox item in lstCustomers.Items)
                item.IsChecked = isChecked;
            UpdateCustomCount();
        }

        private void UpdateCustomCount()
        {
            if (lstCustomers == null || runCustomRecipients == null) return;
            int count = 0;
            foreach (CheckBox item in lstCustomers.Items)
                if (item.IsChecked == true) count++;
            runCustomRecipients.Text = count.ToString();
            if (chkAllCustomers?.IsChecked == true)
            {
                txtCustomAlertMsg.Text = "This SMS will be sent to every customer with a valid phone number.";
                runCustomRecipients.Text = _eligibleCount.ToString();
            }
            else
            {
                txtCustomAlertMsg.Text = "This SMS will be sent only to the customers you selected above.";
            }
        }

        private void BtnSendBirthday_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(txtBirthdayMessage.Text))
            { MessageBox.Show("Please enter a message.", "Validation"); return; }
            if (_birthdayCount == 0) { MessageBox.Show("No customers with birthday today.", "Info"); return; }

            var contacts = new List<string>();
            var todayMd = DateTime.Now.ToString("MM-dd");
            foreach (var c in _phoneCustomers)
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT COALESCE(dob,'') AS dob FROM customers WHERE id=@id";
                cmd.Parameters.Add(new Microsoft.Data.Sqlite.SqliteParameter("@id", c.Id));
                var dob = cmd.ExecuteScalar()?.ToString() ?? "";
                if (!string.IsNullOrEmpty(dob) && ParseMonthDay(dob) == todayMd)
                    contacts.Add(c.Phone);
            }
            if (contacts.Count == 0) { MessageBox.Show("No customers with birthday today.", "Info"); return; }

            string msg = Uri.EscapeDataString(txtBirthdayMessage.Text);
            string uri = $"sms:{string.Join(",", contacts)}?body={msg}";
            Process.Start(new ProcessStartInfo(uri) { UseShellExecute = true });
        }

        private void BtnSendAnniversary_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(txtAnniversaryMessage.Text))
            { MessageBox.Show("Please enter a message.", "Validation"); return; }
            if (_anniversaryCount == 0) { MessageBox.Show("No customers with anniversary today.", "Info"); return; }

            var contacts = new List<string>();
            var todayMd = DateTime.Now.ToString("MM-dd");
            foreach (var c in _phoneCustomers)
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT COALESCE(anniversary,'') AS wd FROM customers WHERE id=@id";
                cmd.Parameters.Add(new Microsoft.Data.Sqlite.SqliteParameter("@id", c.Id));
                var wd = cmd.ExecuteScalar()?.ToString() ?? "";
                if (!string.IsNullOrEmpty(wd) && ParseMonthDay(wd) == todayMd)
                    contacts.Add(c.Phone);
            }
            if (contacts.Count == 0) { MessageBox.Show("No customers with anniversary today.", "Info"); return; }

            string msg = Uri.EscapeDataString(txtAnniversaryMessage.Text);
            string uri = $"sms:{string.Join(",", contacts)}?body={msg}";
            Process.Start(new ProcessStartInfo(uri) { UseShellExecute = true });
        }

        private void BtnSendCustom_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(txtCustomMessage.Text))
            { MessageBox.Show("Please enter a message.", "Validation"); return; }

            var contacts = new List<string>();
            bool sendAll = chkAllCustomers.IsChecked == true;
            if (sendAll)
            {
                foreach (var c in _phoneCustomers) contacts.Add(c.Phone);
            }
            else
            {
                foreach (CheckBox item in lstCustomers.Items)
                {
                    if (item.IsChecked == true && item.Tag is long id)
                    {
                        var c = _phoneCustomers.Find(x => x.Id == id);
                        if (c.Phone != "") contacts.Add(c.Phone);
                    }
                }
            }
            if (contacts.Count == 0) { MessageBox.Show("No recipients selected.", "Info"); return; }

            string msg = Uri.EscapeDataString(txtCustomMessage.Text);
            string uri = $"sms:{string.Join(",", contacts)}?body={msg}";
            Process.Start(new ProcessStartInfo(uri) { UseShellExecute = true });
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            if (_dashboard != null) _dashboard.ShowDashboard();
        }
    }
}
