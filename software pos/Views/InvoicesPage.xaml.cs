using System.Windows;
using System.Windows.Controls;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views
{
    public partial class InvoicesPage : UserControl
    {
        private readonly DatabaseService _db = new DatabaseService();
        private readonly MainDashboard? _dashboard;
        private string _currentFilter = "All";
        private string _searchText = "";
        private int _serialNo = 0;

        public InvoicesPage() { InitializeComponent(); }

        public InvoicesPage(MainDashboard dashboard) : this()
        {
            _dashboard = dashboard;
            LoadInvoices();
        }

        private void LoadInvoices()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();

                string where = "WHERE VchType='Sales' AND (t.VchCode LIKE 'SRV%' OR t.ServerId IS NULL OR t.ServerId=0 OR NOT EXISTS (SELECT 1 FROM Tran1 s2 WHERE s2.ServerId=t.ServerId AND s2.VchCode LIKE 'SRV%' AND s2.VchCode<>t.VchCode))";
                if (_currentFilter == "Pending") where += " AND IsCancelled=0 AND Amount>0";
                else if (_currentFilter == "Overdue") where += " AND IsCancelled=0 AND Amount>0";
                else if (_currentFilter == "Hold") where += " AND IsCancelled=1";

                if (!string.IsNullOrWhiteSpace(_searchText))
                {
                    where += $" AND (t.VchNo LIKE '%{_searchText}%' OR m.Name LIKE '%{_searchText}%')";
                }

                cmd.CommandText = $@"SELECT t.VchCode, t.VchDate as Date, t.VchNo as InvoiceNo,
                    COALESCE(m.Name,'') as PartyName, t.Amount,
                    t.Amount as PendingAmount,
                    t.VchDate as DueDate,
                    CASE WHEN t.IsCancelled=1 THEN 'Hold' ELSE 'Active' END as Status
                    FROM Tran1 t
                    LEFT JOIN Master1 m ON t.MasterCode1=m.Code
                    {where}
                    ORDER BY t.VchDate DESC";

                var list = new System.Collections.ObjectModel.ObservableCollection<InvoiceItem>();
                _serialNo = 0;
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    _serialNo++;
                    list.Add(new InvoiceItem
                    {
                        SN = _serialNo.ToString(),
                        VchCode = r["VchCode"]?.ToString() ?? "",
                        Date = r["Date"]?.ToString() ?? "",
                        InvoiceNo = r["InvoiceNo"]?.ToString() ?? "",
                        PartyName = r["PartyName"]?.ToString() ?? "",
                        Amount = $"₹ {((double)r["Amount"]):N2}",
                        PendingAmount = $"₹ {((double)r["PendingAmount"]):N2}",
                        DueDate = r["DueDate"]?.ToString() ?? "",
                        Status = r["Status"]?.ToString() ?? ""
                    });
                }

                itemsList.ItemsSource = list;
                emptyState.Visibility = list.Count == 0 ? Visibility.Visible : Visibility.Collapsed;
                itemsList.Visibility = list.Count == 0 ? Visibility.Collapsed : Visibility.Visible;

                // Update tab counts
                string dedup = " AND (t.VchCode LIKE 'SRV%' OR t.ServerId IS NULL OR t.ServerId=0 OR NOT EXISTS (SELECT 1 FROM Tran1 s2 WHERE s2.ServerId=t.ServerId AND s2.VchCode LIKE 'SRV%' AND s2.VchCode<>t.VchCode))";
                using var cAll = conn.CreateCommand();
                cAll.CommandText = "SELECT COUNT(*) FROM Tran1 t WHERE VchType='Sales'" + dedup;
                tabAll.Content = $"All ({(long)cAll.ExecuteScalar()})";

                using var cPend = conn.CreateCommand();
                cPend.CommandText = "SELECT COUNT(*) FROM Tran1 t WHERE VchType='Sales' AND IsCancelled=0 AND Amount>0" + dedup;
                tabPending.Content = $"Pending ({(long)cPend.ExecuteScalar()})";

                using var cHold = conn.CreateCommand();
                cHold.CommandText = "SELECT COUNT(*) FROM Tran1 t WHERE VchType='Sales' AND IsCancelled=1" + dedup;
                tabHold.Content = $"Hold ({(long)cHold.ExecuteScalar()})";

                tabOverdue.Content = "Overdue (0)";

                // Totals
                using var cTotal = conn.CreateCommand();
                cTotal.CommandText = "SELECT IFNULL(SUM(t.Amount),0) FROM Tran1 t WHERE VchType='Sales'" + dedup;
                lblTotalAmount.Text = $"₹ {((double)cTotal.ExecuteScalar()):N2}";
                lblTotalPending.Text = $"₹ {((double)cTotal.ExecuteScalar()):N2}";
                lblTotal.Text = $"Total: {list.Count} invoices";
            }
            catch { }
        }

        private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e)
        {
            _searchText = txtSearch.Text.Trim();
            LoadInvoices();
        }

        private void Tab_Click(object sender, RoutedEventArgs e)
        {
            if (tabAll.IsChecked == true) _currentFilter = "All";
            else if (tabPending.IsChecked == true) _currentFilter = "Pending";
            else if (tabOverdue.IsChecked == true) _currentFilter = "Overdue";
            else if (tabHold.IsChecked == true) _currentFilter = "Hold";
            LoadInvoices();
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowDashboard();
        }

        private void BtnAddInvoice_Click(object sender, RoutedEventArgs e)
        {
            var parent = Parent as Panel;
            if (parent != null)
            {
                parent.Children.Clear();
                parent.Children.Add(new CreateInvoicePage(_dashboard!));
            }
        }

        private void BtnView_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is string vchCode)
            {
                // View = custom PDF viewer me sale invoice kholo
                string temp = System.IO.Path.Combine(System.IO.Path.GetTempPath(), "SaleInvoice_" + Guid.NewGuid().ToString("N") + ".pdf");
                PdfService.GenerateTranPdf(vchCode, temp, "SALE INVOICE");
            }
        }

        private void BtnPrint_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is string vchCode)
            {
                // Print = custom PDF viewer me kholo (viewer se hi print/zoom/save)
                string temp = System.IO.Path.Combine(System.IO.Path.GetTempPath(), "SaleInvoice_" + Guid.NewGuid().ToString("N") + ".pdf");
                PdfService.GenerateTranPdf(vchCode, temp, "SALE INVOICE");
            }
        }
    }

    public class InvoiceItem
    {
        public string SN { get; set; } = "";
        public string VchCode { get; set; } = "";
        public string Date { get; set; } = "";
        public string InvoiceNo { get; set; } = "";
        public string PartyName { get; set; } = "";
        public string Amount { get; set; } = "";
        public string PendingAmount { get; set; } = "";
        public string DueDate { get; set; } = "";
        public string Status { get; set; } = "";
    }
}
