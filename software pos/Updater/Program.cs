using System.Diagnostics;
using System.IO.Compression;
using System.Security.Cryptography;

namespace RashanKiDukanUpdater;

/// <summary>
/// Separate process that handles safe application update.
/// Waits for main app to exit, backs up current version, extracts update, restarts app.
/// </summary>
class Program
{
    static int Main(string[] args)
    {
        if (args.Length < 4)
        {
            Console.Error.WriteLine("Usage: RashanKiDukanUpdater.exe <installDir> <zipPath> <expectedSha256> <appExePath> [backupDir]");
            return 1;
        }

        string installDir = args[0];
        string zipPath = args[1];
        string expectedSha256 = args[2];
        string appExePath = args[3];
        string backupDir = args.Length > 4 ? args[4] : Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData),
            "Rashan Ki Dukan", "backups");

        var logFile = Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData),
            "Rashan Ki Dukan", "logs", "updater.log");

        try
        {
            Directory.CreateDirectory(Path.GetDirectoryName(logFile)!);
            Log(logFile, "═══ Updater started ═══");
            Log(logFile, $"Install dir: {installDir}");
            Log(logFile, $"ZIP: {zipPath}");
            Log(logFile, $"Expected SHA256: {expectedSha256}");
            Log(logFile, $"App exe: {appExePath}");

            // 1. Wait for main app to fully exit
            Log(logFile, "Waiting for main app to exit...");
            WaitForAppExit(appExePath);
            Log(logFile, "Main app exited.");

            // 2. Verify ZIP exists
            if (!File.Exists(zipPath))
            {
                Log(logFile, $"ERROR: ZIP not found: {zipPath}");
                return 1;
            }

            // 3. Verify SHA-256 checksum
            Log(logFile, "Verifying SHA-256 checksum...");
            string actualSha256 = ComputeSha256(zipPath);
            Log(logFile, $"Actual SHA256: {actualSha256}");
            if (!string.Equals(actualSha256, expectedSha256, StringComparison.OrdinalIgnoreCase))
            {
                Log(logFile, $"ERROR: SHA-256 mismatch! Expected: {expectedSha256}, Got: {actualSha256}");
                return 1;
            }
            Log(logFile, "SHA-256 verified.");

            // 4. Backup current version
            string backupPath = Path.Combine(backupDir, Directory.Exists(installDir) ? GetCurrentVersion(installDir) : "unknown");
            Log(logFile, $"Backing up to: {backupPath}");
            BackupCurrentVersion(installDir, backupPath);
            Log(logFile, "Backup complete.");

            // 5. Extract update to temp directory, then move
            string tempExtract = Path.Combine(Path.GetTempPath(), "RashanKiDukan_Update_" + Guid.NewGuid().ToString("N")[..8]);
            Log(logFile, $"Extracting to temp: {tempExtract}");
            ExtractUpdate(zipPath, tempExtract, installDir);
            Log(logFile, "Extraction complete.");

            // 6. Verify required files exist
            string exePath = Path.Combine(installDir, "RashanKiDukan.exe");
            if (!File.Exists(exePath))
            {
                Log(logFile, $"ERROR: Main exe not found after update: {exePath}");
                Log(logFile, "Rolling back...");
                Rollback(installDir, backupPath);
                return 1;
            }
            Log(logFile, "Required files verified.");

            // 7. Cleanup temp extract
            try { Directory.Delete(tempExtract, true); } catch { }

            // 8. Cleanup downloaded ZIP
            try { File.Delete(zipPath); } catch { }

            Log(logFile, "═══ Update complete ═══");

            // 9. Restart the application
            Log(logFile, "Restarting application...");
            Process.Start(new ProcessStartInfo
            {
                FileName = exePath,
                WorkingDirectory = installDir,
                UseShellExecute = true
            });

            return 0;
        }
        catch (Exception ex)
        {
            Log(logFile, $"FATAL: {ex}");
            // Attempt rollback if backup exists
            if (Directory.Exists(backupDir))
            {
                var latestBackup = Directory.GetDirectories(backupDir)
                    .OrderByDescending(d => d).FirstOrDefault();
                if (latestBackup != null)
                {
                    Log(logFile, $"Attempting rollback from: {latestBackup}");
                    Rollback(installDir, latestBackup);
                }
            }
            return 1;
        }
    }

    static void WaitForAppExit(string exePath)
    {
        string processName = Path.GetFileNameWithoutExtension(exePath);
        int maxWaitMs = 60000; // 60 seconds max
        int waited = 0;

        while (waited < maxWaitMs)
        {
            var processes = Process.GetProcessesByName(processName);
            bool running = false;
            foreach (var p in processes)
            {
                try
                {
                    // Check it's actually our app (not the updater or something else)
                    if (p.MainModule?.FileName?.Equals(exePath, StringComparison.OrdinalIgnoreCase) == true)
                    {
                        running = true;
                        break;
                    }
                }
                catch { }
                finally { p.Dispose(); }
            }

            if (!running) return;
            Thread.Sleep(1000);
            waited += 1000;
        }
    }

    static void BackupCurrentVersion(string installDir, string backupPath)
    {
        if (!Directory.Exists(installDir)) return;
        Directory.CreateDirectory(backupPath);

        foreach (var file in Directory.GetFiles(installDir, "*", SearchOption.AllDirectories))
        {
            string rel = file.Substring(installDir.Length).TrimStart('\\', '/');
            string dest = Path.Combine(backupPath, rel);
            Directory.CreateDirectory(Path.GetDirectoryName(dest)!);
            File.Copy(file, dest, true);
        }
    }

    static void ExtractUpdate(string zipPath, string tempDir, string installDir)
    {
        Directory.CreateDirectory(tempDir);
        ZipFile.ExtractToDirectory(zipPath, tempDir, overwriteFiles: true);

        // Validate no path traversal in extracted files
        foreach (var file in Directory.GetFiles(tempDir, "*", SearchOption.AllDirectories))
        {
            string relativePath = file.Substring(tempDir.Length).TrimStart('\\', '/');
            if (relativePath.Contains("..") || Path.IsPathRooted(relativePath))
            {
                throw new InvalidOperationException($"Security: suspicious path in update package: {relativePath}");
            }
        }

        // Copy files from temp to install dir
        // Don't touch: data.db, installed.flag, logs, updater khud (locked)
        var skipFiles = new HashSet<string>(StringComparer.OrdinalIgnoreCase)
        {
            "data.db", "installed.flag", "updater.log",
            "RashanKiDukanUpdater.exe" // updater process khud locked hota hai — copy fail hota tha
        };

        foreach (var file in Directory.GetFiles(tempDir, "*", SearchOption.AllDirectories))
        {
            string rel = file.Substring(tempDir.Length).TrimStart('\\', '/');
            string fileName = Path.GetFileName(rel);

            // Skip user data files
            if (skipFiles.Contains(fileName)) continue;
            if (rel.StartsWith("logs\\", StringComparison.OrdinalIgnoreCase)) continue;
            if (rel.StartsWith("Database\\", StringComparison.OrdinalIgnoreCase) && fileName.EndsWith(".sql")) continue;

            string dest = Path.Combine(installDir, rel);
            Directory.CreateDirectory(Path.GetDirectoryName(dest)!);

            // Retry copy (file might be briefly locked)
            for (int attempt = 0; attempt < 5; attempt++)
            {
                try
                {
                    File.Copy(file, dest, true);
                    break;
                }
                catch (IOException) when (attempt < 4)
                {
                    Thread.Sleep(1000);
                }
            }
        }
    }

    static void Rollback(string installDir, string backupPath)
    {
        if (!Directory.Exists(backupPath)) return;

        foreach (var file in Directory.GetFiles(backupPath, "*", SearchOption.AllDirectories))
        {
            string rel = file.Substring(backupPath.Length).TrimStart('\\', '/');
            string dest = Path.Combine(installDir, rel);
            Directory.CreateDirectory(Path.GetDirectoryName(dest)!);
            for (int attempt = 0; attempt < 5; attempt++)
            {
                try
                {
                    File.Copy(file, dest, true);
                    break;
                }
                catch (IOException) when (attempt < 4)
                {
                    Thread.Sleep(1000);
                }
            }
        }

        // Restart with previous version
        string exePath = Path.Combine(installDir, "RashanKiDukan.exe");
        if (File.Exists(exePath))
        {
            Process.Start(new ProcessStartInfo
            {
                FileName = exePath,
                WorkingDirectory = installDir,
                UseShellExecute = true
            });
        }
    }

    static string GetCurrentVersion(string installDir)
    {
        try
        {
            string exePath = Path.Combine(installDir, "RashanKiDukan.exe");
            if (File.Exists(exePath))
            {
                var ver = FileVersionInfo.GetVersionInfo(exePath);
                return $"{ver.FileMajorPart}.{ver.FileMinorPart}.{ver.FileBuildPart}";
            }
        }
        catch { }
        return "unknown";
    }

    static string ComputeSha256(string filePath)
    {
        using var sha = SHA256.Create();
        using var stream = File.OpenRead(filePath);
        var hash = sha.ComputeHash(stream);
        return Convert.ToHexString(hash).ToLowerInvariant();
    }

    static void Log(string logFile, string message)
    {
        try
        {
            var line = $"[{DateTime.Now:yyyy-MM-dd HH:mm:ss}] {message}";
            File.AppendAllText(logFile, line + Environment.NewLine);
        }
        catch { }
    }
}
