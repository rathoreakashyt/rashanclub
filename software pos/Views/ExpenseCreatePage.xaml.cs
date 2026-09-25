using System;
using System.Globalization;
using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class ExpenseCreatePage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new DatabaseService();
        private long _editId;

        public ExpenseCreatePage() { InitializeComponent(); }
        public ExpenseCreatePage(MainDashboard dashboard) : this() { _dashboard = dashboard; LoadLookups(); }
        public ExpenseCreatePage(MainDashboard dashboard, long id) : this()
        {
            _dashboard = dashboard;
            LoadLookups();
            LoadForEdit(id);
        }

        private void LoadForEdit(long id)
        {
            try
            {
                _editId = id;
                lblTitle.Text = "Edit Expense";
                lblSubtitle.Text = "Update the selected expense";
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT category_id, payment_method_id, amount, date, note, reference_no FROM expenses WHERE Id=@id";
                cmd.Parameters.AddWithValue("@id", id);
                using var r = cmd.ExecuteReader();
                if (!r.Read()) return;
                SelectByTag(cmbCategory, r["category_id"] is long cid ? cid.ToString() : "");
                SelectByTag(cmbPayment, r["payment_method_id"] is long pm ? pm.ToString() : "");
                txtAmount.Text = (r["amount"] is double d ? d : 0).ToString(CultureInfo.InvariantCulture);
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

        private void LoadLookups()
        {
            try
            {
                foreach (var c in Lookups.ExpenseCategories(_db))
                    cmbCategory.Items.Add(new ComboBoxItem { Content = c.Name, Tag = c.Id.ToString() });

                foreach (var p in Lookups.PaymentMethods(_db))
                    cmbPayment.Items.Add(new ComboBoxItem { Content = p.Name, Tag = p.Id.ToString() });
                cmbPayment.SelectedIndex = 0;
            }
            catch { }

            dpDate.SelectedDate = DateTime.Today;
            txtReference.Text = LocalTxn.NextReference(_db, "expenses", "EXP-");
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowPage(new ExpenseListPage(_dashboard!));
        }

        private void BtnSave_Click(object sender, RoutedEventArgs e)
        {
            long categoryId = 0;
            long.TryParse((cmbCategory.SelectedItem as ComboBoxItem)?.Tag as string ?? "", out categoryId);
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
                    up.CommandText = @"UPDATE expenses SET reference_no=@ref, date=@date, category_id=@cat, payment_method_id=@pm,
                                            amount=@amt, note=@note, del_status='Live', SyncStatus='Local'
                                       WHERE Id=@id";
                    up.Parameters.AddWithValue("@id", _editId);
                    up.Parameters.AddWithValue("@ref", txtReference.Text.Trim());
                    up.Parameters.AddWithValue("@date", dpDate.SelectedDate?.ToString("yyyy-MM-dd") ?? LocalTxn.Today());
                    up.Parameters.AddWithValue("@cat", categoryId > 0 ? (object)categoryId : DBNull.Value);
                    up.Parameters.AddWithValue("@pm", paymentMethodId > 0 ? (object)paymentMethodId : DBNull.Value);
                    up.Parameters.AddWithValue("@amt", amount);
                    up.Parameters.AddWithValue("@note", (object?)txtNote.Text.Trim() ?? "");
                    up.ExecuteNonQuery();
                }
                else
                {
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = @"INSERT INTO expenses (Id, reference_no, date, category_id, payment_method_id, amount, note, del_status, SyncStatus)
                                        VALUES (@id, @ref, @date, @cat, @pm, @amt, @note, 'Live', 'Local')";
                    cmd.Parameters.AddWithValue("@id", LocalTxn.NextLocalId(_db, "expenses"));
                    cmd.Parameters.AddWithValue("@ref", txtReference.Text.Trim());
                    cmd.Parameters.AddWithValue("@date", dpDate.SelectedDate?.ToString("yyyy-MM-dd") ?? LocalTxn.Today());
                    cmd.Parameters.AddWithValue("@cat", categoryId > 0 ? (object)categoryId : DBNull.Value);
                    cmd.Parameters.AddWithValue("@pm", paymentMethodId > 0 ? (object)paymentMethodId : DBNull.Value);
                    cmd.Parameters.AddWithValue("@amt", amount);
                    cmd.Parameters.AddWithValue("@note", (object?)txtNote.Text.Trim() ?? "");
                    cmd.ExecuteNonQuery();
                }
                txn.Commit();
                lblMsg.Text = _editId > 0 ? "Expense updated. It will sync to the server automatically."
                                          : "Expense saved locally. It will sync to the server automatically.";
                _dashboard?.TriggerSync();
                _dashboard?.ShowPage(new ExpenseListPage(_dashboard!));
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }
    }
}
