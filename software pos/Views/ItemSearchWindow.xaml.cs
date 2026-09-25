using System;
using System.Collections.Generic;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    /// <summary>
    /// Master1 (MasterType='Item') me se item search karke Code return karta hai.
    /// CreateItemWindow ke "Search Item" button ke liye.
    /// </summary>
    public partial class ItemSearchWindow : Window
    {
        private readonly DatabaseService _db = new DatabaseService();
        public string? SelectedCode { get; private set; }

        public ItemSearchWindow()
        {
            InitializeComponent();
            Loaded += (_, _) => { txtSearch.Focus(); Search(""); };
        }

        private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e) => Search(txtSearch.Text.Trim());

        private void Search(string q)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT Code, Name, AliasName, SaleRate FROM Master1
                                    WHERE MasterType='Item' AND IsActive=1
                                      AND (@q = '' OR Name LIKE @like OR AliasName LIKE @like OR Code LIKE @like)
                                    ORDER BY Name LIMIT 300";
                cmd.Parameters.AddWithValue("@q", q);
                cmd.Parameters.AddWithValue("@like", "%" + q + "%");

                var rows = new List<SearchItemRow>();
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    rows.Add(new SearchItemRow
                    {
                        Code = r["Code"]?.ToString() ?? "",
                        Name = r["Name"]?.ToString() ?? "",
                        SaleRate = r["SaleRate"] == DBNull.Value ? "" : Convert.ToDouble(r["SaleRate"]).ToString("N2")
                    });
                }
                lstItems.ItemsSource = null;
                lstItems.ItemsSource = rows;
                lstItems.SelectedIndex = rows.Count > 0 ? 0 : -1;
            }
            catch { }
        }

        private void LstItems_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (lstItems.SelectedItem is SearchItemRow row) SelectedCode = row.Code;
        }

        private void LstItems_MouseDoubleClick(object sender, MouseButtonEventArgs e) => BtnOk_Click(this, null);

        private void BtnOk_Click(object sender, RoutedEventArgs e)
        {
            if (lstItems.SelectedItem is SearchItemRow row) SelectedCode = row.Code;
            if (string.IsNullOrEmpty(SelectedCode))
            {
                MessageBox.Show("Please select an item.", "Search Item",
                    MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }
            DialogResult = true;
        }

        private void BtnCancel_Click(object sender, RoutedEventArgs e) => DialogResult = false;

        private void Window_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Escape) { DialogResult = false; e.Handled = true; }
            else if (e.Key == Key.Enter && lstItems.SelectedIndex >= 0) { BtnOk_Click(this, null); e.Handled = true; }
        }
    }

    public class SearchItemRow
    {
        public string Code { get; set; } = "";
        public string Name { get; set; } = "";
        public string SaleRate { get; set; } = "";
    }
}
