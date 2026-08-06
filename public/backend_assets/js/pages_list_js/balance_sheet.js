$(async function () {
    "use strict";
    let base_url = $('#base_url').val();
    let language_name = $('#language_name').val();
    let language_path = '/resources/lang/' + language_name + '.json';
    let language_file = base_url + language_path;
    let language_key = {};

    async function loadLanguage() {
        try {
            const res = await fetch(language_file);
            language_key = await res.json();
        } catch (err) {
            console.error('Failed to load language file:', err);
        }
    }
    await loadLanguage();

    let outletId = '';
    let dateFrom = '';
    let dateTo = '';
    let table;

    let balanceSheetRoute, filterOptionsRoute;
    try {
        balanceSheetRoute = route('accounting.reports.balance-sheet', {}, false, Ziggy);
        filterOptionsRoute = route('accounting.reports.filter-options', {}, false, Ziggy);
    } catch (e) {
        balanceSheetRoute = '/account-reports/balance-sheet';
        filterOptionsRoute = '/account-reports/filter-options';
    }

    function formatAmount(val) {
        return parseFloat(val || 0).toFixed(2);
    }

    /** Build flat row array for DataTable from API data (Assets, Liabilities, Summary) */
    function buildTableRows(data) {
        if (!data || !data.assets) return [];
        var assets = data.assets || [];
        var liabilities = data.liabilities || [];
        var summary = data.summary || {};
        var totalAssets = (summary.totalAssets != null) ? summary.totalAssets : assets.reduce(function (s, r) { return s + parseFloat(r.amount || 0); }, 0);
        var totalLiabilities = (summary.totalLiabilities != null) ? summary.totalLiabilities : liabilities.reduce(function (s, r) { return s + parseFloat(r.amount || 0); }, 0);
        var netWorth = (summary.netWorth != null) ? summary.netWorth : (totalAssets - totalLiabilities);
        var rows = [];
        var AssetsLabel = language_key['Assets'] || 'Assets';
        var LiabilitiesLabel = language_key['Liabilities'] || 'Liabilities';
        var SummaryLabel = language_key['Summary'] || 'Summary';
        var TotalAssetsLabel = language_key['Total Assets'] || 'Total Assets';
        var TotalLiabilitiesLabel = language_key['Total Liabilities'] || 'Total Liabilities';
        var NetWorthLabel = language_key['Net Worth'] || 'Net Worth';

        // Assets section
        rows.push({ sn: '', title: AssetsLabel, amount: '', _rowType: 'section' });
        assets.forEach(function (row) {
            rows.push({ sn: row.sn, title: row.title || '', amount: row.amount, _rowType: 'data' });
        });
        rows.push({ sn: '', title: TotalAssetsLabel + ':', amount: totalAssets, _rowType: 'total' });

        // Liabilities section
        rows.push({ sn: '', title: LiabilitiesLabel, amount: '', _rowType: 'section' });
        liabilities.forEach(function (row) {
            rows.push({ sn: row.sn, title: row.title || '', amount: row.amount, _rowType: 'data' });
        });
        rows.push({ sn: '', title: TotalLiabilitiesLabel + ':', amount: totalLiabilities, _rowType: 'total' });

        // Summary section
        rows.push({ sn: '', title: SummaryLabel, amount: '', _rowType: 'section' });
        rows.push({ sn: '', title: TotalAssetsLabel + ':', amount: totalAssets, _rowType: 'summary' });
        rows.push({ sn: '', title: TotalLiabilitiesLabel + ':', amount: totalLiabilities, _rowType: 'summary' });
        rows.push({ sn: '', title: NetWorthLabel + ':', amount: netWorth, _rowType: 'total' });

        return rows;
    }

    function initTable() {
        if ($.fn.DataTable.isDataTable('#balanceSheetTable')) {
            $('#balanceSheetTable').DataTable().destroy();
        }
        table = $('#balanceSheetTable').DataTable({
            processing: true,
            serverSide: false,
            paging: false,
            searching: false,
            info: false,
            data: [],
            columns: [
                { data: 'sn' },
                { data: 'title' },
                {
                    data: 'amount',
                    className: 'text-end',
                    render: function (data, type) {
                        if (data === '' || data === null || data === undefined) return '';
                        return (data);
                    }
                }
            ],
            order: [],
            dom: '<"card-header border-bottom p-3"<"head-label"><"dt-action-buttons text-end"B>>' +
                '<"row"<"col-sm-12"tr>>',
            buttons: [
                {
                    extend: 'collection',
                    className: 'btn btn-primary dropdown-toggle me-2',
                    text: '<i class="ti tabler-file-export me-sm-1"></i> <span class="d-none d-sm-inline-block">' + (language_key['Export'] || 'Export') + '</span>',
                    buttons: [
                        {
                            extend: 'print',
                            text: '<i class="ti tabler-printer me-1"></i>' + (language_key.Print || 'Print'),
                            className: 'dropdown-item',
                            exportOptions: { columns: [0, 1, 2] }
                        },
                        {
                            extend: 'excel',
                            text: '<i class="ti tabler-file-spreadsheet me-1"></i>' + (language_key.Excel || 'Excel'),
                            className: 'dropdown-item',
                            exportOptions: { columns: [0, 1, 2] }
                        },
                        {
                            extend: 'pdf',
                            text: '<i class="ti tabler-file-description me-1"></i>' + (language_key.Pdf || 'PDF'),
                            className: 'dropdown-item',
                            exportOptions: { columns: [0, 1, 2] }
                        }
                    ]
                },
                {
                    text: '<i class="ti tabler-filter me-sm-1"></i> <span class="d-none d-sm-inline-block">' + (language_key['Filter'] || 'Filter') + '</span>',
                    className: 'btn create-new btn-primary',
                    action: function () {
                        $('#filterSection').slideToggle();
                    }
                }
            ],
            createdRow: function (row, data) {
                if (data._rowType === 'section') {
                    $(row).addClass('table fw-bold');
                } else if (data._rowType === 'total' || data._rowType === 'summary') {
                    $(row).addClass('table fw-bold');
                }
            }
        });
    }

    function loadFilterOptions() {
        $.ajax({
            url: base_url + filterOptionsRoute,
            type: 'GET',
            success: function (response) {
                if (response.success && response.data && response.data.outlets) {
                    var outletSelect = $('#outlet_id');
                    if (outletSelect.hasClass('select2-hidden-accessible')) {
                        outletSelect.select2('destroy');
                    }
                    outletSelect.empty().append(
                        '<option value="">' + (language_key.AllOutlets || 'All Outlets') + '</option>'
                    );
                    response.data.outlets.forEach(function (outlet) {
                        outletSelect.append(
                            '<option value="' + outlet.id + '">' + outlet.name + '</option>'
                        );
                    });
                    outletSelect.select2({
                        dropdownParent: $('#filterSection'),
                        width: '100%',
                        placeholder: language_key.AllOutlets || 'All Outlets',
                        allowClear: true
                    });
                }
            }
        });
    }

    function loadReportData() {
        $.ajax({
            url: base_url + balanceSheetRoute,
            type: 'GET',
            data: {
                outlet_id: outletId,
                date_from: dateFrom,
                date_to: dateTo
            },
            success: function (response) {
                if (response.success && response.data) {
                    var rows = buildTableRows(response.data);
                    table.clear();
                    table.rows.add(rows);
                    table.draw();
                } else {
                    table.clear().draw();
                }
            },
            error: function () {
                alert(language_key.ErrorLoadingData || 'Error loading data');
                table.clear().draw();
            }
        });
    }

    $('#applyFilter').on('click', function () {
        outletId = $('#outlet_id').val();
        dateFrom = $('#date_from').val();
        dateTo = $('#date_to').val();
        loadReportData();
    });

    $('#resetFilter').on('click', function () {
        outletId = '';
        dateFrom = '';
        dateTo = '';
        $('#outlet_id').val('').trigger('change');
        $('#date_from').val('');
        $('#date_to').val('');
        $('#filterSection').hide();
        table.clear().draw();
    });

    loadFilterOptions();
    initTable();
    loadReportData();
});
