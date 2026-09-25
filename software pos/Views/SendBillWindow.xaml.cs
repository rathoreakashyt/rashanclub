using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Text.Json;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views
{
    public partial class SendBillWindow : Window
    {
        private readonly ApiService _api = new();
        private readonly List<BusyCartItem> _items;
        private readonly string _customerName;
        private readonly string _phone;
        private readonly string _email;
        private readonly string _billNo;
        private readonly string _paymentMode;
        private readonly double _total;
        private readonly double _changeAmount;
        private string _shopName = "Rashan Ki Dukan";

        private int _sel;
        private bool _sending;
        private bool _waEnabled = true;
        private bool _smsEnabled = true;
        private bool _mailEnabled = true;

        private readonly Border[] _rounds;
        private readonly TextBlock[] _status;
        private readonly StackPanel[] _opts;

        public SendBillWindow(string customerName, string phone, string email, string billNo,
                              string paymentMode, double total, List<BusyCartItem> items,
                              double changeAmount = 0)
        {
            InitializeComponent();
            _customerName = customerName;
            _phone = phone;
            _email = email;
            _billNo = billNo;
            _paymentMode = paymentMode;
            _total = total;
            _items = items;
            _changeAmount = changeAmount;

            _rounds = new[] { rndNone, rndWa, rndSms, rndMail };
            _status = new[] { stNone, stWa, stSms, stMail };
            _opts = new[] { optNone, optWa, optSms, optMail };

            lblCust.Text = string.IsNullOrEmpty(_customerName) ? "Walk-in Customer" : _customerName;
            lblBill.Text = $"Bill: {_billNo}   |   Total: Rs.{_total:N2}   |   {_paymentMode}"
                         + (_changeAmount > 0 ? $"   |   Return: Rs.{_changeAmount:N2}" : "")
                         + (string.IsNullOrEmpty(_phone) ? "" : $"   |   {_phone}")
                         + (string.IsNullOrEmpty(_email) ? "" : $"   |   {_email}");

            Loaded += async (_, _) => await LoadSettingsAsync();
            _ = MoveTo(0);
        }

        private async Task LoadSettingsAsync()
        {
            var (ok, _, data) = await _api.GetSendBillSettingsAsync();
            if (ok && data != null)
            {
                var root = data.RootElement;
                _waEnabled = TryGetBool(root, "whatsapp_enabled", true);
                _smsEnabled = TryGetBool(root, "sms_enabled", true);
                _mailEnabled = TryGetBool(root, "email_enabled", true);
                if (root.TryGetProperty("company_name", out var cn) && cn.ValueKind == JsonValueKind.String)
                    _shopName = string.IsNullOrEmpty(cn.GetString()) ? _shopName : cn.GetString()!;
            }
            _status[0].Text = "default";
            _status[1].Text = _waEnabled ? "on" : "off";
            _status[2].Text = _smsEnabled ? "on" : "off";
            _status[3].Text = _mailEnabled ? "on" : "off";
            Refresh();
        }

        private static bool TryGetBool(JsonElement root, string name, bool fallback)
        {
            if (root.TryGetProperty(name, out var v) && v.ValueKind == JsonValueKind.True) return true;
            if (root.TryGetProperty(name, out var f) && f.ValueKind == JsonValueKind.False) return false;
            return fallback;
        }

        private bool IsAvailable(int i)
        {
            return i switch
            {
                1 => _waEnabled,
                2 => _smsEnabled,
                3 => _mailEnabled,
                _ => true
            };
        }

        private void Refresh()
        {
            for (int i = 0; i < 4; i++)
            {
                bool sel = i == _sel;
                bool avail = IsAvailable(i);
                double op = sel ? 1.0 : avail ? 0.75 : 0.35;
                _rounds[i].BorderThickness = sel ? new Thickness(3) : new Thickness(0);
                _rounds[i].Opacity = op;
                if (sel)
                {
                    _rounds[i].BorderBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#FFFFFF"));
                    _rounds[i].Effect = new System.Windows.Media.Effects.DropShadowEffect
                    {
                        BlurRadius = 14,
                        ShadowDepth = 2,
                        Opacity = 0.55
                    };
                }
                else
                {
                    _rounds[i].Effect = null;
                }
            }
            lblStatus.Text = _sel == 0 ? "Bill saved. Koi channel chune ya None pe OK dabayein." : "";
        }

        private int MoveTo(int index)
        {
            int i = index;
            while (!IsAvailable(i))
                i = (i + 1) % 4;
            _sel = i;
            Refresh();
            return i;
        }

        private void Window_KeyDown(object sender, KeyEventArgs e)
        {
            if (_sending) return;
            switch (e.Key)
            {
                case Key.Left:
                    MoveTo((_sel + 3) % 4);
                    e.Handled = true;
                    break;
                case Key.Right:
                    MoveTo((_sel + 1) % 4);
                    e.Handled = true;
                    break;
                case Key.Enter:
                    _ = OkAsync();
                    e.Handled = true;
                    break;
                case Key.Escape:
                    DialogResult = true;
                    Close();
                    e.Handled = true;
                    break;
            }
        }

        private void Option_Click(object sender, MouseButtonEventArgs e)
        {
            if (_sending) return;
            var sp = (StackPanel)sender;
            int idx = Array.IndexOf(_opts, sp);
            if (idx < 0) return;
            if (!IsAvailable(idx))
            {
                lblStatus.Text = "Yeh channel cloud settings me off hai - http://localhost:8080/setting se Enable karein.";
                return;
            }
            MoveTo(idx);
        }

        private void Ok_Click(object sender, RoutedEventArgs e)
        {
            _ = OkAsync();
        }

        private async Task OkAsync()
        {
            if (_sending) return;
            _sending = true;
            btnOk.IsEnabled = false;

            switch (_sel)
            {
                case 0:
                    DialogResult = true;
                    Close();
                    return;
                case 1:
                    SendWhatsApp();
                    break;
                case 2:
                    await SendViaAsync("sms", _phone, "", "SMS");
                    break;
                case 3:
                    await SendViaAsync("email", _email, $"Bill {_billNo} - {_shopName}", "Email");
                    break;
            }
        }

        private string BuildMessage()
        {
            var sb = new System.Text.StringBuilder();
            sb.AppendLine($"{_shopName}");
            sb.AppendLine($"Invoice: {_billNo}");
            sb.AppendLine($"Date: {DateTime.Now:dd-MM-yyyy}");
            sb.AppendLine("------------------------------");
            int n = 0;
            foreach (var item in _items)
            {
                n++;
                sb.AppendLine($"{n}) {item.ItemName}");
                sb.AppendLine($"   x{item.Qty:0.##} {item.Unit} = Rs.{Math.Max(0, item.Amount - item.TotDisc):N2}");
            }
            sb.AppendLine("------------------------------");
            sb.AppendLine($"Total: Rs.{_total:N2}");
            sb.AppendLine($"Payment: {_paymentMode}");
            sb.AppendLine("Thank you for shopping!");
            return sb.ToString();
        }

        private void SendWhatsApp()
        {
            if (string.IsNullOrWhiteSpace(_phone))
            {
                _sending = false;
                btnOk.IsEnabled = true;
                lblStatus.Text = "Customer ka WhatsApp number nahi hai! (F2 Customer me phone set karein)";
                lblStatus.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#DC2626"));
                return;
            }

            string msg = BuildMessage();
            string phone = _phone.Replace("+", "").Replace(" ", "").Replace("-", "");
            if (phone.Length == 10 && !phone.StartsWith("0")) phone = "91" + phone;
            string url = $"https://wa.me/{phone}?text={Uri.EscapeDataString(msg)}";

            try
            {
                Process.Start(new ProcessStartInfo(url) { UseShellExecute = true });
                lblStatus.Text = "WhatsApp Web khul raha hai...";
                lblStatus.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#16A34A"));
                DialogResult = true;
                Close();
            }
            catch (Exception ex)
            {
                _sending = false;
                btnOk.IsEnabled = true;
                lblStatus.Text = "WhatsApp kholne me error: " + ex.Message;
                lblStatus.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#DC2626"));
            }
        }

        private async Task SendViaAsync(string channel, string to, string subject, string display)
        {
            if (string.IsNullOrWhiteSpace(to))
            {
                _sending = false;
                btnOk.IsEnabled = true;
                lblStatus.Text = $"Customer ka {(channel == "sms" ? "phone" : "email")} nahi hai! (F2 Customer me set karein)";
                lblStatus.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#DC2626"));
                return;
            }

            bool retry = true;
            while (retry)
            {
                retry = false;
                lblStatus.Text = $"{display} bheja ja raha hai...";
                lblStatus.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#6B7280"));

                var (ok, message) = await _api.SendBillAsync(channel, to.Trim(), subject, BuildMessage());

                if (ok)
                {
                    lblStatus.Text = $"{display} bhej diya gaya: {message}";
                    lblStatus.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#16A34A"));
                    await Task.Delay(900);
                    DialogResult = true;
                    Close();
                    return;
                }
                else
                {
                    var result = MessageBox.Show(
                        $"{display} send failed: {message}\n\nRetry?",
                        "Send Failed",
                        MessageBoxButton.YesNo,
                        MessageBoxImage.Warning);
                    if (result == MessageBoxResult.Yes)
                    {
                        retry = true;
                    }
                    else
                    {
                        _sending = false;
                        btnOk.IsEnabled = true;
                        lblStatus.Text = $"{display} fail: {message}";
                        lblStatus.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#DC2626"));
                    }
                }
            }
        }
    }
}
