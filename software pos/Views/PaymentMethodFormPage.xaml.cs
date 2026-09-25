using System.Windows;
using System.Windows.Controls;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views;

public partial class PaymentMethodFormPage : UserControl
{
    private readonly long _editId = 0;
    private MainDashboard? _dashboard;
    private readonly DatabaseService _db = new DatabaseService();

    public PaymentMethodFormPage() { InitializeComponent(); Loaded += OnLoaded; }
    public PaymentMethodFormPage(MainDashboard dashboard) : this() { _dashboard = dashboard; }
    public PaymentMethodFormPage(MainDashboard dashboard, long editId) : this()
    {
        _dashboard = dashboard;
        _editId = editId;
    }

    private void OnLoaded(object sender, RoutedEventArgs e)
    {
        LoadAccountTypes();
        cmbStatus.ItemsSource = new[] { "Enable", "Disable" };
        cmbStatus.SelectedIndex = 0;

        if (_editId > 0)
        {
            headerTitle.Text = "Edit Payment Method";
            LoadData();
        }
    }

    private void LoadAccountTypes()
    {
        var types = new List<AccountTypeItem>();

        // First load existing account types from database
        using var conn = _db.GetConnection();
        conn.Open();
        var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT DISTINCT account_type FROM payment_methods WHERE del_status='Live' AND account_type IS NOT NULL AND account_type<>''";
        using var r = cmd.ExecuteReader();
        while (r.Read())
        {
            var val = r.GetString(0);
            if (!string.IsNullOrWhiteSpace(val))
                types.Add(new AccountTypeItem { Name = val });
        }
        r.Close();

        // Also add all standard web types if not already present
        var standardTypes = new[]
        {
            "Cash", "Bank_Account", "Card", "Mobile_Banking",
            "Paypal", "Stripe", "Razorpay", "Paystack", "Paytm",
            "Flutterwave", "SslCommerz", "Mollie", "Senangpay",
            "Bkash", "Mercadopago", "Cashfree", "Payfast",
            "Skrill", "PhonePe", "Telr", "Iyzico", "Pesapal",
            "Midtrans", "MyFatoorah", "EasyPaisa", "Loyalty_Point"
        };

        foreach (var t in standardTypes)
        {
            if (!types.Any(x => x.Name == t))
                types.Add(new AccountTypeItem { Name = t });
        }

        cmbAccountType.ItemsSource = types;
    }

    private void LoadData()
    {
        using var conn = _db.GetConnection();
        conn.Open();
        var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT name, account_type, status, current_balance, description FROM payment_methods WHERE id=@id";
        cmd.Parameters.AddWithValue("@id", _editId);
        using var r = cmd.ExecuteReader();
        if (r.Read())
        {
            txtName.Text = r.IsDBNull(0) ? "" : r.GetString(0);
            SelectAccountType(r.IsDBNull(1) ? "" : r.GetString(1));
            SelectCombo(cmbStatus, r.IsDBNull(2) ? "Enable" : r.GetString(2));
            txtBalance.Text = r.IsDBNull(3) ? "0" : r.GetDouble(3).ToString();
            txtDescription.Text = r.IsDBNull(4) ? "" : r.GetString(4);
        }
    }

    private void SelectAccountType(string value)
    {
        foreach (var item in cmbAccountType.Items)
        {
            if (item is AccountTypeItem ati && ati.Name == value)
            {
                cmbAccountType.SelectedItem = item;
                return;
            }
        }
    }

    private void SelectCombo(ComboBox cmb, string value)
    {
        foreach (var item in cmb.Items)
        {
            if (item?.ToString() == value)
            {
                cmb.SelectedItem = item;
                return;
            }
        }
    }

    private void BtnBack_Click(object sender, RoutedEventArgs e)
    {
        _dashboard?.ShowPage(new PaymentMethodListPage(_dashboard));
    }

    private void BtnSave_Click(object sender, RoutedEventArgs e)
    {
        if (cmbAccountType.SelectedItem is not AccountTypeItem accType)
        { MessageBox.Show("Select Account Type.", "Validation"); return; }
        if (string.IsNullOrWhiteSpace(txtName.Text))
        { MessageBox.Show("Account Name is required.", "Validation"); return; }

        var name = txtName.Text.Trim();
        var accountType = accType.Name;
        var type = accountType.Contains("Bank") ? "Bank" :
                   accountType.Contains("Card") ? "Card" :
                   accountType.Contains("Mobile") || accountType.Contains("Wallet") ? "Mobile" :
                   "Other";
        var status = cmbStatus.SelectedItem?.ToString() ?? "Enable";
        double.TryParse(txtBalance.Text, out var balance);
        var description = txtDescription.Text.Trim();
        var now = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");

        using var conn = _db.GetConnection();
        conn.Open();

        if (_editId > 0)
        {
            var cmd = conn.CreateCommand();
            cmd.CommandText = @"UPDATE payment_methods SET name=@name, type=@type, account_type=@atype, 
                                status=@status, current_balance=@bal, description=@desc, updated_at=@now WHERE id=@id";
            cmd.Parameters.AddWithValue("@name", name);
            cmd.Parameters.AddWithValue("@type", type);
            cmd.Parameters.AddWithValue("@atype", accountType);
            cmd.Parameters.AddWithValue("@status", status);
            cmd.Parameters.AddWithValue("@bal", balance);
            cmd.Parameters.AddWithValue("@desc", description);
            cmd.Parameters.AddWithValue("@now", now);
            cmd.Parameters.AddWithValue("@id", _editId);
            cmd.ExecuteNonQuery();

            SyncService.EnqueueSync("payment_methods", _editId, "update");
        }
        else
        {
            var cmd = conn.CreateCommand();
            cmd.CommandText = @"INSERT INTO payment_methods (name, type, account_type, status, current_balance, description, del_status, created_at, updated_at)
                                VALUES (@name, @type, @atype, @status, @bal, @desc, 'Live', @now, @now)";
            cmd.Parameters.AddWithValue("@name", name);
            cmd.Parameters.AddWithValue("@type", type);
            cmd.Parameters.AddWithValue("@atype", accountType);
            cmd.Parameters.AddWithValue("@status", status);
            cmd.Parameters.AddWithValue("@bal", balance);
            cmd.Parameters.AddWithValue("@desc", description);
            cmd.Parameters.AddWithValue("@now", now);
            cmd.ExecuteNonQuery();

            var getId = conn.CreateCommand();
            getId.CommandText = "SELECT last_insert_rowid()";
            var newId = (long)(getId.ExecuteScalar() ?? 0);
            SyncService.EnqueueSync("payment_methods", newId, "insert");
        }

        _dashboard?.TriggerSync();
        _dashboard?.ShowPage(new PaymentMethodListPage(_dashboard));
    }
}

public class AccountTypeItem
{
    public string Name { get; set; } = "";
}
