using System.Windows;

namespace RashanKiDukan.Views;

public partial class UpdateNotificationDialog : Window
{
    public bool UserClickedUpdate { get; private set; }

    public UpdateNotificationDialog()
    {
        InitializeComponent();
    }

    public void SetVersions(string currentVersion, Services.UpdateInfo info)
    {
        txtCurrentVersion.Text = currentVersion;
        txtNewVersion.Text = info.Version;

        if (!string.IsNullOrEmpty(info.ReleaseDate))
            txtReleaseDate.Text = $"Released: {info.ReleaseDate}";

        if (info.ReleaseNotes.Count > 0)
            txtReleaseNotes.Text = string.Join("\n", info.ReleaseNotes.Select(n => $"  •  {n}"));
        else
            txtReleaseNotes.Text = "  •  Improvements and bug fixes";

        if (info.Mandatory)
        {
            bdMandatory.Visibility = Visibility.Visible;
            btnSkip.Visibility = Visibility.Collapsed;
        }
    }

    private bool _handled;

    private void BtnUpdate_Click(object sender, RoutedEventArgs e)
    {
        // Double-click / closing-race guard: pehle hi click me handle ho chuka
        if (_handled || !IsLoaded) return;
        _handled = true;
        btnUpdate.IsEnabled = false;
        btnSkip.IsEnabled = false;
        UserClickedUpdate = true;
        try { DialogResult = true; } catch { }
        Close();
    }

    private void BtnSkip_Click(object sender, RoutedEventArgs e)
    {
        if (_handled || !IsLoaded) return;
        _handled = true;
        btnUpdate.IsEnabled = false;
        btnSkip.IsEnabled = false;
        try { DialogResult = false; } catch { }
        Close();
    }
}
