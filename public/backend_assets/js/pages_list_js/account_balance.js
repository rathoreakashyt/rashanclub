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
    let table;
    let summaryData = {};
    
    // Get route paths
    let accountBalanceRoute;
    try {
        accountBalanceRoute = route('accounting.reports.account-balance', {}, false, Ziggy);
    } catch (e) {
        // Fallback if route helper doesn't work
        accountBalanceRoute = '/account-reports/account-balance';
    }
  
    // Initialize DataTable
    function initTable() {
        if ($.fn.DataTable.isDataTable('#accountBalanceTable')) {
            $('#accountBalanceTable').DataTable().destroy();
        }
        
        table = $('#accountBalanceTable').DataTable({
            processing: true,
            serverSide: false,
            paging: false,
            searching: false,
            info: false,
            ajax: {
                url: base_url + accountBalanceRoute,
                type: 'GET',
                data: function(d) {
                    d.outlet_id = outletId;
                },
                dataSrc: function(json) {
                    if (json.success && json.data) {
                        summaryData = json.data.summary;
                        return json.data.accounts;
                    }
                    return [];
                },
                error: function(xhr, error, thrown) {
                    console.error('Account Balance AJAX error:', error, thrown);
                    $('#accountBalanceTable').html('<tr><td colspan="3" class="text-center text-danger">Failed to load data. Check console for details.</td></tr>');
                }
            },
            columns: [
                { data: 'sn' },
                { data: 'account_name' },
                { data: 'balance' }
            ],
            dom: '<"card-header border-bottom p-3"<"head-label"><"dt-action-buttons text-end"B>>' +
                '<"row"<"col-sm-12"tr>>',
            buttons: [
                {
                    extend: 'collection',
                    className: 'btn btn-primary dropdown-toggle me-2',
                    text: '<i class="ti tabler-file-export me-sm-1"></i> <span class="d-none d-sm-inline-block">Export</span>',
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
                    text: '<i class="ti tabler-filter me-sm-1"></i> <span class="d-none d-sm-inline-block">Filter</span>',
                    className: 'btn create-new btn-primary',
                    action: function (e, dt, node, config) {
                        $('#filterSection').slideToggle();
                        let isVisible = $('#filterSection').is(':visible');
                    }
                }
            ],
            drawCallback: function () {
                $('#totalBalance').text(summaryData.total_balance || '0');
            }

        });
    }
  
  
    // Apply filter
    $('#applyFilter').on('click', function() {
        outletId = $('#outlet_id').val();
        table.ajax.reload();
    });
    
    // Initialize
    initTable();
});