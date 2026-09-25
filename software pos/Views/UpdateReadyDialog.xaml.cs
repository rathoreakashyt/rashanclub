using System.Windows;

namespace RashanKiDukan.Views;

public partial class UpdateReadyDialog : Window
{
    public bool UserClickedRestart { get; private set; }

    public UpdateReadyDialog()
    {
        InitializeComponent();
    }

    public void SetVersion(string version)
    {
        txtVersion.Text = $"Version {version} has been downloaded successfully.";
    }

    private bool _handled;

    private void BtnRestart_Click(object sender, RoutedEventArgs e)
    {
        if (_handled || !IsLoaded) return; // double-click guard
        _handled = true;
        UserClickedRestart = true;
        try { DialogResult = true; } catch { }
        Close();
    }

    private void BtnCancel_Click(object sender, RoutedEventArgs e)
    {
        if (_handled || !IsLoaded) return; // double-click guard
        _handled = true;
        try { DialogResult = false; } catch { }
        Close();
    }
}
