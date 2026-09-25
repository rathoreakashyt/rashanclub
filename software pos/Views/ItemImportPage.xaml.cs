using System;
using System.Collections.Generic;
using System.Data;
using System.IO;
using System.Windows;
using System.Windows.Controls;
using Microsoft.Win32;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    public partial class ItemImportPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly DatabaseService _db = new();
        private List<string[]> _rows = new();

        public ItemImportPage(MainDashboard? dashboard)
        {
            InitializeComponent();
            _dashboard = dashboard;
        }

        private void BtnChoose_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new OpenFileDialog { Filter = "CSV files (*.csv)|*.csv|Excel files (*.xlsx)|*.xlsx", Title = "Choose items CSV" };
            if (dlg.ShowDialog() != true) return;
            lblFile.Text = dlg.FileName;
            try
            {
                _rows = new List<string[]>();
                using var sr = new StreamReader(dlg.FileName, true);
                string? line;
                while ((line = sr.ReadLine()) != null)
                {
                    if (string.IsNullOrWhiteSpace(line)) continue;
                    _rows.Add(line.Split(','));
                }
                if (_rows.Count == 0) return;

                // Detect header row (first row contains 'name' or 'code')
                int start = 0;
                if (_rows.Count > 1 && (_rows[0][0].Trim().Equals("name", StringComparison.OrdinalIgnoreCase) || _rows[0][0].Trim().Equals("code", StringComparison.OrdinalIgnoreCase)))
                    start = 1;

                var table = new DataTable();
                table.Columns.Add("name");
                table.Columns.Add("code");
                table.Columns.Add("purchase_price");
                table.Columns.Add("sale_price");
                table.Columns.Add("mrp_price");
                table.Columns.Add("stock_quantity");
                for (int i = start; i < Math.Min(_rows.Count, start + 50); i++)
                {
                    var cells = _rows[i];
                    string g(int idx) => cells.Length > idx ? cells[idx].Trim().Trim('"') : "";
                    table.Rows.Add(g(0), g(1), g(2), g(3), g(4), g(5));
                }
                gridPreview.ItemsSource = table.DefaultView;
                gridPreview.Visibility = Visibility.Visible;
                lblFile.Text = $"{dlg.FileName}  ({_rows.Count - start} data rows)";
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error reading file: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void BtnImport_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                if (_rows.Count == 0) { MessageBox.Show("Choose a CSV file first.", "Info"); return; }
                int start = 0;
                if (_rows.Count > 1 && (_rows[0][0].Trim().Equals("name", StringComparison.OrdinalIgnoreCase) || _rows[0][0].Trim().Equals("code", StringComparison.OrdinalIgnoreCase)))
                    start = 1;

                int inserted = 0, updated = 0;
                using var conn = _db.GetConnection();
                using var tx = conn.BeginTransaction();
                for (int i = start; i < _rows.Count; i++)
                {
                    var cells = _rows[i];
                    string g(int idx) => cells.Length > idx ? cells[idx].Trim().Trim('"') : "";
                    string name = g(0), code = g(1);
                    if (string.IsNullOrEmpty(name)) continue;
                    double pp = double.TryParse(g(2), out var p2) ? p2 : 0;
                    double sp = double.TryParse(g(3), out var p3) ? p3 : 0;
                    double mrp = double.TryParse(g(4), out var p4) ? p4 : 0;
                    double stock = double.TryParse(g(5), out var p5) ? p5 : 0;

                    long existing = 0;
                    using (var find = conn.CreateCommand())
                    {
                        find.CommandText = "SELECT Id FROM items WHERE code=@c LIMIT 1";
                        find.Parameters.AddWithValue("@c", (object)code ?? DBNull.Value);
                        var res = find.ExecuteScalar();
                        if (res != null) existing = Convert.ToInt64(res);
                    }

                    if (existing > 0)
                    {
                        using var cmd = conn.CreateCommand();
                        cmd.CommandText = "UPDATE items SET name=@n, purchase_price=@pp, sale_price=@sp, mrp_price=@mrp, stock_quantity=MAX(@sq, 0), SyncStatus='Local' WHERE Id=@id";
                        cmd.Parameters.AddWithValue("@n", name);
                        cmd.Parameters.AddWithValue("@pp", pp);
                        cmd.Parameters.AddWithValue("@sp", sp);
                        cmd.Parameters.AddWithValue("@mrp", mrp);
                        cmd.Parameters.AddWithValue("@sq", stock);
                        cmd.Parameters.AddWithValue("@id", existing);
                        cmd.ExecuteNonQuery();
                        updated++;
                    }
                    else
                    {
                        using var cmd = conn.CreateCommand();
                        cmd.CommandText = "INSERT INTO items (name, code, purchase_price, sale_price, mrp_price, stock_quantity, del_status, SyncStatus) VALUES (@n,@c,@pp,@sp,@mrp,@sq,'Live','Local')";
                        cmd.Parameters.AddWithValue("@n", name);
                        cmd.Parameters.AddWithValue("@c", (object)code ?? DBNull.Value);
                        cmd.Parameters.AddWithValue("@pp", pp);
                        cmd.Parameters.AddWithValue("@sp", sp);
                        cmd.Parameters.AddWithValue("@mrp", mrp);
                        cmd.Parameters.AddWithValue("@sq", stock);
                        cmd.ExecuteNonQuery();
                        inserted++;
                    }
                }
                tx.Commit();
                // Stock import hua — saare subscribed pages refresh
                Services.StockEvents.NotifyStockChanged();
                MessageBox.Show($"Import complete: {inserted} inserted, {updated} updated.", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
                _rows.Clear();
                gridPreview.Visibility = Visibility.Collapsed;
                lblFile.Text = "Done — choose another file to import more.";
            }
            catch (Exception ex)
            {
                MessageBox.Show("Import failed: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            if (_dashboard != null) _dashboard.ShowDashboard();
        }
    }
}
