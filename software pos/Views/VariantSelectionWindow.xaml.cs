using System.Collections.Generic;
using System.Windows;
using System.Windows.Input;

namespace RashanKiDukan.Views
{
    public partial class VariantSelectionWindow : Window
    {
        public ItemLookupRow? SelectedVariant { get; private set; }

        public VariantSelectionWindow(string parentName, List<VariantDisplayItem> variants)
        {
            InitializeComponent();
            lblProductName.Text = parentName;
            lblVariantCount.Text = $"{variants.Count} variants available";
            lstVariants.ItemsSource = variants;
            if (variants.Count > 0) lstVariants.SelectedIndex = 0;
            Loaded += (_, _) => lstVariants.Focus();
        }

        private void BtnSelect_Click(object sender, RoutedEventArgs e) => SelectCurrent();
        private void BtnClose_Click(object sender, RoutedEventArgs e) { DialogResult = false; Close(); }

        private void LstVariants_DoubleClick(object sender, MouseButtonEventArgs e) => SelectCurrent();

        private void LstVariants_KeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter) { SelectCurrent(); e.Handled = true; }
            if (e.Key == Key.Escape) { DialogResult = false; Close(); e.Handled = true; }
        }

        private void SelectCurrent()
        {
            if (lstVariants.SelectedItem is VariantDisplayItem v)
            {
                SelectedVariant = v.LookupRow;
                DialogResult = true;
                Close();
            }
        }
    }

    public class VariantDisplayItem
    {
        public string Name { get; set; } = "";
        public string Code { get; set; } = "";
        public string PriceDisplay { get; set; } = "";
        public string MrpDisplay { get; set; } = "";
        public string StockDisplay { get; set; } = "";
        public string ParentName { get; set; } = "";
        public ItemLookupRow? LookupRow { get; set; }
    }
}
