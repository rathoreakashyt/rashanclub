using System;
using System.Collections.Generic;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Documents;
using System.Windows.Markup;
using System.Windows.Media;
using System.Windows.Media.Imaging;

namespace RashanKiDukan.Views
{
    public partial class BarcodePrintDialog : Window
    {
        private readonly string _itemName;
        private readonly string _itemCode;
        private readonly string _itemPrice;

        public BarcodePrintDialog(string itemName, string itemCode, string itemPrice)
        {
            InitializeComponent();
            _itemName  = itemName;
            _itemCode  = itemCode;
            _itemPrice = itemPrice;

            lblItemName.Text  = itemName;
            lblItemCode.Text  = itemCode;
            lblItemPrice.Text = itemPrice;

            UpdatePreview();
        }

        // ── Event Handlers ────────────────────────────────────────────────
        private void Options_Changed(object sender, EventArgs e) => UpdatePreview();
        private void BtnClose_Click(object s, RoutedEventArgs e) => Close();

        private void BtnPrint_Click(object s, RoutedEventArgs e)
        {
            int qty = int.TryParse(txtQty.Text.Trim(), out int q) && q > 0 ? q : 1;
            var dlg = new PrintDialog();
            if (dlg.ShowDialog() != true) return;

            var doc = BuildFixedDocument(dlg, qty);
            dlg.PrintDocument(doc.DocumentPaginator, $"Barcode - {_itemCode}");
        }

        // ── Live Preview ──────────────────────────────────────────────────
        private void UpdatePreview()
        {
            // XAML load ke dauran TextChanged/Checked events fire ho sakte hain
            // jab controls abhi InitializeComponent() mein create nahi hue hote —
            // isliye null-guard zaroori hai (NRE se bachne ke liye).
            if (chkShowName == null || previewName == null || previewBarcode == null) return;

            bool showName    = chkShowName.IsChecked == true;
            bool showCode    = chkShowCode.IsChecked == true;
            bool showPrice   = chkShowPrice.IsChecked == true;
            bool showBarcode = chkShowBarcode.IsChecked == true;

            double nameFontSize  = double.TryParse(txtNameFontSize.Text,  out var nf) ? nf : 14;
            double codeFontSize  = double.TryParse(txtCodeFontSize.Text,  out var cf) ? cf : 14;
            double priceFontSize = double.TryParse(txtPriceFontSize.Text, out var pf) ? pf : 14;
            double barcodeH      = double.TryParse(txtBarcodeHeight.Text, out var bh) ? bh : 50;
            double barW          = double.TryParse(txtBarcodeWidth.Text,  out var bw) ? bw : 1;
            double codeFontSzBc  = double.TryParse(txtBarcodeFontSize.Text, out var bf) ? bf : 12;

            // Name
            previewName.Visibility = showName ? Visibility.Visible : Visibility.Collapsed;
            previewName.Text       = showName ? _itemName : "";
            previewName.FontSize   = Math.Max(8, nameFontSize);

            // Code
            previewCode.Visibility = showCode ? Visibility.Visible : Visibility.Collapsed;
            previewCode.Text       = showCode ? $"Code: {_itemCode}" : "";
            previewCode.FontSize   = Math.Max(8, codeFontSize);

            // Price
            previewPrice.Visibility = showPrice ? Visibility.Visible : Visibility.Collapsed;
            previewPrice.Text       = showPrice ? $"Price: {_itemPrice}" : "";
            previewPrice.FontSize   = Math.Max(8, priceFontSize);

            // Barcode image
            if (showBarcode && !string.IsNullOrWhiteSpace(_itemCode))
            {
                previewBarcode.Visibility     = Visibility.Visible;
                previewBarcodeText.Visibility = Visibility.Visible;
                previewBarcode.Source = RenderCode128(_itemCode, barW, barcodeH);
                previewBarcodeText.Text     = _itemCode;
                previewBarcodeText.FontSize = Math.Max(7, codeFontSzBc);
            }
            else
            {
                previewBarcode.Visibility     = Visibility.Collapsed;
                previewBarcodeText.Visibility = Visibility.Collapsed;
            }
        }

        // ── Code128 Barcode Renderer ──────────────────────────────────────
        // Encodes text as Code128-B and returns a BitmapSource
        public static BitmapSource RenderCode128(string text, double barWidth = 1, double height = 50)
        {
            barWidth = Math.Max(1, barWidth);
            height   = Math.Max(10, height);

            var bars = EncodeCode128(text); // list of bar widths (1=narrow, 2=wide alternate)
            int totalW = 0;
            foreach (int b in bars) totalW += b;
            int pxW = (int)(totalW * barWidth);
            int pxH = (int)height;

            var bmp = new WriteableBitmap(pxW, pxH, 96, 96, PixelFormats.Bgr32, null);
            int stride = pxW * 4;
            byte[] pixels = new byte[stride * pxH];

            // Fill white
            for (int i = 0; i < pixels.Length; i++) pixels[i] = 255;

            // Draw bars
            bool isBlack = true;
            int x = 0;
            foreach (int bw2 in bars)
            {
                int bwPx = (int)(bw2 * barWidth);
                if (isBlack)
                {
                    for (int row = 0; row < pxH; row++)
                        for (int col = x; col < x + bwPx && col < pxW; col++)
                        {
                            int idx = row * stride + col * 4;
                            pixels[idx] = pixels[idx + 1] = pixels[idx + 2] = 0; // black
                        }
                }
                x += bwPx;
                isBlack = !isBlack;
            }

            bmp.WritePixels(new Int32Rect(0, 0, pxW, pxH), pixels, stride, 0);
            bmp.Freeze();
            return bmp;
        }

        // Code128-B encoding: returns list of module widths
        private static List<int> EncodeCode128(string text)
        {
            // Code128-B patterns: each char = 6 elements (3 bars, 3 spaces alternating)
            // Pattern table: index = ASCII 32-127, value = 6-digit pattern (1=narrow, 2=wide)
            var pat = Code128BPatterns();
            var modules = new List<int>();

            // Start Code B: pattern {2,1,1,4,1,2}
            // NOTE: pattern table mein sirf 0-102 entries hain — isliye hardcode
            // karna zaroori hai (pat[104] index-out-of-bounds deta tha).
            AddPattern(modules, new[] { 2, 1, 1, 4, 1, 2 });

            int checksum = 104;
            for (int i = 0; i < text.Length; i++)
            {
                int c = (int)text[i];
                if (c < 32 || c > 126) c = 32; // replace unsupported
                int idx = c - 32;
                checksum += (i + 1) * idx;
                AddPattern(modules, pat[idx]);
            }

            // Checksum symbol
            int cs = checksum % 103;
            AddPattern(modules, pat[cs]);

            // Stop: 13311211 (7 modules)
            modules.AddRange(new[] { 2, 3, 3, 1, 1, 2, 1, 1 });

            return modules;
        }

        private static void AddPattern(List<int> m, int[] p)
        {
            foreach (var v in p) m.Add(v);
        }

        // Code128-B full pattern table (0–102 = ASCII 32–127, then start/stop special)
        private static int[][] Code128BPatterns()
        {
            // Each entry is 6 elements: bar1,space1,bar2,space2,bar3,space3
            // Standard Code 128 encoding patterns
            var t = new int[][]
            {
                new[]{2,1,2,2,2,2}, new[]{2,2,2,1,2,2}, new[]{2,2,2,2,2,1}, new[]{1,2,1,2,2,3},
                new[]{1,2,1,3,2,2}, new[]{1,3,1,2,2,2}, new[]{1,2,2,2,1,3}, new[]{1,2,2,3,1,2},
                new[]{1,3,2,2,1,2}, new[]{2,2,1,2,1,3}, new[]{2,2,1,3,1,2}, new[]{2,3,1,2,1,2},
                new[]{1,1,2,2,3,2}, new[]{1,2,2,1,3,2}, new[]{1,2,2,2,3,1}, new[]{1,1,3,2,2,2},
                new[]{1,2,3,1,2,2}, new[]{1,2,3,2,2,1}, new[]{2,2,3,2,1,1}, new[]{2,2,1,1,3,2},
                new[]{2,2,1,2,3,1}, new[]{2,1,3,2,1,2}, new[]{2,2,3,1,1,2}, new[]{3,1,2,1,3,1},
                new[]{3,1,1,2,2,2}, new[]{3,2,1,1,2,2}, new[]{3,2,1,2,2,1}, new[]{3,1,2,2,1,2},
                new[]{3,2,2,1,1,2}, new[]{3,2,2,2,1,1}, new[]{2,1,2,1,2,3}, new[]{2,1,2,3,2,1},
                new[]{2,3,2,1,2,1}, new[]{1,1,1,3,2,3}, new[]{1,3,1,1,2,3}, new[]{1,3,1,3,2,1},
                new[]{1,1,2,3,1,3}, new[]{1,3,2,1,1,3}, new[]{1,3,2,3,1,1}, new[]{2,1,1,3,1,3},
                new[]{2,3,1,1,1,3}, new[]{2,3,1,3,1,1}, new[]{1,1,2,1,3,3}, new[]{1,1,2,3,3,1},
                new[]{1,3,2,1,3,1}, new[]{1,1,3,1,2,3}, new[]{1,1,3,3,2,1}, new[]{1,3,3,1,2,1},
                new[]{3,1,3,1,2,1}, new[]{2,1,1,3,3,1}, new[]{2,3,1,1,3,1}, new[]{2,1,3,1,1,3},
                new[]{2,1,3,3,1,1}, new[]{2,1,3,1,3,1}, new[]{3,1,1,1,2,3}, new[]{3,1,1,3,2,1},
                new[]{3,3,1,1,2,1}, new[]{3,1,2,1,1,3}, new[]{3,1,2,3,1,1}, new[]{3,3,2,1,1,1},
                new[]{3,1,4,1,1,1}, new[]{2,2,1,4,1,1}, new[]{4,3,1,1,1,1}, new[]{1,1,1,2,2,4},
                new[]{1,1,1,4,2,2}, new[]{1,2,1,1,2,4}, new[]{1,2,1,4,2,1}, new[]{1,4,1,1,2,2},
                new[]{1,4,1,2,2,1}, new[]{1,1,2,2,1,4}, new[]{1,1,2,4,1,2}, new[]{1,2,2,1,1,4},
                new[]{1,2,2,4,1,1}, new[]{1,4,2,1,1,2}, new[]{1,4,2,2,1,1}, new[]{2,4,1,2,1,1},
                new[]{2,2,1,1,1,4}, new[]{4,1,3,1,1,1}, new[]{2,4,1,1,1,2}, new[]{1,3,4,1,1,1},
                new[]{1,1,1,2,4,2}, new[]{1,2,1,1,4,2}, new[]{1,2,1,2,4,1}, new[]{1,1,4,2,1,2},
                new[]{1,2,4,1,1,2}, new[]{1,2,4,2,1,1}, new[]{4,1,1,2,1,2}, new[]{4,2,1,1,1,2},
                new[]{4,2,1,2,1,1}, new[]{2,1,2,1,4,1}, new[]{2,1,4,1,2,1}, new[]{4,1,2,1,2,1},
                new[]{1,1,1,1,4,3}, new[]{1,1,1,3,4,1}, new[]{1,3,1,1,4,1}, new[]{1,1,4,1,1,3},
                new[]{1,1,4,3,1,1}, new[]{4,1,1,1,1,3}, new[]{4,1,1,3,1,1}, new[]{1,1,3,1,4,1},
                new[]{1,1,4,1,3,1}, new[]{3,1,1,1,4,1}, // 103 entries 0-102
                new[]{2,1,1,4,1,2}, // 104 = Start B
            };
            return t;
        }

        // ── Print ─────────────────────────────────────────────────────────
        private FixedDocument BuildFixedDocument(PrintDialog dlg, int qty)
        {
            bool showName    = chkShowName.IsChecked == true;
            bool showCode    = chkShowCode.IsChecked == true;
            bool showPrice   = chkShowPrice.IsChecked == true;
            bool showBarcode = chkShowBarcode.IsChecked == true;

            double nameFontSize  = double.TryParse(txtNameFontSize.Text,    out var nf) ? nf : 14;
            double codeFontSize  = double.TryParse(txtCodeFontSize.Text,    out var cf) ? cf : 14;
            double priceFontSize = double.TryParse(txtPriceFontSize.Text,   out var pf) ? pf : 14;
            double barcodeH      = double.TryParse(txtBarcodeHeight.Text,   out var bh) ? bh : 50;
            double barW          = double.TryParse(txtBarcodeWidth.Text,    out var bw) ? bw : 1;
            double codeFontSzBc  = double.TryParse(txtBarcodeFontSize.Text, out var bf) ? bf : 12;

            double pageW = dlg.PrintableAreaWidth;
            double pageH = dlg.PrintableAreaHeight;

            var doc = new FixedDocument();
            doc.DocumentPaginator.PageSize = new Size(pageW, pageH);

            // Calculate single label height
            double labelH = 8; // top padding
            if (showName)    labelH += nameFontSize + 4;
            if (showCode)    labelH += codeFontSize + 4;
            if (showPrice)   labelH += priceFontSize + 4;
            if (showBarcode) labelH += barcodeH + codeFontSzBc + 8;
            labelH += 8; // bottom padding

            // Place labels on pages
            int labelsPerCol = Math.Max(1, (int)(pageH / labelH));
            int labelsPerRow = Math.Max(1, (int)(pageW / 200)); // ~200px per label
            double labelW = pageW / labelsPerRow;

            int placed = 0;
            FixedPage? page = null;
            Canvas? canvas = null;

            while (placed < qty)
            {
                page = new FixedPage { Width = pageW, Height = pageH, Background = Brushes.White };
                canvas = new Canvas { Width = pageW, Height = pageH };
                page.Children.Add(canvas);

                int onPage = 0;
                while (placed < qty && onPage < labelsPerCol * labelsPerRow)
                {
                    int row = onPage / labelsPerRow;
                    int col = onPage % labelsPerRow;
                    double x = col * labelW + 4;
                    double y = row * labelH + 4;

                    DrawLabelOnCanvas(canvas, x, y, labelW - 8, showName, showCode, showPrice, showBarcode,
                        nameFontSize, codeFontSize, priceFontSize, barcodeH, barW, codeFontSzBc);
                    placed++;
                    onPage++;
                }

                var pageContent = new PageContent();
                ((IAddChild)pageContent).AddChild(page);
                doc.Pages.Add(pageContent);
            }
            return doc;
        }

        private void DrawLabelOnCanvas(Canvas canvas, double x, double y, double w,
            bool showName, bool showCode, bool showPrice, bool showBarcode,
            double nameFontSz, double codeFontSz, double priceFontSz,
            double barcodeH, double barW, double codeFontSzBc)
        {
            double curY = y;

            if (showName)
            {
                var tb = new TextBlock { Text = _itemName, FontSize = nameFontSz, FontWeight = FontWeights.SemiBold,
                    Width = w, TextAlignment = TextAlignment.Center };
                Canvas.SetLeft(tb, x); Canvas.SetTop(tb, curY);
                canvas.Children.Add(tb);
                curY += nameFontSz + 4;
            }
            if (showCode)
            {
                var tb = new TextBlock { Text = $"Code: {_itemCode}", FontSize = codeFontSz,
                    Width = w, TextAlignment = TextAlignment.Center };
                Canvas.SetLeft(tb, x); Canvas.SetTop(tb, curY);
                canvas.Children.Add(tb);
                curY += codeFontSz + 4;
            }
            if (showPrice)
            {
                var tb = new TextBlock { Text = $"Price: {_itemPrice}", FontSize = priceFontSz,
                    Width = w, TextAlignment = TextAlignment.Center };
                Canvas.SetLeft(tb, x); Canvas.SetTop(tb, curY);
                canvas.Children.Add(tb);
                curY += priceFontSz + 4;
            }
            if (showBarcode)
            {
                var img = new Image { Source = RenderCode128(_itemCode, barW, barcodeH), Stretch = Stretch.None };
                double imgX = x + (w - img.Source.Width) / 2;
                Canvas.SetLeft(img, imgX); Canvas.SetTop(img, curY);
                canvas.Children.Add(img);
                curY += barcodeH + 2;

                var tb = new TextBlock { Text = _itemCode, FontSize = codeFontSzBc,
                    Width = w, TextAlignment = TextAlignment.Center };
                Canvas.SetLeft(tb, x); Canvas.SetTop(tb, curY);
                canvas.Children.Add(tb);
            }
        }
    }
}
