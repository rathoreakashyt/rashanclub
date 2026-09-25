using System.Collections.Generic;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views
{
    public class PurchaseReturnRow
    {
        public int Sn { get; set; }
        public long Id { get; set; }
        public string ReferenceNo { get; set; } = "";
        public string Supplier { get; set; } = "";
        public string Date { get; set; } = "";
        public string PurchaseDate { get; set; } = "";
        public string Status { get; set; } = "";
        public string Total { get; set; } = "";
    }

    public partial class PurchaseReturnListPage : UserControl, ISyncRefreshable
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new DatabaseService();
        private List<PurchaseReturnRow> _allRows = new();

        public PurchaseReturnListPage() { InitializeComponent(); }
        public PurchaseReturnListPage(MainDashboard dashboard) : this() { _dashboard = dashboard; LoadData(); }

        private static string MapStatus(string s)
        {
            return s switch
            {
                "draft" => "Draft",
                "taken_by_sup_pro_not_returned" => "Taken By Supplier Product Not Returned",
                "taken_by_sup_money_returned" => "Taken By Supplier Money Returned",
                "taken_by_sup_pro_returned" => "Taken By Supplier Product Returned",
                _ => s
            };
        }

        // Cloud/Web se change aaya (sync pull) → page khud reload — bina refresh ke
        public void OnSyncPulled()
        {
            Dispatcher.BeginInvoke(new Action(() =>
            {
                if (IsLoaded) LoadData();
            }), System.Windows.Threading.DispatcherPriority.Background);
        }

        private void LoadData()
        {
            _allRows.Clear();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT r.Id, r.reference_no, r.date, r.purchase_date, r.return_status, r.total_return_amount,
                                           COALESCE(s.name, '') AS supplier
                                    FROM purchase_returns r LEFT JOIN Suppliers s ON s.Id = r.supplier_id
                                    WHERE (r.del_status IS NULL OR r.del_status='Live')
                                    ORDER BY r.Id DESC";
                using var r = cmd.ExecuteReader();
                int sn = 1;
                while (r.Read())
                {
                    _allRows.Add(new PurchaseReturnRow
                    {
                        Sn = sn++,
                        Id = r["Id"] is long id ? id : 0,
                        ReferenceNo = r["reference_no"]?.ToString() ?? "",
                        Supplier = r["supplier"]?.ToString() ?? "",
                        Date = r["date"]?.ToString() ?? "",
                        PurchaseDate = r["purchase_date"]?.ToString() ?? "",
                        Status = MapStatus(r["return_status"]?.ToString() ?? ""),
                        Total = $"₹ {(r["total_return_amount"] is double d ? d : 0):N2}"
                    });
                }
            }
            catch { }
            ApplyFilter();
        }

        private void ApplyFilter()
        {
            string q = txtSearch.Text.Trim().ToLowerInvariant();
            var filtered = q == "" ? _allRows : _allRows.FindAll(x =>
                x.ReferenceNo.ToLowerInvariant().Contains(q) ||
                x.Supplier.ToLowerInvariant().Contains(q));
            itemsList.ItemsSource = filtered.ToList();
            emptyState.Visibility = filtered.Count == 0 ? Visibility.Visible : Visibility.Collapsed;
            itemsList.Visibility = filtered.Count == 0 ? Visibility.Collapsed : Visibility.Visible;
        }

        private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (txtSearch != null) ApplyFilter();
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowDashboard();
        }

        private void BtnAdd_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowPage(new PurchaseReturnCreatePage(_dashboard!));
        }

        private void BtnView_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is long id)
                new VoucherDetailWindow(_dashboard, true, id).Show();
        }

        private void BtnPrint_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is long id)
                new VoucherDetailWindow(_dashboard, true, id, true).Show();
        }

        private void BtnEdit_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is long id)
                _dashboard?.ShowPage(new PurchaseReturnCreatePage(_dashboard!, id));
        }

        private void BtnDelete_Click(object sender, RoutedEventArgs e)
        {
            if ((sender as Button)?.Tag is not long id) return;
            var result = MessageBox.Show("Delete this purchase return? Stock will be reverted.", "Confirm", MessageBoxButton.YesNo, MessageBoxImage.Question);
            if (result != MessageBoxResult.Yes) return;

            try
            {
                using var conn = _db.GetConnection();
                using var tx = conn.BeginTransaction();
                var revert = new List<(long Item, double Qty)>();
                using (var rd = conn.CreateCommand())
                {
                    rd.Transaction = tx;
                    rd.CommandText = "SELECT item_id, return_quantity_amount FROM purchase_return_details WHERE pur_return_id=@id";
                    rd.Parameters.AddWithValue("@id", id);
                    using var r = rd.ExecuteReader();
                    while (r.Read())
                        revert.Add((r["item_id"] is long iid ? iid : 0,
                                    r["return_quantity_amount"] is double qd ? qd : 0));
                }
                foreach (var (item, q) in revert)
                {
                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.Transaction = tx;
                        cmd.CommandText = "UPDATE items SET stock_quantity = IFNULL(stock_quantity,0) + @q WHERE ServerId=@item";
                        cmd.Parameters.AddWithValue("@q", q);
                        cmd.Parameters.AddWithValue("@item", item);
                        cmd.ExecuteNonQuery();
                    }
                    using (var cmd = conn.CreateCommand())
                    {
                        cmd.Transaction = tx;
                        cmd.CommandText = "UPDATE Master1 SET CurrentStock = IFNULL(CurrentStock,0) + @q WHERE ServerId=@item AND MasterType='Item'";
                        cmd.Parameters.AddWithValue("@q", q);
                        cmd.Parameters.AddWithValue("@item", item);
                        cmd.ExecuteNonQuery();
                    }
                }
                using (var cmd = conn.CreateCommand())
                {
                    cmd.Transaction = tx;
                    cmd.CommandText = "DELETE FROM purchase_return_payments WHERE purchase_return_id=@id";
                    cmd.Parameters.AddWithValue("@id", id);
                    cmd.ExecuteNonQuery();
                }
                using (var cmd = conn.CreateCommand())
                {
                    cmd.Transaction = tx;
                    cmd.CommandText = "DELETE FROM purchase_return_details WHERE pur_return_id=@id";
                    cmd.Parameters.AddWithValue("@id", id);
                    cmd.ExecuteNonQuery();
                }
                using (var cmd = conn.CreateCommand())
                {
                    cmd.Transaction = tx;
                    cmd.CommandText = "UPDATE purchase_returns SET del_status='Deleted', updated_at=datetime('now') WHERE Id=@id";
                    cmd.Parameters.AddWithValue("@id", id);
                    cmd.ExecuteNonQuery();
                }
                tx.Commit();
                Services.SyncService.EnqueueSync("purchase_returns", id, "delete");
                _dashboard?.TriggerSync();
                LoadData();
            }
            catch (System.Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }
    }
}
