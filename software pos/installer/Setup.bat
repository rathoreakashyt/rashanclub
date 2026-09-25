@echo off
REM ============================================================
REM  RashanKiDukan POS - Installer (C:\RashanKiDukan)
REM  - Right-click "Run as administrator" is recommended
REM ============================================================
title RashanKiDukan POS Installer
color 0A

echo.
echo  ================================================
echo    RashanKiDukan POS - Setup
echo    Install location: C:\RashanKiDukan
echo  ================================================
echo.

REM --- Admin check (shortcut creation needs it, install works without too)
net session >nul 2>&1
if %errorlevel%==0 (set ISADMIN=1) else (set ISADMIN=0)

set "TARGET=C:\RashanKiDukan"
set "SRC=%~dp0app"

if not exist "%SRC%\RashanKiDukan.exe" (
    echo  [ERROR] "app" folder not found next to this Setup.bat
    echo          Keep Setup.bat and the "app" folder together.
    pause
    exit /b 1
)

echo  [1/4] Copying files to %TARGET% ...
if not exist "%TARGET%" mkdir "%TARGET%"
xcopy "%SRC%\*" "%TARGET%\" /E /I /Y /Q >nul
if errorlevel 1 (
    echo  [ERROR] Copy failed. Close the running app and retry.
    pause
    exit /b 1
)
echo        Done.

echo  [2/4] Creating Start Menu shortcut ...
if "%ISADMIN%"=="1" (
    powershell -NoProfile -Command "$s=(New-Object -ComObject WScript.Shell).CreateShortcut([Environment]::GetFolderPath('CommonStartMenu')+'\Programs\RashanKiDukan.lnk');$s.TargetPath='%TARGET%\RashanKiDukan.exe';$s.WorkingDirectory='%TARGET%';$s.Save()" >nul 2>&1
) else (
    powershell -NoProfile -Command "$s=(New-Object -ComObject WScript.Shell).CreateShortcut([Environment]::GetFolderPath('StartMenu')+'\Programs\RashanKiDukan.lnk');$s.TargetPath='%TARGET%\RashanKiDukan.exe';$s.WorkingDirectory='%TARGET%';$s.Save()" >nul 2>&1
)
echo        Done.

echo  [3/4] Creating Desktop shortcut ...
if "%ISADMIN%"=="1" (
    powershell -NoProfile -Command "$s=(New-Object -ComObject WScript.Shell).CreateShortcut([Environment]::GetFolderPath('CommonDesktopDirectory')+'\RashanKiDukan.lnk');$s.TargetPath='%TARGET%\RashanKiDukan.exe';$s.WorkingDirectory='%TARGET%';$s.Save()" >nul 2>&1
) else (
    powershell -NoProfile -Command "$s=(New-Object -ComObject WScript.Shell).CreateShortcut([Environment]::GetFolderPath('Desktop')+'\RashanKiDukan.lnk');$s.TargetPath='%TARGET%\RashanKiDukan.exe';$s.WorkingDirectory='%TARGET%';$s.Save()" >nul 2>&1
)
echo        Done.

echo  [4/4] Uninstaller: %TARGET%\Uninstall.bat
(
    echo @echo off
    echo title RashanKiDukan Uninstaller
    echo taskkill /IM RashanKiDukan.exe /F ^>nul 2^>^&1
    echo timeout /t 2 /nobreak ^>nul
    echo rmdir /S /Q "C:\RashanKiDukan"
    echo del "%%Public%%\Desktop\RashanKiDukan.lnk" 2^>nul
    echo del "%%USERPROFILE%%\Desktop\RashanKiDukan.lnk" 2^>nul
    echo del "%%ProgramData%%\Microsoft\Windows\Start Menu\Programs\RashanKiDukan.lnk" 2^>nul
    echo del "%%APPDATA%%\Microsoft\Windows\Start Menu\Programs\RashanKiDukan.lnk" 2^>nul
    echo echo RashanKiDukan uninstalled. Local data in %%LOCALAPPDATA%%\RashanKiDukan is kept.
    echo pause
) > "%TARGET%\Uninstall.bat"

echo.
echo  ================================================
echo    Installation COMPLETE!
echo    App:      C:\RashanKiDukan\RashanKiDukan.exe
echo    Shortcut: Desktop + Start Menu "RashanKiDukan"
echo  ================================================
echo.
choice /C YN /M "Launch RashanKiDukan now"
if %errorlevel%==1 start "" "%TARGET%\RashanKiDukan.exe"
exit /b 0
