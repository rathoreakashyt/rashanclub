using System.Runtime.InteropServices;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Interop;
using System.Windows.Media;
using System.Windows.Threading;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;
using RashanKiDukan.Models;
using RashanKiDukan.Services;
using System.Net.NetworkInformation;
using System.Diagnostics;
using System.IO.Ports;
using System.Management;

namespace RashanKiDukan.Views
{
    public partial class MainDashboard : Window
    {
        private readonly User _currentUser;
        private readonly DatabaseService _db;
        private readonly ApiService _api = new();
        private readonly SyncService _sync;
        private string _lastOpenMenu = "";
        private POSPage? _posPage;
        private DateTime _lastStatsLoad = DateTime.MinValue;
        private bool _statsLoading;
        private bool _deviceScanning;
        private IntPtr _kbdHook = IntPtr.Zero;
        private LowLevelKeyboardProc? _hookProc;
        private const int WH_KEYBOARD_LL = 13;
        private const int WM_KEYDOWN = 0x0100;
        private const int WM_SYSKEYDOWN = 0x0104;
        private const uint VK_F10 = 0x79;
        private readonly DispatcherTimer _deviceTimer;

        public User CurrentUser => _currentUser;

        public MainDashboard(User user)
        {
            InitializeComponent();
            _currentUser = user;
            _db = new DatabaseService();

            // ═══ ENTERPRISE: Role-based — hide dashboard immediately for POS-only users ═══
            string userRole = (_currentUser.Role ?? "").Trim();
            bool isPosOnlyUser = userRole.Equals("Cashier", StringComparison.OrdinalIgnoreCase) ||
                                 userRole.Equals("Salesman", StringComparison.OrdinalIgnoreCase);
            if (isPosOnlyUser)
            {
                dashboardView.Visibility = Visibility.Collapsed;
                sidebarBorder.Visibility = Visibility.Collapsed;
            }

            LoadStats();
            BuildMenu();
            ApplyFeatureActivation();
            // Stock update kahin bhi hua to dashboard stats (stock value / low stock) refresh
            Services.StockEvents.StockChanged += OnStockChanged;

            _sync = new SyncService(_api);
            _sync.StatusChanged += OnSyncStatusChanged;
            _sync.StartAutoSync();
            UpdateSyncUi(connected: false, message: _api.Token != null ? "Starting sync..." : "Not configured — open Settings → Server Sync");

            // Device detection timer — check every 8 seconds
            _deviceTimer = new DispatcherTimer { Interval = TimeSpan.FromSeconds(8) };
            _deviceTimer.Tick += (_, _) => DetectDevices();
            _deviceTimer.Start();
            DetectDevices(); // Initial check

            // App start: sidebar menu pe focus — Up/Down/Enter se turant navigate karo
            Dispatcher.BeginInvoke(new Action(FocusMenuFirst), DispatcherPriority.Input);

            // ═══ ENTERPRISE: Role-based redirect — Cashier/Salesman → Direct POS ═══
            if (isPosOnlyUser)
            {
                Dispatcher.BeginInvoke(new Action(() =>
                {
                    EnsurePos();
                }), DispatcherPriority.Loaded);
            }
        }

        private void OnSyncStatusChanged(SyncStatusInfo s)
        {
            try
            {
                if (s.Syncing)
                {
                    UpdateSyncUi(false, "Syncing...", syncing: true, deadLetters: s.DeadLetters);
                    return;
                }
                UpdateSyncUi(s.Connected, s.Message, syncing: false, lastSync: s.LastSyncAt, deadLetters: s.DeadLetters);
                if (s.Connected)
                {
                    // ═══ CLOUD→DESKTOP LIVE REFRESH ═══
                    // Web/cloud se kuch bhi aaya (delete/edit) to current open page
                    // khud reload — bina manual refresh ke. Sirf ISyncRefreshable pages.
                    if (s.Pulled > 0)
                    {
                        Dispatcher.BeginInvoke(new Action(() =>
                        {
                            try
                            {
                                if (mainContent.Content is ISyncRefreshable page)
                                    page.OnSyncPulled();
                            }
                            catch { }
                        }), System.Windows.Threading.DispatcherPriority.Background);
                    }
                    // Re-apply feature activation after sync (features may have changed from cloud)
                    Dispatcher.BeginInvoke(new Action(() =>
                    {
                        try { ApplyFeatureActivation(); } catch { }
                    }), System.Windows.Threading.DispatcherPriority.Background);
                    // "Up to date" = nothing changed on this tick — don't re-run the ~24 stats queries
                    if (!string.Equals(s.Message, "Up to date", StringComparison.OrdinalIgnoreCase))
                        LoadStats();
                }
            }
            catch { }
        }

        private void UpdateSyncUi(bool connected, string message, bool syncing = false, DateTime? lastSync = null, int deadLetters = 0)
        {
            try
            {
                // FIX 4: stuck records ka alert pill — count > 0 par red badge
                if (deadLetters > 0)
                {
                    pillDeadLetters.Visibility = Visibility.Visible;
                    lblDeadLetters.Text = deadLetters.ToString();
                    pillDeadLetters.ToolTip = $"{deadLetters} sync record(s) atke hue hain — click karke dekho";
                }
                else
                {
                    pillDeadLetters.Visibility = Visibility.Collapsed;
                }

                if (syncing)
                {
                    syncDot.Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#F59E0B"));
                    lblSyncStatus.Text = "Syncing...";
                    lblSyncStatus.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#F59E0B"));
                    btnResync.IsEnabled = false;
                    resyncIcon.RenderTransform = new RotateTransform(0);
                    return;
                }

                if (connected)
                {
                    syncDot.Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#10B981"));
                    lblSyncStatus.Text = "Synced";
                    lblSyncStatus.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#10B981"));
                }
                else
                {
                    syncDot.Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#EF4444"));
                    lblSyncStatus.Text = "Sync Issue";
                    lblSyncStatus.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#EF4444"));
                }

                btnResync.IsEnabled = true;

                // Show error detail if present (clean, no HTML)
                if (!connected && !string.IsNullOrEmpty(message))
                {
                    // Strip HTML tags and show only short meaningful message
                    string clean = System.Text.RegularExpressions.Regex.Replace(message, "<[^>]+>", "").Trim();
                    if (clean.Contains("502") || clean.Contains("Bad Gateway"))
                        clean = "Server unavailable — will retry";
                    else if (clean.Contains("503") || clean.Contains("Service Unavailable"))
                        clean = "Server maintenance — will retry";
                    else if (clean.Contains("timeout") || clean.Contains("Timeout"))
                        clean = "Connection timeout — will retry";
                    else if (clean.Contains("No such host") || clean.Contains("network"))
                        clean = "No internet — will retry when online";
                    else if (clean.Length > 60)
                        clean = clean.Substring(0, 57) + "...";
                    lblSyncDetail.Text = clean;
                }
                else
                    lblSyncDetail.Text = "";

                // Show last sync time
                if (lastSync.HasValue)
                    lblSyncTime.Text = "Last: " + lastSync.Value.ToString("HH:mm:ss");
                else if (!string.IsNullOrEmpty(message) && connected)
                    lblSyncTime.Text = message;
                else
                    lblSyncTime.Text = "";
            }
            catch { }
        }

        /// <summary>
        /// Startup silent check se aata hai — update available hone par corner icon
        /// par red badge dikhata hai (popup nahi). User icon click kare tab hi update flow.
        /// </summary>
        public void NotifyUpdateAvailable(UpdateInfo info)
        {
            try
            {
                if (updateBadge != null)
                {
                    updateBadge.Visibility = Visibility.Visible;
                    btnUpdateCheck.ToolTip = $"Update available: v{info.Version} — click karke update karo";
                }
            }
            catch { }
        }

        /// <summary>
        /// Top-right update icon click — current version + available update check + update flow.
        /// </summary>
        private async void BtnUpdateCheck_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                btnUpdateCheck.IsEnabled = false;
                string currentVersion = UpdateService.GetCurrentVersion();
                UpdateLogger.Log($"Manual update check (icon) — current version: {currentVersion}");

                // Check background me (network call — UI block na ho)
                var updateInfo = await Task.Run(() =>
                {
                    var svc = new UpdateService();
                    return svc.CheckForUpdateAsync().GetAwaiter().GetResult();
                });

                if (updateInfo == null)
                {
                    UpdateLogger.Log("Icon check result: software up-to-date");
                    MessageBox.Show(
                        $"Current Version: v{currentVersion}\n\n" +
                        $"✅ Aapka software up-to-date hai.\n" +
                        $"Koi naya update available nahi hai.",
                        "Rashan Ki Dukan — Update Check",
                        MessageBoxButton.OK, MessageBoxImage.Information);
                    return;
                }

                // Update available — notification dialog (App.xaml.cs wala hi flow)
                bool accepted = false;
                var dialog = new UpdateNotificationDialog { Owner = this };
                dialog.SetVersions(currentVersion, updateInfo);
                accepted = dialog.ShowDialog() == true && dialog.UserClickedUpdate;

                if (!accepted) return;

                // Download + install — UpdateProgressDialog (real API: SetCancellationTokenSource/UpdateProgress)
                var progressDialog = new UpdateProgressDialog { Owner = this };
                progressDialog.SetVersion(updateInfo.Version);
                var cts = new CancellationTokenSource();
                progressDialog.SetCancellationTokenSource(cts);
                progressDialog.Show();
                _ = Task.Run(async () =>
                {
                    try
                    {
                        var svc = new UpdateService();
                        var progress = new Progress<UpdateProgress>(p =>
                            Dispatcher.Invoke(() => progressDialog.UpdateProgress(p)));
                        bool ok = await svc.DownloadUpdateAsync(updateInfo, progress, cts.Token);
                        Dispatcher.Invoke(() =>
                        {
                            if (ok) updateBadge.Visibility = Visibility.Collapsed;

                            if (!ok)
                            {
                                progressDialog.Close();
                                UpdateLogger.Log("Icon flow: download fail/cancel — current version unchanged");
                                return;
                            }

                            // Download 100% + SHA verified — controlled restart flow dikhao
                            progressDialog.Close();
                            var readyDialog = new UpdateReadyDialog { Owner = this };
                            readyDialog.SetVersion(updateInfo.Version);
                            var ready = readyDialog.ShowDialog();

                            if (ready == true && readyDialog.UserClickedRestart)
                            {
                                UpdateLogger.Log("User clicked Restart & Update (icon flow) — updater launch");
                                new UpdateService().LaunchUpdater(updateInfo); // app khud shutdown karta hai
                            }
                            else
                            {
                                UpdateLogger.Log("Icon flow: restart postpone — ZIP cached hai, agli baar re-download nahi hoga");
                            }
                        });
                    }
                    catch (Exception ex)
                    {
                        UpdateLogger.Error("Icon update flow failed", ex);
                        Dispatcher.Invoke(() =>
                            MessageBox.Show($"Update could not be completed. Your current version has not been changed.\n\n{ex.Message}", "Error",
                                MessageBoxButton.OK, MessageBoxImage.Error));
                    }
                });
            }
            catch (Exception ex)
            {
                MessageBox.Show($"Update check fail: {ex.Message}", "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
            finally
            {
                btnUpdateCheck.IsEnabled = true;
            }
        }

        private async void BtnResync_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                btnResync.IsEnabled = false;
                resyncIcon.RenderTransform = new RotateTransform(0);
                var anim = new System.Windows.Media.Animation.DoubleAnimation(0, 360, TimeSpan.FromSeconds(1));
                resyncIcon.RenderTransform.BeginAnimation(RotateTransform.AngleProperty, anim);
                await _sync.SyncNowAsync(forcePull: true);
            }
            catch { }
            finally
            {
                btnResync.IsEnabled = true;
            }
        }

        private void PillDeadLetters_Click(object sender, System.Windows.Input.MouseButtonEventArgs e)
        {
            try
            {
                var dialog = new DeadLettersDialog(_api, _sync) { Owner = this };
                dialog.ShowDialog();
            }
            catch (Exception ex)
            {
                DebugLog($"PillDeadLetters_Click ERR: {ex.GetType().Name}: {ex.Message}\n{ex.StackTrace}");
                MessageBox.Show("Error: " + ex.Message, "Sync Monitor",
                    MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void SyncStatus_Click(object sender, System.Windows.Input.MouseButtonEventArgs e)
        {
            try
            {
                var dialog = new SyncStatusDialog(_api, _sync) { Owner = this };
                dialog.ShowDialog();
            }
            catch (Exception ex)
            {
                DebugLog($"SyncStatus_Click ERR: {ex.GetType().Name}: {ex.Message}\n{ex.StackTrace}");
                MessageBox.Show("Error: " + ex.Message, "Sync Status",
                    MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }





        /// <summary>
        /// TriggerSync kisi bhi UI action se call hoti hai (delete/edit/sale).
        /// Overlap handling SyncNowAsync ke andar hai — busy sync par request
        /// drop nahi hoti, agle cycle ke liye queue ho jati hai.
        /// </summary>
        public async Task TriggerSync()
        {
            await _sync.SyncNowAsync(forcePull: true);
        }

        protected override void OnSourceInitialized(EventArgs e)
        {
            base.OnSourceInitialized(e);
            _hookProc = HookCallback;
            _kbdHook = SetWindowsHookEx(WH_KEYBOARD_LL, _hookProc, IntPtr.Zero, 0);
            DebugLog($"hook install: handle={_kbdHook} err={Marshal.GetLastWin32Error()}");
        }

        private static void DebugLog(string msg)
        {
            try
            {
                System.IO.File.AppendAllText(
                    System.IO.Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "RashanKiDukan", "f10_debug.log"),
                    $"{DateTime.Now:HH:mm:ss.fff} {msg}{Environment.NewLine}");
            }
            catch { }
        }

        protected override void OnClosed(EventArgs e)
        {
            if (_kbdHook != IntPtr.Zero)
            {
                UnhookWindowsHookEx(_kbdHook);
                _kbdHook = IntPtr.Zero;
            }
            _sync.StopAutoSync();
            _deviceTimer.Stop();
            _posPage?.Shutdown();
            _posPage = null;
            base.OnClosed(e);
        }

        // ═══════════ DEVICE DETECTION ═══════════
        private void DetectDevices()
        {
            // Ping (2s timeout) + WMI + COM port probing can block the caller — run all
            // detection on a background thread and marshal UI updates back in one shot.
            if (_deviceScanning) return;
            _deviceScanning = true;
            _ = Task.Run(() =>
            {
                bool internet = false, printer = false, scanner = false;
                try { internet = CheckInternet(); } catch { }
                try { printer = CheckPrinter(); } catch { }
                try { scanner = CheckScanner(); } catch { }
                Dispatcher.BeginInvoke(new Action(() =>
                {
                    try { ApplyDeviceStatus(internet, printer, scanner); }
                    catch { }
                    finally { _deviceScanning = false; }
                }));
            });
        }

        private static bool CheckInternet()
        {
            bool connected = false;
            try
            {
                using var ping = new Ping();
                var reply = ping.Send("8.8.8.8", 2000);
                connected = reply.Status == IPStatus.Success;
            }
            catch { connected = false; }

            // Fallback: check network interface
            if (!connected)
            {
                try { connected = NetworkInterface.GetIsNetworkAvailable(); } catch { }
            }
            return connected;
        }

        private static bool CheckPrinter()
        {
            bool found = false;
            try
            {
                // 1) Print Spooler service must be running — no spooler = no printer
                bool spoolerRunning = Process.GetProcessesByName("spoolsv").Length > 0;

                if (spoolerRunning)
                {
                    // 2) WMI — physical printers only (skip virtual/software printers)
                    using var searcher = new ManagementObjectSearcher("SELECT * FROM Win32_Printer");
                    foreach (ManagementObject obj in searcher.Get())
                    {
                        string name = obj["Name"]?.ToString() ?? "";
                        string port = obj["PortName"]?.ToString() ?? "";
                        bool workOffline = obj["WorkOffline"] is bool wo && wo;
                        uint status = obj["PrinterStatus"] is uint s ? s : 0;
                        uint printerState = obj["PrinterState"] is uint ps ? ps : 0;
                        bool local = obj["Local"] is bool l && l;

                        // Virtual/software printers ko skip — ye physical printer nahi hain
                        if (IsVirtualPrinter(name, port)) continue;

                        // Real printer = local, online (not work-offline), no error state
                        if (local && !workOffline && printerState == 0 &&
                            (status == 2 || status == 3 || status == 4 || status == 5))
                        {
                            found = true;
                            break;
                        }
                    }

                    // 3) USB printers directly attached — WMI query by port type
                    if (!found)
                    {
                        using var usb = new ManagementObjectSearcher(
                            "SELECT * FROM Win32_Printer WHERE PortName LIKE 'USB%' OR PortName LIKE 'LPT%' OR PortName LIKE 'COM%'");
                        foreach (ManagementObject obj in usb.Get())
                        {
                            string name = obj["Name"]?.ToString() ?? "";
                            bool workOffline = obj["WorkOffline"] is bool wo && wo;
                            if (!workOffline && !IsVirtualPrinter(name, obj["PortName"]?.ToString() ?? ""))
                            {
                                found = true;
                                break;
                            }
                        }
                    }

                    // 4) Network printers (TCP/IP) — check remote print queue is alive
                    if (!found)
                    {
                        using var net = new ManagementObjectSearcher(
                            "SELECT * FROM Win32_Printer WHERE PortName LIKE 'IP_%' OR PortName LIKE 'TCP%'");
                        foreach (ManagementObject obj in net.Get())
                        {
                            string name = obj["Name"]?.ToString() ?? "";
                            bool workOffline = obj["WorkOffline"] is bool wo && wo;
                            uint status = obj["PrinterStatus"] is uint s ? s : 0;
                            if (!workOffline && status != 1 && status != 0 &&
                                !IsVirtualPrinter(name, obj["PortName"]?.ToString() ?? ""))
                            {
                                found = true;
                                break;
                            }
                        }
                    }
                }
            }
            catch { found = false; }
            return found;
        }

        /// <summary>
        /// Virtual/software printers (Microsoft Print to PDF, Fax, XPS, etc.) ko
        /// filter karo — ye physical devices nahi hain, sirf software printers.
        /// </summary>
        private static bool IsVirtualPrinter(string name, string port)
        {
            string n = name.ToLowerInvariant();
            string p = port.ToLowerInvariant();

            // PORTPROMPT: = redirected virtual port (PDF/Fax/XPS sab yahi use karte hain)
            if (p.StartsWith("portprompt")) return true;

            string[] virtualKeywords =
            {
                "print to pdf", "microsoft print to pdf", "adobe pdf", "pdf", "xps document writer",
                "xps writer", "onenote", "send to onenote", "fax", "fax service",
                "microsoft software printer", "cute pdf", "pdf24", "foxit", "quicken",
                "snagit", "nuance", "microsoft print to pdf", "microsoft onecore",
                "image writer", "pdf architect"
            };
            foreach (var kw in virtualKeywords)
            {
                if (n.Contains(kw)) return true;
            }
            return false;
        }

        private static bool CheckScanner()
        {
            bool found = false;
            try
            {
                // Check for HID barcode scanner devices via WMI
                using var searcher = new ManagementObjectSearcher(
                    "SELECT * FROM Win32_USBControllerDevice");
                foreach (ManagementObject obj in searcher.Get())
                {
                    var dependent = obj["Dependent"]?.ToString() ?? "";
                    string desc = dependent.ToLower();

                    // Common scanner identifiers
                    if (desc.Contains("barcode") || desc.Contains("scanner") ||
                        desc.Contains("hid") && (desc.Contains("usb") || desc.Contains("input")) ||
                        desc.Contains("pos") || desc.Contains("citizen") ||
                        desc.Contains("zebra") || desc.Contains("honeywell") ||
                        desc.Contains("datalogic") || desc.Contains("wasp"))
                    {
                        found = true;
                        break;
                    }
                }

                // Fallback: check for active COM ports (serial scanners)
                if (!found)
                {
                    var ports = SerialPort.GetPortNames();
                    // Filter out standard COM ports that are usually Bluetooth/virtual
                    if (ports.Length > 0)
                    {
                        foreach (var port in ports)
                        {
                            try
                            {
                                using var sp = new SerialPort(port);
                                sp.Open();
                                sp.Close();
                                found = true;
                                break;
                            }
                            catch { /* port busy = scanner connected */ found = true; break; }
                        }
                    }
                }

                // Fallback: check for scanner-related processes
                if (!found)
                {
                    string[] scannerProcs = { "ScannerService", "OPOSScanner", "ScanStation" };
                    foreach (var p in scannerProcs)
                    {
                        if (Process.GetProcessesByName(p).Length > 0)
                        {
                            found = true;
                            break;
                        }
                    }
                }
            }
            catch { found = false; }
            return found;
        }

        private bool _lastInternet = false;
        private bool _lastPrinter = false;
        private bool _lastScanner = false;

        private void ApplyDeviceStatus(bool internet, bool printer, bool scanner)
        {
            if (internet != _lastInternet)
            {
                _lastInternet = internet;
                pillInternet.Background = new SolidColorBrush(internet
                    ? (Color)ColorConverter.ConvertFromString("#ECFDF5")
                    : (Color)ColorConverter.ConvertFromString("#FEF2F2"));
                pillInternet.BorderBrush = new SolidColorBrush(internet
                    ? (Color)ColorConverter.ConvertFromString("#10B981")
                    : (Color)ColorConverter.ConvertFromString("#EF4444"));
            }

            if (printer != _lastPrinter)
            {
                _lastPrinter = printer;
                pillPrinter.Background = new SolidColorBrush(printer
                    ? (Color)ColorConverter.ConvertFromString("#ECFDF5")
                    : (Color)ColorConverter.ConvertFromString("#FEF2F2"));
                pillPrinter.BorderBrush = new SolidColorBrush(printer
                    ? (Color)ColorConverter.ConvertFromString("#10B981")
                    : (Color)ColorConverter.ConvertFromString("#EF4444"));
            }

            if (scanner != _lastScanner)
            {
                _lastScanner = scanner;
                dotScanner.Foreground = new SolidColorBrush(scanner
                    ? (Color)ColorConverter.ConvertFromString("#10B981")
                    : (Color)ColorConverter.ConvertFromString("#EF4444"));
                pillScanner.Background = new SolidColorBrush(scanner
                    ? (Color)ColorConverter.ConvertFromString("#ECFDF5")
                    : (Color)ColorConverter.ConvertFromString("#FEF2F2"));
                pillScanner.BorderBrush = new SolidColorBrush(scanner
                    ? (Color)ColorConverter.ConvertFromString("#A7F3D0")
                    : (Color)ColorConverter.ConvertFromString("#FECACA"));
            }
        }

        // Kisi aur app ne F10 ko global hotkey bana rakha hai (RegisterHotKey fail karta hai,
        // isliye yahan low-level keyboard hook). Hook har F10 press ko system ke hotkey
        // processing se PEHLE pakadta hai — chahe hamara window focused ho ya na ho.
        private IntPtr HookCallback(int nCode, IntPtr wParam, IntPtr lParam)
        {
            if (nCode >= 0 && (wParam.ToInt32() == WM_KEYDOWN || wParam.ToInt32() == WM_SYSKEYDOWN))
            {
                int vk = Marshal.ReadInt32(lParam);
                DebugLog($"hook: vk=0x{vk:X2}");
                if (vk == VK_F10)
                {
                    // Register khula ho to F10 usi ke paas (refresh) — baaki dialogs ke
                    // upar bhi naya register khulega, taaki F10 hamesha kaam kare.
                    foreach (Window w in Application.Current.Windows)
                        if (w.IsVisible && w is RegisterDialog)
                            return CallNextHookEx(_kbdHook, nCode, wParam, lParam);

                    DebugLog("hook: opening register");
                    try
                    {
                        EnsurePos().OpenRegister();
                    }
                    catch (Exception ex)
                    {
                        DebugLog("hook: EXCEPTION " + ex.GetType().Name + ": " + ex.Message);
                    }
                    return new IntPtr(1); // key ko swallow karo — dusre app ke hotkey ko na mile
                }
            }
            return CallNextHookEx(_kbdHook, nCode, wParam, lParam);
        }

        private delegate IntPtr LowLevelKeyboardProc(int nCode, IntPtr wParam, IntPtr lParam);

        [DllImport("user32.dll", SetLastError = true)]
        private static extern IntPtr SetWindowsHookEx(int idHook, LowLevelKeyboardProc lpfn, IntPtr hMod, uint dwThreadId);

        [DllImport("user32.dll", SetLastError = true)]
        private static extern bool UnhookWindowsHookEx(IntPtr hhk);

        [DllImport("user32.dll")]
        private static extern IntPtr CallNextHookEx(IntPtr hhk, int nCode, IntPtr wParam, IntPtr lParam);

        // POS page nahi khula ho to pehle kholo, phir shortcut dispatch karo — F-keys
        // dashboard/login ke alawa har jagah se kaam karein.
        private POSPage EnsurePos()
        {
            // Reuse an existing POSPage instead of replacing content — this stops the
            // timer/instance leak and preserves cart state.
            if (_posPage != null)
            {
                sidebarBorder.Visibility = Visibility.Collapsed;
                sidebarCol.Width = new GridLength(0);
                SetPosNavMode();
                dashboardView.Visibility = Visibility.Collapsed;
                mainContent.Content = _posPage;
                syncBar.Visibility = Visibility.Collapsed;
                return _posPage;
            }
            if (mainContent.Content is POSPage pos) return pos;
            sidebarBorder.Visibility = Visibility.Collapsed;
            sidebarCol.Width = new GridLength(0);
            SetPosNavMode();
            var page = new POSPage(this, _currentUser);
            _posPage = page;
            dashboardView.Visibility = Visibility.Collapsed;
            mainContent.Content = page;
            syncBar.Visibility = Visibility.Collapsed;
            return page;
        }

        // F1-F10 hamesha kaam karein — focus kisi bhi element par ho, POS page dikh raha ho to
        // window-level PreviewKeyDown (tunneling) se dispatch hota hai. Modal dialogs (Sale
        // Return, Payment, Register...) ke khule hote hue keys unke paas jaati hain, yahan nahi.
        // Up/Down/Left/Right/Enter/Escape — sidebar menu aur content dono keyboard se chalein.
        private void MainWindow_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            bool isPosActive = mainContent.Content is POSPage;

            // Sidebar visible → sidebar menu navigation (non-POS pages only)
            if (!isPosActive && sidebarBorder.Visibility == Visibility.Visible)
            {
                if (HandleMenuKeys(e)) return;

                if (e.Key == Key.Escape && !IsEditing(Keyboard.FocusedElement as DependencyObject))
                {
                    e.Handled = true;
                    ShowDashboard();
                    return;
                }
            }

            // POS shortcuts F1-F10 + Ctrl+C — sirf jab POS page active ho
            if (isPosActive)
            {
                var page = mainContent.Content as POSPage;
                if (page != null)
                {
                    // Ctrl+C at window level to prevent TextBox stealing it for clipboard copy
                    if (e.Key == Key.C && (Keyboard.Modifiers & ModifierKeys.Control) == ModifierKeys.Control)
                    {
                        e.Handled = true;
                        page.OpenCheckScheme();
                        return;
                    }

                    switch (e.Key)
                    {
                        case Key.F1: e.Handled = true; page.OpenItemList(); break;
                        case Key.F2: e.Handled = true; page.OpenCustomerWindow(); break;
                        case Key.F3: e.Handled = true; page.OpenCalculator(); break;
                        case Key.F4: e.Handled = true; page.OpenPaymentWindow(); break;
                        case Key.F5: e.Handled = true; page.QuickCash(); break;
                        case Key.F6: e.Handled = true; page.OpenReturnWindow(); break;
                        case Key.F7: e.Handled = true; page.HoldCurrentBill(); break;
                        case Key.F8: e.Handled = true; page.RePrint(); break;
                        case Key.F9: e.Handled = true; page.SendLastBill(); break;
                        case Key.F10: e.Handled = true; page.OpenRegister(); break;
                    }
                }
            }
        }

        // ═══════════ SIDEBAR / MENU KEYBOARD NAVIGATION ═══════════

        private sealed class MenuNavItem
        {
            public Button Btn;
            public StackPanel? Sub;
            public Button? Parent;
            public MenuNavItem(Button btn, StackPanel? sub, Button? parent)
            {
                Btn = btn; Sub = sub; Parent = parent;
            }
        }

        private List<MenuNavItem> GetMenuButtons()
        {
            var list = new List<MenuNavItem>();
            try
            {
                foreach (var child in menuStack.Children)
                {
                    if (child is Button b)
                    {
                        if (b.IsVisible)
                            list.Add(new MenuNavItem(b, b.Tag as StackPanel, null));
                    }
                    else if (child is StackPanel sp && sp.IsVisible && sp.Tag is Button parentBtn)
                    {
                        foreach (var c in sp.Children)
                            if (c is Button sb && sb.IsVisible)
                                list.Add(new MenuNavItem(sb, null, parentBtn));
                    }
                }
            }
            catch { }
            return list;
        }

        private bool HandleMenuKeys(KeyEventArgs e)
        {
            var focused = Keyboard.FocusedElement as DependencyObject;
            bool inSidebar = focused != null && IsDescendantOf(focused, sidebarBorder);

            var buttons = GetMenuButtons();
            if (buttons.Count == 0) return false;

            int idx = inSidebar ? buttons.FindIndex(x => ReferenceEquals(x.Btn, focused)) : -1;

            switch (e.Key)
            {
                case Key.Down:
                    if (idx < 0) idx = 0; else idx = (idx + 1) % buttons.Count;
                    buttons[idx].Btn.Focus();
                    e.Handled = true;
                    return true;
                case Key.Up:
                    if (idx < 0) idx = buttons.Count - 1; else idx = (idx - 1 + buttons.Count) % buttons.Count;
                    buttons[idx].Btn.Focus();
                    e.Handled = true;
                    return true;
                case Key.Right:
                    if (idx >= 0 && buttons[idx].Sub != null)
                    {
                        if (buttons[idx].Sub.Visibility != Visibility.Visible)
                            buttons[idx].Btn.RaiseEvent(new RoutedEventArgs(Button.ClickEvent));
                        FocusFirstChild(buttons[idx].Sub);
                        e.Handled = true;
                        return true;
                    }
                    break;
                case Key.Left:
                    if (idx >= 0)
                    {
                        var it = buttons[idx];
                        if (it.Sub == null && it.Parent != null)
                        {
                            it.Parent.Focus();
                            e.Handled = true;
                            return true;
                        }
                        if (it.Sub != null && it.Sub.Visibility == Visibility.Visible)
                        {
                            it.Btn.RaiseEvent(new RoutedEventArgs(Button.ClickEvent));
                            it.Btn.Focus();
                            e.Handled = true;
                            return true;
                        }
                    }
                    else
                    {
                        // content se Left → sidebar menu
                        buttons[0].Btn.Focus();
                        e.Handled = true;
                        return true;
                    }
                    break;
                case Key.Escape:
                    if (inSidebar)
                    {
                        var open = buttons.FirstOrDefault(x => x.Sub != null && x.Sub.Visibility == Visibility.Visible);
                        if (open != null)
                        {
                            open.Btn.RaiseEvent(new RoutedEventArgs(Button.ClickEvent));
                            open.Btn.Focus();
                        }
                        else if (idx >= 0) buttons[idx].Btn.Focus();
                        else buttons[0].Btn.Focus();
                        e.Handled = true;
                        return true;
                    }
                    break;
            }
            return false;
        }

        private void FocusMenuFirst()
        {
            var b = GetMenuButtons();
            if (b.Count > 0) b[0].Btn.Focus();
        }

        private static void FocusFirstChild(StackPanel sp)
        {
            foreach (var c in sp.Children)
                if (c is Button sb && sb.IsVisible) { sb.Focus(); return; }
        }

        private static bool IsEditing(DependencyObject? el)
        {
            return el is TextBox || el is PasswordBox || el is ComboBox || el is RichTextBox
                || el is DataGrid || el is ListBox || el is DatePicker;
        }

        private static bool IsDescendantOf(DependencyObject? child, DependencyObject parent)
        {
            var d = child;
            while (d != null)
            {
                if (ReferenceEquals(d, parent)) return true;
                d = VisualTreeHelper.GetParent(d);
            }
            return false;
        }

        // Page kholte hi pehle focusable control ko focus — arrow keys turant kaam karein
        private static void FocusFirst(DependencyObject root)
        {
            try
            {
                var el = FindFirstFocusable(root);
                if (el is IInputElement ie) ie.Focus();
            }
            catch { }
        }

        private static IInputElement? FindFirstFocusable(DependencyObject d)
        {
            if (d is UIElement u && u.IsVisible && u.IsEnabled && u.Focusable)
                return u;
            for (int i = 0; i < VisualTreeHelper.GetChildrenCount(d); i++)
            {
                var r = FindFirstFocusable(VisualTreeHelper.GetChild(d, i));
                if (r != null) return r;
            }
            return null;
        }

        private void OnStockChanged()
        {
            Dispatcher.BeginInvoke(new Action(() => LoadStats(force: true)), System.Windows.Threading.DispatcherPriority.Background);
        }

        private void LoadStats() => LoadStats(force: false);

        private void LoadStats(bool force)
        {
            try
            {
                // Sync ticks every ~10s — throttle the ~24 heavy queries unless data
                // actually changed (force) or enough time has passed since last load.
                DateTime now = DateTime.UtcNow;
                if (!force && _lastStatsLoad != DateTime.MinValue && (now - _lastStatsLoad).TotalSeconds < 3)
                    return;
                if (_statsLoading) return;
                _statsLoading = true;
                _lastStatsLoad = now;

                Task.Run(QueryStats).ContinueWith(t =>
                {
                    try
                    {
                        if (t.IsFaulted && t.Exception != null)
                            LogStatsError(t.Exception);
                        else if (t.IsCompletedSuccessfully && t.Result != null)
                            Dispatcher.BeginInvoke(new Action(() =>
                            {
                                try { ApplyStats(t.Result); }
                                catch { }
                            }));
                    }
                    catch { }
                    finally
                    {
                        _statsLoading = false;
                    }
                });
            }
            catch { }
        }

        private DashboardStats? QueryStats()
        {
            try
            {
                using var conn = _db.GetConnection();
                int outletId = OutletContext.GetSelectedOutletId(_db);
                int defaultId = OutletContext.GetDefaultOutletId(conn);
                void Filter(Microsoft.Data.Sqlite.SqliteCommand c)
                {
                    c.Parameters.AddWithValue("@outlet", outletId);
                    c.Parameters.AddWithValue("@defOutlet", defaultId);
                }

                var s = new DashboardStats
                {
                    OutletName = OutletContext.GetOutletName(outletId)
                };

                // Sales (total + today) — selected outlet only
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = "SELECT IFNULL(SUM(grand_total),0) FROM sales WHERE del_status='Live' AND (IFNULL(outlet_id, @defOutlet) = @outlet)";
                    Filter(c);
                    s.Sales = Convert.ToDouble(c.ExecuteScalar());
                }
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = "SELECT IFNULL(SUM(grand_total),0) FROM sales WHERE del_status='Live' AND sale_date=date('now') AND (IFNULL(outlet_id, @defOutlet) = @outlet)";
                    Filter(c);
                    s.SalesToday = Convert.ToDouble(c.ExecuteScalar());
                }
                // Purchases (total + today)
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = "SELECT IFNULL(SUM(grand_total),0) FROM purchases WHERE (del_status IS NULL OR del_status='Live') AND (IFNULL(outlet_id, @defOutlet) = @outlet)";
                    Filter(c);
                    s.Purchases = Convert.ToDouble(c.ExecuteScalar());
                }
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = "SELECT IFNULL(SUM(grand_total),0) FROM purchases WHERE (del_status IS NULL OR del_status='Live') AND date=date('now') AND (IFNULL(outlet_id, @defOutlet) = @outlet)";
                    Filter(c);
                    s.PurchasesToday = Convert.ToDouble(c.ExecuteScalar());
                }
                // P&L = Total Sales - Total Purchases
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = @"SELECT 
                        (SELECT IFNULL(SUM(grand_total),0) FROM sales WHERE del_status='Live' AND (IFNULL(outlet_id, @defOutlet) = @outlet))
                        - (SELECT IFNULL(SUM(grand_total),0) FROM purchases WHERE (del_status IS NULL OR del_status='Live') AND (IFNULL(outlet_id, @defOutlet) = @outlet))";
                    Filter(c);
                    s.PL = Convert.ToDouble(c.ExecuteScalar());
                }
                // Cash (from sale payments with payment_id = Cash)
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = @"SELECT IFNULL(SUM(sp.amount),0) FROM sale_payments sp 
                        JOIN payment_methods pm ON pm.Id = sp.payment_id 
                        WHERE LOWER(pm.name) LIKE '%cash%' AND (IFNULL(sp.outlet_id, @defOutlet) = @outlet)";
                    Filter(c);
                    s.Cash = Convert.ToDouble(c.ExecuteScalar());
                }
                // Bank (non-cash payment methods)
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = @"SELECT IFNULL(SUM(sp.amount),0) FROM sale_payments sp 
                        JOIN payment_methods pm ON pm.Id = sp.payment_id 
                        WHERE LOWER(pm.name) NOT LIKE '%cash%' AND (IFNULL(sp.outlet_id, @defOutlet) = @outlet)";
                    Filter(c);
                    s.Bank = Convert.ToDouble(c.ExecuteScalar());
                }
                // Receipt / Payment (today)
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = "SELECT IFNULL(SUM(amount),0) FROM customer_receives WHERE del_status='Live' AND date=date('now') AND (IFNULL(outlet_id, @defOutlet) = @outlet)";
                    Filter(c);
                    s.Receipt = Convert.ToDouble(c.ExecuteScalar());
                }
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = "SELECT IFNULL(SUM(amount),0) FROM supplier_payments WHERE del_status='Live' AND date=date('now') AND (IFNULL(outlet_id, @defOutlet) = @outlet)";
                    Filter(c);
                    s.Payment = Convert.ToDouble(c.ExecuteScalar());
                }
                // ── Accurate stock: view_stock_detail ek hi baar aggregate karo (fast) ──
                // Correlated subquery har item pe chalti thi → 11k items pe UI freeze
                string stockAgg = @"
                    SELECT v.item_id,
                           SUM(CASE WHEN v.type=1 THEN v.stock_quantity ELSE -v.stock_quantity END) AS qty
                    FROM view_stock_detail v
                    WHERE v.del_status IS NULL OR v.del_status='Live'
                    GROUP BY v.item_id";
                string itemsWithStock = $@"
                    SELECT i.id, i.name, i.purchase_price, i.alert_quantity, i.last_three_purchase_avg,
                           i.last_purchase_price, IFNULL(u.UnitName,'') AS unit, s.qty,
                           COALESCE(NULLIF(i.last_three_purchase_avg,0), NULLIF(i.last_purchase_price,0), IFNULL(i.purchase_price,0)) AS avg_price
                    FROM items i
                    LEFT JOIN ({stockAgg}) s ON s.item_id = i.id
                    LEFT JOIN units u ON i.sale_unit_id = u.Id
                    WHERE (i.del_status IS NULL OR i.del_status='' OR i.del_status='Live')
                      AND i.parent_id IS NULL
                      AND IFNULL(i.enable_disable_status,1) = 1
                      AND IFNULL(i.type,'') NOT IN ('Service_Product','Combo_Product','0')";

                // Stock Value (Busy-accurate: view_stock_detail SUM, fallback items.stock_quantity)
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = $@"SELECT IFNULL(SUM(COALESCE(s.qty, IFNULL(i.stock_quantity,0)) * IFNULL(i.purchase_price,0)),0)
                        FROM items i
                        LEFT JOIN ({stockAgg}) s ON s.item_id = i.id
                        WHERE i.del_status='Live'";
                    s.StockValue = Convert.ToDouble(c.ExecuteScalar());
                }
                // Low Stock (Busy-accurate stock via pre-aggregated JOIN — fast)
                // Zero-stock (khatam) items low-stock me NAHI ginte — sirf asli kam stock
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = $@"SELECT COUNT(*) FROM (
                        SELECT COALESCE(s.qty, IFNULL(i.stock_quantity,0)) AS qty, IFNULL(i.alert_quantity,0) AS al
                        FROM items i
                        LEFT JOIN ({stockAgg}) s ON s.item_id = i.id
                        WHERE i.del_status='Live' AND i.parent_id IS NULL
                          AND IFNULL(i.enable_disable_status,1) = 1
                          AND IFNULL(i.type,'') NOT IN ('Service_Product','Combo_Product','0')) WHERE
                        qty > 0 AND ((qty <= al AND al > 0) OR (al = 0 AND qty <= 5))";
                    s.LowStock = (long)c.ExecuteScalar();
                }
                // Receivables (customer due = sales due + customer opening balance Dr - customer receives)
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = @"SELECT 
                        IFNULL((SELECT SUM(due_amount) FROM sales WHERE del_status='Live' AND due_amount > 0 AND (IFNULL(outlet_id, @defOutlet) = @outlet)), 0)
                      + IFNULL((SELECT SUM(opening_balance) FROM customers WHERE del_status='Live' AND opening_balance_type='Dr'), 0)
                      - IFNULL((SELECT SUM(amount) FROM customer_receives WHERE del_status='Live' AND (IFNULL(outlet_id, @defOutlet) = @outlet)), 0)";
                    Filter(c);
                    s.Receivables = Math.Max(0, Convert.ToDouble(c.ExecuteScalar()));
                }
                // Payables (supplier due = purchase due + supplier opening balance Dr - supplier payments)
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = @"SELECT 
                        IFNULL((SELECT SUM(due_amount) FROM purchases WHERE (del_status IS NULL OR del_status='Live') AND due_amount > 0 AND (IFNULL(outlet_id, @defOutlet) = @outlet)), 0)
                      + IFNULL((SELECT SUM(opening_balance) FROM suppliers WHERE del_status='Live' AND opening_balance_type='Dr'), 0)
                      - IFNULL((SELECT SUM(amount) FROM supplier_payments WHERE del_status='Live' AND (IFNULL(outlet_id, @defOutlet) = @outlet)), 0)";
                    Filter(c);
                    s.Payables = Math.Max(0, Convert.ToDouble(c.ExecuteScalar()));
                }
                // Stock Ageing (items not sold in last 150 days)
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = @"SELECT COUNT(*) FROM items i WHERE del_status='Live' 
                        AND NOT EXISTS (SELECT 1 FROM sale_details sd 
                            JOIN sales s ON s.Id = sd.sale_id 
                            WHERE sd.item_id = i.Id AND s.sale_date >= date('now','-150 days'))";
                    s.StockAgeing = (long)c.ExecuteScalar();
                }
                // Unmoved items (zero stock)
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = "SELECT COUNT(*) FROM items WHERE del_status='Live' AND IFNULL(stock_quantity,0) = 0";
                    s.Unmoved = (long)c.ExecuteScalar();
                }
                // Today's sales count for recent activity
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = "SELECT COUNT(*) FROM sales WHERE del_status='Live' AND sale_date=date('now') AND (IFNULL(outlet_id, @defOutlet) = @outlet)";
                    Filter(c);
                    s.TodaySalesCount = (long)c.ExecuteScalar();
                }
                // Total items
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = "SELECT COUNT(*) FROM items WHERE del_status='Live'";
                    s.TotalItems = (long)c.ExecuteScalar();
                }
                // Total customers
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = "SELECT COUNT(*) FROM customers WHERE del_status='Live'";
                    s.TotalCustomers = (long)c.ExecuteScalar();
                }
                // Due invoices
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = "SELECT COUNT(*) FROM sales WHERE del_status='Live' AND due_amount > 0 AND (IFNULL(outlet_id, @defOutlet) = @outlet)";
                    Filter(c);
                    s.DueInvoices = (long)c.ExecuteScalar();
                }
                // Total suppliers
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = "SELECT COUNT(*) FROM suppliers WHERE del_status='Live'";
                    s.TotalSuppliers = (long)c.ExecuteScalar();
                }
                // Today's purchases count
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = "SELECT COUNT(*) FROM purchases WHERE (del_status IS NULL OR del_status='Live') AND date=date('now') AND (IFNULL(outlet_id, @defOutlet) = @outlet)";
                    Filter(c);
                    s.TodayPurchases = (long)c.ExecuteScalar();
                }
                // Recent activity (last 5 sales)
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = @"SELECT s.invoice_no, IFNULL(cu.name,'Walk-in') AS cname,
                        s.grand_total, s.sale_date
                        FROM sales s LEFT JOIN customers cu ON cu.Id = s.customer_id
                        WHERE s.del_status='Live' AND (IFNULL(s.outlet_id, @defOutlet) = @outlet)
                        ORDER BY s.Id DESC LIMIT 5";
                    Filter(c);
                    using var r = c.ExecuteReader();
                    while (r.Read())
                        s.RecentSales.Add((r.GetString(0), r.GetString(1), r.GetDouble(2), r.GetString(3)));
                }
                // Low stock items list (top 8, sabse kam stock pehle) — Busy/POS synced dono se aate hain
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = $@"SELECT name, qty, al, unit FROM (
                        SELECT i.name,
                               COALESCE(s.qty, IFNULL(i.stock_quantity,0)) AS qty,
                               IFNULL(i.alert_quantity,0) AS al,
                               IFNULL(u.UnitName,'') AS unit
                        FROM items i
                        LEFT JOIN ({stockAgg}) s ON s.item_id = i.id
                        LEFT JOIN units u ON i.sale_unit_id = u.Id
                        WHERE (i.del_status IS NULL OR i.del_status='' OR i.del_status='Live')
                          AND i.parent_id IS NULL
                          AND IFNULL(i.enable_disable_status,1) = 1
                          AND IFNULL(i.type,'') NOT IN ('Service_Product','Combo_Product','0'))
                        WHERE qty > 0 AND ((qty <= al AND al > 0) OR (al = 0 AND qty <= 5))
                        ORDER BY qty ASC LIMIT 8";
                    using var r = c.ExecuteReader();
                    while (r.Read())
                        s.LowStockItems.Add((r.GetString(0), r.GetDouble(1), r.GetDouble(2), r.GetString(3)));
                }
                // Stock details — top items by stock value (Busy API import se synced)
                using (var c = conn.CreateCommand())
                {
                    c.CommandText = $@"SELECT name, qty, unit, qty * avg_price AS val FROM (
                        SELECT i.name,
                               COALESCE(s.qty, IFNULL(i.stock_quantity,0)) AS qty,
                               IFNULL(u.UnitName,'') AS unit,
                               COALESCE(NULLIF(i.last_three_purchase_avg,0), NULLIF(i.last_purchase_price,0), IFNULL(i.purchase_price,0)) AS avg_price
                        FROM items i
                        LEFT JOIN ({stockAgg}) s ON s.item_id = i.id
                        LEFT JOIN units u ON i.sale_unit_id = u.Id
                        WHERE (i.del_status IS NULL OR i.del_status='' OR i.del_status='Live')
                          AND i.parent_id IS NULL
                          AND IFNULL(i.enable_disable_status,1) = 1
                          AND IFNULL(i.type,'') NOT IN ('Service_Product','Combo_Product','0'))
                        WHERE qty > 0
                        ORDER BY val DESC LIMIT 8";
                    using var r = c.ExecuteReader();
                    while (r.Read())
                        s.StockDetails.Add((r.GetString(0), r.GetDouble(1), r.GetString(2), r.GetDouble(3)));
                }
                // Busy sync hua hai kya? (set_opening_stocks me BusyNotify import rows)
                using (var c = conn.CreateCommand())
                {
                    try
                    {
                        c.CommandText = "SELECT COUNT(*) FROM set_opening_stocks WHERE IFNULL(item_description,'') LIKE '%BusyNotify%' OR IFNULL(item_description,'') LIKE '%Busy%'";
                        s.BusySynced = (long)c.ExecuteScalar() > 0;
                    }
                    catch { s.BusySynced = false; }
                }
                return s;
            }
            catch (Exception ex)
            {
                LogStatsError(ex);
                return null;
            }
        }

        private void ApplyStats(DashboardStats s)
        {
            if (lblOutletInfo != null) lblOutletInfo.Text = "Outlet: " + s.OutletName;
            lblSales.Text = $"₹ {s.Sales:N2}";
            lblSalesSub.Text = $"Today: ₹ {s.SalesToday:N2}";
            lblPurchases.Text = $"₹ {s.Purchases:N2}";
            lblPurchaseSub.Text = $"Today: ₹ {s.PurchasesToday:N2}";
            lblPL.Text = $"₹ {s.PL:N2}";
            lblPL.Foreground = s.PL >= 0
                ? new SolidColorBrush(Color.FromRgb(0x1B, 0x5E, 0x20))
                : new SolidColorBrush(Color.FromRgb(0xC6, 0x28, 0x28));
            lblCash.Text = $"₹ {s.Cash:N2}";
            lblBank.Text = $"₹ {s.Bank:N2}";
            lblReceipt.Text = $"₹ {s.Receipt:N2}";
            lblPayment.Text = $"₹ {s.Payment:N2}";
            lblStockValue.Text = $"₹ {s.StockValue:N2}";
            lblLowStock.Text = $"{s.LowStock} items";
            if (lblReceivables != null) lblReceivables.Text = $"₹ {s.Receivables:N2}";
            if (lblPayables != null) lblPayables.Text = $"₹ {s.Payables:N2}";
            if (lblStockAgeing != null) lblStockAgeing.Text = $"{s.StockAgeing} items (150+ days)";
            if (lblUnmoved != null) lblUnmoved.Text = $"{s.Unmoved} items";
            if (lblTodaySalesCount != null) lblTodaySalesCount.Text = s.TodaySalesCount + " bills";
            if (lblTotalItems != null) lblTotalItems.Text = s.TotalItems + " items";
            if (lblTotalCustomers != null) lblTotalCustomers.Text = s.TotalCustomers.ToString();
            if (lblDueInvoices != null) lblDueInvoices.Text = s.DueInvoices.ToString();
            if (lblTotalSuppliers != null) lblTotalSuppliers.Text = s.TotalSuppliers.ToString();
            if (lblTodayPurchases != null) lblTodayPurchases.Text = s.TodayPurchases + " bills";

            if (recentSalesPanel == null) return;
            PopulateLowStockPanel(s);
            PopulateStockDetailsPanel(s);
            recentSalesPanel.Children.Clear();
            if (s.RecentSales.Count == 0)
            {
                recentSalesPanel.Children.Add(new TextBlock
                {
                    Text = "No sales yet",
                    FontSize = 12,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x99, 0x99, 0x99)),
                    FontFamily = new FontFamily("Segoe UI")
                });
                return;
            }
            foreach (var (invoice, customer, amount, date) in s.RecentSales)
            {
                var row = new Grid { Margin = new Thickness(0, 0, 0, 8) };
                row.ColumnDefinitions.Add(new System.Windows.Controls.ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                row.ColumnDefinitions.Add(new System.Windows.Controls.ColumnDefinition { Width = System.Windows.GridLength.Auto });

                var left = new StackPanel();
                left.Children.Add(new TextBlock
                {
                    Text = invoice + " — " + customer,
                    FontSize = 12.5,
                    FontWeight = System.Windows.FontWeights.SemiBold,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x1E, 0x29, 0x3B)),
                    FontFamily = new FontFamily("Segoe UI")
                });
                left.Children.Add(new TextBlock
                {
                    Text = date,
                    FontSize = 11,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x88, 0x88, 0x88)),
                    FontFamily = new FontFamily("Segoe UI")
                });
                Grid.SetColumn(left, 0);

                var amt = new TextBlock
                {
                    Text = $"₹ {amount:N2}",
                    FontSize = 13,
                    FontWeight = System.Windows.FontWeights.Bold,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x1B, 0x5E, 0x20)),
                    VerticalAlignment = System.Windows.VerticalAlignment.Center,
                    FontFamily = new FontFamily("Segoe UI")
                };
                Grid.SetColumn(amt, 1);

                row.Children.Add(left);
                row.Children.Add(amt);
                recentSalesPanel.Children.Add(row);

                // divider
                recentSalesPanel.Children.Add(new System.Windows.Shapes.Rectangle
                {
                    Height = 1,
                    Fill = new SolidColorBrush(Color.FromRgb(0xE2, 0xE8, 0xF0)),
                    Margin = new Thickness(0, 0, 0, 8)
                });
            }
        }

        private static readonly Brush DividerBrush = new SolidColorBrush(Color.FromRgb(0xE2, 0xE8, 0xF0));

        /// <summary>Low stock items panel — top 8 items stock ke hisaab se (sabse kam pehle).</summary>
        private void PopulateLowStockPanel(DashboardStats s)
        {
            if (lowStockPanel == null) return;
            lowStockPanel.Children.Clear();

            if (s.LowStockItems.Count == 0)
            {
                lowStockPanel.Children.Add(new TextBlock
                {
                    Text = "Sab items stock me hain 👍",
                    FontSize = 12,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x99, 0x99, 0x99)),
                    FontFamily = new FontFamily("Segoe UI")
                });
                return;
            }

            foreach (var (name, stock, alert, unit) in s.LowStockItems)
            {
                var row = new Grid { Margin = new Thickness(0, 0, 0, 8) };
                row.ColumnDefinitions.Add(new System.Windows.Controls.ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                row.ColumnDefinitions.Add(new System.Windows.Controls.ColumnDefinition { Width = System.Windows.GridLength.Auto });

                var left = new TextBlock
                {
                    Text = name.Length > 28 ? name[..27] + "…" : name,
                    FontSize = 12.5,
                    FontWeight = System.Windows.FontWeights.SemiBold,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x1E, 0x29, 0x3B)),
                    FontFamily = new FontFamily("Segoe UI"),
                    VerticalAlignment = System.Windows.VerticalAlignment.Center,
                    ToolTip = name
                };
                Grid.SetColumn(left, 0);

                var right = new StackPanel { Orientation = System.Windows.Controls.Orientation.Horizontal, VerticalAlignment = System.Windows.VerticalAlignment.Center };
                right.Children.Add(new TextBlock
                {
                    Text = $"{stock:N0} / {alert:N0} {unit}".Trim(),
                    FontSize = 12,
                    FontWeight = System.Windows.FontWeights.Bold,
                    Foreground = new SolidColorBrush(Color.FromRgb(0xC6, 0x28, 0x28)),
                    FontFamily = new FontFamily("Segoe UI")
                });
                if (stock <= 0)
                {
                    right.Children.Add(new TextBlock
                    {
                        Text = "  OUT",
                        FontSize = 10,
                        FontWeight = System.Windows.FontWeights.Bold,
                        Foreground = Brushes.White,
                        Background = new SolidColorBrush(Color.FromRgb(0xDC, 0x26, 0x26)),
                        Padding = new Thickness(4, 1, 4, 1)
                    });
                }
                Grid.SetColumn(right, 1);

                row.Children.Add(left);
                row.Children.Add(right);
                lowStockPanel.Children.Add(row);
                lowStockPanel.Children.Add(new System.Windows.Shapes.Rectangle { Height = 1, Fill = DividerBrush, Margin = new Thickness(0, 0, 0, 8) });
            }
        }

        /// <summary>Stock details panel — top value items (Busy API import se synced stock).</summary>
        private void PopulateStockDetailsPanel(DashboardStats s)
        {
            if (stockDetailsPanel == null) return;
            stockDetailsPanel.Children.Clear();

            if (s.StockDetails.Count == 0)
            {
                stockDetailsPanel.Children.Add(new TextBlock
                {
                    Text = "Stock data nahi mila — Busy import ya sync complete hone do",
                    FontSize = 12,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x99, 0x99, 0x99)),
                    FontFamily = new FontFamily("Segoe UI"),
                    TextWrapping = TextWrapping.Wrap
                });
                return;
            }

            // Header note: Busy sync status
            stockDetailsPanel.Children.Add(new TextBlock
            {
                Text = s.BusySynced
                    ? "Busy import se synced — top items by stock value:"
                    : "Top items by stock value:",
                FontSize = 11,
                Foreground = new SolidColorBrush(Color.FromRgb(0x88, 0x88, 0x88)),
                FontFamily = new FontFamily("Segoe UI"),
                Margin = new Thickness(0, 0, 0, 8)
            });

            foreach (var (name, stock, unit, value) in s.StockDetails)
            {
                var row = new Grid { Margin = new Thickness(0, 0, 0, 8) };
                row.ColumnDefinitions.Add(new System.Windows.Controls.ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                row.ColumnDefinitions.Add(new System.Windows.Controls.ColumnDefinition { Width = System.Windows.GridLength.Auto });

                var left = new StackPanel();
                left.Children.Add(new TextBlock
                {
                    Text = name.Length > 28 ? name[..27] + "…" : name,
                    FontSize = 12.5,
                    FontWeight = System.Windows.FontWeights.SemiBold,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x1E, 0x29, 0x3B)),
                    FontFamily = new FontFamily("Segoe UI"),
                    ToolTip = name
                });
                left.Children.Add(new TextBlock
                {
                    Text = $"{stock:N2} {unit}".Trim(),
                    FontSize = 11,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x88, 0x88, 0x88)),
                    FontFamily = new FontFamily("Segoe UI")
                });
                Grid.SetColumn(left, 0);

                var amt = new TextBlock
                {
                    Text = $"₹ {value:N0}",
                    FontSize = 13,
                    FontWeight = System.Windows.FontWeights.Bold,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x1B, 0x5E, 0x20)),
                    VerticalAlignment = System.Windows.VerticalAlignment.Center,
                    FontFamily = new FontFamily("Segoe UI")
                };
                Grid.SetColumn(amt, 1);

                row.Children.Add(left);
                row.Children.Add(amt);
                stockDetailsPanel.Children.Add(row);
                stockDetailsPanel.Children.Add(new System.Windows.Shapes.Rectangle { Height = 1, Fill = DividerBrush, Margin = new Thickness(0, 0, 0, 8) });
            }
        }

        private static void LogStatsError(Exception ex)
        {
            try
            {
                System.IO.File.AppendAllText(
                    System.IO.Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "RashanKiDukan", "dashboard_error.log"),
                    $"{DateTime.Now:HH:mm:ss.fff} {ex}\n");
            }
            catch { }
        }

        private sealed class DashboardStats
        {
            public string OutletName = "";
            public double Sales, SalesToday, Purchases, PurchasesToday, PL, Cash, Bank, Receipt, Payment, StockValue, Receivables, Payables;
            public long LowStock, StockAgeing, Unmoved, TodaySalesCount, TotalItems, TotalCustomers, DueInvoices, TotalSuppliers, TodayPurchases;
            public List<(string InvoiceNo, string CustomerName, double Amount, string Date)> RecentSales = new();
            // Low stock items (stock <= alert) — name, stock, alert, unit
            public List<(string Name, double Stock, double Alert, string Unit)> LowStockItems = new();
            // Busy API se synced items ka stock — name, stock, unit, value
            public List<(string Name, double Stock, string Unit, double Value)> StockDetails = new();
            public bool BusySynced; // koi item Busy import se aaya hai?
        }

        private bool IsModuleEnabled(string moduleName)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT is_enabled FROM installed_modules WHERE LOWER(name)=LOWER(@n) AND is_enabled=1 LIMIT 1";
                cmd.Parameters.AddWithValue("@n", moduleName);
                var result = cmd.ExecuteScalar();
                return result != null && Convert.ToInt64(result) == 1;
            }
            catch { return true; } // default: show if table doesn't exist
        }

        public void RebuildMenu() => BuildMenu();

        private void BuildMenu()
        {
            menuStack.Children.Clear();
            var openArrow = new List<TextBlock>();
            var openSub = new List<StackPanel>();

            void CloseAll()
            {
                foreach (var a in openArrow) a.Text = "\uE097";
                foreach (var s in openSub) s.Visibility = Visibility.Collapsed;
                openArrow.Clear();
                openSub.Clear();
            }

            void Top(string icon, string text, Action nav, string? tag = null)
            {
                var btn = new Button { Style = (Style)FindResource("MainMenuItemBtn") };
                var sp = new StackPanel { Orientation = Orientation.Horizontal };
                sp.Children.Add(new TextBlock { Text = icon, FontSize = 14, Margin = new Thickness(0, 0, 10, 0), VerticalAlignment = VerticalAlignment.Center });
                sp.Children.Add(new TextBlock { Text = text, VerticalAlignment = VerticalAlignment.Center, FontWeight = FontWeights.SemiBold });
                btn.Content = sp;
                btn.Tag = tag ?? text;
                btn.Click += (s, e) => { CloseAll(); nav(); };
                menuStack.Children.Add(btn);
            }

            void Section(string text)
            {
                menuStack.Children.Add(new TextBlock
                {
                    Text = text,
                    Margin = new Thickness(18, 12, 0, 4),
                    FontSize = 10.5,
                    FontWeight = FontWeights.Bold,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x1B, 0x5E, 0x20)),
                    FontFamily = new FontFamily("Segoe UI")
                });
            }

            void Group(string icon, string title, string groupKey, params (string text, string key, Action nav)[] items)
            {
                var btn = new Button { Style = (Style)FindResource("MainMenuItemBtn") };
                var g = new Grid();
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(30) });
                var sp = new StackPanel { Orientation = Orientation.Horizontal };
                sp.Children.Add(new TextBlock { Text = icon, FontSize = 14, Margin = new Thickness(0, 0, 10, 0), VerticalAlignment = VerticalAlignment.Center });
                sp.Children.Add(new TextBlock { Text = title, VerticalAlignment = VerticalAlignment.Center, FontWeight = FontWeights.SemiBold });
                var arrow = new TextBlock { Text = "\uE097", FontFamily = new FontFamily("Segoe MDL2 Assets"), FontSize = 12, Foreground = new SolidColorBrush(Color.FromRgb(0x88, 0x88, 0x88)), VerticalAlignment = VerticalAlignment.Center, HorizontalAlignment = HorizontalAlignment.Center };
                Grid.SetColumn(sp, 0);
                Grid.SetColumn(arrow, 1);
                g.Children.Add(sp);
                g.Children.Add(arrow);
                btn.Content = g;
                btn.Tag = groupKey;

                var sub = new StackPanel { Visibility = Visibility.Collapsed };
                sub.Tag = btn;
                foreach (var (text, key, nav) in items)
                {
                    var sb = new Button { Style = (Style)FindResource("SubMenuItemBtn"), Content = text };
                    sb.Tag = key;
                    sb.Click += (s, e) => nav();
                    sub.Children.Add(sb);
                }
                btn.Click += (s, e) =>
                {
                    bool wasOpen = sub.Visibility == Visibility.Visible;
                    CloseAll();
                    if (!wasOpen)
                    {
                        sub.Visibility = Visibility.Visible;
                        arrow.Text = "\uE098";
                        openArrow.Add(arrow);
                        openSub.Add(sub);
                    }
                };
                menuStack.Children.Add(btn);
                menuStack.Children.Add(sub);
            }

            void Crud(CrudSpec spec, bool form = false) => ShowPage(new EntityCrudPage(spec, this, form));
            void Voucher(VoucherSpec spec, bool form = false) => ShowPage(new LineItemVoucherPage(spec, this, form));
            void Report(string key) => ShowPage(new ReportPage(this, key));

            // ---------- Top-level ----------
            Top("🏠", "Home", ShowHome, "home");
            Top("📊", "Dashboard", ShowDashboard, "dashboard");
            Top("📅", "Booking", () => Crud(EntityRegistry.Booking(), true), "booking");
            Group("📍", "Outlet", "outlet",
                ("Add Outlet", "outlet_add", () => ShowPage(new AddOutletPage(this))),
                ("List Outlet", "outlet_list", () => ShowPage(new ListOutletPage(this))));

            Section("ITEM & STOCK");
            Group("📦", "Item", "item",
                ("Add Item", "item_add", () => ShowPage(new ItemMasterPage(this))),
                ("List Item", "item_list", () => ShowPage(new ItemMasterListingPage(this))),
                ("Bulk Item Update", "item_bulk_update", () => ShowPage(new BulkItemUpdatePage(this))),
                ("Bulk Item Import", "item_bulk_import", () => ShowPage(new ItemImportPage(this))),
                ("Opening Stock Import", "item_opening_import", () => ShowPage(new ItemImportPage(this))));
            Group("⚙️", "Item Configuration", "item_configuration",
                ("Add Item Category", "ic_category_add", () => CrudConfig(ConfigEntity.ItemCategory, add: true)),
                ("List Item Category", "ic_category_list", () => CrudConfig(ConfigEntity.ItemCategory)),
                ("Add Brand", "ic_brand_add", () => CrudConfig(ConfigEntity.Brand, add: true)),
                ("List Brand", "ic_brand_list", () => CrudConfig(ConfigEntity.Brand)),
                ("Add Unit", "ic_unit_add", () => CrudConfig(ConfigEntity.Unit, add: true)),
                ("List Unit", "ic_unit_list", () => CrudConfig(ConfigEntity.Unit)),
                ("Add Rack", "ic_rack_add", () => CrudConfig(ConfigEntity.Rack, add: true)),
                ("List Rack", "ic_rack_list", () => CrudConfig(ConfigEntity.Rack)),
                ("Add Variation Attribute", "ic_variation_add", () => CrudConfig(ConfigEntity.Variation, add: true)),
                ("List Variation Attribute", "ic_variation_list", () => CrudConfig(ConfigEntity.Variation)));
            Group("📦", "Stock", "stock",
                ("Stock", "stock_view", () => ShowPage(new StockPage(this))),
                ("Low Stock", "stock_low", () => { var p = new StockPage(this); p.tabLowStock.IsChecked = true; ShowPage(p); }));

            Section("SALE & CUSTOMER");
            Group("🛒", "Sale", "sale",
                ("POS", "pos", () => OpenPOS(null, null)),
                ("List Sale", "sale_list", () => ShowPage(new SaleListPage(this))),
                ("Add Promotion", "sale_promotion_add", () => { var p = new PromotionsListPage(this); p.OpenAddForm(); ShowPage(p); }),
                ("List Promotion", "sale_promotion_list", () => ShowPage(new PromotionsListPage(this))),
                ("Add Delivery Partner", "sale_delivery_add", () => Crud(EntityRegistry.DeliveryPartner(), true)),
                ("List Delivery Partner", "sale_delivery_list", () => Crud(EntityRegistry.DeliveryPartner())));
            Group("↩️", "Sale Return", "sale_return",
                ("Add", "sale_return_add", () => ShowPage(new SaleReturnCreatePage(this))),
                ("List", "sale_return_list", () => ShowPage(new SaleReturnsPage(this))));
            Group("💸", "Installment Sale", "installment_sale",
                ("Add", "installment_add", () => Crud(EntityRegistry.InstallmentSale(), true)),
                ("List", "installment_list", () => Crud(EntityRegistry.InstallmentSale())),
                ("Installment Collection", "installment_collection", () => Report("installment-due-report")));
            Group("👤", "Customer", "customer",
                ("Add Customer", "customer_add", () => ShowPage(new CreateCustomerV2Page(this))),
                ("List Customer", "customer_list", () => ShowPage(new CustomersListPage(this))),
                ("Add Installment Customer", "customer_installment_add", () => ShowPage(new CreateInstallmentCustomerPage(this))),
                ("List Installment Customer", "customer_installment_list", () => ShowPage(new InstallmentCustomersPage(this))),
                ("Add Customer Receive", "customer_receive_add", () => Crud(EntityRegistry.CustomerReceive(), true)),
                ("List Customer Receive", "customer_receive_list", () => Crud(EntityRegistry.CustomerReceive())));
            Group("💰", "Income", "income",
                ("Add Income", "income_add", () => ShowPage(new IncomeCreatePage(this))),
                ("List Income", "income_list", () => ShowPage(new IncomeListPage(this))),
                ("Add Income Category", "income_category_add", () => ShowPage(new IncomeCategoryFormPage(this))),
                ("List Income Category", "income_category_list", () => ShowPage(new IncomeCategoryListPage(this))));

            Section("PURCHASE & SUPPLIER");
            Group("📥", "Purchase", "purchase",
                ("Add", "purchase_add", () => ShowPage(new PurchaseCreatePage(this))),
                ("List", "purchase_list", () => ShowPage(new PurchaseListPage(this))));
            Group("↩️", "Purchase Return", "purchase_return",
                ("Add", "purchase_return_add", () => ShowPage(new PurchaseReturnCreatePage(this))),
                ("List", "purchase_return_list", () => ShowPage(new PurchaseReturnListPage(this))));
            Group("🏪", "Supplier", "supplier",
                ("Add Supplier", "supplier_add", () => ShowPage(new CreateSupplierPage(this))),
                ("List Supplier", "supplier_list", () => ShowPage(new SuppliersListPage(this))),
                ("Add Supplier Payment", "supplier_payment_add", () => ShowPage(new SupplierPaymentCreatePage(this))),
                ("List Supplier Payment", "supplier_payment_list", () => ShowPage(new SupplierPaymentListPage(this))));
            Group("🧾", "Expense", "expense",
                ("Add Expense", "expense_add", () => ShowPage(new ExpenseCreatePage(this))),
                ("List Expense", "expense_list", () => ShowPage(new ExpenseListPage(this))),
                ("Add Expense Category", "expense_category_add", () => CrudConfig(ConfigEntity.ExpenseCategory, add: true)),
                ("List Expense Category", "expense_category_list", () => CrudConfig(ConfigEntity.ExpenseCategory)));

            Section("TRANSFER & DAMAGE");
            Group("🚚", "Transfer", "transfer",
                ("Add", "transfer_add", () => ShowPage(new TransferCreatePage(this))),
                ("List", "transfer_list", () => ShowPage(new TransferListPage(this))));
            Group("💥", "Damage", "damage",
                ("Add", "damage_add", () => ShowPage(new DamageCreatePage(this))),
                ("List", "damage_list", () => ShowPage(new DamageListPage(this))));
            Group("📄", "Quotation", "quotation",
                ("Add", "quotation_add", () => ShowPage(new QuotationCreatePage(this))),
                ("List", "quotation_list", () => ShowPage(new QuotationListPage(this))));

            Section("FIXED ASSET");
            Group("🏢", "Fixed Asset", "fixed_asset",
                ("Add Item", "fixed_asset_add", () => Crud(EntityRegistry.FixedAssetItem(), true)),
                ("List Item", "fixed_asset_list", () => Crud(EntityRegistry.FixedAssetItem())),
                ("Add Stock In", "fixed_asset_stock_in_add", () => Voucher(VoucherRegistry.FixedAssetStockIn(), true)),
                ("List Stock In", "fixed_asset_stock_in_list", () => Voucher(VoucherRegistry.FixedAssetStockIn())),
                ("Add Stock Out", "fixed_asset_stock_out_add", () => Voucher(VoucherRegistry.FixedAssetStockOut(), true)),
                ("List Stock Out", "fixed_asset_stock_out_list", () => Voucher(VoucherRegistry.FixedAssetStockOut())));

            if (IsModuleEnabled("Business Club"))
            {
                Section("BUSINESS CLUB");
                Group("🎯", "Business Club", "business_club",
                    ("Dashboard", "business_club_dashboard", () => ShowPage(new BusinessClubPage(this))),
                    ("Settings", "business_club_settings", () => ShowPage(new BusinessClubPage(this, "settings"))),
                    ("Customer Wallets", "business_club_wallets", () => ShowPage(new BusinessClubPage(this, "wallets"))),
                    ("Register Member", "business_club_register", () => ShowPage(new BusinessClubPage(this, "register"))),
                    ("Print ID Card", "business_club_id_card", () => ShowPage(new BusinessClubPage(this, "wallets"))));
            }

            Section("PRICE LISTS");
            Group("🏷️", "Price Lists", "price_list",
                ("Add Price List", "price_list_add", () => Crud(EntityRegistry.PriceList(), true)),
                ("List Price Lists", "price_list_list", () => Crud(EntityRegistry.PriceList())));

            Section("WARRANTY & SERVICING");
            Group("🛠️", "Warranty & Servicing", "warranty_servicing",
                ("Add Servicing", "servicing_add", () => Crud(EntityRegistry.Servicing(), true)),
                ("List Servicing", "servicing_list", () => Crud(EntityRegistry.Servicing())),
                ("Add Warranty", "warranty_add", () => Crud(EntityRegistry.Warranty(), true)),
                ("List Warranty", "warranty_list", () => Crud(EntityRegistry.Warranty())),
                ("Warranty Checking", "warranty_checking", () => Report("warranty-checking-report")));

            Section("ACCOUNTING");
            Group("🏦", "Payment Account", "payment_account",
                ("Add", "payment_account_add", () => ShowPage(new PaymentMethodFormPage(this))),
                ("List", "payment_account_list", () => ShowPage(new PaymentMethodListPage(this))),
                ("Sort Account", "payment_account_sort", () => ShowPage(new SortAccountPage(this))));
            Group("📒", "Accounting", "accounting",
                ("Add Deposit/Withdraw", "accounting_deposit_add", () => ShowPage(new DepositWithdrawFormPage(this))),
                ("List Deposit/Withdraw", "accounting_deposit_list", () => ShowPage(new DepositWithdrawListPage(this))),
                ("Account Balance", "accounting_balance", () => ShowPage(new AccountBalancePage(this))),
                ("Account Statement", "accounting_statement", () => ShowPage(new AccountStatementPage(this))),
                ("Balance Sheet", "accounting_balance_sheet", () => ShowPage(new BalanceSheetPage(this))),
                ("Trial Balance", "accounting_trial_balance", () => ShowPage(new TrialBalancePage(this))),
                ("Transaction History", "accounting_transaction_history", () => ShowPage(new TransactionHistoryPage(this))));

            Section("MARKETING");
            Group("📣", "Marketing", "marketing",
                ("Email Marketing", "marketing_email", () => ShowPage(new EmailMarketingPage(this))),
                ("SMS Marketing", "marketing_sms", () => ShowPage(new SmsMarketingPage(this))),
                ("WhatsApp Marketing", "marketing_whatsapp", () => ShowPage(new WhatsAppMarketingPage(this))));

            Section("HUMAN RESOURCE MANAGEMENT");
            Group("👥", "Role Permission", "role_permission",
                ("Add", "role_add", () => ShowPage(new AddRolePage(this))),
                ("List", "role_list", () => ShowPage(new RoleListPage(this))));
            Group("🧑‍💼", "Employee Account", "employee_account",
                ("Add Employee", "employee_add", () => ShowPage(new AddEmployeePage(this))),
                ("List Employee", "employee_list", () => ShowPage(new EmployeeListPage(this))),
                ("Update Profile", "employee_update_profile", () => ShowPage(new UpdateProfilePage(this))));
            Group("⏰", "Attendance", "attendance",
                ("Add", "attendance_add", () => ShowPage(new AttendanceFormPage(this))),
                ("List", "attendance_list", () => ShowPage(new AttendanceListPage(this))));
            Group("💵", "Salary / Payroll", "salary",
                ("Add", "salary_add", () => ShowPage(new SalaryFormPage(this))),
                ("List", "salary_list", () => ShowPage(new SalaryListPage(this))));
            Group("🪙", "Employee Advance Payment", "employee_advance",
                ("Add", "employee_advance_add", () => ShowPage(new EmployeeAdvanceFormPage(this))),
                ("List", "employee_advance_list", () => ShowPage(new EmployeeAdvanceListPage(this))));

            Section("REPORT & SETTING");
            Group("📊", "Report", "report", ("All Reports", "report_all", () => ShowPage(new ReportsIndexPage(this))));
            Group("🔧", "Settings", "settings",
                ("All Settings", "settings_all", () => ShowPage(new SettingsPage(this))),
                ("Feature Activation", "feature_activation", () => OpenFeatureActivation(null, null)),
                ("Add Denomination", "denomination_add", () => ShowPage(new DenominationPage(this))),
                ("List Denomination", "denomination_list", () => ShowPage(new DenominationPage(this))),
                ("Add Multiple Currency", "currency_add", () => ShowPage(new MultipleCurrencyPage(this))),
                ("List Multiple Currency", "currency_list", () => ShowPage(new MultipleCurrencyPage(this))),
                ("Add Printer", "printer_add", () => ShowPage(new PrinterPage(this))),
                ("List Printer", "printer_list", () => ShowPage(new PrinterPage(this))),
                ("Add Counter", "counter_add", () => ShowPage(new CounterPage(this))),
                ("List Counter", "counter_list", () => ShowPage(new CounterPage(this))),
                ("Add Module", "module_add", () => ShowPage(new AddModulePage(this))),
                ("List Modules", "module_list", () => ShowPage(new ListModulePage(this))),
                ("Check for Updates", "check_updates", () => ManualCheckForUpdate()));

            var logout = new Button
            {
                Style = (Style)FindResource("MainMenuItemBtn"),
                Foreground = new SolidColorBrush(Color.FromRgb(0xDC, 0x26, 0x26)),
                Content = "Logout",
                Margin = new Thickness(0, 10, 0, 0)
            };
            logout.Click += BtnLogout_Click;
            menuStack.Children.Add(logout);
        }

        private void CrudConfig(ConfigEntity entity, bool add = false)
        {
            if (add) ShowPage(new ConfigEditPage(this, entity));
            else ShowPage(new ConfigListPage(this, entity));
        }

        private void ToggleMenu(object sender, RoutedEventArgs e)
        {
            var btn = sender as Button;
            string tag = btn?.Tag as string;
            ToggleMenuByTag(tag);
        }

        private void ToggleMenuByTag(string tag)
        {
            if (string.IsNullOrEmpty(tag)) return;
            var subStack = FindName("sub_" + tag) as StackPanel;
            var arrow = FindName("arrow_" + tag) as TextBlock;
            if (subStack == null) return;

            if (!string.IsNullOrEmpty(_lastOpenMenu) && _lastOpenMenu != tag)
            {
                var prevSub = FindName("sub_" + _lastOpenMenu) as StackPanel;
                var prevArrow = FindName("arrow_" + _lastOpenMenu) as TextBlock;
                if (prevSub != null) prevSub.Visibility = Visibility.Collapsed;
                if (prevArrow != null) prevArrow.Text = "\uE097";
            }

            if (subStack.Visibility == Visibility.Visible)
            {
                subStack.Visibility = Visibility.Collapsed;
                if (arrow != null) arrow.Text = "\uE097";
                _lastOpenMenu = "";
            }
            else
            {
                subStack.Visibility = Visibility.Visible;
                if (arrow != null) arrow.Text = "\uE098";
                _lastOpenMenu = tag;
            }
        }

        private void BtnLogout_Click(object sender, RoutedEventArgs e)
        {
            try { new AuthService(_db).ClearSession(); } catch { }
            var login = new LoginWindow();
            login.Show();
            Close();
        }

        private void OpenInvoices(object sender, RoutedEventArgs e)
        {
            LeavePos();
            var page = new InvoicesPage(this);
            dashboardView.Visibility = Visibility.Collapsed;
            mainContent.Content = page;
            syncBar.Visibility = Visibility.Visible;
        }

        private void OpenSaleReturns(object sender, RoutedEventArgs e)
        {
            LeavePos();
            var page = new SaleReturnsPage(this);
            dashboardView.Visibility = Visibility.Collapsed;
            mainContent.Content = page;
            syncBar.Visibility = Visibility.Visible;
        }

        private void OpenPurchase(object sender, RoutedEventArgs e)
        {
            LeavePos();
            var page = new PurchasePage(this);
            dashboardView.Visibility = Visibility.Collapsed;
            mainContent.Content = page;
            syncBar.Visibility = Visibility.Visible;
        }

        private void OpenPurchaseReturn(object sender, RoutedEventArgs e)
        {
            LeavePos();
            var page = new PurchaseReturnPage(this);
            dashboardView.Visibility = Visibility.Collapsed;
            mainContent.Content = page;
            syncBar.Visibility = Visibility.Visible;
        }

        private void OpenItemMaster(object sender, RoutedEventArgs e)
        {
            LeavePos();
            var page = new ItemMasterPage(this);
            dashboardView.Visibility = Visibility.Collapsed;
            mainContent.Content = page;
            syncBar.Visibility = Visibility.Visible;
        }

        private void OpenItemMasterListing(object sender, RoutedEventArgs e)
        {
            LeavePos();
            var page = new ItemMasterListingPage(this);
            dashboardView.Visibility = Visibility.Collapsed;
            mainContent.Content = page;
            syncBar.Visibility = Visibility.Visible;
        }

        private void OpenAddCategory(object sender, RoutedEventArgs e)
            => ShowPage(new ConfigEditPage(this, ConfigEntity.ItemCategory));

        private void OpenListCategory(object sender, RoutedEventArgs e)
            => ShowPage(new ConfigListPage(this, ConfigEntity.ItemCategory));

        private void OpenAddBrand(object sender, RoutedEventArgs e)
            => ShowPage(new ConfigEditPage(this, ConfigEntity.Brand));

        private void OpenListBrand(object sender, RoutedEventArgs e)
            => ShowPage(new ConfigListPage(this, ConfigEntity.Brand));

        private void OpenAddUnit(object sender, RoutedEventArgs e)
            => ShowPage(new ConfigEditPage(this, ConfigEntity.Unit));

        private void OpenListUnit(object sender, RoutedEventArgs e)
            => ShowPage(new ConfigListPage(this, ConfigEntity.Unit));

        private void OpenAddRack(object sender, RoutedEventArgs e)
            => ShowPage(new ConfigEditPage(this, ConfigEntity.Rack));

        private void OpenListRack(object sender, RoutedEventArgs e)
            => ShowPage(new ConfigListPage(this, ConfigEntity.Rack));

        private void OpenAddVariation(object sender, RoutedEventArgs e)
            => ShowPage(new ConfigEditPage(this, ConfigEntity.Variation));

        private void OpenListVariation(object sender, RoutedEventArgs e)
            => ShowPage(new ConfigListPage(this, ConfigEntity.Variation));

        private void OpenPOS(object sender, RoutedEventArgs e) => OpenPos();

        private void OpenPOS(object sender, object? e) => OpenPos();

        private void OpenPos()
        {
            sidebarBorder.Visibility = Visibility.Collapsed;
            sidebarCol.Width = new GridLength(0);
            SetPosNavMode();
            if (_posPage == null)
                _posPage = new POSPage(this, _currentUser);
            dashboardView.Visibility = Visibility.Collapsed;
            mainContent.Content = _posPage;
            syncBar.Visibility = Visibility.Collapsed;
        }

        private void OpenCustomer(object sender, RoutedEventArgs e)
        {
            LeavePos();
            var page = new CustomersListPage(this);
            dashboardView.Visibility = Visibility.Collapsed;
            mainContent.Content = page;
            syncBar.Visibility = Visibility.Visible;
        }

        private void OpenSuppliers(object sender, RoutedEventArgs e)
        {
            LeavePos();
            var page = new SuppliersListPage(this);
            dashboardView.Visibility = Visibility.Collapsed;
            mainContent.Content = page;
            syncBar.Visibility = Visibility.Visible;
        }

        public void ShowDashboard()
        {
            LeavePos();
            mainContent.Content = null;
            dashboardView.Visibility = Visibility.Visible;
            sidebarBorder.Visibility = Visibility.Visible;
            sidebarCol.Width = new GridLength(230);
            SetPageNavMode();
            LoadStats(force: true);
            syncBar.Visibility = Visibility.Visible;
            Dispatcher.BeginInvoke(new Action(FocusMenuFirst), DispatcherPriority.Input);
        }

        public void ShowHome()
        {
            ShowPage(new HomePage(this));
        }

        /// <summary>
        /// Agar abhi ItemMasterListingPage open hai to uska LoadItems() call karo
        /// (item edit ke baad instant stock refresh ke liye)
        /// </summary>
        public void RefreshItemList()
        {
            if (mainContent.Content is ItemMasterListingPage listPage)
                listPage.LoadItems();
        }

        // Navigating away from POS — stop its timers before replacing content
        private void LeavePos()
        {
            if (mainContent.Content is POSPage)
            {
                _posPage?.Shutdown();
                _posPage = null;
            }
        }

        public void ShowPage(UserControl page)
        {
            LeavePos();
            dashboardView.Visibility = Visibility.Collapsed;
            mainContent.Content = page;
            SetPageNavMode();
            syncBar.Visibility = Visibility.Visible;
            // Page kholte hi pehla focusable control focus — arrow keys turant chalein
            Dispatcher.BeginInvoke(new Action(() => FocusFirst(page)), DispatcherPriority.Input);
        }

        private void SetPageNavMode()
        {
            KeyboardNavigation.SetDirectionalNavigation(mainContent, KeyboardNavigationMode.Continue);
        }

        private void SetPosNavMode()
        {
            KeyboardNavigation.SetDirectionalNavigation(mainContent, KeyboardNavigationMode.None);
        }

        private void OpenPaymentsBanking(object sender, RoutedEventArgs e)
        {
            LeavePos();
            var page = new PaymentsBankingPage(this);
            dashboardView.Visibility = Visibility.Collapsed;
            mainContent.Content = page;
            syncBar.Visibility = Visibility.Visible;
        }

        private void OpenGST(object sender, RoutedEventArgs e)
        {
            LeavePos();
            var page = new GSTPage(this);
            dashboardView.Visibility = Visibility.Collapsed;
            mainContent.Content = page;
            syncBar.Visibility = Visibility.Visible;
        }

        private void OpenInventory(object sender, RoutedEventArgs e)
        {
            LeavePos();
            var page = new InventoryPage(this);
            dashboardView.Visibility = Visibility.Collapsed;
            mainContent.Content = page;
            syncBar.Visibility = Visibility.Visible;
        }

        private void OpenReports(object sender, RoutedEventArgs e)
        {
            LeavePos();
            var page = new ReportsPage(this);
            dashboardView.Visibility = Visibility.Collapsed;
            mainContent.Content = page;
            syncBar.Visibility = Visibility.Visible;
        }

        private void OpenSettings(object sender, RoutedEventArgs e)
        {
            LeavePos();
            var page = new SettingsPage(this);
            dashboardView.Visibility = Visibility.Collapsed;
            mainContent.Content = page;
            syncBar.Visibility = Visibility.Visible;
        }

        private void OpenFeatureActivation(object sender, RoutedEventArgs e)
        {
            LeavePos();
            var page = new FeatureActivationPage(this);
            dashboardView.Visibility = Visibility.Collapsed;
            mainContent.Content = page;
            syncBar.Visibility = Visibility.Visible;
        }

        /// <summary>
        /// FeatureActivationPage save ke baad menu hide/show turant refresh karne ke liye.
        /// </summary>
        public void RefreshFeatureActivation()
        {
            try { ApplyFeatureActivation(); } catch { }
        }

        private void OpenAddPurchase(object sender, RoutedEventArgs e)
            => ShowPage(new PurchaseCreatePage(this));

        private void OpenListPurchase(object sender, RoutedEventArgs e)
            => ShowPage(new PurchaseListPage(this));

        private void OpenAddPurchaseReturn(object sender, RoutedEventArgs e)
            => ShowPage(new PurchaseReturnCreatePage(this));

        private void OpenListPurchaseReturn(object sender, RoutedEventArgs e)
            => ShowPage(new PurchaseReturnListPage(this));

        private void OpenAddSupplier(object sender, RoutedEventArgs e)
            => ShowPage(new CreateSupplierPage(this));

        private void OpenAddSupplierPayment(object sender, RoutedEventArgs e)
            => ShowPage(new SupplierPaymentCreatePage(this));

        private void OpenListSupplierPayment(object sender, RoutedEventArgs e)
            => ShowPage(new SupplierPaymentListPage(this));

        private void OpenAddExpense(object sender, RoutedEventArgs e)
            => ShowPage(new ExpenseCreatePage(this));

        private void OpenListExpense(object sender, RoutedEventArgs e)
            => ShowPage(new ExpenseListPage(this));

        private void OpenAddExpenseCategory(object sender, RoutedEventArgs e)
            => ShowPage(new ConfigEditPage(this, ConfigEntity.ExpenseCategory));

        private void OpenListExpenseCategory(object sender, RoutedEventArgs e)
            => ShowPage(new ConfigListPage(this, ConfigEntity.ExpenseCategory));

        // ═══ FEATURE ACTIVATION: Hide/show sidebar menu items based on cloud config ═══
        private void ApplyFeatureActivation()
        {
            try
            {
                var features = Services.FeatureActivationService.GetAllFeatures();
                if (features.Count == 0) return; // No data yet, show all

                // Map menu group titles → feature keys
                var titleToFeatureKey = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase)
                {
                    { "Item", "item" },
                    { "Item Configuration", "item_configuration" },
                    { "Stock", "inventory" },
                    { "Sale", "sale" },
                    { "Sale Return", "sale" },
                    { "Installment Sale", "sale" },
                    { "Customer", "customer" },
                    { "Income", "accounting" },
                    { "Purchase", "purchase" },
                    { "Purchase Return", "purchase" },
                    { "Supplier", "purchase" },
                    { "Expense", "purchase" },
                    { "Transfer", "inventory" },
                    { "Damage", "inventory" },
                    { "Quotation", "sale" },
                    { "Fixed Asset", "inventory" },
                    { "Business Club", "business_club" },
                    { "Price Lists", "item" },
                    { "Warranty & Servicing", "servicing" },
                    { "Payment Account", "accounting" },
                    { "Accounting", "accounting" },
                    { "Marketing", "marketing" },
                    { "Role Permission", "hrm" },
                    { "Employee Account", "hrm" },
                    { "Attendance", "hrm" },
                    { "Salary / Payroll", "hrm" },
                    { "Employee Advance Payment", "hrm" },
                    { "Report", "report" },
                    { "Settings", "settings" },
                    { "Outlet", "outlet" },
                };

                // Map top-level items → feature keys
                var topToFeatureKey = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase)
                {
                    { "POS", "pos" },
                    { "Booking", "booking" },
                };

                // Iterate menuStack children: each Group adds a Button then a StackPanel (sub)
                for (int i = 0; i < menuStack.Children.Count; i++)
                {
                    var child = menuStack.Children[i];
                    if (child is Button btn)
                    {
                        string featureKey = btn.Tag as string;
                        if (string.IsNullOrEmpty(featureKey))
                        {
                            string title = ExtractMenuTitle(btn);
                            if (!string.IsNullOrEmpty(title))
                            {
                                if (titleToFeatureKey.ContainsKey(title))
                                    featureKey = titleToFeatureKey[title];
                                else if (topToFeatureKey.ContainsKey(title))
                                    featureKey = topToFeatureKey[title];
                            }
                        }

                        if (featureKey != null)
                        {
                            bool active = !features.ContainsKey(featureKey) || features[featureKey];
                            btn.Visibility = active ? Visibility.Visible : Visibility.Collapsed;
                            if (!active && i + 1 < menuStack.Children.Count && menuStack.Children[i + 1] is StackPanel sub)
                            {
                                sub.Visibility = Visibility.Collapsed;
                            }
                        }
                    }
                    else if (child is StackPanel subPanel)
                    {
                        // Sub-menu items — har item ka apna feature key (sb.Tag) hota hai.
                        // Agar group ke saare submenu hidden hain to group button bhi hide kar do
                        // (empty group koi kaam ka nahi). Re-enable par sab wapas aana chahiye.
                        var parentBtn = subPanel.Tag as Button;
                        bool anyVisible = false;
                        foreach (var subChild in subPanel.Children)
                        {
                            if (subChild is Button sb && sb.Tag is string skey)
                            {
                                bool active = !features.ContainsKey(skey) || features[skey];
                                sb.Visibility = active ? Visibility.Visible : Visibility.Collapsed;
                                if (active) anyVisible = true;
                            }
                        }

                        if (parentBtn != null)
                        {
                            bool groupActive = true;
                            if (parentBtn.Tag is string gkey && features.ContainsKey(gkey))
                                groupActive = features[gkey];
                            bool shouldHide = !groupActive || !anyVisible;
                            parentBtn.Visibility = shouldHide ? Visibility.Collapsed : Visibility.Visible;
                        }
                    }
                }

                // Also handle XAML-based buttons (in case BuildMenu is ever removed)
                void SetVisible(UIElement el, string key)
                {
                    if (el == null) return;
                    bool active = !features.ContainsKey(key) || features[key];
                    el.Visibility = active ? Visibility.Visible : Visibility.Collapsed;
                }
                SetVisible(btn_pos, "pos");
                SetVisible(btn_sales, "sale");
                SetVisible(sub_sales, "sale");
                SetVisible(btn_purchase_supplier, "purchase");
                SetVisible(sub_purchase_supplier, "purchase");
                SetVisible(btn_items, "item");
                SetVisible(sub_items, "item");
                SetVisible(btn_config, "item_configuration");
                SetVisible(sub_config, "item_configuration");
                SetVisible(btn_payments, "accounting");
                SetVisible(sub_payments, "accounting");
                SetVisible(btn_gst, "gst");
                SetVisible(sub_gst, "gst");
                SetVisible(btn_inventory, "inventory");
                SetVisible(sub_inventory, "inventory");
                SetVisible(btn_reports, "report");
                SetVisible(sub_reports, "report");
                SetVisible(btn_more, "more");
                SetVisible(sub_more, "more");
            }
            catch (Exception ex)
            {
                System.Diagnostics.Debug.WriteLine($"ApplyFeatureActivation error: {ex.Message}");
            }
        }

        private string ExtractMenuTitle(Button btn)
        {
            // The Group() local function sets btn.Content = Grid → StackPanel → TextBlock[1].Text
            if (btn.Content is Grid g)
            {
                foreach (var gc in g.Children)
                {
                    if (gc is StackPanel sp)
                    {
                        foreach (var spc in sp.Children)
                        {
                            if (spc is TextBlock tb && tb.FontWeight == FontWeights.SemiBold)
                                return tb.Text;
                        }
                    }
                }
            }
            // Top() uses StackPanel directly
            if (btn.Content is StackPanel topSp)
            {
                foreach (var spc in topSp.Children)
                {
                    if (spc is TextBlock tb && tb.FontWeight == FontWeights.SemiBold)
                        return tb.Text;
                }
            }
            return null;
        }

        // ═══ AUTO-UPDATE: Manual check from sidebar menu ═══
        private async void ManualCheckForUpdate()
        {
            try
            {
                var updateService = new UpdateService();
                var currentVersion = UpdateService.GetCurrentVersion();
                LogService.Info($"Manual update check triggered (current: {currentVersion})");
                UpdateLogger.Log($"Manual update check (menu) — current version: {currentVersion}");

                var updateInfo = await updateService.CheckForUpdateAsync();

                if (updateInfo == null)
                {
                    UpdateLogger.Log("Menu check result: software up-to-date");
                    MessageBox.Show(
                        $"You are running the latest version ({currentVersion}).\n\nNo updates available.",
                        "Check for Updates",
                        MessageBoxButton.OK, MessageBoxImage.Information);
                    return;
                }

                // Show notification dialog
                var dialog = new UpdateNotificationDialog { Owner = this };
                dialog.SetVersions(currentVersion, updateInfo);
                var result = dialog.ShowDialog();

                if (result != true || !dialog.UserClickedUpdate) return;

                // Download update
                var progressDialog = new UpdateProgressDialog { Owner = this };
                progressDialog.SetVersion(updateInfo.Version);
                var cts = new CancellationTokenSource();
                progressDialog.SetCancellationTokenSource(cts);

                var progressReporter = new Progress<UpdateProgress>(progress =>
                {
                    progressDialog.UpdateProgress(progress);
                });

                progressDialog.Show();
                bool downloadComplete = await updateService.DownloadUpdateAsync(updateInfo, progressReporter, cts.Token);
                progressDialog.Close();

                if (!downloadComplete || progressDialog.UserCancelled) return;

                // Show restart prompt
                var readyDialog = new UpdateReadyDialog { Owner = this };
                readyDialog.SetVersion(updateInfo.Version);
                var readyResult = readyDialog.ShowDialog();

                if (readyResult == true && readyDialog.UserClickedRestart)
                {
                    updateService.LaunchUpdater(updateInfo);
                }
            }
            catch (Exception ex)
            {
                LogService.Warn($"Manual update check error: {ex.Message}");
                MessageBox.Show(
                    $"Could not check for updates.\n\n{ex.Message}",
                    "Check for Updates",
                    MessageBoxButton.OK, MessageBoxImage.Warning);
            }
        }
    }
}
