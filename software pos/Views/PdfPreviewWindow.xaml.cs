using System;
using System.Diagnostics;
using System.IO;
using System.Windows;
using System.Windows.Input;
using Microsoft.Web.WebView2.Core;

namespace RashanKiDukan.Views
{
    /// <summary>
    /// In-app PDF previewer: kisi bhi PDF (hamra bill ho ya koi aur) ko
    /// app ke andar hi kholta hai — WebView2 (Chromium) render karta hai.
    /// Print / Open / External / drag-drop sab built-in.
    /// </summary>
    public partial class PdfPreviewWindow : Window
    {
        private string _currentFile;
        private bool _initOk;
        // Init fail hone par window pehle hi close ho chuki hoti hai — Show() race guard
        internal bool _closedEarly;

        public PdfPreviewWindow()
        {
            InitializeComponent();

            // WebView2 user-data folder writable location me — default install-dir
            // (Program Files) me folder banne par E_ACCESSDENIED (0x80070005) aata tha.
            // Source assign hone se PEHLE set karna zaroori hai.
            try
            {
                var userDataFolder = Path.Combine(
                    Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                    "RashanKiDukan", "WebView2");
                Directory.CreateDirectory(userDataFolder);
                webView.CreationProperties ??= new Microsoft.Web.WebView2.Wpf.CoreWebView2CreationProperties();
                if (string.IsNullOrEmpty(webView.CreationProperties.UserDataFolder))
                    webView.CreationProperties.UserDataFolder = userDataFolder;
            }
            catch { }
        }

        /// <summary>PDF ko previewer me kholo (non-modal).</summary>
        public static void ShowPdf(string filePath, string title = null)
        {
            try
            {
                if (string.IsNullOrWhiteSpace(filePath) || !File.Exists(filePath))
                {
                    MessageBox.Show("PDF file nahi mili:\n" + filePath, "PDF Preview",
                        MessageBoxButton.OK, MessageBoxImage.Warning);
                    return;
                }

                var win = new PdfPreviewWindow();
                win.LoadPdf(filePath, title);
                // Closed-window race guard: init fail hone par win khud Close() kar
                // leta hai — us case me Show() mat karo (WPF exception deta hai)
                if (!win._closedEarly) win.Show();
            }
            catch (Exception ex)
            {
                MessageBox.Show("PDF preview khulne me error: " + ex.Message, "PDF Preview",
                    MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void LoadPdf(string filePath, string title = null)
        {
            _currentFile = filePath;
            Title = title ?? "PDF Preview — " + Path.GetFileName(filePath);
            lblFileName.Text = filePath;
            lblStatus.Text = "Loading…";

            var uri = new Uri(filePath);
            if (webView.CoreWebView2 != null)
                webView.CoreWebView2.Navigate(uri.AbsoluteUri);
            else
                webView.Source = uri; // init hone ke baad auto-navigate ho jayega
        }

        // ───────────── WebView2 events ─────────────

        private void WebView_CoreWebView2InitializationCompleted(object sender, CoreWebView2InitializationCompletedEventArgs e)
        {
            if (e.IsSuccess)
            {
                _initOk = true;
                // File access to same-directory assets allow karo (agar PDF external
                // resources use kare) — PDFs ke liye generally zaroori nahi.
                Dispatcher.Invoke(() =>
                {
                    try { webView.CoreWebView2.Settings.AreDefaultContextMenusEnabled = true; } catch { }
                });
                return;
            }

            Dispatcher.Invoke(() =>
            {
                _initOk = false;
                _closedEarly = true; // ShowPdf() is window ko Show() nahi karega
                var msg = "WebView2 runtime install nahi hai, isliye in-app preview available nahi." +
                          Environment.NewLine + Environment.NewLine +
                          "Fix: https://developer.microsoft.com/microsoft-edge/webview2/ se" +
                          " 'Evergreen Runtime' install karo, ya app ko restart karo." +
                          Environment.NewLine + Environment.NewLine +
                          (e.InitializationException != null ? e.InitializationException.Message : "");
                MessageBox.Show(msg, "PDF Preview", MessageBoxButton.OK, MessageBoxImage.Warning);
                TryOpenExternal();
                Close();
            });
        }

        private void WebView_NavigationCompleted(object sender, CoreWebView2NavigationCompletedEventArgs e)
        {
            Dispatcher.Invoke(() =>
            {
                if (e.IsSuccess)
                    lblStatus.Text = _currentFile != null ? Path.GetFileName(_currentFile) : "Ready";
                else
                {
                    lblStatus.Text = "Load failed";
                    MessageBox.Show("PDF load nahi ho saki:\n" + _currentFile, "PDF Preview",
                        MessageBoxButton.OK, MessageBoxImage.Error);
                }
            });
        }

        // ───────────── Toolbar actions ─────────────

        private void BtnOpen_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new Microsoft.Win32.OpenFileDialog
            {
                Title = "PDF kholo",
                Filter = "PDF files (*.pdf)|*.pdf|All files (*.*)|*.*",
                DefaultExt = "pdf"
            };
            if (dlg.ShowDialog() == true)
                LoadPdf(dlg.FileName);
        }

        private void BtnPrint_Click(object sender, RoutedEventArgs e)
        {
            if (_currentFile == null)
            {
                MessageBox.Show("Pehle koi PDF open karo.", "PDF Preview",
                    MessageBoxButton.OK, MessageBoxImage.Information);
                return;
            }
            try
            {
                if (webView.CoreWebView2 != null)
                {
                    webView.CoreWebView2.ExecuteScriptAsync("window.print();");
                    return;
                }
                TryOpenExternal();
            }
            catch (Exception ex)
            {
                MessageBox.Show("Print error: " + ex.Message, "PDF Preview",
                    MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void BtnExternal_Click(object sender, RoutedEventArgs e) => TryOpenExternal();

        private void TryOpenExternal()
        {
            if (string.IsNullOrEmpty(_currentFile) || !File.Exists(_currentFile)) return;
            try
            {
                Process.Start(new ProcessStartInfo(_currentFile) { UseShellExecute = true });
            }
            catch (Exception ex)
            {
                MessageBox.Show("External open error: " + ex.Message, "PDF Preview",
                    MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        // ───────────── Drag & drop ─────────────

        private void Window_DragOver(object sender, DragEventArgs e)
        {
            e.Effects = e.Data.GetDataPresent(DataFormats.FileDrop) ? DragDropEffects.Copy : DragDropEffects.None;
            e.Handled = true;
        }

        private void Window_Drop(object sender, DragEventArgs e)
        {
            if (e.Data.GetData(DataFormats.FileDrop) is string[] files)
            {
                foreach (var f in files)
                {
                    if (string.Equals(Path.GetExtension(f), ".pdf", StringComparison.OrdinalIgnoreCase))
                    {
                        LoadPdf(f);
                        return;
                    }
                }
            }
        }

        // ───────────── Keyboard shortcuts ─────────────

        protected override void OnPreviewKeyDown(KeyEventArgs e)
        {
            if (e.Key == Key.Escape)
            {
                Close();
                e.Handled = true;
                return;
            }
            if (Keyboard.Modifiers == ModifierKeys.Control && e.Key == Key.O)
            {
                BtnOpen_Click(this, null);
                e.Handled = true;
                return;
            }
            if (Keyboard.Modifiers == ModifierKeys.Control && e.Key == Key.P)
            {
                BtnPrint_Click(this, null);
                e.Handled = true;
                return;
            }
            base.OnPreviewKeyDown(e);
        }
    }
}
