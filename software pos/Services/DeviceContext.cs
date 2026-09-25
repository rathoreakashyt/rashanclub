using System;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Services
{
    /// <summary>
    /// Har machine/counter ka persistent unique device id (AppSettings me save).
    /// Multi-counter sync me server-side mapping isi se key hoti hai — 10 counters
    /// ke same local ids kabhi clash nahi karte.
    /// </summary>
    public static class DeviceContext
    {
        public const string SettingKey = "device_id";

        public static string GetDeviceId()
        {
            try
            {
                using var conn = new DatabaseService().GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT Value FROM AppSettings WHERE Key=@k";
                cmd.Parameters.AddWithValue("@k", SettingKey);
                var v = cmd.ExecuteScalar() as string;
                if (!string.IsNullOrWhiteSpace(v) && Guid.TryParse(v, out _))
                    return v;

                var id = Guid.NewGuid().ToString();
                using var ins = conn.CreateCommand();
                ins.CommandText = @"INSERT INTO AppSettings (Key, Value) VALUES (@k, @v)
                                    ON CONFLICT(Key) DO UPDATE SET Value=@v";
                ins.Parameters.AddWithValue("@k", SettingKey);
                ins.Parameters.AddWithValue("@v", id);
                ins.ExecuteNonQuery();
                return id;
            }
            catch { return "unknown"; }
        }

        /// <summary>
        /// Stable short device tag (4 base36 chars) — item/party codes aur invoice
        /// numbers me use hota hai taaki alag counters same code/numbers na banayein.
        /// </summary>
        public static string Short
        {
            get
            {
                try
                {
                    string hex = Guid.Parse(GetDeviceId()).ToString("N"); // 32 hex chars
                    int v = Convert.ToInt32(hex.Substring(0, 6), 16);    // 0..16.7M
                    const string chars = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
                    var sb = new char[4];
                    for (int i = 3; i >= 0; i--) { sb[i] = chars[v % 36]; v /= 36; }
                    return new string(sb);
                }
                catch { return "0000"; }
            }
        }
    }
}
