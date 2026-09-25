using System;
using System.Collections.Generic;
using System.Threading.Tasks;
using Microsoft.Data.Sqlite;
using System.IO;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Text.Json;

namespace RashanKiDukan.Services
{
    public class FeatureActivationService
    {
        private static readonly string DbPath = Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
            "RashanKiDukan", "data.db");

        // Keys jo user ne toggle ki hain lekin Save nahi kiya abhi tak.
        // Ye local (instant hide) par apply hoti hain, par cloud sync inhe
        // overwrite nahi karega jab tak Save nahi hota (save par clear).
        private static readonly HashSet<string> _pending = new(StringComparer.OrdinalIgnoreCase);

        public static void MarkPending(string key) => _pending.Add(key);
        public static void MarkPending(IEnumerable<string> keys) { foreach (var k in keys) _pending.Add(k); }
        public static void ClearPending() => _pending.Clear();
        public static bool HasPending(string key) => _pending.Contains(key);

        public static void EnsureTable()
        {
            using var conn = new SqliteConnection($"Data Source={DbPath}");
            conn.Open();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = @"
                CREATE TABLE IF NOT EXISTS FeatureActivations (
                    Id INTEGER PRIMARY KEY AUTOINCREMENT,
                    FeatureKey TEXT NOT NULL UNIQUE,
                    FeatureName TEXT NOT NULL,
                    FeatureGroup TEXT DEFAULT 'general',
                    IsActive INTEGER DEFAULT 1,
                    UpdatedAt TEXT
                );";
            cmd.ExecuteNonQuery();
        }

        public static Dictionary<string, bool> GetAllFeatures()
        {
            EnsureTable();
            var result = new Dictionary<string, bool>();
            using var conn = new SqliteConnection($"Data Source={DbPath}");
            conn.Open();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "SELECT FeatureKey, IsActive FROM FeatureActivations";
            using var reader = cmd.ExecuteReader();
            while (reader.Read())
            {
                result[reader.GetString(0)] = reader.GetInt32(1) == 1;
            }
            return result;
        }

        public static bool IsActive(string featureKey)
        {
            EnsureTable();
            using var conn = new SqliteConnection($"Data Source={DbPath}");
            conn.Open();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "SELECT IsActive FROM FeatureActivations WHERE FeatureKey = @key";
            cmd.Parameters.AddWithValue("@key", featureKey);
            var result = cmd.ExecuteScalar();
            return result == null || Convert.ToInt32(result) == 1;
        }

        public static List<FeatureActivationItem> GetAllItems()
        {
            EnsureTable();
            var result = new List<FeatureActivationItem>();
            using var conn = new SqliteConnection($"Data Source={DbPath}");
            conn.Open();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "SELECT FeatureKey, FeatureName, FeatureGroup, IsActive FROM FeatureActivations ORDER BY FeatureGroup, FeatureName";
            using var reader = cmd.ExecuteReader();
            while (reader.Read())
            {
                result.Add(new FeatureActivationItem
                {
                    FeatureKey = reader.GetString(0),
                    FeatureName = reader.GetString(1),
                    FeatureGroup = reader.GetString(2),
                    IsActive = reader.GetInt32(3) == 1
                });
            }
            return result;
        }

        public static void SaveFromJson(JsonDocument doc)
        {
            EnsureTable();
            if (!doc.RootElement.TryGetProperty("features", out var features)) return;
            using var conn = new SqliteConnection($"Data Source={DbPath}");
            conn.Open();
            foreach (var f in features.EnumerateArray())
            {
                var key = f.GetProperty("feature_key").GetString();
                if (string.IsNullOrEmpty(key) || _pending.Contains(key)) continue;
                var name = f.GetProperty("feature_name").GetString();
                var group = f.GetProperty("group").GetString();
                var isActive = f.GetProperty("is_active").ValueKind == JsonValueKind.True
                    || (f.GetProperty("is_active").ValueKind == JsonValueKind.Number && f.GetProperty("is_active").GetInt32() == 1);
                var updatedAt = f.GetProperty("updated_at").GetString();

                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"
                    INSERT OR REPLACE INTO FeatureActivations (FeatureKey, FeatureName, FeatureGroup, IsActive, UpdatedAt)
                    VALUES (@key, @name, @group, @active, @updated)";
                cmd.Parameters.AddWithValue("@key", key);
                cmd.Parameters.AddWithValue("@name", name ?? "");
                cmd.Parameters.AddWithValue("@group", group ?? "general");
                cmd.Parameters.AddWithValue("@active", isActive ? 1 : 0);
                cmd.Parameters.AddWithValue("@updated", updatedAt ?? "");
                cmd.ExecuteNonQuery();
            }
        }

        public static void UpdateLocal(List<(string FeatureKey, bool IsActive)> updates)
        {
            EnsureTable();
            using var conn = new SqliteConnection($"Data Source={DbPath}");
            conn.Open();
            foreach (var (key, active) in updates)
            {
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "UPDATE FeatureActivations SET IsActive = @active, UpdatedAt = @updated WHERE FeatureKey = @key";
                cmd.Parameters.AddWithValue("@active", active ? 1 : 0);
                cmd.Parameters.AddWithValue("@updated", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
                cmd.Parameters.AddWithValue("@key", key);
                cmd.ExecuteNonQuery();
            }
        }

        public static async Task SyncFromCloud(string baseUrl, string token)
        {
            EnsureTable();
            try
            {
                using var http = new HttpClient();
                http.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", token);
                var response = await http.GetAsync($"{baseUrl}/api/feature-activations");
                if (!response.IsSuccessStatusCode) return;

                var json = await response.Content.ReadAsStringAsync();
                using var doc = JsonDocument.Parse(json);
                var features = doc.RootElement.GetProperty("features");

                using var conn = new SqliteConnection($"Data Source={DbPath}");
                conn.Open();

                foreach (var f in features.EnumerateArray())
                {
                    var key = f.GetProperty("feature_key").GetString();
                    if (string.IsNullOrEmpty(key) || _pending.Contains(key)) continue;
                    var name = f.GetProperty("feature_name").GetString();
                    var group = f.GetProperty("group").GetString();
                    var isActive = f.GetProperty("is_active").ValueKind == JsonValueKind.True
                        || (f.GetProperty("is_active").ValueKind == JsonValueKind.Number && f.GetProperty("is_active").GetInt32() == 1);
                    var updatedAt = f.GetProperty("updated_at").GetString();

                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = @"
                        INSERT OR REPLACE INTO FeatureActivations (FeatureKey, FeatureName, FeatureGroup, IsActive, UpdatedAt)
                        VALUES (@key, @name, @group, @active, @updated)";
                    cmd.Parameters.AddWithValue("@key", key);
                    cmd.Parameters.AddWithValue("@name", name ?? "");
                    cmd.Parameters.AddWithValue("@group", group ?? "general");
                    cmd.Parameters.AddWithValue("@active", isActive ? 1 : 0);
                    cmd.Parameters.AddWithValue("@updated", updatedAt ?? "");
                    cmd.ExecuteNonQuery();
                }
            }
            catch (Exception ex)
            {
                System.Diagnostics.Debug.WriteLine($"FeatureActivation sync failed: {ex.Message}");
            }
        }
    }

    public class FeatureActivationItem
    {
        public string FeatureKey { get; set; } = "";
        public string FeatureName { get; set; } = "";
        public string FeatureGroup { get; set; } = "general";
        public bool IsActive { get; set; } = true;
    }
}
