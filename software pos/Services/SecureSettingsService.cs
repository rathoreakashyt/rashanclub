using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;
using System;
using System.Security.Cryptography;
using System.Text;

namespace RashanKiDukan.Services
{
    /// <summary>
    /// Provides DPAPI-based encryption for sensitive settings stored in AppSettings.
    /// Machine-scoped: encrypted values can only be decrypted on the same machine.
    /// Thread-safe via internal locking.
    /// </summary>
    public static class SecureSettingsService
    {
        private static readonly object _lock = new object();
        private const string EncryptedPrefix = "enc_";

        private static readonly string[] CredentialKeys = new[]
        {
            "server_email",
            "server_password",
            "server_token"
        };

        /// <summary>
        /// Encrypts the value using DPAPI and stores it in AppSettings with the 'enc_' prefix on the key.
        /// </summary>
        public static void EncryptAndStore(DatabaseService db, string key, string value)
        {
            if (db == null) throw new ArgumentNullException(nameof(db));
            if (string.IsNullOrEmpty(key)) throw new ArgumentNullException(nameof(key));
            if (value == null) throw new ArgumentNullException(nameof(value));

            byte[] plainBytes = Encoding.UTF8.GetBytes(value);
            byte[] encryptedBytes = ProtectedData.Protect(plainBytes, null, DataProtectionScope.CurrentUser);
            string base64Value = Convert.ToBase64String(encryptedBytes);

            string storageKey = EncryptedPrefix + key;

            lock (_lock)
            {
                using var conn = db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"INSERT OR REPLACE INTO AppSettings (Key, Value) VALUES (@key, @value)";
                cmd.Parameters.AddWithValue("@key", storageKey);
                cmd.Parameters.AddWithValue("@value", base64Value);
                cmd.ExecuteNonQuery();
            }
        }

        /// <summary>
        /// Retrieves and decrypts a value from AppSettings.
        /// Returns null if the key does not exist or decryption fails (e.g., machine changed).
        /// </summary>
        public static string DecryptAndGet(DatabaseService db, string key)
        {
            if (db == null) throw new ArgumentNullException(nameof(db));
            if (string.IsNullOrEmpty(key)) throw new ArgumentNullException(nameof(key));

            string storageKey = EncryptedPrefix + key;

            lock (_lock)
            {
                using var conn = db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT Value FROM AppSettings WHERE Key = @key";
                cmd.Parameters.AddWithValue("@key", storageKey);

                var result = cmd.ExecuteScalar();
                if (result == null || result == DBNull.Value)
                    return null;

                string base64Value = result.ToString();

                try
                {
                    byte[] encryptedBytes = Convert.FromBase64String(base64Value);
                    try
                    {
                        byte[] plainBytes = ProtectedData.Unprotect(encryptedBytes, null, DataProtectionScope.CurrentUser);
                        return Encoding.UTF8.GetString(plainBytes);
                    }
                    catch (CryptographicException)
                    {
                        // Retry with LocalMachine scope for legacy data written before user-scoped encryption
                        byte[] plainBytes = ProtectedData.Unprotect(encryptedBytes, null, DataProtectionScope.LocalMachine);
                        return Encoding.UTF8.GetString(plainBytes);
                    }
                }
                catch (CryptographicException)
                {
                    // Decryption failed - likely machine changed or data corrupted
                    return null;
                }
                catch (FormatException)
                {
                    // Invalid Base64 string
                    return null;
                }
            }
        }

        /// <summary>
        /// Checks whether an encrypted version of the given key exists in AppSettings.
        /// </summary>
        public static bool IsEncrypted(DatabaseService db, string key)
        {
            if (db == null) throw new ArgumentNullException(nameof(db));
            if (string.IsNullOrEmpty(key)) throw new ArgumentNullException(nameof(key));

            string storageKey = EncryptedPrefix + key;

            lock (_lock)
            {
                using var conn = db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT COUNT(1) FROM AppSettings WHERE Key = @key";
                cmd.Parameters.AddWithValue("@key", storageKey);

                var count = Convert.ToInt64(cmd.ExecuteScalar());
                return count > 0;
            }
        }

        /// <summary>
        /// Migrates existing plain-text credentials (server_email, server_password, server_token)
        /// to encrypted storage. Reads plain-text values, encrypts them with DPAPI,
        /// stores them with 'enc_' prefix, then deletes the plain-text versions.
        /// </summary>
        public static void MigrateExistingCredentials(DatabaseService db)
        {
            if (db == null) throw new ArgumentNullException(nameof(db));

            lock (_lock)
            {
                using var conn = db.GetConnection();

                foreach (var key in CredentialKeys)
                {
                    // Read plain-text value
                    string plainValue = null;
                    using (var readCmd = conn.CreateCommand())
                    {
                        readCmd.CommandText = @"SELECT Value FROM AppSettings WHERE Key = @key";
                        readCmd.Parameters.AddWithValue("@key", key);

                        var result = readCmd.ExecuteScalar();
                        if (result == null || result == DBNull.Value)
                            continue;

                        plainValue = result.ToString();
                    }

                    if (string.IsNullOrEmpty(plainValue))
                        continue;

                    // Check if already migrated
                    string encKey = EncryptedPrefix + key;
                    using (var checkCmd = conn.CreateCommand())
                    {
                        checkCmd.CommandText = @"SELECT COUNT(1) FROM AppSettings WHERE Key = @key";
                        checkCmd.Parameters.AddWithValue("@key", encKey);

                        if (Convert.ToInt64(checkCmd.ExecuteScalar()) > 0)
                            continue; // Already migrated
                    }

                    // Encrypt and store
                    byte[] plainBytes = Encoding.UTF8.GetBytes(plainValue);
                    byte[] encryptedBytes = ProtectedData.Protect(plainBytes, null, DataProtectionScope.CurrentUser);
                    string base64Value = Convert.ToBase64String(encryptedBytes);

                    using (var insertCmd = conn.CreateCommand())
                    {
                        insertCmd.CommandText = @"INSERT OR REPLACE INTO AppSettings (Key, Value) VALUES (@key, @value)";
                        insertCmd.Parameters.AddWithValue("@key", encKey);
                        insertCmd.Parameters.AddWithValue("@value", base64Value);
                        insertCmd.ExecuteNonQuery();
                    }

                    // Delete plain-text version
                    using (var deleteCmd = conn.CreateCommand())
                    {
                        deleteCmd.CommandText = @"DELETE FROM AppSettings WHERE Key = @key";
                        deleteCmd.Parameters.AddWithValue("@key", key);
                        deleteCmd.ExecuteNonQuery();
                    }
                }
            }
        }
    }
}
