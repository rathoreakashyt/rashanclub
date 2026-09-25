using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public static class LocalTxn
    {
        public static long NextLocalId(DatabaseService db, string table)
        {
            try
            {
                using var conn = db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = $"SELECT MIN(Id) FROM {table} WHERE Id < 0";
                var v = cmd.ExecuteScalar();
                return (v is long l ? l : 0) - 1;
            }
            catch { return -1; }
        }

        public static string NextReference(DatabaseService db, string table, string prefix)
        {
            try
            {
                using var conn = db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = $"SELECT MAX(reference_no) FROM {table} WHERE reference_no LIKE @p";
                cmd.Parameters.AddWithValue("@p", prefix + "%");
                var v = cmd.ExecuteScalar() as string;
                int last = 0;
                if (!string.IsNullOrEmpty(v) && v.Length > prefix.Length)
                    int.TryParse(v.Substring(prefix.Length), out last);
                return prefix + (last + 1).ToString().PadLeft(5, '0');
            }
            catch { return prefix + "00001"; }
        }

        public static string Today()
        {
            return DateTime.Now.ToString("yyyy-MM-dd");
        }
    }
}
