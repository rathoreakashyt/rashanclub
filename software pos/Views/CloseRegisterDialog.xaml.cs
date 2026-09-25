using System;
using System.Collections.Generic;
using System.Globalization;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using RashanKiDukan.Database;
using RashanKiDukan.Models;

namespace RashanKiDukan.Views
{
    /// <summary>
    /// F11 — compact Close Register popup: Submitted Cash / Advances / Vouchers.
    /// Up/Down arrows se field move, type karke Enter → register close + Z-Report print.
    /// Counter person ko expected/match kuch nahi dikhta — wo sirf printout par aata hai.
    /// </summary>
    public partial class CloseRegisterDialog : Window
    {
        private readonly DatabaseService _db;
        private readonly User? _user;
        private int _rowIndex;   // 0=Submitted, 1=Advances, 2=Vouchers

        public bool Printed { get; private set; }

        /// <summary>Print ke baad populated settlement — register close me use hota hai.</summary>
        public ShiftSettlementService.SettlementData? Settlement { get; private set; }

        public CloseRegisterDialog(DatabaseService db, User? user)
        {
            InitializeComponent();
            _db = db;
            _user = user;
            Loaded += (_, _) => { LoadOpening(); txtSubmitted.Focus(); HighlightRow(0); };
        }

        /// <summary>
        /// F10 par dale gaye opening amounts dikhao + Submitted Cash ka prefill
        /// proper calculation se: Opening Cash + Cash Sales − Cash Returns − Advances.
        /// (Expected drawer cash — counter person ise edit kar sakta hai.)
        /// </summary>
        private void LoadOpening()
        {
            try
            {
                var service = new ShiftSettlementService(_db, _user);
                var (opCash, opUpi, opCard) = service.GetOpeningByMethod();

                lblOpening.Text = $"Cash ₹{opCash:N2}  ·  UPI ₹{opUpi:N2}  ·  Card ₹{opCard:N2}";

                // Expected cash = opening cash + cash sales − cash returns (advances 0 abhi)
                var expected = service.Collect(0, 0, 0);
                double prefill = Math.Round(expected.OpeningCashFloat + expected.CashAfterReturn, 2);
                txtSubmitted.Text = prefill > 0 ? prefill.ToString("0.##") : "0";
            }
            catch
            {
                lblOpening.Text = "—";
                txtSubmitted.Text = "0";
            }
        }

        // ═══ ROW NAVIGATION (Up/Down + focus highlight) ═══

        private void Row_GotFocus(object sender, RoutedEventArgs e)
        {
            _rowIndex = sender == txtSubmitted ? 0 : sender == txtAdvances ? 1 : 2;
            HighlightRow(_rowIndex);
        }

        private void HighlightRow(int idx)
        {
            SetRowStyle(rowSubmitted, txtSubmitted, idx == 0);
            SetRowStyle(rowAdvances, txtAdvances, idx == 1);
            SetRowStyle(rowVouchers, txtVouchers, idx == 2);
        }

        private static void SetRowStyle(Panel row, TextBox box, bool active)
        {
            row.Opacity = active ? 1.0 : 0.75;
            box.BorderBrush = active
                ? new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#B91C1C"))
                : new System.Windows.Media.SolidColorBrush((System.Windows.Media.Color)System.Windows.Media.ColorConverter.ConvertFromString("#D1D5DB"));
            box.BorderThickness = new Thickness(active ? 1.5 : 1);
        }

        private void Window_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            switch (e.Key)
            {
                case Key.Down:
                    e.Handled = true;
                    MoveFocus(1);
                    break;
                case Key.Up:
                    e.Handled = true;
                    MoveFocus(-1);
                    break;
                case Key.Enter:
                    e.Handled = true;
                    DoCloseAndPrint();
                    break;
                case Key.Escape:
                    e.Handled = true;
                    Close();
                    break;
            }
        }

        private void MoveFocus(int dir)
        {
            _rowIndex = Math.Clamp(_rowIndex + dir, 0, 2);
            (_rowIndex == 0 ? txtSubmitted : _rowIndex == 1 ? txtAdvances : txtVouchers).Focus();
        }

        // ═══ INPUT ═══

        private void NumOnly_PreviewTextInput(object sender, TextCompositionEventArgs e)
            => e.Handled = !e.Text.All(char.IsDigit) && e.Text != ".";

        private void Any_TextChanged(object sender, TextChangedEventArgs e) { /* reserved */ }

        private static double ParseNum(string s)
            => double.TryParse((s ?? "").Trim(), NumberStyles.Any, CultureInfo.InvariantCulture, out var v) ? v : 0;

        private void BtnClose_Click(object sender, MouseButtonEventArgs e) => Close();

        // ═══ CLOSE REGISTER + PRINT ═══

        private void DoCloseAndPrint()
        {
            try
            {
                double submitted = ParseNum(txtSubmitted.Text);
                double advances = ParseNum(txtAdvances.Text);
                double vouchers = ParseNum(txtVouchers.Text);

                // Settlement figures compute karo (sirf printout ke liye)
                var service = new ShiftSettlementService(_db, _user);
                Settlement = service.Collect(submitted, advances, vouchers);

                // Z-Report seedha print (PDF thermal slip)
                PrintInvoiceHelper.PrintSettlementReceipt(Settlement, DateTime.Now);

                Printed = true;
                Close();
            }
            catch (Exception ex)
            {
                MessageBox.Show("Close Register failed: " + ex.Message, "Close Register",
                    MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }
    }
}
