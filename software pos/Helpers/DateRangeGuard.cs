using System.Windows;
using System.Windows.Controls;

namespace RashanKiDukan.Helpers
{
    /// <summary>
    /// Date range validation — attached properties se wiring:
    ///   From picker par:  helpers:DateRangeGuard.To="{Binding ElementName=dpTo}"
    ///   To picker par:    helpers:DateRangeGuard.From="{Binding ElementName=dpFrom}"
    /// Rule: To kabhi From se pehle nahi ho sakta — agar user galat select kare
    /// to dusra side auto-adjust ho jata hai (koi invalid range possible nahi).
    /// </summary>
    public static class DateRangeGuard
    {
        public static readonly DependencyProperty FromProperty =
            DependencyProperty.RegisterAttached("From", typeof(DatePicker), typeof(DateRangeGuard),
                new PropertyMetadata(null, OnPairChanged));

        public static readonly DependencyProperty ToProperty =
            DependencyProperty.RegisterAttached("To", typeof(DatePicker), typeof(DateRangeGuard),
                new PropertyMetadata(null, OnPairChanged));

        private static readonly DependencyProperty WiredProperty =
            DependencyProperty.RegisterAttached("Wired", typeof(bool), typeof(DateRangeGuard),
                new PropertyMetadata(false));

        public static void SetFrom(DependencyObject d, DatePicker value) => d.SetValue(FromProperty, value);
        public static DatePicker GetFrom(DependencyObject d) => (DatePicker)d.GetValue(FromProperty);
        public static void SetTo(DependencyObject d, DatePicker value) => d.SetValue(ToProperty, value);
        public static DatePicker GetTo(DependencyObject d) => (DatePicker)d.GetValue(ToProperty);

        private static void OnPairChanged(DependencyObject d, DependencyPropertyChangedEventArgs e)
        {
            if (d is not DatePicker dp || e.NewValue is not DatePicker partner) return;
            Wire(dp, partner);
        }

        /// <summary>Code-behind se bhi pair wire kar sakte ho — From/To XAML attribute ke bina.</summary>
        public static void Wire(DatePicker from, DatePicker to)
        {
            if (from == null || to == null || from == to) return;
            if (from.GetValue(WiredProperty) is true && to.GetValue(WiredProperty) is true) return;
            from.SetValue(WiredProperty, true);
            to.SetValue(WiredProperty, true);

            from.SelectedDateChanged += (_, _) => Clamp(from, to);
            to.SelectedDateChanged += (_, _) => Clamp(from, to);
        }

        private static void Clamp(DatePicker from, DatePicker to)
        {
            if (!from.SelectedDate.HasValue || !to.SelectedDate.HasValue) return;
            if (from.SelectedDate.Value.Date > to.SelectedDate.Value.Date)
            {
                // Recursion se bachne ke liye flag — Wired already true hai, par
                // partner ka SelectedDateChanged dobara Clamp karega; equal hone
                // par ye check fail ho jayega, isliye terminate ho jata hai.
                to.SelectedDate = from.SelectedDate.Value.Date;
            }
        }
    }
}