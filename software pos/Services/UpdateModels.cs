using System.Text.Json;

namespace RashanKiDukan.Services;

/// <summary>
/// Server response for /updates/latest.json
/// </summary>
public class UpdateInfo
{
    public string Version { get; set; } = "";
    public string MinimumVersion { get; set; } = "";
    public bool Mandatory { get; set; }
    public string DownloadUrl { get; set; } = "";
    /// <summary>Google Drive link (optional) — primary source, server fallback.</summary>
    public string DownloadUrlDrive { get; set; } = "";
    public string Sha256 { get; set; } = "";
    public string ReleaseDate { get; set; } = "";
    public List<string> ReleaseNotes { get; set; } = new();

    /// <summary>
    /// Parse UpdateInfo from a JsonElement (flexible property names).
    /// </summary>
    public static UpdateInfo? FromJson(JsonElement root)
    {
        try
        {
            var info = new UpdateInfo();
            if (root.TryGetProperty("version", out var v)) info.Version = v.GetString() ?? "";
            if (root.TryGetProperty("minimum_version", out var mv)) info.MinimumVersion = mv.GetString() ?? "";
            if (root.TryGetProperty("mandatory", out var m)) info.Mandatory = m.GetBoolean();
            if (root.TryGetProperty("download_url", out var du)) info.DownloadUrl = du.GetString() ?? "";
            if (root.TryGetProperty("download_url_drive", out var dud)) info.DownloadUrlDrive = dud.GetString() ?? "";
            else if (root.TryGetProperty("drive_url", out var durl)) info.DownloadUrlDrive = durl.GetString() ?? "";
            if (root.TryGetProperty("sha256", out var s)) info.Sha256 = s.GetString() ?? "";
            if (root.TryGetProperty("release_date", out var rd)) info.ReleaseDate = rd.GetString() ?? "";
            if (root.TryGetProperty("release_notes", out var rn) && rn.ValueKind == JsonValueKind.Array)
            {
                foreach (var note in rn.EnumerateArray())
                    info.ReleaseNotes.Add(note.GetString() ?? "");
            }
            return info;
        }
        catch { return null; }
    }
}

/// <summary>
/// Semantic version comparison (handles 1.9.0 → 1.10.0 correctly).
/// </summary>
public class SemanticVersion : IComparable<SemanticVersion>
{
    public int Major { get; }
    public int Minor { get; }
    public int Patch { get; }

    public SemanticVersion(string version)
    {
        // Strip leading 'v' if present
        version = version.TrimStart('v', 'V');
        var parts = version.Split('.');
        Major = parts.Length > 0 && int.TryParse(parts[0], out var m) ? m : 0;
        Minor = parts.Length > 1 && int.TryParse(parts[1], out var n) ? n : 0;
        Patch = parts.Length > 2 && int.TryParse(parts[2], out var p) ? p : 0;
    }

    public int CompareTo(SemanticVersion? other)
    {
        if (other == null) return 1;
        int cmp = Major.CompareTo(other.Major);
        if (cmp != 0) return cmp;
        cmp = Minor.CompareTo(other.Minor);
        if (cmp != 0) return cmp;
        return Patch.CompareTo(other.Patch);
    }

    public static bool operator >(SemanticVersion a, SemanticVersion b) => a.CompareTo(b) > 0;
    public static bool operator <(SemanticVersion a, SemanticVersion b) => a.CompareTo(b) < 0;
    public static bool operator >=(SemanticVersion a, SemanticVersion b) => a.CompareTo(b) >= 0;
    public static bool operator <=(SemanticVersion a, SemanticVersion b) => a.CompareTo(b) <= 0;

    public override string ToString() => $"{Major}.{Minor}.{Patch}";
}

/// <summary>
/// Current status of the update process.
/// </summary>
public enum UpdateProgressStatus
{
    Checking,
    Available,
    Downloading,
    DownloadComplete,
    Verifying,
    Ready,
    Installing,
    Error,
    Offline
}

/// <summary>
/// Progress data for download UI.
/// </summary>
public class UpdateProgress
{
    public UpdateProgressStatus Status { get; set; }
    public long DownloadedBytes { get; set; }
    public long TotalBytes { get; set; }
    public double Percentage => TotalBytes > 0 ? Math.Min(100, DownloadedBytes * 100.0 / TotalBytes) : 0;
    public string SpeedBytesPerSec { get; set; } = "";
    public double SpeedBytesPerSecRaw { get; set; }
    public bool HasTotalBytes => TotalBytes > 0;
    public string ErrorMessage { get; set; } = "";
    public UpdateInfo? UpdateInfo { get; set; }
}
