using System.Windows;
using System.Windows.Media;

namespace RashanKiDukan.Views;

public partial class UpdateProgressDialog : Window
{
    private CancellationTokenSource? _cts;
    public bool UserCancelled { get; private set; }

    public UpdateProgressDialog()
    {
        InitializeComponent();
    }

    public void SetVersion(string version)
    {
        txtVersion.Text = $"Version {version}";
    }

    public void UpdateProgress(Services.UpdateProgress progress)
    {
        try
        {
            Dispatcher.Invoke(() =>
            {
                double pct = progress.Percentage;
                txtPercentage.Text = $"{pct:F0}%";

                // Update progress bar width (parent border = 404px approx)
                double maxWidth = ActualWidth > 0 ? ActualWidth - 56 : 400;
                progressFill.Width = Math.Max(0, maxWidth * pct / 100.0);

                txtDownloaded.Text = FormatBytes(progress.DownloadedBytes);
                txtTotal.Text = progress.HasTotalBytes ? FormatBytes(progress.TotalBytes) : "?? MB";
                txtTotal.Visibility = progress.HasTotalBytes ? System.Windows.Visibility.Visible : System.Windows.Visibility.Collapsed;

                // Remaining = total - downloaded (sirf jab total pata ho)
                if (progress.HasTotalBytes)
                {
                    long remaining = Math.Max(0, progress.TotalBytes - progress.DownloadedBytes);
                    txtRemaining.Text = $"{FormatBytes(remaining)} remaining";
                }
                else
                {
                    txtRemaining.Text = "";
                }

                // Speed (raw) + ETA
                string speedText = progress.SpeedBytesPerSecRaw > 0
                    ? $"Speed: {FormatBytes((long)progress.SpeedBytesPerSecRaw)}/s"
                    : "";
                txtSpeed.Text = speedText;

                if (progress.SpeedBytesPerSecRaw > 0 && progress.HasTotalBytes)
                {
                    long remaining = Math.Max(0, progress.TotalBytes - progress.DownloadedBytes);
                    double etaSec = remaining / progress.SpeedBytesPerSecRaw;
                    if (etaSec <= 0) { txtEta.Text = ""; }
                    else
                    {
                        TimeSpan t = TimeSpan.FromSeconds(etaSec);
                        txtEta.Text = t.TotalMinutes >= 1
                            ? $"  •  ETA: {t.Minutes}m {t.Seconds}s"
                            : $"  •  ETA: {t.Seconds}s";
                    }
                }
                else
                {
                    txtEta.Text = "";
                }

                switch (progress.Status)
                {
                    case Services.UpdateProgressStatus.Downloading:
                        txtStatus.Text = "Downloading...";
                        break;
                    case Services.UpdateProgressStatus.Verifying:
                        txtStatus.Text = "Verifying package integrity...";
                        txtSpeed.Text = "";
                        txtEta.Text = "";
                        txtRemaining.Text = "";
                        break;
                    case Services.UpdateProgressStatus.DownloadComplete:
                        txtStatus.Text = "Download complete! Installing... (UAC 'Yes' karo)";
                        txtSpeed.Text = "";
                        txtEta.Text = "";
                        txtRemaining.Text = "";
                        break;
                    case Services.UpdateProgressStatus.Error:
                        txtStatus.Text = $"Error: {progress.ErrorMessage}";
                        txtStatus.Foreground = FindResource("DangerBrush") as Brush ?? Brushes.Red;
                        break;
                }
            });
        }
        catch { }
    }

    private void BtnCancel_Click(object sender, RoutedEventArgs e)
    {
        UserCancelled = true;
        _cts?.Cancel();
        Close();
    }

    private void Window_Closing(object? sender, System.ComponentModel.CancelEventArgs e)
    {
        // If user clicks X, treat as cancel
        if (!UserCancelled)
        {
            UserCancelled = true;
            _cts?.Cancel();
        }
    }

    public void SetCancellationTokenSource(CancellationTokenSource cts)
    {
        _cts = cts;
    }

    private static string FormatBytes(long bytes)
    {
        if (bytes >= 1073741824) return $"{bytes / 1073741824.0:F1} GB";
        if (bytes >= 1048576) return $"{bytes / 1048576.0:F1} MB";
        if (bytes >= 1024) return $"{bytes / 1024.0:F1} KB";
        return $"{bytes} B";
    }
}
