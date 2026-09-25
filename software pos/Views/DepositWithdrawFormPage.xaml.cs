using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views;

public partial class DepositWithdrawFormPage : UserControl
{
    private MainDashboard? _dashboard;
    private readonly DatabaseService _db = new DatabaseService();
    private long _editId = 0;
    private bool IsEdit => _editId > 0;

    public DepositWithdrawFormPage() { InitializeComponent(); Loaded += OnLoaded; }
    public DepositWithdrawFormPage(MainDashboard dashboard) : this() { _dashboard = dashboard; }
    public DepositWithdrawFormPage(MainDashboard dashboard, long editId) : this(dashboard) { _editId = editId; }

    private void OnLoaded(object sender, RoutedEventArgs e)
    {
        LoadPaymentMethods();
        txtRef.Text = GenerateRef();
        dpDate.SelectedDate = DateTime.Today;

        if (IsEdit) LoadForEdit();
    }

    private void LoadPaymentMethods()
    {
        var methods = new List<PaymentMethodItem>();
        using var conn = _db.GetConnection();
        conn.Open();
        var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT id, name FROM payment_methods WHERE (del_status IS NULL OR del_status='Live') AND status='Enable' ORDER BY name";
        using var r = cmd.ExecuteReader();
        while (r.Read())
            methods.Add(new PaymentMethodItem { Id = r.GetInt64(0), Name = r.IsDBNull(1) ? "" : r.GetString(1) });
        cmbPaymentMethod.ItemsSource = methods;
    }

    private string GenerateRef()
    {
        using var conn = _db.GetConnection();
        conn.Open();
        var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT COUNT(*) FROM deposit_withdraws";
        var count = Convert.ToInt64(cmd.ExecuteScalar() ?? 0);
        return $"DW-{(count + 1).ToString("D6")}";
    }

    private void LoadForEdit()
    {
        using var conn = _db.GetConnection();
        conn.Open();
        var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT reference_no, date, type, payment_method_id, amount, note FROM deposit_withdraws WHERE id=@id";
        cmd.Parameters.AddWithValue("@id", _editId);
        using var r = cmd.ExecuteReader();
        if (r.Read())
        {
            txtRef.Text = r.IsDBNull(0) ? "" : r.GetString(0);
            if (!r.IsDBNull(1))
            {
                var dt = r.GetDateTime(1);
                dpDate.SelectedDate = dt;
            }
            if (!r.IsDBNull(2))
            {
                var type = r.GetString(2);
                foreach (ComboBoxItem item in cmbType.Items)
                {
                    if ((string)item.Tag == type) { cmbType.SelectedItem = item; break; }
                }
            }
            if (!r.IsDBNull(3))
            {
                var pmId = r.GetInt64(3);
                foreach (PaymentMethodItem pm in cmbPaymentMethod.Items)
                {
                    if (pm.Id == pmId) { cmbPaymentMethod.SelectedItem = pm; break; }
                }
            }
            txtAmount.Text = r.IsDBNull(4) ? "" : r.GetDecimal(4).ToString();
            txtNote.Text = r.IsDBNull(5) ? "" : r.GetString(5);
        }
    }

    private void BtnSubmit_Click(object sender, RoutedEventArgs e)
    {
        if (cmbType.SelectedItem is not ComboBoxItem typeItem)
        { MessageBox.Show("Please select Type.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning); return; }
        if (cmbPaymentMethod.SelectedItem is not PaymentMethodItem pm)
        { MessageBox.Show("Please select Payment Method.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning); return; }
        if (!decimal.TryParse(txtAmount.Text, out var amount) || amount <= 0)
        { MessageBox.Show("Please enter a valid Amount.", "Validation", MessageBoxButton.OK, MessageBoxImage.Warning); return; }

        var refNo = txtRef.Text.Trim();
        var date = dpDate.SelectedDate?.ToString("yyyy-MM-dd") ?? DateTime.Today.ToString("yyyy-MM-dd");
        var type = (string)typeItem.Tag;
        var note = txtNote.Text.Trim();

        using var conn = _db.GetConnection();
        conn.Open();
        var now = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");

        if (IsEdit)
        {
            var cmd = conn.CreateCommand();
            cmd.CommandText = @"UPDATE deposit_withdraws SET reference_no=@ref, date=@date, type=@type, 
                                payment_method_id=@pm, amount=@amt, note=@note, updated_at=@now WHERE id=@id";
            cmd.Parameters.AddWithValue("@ref", refNo);
            cmd.Parameters.AddWithValue("@date", date);
            cmd.Parameters.AddWithValue("@type", type);
            cmd.Parameters.AddWithValue("@pm", pm.Id);
            cmd.Parameters.AddWithValue("@amt", amount);
            cmd.Parameters.AddWithValue("@note", note);
            cmd.Parameters.AddWithValue("@now", now);
            cmd.Parameters.AddWithValue("@id", _editId);
            cmd.ExecuteNonQuery();
            SyncService.EnqueueSync("deposit_withdraws", _editId, "update");
        }
        else
        {
            var cmd = conn.CreateCommand();
            cmd.CommandText = @"INSERT INTO deposit_withdraws (reference_no, date, type, payment_method_id, amount, note, del_status, created_at, updated_at, SyncStatus)
                                VALUES (@ref, @date, @type, @pm, @amt, @note, 'Live', @now, @now, 'Local');
                                SELECT last_insert_rowid();";
            cmd.Parameters.AddWithValue("@ref", refNo);
            cmd.Parameters.AddWithValue("@date", date);
            cmd.Parameters.AddWithValue("@type", type);
            cmd.Parameters.AddWithValue("@pm", pm.Id);
            cmd.Parameters.AddWithValue("@amt", amount);
            cmd.Parameters.AddWithValue("@note", note);
            cmd.Parameters.AddWithValue("@now", now);
            var newId = Convert.ToInt64(cmd.ExecuteScalar());
            SyncService.EnqueueSync("deposit_withdraws", newId, "create");
        }

        _dashboard?.TriggerSync();
        MessageBox.Show(IsEdit ? "Record updated successfully!" : "Record added successfully!", "Success", MessageBoxButton.OK, MessageBoxImage.Information);
        _dashboard?.ShowPage(new DepositWithdrawListPage(_dashboard));
    }

    private void BtnBack_Click(object sender, RoutedEventArgs e)
    {
        _dashboard?.ShowPage(new DepositWithdrawListPage(_dashboard));
    }
}

public class PaymentMethodItem
{
    public long Id { get; set; }
    public string Name { get; set; } = "";
    public override string ToString() => Name;
}
