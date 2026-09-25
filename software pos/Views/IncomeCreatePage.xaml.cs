using System;
using System.Windows;
using System.Windows.Controls;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class IncomeCreatePage : UserControl
    {
        private readonly DatabaseService _db = new();
        private readonly MainDashboard? _dashboard;
        private long _editId = 0;

        public IncomeCreatePage() { InitializeComponent(); }
        public IncomeCreatePage(MainDashboard dashboard) : this()
        {
            _dashboard = dashboard;
            LoadCombos();
            dtpDate.SelectedDate = DateTime.Today;
            txtReference.Text = NextReference();
        }
        public IncomeCreatePage(MainDashboard dashboard, long incomeId) : this()
        {
            _dashboard = dashboard;
            LoadForEdit(incomeId);
        }

        private void LoadCombos()
        {
            try
            {
                using var conn = _db.GetConnection();

                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"SELECT Id, Name FROM income_categories
                                        WHERE (del_status IS NULL OR del_status='Live') ORDER BY Name";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        cmbCategory.Items.Add(new ComboBoxItem
                        {
                            Content = r["Name"]?.ToString() ?? "",
                            Tag = r["Id"] is long id ? id : 0
                        });
                    }
                }

                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"SELECT Id, Name FROM payment_methods
                                        WHERE (del_status IS NULL OR del_status='Live') AND Name<>'' ORDER BY Name";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        cmbAccount.Items.Add(new ComboBoxItem
                        {
                            Content = r["Name"]?.ToString() ?? "",
                            Tag = r["Id"] is long id ? id : 0
                        });
                    }
                }

                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT Id, Name FROM employees WHERE Name<>'' ORDER BY Name";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        cmbEmployee.Items.Add(new ComboBoxItem
                        {
                            Content = r["Name"]?.ToString() ?? "",
                            Tag = r["Id"] is long id ? id : 0
                        });
                    }
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error loading options: " + ex.Message, "Error",
                    MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private string NextReference()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT MAX(CAST(SUBSTR(reference_no, 5) AS INTEGER))
                                    FROM incomes WHERE reference_no LIKE 'INC-%'";
                var max = cmd.ExecuteScalar();
                int next = (max is long l ? (int)l : 0) + 1;
                return $"INC-{next:D4}";
            }
            catch { return "INC-0001"; }
        }

        private void LoadForEdit(long id)
        {
            try
            {
                _editId = id;
                lblTitle.Text = "Edit Income";
                if (lblSaveText != null) lblSaveText.Text = "Update";

                LoadCombos();

                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT reference_no, date, category_id, payment_method_id,
                                           amount, employee_id, note
                                    FROM incomes WHERE id=@id";
                cmd.Parameters.AddWithValue("@id", id);
                using var r = cmd.ExecuteReader();
                if (!r.Read()) return;

                txtReference.Text = r["reference_no"]?.ToString() ?? "";

                string dateStr = r["date"]?.ToString() ?? "";
                if (DateTime.TryParse(dateStr, out var d)) dtpDate.SelectedDate = d;

                long catId = r["category_id"] is long cv ? cv : 0;
                SelectComboById(cmbCategory, catId);

                long pmId = r["payment_method_id"] is long pv ? pv : 0;
                SelectComboById(cmbAccount, pmId);

                double amount = r["amount"] is double av ? av : 0;
                txtAmount.Text = amount != 0 ? amount.ToString("F2") : "";

                long empId = r["employee_id"] is long ev ? ev : 0;
                SelectComboById(cmbEmployee, empId);

                txtNote.Text = r["note"]?.ToString() ?? "";
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error loading income: " + ex.Message, "Error",
                    MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private static void SelectComboById(ComboBox combo, long id)
        {
            if (id <= 0) return;
            foreach (var item in combo.Items)
            {
                if (item is ComboBoxItem cbi && cbi.Tag is long tag && tag == id)
                {
                    combo.SelectedItem = item;
                    return;
                }
            }
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowPage(new IncomeListPage(_dashboard!));
        }

        private void BtnSave_Click(object sender, RoutedEventArgs e)
        {
            if (dtpDate.SelectedDate == null)
            {
                MessageBox.Show("Please select a date.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }
            if (!double.TryParse(txtAmount.Text, out double amount) || amount <= 0)
            {
                MessageBox.Show("Please enter a valid amount.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                txtAmount.Focus(); return;
            }
            if (cmbCategory.SelectedItem is not ComboBoxItem catItem || catItem.Tag is not long catId || catId <= 0)
            {
                MessageBox.Show("Please select a category.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }

            try
            {
                long accountId = cmbAccount.SelectedItem is ComboBoxItem accItem && accItem.Tag is long accTag ? accTag : 0;
                long empId = cmbEmployee.SelectedItem is ComboBoxItem empItem && empItem.Tag is long empTag ? empTag : 0;

                string date = dtpDate.SelectedDate!.Value.ToString("yyyy-MM-dd");
                string reference = txtReference.Text.Trim();
                if (reference == "") reference = NextReference();

                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();
                using var cmd = conn.CreateCommand();

                if (_editId > 0)
                {
                    cmd.CommandText = @"UPDATE incomes SET
                        reference_no=@ref, date=@date, category_id=@cat,
                        payment_method_id=@acc, amount=@amt, employee_id=@emp,
                        note=@note, updated_at=datetime('now')
                        WHERE id=@id";
                    cmd.Parameters.AddWithValue("@id", _editId);
                }
                else
                {
                    cmd.CommandText = @"INSERT INTO incomes
                        (reference_no, date, category_id, payment_method_id, amount,
                         employee_id, note, del_status, company_id,
                         created_at, updated_at, SyncStatus)
                        VALUES
                        (@ref,@date,@cat,@acc,@amt,@emp,@note,'Live',1,
                         datetime('now'),datetime('now'),'Local')";
                }

                cmd.Parameters.AddWithValue("@ref", reference);
                cmd.Parameters.AddWithValue("@date", date);
                cmd.Parameters.AddWithValue("@cat", catId);
                cmd.Parameters.AddWithValue("@acc", accountId == 0 ? DBNull.Value : (object)accountId);
                cmd.Parameters.AddWithValue("@amt", amount);
                cmd.Parameters.AddWithValue("@emp", empId == 0 ? DBNull.Value : (object)empId);
                cmd.Parameters.AddWithValue("@note", txtNote.Text.Trim());
                cmd.ExecuteNonQuery();

                long savedId = _editId;
                if (savedId == 0)
                {
                    using var idCmd = conn.CreateCommand();
                    idCmd.CommandText = "SELECT last_insert_rowid()";
                    savedId = (long)idCmd.ExecuteScalar();
                }

                txn.Commit();

                Services.SyncService.EnqueueSync("incomes", savedId, _editId > 0 ? "update" : "insert");
                MessageBox.Show("Income saved successfully!", "Success",
                    MessageBoxButton.OK, MessageBoxImage.Information);
                _dashboard?.ShowPage(new IncomeListPage(_dashboard!));
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }
    }
}
