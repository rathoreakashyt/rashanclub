using System.Windows;
using System.Windows.Controls;

namespace RashanKiDukan.Views
{
    public partial class GSTPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        public GSTPage() { InitializeComponent(); }
        public GSTPage(MainDashboard dashboard) : this() { _dashboard = dashboard; }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowDashboard();
        }
    }
}
