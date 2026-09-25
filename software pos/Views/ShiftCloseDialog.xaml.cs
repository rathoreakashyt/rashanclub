using System;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public enum ShiftAction { None, CloseShift, ChangeShift }

    /// <summary>
    /// F11 popup — sirf Close Shift (Change Shift option hata diya).
    /// Flow: Close Shift → ShiftSettlementDialog (amounts) → register close → Z-Report print.
    /// </summary>
    public partial class ShiftCloseDialog : Window
    {
        public ShiftAction Result { get; private set; } = ShiftAction.None;

        private static readonly Color _activeBlue = (Color)ColorConverter.ConvertFromString("#1C3F7A");
        private static readonly Color _activeBg   = (Color)ColorConverter.ConvertFromString("#E8EDF4");
        private static readonly Color _inactiveBorder = (Color)ColorConverter.ConvertFromString("#B0B8C4");

        public ShiftCloseDialog()
        {
            InitializeComponent();
            Loaded += (s, e) => { btnClose.Focus(); SetSelected(btnClose); };
        }

        private void SetSelected(Border active)
        {
            var act   = new SolidColorBrush(_activeBlue);
            var actBg = new SolidColorBrush(_activeBg);
            var inact = new SolidColorBrush(_inactiveBorder);
            var white = new SolidColorBrush(Colors.White);

            bool closeActive = active == btnClose;
            btnClose.BorderBrush     = closeActive ? act   : inact;
            btnClose.BorderThickness = closeActive ? new Thickness(2) : new Thickness(1);
            btnClose.Background      = closeActive ? actBg : white;
        }

        private void Btn_GotFocus(object sender, KeyboardFocusChangedEventArgs e)
            => SetSelected((Border)sender);
        private void Btn_LostFocus(object sender, KeyboardFocusChangedEventArgs e) { }

        private void BtnCloseShift_Click(object sender, MouseButtonEventArgs e)
            { Result = ShiftAction.CloseShift; Close(); }

        private void BtnCancel_Click(object sender, MouseButtonEventArgs e)
            { Result = ShiftAction.None; Close(); }

        private void Window_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            switch (e.Key)
            {
                case Key.Enter:
                    e.Handled = true;
                    Result = ShiftAction.CloseShift;
                    Close();
                    break;
                case Key.Escape:
                    e.Handled = true;
                    Result = ShiftAction.None;
                    Close();
                    break;
            }
        }
    }
}
