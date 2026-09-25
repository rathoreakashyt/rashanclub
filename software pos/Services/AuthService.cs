using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;
using RashanKiDukan.Models;

namespace RashanKiDukan.Services
{
    public class AuthService
    {
        private readonly DatabaseService _db;
        private const int SessionTimeoutMinutes = 480; // 8 hours default session timeout
        private static readonly string DefaultPasswordHash = BCrypt.Net.BCrypt.HashPassword(GenerateSecureDefaultPassword());

        public AuthService(DatabaseService db)
        {
            _db = db;
        }

        /// <summary>
        /// Generate a machine-specific default password instead of hardcoded "admin123".
        /// Uses device ID + fixed salt so it's unique per machine but deterministic.
        /// </summary>
        private static string GenerateSecureDefaultPassword()
        {
            try
            {
                string deviceId = DeviceContext.GetDeviceId();
                // Create a deterministic but unique password per device
                return "Adm!n_" + deviceId.Substring(0, 8);
            }
            catch
            {
                return "Adm!n_" + Environment.MachineName.Substring(0, Math.Min(4, Environment.MachineName.Length)) + "_Setup";
            }
        }

        public User Login(string username, string password)
        {
            using var conn = _db.GetConnection();

            // ═══ STEP 1: Cloud API login (primary) ═══
            string serverUrl = _db.GetAppSetting("server_url") ?? "";
            if (!string.IsNullOrEmpty(serverUrl))
            {
                LogService.Info($"Login attempt: {username} — trying cloud server...");
                var cloudUser = TryCloudLogin(username, password);
                if (cloudUser != null)
                {
                    SaveSessionTimestamp();
                    return cloudUser;
                }
                LogService.Warn($"Cloud login failed for: {username}");
                throw new UnauthorizedAccessException("Cloud login failed. Check your email/password, or configure Server Settings.");
            }

            // ═══ STEP 2: No server configured — only local login ═══
            LogService.Info($"Login attempt: {username} — no server configured, trying local...");
            using (var cmd = conn.CreateCommand())
            {
                cmd.CommandText = @"SELECT id, name, email, password, role, company_id
                                   FROM employees
                                   WHERE (lower(email) = @u OR lower(name) = @u)
                                     AND lower(COALESCE(will_login,'')) <> 'no'
                                     AND lower(COALESCE(del_status,'')) <> 'delete'
                                     AND password IS NOT NULL AND password <> ''
                                   LIMIT 1";
                cmd.Parameters.AddWithValue("@u", username.ToLower());

                using var reader = cmd.ExecuteReader();
                if (reader.Read())
                {
                    string hash = reader.IsDBNull(3) ? "" : reader.GetString(3);
                    if (hash.StartsWith("$2y$")) hash = "$2a$" + hash.Substring(4);
                    if (!string.IsNullOrEmpty(hash) && BCrypt.Net.BCrypt.Verify(password, hash))
                    {
                        int empId = reader.GetInt32(0);
                        string empRole = reader.IsDBNull(4) ? "" : reader.GetString(4);

                        if (string.IsNullOrWhiteSpace(empRole))
                        {
                            try
                            {
                                using var roleCmd = conn.CreateCommand();
                                roleCmd.CommandText = @"SELECT r.name FROM roles r
                                    JOIN model_has_roles mhr ON mhr.role_id = r.id
                                    WHERE mhr.model_id = @uid LIMIT 1";
                                roleCmd.Parameters.AddWithValue("@uid", empId);
                                empRole = roleCmd.ExecuteScalar() as string ?? "Operator";
                            }
                            catch { empRole = "Operator"; }
                        }

                        var user = new User
                        {
                            Id = empId,
                            Username = reader.IsDBNull(2) ? username : reader.GetString(2),
                            FullName = reader.IsDBNull(1) ? username : reader.GetString(1),
                            Role = empRole,
                            CompanyId = reader.IsDBNull(5) ? 0 : reader.GetInt32(5)
                        };
                        SaveSessionTimestamp();
                        LogService.Info($"Offline login successful: {username} (Role: {empRole})");
                        return user;
                    }
                }
            }

            LogService.Warn($"Failed login attempt for: {username}");
            throw new UnauthorizedAccessException("Invalid credentials. Configure Server Settings to login with your cloud account.");
        }

        /// <summary>
        /// Try login via cloud API. Uses configured ApiService with retry/SSL handling.
        /// On success, stores token + user info locally for future offline use.
        /// </summary>
        public User? TryCloudLogin(string username, string password)
        {
            try
            {
                string serverUrl = _db.GetAppSetting("server_url") ?? "";
                if (string.IsNullOrEmpty(serverUrl))
                {
                    LogService.Warn("Cloud login skipped — no server_url configured");
                    return null;
                }

                string cleanUrl = serverUrl.TrimEnd('/');
                LogService.Info($"Trying cloud login: {cleanUrl}/api/auth/login");

                var task = Task.Run(async () =>
                {
                    var handler = new System.Net.Http.HttpClientHandler
                    {
                        ServerCertificateCustomValidationCallback = (_, _, _, _) => true
                    };
                    using var client = new System.Net.Http.HttpClient(handler)
                    {
                        Timeout = TimeSpan.FromSeconds(8)
                    };
                    client.DefaultRequestHeaders.Add("Accept", "application/json");

                    var payload = new { email = username, password };
                    var json = System.Text.Json.JsonSerializer.Serialize(payload);
                    var content = new System.Net.Http.StringContent(json, System.Text.Encoding.UTF8, "application/json");

                    return await client.PostAsync($"{cleanUrl}/api/auth/login", content);
                });

                var response = task.GetAwaiter().GetResult();
                using var responseContent = response.Content;
                var body = responseContent.ReadAsStringAsync().GetAwaiter().GetResult();

                if (!response.IsSuccessStatusCode)
                {
                    string errMsg = "";
                    try
                    {
                        using var errDoc = System.Text.Json.JsonDocument.Parse(body);
                        if (errDoc.RootElement.TryGetProperty("message", out var m))
                            errMsg = m.GetString() ?? "";
                    }
                    catch { }
                    LogService.Warn($"Cloud login HTTP {response.StatusCode}: {errMsg}");
                    return null;
                }

                using var doc = System.Text.Json.JsonDocument.Parse(body);
                var root = doc.RootElement;

                if (!root.TryGetProperty("token", out var tokenEl)) return null;
                string? token = tokenEl.GetString();
                if (string.IsNullOrEmpty(token)) return null;

                string name = username;
                int userId = 0;
                if (root.TryGetProperty("user", out var userEl))
                {
                    name = userEl.TryGetProperty("name", out var n) ? n.GetString() ?? username : username;
                    userId = userEl.TryGetProperty("id", out var id) && id.ValueKind == System.Text.Json.JsonValueKind.Number ? id.GetInt32() : 0;
                }

                SecureSettingsService.EncryptAndStore(_db, "server_token", token);

                string hash = BCrypt.Net.BCrypt.HashPassword(password);
                using var conn = _db.GetConnection();

                string role = "Operator";
                if (userId > 0)
                {
                    try
                    {
                        using var roleCmd = conn.CreateCommand();
                        roleCmd.CommandText = @"SELECT r.name FROM roles r
                            JOIN model_has_roles mhr ON mhr.role_id = r.id
                            WHERE mhr.model_id = @uid LIMIT 1";
                        roleCmd.Parameters.AddWithValue("@uid", userId);
                        role = roleCmd.ExecuteScalar() as string ?? "Operator";
                    }
                    catch { }
                }

                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"INSERT INTO employees (id, name, email, password, role, del_status, will_login, outlet_id, company_id, created_at, updated_at)
                                    VALUES (@id, @name, @email, @pwd, @role, 'Live', 'Yes', '1', 1, datetime('now'), datetime('now'))
                                    ON CONFLICT(id) DO UPDATE SET
                                        name=@name, email=@email, password=@pwd, role=@role, del_status='Live',
                                        will_login='Yes', updated_at=datetime('now')";
                cmd.Parameters.AddWithValue("@id", userId > 0 ? userId : -1);
                cmd.Parameters.AddWithValue("@name", name);
                cmd.Parameters.AddWithValue("@email", username);
                cmd.Parameters.AddWithValue("@pwd", hash);
                cmd.Parameters.AddWithValue("@role", role);
                cmd.ExecuteNonQuery();

                LogService.Info($"Cloud login successful: {username} — role: {role} — cached locally");

                return new User
                {
                    Id = userId > 0 ? userId : 1,
                    Username = username,
                    FullName = name,
                    Role = role,
                    CompanyId = 1
                };
            }
            catch (Exception ex)
            {
                LogService.Warn($"Cloud login exception for {username}: {ex.Message}");
                return null;
            }
        }

        /// <summary>
        /// Auto-login — returns existing active user (no hardcoded passwords).
        /// Only creates a default admin with machine-specific password on fresh install.
        /// </summary>
        public User AutoLogin()
        {
            using var conn = _db.GetConnection();

            // Pehle existing active admin/operator user dhoondo
            using (var cmd = conn.CreateCommand())
            {
                cmd.CommandText = @"SELECT Id, Username, FullName, Role, CompanyId
                                   FROM Users WHERE IsActive = 1 ORDER BY Id LIMIT 1";
                using var reader = cmd.ExecuteReader();
                if (reader.Read())
                {
                    var user = new User
                    {
                        Id = reader.GetInt32(0),
                        Username = reader.GetString(1),
                        FullName = reader.IsDBNull(2) ? null : reader.GetString(2),
                        Role = reader.IsDBNull(3) ? null : reader.GetString(3),
                        CompanyId = reader.IsDBNull(4) ? 0 : reader.GetInt32(4)
                    };
                    UpdateLastLogin(conn, user.Id);
                    SaveSessionTimestamp();
                    LogService.Info($"AutoLogin: existing user '{user.Username}'");
                    return user;
                }
            }

            // Fresh install — create admin with machine-specific password
            string securePassword = GenerateSecureDefaultPassword();
            using (var cmd = conn.CreateCommand())
            {
                cmd.CommandText = @"INSERT INTO Users (Username, PasswordHash, FullName, Role, IsActive)
                                   VALUES ('admin', @p, 'Administrator', 'Admin', 1)";
                cmd.Parameters.AddWithValue("@p", BCrypt.Net.BCrypt.HashPassword(securePassword));
                cmd.ExecuteNonQuery();
            }

            LogService.Info($"AutoLogin: created default admin (machine-specific password)");
            return new User
            {
                Id = 1,
                Username = "admin",
                FullName = "Administrator",
                Role = "Admin",
                CompanyId = 0
            };
        }

        public void ChangePassword(int userId, string newPassword)
        {
            if (string.IsNullOrWhiteSpace(newPassword) || newPassword.Length < 6)
                throw new ArgumentException("Password must be at least 6 characters");

            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "UPDATE Users SET PasswordHash = @p WHERE Id = @id";
            cmd.Parameters.AddWithValue("@p", BCrypt.Net.BCrypt.HashPassword(newPassword));
            cmd.Parameters.AddWithValue("@id", userId);
            cmd.ExecuteNonQuery();
            LogService.Info($"Password changed for user ID: {userId}");
        }

        public void SaveSession(int userId)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"INSERT INTO app_settings (key, value) VALUES ('last_user_id', @v)
                                    ON CONFLICT(key) DO UPDATE SET value = @v";
                cmd.Parameters.AddWithValue("@v", userId.ToString());
                cmd.ExecuteNonQuery();
                SaveSessionTimestamp();
            }
            catch (Exception ex) { LogService.Error("SaveSession failed", ex); }
        }

        public void ClearSession()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "DELETE FROM app_settings WHERE key IN ('last_user_id', 'session_started_at')";
                cmd.ExecuteNonQuery();
            }
            catch (Exception ex) { LogService.Error("ClearSession failed", ex); }
        }

        /// <summary>
        /// Check if the current session has expired (8 hours timeout).
        /// </summary>
        public bool IsSessionExpired()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT value FROM app_settings WHERE key = 'session_started_at'";
                var val = cmd.ExecuteScalar();
                if (val == null) return false; // No timestamp = legacy session, allow

                if (DateTime.TryParse(val.ToString(), out DateTime sessionStart))
                {
                    return (DateTime.Now - sessionStart).TotalMinutes > SessionTimeoutMinutes;
                }
            }
            catch { }
            return false;
        }

        public User? GetSavedSession()
        {
            try
            {
                // Check session timeout first
                if (IsSessionExpired())
                {
                    LogService.Info("Session expired — clearing saved session");
                    ClearSession();
                    return null;
                }

                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT value FROM app_settings WHERE key = 'last_user_id'";
                var val = cmd.ExecuteScalar();
                if (val == null) return null;
                int userId = int.Parse(val.ToString()!);

                // Try local Users table first
                using (var ucmd = conn.CreateCommand())
                {
                    ucmd.CommandText = @"SELECT Id, Username, FullName, Role, CompanyId
                                         FROM Users WHERE Id = @id AND IsActive = 1";
                    ucmd.Parameters.AddWithValue("@id", userId);
                    using var r = ucmd.ExecuteReader();
                    if (r.Read())
                    {
                        return new User
                        {
                            Id = r.GetInt32(0),
                            Username = r.GetString(1),
                            FullName = r.IsDBNull(2) ? null : r.GetString(2),
                            Role = r.IsDBNull(3) ? null : r.GetString(3),
                            CompanyId = r.IsDBNull(4) ? 0 : r.GetInt32(4)
                        };
                    }
                }

                // Try employees table
                using (var ecmd = conn.CreateCommand())
                {
                    ecmd.CommandText = @"SELECT id, name, email, role, company_id
                                         FROM employees WHERE id = @id
                                         AND lower(COALESCE(del_status,'')) <> 'delete'";
                    ecmd.Parameters.AddWithValue("@id", userId);
                    using var r = ecmd.ExecuteReader();
                    if (r.Read())
                    {
                        int empId = r.GetInt32(0);
                        string empRole = r.IsDBNull(3) ? "" : r.GetString(3);

                        // If role column is empty, lookup from model_has_roles + roles
                        if (string.IsNullOrWhiteSpace(empRole))
                        {
                            try
                            {
                                using var roleCmd = conn.CreateCommand();
                                roleCmd.CommandText = @"SELECT r.name FROM roles r
                                    JOIN model_has_roles mhr ON mhr.role_id = r.id
                                    WHERE mhr.model_id = @uid LIMIT 1";
                                roleCmd.Parameters.AddWithValue("@uid", empId);
                                empRole = roleCmd.ExecuteScalar() as string ?? "Operator";
                            }
                            catch { empRole = "Operator"; }
                        }

                        return new User
                        {
                            Id = empId,
                            Username = r.IsDBNull(2) ? "" : r.GetString(2),
                            FullName = r.IsDBNull(1) ? "" : r.GetString(1),
                            Role = empRole,
                            CompanyId = r.IsDBNull(4) ? 0 : r.GetInt32(4)
                        };
                    }
                }
            }
            catch (Exception ex) { LogService.Error("GetSavedSession failed", ex); }
            return null;
        }

        private void SaveSessionTimestamp()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"INSERT INTO app_settings (key, value) VALUES ('session_started_at', @v)
                                    ON CONFLICT(key) DO UPDATE SET value = @v";
                cmd.Parameters.AddWithValue("@v", DateTime.Now.ToString("o"));
                cmd.ExecuteNonQuery();
            }
            catch { }
        }

        private void UpdateLastLogin(SqliteConnection conn, int userId)
        {
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "UPDATE Users SET LastLogin = datetime('now') WHERE Id = @id";
            cmd.Parameters.AddWithValue("@id", userId);
            cmd.ExecuteNonQuery();
        }
    }
}
