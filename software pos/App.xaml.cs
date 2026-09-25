using System.Windows;
using System.Windows.Threading;
using QuestPDF.Infrastructure;
using RashanKiDukan.Database;
using RashanKiDukan.Models;
using RashanKiDukan.Services;
using RashanKiDukan.Views;

namespace RashanKiDukan
{
    public partial class App : Application
    {
        protected override void OnStartup(StartupEventArgs e)
        {
            base.OnStartup(e);

            // ═══ ENTERPRISE: Global error handling ═══
            // UI thread exceptions
            DispatcherUnhandledException += OnDispatcherUnhandledException;
            // Non-UI thread exceptions
            AppDomain.CurrentDomain.UnhandledException += OnUnhandledException;
            // Task exceptions that were never observed
            TaskScheduler.UnobservedTaskException += OnUnobservedTaskException;

            // QuestPDF license — application startup par set karna zaroori hai.
            QuestPDF.Settings.License = LicenseType.Community;

            LogService.Info("═══ Application starting ═══");
            LogService.Info($"Version: {UpdateService.GetCurrentVersion()}");
            LogService.Info($"Machine: {Environment.MachineName}");
            LogService.Info($"OS: {Environment.OSVersion}");

            try
            {
                // 1. Initialize database (WAL mode, indexes, migrations)
                var db = new DatabaseService();
                db.Initialize();
                LogService.Info("Database initialized successfully");

                // 2. Initialize authentication
                var auth = new AuthService(db);
                var user = auth.GetSavedSession();

                if (user != null)
                {
                    // Session exists — direct dashboard
                    LogService.Info($"User authenticated: {user.Username} ({user.Role})");
                    var dashboard = new MainDashboard(user);
                    dashboard.Show();
                    // ═══ AUTO-UPDATE CHECK (non-blocking, after dashboard shows) ═══
                    _ = CheckForUpdateInBackground();
                }
                else
                {
                    // No session (fresh install or logout) — check if any user exists
                    // If fresh install (no users at all), auto-login as admin
                    // If users exist but session cleared (logout), show login screen
                    bool hasUsers = HasExistingUsers(db);
                    if (!hasUsers)
                    {
                        // Fresh install — create default admin and auto-login
                        var defaultUser = auth.AutoLogin();
                        auth.SaveSession(defaultUser.Id);
                        LogService.Info($"Fresh install — auto-login as: {defaultUser.Username}");
                        var dashboard = new MainDashboard(defaultUser);
                        dashboard.Show();
                        // ═══ AUTO-UPDATE CHECK (non-blocking, after dashboard shows) ═══
                        _ = CheckForUpdateInBackground();
                    }
                    else
                    {
                        // Users exist but session cleared — try auto-login with saved server credentials
                        var savedEmail = SecureSettingsService.DecryptAndGet(db, "server_email");
                        var savedPassword = SecureSettingsService.DecryptAndGet(db, "server_password");

                        if (!string.IsNullOrEmpty(savedEmail) && !string.IsNullOrEmpty(savedPassword))
                        {
                            LogService.Info("Attempting auto-login with saved server credentials...");

                            // Show login window first (no freeze)
                            var loginWindow = new LoginWindow();
                            loginWindow.Show();

                            // Auto-login on background thread
                            _ = Task.Run(() =>
                            {
                                try
                                {
                                    var autoUser = auth.TryCloudLogin(savedEmail, savedPassword);
                                    if (autoUser != null)
                                    {
                                        Dispatcher.Invoke(() =>
                                        {
                                            auth.SaveSession(autoUser.Id);
                                            LogService.Info($"Auto-login successful: {autoUser.Username} ({autoUser.Role})");
                                            var dashboard = new MainDashboard(autoUser);
                                            dashboard.Show();
                                            loginWindow.Close();
                                        });
                                    }
                                }
                                catch (Exception ex)
                                {
                                    LogService.Warn($"Auto-login failed: {ex.Message}");
                                }
                            });
                        }
                        else
                        {
                            // No saved credentials — show login screen
                            LogService.Info("No saved credentials — showing login window");
                            var login = new LoginWindow();
                            login.Show();
                        }
                    }
                }
                LogService.Info("Dashboard launched");
            }
            catch (Exception ex)
            {
                LogService.Error("FATAL: Startup failed", ex);
                MessageBox.Show($"Startup Error:\n{ex.Message}\n\nDetails logged to:\n%LocalAppData%\\RashanKiDukan\\logs\\",
                    "Rashan Ki Dukan - Fatal Error",
                    MessageBoxButton.OK, MessageBoxImage.Error);
                Shutdown(1);
            }
        }

        /// <summary>
        /// Check for update in background — OFFLINE-FIRST. If server unreachable,
        /// silently continues. Never blocks the POS.
        /// </summary>
        private async Task CheckForUpdateInBackground()
        {
            try
            {
                // Small delay so dashboard renders first
                await Task.Delay(3000);

                var updateService = new UpdateService();
                var updateInfo = await updateService.CheckForUpdateAsync();

                if (updateInfo == null)
                {
                    UpdateLogger.Log("Update check result: up-to-date (no popup)");
                    return; // No update or offline
                }

                LogService.Info($"Update available: v{updateInfo.Version}");
                UpdateLogger.Log($"Update available: current vs latest v{updateInfo.Version}");

                // ── STARTUP POPUP: update available hone par turant dialog dikhao ──
                await Dispatcher.InvokeAsync(() =>
                {
                    if (Application.Current.MainWindow is MainDashboard dash)
                    {
                        string currentVersion = UpdateService.GetCurrentVersion();
                        var dialog = new UpdateNotificationDialog { Owner = dash };
                        dialog.SetVersions(currentVersion, updateInfo);
                        var result = dialog.ShowDialog();
                        if (result != true || !dialog.UserClickedUpdate) return;

                        // Update Now → download (GitHub) → restart prompt
                        _ = RunUpdateFlowAsync(dash, updateInfo);
                    }
                });
            }
            catch (Exception ex)
            {
                // OFFLINE-FIRST: never crash on update failure
                LogService.Warn($"Update check background error: {ex.Message}");
            }
        }

        /// <summary>
        /// Update Now click ke baad ka flow: GitHub download → progress dialog →
        /// Restart & Update prompt → updater launch.
        /// </summary>
        private static async Task RunUpdateFlowAsync(MainDashboard dash, Services.UpdateInfo updateInfo)
        {
            try
            {
                var updateService = new UpdateService();
                var progressDialog = new UpdateProgressDialog { Owner = dash };
                progressDialog.SetVersion(updateInfo.Version);
                var cts = new System.Threading.CancellationTokenSource();
                progressDialog.SetCancellationTokenSource(cts);

                var progressReporter = new Progress<UpdateProgress>(p => progressDialog.UpdateProgress(p));

                progressDialog.Show();
                bool downloadComplete = await updateService.DownloadUpdateAsync(updateInfo, progressReporter, cts.Token);
                progressDialog.Close();

                if (!downloadComplete || progressDialog.UserCancelled)
                {
                    UpdateLogger.Log("Update flow: download incomplete ya cancel — app chalu hai, current version unchanged");
                    return;
                }

                // Restart prompt — user RESTART & UPDATE dabaye tabhi install hoga
                var readyDialog = new UpdateReadyDialog { Owner = dash };
                readyDialog.SetVersion(updateInfo.Version);
                var readyResult = readyDialog.ShowDialog();

                if (readyResult == true && readyDialog.UserClickedRestart)
                {
                    UpdateLogger.Log("User clicked Restart & Update — updater launch ho raha hai");
                    updateService.LaunchUpdater(updateInfo); // app khud shutdown karta hai
                }
                else
                {
                    UpdateLogger.Log("User ne restart postpone kiya — ZIP downloaded hai, agli baar download skip hoga");
                }
            }
            catch (Exception ex)
            {
                UpdateLogger.Error("Update flow failed", ex);
                System.Windows.MessageBox.Show(
                    $"Update could not be completed. Your current version has not been changed.\n\n{ex.Message}",
                    "Rashan Ki Dukan", System.Windows.MessageBoxButton.OK, System.Windows.MessageBoxImage.Warning);
            }
        }

        private static bool HasExistingUsers(Database.DatabaseService db)
        {
            try
            {
                using var conn = db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT COUNT(*) FROM (
                    SELECT 1 FROM Users WHERE IsActive=1
                    UNION ALL
                    SELECT 1 FROM employees WHERE COALESCE(del_status,'') <> 'delete' AND COALESCE(will_login,'') <> 'no'
                    LIMIT 1)";
                return (long)cmd.ExecuteScalar() > 0;
            }
            catch { return false; }
        }

        private void OnDispatcherUnhandledException(object sender, DispatcherUnhandledExceptionEventArgs e)
        {
            LogService.Error("Unhandled UI exception", e.Exception);
            MessageBox.Show($"Error: {e.Exception.Message}",
                "Rashan Ki Dukan", MessageBoxButton.OK, MessageBoxImage.Error);
            e.Handled = true; // Prevent crash — keep app running
        }

        private void OnUnhandledException(object sender, UnhandledExceptionEventArgs e)
        {
            if (e.ExceptionObject is Exception ex)
            {
                LogService.Error($"FATAL: Unhandled exception (IsTerminating={e.IsTerminating})", ex);
            }
        }

        private void OnUnobservedTaskException(object? sender, UnobservedTaskExceptionEventArgs e)
        {
            LogService.Error("Unobserved task exception", e.Exception);
            e.SetObserved(); // Prevent process termination
        }

        protected override void OnExit(ExitEventArgs e)
        {
            LogService.Info($"═══ Application exiting (code: {e.ApplicationExitCode}) ═══");
            base.OnExit(e);
        }
    }
}
