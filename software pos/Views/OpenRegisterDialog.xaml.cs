using System;
using System.Collections.Generic;
using System.Globalization;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using RashanKiDukan.Database;
using RashanKiDukan.Models;

namespace RashanKiDukan.Views
{
    /// <summary>
    /// F10 — compact Open Register popup: sirf Cash / UPI / Card 3 rows.
    /// Up/Down arrows se field move, type karke Enter → register open.
    /// Opening details registers.opening_details me save hote hain
    /// ("pmId||name||amount" JSON array — ShiftSettlementService isi ko padhta hai).
    /// </summary>
    public partial class OpenRegisterDialog : Window
    {
        private readonly DatabaseService _db;
        private readonly User? _user;
        private int _rowIndex;   // 0=Cash, 1=UPI, 2=Card

        public bool Opened { get; private set; }

        public OpenRegisterDialog(DatabaseService db, User? user)
        {
            InitializeComponent();
            _db = db;
            _user = user;
            Loaded += (_, _) => { txtCash.Focus(); HighlightRow(0); };
        }

        // ═══ ROW NAVIGATION (Up/Down + focus highlight) ═══

        private void Row_GotFocus(object sender, RoutedEventArgs e)
        {
            _rowIndex = sender == txtCash ? 0 : sender == txtUpi ? 1 : 2;
            HighlightRow(_rowIndex);
        }

        private void HighlightRow(int idx)
        {
            SetRowStyle(rowCash, txtCash, idx == 0);
            SetRowStyle(rowUpi, txtUpi, idx == 1);
            SetRowStyle(rowCard, txtCard, idx == 2);
        }

        private static void SetRowStyle(System.Windows.Controls.Panel row, System.Windows.Controls.TextBox box, bool active)
        {
            row.Opacity = active ? 1.0 : 0.75;
            box.BorderBrush = active
                ? new SolidColorBrush((Color)ColorConverter.ConvertFromString("#0F766E"))
                : new SolidColorBrush((Color)ColorConverter.ConvertFromString("#D1D5DB"));
            box.BorderThickness = new Thickness(active ? 1.5 : 1);
        }

        private void Window_PreviewKeyDown(object sender, KeyEventArgs e)
        {
            switch (e.Key)
            {
                case Key.Down:
                    e.Handled = true;
                    MoveFocus(1);
                    break;
                case Key.Up:
                    e.Handled = true;
                    MoveFocus(-1);
                    break;
                case Key.Enter:
                    e.Handled = true;
                    DoOpenRegister();
                    break;
                case Key.Escape:
                    e.Handled = true;
                    Close();
                    break;
            }
        }

        private void MoveFocus(int dir)
        {
            _rowIndex = Math.Clamp(_rowIndex + dir, 0, 2);
            ( _rowIndex == 0 ? txtCash : _rowIndex == 1 ? (System.Windows.Controls.TextBox)txtUpi : txtCard ).Focus();
        }

        // ═══ INPUT ═══

        private void NumOnly_PreviewTextInput(object sender, TextCompositionEventArgs e)
            => e.Handled = !e.Text.All(char.IsDigit) && e.Text != ".";

        private void Any_TextChanged(object sender, TextChangedEventArgs e) { /* reserved */ }

        private static double ParseNum(string s)
            => double.TryParse((s ?? "").Trim(), NumberStyles.Any, CultureInfo.InvariantCulture, out var v) ? v : 0;

        private void BtnClose_Click(object sender, MouseButtonEventArgs e) => Close();

        // ═══ OPEN REGISTER ═══

        private void DoOpenRegister()
        {
            double cash = ParseNum(txtCash.Text);
            double upi = ParseNum(txtUpi.Text);
            double card = ParseNum(txtCard.Text);

            if (cash + upi + card <= 0)
            {
                MessageBox.Show("Kam se kam ek opening amount daalein.", "Open Register",
                    MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }

            // Duplicate guard: already open register hai to dobara open na ho
            long userId = _user?.Id ?? 1;
            long companyId = _user?.CompanyId ?? 1;
            try
            {
                using var conn = _db.GetConnection();
                using (var chk = conn.CreateCommand())
                {
                    chk.CommandText = @"SELECT COUNT(*) FROM registers
                        WHERE user_id=@u AND outlet_id=1 AND company_id=@c
                          AND del_status='Live' AND register_status=1";
                    chk.Parameters.AddWithValue("@u", userId);
                    chk.Parameters.AddWithValue("@c", companyId);
                    if (Convert.ToInt64(chk.ExecuteScalar()) > 0)
                    {
                        MessageBox.Show("Register pehle se open hai.", "Open Register",
                            MessageBoxButton.OK, MessageBoxImage.Information);
                        Opened = true;
                        Close();
                        return;
                    }
                }

                // Payment method ids (Cash=1, UPI=-1, Card/Stripe/Bank — 'card' name se)
                long cashId = GetPaymentMethodId(conn, "cash", 1);
                long upiId = GetPaymentMethodId(conn, "upi", -1);
                long cardId = GetPaymentMethodId(conn, "card", 4);

                // opening_details: ["pmId||Name||amount", ...] (non-zero only)
                var entries = new List<string>();
                void AddEntry(long pmId, string name, double amt)
                {
                    if (amt <= 0) return;
                    entries.Add($"\"{pmId}||{name}||{amt.ToString("0.##", CultureInfo.InvariantCulture)}\"");
                }
                AddEntry(cashId, "Cash", cash);
                AddEntry(upiId, "UPI", upi);
                AddEntry(cardId, "Card", card);
                string openingDetails = "[" + string.Join(",", entries) + "]";

                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"INSERT INTO registers
                    (opening_balance, opening_balance_date_time, opening_details, register_status,
                     user_id, outlet_id, company_id, del_status, created_at, updated_at, SyncStatus)
                    VALUES (@ob, @odt, @odet, 1, @u, 1, @c, 'Live', datetime('now'), datetime('now'), 'Local')";
                cmd.Parameters.AddWithValue("@ob", cash + upi + card);
                cmd.Parameters.AddWithValue("@odt", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
                cmd.Parameters.AddWithValue("@odet", openingDetails);
                cmd.Parameters.AddWithValue("@u", userId);
                cmd.Parameters.AddWithValue("@c", companyId);
                cmd.ExecuteNonQuery();

                Opened = true;
                Close();
            }
            catch (Exception ex)
            {
                MessageBox.Show("Register open failed: " + ex.Message, "Open Register",
                    MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private static long GetPaymentMethodId(Microsoft.Data.Sqlite.SqliteConnection conn, string key, long fallback)
        {
            try
            {
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id FROM payment_methods WHERE del_status='Live' AND LOWER(name) LIKE @k LIMIT 1";
                cmd.Parameters.AddWithValue("@k", "%" + key + "%");
                var v = cmd.ExecuteScalar();
                return v == null || v == DBNull.Value ? fallback : Convert.ToInt64(v);
            }
            catch { return fallback; }
        }
    }
}
