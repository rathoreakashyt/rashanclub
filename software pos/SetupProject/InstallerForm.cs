using System;
using System.Diagnostics;
using System.Drawing;
using System.Drawing.Drawing2D;
using System.IO;
using System.Threading.Tasks;
using System.Windows.Forms;

namespace RashanKiDukanSetup
{
    public class InstallerForm : Form
    {
        // ─────────────────── STEPS ───────────────────
        private enum Step { Welcome = 0, License = 1, Location = 2, Installing = 3, Done = 4 }

        private Panel contentPanel;
        private Panel pnlWelcome;
        private Panel pnlLicense;
        private Panel pnlLocation;
        private Panel pnlInstalling;
        private Panel pnlDone;
        private Panel[] stepPanels;
        private Step current = Step.Welcome;

        private Button nextBtn;
        private Button backBtn;
        private Button cancelBtn;

        // License
        private CheckBox chkLicense;

        // Location
        private Label lblPath;
        private string installDir = @"C:\RashanKiDukan";

        // Installing
        private BlueProgressBar progressBlue;
        private Label lblStatus;
        private Label lblPercent;

        // Done
        private Label lblDonePath;
        private CheckBox chkDesktop;
        private CheckBox chkLaunch;

        // Data source
        private string srcData;
        private volatile bool installing;
        private volatile bool formClosed;

        private readonly string APP_NAME = "RashanKiDukan POS";
        private readonly string EXE_NAME = "RashanKiDukan.exe";
        private readonly string APP_VERSION = "1.0.0";
        private readonly Font TITLE_FONT = new Font("Segoe UI Semibold", 15f);
        private readonly Font BODY_FONT = new Font("Segoe UI", 9.5f);
        private readonly Font SMALL_FONT = new Font("Segoe UI", 8.5f);
        private readonly Color ACCENT = Color.FromArgb(0, 120, 215);
        private readonly Color DARK = Color.FromArgb(32, 32, 32);
        private readonly Color GRAY = Color.FromArgb(100, 100, 100);
        private readonly Color LIGHT_BG = Color.FromArgb(248, 249, 250);

        public InstallerForm()
        {
            srcData = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "data");
            BuildUI();
        }

        // ─────────────────── UI BUILD ───────────────────
        private void BuildUI()
        {
            SuspendLayout();
            Text = APP_NAME + " Setup";
            Size = new Size(580, 430);
            StartPosition = FormStartPosition.CenterScreen;
            FormBorderStyle = FormBorderStyle.FixedDialog;
            MaximizeBox = false;
            MinimizeBox = false;
            BackColor = Color.White;

            // ═══ HEADER ═══
            var headerPanel = new Panel { Dock = DockStyle.Top, Height = 72, BackColor = DARK };
            var lblTitle = new Label
            {
                Text = "RashanKiDukan POS",
                ForeColor = Color.White,
                Font = new Font("Segoe UI Semibold", 17f),
                AutoSize = false,
                Width = 400,
                Height = 36,
                Location = new Point(22, 6),
                TextAlign = ContentAlignment.MiddleLeft
            };
            var lblSub = new Label
            {
                Text = "Setup Wizard",
                ForeColor = Color.FromArgb(160, 160, 160),
                Font = new Font("Segoe UI", 10f),
                AutoSize = false,
                Width = 400,
                Height = 24,
                Location = new Point(24, 42),
                TextAlign = ContentAlignment.MiddleLeft
            };
            headerPanel.Controls.Add(lblTitle);
            headerPanel.Controls.Add(lblSub);
            Controls.Add(headerPanel);

            // ═══ CONTENT ═══
            contentPanel = new Panel
            {
                Location = new Point(0, 72),
                Size = new Size(580, 282),
                BackColor = Color.White
            };

            BuildWelcome();
            BuildLicense();
            BuildLocation();
            BuildInstalling();
            BuildDone();

            stepPanels = new Panel[] { pnlWelcome, pnlLicense, pnlLocation, pnlInstalling, pnlDone };
            Controls.Add(contentPanel);

            // ═══ FOOTER ═══
            var footerPanel = new Panel
            {
                Dock = DockStyle.Bottom,
                Height = 56,
                BackColor = LIGHT_BG
            };
            var sep = new Panel { Dock = DockStyle.Top, Height = 1, BackColor = Color.FromArgb(210, 210, 210) };
            footerPanel.Controls.Add(sep);

            cancelBtn = MakeBtn("Cancel", 90, Color.FromArgb(230, 230, 230), DARK, false);
            cancelBtn.Location = new Point(470, 11);
            cancelBtn.Click += (s, e) => Close();
            footerPanel.Controls.Add(cancelBtn);

            backBtn = MakeBtn("\u2190 Back", 100, Color.FromArgb(230, 230, 230), DARK, false);
            backBtn.Location = new Point(360, 11);
            backBtn.Click += (s, e) => { if (current > Step.Welcome && current < Step.Installing) ShowStep(current - 1); };
            footerPanel.Controls.Add(backBtn);

            nextBtn = MakeBtn("Next \u2192", 110, ACCENT, Color.White, true);
            nextBtn.Location = new Point(240, 11);
            nextBtn.Click += Next_Click;
            footerPanel.Controls.Add(nextBtn);

            Controls.Add(footerPanel);

            ResumeLayout(false);
            ShowStep(Step.Welcome);
        }

        // ────────── Step Panels ──────────
        private void BuildWelcome()
        {
            pnlWelcome = MakePanel();
            var icon = new Label { Text = "\U0001F4E6", Font = new Font("Segoe UI Emoji", 40f), AutoSize = true, Location = new Point(235, 18) };
            var t = MakeLabel("Welcome to the Installer", TITLE_FONT, DARK, 170, 80);
            var d = MakeLabel(
                $"This wizard will install {APP_NAME} on your computer.\n\n" +
                "Click \"Next\" to continue or \"Cancel\" to exit.",
                BODY_FONT, GRAY, 110, 120, 350, 60);
            var v = MakeLabel($"Version {APP_VERSION}", SMALL_FONT, Color.FromArgb(160, 160, 160), 240, 230);
            pnlWelcome.Controls.AddRange(new Control[] { icon, t, d, v });
        }

        private void BuildLicense()
        {
            pnlLicense = MakePanel();
            var t = MakeLabel("License Agreement", TITLE_FONT, DARK, 22, 18);
            var d = MakeLabel("Please review the terms before installing:", BODY_FONT, GRAY, 22, 52);
            var rtb = new RichTextBox
            {
                Location = new Point(22, 80),
                Size = new Size(516, 160),
                ReadOnly = true,
                BorderStyle = BorderStyle.FixedSingle,
                Font = new Font("Consolas", 8.5f),
                BackColor = Color.White,
                Text =
                "RashanKiDukan POS - End User License Agreement\n" +
                "================================================\n\n" +
                "Permission is hereby granted, free of charge, to any person obtaining a copy\n" +
                "of this software and associated documentation files (the \"Software\"), to deal\n" +
                "in the Software without restriction, including without limitation the rights\n" +
                "to use, copy, modify, merge, publish, distribute, sublicense, and/or sell\n" +
                "copies of the Software.\n\n" +
                "THE SOFTWARE IS PROVIDED \"AS IS\", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR\n" +
                "IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,\n" +
                "FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT."
            };
            chkLicense = new CheckBox
            {
                Text = "I accept the terms of this agreement",
                Font = BODY_FONT,
                AutoSize = true,
                Location = new Point(22, 248),
                Checked = false
            };
            chkLicense.CheckedChanged += (s, e) =>
            {
                if (current == Step.License) nextBtn.Enabled = chkLicense.Checked;
            };
            pnlLicense.Controls.AddRange(new Control[] { t, d, rtb, chkLicense });
        }

        private void BuildLocation()
        {
            pnlLocation = MakePanel();
            var t = MakeLabel("Choose Install Location", TITLE_FONT, DARK, 22, 18);
            var d = MakeLabel("Application will be installed to:", BODY_FONT, GRAY, 22, 52);

            lblPath = new Label
            {
                Text = installDir,
                Font = new Font("Consolas", 11f),
                ForeColor = DARK,
                BackColor = Color.FromArgb(245, 245, 245),
                BorderStyle = BorderStyle.FixedSingle,
                Location = new Point(22, 82),
                Size = new Size(410, 32),
                TextAlign = ContentAlignment.MiddleLeft,
                Padding = new Padding(8, 0, 0, 0)
            };

            var browseBtn = MakeBtn("Browse...", 100, Color.FromArgb(230, 230, 230), DARK, false);
            browseBtn.Location = new Point(440, 82);
            browseBtn.Height = 32;
            browseBtn.Click += (s, e) =>
            {
                using (var fd = new FolderBrowserDialog { Description = "Select installation folder", SelectedPath = installDir })
                {
                    if (fd.ShowDialog(this) == DialogResult.OK)
                    {
                        installDir = Path.Combine(fd.SelectedPath, "RashanKiDukan");
                        lblPath.Text = installDir;
                    }
                }
            };

            var req = MakeLabel(
                "\u2699  Requirements:\n" +
                "\u2022  Windows 10 or later\n" +
                "\u2022  ~190 MB free disk space\n" +
                "\u2022  .NET runtime is bundled (no install needed)",
                BODY_FONT, GRAY, 22, 140, 400, 100);

            var size = MakeLabel("Space required: ~190 MB", SMALL_FONT, Color.FromArgb(160, 160, 160), 22, 248);

            pnlLocation.Controls.AddRange(new Control[] { t, d, lblPath, browseBtn, req, size });
        }

        private void BuildInstalling()
        {
            pnlInstalling = MakePanel();
            var t = MakeLabel("Installing...", TITLE_FONT, DARK, 22, 18);
            lblStatus = MakeLabel("Preparing...", BODY_FONT, GRAY, 22, 55, 480, 22);
            lblPercent = MakeLabel("0%", new Font("Segoe UI Semibold", 11f), ACCENT, 500, 52, 58, 22);
            lblPercent.TextAlign = ContentAlignment.MiddleRight;

            // ─── Blue Progress Patti ───
            progressBlue = new BlueProgressBar
            {
                Location = new Point(22, 90),
                Size = new Size(516, 24)
            };

            var wait = MakeLabel(
                "Please wait while files are being installed.\nThis may take a few minutes.",
                SMALL_FONT, Color.FromArgb(150, 150, 150), 22, 140, 400, 40);

            pnlInstalling.Controls.AddRange(new Control[] { t, lblStatus, lblPercent, progressBlue, wait });
        }

        private void BuildDone()
        {
            pnlDone = MakePanel();
            var icon = new Label { Text = "\u2705", Font = new Font("Segoe UI Emoji", 38f), AutoSize = true, Location = new Point(240, 10) };
            var t = MakeLabel("Installation Complete!", TITLE_FONT, DARK, 150, 75);
            lblDonePath = MakeLabel($"{APP_NAME} has been installed successfully.\nInstalled to: {installDir}", BODY_FONT, GRAY, 100, 112, 400, 40);

            chkDesktop = new CheckBox
            {
                Text = "Create desktop shortcut",
                Font = BODY_FONT,
                Checked = true,
                AutoSize = true,
                Location = new Point(170, 175)
            };
            chkLaunch = new CheckBox
            {
                Text = "Launch RashanKiDukan POS now",
                Font = BODY_FONT,
                Checked = true,
                AutoSize = true,
                Location = new Point(170, 205)
            };

            pnlDone.Controls.AddRange(new Control[] { icon, t, lblDonePath, chkDesktop, chkLaunch });
        }

        // ────────── NAVIGATION ──────────
        private void ShowStep(Step step)
        {
            current = step;
            var p = stepPanels[(int)step];

            contentPanel.Controls.Add(p);
            p.BringToFront();
            p.Visible = true;

            backBtn.Enabled = step > Step.Welcome && step < Step.Installing;
            cancelBtn.Enabled = step != Step.Installing && step != Step.Done;

            switch (step)
            {
                case Step.Welcome:
                    nextBtn.Text = "Next \u2192";
                    nextBtn.Enabled = true;
                    break;

                case Step.License:
                    nextBtn.Text = "Next \u2192";
                    nextBtn.Enabled = chkLicense.Checked; // respect checkbox state (also when coming Back)
                    break;

                case Step.Location:
                    nextBtn.Text = "Install";
                    nextBtn.Enabled = true;
                    break;

                case Step.Installing:
                    nextBtn.Text = "Next \u2192";
                    nextBtn.Enabled = false;
                    break;

                case Step.Done:
                    nextBtn.Text = "Finish";
                    nextBtn.Enabled = true;
                    break;
            }
        }

        private void Next_Click(object s, EventArgs e)
        {
            switch (current)
            {
                case Step.Welcome:
                    ShowStep(Step.License);
                    break;

                case Step.License:
                    ShowStep(Step.Location);
                    break;

                case Step.Location:
                    if (!Directory.Exists(srcData) || !File.Exists(Path.Combine(srcData, EXE_NAME)))
                    {
                        MessageBox.Show(
                            "Setup files not found!\n\n" +
                            $"The 'data' folder (containing {EXE_NAME}) must be in the same\n" +
                            "directory as the installer.\n\n" +
                            "Expected: " + srcData,
                            "Setup Error", MessageBoxButtons.OK, MessageBoxIcon.Error);
                        return;
                    }
                    if (installDir.TrimEnd('\\').ToLowerInvariant() == @"c:".ToLowerInvariant() ||
                        string.IsNullOrWhiteSpace(installDir))
                    {
                        MessageBox.Show("Please choose a valid installation folder.",
                            "Invalid Path", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                        return;
                    }
                    ShowStep(Step.Installing);
                    StartInstall();
                    break;

                case Step.Done:
                    if (chkLaunch.Checked)
                    {
                        try
                        {
                            Process.Start(new ProcessStartInfo
                            {
                                FileName = Path.Combine(installDir, EXE_NAME),
                                WorkingDirectory = installDir,
                                UseShellExecute = true
                            });
                        }
                        catch { }
                    }
                    Close();
                    break;
            }
        }

        // ────────── INSTALL LOGIC ──────────
        private void StartInstall() => Task.Run(DoInstall);

        private void DoInstall()
        {
            try
            {
                installing = true;
                SafeUI(() => { lblStatus.Text = "Preparing installation..."; SetProgress(2); });

                // 1) Create install folder
                if (!Directory.Exists(installDir))
                    Directory.CreateDirectory(installDir);

                // 2) Copy all files with byte-based progress (2% → 92%)
                var files = Directory.GetFiles(srcData, "*", SearchOption.AllDirectories);
                long totalBytes = 0;
                foreach (var f in files) totalBytes += new FileInfo(f).Length;
                if (totalBytes <= 0) totalBytes = 1;

                long copied = 0;
                for (int i = 0; i < files.Length; i++)
                {
                    string rel = files[i].Substring(srcData.Length).TrimStart('\\', '/');
                    string dest = Path.Combine(installDir, rel);
                    string dir = Path.GetDirectoryName(dest);
                    if (!string.IsNullOrEmpty(dir) && !Directory.Exists(dir))
                        Directory.CreateDirectory(dir);

                    File.Copy(files[i], dest, true);
                    copied += new FileInfo(files[i]).Length;

                    int pct = 2 + (int)((double)copied / totalBytes * 90);
                    string name = Path.GetFileName(files[i]);
                    SafeUI(() =>
                    {
                        lblStatus.Text = "Installing: " + name;
                        SetProgress(pct);
                    });
                }

                // 3) Start Menu shortcut (always)
                SafeUI(() => { lblStatus.Text = "Creating Start Menu shortcut..."; SetProgress(93); });
                CreateShortcut(GetStartMenuDir(), APP_NAME + ".lnk");

                // 4) Desktop shortcut (optional)
                if (chkDesktop != null && chkDesktop.Checked)
                {
                    SafeUI(() => { lblStatus.Text = "Creating desktop shortcut..."; SetProgress(95); });
                    CreateShortcut(GetDesktopDir(), APP_NAME + ".lnk");
                }

                // 5) Uninstaller
                SafeUI(() => { lblStatus.Text = "Creating uninstaller..."; SetProgress(97); });
                WriteUninstaller();

                // 6) Finalize
                SafeUI(() => { lblStatus.Text = "Finalizing..."; SetProgress(100); });
                System.Threading.Thread.Sleep(400);

                SafeUI(() =>
                {
                    installing = false;
                    lblDonePath.Text = $"{APP_NAME} has been installed successfully.\nInstalled to: {installDir}";
                    ShowStep(Step.Done);
                });
            }
            catch (Exception ex)
            {
                installing = false;
                SafeUI(() =>
                {
                    MessageBox.Show("Installation failed:\n\n" + ex.Message,
                        "Error", MessageBoxButtons.OK, MessageBoxIcon.Error);
                    SetProgress(0);
                    lblStatus.Text = "Preparing...";
                    ShowStep(Step.Location);
                });
            }
        }

        private void WriteUninstaller()
        {
            string bat = Path.Combine(installDir, "Uninstall.bat");
            var lines = new[]
            {
                "@echo off",
                "title RashanKiDukan Uninstaller",
                "taskkill /IM " + EXE_NAME + " /F >nul 2>&1",
                "timeout /t 2 /nobreak >nul",
                "rmdir /S /Q \"" + installDir + "\"",
                "del \"" + GetDesktopDir() + "\\" + APP_NAME + ".lnk\" 2>nul",
                "del \"" + GetStartMenuDir() + "\\" + APP_NAME + ".lnk\" 2>nul",
                "echo RashanKiDukan uninstalled. Local data in %LOCALAPPDATA%\\RashanKiDukan is kept.",
                "pause"
            };
            File.WriteAllLines(bat, lines);
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

        private void CreateShortcut(string folder, string lnkName)
        {
            try
            {
                if (string.IsNullOrEmpty(folder) || !Directory.Exists(folder)) return;
                string lnk = Path.Combine(folder, lnkName);
                string exe = Path.Combine(installDir, EXE_NAME);
                // Escape single quotes for PowerShell single-quoted strings
                string eLnk = lnk.Replace("'", "''");
                string eExe = exe.Replace("'", "''");
                string eDir = installDir.Replace("'", "''");
                string eName = APP_NAME.Replace("'", "''");

                var psi = new ProcessStartInfo
                {
                    FileName = "powershell.exe",
                    Arguments = "-NoProfile -Command \"$s=(New-Object -COM WScript.Shell).CreateShortcut('" + eLnk + "'); $s.TargetPath='" + eExe + "'; $s.WorkingDirectory='" + eDir + "'; $s.Description='" + eName + "'; $s.IconLocation='" + eExe + ",0'; $s.Save()\"",
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

        // ────────── CLOSE GUARD ──────────
        protected override void OnFormClosing(FormClosingEventArgs e)
        {
            if (installing && e.CloseReason == CloseReason.UserClosing)
            {
                var r = MessageBox.Show("Installation is in progress. Cancel and exit setup?",
                    "Cancel Installation", MessageBoxButtons.YesNo, MessageBoxIcon.Question);
                if (r == DialogResult.No)
                {
                    e.Cancel = true;
                    return;
                }
            }
            formClosed = true;
            base.OnFormClosing(e);
        }

        // ────────── HELPERS ──────────
        private void SafeUI(Action a)
        {
            if (formClosed || IsDisposed || !IsHandleCreated) return;
            try { Invoke(a); } catch { }
        }

        private void SetProgress(int p) => progressBlue.Value = Math.Max(0, Math.Min(100, p));

        private Panel MakePanel()
        {
            return new Panel
            {
                Dock = DockStyle.Fill,
                BackColor = Color.White,
                Visible = false
            };
        }

        private Label MakeLabel(string text, Font font, Color color, int x, int y, int w = 450, int h = 30)
        {
            return new Label
            {
                Text = text,
                Font = font,
                ForeColor = color,
                AutoSize = false,
                Location = new Point(x, y),
                Size = new Size(w, h)
            };
        }

        private Button MakeBtn(string text, int width, Color bg, Color fg, bool bold)
        {
            return new Button
            {
                Text = text,
                Size = new Size(width, 34),
                FlatStyle = FlatStyle.Flat,
                BackColor = bg,
                ForeColor = fg,
                Font = bold ? new Font("Segoe UI", 9.5f, FontStyle.Bold) : new Font("Segoe UI", 9.5f),
                Cursor = Cursors.Hand
            };
        }
    }

    // ══════════════════════════════════════════════════════════════
    //  Custom smooth blue progress bar — guaranteed solid blue patti
    //  (standard WinForms ProgressBar looks blocky / themed on Win11)
    // ══════════════════════════════════════════════════════════════
    public class BlueProgressBar : Control
    {
        private int value;
        private int minimum;
        private int maximum = 100;

        public int Minimum
        {
            get => minimum;
            set { minimum = Math.Min(value, maximum); Invalidate(); }
        }

        public int Maximum
        {
            get => maximum;
            set { maximum = Math.Max(value, minimum); Invalidate(); }
        }

        public int Value
        {
            get => value;
            set
            {
                int v = Math.Max(minimum, Math.Min(maximum, value));
                if (this.value == v) return;
                this.value = v;
                Invalidate();
                Update();
            }
        }

        public BlueProgressBar()
        {
            SetStyle(ControlStyles.AllPaintingInWmPaint |
                     ControlStyles.OptimizedDoubleBuffer |
                     ControlStyles.UserPaint |
                     ControlStyles.ResizeRedraw, true);
            BackColor = Color.White;
        }

        protected override void OnPaint(PaintEventArgs e)
        {
            var g = e.Graphics;
            g.SmoothingMode = SmoothingMode.AntiAlias;

            var rect = new Rectangle(0, 0, Width - 1, Height - 1);
            float radius = 6f;

            using (var track = GetRoundedPath(rect, radius))
            using (var trackBrush = new SolidBrush(Color.FromArgb(233, 236, 239)))
            using (var border = new Pen(Color.FromArgb(205, 210, 215)))
            {
                g.FillPath(trackBrush, track);
                g.DrawPath(border, track);
            }

            int range = maximum - minimum;
            if (range <= 0 || value <= minimum) return;

            float frac = (float)(value - minimum) / range;
            int w = (int)Math.Max(Height * frac, (rect.Height - 4) * frac + 2); // never a zero-width sliver
            if (w < 8 && frac > 0) w = 8; // show a visible start even at small %
            if (w > rect.Width - 2) w = rect.Width - 2;

            var fillRect = new Rectangle(2, 2, w, rect.Height - 3);
            using (var fillPath = GetRoundedPath(fillRect, radius - 2f))
            using (var fillBrush = new LinearGradientBrush(
                new Rectangle(fillRect.X - 1, fillRect.Y, fillRect.Width + 2, fillRect.Height),
                Color.FromArgb(0, 98, 204), Color.FromArgb(46, 155, 255), 90f))
            {
                g.FillPath(fillBrush, fillPath);
            }

            base.OnPaint(e);
        }

        private static GraphicsPath GetRoundedPath(Rectangle r, float radius)
        {
            var path = new GraphicsPath();
            float d = radius * 2;
            if (d <= 0 || r.Width <= 0 || r.Height <= 0)
            {
                path.AddRectangle(r);
                return path;
            }
            path.AddArc(r.X, r.Y, d, d, 180, 90);
            path.AddArc(r.Right - d, r.Y, d, d, 270, 90);
            path.AddArc(r.Right - d, r.Bottom - d, d, d, 0, 90);
            path.AddArc(r.X, r.Bottom - d, d, d, 90, 90);
            path.CloseFigure();
            return path;
        }
    }
}
