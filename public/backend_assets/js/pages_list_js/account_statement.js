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
    
    // Get route paths
    let accountStatementRoute, filterOptionsRoute;
    try {
        accountStatementRoute = route('accounting.reports.account-statement', {}, false, Ziggy);
        filterOptionsRoute = route('accounting.reports.filter-options', {}, false, Ziggy);
    } catch (e) {
        // Fallback if route helper doesn't work
        accountStatementRoute = '/account-reports/account-statement';
        filterOptionsRoute = '/account-reports/filter-options';
    }
    
    // Initialize DataTable (without loading data initially)
    function initTable() {
        if ($.fn.DataTable.isDataTable('#accountStatementTable')) {
            $('#accountStatementTable').DataTable().destroy();
        }
        
        table = $('#accountStatementTable').DataTable({
            processing: true,
            serverSide: false,
            paging: false,
            searching: false,
            info: false,
            data: [], // Start with empty data
            columns: [
                { data: 'sn' },
                { 
                    data: 'date',
                    render: function(data) {
                        return data || '-';
                    }
                },
                { data: 'title' },
                { 
                    data: 'debit',
                    render: function(data) {
                        return data || '-';
                    }
                },
                { 
                    data: 'credit',
                    render: function(data) {
                        return data || '-';
                    }
                },
                { 
                    data: 'balance',
                    render: function(data) {
                        return data || '-';
                    }
                },
                { 
                    data: 'added_date_time',
                    render: function(data) {
                        return data || 'N/A';
                    }
                }
            ],
            order: [[1, 'asc']],
            dom: '<"card-header border-bottom p-3"<"head-label"><"dt-action-buttons text-end"B>>' +
                '<"row"<"col-sm-12"tr>>',
            buttons: [
                {
                    extend: 'collection',
                    className: 'btn btn-primary dropdown-toggle me-2',
                    text: '<i class="ti tabler-file-export me-sm-1"></i> <span class="d-none d-sm-inline-block">'+language_key['Export']+'</span>',
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
                    text: '<i class="ti tabler-filter me-sm-1"></i> <span class="d-none d-sm-inline-block">'+language_key['Filter']+'</span>',
                    className: 'btn create-new btn-primary',
                    action: function (e, dt, node, config) {
                        $('#filterSection').slideToggle();
                        let isVisible = $('#filterSection').is(':visible');
                    }
                }
            ],
            drawCallback: function(settings) {
                let api = this.api();
                let totalDebit = 0;
                let totalCredit = 0;
                let closingBalance = 0;
                
                api.rows({search: 'applied'}).data().each(function(row) {
                    totalDebit += parseFloat(row.debit || 0);
                    totalCredit += parseFloat(row.credit || 0);
                    closingBalance = parseFloat(row.balance || 0);
                });
                
                $('#totalDebit').text(totalDebit.toFixed(2));
                $('#totalCredit').text(totalCredit.toFixed(2));
                $('#closingBalance').text(closingBalance.toFixed(2));
            }
        });
    }
    
    // Load filter options
    function loadFilterOptions() {
        $.ajax({
            url: base_url + filterOptionsRoute,
            type: 'GET',
            success: function (response) {
                if (response.success) {
                    // Load Payment Methods
                    if (response.data.payment_methods) {
                        let paymentMethodSelect = $('#payment_method_id');
                        
                        if (paymentMethodSelect.hasClass('select2-hidden-accessible')) {
                            paymentMethodSelect.select2('destroy');
                        }
                        
                        paymentMethodSelect.empty();
                        paymentMethodSelect.append(
                            '<option value="">' + (language_key.SelectPaymentMethod || 'Select Payment Method') + '</option>'
                        );
                        
                        response.data.payment_methods.forEach(function (pm) {
                            paymentMethodSelect.append(
                                '<option value="' + pm.id + '">' + pm.name + '</option>'
                            );
                        });
                        
                        paymentMethodSelect.select2({
                            dropdownParent: $('#filterSection'),
                            width: '100%',
                            placeholder: language_key.SelectPaymentMethod || 'Select Payment Method',
                            allowClear: true
                        });
                    }
                    
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
    
    // Load report data
    function loadReportData() {
        if (!paymentMethodId) {
            Swal.fire({
                icon: 'error',
                title: language_key.PleaseSelectPaymentMethod || 'Please select a Payment Method',
                showConfirmButton: false,
                timer: 1500
            });
            return;
        }
        
        $.ajax({
            url: base_url + accountStatementRoute,
            type: 'GET',
            data: {
                payment_method_id: paymentMethodId,
                outlet_id: outletId,
                date_from: dateFrom,
                date_to: dateTo
            },
            success: function(response) {
                if (response.success && response.data && response.data.statements) {
                    summaryData = response.data.summary || {};
                    table.clear();
                    table.rows.add(response.data.statements);
                    table.draw();
                } else {
                    table.clear().draw();
                }
            },
            error: function(xhr, error, thrown) {
                if (xhr.status === 400) {
                    let response = JSON.parse(xhr.responseText);
                    console.log(response.message)
                } 
                table.clear().draw();
            }
        });
    }
    
    // Apply filter
    $('#applyFilter').on('click', function() {
        paymentMethodId = $('#payment_method_id').val();
        if (!paymentMethodId) {
            Swal.fire({
                icon: 'error',
                title: language_key.PleaseSelectPaymentMethod || 'Please select a Payment Method',
                showConfirmButton: false,
                timer: 1500
            });
            return;
        }
        outletId = $('#outlet_id').val();
        dateFrom = $('#date_from').val();
        dateTo = $('#date_to').val();
        loadReportData();
    });
    
    // Reset filters
    $('#resetFilter').on('click', function() {
        paymentMethodId = '';
        outletId = '';
        dateFrom = '';
        dateTo = '';
        $('#payment_method_id').val('').trigger('change');
        $('#outlet_id').val('').trigger('change');
        $('#date_from').val('');
        $('#date_to').val('');
        $('#filterSection').hide();
        table.clear().draw();
        $('#totalDebit').text('0.00');
        $('#totalCredit').text('0.00');
        $('#closingBalance').text('0.00');
    });
    
    $('#filterSection').on('shown.bs.collapse shown', function () {
        if ($('#payment_method_id').hasClass('select2-hidden-accessible')) {
            $('#payment_method_id').select2('open').select2('close');
        }
        if ($('#outlet_id').hasClass('select2-hidden-accessible')) {
            $('#outlet_id').select2('open').select2('close');
        }
    });
    
    // Initialize
    loadFilterOptions();
    initTable();
});

