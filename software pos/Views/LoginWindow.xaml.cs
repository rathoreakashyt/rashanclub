using System;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Input;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views
{
    public partial class LoginWindow : Window
    {
        private AuthService _authService;
        private readonly DatabaseService _db;
        private int _loginAttempts = 0;

        public LoginWindow()
        {
            InitializeComponent();
            _db = new DatabaseService();
            _authService = new AuthService(_db);
            txtUsername.Focus();
        }

        private void BtnMinimize_Click(object sender, MouseButtonEventArgs e)
            => WindowState = WindowState.Minimized;

        private void BtnClose_Click(object sender, MouseButtonEventArgs e)
            => Close();

        private void Header_MouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            if (e.ClickCount == 1) DragMove();
        }

        private void BtnLogin_Click(object sender, RoutedEventArgs e)
        {
            _ = LoginAsync();
        }

        private void OnPasswordKeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter) _ = LoginAsync();
        }

        private async Task LoginAsync()
        {
            string username = txtUsername.Text.Trim();
            string password = txtPassword.Password;

            if (string.IsNullOrEmpty(username) || string.IsNullOrEmpty(password))
            {
                ShowError("Please enter username and password");
                return;
            }

            btnLogin.IsEnabled = false;
            btnLogin.Content = "Signing in...";
            lblError.Visibility = Visibility.Collapsed;

            try
            {
                var user = await Task.Run(() => _authService.Login(username, password));
                _authService.SaveSession(user.Id);
                var dashboard = new MainDashboard(user);
                dashboard.Show();
                Close();
            }
            catch (UnauthorizedAccessException ex)
            {
                _loginAttempts++;
                ShowError(ex.Message);
                txtPassword.Password = "";
                txtPassword.Focus();
            }
            catch (Exception ex)
            {
                ShowError($"Connection error: {ex.Message}");
            }
            finally
            {
                btnLogin.IsEnabled = true;
                btnLogin.Content = "Sign In";
            }
        }

        private void ShowError(string message)
        {
            lblError.Text = message;
            lblError.Visibility = string.IsNullOrEmpty(message) ? Visibility.Collapsed : Visibility.Visible;
        }

        private void BtnServerSettings_Click(object sender, RoutedEventArgs e)
        {
            var api = new ApiService();
            var dlg = new ServerSettingsWindow(api);
            dlg.Owner = this;
            if (dlg.ShowDialog() == true)
            {
                _db.Initialize();
                _authService = new AuthService(_db);
                ShowError("");
            }
        }
    }
}
