using System.Collections.Generic;
using Microsoft.Data.Sqlite;

namespace RashanKiDukan.Database
{
    public static class Lookups
    {
        public static List<(long Id, string Name)> Suppliers(DatabaseService db)
        {
            var list = new List<(long, string)>();
            using var conn = db.GetConnection();
            // Try suppliers table first (cloud-synced, more reliable)
            using (var cmd = conn.CreateCommand())
            {
                cmd.CommandText = "SELECT Id, name FROM suppliers WHERE (del_status IS NULL OR del_status='Live') AND name<>'' ORDER BY name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add((Convert.ToInt64(r["Id"]), r["name"]?.ToString() ?? ""));
            }
            if (list.Count == 0)
            {
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT ServerId, Name FROM Master1 WHERE MasterType='Party' AND PartyType IN ('Supplier','Both') AND IsActive=1 AND Name<>'' AND ServerId IS NOT NULL AND ServerId > 0 ORDER BY Name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add((Convert.ToInt64(r["ServerId"]), r["Name"]?.ToString() ?? ""));
            }
            return list;
        }

        public static List<(long Id, string Name)> PaymentMethods(DatabaseService db)
        {
            var list = new List<(long, string)>();
            using var conn = db.GetConnection();
            using (var cmd = conn.CreateCommand())
            {
                cmd.CommandText = "SELECT Id, Name FROM payment_methods WHERE Name<>'' AND (del_status IS NULL OR del_status='Live') ORDER BY Name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add((Convert.ToInt64(r["Id"]), r["Name"]?.ToString() ?? ""));
            }
            if (list.Count == 0)
            {
                foreach (var name in new[] { "Cash", "Bank", "UPI", "Card", "Cheque" })
                {
                    using var ins = conn.CreateCommand();
                    ins.CommandText = "INSERT INTO payment_methods (Id, Name, SyncStatus) VALUES (@id, @name, 'Local')";
                    ins.Parameters.AddWithValue("@id", Views.LocalTxn.NextLocalId(db, "payment_methods"));
                    ins.Parameters.AddWithValue("@name", name);
                    ins.ExecuteNonQuery();
                }
                return PaymentMethods(db);
            }
            return list;
        }

        public static List<(long Id, string Name)> ExpenseCategories(DatabaseService db)
        {
            var list = new List<(long, string)>();
            using var conn = db.GetConnection();
            using (var cmd = conn.CreateCommand())
            {
                cmd.CommandText = "SELECT Id, Name FROM expense_categories WHERE Name<>'' AND (del_status IS NULL OR del_status='Live') ORDER BY Name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add((Convert.ToInt64(r["Id"]), r["Name"]?.ToString() ?? ""));
            }
            if (list.Count == 0)
            {
                using var ins = conn.CreateCommand();
                ins.CommandText = "INSERT INTO expense_categories (Id, Name, SyncStatus) VALUES (@id, @name, 'Local')";
                ins.Parameters.AddWithValue("@id", Views.LocalTxn.NextLocalId(db, "expense_categories"));
                ins.Parameters.AddWithValue("@name", "General");
                ins.ExecuteNonQuery();
                return ExpenseCategories(db);
            }
            return list;
        }
    }
}
