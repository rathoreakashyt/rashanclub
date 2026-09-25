using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class CreateDrNotePage : UserControl
    {
        private readonly DatabaseService _db = new DatabaseService();
        private readonly MainDashboard? _dashboard;

        public CreateDrNotePage() { InitializeComponent(); }
        public CreateDrNotePage(MainDashboard dashboard) : this() { _dashboard = dashboard; LoadSuppliers(); }

        private void LoadSuppliers()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT Code, Name FROM Master1 WHERE MasterType='Party' AND Name<>'' ORDER BY Name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                    cmbSupplier.Items.Add(new ComboBoxItem { Content = r["Name"]?.ToString() ?? "", Tag = r["Code"] });
                if (cmbSupplier.Items.Count > 0) cmbSupplier.SelectedIndex = 0;
            }
            catch { }
        }

        private void ToggleNarration(object sender, MouseButtonEventArgs e)
        {
            if (pnlNarration.Visibility == Visibility.Visible) { pnlNarration.Visibility = Visibility.Collapsed; arrowNarr.Text = "\uE096"; }
            else { pnlNarration.Visibility = Visibility.Visible; arrowNarr.Text = "\uE094"; }
        }

        private void ToggleTaxSummary(object sender, MouseButtonEventArgs e)
        {
            if (pnlTaxSummary.Visibility == Visibility.Visible) { pnlTaxSummary.Visibility = Visibility.Collapsed; arrowTax.Text = "\uE096"; }
            else { pnlTaxSummary.Visibility = Visibility.Visible; arrowTax.Text = "\uE094"; }
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            var parent = Parent as Panel;
            if (parent != null)
            {
                parent.Children.Clear();
                parent.Children.Add(new PurchaseReturnPage(_dashboard!));
            }
        }

        private void BtnSave_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();

                string vchCode = "DR" + DateTime.Now.ToString("yyyyMMddHHmmss");

                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"INSERT INTO Tran1 (VchCode, VchType, VchNo, VchDate, VchSeriesCode, Narration, Amount, IsCancelled, CreatedAt, UpdatedAt)
                    VALUES (@code, 'PurchaseReturn', @vno, @vdate, @series, @narr, 0, 0, datetime('now'), datetime('now'))";
                cmd.Parameters.AddWithValue("@code", vchCode);
                cmd.Parameters.AddWithValue("@vno", txtVchNo.Text);
                cmd.Parameters.AddWithValue("@vdate", txtDate.Text);
                cmd.Parameters.AddWithValue("@series", cmbSeries.Text);
                cmd.Parameters.AddWithValue("@narr", txtNarration.Text.Trim());
                cmd.ExecuteNonQuery();

                txn.Commit();
                MessageBox.Show("Dr. Note saved successfully!", "Success", MessageBoxButton.OK, MessageBoxImage.Information);

                var parent = Parent as Panel;
                if (parent != null) { parent.Children.Clear(); parent.Children.Add(new PurchaseReturnPage(_dashboard!)); }
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }
    }
}
