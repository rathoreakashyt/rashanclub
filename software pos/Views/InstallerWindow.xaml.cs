using System;
using System.Diagnostics;
using System.IO;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;

namespace RashanKiDukan.Views
{
    /// <summary>
    /// First-run installer window (in-app setup wizard).
    /// Welcome → Terms accept → Install (blue progress) → Files C:\RashanKiDukan me copy → Done → Login.
    /// </summary>
    public partial class InstallerWindow : Window
    {
        private enum Step { Welcome = 0, License = 1, Installing = 2, Done = 3 }

        private Step current = Step.Welcome;

        // Panels per step
        private Border pnlWelcome;
        private Border pnlLicense;
        private Border pnlInstalling;
        private Border pnlDone;

        // Welcome
        private TextBlock txtWelcomePath;

        // License
        private CheckBox chkLicense;
        private Button btnNext;
        private Button btnBack;
        private Button btnCancel;

        // Installing
        private Border progressFill;
        private TextBlock txtStatus;
        private TextBlock txtPercent;

        // Done
        private TextBlock txtDonePath;
        private CheckBox chkLaunch;

        private readonly string installDir = @"C:\RashanKiDukan";
        private string sourceDir;                       // exe ke sath wali "app" folder (files yahin se copy hoti hain)
        private string selfExe;
        private volatile bool installing;

        // ═══ Sneat colors (App.xaml design system) ═══
        private static readonly Color C_PRIMARY = Color.FromRgb(0x69, 0x6C, 0xFF);
        private static readonly Color C_PRIMARY_PRESSED = Color.FromRgb(0x4A, 0x4C, 0xD6);
        private static readonly Color C_DARK = Color.FromRgb(0x11, 0x11, 0x11);
        private static readonly Color C_MUTED = Color.FromRgb(0x33, 0x33, 0x33);
        private static readonly Color C_FAINT = Color.FromRgb(0x66, 0x66, 0x66);
        private static readonly Color C_BORDER = Color.FromRgb(0xEA, 0xEA, 0xEC);
        private static readonly Color C_TRACK = Color.FromRgb(0xEE, 0xEF, 0xF2);
        private static readonly Color C_SUCCESS = Color.FromRgb(0x4C, 0xAF, 0x50);

        public InstallerWindow()
        {
            selfExe = Process.GetCurrentProcess().MainModule?.FileName ?? Environment.ProcessPath ?? "";
            sourceDir = Path.Combine(AppContext.BaseDirectory, "app");

            // Agar "app" folder nahi hai (single exe distribution), source = exe ka apna folder
            if (!Directory.Exists(sourceDir))
                sourceDir = AppContext.BaseDirectory;

            Width = 560;
            Height = 440;
            WindowStartupLocation = WindowStartupLocation.CenterScreen;
            ResizeMode = ResizeMode.NoResize;
            Title = "RashanKiDukan POS — Setup";
            Background = new SolidColorBrush(Color.FromRgb(0xF8, 0xF7, 0xFA));

            BuildWizard();
            ShowStep(Step.Welcome);
        }

        // ═══════════════════════════ UI BUILD ═══════════════════════════
        private void BuildWizard()
        {
            var root = new Grid();
            root.RowDefinitions.Add(new RowDefinition { Height = new GridLength(84) });
            root.RowDefinitions.Add(new RowDefinition { Height = new GridLength(1, GridUnitType.Star) });
            root.RowDefinitions.Add(new RowDefinition { Height = new GridLength(68) });

            // ─── Header (dark) ───
            var header = new Border { Background = new SolidColorBrush(Color.FromRgb(0x23, 0x26, 0x31)) };
            var hStack = new StackPanel { VerticalAlignment = VerticalAlignment.Center, Margin = new Thickness(24, 0, 0, 0) };
            hStack.Children.Add(new TextBlock
            {
                Text = "RashanKiDukan POS",
                FontSize = 20,
                FontWeight = FontWeights.SemiBold,
                Foreground = Brushes.White
            });
            hStack.Children.Add(new TextBlock
            {
                Text = "First-time Setup Wizard",
                FontSize = 12,
                Foreground = new SolidColorBrush(Color.FromRgb(0x9A, 0x9A, 0xA2)),
                Margin = new Thickness(0, 3, 0, 0)
            });
            header.Child = hStack;
            Grid.SetRow(header, 0);
            root.Children.Add(header);

            // ─── Content host ───
            var content = new Grid { Margin = new Thickness(24, 20, 24, 8) };
            Grid.SetRow(content, 1);

            pnlWelcome = BuildWelcomePanel();
            pnlLicense = BuildLicensePanel();
            pnlInstalling = BuildInstallingPanel();
            pnlDone = BuildDonePanel();

            foreach (var p in new[] { pnlWelcome, pnlLicense, pnlInstalling, pnlDone })
            {
                p.Visibility = Visibility.Collapsed;
                content.Children.Add(p);
            }
            root.Children.Add(content);

            // ─── Footer ───
            var footer = new Border
            {
                Background = Brushes.White,
                BorderBrush = new SolidColorBrush(C_BORDER),
                BorderThickness = new Thickness(0, 1, 0, 0)
            };
            var footGrid = new Grid { Margin = new Thickness(24, 0, 24, 0) };
            footGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            footGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
            footGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

            btnCancel = MakeButton("Cancel", false);
            btnCancel.Click += (s, e) =>
            {
                if (installing)
                {
                    var r = MessageBox.Show("Installation is in progress. Cancel and exit setup?",
                        "Cancel Installation", MessageBoxButton.YesNo, MessageBoxImage.Question);
                    if (r != MessageBoxResult.Yes) return;
                }
                Application.Current.Shutdown();
            };
            Grid.SetColumn(btnCancel, 0);
            btnCancel.HorizontalAlignment = HorizontalAlignment.Left;
            footGrid.Children.Add(btnCancel);

            btnBack = MakeButton("← Back", false);
            btnBack.Margin = new Thickness(0, 0, 10, 0);
            btnBack.Click += (s, e) => { if (current > Step.Welcome && current < Step.Installing) ShowStep(current - 1); };
            Grid.SetColumn(btnBack, 1);
            footGrid.Children.Add(btnBack);

            btnNext = MakeButton("Next →", true);
            btnNext.MinWidth = 110;
            btnNext.Click += Next_Click;
            Grid.SetColumn(btnNext, 2);
            footGrid.Children.Add(btnNext);

            footer.Child = footGrid;
            Grid.SetRow(footer, 2);
            root.Children.Add(footer);

            Content = root;
        }

        private Border BuildWelcomePanel()
        {
            var card = MakeCard();
            var stack = new StackPanel { VerticalAlignment = VerticalAlignment.Center };

            stack.Children.Add(new TextBlock
            {
                Text = "📦",
                FontSize = 42,
                HorizontalAlignment = HorizontalAlignment.Center,
                Margin = new Thickness(0, 0, 0, 12)
            });
            stack.Children.Add(new TextBlock
            {
                Text = "Welcome to RashanKiDukan POS",
                FontSize = 19,
                FontWeight = FontWeights.SemiBold,
                Foreground = new SolidColorBrush(C_DARK),
                HorizontalAlignment = HorizontalAlignment.Center
            });
            stack.Children.Add(new TextBlock
            {
                Text = "This wizard will set up the application on your computer.\nFiles will be installed and a shortcut will be created.",
                FontSize = 13,
                Foreground = new SolidColorBrush(C_MUTED),
                TextAlignment = TextAlignment.Center,
                Margin = new Thickness(0, 10, 0, 16)
            });

            var pathBorder = new Border
            {
                Background = new SolidColorBrush(Color.FromRgb(0xF5, 0xF5, 0xFA)),
                BorderBrush = new SolidColorBrush(C_BORDER),
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(7),
                Padding = new Thickness(14, 10, 14, 10),
                Margin = new Thickness(40, 0, 40, 0)
            };
            txtWelcomePath = new TextBlock
            {
                Text = "Install location:  " + installDir,
                FontSize = 12.5,
                Foreground = new SolidColorBrush(C_DARK),
                TextAlignment = TextAlignment.Center
            };
            pathBorder.Child = txtWelcomePath;
            stack.Children.Add(pathBorder);

            card.Child = stack;
            return card;
        }

        private Border BuildLicensePanel()
        {
            var card = MakeCard();
            var stack = new StackPanel();

            stack.Children.Add(new TextBlock
            {
                Text = "License Agreement",
                FontSize = 17,
                FontWeight = FontWeights.SemiBold,
                Foreground = new SolidColorBrush(C_DARK)
            });
            stack.Children.Add(new TextBlock
            {
                Text = "Please review the terms before installing:",
                FontSize = 12.5,
                Foreground = new SolidColorBrush(C_MUTED),
                Margin = new Thickness(0, 4, 0, 10)
            });

            var termsBox = new TextBox
            {
                Text =
                    "RashanKiDukan POS — End User License Agreement\n" +
                    "================================================\n\n" +
                    "1. This software is licensed for use in your own business premises.\n\n" +
                    "2. You may install this software on computers you own or control.\n\n" +
                    "3. Local data (database, invoices, settings) stays on your computer.\n\n" +
                    "4. Redistribution, reverse engineering or resale of this software\n" +
                    "   without permission is not allowed.\n\n" +
                    "5. THE SOFTWARE IS PROVIDED \"AS IS\", WITHOUT WARRANTY OF ANY KIND.\n" +
                    "   The authors are not liable for any damages arising from its use.",
                IsReadOnly = true,
                FontSize = 12,
                FontFamily = new FontFamily("Consolas"),
                Background = Brushes.White,
                BorderBrush = new SolidColorBrush(C_BORDER),
                BorderThickness = new Thickness(1),
                Padding = new Thickness(10),
                VerticalScrollBarVisibility = ScrollBarVisibility.Auto,
                Height = 170,
                TextWrapping = TextWrapping.NoWrap
            };
            stack.Children.Add(termsBox);

            chkLicense = new CheckBox
            {
                Content = "I accept the terms of this agreement",
                FontSize = 13,
                Foreground = new SolidColorBrush(C_DARK),
                Margin = new Thickness(2, 12, 0, 0)
            };
            chkLicense.Checked += (s, e) => { if (current == Step.License) btnNext.IsEnabled = true; };
            chkLicense.Unchecked += (s, e) => { if (current == Step.License) btnNext.IsEnabled = false; };
            stack.Children.Add(chkLicense);

            card.Child = stack;
            return card;
        }

        private Border BuildInstallingPanel()
        {
            var card = MakeCard();
            var stack = new StackPanel { VerticalAlignment = VerticalAlignment.Center };

            stack.Children.Add(new TextBlock
            {
                Text = "Installing...",
                FontSize = 17,
                FontWeight = FontWeights.SemiBold,
                Foreground = new SolidColorBrush(C_DARK)
            });

            txtStatus = new TextBlock
            {
                Text = "Preparing...",
                FontSize = 12.5,
                Foreground = new SolidColorBrush(C_MUTED),
                Margin = new Thickness(0, 6, 0, 0)
            };
            stack.Children.Add(txtStatus);

            // ─── Blue patti (custom smooth progress bar) ───
            var track = new Border
            {
                Height = 14,
                Background = new SolidColorBrush(C_TRACK),
                CornerRadius = new CornerRadius(7),
                Margin = new Thickness(0, 14, 0, 4)
            };
            var grid = new Grid();
            progressFill = new Border
            {
                Background = new LinearGradientBrush(
                    Color.FromRgb(0x5F, 0x61, 0xE6),
                    Color.FromRgb(0x85, 0x87, 0xFF), 90),
                CornerRadius = new CornerRadius(7),
                Width = 0,
                HorizontalAlignment = HorizontalAlignment.Left
            };
            grid.Children.Add(progressFill);
            track.Child = grid;
            stack.Children.Add(track);

            txtPercent = new TextBlock
            {
                Text = "0%",
                FontSize = 13,
                FontWeight = FontWeights.SemiBold,
                Foreground = new SolidColorBrush(C_PRIMARY),
                HorizontalAlignment = HorizontalAlignment.Right,
                Margin = new Thickness(0, 2, 0, 0)
            };
            stack.Children.Add(txtPercent);

            stack.Children.Add(new TextBlock
            {
                Text = "Please wait while files are being installed.\nThis may take a few minutes.",
                FontSize = 11.5,
                Foreground = new SolidColorBrush(C_FAINT),
                TextAlignment = TextAlignment.Center,
                Margin = new Thickness(0, 14, 0, 0)
            });

            card.Child = stack;
            return card;
        }

        private Border BuildDonePanel()
        {
            var card = MakeCard();
            var stack = new StackPanel { VerticalAlignment = VerticalAlignment.Center };

            stack.Children.Add(new TextBlock
            {
                Text = "✅",
                FontSize = 40,
                HorizontalAlignment = HorizontalAlignment.Center
            });
            stack.Children.Add(new TextBlock
            {
                Text = "Installation Complete!",
                FontSize = 19,
                FontWeight = FontWeights.SemiBold,
                Foreground = new SolidColorBrush(C_SUCCESS),
                HorizontalAlignment = HorizontalAlignment.Center,
                Margin = new Thickness(0, 8, 0, 0)
            });
            txtDonePath = new TextBlock
            {
                Text = "RashanKiDukan POS has been installed successfully.\nInstalled to: " + installDir,
                FontSize = 12.5,
                Foreground = new SolidColorBrush(C_MUTED),
                TextAlignment = TextAlignment.Center,
                Margin = new Thickness(0, 10, 0, 14)
            };
            stack.Children.Add(txtDonePath);

            chkLaunch = new CheckBox
            {
                Content = "Launch RashanKiDukan POS now",
                FontSize = 13,
                IsChecked = true,
                HorizontalAlignment = HorizontalAlignment.Center
            };
            stack.Children.Add(chkLaunch);

            card.Child = stack;
            return card;
        }

        // ═══════════════════════════ NAVIGATION ═══════════════════════════
        private void ShowStep(Step step)
        {
            current = step;

            pnlWelcome.Visibility = step == Step.Welcome ? Visibility.Visible : Visibility.Collapsed;
            pnlLicense.Visibility = step == Step.License ? Visibility.Visible : Visibility.Collapsed;
            pnlInstalling.Visibility = step == Step.Installing ? Visibility.Visible : Visibility.Collapsed;
            pnlDone.Visibility = step == Step.Done ? Visibility.Visible : Visibility.Collapsed;

            btnBack.IsEnabled = step > Step.Welcome && step < Step.Installing;
            btnCancel.IsEnabled = step != Step.Installing;

            switch (step)
            {
                case Step.Welcome:
                    btnNext.Content = "Next →";
                    btnNext.IsEnabled = true;
                    break;
                case Step.License:
                    btnNext.Content = "Next →";
                    btnNext.IsEnabled = chkLicense.IsChecked == true;
                    break;
                case Step.Installing:
                    btnNext.Content = "Next →";
                    btnNext.IsEnabled = false;
                    break;
                case Step.Done:
                    btnNext.Content = "Finish";
                    btnNext.IsEnabled = true;
                    btnCancel.Visibility = Visibility.Hidden;
                    break;
            }
        }

        private async void Next_Click(object sender, RoutedEventArgs e)
        {
            switch (current)
            {
                case Step.Welcome:
                    ShowStep(Step.License);
                    break;

                case Step.License:
                    ShowStep(Step.Installing);
                    try
                    {
                        await DoInstallAsync();
                    }
                    catch (Exception ex)
                    {
                        installing = false;
                        MessageBox.Show("Installation failed:\n\n" + ex.Message,
                            "Setup Error", MessageBoxButton.OK, MessageBoxImage.Error);
                        SetProgress(0);
                        ShowStep(Step.License);
                    }
                    break;

                case Step.Done:
                    FinishAndContinue();
                    break;
            }
        }

        // ═══════════════════════════ INSTALL LOGIC ═══════════════════════════
        private async Task DoInstallAsync()
        {
            installing = true;
            SetStatus("Preparing installation...");
            SetProgress(2);
            await Task.Delay(250);

            // Already installed at target? (e.g. running from C:\RashanKiDukan itself)
            bool sameAsTarget = string.Equals(
                TrimSlash(AppContext.BaseDirectory), TrimSlash(installDir + "\\"),
                StringComparison.OrdinalIgnoreCase);

            if (!sameAsTarget)
            {
                if (!Directory.Exists(installDir))
                    Directory.CreateDirectory(installDir);

                // Files list — "app" folder ka content + khud ka exe
                var files = new System.Collections.Generic.List<string>();
                files.AddRange(Directory.GetFiles(sourceDir, "*", SearchOption.AllDirectories));
                if (!string.IsNullOrEmpty(selfExe) && File.Exists(selfExe))
                    files.Add(selfExe);

                long totalBytes = 0;
                foreach (var f in files)
                    totalBytes += new FileInfo(f).Length;
                if (totalBytes <= 0) totalBytes = 1;

                long copied = 0;
                for (int i = 0; i < files.Count; i++)
                {
                    string src = files[i];

                    // Self-exe → installDir\RashanKiDukan.exe; baaki relative path se
                    string rel = src.StartsWith(sourceDir, StringComparison.OrdinalIgnoreCase)
                        ? src.Substring(sourceDir.Length).TrimStart('\\', '/')
                        : Path.GetFileName(src);
                    string dest = Path.Combine(installDir, rel);

                    string dir = Path.GetDirectoryName(dest);
                    if (!string.IsNullOrEmpty(dir) && !Directory.Exists(dir))
                        Directory.CreateDirectory(dir);

                    // Agar dest == src (already installed location me se chal raha hai) skip
                    if (!string.Equals(src, dest, StringComparison.OrdinalIgnoreCase))
                        File.Copy(src, dest, true);

                    copied += new FileInfo(src).Length;
                    int pct = 2 + (int)((double)copied / totalBytes * 88);

                    string name = Path.GetFileName(src);
                    SetStatus("Installing: " + name);
                    SetProgress(pct);
                    await Task.Delay(1); // UI ko update ka mauka
                }
            }
            else
            {
                SetStatus("Files already in place...");
                SetProgress(90);
                await Task.Delay(200);
            }

            // Start Menu shortcut (always)
            SetStatus("Creating Start Menu shortcut...");
            SetProgress(93);
            CreateShortcut(GetStartMenuDir());

            // Desktop shortcut
            SetStatus("Creating desktop shortcut...");
            SetProgress(95);
            CreateShortcut(GetDesktopDir());

            // Uninstaller batch
            SetStatus("Creating uninstaller...");
            SetProgress(97);
            WriteUninstaller();

            SetStatus("Finalizing...");
            SetProgress(100);
            await Task.Delay(350);

            installing = false;
            txtDonePath.Text = "RashanKiDukan POS has been installed successfully.\nInstalled to: " + installDir;
            ShowStep(Step.Done);
        }

        private void FinishAndContinue()
        {
            bool launch = chkLaunch.IsChecked == true;
            string exePath = Path.Combine(installDir, "RashanKiDukan.exe");
            bool runningFromInstall = string.Equals(
                TrimSlash(AppContext.BaseDirectory), TrimSlash(installDir + "\\"),
                StringComparison.OrdinalIgnoreCase);

            if (launch && File.Exists(exePath) && !runningFromInstall)
            {
                // Installed copy se restart (fresh install location se chale)
                try
                {
                    Process.Start(new ProcessStartInfo
                    {
                        FileName = exePath,
                        WorkingDirectory = installDir,
                        UseShellExecute = true
                    });
                    Application.Current.Shutdown();
                    return;
                }
                catch { /* fallthrough */ }
            }

            // Same location ya launch nahi karna — bas wizard band, login aage khulega
            Close();
        }

        // ═══════════════════════════ HELPERS ═══════════════════════════
        private void SetStatus(string text)
        {
            txtStatus.Text = text;
        }

        private void SetProgress(int percent)
        {
            percent = Math.Max(0, Math.Min(100, percent));
            txtPercent.Text = percent + "%";
            double width = (percent / 100.0) * (progressFill.Parent is Grid g && g.Parent is Border b ? Math.Max(b.ActualWidth, 300) : 300);
            progressFill.Width = Math.Max(percent == 0 ? 0 : 10, width);
        }

        private void WriteUninstaller()
        {
            try
            {
                string bat = Path.Combine(installDir, "Uninstall.bat");
                string contents =
                    "@echo off\r\n" +
                    "title RashanKiDukan Uninstaller\r\n" +
                    "taskkill /IM RashanKiDukan.exe /F >nul 2>&1\r\n" +
                    "timeout /t 2 /nobreak >nul\r\n" +
                    "rmdir /S /Q \"" + installDir + "\"\r\n" +
                    "del \"" + GetDesktopDir() + "\\RashanKiDukan POS.lnk\" 2>nul\r\n" +
                    "del \"" + GetStartMenuDir() + "\\RashanKiDukan POS.lnk\" 2>nul\r\n" +
                    "echo RashanKiDukan uninstalled. Local data in %LOCALAPPDATA%\\RashanKiDukan is kept.\r\n" +
                    "pause\r\n";
                File.WriteAllText(bat, contents);
            }
            catch { }
        }

        private static string GetDesktopDir()
        {
            return Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory);
        }

        private static string GetStartMenuDir()
        {
            string dir = Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.StartMenu),
                "Programs");
            if (!Directory.Exists(dir)) Directory.CreateDirectory(dir);
            return dir;
        }

        private void CreateShortcut(string folder)
        {
            try
            {
                if (string.IsNullOrEmpty(folder) || !Directory.Exists(folder)) return;
                string lnk = Path.Combine(folder, "RashanKiDukan POS.lnk");
                string exe = Path.Combine(installDir, "RashanKiDukan.exe");
                if (!File.Exists(exe)) return;

                string eLnk = lnk.Replace("'", "''");
                string eExe = exe.Replace("'", "''");
                string eDir = installDir.Replace("'", "''");

                var psi = new ProcessStartInfo
                {
                    FileName = "powershell.exe",
                    Arguments = "-NoProfile -Command \"$s=(New-Object -COM WScript.Shell).CreateShortcut('" + eLnk + "'); $s.TargetPath='" + eExe + "'; $s.WorkingDirectory='" + eDir + "'; $s.Description='RashanKiDukan POS'; $s.IconLocation='" + eExe + ",0'; $s.Save()\"",
                    UseShellExecute = false,
                    CreateNoWindow = true,
                    WindowStyle = ProcessWindowStyle.Hidden
                };
                using (var p = Process.Start(psi))
                {
                    p?.WaitForExit(5000);
                }
            }
            catch { }
        }

        private static string TrimSlash(string path)
        {
            return path?.TrimEnd('\\', '/').ToLowerInvariant() ?? "";
        }

        protected override void OnClosing(System.ComponentModel.CancelEventArgs e)
        {
            if (installing)
            {
                var r = MessageBox.Show("Installation is in progress. Cancel and exit setup?",
                    "Cancel Installation", MessageBoxButton.YesNo, MessageBoxImage.Question);
                if (r != MessageBoxResult.Yes)
                {
                    e.Cancel = true;
                    return;
                }
            }
            base.OnClosing(e);
        }

        // ─── Small UI factory helpers ───
        private static Border MakeCard()
        {
            return new Border
            {
                Background = Brushes.White,
                BorderBrush = new SolidColorBrush(C_BORDER),
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(10),
                Padding = new Thickness(24, 18, 24, 18)
            };
        }

        private Button MakeButton(string text, bool primary)
        {
            var btn = new Button
            {
                Content = text,
                FontSize = 13,
                FontWeight = FontWeights.SemiBold,
                Height = 36,
                Padding = new Thickness(18, 0, 18, 0),
                Cursor = System.Windows.Input.Cursors.Hand,
                FocusVisualStyle = null
            };

            if (primary)
            {
                btn.Background = new SolidColorBrush(C_PRIMARY);
                btn.Foreground = Brushes.White;
                btn.BorderThickness = new Thickness(0);
            }
            else
            {
                btn.Background = new SolidColorBrush(Color.FromRgb(0xF1, 0xF1, 0xF4));
                btn.Foreground = new SolidColorBrush(C_DARK);
                btn.BorderThickness = new Thickness(0);
            }

            // Rounded corner template
            var template = new ControlTemplate(typeof(Button));
            var factory = new FrameworkElementFactory(typeof(Border));
            factory.Name = "bd";
            factory.SetValue(Border.CornerRadiusProperty, new CornerRadius(7));
            factory.SetBinding(Border.BackgroundProperty, new System.Windows.Data.Binding("Background")
            {
                RelativeSource = new System.Windows.Data.RelativeSource(System.Windows.Data.RelativeSourceMode.TemplatedParent)
            });
            factory.SetValue(Border.PaddingProperty, new Thickness(18, 0, 18, 0));
            var presenter = new FrameworkElementFactory(typeof(ContentPresenter));
            presenter.SetValue(ContentPresenter.HorizontalAlignmentProperty, HorizontalAlignment.Center);
            presenter.SetValue(ContentPresenter.VerticalAlignmentProperty, VerticalAlignment.Center);
            factory.AppendChild(presenter);
            template.VisualTree = factory;
            btn.Template = template;

            // Hover/press effects
            btn.MouseEnter += (s, e) =>
            {
                if (btn.IsEnabled && primary)
                    btn.Background = new SolidColorBrush(Color.FromRgb(0x5F, 0x61, 0xE6));
                else if (btn.IsEnabled)
                    btn.Background = new SolidColorBrush(Color.FromRgb(0xE4, 0xE4, 0xEA));
            };
            btn.MouseLeave += (s, e) =>
            {
                btn.Background = primary
                    ? new SolidColorBrush(C_PRIMARY)
                    : new SolidColorBrush(Color.FromRgb(0xF1, 0xF1, 0xF4));
            };
            btn.MouseLeftButtonDown += (s, e) => { if (primary) btn.Background = new SolidColorBrush(C_PRIMARY_PRESSED); };
            btn.MouseLeftButtonUp += (s, e) => { if (primary) btn.Background = new SolidColorBrush(C_PRIMARY); };

            return btn;
        }
    }
}
