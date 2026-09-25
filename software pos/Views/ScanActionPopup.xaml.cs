using System.Windows;
using System.Windows.Input;
using System.Windows.Media;

namespace RashanKiDukan.Views
{
    public enum ScanAction { ScanBarcode, AddItem, Cancel }

    public partial class ScanActionPopup : Window
    {
        public ScanAction Result { get; private set; } = ScanAction.Cancel;

        private static readonly Color _activeBlue  = (Color)ColorConverter.ConvertFromString("#1C3F7A");
        private static readonly Color _activeBg    = (Color)ColorConverter.ConvertFromString("#E8EDF4");
        private static readonly Color _inactiveB   = (Color)ColorConverter.ConvertFromString("#B0B8C4");

        public ScanActionPopup()
        {
            InitializeComponent();
            Loaded += (s, e) => { btnScan.Focus(); SetSelected(btnScan); };
        }

        private void SetSelected(System.Windows.Controls.Border active)
        {
            // Active button — filled blue border + light blue bg
            var act = new SolidColorBrush(_activeBlue);
            var actBg = new SolidColorBrush(_activeBg);
            var inact = new SolidColorBrush(_inactiveB);
            var white = new SolidColorBrush(Colors.White);

            bool scanActive = active == btnScan;

            btnScan.BorderBrush      = scanActive ? act   : inact;
            btnScan.BorderThickness  = scanActive ? new Thickness(2) : new Thickness(1);
            btnScan.Background       = scanActive ? actBg : white;

            btnAddItem.BorderBrush     = !scanActive ? act   : inact;
            btnAddItem.BorderThickness = !scanActive ? new Thickness(2) : new Thickness(1);
            btnAddItem.Background      = !scanActive ? actBg : white;
        }

        // Focus handlers
        private void BtnScan_GotKeyboardFocus(object sender, KeyboardFocusChangedEventArgs e)    => SetSelected(btnScan);
        private void BtnAddItem_GotKeyboardFocus(object sender, KeyboardFocusChangedEventArgs e) => SetSelected(btnAddItem);
        private void BtnScan_LostKeyboardFocus(object sender, KeyboardFocusChangedEventArgs e)    { /* keep highlight via GotFocus of other */ }
        private void BtnAddItem_LostKeyboardFocus(object sender, KeyboardFocusChangedEventArgs e) { }

        // Click handlers
        private void BtnScan_Click(object sender, MouseButtonEventArgs e)    { Result = ScanAction.ScanBarcode; Close(); }
        private void BtnAddItem_Click(object sender, MouseButtonEventArgs e) { Result = ScanAction.AddItem;    Close(); }
        private void BtnCancel_Click(object sender, MouseButtonEventArgs e)  { Result = ScanAction.Cancel;     Close(); }

        private void Window_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            switch (e.Key)
            {
                case Key.Tab:
                case Key.Right:
                case Key.Down:
                    e.Handled = true;
                    if (btnScan.IsFocused) btnAddItem.Focus(); else btnScan.Focus();
                    break;
                case Key.Left:
                case Key.Up:
                    e.Handled = true;
                    if (btnAddItem.IsFocused) btnScan.Focus(); else btnAddItem.Focus();
                    break;
                case Key.Enter:
                    e.Handled = true;
                    Result = btnAddItem.IsFocused ? ScanAction.AddItem : ScanAction.ScanBarcode;
                    Close();
                    break;
                case Key.Escape:
                    e.Handled = true;
                    Result = ScanAction.Cancel;
                    Close();
                    break;
            }
        }
    }
}
