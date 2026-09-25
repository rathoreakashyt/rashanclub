# Auto-Update System — Server Setup & Publish Guide

## Architecture

```
┌─────────────────────────────────────────────────┐
│  Main App (RashanKiDukan.exe)                   │
│  - Checks server for latest.json                │
│  - Downloads ZIP, SHA-256 verifies              │
│  - Launches RashanKiDukanUpdater.exe            │
│  - Closes itself                                │
└─────────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────┐
│  Updater (RashanKiDukanUpdater.exe)             │
│  - Waits for main app to exit                   │
│  - Backs up current install to rollback_YYYYMMDD│
│  - Extracts ZIP over install dir                │
│  - Restores data.db, logs, user config          │
│  - Restarts main app                            │
│  - On failure: auto-rollback from backup        │
└─────────────────────────────────────────────────┘
```

---

## Server Directory Structure

Host the following on any HTTP server (Apache/Nginx/cPanel file manager):

```
https://yourdomain.com/updates/
├── latest.json                    ← Version metadata
└── releases/
    └── RashanKiDukan-1.1.0.zip   ← Update ZIP package
```

---

## latest.json Format

```json
{
  "version": "1.1.0",
  "minimum_version": "1.0.0",
  "mandatory": false,
  "download_url": "https://rashankidukanindia.com/updates/releases/RashanKiDukan-1.1.0.zip",
  "sha256": "a1b2c3d4e5f6...full 64 char hex...",
  "release_date": "2026-09-22",
  "release_notes": [
    "Bug fixes and improvements",
    "Added new report pages"
  ]
}
```

### Field Reference

| Field | Required | Description |
|-------|----------|-------------|
| `version` | Yes | Semantic version (Major.Minor.Patch) |
| `minimum_version` | No | Oldest version that can update directly. Below this → mandatory update. |
| `mandatory` | No | If `true`, user cannot skip. Default `false`. |
| `download_url` | Yes | Full URL to the ZIP file |
| `sha256` | Yes | SHA-256 hash of the ZIP (lowercase hex, 64 chars) |
| `release_date` | No | ISO date string |
| `release_notes` | No | Array of strings shown to user |

---

## How to Generate SHA-256 Hash

### PowerShell (Windows)
```powershell
(Get-FileHash "RashanKiDukan-1.1.0.zip" -Algorithm SHA256).Hash.ToLower()
```

### Linux/macOS
```bash
sha256sum RashanKiDukan-1.1.0.zip
```

---

## Publish Workflow (Step by Step)

### 1. Bump Version

Edit `RashanKiDukan.csproj`:
```xml
<Version>1.1.0</Version>
<AssemblyVersion>1.1.0.0</AssemblyVersion>
<FileVersion>1.1.0.0</FileVersion>
```

### 2. Build Everything

Double-click `build-installer.bat`. This will:
1. Build the Updater (`RashanKiDukanUpdater.exe`)
2. Publish the main app (self-contained, win-x64)
3. Compile the Inno Setup installer
4. Output at `dist\Rashan Ki Dukan Setup.exe`

### 3. Create Update Package

```powershell
# From the software pos directory
cd "C:\Users\Akash\Desktop\pos\rashankidukan\software pos"

# Create the update ZIP (only include publish_output contents)
Compress-Archive -Path "publish_output\*" -DestinationPath "releases\RashanKiDukan-1.1.0.zip"
```

The ZIP should contain the full published app files (exe, dlls, etc.)

### 4. Compute SHA-256

```powershell
(Get-FileHash "releases\RashanKiDukan-1.1.0.zip" -Algorithm SHA256).Hash.ToLower()
```

### 5. Update latest.json

```json
{
  "version": "1.1.0",
  "download_url": "https://rashankidukanindia.com/updates/releases/RashanKiDukan-1.1.0.zip",
  "sha256": "<paste hash here>",
  "release_date": "2026-09-22",
  "release_notes": [
    "Added auto-update support",
    "Bug fixes"
  ]
}
```

### 6. Upload to Server

Upload to cPanel File Manager:
- `latest.json` → `/public_html/updates/latest.json`
- ZIP file → `/public_html/updates/releases/RashanKiDukan-1.1.0.zip`

### 7. Test

Open in browser: `https://rashankidukanindia.com/updates/latest.json`
Should return valid JSON.

---

## App Update Flow

1. **On startup**: App silently checks `{server}/updates/latest.json` in background
2. **If update available**: Shows notification dialog with version + release notes
3. **User clicks "Update Now"**: Downloads ZIP with progress bar
4. **SHA-256 verified**: If hash mismatch → download deleted, update cancelled
5. **"Ready to Update" dialog**: User clicks "Restart & Update"
6. **Launches `RashanKiDukanUpdater.exe`**: Main app closes
7. **Updater waits 2s** for process to fully exit
8. **Backs up** current install to `C:\ProgramData\Rashan Ki Dukan\backups\rollback_YYYYMMDD_HHMMSS\`
9. **Extracts** ZIP over install directory (`C:\RashanKiDukan\`)
10. **Preserves**: `data.db`, `installed.flag`, logs, user config
11. **Restarts** main app
12. **On failure**: Auto-rollback from backup, shows error

### Manual Check

Users can also check via **Settings → Check for Updates** in the dashboard.

### Skipping Versions

- Non-mandatory updates can be "Skipped" — won't prompt again for that version
- Newer version overrides previous skip

---

## Data Safety

These are **NEVER overwritten** by updates:
- `data.db` (SQLite database)
- `installed.flag`
- `app_settings.json` (user config)
- Log files in `C:\ProgramData\Rashan Ki Dukan\logs\`

---

## Rollback

If the update fails (corrupted ZIP, crash, etc.):
- Updater automatically restores from the backup directory
- Original files are preserved in `rollback_YYYYMMDD_HHMMSS\`

Manual rollback:
1. Go to `C:\ProgramData\Rashan Ki Dukan\backups\`
2. Find the most recent `rollback_*` folder
3. Copy all files back to `C:\RashanKiDukan\`

---

## Update Server URL Configuration

The update URL is configurable per-installation via the `app_settings` database table:

```sql
-- Set custom update server URL
INSERT INTO app_settings (key, value) VALUES ('update_server_url', 'https://myserver.com/updates');

-- Or update existing
UPDATE app_settings SET value = 'https://myserver.com/updates' WHERE key = 'update_server_url';
```

If not set, defaults to: `{server_url}/updates` (derived from the main API server URL).

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| "Updater not found" | Run `build-installer.bat` which builds the updater |
| Download fails | Check URL is accessible, SSL is valid |
| SHA-256 mismatch | Re-download ZIP, recompute hash, update `latest.json` |
| Update doesn't appear | Check `app_settings` for `update_server_url`, verify `latest.json` is valid JSON |
| Rollback needed | Copy files from `C:\ProgramData\Rashan Ki Dukan\backups\rollback_*` |
| Logs | Check `C:\ProgramData\Rashan Ki Dukan\logs\updater_YYYY-MM-DD.log` |
