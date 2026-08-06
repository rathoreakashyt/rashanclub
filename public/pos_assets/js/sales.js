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
  
    // Sales DataTable - Initialize when modal is shown
    let salesDataTable = null;
    
    $(document).on('shown.bs.modal', '#modal_pos_sales', function () {
        if (!salesDataTable) {
            const route_path = route('sale.index', {}, false, Ziggy);
            
            const dt_basic_table = $('.datatables-basic-sales');
            if (dt_basic_table.length && !salesDataTable) {
                salesDataTable = new DataTable(dt_basic_table, {
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
                        { data: 'sale_no' },
                        { 
                            data: 'customer_name',
                            render: function(data, type, row) {
                                return data || 'Walk-in Customer';
                            }
                        },
                        { 
                            data: 'employee_name',
                            render: function(data, type, row) {
                                return data || 'N/A';
                            }
                        },
                        { data: 'sale_date' },
                        { data: 'grand_total' },
                        { data: 'paid_amount' },
                        { data: 'due_amount' },
                        { 
                            data: null,
                            orderable: false,
                            searchable: false,
                            render: function(data) {
                                const csrf = $('meta[name="csrf-token"]').attr('content');
                                const edit_url = route('sale.edit', { sale: data.encrypted_id }, false, Ziggy);
                                const destroy_url = route('sale.destroy', { sale: data.encrypted_id }, false, Ziggy);
                                const show_url = route('sale.show', { sale: data.encrypted_id }, false, Ziggy);
                                const print_url = route('sale.print-invoice', { sale: data.encrypted_id }, false, Ziggy);
                                const print_challan_url = route('sale.print-challan', { sale: data.encrypted_id }, false, Ziggy);
                                const download_pdf_url = route('sale.generate-pdf', { sale: data.encrypted_id }, false, Ziggy);
                                return `<div class="d-inline-flex datatable-action">
                                    <a href="${base_url+print_url}" target="_blank" class="btn btn-sm btn-icon" title="Print Invoice">
                                        <i class="ti tabler-printer"></i>
                                    </a>
                                    <a href="${base_url+print_challan_url}" target="_blank" class="btn btn-sm btn-icon" title="Print Challan">
                                        <i class="ti tabler-file-description"></i>
                                    </a>
                                    <a href="${base_url+download_pdf_url}" class="btn btn-sm btn-icon" title="Download PDF">
                                        <i class="ti tabler-download"></i>
                                    </a>
                                    <a href="${base_url+show_url}" class="btn btn-sm btn-icon" title="View">
                                        <i class="ti tabler-eye"></i>
                                    </a>
                                    <a href="${base_url+edit_url}" class="btn btn-sm btn-icon edit-record" title="Edit">
                                        <i class="ti tabler-edit"></i>
                                    </a>
                                    <button type="button" data-id="${ data.actual_id }" class="delete_data btn btn-sm btn-icon delete-record" title="Delete">
                                        <i class="ti tabler-trash delete"></i>
                                    </button>
                                    <form id="deleteForm-${ data.actual_id }" action="${ base_url+destroy_url }" method="POST" style="display: none;">
                                        <input type="hidden" name="_token" value="${ csrf }">
                                        <input type="hidden" name="_method" value="DELETE">
                                    </form>
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
            }
        } else {
            // Reload the table if it already exists
            salesDataTable.ajax.reload();
        }
    });
  
    // Delete sale functionality
    $(document).on('click', '.datatables-basic-sales .delete_data', function() {
        const saleId = $(this).data('id');
        const deleteForm = $('#deleteForm-' + saleId);
        
        if (confirm('Are you sure you want to delete this sale?')) {
            deleteForm.submit();
        }
    });
});
  
  