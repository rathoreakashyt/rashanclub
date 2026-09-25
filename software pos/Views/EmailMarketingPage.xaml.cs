using System;
using System.Collections.Generic;
using System.Data;
using System.Diagnostics;
using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class EmailMarketingPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();
        private int _birthdayCount, _anniversaryCount, _eligibleCount;
        private readonly List<(long Id, string Name, string Email)> _emailCustomers = new();

        public EmailMarketingPage(MainDashboard? dashboard)
        {
            InitializeComponent();
            _dashboard = dashboard;
            LoadData();
        }

        private void LoadData()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, name, COALESCE(email,'') AS email, COALESCE(dob,'') AS dob, COALESCE(anniversary,'') AS anniversary FROM customers WHERE (del_status IS NULL OR del_status='Live') AND email IS NOT NULL AND email != '' ORDER BY name";
                var table = new DataTable();
                using (var r = cmd.ExecuteReader()) table.Load(r);

                var todayMd = DateTime.Now.ToString("MM-dd");
                _birthdayCount = 0;
                _anniversaryCount = 0;
                _emailCustomers.Clear();

                foreach (DataRow row in table.Rows)
                {
                    long id = Convert.ToInt64(row["id"]);
                    string name = row["name"].ToString()!;
                    string email = row["email"].ToString()!;
                    string dob = row["dob"].ToString()!;
                    string wedding = row["anniversary"].ToString()!;

                    _emailCustomers.Add((id, name, email));

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

                _eligibleCount = _emailCustomers.Count;
                txtBirthdayCount.Text = _birthdayCount.ToString();
                txtAnniversaryCount.Text = _anniversaryCount.ToString();
                txtEligibleCount.Text = _eligibleCount.ToString();
                runBirthdayRecipients.Text = _birthdayCount.ToString();
                runAnniversaryRecipients.Text = _anniversaryCount.ToString();

                // Load recommended messages
                txtBirthdaySubject.Text = "Happy Birthday!";
                txtBirthdayMessage.Text = "We're wishing you a very happy birthday! Thank you for being a valued customer.";
                txtAnniversarySubject.Text = "Happy Anniversary!";
                txtAnniversaryMessage.Text = "Congratulations on your anniversary! We appreciate your continued support.";

                // Populate custom recipients list
                foreach (var c in _emailCustomers)
                {
                    var chk = new CheckBox { Content = $"{c.Name} ({c.Email})", FontSize = 12, Foreground = new System.Windows.Media.SolidColorBrush(System.Windows.Media.Color.FromRgb(0x37, 0x41, 0x51)), Margin = new Thickness(4, 4, 4, 4), Tag = c.Id };
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
                txtCustomAlertMsg.Text = "This email will be sent to every customer with a valid email address.";
                runCustomRecipients.Text = _eligibleCount.ToString();
            }
            else
            {
                txtCustomAlertMsg.Text = "This email will be sent only to the customers you selected above.";
            }
        }

        private void BtnSendBirthday_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(txtBirthdaySubject.Text) || string.IsNullOrWhiteSpace(txtBirthdayMessage.Text))
            { MessageBox.Show("Please fill Subject and Message.", "Validation"); return; }
            if (_birthdayCount == 0) { MessageBox.Show("No customers with birthday today.", "Info"); return; }

            var contacts = new List<string>();
            var todayMd = DateTime.Now.ToString("MM-dd");
            foreach (var c in _emailCustomers)
            {
                // re-check birthday from DB
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT COALESCE(dob,'') AS dob FROM customers WHERE id=@id";
                cmd.Parameters.Add(new Microsoft.Data.Sqlite.SqliteParameter("@id", c.Id));
                var dob = cmd.ExecuteScalar()?.ToString() ?? "";
                if (!string.IsNullOrEmpty(dob) && ParseMonthDay(dob) == todayMd)
                    contacts.Add(c.Email);
            }
            if (contacts.Count == 0) { MessageBox.Show("No customers with birthday today.", "Info"); return; }

            string subject = Uri.EscapeDataString(txtBirthdaySubject.Text);
            string body = Uri.EscapeDataString(txtBirthdayMessage.Text);
            string uri = $"mailto:{string.Join(";", contacts)}?subject={subject}&body={body}";
            Process.Start(new ProcessStartInfo(uri) { UseShellExecute = true });
        }

        private void BtnSendAnniversary_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(txtAnniversarySubject.Text) || string.IsNullOrWhiteSpace(txtAnniversaryMessage.Text))
            { MessageBox.Show("Please fill Subject and Message.", "Validation"); return; }
            if (_anniversaryCount == 0) { MessageBox.Show("No customers with anniversary today.", "Info"); return; }

            var contacts = new List<string>();
            var todayMd = DateTime.Now.ToString("MM-dd");
            foreach (var c in _emailCustomers)
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT COALESCE(anniversary,'') AS wd FROM customers WHERE id=@id";
                cmd.Parameters.Add(new Microsoft.Data.Sqlite.SqliteParameter("@id", c.Id));
                var wd = cmd.ExecuteScalar()?.ToString() ?? "";
                if (!string.IsNullOrEmpty(wd) && ParseMonthDay(wd) == todayMd)
                    contacts.Add(c.Email);
            }
            if (contacts.Count == 0) { MessageBox.Show("No customers with anniversary today.", "Info"); return; }

            string subject = Uri.EscapeDataString(txtAnniversarySubject.Text);
            string body = Uri.EscapeDataString(txtAnniversaryMessage.Text);
            string uri = $"mailto:{string.Join(";", contacts)}?subject={subject}&body={body}";
            Process.Start(new ProcessStartInfo(uri) { UseShellExecute = true });
        }

        private void BtnSendCustom_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(txtCustomSubject.Text) || string.IsNullOrWhiteSpace(txtCustomMessage.Text))
            { MessageBox.Show("Please fill Subject and Message.", "Validation"); return; }

            var contacts = new List<string>();
            bool sendAll = chkAllCustomers.IsChecked == true;
            if (sendAll)
            {
                foreach (var c in _emailCustomers) contacts.Add(c.Email);
            }
            else
            {
                foreach (CheckBox item in lstCustomers.Items)
                {
                    if (item.IsChecked == true && item.Tag is long id)
                    {
                        var c = _emailCustomers.Find(x => x.Id == id);
                        if (c.Email != "") contacts.Add(c.Email);
                    }
                }
            }
            if (contacts.Count == 0) { MessageBox.Show("No recipients selected.", "Info"); return; }

            string subject = Uri.EscapeDataString(txtCustomSubject.Text);
            string body = Uri.EscapeDataString(txtCustomMessage.Text);
            string uri = $"mailto:{string.Join(";", contacts)}?subject={subject}&body={body}";
            Process.Start(new ProcessStartInfo(uri) { UseShellExecute = true });
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            if (_dashboard != null) _dashboard.ShowDashboard();
        }
    }
}
