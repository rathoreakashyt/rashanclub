@echo off
title RashanKiDukan Installer
echo ============================================
echo    RashanKiDukan POS - Installer
echo ============================================
echo.

:: Check admin privileges
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo [!] Right-click this file and select "Run as Administrator"
    echo.
    pause
    exit /b 1
)

set INSTALL_DIR=C:\RashanKiDukan
set SOURCE_DIR=%~dp0app

echo [1/4] Creating installation folder: %INSTALL_DIR%
if not exist "%INSTALL_DIR%" mkdir "%INSTALL_DIR%"

echo [2/4] Copying files...
xcopy /E /Y /Q "%SOURCE_DIR%\*" "%INSTALL_DIR%\" >nul

echo [3/4] Creating Desktop shortcut...
powershell -Command "$s=(New-Object -COM WScript.Shell).CreateShortcut('%USERPROFILE%\Desktop\RashanKiDukan.lnk'); $s.TargetPath='%INSTALL_DIR%\RashanKiDukan.exe'; $s.WorkingDirectory='%INSTALL_DIR%'; $s.Save()"

echo [4/4] Done!
echo.
echo ============================================
echo    Installation Complete!
echo    Location: %INSTALL_DIR%
echo    Desktop Shortcut: Created
echo ============================================
echo.
echo You can now double-click "RashanKiDukan" on your Desktop to launch.
echo.
pause
