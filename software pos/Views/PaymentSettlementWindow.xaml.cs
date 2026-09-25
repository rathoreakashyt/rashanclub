using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;

namespace RashanKiDukan.Views
{
    public partial class PaymentSettlementWindow : Window
    {
        private readonly double _billTotal;
        private readonly int _itemCount;
        private readonly double _totalQty;
        private string _selectedMode = "Cash";
        public bool IsConfirmed { get; private set; }
        public string PaymentMode { get; private set; } = "Cash";
        public double CashReceived { get; private set; }
        public double Change { get; private set; }

        public PaymentSettlementWindow(double billTotal, int itemCount, double totalQty)
        {
            InitializeComponent();
            _billTotal = billTotal;
            _itemCount = itemCount;
            _totalQty = totalQty;

            lblBillTotal.Text = $"Rs.{billTotal:N2}";
            lblItemSummary.Text = $"{itemCount} items | {totalQty:N3} qty";
            lblUPIAmount.Text = $"Amount: Rs.{billTotal:N2}";
            Owner = Application.Current.MainWindow;
        }

        private void TabCash_Click(object sender, MouseButtonEventArgs e) { SwitchTab("Cash"); }
        private void TabUPI_Click(object sender, MouseButtonEventArgs e) { SwitchTab("UPI"); }
        private void TabCard_Click(object sender, MouseButtonEventArgs e) { SwitchTab("Card"); }

        private void SwitchTab(string mode)
        {
            _selectedMode = mode;
            var bc = new System.Windows.Media.BrushConverter();
            tabCash.Background = mode == "Cash" ? (System.Windows.Media.Brush)bc.ConvertFromString("#059669") : (System.Windows.Media.Brush)bc.ConvertFromString("#334155");
            tabUPI.Background = mode == "UPI" ? (System.Windows.Media.Brush)bc.ConvertFromString("#7C3AED") : (System.Windows.Media.Brush)bc.ConvertFromString("#334155");
            tabCard.Background = mode == "Card" ? (System.Windows.Media.Brush)bc.ConvertFromString("#0E7490") : (System.Windows.Media.Brush)bc.ConvertFromString("#334155");

            var lightBrush = (System.Windows.Media.Brush)bc.ConvertFromString("#E2E8F0");
            var darkBrush = (System.Windows.Media.Brush)bc.ConvertFromString("#94A3B8");

            ((tabCash.Child as StackPanel)!.Children[0] as TextBlock)!.Foreground = mode == "Cash" ? lightBrush : darkBrush;
            ((tabUPI.Child as StackPanel)!.Children[0] as TextBlock)!.Foreground = mode == "UPI" ? lightBrush : darkBrush;
            ((tabCard.Child as StackPanel)!.Children[0] as TextBlock)!.Foreground = mode == "Card" ? lightBrush : darkBrush;

            pnlCash.Visibility = mode == "Cash" ? Visibility.Visible : Visibility.Collapsed;
            pnlUPI.Visibility = mode == "UPI" ? Visibility.Visible : Visibility.Collapsed;
            pnlCard.Visibility = mode == "Card" ? Visibility.Visible : Visibility.Collapsed;
        }

        private void CashChanged(object sender, TextChangedEventArgs e)
        {
            double total = 0;
            if (double.TryParse(txt2000.Text, out double n2000)) total += n2000 * 2000;
            if (double.TryParse(txt500.Text, out double n500)) total += n500 * 500;
            if (double.TryParse(txt200.Text, out double n200)) total += n200 * 200;
            if (double.TryParse(txt100.Text, out double n100)) total += n100 * 100;
            if (double.TryParse(txt50.Text, out double n50)) total += n50 * 50;
            if (double.TryParse(txt20.Text, out double n20)) total += n20 * 20;
            if (double.TryParse(txt10.Text, out double n10)) total += n10 * 10;

            lblCashTotal.Text = $"Rs.{total:N2}";
            double change = total - _billTotal;
            lblChange.Text = change >= 0 ? $"Rs.{change:N2}" : $"- Rs.{Math.Abs(change):N2}";
            var bc = new System.Windows.Media.BrushConverter();
            lblChange.Foreground = change >= 0
                ? (System.Windows.Media.Brush)bc.ConvertFromString("#34D399")
                : (System.Windows.Media.Brush)bc.ConvertFromString("#F87171");
        }

        private void BtnConfirm_Click(object sender, RoutedEventArgs e)
        {
            if (_selectedMode == "Cash")
            {
                double total = 0;
                if (double.TryParse(txt2000.Text, out double n2000)) total += n2000 * 2000;
                if (double.TryParse(txt500.Text, out double n500)) total += n500 * 500;
                if (double.TryParse(txt200.Text, out double n200)) total += n200 * 200;
                if (double.TryParse(txt100.Text, out double n100)) total += n100 * 100;
                if (double.TryParse(txt50.Text, out double n50)) total += n50 * 50;
                if (double.TryParse(txt20.Text, out double n20)) total += n20 * 20;
                if (double.TryParse(txt10.Text, out double n10)) total += n10 * 10;

                if (total < _billTotal)
                {
                    MessageBox.Show("Cash received is less than bill amount!", "Insufficient", MessageBoxButton.OK, MessageBoxImage.Warning);
                    return;
                }
                CashReceived = total;
                Change = total - _billTotal;
            }

            IsConfirmed = true;
            PaymentMode = _selectedMode;
            DialogResult = true;
            Close();
        }

        private void BtnClose_Click(object sender, RoutedEventArgs e)
        {
            DialogResult = false;
            Close();
        }
    }
}
