using System;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;

namespace RashanKiDukan.Views
{
    /// <summary>
    /// Buy X Get Y scheme par custom % price override.
    /// Agar customer ne poori qty nahi li (e.g. buy 1 get 1 mein sirf 1 item),
    /// toh shopkeeper List Price ka koi bhi % (0–100) laga sakta hai.
    /// </summary>
    public partial class SchemePercentWindow : Window
    {
        private string _pctText = "60";
        private readonly double _listPrice;
        private readonly double _qty;

        public double Percentage { get; private set; } = 60;

        public SchemePercentWindow(string name, string code, double listPrice, double qty)
        {
            InitializeComponent();
            _listPrice = listPrice;
            _qty = qty;

            lblItemName.Text = name;
            lblItemMeta.Text = $"{code}   |   List Price: Rs.{listPrice:N2}   |   Qty: {qty:0.##}";

            UpdateDisplay();
        }

        private void UpdateDisplay()
        {
            txtPct.Text = _pctText;

            if (double.TryParse(_pctText, out var pct))
            {
                pct = Math.Clamp(pct, 0, 100);
                double newPrice = _listPrice * pct / 100.0;
                double amount = newPrice * _qty;
                lblPreview.Text = $"New Price: Rs.{newPrice:N2} / unit   →   Amount: Rs.{amount:N2}";

                if (pct < 0 || pct > 100)
                {
                    lblWarn.Text = "Percentage 0 – 100 ke beech hona chahiye.";
                    lblWarn.Visibility = Visibility.Visible;
                }
                else
                {
                    lblWarn.Visibility = Visibility.Collapsed;
                }
            }
            else
            {
                lblPreview.Text = "";
                lblWarn.Text = "Invalid percentage.";
                lblWarn.Visibility = Visibility.Visible;
            }
        }

        private void AppendDigit(string digit)
        {
            var digitsOnly = _pctText.Replace(".", "");
            if (digitsOnly.Length >= 4) return;

            var dotIndex = _pctText.IndexOf('.');
            if (dotIndex >= 0 && _pctText.Length - dotIndex - 1 >= 2) return;

            _pctText += digit;
            UpdateDisplay();
        }

        private void IncDec(double delta)
        {
            if (!double.TryParse(_pctText, out var p)) p = 60;
            p = Math.Clamp(Math.Round(p + delta, 2), 0, 100);
            _pctText = p.ToString("0.##");
            UpdateDisplay();
        }

        private void Backspace()
        {
            if (_pctText.Length > 1)
                _pctText = _pctText.Substring(0, _pctText.Length - 1);
            else
                _pctText = "0";
            UpdateDisplay();
        }

        private void Confirm()
        {
            if (!double.TryParse(_pctText, out var pct)) return;
            pct = Math.Clamp(pct, 0, 100);
            if (pct < 0 || pct > 100) return;

            Percentage = pct;
            DialogResult = true;
            Close();
        }

        private void Window_KeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key >= Key.D0 && e.Key <= Key.D9)
            {
                AppendDigit(((int)(e.Key - Key.D0)).ToString());
                e.Handled = true;
            }
            else if (e.Key >= Key.NumPad0 && e.Key <= Key.NumPad9)
            {
                AppendDigit(((int)(e.Key - Key.NumPad0)).ToString());
                e.Handled = true;
            }
            else if (e.Key == Key.OemPeriod || e.Key == Key.Decimal)
            {
                if (!_pctText.Contains(".")) { _pctText += "."; UpdateDisplay(); }
                e.Handled = true;
            }
            else if (e.Key == Key.Back)
            {
                Backspace();
                e.Handled = true;
            }
            else
            {
                switch (e.Key)
                {
                    case Key.Left:
                    case Key.Down:
                        IncDec(-1);
                        e.Handled = true;
                        break;
                    case Key.Right:
                    case Key.Up:
                        IncDec(1);
                        e.Handled = true;
                        break;
                    case Key.Enter:
                        Confirm();
                        e.Handled = true;
                        break;
                    case Key.Escape:
                        DialogResult = false;
                        Close();
                        e.Handled = true;
                        break;
                }
            }
        }

        private void QuickPct_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is string tag)
            {
                _pctText = tag;
                UpdateDisplay();
            }
        }

        private void BtnOk_Click(object sender, RoutedEventArgs e) => Confirm();

        private void BtnCancel_Click(object sender, RoutedEventArgs e)
        {
            DialogResult = false;
            Close();
        }
    }
}
