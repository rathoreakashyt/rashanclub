using System.Collections.ObjectModel;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views
{
    public partial class CreateInvoicePage : UserControl
    {
        private readonly DatabaseService _db = new DatabaseService();
        private readonly MainDashboard? _dashboard;
        private readonly ObservableCollection<InvoiceLineItem> _items = new();
        private readonly ObservableCollection<BillSundryItem> _billSundry = new();
        private readonly List<TaxSummaryRow> _taxSummary = new();
        private readonly List<BusyItemInfo> _itemList = new();
        private int _srNo = 0;
        private bool _isTaxInclusive = true;
        private bool _isInterState = false;

        public CreateInvoicePage() { InitializeComponent(); }
        public CreateInvoicePage(MainDashboard dashboard) : this()
        {
            _dashboard = dashboard;
            txtDate.Text = DateTime.Now.ToString("dd-MM-yyyy");
            txtVchNo.Text = InvoiceNumberService.GenerateSaleNo(_db, dashboard.CurrentUser.Id);
            LoadParties();
            LoadItems();
            dgItems.ItemsSource = _items;
            dgBillSundry.ItemsSource = _billSundry;
            dgTaxSummary.ItemsSource = _taxSummary.ToList();
            cmbSaleType.SelectedIndex = 0;
            CalculateTotals();
        }

        // ═══════ DATA LOAD ═══════

        private void LoadParties()
        {
            try
            {
                cmbParty.Items.Clear();
                cmbParty.Items.Add(new ComboBoxItem { Content = "Select Party", Tag = "" });
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT Code, Name, GSTIN, OpeningBalance FROM Master1 WHERE MasterType='Party' AND IsActive=1 ORDER BY Name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    double.TryParse(r["OpeningBalance"]?.ToString() ?? "0", out double bal);
                    cmbParty.Items.Add(new ComboBoxItem
                    {
                        Content = r["Name"]?.ToString() ?? "",
                        Tag = new PartyInfo
                        {
                            Code = r["Code"]?.ToString() ?? "",
                            Name = r["Name"]?.ToString() ?? "",
                            GSTIN = r["GSTIN"]?.ToString() ?? "",
                            Balance = bal
                        }
                    });
                }
                cmbParty.SelectedIndex = 0;
                cmbParty.SelectionChanged += CmbParty_SelectionChanged;
            }
            catch { }
        }

        private void LoadItems()
        {
            try
            {
                cmbAddItem.Items.Clear();
                _itemList.Clear();
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT Code, Name, HSNCode, MRP, SaleRate, TaxCategory, MainUnit FROM Master1 WHERE MasterType='Item' AND IsActive=1 AND Name<>'' ORDER BY Name";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    double.TryParse(r["SaleRate"]?.ToString() ?? "0", out double rate);
                    double.TryParse(r["MRP"]?.ToString() ?? "0", out double mrp);
                    var info = new BusyItemInfo
                    {
                        Code = r["Code"]?.ToString() ?? "",
                        Name = r["Name"]?.ToString() ?? "",
                        HSNCode = r["HSNCode"]?.ToString() ?? "",
                        MRP = mrp,
                        Rate = rate,
                        TaxCat = r["TaxCategory"]?.ToString() ?? "",
                        Unit = r["MainUnit"]?.ToString() ?? "NOS"
                    };
                    _itemList.Add(info);
                    cmbAddItem.Items.Add(new ComboBoxItem { Content = info.Name, Tag = info });
                }
                if (cmbAddItem.Items.Count > 0) cmbAddItem.SelectedIndex = 0;
            }
            catch { }
        }

        private void CmbParty_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (cmbParty.SelectedItem is ComboBoxItem ci && ci.Tag is PartyInfo party)
            {
                lblCurBal.Text = $"( Cur. Bal : Rs. {party.Balance:N2} )  ( GSTIN / UIN : {party.GSTIN} )";
            }
            else
            {
                lblCurBal.Text = "( Cur. Bal : Rs. 0.00 )  ( GSTIN / UIN : )";
            }
        }

        private void cmbSaleType_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (cmbSaleType.SelectedItem is ComboBoxItem ci && ci.Tag is string tag)
            {
                _isTaxInclusive = tag.Contains("Incl");
                _isInterState = tag.Contains("Central") || tag.Contains("IGST");
            }
            CalculateTotals();
        }

        // ═══════ ITEM OPERATIONS ═══════

        private void BtnAddItem_Click(object sender, MouseButtonEventArgs e) { AddItem(); }

        private void AddItem()
        {
            if (cmbAddItem.SelectedItem == null) return;
            var itemInfo = (cmbAddItem.SelectedItem as ComboBoxItem)?.Tag as BusyItemInfo;
            if (itemInfo == null) return;

            double.TryParse(txtAddQty.Text, out double qty);
            double.TryParse(txtAddRate.Text, out double rate);
            double.TryParse(txtAddDisc.Text, out double disc);

            if (rate == 0) rate = itemInfo.Rate;
            if (qty <= 0) qty = 1;

            double listPrice = rate;
            double taxRate = GetTaxRate(itemInfo.TaxCat);
            double pricePerUnit = rate;

            if (_isTaxInclusive)
            {
                pricePerUnit = rate / (1 + taxRate / 100.0);
            }

            double discountedPrice = pricePerUnit - (pricePerUnit * disc / 100.0);
            double totDisc = (pricePerUnit - discountedPrice) * qty;
            double taxable = discountedPrice * qty;
            double amount = pricePerUnit * qty;

            _srNo++;
            _items.Add(new InvoiceLineItem
            {
                SrNo = _srNo,
                ItemCode = itemInfo.Code,
                ItemName = itemInfo.Name,
                HSNCode = itemInfo.HSNCode,
                MRP = itemInfo.MRP,
                Qty = qty,
                Unit = itemInfo.Unit,
                ListPrice = Math.Round(listPrice, 2),
                Disc = disc,
                TotDisc = Math.Round(totDisc, 2),
                Price = Math.Round(discountedPrice, 2),
                Amount = Math.Round(amount, 2),
                TaxPerc = taxRate,
                TaxableAmount = Math.Round(taxable, 2)
            });

            CalculateTotals();
            txtAddQty.Text = "1";
            txtAddRate.Text = "0";
            txtAddDisc.Text = "0";
        }

        private double GetTaxRate(string taxCat)
        {
            if (string.IsNullOrEmpty(taxCat)) return 0;
            if (taxCat.Contains("28")) return 28;
            if (taxCat.Contains("18")) return 18;
            if (taxCat.Contains("12")) return 12;
            if (taxCat.Contains("5")) return 5;
            return 0;
        }

        // ═══════ TAX CALCULATION ═══════

        private void CalculateTotals()
        {
            double subTotal = 0, totalDisc = 0, totalCGST = 0, totalSGST = 0, totalIGST = 0;

            _taxSummary.Clear();
            var groupedTax = new Dictionary<double, TaxSummaryRow>();

            foreach (var item in _items)
            {
                subTotal += item.Amount;
                totalDisc += item.TotDisc;

                double taxableAmt = item.TaxableAmount;
                double taxAmt = taxableAmt * item.TaxPerc / 100.0;

                if (!groupedTax.ContainsKey(item.TaxPerc))
                {
                    groupedTax[item.TaxPerc] = new TaxSummaryRow { TaxRate = $"{item.TaxPerc}%" };
                }
                groupedTax[item.TaxPerc].TaxableAmount += taxableAmt;

                if (_isInterState)
                {
                    double igst = taxAmt;
                    groupedTax[item.TaxPerc].IGST += igst;
                    totalIGST += igst;
                }
                else
                {
                    double cgst = taxAmt / 2.0;
                    double sgst = taxAmt / 2.0;
                    groupedTax[item.TaxPerc].CGST += cgst;
                    groupedTax[item.TaxPerc].SGST += sgst;
                    totalCGST += cgst;
                    totalSGST += sgst;
                }
                groupedTax[item.TaxPerc].TotalTax += taxAmt;
            }

            foreach (var row in groupedTax.Values)
            {
                row.TaxableAmount = Math.Round(row.TaxableAmount, 2);
                row.CGST = Math.Round(row.CGST, 2);
                row.SGST = Math.Round(row.SGST, 2);
                row.IGST = Math.Round(row.IGST, 2);
                row.TotalTax = Math.Round(row.TotalTax, 2);
                _taxSummary.Add(row);
            }

            double grandTotal = subTotal + totalCGST + totalSGST + totalIGST;

            lblSubTotal.Text = $"Rs.{subTotal:N2}";
            lblTotalDisc.Text = $"- Rs.{totalDisc:N2}";
            lblCGST.Text = $"Rs.{totalCGST:N2}";
            lblSGST.Text = $"Rs.{totalSGST:N2}";
            lblIGST.Text = $"Rs.{totalIGST:N2}";
            lblGrandTotal.Text = $"Rs.{grandTotal:N2}";
        }

        // ═══════ SAVE ═══════

        private void BtnSave_Click(object sender, RoutedEventArgs e)
        {
            if (_items.Count == 0) { MessageBox.Show("Add at least one item!", "Error", MessageBoxButton.OK, MessageBoxImage.Warning); return; }

            try
            {
                using var conn = _db.GetConnection();
                using var txn = conn.BeginTransaction();

                string vchCode = InvoiceNumberService.MakeVchCode(_db, _dashboard?.CurrentUser.Id, "SAL");
                double grandTotal = _items.Sum(x => x.Amount);
                double totalCGST = _taxSummary.Sum(x => x.CGST);
                double totalSGST = _taxSummary.Sum(x => x.SGST);
                double totalIGST = _taxSummary.Sum(x => x.IGST);
                double totalTax = totalCGST + totalSGST + totalIGST;

                var partyItem = cmbParty.SelectedItem as ComboBoxItem;
                var partyInfo = partyItem?.Tag as PartyInfo;

                string syncPayload = BuildSyncPayload(vchCode, partyInfo, grandTotal, totalTax, txtVchNo.Text.Trim());

                // Insert Tran1
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"INSERT INTO Tran1 (VchCode, VchType, VchNo, VchDate, VchSeriesCode, MasterCode1, Narration, Amount, PaymentMode, SyncPayload, SyncStatus, IsCancelled, CreatedAt, UpdatedAt)
                        VALUES (@code, 'Sales', @vno, @vdate, @series, @party, @narr, @amt, 'Cash', @payload, 'Local', 0, datetime('now'), datetime('now'))";
                    cmd.Parameters.AddWithValue("@code", vchCode);
                    cmd.Parameters.AddWithValue("@vno", txtVchNo.Text);
                    cmd.Parameters.AddWithValue("@vdate", txtDate.Text);
                    cmd.Parameters.AddWithValue("@series", cmbSeries.Text);
                    cmd.Parameters.AddWithValue("@party", partyInfo?.Code ?? "");
                    cmd.Parameters.AddWithValue("@narr", txtNarration.Text.Trim());
                    cmd.Parameters.AddWithValue("@amt", grandTotal);
                    cmd.Parameters.AddWithValue("@payload", syncPayload);
                    cmd.ExecuteNonQuery();
                }

                // Insert Tran2
                foreach (var item in _items)
                {
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = @"INSERT INTO Tran2 (VchCode, SrNo, MasterCode1, Description, Quantity, Unit, Rate, Amount, DiscountPercent, DiscountAmount, TaxableAmount)
                        VALUES (@vch, @sr, @item, @desc, @qty, @unit, @rate, @amt, @discp, @disca, @taxable)";
                    cmd.Parameters.AddWithValue("@vch", vchCode);
                    cmd.Parameters.AddWithValue("@sr", item.SrNo);
                    cmd.Parameters.AddWithValue("@item", item.ItemCode);
                    cmd.Parameters.AddWithValue("@desc", item.ItemName);
                    cmd.Parameters.AddWithValue("@qty", item.Qty);
                    cmd.Parameters.AddWithValue("@unit", item.Unit);
                    cmd.Parameters.AddWithValue("@rate", item.Price);
                    cmd.Parameters.AddWithValue("@amt", item.Amount);
                    cmd.Parameters.AddWithValue("@discp", item.Disc);
                    cmd.Parameters.AddWithValue("@disca", item.TotDisc);
                    cmd.Parameters.AddWithValue("@taxable", item.TaxableAmount);
                    cmd.ExecuteNonQuery();
                }

                // Insert VchGSTSumItemWise
                int taxSrNo = 1;
                foreach (var ts in _taxSummary)
                {
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = @"INSERT INTO VchGSTSumItemWise (VchCode, SrNo, HSNCode, TaxRate, TaxableAmount, CGST, SGST, IGST, TotalTax)
                        VALUES (@vch, @sr, '', @rate, @taxable, @cgst, @sgst, @igst, @total)";
                    cmd.Parameters.AddWithValue("@vch", vchCode);
                    cmd.Parameters.AddWithValue("@sr", taxSrNo++);
                    cmd.Parameters.AddWithValue("@rate", ts.TaxRate.Replace("%", ""));
                    cmd.Parameters.AddWithValue("@taxable", ts.TaxableAmount);
                    cmd.Parameters.AddWithValue("@cgst", ts.CGST);
                    cmd.Parameters.AddWithValue("@sgst", ts.SGST);
                    cmd.Parameters.AddWithValue("@igst", ts.IGST);
                    cmd.Parameters.AddWithValue("@total", ts.TotalTax);
                    cmd.ExecuteNonQuery();
                }

                // Insert BillingDet
                if (partyInfo != null)
                {
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = @"INSERT INTO BillingDet (VchCode, BillToName, BillToGSTIN)
                        VALUES (@vch, @name, @gstin)";
                    cmd.Parameters.AddWithValue("@vch", vchCode);
                    cmd.Parameters.AddWithValue("@name", partyInfo.Name);
                    cmd.Parameters.AddWithValue("@gstin", partyInfo.GSTIN);
                    cmd.ExecuteNonQuery();
                }

                txn.Commit();
                MessageBox.Show($"Invoice saved!\nVoucher: {vchCode}\nTotal: Rs.{grandTotal:N2}\nTax: Rs.{totalTax:N2}",
                    "Success", MessageBoxButton.OK, MessageBoxImage.Information);

                var parent = Parent as Panel;
                if (parent != null) { parent.Children.Clear(); parent.Children.Add(new InvoicesPage(_dashboard!)); }
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        // ═══════ SYNC PAYLOAD ═══════

        private string BuildSyncPayload(string vchCode, PartyInfo? partyInfo, double grandTotal, double totalTax, string invoiceNo)
        {
            double subTotal = _items.Sum(x => x.Amount);
            double totalDisc = _items.Sum(x => x.TotDisc);

            var items = new List<object>();
            foreach (var item in _items)
            {
                items.Add(new Dictionary<string, object?>
                {
                    ["item_code"] = item.ItemCode,
                    ["item_id"] = ServerIdOf(item.ItemCode),
                    ["qty"] = item.Qty,
                    ["menu_unit_price"] = item.Price,
                    ["menu_vat_percentage"] = item.TaxPerc,
                    ["item_tax_amount"] = Math.Round(item.TaxableAmount * item.TaxPerc / 100.0, 2),
                    ["discount_amount"] = item.TotDisc,
                    ["discount_type"] = "fixed"
                });
            }

            var payload = new Dictionary<string, object?>
            {
                ["local_id"] = vchCode,
                ["invoice_no"] = invoiceNo,
                ["sale_date"] = txtDate.Text,
                ["date_time"] = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"),
                ["customer_id"] = partyInfo != null ? ServerIdOfParty(partyInfo.Code) : null,
                ["sub_total"] = Math.Round(subTotal, 2),
                ["given_amount"] = grandTotal,
                ["paid_amount"] = grandTotal,
                ["change_amount"] = 0,
                ["disc"] = Math.Round(totalDisc, 2),
                ["vat"] = Math.Round(totalTax, 2),
                ["total_payable"] = grandTotal,
                ["grand_total"] = grandTotal,
                ["note"] = txtNarration.Text.Trim(),
                ["items"] = items,
                ["payments"] = new List<object>
                {
                    new Dictionary<string, object?> { ["payment_name"] = "Cash", ["amount"] = grandTotal }
                }
            };

            return System.Text.Json.JsonSerializer.Serialize(payload);
        }

        private long ServerIdOf(string masterCode)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT ServerId FROM Master1 WHERE Code = @c AND MasterType='Item'";
                cmd.Parameters.AddWithValue("@c", masterCode);
                var val = cmd.ExecuteScalar();
                return val != null && long.TryParse(val.ToString(), out long id) ? id : 0;
            }
            catch { return 0; }
        }

        private long ServerIdOfParty(string masterCode)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT ServerId FROM Master1 WHERE Code = @c AND MasterType='Party'";
                cmd.Parameters.AddWithValue("@c", masterCode);
                var val = cmd.ExecuteScalar();
                return val != null && long.TryParse(val.ToString(), out long id) ? id : 0;
            }
            catch { return 0; }
        }

        // ═══════ UI HELPERS ═══════

        private void dgItems_CellEditEnding(object sender, DataGridCellEditEndingEventArgs e)
        {
            if (e.EditAction == DataGridEditAction.Commit)
            {
                Dispatcher.BeginInvoke(new Action(() => CalculateTotals()), System.Windows.Threading.DispatcherPriority.Background);
            }
        }

        private void ToggleNarration(object sender, MouseButtonEventArgs e)
        {
            if (pnlNarration.Visibility == Visibility.Visible) { pnlNarration.Visibility = Visibility.Collapsed; arrowNarr.Text = "\uE096"; }
            else { pnlNarration.Visibility = Visibility.Visible; arrowNarr.Text = "\uE094"; }
        }

        private void ToggleTaxSummary(object sender, MouseButtonEventArgs e)
        {
            if (pnlTaxSummary.Visibility == Visibility.Visible) { pnlTaxSummary.Visibility = Visibility.Collapsed; arrowTax.Text = "\uE096"; }
            else { pnlTaxSummary.Visibility = Visibility.Visible; arrowTax.Text = "\uE094"; }
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            var parent = Parent as Panel;
            if (parent != null) { parent.Children.Clear(); parent.Children.Add(new InvoicesPage(_dashboard!)); }
        }
    }

    // ═══════ MODEL CLASSES ═══════

    public class InvoiceLineItem
    {
        public int SrNo { get; set; }
        public string ItemCode { get; set; } = "";
        public string ItemName { get; set; } = "";
        public string HSNCode { get; set; } = "";
        public double MRP { get; set; }
        public double Qty { get; set; }
        public string Unit { get; set; } = "";
        public double ListPrice { get; set; }
        public double Disc { get; set; }
        public double TotDisc { get; set; }
        public double Price { get; set; }
        public double Amount { get; set; }
        public double TaxPerc { get; set; }
        public double TaxableAmount { get; set; }
    }

    public class BillSundryItem
    {
        public string Name { get; set; } = "";
        public string Type { get; set; } = "";
        public double Rate { get; set; }
        public double Amount { get; set; }
    }

    public class TaxSummaryRow
    {
        public string TaxRate { get; set; } = "0%";
        public double TaxableAmount { get; set; }
        public double CGST { get; set; }
        public double SGST { get; set; }
        public double IGST { get; set; }
        public double TotalTax { get; set; }
    }

    public class PartyInfo
    {
        public string Code { get; set; } = "";
        public string Name { get; set; } = "";
        public string GSTIN { get; set; } = "";
        public double Balance { get; set; }
    }
}
