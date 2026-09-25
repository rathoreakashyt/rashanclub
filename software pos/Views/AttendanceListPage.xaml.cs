using System;
using System.Collections.Generic;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Text;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Documents;
using System.Windows.Media;
using Microsoft.Win32;
using QuestPDF.Fluent;
using QuestPDF.Helpers;
using QuestPDF.Infrastructure;
using RashanKiDukan.Database;
using RashanKiDukan.Services;

namespace RashanKiDukan.Views;

public partial class AttendanceListPage : UserControl, ISyncRefreshable
{
    private MainDashboard? _dashboard;
    private readonly DatabaseService _db = new();
    private List<AttendanceRow> _filtered = new();
    private int _pageSize = 10;
    private int _page = 1;
    private string _dateFormat = "yyyy/MM/dd";

    public AttendanceListPage() { InitializeComponent(); Loaded += OnLoaded; }
    public AttendanceListPage(MainDashboard dashboard) : this() { _dashboard = dashboard; }

    private void OnLoaded(object sender, RoutedEventArgs e)
    {
        cmbPageSize.Items.Add("10");
        cmbPageSize.Items.Add("25");
        cmbPageSize.Items.Add("50");
        cmbPageSize.Items.Add("100");
        cmbPageSize.SelectedIndex = 0;
        LoadEmployees();
        LoadDateFormat();
        RefreshData();
    }

    private void LoadDateFormat()
    {
        try
        {
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "SELECT COALESCE(date_format,'') FROM companies WHERE del_status='Live' LIMIT 1";
            var v = cmd.ExecuteScalar();
            if (v != null && !string.IsNullOrWhiteSpace(v.ToString()))
                _dateFormat = ToNetDateFormat(v.ToString()!);
        }
        catch { }
    }

    private static string ToNetDateFormat(string phpFormat)
    {
        return phpFormat.Replace("Y", "yyyy").Replace("m", "MM").Replace("d", "dd")
                        .Replace("M", "MMM").Replace("y", "yy");
    }

    private void LoadEmployees()
    {
        cmbEmployee.Items.Clear();
        cmbEmployee.Items.Add(new ComboBoxItem { Content = "All Employees", Tag = "" });
        try
        {
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "SELECT id, COALESCE(name,'') FROM employees WHERE del_status IS NULL OR del_status='Live' ORDER BY name";
            using var r = cmd.ExecuteReader();
            while (r.Read())
                cmbEmployee.Items.Add(new ComboBoxItem { Content = r.GetString(1), Tag = r.GetInt64(0).ToString() });
        }
        catch { }
        cmbEmployee.SelectedIndex = 0;
    }

    // ═══════════ DATA ═══════════

    // Cloud/Web se change aaya (sync pull) → page khud reload — bina refresh ke
    public void OnSyncPulled()
    {
        Dispatcher.BeginInvoke(new Action(() =>
        {
            if (IsLoaded) RefreshData();
        }), System.Windows.Threading.DispatcherPriority.Background);
    }

    private void RefreshData()
    {
        string search = txtSearch.Text?.Trim() ?? "";
        string dateFrom = dpDateFrom.SelectedDate?.ToString("yyyy-MM-dd") ?? "";
        string dateTo = dpDateTo.SelectedDate?.ToString("yyyy-MM-dd") ?? "";
        string employeeId = (cmbEmployee.SelectedItem as ComboBoxItem)?.Tag?.ToString() ?? "";

        _filtered.Clear();
        try
        {
            using var conn = _db.GetConnection();
            var sql = new StringBuilder();
            sql.Append(@"SELECT a.id, a.reference_no, a.date, COALESCE(a.in_time,'') AS in_time, COALESCE(a.out_time,'') AS out_time,
                                COALESCE(a.note,'') AS note,
                                COALESCE(e.name,'') AS emp_name, COALESCE(e.phone,'') AS emp_phone
                         FROM attendances a
                         LEFT JOIN employees e ON e.id = a.employee_id
                         WHERE (a.del_status IS NULL OR a.del_status='Live')");

            var filters = new List<string>();
            if (!string.IsNullOrEmpty(dateFrom)) filters.Add("a.date >= @from");
            if (!string.IsNullOrEmpty(dateTo)) filters.Add("a.date <= @to");
            if (!string.IsNullOrEmpty(employeeId)) filters.Add("a.employee_id = @emp");
            if (filters.Count > 0) sql.Append(" AND " + string.Join(" AND ", filters));
            if (!string.IsNullOrEmpty(search))
                sql.Append(@" AND (a.reference_no LIKE @s OR a.date LIKE @s OR a.note LIKE @s OR e.name LIKE @s OR e.email LIKE @s)");

            sql.Append(" ORDER BY a.date DESC, a.in_time DESC");

            using var cmd = conn.CreateCommand();
            cmd.CommandText = sql.ToString();
            if (!string.IsNullOrEmpty(dateFrom)) cmd.Parameters.AddWithValue("@from", dateFrom);
            if (!string.IsNullOrEmpty(dateTo)) cmd.Parameters.AddWithValue("@to", dateTo);
            if (!string.IsNullOrEmpty(employeeId)) cmd.Parameters.AddWithValue("@emp", long.Parse(employeeId));
            if (!string.IsNullOrEmpty(search)) cmd.Parameters.AddWithValue("@s", "%" + search + "%");

            using var r = cmd.ExecuteReader();
            while (r.Read())
            {
                string inTime = r.GetString(3);
                string outTime = r.GetString(4);
                _filtered.Add(new AttendanceRow
                {
                    Id = r.GetInt64(0),
                    ReferenceNo = r.GetString(1),
                    Date = FormatDate(r.GetString(2)),
                    Employee = FormatEmployee(r.GetString(6), r.GetString(7)),
                    InTime = string.IsNullOrEmpty(inTime) ? "N/A" : inTime,
                    OutTime = string.IsNullOrEmpty(outTime) ? "N/A" : outTime,
                    TotalTime = FormatTotalTime(inTime, outTime),
                    Note = TruncateNote(r.GetString(5))
                });
            }
        }
        catch (Exception ex)
        {
            MessageBox.Show("Error loading attendance: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
        }

        UpdateTotalHours();
        ApplyPaging();
    }

    private string FormatDate(string raw)
    {
        if (DateTime.TryParse(raw, CultureInfo.InvariantCulture, DateTimeStyles.None, out var d))
            return d.ToString(_dateFormat);
        return raw;
    }

    private static string FormatEmployee(string name, string phone)
    {
        if (string.IsNullOrEmpty(name)) return "N/A";
        return string.IsNullOrEmpty(phone) ? name : $"{name} ({phone})";
    }

    private static string FormatTotalTime(string inTime, string outTime)
    {
        if (string.IsNullOrEmpty(inTime) || string.IsNullOrEmpty(outTime)) return "N/A";
        if (!TimeSpan.TryParse(inTime, out var tin) || !TimeSpan.TryParse(outTime, out var tout)) return "N/A";
        if (tout <= tin) return "N/A";
        double hours = (tout - tin).TotalHours;
        // Server: diffInSeconds/3600 . 'h'  => "9h", "8.5h"
        string h = hours == Math.Floor(hours) ? hours.ToString("0") : hours.ToString("0.#", CultureInfo.InvariantCulture);
        return h + "h";
    }

    private static string TruncateNote(string note)
    {
        if (string.IsNullOrEmpty(note)) return "";
        return note.Length > 50 ? note.Substring(0, 50) + "..." : note;
    }

    private void UpdateTotalHours()
    {
        double totalMinutes = 0;
        foreach (var row in _filtered)
        {
            if (row.InTime == "N/A" || row.OutTime == "N/A") continue;
            if (TimeSpan.TryParse(row.InTime, out var tin) && TimeSpan.TryParse(row.OutTime, out var tout) && tout > tin)
                totalMinutes += (tout - tin).TotalMinutes;
        }
        double totalHours = totalMinutes / 60;
        int hours = (int)Math.Floor(totalHours);
        int minutes = (int)Math.Floor((totalHours - hours) * 60);
        txtTotalHours.Text = $"{hours}h {minutes}m";
    }

    // ═══════════ PAGINATION ═══════════

    private void ApplyPaging()
    {
        int total = _filtered.Count;
        int totalPages = Math.Max(1, (int)Math.Ceiling(total / (double)_pageSize));
        if (_page > totalPages) _page = totalPages;
        if (_page < 1) _page = 1;

        int skip = (_page - 1) * _pageSize;
        var pageRows = _filtered.Skip(skip).Take(_pageSize).ToList();

        // Cloud serial: totalRecords - (page*pageLength) - rowIndex  (descending)
        int sn = total - (_page - 1) * _pageSize;
        foreach (var row in pageRows) row.Sn = sn--;

        itemsList.ItemsSource = pageRows;
        emptyState.Visibility = total == 0 ? Visibility.Visible : Visibility.Collapsed;

        int start = total == 0 ? 0 : skip + 1;
        int end = Math.Min(skip + _pageSize, total);
        txtInfo.Text = $"Showing {start} to {end} of {total} entries";
        txtPage.Text = _page.ToString();
        btnPrev.IsEnabled = _page > 1;
        btnNext.IsEnabled = _page < totalPages;
    }

    // ═══════════ EVENTS ═══════════

    private void TxtSearch_TextChanged(object sender, TextChangedEventArgs e)
    {
        _page = 1;
        RefreshData();
    }

    private void CmbPageSize_SelectionChanged(object sender, SelectionChangedEventArgs e)
    {
        if (cmbPageSize.SelectedItem is not string s || !int.TryParse(s, out var size)) return;
        _pageSize = size;
        _page = 1;
        RefreshData();
    }

    private void BtnPrev_Click(object sender, RoutedEventArgs e)
    {
        if (_page > 1) { _page--; ApplyPaging(); }
    }

    private void BtnNext_Click(object sender, RoutedEventArgs e)
    {
        int totalPages = Math.Max(1, (int)Math.Ceiling(_filtered.Count / (double)_pageSize));
        if (_page < totalPages) { _page++; ApplyPaging(); }
    }

    private void BtnFilter_Click(object sender, RoutedEventArgs e)
    {
        filterSection.Visibility = filterSection.Visibility == Visibility.Visible ? Visibility.Collapsed : Visibility.Visible;
    }

    private void BtnApplyFilter_Click(object sender, RoutedEventArgs e)
    {
        _page = 1;
        RefreshData();
    }

    private void BtnAdd_Click(object sender, RoutedEventArgs e)
    {
        _dashboard?.ShowPage(new AttendanceFormPage(_dashboard));
    }

    private void BtnEdit_Click(object sender, RoutedEventArgs e)
    {
        if (sender is Button btn && btn.Tag is long id && _dashboard != null)
        {
            _dashboard.ShowPage(new AttendanceFormPage(_dashboard, id));
        }
    }

    private void BtnDelete_Click(object sender, RoutedEventArgs e)
    {
        if (sender is not Button btn || btn.Tag is not long id) return;
        if (MessageBox.Show("Are you sure you want to delete this attendance record?", "Confirm Delete",
                MessageBoxButton.YesNo, MessageBoxImage.Warning) != MessageBoxResult.Yes) return;
        try
        {
            using var conn = _db.GetConnection();
            using var cmd = conn.CreateCommand();
            cmd.CommandText = "UPDATE attendances SET del_status='Deleted', updated_at=@now WHERE id=@id";
            cmd.Parameters.AddWithValue("@now", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
            cmd.Parameters.AddWithValue("@id", id);
            cmd.ExecuteNonQuery();
            SyncService.EnqueueSync("attendances", id, "delete");
            _dashboard?.TriggerSync();
            RefreshData();
        }
        catch (Exception ex)
        {
            MessageBox.Show("Error deleting: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
        }
    }

    // ═══════════ EXPORT ═══════════

    private void BtnExport_Click(object sender, RoutedEventArgs e)
    {
        if (btnExport.ContextMenu != null)
        {
            btnExport.ContextMenu.PlacementTarget = btnExport;
            btnExport.ContextMenu.IsOpen = true;
        }
    }

    private void BtnExportPrint_Click(object sender, RoutedEventArgs e)
    {
        var dlg = new PrintDialog();
        if (dlg.ShowDialog() != true) return;
        var doc = new FlowDocument
        {
            PagePadding = new Thickness(30),
            FontFamily = new FontFamily("Segoe UI"),
            FontSize = 11
        };
        doc.Blocks.Add(new Paragraph(new Run("List Attendance")) { FontSize = 18, FontWeight = FontWeights.Bold, TextAlignment = TextAlignment.Center });
        doc.Blocks.Add(new Paragraph(new Run("Generated: " + DateTime.Now.ToString("dd MMM yyyy, hh:mm tt")))
        { FontSize = 10, Foreground = Brushes.Gray, TextAlignment = TextAlignment.Center });

        var table = new Table { CellSpacing = 0 };
        table.Columns.Add(new TableColumn { Width = new GridLength(1, GridUnitType.Auto) });
        table.Columns.Add(new TableColumn { Width = new GridLength(1, GridUnitType.Auto) });
        table.Columns.Add(new TableColumn { Width = new GridLength(1, GridUnitType.Auto) });
        table.Columns.Add(new TableColumn { Width = new GridLength(1, GridUnitType.Auto) });
        table.Columns.Add(new TableColumn { Width = new GridLength(1, GridUnitType.Auto) });
        table.Columns.Add(new TableColumn { Width = new GridLength(1, GridUnitType.Auto) });
        table.Columns.Add(new TableColumn { Width = new GridLength(1, GridUnitType.Auto) });
        table.Columns.Add(new TableColumn { Width = new GridLength(1, GridUnitType.Auto) });

        var header = new TableRowGroup();
        var hr = new TableRow();
        foreach (var h in new[] { "SN", "Reference No", "Date", "Employee", "In Time", "Out Time", "Total Time", "Note" })
        {
            var cell = new TableCell(new Paragraph(new Run(h)) { FontWeight = FontWeights.Bold });
            cell.BorderBrush = Brushes.DarkGray;
            cell.BorderThickness = new Thickness(0.6);
            cell.Padding = new Thickness(6, 4, 6, 4);
            hr.Cells.Add(cell);
        }
        header.Rows.Add(hr);
        table.RowGroups.Add(header);

        var body = new TableRowGroup();
        int sn = _filtered.Count;
        foreach (var row in _filtered)
        {
            var tr = new TableRow();
            foreach (var val in new[] { sn--.ToString(), row.ReferenceNo, row.Date, row.Employee, row.InTime, row.OutTime, row.TotalTime, row.Note })
            {
                var cell = new TableCell(new Paragraph(new Run(val)));
                cell.BorderBrush = Brushes.LightGray;
                cell.BorderThickness = new Thickness(0.6);
                cell.Padding = new Thickness(6, 3, 6, 3);
                tr.Cells.Add(cell);
            }
            body.Rows.Add(tr);
        }
        table.RowGroups.Add(body);
        doc.Blocks.Add(table);
        dlg.PrintDocument(((IDocumentPaginatorSource)doc).DocumentPaginator, "Attendance");
    }

    private void BtnExportExcel_Click(object sender, RoutedEventArgs e)
    {
        if (_filtered.Count == 0) { MessageBox.Show("No data to export.", "Info"); return; }
        var dlg = new SaveFileDialog { Filter = "CSV files (*.csv)|*.csv", FileName = "Attendance_" + DateTime.Now.ToString("yyyyMMdd_HHmmss") + ".csv" };
        if (dlg.ShowDialog() != true) return;
        try
        {
            using var sw = new StreamWriter(dlg.FileName, false, Encoding.UTF8);
            sw.WriteLine("SN,Reference No,Date,Employee,In Time,Out Time,Total Time,Note");
            int sn = _filtered.Count;
            foreach (var row in _filtered)
            {
                var vals = new[] { sn--.ToString(), row.ReferenceNo, row.Date, row.Employee, row.InTime, row.OutTime, row.TotalTime, row.Note };
                sw.WriteLine(string.Join(",", vals.Select(v => "\"" + v.Replace("\"", "\"\"") + "\"")));
            }
            MessageBox.Show("Exported to " + dlg.FileName, "Success", MessageBoxButton.OK, MessageBoxImage.Information);
        }
        catch (Exception ex)
        {
            MessageBox.Show("Export failed: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
        }
    }

    private void BtnExportPdf_Click(object sender, RoutedEventArgs e)
    {
        if (_filtered.Count == 0) { MessageBox.Show("No data to export.", "Info"); return; }
        var dlg = new SaveFileDialog { Filter = "PDF files (*.pdf)|*.pdf", FileName = "Attendance_" + DateTime.Now.ToString("yyyyMMdd_HHmmss") + ".pdf" };
        if (dlg.ShowDialog() != true) return;
        try
        {
            var rows = _filtered.ToList();
            QuestPDF.Settings.License = LicenseType.Community;
            Document.Create(container =>
            {
                container.Page(page =>
                {
                    page.Size(PageSizes.A4.Landscape());
                    page.Margin(30);
                    page.DefaultTextStyle(x => x.FontSize(9));
                    page.Header().Column(col =>
                    {
                        col.Item().Text("List Attendance").FontSize(16).Bold();
                        col.Item().PaddingTop(4).Text("Generated: " + DateTime.Now.ToString("dd MMM yyyy, hh:mm tt")).FontSize(9).FontColor("#64748B");
                    });
                    page.Content().PaddingTop(10).Table(table =>
                    {
                        table.ColumnsDefinition(c =>
                        {
                            c.ConstantColumn(30);
                            c.RelativeColumn(1.2f);
                            c.RelativeColumn(1.2f);
                            c.RelativeColumn(2);
                            c.RelativeColumn(1.2f);
                            c.RelativeColumn(1.2f);
                            c.RelativeColumn(1);
                            c.RelativeColumn(2);
                        });
                        table.Header(h =>
                        {
                            h.Cell().Background("#EEF2FF").Padding(5).Text("SN").Bold();
                            h.Cell().Background("#EEF2FF").Padding(5).Text("Reference No").Bold();
                            h.Cell().Background("#EEF2FF").Padding(5).Text("Date").Bold();
                            h.Cell().Background("#EEF2FF").Padding(5).Text("Employee").Bold();
                            h.Cell().Background("#EEF2FF").Padding(5).Text("In Time").Bold();
                            h.Cell().Background("#EEF2FF").Padding(5).Text("Out Time").Bold();
                            h.Cell().Background("#EEF2FF").Padding(5).Text("Total Time").Bold();
                            h.Cell().Background("#EEF2FF").Padding(5).Text("Note").Bold();
                        });
                        int sn = rows.Count;
                        foreach (var row in rows)
                        {
                            table.Cell().Padding(5).Text(sn--.ToString());
                            table.Cell().Padding(5).Text(row.ReferenceNo);
                            table.Cell().Padding(5).Text(row.Date);
                            table.Cell().Padding(5).Text(row.Employee);
                            table.Cell().Padding(5).Text(row.InTime);
                            table.Cell().Padding(5).Text(row.OutTime);
                            table.Cell().Padding(5).Text(row.TotalTime);
                            table.Cell().Padding(5).Text(row.Note);
                        }
                    });
                    page.Footer().AlignCenter().Text("Generated by Rashan Ki Dukan POS").FontSize(8).FontColor("#94A3B8");
                });
            }).GeneratePdf(dlg.FileName);
            PdfPreviewWindow.ShowPdf(dlg.FileName);
        }
        catch (Exception ex)
        {
            MessageBox.Show("PDF export failed: " + ex.Message, "Error", MessageBoxButton.OK, MessageBoxImage.Error);
        }
    }
}

public class AttendanceRow
{
    public long Id { get; set; }
    public int Sn { get; set; }
    public string ReferenceNo { get; set; } = "";
    public string Date { get; set; } = "";
    public string Employee { get; set; } = "";
    public string InTime { get; set; } = "";
    public string OutTime { get; set; } = "";
    public string TotalTime { get; set; } = "";
    public string Note { get; set; } = "";
}
