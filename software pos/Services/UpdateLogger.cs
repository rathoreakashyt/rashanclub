using System.IO;

namespace RashanKiDukan.Services;

/// <summary>
/// ONE dedicated update log file — update flow ke har step ka record.
/// Location: %LOCALAPPDATA%\RashanKiDukan\update.log
/// (Program Files me permission issue hota hai, isliye user-writable location.)
///
/// Format:
///   [2026-09-25 09:30:01] Current Version: 1.1.0
///   [2026-09-25 09:31:25] UPDATE SUCCESSFUL — Version: 1.2.0
///   [2026-09-25 09:31:17] UPDATE FAILED — Error: Access denied...
/// </summary>
public static class UpdateLogger
{
    public static string LogDir => Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
        "RashanKiDukan");

    public static string LogPath => Path.Combine(LogDir, "update.log");

    private static readonly object _lock = new();

    public static void Log(string message)
    {
        try
        {
            Directory.CreateDirectory(LogDir);
            var line = $"[{DateTime.Now:yyyy-MM-dd HH:mm:ss}] {message}";
            lock (_lock)
            {
                File.AppendAllText(LogPath, line + Environment.NewLine);
            }
        }
        catch { /* logging kabhi app crash na kare */ }
    }

    /// <summary>Exception ke saath log — message + stack trace.</summary>
    public static void Error(string message, Exception? ex = null)
    {
        var text = $"ERROR: {message}";
        if (ex != null)
            text += $"\n[{DateTime.Now:yyyy-MM-dd HH:mm:ss}] Exception: {ex.GetType().Name}: {ex.Message}" +
                    $"\n[{DateTime.Now:yyyy-MM-dd HH:mm:ss}] StackTrace: {ex.StackTrace}";
        Log(text);
    }
}
