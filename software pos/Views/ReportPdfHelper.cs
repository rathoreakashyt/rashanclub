using System;
using System.Data;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Controls.Primitives;
using System.Windows.Input;
using System.Windows.Media;
using Microsoft.Win32;
using QuestPDF.Fluent;
using QuestPDF.Helpers;

namespace RashanKiDukan.Views
{
    /// <summary>
    /// Report pages ke liye shared PDF export helper:
    /// har report page ke Export popup me "Export PDF" button add karta hai,
    /// QuestPDF se DataTable ka PDF banata hai aur in-app PdfPreviewWindow me kholta hai.
    /// </summary>
    public static class ReportPdfHelper
    {
        /// <summary>Report page ke Export popup me "Export PDF" button insert karo (top par).</summary>
        public static void Attach(Popup popup, Func<DataView> getDv, string title, string subtitle = null)
        {
            try
            {
                if (popup?.Child is not Border b || b.Child is not StackPanel sp) return;
                var btn = new Button
                {
                    Content = "📄  Export PDF",
                    Background = Brushes.Transparent,
                    BorderThickness = new Thickness(0),
                    FontSize = 13,
                    FontFamily = new FontFamily("Inter, Segoe UI"),
                    Foreground = new SolidColorBrush(System.Windows.Media.Color.FromRgb(0x37, 0x41, 0x51)),
                    Padding = new System.Windows.Thickness(14, 9, 14, 9),
                    HorizontalContentAlignment = System.Windows.HorizontalAlignment.Left,
                    Cursor = Cursors.Hand
                };
                btn.Click += (s, e) =>
                {
                    popup.IsOpen = false;
                    Export(getDv(), title, subtitle);
                };
                sp.Children.Insert(0, btn);
            }
            catch { }
        }

        /// <summary>DataView se PDF banakar in-app viewer me kholo (Save dialog ke baad).</summary>
        public static void Export(DataView dv, string title, string subtitle = null)
        {
            var table = dv?.Table?.Clone() ?? new DataTable();
            if (dv != null)
                foreach (DataRowView rv in dv)
                    table.ImportRow(rv.Row);
            Export(table, title, subtitle);
        }

        /// <summary>DataTable se PDF banakar in-app viewer me kholo (Save dialog ke baad).</summary>
        public static void Export(DataTable table, string title, string subtitle = null)
        {
            if (table == null || table.Rows.Count == 0)
            { MessageBox.Show("No data to export.", "Info"); return; }

            var dlg = new SaveFileDialog
            {
                Filter = "PDF files (*.pdf)|*.pdf",
                DefaultExt = "pdf",
                Title = "Export PDF",
                FileName = Sanitize(title) + "_" + DateTime.Now.ToString("yyyyMMdd_HHmmss") + ".pdf"
            };
            if (dlg.ShowDialog() != true) return;

            try
            {
                QuestPDF.Settings.License = QuestPDF.Infrastructure.LicenseType.Community;
                Document.Create(container =>
                {
                    container.Page(page =>
                    {
                        bool landscape = table.Columns.Count > 6;
                        page.Size(landscape ? PageSizes.A4.Landscape() : PageSizes.A4);
                        page.Margin(28);
                        page.DefaultTextStyle(x => x.FontSize(8.5f).FontColor("#111827"));

                        page.Header().Column(h =>
                        {
                            h.Item().Row(r =>
                            {
                                r.RelativeItem().Text(CompanyName()).FontSize(14).Bold().FontColor("#111827");
                                r.RelativeItem().AlignRight().Text(title).FontSize(12).Bold().FontColor("#4F46E5");
                            });
                            string sub = "Generated: " + DateTime.Now.ToString("dd MMM yyyy, hh:mm tt");
                            if (!string.IsNullOrEmpty(subtitle)) sub += "   |   " + subtitle;
                            h.Item().PaddingTop(2).Text(sub).FontSize(8).FontColor("#64748B");
                            h.Item().PaddingTop(4).LineHorizontal(1).LineColor("#E2E8F0");
                        });

                        page.Content().PaddingTop(8).Table(tbl =>
                        {
                            var weights = new float[table.Columns.Count];
                            for (int c = 0; c < table.Columns.Count; c++)
                            {
                                double maxLen = Math.Max(8, table.Columns[c].ColumnName.Length);
                                foreach (DataRow row in table.Rows)
                                    maxLen = Math.Max(maxLen, (row[c]?.ToString() ?? "").Length);
                                weights[c] = (float)Math.Clamp(maxLen / 9.0, 0.7, 4.5);
                            }
                            tbl.ColumnsDefinition(cols =>
                            {
                                foreach (float w in weights) cols.RelativeColumn(w);
                            });
                            tbl.Header(h =>
                            {
                                foreach (DataColumn col in table.Columns)
                                    h.Cell().Background("#EEF2FF").Padding(4)
                                        .Text(col.ColumnName.Replace("_", " ").ToUpper())
                                        .Bold().FontSize(8).FontColor("#4F46E5");
                            });
                            foreach (DataRow row in table.Rows)
                                foreach (DataColumn col in table.Columns)
                                    tbl.Cell().Padding(4).Text(row[col]?.ToString() ?? "");
                        });

                        page.Footer().PaddingTop(8).AlignCenter()
                            .Text("Generated by Rashan Ki Dukan POS").FontSize(8).FontColor("#94A3B8");
                    });
                }).GeneratePdf(dlg.FileName);

                PdfPreviewWindow.ShowPdf(dlg.FileName, title);
            }
            catch (Exception ex)
            {
                MessageBox.Show("PDF export failed: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        /// <summary>Report page header ke aage extra button (bina popup wale pages ke liye).</summary>
        public static void AddButton(Button anchor, string content, Action onClick)
        {
            try
            {
                if (anchor?.Parent is not StackPanel sp) return;
                var btn = new Button
                {
                    Content = content,
                    FontSize = 12.5,
                    FontWeight = FontWeights.SemiBold,
                    FontFamily = new FontFamily("Inter, Segoe UI"),
                    Foreground = new SolidColorBrush(System.Windows.Media.Color.FromRgb(0x4F, 0x46, 0xE5)),
                    BorderThickness = new System.Windows.Thickness(0),
                    Padding = new System.Windows.Thickness(14, 7, 14, 7),
                    Margin = new Thickness(0, 0, 8, 0),
                    Cursor = Cursors.Hand
                };
                btn.Click += (s, e) => onClick();
                int idx = sp.Children.IndexOf(anchor);
                sp.Children.Insert(idx < 0 ? 0 : idx, btn);
            }
            catch { }
        }

        private static string Sanitize(string s)
        {
            var invalid = System.IO.Path.GetInvalidFileNameChars();
            return new string(s.Select(ch => invalid.Contains(ch) ? '_' : ch).ToArray()).Replace(" ", "_");
        }

        private static string CompanyName()
        {
            try
            {
                using var conn = new Database.DatabaseService().GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT COALESCE(NULLIF(name,''), NULLIF(business_name,'')) FROM companies LIMIT 1";
                var v = cmd.ExecuteScalar();
                if (v != null && v.ToString() != "") return v.ToString();
            }
            catch { }
            return "Rashan Ki Dukan";
        }
    }
}