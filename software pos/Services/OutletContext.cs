using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Services
{
    public static class OutletContext
    {
        public const string SettingKey = "selected_outlet";

        public static int GetSelectedOutletId(DatabaseService? db = null)
        {
            try
            {
                db ??= new DatabaseService();
                using var conn = db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT Value FROM AppSettings WHERE Key = @k";
                cmd.Parameters.AddWithValue("@k", SettingKey);
                if (int.TryParse(cmd.ExecuteScalar() as string, out int id) && id > 0 && OutletExists(conn, id))
                    return id;
                return GetDefaultOutletId(conn);
            }
            catch { return 1; }
        }

        public static void SetSelectedOutletId(int id)
        {
            try
            {
                using var conn = new DatabaseService().GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"INSERT INTO AppSettings (Key, Value) VALUES (@k, @v)
                                    ON CONFLICT(Key) DO UPDATE SET Value = @v";
                cmd.Parameters.AddWithValue("@k", SettingKey);
                cmd.Parameters.AddWithValue("@v", id.ToString());
                cmd.ExecuteNonQuery();
            }
            catch { }
        }

        // NULL outlet_id wala purana data isi outlet ka mana jayega
        public static int GetDefaultOutletId(SqliteConnection conn)
        {
            try
            {
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT MIN(id) FROM outlets
                                    WHERE del_status IS NULL OR del_status != 'Deleted'";
                var val = cmd.ExecuteScalar();
                if (val != null && val != DBNull.Value && long.TryParse(val.ToString(), out long id) && id > 0)
                    return (int)id;
            }
            catch { }
            return 1;
        }

        public static string GetOutletName(int outletId)
        {
            try
            {
                using var conn = new DatabaseService().GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT COALESCE(outlet_name, name, '') FROM outlets WHERE id = @id";
                cmd.Parameters.AddWithValue("@id", outletId);
                var val = cmd.ExecuteScalar() as string;
                return string.IsNullOrWhiteSpace(val) ? $"Outlet {outletId}" : val;
            }
            catch { return $"Outlet {outletId}"; }
        }

        private static bool OutletExists(SqliteConnection conn, int id)
        {
            using var cmd = conn.CreateCommand();
            cmd.CommandText = @"SELECT COUNT(*) FROM outlets WHERE id = @id
                                AND (del_status IS NULL OR del_status != 'Deleted')";
            cmd.Parameters.AddWithValue("@id", id);
            return (long)cmd.ExecuteScalar() > 0;
        }
    }
}
