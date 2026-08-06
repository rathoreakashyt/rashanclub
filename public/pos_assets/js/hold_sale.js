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

    // Hold Sales DataTable - Initialize when modal is shown
    let holdsDataTable = null;
    
    $(document).on('shown.bs.modal', '#modal_pos_list_holds', function () {
        // Initialize DataTable for list view
        if (!holdsDataTable) {
            const route_path = route('pos.holds.get', {}, false, Ziggy);
            
            const dt_basic_table = $('.datatables-basic-holds');
            if (dt_basic_table.length && !holdsDataTable) {
                holdsDataTable = new DataTable(dt_basic_table, {
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
                        { data: 'hold_no' },
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
                        { data: 'total_items' },
                        { 
                            data: 'total_payable',
                            render: function(data, type, row) {
                                return parseFloat(data || 0).toFixed(2);
                            }
                        },
                        { 
                            data: 'date_time',
                            render: function(data, type, row) {
                                if (data) {
                                    return new Date(data).toLocaleString();
                                }
                                return '';
                            }
                        },
                        { 
                            data: null,
                            orderable: false,
                            searchable: false,
                            render: function(data) {
                                return `<div class="d-inline-flex datatable-action">
                                    <button class="btn btn-sm btn-icon preview-hold-btn" data-hold-id="${data.id}" title="Preview">
                                        <i class="ti tabler-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-icon convert-hold-btn" data-hold-id="${data.id}" title="Convert to Sale">
                                        <i class="ti tabler-arrow-right"></i>
                                    </button>
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
                                    exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
                                },
                                {
                                    extend: 'excel',
                                    text: '<i class="ti tabler-file-spreadsheet me-1"></i>'+language_key.Excel,
                                    className: 'dropdown-item',
                                    exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
                                },
                                {
                                    extend: 'pdf',
                                    text: '<i class="ti tabler-file-description me-1"></i>'+language_key.Pdf,
                                    className: 'dropdown-item',
                                    exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
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
            holdsDataTable.ajax.reload();
        }
    });

    // Initialize hold functionality
    initHoldFunctionality();

    function initHoldFunctionality() {
        // Add Hold Button Click
        $('.add-hold-btn').on('click', function() {
            // Check if cart has items
            if (typeof posCartManager !== 'undefined' && posCartManager.cartItems.length === 0) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('Cart is empty. Please add products to cart first.');
                } else {
                    alert('Cart is empty. Please add products to cart first.');
                }
                return;
            }
            
            // Get next hold number
            $.ajax({
                url: route('pos.hold.next-hold-no'),
                method: 'GET',
                success: function(response) {
                    if (response.status === 'success') {
                        $('#hold_reference_no').val(response.hold_no);
                        const modal = new bootstrap.Modal(document.getElementById('modal_pos_add_hold'));
                        modal.show();
                    }
                },
                error: function(xhr) {
                    console.error('Failed to get hold number:', xhr);
                    // Still show modal with empty field
                    const modal = new bootstrap.Modal(document.getElementById('modal_pos_add_hold'));
                    modal.show();
                }
            });
        });
        
        // Save Hold Button Click
        $('#btn_save_hold').on('click', function() {
            const holdNo = $('#hold_reference_no').val().trim();
            
            if (!holdNo) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('Please enter a hold reference number.');
                } else {
                    alert('Please enter a hold reference number.');
                }
                return;
            }
            
            // Check if cart has items
            if (typeof posCartManager === 'undefined' || posCartManager.cartItems.length === 0) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('Cart is empty. Please add products to cart first.');
                } else {
                    alert('Cart is empty. Please add products to cart first.');
                }
                return;
            }
            
            // Get cart data
            const cartItems = posCartManager.cartItems.map(item => ({
                product_id: item.product_id,
                product_type: item.product_type,
                quantity: item.quantity,
                unit_price: item.unit_price,
                discount: item.discount || 0,
                discount_type: item.discount_type || 'fixed',
                tax_information: item.tax_information || [],
                applicable_tax_id: item.applicable_tax_id || null,
                tax_type: item.tax_type || 'Inclusive',
                selected_imei_serial: item.selected_imei_serial || [],
                selected_medicine_expiry: item.selected_medicine_expiry || [],
                item_seller_id: item.item_seller_id || null,
                combo_items: item.combo_items || []
            }));
            
            // Get customer and employee
            const customerId = $('#customer-select').val();
            const employeeId = $('#employee-select').val();
            
            // Get cart summary
            const subtotal = parseFloat($('#pos-subtotal').text().replace(/,/g, '')) || 0;
            const tax = parseFloat($('#pos-tax').text().replace(/,/g, '')) || 0;
            const discount = posCartManager.cartSummaryDiscount || 0;
            const discountType = posCartManager.cartSummaryDiscountType || 'fixed';
            const shipping = posCartManager.cartSummaryShipping || 0;
            const totalPayable = parseFloat($('#pos-total-amount').text().replace(/,/g, '')) || 0;
            
            // Prepare data
            const holdData = {
                hold_no: holdNo,
                customer_id: customerId ? parseInt(customerId) : null,
                employee_id: employeeId ? parseInt(employeeId) : null,
                cart_items: cartItems,
                subtotal: subtotal,
                tax: tax,
                discount: discount,
                discount_type: discountType,
                shipping: shipping,
                total_payable: totalPayable
            };
            
            // Disable button
            const $btn = $('#btn_save_hold');
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');
            
            // Save hold
            $.ajax({
                url: route('pos.hold.save'),
                method: 'POST',
                data: holdData,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.status === 'success') {
                        // Clear cart
                        if (typeof posCartManager !== 'undefined') {
                            posCartManager.clearCart();
                        }
                        
                        // Close modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('modal_pos_add_hold'));
                        if (modal) {
                            modal.hide();
                        }
                        
                        // Reset form
                        $('#form_add_hold')[0].reset();
                        
                        // Reload holds datatable if it exists
                        if (holdsDataTable) {
                            holdsDataTable.ajax.reload();
                        }
                        
                        // Show success message
                        if (typeof showSuccessNotification !== 'undefined') {
                            showSuccessNotification('Hold saved successfully!');
                        } else {
                            alert('Hold saved successfully!');
                        }
                    } else {
                        if (typeof showErrorNotification !== 'undefined') {
                            showErrorNotification(response.message || 'Failed to save hold');
                        } else {
                            alert(response.message || 'Failed to save hold');
                        }
                    }
                },
                error: function(xhr) {
                    let errorMessage = 'Failed to save hold';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                        const errors = Object.values(xhr.responseJSON.errors).flat();
                        errorMessage = errors.join(', ');
                    }
                    
                    if (typeof showErrorNotification !== 'undefined') {
                        showErrorNotification(errorMessage);
                    } else {
                        alert(errorMessage);
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="icon-base ti tabler-device-floppy me-1"></i> Save Hold');
                }
            });
        });
        
        // List Hold Button Click
        $('.list-hold-btn').on('click', function() {
            const modal = new bootstrap.Modal(document.getElementById('modal_pos_list_holds'));
            modal.show();
        });
    }
    
    // Preview Hold Button Click
    $(document).on('click', '.preview-hold-btn', function() {
        const holdId = $(this).data('hold-id');
        previewHold(holdId);
    });
    
    // Preview Hold
    function previewHold(holdId) {
        const $content = $('#preview_hold_content');
        $content.html('<div class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
        
        const modal = new bootstrap.Modal(document.getElementById('modal_pos_preview_hold'));
        modal.show();
        
        $.ajax({
            url: route('pos.hold.get', { id: holdId }),
            method: 'GET',
            success: function(response) {
                if (response.status === 'success') {
                    const hold = response.data.hold;
                    const items = response.data.items;
                    
                    let html = '<div class="hold-preview">';
                    html += '<div class="row mb-3">';
                    html += '<div class="col-md-6"><strong>Hold No:</strong> ' + hold.hold_no + '</div>';
                    html += '<div class="col-md-6"><strong>Date:</strong> ' + new Date(hold.date_time).toLocaleString() + '</div>';
                    html += '</div>';
                    html += '<div class="row mb-3">';
                    html += '<div class="col-md-6"><strong>Customer:</strong> ' + hold.customer_name + '</div>';
                    html += '<div class="col-md-6"><strong>Employee:</strong> ' + hold.employee_name + '</div>';
                    html += '</div>';
                    html += '<hr>';
                    html += '<h6>Items:</h6>';
                    html += '<div class="table-responsive">';
                    html += '<table class="table table-sm table-bordered">';
                    html += '<thead><tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Discount</th><th>Total</th></tr></thead>';
                    html += '<tbody>';
                    
                    items.forEach(function(item) {
                        const itemSubtotal = item.quantity * item.unit_price;
                        const itemDiscount = item.discount || 0;
                        const itemDiscountType = item.discount_type || 'fixed';
                        let discountAmount = 0;
                        if (itemDiscountType === 'percentage') {
                            discountAmount = (itemSubtotal * itemDiscount) / 100;
                        } else {
                            discountAmount = itemDiscount;
                        }
                        const total = itemSubtotal - discountAmount;
                        
                        html += '<tr>';
                        html += '<td>' + item.product_name + ' (' + item.product_code + ')</td>';
                        html += '<td>' + item.quantity + '</td>';
                        html += '<td>' + parseFloat(item.unit_price).toFixed(2) + '</td>';
                        html += '<td>';
                        if (itemDiscount > 0) {
                            html += itemDiscount + (itemDiscountType === 'percentage' ? '%' : '') + '<br>';
                            html += '<small class="text-muted">(-' + parseFloat(discountAmount).toFixed(2) + ')</small>';
                        } else {
                            html += '-';
                        }
                        html += '</td>';
                        html += '<td>' + parseFloat(total).toFixed(2) + '</td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table></div>';
                    html += '<hr>';
                    html += '<div class="row">';
                    html += '<div class="col-md-6"><strong>Subtotal:</strong> ' + parseFloat(hold.subtotal).toFixed(2) + '</div>';
                    html += '<div class="col-md-6"><strong>Tax:</strong> ' + parseFloat(hold.tax).toFixed(2) + '</div>';
                    html += '</div>';
                    html += '<div class="row mt-2">';
                    html += '<div class="col-md-6"><strong>Discount:</strong> ' + parseFloat(hold.discount || 0).toFixed(2) + (hold.discount_type === 'percentage' ? '%' : '') + '</div>';
                    html += '<div class="col-md-6"><strong>Shipping:</strong> ' + parseFloat(hold.shipping || 0).toFixed(2) + '</div>';
                    html += '</div>';
                    html += '<div class="row mt-2">';
                    html += '<div class="col-md-12"><strong>Total Payable:</strong> ' + parseFloat(hold.total_payable).toFixed(2) + '</div>';
                    html += '</div>';
                    html += '</div>';
                    
                    $content.html(html);
                } else {
                    $content.html('<div class="alert alert-danger">Failed to load hold details</div>');
                }
            },
            error: function(xhr) {
                $content.html('<div class="alert alert-danger">Failed to load hold details</div>');
            }
        });
    }
    
    // Convert Hold to Sale Button Click
    $(document).on('click', '.convert-hold-btn', function() {
        const holdId = $(this).data('hold-id');
        const $btn = $(this);

        Swal.fire({
            title: 'Are you sure you want to convert this hold to sale?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, convert it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: route('pos.hold.convert', { id: holdId }),
                    method: 'GET',
                    success: function(response) {
                        if (response.status === 'success') {
                            // Load items into cart
                            if (typeof posCartManager !== 'undefined') {
                                // Clear current cart
                                posCartManager.clearCart();
                                
                                // Set customer and employee
                                if (response.customer_id) {
                                    $('#customer-select').val(response.customer_id).trigger('change');
                                }
                                if (response.employee_id) {
                                    $('#employee-select').val(response.employee_id).trigger('change');
                                }
                                
                                // Add items to cart
                                response.cart_items.forEach(function(item) {
                                    const cartItem = {
                                        product_id: item.product_id,
                                        product_name: item.product_name,
                                        product_code: item.product_code,
                                        product_type: item.product_type,
                                        quantity: item.quantity,
                                        unit_price: item.unit_price,
                                        discount: item.discount || 0,
                                        discount_type: item.discount_type || 'fixed',
                                        tax_information: item.tax_information || [],
                                        applicable_tax_id: item.applicable_tax_id || null,
                                        tax_type: item.tax_type || 'Inclusive',
                                        selected_imei_serial: item.selected_imei_serial || [],
                                        selected_medicine_expiry: item.selected_medicine_expiry || [],
                                        item_seller_id: item.item_seller_id || null,
                                        combo_items: item.combo_items || []
                                    };
                                    posCartManager.addItem(cartItem, true);
                                });
                                
                                // Set cart summary values
                                posCartManager.cartSummaryDiscount = response.discount || 0;
                                posCartManager.cartSummaryDiscountType = response.discount_type || 'fixed';
                                posCartManager.cartSummaryShipping = response.shipping || 0;
                                posCartManager.updateCartSummary();
                            }
                            
                            // Delete hold
                            $.ajax({
                                url: route('pos.hold.delete', { id: holdId }),
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                },
                                success: function(deleteResponse) {
                                    // Close modals
                                    const listModal = bootstrap.Modal.getInstance(document.getElementById('modal_pos_list_holds'));
                                    if (listModal) {
                                        listModal.hide();
                                    }
                                    
                                    // Reload holds datatable
                                    if (holdsDataTable) {
                                        holdsDataTable.ajax.reload();
                                    }
                                    
                                    // Show success message
                                    if (typeof showSuccessNotification !== 'undefined') {
                                        showSuccessNotification('Hold converted to sale successfully!');
                                    } else {
                                        alert('Hold converted to sale successfully!');
                                    }
                                },
                                error: function(xhr) {
                                    console.error('Failed to delete hold:', xhr);
                                    // Still show success as items are in cart
                                    if (typeof showSuccessNotification !== 'undefined') {
                                        showSuccessNotification('Hold items added to cart. Please delete the hold manually.');
                                    }
                                }
                            });
                        } else {
                            if (typeof showErrorNotification !== 'undefined') {
                                showErrorNotification(response.message || 'Failed to convert hold');
                            } else {
                                alert(response.message || 'Failed to convert hold');
                            }
                            $btn.prop('disabled', false).html('<i class="ti tabler-arrow-right"></i>');
                        }
                    },
                    error: function(xhr) {
                        let errorMessage = 'Failed to convert hold';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        
                        if (typeof showErrorNotification !== 'undefined') {
                            showErrorNotification(errorMessage);
                        } else {
                            alert(errorMessage);
                        }
                        $btn.prop('disabled', false).html('<i class="ti tabler-arrow-right"></i>');
                    }
                });


            }
        });
    });
});
