using System.Windows;
using System.Windows.Controls;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class PurchasePage : UserControl
    {
        private readonly DatabaseService _db = new DatabaseService();
        private readonly MainDashboard? _dashboard;
        private string _currentFilter = "All";

        public PurchasePage() { InitializeComponent(); }
        public PurchasePage(MainDashboard dashboard) : this() { _dashboard = dashboard; LoadPurchases(); }

        private void LoadPurchases()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                string where = "WHERE VchType='Purchase'";
                if (_currentFilter == "Hold") where += " AND IsCancelled=1";

                cmd.CommandText = $@"SELECT t.VchCode, t.VchDate as Date, t.VchNo as InvoiceNo,
                    COALESCE(m.Name,'') as SupplierName, t.Amount, t.Amount as PendingAmount,
                    t.VchDate as DueDate,
                    CASE WHEN t.IsCancelled=1 THEN 'Hold' ELSE 'Active' END as Status
                    FROM Tran1 t LEFT JOIN Master1 m ON t.MasterCode1=m.Code {where} ORDER BY t.VchDate DESC";

                var list = new System.Collections.ObjectModel.ObservableCollection<PurchaseItem>();
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    list.Add(new PurchaseItem { Date = r["Date"]?.ToString() ?? "", InvoiceNo = r["InvoiceNo"]?.ToString() ?? "", SupplierName = r["SupplierName"]?.ToString() ?? "", Amount = $"₹ {((double)r["Amount"]):N2}", PendingAmount = $"₹ {((double)r["PendingAmount"]):N2}", DueDate = r["DueDate"]?.ToString() ?? "", Status = r["Status"]?.ToString() ?? "" });

                dgPurchases.ItemsSource = list;
                emptyState.Visibility = list.Count == 0 ? Visibility.Visible : Visibility.Collapsed;
                dgPurchases.Visibility = list.Count == 0 ? Visibility.Collapsed : Visibility.Visible;

                using var cAll = conn.CreateCommand(); cAll.CommandText = "SELECT COUNT(*) FROM Tran1 WHERE VchType='Purchase'"; tabAll.Content = $"All ({(long)cAll.ExecuteScalar()})";
                using var cPend = conn.CreateCommand(); cPend.CommandText = "SELECT COUNT(*) FROM Tran1 WHERE VchType='Purchase' AND IsCancelled=0 AND Amount>0"; tabPending.Content = $"Pending ({(long)cPend.ExecuteScalar()})";
                tabOverdue.Content = "Overdue (0)"; tabOnAcc.Content = "On Acc (0)";
                using var cHold = conn.CreateCommand(); cHold.CommandText = "SELECT COUNT(*) FROM Tran1 WHERE VchType='Purchase' AND IsCancelled=1"; tabHold.Content = $"Hold ({(long)cHold.ExecuteScalar()})";

                using var cTotal = conn.CreateCommand(); cTotal.CommandText = "SELECT IFNULL(SUM(Amount),0) FROM Tran1 WHERE VchType='Purchase'";
                lblTotalAmount.Text = $"₹ {((double)cTotal.ExecuteScalar()):N2}"; lblTotalPending.Text = $"₹ {((double)cTotal.ExecuteScalar()):N2}";
            }
            catch { }
        }

        private void Tab_Click(object sender, RoutedEventArgs e)
        {
            if (tabAll.IsChecked == true) _currentFilter = "All";
            else if (tabPending.IsChecked == true) _currentFilter = "Pending";
            else if (tabOverdue.IsChecked == true) _currentFilter = "Overdue";
            else if (tabOnAcc.IsChecked == true) _currentFilter = "On Acc";
            else if (tabHold.IsChecked == true) _currentFilter = "Hold";
            LoadPurchases();
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e) { _dashboard?.ShowDashboard(); }

        private void BtnAddPurchase_Click(object sender, RoutedEventArgs e)
        {
            var page = new CreatePurchasePage(_dashboard!);
            var parent = Parent as Panel;
            if (parent != null) { parent.Children.Clear(); parent.Children.Add(page); }
        }
    }

    public class PurchaseItem
    {
        public string Date { get; set; } = "";
        public string InvoiceNo { get; set; } = "";
        public string SupplierName { get; set; } = "";
        public string Amount { get; set; } = "";
        public string PendingAmount { get; set; } = "";
        public string DueDate { get; set; } = "";
        public string Status { get; set; } = "";
    }
}
