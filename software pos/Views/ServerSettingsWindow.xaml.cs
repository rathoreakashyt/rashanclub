using System.Windows;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views
{
    public partial class ServerSettingsWindow : Window
    {
        private readonly ApiService _api;
        private readonly SyncService? _sync;

        public ServerSettingsWindow(ApiService api, SyncService? sync = null)
        {
            InitializeComponent();
            _api  = api;
            _sync = sync;
            txtServerUrl.Text = _api.BaseUrl;
            txtEmail.Text = SecureSettingsService.DecryptAndGet(_api.Db, "server_email") ?? "";
            txtPassword.Password = SecureSettingsService.DecryptAndGet(_api.Db, "server_password") ?? "";
        }

        private async void BtnTest_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(txtEmail.Text) || txtPassword.Password.Length == 0)
            {
                ShowStatus("Enter email and password first.", false);
                return;
            }

            btnTest.IsEnabled = false;
            btnTest.Content = "Testing...";
            try
            {
                var (ok, message) = await _api.TestLoginAsync(txtServerUrl.Text.Trim(), txtEmail.Text.Trim(), txtPassword.Password);
                ShowStatus(ok ? "Connected successfully." : "Failed: " + message, ok);
            }
            catch (System.Exception ex)
            {
                ShowStatus("Failed: " + ex.Message, false);
            }
            finally
            {
                btnTest.IsEnabled = true;
                btnTest.Content = "Test Connection";
            }
        }

        private async void BtnSave_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(txtEmail.Text) || txtPassword.Password.Length == 0)
            {
                ShowStatus("Enter email and password.", false);
                return;
            }

            btnSave.IsEnabled = false;
            btnSave.Content = "Saving...";
            try
            {
                _api.SetCredentials(txtServerUrl.Text.Trim(), txtEmail.Text.Trim(), txtPassword.Password);
                var (ok, message, _) = await _api.LoginAsync(txtEmail.Text.Trim(), txtPassword.Password);
                if (!ok)
                {
                    ShowStatus("Login failed: " + message, false);
                    btnSave.IsEnabled = true;
                    btnSave.Content = "Save & Sync";
                    return;
                }
                DialogResult = true;
                Close();
            }
            catch (System.Exception ex)
            {
                ShowStatus("Failed: " + ex.Message, false);
                btnSave.IsEnabled = true;
                btnSave.Content = "Save & Sync";
            }
        }

        private void ShowStatus(string text, bool ok)
        {
            lblStatus.Text = text;
            lblStatus.Foreground = ok
                ? new System.Windows.Media.SolidColorBrush(System.Windows.Media.Color.FromRgb(0x16, 0xA3, 0x4A))
                : new System.Windows.Media.SolidColorBrush(System.Windows.Media.Color.FromRgb(0xDC, 0x26, 0x26));
            lblStatus.Visibility = Visibility.Visible;
        }

        // ─── BusyNotify Import ────────────────────────────────────────

        private async void BtnBusyImport_Click(object sender, RoutedEventArgs e)
        {
            if (_api.Token == null)
            {
                ShowBusyResult("Pehle server se connect karo (Save & Sync).", ok: false);
                return;
            }

            if (_sync == null)
            {
                ShowBusyResult("Sync service available nahi. App restart karo.", ok: false);
                return;
            }

            btnBusyImport.IsEnabled = false;
            btnBusyImport.Content = "⏳  Import chal raha hai...";
            lblBusyProgress.Visibility = Visibility.Visible;
            lblBusyResult.Visibility = Visibility.Collapsed;

            try
            {
                var service = new BusyImportService(_api, _sync);
                service.ProgressChanged += msg =>
                {
                    Dispatcher.Invoke(() => lblBusyProgress.Text = msg);
                };

                var result = await service.ImportAllAsync();

                ShowBusyResult(result.Message, result.Success);
            }
            catch (System.Exception ex)
            {
                ShowBusyResult("Error: " + ex.Message, ok: false);
            }
            finally
            {
                btnBusyImport.IsEnabled = true;
                btnBusyImport.Content = "🔄  Import from BUSY Accounting";
                lblBusyProgress.Visibility = Visibility.Collapsed;
            }
        }

        private void ShowBusyResult(string text, bool ok)
        {
            lblBusyResult.Text = text;
            lblBusyResult.Foreground = ok
                ? new System.Windows.Media.SolidColorBrush(System.Windows.Media.Color.FromRgb(0x05, 0x96, 0x69))
                : new System.Windows.Media.SolidColorBrush(System.Windows.Media.Color.FromRgb(0xDC, 0x26, 0x26));
            lblBusyResult.Visibility = Visibility.Visible;
        }
    }
}
