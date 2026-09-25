@echo off
title RashanKiDukan - Build Setup
echo ============================================
echo    Building RashanKiDukan Setup...
echo ============================================
echo.

:: Step 1: Build the installer
echo [1/3] Building installer...
dotnet publish "SetupProject/RashanKiDukanSetup.csproj" -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true -o "SetupProject/bin/publish" >nul 2>&1
if %errorLevel% neq 0 (
    echo [ERROR] Installer build failed!
    pause
    exit /b 1
)
echo     Installer built successfully.

:: Step 2: Prepare data folder
echo [2/3] Copying app files to data folder...
if exist "SetupProject/bin/publish/data" rmdir /s /q "SetupProject/bin/publish/data"
mkdir "SetupProject/bin/publish/data"
xcopy /E /Y /Q "publish_output\*" "SetupProject\bin\publish\data\" >nul
echo     App files copied.

:: Step 3: Create distributable zip
echo [3/3] Creating distributable package...
cd SetupProject/bin/publish
if exist "..\..\..\RashanKiDukan-Setup.zip" del "..\..\..\RashanKiDukan-Setup.zip"
powershell -Command "Compress-Archive -Path 'RashanKiDukanSetup.exe','data' -DestinationPath '..\..\..\RashanKiDukan-Setup.zip'"
cd ..\..\..

echo.
echo ============================================
echo    Build Complete!
echo ============================================
echo.
echo    Installer: SetupProject/bin/publish/RashanKiDukanSetup.exe
echo    Package:   RashanKiDukan-Setup.zip
echo.
echo    To install: Extract zip, run RashanKiDukanSetup.exe
echo ============================================
echo.
pause
