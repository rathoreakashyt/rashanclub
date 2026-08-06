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

    let paymentMethodId = '';
    let outletId = '';
    let dateFrom = '';
    let dateTo = '';
    let table;
    let summaryData = {};

    /** ================= Routes ================= */
    let transactionHistoryRoute;
    try {
        transactionHistoryRoute = route('accounting.reports.transaction-history', {}, false, Ziggy);
    } catch (e) {
        transactionHistoryRoute = '/account-reports/transaction-history';
    }
    /** ========================================== */

    /** ================= DataTable ================= */
    function initTable() {
        if ($.fn.DataTable.isDataTable('#transactionHistoryTable')) {
            $('#transactionHistoryTable').DataTable().destroy();
        }

        table = $('#transactionHistoryTable').DataTable({
            processing: true,
            serverSide: false,
            paging: false,
            searching: false,
            info: false,
            ajax: {
                url: base_url + transactionHistoryRoute,
                type: 'GET',
                data: function (d) {
                    d.payment_method_id = paymentMethodId;
                    d.outlet_id = outletId;
                    d.date_from = dateFrom;
                    d.date_to = dateTo;
                },
                dataSrc: function (json) {
                    if (json.success && json.data?.transactions) {
                        summaryData = json.data.summary || {};
                        return json.data.transactions;
                    }
                    return [];
                },
                // error: function () {
                //     window.location.reload();
                // }
            },
            columns: [
                { data: 'sn' },
                { data: 'date' },
                { data: 'reference_no' },
                { data: 'type' },
                { data: 'payment_method' },
                { data: 'amount' },
                { data: 'created_at' }
            ],
            order: [[1, 'desc']],
            dom:
                '<"card-header border-bottom p-3"<"head-label"><"dt-action-buttons text-end"B>>' +
                '<"row"<"col-sm-12"tr>>',
            buttons: [
                {
                    extend: 'collection',
                    className: 'btn btn-primary dropdown-toggle me-2',
                    text: '<i class="ti tabler-file-export me-1"></i> '+language_key['Export'],
                    buttons: [
                        {
                            extend: 'print',
                            text: '<i class="ti tabler-printer me-1"></i>' + (language_key.Print || 'Print'),
                            className: 'dropdown-item',
                            exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
                        },
                        {
                            extend: 'excel',
                            text: '<i class="ti tabler-file-spreadsheet me-1"></i>' + (language_key.Excel || 'Excel'),
                            className: 'dropdown-item',
                            exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
                        },
                        {
                            extend: 'pdf',
                            text: '<i class="ti tabler-file-description me-1"></i>' + (language_key.Pdf || 'PDF'),
                            className: 'dropdown-item',
                            exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
                        }
                    ]
                },
                {
                    text: '<i class="ti tabler-filter me-1"></i> '+language_key['Filter'],
                    className: 'btn btn-primary',
                    action: function () {
                        $('#filterSection').slideToggle();
                        let isVisible = $('#filterSection').is(':visible');
                    }
                }
            ],
        });
    }
    /** ============================================ */

    

    /** ================= Actions ================= */
    $('#applyFilter').on('click', function () {
        paymentMethodId = $('#payment_method_id').val();
        outletId = $('#outlet_id').val();
        dateFrom = $('#date_from').val();
        dateTo = $('#date_to').val();
        
        // Validate payment method is required
        if (!paymentMethodId) {
            $('.payment_method_id .invalid-feedback').text(language_key.PaymentMethodRequired || 'Payment Method is required');
            return;
        } else {
            $('.payment_method_id .invalid-feedback').text('');
        }
        
        table.ajax.reload();
    });
    /** ============================================ */

    /** ================= Init ================= */
    // Initialize table but don't load data until filter is applied
    initTable();
    // Clear the table initially
    table.clear().draw();
});
