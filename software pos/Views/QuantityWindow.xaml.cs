using System;
using System.Windows;
using System.Windows.Input;

namespace RashanKiDukan.Views
{
    public partial class QuantityWindow : Window
    {
        private const double DefaultMinQty = 1;
        private string _qtyText = "1";
        private readonly double _maxQty;
        private readonly double _rate;
        private readonly double _inCart;
        private readonly double _minQty;

        public double Quantity { get; private set; } = 1;

        public QuantityWindow(string name, string code, double rate, string unit, double stock, double maxQty, double packageSize = 1, double inCart = 0)
        {
            InitializeComponent();
            _maxQty = Math.Max(0, maxQty);
            _rate = rate;
            _inCart = Math.Max(0, inCart);

            // Never block returning items even if the cart qty exceeds current stock
            _maxQty = Math.Max(_maxQty, _inCart);

            // In-cart item: start from its current qty, allow decreasing down to 0 (remove line),
            // max stays at stock total so the combined qty can never exceed stock.
            if (_inCart > 0)
            {
                _minQty = 0;
                _qtyText = _inCart.ToString("0.##");
            }
            else
            {
                _minQty = DefaultMinQty;
            }

            lblItemName.Text = name;
            var meta = $"{code}   |   Rate: Rs.{rate:N2}/{unit}";
            if (packageSize > 1) meta += $"   |   Package: {packageSize:0.##} {unit}";
            if (_inCart > 0) meta += $"   |   In Cart: {_inCart:0.##}";
            lblItemMeta.Text = meta;

            lblStatus.Text = $"Stock: {stock:0.##} {unit}";
            if (_inCart > 0) lblStatus.Text += $"   |   In Cart: {_inCart:0.##}";
            if (maxQty >= 1) lblStatus.Text += $"   |   Max: {_maxQty:0.##}";
            else lblStatus.Text += "   |   OUT OF STOCK";

            UpdateDisplay();
        }

        private void UpdateDisplay()
        {
            txtQty.Text = _qtyText;

            if (double.TryParse(_qtyText, out var q))
            {
                lblAmount.Text = $"Amount: Rs.{q * _rate:N2}";
            }

            if (double.TryParse(_qtyText, out var qq) && qq > _maxQty)
            {
                lblWarn.Text = $"Only {_maxQty:0.##} available in stock (Max). Increase not allowed.";
                lblWarn.Visibility = Visibility.Visible;
            }
            else
            {
                lblWarn.Visibility = Visibility.Collapsed;
            }
        }

        private void AppendDigit(string digit)
        {
            var digitsOnly = _qtyText.Replace(".", "");
            if (digitsOnly.Length >= 6) return;

            var dotIndex = _qtyText.IndexOf('.');
            if (dotIndex >= 0 && _qtyText.Length - dotIndex - 1 >= 2) return;

            _qtyText += digit;
            UpdateDisplay();
        }

        private void IncDec(double delta)
        {
            if (!double.TryParse(_qtyText, out var q) || q < _minQty) q = _minQty;
            q = Math.Round(q + delta, 2);

            if (q > _maxQty)
            {
                q = _maxQty;
                if (_maxQty >= 1)
                {
                    lblWarn.Text = $"Maximum quantity reached ({_maxQty:0.##}). Stock limit.";
                    lblWarn.Visibility = Visibility.Visible;
                }
            }
            if (q < _minQty) q = _minQty;

            _qtyText = q.ToString("0.##");
            UpdateDisplay();
        }

        private void Backspace()
        {
            if (_qtyText.Length > 1)
            {
                _qtyText = _qtyText.Substring(0, _qtyText.Length - 1);
            }
            else
            {
                _qtyText = "1";
            }
            UpdateDisplay();
        }

        private void Confirm()
        {
            if (!double.TryParse(_qtyText, out var q)) return;

            if (q > _maxQty)
            {
                lblWarn.Text = $"Only {_maxQty:0.##} available in stock. Please reduce quantity.";
                lblWarn.Visibility = Visibility.Visible;
                return;
            }

            if (q >= _minQty)
            {
                Quantity = q;
                DialogResult = true;
                Close();
            }
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
                if (!_qtyText.Contains("."))
                {
                    _qtyText += ".";
                    UpdateDisplay();
                }
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

        private void QtyBox_MouseWheel(object sender, MouseWheelEventArgs e)
        {
            IncDec(e.Delta > 0 ? 1 : -1);
        }

        private void BtnMinus_Click(object sender, RoutedEventArgs e)
        {
            IncDec(-1);
        }

        private void BtnPlus_Click(object sender, RoutedEventArgs e)
        {
            IncDec(1);
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
}
