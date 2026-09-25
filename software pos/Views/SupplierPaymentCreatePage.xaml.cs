using System;
using System.Globalization;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class SupplierPaymentCreatePage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new DatabaseService();
        private long _editId;

        public SupplierPaymentCreatePage() { InitializeComponent(); }
        public SupplierPaymentCreatePage(MainDashboard dashboard) : this() { _dashboard = dashboard; LoadLookups(); }
        public SupplierPaymentCreatePage(MainDashboard dashboard, long id) : this()
        {
            _dashboard = dashboard;
            LoadLookups();
            LoadForEdit(id);
        }

        private void LoadLookups()
        {
            try
            {
                foreach (var s in Lookups.Suppliers(_db))
                    cmbSupplier.Items.Add(new ComboBoxItem { Content = s.Name, Tag = s.Id.ToString() });

                foreach (var p in Lookups.PaymentMethods(_db))
                    cmbPayment.Items.Add(new ComboBoxItem { Content = p.Name, Tag = p.Id.ToString() });
                cmbPayment.SelectedIndex = 0;
            }
            catch { }

            dpDate.SelectedDate = DateTime.Today;
            txtReference.Text = LocalTxn.NextReference(_db, "supplier_payments", "SPAY-");
        }

        private void LoadForEdit(long id)
        {
            try
            {
                _editId = id;
                lblTitle.Text = "Update Supplier Payment";
                lblSubtitle.Text = "Home / Purchase / Update Supplier Payment";
                lblSaveIcon.Text = "\uE70F";
                lblSaveText.Text = "Update";
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT supplier_id, payment_method_id, amount, date, note, reference_no FROM supplier_payments WHERE Id=@id";
                cmd.Parameters.AddWithValue("@id", id);
                using var r = cmd.ExecuteReader();
                if (!r.Read()) return;
                SelectByTag(cmbSupplier, r["supplier_id"] is long sid ? sid.ToString() : "");
                SelectByTag(cmbPayment, r["payment_method_id"] is long pm ? pm.ToString() : "");
                txtAmount.Text = (r["amount"] is double d ? d : 0).ToString("0.###", CultureInfo.InvariantCulture);
                txtNote.Text = r["note"]?.ToString() ?? "";
                txtReference.Text = r["reference_no"]?.ToString() ?? "";
                if (DateTime.TryParse(r["date"]?.ToString(), out DateTime dt)) dpDate.SelectedDate = dt;
            }
            catch { }
        }

        private static void SelectByTag(ComboBox cb, string tag)
        {
            for (int i = 0; i < cb.Items.Count; i++)
                if ((cb.Items[i] as ComboBoxItem)?.Tag?.ToString() == tag) { cb.SelectedIndex = i; return; }
            if (cb.Items.Count > 0) cb.SelectedIndex = 0;
        }

        private void CmbSupplier_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            LoadSupplierBalance();
        }

        private void LoadSupplierBalance()
        {
            balanceRow.Visibility = Visibility.Collapsed;
            if (cmbSupplier.SelectedItem is not ComboBoxItem it) return;
            if (!long.TryParse(it.Tag as string ?? "", out long sid) || sid <= 0) return;

            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT COALESCE(m.OpeningBalance,0) AS ob, COALESCE(m.DrCr,'Dr') AS obt,
                        COALESCE((SELECT SUM(due_amount) FROM purchases p WHERE p.supplier_id = m.ServerId AND (p.del_status IS NULL OR p.del_status='Live')),0) AS due,
                        COALESCE((SELECT SUM(amount) FROM supplier_payments sp WHERE sp.supplier_id = m.ServerId AND (sp.del_status IS NULL OR sp.del_status='Live')),0) AS paid,
                        COALESCE((SELECT SUM(total_return_amount) FROM purchase_returns pr WHERE pr.supplier_id = m.ServerId AND (pr.del_status IS NULL OR pr.del_status='Live')),0) AS ret
                        FROM Master1 m WHERE m.MasterType='Party' AND m.PartyType IN ('Supplier','Both') AND m.ServerId=@id AND m.IsActive=1";
                cmd.Parameters.AddWithValue("@id", sid);
                using var r = cmd.ExecuteReader();
                if (!r.Read()) return;

                double ob = r["ob"] is double ob2 ? ob2 : 0;
                string obt = r["obt"]?.ToString() ?? "Dr";
                double due = r["due"] is double d1 ? d1 : 0;
                double paid = r["paid"] is double d2 ? d2 : 0;
                double ret = r["ret"] is double d3 ? d3 : 0;
                double bal = obt == "Cr" || obt == "Credit"
                    ? (due - paid) + ob - ret
                    : (due - paid) - ob - ret;

                // Web behavior: hide balance if 0
                if (Math.Abs(bal) < 0.005)
                {
                    balanceRow.Visibility = Visibility.Collapsed;
                    return;
                }

                bool isCredit = bal < 0;
                lblBalanceAmount.Text = Math.Abs(bal).ToString("N2");
                lblBalanceType.Text = isCredit ? "(Credit)" : "(Debit)";

                // Web: Debit=green badge, Credit=red badge
                balancePill.Background = isCredit
                    ? new SolidColorBrush((Color)ColorConverter.ConvertFromString("#FEE2E2"))
                    : new SolidColorBrush((Color)ColorConverter.ConvertFromString("#D1FAE5"));
                lblBalanceType.Foreground = isCredit
                    ? new SolidColorBrush((Color)ColorConverter.ConvertFromString("#DC2626"))
                    : new SolidColorBrush((Color)ColorConverter.ConvertFromString("#059669"));

                balanceRow.Visibility = Visibility.Visible;
            }
            catch { }
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowPage(new SupplierPaymentListPage(_dashboard!));
        }

        private void BtnSave_Click(object sender, RoutedEventArgs e)
        {
            if (cmbSupplier.SelectedIndex < 0 || !long.TryParse((cmbSupplier.SelectedItem as ComboBoxItem)?.Tag as string ?? "", out long supplierId))
            {
                MessageBox.Show("Please select a supplier.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }
            if (!double.TryParse(txtAmount.Text.Trim(), NumberStyles.Any, CultureInfo.InvariantCulture, out double amount) || amount <= 0)
            {
                MessageBox.Show("Please enter a valid amount.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }
            long paymentMethodId = 0;
            long.TryParse((cmbPayment.SelectedItem as ComboBoxItem)?.Tag as string ?? "", out paymentMethodId);

            try
            {
                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();
                if (_editId > 0)
                {
                    using var up = conn.CreateCommand();
                    up.CommandText = @"UPDATE supplier_payments SET supplier_id=@sup, payment_method_id=@pm, amount=@amt, date=@date,
                                            note=@note, reference_no=@ref, del_status='Live', SyncStatus='Local'
                                       WHERE Id=@id";
                    up.Parameters.AddWithValue("@id", _editId);
                    up.Parameters.AddWithValue("@sup", supplierId);
                    up.Parameters.AddWithValue("@pm", paymentMethodId > 0 ? (object)paymentMethodId : DBNull.Value);
                    up.Parameters.AddWithValue("@amt", amount);
                    up.Parameters.AddWithValue("@date", dpDate.SelectedDate?.ToString("yyyy-MM-dd") ?? LocalTxn.Today());
                    up.Parameters.AddWithValue("@note", (object?)txtNote.Text.Trim() ?? "");
                    up.Parameters.AddWithValue("@ref", txtReference.Text.Trim());
                    up.ExecuteNonQuery();
                }
                else
                {
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = @"INSERT INTO supplier_payments (Id, supplier_id, payment_method_id, amount, date, note, reference_no, del_status, SyncStatus)
                                        VALUES (@id, @sup, @pm, @amt, @date, @note, @ref, 'Live', 'Local')";
                    cmd.Parameters.AddWithValue("@id", LocalTxn.NextLocalId(_db, "supplier_payments"));
                    cmd.Parameters.AddWithValue("@sup", supplierId);
                    cmd.Parameters.AddWithValue("@pm", paymentMethodId > 0 ? (object)paymentMethodId : DBNull.Value);
                    cmd.Parameters.AddWithValue("@amt", amount);
                    cmd.Parameters.AddWithValue("@date", dpDate.SelectedDate?.ToString("yyyy-MM-dd") ?? LocalTxn.Today());
                    cmd.Parameters.AddWithValue("@note", (object?)txtNote.Text.Trim() ?? "");
                    cmd.Parameters.AddWithValue("@ref", txtReference.Text.Trim());
                    cmd.ExecuteNonQuery();
                }
                txn.Commit();
                lblMsg.Text = _editId > 0 ? "Payment updated. It will sync to the server automatically."
                                          : "Payment saved locally. It will sync to the server automatically.";
                lblMsg.Visibility = Visibility.Visible;
                _dashboard?.TriggerSync();
                _dashboard?.ShowPage(new SupplierPaymentListPage(_dashboard!));
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }
    }
}
