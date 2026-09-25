using System.Windows;
using System.Windows.Controls;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views
{
    public partial class PurchaseReturnPage : UserControl
    {
        private readonly DatabaseService _db = new DatabaseService();
        private readonly MainDashboard? _dashboard;
        private string _currentFilter = "All";
        private string _searchText = "";
        private int _serialNo = 0;

        public PurchaseReturnPage() { InitializeComponent(); }
        public PurchaseReturnPage(MainDashboard dashboard) : this() { _dashboard = dashboard; LoadReturns(); }

        private void LoadReturns()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                string where = "WHERE VchType='PurchaseReturn'";
                if (_currentFilter == "Hold") where += " AND IsCancelled=1";

                if (!string.IsNullOrWhiteSpace(_searchText))
                {
                    where += $" AND (t.VchNo LIKE '%{_searchText}%' OR m.Name LIKE '%{_searchText}%')";
                }

                cmd.CommandText = $@"SELECT t.VchCode, t.VchDate as Date, t.VchNo as VchNo,
                    '' as OriginalBillNo,
                    COALESCE(m.Name,'') as SupplierName, t.Amount,
                    CASE WHEN t.IsCancelled=1 THEN 'Hold' ELSE 'Active' END as Status
                    FROM Tran1 t LEFT JOIN Master1 m ON t.MasterCode1=m.Code {where} ORDER BY t.VchDate DESC";

                var list = new System.Collections.ObjectModel.ObservableCollection<PurchaseReturnItem>();
                _serialNo = 0;
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    _serialNo++;
                    list.Add(new PurchaseReturnItem
                    {
                        SN = _serialNo.ToString(),
                        VchCode = r["VchCode"]?.ToString() ?? "",
                        Date = r["Date"]?.ToString() ?? "",
                        VchNo = r["VchNo"]?.ToString() ?? "",
                        OriginalBillNo = r["OriginalBillNo"]?.ToString() ?? "",
                        SupplierName = r["SupplierName"]?.ToString() ?? "",
                        Amount = $"₹ {((double)r["Amount"]):N2}",
                        Status = r["Status"]?.ToString() ?? ""
                    });
                }

                itemsList.ItemsSource = list;
                emptyState.Visibility = list.Count == 0 ? Visibility.Visible : Visibility.Collapsed;
                itemsList.Visibility = list.Count == 0 ? Visibility.Collapsed : Visibility.Visible;

                using var cAll = conn.CreateCommand(); cAll.CommandText = "SELECT COUNT(*) FROM Tran1 WHERE VchType='PurchaseReturn'"; tabAll.Content = $"All ({(long)cAll.ExecuteScalar()})";
                tabNewRef.Content = "New Ref (0)"; tabAdjusted.Content = "Adjusted (0)"; tabOnAcc.Content = "On Acc (0)";
                using var cHold = conn.CreateCommand(); cHold.CommandText = "SELECT COUNT(*) FROM Tran1 WHERE VchType='PurchaseReturn' AND IsCancelled=1"; tabHold.Content = $"Hold ({(long)cHold.ExecuteScalar()})";

                using var cTotal = conn.CreateCommand(); cTotal.CommandText = "SELECT IFNULL(SUM(Amount),0) FROM Tran1 WHERE VchType='PurchaseReturn'";
                lblTotalAmount.Text = $"₹ {((double)cTotal.ExecuteScalar()):N2}";
                lblTotal.Text = $"Total: {list.Count} returns";
            }
            catch { }
        }

        private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e)
        {
            _searchText = txtSearch.Text.Trim();
            LoadReturns();
        }

        private void Tab_Click(object sender, RoutedEventArgs e)
        {
            if (tabAll.IsChecked == true) _currentFilter = "All";
            else if (tabNewRef.IsChecked == true) _currentFilter = "New Ref";
            else if (tabAdjusted.IsChecked == true) _currentFilter = "Adjusted";
            else if (tabOnAcc.IsChecked == true) _currentFilter = "On Acc";
            else if (tabHold.IsChecked == true) _currentFilter = "Hold";
            LoadReturns();
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e) { _dashboard?.ShowDashboard(); }

        private void BtnAddReturn_Click(object sender, RoutedEventArgs e)
        {
            var page = new CreateDrNotePage(_dashboard!);
            var parent = Parent as Panel;
            if (parent != null) { parent.Children.Clear(); parent.Children.Add(page); }
        }

        private void BtnView_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is string vchCode)
            {
                // View = custom PDF viewer me purchase return kholo
                string temp = System.IO.Path.Combine(System.IO.Path.GetTempPath(), "PurchaseReturn_" + Guid.NewGuid().ToString("N") + ".pdf");
                PdfService.GenerateTranPdf(vchCode, temp, "PURCHASE RETURN");
            }
        }

        private void BtnDelete_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is string vchCode)
            {
                var result = MessageBox.Show("Delete this return?", "Confirm Delete",
                    MessageBoxButton.YesNo, MessageBoxImage.Warning);
                if (result == MessageBoxResult.Yes)
                {
                    try
                    {
                        using var conn = _db.GetConnection();
                        using var cmd = conn.CreateCommand();
                        cmd.CommandText = "DELETE FROM Tran1 WHERE VchCode=@code AND VchType='PurchaseReturn'";
                        cmd.Parameters.AddWithValue("@code", vchCode);
                        cmd.ExecuteNonQuery();
                        LoadReturns();
                    }
                    catch { }
                }
            }
        }
    }

    public class PurchaseReturnItem
    {
        public string SN { get; set; } = "";
        public string VchCode { get; set; } = "";
        public string Date { get; set; } = "";
        public string VchNo { get; set; } = "";
        public string OriginalBillNo { get; set; } = "";
        public string SupplierName { get; set; } = "";
        public string Amount { get; set; } = "";
        public string Status { get; set; } = "";
    }
}
