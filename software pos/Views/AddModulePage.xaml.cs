using System;
using System.IO;
using System.IO.Compression;
using System.Text.Json;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using Microsoft.Data.Sqlite;
using Microsoft.Win32;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class AddModulePage : UserControl
    {
        private readonly DatabaseService _db = new DatabaseService();
        private readonly MainDashboard? _dashboard;
        private string? _selectedFilePath;

        public AddModulePage() { InitializeComponent(); }
        public AddModulePage(MainDashboard dashboard) : this() { _dashboard = dashboard; }

        // ═══════════════════════════════════════════════════════════════════
        // DRAG & DROP
        // ═══════════════════════════════════════════════════════════════════

        private void DropZone_DragOver(object sender, DragEventArgs e)
        {
            if (e.Data.GetDataPresent(DataFormats.FileDrop))
            {
                string[] files = (string[])e.Data.GetData(DataFormats.FileDrop);
                if (files.Length == 1 && files[0].EndsWith(".zip", StringComparison.OrdinalIgnoreCase))
                {
                    e.Effects = DragDropEffects.Copy;
                    DropZone.BorderBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#6366F1"));
                    DropZone.Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#EEF2FF"));
                }
                else
                {
                    e.Effects = DragDropEffects.None;
                }
            }
            else
            {
                e.Effects = DragDropEffects.None;
            }
            e.Handled = true;
        }

        private void DropZone_DragLeave(object sender, DragEventArgs e)
        {
            DropZone.BorderBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#9CA3AF"));
            DropZone.Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#F9FAFB"));
        }

        private void DropZone_Drop(object sender, DragEventArgs e)
        {
            DropZone.BorderBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#9CA3AF"));
            DropZone.Background = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#F9FAFB"));

            if (e.Data.GetDataPresent(DataFormats.FileDrop))
            {
                string[] files = (string[])e.Data.GetData(DataFormats.FileDrop);
                if (files.Length == 1 && files[0].EndsWith(".zip", StringComparison.OrdinalIgnoreCase))
                {
                    SelectFile(files[0]);
                }
                else
                {
                    ShowStatus("Only .zip files are accepted.", isError: true);
                }
            }
        }

        private void DropZone_Click(object sender, MouseButtonEventArgs e)
        {
            var dlg = new OpenFileDialog
            {
                Filter = "ZIP Files (*.zip)|*.zip",
                Title = "Select Module ZIP File"
            };
            if (dlg.ShowDialog() == true)
            {
                SelectFile(dlg.FileName);
            }
        }

        // ═══════════════════════════════════════════════════════════════════
        // FILE SELECTION
        // ═══════════════════════════════════════════════════════════════════

        private void SelectFile(string filePath)
        {
            _selectedFilePath = filePath;
            var fi = new FileInfo(filePath);
            txtFileName.Text = fi.Name;
            txtFileSize.Text = FormatFileSize(fi.Length);
            pnlFileInfo.Visibility = Visibility.Visible;
            btnUpload.IsEnabled = true;
            txtStatus.Visibility = Visibility.Collapsed;
        }

        private void BtnRemoveFile_Click(object sender, RoutedEventArgs e)
        {
            _selectedFilePath = null;
            pnlFileInfo.Visibility = Visibility.Collapsed;
            btnUpload.IsEnabled = false;
            txtStatus.Visibility = Visibility.Collapsed;
        }

        // ═══════════════════════════════════════════════════════════════════
        // UPLOAD & INSTALL
        // ═══════════════════════════════════════════════════════════════════

        private void BtnUpload_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrEmpty(_selectedFilePath) || !File.Exists(_selectedFilePath))
            {
                ShowStatus("Selected file not found. Please select again.", isError: true);
                return;
            }

            try
            {
                // Ensure modules folder exists
                string modulesDir = Path.Combine(
                    Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                    "RashanKiDukan", "modules");
                Directory.CreateDirectory(modulesDir);

                // Copy ZIP to modules folder
                string destFileName = Path.GetFileName(_selectedFilePath);
                string destPath = Path.Combine(modulesDir, destFileName);

                // If already exists, add timestamp
                if (File.Exists(destPath))
                {
                    string nameWithoutExt = Path.GetFileNameWithoutExtension(destFileName);
                    destPath = Path.Combine(modulesDir, $"{nameWithoutExt}_{DateTime.Now:yyyyMMddHHmmss}.zip");
                }

                File.Copy(_selectedFilePath, destPath, overwrite: false);

                // Extract module.json info from ZIP
                string moduleName = Path.GetFileNameWithoutExtension(destFileName);
                string description = "";
                string version = "1.0.0";
                string author = "Unknown";

                try
                {
                    using var archive = ZipFile.OpenRead(destPath);
                    foreach (var entry in archive.Entries)
                    {
                        if (entry.Name.Equals("module.json", StringComparison.OrdinalIgnoreCase))
                        {
                            using var stream = entry.Open();
                            using var reader = new StreamReader(stream);
                            string json = reader.ReadToEnd();
                            var doc = JsonDocument.Parse(json);
                            var root = doc.RootElement;

                            if (root.TryGetProperty("name", out var nameProp))
                                moduleName = nameProp.GetString() ?? moduleName;
                            if (root.TryGetProperty("description", out var descProp))
                                description = descProp.GetString() ?? "";
                            if (root.TryGetProperty("version", out var verProp))
                                version = verProp.GetString() ?? "1.0.0";
                            if (root.TryGetProperty("author", out var authProp))
                                author = authProp.GetString() ?? "Unknown";
                            break;
                        }
                    }
                }
                catch { /* module.json not found or invalid — use defaults */ }

                // Save to DB
                SaveModuleToDb(moduleName, description, version, author, destPath);

                ShowStatus($"Module '{moduleName}' (v{version}) installed successfully!", isError: false);
                btnUpload.IsEnabled = false;
                _selectedFilePath = null;
                pnlFileInfo.Visibility = Visibility.Collapsed;
            }
            catch (Exception ex)
            {
                ShowStatus($"Installation failed: {ex.Message}", isError: true);
            }
        }

        private void SaveModuleToDb(string name, string description, string version, string author, string zipPath)
        {
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = @"INSERT INTO installed_modules (name, description, version, author, zip_path, is_enabled, installed_at, updated_at)
                                VALUES (@name, @desc, @ver, @author, @zip, 1, @now, @now);
                                SELECT last_insert_rowid();";
            cmd.Parameters.AddWithValue("@name", name);
            cmd.Parameters.AddWithValue("@desc", description);
            cmd.Parameters.AddWithValue("@ver", version);
            cmd.Parameters.AddWithValue("@author", author);
            cmd.Parameters.AddWithValue("@zip", zipPath);
            cmd.Parameters.AddWithValue("@now", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
            var newId = Convert.ToInt64(cmd.ExecuteScalar());
            Services.SyncService.EnqueueSync("installed_modules", newId, "insert");
        }

        // ═══════════════════════════════════════════════════════════════════
        // HELPERS
        // ═══════════════════════════════════════════════════════════════════

        private void ShowStatus(string message, bool isError)
        {
            txtStatus.Text = message;
            txtStatus.Foreground = new SolidColorBrush(
                (Color)ColorConverter.ConvertFromString(isError ? "#DC2626" : "#059669"));
            txtStatus.Visibility = Visibility.Visible;
        }

        private static string FormatFileSize(long bytes)
        {
            if (bytes < 1024) return $"{bytes} B";
            if (bytes < 1024 * 1024) return $"{bytes / 1024.0:F1} KB";
            return $"{bytes / (1024.0 * 1024.0):F2} MB";
        }
    }
}
