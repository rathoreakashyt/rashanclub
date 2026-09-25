using System;
using System.Data;
using System.Windows;
using System.Windows.Controls;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class BulkItemUpdatePage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();

        public BulkItemUpdatePage(MainDashboard? dashboard)
        {
            InitializeComponent();
            _dashboard = dashboard;
            LoadItems();
        }

        private void LoadItems()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT i.Id, i.name AS name, i.code AS code,
                                           IFNULL(i.purchase_price,0) AS purchase_price,
                                           IFNULL(i.sale_price,0) AS sale_price,
                                           IFNULL(i.mrp_price,0) AS mrp_price,
                                           IFNULL(i.stock_quantity,0) AS stock_quantity,
                                           IFNULL(i.alert_quantity,0) AS alert_quantity
                                    FROM items i WHERE (i.del_status IS NULL OR i.del_status='Live') ORDER BY i.name";
                var table = new DataTable();
                using (var r = cmd.ExecuteReader()) table.Load(r);
                grid.ItemsSource = table.DefaultView;

                grid.Columns.Clear();
                grid.Columns.Add(new DataGridTextColumn { Header = "Name", Binding = new System.Windows.Data.Binding("name") { Mode = System.Windows.Data.BindingMode.TwoWay }, Width = new DataGridLength(1.6, DataGridLengthUnitType.Star) });
                grid.Columns.Add(new DataGridTextColumn { Header = "Code", Binding = new System.Windows.Data.Binding("code") { Mode = System.Windows.Data.BindingMode.TwoWay }, Width = 110 });
                grid.Columns.Add(new DataGridTextColumn { Header = "Purchase Price", Binding = new System.Windows.Data.Binding("purchase_price") { Mode = System.Windows.Data.BindingMode.TwoWay }, Width = 130 });
                grid.Columns.Add(new DataGridTextColumn { Header = "Sale Price", Binding = new System.Windows.Data.Binding("sale_price") { Mode = System.Windows.Data.BindingMode.TwoWay }, Width = 120 });
                grid.Columns.Add(new DataGridTextColumn { Header = "MRP", Binding = new System.Windows.Data.Binding("mrp_price") { Mode = System.Windows.Data.BindingMode.TwoWay }, Width = 110 });
                grid.Columns.Add(new DataGridTextColumn { Header = "Stock", Binding = new System.Windows.Data.Binding("stock_quantity") { Mode = System.Windows.Data.BindingMode.TwoWay }, Width = 100 });
                grid.Columns.Add(new DataGridTextColumn { Header = "Alert Qty", Binding = new System.Windows.Data.Binding("alert_quantity") { Mode = System.Windows.Data.BindingMode.TwoWay }, Width = 100 });
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error loading items: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void BtnSave_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                var view = grid.ItemsSource as DataView;
                if (view == null) return;
                var updatedIds = new List<long>();
                using var conn = _db.GetConnection();
                using var tx = conn.BeginTransaction();
                foreach (DataRowView row in view)
                {
                    long id = Convert.ToInt64(row["Id"]);
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = @"UPDATE items SET purchase_price=@pp, sale_price=@sp, mrp_price=@mrp,
                                               stock_quantity=MAX(@sq, 0), alert_quantity=@aq, SyncStatus='Local',
                                               updated_at=datetime('now') WHERE Id=@id";
                    cmd.Parameters.AddWithValue("@pp", Convert.ToDouble(row["purchase_price"]));
                    cmd.Parameters.AddWithValue("@sp", Convert.ToDouble(row["sale_price"]));
                    cmd.Parameters.AddWithValue("@mrp", Convert.ToDouble(row["mrp_price"]));
                    cmd.Parameters.AddWithValue("@sq", Convert.ToDouble(row["stock_quantity"]));
                    cmd.Parameters.AddWithValue("@aq", Convert.ToDouble(row["alert_quantity"]));
                    cmd.Parameters.AddWithValue("@id", id);
                    cmd.ExecuteNonQuery();
                    updatedIds.Add(id);
                }
                tx.Commit();

                // IMPORTANT: EnqueueSync transaction ke ANDAR nahi karna — woh alag
                // SQLite connection kholta hai aur write-lock par busy-wait karta hai
                // (bulk update hang ho jaata tha). Ab commit ke baad safe hai.
                foreach (long id in updatedIds)
                    Services.SyncService.EnqueueSync("items", id, "update");

                // Stock update hua — saare subscribed pages (Stock/Low Stock/Inventory) refresh
                Services.StockEvents.NotifyStockChanged();

                MessageBox.Show($"{updatedIds.Count} items updated successfully!", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error saving: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            if (_dashboard != null) _dashboard.ShowDashboard();
        }
    }
}
