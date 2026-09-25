using System;
using System.Globalization;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using RashanKiDukan.Database;
using RashanKiDukan.Models;

namespace RashanKiDukan.Views
{
    /// <summary>
    /// Day Close / Shift Settlement (Z-Report) modal:
    /// Cashier submitted cash / advances / vouchers fill karta hai —
    /// system real-time shortage/excess dikhata hai, Print par thermal
    /// PDF receipt (PdfPreviewWindow) banti hai.
    /// </summary>
    public partial class ShiftSettlementDialog : Window
    {
        private readonly ShiftSettlementService _service;
        private ShiftSettlementService.SettlementData? _data;

        public bool Printed { get; private set; }

        /// <summary>Print ke baad populated settlement — register close me use hota hai.</summary>
        public ShiftSettlementService.SettlementData? Settlement => _data;

        public ShiftSettlementDialog(DatabaseService db, User? user)
        {
            InitializeComponent();
            _service = new ShiftSettlementService(db, user);
            PreviewKeyDown += (_, e) =>
            {
                if (e.Key == Key.Escape) { ResultClose(); }
            };
            Loaded += (_, _) => { LoadExpected(); txtSubmitted.Focus(); };
        }

        private void ResultClose()
        {
            DialogResult = Printed;
            Close();
        }

        private void LoadExpected()
        {
            // Submitted pre-fill NAHI — counter person khud count karke likhega.
            txtSubmitted.Text = "0";
            RefreshData();
        }

        /// <summary>Inputs se settlement data compute karo — UI par kuch nahi dikhate
        /// (na expected, na match/shortage/excess; wo sirf printout par aata hai).</summary>
        private void RefreshData()
        {
            try
            {
                double submitted = ParseNum(txtSubmitted.Text);
                double advances = ParseNum(txtAdvances.Text);
                double vouchers = ParseNum(txtVouchers.Text);

                _data = _service.Collect(submitted, advances, vouchers);
            }
            catch { }
        }

        private void Input_TextChanged(object sender, TextChangedEventArgs e) => RefreshData();

        private void NumOnly_PreviewTextInput(object sender, TextCompositionEventArgs e)
            => e.Handled = !e.Text.All(char.IsDigit) && e.Text != ".";

        private static double ParseNum(string s)
            => double.TryParse((s ?? "").Trim(), NumberStyles.Any, CultureInfo.InvariantCulture, out var v) ? v : 0;

        private void BtnPrint_Click(object sender, RoutedEventArgs e)
        {
            if (_data == null) RefreshData();
            if (_data == null)
            {
                MessageBox.Show("Settlement data ready nahi hua.", "Shift Settlement",
                    MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }

            try
            {
                PrintInvoiceHelper.PrintSettlementReceipt(_data, DateTime.Now);
                Printed = true;
                ResultClose();
            }
            catch (Exception ex)
            {
                MessageBox.Show("Print error: " + ex.Message, "Shift Settlement",
                    MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void BtnCancel_Click(object sender, RoutedEventArgs e) => ResultClose();
    }
}
