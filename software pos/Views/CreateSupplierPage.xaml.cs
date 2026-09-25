using System;
using System.Windows;
using System.Windows.Controls;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class CreateSupplierPage : UserControl
    {
        private readonly DatabaseService _db = new DatabaseService();
        private readonly MainDashboard? _dashboard;
        private long _editServerId = 0;
        private string _editCode = "";
        private bool _saving = false;

        public CreateSupplierPage() { InitializeComponent(); }
        public CreateSupplierPage(MainDashboard dashboard) : this() { _dashboard = dashboard; }
        public CreateSupplierPage(MainDashboard dashboard, string code) : this()
        {
            _dashboard = dashboard;
            LoadForEdit(code);
        }

        private void LoadForEdit(string code)
        {
            try
            {
                _editCode = code;
                if (lblTitle != null) lblTitle.Text = "Update Supplier";
                if (lblSaveText != null) lblSaveText.Text = "Update";

                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT Name, ContactPerson, Email, Phone, Mobile,
                                           Address, OpeningBalance, DrCr, ServerId, Description
                                    FROM Master1 WHERE Code=@code AND MasterType='Party'";
                cmd.Parameters.AddWithValue("@code", code);
                using var r = cmd.ExecuteReader();
                if (!r.Read()) return;

                txtName.Text = r["Name"]?.ToString() ?? "";
                txtContactPerson.Text = r["ContactPerson"]?.ToString() ?? "";
                txtPhone.Text = r["Phone"]?.ToString() ?? r["Mobile"]?.ToString() ?? "";
                txtEmail.Text = r["Email"]?.ToString() ?? "";
                txtAddress.Text = r["Address"]?.ToString() ?? "";

                double ob = r["OpeningBalance"] is double v ? v : 0;
                txtOpeningBalance.Text = ob != 0 ? ob.ToString("F3") : "";
                string obType = r["DrCr"]?.ToString() ?? "Dr";
                cmbDrCr.SelectedIndex = obType == "Cr" ? 1 : 0;

                if (r["ServerId"] is long sid) _editServerId = sid;

                // Load description from suppliers table
                if (_editServerId > 0)
                {
                    using var sc = conn.CreateCommand();
                    sc.CommandText = "SELECT description FROM suppliers WHERE id=@sid";
                    sc.Parameters.AddWithValue("@sid", _editServerId);
                    var desc = sc.ExecuteScalar();
                    txtDescription.Text = desc?.ToString() ?? "";
                }
            }
            catch { }
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowPage(new SuppliersListPage(_dashboard!));
        }

        private void BtnSave_Click(object sender, RoutedEventArgs e)
        {
            if (_saving) return;
            if (string.IsNullOrWhiteSpace(txtName.Text))
            {
                MessageBox.Show("Please enter supplier name.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                txtName.Focus(); return;
            }
            if (string.IsNullOrWhiteSpace(txtContactPerson.Text))
            {
                MessageBox.Show("Please enter contact person.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                txtContactPerson.Focus(); return;
            }
            if (string.IsNullOrWhiteSpace(txtPhone.Text))
            {
                MessageBox.Show("Please enter phone number.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning);
                txtPhone.Focus(); return;
            }

            _saving = true;
            try
            {
                // Prevent duplicates: a Live supplier with the same name must not be created twice
                if (_editCode == "")
                {
                    using var dc = _db.GetConnection();
                    using var dcmd = dc.CreateCommand();
                    dcmd.CommandText = @"SELECT COUNT(*) FROM Master1
                                         WHERE MasterType='Party'
                                           AND PartyType IN ('Supplier','Both')
                                           AND (DelStatus IS NULL OR DelStatus != 'Deleted')
                                           AND LOWER(TRIM(Name)) = LOWER(@name)";
                    dcmd.Parameters.AddWithValue("@name", txtName.Text.Trim());
                    if ((long)dcmd.ExecuteScalar() > 0)
                    {
                        MessageBox.Show("A supplier with this name already exists. Open the existing supplier to edit it.", "Duplicate", MessageBoxButton.OK, MessageBoxImage.Warning);
                        return;
                    }
                }

                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();

                double.TryParse(txtOpeningBalance.Text, out double opbal);

                using (var cmd = conn.CreateCommand())
                {
                    cmd.Transaction = txn;
                    if (_editCode != "")
                    {
                        cmd.CommandText = @"UPDATE Master1 SET
                            Name=@name, ContactPerson=@contact, Email=@email,
                            Phone=@phone, Mobile=@phone, Address=@addr,
                            OpeningBalance=@opbal, DrCr=@drcr,
                            UpdatedAt=datetime('now'), SyncStatus='Local'
                            WHERE Code=@code AND MasterType='Party'";
                        cmd.Parameters.AddWithValue("@code", _editCode);
                    }
                    else
                    {
                        string code = "SUP" + RashanKiDukan.Services.DeviceContext.Short + DateTime.Now.ToString("yyyyMMddHHmmssfff");
                        cmd.CommandText = @"INSERT INTO Master1
                            (Code, Name, ContactPerson, Email, Phone, Mobile, Address,
                             OpeningBalance, DrCr, MasterType, PartyType,
                             IsActive, CreatedAt, UpdatedAt, SyncStatus)
                            VALUES
                            (@code, @name, @contact, @email, @phone, @phone, @addr,
                             @opbal, @drcr, 'Party', 'Supplier',
                             1, datetime('now'), datetime('now'), 'Local')";
                        cmd.Parameters.AddWithValue("@code", code);
                    }
                    cmd.Parameters.AddWithValue("@name", txtName.Text.Trim());
                    cmd.Parameters.AddWithValue("@contact", txtContactPerson.Text.Trim());
                    cmd.Parameters.AddWithValue("@email", txtEmail.Text.Trim());
                    cmd.Parameters.AddWithValue("@phone", txtPhone.Text.Trim());
                    cmd.Parameters.AddWithValue("@addr", txtAddress.Text.Trim());
                    cmd.Parameters.AddWithValue("@opbal", opbal);
                    cmd.Parameters.AddWithValue("@drcr", cmbDrCr.Text == "Credit" ? "Cr" : "Dr");
                    cmd.ExecuteNonQuery();
                }

                // Get ServerId for sync
                long serverId = _editServerId;
                if (serverId == 0 && _editCode != "")
                {
                    using var sc = conn.CreateCommand();
                    sc.Transaction = txn;
                    sc.CommandText = "SELECT ServerId FROM Master1 WHERE Code=@code AND MasterType='Party'";
                    sc.Parameters.AddWithValue("@code", _editCode);
                    var v = sc.ExecuteScalar();
                    if (v is long sid) serverId = sid;
                }

                // Upsert suppliers table row
                string description = txtDescription.Text.Trim();
                if (serverId > 0)
                {
                    using var sc = conn.CreateCommand();
                    sc.Transaction = txn;
                    sc.CommandText = @"INSERT INTO suppliers (id, name, email, phone, address, opening_balance, opening_balance_type, description, del_status, updated_at)
                                        VALUES (@id,@name,@email,@phone,@addr,@ob,@obt,@desc,'Live',datetime('now'))
                                        ON CONFLICT(id) DO UPDATE SET
                                            name=@name, email=@email, phone=@phone, address=@addr,
                                            opening_balance=@ob, opening_balance_type=@obt, description=@desc, updated_at=datetime('now')";
                    sc.Parameters.AddWithValue("@id", serverId);
                    sc.Parameters.AddWithValue("@name", txtName.Text.Trim());
                    sc.Parameters.AddWithValue("@email", txtEmail.Text.Trim());
                    sc.Parameters.AddWithValue("@phone", txtPhone.Text.Trim());
                    sc.Parameters.AddWithValue("@addr", txtAddress.Text.Trim());
                    sc.Parameters.AddWithValue("@ob", opbal);
                    sc.Parameters.AddWithValue("@obt", cmbDrCr.Text == "Credit" ? "Cr" : "Dr");
                    sc.Parameters.AddWithValue("@desc", description);
                    sc.ExecuteNonQuery();
                }

                txn.Commit();

                _dashboard?.TriggerSync();
                MessageBox.Show("Supplier saved successfully!", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
                _dashboard?.ShowPage(new SuppliersListPage(_dashboard!));
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
            finally
            {
                _saving = false;
            }
        }
    }
}
