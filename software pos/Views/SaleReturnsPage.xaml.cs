using System.IO;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Documents;
using System.Windows.Media;
using Microsoft.Data.Sqlite;
using Microsoft.Win32;
using QuestPDF.Fluent;
using QuestPDF.Helpers;
using QuestPDF.Infrastructure;
using RashanKiDukan.Database;
// QuestPDF ke saath namespace clash na ho — WPF ke types alias se resolve karo
using Color = System.Windows.Media.Color;
using FontWeight = System.Windows.FontWeight;

namespace RashanKiDukan.Views
{
    public partial class SaleReturnsPage : UserControl, ISyncRefreshable
    {
        private readonly DatabaseService _db = new DatabaseService();
        private readonly MainDashboard? _dashboard;
        private string _currentFilter = "All";
        private string _searchText = "";
        private int _serialNo = 0;

        public SaleReturnsPage() { InitializeComponent(); }

        public SaleReturnsPage(MainDashboard dashboard) : this()
        {
            _dashboard = dashboard;
            LoadReturns();
        }

        // Cloud/Web se change aaya (sync pull) → page khud reload — bina refresh ke
        public void OnSyncPulled()
        {
            Dispatcher.BeginInvoke(new Action(() =>
            {
                if (IsLoaded) LoadReturns();
            }), System.Windows.Threading.DispatcherPriority.Background);
        }

        private void LoadReturns()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();

                string where = "WHERE 1=1";
                if (_currentFilter == "Hold") where += " AND sr.del_status='Deleted'";

                if (!string.IsNullOrWhiteSpace(_searchText))
                {
                    where += $" AND (sr.reference_no LIKE '%{_searchText}%' OR COALESCE(c.name,'') LIKE '%{_searchText}%')";
                }

                cmd.CommandText = $@"SELECT sr.id, sr.reference_no,
                    COALESCE(c.name, 'Walk-in Customer') as customer,
                    s.invoice_no as sale_invoice,
                    sr.date,
                    sr.total_return_amount,
                    COALESCE(sr.paid, 0) as paid,
                    COALESCE(sr.due, 0) as due
                    FROM sale_returns sr
                    LEFT JOIN customers c ON sr.customer_id = c.id
                    LEFT JOIN sales s ON sr.sale_id = s.id
                    {where}
                    ORDER BY sr.id DESC";

                var list = new System.Collections.ObjectModel.ObservableCollection<SaleReturnItem>();
                _serialNo = 0;
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    _serialNo++;
                    double totalAmt = r["total_return_amount"] == DBNull.Value ? 0 : Convert.ToDouble(r["total_return_amount"]);
                    double paid = r["paid"] == DBNull.Value ? 0 : Convert.ToDouble(r["paid"]);
                    double due = r["due"] == DBNull.Value ? 0 : Convert.ToDouble(r["due"]);
                    string dateStr = r["date"]?.ToString() ?? "";
                    if (DateTime.TryParse(dateStr, out var dt))
                        dateStr = dt.ToString("dd/MM/yyyy");

                    list.Add(new SaleReturnItem
                    {
                        Id = r["id"]?.ToString() ?? "",
                        SN = _serialNo.ToString(),
                        RefNo = r["reference_no"]?.ToString() ?? "",
                        Customer = r["customer"]?.ToString() ?? "Walk-in Customer",
                        SaleInvoice = r["sale_invoice"]?.ToString() ?? "N/A",
                        Date = dateStr,
                        TotalReturnAmount = $"INR{totalAmt:N2}",
                        Paid = $"INR{paid:N2}",
                        Due = $"INR{due:N2}"
                    });
                }

                itemsList.ItemsSource = list;
                emptyState.Visibility = list.Count == 0 ? Visibility.Visible : Visibility.Collapsed;
                itemsList.Visibility = list.Count == 0 ? Visibility.Collapsed : Visibility.Visible;

                using var cAll = conn.CreateCommand();
                cAll.CommandText = "SELECT COUNT(*) FROM sale_returns WHERE del_status IS NOT 'Deleted'";
                tabAll.Content = $"All ({Convert.ToInt64(cAll.ExecuteScalar())})";

                tabNewRef.Content = "New Ref (0)";
                tabAdjusted.Content = "Adjusted (0)";
                tabOnAcc.Content = "On Acc (0)";

                using var cHold = conn.CreateCommand();
                cHold.CommandText = "SELECT COUNT(*) FROM sale_returns WHERE del_status='Deleted'";
                tabHold.Content = $"Hold ({Convert.ToInt64(cHold.ExecuteScalar())})";

                using var cTotal = conn.CreateCommand();
                cTotal.CommandText = "SELECT IFNULL(SUM(total_return_amount),0) FROM sale_returns WHERE del_status IS NOT 'Deleted'";
                lblTotalAmount.Text = $"INR {Convert.ToDouble(cTotal.ExecuteScalar()):N2}";
                lblTotal.Text = $"Total: {list.Count} returns";
            }
            catch { }
        }

        private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e)
        {
            _searchText = txtSearch.Text.Trim();
            LoadReturns();
        }

        private void Tab_Click(object sender, RoutedEventArgs e)
        {
            if (tabAll.IsChecked == true) _currentFilter = "All";
            else if (tabNewRef.IsChecked == true) _currentFilter = "New Ref";
            else if (tabAdjusted.IsChecked == true) _currentFilter = "Adjusted";
            else if (tabOnAcc.IsChecked == true) _currentFilter = "On Acc";
            else if (tabHold.IsChecked == true) _currentFilter = "Hold";
            LoadReturns();
        }

        private void Row_MouseEnter(object sender, System.Windows.Input.MouseEventArgs e)
        {
            if (sender is Border bd) bd.Background = new SolidColorBrush(Color.FromRgb(0xF9, 0xFA, 0xFB));
        }

        private void Row_MouseLeave(object sender, System.Windows.Input.MouseEventArgs e)
        {
            if (sender is Border bd) bd.Background = Brushes.White;
        }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowDashboard();
        }

        private void BtnAddReturn_Click(object sender, RoutedEventArgs e)
        {
            try
            {
                _dashboard?.ShowPage(new SaleReturnCreatePage(_dashboard));
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error opening Sale Return: " + ex.Message, "Rashan Ki Dukan",
                    MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void BtnPrint_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is string id && long.TryParse(id, out long rid))
            {
                // Print = custom PDF viewer me kholo (temp file me bana ke, koi Save dialog nahi).
                // Viewer ke andar hi Print / zoom / save buttons hain.
                string temp = System.IO.Path.Combine(System.IO.Path.GetTempPath(), "SaleReturn_" + rid + "_" + DateTime.Now.Ticks + ".pdf");
                GenerateSaleReturnPdf(rid, temp);
            }
        }

        private void BtnDownload_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is string id && long.TryParse(id, out long rid))
                GenerateSaleReturnPdf(rid, null);
        }

        private void BtnView_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is string id)
            {
                try
                {
                    if (long.TryParse(id, out long rid))
                    {
                        string temp = System.IO.Path.Combine(System.IO.Path.GetTempPath(), "SaleReturn_" + Guid.NewGuid().ToString("N") + ".pdf");
                        GenerateSaleReturnPdf(rid, temp);
                    }
                }
                catch (Exception ex)
                {
                    MessageBox.Show("Error viewing return: " + ex.Message, "Rashan Ki Dukan",
                        MessageBoxButton.OK, MessageBoxImage.Error);
                }
            }
        }

        private void BtnEdit_Click(object sender, RoutedEventArgs e)
        {
            MessageBox.Show("Editing an existing sale return is not supported yet.\nDelete it and create a new one.",
                "Rashan Ki Dukan", MessageBoxButton.OK, MessageBoxImage.Information);
        }

        private void BtnDelete_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is string id)
            {
                var result = MessageBox.Show("Delete this sale return?", "Confirm Delete",
                    MessageBoxButton.YesNo, MessageBoxImage.Warning);
                if (result == MessageBoxResult.Yes)
                {
                    try
                    {
                        using var conn = _db.GetConnection();
                        using var cmd = conn.CreateCommand();
                        cmd.CommandText = "UPDATE sale_returns SET del_status='Deleted' WHERE id=@id";
                        cmd.Parameters.AddWithValue("@id", int.Parse(id));
                        cmd.ExecuteNonQuery();
                        Services.SyncService.EnqueueSync("sale_returns", long.Parse(id), "delete");
                        _dashboard?.TriggerSync();
                        LoadReturns();
                    }
                    catch { }
                }
            }
        }

        // ═══════════ SALE RETURN: PRINT / PDF / PREVIEW ═══════════

        private (string refNo, string date, string customer, string saleInvoice, string note, double total)? LoadReturnHeader(long id)
        {
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = @"SELECT sr.reference_no, sr.date, sr.note, sr.total_return_amount,
                                       COALESCE(c.name, 'Walk-in Customer') as customer,
                                       COALESCE(s.invoice_no, '') as sale_invoice
                                FROM sale_returns sr
                                LEFT JOIN customers c ON sr.customer_id = c.id
                                LEFT JOIN sales s ON sr.sale_id = s.id
                                WHERE sr.id=@id";
            cmd.Parameters.AddWithValue("@id", id);
            using var r = cmd.ExecuteReader();
            if (!r.Read()) return null;

            string dateStr = r["date"]?.ToString() ?? "";
            if (DateTime.TryParse(dateStr, out var dt)) dateStr = dt.ToString("dd/MM/yyyy");
            double total = r["total_return_amount"] == DBNull.Value ? 0 : Convert.ToDouble(r["total_return_amount"]);

            return (
                r["reference_no"]?.ToString() ?? "",
                dateStr,
                r["customer"]?.ToString() ?? "Walk-in Customer",
                r["sale_invoice"]?.ToString() ?? "",
                r["note"]?.ToString() ?? "",
                total);
        }

        private List<(string Name, double Qty, double Price, double Total)> LoadReturnItems(long id)
        {
            var list = new List<(string Name, double Qty, double Price, double Total)>();
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = @"SELECT COALESCE(i.name, 'Item #' || d.item_id) as name,
                                       d.return_quantity_amount, d.unit_price_in_return
                                FROM sale_return_details d
                                LEFT JOIN items i ON d.item_id = i.id
                                WHERE d.sale_return_id=@id
                                ORDER BY d.id";
            cmd.Parameters.AddWithValue("@id", id);
            using var r = cmd.ExecuteReader();
            while (r.Read())
            {
                double qty = r["return_quantity_amount"] == DBNull.Value ? 0 : Convert.ToDouble(r["return_quantity_amount"]);
                double price = r["unit_price_in_return"] == DBNull.Value ? 0 : Convert.ToDouble(r["unit_price_in_return"]);
                string name = r["name"]?.ToString() ?? "Item";
                list.Add((name, qty, price, qty * price));
            }
            return list;
        }

        /// <summary>Sale return ki PDF banao. savePath = download/preview path, null = Save dialog.</summary>
        private bool GenerateSaleReturnPdf(long id, string? savePath)
        {
            var header = LoadReturnHeader(id);
            if (header == null)
            {
                MessageBox.Show("Sale return not found.", "Error", MessageBoxButton.OK, MessageBoxImage.Warning);
                return false;
            }
            var items = LoadReturnItems(id);

            try
            {
                string safeName = "Sale Return " + header.Value.refNo + ".pdf";
                foreach (char c in Path.GetInvalidFileNameChars()) safeName = safeName.Replace(c, '_');

                if (savePath == null)
                {
                    var dlg = new SaveFileDialog
                    {
                        FileName = safeName,
                        Filter = "PDF files (*.pdf)|*.pdf",
                        DefaultExt = "pdf",
                        Title = "Download Sale Return PDF"
                    };
                    if (dlg.ShowDialog() != true) return false;
                    savePath = dlg.FileName;
                }

                using (var fs = File.Create(savePath))
                {
                    Document.Create(container =>
                    {
                        container.Page(page =>
                        {
                            page.Size(PageSizes.A4);
                            page.Margin(36);
                            page.DefaultTextStyle(x => x.FontSize(10).FontColor("#111827"));
                            page.Header().Column(col =>
                            {
                                col.Item().Row(row =>
                                {
                                    row.RelativeItem().Text("RASHAN KI DUKAN").FontSize(18).Bold().FontColor("#111827");
                                    row.RelativeItem().AlignRight().Text("SALE RETURN").FontSize(14).Bold().FontColor("#4F46E5");
                                });
                                col.Item().PaddingTop(4).Row(row =>
                                {
                                    row.RelativeItem().Text("Date: " + header.Value.date).FontSize(10);
                                    row.RelativeItem().AlignRight().Text("Ref: " + header.Value.refNo).FontSize(10);
                                });
                                col.Item().Row(row =>
                                {
                                    row.RelativeItem().Text("Customer: " + header.Value.customer).FontSize(10);
                                    row.RelativeItem().AlignRight().Text("Invoice: " + header.Value.saleInvoice).FontSize(10);
                                });
                                col.Item().PaddingTop(8).LineHorizontal(1).LineColor("#E2E8F0");
                            });
                            page.Content().PaddingTop(12).Column(col =>
                            {
                                col.Item().Table(table =>
                                {
                                    table.ColumnsDefinition(c =>
                                    {
                                        c.RelativeColumn(1);
                                        c.RelativeColumn(3);
                                        c.RelativeColumn(2);
                                        c.RelativeColumn(2);
                                        c.RelativeColumn(2);
                                    });
                                    table.Header(h =>
                                    {
                                        h.Cell().Background("#EEF2FF").Padding(6).Text("#").Bold().FontColor("#4F46E5");
                                        h.Cell().Background("#EEF2FF").Padding(6).Text("ITEM").Bold().FontColor("#4F46E5");
                                        h.Cell().Background("#EEF2FF").Padding(6).Text("QUANTITY").Bold().FontColor("#4F46E5");
                                        h.Cell().Background("#EEF2FF").Padding(6).Text("UNIT PRICE").Bold().FontColor("#4F46E5");
                                        h.Cell().Background("#EEF2FF").Padding(6).AlignRight().Text("TOTAL").Bold().FontColor("#4F46E5");
                                    });
                                    int n = 1;
                                    foreach (var it in items)
                                    {
                                        table.Cell().Padding(6).Text(n.ToString());
                                        table.Cell().Padding(6).Text(it.Name);
                                        table.Cell().Padding(6).Text(it.Qty.ToString("N2"));
                                        table.Cell().Padding(6).Text(it.Price.ToString("N2"));
                                        table.Cell().Padding(6).AlignRight().Text(it.Total.ToString("N2"));
                                        n++;
                                    }
                                });
                                col.Item().PaddingTop(14).AlignRight().Column(sum =>
                                {
                                    sum.Item().Row(r => { r.RelativeItem().Text("Total Return Amount").SemiBold(); r.ConstantItem(90).AlignRight().Text(header.Value.total.ToString("N2")).SemiBold(); });
                                });
                                if (!string.IsNullOrEmpty(header.Value.note))
                                {
                                    col.Item().PaddingTop(14).Text("Note: " + header.Value.note).FontSize(9).FontColor("#64748B");
                                }
                            });
                            page.Footer().PaddingTop(10).AlignCenter().Text("Generated by Rashan Ki Dukan POS").FontSize(8).FontColor("#94A3B8");
                        });
                    }).GeneratePdf(fs);
                }

                PdfPreviewWindow.ShowPdf(savePath, "Sale Return - " + header.Value.refNo);
                return true;
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error generating PDF: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
                return false;
            }
        }
    }

    public class SaleReturnItem
    {
        public string Id { get; set; } = "";
        public string SN { get; set; } = "";
        public string RefNo { get; set; } = "";
        public string Customer { get; set; } = "";
        public string SaleInvoice { get; set; } = "";
        public string Date { get; set; } = "";
        public string TotalReturnAmount { get; set; } = "";
        public string Paid { get; set; } = "";
        public string Due { get; set; } = "";
    }
}
