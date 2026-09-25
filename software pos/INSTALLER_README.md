# RashanKiDukan POS - Windows Installer

Professional Windows installer for the RashanKiDukan POS desktop application.

## What It Does

- Installs the self-contained .NET 8 WPF app (no .NET runtime needed on target machine)
- Creates Desktop and Start Menu shortcuts
- Registers in Windows "Add or Remove Programs"
- Optional local data cleanup on uninstall
- UAC admin elevation prompt

## Prerequisites (Build Machine)

| Tool | Version | Download |
|------|---------|----------|
| .NET 8 SDK | 8.0+ | https://dotnet.microsoft.com/download/dotnet/8.0 |
| Inno Setup | 6.x | https://jrsoftware.org/isdl.php |

## Quick Build (One Command)

```bat
cd "software pos"
build-installer.bat
```

This will:
1. `dotnet publish` the WPF app (self-contained, win-x64)
2. Compile `installer.iss` with Inno Setup
3. Output: `dist\Rashan Ki Dukan Setup.exe`

## Build Output

```
dist/
  Rashan Ki Dukan Setup.exe    (~190 MB)
```

The installer is a single `.exe` — ready to share via USB, Google Drive, or any distribution method.

## Installation Flow

```
Welcome → License → Install Location → Installing → Done
                                                  ├─ Desktop shortcut (optional)
                                                  ├─ Start Menu shortcut (optional)
                                                  └─ Launch now (optional)
```

## Installer Features

| Feature | Details |
|---------|---------|
| Default path | `C:\Program Files\RashanKiDukan` |
| Admin check | UAC elevation dialog |
| Shortcuts | Desktop + Start Menu Programs |
| Add/Remove | Registered in Windows Programs list |
| Uninstaller | Built-in, accessible from Start Menu or Settings |
| Data cleanup | Prompts to delete `%LOCALAPPDATA%\RashanKiDukan` on uninstall |

## Local Data

The app stores data in:
```
%LOCALAPPDATA%\RashanKiDukan\
  data.db          (SQLite database)
```

This folder is preserved across reinstalls unless the user chooses to delete it during uninstall.

## Customization

### Change install directory
Edit `installer.iss` line:
```
DefaultDirName={autopf}\RashanKiDukan
```
`{autopf}` = `C:\Program Files` (64-bit) or `C:\Program Files (x86)`.

### Change version
Edit `installer.iss` line:
```
#define MyAppVersion "1.0.0"
```

### Remove uninstall data prompt
Delete the `[Code]` section from `installer.iss` or set the prompt to always delete.

## File Structure

```
software pos/
├── installer.iss              # Inno Setup script
├── build-installer.bat        # One-click build script
├── LICENSE.txt                # License text shown during install
├── RashanKiDukan.csproj       # Main WPF project
├── Assets/
│   └── logo.ico               # App icon (used in installer)
├── publish_output/             # Build output (source for installer)
│   ├── RashanKiDukan.exe
│   ├── *.dll
│   ├── Database/
│   ├── LatoFont/
│   └── ...
├── SetupProject/              # Legacy WinForms installer (can be deleted)
└── installer/                 # Legacy bat installer (can be deleted)
```

## Troubleshooting

### "Inno Setup 6 not found"
Install Inno Setup from https://jrsoftware.org/isdl.php, then re-run `build-installer.bat`.

### "dotnet publish failed"
Ensure .NET 8 SDK is installed: `dotnet --list-sdks`

### Installer won't run on target machine
- Ensure target is Windows 10+ (x64)
- Right-click → Run as Administrator
