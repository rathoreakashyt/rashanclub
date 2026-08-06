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

    // Sale Returns DataTable - Initialize when modal is shown
    // Make it globally accessible so it can be accessed from add_sale_return.js
    window.saleReturnsDataTable = null;
    let saleReturnsDataTable = null;
    
    $(document).on('shown.bs.modal', '#modal_pos_sale_returns', function () {
        // Initialize DataTable for list view
        if (!saleReturnsDataTable) {
            const route_path = route('sale-return.index', {}, false, Ziggy);
            
            const dt_basic_table = $('.datatables-basic-sale-returns');
            if (dt_basic_table.length && !saleReturnsDataTable) {
                saleReturnsDataTable = new DataTable(dt_basic_table, {
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: base_url + route_path,
                        type: 'GET',
                        error: function(xhr, error, thrown) {
                            console.error('DataTable error:', error);
                        }
                    },
                    columns: [
                        { data: 'id' },
                        { data: 'reference_no' },
                        { 
                            data: 'customer_name',
                            render: function(data, type, row) {
                                return data || 'Walk-in Customer';
                            }
                        },
                        { data: 'sale_no' },
                        { data: 'date' },
                        { data: 'total_return_amount' },
                        { data: 'paid' },
                        { data: 'due' },
                        { 
                            data: null,
                            orderable: false,
                            searchable: false,
                            render: function(data) {
                                const csrf = $('meta[name="csrf-token"]').attr('content');
                                const show_url = route('sale-return.show', { sale_return: data.encrypted_id }, false, Ziggy);
                                const print_url = route('sale-return.print-invoice', { sale_return: data.encrypted_id }, false, Ziggy);
                                const download_pdf_url = route('sale-return.generate-pdf', { sale_return: data.encrypted_id }, false, Ziggy);
                                return `<div class="d-inline-flex datatable-action">
                                    <a href="${base_url+print_url}" target="_blank" class="btn btn-sm btn-icon" title="Print Invoice">
                                        <i class="ti tabler-printer"></i>
                                    </a>
                                    <a href="${base_url+download_pdf_url}" class="btn btn-sm btn-icon" title="Download PDF">
                                        <i class="ti tabler-download"></i>
                                    </a>
                                    <a href="${base_url+show_url}" target="_blank" class="btn btn-sm btn-icon" title="View">
                                        <i class="ti tabler-eye"></i>
                                    </a>
                                </div>`;
                            }
                        }
                    ],
                    dom: '<"card-header border-bottom p-3"<"head-label"><"dt-action-buttons text-end"B>>' +
                        '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                        '<"row"<"col-sm-12"tr>>' +
                        '<"row"<"col-sm-12 col-md-5 d-flex align-items-center"i><"col-sm-12 col-md-7 d-flex align-items-center justify-content-end"p>>',
                    buttons: [
                        {
                            extend: 'collection',
                            className: 'btn btn-primary dropdown-toggle me-2',
                            text: '<i class="ti tabler-file-export me-sm-1"></i> <span class="d-none d-sm-inline-block">Export</span>',
                            buttons: [
                                {
                                    extend: 'print',
                                    text: '<i class="ti tabler-printer me-1" ></i>'+language_key.Print,
                                    className: 'dropdown-item',
                                    exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] }
                                },
                                {
                                    extend: 'excel',
                                    text: '<i class="ti tabler-file-spreadsheet me-1"></i>'+language_key.Excel,
                                    className: 'dropdown-item',
                                    exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] }
                                },
                                {
                                    extend: 'pdf',
                                    text: '<i class="ti tabler-file-description me-1"></i>'+language_key.Pdf,
                                    className: 'dropdown-item',
                                    exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] }
                                }
                            ]
                        }
                    ],
                    lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                    pageLength: 10,
                    order: [[0, 'desc']]
                });
                
                // Make it globally accessible
                window.saleReturnsDataTable = saleReturnsDataTable;
            }
        } else {
            // Reload the table if it already exists
            saleReturnsDataTable.ajax.reload();
            window.saleReturnsDataTable = saleReturnsDataTable;
        }
    });
    
    // Listen for custom event to reload the table
    $(document).on('saleReturnCreated', function() {
        if (window.saleReturnsDataTable) {
            window.saleReturnsDataTable.ajax.reload(null, false); // false = don't reset paging
        }
    });

    // Load form when create tab is shown
    $(document).on('shown.bs.tab', '#pos_sale_returns_create_tab', function () {
        loadPosSaleReturnForm();
    });

    // Function to load POS sale return form
    function loadPosSaleReturnForm() {
        const container = $('#pos_sale_return_form_container');
        
        // Check if form is already loaded
        if (container.find('#saleReturnForm').length > 0) {
            return;
        }
        
        // Show loading
        container.html(`
            <div class="text-center p-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Loading form...</p>
            </div>
        `);
        
        // Fetch form data
        $.ajax({
            type: "GET",
            url: base_url + route('sale-return.get-pos-form-data', {}, false, Ziggy),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.success && response.data) {
                    // Render form using the partial
                    renderPosSaleReturnForm(response.data);
                } else {
                    container.html(`
                        <div class="alert alert-danger">
                            <i class="ti tabler-alert-circle me-1"></i>
                            Failed to load form data. Please try again.
                        </div>
                    `);
                }
            },
            error: function (xhr) {
                container.html(`
                    <div class="alert alert-danger">
                        <i class="ti tabler-alert-circle me-1"></i>
                        Failed to load form data. Please try again.
                    </div>
                `);
            }
        });
    }

    // Function to render POS sale return form
    function renderPosSaleReturnForm(data) {
        const container = $('#pos_sale_return_form_container');
        
        // Build form HTML
        let formHtml = `
            <form action="${base_url + route('sale-return.store', {}, false, Ziggy)}" method="POST" id="saleReturnForm" enctype="multipart/form-data" data-is-pos="true">
                <input type="hidden" name="_token" value="${$('meta[name="csrf-token"]').attr('content')}">
                
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5 validate_wrapper">
                                <label class="form-label" for="reference_no">Reference No <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" placeholder="Reference No" name="reference_no" id="reference_no" value="${data.reference_no}" readonly />
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5 validate_wrapper">
                                <label class="form-label" for="date">Date <span class="text-danger">*</span></label>
                                <input type="text" class="form-control datePicker" placeholder="Date" name="date" id="date" value="${new Date().toISOString().split('T')[0]}" />
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5 validate_wrapper">
                                <label class="form-label" for="customer_id">Customer <span class="text-danger">*</span></label>
                                <select id="customer_id" name="customer_id" class="select2 form-select" data-placeholder="Select Customer">
                                    <option value="">Select Customer</option>
        `;
        
        // Add customers
        data.customers.forEach(function(customer) {
            formHtml += `<option value="${customer.id}">${customer.name}</option>`;
        });
        
        formHtml += `
                                </select>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5 validate_wrapper">
                                <label class="form-label" for="sale_id">Sale Invoice <span class="text-danger">*</span></label>
                                <select id="sale_id" name="sale_id" class="select2 form-select" data-placeholder="Select Sale Invoice">
                                    <option value="">Select Sale Invoice</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5 validate_wrapper">
                                <label class="form-label" for="sale_item_id">Sale Items</label>
                                <select id="sale_item_id" class="form-select select2" data-placeholder="Select Sale Item">
                                    <option value="">Select Sale Item</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th class="w-5">SN</th>
                                            <th class="w-20">Item - Code - Brand</th>
                                            <th class="w-15">IMEI/Serial</th>
                                            <th class="w-10">Sale Qty</th>
                                            <th class="w-10">Return Qty</th>
                                            <th class="w-10">Unit Price</th>
                                            <th class="w-10">Return Price</th>
                                            <th class="w-10">Total</th>
                                            <th class="w-5">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="saleReturnItems">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row justify-content-end">
                        <div class="col-12 col-md-6 col-lg-4">
                            <h5 class="mb-4" id="totalItemCount">Total Item 0 (0)</h5>
                        </div>
                        <div class="clear-fix"></div>
                        
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5">
                                <label class="form-label" for="grandTotal">Grand Total</label>
                                <input type="text" placeholder="0.00" class="form-control" id="grandTotal" name="grandTotal" readonly>
                            </div>
                        </div>
                        <div class="clear-fix"></div>
                        
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5 validate_wrapper">
                                <label class="form-label" for="payment_method_id">Payment Method <span class="text-danger">*</span></label>
                                <select id="payment_method_id" name="payment_method_id" class="select2 form-select" data-placeholder="Select Payment Method">
                                    <option value="">Select Payment Method</option>
        `;
        
        // Add payment methods
        data.payment_methods.forEach(function(method) {
            formHtml += `<option value="${method.id}">${method.name}</option>`;
        });
        
        formHtml += `
                                </select>
                            </div>
                        </div>
                        <div class="clear-fix"></div>
                        
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5 validate_wrapper">
                                <label class="form-label" for="paid">Paid Amount <span class="text-danger">*</span></label>
                                <input type="text" placeholder="0.00" class="form-control" id="paid" name="paid" readonly>
                            </div>
                        </div>
                        <div class="clear-fix"></div>
                        
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5">
                                <label class="form-label" for="due">Due Amount</label>
                                <input type="text" placeholder="0.00" class="form-control" id="due" name="due" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" name="submit" value="submit" class="btn btn-primary add_item_submit">
                        <i class="ti tabler-check me-1"></i> Submit
                    </button>
                </div>
            </form>
        `;
        
        container.html(formHtml);
        
        // Initialize Select2 and date picker after a short delay to ensure DOM is ready
        setTimeout(function() {
            if (typeof $ !== 'undefined' && $.fn.select2) {
                container.find('.select2').each(function() {
                    if (!$(this).hasClass('select2-hidden-accessible')) {
                        $(this).select2({
                            dropdownParent: $('#modal_pos_sale_returns')
                        });
                    }
                });
            }
            
            // Initialize date picker using the exposed function from add_sale_return.js
            if (typeof window.initializeSaleReturnDatePickers === 'function') {
                window.initializeSaleReturnDatePickers(container[0]);
            } else if (typeof $ !== 'undefined' && $.fn.flatpickr) {
                container.find('.datePicker').each(function() {
                    if (!$(this).data('flatpickr')) {
                        $(this).flatpickr({
                            altInput: true,
                            altFormat: 'Y-m-d',
                            dateFormat: 'Y-m-d',
                            static: true,
                            allowInput: true
                        });
                    }
                });
            }
        }, 100);
        
        // The add_sale_return.js will handle the form submission and events
        // since it's already included in the POS layout
    }
});
