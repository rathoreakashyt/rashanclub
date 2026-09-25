using System.Diagnostics;
using System.IO;
using System.IO.Compression;
using System.Net.Http;
using System.Security.Cryptography;
using System.Text.Json;
using RashanKiDukan.Database;

namespace RashanKiDukan.Services;

/// <summary>
/// Enterprise auto-update service. Handles version checking, downloading,
/// SHA-256 verification, backup, and orchestrating the update process via
/// a separate updater process.
///
/// OFFLINE-FIRST: If the update server cannot be reached, silently continues
/// without showing errors. POS remains fully functional.
/// </summary>
public class UpdateService
{
    private const string UpdateCheckSettingKey = "update_server_url";
    private const string LastCheckKey = "update_last_check";
    private const string SkipVersionKey = "update_skipped_version";

    /// <summary>
    /// cPanel update folder — latest.json SIRF yahan se check hota hai.
    /// Download package GitHub se aata hai (latest.json ke download_url se).
    /// </summary>
    public const string CpanelUpdateUrl = "https://rashankidukanindia.com/update";

    private readonly DatabaseService _db = new();
    private readonly ApiService _api = new();

    // ═══ Paths ═══
    public static string UpdateDataDir => Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData),
        "Rashan Ki Dukan", "updates");

    public static string BackupDir => Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData),
        "Rashan Ki Dukan", "backups");

    public static string LogDir => Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData),
        "Rashan Ki Dukan", "logs");

    public static string UpdaterExePath => Path.Combine(
        AppDomain.CurrentDomain.BaseDirectory, "RashanKiDukanUpdater.exe");

    // ═══ Public API ═══

    /// <summary>
    /// Get the update check URL (cPanel) — latest.json sirf cPanel par host hota hai.
    /// Override ke liye 'update_server_url' setting use ho sakti hai.
    /// </summary>
    public string GetUpdateServerUrl()
    {
        var configured = _api.GetSetting(UpdateCheckSettingKey);
        if (!string.IsNullOrEmpty(configured)) return configured.TrimEnd('/');

        return CpanelUpdateUrl;
    }

    /// <summary>
    /// Set the update server URL.
    /// </summary>
    public void SetUpdateServerUrl(string url)
    {
        _api.SetSetting(UpdateCheckSettingKey, url.TrimEnd('/'));
    }

    /// <summary>
    /// Get current application version from assembly.
    /// </summary>
    public static string GetCurrentVersion()
    {
        // TEST OVERRIDE: version_override file ho to wahi version report karo
        // (popup/update flow test ke liye — file delete karte hi asli version wapas)
        try
        {
            string overridePath = Path.Combine(UpdateDataDir, "version_override");
            if (File.Exists(overridePath))
            {
                string v = File.ReadAllText(overridePath).Trim();
                if (!string.IsNullOrEmpty(v)) return v;
            }
        }
        catch { }

        try
        {
            var ver = typeof(App).Assembly.GetName().Version;
            if (ver != null && ver.Major > 0)
                return $"{ver.Major}.{ver.Minor}.{ver.Build}";
        }
        catch { }

        // Fallback: try FileVersionInfo
        try
        {
            var fi = FileVersionInfo.GetVersionInfo(typeof(App).Assembly.Location);
            if (fi.FileMajorPart > 0)
                return $"{fi.FileMajorPart}.{fi.FileMinorPart}.{fi.FileBuildPart}";
        }
        catch { }

        return "1.0.0";
    }

    /// <summary>
    /// Check for update from server. Returns UpdateInfo if available, null otherwise.
    /// SILENTLY fails on network errors (offline-first).
    /// </summary>
    public async Task<UpdateInfo?> CheckForUpdateAsync()
    {
        try
        {
            var serverUrl = GetUpdateServerUrl();
            if (string.IsNullOrEmpty(serverUrl)) return null;

            // latest.json SIRF cPanel se — koi fallback URL nahi
            var checkUrl = $"{serverUrl}/latest.json";

            string? body = null;
            using var client = new HttpClient { Timeout = TimeSpan.FromSeconds(15) };
            client.DefaultRequestHeaders.Accept.Add(
                new System.Net.Http.Headers.MediaTypeWithQualityHeaderValue("application/json"));

            try
            {
                Log($"Checking for update: {checkUrl}");
                UpdateLogger.Log($"Checking latest.json: {checkUrl}");
                var response = await client.GetAsync(checkUrl);
                if (!response.IsSuccessStatusCode)
                {
                    Log($"Update check: HTTP {(int)response.StatusCode}");
                    UpdateLogger.Log($"Update check failed: HTTP {(int)response.StatusCode}");
                    return null;
                }

                body = await response.Content.ReadAsStringAsync();
            }
            catch (Exception ex)
            {
                Log($"Update check error at {checkUrl}: {ex.Message}");
                UpdateLogger.Error("Update check failed (network)", ex);
                return null;
            }

            UpdateLogger.Log($"latest.json fetched OK");
            using var doc = JsonDocument.Parse(body);

            var info = UpdateInfo.FromJson(doc.RootElement);
            if (info == null || string.IsNullOrEmpty(info.Version))
            {
                Log("Update check: no version in response");
                return null;
            }

            var currentVersion = new SemanticVersion(GetCurrentVersion());
            var serverVersion = new SemanticVersion(info.Version);

            Log($"Current version: {currentVersion}, Server version: {serverVersion}");

            if (serverVersion <= currentVersion)
            {
                Log("Already up to date.");
                return null;
            }

            // Check minimum version requirement
            if (!string.IsNullOrEmpty(info.MinimumVersion))
            {
                var minVersion = new SemanticVersion(info.MinimumVersion);
                if (currentVersion < minVersion)
                {
                    Log($"Version {currentVersion} below minimum {minVersion}. Update mandatory.");
                    info.Mandatory = true;
                }
            }

            Log($"Update available: {info.Version}");
            return info;
        }
        catch (Exception ex)
        {
            // OFFLINE-FIRST: silently fail
            Log($"Update check exception: {ex.Message}");
            return null;
        }
    }

/// <summary>
        /// Download update ZIP with progress reporting.
        /// Partial download ho to Range request se RESUME hota hai — beech me
        /// connection drop ho jaaye to dobara 89MB nahi utarta, wahan se hi
        /// continue karta hai jahan tak aaya tha.
        /// </summary>
        public async Task<bool> DownloadUpdateAsync(
            UpdateInfo info,
            IProgress<UpdateProgress>? progress = null,
            CancellationToken cancellationToken = default)
        {
            try
            {
                Directory.CreateDirectory(UpdateDataDir);

                string zipFileName = $"RashanKiDukan-{info.Version}.zip";
                string zipPath = Path.Combine(UpdateDataDir, zipFileName);

                // If already downloaded and verified, skip download
                if (File.Exists(zipPath))
                {
                    string existingHash = ComputeSha256(zipPath);
                    if (string.Equals(existingHash, info.Sha256, StringComparison.OrdinalIgnoreCase))
                    {
                        Log($"Update ZIP already exists and verified: {zipPath}");
                        progress?.Report(new UpdateProgress
                        {
                            Status = UpdateProgressStatus.DownloadComplete,
                            DownloadedBytes = new FileInfo(zipPath).Length,
                            TotalBytes = new FileInfo(zipPath).Length,
                            UpdateInfo = info
                        });
                        return true;
                    }
                }

                Log($"Downloading update (GitHub: '{info.DownloadUrl}')");
                UpdateLogger.Log($"GitHub download started: {info.DownloadUrl}");
                progress?.Report(new UpdateProgress { Status = UpdateProgressStatus.Downloading, UpdateInfo = info });

                // ── GitHub-only: download_url (latest.json me GitHub release asset hona chahiye) ──
                if (string.IsNullOrWhiteSpace(info.DownloadUrl))
                    throw new InvalidOperationException("No download URL in update info (GitHub release URL expected).");

                using var client = new HttpClient { Timeout = TimeSpan.FromMinutes(60) };

                var (ok, err) = await TryDownloadFromUrlAsync(client, info.DownloadUrl.Trim(), zipPath, info, progress, cancellationToken);
                if (!ok)
                    throw new InvalidOperationException(err ?? "Unknown download error");
                UpdateLogger.Log($"Download completed: {new FileInfo(zipPath).Length} bytes (100%)");
                return true;
            }
            catch (Exception ex)
            {
                Log($"Download failed: {ex.Message}");
                UpdateLogger.Error($"GitHub download failed: {ex.Message}", ex);
                progress?.Report(new UpdateProgress
                {
                    Status = UpdateProgressStatus.Error,
                    ErrorMessage = ex.Message,
                    UpdateInfo = info
                });
                return false;
            }
        }

        /// <summary>
        /// Ek URL se download try karo. Google Drive link ho to direct-download URL
        /// me convert karke confirm-token handle karta hai. Resume (Range) support.
        /// Returns: (true, _) = download verified OK, (false, err) = source failed.
        /// </summary>
        private async Task<(bool ok, string? error)> TryDownloadFromUrlAsync(
            HttpClient client,
            string url,
            string zipPath,
            UpdateInfo info,
            IProgress<UpdateProgress>? progress,
            CancellationToken ct)
        {
            try
            {
                var driveId = ExtractGoogleDriveFileId(url);
                if (driveId != null)
                    url = $"https://drive.google.com/uc?export=download&id={driveId}";

                // ── RESUME: partial (incomplete/corrupt) file ho to Range se continue ──
                long existingSize = 0;
                if (File.Exists(zipPath))
                {
                    try { existingSize = new FileInfo(zipPath).Length; } catch { existingSize = 0; }
                }

                HttpResponseMessage? response = null;
                HttpRequestMessage? request = null;

                for (int attempt = 0; attempt < 4; attempt++)
                {
                    request = new HttpRequestMessage(HttpMethod.Get, url);
                    if (existingSize > 0)
                    {
                        request.Headers.Range = new System.Net.Http.Headers.RangeHeaderValue(existingSize, null);
                        Log($"Resuming update download from byte {existingSize}");
                    }

                    var resp = await client.SendAsync(request, HttpCompletionOption.ResponseHeadersRead, ct);

                    // 416 → server ne range reject ki → fresh start
                    if ((int)resp.StatusCode == 416)
                    {
                        Log("Server rejected resume (416) — restarting download");
                        resp.Dispose();
                        try { File.Delete(zipPath); } catch { }
                        existingSize = 0;
                        continue;
                    }

                    // Google Drive virus-scan warning page (HTML) → confirm token nikaal ke dobara
                    if (resp.Content.Headers.ContentType?.MediaType?.Contains("html") == true)
                    {
                        var html = await resp.Content.ReadAsStringAsync(ct);
                        resp.Dispose();

                        var token = ExtractDriveConfirmToken(html);
                        if (token == null)
                            return (false, "Google Drive HTML page returned (no confirm token) — file link check karo");

                        Log($"Google Drive confirm token mila — download continue");
                        url = $"https://drive.usercontent.google.com/download?id={driveId}&export=download&confirm={token}";
                        existingSize = 0;
                        continue;
                    }

                    response = resp;
                    break;
                }

                if (response == null)
                    return (false, "Download attempts exhausted");

                using (response)
                {
                    response.EnsureSuccessStatusCode();

                    // 206 → resume accept → existingSize se continue
                    if ((int)response.StatusCode == 206)
                    {
                        long? total = response.Content.Headers.ContentRange?.Length;
                        return (await WriteDownloadAsync(zipPath, existingSize, response, info, progress, ct, total), null);
                    }

                    // 200 → full download
                    return (await WriteDownloadAsync(zipPath, 0, response, info, progress, ct), null);
                }
            }
            catch (Exception ex)
            {
                try { return (false, ex.Message); } catch { return (false, ex.Message); }
            }
        }

        /// <summary>Google Drive share link se file ID nikaalo (kisi bhi link format se).</summary>
        private static string? ExtractGoogleDriveFileId(string url)
        {
            if (string.IsNullOrWhiteSpace(url)) return null;
            if (!url.Contains("drive.google.com") && !url.Contains("docs.google.com")) return null;

            // /file/d/{id}/...
            var m = System.Text.RegularExpressions.Regex.Match(url, @"/file/d/([a-zA-Z0-9_-]{10,})");
            if (m.Success) return m.Groups[1].Value;
            // ?id= or &id=
            m = System.Text.RegularExpressions.Regex.Match(url, @"[?&]id=([a-zA-Z0-9_-]{10,})");
            if (m.Success) return m.Groups[1].Value;
            // /open?id=
            m = System.Text.RegularExpressions.Regex.Match(url, @"open\?id=([a-zA-Z0-9_-]{10,})");
            if (m.Success) return m.Groups[1].Value;
            return null;
        }

        /// <summary>Drive virus-scan warning HTML se confirm token nikaalo.</summary>
        private static string? ExtractDriveConfirmToken(string html)
        {
            // name="confirm" value="t;..." form field
            var m = System.Text.RegularExpressions.Regex.Match(
                html, @"name=""confirm""\s+value=""([^""]+)""");
            if (m.Success) return m.Groups[1].Value;
            // action="...&confirm=xyz"
            m = System.Text.RegularExpressions.Regex.Match(html, @"[?&]confirm=([^&""]+)");
            if (m.Success) return m.Groups[1].Value;
            return null;
        }

        /// <summary>
        /// Stream write + progress + SHA-256 verify.
        /// </summary>
        private async Task<bool> WriteDownloadAsync(
            string zipPath,
            long startBytes,
            HttpResponseMessage response,
            UpdateInfo info,
            IProgress<UpdateProgress>? progress,
            CancellationToken cancellationToken,
            long? totalOverride = null)
        {
            long totalBytes = totalOverride ?? response.Content.Headers.ContentLength ?? 0;
            long downloadedBytes = startBytes;
            var buffer = new byte[81920];
            DateTime startTime = DateTime.Now;

            using (var contentStream = await response.Content.ReadAsStreamAsync(cancellationToken))
            using (var fileStream = new FileStream(
                       zipPath,
                       startBytes > 0 ? FileMode.OpenOrCreate : FileMode.Create,
                       FileAccess.Write, FileShare.None))
            {
                if (startBytes > 0) fileStream.Seek(startBytes, SeekOrigin.Begin);

                int bytesRead;
                while ((bytesRead = await contentStream.ReadAsync(buffer, cancellationToken)) > 0)
                {
                    await fileStream.WriteAsync(buffer.AsMemory(0, bytesRead), cancellationToken);
                    downloadedBytes += bytesRead;

                    double elapsedSeconds = (DateTime.Now - startTime).TotalSeconds;
                    double speed = elapsedSeconds > 0 ? downloadedBytes / elapsedSeconds : 0;

                    progress?.Report(new UpdateProgress
                    {
                        Status = UpdateProgressStatus.Downloading,
                        DownloadedBytes = downloadedBytes,
                        TotalBytes = totalBytes,
                        SpeedBytesPerSec = FormatBytes((long)speed) + "/s",
                        SpeedBytesPerSecRaw = speed,
                        UpdateInfo = info
                    });
                }
            }

            Log($"Download complete: {downloadedBytes} bytes");

            // Verify checksum
            progress?.Report(new UpdateProgress { Status = UpdateProgressStatus.Verifying, UpdateInfo = info });
            string actualHash = ComputeSha256(zipPath);
            if (!string.Equals(actualHash, info.Sha256, StringComparison.OrdinalIgnoreCase))
            {
                Log($"SHA-256 mismatch! Expected: {info.Sha256}, Got: {actualHash}");
                try { File.Delete(zipPath); } catch { }
                return false;
            }
            Log("SHA-256 verified.");

            progress?.Report(new UpdateProgress
            {
                Status = UpdateProgressStatus.DownloadComplete,
                DownloadedBytes = downloadedBytes,
                TotalBytes = totalBytes,
                UpdateInfo = info
            });

            return true;
        }

    /// <summary>
    /// Launch the updater process and close the main application.
    /// </summary>
    public void LaunchUpdater(UpdateInfo info)
    {
        string zipFileName = $"RashanKiDukan-{info.Version}.zip";
        string zipPath = Path.Combine(UpdateDataDir, zipFileName);

        if (!File.Exists(UpdaterExePath))
        {
            Log($"Updater not found: {UpdaterExePath}");
            throw new FileNotFoundException("Updater executable not found.", UpdaterExePath);
        }

        string installDir = InstallGate.IsRunningFromInstallDir()
            ? InstallGate.InstallDir
            : AppDomain.CurrentDomain.BaseDirectory;

        string appExePath = Path.Combine(installDir, "RashanKiDukan.exe");

        Log($"Launching updater: {UpdaterExePath}");
        Log($"  Install dir: {installDir}");
        Log($"  ZIP: {zipPath}");
        Log($"  SHA256: {info.Sha256}");

        var psi = new ProcessStartInfo
        {
            FileName = UpdaterExePath,
            Arguments = $"\"{installDir}\" \"{zipPath}\" \"{info.Sha256}\" \"{appExePath}\" \"{BackupDir}\"",
            // UAC elevation: updater ka manifest requireAdministrator hai — runas verb se
            // UAC prompt aayega. CreateNoWindow true mat karo warna prompt suppress ho jata hai
            // (updater silently fail hota tha — download 100% ke baad bhi install nahi hota tha).
            UseShellExecute = true,
            Verb = "runas"
        };

        try
        {
            Process.Start(psi);
        }
        catch (System.ComponentModel.Win32Exception ex) when (ex.NativeErrorCode == 1223)
        {
            // UAC "No" — update cancel, app band NAHI karo
            Log("Updater launch cancelled by user (UAC denied).");
            System.Windows.MessageBox.Show(
                "Update cancel ho gaya (UAC permission nahi mili).\n" +
                "Dobara update karne ke liye phir se 'Update Now' dabao aur UAC par 'Yes' karo.",
                "Rashan Ki Dukan", System.Windows.MessageBoxButton.OK, System.Windows.MessageBoxImage.Warning);
            return;
        }

        // Close the main application
        System.Windows.Application.Current.Shutdown();
    }

    /// <summary>
    /// Mark a version as skipped by user.
    /// </summary>
    public void SkipVersion(string version)
    {
        _api.SetSetting(SkipVersionKey, version);
    }

    /// <summary>
    /// Clear skipped version (e.g., when a newer version is available).
    /// </summary>
    public void ClearSkipVersion()
    {
        _api.SetSetting(SkipVersionKey, null);
    }

    // ═══ Helpers ═══

    private static string ComputeSha256(string filePath)
    {
        using var sha = SHA256.Create();
        using var stream = File.OpenRead(filePath);
        var hash = sha.ComputeHash(stream);
        return Convert.ToHexString(hash).ToLowerInvariant();
    }

    private static string FormatBytes(long bytes)
    {
        if (bytes >= 1073741824) return $"{bytes / 1073741824.0:F1} GB";
        if (bytes >= 1048576) return $"{bytes / 1048576.0:F1} MB";
        if (bytes >= 1024) return $"{bytes / 1024.0:F1} KB";
        return $"{bytes} B";
    }

    private static void Log(string message)
    {
        try
        {
            Directory.CreateDirectory(LogDir);
            var logFile = Path.Combine(LogDir, $"updater_{DateTime.Now:yyyy-MM-dd}.log");
            var line = $"[{DateTime.Now:yyyy-MM-dd HH:mm:ss}] {message}";
            File.AppendAllText(logFile, line + Environment.NewLine);
        }
        catch { }
    }
}
