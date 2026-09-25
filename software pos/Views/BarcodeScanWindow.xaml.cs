using System;
using System.Windows;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Threading;

namespace RashanKiDukan.Views
{
    public partial class BarcodeScanWindow : Window
    {
        private readonly Func<string, string> _tryAdd;

        // Auto-capture timer — scanner ke characters ek burst mein aate hain
        // Agar 120ms tak koi naya character na aaye → auto-submit
        private readonly DispatcherTimer _autoTimer;
        private const int AutoCaptureMs = 120;

        // Feedback hide timer
        private readonly DispatcherTimer _feedbackTimer;

        // Indicator blink timer
        private readonly DispatcherTimer _blinkTimer;
        private bool _blinkState = true;

        public BarcodeScanWindow(Func<string, string> tryAdd)
        {
            _tryAdd = tryAdd;
            InitializeComponent();

            _autoTimer = new DispatcherTimer { Interval = TimeSpan.FromMilliseconds(AutoCaptureMs) };
            _autoTimer.Tick += (_, _) => { _autoTimer.Stop(); ProcessBarcode(); };

            _feedbackTimer = new DispatcherTimer { Interval = TimeSpan.FromSeconds(2) };
            _feedbackTimer.Tick += (_, _) => { _feedbackTimer.Stop(); feedbackBar.Visibility = Visibility.Collapsed; };

            // Green dot blink — scanner "alive" feel
            _blinkTimer = new DispatcherTimer { Interval = TimeSpan.FromMilliseconds(800) };
            _blinkTimer.Tick += (_, _) =>
            {
                _blinkState = !_blinkState;
                indicatorDot.Background = new SolidColorBrush(_blinkState
                    ? (Color)ColorConverter.ConvertFromString("#22C55E")
                    : (Color)ColorConverter.ConvertFromString("#86EFAC"));
            };

            Loaded += (s, e) =>
            {
                txtScan.Focus();
                _blinkTimer.Start();
            };
            Closed += (s, e) => { _blinkTimer.Stop(); _autoTimer.Stop(); _feedbackTimer.Stop(); };
        }

        // Har character aane par timer restart — burst khatam hone par auto-submit
        private void TxtScan_TextChanged(object sender, System.Windows.Controls.TextChangedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(txtScan.Text)) return;
            _autoTimer.Stop();
            _autoTimer.Start();

            // Scanning indicator — dot red ho jaaye
            indicatorDot.Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#EF4444"));
            lblScanState.Text = "Scanning...";
        }

        private void TxtScan_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter)
            {
                e.Handled = true;
                _autoTimer.Stop();
                ProcessBarcode();
            }
            else if (e.Key == Key.Escape)
            {
                e.Handled = true;
                Close();
            }
        }

        private void ProcessBarcode()
        {
            string code = txtScan.Text.Trim();
            txtScan.Clear();
            txtScan.Focus();

            // Reset indicator to green
            indicatorDot.Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#22C55E"));
            lblScanState.Text = "Point scanner at barcode and pull trigger";

            if (string.IsNullOrEmpty(code)) return;

            string result = _tryAdd(code);

            _feedbackTimer.Stop();
            feedbackBar.Visibility = Visibility.Visible;

            if (!string.IsNullOrEmpty(result))
            {
                feedbackBar.Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#F0FDF4"));
                lblScanFeedback.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#16A34A"));
                lblScanFeedback.Text = "✓  " + result + " — added to cart";
            }
            else
            {
                feedbackBar.Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#FEF2F2"));
                lblScanFeedback.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#DC2626"));
                lblScanFeedback.Text = "✗  Not found: " + code;
            }

            _feedbackTimer.Start();
        }

        private void BtnClose_Click(object sender, MouseButtonEventArgs e) => Close();
    }
}
