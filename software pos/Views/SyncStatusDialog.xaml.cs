using System;
using System.Windows;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Threading;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views
{
    /// <summary>
    /// Corner "Synced" status par click → popup:
    /// live sync status + auto-timer (chal raha hai ya nahi, kitne second
    /// mein next tick) + last cycle result + Resync / Sync Monitor shortcuts.
    /// 1s DispatcherTimer se live refresh.
    /// </summary>
    public partial class SyncStatusDialog : Window
    {
        private readonly ApiService _api;
        private readonly SyncService _sync;
        private readonly DispatcherTimer _refresh;

        public SyncStatusDialog(ApiService api, SyncService sync)
        {
            InitializeComponent();
            _api = api;
            _sync = sync;
            PreviewKeyDown += Window_PreviewKeyDown;
            _refresh = new DispatcherTimer { Interval = TimeSpan.FromSeconds(1) };
            _refresh.Tick += (_, _) => RefreshUi();
            _refresh.Start();
            Loaded += (_, _) => RefreshUi();
        }

        private void Window_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Escape) Close();
        }

        private void RefreshUi()
        {
            try
            {
                var s = _sync.LastStatus;
                string color = s.Syncing ? "#F59E0B" : s.Connected ? "#10B981" : "#EF4444";
                dStatusDot.Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString(color));
                dStatus.Foreground = new SolidColorBrush((Color)ColorConverter.ConvertFromString(color));
                dStatus.Text = s.Syncing ? "Syncing…" : s.Connected ? "Synced" : "Sync Issue";
                dDetail.Text = s.Syncing
                    ? (s.Message ?? "Cycle chalu hai…")
                    : (s.Message ?? "");

                badgeSyncing.Visibility = s.Syncing ? Visibility.Visible : Visibility.Collapsed;

                // Timer row: chalu / band + next tick countdown + interval
                if (_sync.SyncingSince.HasValue)
                {
                    dTimer.Text = "Cycle chalu — " + _sync.SyncingSince.Value.ToString("HH:mm:ss");
                }
                else if (_sync.IsTimerRunning)
                {
                    double secs = Math.Max(0, (_sync.NextTickAt - DateTime.Now).TotalSeconds);
                    dTimer.Text = _sync.IsTimerRunning
                        ? $"Chal raha hai — every {_sync.TimerInterval.TotalSeconds:0}s · next tick in {secs:0}s"
                        : "Band hai";
                }
                else
                {
                    dTimer.Text = "Band hai (StopAutoSync)";
                }

                dLastSync.Text = s.LastSyncAt.HasValue
                    ? s.LastSyncAt.Value.ToString("dd-MM-yyyy  HH:mm:ss")
                    : "—";

                dLastCycle.Text = $"{s.Pushed} pushed · {s.Pulled} pulled";

                dPending.Text = _sync.HasPendingLocalData ? "Hai — sync pending" : "Kuch nahi";

                dServer.Text = _sync.LastStatus.Connected ? "Connected" : "Not reachable";
            }
            catch { }
        }

        private async void BtnResync_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                btnResync.IsEnabled = false;
                dStatus.Text = "Starting…";
                await _sync.SyncNowAsync(forcePull: true);
            }
            catch { }
            finally
            {
                btnResync.IsEnabled = true;
                RefreshUi();
            }
        }

        private void BtnMonitor_Click(object sender, RoutedEventArgs e)
        {
            var dialog = new DeadLettersDialog(_api, _sync) { Owner = this };
            dialog.ShowDialog();
            RefreshUi();
        }

        private void BtnClose_Click(object sender, RoutedEventArgs e) => Close();
    }
}