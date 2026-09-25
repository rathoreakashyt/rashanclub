using System.Windows;
using System.Windows.Controls;

namespace RashanKiDukan.Views
{
    public partial class ItemConfigurationPage : UserControl
    {
        private readonly MainDashboard? _dashboard;

        public ItemConfigurationPage() { InitializeComponent(); }
        public ItemConfigurationPage(MainDashboard dashboard) : this() { _dashboard = dashboard; }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowDashboard();
        }

        private void OpenAddCategory_Click(object sender, RoutedEventArgs e)
            => _dashboard?.ShowPage(new ConfigEditPage(_dashboard!, ConfigEntity.ItemCategory));

        private void OpenListCategory_Click(object sender, RoutedEventArgs e)
            => _dashboard?.ShowPage(new ConfigListPage(_dashboard!, ConfigEntity.ItemCategory));

        private void OpenAddBrand_Click(object sender, RoutedEventArgs e)
            => _dashboard?.ShowPage(new ConfigEditPage(_dashboard!, ConfigEntity.Brand));

        private void OpenListBrand_Click(object sender, RoutedEventArgs e)
            => _dashboard?.ShowPage(new ConfigListPage(_dashboard!, ConfigEntity.Brand));

        private void OpenAddUnit_Click(object sender, RoutedEventArgs e)
            => _dashboard?.ShowPage(new ConfigEditPage(_dashboard!, ConfigEntity.Unit));

        private void OpenListUnit_Click(object sender, RoutedEventArgs e)
            => _dashboard?.ShowPage(new ConfigListPage(_dashboard!, ConfigEntity.Unit));

        private void OpenAddRack_Click(object sender, RoutedEventArgs e)
            => _dashboard?.ShowPage(new ConfigEditPage(_dashboard!, ConfigEntity.Rack));

        private void OpenListRack_Click(object sender, RoutedEventArgs e)
            => _dashboard?.ShowPage(new ConfigListPage(_dashboard!, ConfigEntity.Rack));

        private void OpenAddVariation_Click(object sender, RoutedEventArgs e)
            => _dashboard?.ShowPage(new ConfigEditPage(_dashboard!, ConfigEntity.Variation));

        private void OpenListVariation_Click(object sender, RoutedEventArgs e)
            => _dashboard?.ShowPage(new ConfigListPage(_dashboard!, ConfigEntity.Variation));

        private void OpenAddExpenseCategory_Click(object sender, RoutedEventArgs e)
            => _dashboard?.ShowPage(new ConfigEditPage(_dashboard!, ConfigEntity.ExpenseCategory));

        private void OpenListExpenseCategory_Click(object sender, RoutedEventArgs e)
            => _dashboard?.ShowPage(new ConfigListPage(_dashboard!, ConfigEntity.ExpenseCategory));
    }
}
