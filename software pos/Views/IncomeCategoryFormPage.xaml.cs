using System;
using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class IncomeCategoryFormPage : UserControl
    {
        private readonly DatabaseService _db = new();
        private readonly MainDashboard? _dashboard;
        private long _editId = 0;

        public IncomeCategoryFormPage() { InitializeComponent(); }
        public IncomeCategoryFormPage(MainDashboard dashboard) : this()
        {
            _dashboard = dashboard;
        }
        public IncomeCategoryFormPage(MainDashboard dashboard, long categoryId) : this()
        {
            _dashboard = dashboard;
            LoadForEdit(categoryId);
        }

        private void LoadForEdit(long id)
        {
            try
            {
                _editId = id;
                lblTitle.Text = "Edit Income Category";
                if (lblSaveText != null) lblSaveText.Text = "Update";

                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT name, description FROM income_categories WHERE id=@id";
                cmd.Parameters.AddWithValue("@id", id);
                using var r = cmd.ExecuteReader();
                if (!r.Read()) return;

                txtName.Text = r["name"]?.ToString() ?? "";
                txtDescription.Text = r["description"]?.ToString() ?? "";
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error loading category: " + ex.Message, "Error",
                    MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowPage(new IncomeCategoryListPage(_dashboard!));
        }

        private void BtnSave_Click(object sender, RoutedEventArgs e)
        {
            if (string.IsNullOrWhiteSpace(txtName.Text))
            {
                MessageBox.Show("Please enter category name.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                txtName.Focus(); return;
            }

            try
            {
                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();
                using var cmd = conn.CreateCommand();

                if (_editId > 0)
                {
                    cmd.CommandText = @"UPDATE income_categories SET
                        name=@name, description=@desc, updated_at=datetime('now')
                        WHERE id=@id";
                    cmd.Parameters.AddWithValue("@id", _editId);
                }
                else
                {
                    cmd.CommandText = @"INSERT INTO income_categories
                        (name, description, user_id, company_id, del_status, created_at, updated_at)
                        VALUES (@name,@desc,1,1,'Live',datetime('now'),datetime('now'))";
                }

                cmd.Parameters.AddWithValue("@name", txtName.Text.Trim());
                cmd.Parameters.AddWithValue("@desc", txtDescription.Text.Trim());
                cmd.ExecuteNonQuery();

                long savedId = _editId;
                if (savedId == 0)
                {
                    using var idCmd = conn.CreateCommand();
                    idCmd.CommandText = "SELECT last_insert_rowid()";
                    savedId = (long)idCmd.ExecuteScalar();
                }

                txn.Commit();

                Services.SyncService.EnqueueSync("income_categories", savedId, _editId > 0 ? "update" : "insert");
                MessageBox.Show("Income category saved successfully!", "Success",
                    MessageBoxButton.OK, MessageBoxImage.Information);
                _dashboard?.ShowPage(new IncomeCategoryListPage(_dashboard!));
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }
    }
}
