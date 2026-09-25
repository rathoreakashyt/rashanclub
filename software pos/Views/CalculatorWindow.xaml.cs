using System;
using System.Collections.Generic;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;

namespace RashanKiDukan.Views
{
    public partial class CalculatorWindow : Window
    {
        private string _display = "0";
        private double _pending = 0;
        private string? _op;
        private bool _fresh = true;

        private readonly Dictionary<(int Row, int Col), Button> _nav = new();
        private (int Row, int Col) _navPos = (4, 3);

        public CalculatorWindow()
        {
            InitializeComponent();
            BuildNavMap();
            FocusNav(BtnEq);
            UpdateDisplay();
        }

        private void BuildNavMap()
        {
            foreach (var child in btnGrid.Children)
            {
                if (child is Button b)
                {
                    var r = Grid.GetRow(b);
                    var c = Grid.GetColumn(b);
                    _nav[(r, c)] = b;
                    if (r == 4 && c == 0) _nav[(4, 1)] = b;
                }
            }
        }

        private void FocusNav(Button b)
        {
            foreach (var kv in _nav)
            {
                if (kv.Value == b) { _navPos = kv.Key; break; }
            }
            b.Focus();
        }

        private void MoveNav(int dr, int dc)
        {
            var (r, c) = _navPos;
            r = Math.Clamp(r + dr, 0, 4);
            c = Math.Clamp(c + dc, 0, 3);
            if (_nav.TryGetValue((r, c), out var b))
            {
                _navPos = (r, c);
                b.Focus();
            }
        }

        private void CalcBtn_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn)
                FocusNav(btn);

            var tag = (sender as Button)?.Tag as string;
            switch (tag)
            {
                case "C": PressClear(); break;
                case "BS": PressBackspace(); break;
                case "%": PressPercent(); break;
                case "+":
                case "-":
                case "*":
                case "/": PressOp(tag); break;
                case "=": PressEquals(); break;
                case ".": PressDigit("."); break;
                default:
                    if (tag != null && tag.Length == 1 && char.IsDigit(tag[0]))
                        PressDigit(tag);
                    break;
            }
        }

        private void Window_KeyDown(object sender, KeyEventArgs e)
        {
            var k = e.Key;
            if (k >= Key.D0 && k <= Key.D9)
            {
                PressDigit(((int)(k - Key.D0)).ToString());
                e.Handled = true;
            }
            else if (k >= Key.NumPad0 && k <= Key.NumPad9)
            {
                PressDigit(((int)(k - Key.NumPad0)).ToString());
                e.Handled = true;
            }
            else
            {
                switch (k)
                {
                    case Key.Add: PressOp("+"); break;
                    case Key.Subtract:
                    case Key.OemMinus: PressOp("-"); break;
                    case Key.Multiply: PressOp("*"); break;
                    case Key.Divide: PressOp("/"); break;
                    case Key.OemPlus:
                        if ((Keyboard.Modifiers & ModifierKeys.Shift) != 0) PressOp("+");
                        else PressEquals();
                        break;
                    case Key.Decimal:
                    case Key.OemPeriod: PressDigit("."); break;
                    case Key.Enter:
                        if (Keyboard.FocusedElement is Button fb && fb.Tag is string ft && ft.Length > 0)
                            CalcBtn_Click(fb, new RoutedEventArgs());
                        else
                            PressEquals();
                        break;
                    case Key.Back: PressBackspace(); break;
                    case Key.Escape:
                        DialogResult = false;
                        Close();
                        break;
                    case Key.C:
                    case Key.Delete: PressClear(); break;
                    case Key.Left: MoveNav(0, -1); break;
                    case Key.Right: MoveNav(0, 1); break;
                    case Key.Up: MoveNav(-1, 0); break;
                    case Key.Down: MoveNav(1, 0); break;
                    default: return;
                }
                e.Handled = true;
            }
        }

        private void PressDigit(string d)
        {
            if (_fresh)
            {
                _display = d == "." ? "0." : d;
                _fresh = false;
            }
            else
            {
                if (_display.Length >= 14) return;
                if (d == "." && _display.Contains(".")) return;
                if (_display == "0" && d != ".") _display = d;
                else _display += d;
            }
            UpdateDisplay();
        }

        private void PressOp(string op)
        {
            if (!double.TryParse(_display, out var cur)) cur = 0;

            if (_op != null && !_fresh)
            {
                cur = Apply(_pending, cur, _op);
                _display = FormatNum(cur);
            }

            _pending = cur;
            _op = op;
            _fresh = true;
            UpdateDisplay();
        }

        private void PressEquals()
        {
            if (_op == null) return;
            if (!double.TryParse(_display, out var cur)) cur = 0;

            var res = Apply(_pending, cur, _op);
            _display = FormatNum(res);
            _op = null;
            _fresh = true;
            UpdateDisplay();
        }

        private void PressPercent()
        {
            if (!double.TryParse(_display, out var cur)) return;
            _display = FormatNum(cur / 100.0);
            UpdateDisplay();
        }

        private void PressClear()
        {
            _display = "0";
            _pending = 0;
            _op = null;
            _fresh = true;
            UpdateDisplay();
        }

        private void PressBackspace()
        {
            if (_fresh) return;
            _display = _display.Length > 1 ? _display.Substring(0, _display.Length - 1) : "0";
            UpdateDisplay();
        }

        private static double Apply(double a, double b, string op)
        {
            return op switch
            {
                "+" => a + b,
                "-" => a - b,
                "*" => a * b,
                "/" => b == 0 ? 0 : a / b,
                _ => b
            };
        }

        private static string FormatNum(double v)
        {
            if (double.IsInfinity(v) || double.IsNaN(v)) return "Error";
            return v.ToString("0.##########");
        }

        private static string OpSymbol(string op)
        {
            return op switch
            {
                "+" => "+",
                "-" => "\u2212",
                "*" => "\u00D7",
                "/" => "\u00F7",
                _ => op
            };
        }

        private void UpdateDisplay()
        {
            lblDisplay.Text = _display;
            lblExpr.Text = _op == null ? "" : $"{FormatNum(_pending)} {OpSymbol(_op)}";
        }
    }
}
