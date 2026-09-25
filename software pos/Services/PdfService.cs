using System;
using System.Collections.Generic;
using System.IO;
using System.Windows;
using Microsoft.Win32;
using RashanKiDukan.Database;
using RashanKiDukan.Views;
using QuestPDF.Fluent;
using QuestPDF.Helpers;
using QuestPDF.Infrastructure;

namespace RashanKiDukan.Services
{
    public static class PdfService
    {
        static PdfService()
        {
            QuestPDF.Settings.License = LicenseType.Community;
            // Hindi (Devanagari) store name ke liye Windows Nirmala UI font
            try
            {
                var fontPath = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.Fonts), "Nirmala.ttf");
                if (File.Exists(fontPath))
                    QuestPDF.Drawing.FontManager.RegisterFont(File.OpenRead(fontPath));
            }
            catch { }
        }

        private static string CompanyName(DatabaseService db)
        {
            try
            {
                using var conn = db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT COALESCE(NULLIF(name,''), NULLIF(business_name,'')) FROM companies LIMIT 1";
                var v = cmd.ExecuteScalar();
                if (v != null && v.ToString() != "") return v.ToString()!;
            }
            catch { }
            try
            {
                using var conn = db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT Name FROM Company LIMIT 1";
                var v = cmd.ExecuteScalar();
                if (v != null && v.ToString() != "") return v.ToString()!;
            }
            catch { }
            return "Rashan Ki Dukan";
        }

        /// <summary>companies table se full company header info (name/address/phone/GSTIN/email/logo).</summary>
        private static (string Name, string Address, string Phone, string Gstin, string Email, string LogoFile, bool LogoShow) CompanyDetails(DatabaseService db)
        {
            try
            {
                using var conn = db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT COALESCE(NULLIF(name,''), NULLIF(business_name,''), '') AS name,
                                           COALESCE(NULLIF(address,''),'') AS address,
                                           COALESCE(NULLIF(phone,''),'') AS phone,
                                           COALESCE(NULLIF(tax_registration_no,''),'') AS gstin,
                                           COALESCE(NULLIF(email,''),'') AS email,
                                           COALESCE(NULLIF(invoice_logo,''),'') AS logo,
                                           COALESCE(NULLIF(inv_logo_is_show,'Yes'),'Yes') AS logo_show
                                    FROM companies LIMIT 1";
                using var r = cmd.ExecuteReader();
                if (r.Read())
                    return (r["name"]?.ToString() ?? "", r["address"]?.ToString() ?? "", r["phone"]?.ToString() ?? "",
                            r["gstin"]?.ToString() ?? "", r["email"]?.ToString() ?? "", r["logo"]?.ToString() ?? "",
                            r["logo_show"]?.ToString() != "No");
            }
            catch { }
            return (CompanyName(db), "", "", "", "", "", false);
        }

        /// <summary>Cloud se downloaded invoice logo ka local path (AppData\RashanKiDukan\logos).</summary>
        private static string InvoiceLogoPath(string logoFile)
        {
            if (string.IsNullOrEmpty(logoFile)) return "";
            var logosDir = Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                "RashanKiDukan", "logos");
            var path = Path.Combine(logosDir, Path.GetFileName(logoFile));
            return File.Exists(path) ? path : "";
        }

        /// <summary>A4 bill headers (name left + title right) ke neeche company ki sari
        /// detail: address, phone, GSTIN — taaki bill ke upar kuch miss na ho.</summary>
        private static void WriteCompanyHeader(QuestPDF.Fluent.ColumnDescriptor col, DatabaseService db, string title)
        {
            var (name, address, phone, gstin, _, _, _) = CompanyDetails(db);
            col.Item().Row(row =>
            {
                row.RelativeItem().Text(name).FontSize(18).Bold().FontColor("#111827");
                row.RelativeItem().AlignRight().Text(title).FontSize(14).Bold().FontColor("#4F46E5");
            });
            if (!string.IsNullOrEmpty(address))
                col.Item().PaddingTop(2).Text(address).FontSize(9).FontColor("#475569");
            if (!string.IsNullOrEmpty(phone))
                col.Item().Text("Phone: " + phone).FontSize(9).FontColor("#475569");
            if (!string.IsNullOrEmpty(gstin))
                col.Item().PaddingTop(1).Text("GSTIN: " + gstin).FontSize(9).Bold().FontColor("#111827");
        }

        private static string PartyName(DatabaseService db, long supplierId)
        {
            try
            {
                using var conn = db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT COALESCE((SELECT Name FROM Master1 WHERE MasterType='Party' AND ServerId=@id LIMIT 1),
                                    (SELECT Name FROM Suppliers WHERE Id=@id LIMIT 1), '')";
                cmd.Parameters.AddWithValue("@id", supplierId);
                return cmd.ExecuteScalar()?.ToString() ?? "";
            }
            catch { return ""; }
        }

        private static string ItemName(DatabaseService db, long itemId)
        {
            try
            {
                using var conn = db.GetConnection();
                using var cmd = conn.CreateCommand();
                // Check if it's a variation item (has parent_id) and prepend parent name
                cmd.CommandText = @"SELECT i.name, 
                    CASE WHEN i.parent_id IS NOT NULL AND i.parent_id > 0 
                         THEN (SELECT p.name FROM items p WHERE p.id = i.parent_id LIMIT 1)
                         ELSE NULL END AS parent_name
                    FROM items i WHERE i.id=@id LIMIT 1";
                cmd.Parameters.AddWithValue("@id", itemId);
                using var r = cmd.ExecuteReader();
                if (r.Read())
                {
                    string name = r["name"]?.ToString() ?? "";
                    string parentName = r["parent_name"]?.ToString() ?? "";
                    if (!string.IsNullOrEmpty(parentName))
                        return parentName + " - " + name;
                    if (!string.IsNullOrEmpty(name))
                        return name;
                }
                // Fallback to Master1
                using var cmd2 = conn.CreateCommand();
                cmd2.CommandText = "SELECT Name FROM Master1 WHERE MasterType='Item' AND ServerId=@id LIMIT 1";
                cmd2.Parameters.AddWithValue("@id", itemId);
                return cmd2.ExecuteScalar()?.ToString() ?? "Item #" + itemId;
            }
            catch { return "Item #" + itemId; }
        }

        private static bool SavePdf(string suggestedName, Action<Stream> writer, string? savePath = null)
        {
            if (savePath == null)
            {
                var dlg = new SaveFileDialog
                {
                    FileName = suggestedName,
                    Filter = "PDF files (*.pdf)|*.pdf",
                    DefaultExt = "pdf",
                    Title = "Download PDF"
                };
                if (dlg.ShowDialog() != true) return false;
                savePath = dlg.FileName;
            }
            try
            {
                using (var fs = File.Create(savePath))
                {
                    writer(fs);
                }
                // Bill ko in-app previewer me kholo (print/zoom/save wahin se)
                PdfPreviewWindow.ShowPdf(savePath);
                return true;
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error saving PDF: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
                return false;
            }
        }

        public static bool GeneratePurchasePdf(long purchaseId, string? savePath = null)
        {
            var db = new DatabaseService();
            string refNo = "";
            string date = "";
            string invoiceNo = "";
            string note = "";
            string supplier = "";
            double grand = 0, paid = 0, due = 0, disc = 0;
            var items = new List<(string Name, string Qty, string Price, string Total)>();
            try
            {
                using var conn = db.GetConnection();
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT reference_no, date, invoice_no, note, grand_total, paid, due_amount, discount, supplier_id FROM purchases WHERE Id=@id";
                    cmd.Parameters.AddWithValue("@id", purchaseId);
                    using var r = cmd.ExecuteReader();
                    if (!r.Read()) { MessageBox.Show("Purchase not found.", "Error", MessageBoxButton.OK, MessageBoxImage.Warning); return false; }
                    refNo = r["reference_no"]?.ToString() ?? "";
                    date = r["date"]?.ToString() ?? "";
                    invoiceNo = r["invoice_no"]?.ToString() ?? "";
                    note = r["note"]?.ToString() ?? "";
                    grand = r["grand_total"] is double g ? g : 0;
                    paid = r["paid"] is double p ? p : 0;
                    due = r["due_amount"] is double d ? d : 0;
                    supplier = PartyName(db, r["supplier_id"] is long sid ? sid : 0);
                }
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT item_id, quantity_amount, unit_price, total FROM purchase_details WHERE purchase_id=@id";
                    cmd.Parameters.AddWithValue("@id", purchaseId);
                    using var r = cmd.ExecuteReader();
                    int i = 1;
                    while (r.Read())
                    {
                        long itemId = r["item_id"] is long iid ? iid : 0;
                        items.Add((ItemName(db, itemId),
                                   r["quantity_amount"] is double q ? q.ToString("N2") : "0",
                                   r["unit_price"] is double pr ? pr.ToString("N2") : "0",
                                   r["total"] is double t ? t.ToString("N2") : "0"));
                        i++;
                    }
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error reading purchase: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
                return false;
            }

            return SavePdf(("Purchase " + refNo + ".pdf").ReplaceInvalidFileNameChars(), stream =>
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
                            WriteCompanyHeader(col, db, "PURCHASE INVOICE");
                            col.Item().PaddingTop(4).Row(row =>
                            {
                                row.RelativeItem().Text("Date: " + date).FontSize(10);
                                row.RelativeItem().AlignRight().Text("Ref: " + refNo).FontSize(10);
                            });
                            col.Item().Row(row =>
                            {
                                row.RelativeItem().Text("Supplier: " + supplier).FontSize(10);
                                row.RelativeItem().AlignRight().Text("Invoice No: " + invoiceNo).FontSize(10);
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
                                    c.RelativeColumn(2);
                                    c.RelativeColumn(3);
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
                                    table.Cell().Padding(6).Text(it.Qty);
                                    table.Cell().Padding(6).Text(it.Price);
                                    table.Cell().Padding(6).AlignRight().Text(it.Total);
                                    n++;
                                }
                            });
                            col.Item().PaddingTop(14).AlignRight().Column(sum =>
                            {
                                sum.Item().Row(r => { r.RelativeItem().Text("Grand Total").SemiBold(); r.ConstantItem(90).AlignRight().Text(grand.ToString("N2")).SemiBold(); });
                                if (disc > 0)
                                    sum.Item().Row(r => { r.RelativeItem().Text("Discount"); r.ConstantItem(90).AlignRight().Text(disc.ToString("N2")); });
                                sum.Item().Row(r => { r.RelativeItem().Text("Paid Amount"); r.ConstantItem(90).AlignRight().Text(paid.ToString("N2")); });
                                sum.Item().Row(r => { r.RelativeItem().Text("Due Amount").Bold().FontColor("#DC2626"); r.ConstantItem(90).AlignRight().Text(due.ToString("N2")).Bold().FontColor("#DC2626"); });
                            });
                            if (!string.IsNullOrEmpty(note))
                            {
                                col.Item().PaddingTop(14).Text("Note: " + note).FontSize(9).FontColor("#64748B");
                            }
                        });
                        page.Footer().PaddingTop(10).AlignCenter().Text("Generated by Rashan Ki Dukan POS").FontSize(8).FontColor("#94A3B8");
                    });
                }).GeneratePdf(stream);
            }, savePath);
        }

        public static bool GeneratePurchaseReturnPdf(long returnId, string? savePath = null)
        {
            var db = new DatabaseService();
            string refNo = "", date = "", purDate = "", note = "", supplier = "", status = "";
            double total = 0;
            var items = new List<(string Name, string Qty, string Price, string Total)>();
            try
            {
                using var conn = db.GetConnection();
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT reference_no, date, purchase_date, note, return_status, total_return_amount, supplier_id FROM purchase_returns WHERE Id=@id";
                    cmd.Parameters.AddWithValue("@id", returnId);
                    using var r = cmd.ExecuteReader();
                    if (!r.Read()) { MessageBox.Show("Purchase return not found.", "Error", MessageBoxButton.OK, MessageBoxImage.Warning); return false; }
                    refNo = r["reference_no"]?.ToString() ?? "";
                    date = r["date"]?.ToString() ?? "";
                    purDate = r["purchase_date"]?.ToString() ?? "";
                    note = r["note"]?.ToString() ?? "";
                    status = r["return_status"]?.ToString() ?? "";
                    total = r["total_return_amount"] is double d ? d : 0;
                    supplier = PartyName(db, r["supplier_id"] is long sid ? sid : 0);
                }
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT item_id, return_quantity_amount, unit_price, total FROM purchase_return_details WHERE pur_return_id=@id";
                    cmd.Parameters.AddWithValue("@id", returnId);
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        long itemId = r["item_id"] is long iid ? iid : 0;
                        items.Add((ItemName(db, itemId),
                                   r["return_quantity_amount"] is double q ? q.ToString("N2") : "0",
                                   r["unit_price"] is double pr ? pr.ToString("N2") : "0",
                                   r["total"] is double t ? t.ToString("N2") : "0"));
                    }
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error reading purchase return: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
                return false;
            }

            return SavePdf(("Purchase Return " + refNo + ".pdf").ReplaceInvalidFileNameChars(), stream =>
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
                            WriteCompanyHeader(col, db, "PURCHASE RETURN");
                            col.Item().PaddingTop(4).Row(row =>
                            {
                                row.RelativeItem().Text("Date: " + date).FontSize(10);
                                row.RelativeItem().AlignRight().Text("Ref: " + refNo).FontSize(10);
                            });
                            col.Item().Row(row =>
                            {
                                row.RelativeItem().Text("Supplier: " + supplier).FontSize(10);
                                row.RelativeItem().AlignRight().Text("Purchase Date: " + purDate).FontSize(10);
                            });
                            col.Item().Row(row =>
                            {
                                row.RelativeItem().Text("Status: " + status).FontSize(10);
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
                                    c.RelativeColumn(2);
                                    c.RelativeColumn(3);
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
                                    table.Cell().Padding(6).Text(it.Qty);
                                    table.Cell().Padding(6).Text(it.Price);
                                    table.Cell().Padding(6).AlignRight().Text(it.Total);
                                    n++;
                                }
                            });
                            col.Item().PaddingTop(14).AlignRight().Column(sum =>
                            {
                                sum.Item().Row(r => { r.RelativeItem().Text("Total Return Amount").SemiBold(); r.ConstantItem(90).AlignRight().Text(total.ToString("N2")).SemiBold(); });
                            });
                            if (!string.IsNullOrEmpty(note))
                            {
                                col.Item().PaddingTop(14).Text("Note: " + note).FontSize(9).FontColor("#64748B");
                            }
                        });
                        page.Footer().PaddingTop(10).AlignCenter().Text("Generated by Rashan Ki Dukan POS").FontSize(8).FontColor("#94A3B8");
                    });
                }).GeneratePdf(stream);
            }, savePath);
        }

        /// <summary>
        /// Tran1 (header) + Tran2 (items) se generic voucher PDF — sale invoices
        /// (VchType='Sales') aur purchase returns (VchType='PurchaseReturn') dono ke liye.
        /// savePath null = Save dialog; non-null = seedha file + in-app PDF viewer.
        /// </summary>
        public static bool GenerateTranPdf(string vchCode, string? savePath = null, string title = "VOUCHER")
        {
            var db = new DatabaseService();
            string vchNo = "", date = "", party = "";
            double amount = 0;
            var items = new List<(string Name, string Qty, string Price, string Total)>();
            try
            {
                using var conn = db.GetConnection();
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"SELECT t.VchCode, t.VchNo, t.VchDate, t.Amount,
                                               COALESCE(m.Name,'') AS Party
                                        FROM Tran1 t LEFT JOIN Master1 m ON t.MasterCode1=m.Code
                                        WHERE t.VchCode=@c LIMIT 1";
                    cmd.Parameters.AddWithValue("@c", vchCode);
                    using var r = cmd.ExecuteReader();
                    if (!r.Read())
                    {
                        MessageBox.Show("Voucher not found: " + vchCode, "Error", MessageBoxButton.OK, MessageBoxImage.Warning);
                        return false;
                    }
                    vchNo = r["VchNo"]?.ToString() ?? vchCode;
                    date = r["VchDate"]?.ToString() ?? "";
                    party = r["Party"]?.ToString() ?? "";
                    amount = r["Amount"] is double a ? a : 0;
                }
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"SELECT SrNo, Description, Quantity, Rate, Amount - IFNULL(DiscountAmount,0) AS Amount
                                        FROM Tran2 WHERE VchCode=@c ORDER BY SrNo";
                    cmd.Parameters.AddWithValue("@c", vchCode);
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        items.Add((
                            r["Description"]?.ToString() ?? "",
                            r["Quantity"] is double q ? q.ToString("N2") : "0",
                            r["Rate"] is double pr ? pr.ToString("N2") : "0",
                            r["Amount"] is double t ? t.ToString("N2") : "0"));
                    }
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error reading voucher: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
                return false;
            }

            return SavePdf(("Voucher " + vchCode + ".pdf").ReplaceInvalidFileNameChars(), stream =>
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
                            WriteCompanyHeader(col, db, title);
                            col.Item().PaddingTop(4).Row(row =>
                            {
                                row.RelativeItem().Text("Date: " + date).FontSize(10);
                                row.RelativeItem().AlignRight().Text("Vch No: " + vchNo).FontSize(10);
                            });
                            col.Item().Row(row => row.RelativeItem().Text("Party: " + party).FontSize(10));
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
                                    h.Cell().Background("#EEF2FF").Padding(6).Text("RATE").Bold().FontColor("#4F46E5");
                                    h.Cell().Background("#EEF2FF").Padding(6).AlignRight().Text("AMOUNT").Bold().FontColor("#4F46E5");
                                });
                                int n = 1;
                                foreach (var it in items)
                                {
                                    table.Cell().Padding(6).Text(n.ToString());
                                    table.Cell().Padding(6).Text(it.Name);
                                    table.Cell().Padding(6).Text(it.Qty);
                                    table.Cell().Padding(6).Text(it.Price);
                                    table.Cell().Padding(6).AlignRight().Text(it.Total);
                                    n++;
                                }
                            });
                            col.Item().PaddingTop(14).AlignRight().Column(sum =>
                            {
                                sum.Item().Row(r => { r.RelativeItem().Text("Total Amount").SemiBold(); r.ConstantItem(90).AlignRight().Text(amount.ToString("N2")).SemiBold(); });
                            });
                        });
                        page.Footer().PaddingTop(10).AlignCenter().Text("Generated by Rashan Ki Dukan POS").FontSize(8).FontColor("#94A3B8");
                    });
                }).GeneratePdf(stream);
            }, savePath);
        }

        /// <summary>
        /// POS sales table se sale invoice PDF (Sales list / SaleListDialog ke liye).
        /// savePath null = Save dialog; non-null = seedha file + in-app PDF viewer.
        /// </summary>
        public static bool GenerateSalePdf(long saleId, string? savePath = null)
        {
            var db = new DatabaseService();
            string invoiceNo = "", date = "", time = "", customer = "", customerPhone = "", userName = "";
            string companyName = "", companyAddress = "", companyPhone = "", gstin = "", companyEmail = "", invoiceLogoFile = "";
            bool logoShow = false;
            double subTotal = 0, paid = 0, due = 0, vat = 0, disc = 0, grandTotal = 0;
            double mrpTotal = 0, savings = 0, givenAmount = 0, changeAmount = 0, rounding = 0;
            long userId = 0, custId = 0;
            string paymentMode = "Cash";
            int totalItems = 0;
            double totalQty = 0;
            double gramEarned = 0, gramRedeemed = 0, gramAvailable = 0;
            var items = new List<(string Name, string Hsn, double Qty, double Rate, double Mrp,
                double SellingPrice, double Disc, double GstPerc, double TaxAmt, double Amount, double Loyalty)>();
            try
            {
                using var conn = db.GetConnection();

                // Company info
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"SELECT COALESCE(NULLIF(name,''), NULLIF(business_name,''), '') AS name,
                                               COALESCE(NULLIF(address,''),'') AS address,
                                               COALESCE(NULLIF(phone,''),'') AS phone,
                                               COALESCE(NULLIF(tax_registration_no,''),'') AS gstin,
                                               COALESCE(NULLIF(email,''),'') AS email,
                                               COALESCE(NULLIF(invoice_logo,''),'') AS logo,
                                               COALESCE(NULLIF(inv_logo_is_show,'Yes'),'Yes') AS logo_show
                                        FROM companies LIMIT 1";
                    using var r = cmd.ExecuteReader();
                    if (r.Read())
                    {
                        companyName = r["name"]?.ToString() ?? "Rashan Ki Dukan";
                        companyAddress = r["address"]?.ToString() ?? "";
                        companyPhone = r["phone"]?.ToString() ?? "";
                        gstin = r["gstin"]?.ToString() ?? "";
                        companyEmail = r["email"]?.ToString() ?? "";
                        invoiceLogoFile = r["logo"]?.ToString() ?? "";
                        logoShow = r["logo_show"]?.ToString() != "No";
                    }
                }

                // Sale header
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"SELECT s.invoice_no, s.sale_date, s.date_time, s.note,
                                               IFNULL(s.sub_total,0) AS sub_total,
                                               IFNULL(s.total_payable, s.grand_total) AS grand_total,
                                               IFNULL(s.paid_amount,0) AS paid_amount,
                                               IFNULL(s.due_amount,0) AS due_amount,
                                               IFNULL(s.vat,0) AS vat,
                                               IFNULL(s.total_discount_amount, IFNULL(s.disc,0)) AS disc,
                                               IFNULL(s.mrp_total,0) AS mrp_total,
                                               IFNULL(s.savings,0) AS savings,
                                               IFNULL(s.given_amount,0) AS given_amount,
                                               IFNULL(s.change_amount,0) AS change_amount,
                                               IFNULL(s.rounding,0) AS rounding,
                                               s.customer_id, s.user_id
                                        FROM sales s WHERE s.Id=@id";
                    cmd.Parameters.AddWithValue("@id", saleId);
                    using var r = cmd.ExecuteReader();
                    if (!r.Read())
                    {
                        MessageBox.Show("Sale not found.", "Error", MessageBoxButton.OK, MessageBoxImage.Warning);
                        return false;
                    }
                    invoiceNo = r["invoice_no"]?.ToString() ?? "";
                    string dt = r["date_time"]?.ToString() ?? r["sale_date"]?.ToString() ?? "";
                    if (DateTime.TryParse(dt, out DateTime dtParsed))
                    {
                        date = dtParsed.ToString("dd/MM/yy");
                        time = dtParsed.ToString("hh:mm tt");
                    }
                    else
                    {
                        date = r["sale_date"]?.ToString() ?? "";
                        time = "";
                    }
                    subTotal = Convert.ToDouble(r["sub_total"]);
                    grandTotal = Convert.ToDouble(r["grand_total"]);
                    paid = Convert.ToDouble(r["paid_amount"]);
                    due = Convert.ToDouble(r["due_amount"]);
                    vat = Convert.ToDouble(r["vat"]);
                    disc = Convert.ToDouble(r["disc"]);
                    mrpTotal = Convert.ToDouble(r["mrp_total"]);
                    savings = Convert.ToDouble(r["savings"]);
                    givenAmount = Convert.ToDouble(r["given_amount"]);
                    changeAmount = Convert.ToDouble(r["change_amount"]);
                    rounding = Convert.ToDouble(r["rounding"]);
                    userId = r["user_id"] is long uid ? uid : 0;
                    custId = r["customer_id"] is long cid ? cid : 0;
                    if (custId > 0)
                    {
                        using var cc = conn.CreateCommand();
                        cc.CommandText = "SELECT name, phone, IFNULL(loyalty_point,0) AS lp FROM customers WHERE id=@id LIMIT 1";
                        cc.Parameters.AddWithValue("@id", custId);
                        using var cr = cc.ExecuteReader();
                        if (cr.Read())
                        {
                            customer = cr["name"]?.ToString() ?? "";
                            customerPhone = cr["phone"]?.ToString() ?? "";
                            gramAvailable = Convert.ToDouble(cr["lp"]);
                        }
                    }
                    if (string.IsNullOrEmpty(customer)) customer = "Walk-in Customer";
                }

                // Payment method
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"SELECT IFNULL(pm.name, 'Cash') FROM sale_payments sp
                        LEFT JOIN payment_methods pm ON pm.id=sp.payment_id
                        WHERE sp.sale_id=@id AND sp.del_status='Live' LIMIT 1";
                    cmd.Parameters.AddWithValue("@id", saleId);
                    var pmResult = cmd.ExecuteScalar();
                    if (pmResult != null) paymentMode = pmResult.ToString() ?? "Cash";
                }

                // Gram (loyalty points) redeemed on this sale
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"SELECT IFNULL(SUM(sp.amount),0) FROM sale_payments sp
                        LEFT JOIN payment_methods pm ON pm.id=sp.payment_id
                        WHERE sp.sale_id=@id AND sp.del_status='Live'
                          AND LOWER(pm.name) LIKE '%loyalty%'";
                    cmd.Parameters.AddWithValue("@id", saleId);
                    var gResult = cmd.ExecuteScalar();
                    if (gResult != null && gResult != DBNull.Value)
                        gramRedeemed = Convert.ToDouble(gResult);
                }

                // User/Cashier name
                if (userId > 0)
                {
                    using var cmd = conn.CreateCommand();
                    cmd.CommandText = "SELECT name FROM users WHERE id=@id LIMIT 1";
                    cmd.Parameters.AddWithValue("@id", userId);
                    var nameResult = cmd.ExecuteScalar();
                    if (nameResult != null) userName = nameResult.ToString() ?? "";
                }

                // Sale items (with MRP)
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = @"SELECT d.item_id, d.qty, d.menu_unit_price,
                                               IFNULL(d.menu_price_with_discount, d.menu_unit_price) AS selling_price,
                                               IFNULL(d.discount_amount,0) AS disc,
                                               IFNULL(d.menu_vat_percentage,0) AS gst_perc,
                                               IFNULL(d.item_tax_amount,0) AS tax_amt,
                                               d.qty * d.menu_unit_price AS amount,
                                               IFNULL(i.mrp_price, IFNULL((SELECT m.MRP FROM Master1 m WHERE m.MasterType='Item' AND m.ServerId=d.item_id LIMIT 1), d.menu_unit_price)) AS mrp_price,
                                               IFNULL(i.loyalty_point,0) AS loyalty,
                                               CASE
                                                   WHEN i.parent_id IS NOT NULL AND i.parent_id > 0 THEN
                                                       IFNULL((SELECT p.name FROM items p WHERE p.id=i.parent_id LIMIT 1),'') || ' - ' || IFNULL(i.name,'Item')
                                                   ELSE
                                                       IFNULL(i.name, IFNULL((SELECT Name FROM Master1 WHERE MasterType='Item' AND ServerId=d.item_id LIMIT 1),'Item'))
                                               END AS item_name,
                                               IFNULL(i.hsn_code, IFNULL((SELECT HSNCode FROM Master1 WHERE MasterType='Item' AND ServerId=d.item_id LIMIT 1),'')) AS hsn
                                        FROM sale_details d
                                        LEFT JOIN items i ON i.id=d.item_id
                                        WHERE d.sales_id=@id ORDER BY d.id";
                    cmd.Parameters.AddWithValue("@id", saleId);
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                    {
                        double qty = Convert.ToDouble(r["qty"]);
                        double rate = Convert.ToDouble(r["menu_unit_price"]);
                        double sellingPrice = Convert.ToDouble(r["selling_price"]);
                        double dsc = Convert.ToDouble(r["disc"]);
                        double gstPerc = Convert.ToDouble(r["gst_perc"]);
                        double amt = Convert.ToDouble(r["amount"]);
                        double mrp = Convert.ToDouble(r["mrp_price"]);
                        double loyalty = Convert.ToDouble(r["loyalty"]);
                        string name = r["item_name"]?.ToString() ?? "Item";
                        string hsn = r["hsn"]?.ToString() ?? "";
                        items.Add((name, hsn, qty, rate, mrp, sellingPrice, dsc, gstPerc, 0, amt, loyalty));
                        totalQty += qty;
                        gramEarned += qty * loyalty;
                    }
                    totalItems = items.Count;
                }

                // Recompute MRP total & savings from details (stored values may be stale)
                mrpTotal = items.Sum(it => it.Qty * it.Mrp);
                savings = Math.Max(0, mrpTotal - grandTotal);
            }
            catch (Exception ex)
            {
                MessageBox.Show("Error reading sale: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
                return false;
            }

            // Cumulative total saving from all previous bills of THIS customer (per-customer running total)
            double prevTotalSaving = 0;
            if (custId > 0)
            {
                using (var conn2 = db.GetConnection())
                using (var cmd = conn2.CreateCommand())
                {
                    cmd.CommandText = "SELECT IFNULL(SUM(IFNULL(savings,0)),0) FROM sales WHERE Id < @id AND customer_id=@cid AND IFNULL(del_status,'')='Live'";
                    cmd.Parameters.AddWithValue("@id", saleId);
                    cmd.Parameters.AddWithValue("@cid", custId);
                    var psResult = cmd.ExecuteScalar();
                    if (psResult != null && psResult != DBNull.Value)
                        prevTotalSaving = Convert.ToDouble(psResult);
                }
            }

            if (disc <= 0 && subTotal + vat > grandTotal)
                disc = subTotal + vat - grandTotal;

            // Tax breakdown by rate (CGST/SGST)
            var taxByRate = new SortedDictionary<double, (double taxable, double cgst, double sgst)>();
            double totalTaxable = 0, totalCgst = 0, totalSgst = 0;
            foreach (var item in items)
            {
                if (item.GstPerc > 0)
                {
                    if (!taxByRate.ContainsKey(item.GstPerc))
                        taxByRate[item.GstPerc] = (0, 0, 0);
                    var e = taxByRate[item.GstPerc];
                    double taxAmt = item.Amount * item.GstPerc / (100 + item.GstPerc);
                    e.taxable += item.Amount - taxAmt;
                    e.cgst += taxAmt / 2;
                    e.sgst += taxAmt / 2;
                    taxByRate[item.GstPerc] = e;
                }
            }
            foreach (var kvp in taxByRate) { totalTaxable += kvp.Value.taxable; totalCgst += kvp.Value.cgst; totalSgst += kvp.Value.sgst; }

            // Item-level savings (MRP vs selling price per item)
            double itemSavings = items.Sum(it => it.Qty * Math.Max(0, it.Mrp - it.SellingPrice));
            double priceTotal = items.Sum(it => it.Qty * it.SellingPrice);
            double totalSavings = savings > 0 ? savings : (mrpTotal > 0 ? Math.Max(0, mrpTotal - grandTotal) : disc);
            double billDiscount = disc;
            double gramPrevious = gramAvailable - gramEarned + gramRedeemed;

            return SavePdf(("Invoice " + invoiceNo + ".pdf").ReplaceInvalidFileNameChars(), stream =>
            {
                Document.Create(container =>
                {
                    container.Page(page =>
                    {
                        // 80mm thermal receipt (~226pt wide), height auto-flows
                        page.Size(226, 1100);
                        page.MarginHorizontal(8);
                        page.MarginVertical(6);
                        page.DefaultTextStyle(x => x.FontSize(7.5f).FontColor("#000000"));

                        page.Content().Column(col =>
                        {
                            // ═══ HEADER: Logo + Store Name (cloud logo, Hindi name) ═══
                            string logoPath = logoShow ? InvoiceLogoPath(invoiceLogoFile) : "";
                            if (!string.IsNullOrEmpty(logoPath))
                                col.Item().AlignCenter().Width(50).Image(logoPath).FitWidth();
                            else
                                col.Item().AlignCenter().PaddingBottom(1).Text("RK").FontSize(18).Bold().FontColor("#DC2626");
                            col.Item().AlignCenter().Text("राशन की दुकान").FontSize(12).Bold().FontColor("#DC2626").FontFamily("Nirmala UI");
                            col.Item().AlignCenter().Text("Trusted Multi Brand Grocery Chain Stores").FontSize(8).FontColor("#333333");
                            col.Item().PaddingTop(1).LineHorizontal(0.5f).LineColor("#000000");
                            if (!string.IsNullOrEmpty(companyAddress))
                                col.Item().AlignCenter().Text(companyAddress).FontSize(7).FontColor("#333333");
                            if (!string.IsNullOrEmpty(companyPhone))
                                col.Item().Text("Customercare No : " + companyPhone).FontSize(6);
                            if (!string.IsNullOrEmpty(companyEmail))
                                col.Item().Text("Email : " + companyEmail).FontSize(6);
                            if (!string.IsNullOrEmpty(gstin))
                                col.Item().PaddingTop(1).AlignCenter().Text("GSTIN : " + gstin).FontSize(6.5f).Bold();

                            // ═══ TAX INVOICE ═══
                            col.Item().PaddingTop(4).LineHorizontal(1f).LineColor("#000000");
                            col.Item().PaddingTop(2).AlignCenter().Text("TAX INVOICE").FontSize(9).Bold().Underline();
                            col.Item().PaddingTop(1).LineHorizontal(1f).LineColor("#000000");

                            // ═══ BILL INFO ═══
                            col.Item().PaddingTop(3).Row(row =>
                            {
                                row.RelativeItem().Column(left =>
                                {
                                left.Item().Text("Invoice No/Date/Time :").FontSize(7).Bold();
                                left.Item().Text("User Name").FontSize(7).Bold();
                                left.Item().Text("Customer Name :").FontSize(7).Bold();
                                left.Item().Text("Mobile :").FontSize(7).Bold();
                                });
                                row.RelativeItem().Column(right =>
                                {
                                    right.Item().Text("  " + invoiceNo + "  " + date + "  " + time).FontSize(7);
                                    right.Item().Text("  " + (userName != "" ? userName : "Busy")).FontSize(7);
                                    right.Item().Text("  " + customer).FontSize(7);
                                    right.Item().Text("  " + customerPhone).FontSize(7);
                                });
                            });

                            // ═══ ITEMS TABLE — header 2-line ═══
                            col.Item().PaddingTop(4).LineHorizontal(0.5f).LineColor("#000000");
                            col.Item().PaddingTop(2).Row(row =>
                            {
                                row.ConstantItem(20).Text("S.no").FontSize(6.5f).Bold();
                                row.ConstantItem(28).Text("Qty").FontSize(6.5f).Bold();
                                row.RelativeItem().Text("Product").FontSize(6.5f).Bold();
                            });
                            col.Item().Row(row =>
                            {
                                row.ConstantItem(48);
                                row.ConstantItem(52).AlignRight().Text("MRP").FontSize(6.5f).Bold();
                                row.ConstantItem(52).AlignRight().Text("Price").FontSize(6.5f).Bold();
                                row.ConstantItem(58).AlignRight().Text("Amt.").FontSize(6.5f).Bold();
                            });
                            col.Item().PaddingTop(1).LineHorizontal(0.3f).LineColor("#000000");

                            int sno = 0;
                            foreach (var item in items)
                            {
                                sno++;
                                col.Item().PaddingTop(2).Row(row =>
                                {
                                    row.ConstantItem(20).Text(sno.ToString()).FontSize(7);
                                    row.ConstantItem(28).Text(item.Qty.ToString("0.##")).FontSize(7);
                                    row.RelativeItem().Text(item.Name).FontSize(7);
                                });
                                col.Item().Row(row =>
                                {
                                    row.ConstantItem(48);
                                    row.ConstantItem(52).AlignRight().Text(item.Mrp.ToString("0.00")).FontSize(7);
                                    row.ConstantItem(52).AlignRight().Text(item.SellingPrice.ToString("0.00")).FontSize(7);
                                    row.ConstantItem(58).AlignRight().Text(item.Amount.ToString("0.00")).FontSize(7);
                                });
                            }

                            // ═══ TOTALS ═══
                            col.Item().PaddingTop(3).LineHorizontal(0.5f).LineColor("#000000");
                            col.Item().PaddingTop(2).Row(row =>
                            {
                                row.RelativeItem();
                                row.ConstantItem(90).AlignRight().Text("Total").FontSize(7).Bold();
                                row.ConstantItem(70).AlignRight().Text(subTotal.ToString("0.00")).FontSize(7);
                            });
                            if (Math.Abs(rounding) > 0.001)
                            {
                                col.Item().Row(row =>
                                {
                                    row.RelativeItem();
                                    row.ConstantItem(90).AlignRight().Text("Rounded Off (-)").FontSize(7);
                                    row.ConstantItem(70).AlignRight().Text(Math.Abs(rounding).ToString("0.00")).FontSize(7);
                                });
                            }
                            col.Item().Border(1).BorderColor("#000000").Padding(2).Row(row =>
                            {
                                row.RelativeItem();
                                row.ConstantItem(90).AlignRight().Text("Net Payable").FontSize(8).Bold();
                                row.ConstantItem(70).AlignRight().Text(grandTotal.ToString("0.00")).FontSize(8).Bold();
                            });

                            // ═══ MRP/PRICE/AMT TOTALS + AMOUNT IN WORDS ═══
                            col.Item().PaddingTop(4).Border(1).BorderColor("#000000").Padding(2).Row(row =>
                            {
                                row.RelativeItem().Column(c =>
                                {
                                    c.Item().AlignCenter().Text("MRP TOTAL").FontSize(6.5f).Bold();
                                    c.Item().AlignCenter().Text(mrpTotal.ToString("0.00")).FontSize(7);
                                });
                                row.RelativeItem().Column(c =>
                                {
                                    c.Item().AlignCenter().Text("Price Total").FontSize(6.5f).Bold();
                                    c.Item().AlignCenter().Text(priceTotal.ToString("0.00")).FontSize(7);
                                });
                                row.RelativeItem().Column(c =>
                                {
                                    c.Item().AlignCenter().Text("Amt. Total").FontSize(6.5f).Bold();
                                    c.Item().AlignCenter().Text(subTotal.ToString("0.00")).FontSize(7);
                                });
                            });
                            col.Item().PaddingTop(2).LineHorizontal(0.5f).LineColor("#000000");
                            col.Item().PaddingTop(1).AlignCenter().Text(NumberToWords(grandTotal)).FontSize(7).Bold();

                            // ═══ YOU SAVE ═══
                            if (totalSavings > 0)
                            {
                                col.Item().PaddingTop(4).Border(2).BorderColor("#DC2626").Padding(3).Column(saveCol =>
                                {
                                    saveCol.Item().AlignCenter().Text("YOU SAVE:  " + totalSavings.ToString("0.00")).FontSize(11).Bold().FontColor("#DC2626");
                                    saveCol.Item().PaddingTop(1).AlignCenter()
                                        .Text("Total Saving : " + prevTotalSaving.ToString("0.00") + " + " + totalSavings.ToString("0.00") + " = " + (prevTotalSaving + totalSavings).ToString("0.00"))
                                        .FontSize(7).FontColor("#333333");
                                });
                            }

                            // ═══ TAX TABLE (CGST/SGST by rate) ═══
                            if (taxByRate.Count > 0)
                            {
                                col.Item().PaddingTop(4).LineHorizontal(0.3f).LineColor("#000000");
                                col.Item().PaddingTop(2).Row(row =>
                                {
                                    row.ConstantItem(55).Text("Tax Rate").FontSize(6.5f).Bold();
                                    row.ConstantItem(55).AlignRight().Text("Taxable").FontSize(6.5f).Bold();
                                    row.ConstantItem(50).AlignRight().Text("CGST").FontSize(6.5f).Bold();
                                    row.ConstantItem(50).AlignRight().Text("SGST").FontSize(6.5f).Bold();
                                });
                                col.Item().PaddingTop(1).LineHorizontal(0.3f).LineColor("#000000");
                                foreach (var kvp in taxByRate)
                                {
                                    col.Item().PaddingTop(1).Row(row =>
                                    {
                                        row.ConstantItem(55).Text(kvp.Key.ToString("0") + "%").FontSize(6.5f);
                                        row.ConstantItem(55).AlignRight().Text(kvp.Value.taxable.ToString("0.00")).FontSize(6.5f);
                                        row.ConstantItem(50).AlignRight().Text(kvp.Value.cgst.ToString("0.00")).FontSize(6.5f);
                                        row.ConstantItem(50).AlignRight().Text(kvp.Value.sgst.ToString("0.00")).FontSize(6.5f);
                                    });
                                }
                                col.Item().PaddingTop(1).LineHorizontal(0.3f).LineColor("#000000");
                                col.Item().PaddingTop(1).Row(row =>
                                {
                                    row.ConstantItem(55).Text("Total").FontSize(6.5f).Bold();
                                    row.ConstantItem(55).AlignRight().Text(totalTaxable.ToString("0.00")).FontSize(6.5f).Bold();
                                    row.ConstantItem(50).AlignRight().Text(totalCgst.ToString("0.00")).FontSize(6.5f).Bold();
                                    row.ConstantItem(50).AlignRight().Text(totalSgst.ToString("0.00")).FontSize(6.5f).Bold();
                                });
                            }

                            // ═══ TOTAL GST + PAYMENT INFO ═══
                            col.Item().PaddingTop(3).LineHorizontal(0.3f).LineColor("#000000");
                            col.Item().PaddingTop(2).Text("Total GST  :  " + vat.ToString("0.00")).FontSize(7);
                            col.Item().Text("Payment Mode  :  " + paymentMode).FontSize(7);
                            if (givenAmount > 0)
                            {
                                col.Item().Text("Tender Amount  :  " + givenAmount.ToString("0.00")).FontSize(7);
                                col.Item().Text("Return Amount  :  " + changeAmount.ToString("0.00")).FontSize(7);
                            }

                            // ═══ POINTS (LOYALTY) SECTION ═══
                            if (custId > 0 && (gramEarned > 0 || gramRedeemed > 0 || gramAvailable > 0))
                            {
                                col.Item().PaddingTop(2).Text("Previous Points  :  " + gramPrevious.ToString("0")).FontSize(7);
                                col.Item().Text("Points Earned  :  " + gramEarned.ToString("0")).FontSize(7);
                                col.Item().Text("Points Redeemed  :  " + gramRedeemed.ToString("0")).FontSize(7);
                                col.Item().Text("Available Points  :  " + gramAvailable.ToString("0")).FontSize(7);
                            }

                            // ═══ FOOTER ═══
                            col.Item().PaddingTop(6).LineHorizontal(0.5f).LineColor("#000000");
                            col.Item().PaddingTop(3).AlignCenter().Text("THANKS FOR SHOPPING").FontSize(8).Bold();
                            col.Item().AlignCenter().Text("राशन की दुकान").FontSize(10).Bold().FontColor("#DC2626").FontFamily("Nirmala UI");
                            col.Item().PaddingTop(2).AlignCenter().Text("NO EXCHANGE, NO RETURN, NO REFUND").FontSize(6).Bold();
                            col.Item().AlignCenter().Text("*T&C APPLY          SCAN ME FOR LATEST OFFER").FontSize(6);
                            col.Item().PaddingTop(4).AlignCenter().Container().Width(50).Height(50).Image(GenerateQrCodeBytes(invoiceNo));
                        });
                    });
                }).GeneratePdf(stream);
            }, savePath);
        }

        /// <summary>
        /// Generate a QR-like pattern as PNG bytes using System.Drawing.
        /// </summary>
        private static byte[] GenerateQrCodeBytes(string text)
        {
            int size = 200;
            int modules = 21;
            int moduleSize = size / modules;
            size = moduleSize * modules; // Align to grid

            using var bmp = new System.Drawing.Bitmap(size, size);
            using var g = System.Drawing.Graphics.FromImage(bmp);
            g.Clear(System.Drawing.Color.White);
            var black = System.Drawing.Brushes.Black;

            // QR finder patterns
            DrawFinder(g, black, moduleSize, 0, 0);
            DrawFinder(g, black, moduleSize, (modules - 7) * moduleSize, 0);
            DrawFinder(g, black, moduleSize, 0, (modules - 7) * moduleSize);

            // Data pattern from text
            byte[] bytes = System.Text.Encoding.UTF8.GetBytes(text);
            int hash = 0;
            foreach (var b in bytes) hash = hash * 31 + b;
            var rng = new Random(hash);
            for (int row = 0; row < modules; row++)
            {
                for (int col = 0; col < modules; col++)
                {
                    if ((row < 8 && col < 8) || (row < 8 && col >= modules - 8) || (row >= modules - 8 && col < 8))
                        continue;
                    if (rng.Next(2) == 1)
                        g.FillRectangle(black, col * moduleSize, row * moduleSize, moduleSize, moduleSize);
                }
            }

            using var ms = new MemoryStream();
            bmp.Save(ms, System.Drawing.Imaging.ImageFormat.Png);
            return ms.ToArray();
        }

        private static void DrawFinder(System.Drawing.Graphics g, System.Drawing.Brush black, int ms, int x, int y)
        {
            g.FillRectangle(black, x, y, 7 * ms, 7 * ms);
            g.FillRectangle(System.Drawing.Brushes.White, x + ms, y + ms, 5 * ms, 5 * ms);
            g.FillRectangle(black, x + 2 * ms, y + 2 * ms, 3 * ms, 3 * ms);
        }

        private static string ReplaceInvalidFileNameChars(this string name)
        {
            foreach (var c in Path.GetInvalidFileNameChars())
                name = name.Replace(c, '_');
            return name;
        }

        /// <summary>Amount ko English words mein convert (Indian numbering: Lakh/Crore).</summary>
        private static string NumberToWords(double amount)
        {
            long n = (long)Math.Round(amount);
            if (n == 0) return "Zero";

            string[] ones = { "", "One", "Two", "Three", "Four", "Five", "Six", "Seven", "Eight", "Nine",
                              "Ten", "Eleven", "Twelve", "Thirteen", "Fourteen", "Fifteen", "Sixteen",
                              "Seventeen", "Eighteen", "Nineteen" };
            string[] tens = { "", "", "Twenty", "Thirty", "Forty", "Fifty", "Sixty", "Seventy", "Eighty", "Ninety" };

            string words = "";
            if (n >= 10000000) { words += NumberToWords(n / 10000000) + " Crore "; n %= 10000000; }
            if (n >= 100000)    { words += NumberToWords(n / 100000) + " Lakh ";  n %= 100000; }
            if (n >= 1000)      { words += NumberToWords(n / 1000) + " Thousand "; n %= 1000; }
            if (n >= 100)       { words += ones[n / 100] + " Hundred "; n %= 100; }
            if (n >= 20)        { words += tens[n / 10] + " "; n %= 10; }
            if (n > 0)          { words += ones[n]; }

            return "Rupees " + words.Trim() + " Only";
        }
    }
}
