@echo off
title RashanKiDukan - Build Installer
echo ============================================
echo    RashanKiDukan POS - Installer Builder
echo ============================================
echo.

:: ─── CONFIG ───
set "PROJECT_DIR=%~dp0"
set "PUBLISH_DIR=%PROJECT_DIR%publish_output"
set "UPDATER_OUTPUT=%PROJECT_DIR%UpdaterOutput"
set "DIST_DIR=%PROJECT_DIR%..\dist"
set "ISCC_PATH=C:\Program Files (x86)\Inno Setup 6\ISCC.exe"

:: ─── STEP 1: Publish the updater (small, separate process) ───
echo [1/5] Building updater process...
dotnet publish "%PROJECT_DIR%Updater\RashanKiDukanUpdater.csproj" -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true -p:EnableCompressionInSingleFile=true -o "%UPDATER_OUTPUT%" >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Updater build failed!
    pause
    exit /b 1
)
echo       Updater built.

:: ─── STEP 2: Publish the main .NET app ───
echo [2/5] Publishing .NET app (self-contained, win-x64)...
dotnet publish "%PROJECT_DIR%RashanKiDukan.csproj" -c Release -r win-x64 --self-contained true -p:PublishSingleFile=false -o "%PUBLISH_DIR%" >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] dotnet publish failed!
    echo         Make sure .NET 8 SDK is installed.
    pause
    exit /b 1
)
echo       Published to: %PUBLISH_DIR%

:: ─── STEP 3: Copy updater into publish_output ───
echo [3/5] Copying updater to publish output...
if exist "%UPDATER_OUTPUT%\RashanKiDukanUpdater.exe" (
    copy /Y "%UPDATER_OUTPUT%\RashanKiDukanUpdater.exe" "%PUBLISH_DIR%\RashanKiDukanUpdater.exe" >nul
    echo       Updater copied.
) else (
    echo       [WARN] Updater exe not found — updater feature may be missing.
)

:: ─── STEP 4: Verify Inno Setup ───
echo [4/5] Checking Inno Setup...
if not exist "%ISCC_PATH%" (
    echo [ERROR] Inno Setup 6 not found at:
    echo         %ISCC_PATH%
    echo.
    echo         Download from: https://jrsoftware.org/isdl.php
    echo         Install, then re-run this script.
    pause
    exit /b 1
)
echo       Found: %ISCC_PATH%

:: ─── STEP 5: Build installer ───
echo [5/5] Building installer with Inno Setup...
if not exist "%DIST_DIR%" mkdir "%DIST_DIR%"
"%ISCC_PATH%" "%PROJECT_DIR%installer.iss"
if %errorlevel% neq 0 (
    echo [ERROR] Inno Setup compilation failed!
    echo         Check the output above for errors.
    pause
    exit /b 1
)

:: ─── DONE ───
echo.
echo ============================================
echo    Build Complete!
echo ============================================
echo.
echo    Installer:  %DIST_DIR%\Rashan Ki Dukan Setup.exe
echo    Updater:    %PUBLISH_DIR%\RashanKiDukanUpdater.exe
echo.
echo ============================================
pause
