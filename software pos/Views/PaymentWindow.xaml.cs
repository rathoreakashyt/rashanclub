using System.Collections.Generic;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;

namespace RashanKiDukan.Views
{
    public partial class PaymentWindow : Window
    {
        private readonly double _grandTotal;
        private readonly CustomerInfo? _customer;
        private readonly List<TextBox> _amountBoxes = new();
        private int _navIndex = 0;

        public List<PaymentLine> Payments { get; } = new();
        public string PrimaryMode => Payments.Count > 0 ? Payments[0].Name : "Cash";
        public double ChangeAmount { get; private set; }

        public PaymentWindow(double grandTotal, CustomerInfo? customer)
        {
            InitializeComponent();
            _grandTotal = grandTotal;
            _customer = customer;

            // Re-fetch latest wallet balance from DB to avoid stale data
            if (_customer != null && _customer.Id > 0)
            {
                try
                {
                    using var conn = new RashanKiDukan.Database.DatabaseService().GetConnection();
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = "SELECT IFNULL(balance,0) FROM customer_wallets WHERE customer_id=@cid LIMIT 1";
                    cmd.Parameters.AddWithValue("@cid", _customer.Id);
                    var bal = cmd.ExecuteScalar();
                    if (bal != null && bal != DBNull.Value)
                        _customer.WalletBalance = Convert.ToDouble(bal);
                }
                catch { /* keep existing value if DB read fails */ }
            }

            lblTotal.Text = $"Rs.{grandTotal:N2}";

            if (customer != null)
            {
                lblCustomer.Text = customer.Name + (string.IsNullOrEmpty(customer.Phone) ? "" : $"  ·  {customer.Phone}");
                lblWallet.Text = $"Wallet: Rs.{customer.WalletBalance:N2}";
                lblLoyalty.Text = $"Loyalty: {customer.LoyaltyPoints:N0} pts";
                lblWalletAvail.Text = $"Balance: Rs.{customer.WalletBalance:N2}";
                lblLoyaltyAvail.Text = $"Points: {customer.LoyaltyPoints:N0}";
                if (customer.WalletBalance <= 0) { txtWallet.IsEnabled = false; lblWalletAvail.Text = "No balance"; }
                if (customer.LoyaltyPoints <= 0) { txtLoyalty.IsEnabled = false; lblLoyaltyAvail.Text = "No points"; }
            }
            else
            {
                txtWallet.IsEnabled = false;
                txtLoyalty.IsEnabled = false;
                lblWalletAvail.Text = "No balance";
                lblLoyaltyAvail.Text = "No points";
            }

            _amountBoxes.Add(txtCash);
            _amountBoxes.Add(txtUPI);
            _amountBoxes.Add(txtCard);
            _amountBoxes.Add(txtWallet);
            _amountBoxes.Add(txtLoyalty);

            // Bill amount pre-filled in Cash, selected - type over it or use it as-is
            txtCash.Text = _grandTotal.ToString("0.##");
            Recalc();
            txtCash.Focus();
            txtCash.SelectAll();
        }

        private double Get(TextBox box) => double.TryParse(box.Text, out var v) ? v : 0;

        private static readonly System.Windows.Media.Brush BrushRed = Hex("#FF4500");
        private static readonly System.Windows.Media.Brush BrushGreen = Hex("#228B22");

        private static System.Windows.Media.Brush Hex(string hex)
            => (System.Windows.Media.Brush)new System.Windows.Media.BrushConverter().ConvertFromString(hex)!;

        private void SetStatus(string prefix, string? redPart, System.Windows.Media.Brush color)
        {
            lblStatus.Inlines.Clear();
            lblStatus.Inlines.Add(new System.Windows.Documents.Run(prefix) { Foreground = color });
            if (!string.IsNullOrEmpty(redPart))
                lblStatus.Inlines.Add(new System.Windows.Documents.Run(redPart) { Foreground = BrushRed, FontWeight = FontWeights.Bold });
        }

        private void Recalc()
        {
            double sum = Get(txtCash) + Get(txtUPI) + Get(txtCard) + Get(txtWallet) + Get(txtLoyalty);
            double remaining = _grandTotal - sum;
            bool walletOver = _customer != null && Get(txtWallet) > _customer.WalletBalance + 0.001;
            bool loyaltyOver = _customer != null && Get(txtLoyalty) > _customer.LoyaltyPoints + 0.001;

            ChangeAmount = Math.Max(0, sum - _grandTotal);

            if (walletOver) SetStatus($"Wallet exceeds balance (Rs.{_customer!.WalletBalance:N2})", null, BrushRed);
            else if (loyaltyOver) SetStatus($"Loyalty exceeds points ({_customer!.LoyaltyPoints:N0} available)", null, BrushRed);
            else if (remaining > 0.001) SetStatus($"Paid Rs.{sum:N2} · Remaining Rs.{remaining:N2} - type amount to cover", null, BrushRed);
            else if (remaining < -0.001) SetStatus($"Paid Rs.{sum:N2} · ", $"Return Rs.{ChangeAmount:N2} to customer", BrushGreen);
            else SetStatus("Full amount covered - press Enter to pay", null, BrushGreen);

            brChange.Visibility = ChangeAmount > 0.001 ? Visibility.Visible : Visibility.Collapsed;
            lblChange.Text = $"Rs.{ChangeAmount:N2}";
        }

        private void Amount_TextChanged(object sender, TextChangedEventArgs e) => Recalc();

        private void TxtAmount_PreviewTextInput(object sender, TextCompositionEventArgs e)
        {
            e.Handled = !double.TryParse(((TextBox)sender).Text + e.Text, out _);
        }

        private void Window_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            switch (e.Key)
            {
                case Key.Down:
                    MoveNav(1);
                    e.Handled = true;
                    break;
                case Key.Up:
                    MoveNav(-1);
                    e.Handled = true;
                    break;
                case Key.Tab:
                    MoveNav((Keyboard.Modifiers & ModifierKeys.Shift) != 0 ? -1 : 1);
                    e.Handled = true;
                    break;
                case Key.Enter:
                    TryOk();
                    e.Handled = true;
                    break;
                case Key.Escape:
                    DialogResult = false;
                    Close();
                    e.Handled = true;
                    break;
            }
        }

        private void MoveNav(int dir)
        {
            for (int i = 0; i < _amountBoxes.Count; i++)
            {
                int idx = (_navIndex + dir * (i + 1)) % _amountBoxes.Count;
                if (idx < 0) idx += _amountBoxes.Count;
                var box = _amountBoxes[idx];
                if (box.IsEnabled)
                {
                    _navIndex = idx;
                    box.Focus();
                    box.SelectAll();
                    return;
                }
            }
        }

        private void TryOk()
        {
            double cash = Get(txtCash), upi = Get(txtUPI), card = Get(txtCard), wallet = Get(txtWallet), loyalty = Get(txtLoyalty);
            double sum = cash + upi + card + wallet + loyalty;

            if (_customer != null && wallet > _customer.WalletBalance + 0.001)
            {
                lblStatus.Text = "Wallet amount exceeds balance!";
                lblStatus.Foreground = BrushRed;
                txtWallet.Focus();
                txtWallet.SelectAll();
                return;
            }
            if (_customer != null && loyalty > _customer.LoyaltyPoints + 0.001)
            {
                lblStatus.Text = "Loyalty points exceed balance!";
                lblStatus.Foreground = BrushRed;
                txtLoyalty.Focus();
                txtLoyalty.SelectAll();
                return;
            }
            if (sum + 0.001 < _grandTotal)
            {
                lblStatus.Text = $"Payment total Rs.{sum:N2} is less than bill Rs.{_grandTotal:N2}";
                lblStatus.Foreground = BrushRed;
                return;
            }

            ChangeAmount = Math.Max(0, sum - _grandTotal);

            if (cash > 0) Payments.Add(new PaymentLine("Cash", cash, 0));
            if (upi > 0) Payments.Add(new PaymentLine("UPI", upi, 0));
            if (card > 0) Payments.Add(new PaymentLine("Card", card, 0));
            if (wallet > 0) Payments.Add(new PaymentLine("Wallet", wallet, 0));
            if (loyalty > 0) Payments.Add(new PaymentLine("Loyalty Point", loyalty, (int)System.Math.Round(loyalty)));

            DialogResult = true;
            Close();
        }

        private void BtnOk_Click(object sender, RoutedEventArgs e) => TryOk();

        private void BtnCancel_Click(object sender, RoutedEventArgs e)
        {
            DialogResult = false;
            Close();
        }
    }

    public class PaymentLine
    {
        public string Name { get; }
        public double Amount { get; }
        public int UsagePoints { get; }

        public PaymentLine(string name, double amount, int usagePoints)
        {
            Name = name;
            Amount = amount;
            UsagePoints = usagePoints;
        }
    }
}
