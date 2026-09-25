using System;
using System.Data;
using System.Diagnostics;
using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class MarketingPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();

        public MarketingPage(MainDashboard? dashboard)
        {
            InitializeComponent();
            _dashboard = dashboard;
            LoadCustomers();
        }

        private void LoadCustomers()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT name AS customer, phone, email FROM customers WHERE (del_status IS NULL OR del_status='Live') ORDER BY name";
                var table = new DataTable();
                using (var r = cmd.ExecuteReader()) table.Load(r);
                grid.ItemsSource = table.DefaultView;
                lblInfo.Text = $"{table.Rows.Count} customers loaded. Select rows to send.";
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void Tab_Checked(object sender, RoutedEventArgs e)
        {
            if (btnSend == null) return; // fires during InitializeComponent before fields exist
            if (tabEmail.IsChecked == true)
                btnSend.Content = "Send Email";
            else if (tabSms.IsChecked == true)
                btnSend.Content = "Send SMS";
            else
                btnSend.Content = "Send WhatsApp";
        }

        private void BtnSend_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                var view = grid.ItemsSource as DataView;
                if (view == null || view.Count == 0) return;
                var contacts = new System.Collections.Generic.List<string>();
                foreach (DataRowView row in view)
                {
                    string phone = row["phone"]?.ToString() ?? "";
                    string email = row["email"]?.ToString() ?? "";
                    if (tabEmail.IsChecked == true && !string.IsNullOrEmpty(email)) contacts.Add(email);
                    else if (tabSms.IsChecked == true && !string.IsNullOrEmpty(phone)) contacts.Add(phone);
                    else if (tabWhatsApp.IsChecked == true && !string.IsNullOrEmpty(phone)) contacts.Add(phone);
                }
                if (contacts.Count == 0)
                {
                    MessageBox.Show("No contacts with " + (tabEmail.IsChecked == true ? "email" : "phone") + " found.", "Info");
                    return;
                }
                string uri;
                if (tabEmail.IsChecked == true)
                    uri = "mailto:" + string.Join(";", contacts);
                else if (tabSms.IsChecked == true)
                    uri = "sms:" + string.Join(",", contacts);
                else
                    uri = "https://wa.me/?text=" + Uri.EscapeDataString("Hello! Greetings from Rashan Ki Dukan.") + "&phone=" + contacts[0];
                Process.Start(new ProcessStartInfo(uri) { UseShellExecute = true });
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            if (_dashboard != null) _dashboard.ShowDashboard();
        }
    }
}
