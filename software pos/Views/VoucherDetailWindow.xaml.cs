using System;
using System.Collections.Generic;
using System.Windows;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views
{
    public partial class VoucherDetailWindow : Window
    {
        private readonly MainDashboard? _dashboard;
        private readonly bool _isReturn;
        private readonly long _id;
        private long _supplierId;

        public class DetailItem
        {
            public string Name { get; set; } = "";
            public string Qty { get; set; } = "";
            public string Price { get; set; } = "";
            public string Total { get; set; } = "";
        }

        public VoucherDetailWindow(MainDashboard dashboard, bool isReturn, long id, bool printNow = false)
        {
            InitializeComponent();
            Owner = Application.Current.MainWindow;
            _dashboard = dashboard;
            _isReturn = isReturn;
            _id = id;
            lblTitle.Text = isReturn ? "Purchase Return Details" : "Purchase Details";
            lblTotalCaption.Text = isReturn ? "Total Return:" : "Grand Total:";
            LoadData();
            if (printNow) BtnPrint_Click(this, new RoutedEventArgs());
        }

        private void LoadData()
        {
            try
            {
                var db = new DatabaseService();
                using var conn = db.GetConnection();
                string table = _isReturn ? "purchase_returns" : "purchases";
                string refCol = _isReturn ? "reference_no" : "reference_no";
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = _isReturn
                        ? @"SELECT r.reference_no, r.date, r.purchase_date, r.note, r.total_return_amount, r.supplier_id,
                                   COALESCE(s.name,'') AS supplier
                            FROM purchase_returns r LEFT JOIN Suppliers s ON s.Id = r.supplier_id WHERE r.Id=@id"
                        : @"SELECT p.reference_no, p.date, p.invoice_no, p.note, p.grand_total, p.paid, p.due_amount, p.supplier_id,
                                   COALESCE(s.name,'') AS supplier
                            FROM purchases p LEFT JOIN Suppliers s ON s.Id = p.supplier_id WHERE p.Id=@id";
                    cmd.Parameters.AddWithValue("@id", _id);
                    using var r = cmd.ExecuteReader();
                    if (r.Read())
                    {
                        lblRef.Text = r["reference_no"]?.ToString() ?? "";
                        lblSupplier.Text = r["supplier"]?.ToString() ?? "";
                        lblDate.Text = r["date"]?.ToString() ?? "";
                        _supplierId = r["supplier_id"] is long sid ? sid : 0;
                        if (_isReturn)
                        {
                            lblExtra.Text = "Purchase Date: " + (r["purchase_date"]?.ToString() ?? "");
                            lblTotal.Text = (r["total_return_amount"] is double d ? d : 0).ToString("N2");
                            lblPaid.Text = "-";
                            lblDue.Text = "-";
                        }
                        else
                        {
                            lblExtra.Text = "Invoice No: " + (r["invoice_no"]?.ToString() ?? "");
                            lblTotal.Text = (r["grand_total"] is double gt ? gt : 0).ToString("N2");
                            lblPaid.Text = (r["paid"] is double pd ? pd : 0).ToString("N2");
                            lblDue.Text = (r["due_amount"] is double da ? da : 0).ToString("N2");
                        }
                    }
                }

                var items = new List<DetailItem>();
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = _isReturn
                        ? "SELECT item_id, return_quantity_amount, unit_price, total FROM purchase_return_details WHERE pur_return_id=@id"
                        : "SELECT item_id, quantity_amount, unit_price, total FROM purchase_details WHERE purchase_id=@id";
                    cmd.Parameters.AddWithValue("@id", _id);
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        long itemId = r["item_id"] is long iid ? iid : 0;
                        string qtyCol = _isReturn ? "return_quantity_amount" : "quantity_amount";
                        items.Add(new DetailItem
                        {
                            Name = ItemName(conn, itemId),
                            Qty = r[qtyCol] is double q ? q.ToString("N2") : "0",
                            Price = r["unit_price"] is double pr ? pr.ToString("N2") : "0",
                            Total = r["total"] is double t ? t.ToString("N2") : "0"
                        });
                    }
                }
                dgItems.ItemsSource = items;
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error loading details: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private static string ItemName(Microsoft.Data.Sqlite.SqliteConnection conn, long itemId)
        {
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "SELECT Name FROM Master1 WHERE MasterType='Item' AND ServerId=@id LIMIT 1";
            cmd.Parameters.AddWithValue("@id", itemId);
            return cmd.ExecuteScalar()?.ToString() ?? "Item #" + itemId;
        }

        private void BtnEdit_Click(object sender, RoutedEventArgs e)
        {
            if (_isReturn)
                _dashboard?.ShowPage(new PurchaseReturnCreatePage(_dashboard!, _id));
            else
                _dashboard?.ShowPage(new PurchaseCreatePage(_dashboard!, _id));
            Close();
        }

        private void BtnPdf_Click(object sender, RoutedEventArgs e)
        {
            if (_isReturn) PdfService.GeneratePurchaseReturnPdf(_id);
            else PdfService.GeneratePurchasePdf(_id);
        }

        private void BtnPrint_Click(object sender, RoutedEventArgs e)
        {
            // Print = custom PDF viewer me kholo (temp file — koi Save dialog nahi).
            // Viewer ke andar hi print/zoom/save buttons hain.
            string temp = System.IO.Path.Combine(System.IO.Path.GetTempPath(), (_isReturn ? "PurchaseReturn_" : "Purchase_") + _id + "_" + DateTime.Now.Ticks + ".pdf");
            if (_isReturn) PdfService.GeneratePurchaseReturnPdf(_id, temp);
            else PdfService.GeneratePurchasePdf(_id, temp);
        }

        private void BtnClose_Click(object sender, RoutedEventArgs e) => Close();
    }
}
