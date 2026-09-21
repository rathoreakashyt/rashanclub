
$(function() {
    'use strict';

    // Get base URL
    let base_url =  $('#base_url').val();
    // Company Info
    let company_data = $('#company_data').val();
    let company_session_data;
    try {
        company_session_data = JSON.parse(company_data);
    } catch (e) {
        console.error('Error parsing company info:', e);
        company_session_data = {};
    }

    // Discount toggle (Percentage/Amount)
    $('.pos-toggle-btn').on('click', function() {
        $(this).siblings('.pos-toggle-btn').removeClass('active');
        $(this).addClass('active');
    });

    // Make internetConnected globally accessible (start with navigator.onLine)
    window.internetConnected = navigator.onLine;
    
    // Offline sales sync helper
    async function checkAndSyncOfflineSales(showNotifications = true) {
        if (typeof posIndexedDB === 'undefined') return { success: 0, errors: 0, total: 0 };
        if (!navigator.onLine && !window.internetConnected) return { success: 0, errors: 0, total: 0 };
        try {
            const result = await posIndexedDB.syncOfflineSales();
            updateOfflineSalesBadge();
            if (showNotifications) {
                if (result.success > 0) {
                    showSuccessNotification(`Successfully synced ${result.success} offline sale(s)`);
                }
                if (result.errors > 0) {
                    const firstError = result.errorDetails && result.errorDetails[0]?.error;
                    if (firstError && firstError.includes('register')) {
                        showErrorNotification(firstError);
                    } else {
                        showErrorNotification(`Failed to sync ${result.errors} sale(s). ` + (firstError ? 'Error: ' + firstError : ''));
                    }
                }
            }
            return result;
        } catch (error) {
            console.error('Error syncing offline sales:', error);
            return { success: 0, errors: 0, total: 0 };
        }
    }

    // Update offline sales badge visibility
    async function updateOfflineSalesBadge() {
        if (typeof posIndexedDB === 'undefined') return;
        try {
            const count = await posIndexedDB.getUnsyncedSalesCount();
            const $btn = $('#pos-offline-sync-btn');
            const $badge = $btn.find('.offline-sales-badge');
            if (count > 0) {
                $btn.show();
                $badge.text(count).show();
            } else {
                $btn.hide();
                $badge.hide();
            }
        } catch (e) {
            // ignore
        }
    }

    window.addEventListener('online', () => {
        window.internetConnected = true;
        showInfoNotification('Internet connection restored. Syncing offline sales...');
        setTimeout(() => checkAndSyncOfflineSales(), 1500);
    });

    window.addEventListener('offline', () => {
        window.internetConnected = false;
        showWarningNotification('Internet connection lost. Sales will be saved offline.');
        updateOfflineSalesBadge();
    });

    // Manual sync button
    $(document).on('click', '#pos-offline-sync-btn', function () {
        const $btn = $(this);
        $btn.find('i').addClass('spin-animation');
        checkAndSyncOfflineSales(true).finally(() => {
            $btn.find('i').removeClass('spin-animation');
        });
    });

    // Periodic auto-sync every 30 seconds
    setInterval(() => checkAndSyncOfflineSales(false), 30000);

    // Check unsynced sales on page load (after IndexedDB is ready)
    setTimeout(() => {
        updateOfflineSalesBadge();
        if (navigator.onLine && window.internetConnected) {
            checkAndSyncOfflineSales(false);
        }
    }, 3000);

    // function showOfflineUI() {
    //     alert('Internet disconnected');
    // }
    // function verifyInternetOnce() {
    //     alert('Internet connected');
    // }



    // Toggle GSTIN required indicator based on business type (B2B = required, B2C = optional)
    function togglePosGstinRequired() {
        var bt = $('#pos_customer_business_type').val();
        $('.pos-gstin-required').css('display', bt === 'B2B' ? '' : 'none');
    }

    // Clear customer form
    function clearCustomerForm() {
        $('#posCustomerForm')[0].reset();
        $('#pos_customer_id').val('');
        $('#pos_customer_modal_title').text('Add Customer');
        $('#pos_customer_opening_balance').val('0');
        $('#pos_customer_credit_limit').val('0');
        $('#pos_customer_discount').val('0');
        $('#pos_customer_opening_balance_type').val('Debit').trigger('change');
        $('#pos_customer_type').val('1').trigger('change');
        $('#pos_customer_business_type').val('B2C').trigger('change');
        togglePosGstinRequired();
        $('#pos_customer_same_or_diff_state').val('1').trigger('change');
        $('#pos_customer_state_id').val('').trigger('change');
        $('#pos_customer_gst_number').val('');
        clearValidationErrors();
    }

    // Clear validation errors
    function clearValidationErrors() {
        $('#posCustomerForm .is-invalid').removeClass('is-invalid');
        $('#posCustomerForm .invalid-feedback').remove();
    }


    function showValidationErrors(errors) {
        clearValidationErrors();
        $.each(errors, function (field, messages) {
            let fieldName = field.replace(/\.(\w+)/g, '[$1]');
            let input = $('[name="' + fieldName + '"]');
            if (input.length > 0) {
                input.addClass('is-invalid');
                let wrapper = input.closest('.validate_wrapper');
                if (wrapper.length === 0) {
                    wrapper = input.parent();
                }
                if (wrapper.find('.invalid-feedback').length === 0) {
                    wrapper.append(
                        '<div class="invalid-feedback">' + messages[0] + '</div>'
                    );
                }
            }
        });
    }


    // Load customers and update select dropdown (used after adding new customer)
    function loadCustomersAndUpdateSelect(selectedCustomerId, selectedEncryptedId) {
        selectedCustomerId = selectedCustomerId || null;
        
        $.ajax({
            url: base_url + '/pos/customers',
            type: 'GET',
            data: { include_id: selectedCustomerId || '' },
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            success: function(response) {
                if (response.status === 'success') {
                    var customerSelect = $('#customer-select');
                    var currentValue = selectedCustomerId || customerSelect.val();
                    customerSelect.empty();
                    customerSelect.append('<option value="">Select Customer</option>');
                    var walkInCustomer = response.customers.find(function(c) { return c.name === 'Walk-in Customer'; });
                    if (walkInCustomer) {
                        customerSelect.append('<option value="' + walkInCustomer.id + '">' + walkInCustomer.name + '</option>');
                    }
                    response.customers.forEach(function(customer) {
                        if (customer.name !== 'Walk-in Customer') {
                            customerSelect.append('<option value="' + customer.id + '">' + customer.name + '</option>');
                        }
                    });
                    customerSelect.select2('destroy').select2(getPosCustomerSelectConfig(customerSelect));
                    if (currentValue) {
                        customerSelect.val(currentValue).trigger('change');
                    }
                }
            },
            error: function() {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('Failed to load customers');
                }
            }
        });
    }

    // Open Edit Customer Modal
    $(document).on('click', '#pos-edit-customer-btn', function(e) {
        e.preventDefault();
        let customerSelect = $('#customer-select');
        // this customer option text get
        let customerOptionText = customerSelect.find('option:selected').text();
        let customerId = customerSelect.val();
        if (!customerId || customerId === '') {
            showErrorNotification('Please select a customer first');
            return;
        }
        if(customerOptionText === 'Walk-in Customer') {
            showErrorNotification('Walk-in Customer is not editable');
            return;
        }
        let url = base_url + '/pos/get-customer/' + customerId;
        $.ajax({
            url: url,
            type: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                if (response.status === 'success') {
                    let customer = response.customer;
                    $('#pos_customer_id').val(customer.id);
                    $('#pos_customer_name').val(customer.name);
                    $('#pos_customer_phone').val(customer.phone);
                    $('#pos_customer_email').val(customer.email || '');
                    $('#pos_customer_opening_balance').val(customer.opening_balance || 0);
                    $('#pos_customer_opening_balance_type').val(customer.opening_balance_type || 'Debit').trigger('change');
                    $('#pos_customer_same_or_diff_state').val(customer.same_or_diff_state || '1').trigger('change');
                    $('#pos_customer_state_id').val(customer.state_id || '').trigger('change');
                    $('#pos_customer_gst_number').val(customer.gst_number || '');
                    $('#pos_customer_business_type').val(customer.business_type || 'B2C').trigger('change');
                    togglePosGstinRequired();
                    $('#pos_customer_credit_limit').val(customer.credit_limit || 0);
                    $('#pos_customer_discount').val(customer.discount || 0);
                    $('#pos_customer_type').val(customer.customer_type || '1').trigger('change');
                    $('#pos_customer_date_of_birth').val(customer.date_of_birth || '');
                    $('#pos_customer_date_of_anniversary').val(customer.date_of_anniversary || '');
                    $('#pos_customer_modal_title').text('Edit Customer');
                    $('#modal_pos_customer').modal('show');
                } else {
                    showErrorNotification(response.message || 'Failed to load customer data');
                }
            },
            error: function(xhr) {
                if (xhr.status === 404) {
                    showErrorNotification('Customer not found');
                } else {
                    showErrorNotification('Failed to load customer data');
                }
            }
        });
    });

    // Toggle GSTIN required when business type changes or modal is shown
    $(document).on('change', '#pos_customer_business_type', togglePosGstinRequired);
    $(document).on('shown.bs.modal', '#modal_pos_customer', togglePosGstinRequired);

    // ═══ Phone Auto-Fill: lookup existing customer when phone is 10 digits ═══
    let phoneLookupTimeout = null;
    $(document).on('input', '#pos_customer_phone', function() {
        let phone = $(this).val().trim();
        clearTimeout(phoneLookupTimeout);
        
        // Only lookup when phone has >= 10 digits
        if (phone.length < 10) {
            // Reset form fields if phone is being cleared/edited
            if (_posPhoneAutoFilled) {
                _posPhoneAutoFilled = false;
                $('#pos_customer_name').val('');
                $('#pos_customer_email').val('');
                $('#pos_customer_opening_balance').val('0');
                $('#pos_customer_opening_balance_type').val('Debit').trigger('change');
                $('#pos_customer_credit_limit').val('0');
                $('#pos_customer_business_type').val('B2C').trigger('change');
            }
            return;
        }
        
        phoneLookupTimeout = setTimeout(function() {
            $.ajax({
                url: base_url + '/pos/lookup-customer-by-phone',
                type: 'GET',
                data: { phone: phone },
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                success: function(response) {
                    if (response.status === 'success' && response.customer) {
                        let c = response.customer;
                        _posPhoneAutoFilled = true;
                        _posPhoneAutoFillCustomerId = c.id;
                        $('#pos_customer_name').val(c.name || '');
                        $('#pos_customer_email').val(c.email || '');
                        $('#pos_customer_opening_balance').val(c.opening_balance || '0');
                        $('#pos_customer_opening_balance_type').val(c.opening_balance_type || 'Debit').trigger('change');
                        $('#pos_customer_credit_limit').val(c.credit_limit || '0');
                        $('#pos_customer_discount').val(c.discount || '0');
                        $('#pos_customer_business_type').val(c.business_type || 'B2C').trigger('change');
                        $('#pos_customer_same_or_diff_state').val(c.same_or_diff_state || '1').trigger('change');
                        $('#pos_customer_state_id').val(c.state_id || '').trigger('change');
                        $('#pos_customer_gst_number').val(c.gst_number || '');
                        $('#pos_customer_date_of_birth').val(c.date_of_birth || '');
                        $('#pos_customer_date_of_anniversary').val(c.date_of_anniversary || '');
                        togglePosGstinRequired();
                        
                        // Update modal title to indicate existing customer
                        $('#pos_customer_modal_title').text('Edit Customer (Existing)');
                        $('#pos_customer_id').val(c.id);
                        
                        if (typeof showInfoNotification !== 'undefined') {
                            showInfoNotification('Customer found: ' + c.name + ' — details auto-filled');
                        }
                    } else {
                        _posPhoneAutoFilled = false;
                        _posPhoneAutoFillCustomerId = null;
                        $('#pos_customer_modal_title').text('Add Customer');
                        $('#pos_customer_id').val('');
                    }
                },
                error: function() {
                    // Silently ignore lookup errors
                }
            });
        }, 300); // 300ms debounce
    });
    // Track whether form was auto-filled from phone lookup
    window._posPhoneAutoFilled = false;
    window._posPhoneAutoFillCustomerId = null;
    var _posPhoneAutoFilled = false;
    var _posPhoneAutoFillCustomerId = null;

    // Submit Customer Form
    $(document).on('submit', '#posCustomerForm', function(e) {
        e.preventDefault();
        clearValidationErrors();
        let formData = $(this).serialize();
        let customerId = $('#pos_customer_id').val();
        let url, method;
        if (customerId) {
            url = base_url + '/pos/customer/' + customerId;
            method = 'PUT';
        } else {
            url = base_url + '/pos/customer';
            method = 'POST';
        }
        if (method === 'PUT') {
            formData += '&_method=PUT';
        }
        $.ajax({
            url: url,
            type: method === 'PUT' ? 'POST' : method,
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'X-Requested-With': 'XMLHttpRequest'
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    showSuccessNotification(response.message);
                    $('#modal_pos_customer').modal('hide');
                    clearCustomerForm();
                    loadCustomersAndUpdateSelect(response.customer.id);
                } else {
                    showErrorNotification(response.message || 'Something went wrong');
                }
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        showValidationErrors(xhr.responseJSON.errors);
                    }
                    if (typeof showErrorNotification !== 'undefined') {
                        showErrorNotification(xhr.responseJSON?.message || 'Please check the form for errors');
                    }
                } else {
                    if (typeof showErrorNotification !== 'undefined') {
                        showErrorNotification(xhr.responseJSON?.message || 'An unexpected error occurred');
                    }
                }
            }
        });
    });

    // Customer select AJAX config - reusable for init and after add
    function getPosCustomerSelectConfig($sel) {
        
        var customersUrl = base_url + '/pos/customers';
        return {
            ajax: {
                delay: 250,
                transport: function(params, success, failure) {
                    var term = (params.data && params.data.term) ? params.data.term : '';
                    var includeId = (params.data && params.data.include_id) ? params.data.include_id : ($sel.val() || '');
                    var url = customersUrl + '?search=' + encodeURIComponent(term) + '&include_id=' + encodeURIComponent(includeId);
                    $.ajax({
                        url: url,
                        type: 'GET',
                        dataType: 'json',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                    }).done(function(data) {
                        if (data && data.status === 'success' && data.customers) {
                            success({ results: data.customers.map(function(c) { return { id: c.id, text: c.name }; }) });
                        } else {
                            success({ results: [] });
                        }
                    }).fail(failure);
                },
                data: function(params) {
                    return { term: params.term || '', include_id: $sel.val() || '' };
                }
            },
            placeholder: 'Select Customer',
            minimumInputLength: 0,
            allowClear: true,
            width: '100%'
        };
    }

    function initPosCustomerSelect() {
        var $sel = $('#customer-select');
        if (!$sel.length) return;
        if ($sel.hasClass('select2-hidden-accessible')) {
            $sel.select2('destroy');
        }
        $sel.select2(getPosCustomerSelectConfig($sel));
    }
    setTimeout(initPosCustomerSelect, 100);


    setTimeout(function() {
        $('#template-customizer').css('display', 'none');
    }, 100);

    // Save grocery_experience when pos-layout-select changes
    $(document).on('change', '#pos-layout-select', function(e) {
        const groceryExperience = $(this).val();
        const baseUrl = $('#base_url').val() || window.location.origin;
        
        $.ajax({
            url: base_url + '/pos-layout',
            type: 'POST',
            data: {
                grocery_experience: groceryExperience,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                if (response.status === 'success') {
                    // Reload page to apply changes
                    location.reload();
                } else {
                    if (typeof showErrorNotification !== 'undefined') {
                        showErrorNotification(response.message || 'Failed to save layout setting');
                    } else {
                        alert(response.message || 'Failed to save layout setting');
                    }
                }
            },
            error: function(xhr) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('Failed to save layout setting');
                } else {
                    alert('Failed to save layout setting');
                }
            }
        });
    });

    // Item Operation Code


    // Mobile Menu Toggler - Only for screens < 992px
    function isMobileScreen() {
        return window.innerWidth < 992;
    }

    // Handle cart toggler click
    $(document).on('click', '.cart-toggler', function(e) {
        e.preventDefault();
        
        // Only apply mobile logic if screen is < 992px
        if (isMobileScreen()) {
            // Hide cart-toggler, show products-toggler
            $('.mobile-menu-toggler').removeClass('products-view').addClass('cart-view');
            
            // Show cart panel, hide products content
            $('.pos-cart-panel').addClass('show');
            $('.pos-products-panel').css('display', 'none');
        } else {
            // Desktop behavior - just toggle cart panel
            $('.pos-cart-panel').toggleClass('show');
        }
    });

    // Handle products toggler click
    $(document).on('click', '.products-toggler', function(e) {
        e.preventDefault();
        
        // Only apply mobile logic if screen is < 992px
        if (isMobileScreen()) {
            // Hide products-toggler, show cart-toggler
            $('.mobile-menu-toggler').removeClass('cart-view').addClass('products-view');
            
            // Hide cart panel, show products content
            $('.pos-cart-panel').removeClass('show');
            $('.pos-products-panel').css('display', 'flex');
        } else {
            // Desktop behavior - just toggle products panel
            $('.pos-products-panel').toggleClass('show');
        }
    });

    // Handle window resize to reset state if needed
    let resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (!isMobileScreen()) {
                // Reset mobile states on desktop - show both panels
                $('.mobile-menu-toggler').removeClass('cart-view products-view');
                $('.pos-cart-panel').removeClass('show');
                $('.pos-products-panel').css('display', 'flex');
            } else {
                // Ensure correct state on mobile based on current view
                if ($('.pos-cart-panel').hasClass('show')) {
                    // Cart is showing, so we're in cart-view
                    $('.mobile-menu-toggler').removeClass('products-view').addClass('cart-view');
                    $('.pos-products-panel').css('display', 'none');
                } else {
                    // Products are showing, so we're in products-view
                    $('.mobile-menu-toggler').removeClass('cart-view').addClass('products-view');
                    $('.pos-products-panel').css('display', 'flex');
                }
            }
        }, 250);
    });

    // Initialize mobile menu state on page load
    $(document).ready(function() {
        if (isMobileScreen()) {
            // Initial state: products view (cart-toggler visible, products-content visible)
            $('.mobile-menu-toggler').addClass('products-view');
            $('.pos-cart-panel').removeClass('show');
            $('.pos-products-panel').css('display', 'flex');
        } else {
            // Desktop: ensure both are visible
            $('.pos-products-panel').css('display', 'flex');
        }
        
        // Update cart count badge on page load
        // Wait a bit for posCartManager to be initialized
        setTimeout(function() {
            if (typeof posCartManager !== 'undefined') {
                posCartManager.updateCartCountBadge();
            }
        }, 100);
    });

    /**
     * Get invoice format and window dimensions for popup
     * @returns {Object} {format: string, width: number, height: number}
     */
    window.getInvoicePopupDimensions = function() {
        let invoiceFormat = 'A4 Print'; // Default format
        let width = 1600;
        let height = 550;

        try {
            const invoiceConfig = company_session_data.invoice_configuration;
            if (invoiceConfig) {
                const invConfig = typeof invoiceConfig === 'string' ? JSON.parse(invoiceConfig) : invoiceConfig;
                if (invConfig && invConfig.invoice_format_or_size) {
                    invoiceFormat = invConfig.invoice_format_or_size;
                }
            }
        } catch (e) {
            console.error('Error parsing invoice configuration:', e);
        }

        // Set window dimensions based on invoice format
        switch (invoiceFormat) {
            case '56mm':
                width = 480;
                height = 550;
                break;
            case '80mm':
                width = 685;
                height = 550;
                break;
            case 'A4 Print':
            case 'Half A4 Print':
            case 'Letter Head':
            default:
                width = 1600;
                height = 550;
                break;
        }

        return {
            format: invoiceFormat,
            width: width,
            height: height
        };
    };

    /**
     * Open invoice popup window or send to live print server
     * @param {string} saleEncryptedId - Encrypted sale ID
     */
    window.openInvoicePopup = function(saleEncryptedId) {
        const baseUrl = $('#base_url').val() || window.location.origin;
        const printerSettings = window.posPrinterSettings || {};
        const invoicePrint = printerSettings.invoice_print || 'browser_print';

        if (invoicePrint === 'live_server_print') {
            $.ajax({
                url: baseUrl + '/call-print-server',
                method: 'POST',
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify({ sale_id: saleEncryptedId }),
                contentType: 'application/json',
                success: function(data) {
                    if (data && data.printer_server_url) {
                        const printUrl = data.printer_server_url + 'print-server/receive';
                        $.ajax({
                            url: printUrl,
                            method: 'POST',
                            dataType: 'json',
                            data: {
                                content_data: JSON.stringify(data.content_data),
                                print_type: data.print_type || 'Invoice'
                            },
                            success: function() {},
                            error: function() {}
                        });
                    }
                },
                error: function() {}
            });
            return;
        }

        const dimensions = window.getInvoicePopupDimensions();
        const invoiceUrl = `${baseUrl}/sale/${saleEncryptedId}/print`;
        window.open(
            invoiceUrl,
            'Print Invoice',
            `width=${dimensions.width},height=${dimensions.height},scrollbars=yes,resizable=yes`
        );
    };

    /**
     * Register Summary Handler
     */
    $(document).on('click', '#pos-register-btn', function(e) {
        e.preventDefault();
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('modal_pos_register_summary'));
        modal.show();
        
        // Show loading, hide content and error
        $('#register-summary-loading').show();
        $('#register-summary-content').hide();
        $('#register-summary-error').hide();
        
        // Fetch register summary
        $.ajax({
            url: base_url + '/register/summary',
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            success: function(response) {
                $('#register-summary-loading').hide();
                
                if (response.success && response.data) {
                    displayRegisterSummary(response.data);
                    $('#register-summary-content').show();
                } else {
                    $('#register-summary-error-message').text(response.message || 'Failed to load register summary');
                    $('#register-summary-error').show();
                }
            },
            error: function(xhr) {
                $('#register-summary-loading').hide();
                const errorMessage = xhr.responseJSON?.message || 'Failed to load register summary';
                $('#register-summary-error-message').text(errorMessage);
                $('#register-summary-error').show();
            }
        });
    });

    /**
     * Display register summary data in modal
     */
    function displayRegisterSummary(data) {
        // Set user name and time range
        $('#register-user-name').text(data.user_name || 'Unknown');
        $('#register-time-range').text(
            formatDateTime(data.start_time) + ' to ' + formatDateTime(data.end_time)
        );

        // Clear existing data
        $('#register-summary-tbody').empty();
        $('#register-summary-totals-tbody').empty();

        let globalSN = 1;
        const summary = data.summary || [];
        const paymentMethodTotals = data.payment_method_totals || {};

        // Populate transactions table - show all transactions in order
        summary.forEach(function(paymentMethod) {
            const paymentMethodName = paymentMethod.payment_method_name;
            const transactions = paymentMethod.transactions || [];

            transactions.forEach(function(transaction) {
                const row = $('<tr>');
                row.append('<td>' + globalSN++ + '</td>');
                row.append('<td>' + escapeHtml(paymentMethodName) + '</td>');
                row.append('<td>' + escapeHtml(transaction.transaction_type) + '</td>');
                
                const amount = parseFloat(transaction.amount) || 0;
                const amountClass = amount >= 0 ? 'text-success' : 'text-danger';
                const amountSign = amount >= 0 ? '+' : '';
                
                // Use company currency formatting if available
                let formattedAmount = formatAmount(Math.abs(amount));
                if (typeof window.formatAmount === 'function') {
                    // Remove currency symbol for table display, just show number
                    formattedAmount = window.formatAmount(Math.abs(amount), false);
                }
                
                row.append('<td class="text-end ' + amountClass + '">' + 
                    amountSign + formattedAmount + '</td>');
                
                $('#register-summary-tbody').append(row);
            });
        });

        // Populate summary totals - only show payment methods with transactions
        summary.forEach(function(paymentMethod) {
            const paymentMethodId = paymentMethod.payment_method_id;
            const total = parseFloat(paymentMethodTotals[paymentMethodId]) || 0;
            
            // Only show if there are transactions or total is not zero
            if (paymentMethod.transactions.length > 0 || total != 0) {
                const row = $('<tr>');
                row.append('<td><strong>' + escapeHtml(paymentMethod.payment_method_name) + '</strong></td>');
                
                const totalClass = total >= 0 ? 'text-success' : 'text-danger';
                const totalSign = total >= 0 ? '+' : '';
                
                let formattedTotal = formatAmount(Math.abs(total));
                if (typeof window.formatAmount === 'function') {
                    formattedTotal = window.formatAmount(Math.abs(total), false);
                }
                
                row.append('<td class="text-end ' + totalClass + '"><strong>' + 
                    totalSign + formattedTotal + '</strong></td>');
                
                $('#register-summary-totals-tbody').append(row);
            }
        });
    }

    /**
     * Format date time for display
     */
    function formatDateTime(dateTimeString) {
        if (!dateTimeString) return '';
        try {
            const date = new Date(dateTimeString);
            if (isNaN(date.getTime())) return dateTimeString;
            return date.toLocaleString();
        } catch (e) {
            return dateTimeString;
        }
    }

    /**
     * Format amount using company settings
     */
    function formatAmount(amount) {
        if (amount === null || amount === undefined || isNaN(amount)) {
            return '0.00';
        }
        
        // Try to use global formatAmount if available
        if (typeof window.formatAmount === 'function') {
            return window.formatAmount(amount, false); // false = without currency symbol for table display
        }
        
        // Fallback to simple formatting
        const numValue = parseFloat(amount);
        const precision = company_session_data.precision || 2;
        return numValue.toFixed(precision);
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, (m) => map[m]);
    }

    /**
     * Export to Print
     */
    $(document).on('click', '#register-summary-print-btn', function(e) {
        e.preventDefault();
        const printContent = document.getElementById('register-summary-content').innerHTML;
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Register Summary</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    .text-end { text-align: right; }
                    .text-success { color: green; }
                    .text-danger { color: red; }
                </style>
            </head>
            <body>
                ${printContent}
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    });

    /**
     * Export to Excel
     */
    $(document).on('click', '#register-summary-excel-btn', function(e) {
        e.preventDefault();
        
        const table = document.getElementById('register-summary-table');
        const totalsTable = document.getElementById('register-summary-totals-table');
        
        let csv = '\uFEFF'; // BOM for UTF-8
        
        // Add header info
        csv += 'User Name:,' + $('#register-user-name').text() + '\n';
        csv += 'Time Range:,' + $('#register-time-range').text() + '\n\n';
        
        // Add transactions table
        csv += 'Register Summary Transactions\n';
        for (let i = 0; i < table.rows.length; i++) {
            const row = table.rows[i];
            const rowData = [];
            for (let j = 0; j < row.cells.length; j++) {
                let cellText = row.cells[j].innerText.trim();
                // Remove currency symbols and format
                cellText = cellText.replace(/[^\d.-]/g, '');
                rowData.push('"' + cellText + '"');
            }
            csv += rowData.join(',') + '\n';
        }
        
        csv += '\n';
        
        // Add totals table
        csv += 'Summary Totals\n';
        for (let i = 0; i < totalsTable.rows.length; i++) {
            const row = totalsTable.rows[i];
            const rowData = [];
            for (let j = 0; j < row.cells.length; j++) {
                let cellText = row.cells[j].innerText.trim();
                cellText = cellText.replace(/[^\d.-]/g, '');
                rowData.push('"' + cellText + '"');
            }
            csv += rowData.join(',') + '\n';
        }
        
        // Create download link
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', 'register_summary_' + new Date().getTime() + '.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });

    /**
     * Export to PDF
     */
    $(document).on('click', '#register-summary-pdf-btn', function(e) {
        e.preventDefault();

        const table = document.getElementById('register-summary-table');
        const totalsTable = document.getElementById('register-summary-totals-table');
        if (!table || !totalsTable) {
            if (typeof showErrorNotification !== 'undefined') showErrorNotification('Register summary data not found');
            else alert('Register summary data not found');
            return;
        }

        // Print-friendly window -> browser print dialog me "Save as PDF" se export
        const printWin = window.open('', '_blank', 'width=900,height=700');
        if (!printWin) {
            if (typeof showErrorNotification !== 'undefined') showErrorNotification('Popup blocked - popup allow karke dobara try karo');
            else alert('Popup blocked - popup allow karke dobara try karo');
            return;
        }

        printWin.document.write(
            '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Register Summary</title>' +
            '<style>' +
            'body{font-family:Arial,Helvetica,sans-serif;color:#0F172A;padding:24px;}' +
            'h2{margin:0 0 4px;}' +
            '.meta{color:#64748B;font-size:13px;margin-bottom:16px;}' +
            'table{width:100%;border-collapse:collapse;font-size:12px;margin-bottom:20px;}' +
            'th,td{border:1px solid #CBD5E1;padding:6px 8px;text-align:left;}' +
            'th{background:#EEF2FF;color:#4F46E5;font-weight:bold;}' +
            'h3{color:#334155;margin:18px 0 8px;}' +
            '@media print{body{width:100%;}}' +
            '</style></head><body>' +
            '<h2>Register Summary</h2>' +
            '<div class="meta">User: <b>' + $('#register-user-name').text() + '</b> | Time Range: <b>' + $('#register-time-range').text() + '</b></div>' +
            '<h3>Register Summary Transactions</h3>' + table.outerHTML +
            '<h3>Summary Totals</h3>' + totalsTable.outerHTML +
            '</body></html>'
        );
        printWin.document.close();
        printWin.focus();
        printWin.print();
    });

    /**
     * ============================================================================
     * WEIGHT SCALE INTEGRATION (HID/Keyboard Mode)
     * ============================================================================
     * 
     * This module integrates USB weight scales that operate in HID/keyboard mode.
     * The scale sends weight data as keyboard input (e.g., "0.750" + ENTER).
     * 
     * Features:
     * - Automatic detection of weight vs barcode input
     * - Support for weighted barcodes (prefix + weight, e.g., "210.750")
     * - Seamless integration with existing barcode scanning
     * - Auto-fills weight into product modal quantity field
     * - Works with offline/IndexedDB POS system
     * 
     * Usage:
     * 1. Scan barcode → Product opens in modal
     * 2. Place item on scale → Weight auto-fills quantity field
     * 3. Or: Scan weighted barcode (prefix + weight) → Product opens with weight pre-filled
     * 4. Or: Scan GS1 variable-weight barcode (EAN-13/UPC-A) → Product opens with weight pre-filled
     * 
     * GS1 Variable-Weight Barcode Support:
     * - EAN-13: 13 digits starting with '2' (Prefix:2 + SKU:6 + Weight:5 + Check:1)
     * - UPC-A: 12 digits starting with '2' (Prefix:2 + SKU:5 + Weight:5 + Check:1)
     * - Example: 2010012017505 → SKU: 010012, Weight: 1.750 kg
     * 
     * ============================================================================
     */
    
    // Weight scale configuration
    const WeightScaleConfig = {
        // Weight detection patterns
        weightPatterns: [
            /^\d+\.\d{1,3}$/,           // Decimal weight: 0.750, 1.250, etc.
            /^0\.\d{1,3}$/,              // Small weights: 0.123
            /^\d+\.\d{1,3}kg$/i,        // With kg suffix: 0.750kg
            /^\d+\.\d{1,3}\s*kg$/i,      // With space: 0.750 kg
        ],
        // Weighted barcode prefixes (common PLU prefixes)
        weightedBarcodePrefixes: ['21', '22', '23', '24', '25'],
        // Minimum weight value (in kg)
        minWeight: 0.001,
        // Maximum weight value (in kg) - adjust based on your scale
        maxWeight: 999.999
    };
    
    // GS1 Variable-Weight Barcode configuration
    const GS1Config = {
        // GS1 prefix for variable-weight items
        prefix: '2',
        // EAN-13: 13 digits total
        ean13Length: 13,
        // UPC-A: 12 digits total
        upcaLength: 12,
        // SKU/PLU length
        ean13SkuLength: 6,  // Positions 2-7
        upcaSkuLength: 5,   // Positions 2-6
        // Weight length (in grams, 5 digits)
        weightLength: 5,
        // Minimum weight in grams (0.001 kg = 1 gram)
        minWeightGrams: 1,
        // Maximum weight in grams (999.999 kg = 999999 grams)
        maxWeightGrams: 999999
    };
    
    /**
     * Detect GS1 Variable-Weight Barcode (EAN-13 or UPC-A)
     * 
     * GS1 Variable-Weight Barcode Structure:
     * - EAN-13 (13 digits): [Prefix:2][SKU:6 digits][Weight:5 digits grams][Check:1]
     * - UPC-A (12 digits): [Prefix:2][SKU:5 digits][Weight:5 digits grams][Check:1]
     * 
     * Example EAN-13: 2010012017505
     *   → Prefix: 2
     *   → SKU: 010012
     *   → Weight: 01750 grams = 1.750 kg
     *   → Check: 5
     * 
     * Example UPC-A: 20100117505
     *   → Prefix: 2
     *   → SKU: 01001
     *   → Weight: 1750 grams = 1.750 kg
     *   → Check: 5
     * 
     * @param {string} barcode - Barcode string to check
     * @returns {Object|null} Parsed GS1 data or null if not GS1 format
     *   {sku: string, weightKg: number, weightGrams: number, format: 'EAN-13'|'UPC-A'}
     */
    function detectGS1WeightedBarcode(barcode) {
        if (!barcode || typeof barcode !== 'string') {
            return null;
        }
        
        // Remove any whitespace
        const trimmed = barcode.trim();
        
        // Must be numeric only
        if (!/^\d+$/.test(trimmed)) {
            return null;
        }
        
        // Must start with GS1 prefix '2'
        if (!trimmed.startsWith(GS1Config.prefix)) {
            return null;
        }
        
        // Check length (EAN-13: 13 digits, UPC-A: 12 digits)
        const length = trimmed.length;
        if (length !== GS1Config.ean13Length && length !== GS1Config.upcaLength) {
            return null;
        }
        
        // Determine format
        const isEAN13 = length === GS1Config.ean13Length;
        const format = isEAN13 ? 'EAN-13' : 'UPC-A';
        const skuLength = isEAN13 ? GS1Config.ean13SkuLength : GS1Config.upcaSkuLength;
        
        // Extract components
        // Position 1: Prefix (already verified as '2')
        // Position 2 to (2+skuLength-1): SKU/PLU
        const skuStart = 1;
        const skuEnd = skuStart + skuLength;
        const sku = trimmed.substring(skuStart, skuEnd);
        
        // Position after SKU to (skuEnd+weightLength-1): Weight in grams
        const weightStart = skuEnd;
        const weightEnd = weightStart + GS1Config.weightLength;
        const weightGramsStr = trimmed.substring(weightStart, weightEnd);
        const weightGrams = parseInt(weightGramsStr, 10);
        
        // Validate weight
        if (isNaN(weightGrams) || weightGrams < GS1Config.minWeightGrams || weightGrams > GS1Config.maxWeightGrams) {
            return null;
        }
        
        // Convert grams to kilograms
        const weightKg = weightGrams / 1000;
        
        // Validate weight in kg
        if (weightKg < WeightScaleConfig.minWeight || weightKg > WeightScaleConfig.maxWeight) {
            return null;
        }
        
        return {
            sku: sku,
            weightKg: weightKg,
            weightGrams: weightGrams,
            format: format,
            originalBarcode: trimmed
        };
    }
    
    /**
     * Detect if input is weight or barcode
     * 
     * Detection Logic (in order of priority):
     * 1. GS1 Variable-Weight Barcode: EAN-13/UPC-A format starting with '2'
     *    Example: "2010012017505" → SKU: 010012, Weight: 1.750 kg
     * 2. Weighted Barcode: Starts with prefix (21, 22, etc.) followed by decimal weight
     *    Example: "210.750" → prefix: "21", weight: 0.750
     * 3. Pure Weight: Decimal number matching weight patterns
     *    Example: "0.750", "1.250", "0.750kg"
     * 4. Regular Barcode: Everything else (numeric or alphanumeric)
     * 
     * @param {string} input - Input value from search field
     * @returns {Object} {type: 'gs1_weighted'|'weight'|'barcode'|'weighted_barcode', value: parsed value, ...}
     */
    function detectInputType(input) {
        if (!input || typeof input !== 'string') {
            return { type: 'barcode', value: input };
        }
        
        const trimmed = input.trim();
        
        // Priority 1: Check for GS1 Variable-Weight Barcode (EAN-13/UPC-A)
        const gs1Data = detectGS1WeightedBarcode(trimmed);
        if (gs1Data) {
            return {
                type: 'gs1_weighted',
                value: trimmed,
                sku: gs1Data.sku,
                weightKg: gs1Data.weightKg,
                weightGrams: gs1Data.weightGrams,
                format: gs1Data.format
            };
        }
        
        // Priority 2: Check for weighted barcode (prefix + weight)
        for (const prefix of WeightScaleConfig.weightedBarcodePrefixes) {
            if (trimmed.startsWith(prefix)) {
                const weightPart = trimmed.substring(prefix.length);
                const weight = parseFloat(weightPart);
                if (!isNaN(weight) && weight >= WeightScaleConfig.minWeight && weight <= WeightScaleConfig.maxWeight) {
                    return {
                        type: 'weighted_barcode',
                        value: weight,
                        prefix: prefix,
                        barcode: prefix
                    };
                }
            }
        }
        
        // Priority 3: Check for pure weight (decimal number)
        for (const pattern of WeightScaleConfig.weightPatterns) {
            if (pattern.test(trimmed)) {
                // Extract numeric value
                const weightMatch = trimmed.match(/(\d+\.?\d*)/);
                if (weightMatch) {
                    const weight = parseFloat(weightMatch[1]);
                    if (!isNaN(weight) && weight >= WeightScaleConfig.minWeight && weight <= WeightScaleConfig.maxWeight) {
                        return { type: 'weight', value: weight };
                    }
                }
            }
        }
        
        // Priority 4: Default - treat as regular barcode
        return { type: 'barcode', value: trimmed };
    }
    
    /**
     * Handle weight input from scale
     * 
     * Behavior:
     * - If product modal is open: Apply weight to quantity field
     * - If last cart item is weight-based: Update its quantity
     * - Otherwise: Show warning to open product first
     * 
     * @param {number} weight - Weight value in kg
     * @returns {boolean} True if weight was applied successfully
     */
    function handleWeight(weight) {
        // Validate weight
        if (isNaN(weight) || weight < WeightScaleConfig.minWeight || weight > WeightScaleConfig.maxWeight) {
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification(`Invalid weight: ${weight.toFixed(3)} kg. Must be between ${WeightScaleConfig.minWeight} and ${WeightScaleConfig.maxWeight} kg.`);
            }
            return false;
        }
        
        // Check if product modal is open
        const $modal = $('#modal_item_info');
        if ($modal.length && $modal.hasClass('show')) {
            // Product modal is open - apply weight to quantity field
            const $quantityInput = $('#pos_item_info_quantity');
            if ($quantityInput.length) {
                // Set weight as quantity
                $quantityInput.val(weight.toFixed(3));
                
                // Trigger input event to recalculate total
                $quantityInput.trigger('input');
                
                // Show notification
                if (typeof showInfoNotification !== 'undefined') {
                    showInfoNotification(`Weight applied: ${weight.toFixed(3)} kg`);
                }
                
                // Clear search field and refocus
                $('#pos-product-search').val('').focus();
                return true;
            }
        }
        
        // Check if there's a product in cart that can accept weight
        // This handles the case where user wants to update quantity of last added item
        if (typeof posCartManager !== 'undefined' && posCartManager.cartItems.length > 0) {
            const lastItem = posCartManager.cartItems[posCartManager.cartItems.length - 1];
            
            // Check if last item is a weight-based product (you may need to add a flag to products)
            // For now, we'll check if quantity is a decimal (likely weight-based)
            if (lastItem && lastItem.quantity < 10 && lastItem.quantity % 1 !== 0) {
                // Update last item quantity with new weight
                posCartManager.updateItemQuantity(lastItem.product_id, weight);
                
                if (typeof showInfoNotification !== 'undefined') {
                    showInfoNotification(`Weight updated: ${weight.toFixed(3)} kg`);
                }
                
                // Clear search field and refocus
                $('#pos-product-search').val('').focus();
                return true;
            }
        }
        
        // No product modal open and no weight-based item in cart
        if (typeof showWarningNotification !== 'undefined') {
            showWarningNotification('Please open a product first, then place item on scale');
        }
        
        // Clear search field and refocus
        $('#pos-product-search').val('').focus();
        return false;
    }
    
    /**
     * Handle weighted barcode (prefix + weight)
     * 
     * Weighted barcodes combine product identification with weight:
     * Format: [PREFIX][WEIGHT]
     * Example: "210.750" → Product with prefix "21", weight 0.750 kg
     * 
     * Process:
     * 1. Search for product matching the prefix
     * 2. Open product modal
     * 3. Pre-fill quantity with weight
     * 
     * @param {string} prefix - Barcode prefix (e.g., '21', '22')
     * @param {number} weight - Weight value in kg
     * @returns {boolean} True if handled successfully
     */
    async function handleWeightedBarcode(prefix, weight) {
        // Validate weight
        if (isNaN(weight) || weight < WeightScaleConfig.minWeight || weight > WeightScaleConfig.maxWeight) {
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification(`Invalid weight: ${weight.toFixed(3)} kg`);
            }
            $('#pos-product-search').val('').focus();
            return false;
        }
        
        // Map prefix to product (you may need to configure this based on your system)
        // For now, we'll search for products that match the prefix pattern
        // This is a placeholder - adjust based on your barcode structure
        
        // Option 1: Search for product by prefix code
        try {
            if (typeof posIndexedDB !== 'undefined' && posIndexedDB.isInitialized) {
                // Search products by code starting with prefix
                const products = await posIndexedDB.searchProducts(prefix);
                
                if (products && products.length > 0) {
                    // Use first matching product
                    const product = products[0];
                    
                    // Get product details and open modal with weight pre-filled
                    if (typeof posProductsDisplay !== 'undefined') {
                        // Open product modal
                        await posProductsDisplay.openProductInfoModal(product);
                        
                        // Wait a bit for modal to open, then set weight
                        setTimeout(() => {
                            const $quantityInput = $('#pos_item_info_quantity');
                            if ($quantityInput.length) {
                                $quantityInput.val(weight.toFixed(3));
                                $quantityInput.trigger('input');
                                
                                if (typeof showInfoNotification !== 'undefined') {
                                    showInfoNotification(`Product found. Weight: ${weight.toFixed(3)} kg`);
                                }
                            }
                        }, 300);
                        
                        // Clear search field
                        $('#pos-product-search').val('');
                        return true;
                    }
                }
            }
        } catch (error) {
            console.error('Error handling weighted barcode:', error);
        }
        
        // If product not found, treat as regular weight
        if (typeof showWarningNotification !== 'undefined') {
            showWarningNotification(`Product with prefix ${prefix} not found. Applying as weight.`);
        }
        
        return handleWeight(weight);
    }
    
    /**
     * Handle GS1 Variable-Weight Barcode
     * 
     * GS1 Variable-Weight Barcodes contain both product SKU/PLU and weight:
     * - Extract SKU/PLU from barcode
     * - Extract weight (convert from grams to kg)
     * - Search for product by SKU/PLU
     * - Open product modal with weight pre-filled
     * 
     * Process:
     * 1. Parse GS1 barcode to extract SKU and weight
     * 2. Search for product using SKU/PLU (offline via IndexedDB, fallback to AJAX)
     * 3. Open product modal
     * 4. Auto-fill quantity field with extracted weight
     * 5. Recalculate total price automatically
     * 
     * @param {Object} gs1Data - Parsed GS1 data from detectGS1WeightedBarcode()
     *   {sku: string, weightKg: number, weightGrams: number, format: string}
     * @returns {boolean} True if handled successfully
     */
    async function handleGS1WeightedBarcode(gs1Data) {
        if (!gs1Data || !gs1Data.sku || !gs1Data.weightKg) {
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Invalid GS1 barcode format');
            }
            $('#pos-product-search').val('').focus();
            return false;
        }
        
        const sku = gs1Data.sku;
        const weightKg = gs1Data.weightKg;
        const format = gs1Data.format;
        
        // Validate weight
        if (isNaN(weightKg) || weightKg < WeightScaleConfig.minWeight || weightKg > WeightScaleConfig.maxWeight) {
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification(`Invalid weight in GS1 barcode: ${weightKg.toFixed(3)} kg`);
            }
            $('#pos-product-search').val('').focus();
            return false;
        }
        
        try {
            let product = null;
            
            // Step 1: Try offline search via IndexedDB
            // Note: posIndexedDB.searchProducts() already searches by code, name, brand, etc.
            if (typeof posIndexedDB !== 'undefined' && posIndexedDB.isInitialized) {
                try {
                    // Search products by SKU/PLU code (IndexedDB search includes code matching)
                    const products = await posIndexedDB.searchProducts(sku);
                    
                    // Find exact SKU match (code should match exactly)
                    if (products && products.length > 0) {
                        // Try exact match first - most important for GS1 barcodes
                        product = products.find(p => {
                            const productCode = p.code ? p.code.toString().trim() : '';
                            // Exact match or ends with SKU (for cases where code includes prefix)
                            return productCode === sku || 
                                   productCode.endsWith(sku) ||
                                   productCode === '0' + sku || // Handle leading zero
                                   productCode === sku + '0';   // Handle trailing zero
                        });
                        
                        // If no exact match, try partial match (code contains SKU)
                        if (!product) {
                            product = products.find(p => {
                                const productCode = p.code ? p.code.toString().trim() : '';
                                return productCode.includes(sku);
                            });
                        }
                        
                        // If still no match, use first result (fallback)
                        if (!product && products.length > 0) {
                            product = products[0];
                        }
                    }
                } catch (error) {
                    console.error('Error searching IndexedDB for GS1 SKU:', error);
                }
            }
            
            // Step 2: Fallback to online AJAX search if product not found offline
            // Note: If you need a dedicated endpoint, create: Route::get('pos/search-product-by-code', 'searchProductByCode')
            // For now, we rely on IndexedDB which should have all products synced
            if (!product && window.internetConnected) {
                try {
                    // Try to get product from IndexedDB by ensuring it's synced
                    // The product should already be in IndexedDB if it was synced during POS initialization
                    // If not found, it means the product doesn't exist or wasn't synced
                    console.warn(`GS1 SKU "${sku}" not found in IndexedDB. Product may need to be added to system.`);
                } catch (ajaxError) {
                    console.error('Error in fallback search:', ajaxError);
                }
            }
            
            // Step 3: Handle product found or not found
            if (product) {
                // Get company data to check direct cart setting
                const companyData = JSON.parse($('#company_data').val() || '{}');
                const directCart = companyData.direct_cart || 'No';
                const productType = product.type || '';
                
                // Check if product can be added directly to cart
                const isDirectCartEligible = (productType === 'General_Product' || productType === 'Installment_Product');
                
                if (isDirectCartEligible && directCart === 'Yes') {
                    // Add directly to cart with weight
                    if (typeof posProductsDisplay !== 'undefined') {
                        // Get product details
                        await posIndexedDB.ensureProductInIndexedDB(product.id);
                        const fullProduct = await posIndexedDB.getProductById(product.id);
                        
                        if (fullProduct) {
                            // Create cart item with weight as quantity
                            const cartItem = {
                                product_id: fullProduct.id,
                                product_name: fullProduct.name,
                                product_code: fullProduct.code,
                                product_type: fullProduct.type,
                                quantity: weightKg, // Use weight as quantity
                                unit_price: fullProduct.sale_price || 0,
                                mrp_price: fullProduct.mrp_price || 0,
                                discount: 0,
                                discount_type: 'fixed',
                                total: (fullProduct.sale_price || 0) * weightKg,
                                tax_information: fullProduct.tax_information || []
                            };
                            
                            // Add to cart
                            if (typeof posCartManager !== 'undefined') {
                                posCartManager.addItem(cartItem);
                                
                                if (typeof showSuccessNotification !== 'undefined') {
                                    showSuccessNotification(`${fullProduct.name} (${weightKg.toFixed(3)} kg) added to cart`);
                                }
                            }
                        }
                    }
                } else {
                    // Open product modal with weight pre-filled
                    if (typeof posProductsDisplay !== 'undefined') {
                        // Ensure product is in IndexedDB
                        await posIndexedDB.ensureProductInIndexedDB(product.id);
                        const fullProduct = await posIndexedDB.getProductById(product.id);
                        
                        if (fullProduct) {
                            // Open product modal
                            await posProductsDisplay.openProductInfoModal(fullProduct);
                            
                            // Wait for modal to open, then set weight
                            setTimeout(() => {
                                const $quantityInput = $('#pos_item_info_quantity');
                                if ($quantityInput.length) {
                                    $quantityInput.val(weightKg.toFixed(3));
                                    $quantityInput.trigger('input');
                                    
                                    if (typeof showInfoNotification !== 'undefined') {
                                        showInfoNotification(`GS1 ${format} detected. Weight: ${weightKg.toFixed(3)} kg`);
                                    }
                                }
                            }, 300);
                        }
                    }
                }
                
                // Clear search field and refocus
                $('#pos-product-search').val('').focus();
                return true;
            } else {
                // Product not found
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification(`Product with SKU/PLU "${sku}" not found (GS1 ${format})`);
                }
                $('#pos-product-search').val('').focus();
                return false;
            }
        } catch (error) {
            console.error('Error handling GS1 weighted barcode:', error);
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Error processing GS1 barcode. Please try again.');
            }
            $('#pos-product-search').val('').focus();
            return false;
        }
    }
    
    /**
     * Handle barcode input (existing flow)
     * 
     * This maintains the existing barcode scanning functionality:
     * - Search for product by barcode
     * - Add to cart directly (if direct_cart enabled) or open modal
     * - Works with IndexedDB for offline support
     * 
     * @param {string} barcode - Barcode value
     * @returns {boolean} True if product was found and processed
     */
    async function handleBarcode(barcode) {
        if (!barcode || barcode.trim() === '') {
            return false;
        }
        
        try {
            // Search for product by barcode
            if (typeof posIndexedDB !== 'undefined' && posIndexedDB.isInitialized) {
                const products = await posIndexedDB.searchProducts(barcode);
                
                if (products && products.length > 0) {
                    // Find exact barcode match first
                    let product = products.find(p => p.code && p.code.toString().trim() === barcode.trim());
                    
                    // If no exact match, use first result
                    if (!product) {
                        product = products[0];
                    }
                    
                    // Get company data to check direct cart setting
                    const companyData = JSON.parse($('#company_data').val() || '{}');
                    const directCart = companyData.direct_cart || 'No';
                    const productType = product.type || '';
                    
                    // Check if product can be added directly to cart
                    const isDirectCartEligible = (productType === 'General_Product' || productType === 'Installment_Product');
                    
                    if (isDirectCartEligible && directCart === 'Yes') {
                        // Add directly to cart
                        if (typeof posProductsDisplay !== 'undefined') {
                            await posProductsDisplay.addProductToCartDirectly(product);
                        }
                    } else {
                        // Open product modal
                        if (typeof posProductsDisplay !== 'undefined') {
                            await posProductsDisplay.openProductInfoModal(product);
                        }
                    }
                    
                    // Clear search field and refocus
                    $('#pos-product-search').val('').focus();
                    return true;
                } else {
                    // Product not found
                    if (typeof showErrorNotification !== 'undefined') {
                        showErrorNotification(`Product with barcode "${barcode}" not found`);
                    }
                    $('#pos-product-search').val('').focus();
                    return false;
                }
            }
        } catch (error) {
            console.error('Error handling barcode:', error);
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Error processing barcode. Please try again.');
            }
        }
        
        // Clear search field and refocus
        $('#pos-product-search').val('').focus();
        return false;
    }
    
    /**
     * Setup weight scale input handler
     * 
     * This function sets up the main event handler that:
     * - Intercepts Enter key presses in #pos-product-search
     * - Detects if input is weight, weighted barcode, or regular barcode
     * - Routes to appropriate handler function
     * - Clears input and refocuses for next scan/weigh
     * 
     * The handler works seamlessly with existing barcode scanners and
     * weight scales that send data followed by ENTER key.
     */
    function setupWeightScaleHandler() {
        const $searchInput = $('#pos-product-search');
        
        if (!$searchInput.length) {
            console.warn('POS search input not found');
            return;
        }
        
        // Disable browser autocomplete for better scale input handling
        $searchInput.attr('autocomplete', 'off');
        $searchInput.attr('spellcheck', 'false');
        
        // Handle Enter key press (when scale or barcode scanner sends data)
        $searchInput.on('keydown', async function(e) {
            // Only handle Enter key
            if (e.key !== 'Enter' && e.keyCode !== 13) {
                return;
            }
            
            e.preventDefault();
            e.stopPropagation();
            
            const inputValue = $(this).val().trim();
            
            // Skip if input is empty
            if (!inputValue) {
                return;
            }
            
            // Detect input type
            const inputType = detectInputType(inputValue);
            
            // Handle based on detected type
            let handled = false;
            
            switch (inputType.type) {
                case 'gs1_weighted':
                    // GS1 Variable-Weight Barcode (EAN-13/UPC-A)
                    handled = await handleGS1WeightedBarcode({
                        sku: inputType.sku,
                        weightKg: inputType.weightKg,
                        weightGrams: inputType.weightGrams,
                        format: inputType.format,
                        originalBarcode: inputType.value
                    });
                    break;
                    
                case 'weight':
                    // Pure weight from scale
                    handled = handleWeight(inputType.value);
                    break;
                    
                case 'weighted_barcode':
                    // Weighted barcode (prefix + weight)
                    handled = await handleWeightedBarcode(inputType.prefix, inputType.value);
                    break;
                    
                case 'barcode':
                default:
                    // Regular barcode
                    handled = await handleBarcode(inputType.value);
                    break;
            }
            
            // Clear input after processing (always clear, regardless of success)
            $(this).val('');
            
            // Refocus for next input
            setTimeout(() => {
                $(this).focus();
            }, 100);
        });
        
        // Prevent accidental manual typing if needed (optional - can be enabled via config)
        // Uncomment below to disable manual typing (scale/scanner only)
        /*
        $searchInput.on('keypress', function(e) {
            // Allow backspace, delete, arrow keys
            if ([8, 9, 27, 13, 46, 37, 38, 39, 40].indexOf(e.keyCode) !== -1 ||
                (e.keyCode === 65 && e.ctrlKey === true) || // Ctrl+A
                (e.keyCode >= 35 && e.keyCode <= 40)) {    // Home, End, Arrow keys
                return;
            }
            // Block manual typing (uncomment to enable)
            // e.preventDefault();
        });
        */
    }
    
    // Initialize weight scale handler when DOM is ready
    $(document).ready(function() {
        // Wait a bit for other scripts to initialize
        setTimeout(() => {
            setupWeightScaleHandler();
        }, 500);
    });

    // Load sale for edit when redirected from sale edit (edit_sale in URL)
    if (window.posEditSaleId) {
        setTimeout(function() {
            if (typeof posCartManager === 'undefined') return;
            const baseUrl = $('#base_url').val() || window.location.origin;
            const url = baseUrl + route('pos.sale.for-edit', { encryptedId: window.posEditSaleId }, false, Ziggy);
            $.ajax({
                url: url,
                method: 'GET',
                success: function(response) {
                    if (response.status === 'success' && response.data) {
                        const saleData = response.data.sale;
                        const items = response.data.items || [];
                        posCartManager.clearCart();
                        if (saleData.customer_id) {
                            $('#customer-select').val(saleData.customer_id).trigger('change');
                        }
                        if (saleData.employee_id) {
                            $('#employee-select').val(saleData.employee_id).trigger('change');
                        }
                        items.forEach(function(item) {
                            const cartItem = {
                                product_id: item.product_id,
                                product_name: item.product_name || 'N/A',
                                product_code: item.product_code || 'N/A',
                                product_type: item.product_type,
                                quantity: item.quantity,
                                unit_price: parseFloat(item.unit_price) || 0,
                                discount: parseFloat(item.discount) || 0,
                                discount_type: item.discount_type || 'fixed',
                                tax_information: item.tax_information || [],
                                selected_imei_serial: item.selected_imei_serial || [],
                                selected_medicine_expiry: item.selected_medicine_expiry || [],
                                item_seller_id: item.item_seller_id || null,
                                combo_items: item.combo_items || []
                            };
                            posCartManager.addItem(cartItem, true);
                        });
                        posCartManager.cartSummaryDiscount = parseFloat(saleData.discount) || 0;
                        posCartManager.cartSummaryDiscountType = saleData.discount_type || 'fixed';
                        posCartManager.cartSummaryShipping = parseFloat(saleData.shipping) || 0;
                        posCartManager.updateCartSummary();
                        window.posEditingSaleId = window.posEditSaleId;
                        if (typeof showInfoNotification !== 'undefined') {
                            showInfoNotification('Sale ' + (saleData.sale_no || '') + ' loaded for editing. Complete payment to update.');
                        }
                    } else {
                        if (typeof showErrorNotification !== 'undefined') {
                            showErrorNotification(response.message || 'Failed to load sale for edit');
                        } else {
                            alert(response.message || 'Failed to load sale for edit');
                        }
                    }
                    window.posEditSaleId = null;
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to load sale for edit';
                    if (typeof showErrorNotification !== 'undefined') {
                        showErrorNotification(msg);
                    } else {
                        alert(msg);
                    }
                    window.posEditSaleId = null;
                }
            });
        }, 300);
    }

    // Track customer type for price auto-selection
    function updateCustomerType() {
        var $selected = $('#customer-select option:selected');
        var custType = $selected.data('customer-type') || '';
        $('#selected-customer-type').val(custType);
    }
    $('#customer-select').on('change', function() {
        updateCustomerType();
        // Business Club: check if selected customer is a BC member
        var customerId = $(this).val();
        if (customerId && customerId != '1' && typeof posCartManager !== 'undefined') {
            var baseUrl = $('#base_url').val() || window.location.origin;
            $.ajax({
                url: baseUrl + '/business-club/api/check-membership/' + customerId,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response && response.is_member === true) {
                        posCartManager.setBcMemberStatus(true, response.member);
                    } else {
                        posCartManager.setBcMemberStatus(false, null);
                    }
                },
                error: function() {
                    posCartManager.setBcMemberStatus(false, null);
                }
            });
        } else if (typeof posCartManager !== 'undefined') {
            posCartManager.setBcMemberStatus(false, null);
        }
    });
    updateCustomerType();

    // Coupon code apply handler
    $(document).on('click', '#pos-coupon-apply-btn', function() {
        var couponCode = $('#pos-coupon-input').val().trim();
        if (!couponCode) {
            $('#pos-coupon-message').text('Please enter a coupon code').removeClass('text-success').addClass('text-danger');
            return;
        }
        if (typeof posCartManager !== 'undefined') {
            var result = posCartManager.applyCouponDiscount(couponCode);
            if (result) {
                $('#pos-coupon-message').text('Coupon applied: ' + (result.title || 'Discount')).removeClass('text-danger').addClass('text-success');
            } else {
                $('#pos-coupon-message').text('Invalid or expired coupon').removeClass('text-success').addClass('text-danger');
            }
        }
    });
    $('#pos-coupon-input').on('keypress', function(e) {
        if (e.which === 13) $('#pos-coupon-apply-btn').click();
    });

    // Bill-level promotions: check after cart update
    if (typeof posCartManager !== 'undefined') {
        var origUpdateSummary = posCartManager.updateCartSummary;
        posCartManager.updateCartSummary = function() {
            origUpdateSummary.call(this);
            this.applyBillLevelPromotions();
        };
    }
});
