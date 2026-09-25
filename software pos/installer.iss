; ============================================================
;  RashanKiDukan POS - Inno Setup Script
;  Creates a professional Windows installer (.exe)
;
;  Requirements:
;    - Inno Setup 6.x  (https://jrsoftware.org/isinfo.php)
;    - .NET 8 SDK      (for dotnet publish)
;    - The publish_output folder built via: dotnet publish -c Release -r win-x64 --self-contained true
;
;  Build: run  build-installer.bat  (or open this .iss in Inno Setup IDE)
; ============================================================

#define MyAppName      "RashanKiDukan POS"
#define MyAppPublisher "RashanKiDukan"
#define MyAppURL       "https://rashankidukanindia.com"
#define MyAppExeName   "RashanKiDukan.exe"
#define MyAppVersion   "1.2.1"
#define InstallDirName "RashanKiDukan"

[Setup]
AppId={{A3F8B2C1-4D5E-6F70-8912-3456789ABCDE}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
AppSupportURL={#MyAppURL}
AppUpdatesURL={#MyAppURL}
DefaultDirName={autopf}\{#InstallDirName}
DefaultGroupName={#MyAppName}
AllowNoIcons=yes
OutputDir=..\dist
OutputBaseFilename=Rashan Ki Dukan Setup
SetupIconFile=Assets\logo.ico
UninstallDisplayIcon={app}\{#MyAppExeName}
Compression=lzma2/ultra64
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=admin
PrivilegesRequiredOverridesAllowed=dialog
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
DisableProgramGroupPage=yes
DisableWelcomePage=no
LicenseFile=LICENSE.txt
CloseApplicationsFilter=RashanKiDukan.exe,RashanKiDukanUpdater.exe

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"; Flags: checkedonce
Name: "startmenuicon"; Description: "Create Start Menu shortcut"; GroupDescription: "{cm:AdditionalIcons}"; Flags: checkedonce
Name: "launchafterinstall"; Description: "Launch {#MyAppName} after installation"; GroupDescription: "Post-install"; Flags: checkedonce

[Files]
; Source: the entire publish_output directory (self-contained .NET 8 app)
Source: "publish_output\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs
; Source: the updater executable (built separately)
Source: "UpdaterOutput\RashanKiDukanUpdater.exe"; DestDir: "{app}"; Flags: ignoreversion skipifsourcedoesntexist

[Icons]
; Start Menu shortcut
Name: "{group}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"; WorkingDir: "{app}"; IconFilename: "{app}\{#MyAppExeName}"; Tasks: startmenuicon
Name: "{group}\Uninstall {#MyAppName}"; Filename: "{uninstallexe}"; Tasks: startmenuicon
; Desktop shortcut
Name: "{autodesktop}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"; WorkingDir: "{app}"; IconFilename: "{app}\{#MyAppExeName}"; Tasks: desktopicon

[Run]
Filename: "{app}\{#MyAppExeName}"; Description: "Launch {#MyAppName} now"; Flags: nowait postinstall skipifsilent; Tasks: launchafterinstall

[Registry]
; Register app in Windows Programs list
Root: HKLM; Subkey: "SOFTWARE\{#MyAppPublisher}\{#InstallDirName}"; ValueType: string; ValueName: "InstallPath"; ValueData: "{app}"; Flags: uninsdeletekey
Root: HKLM; Subkey: "SOFTWARE\{#MyAppPublisher}\{#InstallDirName}"; ValueType: string; ValueName: "Version"; ValueData: "{#MyAppVersion}"; Flags: uninsdeletekey

[UninstallDelete]
; Clean temp files created during runtime
Type: filesandordirs; Name: "{localappdata}\RashanKiDukan\logs"
Type: filesandordirs; Name: "{localappdata}\RashanKiDukan\cache"
Type: filesandordirs; Name: "{app}\app.publish"

[Code]
// ─── Close running app before install/uninstall ───
function InitializeSetup(): Boolean;
var
  ResultCode: Integer;
begin
  Result := True;
  if CheckForMutexes('{#MyAppPublisher}_{#InstallDirName}') then
  begin
    if MsgBox('{#MyAppName} is running. Close it now?',
              mbConfirmation, MB_YESNO + MB_DEFBUTTON1) = IDYES then
    begin
      Exec('taskkill', '/f /im {#MyAppExeName}', '', SW_HIDE, ewWaitUntilTerminated, ResultCode);
      Sleep(1000);
    end;
  end;
end;

// ─── Close running app before uninstall ───
function InitializeUninstall(): Boolean;
var
  ResultCode: Integer;
begin
  Result := True;
  Exec('taskkill', '/f /im {#MyAppExeName}', '', SW_HIDE, ewWaitUntilTerminated, ResultCode);
  Exec('taskkill', '/f /im RashanKiDukanUpdater.exe', '', SW_HIDE, ewWaitUntilTerminated, ResultCode);
  Sleep(500);
end;

// ─── Prompt to delete local data during uninstall ───
procedure CurUninstallStepChanged(CurUninstallStep: TUninstallStep);
var
  MsgResult: Integer;
  DataDir: String;
begin
  if CurUninstallStep = usUninstall then
  begin
    DataDir := ExpandConstant('{localappdata}\RashanKiDukan');
    if DirExists(DataDir) then
    begin
      MsgResult := MsgBox(
        'Delete local data?'+#13#10+#13#10+
        'Location: '+DataDir+#13#10+#10+
        'YES = Delete all (database, settings, logs)'+#13#10+
        'NO  = Keep for reinstall',
        mbConfirmation, MB_YESNO + MB_DEFBUTTON2);
      if MsgResult = IDYES then
      begin
        DelTree(DataDir, True, True, True);
      end;
    end;
  end;
end;
