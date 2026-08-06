$(async function () {
    "use strict";
    /** #################### -- The base JS part should be on top of all JS files --  #################### **/
    // Language Translator
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
  
  
    // Company Info
    let company_data = $('#company_data').val();
    let company_session_data = {};
    try {
        company_session_data = JSON.parse(company_data);
    } catch (e) {
        console.error('Error parsing company info:', e);
    }
    /** #################### -- The base JS part should be on top of all JS files --  #################### **/

    let outletId = '';
    let dateFrom = '';
    let dateTo = '';
    let table;
    let summaryData = {};

    /** ================= Routes ================= */
    let trialBalanceRoute, filterOptionsRoute;
    try {
        trialBalanceRoute = route('accounting.reports.trial-balance', {}, false, Ziggy);
        filterOptionsRoute = route('accounting.reports.filter-options', {}, false, Ziggy);
    } catch (e) {
        trialBalanceRoute = '/account-reports/trial-balance';
        filterOptionsRoute = '/account-reports/filter-options';
    }
    /** ========================================== */

    /** ================= DataTable ================= */
    /** Debit/Credit and Total are formatted in the controller via formatAmount (GlobalHelper). */
    function initTable() {
        if ($.fn.DataTable.isDataTable('#trialBalanceTable')) {
            $('#trialBalanceTable').DataTable().destroy();
        }

        table = $('#trialBalanceTable').DataTable({
            processing: true,
            serverSide: false,
            paging: false,
            searching: false,
            info: false,
            data: [],
            columns: [
                { data: 'sn', className: 'text-center' },
                { data: 'title' },
                {
                    data: 'debit',
                    className: 'text-end',
                    render: function(data) {
                        return (data !== undefined && data !== null && data !== '') ? data : '';
                    }
                },
                {
                    data: 'credit',
                    // className: 'text-end',
                    render: function(data) {
                        return (data !== undefined && data !== null && data !== '') ? data : '';
                    }
                }
            ],
            order: [[0, 'asc']],
            dom: '<"card-header border-bottom p-3"<"head-label"><"dt-action-buttons text-end"B>>' +
                '<"row"<"col-sm-12"tr>>',
            buttons: [
                {
                    extend: 'collection',
                    className: 'btn btn-primary dropdown-toggle me-2',
                    text: '<i class="ti tabler-file-export me-1"></i> '+ (language_key['Export'] || 'Export'),
                    buttons: [
                        {
                            extend: 'print',
                            text: '<i class="ti tabler-printer me-1"></i>' + (language_key.Print || 'Print'),
                            className: 'dropdown-item',
                            exportOptions: { columns: [0, 1, 2, 3] }
                        },
                        {
                            extend: 'excel',
                            text: '<i class="ti tabler-file-spreadsheet me-1"></i>' + (language_key.Excel || 'Excel'),
                            className: 'dropdown-item',
                            exportOptions: { columns: [0, 1, 2, 3] }
                        },
                        {
                            extend: 'pdf',
                            text: '<i class="ti tabler-file-description me-1"></i>' + (language_key.Pdf || 'PDF'),
                            className: 'dropdown-item',
                            exportOptions: { columns: [0, 1, 2, 3] }
                        }
                    ]
                },
                {
                    text: '<i class="ti tabler-filter me-1"></i> '+ (language_key['Filter'] || 'Filter'),
                    className: 'btn btn-primary',
                    action: function () {
                        $('#filterSection').slideToggle();
                    }
                }
            ],
            drawCallback: function() {
                if (summaryData && summaryData.totalDebit !== undefined && summaryData.totalCredit !== undefined) {
                    $('#totalDebit').text(summaryData.totalDebit);
                    $('#totalCredit').text(summaryData.totalCredit);
                } else {
                    $('#totalDebit').text('');
                    $('#totalCredit').text('');
                }
            }
        });
    }
    /** ============================================ */

    /** ================= Load Filter Options ================= */
    function loadFilterOptions() {
        $.ajax({
            url: base_url + filterOptionsRoute,
            type: 'GET',
            success: function (response) {
                if (response.success) {
                    // Load Outlets
                    if (response.data.outlets) {
                        let outletSelect = $('#outlet_id');
                        if (outletSelect.hasClass('select2-hidden-accessible')) {
                            outletSelect.select2('destroy');
                        }
                        outletSelect.empty();
                        outletSelect.append(
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
            }
        });
    }

    /** ================= Load Report Data ================= */
    function loadReportData() {
        $.ajax({
            url: base_url + trialBalanceRoute,
            type: 'GET',
            data: {
                outlet_id: outletId,
                date_from: dateFrom,
                date_to: dateTo
            },
            success: function(response) {
                if (response.success && response.data && response.data.trialBalance) {
                    summaryData = response.data.summary || {};
                    table.clear();
                    table.rows.add(response.data.trialBalance);
                    table.draw();
                } else {
                    summaryData = {};
                    table.clear().draw();
                }
            },
            error: function() {
                alert(language_key.ErrorLoadingData || 'Error loading data');
                summaryData = {};
                table.clear().draw();
            }
        });
    }

    /** ================= Actions ================= */
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
        summaryData = {};
        table.clear().draw();
        $('#totalDebit').text('');
        $('#totalCredit').text('');
    });
    /** ============================================ */

    /** ================= Init ================= */
    loadFilterOptions();
    initTable();
    // Load data on page load
    loadReportData();
});

