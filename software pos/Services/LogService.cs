using System;
using System.IO;
using System.Runtime.CompilerServices;
using System.Threading;

namespace RashanKiDukan.Services;

/// <summary>
/// Thread-safe structured logging service with daily rotation, size-based rotation,
/// and automatic cleanup of old log files.
/// </summary>
public static class LogService
{
    private static readonly object _lock = new();
    private static readonly string _logDirectory;
    private static readonly long _maxFileSizeBytes = 10 * 1024 * 1024; // 10 MB
    private static readonly int _retentionDays = 30;
    private static bool _initialized;

    static LogService()
    {
        var localAppData = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
        _logDirectory = Path.Combine(localAppData, "RashanKiDukan", "logs");
    }

    /// <summary>
    /// Ensures the log directory exists and triggers cleanup of old logs.
    /// </summary>
    private static void EnsureInitialized()
    {
        if (_initialized) return;

        lock (_lock)
        {
            if (_initialized) return;

            Directory.CreateDirectory(_logDirectory);
            CleanupOldLogs();
            _initialized = true;
        }
    }

    /// <summary>
    /// Logs an informational message.
    /// </summary>
    public static void Info(
        string message,
        [CallerMemberName] string memberName = "",
        [CallerFilePath] string filePath = "")
    {
        WriteLog("INFO", message, null, memberName, filePath);
    }

    /// <summary>
    /// Logs a warning message.
    /// </summary>
    public static void Warn(
        string message,
        [CallerMemberName] string memberName = "",
        [CallerFilePath] string filePath = "")
    {
        WriteLog("WARN", message, null, memberName, filePath);
    }

    /// <summary>
    /// Logs an error message with optional exception details including stack trace.
    /// </summary>
    public static void Error(
        string message,
        Exception? exception = null,
        [CallerMemberName] string memberName = "",
        [CallerFilePath] string filePath = "")
    {
        WriteLog("ERROR", message, exception, memberName, filePath);
    }

    /// <summary>
    /// Logs a debug message.
    /// </summary>
    public static void Debug(
        string message,
        [CallerMemberName] string memberName = "",
        [CallerFilePath] string filePath = "")
    {
#if DEBUG
        WriteLog("DEBUG", message, null, memberName, filePath);
#endif
    }

    /// <summary>
    /// Core logging method. Thread-safe with file rotation support.
    /// </summary>
    private static void WriteLog(
        string level,
        string message,
        Exception? exception,
        string memberName,
        string filePath)
    {
        EnsureInitialized();

        var timestamp = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss.fff");
        var caller = FormatCaller(filePath, memberName);
        var logEntry = $"[{timestamp}] [{level}] [{caller}] {message}";

        if (exception != null)
        {
            logEntry += Environment.NewLine +
                        $"  Exception: {exception.GetType().FullName}: {exception.Message}" +
                        Environment.NewLine +
                        $"  StackTrace: {exception.StackTrace}";

            // Include inner exceptions
            var inner = exception.InnerException;
            while (inner != null)
            {
                logEntry += Environment.NewLine +
                            $"  InnerException: {inner.GetType().FullName}: {inner.Message}" +
                            Environment.NewLine +
                            $"  StackTrace: {inner.StackTrace}";
                inner = inner.InnerException;
            }
        }

        lock (_lock)
        {
            try
            {
                var logFilePath = GetCurrentLogFilePath();
                RotateIfNeeded(logFilePath);

                // Re-get path after potential rotation
                logFilePath = GetCurrentLogFilePath();

                using var writer = new StreamWriter(logFilePath, append: true);
                writer.WriteLine(logEntry);
            }
            catch
            {
                // Swallow logging exceptions to prevent app crashes
            }
        }
    }

    /// <summary>
    /// Gets the log file path for today's date.
    /// </summary>
    private static string GetCurrentLogFilePath()
    {
        var date = DateTime.Now.ToString("yyyy-MM-dd");
        return Path.Combine(_logDirectory, $"log_{date}.txt");
    }

    /// <summary>
    /// Rotates the log file if it exceeds the maximum size (10 MB).
    /// Renames current file with a sequence number and a new file will be created on next write.
    /// </summary>
    private static void RotateIfNeeded(string logFilePath)
    {
        if (!File.Exists(logFilePath)) return;

        var fileInfo = new FileInfo(logFilePath);
        if (fileInfo.Length < _maxFileSizeBytes) return;

        // Find next available rotation number
        var directory = Path.GetDirectoryName(logFilePath)!;
        var fileNameWithoutExt = Path.GetFileNameWithoutExtension(logFilePath);
        var extension = Path.GetExtension(logFilePath);

        int rotationNumber = 1;
        string rotatedPath;

        do
        {
            rotatedPath = Path.Combine(directory, $"{fileNameWithoutExt}_{rotationNumber:D3}{extension}");
            rotationNumber++;
        }
        while (File.Exists(rotatedPath));

        File.Move(logFilePath, rotatedPath);
    }

    /// <summary>
    /// Removes log files older than the retention period (30 days).
    /// </summary>
    private static void CleanupOldLogs()
    {
        try
        {
            if (!Directory.Exists(_logDirectory)) return;

            var cutoffDate = DateTime.Now.AddDays(-_retentionDays);
            var logFiles = Directory.GetFiles(_logDirectory, "log_*.txt");

            foreach (var file in logFiles)
            {
                var fileInfo = new FileInfo(file);
                if (fileInfo.LastWriteTime < cutoffDate)
                {
                    File.Delete(file);
                }
            }
        }
        catch
        {
            // Swallow cleanup exceptions
        }
    }

    /// <summary>
    /// Formats the caller information from file path and member name.
    /// Example: MainWindow.LoadData
    /// </summary>
    private static string FormatCaller(string filePath, string memberName)
    {
        if (string.IsNullOrEmpty(filePath))
            return memberName;

        var className = Path.GetFileNameWithoutExtension(filePath);
        return $"{className}.{memberName}";
    }
}
