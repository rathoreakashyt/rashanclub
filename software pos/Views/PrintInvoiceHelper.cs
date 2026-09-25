using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Documents;
using System.Windows.Media;
using RashanKiDukan.Database;
using QuestPDF.Fluent;
using QuestPDF.Helpers;
using QuestPDF.Infrastructure;

namespace RashanKiDukan.Views
{
    public static class PrintInvoiceHelper
    {
        public static void PrintReceipt(string vchCode, string date, List<BusyCartItem> items, double grandTotal, string paymentMode, string customerName = "Walk-in Customer", string firmName = "RASHAN KI DUKAN")
        {
            try
            {
                var printDialog = new PrintDialog();
                if (printDialog.ShowDialog() != true) return;

                var flowDoc = new FlowDocument
                {
                    PageWidth = 300,
                    PageHeight = 600
                };

                var paragraph = new Paragraph();
                paragraph.FontSize = 10;
                paragraph.FontFamily = new FontFamily("Consolas");

                AddLine(paragraph, "=========================================", 10, FontWeights.Bold, Brushes.Black);
                AddLine(paragraph, firmName, 14, FontWeights.Bold, Brushes.Black);
                AddLine(paragraph, "Your Daily Needs, Our Priority", 8, FontWeights.Normal, Brushes.Gray);
                AddLine(paragraph, "=========================================", 10, FontWeights.Bold, Brushes.Black);

                AddLine(paragraph, $"Invoice #: {vchCode}", 9, FontWeights.Normal, Brushes.Black);
                AddLine(paragraph, $"Date: {date}", 9, FontWeights.Normal, Brushes.Black);
                AddLine(paragraph, $"Customer: {customerName}", 9, FontWeights.Normal, Brushes.Black);
                AddLine(paragraph, $"Payment: {paymentMode}", 9, FontWeights.Normal, Brushes.Black);
                AddLine(paragraph, "-----------------------------------------", 9, FontWeights.Normal, Brushes.Black);

                AddLine(paragraph, "Item             Qty   Rate    Amount", 8, FontWeights.Bold, Brushes.Black);
                AddLine(paragraph, "-----------------------------------------", 9, FontWeights.Normal, Brushes.Black);

                foreach (var item in items)
                {
                    string name = item.ItemName.Length > 14 ? item.ItemName[..14] : item.ItemName.PadRight(14);
                    string line = $"{name} {item.Qty:N0,3} {item.Price:N2,7} {item.Amount:N2,8}";
                    AddLine(paragraph, line, 8, FontWeights.Normal, Brushes.Black);
                }

                AddLine(paragraph, "-----------------------------------------", 9, FontWeights.Normal, Brushes.Black);

                double subTotal = items.Sum(x => x.Price * x.Qty);
                double totalDisc = items.Sum(x => x.TotDisc);
                double totalTax = items.Sum(x => x.TaxableAmount * x.TaxPerc / 100.0);

                AddLine(paragraph, $"  Sub Total:           {subTotal:N2,10}", 9, FontWeights.Normal, Brushes.Black);
                if (totalDisc > 0)
                    AddLine(paragraph, $"  Discount:           -{totalDisc:N2,10}", 9, FontWeights.Normal, Brushes.Red);
                if (totalTax > 0)
                    AddLine(paragraph, $"  Tax (incl):          {totalTax:N2,10}", 9, FontWeights.Normal, Brushes.Black);

                AddLine(paragraph, "-----------------------------------------", 9, FontWeights.Normal, Brushes.Black);
                AddLine(paragraph, $"  GRAND TOTAL: Rs.{grandTotal:N2}", 11, FontWeights.Bold, Brushes.Black);
                AddLine(paragraph, "=========================================", 10, FontWeights.Bold, Brushes.Black);

                AddLine(paragraph, "");
                AddLine(paragraph, "Thank you for shopping with us!", 9, FontWeights.Normal, Brushes.Gray);
                AddLine(paragraph, "Visit Again!", 9, FontWeights.Bold, Brushes.Gray);

                flowDoc.Blocks.Add(paragraph);

                printDialog.PrintDocument(((IDocumentPaginatorSource)flowDoc).DocumentPaginator, $"Invoice - {vchCode}");
            }
            catch (Exception ex)
            {
                MessageBox.Show("Print error: " + ex.Message, "Print", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        /// <summary>
        /// Day Close / Shift Settlement (Z-Report) — 80mm thermal PDF,
        /// prompt ke format me. PdfPreviewWindow me dikhta hai.
        /// </summary>
        public static void PrintSettlementReceipt(ShiftSettlementService.SettlementData d, DateTime now)
        {
            QuestPDF.Settings.License = LicenseType.Community;

            string temp = Path.Combine(Path.GetTempPath(),
                "ShiftSettlement_" + DateTime.Now.Ticks + ".pdf");

            string sep = new string('=', 42);
            string line = new string('-', 42);
            string Row(string label, string value) => $"{(label + " ").PadRight(21, '.')} : {value}";

            Document.Create(container =>
            {
                container.Page(page =>
                {
                    page.Size(226.77f, 700f, Unit.Point);   // 80mm roll
                    page.Margin(8, Unit.Point);
                    page.DefaultTextStyle(t => t.FontFamily("Consolas").FontSize(8));

                    page.Content().Column(col =>
                    {
                        col.Item().AlignCenter().Text(sep).Bold();
                        col.Item().AlignCenter().Text("SHIFT / DAY CLOSE SUMMARY").Bold().FontSize(10);
                        col.Item().AlignCenter().Text(sep).Bold();
                        col.Item().Text($"Counter: {d.CounterName}  |  Cashier: {d.CashierName}");
                        col.Item().Text($"Date: {now:dd-MM-yyyy}   |  Time: {now:HH:mm:ss}");
                        col.Item().AlignCenter().Text(sep).Bold();

                        // ── SALES SECTION ──
                        col.Item().Text(Row("TOTAL BILLS", d.TotalBills.ToString()));
                        col.Item().Text(Row("CASH", d.TotalCashSales.ToString("0.00")));
                        col.Item().Text(Row("C.CRD AMOUNT", d.TotalCardSales.ToString("0.00")));
                        col.Item().Text(Row("C.CRD AMT ENT", d.CardEnteredAmount.ToString("0.00")));
                        col.Item().Text(Row("FREE SCHEME / SUGAR", d.FreeSchemeValue.ToString("0.00")));
                        col.Item().Text(Row("BILL AMOUNT", d.GrossBillAmount.ToString("0.00")));
                        col.Item().Text(Row("SALE RETURN ADJ", d.SaleReturnAdj.ToString("0.00")));
                        col.Item().Text(Row("SALE RETURN BY CASH", d.SaleReturnCash.ToString("0.00")));
                        col.Item().Text(Row("CASH AFT SRC", d.CashAfterReturn.ToString("0.00")));
                        col.Item().AlignCenter().Text(sep).Bold();
                        col.Item().Text(Row("TOTAL NET AMOUNT", d.NetTotalAmount.ToString("0.00"))).Bold();
                        col.Item().Text(Row("SALE RETURN TOBE", d.SaleReturnTobe.ToString("0.00")));
                        col.Item().AlignCenter().Text(sep).Bold();

                        // ── COUNTER / RECONCILIATION ──
                        col.Item().Text(Row("COUNTER SUMMARY", d.OpeningCashFloat.ToString("0.00")));
                        col.Item().Text(Row("SUBMITTED SUMMARY", d.CashierSubmittedCash.ToString("0.00")));
                        col.Item().Text(Row("ADVANCES", d.CashAdvances.ToString("0.00")));
                        col.Item().Text(Row("VOUCHERS", d.VoucherAmount.ToString("0.00")));
                        col.Item().Text(Row("SHORTAGE", d.ShortageAmount.ToString("0.00")))
                            .FontColor(d.ShortageAmount > 0 ? "#DC2626" : "#000000").Bold();
                        col.Item().Text(Row("EXCESS", d.ExcessAmount.ToString("0.00")))
                            .FontColor(d.ExcessAmount > 0 ? "#D97706" : "#000000").Bold();
                        col.Item().Text(Row("CARD ISSUED", d.LoyaltyCardsIssued.ToString()));
                        col.Item().Text(Row("CARD RENEWED", d.LoyaltyCardsRenewed.ToString()));
                        col.Item().AlignCenter().Text(sep).Bold();

                        col.Item().PaddingTop(10).Text("Cashier Signature: _______  Manager: _______");
                        col.Item().AlignCenter().Text(sep).Bold();
                    });
                });
            }).GeneratePdf(temp);

            PdfPreviewWindow.ShowPdf(temp, "Shift Settlement (Z-Report)");
        }

        public static void PrintShiftReceipt(
            string shiftType,       // "CLOSE SHIFT" or "CHANGE SHIFT"
            string cashierName,
            string counterName,
            DateTime shiftTime,
            DatabaseService db)
        {
            try
            {
                // Load company info from DB
                string firmName = "RASHAN KI DUKAN";
                string address1 = "", address2 = "", city = "", state = "", pinCode = "";
                string gstin = "", phone = "", email = "";

                try
                {
                    using var conn = db.GetConnection();
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = "SELECT Name,Address1,Address2,City,State,PinCode,GSTIN,Phone,Email FROM Company WHERE IsActive=1 LIMIT 1";
                    using var r = cmd.ExecuteReader();
                    if (r.Read())
                    {
                        firmName = r.IsDBNull(0) ? firmName : r.GetString(0);
                        address1 = r.IsDBNull(1) ? "" : r.GetString(1);
                        address2 = r.IsDBNull(2) ? "" : r.GetString(2);
                        city     = r.IsDBNull(3) ? "" : r.GetString(3);
                        state    = r.IsDBNull(4) ? "" : r.GetString(4);
                        pinCode  = r.IsDBNull(5) ? "" : r.GetString(5);
                        gstin    = r.IsDBNull(6) ? "" : r.GetString(6);
                        phone    = r.IsDBNull(7) ? "" : r.GetString(7);
                        email    = r.IsDBNull(8) ? "" : r.GetString(8);
                    }
                }
                catch { }

                // ═══ PDF (thermal 80mm) — existing PdfPreviewWindow me dikhata hai ═══
                // Direct PrintDialog ki jagah ab QuestPDF se 80mm receipt banti hai
                // aur user ke saamne PdfViewer khulta hai (print wahan se ho sakta hai).
                QuestPDF.Settings.License = LicenseType.Community;

                string sep = new string('=', 40);
                string line = new string('-', 40);

                string temp = Path.Combine(Path.GetTempPath(),
                    "Shift_" + shiftType.Replace(" ", "") + "_" + DateTime.Now.Ticks + ".pdf");

                Document.Create(container =>
                {
                    container.Page(page =>
                    {
                        // 80mm thermal roll ≈ 226.77pt width
                        page.Size(226.77f, 500f, Unit.Point);
                        page.Margin(8, Unit.Point);
                        page.DefaultTextStyle(t => t.FontFamily("Consolas").FontSize(8));

                        page.Content().Column(col =>
                        {
                            col.Item().AlignCenter().Text(sep).Bold();
                            col.Item().AlignCenter().Text(firmName.ToUpper()).Bold().FontSize(11);

                            if (!string.IsNullOrWhiteSpace(address1))
                                col.Item().AlignCenter().Text(address1);
                            if (!string.IsNullOrWhiteSpace(address2))
                                col.Item().AlignCenter().Text(address2);

                            string cityLine = string.Join(", ", new[] { city, state, pinCode }.Where(x => !string.IsNullOrWhiteSpace(x)));
                            if (!string.IsNullOrWhiteSpace(cityLine))
                                col.Item().AlignCenter().Text(cityLine);

                            if (!string.IsNullOrWhiteSpace(phone))
                                col.Item().AlignCenter().Text("Ph: " + phone);
                            if (!string.IsNullOrWhiteSpace(email))
                                col.Item().AlignCenter().Text(email);
                            if (!string.IsNullOrWhiteSpace(gstin))
                                col.Item().AlignCenter().Text("GSTIN: " + gstin);

                            col.Item().AlignCenter().Text(sep).Bold();
                            col.Item().AlignCenter().Text($"*** {shiftType} RECEIPT ***").Bold().FontSize(10);
                            col.Item().AlignCenter().Text(line);

                            // ── SHIFT DETAILS ──
                            col.Item().Text($"Date     : {shiftTime:dd-MMM-yyyy}");
                            col.Item().Text($"Time     : {shiftTime:hh:mm:ss tt}");
                            col.Item().Text($"Cashier  : {cashierName}");
                            col.Item().Text($"Counter  : {counterName}");
                            col.Item().AlignCenter().Text(line);

                            col.Item().AlignCenter().Text(sep).Bold();
                            col.Item().AlignCenter().Text("Thank You!").FontColor("#888888");
                            col.Item().AlignCenter().Text(sep).Bold();
                        });
                    });
                }).GeneratePdf(temp);

                Views.PdfPreviewWindow.ShowPdf(temp, shiftType + " Receipt");
            }
            catch (Exception ex)
            {
                System.Windows.MessageBox.Show("Print error: " + ex.Message, "Print", System.Windows.MessageBoxButton.OK, System.Windows.MessageBoxImage.Error);
            }
        }

        private static string CenterText(string text, int width)
        {
            if (text.Length >= width) return text;
            int totalPad = width - text.Length;
            int left  = totalPad / 2;
            return text.PadLeft(text.Length + left).PadRight(width);
        }

        private static void AddLine(Paragraph p, string text, double fontSize = 10, System.Windows.FontWeight weight = default, Brush? brush = null)
        {
            var run = new Run(text)
            {
                FontSize = fontSize,
                FontWeight = weight == default ? FontWeights.Normal : weight,
                Foreground = brush ?? Brushes.Black
            };
            p.Inlines.Add(run);
            p.Inlines.Add(new LineBreak());
        }

        public static void PrintToTextFile(string vchCode, string date, List<BusyCartItem> items, double grandTotal, string paymentMode, string filePath)
        {
            var sb = new System.Text.StringBuilder();
            sb.AppendLine("=========================================");
            sb.AppendLine("         RASHAN KI DUKAN");
            sb.AppendLine("   Your Daily Needs, Our Priority");
            sb.AppendLine("=========================================");
            sb.AppendLine();
            sb.AppendLine($"Invoice #: {vchCode}");
            sb.AppendLine($"Date: {date}");
            sb.AppendLine($"Payment: {paymentMode}");
            sb.AppendLine("-----------------------------------------");
            sb.AppendLine("Item              Qty   Rate    Amount");
            sb.AppendLine("-----------------------------------------");

            foreach (var item in items)
            {
                string name = item.ItemName.Length > 16 ? item.ItemName[..16] : item.ItemName.PadRight(16);
                sb.AppendLine($"{name} {item.Qty:N0,3} {item.Price:N2,6} {item.Amount:N2,8}");
            }

            sb.AppendLine("-----------------------------------------");
            double subTotal = items.Sum(x => x.Price * x.Qty);
            double totalDisc = items.Sum(x => x.TotDisc);
            sb.AppendLine($"{"",30}Sub Total: {subTotal:N2}");
            if (totalDisc > 0)
                sb.AppendLine($"{"",30}Discount:  -{totalDisc:N2}");
            sb.AppendLine("-----------------------------------------");
            sb.AppendLine($"         GRAND TOTAL: Rs.{grandTotal:N2}");
            sb.AppendLine("=========================================");
            sb.AppendLine();
            sb.AppendLine("    Thank you for shopping with us!");
            sb.AppendLine("         Visit Again!");

            File.WriteAllText(filePath, sb.ToString());
        }
    }
}
