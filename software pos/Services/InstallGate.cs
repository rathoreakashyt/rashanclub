using System;
using System.IO;
using System.Linq;

namespace RashanKiDukan.Services
{
    /// <summary>
    /// First-run detection: app ko install kiya gaya hai ya nahi.
    /// Install marker file %LOCALAPPDATA%\RashanKiDukan\installed.flag me install path save hota hai.
    /// </summary>
    public static class InstallGate
    {
        public const string InstallDir = @"C:\RashanKiDukan";
        public static string MarkerPath => Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
            "RashanKiDukan", "installed.flag");

        /// <summary>App pehle se C:\RashanKiDukan me installed hai (marker + exe dono check hote hain)</summary>
        public static bool IsInstalled()
        {
            try
            {
                // 1) Marker file check
                if (File.Exists(MarkerPath))
                {
                    string savedPath = File.ReadAllText(MarkerPath).Trim();
                    string exe = Path.Combine(savedPath, "RashanKiDukan.exe");
                    if (Directory.Exists(savedPath) && File.Exists(exe))
                        return true;
                }

                // 2) Fallback — standard install location me exe hai?
                if (File.Exists(Path.Combine(InstallDir, "RashanKiDukan.exe")))
                {
                    // Marker banate hain (self-heal)
                    MarkInstalled(InstallDir);
                    return true;
                }

                return false;
            }
            catch
            {
                return false;
            }
        }

        /// <summary>Kya ye process khud installed location se chal raha hai?</summary>
        public static bool IsRunningFromInstallDir()
        {
            try
            {
                string baseDir = TrimSlash(AppContext.BaseDirectory);
                return string.Equals(baseDir, TrimSlash(InstallDir + "\\"), StringComparison.OrdinalIgnoreCase);
            }
            catch { return false; }
        }

        public static void MarkInstalled(string path)
        {
            try
            {
                string dir = Path.GetDirectoryName(MarkerPath);
                if (!string.IsNullOrEmpty(dir) && !Directory.Exists(dir))
                    Directory.CreateDirectory(dir);
                File.WriteAllText(MarkerPath, path);
            }
            catch { }
        }

        /// <summary>Installer wizard dikhao. Return true = install complete (continue), false = user ne cancel kiya.</summary>
        public static bool RunInstallerIfNeeded()
        {
            if (IsInstalled() || IsRunningFromInstallDir())
                return true;

            var wizard = new Views.InstallerWindow();
            wizard.ShowDialog();

            // Wizard ke baad marker bana do (agar install dir valid hai)
            if (Directory.Exists(InstallDir) && File.Exists(Path.Combine(InstallDir, "RashanKiDukan.exe")))
                MarkInstalled(InstallDir);

            return true; // wizard band hone par app continue kare (user cancel bhi kare to app band ho chuka hoga)
        }

        private static string TrimSlash(string path)
        {
            return path?.TrimEnd('\\', '/').ToLowerInvariant() ?? "";
        }
    }
}
