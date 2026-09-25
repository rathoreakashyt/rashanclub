using System.Windows;
using System.Windows.Controls;

namespace RashanKiDukan.Views
{
    public partial class PaymentsBankingPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        public PaymentsBankingPage() { InitializeComponent(); }
        public PaymentsBankingPage(MainDashboard dashboard) : this() { _dashboard = dashboard; }

        private void BtnBack_Click(object sender, RoutedEventArgs e)
        {
            _dashboard?.ShowDashboard();
        }
    }
}
