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
    
    let variation_child_items_url = $('#variation_child_items_url').val();
    let item_current_stock_url = $('#item_current_stock_url').val() || (base_url + '/api/purchase-return/item-current-stock');
    // Function to clear validation errors
    function clearValidationErrors() {
        $('.is-invalid').removeClass('is-invalid');
        $('.select2-container.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').remove();
    }

    // Function to show validation errors from backend
    function showValidationErrors(wrapperSelector, errors) {
        clearValidationErrors();
        $.each(errors, function(field, messages) {
            let input = null;
            let formGroup = null;
            
            // Handle nested array fields
            if (field.includes('.')) {
                let parts = field.split('.');
                let [baseName, index] = field.split('.');
                input = $(`${wrapperSelector} [name="${baseName}[]"]`).eq(parseInt(index));
            } else {
                // Handle regular fields
                input = $(`${wrapperSelector} [name="${field}"]`);
            }
            
            if (input && input.length > 0) {
                formGroup = input.closest('.validate_wrapper');
                if (formGroup.length === 0) {
                    formGroup = input.closest('.mb-3, .mb-5');
                }
                input.addClass('is-invalid');
                
                // For Select2 fields, also add invalid class to the Select2 container
                if (input.hasClass('select2-hidden-accessible')) {
                    input.next('.select2-container').addClass('is-invalid');
                }
                
                if (formGroup.length > 0) {
                    // Remove existing feedback for this field
                    formGroup.find('.invalid-feedback').remove();
                    formGroup.append(`<div class="invalid-feedback">${messages[0]}</div>`);
                } else {
                    // If no wrapper found, append after input or its container
                    let container = input.next('.select2-container');
                    if (container.length > 0) {
                        container.after(`<div class="invalid-feedback">${messages[0]}</div>`);
                    } else {
                        input.after(`<div class="invalid-feedback">${messages[0]}</div>`);
                    }
                }
            }
        });
        $('.invalid-feedback').show();
    }

    // Function to update supplier select options
    function updateSupplierSelect(suppliers, selectedId = null) {
        let select = $('#supplier_id');
        select.empty();
        select.append(`<option value="">${ language_key.Select + ' ' + language_key.Supplier }</option>`);
        suppliers.forEach(function(supplier) {
            let option = $('<option></option>')
                .val(supplier.id)
                .text(supplier.name);
            
            if (selectedId && supplier.id === selectedId) {
                option.prop('selected', true);
            }
            select.append(option);
        });
        
        // Reinitialize select2 if it exists
        if (select.hasClass('select2-hidden-accessible')) {
            select.select2('destroy');
        }
        select.select2({
            dropdownParent: select.parent(),
            placeholder: language_key.Select + ' ' + language_key.Supplier
        });
    }

    // Function to fetch suppliers
    function fetchSuppliers() {
        $.ajax({
            type: "GET",
            url: base_url + "/get-suppliers",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    updateSupplierSelect(response.data);
                }
            },
            error: function () {
                showErrorNotification('Failed to fetch suppliers');
            }
        });
    }
    
    $(document).on('click', '.add_supplier', function (e) {
        e.preventDefault();
        let formData = $('#supplierForm').serialize();
        // Clear previous errors
        clearValidationErrors();
        $.ajax({
            type: "POST",
            url: base_url + "/store-supplier",
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    $('#modal_supplier').modal('hide');
                    $('#supplierForm')[0].reset();
                    showSuccessNotification(response.message || 'Supplier added successfully');
                    // Update supplier select with new data and select the newly added supplier
                    if (response.data && response.data.suppliers) {
                        updateSupplierSelect(response.data.suppliers, response.data.supplier.id);
                    }
                } else {
                    showErrorNotification(response.message || 'Something went wrong');
                }
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    // Handle validation errors
                    showValidationErrors('#supplierForm', xhr.responseJSON.errors);
                    showErrorNotification('Please check the form for errors');
                } else {
                    showErrorNotification('An unexpected error occurred');
                }
            }
        });
    });

    // Store the current row being edited
    let currentRow = null;
    
    // Track IMEI/Serial/Medicine data
    let purchaseIMEISerialData = []; // Array to track all IMEI/Serial numbers
    let purchaseMedicineData = []; // Array to track medicine data (quantity + expiry)
    let checkStockAvailability = true; // Variable to enable/disable stock checking
    
    // Function to check if an item already exists in table
    function itemExists(itemId) {
        let exists = false;
        $('#purchaseReturnItems tr').each(function() {
            if ($(this).find('.item-id').val() === itemId) {
                exists = true;
                return false; // break the loop
            }
        });
        return exists;
    }
    
    // Function to check if a variation product's child items already exist in the table
    function variationExists(parentId) {
        let exists = false;
        $('#purchaseReturnItems tr').each(function() {
            if ($(this).find('.parent-id').val() === parentId) {
                exists = true;
                return false; // break the loop
            }
        });
        return exists;
    }

    /**
     * Get current stock for an item (in sale unit). Calls API and invokes callback with current_stock number.
     */
    function getItemCurrentStock(itemId, callback) {
        if (!itemId || !item_current_stock_url) {
            if (callback) callback(0);
            return;
        }
        $.ajax({
            type: 'GET',
            url: item_current_stock_url,
            data: { item_id: itemId },
            dataType: 'json',
            success: function(response) {
                const stock = parseFloat(response.current_stock) || 0;
                if (callback) callback(stock);
            },
            error: function() {
                if (callback) callback(0);
            }
        });
    }

    /**
     * Validate return quantity does not exceed current stock. Sets invalid class and message on quantity input if invalid.
     */
    function validateQuantityAgainstStock(row, callback) {
        const itemId = row.find('.item-id').val();
        const quantityInput = row.find('.quantity');
        const qty = parseFloat(quantityInput.val()) || 0;

        if (!itemId || !checkStockAvailability) {
            quantityInput.removeClass('is-invalid');
            row.find('.quantity').closest('td').find('.invalid-feedback').remove();
            if (callback) callback(true);
            return;
        }

        getItemCurrentStock(itemId, function(currentStock) {
            row.data('current-stock', currentStock);
            quantityInput.attr('data-max-stock', currentStock);

            if (qty > currentStock) {
                quantityInput.addClass('is-invalid');
                let td = quantityInput.closest('td');
                td.find('.invalid-feedback').remove();
                td.append('<div class="invalid-feedback">Return quantity cannot exceed current stock (' + currentStock + ')</div>');
                if (callback) callback(false);
            } else {
                quantityInput.removeClass('is-invalid');
                quantityInput.closest('td').find('.invalid-feedback').remove();
                if (callback) callback(true);
            }
        });
    }
    
    // Function to calculate subtotal of all items
    function calculateSubtotal() {
        let subtotal = 0;
        $('#purchaseReturnItems tr').each(function() {
            const total = parseFloat($(this).find('.total').val()) || 0;
            subtotal += total;
        });
        return subtotal;
    }
    
    // Function to update summary
    function updateSummary() {
        const subtotal = calculateSubtotal();
        let totalQuantity = 0;
        let itemCount = 0;
        
        $('#purchaseReturnItems tr').each(function() {
            const quantity = parseFloat($(this).find('.quantity').val()) || 0;
            totalQuantity += quantity;
            itemCount++;
        });
        
        const formattedSubtotal = subtotal.toFixed(2);
        $('#total_return_amount').val(formattedSubtotal);
        // Edit page may use #grandTotal - keep in sync
        $('#grandTotal').val(formattedSubtotal);
        $('#totalItemCount').text(itemCount);
        $('#totalItemQuantity').text(totalQuantity);
    }
    
    // Function to toggle fields based on status
    function toggleStatusFields() {
        const status = $('#status').val();
        
        // Toggle amount field for taken_by_sup_money_returned
        if (status === 'taken_by_sup_money_returned') {
            $('#amount_field_wrapper').show();
            $('#payment_method_wrapper').show();
            $('#payment_amount_wrapper').show();
        } else {
            $('#amount_field_wrapper').hide();
            $('#amount').val('');
            $('#payment_method_wrapper').hide();
            $('#payment_amount_wrapper').hide();
            $('#paymentMethodSelect').val('').trigger('change');
            $('#payment_amount_input').val('');
            $('#payment_method_id').val('');
            $('#payment_amount').val('');
        }
        
        // Toggle returned IMEI/Serial/Medicine fields for taken_by_sup_pro_returned
        if (status === 'taken_by_sup_pro_returned') {
            // Show returned fields for IMEI/Serial/Medicine items
            $('#purchaseReturnItems tr').each(function() {
                const row = $(this);
                const itemType = row.find('.item-type').val();
                const returnedField = row.find('.returned-imei-serial');
                
                if (itemType === 'IMEI_Product' || itemType === 'Serial_Product' || itemType === 'Medicine_Product') {
                    returnedField.show();
                    returnedField.prop('required', true);
                }
            });
        } else {
            // Hide returned fields
            $('.returned-imei-serial').hide();
            $('.returned-imei-serial').prop('required', false);
            $('.returned-imei-serial').val('');
        }
    }
    
    // Store previous status value - initialize on page load
    let previousStatus = $('#status').val() || '';
    
    // Status change event
    $('#status').on('change', function() {
        const newStatus = $(this).val();
        const hasItems = $('#purchaseReturnItems tr').length > 0;
        const statusSelect = $(this);
        
        // If there are items in cart and status is changing, show confirmation
        if (hasItems && newStatus !== previousStatus) {
            // Temporarily revert to previous status while showing confirmation
            statusSelect.val(previousStatus);
            if (statusSelect.hasClass('select2-hidden-accessible')) {
                statusSelect.trigger('change.select2');
            }
            
            Swal.fire({
                title: 'Are you sure?',
                text: 'If you change status, cart items will be empty.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, clear cart',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // User confirmed - clear cart items and update status
                    $('#purchaseReturnItems').empty();
                    statusSelect.val(newStatus);
                    if (statusSelect.hasClass('select2-hidden-accessible')) {
                        statusSelect.trigger('change.select2');
                    }
                    previousStatus = newStatus;
                    updateSummary();
                    toggleStatusFields();
                } else {
                    // User cancelled - status already reverted, just ensure it's synced
                    if (statusSelect.hasClass('select2-hidden-accessible')) {
                        statusSelect.trigger('change.select2');
                    }
                }
            });
        } else {
            // No items or status not actually changing, proceed normally
            previousStatus = newStatus;
            toggleStatusFields();
        }
    });
    
    // Payment method change event
    $('#paymentMethodSelect').on('change', function() {
        const methodId = $(this).val();
        $('#payment_method_id').val(methodId);
    });
    
    // Payment amount input event
    $('#payment_amount_input').on('input', function() {
        const amount = $(this).val();
        $('#payment_amount').val(amount);
    });
    
    // Function to add a new row to the purchase return items table
    function addNewRow(itemData = null) {
        const rowCount = $('#purchaseReturnItems tr').length;
        const newRow = $('<tr valign="top"></tr>');
        
        // Determine if IMEI/Serial field should be shown
        // For Medicine_Product, check expiry_date_maintain - only show if "Yes"
        let showImeiSerial = false;
        if (itemData) {
            if (itemData.type === 'IMEI_Product' || itemData.type === 'Serial_Product') {
                showImeiSerial = true;
            } else if (itemData.type === 'Medicine_Product') {
                // Get expiry_date_maintain from select option
                const selectedOption = $('#quickItemSelect option[value="' + itemData.id + '"]');
                const expiryDateMaintain = selectedOption.data('expiry-date-maintain') || 'Yes';
                showImeiSerial = (expiryDateMaintain === 'Yes');
            }
        }
        const status = $('#status').val();
        const showReturnedField = status === 'taken_by_sup_pro_returned' && showImeiSerial;
        
        // Purchase return is in sale unit: use sale unit name for display (fallback to purchase unit)
        let unitLabel = itemData ? (itemData.saleUnitName || itemData.purchaseUnitName || '') : '';
        if (!unitLabel && itemData && itemData.id) {
            const selectedOption = $('#quickItemSelect option[value="' + itemData.id + '"]');
            unitLabel = selectedOption.data('sale-unit-name') || selectedOption.data('purchase-unit-name') || '';
        }
        
        // Create row HTML
        let rowHtml = `
            <td>${rowCount + 1}</td>
            <td>
                <input type="hidden" class="item-id" name="items[]" value="${itemData ? itemData.id : ''}">
                <input type="hidden" class="parent-id" name="parent_ids[]" value="${itemData && itemData.parentId ? itemData.parentId : ''}">
                <input type="hidden" class="item-type" name="item_types[]" value="${itemData ? itemData.type : ''}">
                <span class="item-name">${itemData ? itemData.name : ''}</span>
            </td>
            <td class="imei-serial-cell">
                <div class="imei-serial-container">
                    <input type="text" class="form-control imei-serial" name="imei_serial[]" placeholder="IMEI/Serial" readonly style="${showImeiSerial ? '' : 'display:none;'}">
                    ${showImeiSerial ? '<button type="button" class="btn btn-sm btn-primary add-imei-serial" style="display:inline-block;"><i class="ti tabler-plus"></i></button>' : ''}
                </div>
                ${showReturnedField ? `
                <div class="mt-2">
                    <label class="form-label small">Returned ${itemData.type === 'IMEI_Product' ? 'IMEI' : (itemData.type === 'Serial_Product' ? 'Serial' : 'Medicine')}</label>
                    <input type="text" class="form-control returned-imei-serial ${itemData.type === 'Medicine_Product' ? 'datePicker' : ''}" name="returned_imei_serial[]" placeholder="Enter returned ${itemData.type === 'IMEI_Product' ? 'IMEI' : (itemData.type === 'Serial_Product' ? 'Serial' : 'Medicine')}" style="display:block;">
                </div>
                ` : ''}
            </td>
            <td>
                <div class="input-group">
                    <input type="text" class="form-control number-input quantity" name="quantity[]" min="1" value="1" data-max-stock="">
                    <button type="button" class="btn btn-outline-secondary unit-label" type="button">
                        ${unitLabel}
                    </button>
                </div>
            </td>
            <td>
                <input type="text" class="form-control number-input unit-price" name="unit_price[]">
            </td>
            <td>
                <input type="text" class="form-control number-input total" name="total[]" readonly>
            </td>
            <td>
                <button type="button" class="btn text-danger remove-row">
                    <i class="icon-base ti tabler-trash"></i>
                </button>
            </td>
        `;
        
        newRow.html(rowHtml);
        $('#purchaseReturnItems').append(newRow);
        
        // If item data is provided, populate the row (unit price = per sale unit price)
        if (itemData) {
            const unitPrice = itemData.unitPrice != null ? itemData.unitPrice : (itemData.purchasePrice || 0);
            newRow.find('.unit-price').val(unitPrice);
            calculateTotal(newRow);
            // Fetch current stock and validate quantity
            if (itemData.id && checkStockAvailability) {
                getItemCurrentStock(itemData.id, function(currentStock) {
                    newRow.data('current-stock', currentStock);
                    newRow.find('.quantity').attr('data-max-stock', currentStock);
                    validateQuantityAgainstStock(newRow);
                });
            }
            
            // Handle IMEI/Serial/Medicine field based on item type
            // For Medicine_Product, check expiry_date_maintain
            let shouldShowImeiSerial = false;
            if (itemData.type === 'IMEI_Product' || itemData.type === 'Serial_Product') {
                shouldShowImeiSerial = true;
            } else if (itemData.type === 'Medicine_Product') {
                const selectedOption = $('#quickItemSelect option[value="' + itemData.id + '"]');
                const expiryDateMaintain = selectedOption.data('expiry-date-maintain') || 'Yes';
                shouldShowImeiSerial = (expiryDateMaintain === 'Yes');
            }
            
            if (shouldShowImeiSerial) {
                newRow.find('.imei-serial').show();
                newRow.find('.imei-serial').prop('readonly', true);
                newRow.find('.add-imei-serial').show();
            } else {
                newRow.find('.imei-serial').hide();
                newRow.find('.add-imei-serial').hide();
            }
        }
        
        // Initialize row events
        initializeRowEvents(newRow);

        // Initialize date picker for Medicine_Product if expiry_date_maintain is "Yes"
        if (itemData && itemData.type === 'Medicine_Product') {
            const selectedOption = $('#quickItemSelect option[value="' + itemData.id + '"]');
            const expiryDateMaintain = selectedOption.data('expiry-date-maintain') || 'Yes';
            if (expiryDateMaintain === 'Yes') {
                setTimeout(function() {
                    const returnedInput = newRow.find('.datePicker');
                    if (returnedInput.length > 0 && !returnedInput.hasClass('flatpickr-input')) {
                        returnedInput.flatpickr({
                            altInput: true,
                            altFormat: 'Y-m-d',
                            dateFormat: 'Y-m-d',
                            static: true,
                            allowInput: true
                        });
                    }
                }, 100);
            }
        }
    }
    
    // Function to initialize events for a row
    function initializeRowEvents(row) {
        // Add IMEI/Serial/Medicine button click event
        row.find('.add-imei-serial').on('click', function() {
            currentRow = row;
            const unitPrice = row.find('.unit-price').val();
            const itemId = row.find('.item-id').val();
            
            // Get item type from original select options
            const selectedOption = $('#quickItemSelect option[value="' + itemId + '"]');
            const itemType = selectedOption.data('item-type');
            const itemName = selectedOption.data('item-name');
            const purchasePrice = parseFloat(selectedOption.data('purchase-price')) || 0;
            const conversionRate = parseFloat(selectedOption.data('conversion-rate')) || 1;
            const saleUnitPrice = conversionRate > 0 ? (purchasePrice / conversionRate) : purchasePrice;
            const expiryDateMaintain = selectedOption.data('expiry-date-maintain') || 'Yes';
            
            // Set current values in modal (per sale unit price: purchase_price / conversion_rate)
            $('#modal_unit_price').val(unitPrice || saleUnitPrice || purchasePrice);
            
            // Reset modal sections
            $('#imei_serial_section').hide();
            $('#medicine_section').hide();
            purchaseIMEISerialData = [];
            purchaseMedicineData = [];
            
            // Show appropriate section based on item type
            if (itemType === 'IMEI_Product' || itemType === 'Serial_Product') {
                const fieldLabel = itemType === 'IMEI_Product' ? 'IMEI' : 'Serial';
                $('#modal_imei_serial_title').text(`Edit ${fieldLabel} Numbers`);
                $('#imei_serial_label').text(`${fieldLabel} Numbers`);
                $('#purchase_imei_serial_input').attr('placeholder', `Enter ${fieldLabel} Number`);
                $('#imei_serial_section').show();
                
                // If there are existing values, populate them
                const existingValues = row.find('.imei-serial').val();
                if (existingValues) {
                    const values = existingValues.split(',').map(v => v.trim());
                    values.forEach(value => {
                        if (value) {
                            // Add directly to array and DOM without validation (existing values)
                            purchaseIMEISerialData.push(value);
                            const fieldLabel = itemType === 'IMEI_Product' ? 'IMEI' : 'Serial';
                            const listContainer = $('#purchase_imei_serial_list');
                            
                            const fieldHtml = `
                                <div class="input-group mb-2 purchase-imei-serial-item" data-value="${value}">
                                    <input type="text" 
                                        class="form-control purchase-imei-serial-value" 
                                        value="${value}"
                                        readonly />
                                    <button type="button" class="btn btn-outline-danger remove-purchase-imei-serial-field" data-value="${value}">
                                        <i class="ti tabler-trash"></i>
                                    </button>
                                </div>
                            `;
                            
                            listContainer.append(fieldHtml);
                        }
                    });
                }
            } else if (itemType === 'Medicine_Product' && expiryDateMaintain === 'Yes') {
                $('#modal_imei_serial_title').text('Edit Medicine Details');
                $('#medicine_section').show();
                $('#purchase_medicine_quantity_input').val('');
                $('#purchase_medicine_expiry_input').val('');
                $('#purchase_medicine_mmyy_input').val('');
                
                // If there are existing values, populate them
                const existingValues = row.find('.imei-serial').val();
                if (existingValues) {
                    const rowQuantity = row.find('.quantity').val() || 1;
                    
                    if (existingValues.includes(' - ')) {
                        const entries = existingValues.split(',').map(v => v.trim());
                        entries.forEach(entry => {
                            const parts = entry.split(' - ').map(p => p.trim());
                            if (parts.length === 2) {
                                const quantity = parts[0];
                                const expiryDate = parts[1];
                                purchaseMedicineData.push({ quantity: quantity, expiry_date: expiryDate });
                                addPurchaseMedicineField(quantity, expiryDate);
                            }
                        });
                    } else {
                        const expiryDate = existingValues.trim();
                        if (expiryDate) {
                            purchaseMedicineData.push({ quantity: rowQuantity, expiry_date: expiryDate });
                            addPurchaseMedicineField(rowQuantity, expiryDate);
                        }
                    }
                }
            }
            
            // Store item data for modal (include sale unit for row display)
            const saleUnitName = selectedOption.data('sale-unit-name') || selectedOption.data('purchase-unit-name') || '';
            $('#modal_imei_serial').data('itemData', {
                id: itemId,
                name: itemName,
                purchasePrice: purchasePrice,
                unitPrice: saleUnitPrice,
                saleUnitName: saleUnitName,
                purchaseUnitName: selectedOption.data('purchase-unit-name') || '',
                type: itemType
            });
            
            // Initialize date picker for medicine expiry if needed
            if (itemType === 'Medicine_Product' && expiryDateMaintain === 'Yes') {
                setTimeout(function() {
                    const expiryInput = $('#purchase_medicine_expiry_input');
                    if (expiryInput.length > 0 && !expiryInput.hasClass('flatpickr-input')) {
                        expiryInput.flatpickr({
                            altInput: true,
                            altFormat: 'Y-m-d',
                            dateFormat: 'Y-m-d',
                            static: true,
                            allowInput: true
                        });
                    }
                }, 100);
            }
            
            // Show modal
            $('#modal_imei_serial').modal('show');
        });
        
        // Quantity and unit price change events
        row.find('.quantity, .unit-price').on('input', function() {
            calculateTotal(row);
            if ($(this).hasClass('quantity')) {
                validateQuantityAgainstStock(row);
            }
        });
        row.find('.quantity').on('blur', function() {
            validateQuantityAgainstStock(row);
        });
        
        // Remove row button click event
        row.find('.remove-row').on('click', function() {
            row.remove();
            updateRowNumbers();
            updateSummary();
        });
    }
    
    // Function to calculate total for a row
    function calculateTotal(row) {
        const quantity = parseFloat(row.find('.quantity').val()) || 0;
        const unitPrice = parseFloat(row.find('.unit-price').val()) || 0;
        const total = quantity * unitPrice;
        row.find('.total').val(total.toFixed(2));
        
        // Update summary when row total changes
        updateSummary();
    }
    
    // Function to update row numbers
    function updateRowNumbers() {
        $('#purchaseReturnItems tr').each(function(index) {
            $(this).find('td:first').text(index + 1);
        });
    }
    
    // Quick item select change event
    $('#quickItemSelect').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const itemId = $(this).val();
        const itemType = selectedOption.data('item-type');
        
        if (!itemId) return;
        
        // Purchase return is in sale unit (PCS). Unit price = purchase price / conversion rate.
        // e.g. purchase_price 120 (per BOX), conversion 12 → single unit price 120/12 = 10
        const purchasePrice = parseFloat(selectedOption.data('purchase-price')) || 0;
        const conversionRate = parseFloat(selectedOption.data('conversion-rate')) || 1;
        const saleUnitPrice = conversionRate > 0 ? (purchasePrice / conversionRate) : purchasePrice;
        
        const itemData = {
            id: itemId,
            name: selectedOption.data('item-name'),
            purchasePrice: purchasePrice,
            unitPrice: saleUnitPrice,
            purchaseUnitName: selectedOption.data('purchase-unit-name') || '',
            saleUnitName: selectedOption.data('sale-unit-name') || selectedOption.data('purchase-unit-name') || '',
            conversionRate: conversionRate,
            type: itemType
        };
        
        // Check for duplicate items for General_Product and Installment_Product
        if (itemType === 'General_Product' || itemType === 'Installment_Product') {
            if (itemExists(itemId)) {
                showErrorNotification('This item already exists in the purchase return list.');
                $(this).val(''); // Reset the select
                return;
            }
            // Add item directly to the table
            addNewRow(itemData);
        } else if (itemType === 'Variation_Product') {
            // Check if this variation product's child items already exist
            if (variationExists(itemId)) {
                showErrorNotification('This variation product already exists in the purchase return list.');
                $(this).val(''); // Reset the select
                return;
            }
            
            // Fetch child items via AJAX
            $.ajax({
                url: variation_child_items_url || (base_url + '/api/purchase-return/variation-child-items'),
                type: 'GET',
                data: { parent_id: itemId },
                success: function(response) {
                    if (response.length > 0) {
                        // Get parent name from first child item or from select option
                        const parentName = selectedOption.data('item-parent-name');
                        
                        // Add each child item to the table with parent ID
                        response.forEach(function(childItem) {
                            // Format: "Parent Name - Child Name (Code)"
                            let displayName = childItem.name + ' (' + childItem.code + ')';
                            if (parentName) {
                                displayName = parentName + ' - ' + displayName;
                            }
                            
                            const childPurchasePrice = parseFloat(childItem.purchase_price) || 0;
                            const childConversionRate = parseFloat(childItem.conversion_rate) || 1;
                            const childSaleUnitPrice = childConversionRate > 0 ? (childPurchasePrice / childConversionRate) : childPurchasePrice;
                            const childData = {
                                id: childItem.id,
                                name: displayName,
                                purchasePrice: childPurchasePrice,
                                unitPrice: childSaleUnitPrice,
                                purchaseUnitName: childItem.purchase_unit?.unit_name || selectedOption.data('purchase-unit-name') || '',
                                saleUnitName: childItem.sale_unit?.unit_name || selectedOption.data('sale-unit-name') || '',
                                conversionRate: childConversionRate,
                                type: '0', // Child items have type '0'
                                parentId: itemId // Store the parent ID
                            };
                            addNewRow(childData);
                        });
                    } else {
                        showErrorNotification('No child items found for this variation product.');
                    }
                },
                error: function() {
                    showErrorNotification('Error fetching child items. Please try again.');
                }
            });
        } else {
            // Check if Medicine_Product with expiry_date_maintain = "No" - treat like normal product
            const selectedOption = $('#quickItemSelect option[value="' + itemData.id + '"]');
            const expiryDateMaintain = selectedOption.data('expiry-date-maintain') || 'Yes';
            
            if (itemType === 'Medicine_Product' && expiryDateMaintain === 'No') {
                // Treat like normal product - just add the row
                addNewRow(itemData);
            } else if (itemType === 'IMEI_Product' || itemType === 'Serial_Product' || (itemType === 'Medicine_Product' && expiryDateMaintain === 'Yes')) {
                // Open modal for IMEI/Serial/Medicine products (unit price = per sale unit, no double conversion)
                currentRow = null;
                $('#modal_unit_price').val(itemData.unitPrice != null ? itemData.unitPrice : itemData.purchasePrice);
                
                // Reset modal sections
                $('#imei_serial_section').hide();
                $('#medicine_section').hide();
                purchaseIMEISerialData = [];
                purchaseMedicineData = [];
                
                // Show appropriate section based on item type
                if (itemType === 'IMEI_Product' || itemType === 'Serial_Product') {
                    const fieldLabel = itemType === 'IMEI_Product' ? 'IMEI' : 'Serial';
                    $('#modal_imei_serial_title').text(`Add ${fieldLabel} Numbers`);
                    $('#imei_serial_label').text(`${fieldLabel} Numbers`);
                    $('#purchase_imei_serial_input').attr('placeholder', `Enter ${fieldLabel} Number`);
                    $('#imei_serial_section').show();
                    $('#purchase_imei_serial_list').empty();
                    $('#purchase_imei_serial_input').val('');
                } else if (itemType === 'Medicine_Product' && expiryDateMaintain === 'Yes') {
                    $('#modal_imei_serial_title').text('Add Medicine Details');
                    $('#medicine_section').show();
                    $('#purchase_medicine_list').empty();
                $('#purchase_medicine_quantity_input').val('');
                $('#purchase_medicine_expiry_input').val('');
                $('#purchase_medicine_mmyy_input').val('');
            }
            
            // Store item data for later use when saving from modal
            $('#modal_imei_serial').data('itemData', itemData);
            
            // Initialize date picker for medicine expiry if needed
            if (itemType === 'Medicine_Product' && expiryDateMaintain === 'Yes') {
                setTimeout(function() {
                    const expiryInput = $('#purchase_medicine_expiry_input');
                    if (expiryInput.length > 0 && !expiryInput.hasClass('flatpickr-input')) {
                        expiryInput.flatpickr({
                            altInput: true,
                            altFormat: 'Y-m-d',
                            dateFormat: 'Y-m-d',
                            static: true,
                            allowInput: true
                        });
                    }
                }, 100);
            }
            
            // Show modal
            $('#modal_imei_serial').modal('show');
            }
        }
        
        // Reset the select
        $(this).val('');
    });
    
    // Add IMEI/Serial field on Enter key
    $(document).on('keypress', '.purchase-imei-serial-input', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            const value = $(this).val().trim();
            if (value) {
                const input = $(this);
                // Disable input while checking
                input.prop('disabled', true);
                addPurchaseIMEISerialField(value, function(success) {
                    input.prop('disabled', false);
                    if (success) {
                        input.val('').focus();
                    } else {
                        // Don't clear input on error, just focus
                        input.focus();
                    }
                });
            }
        }
    });
    
    // Add IMEI/Serial field on Plus button click
    $(document).on('click', '.add-purchase-imei-serial-field', function() {
        const input = $('#purchase_imei_serial_input');
        const value = input.val().trim();
        const button = $(this);
        if (value) {
            // Disable input and button while checking
            input.prop('disabled', true);
            button.prop('disabled', true);
            addPurchaseIMEISerialField(value, function(success) {
                input.prop('disabled', false);
                button.prop('disabled', false);
                if (success) {
                    input.val('').focus();
                } else {
                    // Don't clear input on error, just focus
                    input.focus();
                }
            });
        } else {
            input.focus();
        }
        return false;
    });
    
    // Function to add IMEI/Serial field with stock availability check
    function addPurchaseIMEISerialField(value, callback) {
        // Sync array with DOM to ensure accuracy
        purchaseIMEISerialData = [];
        $('#purchase_imei_serial_list .purchase-imei-serial-item').each(function() {
            const existingValue = $(this).find('.purchase-imei-serial-value').val();
            if (existingValue && existingValue.trim()) {
                purchaseIMEISerialData.push(existingValue.trim());
            }
        });
        
        // Check for duplicates in current list
        if (purchaseIMEISerialData.includes(value)) {
            const itemData = $('#modal_imei_serial').data('itemData');
            const fieldLabel = itemData && itemData.type === 'IMEI_Product' ? 'IMEI' : 'Serial';
            
            // Show user-friendly message with list of existing IMEIs
            const existingList = purchaseIMEISerialData.length > 0 ? purchaseIMEISerialData.join(', ') : 'none';
            const message = `${fieldLabel} number "${value}" is already in the list. Current ${fieldLabel}s in list: ${existingList}. Please enter a different ${fieldLabel} or remove the existing one first.`;
            showErrorNotification(message);
            if (callback) callback(false);
            return false;
        }
        
        // Get item data for stock check
        const itemData = $('#modal_imei_serial').data('itemData');
        if (!itemData || !itemData.id) {
            showErrorNotification('Item data not found');
            if (callback) callback(false);
            return false;
        }
        
        // Get current status to determine which check to perform
        const status = $('#status').val();
        const shouldCheckStock = status === 'taken_by_sup_pro_not_returned' || 
                                  status === 'taken_by_sup_pro_returned' || 
                                  status === 'taken_by_sup_money_returned';
        
        // Check stock availability if enabled and status requires it
        if (checkStockAvailability && shouldCheckStock && (itemData.type === 'IMEI_Product' || itemData.type === 'Serial_Product')) {
            // Prepare data for stock check
            const itemDetails = [{
                type: itemData.type === 'IMEI_Product' ? 'imei' : 'serial',
                value: value
            }];
            
            // Make AJAX call to check stock availability
            let routePath = route('purchase.check-stock-availability', [], false, Ziggy);
            let checkStockUrl = base_url + routePath;
            $.ajax({
                type: 'POST',
                url: checkStockUrl,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    item_id: itemData.id,
                    item_type: itemData.type,
                    item_details: itemDetails
                },
                dataType: 'json',
                success: function(response) {
                    // For purchase return, we want IMEI/Serial to EXIST in stock for this product
                    // The API returns available: false when IMEI exists in stock (inverted logic)
                    // So we need to check: if available is false, it means IMEI exists in stock - allow it
                    if (!response.available) {
                        // IMEI/Serial exists in stock for this product, allow it
                        purchaseIMEISerialData.push(value);
                        const fieldLabel = itemData.type === 'IMEI_Product' ? 'IMEI' : 'Serial';
                        const listContainer = $('#purchase_imei_serial_list');
                        
                        const fieldHtml = `
                            <div class="input-group mb-2 purchase-imei-serial-item" data-value="${value}">
                                <input type="text" 
                                    class="form-control purchase-imei-serial-value" 
                                    value="${value}"
                                    readonly />
                                <button type="button" class="btn btn-outline-danger remove-purchase-imei-serial-field" data-value="${value}">
                                    <i class="ti tabler-trash"></i>
                                </button>
                            </div>
                        `;
                        
                        listContainer.append(fieldHtml);
                        // Auto-focus the input after successful add
                        $('#purchase_imei_serial_input').focus();
                        if (callback) callback(true);
                    } else {
                        // IMEI/Serial doesn't exist in stock for this product - don't allow
                        const fieldLabel = itemData.type === 'IMEI_Product' ? 'IMEI' : 'Serial';
                        const message = response.message || `${fieldLabel} number "${value}" does not exist in stock for this product. Please enter a valid ${fieldLabel} that exists in stock for this product.`;
                        showErrorNotification(message);
                        // Keep the input value and focus on it
                        $('#purchase_imei_serial_input').focus();
                        if (callback) callback(false);
                    }
                },
                error: function(xhr) {
                    console.error('Error checking stock availability:', xhr);
                    // On error, don't allow adding
                    showErrorNotification('Error checking stock availability. Please try again.');
                    if (callback) callback(false);
                }
            });
        } else {
            // Stock check disabled or not IMEI/Serial product, add directly
            purchaseIMEISerialData.push(value);
            const itemData = $('#modal_imei_serial').data('itemData');
            const fieldLabel = itemData && itemData.type === 'IMEI_Product' ? 'IMEI' : 'Serial';
            const listContainer = $('#purchase_imei_serial_list');
            
            const fieldHtml = `
                <div class="input-group mb-2 purchase-imei-serial-item" data-value="${value}">
                    <input type="text" 
                        class="form-control purchase-imei-serial-value" 
                        value="${value}"
                        readonly />
                    <button type="button" class="btn btn-outline-danger remove-purchase-imei-serial-field" data-value="${value}">
                        <i class="ti tabler-trash"></i>
                    </button>
                </div>
            `;
            
            listContainer.append(fieldHtml);
            // Auto-focus the input after successful add
            $('#purchase_imei_serial_input').focus();
            if (callback) callback(true);
        }
    }
    
    // Remove IMEI/Serial field
    $(document).on('click', '.remove-purchase-imei-serial-field', function() {
        const value = $(this).data('value');
        // Remove from DOM first
        $(this).closest('.purchase-imei-serial-item').remove();
        
        // Sync array with DOM to ensure accuracy
        purchaseIMEISerialData = [];
        $('#purchase_imei_serial_list .purchase-imei-serial-item').each(function() {
            const existingValue = $(this).find('.purchase-imei-serial-value').val();
            if (existingValue && existingValue.trim()) {
                purchaseIMEISerialData.push(existingValue.trim());
            }
        });
        
        // Focus back on input after removal
        $('#purchase_imei_serial_input').focus();
    });
    
    // Validate returned IMEI/Serial field on blur or enter
    $(document).on('blur', '.returned-imei-serial', function() {
        const input = $(this);
        const value = input.val().trim();
        const row = input.closest('tr');
        const itemType = row.find('.item-type').val();
        const status = $('#status').val();
        
        // Only validate if status is taken_by_sup_pro_returned and value is provided
        if (status === 'taken_by_sup_pro_returned' && value && 
            (itemType === 'IMEI_Product' || itemType === 'Serial_Product')) {
            validateReturnedIMEISerial(input, value, row, itemType);
        } else {
            // Clear any previous errors
            input.removeClass('is-invalid');
            input.closest('td').find('.invalid-feedback').remove();
        }
    });
    
    // Validate returned IMEI/Serial on Enter key
    $(document).on('keypress', '.returned-imei-serial', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            const input = $(this);
            const value = input.val().trim();
            const row = input.closest('tr');
            const itemType = row.find('.item-type').val();
            const status = $('#status').val();
            
            if (status === 'taken_by_sup_pro_returned' && value && 
                (itemType === 'IMEI_Product' || itemType === 'Serial_Product')) {
                validateReturnedIMEISerial(input, value, row, itemType);
            }
        }
    });
    
    // Function to validate returned IMEI/Serial
    function validateReturnedIMEISerial(input, value, row, itemType) {
        // Disable input while checking
        input.prop('disabled', true);
        
        // Get item ID
        const itemId = row.find('.item-id').val();
        if (!itemId) {
            input.prop('disabled', false);
            showErrorNotification('Item ID not found');
            return;
        }
        
        // First check: Returned IMEI should not be the same as return-out IMEI
        const returnOutImei = row.find('.imei-serial').val();
        if (returnOutImei && returnOutImei.trim() === value) {
            input.prop('disabled', false);
            const fieldLabel = itemType === 'IMEI_Product' ? 'IMEI' : 'Serial';
            const message = `Returned ${fieldLabel} cannot be the same as the return-out ${fieldLabel}. Please enter a different ${fieldLabel}.`;
            
            input.addClass('is-invalid');
            let wrapper = input.closest('td');
            wrapper.find('.invalid-feedback').remove();
            wrapper.append(`<div class="invalid-feedback">${message}</div>`);
            
            showErrorNotification(message);
            return;
        }
        
        // Prepare data for check
        const itemDetails = [{
            type: itemType === 'IMEI_Product' ? 'imei' : 'serial',
            value: value
        }];
        
        // Make AJAX call to check if IMEI/Serial exists in system
        let routePath = route('purchase.check-existing-imei-serial', [], false, Ziggy);
        let checkUrl = base_url + routePath;
        
        $.ajax({
            type: 'POST',
            url: checkUrl,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                item_id: itemId,
                item_type: itemType,
                item_details: itemDetails
            },
            dataType: 'json',
            success: function(response) {
                input.prop('disabled', false);
                
                if (response.available) {
                    // IMEI/Serial doesn't exist in system, allow it
                    input.removeClass('is-invalid');
                    input.closest('td').find('.invalid-feedback').remove();
                } else {
                    // IMEI/Serial already exists in system, don't allow
                    const fieldLabel = itemType === 'IMEI_Product' ? 'IMEI' : 'Serial';
                    const message = response.message || `${fieldLabel} number "${value}" already exists in the system. Please enter a unique ${fieldLabel} that doesn't exist in the system.`;
                    
                    input.addClass('is-invalid');
                    let wrapper = input.closest('td');
                    wrapper.find('.invalid-feedback').remove();
                    wrapper.append(`<div class="invalid-feedback">${message}</div>`);
                    
                    showErrorNotification(message);
                }
            },
            error: function(xhr) {
                input.prop('disabled', false);
                console.error('Error checking existing IMEI/Serial:', xhr);
                showErrorNotification('Error validating returned IMEI/Serial. Please try again.');
            }
        });
    }
    
    // MM/YY input handler - Auto-formatting like bank card expiry
    $(document).on('input', '.purchase-medicine-mmyy-input', function() {
        let value = $(this).val().replace(/\D/g, ''); // Remove non-digits
        let formattedValue = value;
        
        // Auto-add "/" after 2 digits (month)
        if (value.length >= 2) {
            formattedValue = value.substring(0, 2) + '/' + value.substring(2, 4);
        }
        
        $(this).val(formattedValue);
        
        // If MM/YY is complete (MM/YY format), auto-set expiry date to last day of that month
        if (formattedValue.length === 5) {
            let parts = formattedValue.split('/');
            let month = parseInt(parts[0]);
            let year = parseInt(parts[1]);
            
            // Validate month (01-12)
            if (month >= 1 && month <= 12) {
                // Convert 2-digit year to 4-digit (assume 2000-2099)
                let fullYear = year < 100 ? 2000 + year : year;
                
                // Get last day of the month
                let lastDay = new Date(fullYear, month, 0).getDate();
                let expiryDate = fullYear + '-' + String(month).padStart(2, '0') + '-' + String(lastDay).padStart(2, '0');
                
                // Set the expiry date field
                let expiryInput = $('#purchase_medicine_expiry_input');
                
                // If flatpickr is initialized, use setDate method
                if (expiryInput.length > 0) {
                    let fpInstance = expiryInput[0]._flatpickr;
                    if (fpInstance) {
                        fpInstance.setDate(expiryDate, false);
                    } else {
                        expiryInput.val(expiryDate);
                    }
                }
            }
        }
    });
    
    // MM/YY keypress handler - Allow only numbers and auto-format
    $(document).on('keypress', '.purchase-medicine-mmyy-input', function(e) {
        // Allow backspace, delete, tab, escape, enter
        if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
            // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
            (e.keyCode === 65 && e.ctrlKey === true) ||
            (e.keyCode === 67 && e.ctrlKey === true) ||
            (e.keyCode === 86 && e.ctrlKey === true) ||
            (e.keyCode === 88 && e.ctrlKey === true)) {
            return;
        }
        // Ensure that it is a number and stop the keypress
        if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
            e.preventDefault();
        }
    });
    
    // Add Medicine field on Enter key (Quantity or Expiry Date)
    $(document).on('keypress', '.purchase-medicine-quantity-input, .purchase-medicine-expiry-input', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            const quantityInput = $('#purchase_medicine_quantity_input');
            const expiryInput = $('#purchase_medicine_expiry_input');
            const mmyyInput = $('#purchase_medicine_mmyy_input');
            const quantity = quantityInput.val().trim();
            const expiryDate = expiryInput.val().trim();
            
            if (quantity && expiryDate) {
                let result = addPurchaseMedicineField(quantity, expiryDate);
                if (result) {
                    quantityInput.val('');
                    expiryInput.val('');
                    mmyyInput.val('');
                } else {
                    quantityInput.focus();
                }
            }
        }
    });
    
    // Add Medicine field on Plus button click
    $(document).on('click', '.add-purchase-medicine-field', function() {
        const quantityInput = $('#purchase_medicine_quantity_input');
        const expiryInput = $('#purchase_medicine_expiry_input');
        const mmyyInput = $('#purchase_medicine_mmyy_input');
        const quantity = quantityInput.val().trim();
        const expiryDate = expiryInput.val().trim();
        
        if (quantity && expiryDate) {
            let result = addPurchaseMedicineField(quantity, expiryDate);
            if (result) {
                quantityInput.val('').focus();
                expiryInput.val('');
                mmyyInput.val('');
            } else {
                quantityInput.focus();
            }
        } else {
            if (!quantity) {
                quantityInput.focus();
            } else {
                expiryInput.focus();
            }
        }
    });
    
    // Function to add Medicine field (Quantity + Expiry Date)
    function addPurchaseMedicineField(quantity, expiryDate) {
        // Check for duplicate date
        const duplicateDateFound = purchaseMedicineData.some(function(item) {
            return item.expiry_date == expiryDate;
        });
        
        if (duplicateDateFound) {
            showErrorNotification('This expiry date already exists. Please increase quantity or use a different date.');
            return false;
        }
        
        // Check for exact duplicate (quantity + expiry_date)
        const duplicateFound = purchaseMedicineData.some(function(item) {
            return item.quantity == quantity && item.expiry_date == expiryDate;
        });
        
        if (duplicateFound) {
            showErrorNotification(`The combination of Quantity "${quantity}" and Expiry Date "${expiryDate}" already exists. Please enter a unique combination.`);
            return false;
        }
        
        // Add to tracking array
        purchaseMedicineData.push({
            quantity: quantity,
            expiry_date: expiryDate
        });
        
        const listContainer = $('#purchase_medicine_list');
        
        const fieldHtml = `
            <div class="input-group mb-2 purchase-medicine-item" data-quantity="${quantity}" data-expiry="${expiryDate}">
                <input type="text" 
                    class="form-control purchase-medicine-value" 
                    value="${quantity} - ${expiryDate}"
                    data-quantity="${quantity}"
                    data-expiry="${expiryDate}"
                    readonly />
                <button type="button" class="btn btn-outline-danger remove-purchase-medicine-field" data-quantity="${quantity}" data-expiry="${expiryDate}">
                    <i class="ti tabler-trash"></i>
                </button>
            </div>
        `;
        
        listContainer.append(fieldHtml);
        return true;
    }
    
    // Remove Medicine field
    $(document).on('click', '.remove-purchase-medicine-field', function() {
        const quantity = $(this).data('quantity');
        const expiryDate = $(this).data('expiry');
        
        // Remove from tracking array
        purchaseMedicineData = purchaseMedicineData.filter(function(item) {
            return !(item.quantity == quantity && item.expiry_date == expiryDate);
        });
        
        $(this).closest('.purchase-medicine-item').remove();
    });
    
    // Save IMEI/Serial/Medicine button
    $('#save_imei_serial').on('click', function() {
        const unitPrice = $('#modal_unit_price').val();
        
        if (!unitPrice || parseFloat(unitPrice) <= 0) {
            showErrorNotification('Please enter a valid unit price');
            return;
        }
        
        // Get item data from modal
        const itemData = $('#modal_imei_serial').data('itemData');
        if (!itemData) {
            showErrorNotification('Item data not found');
            return;
        }
        
        if (itemData.type === 'IMEI_Product' || itemData.type === 'Serial_Product') {
            // Get IMEI/Serial values
            const imeiSerialValues = purchaseIMEISerialData;
            
            if (imeiSerialValues.length === 0) {
                const fieldLabel = itemData.type === 'IMEI_Product' ? 'IMEI' : 'Serial';
                showErrorNotification(`Please enter at least one ${fieldLabel} number`);
                return;
            }
            
            // Create separate rows for each IMEI/Serial
            if (currentRow) {
                // If editing existing row, remove it first and create new rows
                currentRow.remove();
                updateRowNumbers();
            }
            
            // Create one row per IMEI/Serial
            imeiSerialValues.forEach((value, index) => {
                const rowCount = $('#purchaseReturnItems tr').length;
                const newRow = $('<tr valign="top"></tr>');
                const status = $('#status').val();
                const showReturnedField = status === 'taken_by_sup_pro_returned';
                
                const rowHtml = `
                    <td>${rowCount + 1}</td>
                    <td>
                        <input type="hidden" class="item-id" name="items[]" value="${itemData.id}">
                        <input type="hidden" class="parent-id" name="parent_ids[]" value="">
                        <input type="hidden" class="item-type" name="item_types[]" value="${itemData.type}">
                        <span class="item-name">${itemData.name}</span>
                    </td>
                    <td class="imei-serial-cell">
                        <div class="imei-serial-container">
                            <input type="text" class="form-control imei-serial" name="imei_serial[]" placeholder="IMEI/Serial" readonly value="${value}">
                        </div>
                        ${showReturnedField ? `
                        <div class="mt-2">
                            <label class="form-label small">Returned ${itemData.type === 'IMEI_Product' ? 'IMEI' : 'Serial'}</label>
                            <input type="text" class="form-control returned-imei-serial ${itemData.type === 'Medicine_Product' ? 'datePicker' : ''}" name="returned_imei_serial[]" placeholder="Enter returned ${itemData.type === 'IMEI_Product' ? 'IMEI' : 'Serial'}" style="display:block;">
                        </div>
                        ` : ''}
                    </td>
                    <td>
                        <div class="input-group">
                            <input type="text" class="form-control number-input quantity" name="quantity[]" min="1" value="1" readonly data-max-stock="">
                            <button type="button" class="btn btn-outline-secondary unit-label" type="button">
                                ${itemData.saleUnitName || itemData.purchaseUnitName || ''}
                            </button>
                        </div>
                    </td>
                    <td>
                        <input type="text" class="form-control number-input unit-price" name="unit_price[]" value="${unitPrice}">
                    </td>
                    <td>
                        <input type="text" class="form-control number-input total" name="total[]" readonly value="${parseFloat(unitPrice).toFixed(2)}">
                    </td>
                    <td>
                        <button type="button" class="btn text-danger remove-row">
                            <i class="icon-base ti tabler-trash"></i>
                        </button>
                    </td>
                `;
                
                newRow.html(rowHtml);
                $('#purchaseReturnItems').append(newRow);
                initializeRowEvents(newRow);

                // Initialize date picker for Medicine_Product if expiry_date_maintain is "Yes"
                if (itemData.type === 'Medicine_Product') {
                    const selectedOption = $('#quickItemSelect option[value="' + itemData.id + '"]');
                    const expiryDateMaintain = selectedOption.data('expiry-date-maintain') || 'Yes';
                    if (expiryDateMaintain === 'Yes') {
                        setTimeout(function() {
                            const returnedInput = newRow.find('.datePicker');
                            if (returnedInput.length > 0 && !returnedInput.hasClass('flatpickr-input')) {
                                returnedInput.flatpickr({
                                    altInput: true,
                                    altFormat: 'Y-m-d',
                                    dateFormat: 'Y-m-d',
                                    static: true,
                                    allowInput: true
                                });
                            }
                        }, 100);
                    }
                }

            });
            
            // Update summary and close modal
            updateSummary();
            purchaseIMEISerialData = [];
            purchaseMedicineData = [];
            $('#modal_imei_serial').modal('hide');
            return;
            
        } else if (itemData.type === 'Medicine_Product') {
            // Get Medicine values
            if (purchaseMedicineData.length === 0) {
                showErrorNotification('Please add at least one medicine entry (quantity + expiry date)');
                return;
            }
            
            // Create separate rows for Medicine products
            if (currentRow) {
                // If editing existing row, remove it first
                currentRow.remove();
                updateRowNumbers();
            }
            
            // Create one row per medicine entry (quantity + expiry date)
            purchaseMedicineData.forEach((medicineItem, index) => {
                const rowCount = $('#purchaseReturnItems tr').length;
                const newRow = $('<tr valign="top"></tr>');
                const medicineQuantity = parseFloat(medicineItem.quantity) || 1;
                const medicineTotal = medicineQuantity * parseFloat(unitPrice);
                const status = $('#status').val();
                const showReturnedField = status === 'taken_by_sup_pro_returned';
                
                const rowHtml = `
                    <td>${rowCount + 1}</td>
                    <td>
                        <input type="hidden" class="item-id" name="items[]" value="${itemData.id}">
                        <input type="hidden" class="parent-id" name="parent_ids[]" value="">
                        <input type="hidden" class="item-type" name="item_types[]" value="${itemData.type}">
                        <span class="item-name">${itemData.name}</span>
                    </td>
                    <td class="imei-serial-cell">
                        <div class="imei-serial-container">
                            <input type="text" class="form-control imei-serial" name="imei_serial[]" placeholder="Medicine" readonly value="${medicineItem.expiry_date}" data-full-value="${medicineItem.quantity} - ${medicineItem.expiry_date}">
                        </div>
                        ${showReturnedField ? `
                        <div class="mt-2">
                            <label class="form-label small">Returned Medicine</label>
                            <input type="text" class="form-control returned-imei-serial ${itemData.type === 'Medicine_Product' ? 'datePicker' : ''}" name="returned_imei_serial[]" placeholder="Enter returned medicine" style="display:block;">
                        </div>
                        ` : ''}
                    </td>
                    <td>
                        <div class="input-group">
                            <input type="text" class="form-control number-input quantity" name="quantity[]" min="1" value="${medicineQuantity}" readonly data-max-stock="">
                            <button type="button" class="btn btn-outline-secondary unit-label" type="button">
                                ${itemData.saleUnitName || itemData.purchaseUnitName || ''}
                            </button>
                        </div>
                    </td>
                    <td>
                        <input type="text" class="form-control number-input unit-price" name="unit_price[]" value="${unitPrice}">
                    </td>
                    <td>
                        <input type="text" class="form-control number-input total" name="total[]" readonly value="${medicineTotal.toFixed(2)}">
                    </td>
                    <td>
                        <button type="button" class="btn text-danger remove-row">
                            <i class="icon-base ti tabler-trash"></i>
                        </button>
                    </td>
                `;
                
                newRow.html(rowHtml);
                $('#purchaseReturnItems').append(newRow);
                initializeRowEvents(newRow);

                if (itemData.type === 'Medicine_Product') {
                    setTimeout(function() {
                        const returnedInput = newRow.find('.datePicker');
                        if (returnedInput.length > 0 && !returnedInput.hasClass('flatpickr-input')) {
                            returnedInput.flatpickr({
                                altInput: true,
                                altFormat: 'Y-m-d',
                                dateFormat: 'Y-m-d',
                                static: true,
                                allowInput: true
                            });
                        }
                    }, 100);
                }
            });
        }
        
        // Reset modal data
        purchaseIMEISerialData = [];
        purchaseMedicineData = [];
        
        // Update summary
        updateSummary();
        
        // Close modal
        $('#modal_imei_serial').modal('hide');
    });
    
    // Function to validate required fields
    function validateRequiredFields() {
        let hasError = false;
        
        // Clear previous errors
        clearValidationErrors();
        
        // Validate Supplier
        const supplierId = $('#supplier_id').val();
        if (!supplierId || supplierId === '' || supplierId === null) {
            $('#supplier_id').addClass('is-invalid');
            let supplierWrapper = $('#supplier_id').closest('.validate_wrapper');
            if (supplierWrapper.find('.invalid-feedback').length === 0) {
                supplierWrapper.append('<div class="invalid-feedback">Supplier is required</div>');
            }
            if ($('#supplier_id').next('.select2-container').length) {
                $('#supplier_id').next('.select2-container').addClass('is-invalid');
            }
            hasError = true;
        } else {
            $('#supplier_id').removeClass('is-invalid');
            $('#supplier_id').closest('.validate_wrapper').find('.invalid-feedback').remove();
            if ($('#supplier_id').next('.select2-container').length) {
                $('#supplier_id').next('.select2-container').removeClass('is-invalid');
            }
        }
        
        // Validate Date
        const date = $('#date').val();
        if (!date || date.trim() === '') {
            $('#date').addClass('is-invalid');
            let dateWrapper = $('#date').closest('.validate_wrapper');
            if (dateWrapper.find('.invalid-feedback').length === 0) {
                dateWrapper.append('<div class="invalid-feedback">Date is required</div>');
            }
            hasError = true;
        } else {
            $('#date').removeClass('is-invalid');
            $('#date').closest('.validate_wrapper').find('.invalid-feedback').remove();
        }
        
        // Purchase Date is optional, no validation needed
        
        // Validate Status
        const status = $('#status').val();
        if (!status || status.trim() === '') {
            $('#status').addClass('is-invalid');
            let statusWrapper = $('#status').closest('.validate_wrapper');
            if (statusWrapper.find('.invalid-feedback').length === 0) {
                statusWrapper.append('<div class="invalid-feedback">Status is required</div>');
            }
            if ($('#status').next('.select2-container').length) {
                $('#status').next('.select2-container').addClass('is-invalid');
            }
            hasError = true;
        } else {
            $('#status').removeClass('is-invalid');
            $('#status').closest('.validate_wrapper').find('.invalid-feedback').remove();
            if ($('#status').next('.select2-container').length) {
                $('#status').next('.select2-container').removeClass('is-invalid');
            }
        }
        
        // Validate Items - Check if there are any items in the table
        if ($('#purchaseReturnItems tr').length === 0) {
            showErrorNotification('Please add at least one item to the purchase return');
            hasError = true;
        } else {
            // Validate each purchase return item
            $('#purchaseReturnItems tr').each(function(index) {
                const row = $(this);
                const itemIdInput = row.find('.item-id');
                let itemId = null;
                let itemType = null;
                
                if (itemIdInput.length > 0) {
                    itemId = itemIdInput.val();
                    itemType = row.find('.item-type').val() || $('#quickItemSelect option[value="' + itemId + '"]').data('item-type');
                }
                
                if (!itemId) {
                    row.addClass('table-danger');
                    hasError = true;
                    return;
                } else {
                    row.removeClass('table-danger');
                }
                
                // Validate quantity
                const quantity = parseFloat(row.find('.quantity').val()) || 0;
                if (isNaN(quantity) || quantity <= 0) {
                    row.find('.quantity').addClass('is-invalid');
                    let quantityWrapper = row.find('.quantity').closest('td');
                    if (quantityWrapper.find('.invalid-feedback').length === 0) {
                        quantityWrapper.append('<div class="invalid-feedback">Quantity must be greater than 0</div>');
                    }
                    hasError = true;
                } else {
                    row.find('.quantity').removeClass('is-invalid');
                    row.find('.quantity').closest('td').find('.invalid-feedback').remove();
                }
                
                // Validate return quantity does not exceed current stock (when data-max-stock is set)
                if (checkStockAvailability && !hasError) {
                    const maxStock = row.find('.quantity').attr('data-max-stock');
                    if (maxStock !== undefined && maxStock !== '' && quantity > parseFloat(maxStock)) {
                        row.find('.quantity').addClass('is-invalid');
                        let quantityWrapper = row.find('.quantity').closest('td');
                        if (quantityWrapper.find('.invalid-feedback').length === 0) {
                            quantityWrapper.append('<div class="invalid-feedback">Return quantity cannot exceed current stock (' + maxStock + ')</div>');
                        }
                        hasError = true;
                    }
                }
                
                // Validate unit price
                const unitPrice = parseFloat(row.find('.unit-price').val()) || 0;
                if (isNaN(unitPrice) || unitPrice < 0) {
                    row.find('.unit-price').addClass('is-invalid');
                    let unitPriceWrapper = row.find('.unit-price').closest('td');
                    if (unitPriceWrapper.find('.invalid-feedback').length === 0) {
                        unitPriceWrapper.append('<div class="invalid-feedback">Unit price must be 0 or greater</div>');
                    }
                    hasError = true;
                } else {
                    row.find('.unit-price').removeClass('is-invalid');
                    row.find('.unit-price').closest('td').find('.invalid-feedback').remove();
                }
                
                // Check if IMEI/Serial is required for IMEI/Serial/Medicine products
                // For Medicine_Product, check expiry_date_maintain - only require if "Yes"
                let requiresImeiSerial = false;
                if (itemType === 'IMEI_Product' || itemType === 'Serial_Product') {
                    requiresImeiSerial = true;
                } else if (itemType === 'Medicine_Product') {
                    const selectedOption = $('#quickItemSelect option[value="' + itemId + '"]');
                    const expiryDateMaintain = selectedOption.data('expiry-date-maintain') || 'Yes';
                    requiresImeiSerial = (expiryDateMaintain === 'Yes');
                }
                
                if (requiresImeiSerial) {
                    const imeiSerialInput = row.find('.imei-serial');
                    if (!imeiSerialInput.val() || !imeiSerialInput.val().trim()) {
                        imeiSerialInput.addClass('is-invalid');
                        let imeiSerialWrapper = imeiSerialInput.closest('td');
                        if (imeiSerialWrapper.find('.invalid-feedback').length === 0) {
                            const fieldLabel = itemType === 'IMEI_Product' ? 'IMEI' : (itemType === 'Serial_Product' ? 'Serial' : 'Medicine');
                            imeiSerialWrapper.append(`<div class="invalid-feedback">${fieldLabel} details are required for this item type</div>`);
                        }
                        hasError = true;
                    } else {
                        imeiSerialInput.removeClass('is-invalid');
                        imeiSerialInput.closest('td').find('.invalid-feedback').remove();
                    }
                    
                    // Check returned IMEI/Serial/Medicine if status is taken_by_sup_pro_returned
                    if (status === 'taken_by_sup_pro_returned') {
                        const returnedInput = row.find('.returned-imei-serial');
                        if (!returnedInput.val() || !returnedInput.val().trim()) {
                            const fieldLabel = itemType === 'IMEI_Product' ? 'IMEI' : (itemType === 'Serial_Product' ? 'Serial' : 'Medicine');
                            returnedInput.addClass('is-invalid');
                            let returnedWrapper = returnedInput.closest('td');
                            if (returnedWrapper.find('.invalid-feedback').length === 0) {
                                returnedWrapper.append(`<div class="invalid-feedback">Returned ${fieldLabel} is required when status is "Taken By Supplier Product Returned"</div>`);
                            }
                            hasError = true;
                        } else {
                            // Check if returned IMEI/Serial has validation errors
                            if (returnedInput.hasClass('is-invalid')) {
                                hasError = true;
                            } else {
                                returnedInput.removeClass('is-invalid');
                                returnedInput.closest('td').find('.invalid-feedback').remove();
                            }
                        }
                    }
                }
            });
        }
        
        // Validate amount and payment method if status requires it
        if (status === 'taken_by_sup_money_returned') {
            const paymentMethodId = $('#payment_method_id').val();
            if (!paymentMethodId) {
                $('#paymentMethodSelect').addClass('is-invalid');
                let paymentMethodWrapper = $('#paymentMethodSelect').closest('.mb-5');
                if (paymentMethodWrapper.find('.invalid-feedback').length === 0) {
                    paymentMethodWrapper.append('<div class="invalid-feedback">Payment method is required</div>');
                }
                if ($('#paymentMethodSelect').next('.select2-container').length) {
                    $('#paymentMethodSelect').next('.select2-container').addClass('is-invalid');
                }
                hasError = true;
            } else {
                $('#paymentMethodSelect').removeClass('is-invalid');
                $('#paymentMethodSelect').closest('.mb-5').find('.invalid-feedback').remove();
                if ($('#paymentMethodSelect').next('.select2-container').length) {
                    $('#paymentMethodSelect').next('.select2-container').removeClass('is-invalid');
                }
            }
            
            const paymentAmount = parseFloat($('#total_return_amount').val()) || 0;
            if (isNaN(paymentAmount) || paymentAmount <= 0) {
                $('#total_return_amount').addClass('is-invalid');
                let paymentAmountWrapper = $('#total_return_amount').closest('.mb-5');
                if (paymentAmountWrapper.find('.invalid-feedback').length === 0) {
                    paymentAmountWrapper.append('<div class="invalid-feedback">Grand total is required and must be greater than 0</div>');
                }
                hasError = true;
            } else {
                $('#total_return_amount').removeClass('is-invalid');
                $('#total_return_amount').closest('.mb-5').find('.invalid-feedback').remove();
            }
        }
        
        return !hasError;
    }

    // Function to validate all returned IMEI/Serial fields
    function validateAllReturnedIMEISerial(callback) {
        const status = $('#status').val();
        if (status !== 'taken_by_sup_pro_returned') {
            if (callback) callback(true);
            return;
        }
        
        let allValid = true;
        let validationPromises = [];
        
        $('#purchaseReturnItems tr').each(function() {
            const row = $(this);
            const itemType = row.find('.item-type').val();
            const returnedInput = row.find('.returned-imei-serial');
            
            if ((itemType === 'IMEI_Product' || itemType === 'Serial_Product') && 
                returnedInput.length > 0 && returnedInput.val() && returnedInput.val().trim()) {
                const value = returnedInput.val().trim();
                const itemId = row.find('.item-id').val();
                const returnOutImei = row.find('.imei-serial').val();
                
                // First check: Returned IMEI should not be the same as return-out IMEI
                if (returnOutImei && returnOutImei.trim() === value) {
                    const fieldLabel = itemType === 'IMEI_Product' ? 'IMEI' : 'Serial';
                    const message = `Returned ${fieldLabel} cannot be the same as the return-out ${fieldLabel}. Please enter a different ${fieldLabel}.`;
                    
                    returnedInput.addClass('is-invalid');
                    let wrapper = returnedInput.closest('td');
                    wrapper.find('.invalid-feedback').remove();
                    wrapper.append(`<div class="invalid-feedback">${message}</div>`);
                    
                    allValid = false;
                    // Continue to next row
                    return;
                }
                
                if (itemId) {
                    // Create a promise for this validation
                    const promise = new Promise(function(resolve) {
                        const itemDetails = [{
                            type: itemType === 'IMEI_Product' ? 'imei' : 'serial',
                            value: value
                        }];
                        
                        let routePath = route('purchase.check-existing-imei-serial', [], false, Ziggy);
                        let checkUrl = base_url + routePath;
                        
                        $.ajax({
                            type: 'POST',
                            url: checkUrl,
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: {
                                item_id: itemId,
                                item_type: itemType,
                                item_details: itemDetails
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (!response.available) {
                                    // IMEI/Serial exists in system, mark as invalid
                                    const fieldLabel = itemType === 'IMEI_Product' ? 'IMEI' : 'Serial';
                                    const message = response.message || `${fieldLabel} number "${value}" already exists in the system. Please enter a unique ${fieldLabel} that doesn't exist in the system.`;
                                    
                                    returnedInput.addClass('is-invalid');
                                    let wrapper = returnedInput.closest('td');
                                    wrapper.find('.invalid-feedback').remove();
                                    wrapper.append(`<div class="invalid-feedback">${message}</div>`);
                                    
                                    allValid = false;
                                    // Show error notification
                                    showErrorNotification(message);
                                } else {
                                    returnedInput.removeClass('is-invalid');
                                    returnedInput.closest('td').find('.invalid-feedback').remove();
                                }
                                resolve();
                            },
                            error: function(xhr) {
                                console.error('Error validating returned IMEI/Serial:', xhr);
                                allValid = false;
                                resolve();
                            }
                        });
                    });
                    
                    validationPromises.push(promise);
                }
            }
        });
        
        // Wait for all validations to complete
        if (validationPromises.length > 0) {
            Promise.all(validationPromises).then(function() {
                if (callback) callback(allValid);
            });
        } else {
            if (callback) callback(true);
        }
    }

    // Flag to prevent double submission
    let isSubmitting = false;
    
    // Form submission validation with real-time backend validation
    $('#purchaseReturnForm').on('submit', function(e) {
        e.preventDefault();
        
        // Prevent double submission
        if (isSubmitting) {
            return false;
        }
        
        // Validate all required fields first (client-side)
        if (!validateRequiredFields()) {
            showErrorNotification('Please fill in all required fields');
            // Scroll to first error after a short delay
            setTimeout(function() {
                let firstError = $('.is-invalid').first();
                if (firstError.length > 0) {
                    let scrollTarget = firstError;
                    // If it's a Select2 field, scroll to its container
                    if (firstError.hasClass('select2-hidden-accessible')) {
                        scrollTarget = firstError.next('.select2-container');
                    }
                    $('html, body').animate({
                        scrollTop: scrollTarget.offset().top - 100
                    }, 500);
                }
            }, 100);
            return false;
        }
        
        // Check if there are any existing validation errors before proceeding
        if ($('.is-invalid').length > 0) {
            showErrorNotification('Please fix all validation errors before submitting');
            setTimeout(function() {
                let firstError = $('.is-invalid').first();
                if (firstError.length > 0) {
                    let scrollTarget = firstError;
                    if (firstError.hasClass('select2-hidden-accessible')) {
                        scrollTarget = firstError.next('.select2-container');
                    }
                    $('html, body').animate({
                        scrollTop: scrollTarget.offset().top - 100
                    }, 500);
                }
            }, 100);
            return false;
        }
        
        // Validate all returned IMEI/Serial fields
        validateAllReturnedIMEISerial(function(isValid) {
            if (!isValid) {
                showErrorNotification('Please fix the returned IMEI/Serial validation errors before submitting. Form submission has been blocked.');
                // Scroll to first error after a short delay
                setTimeout(function() {
                    let firstError = $('.is-invalid').first();
                    if (firstError.length > 0) {
                        let scrollTarget = firstError;
                        if (firstError.hasClass('select2-hidden-accessible')) {
                            scrollTarget = firstError.next('.select2-container');
                        }
                        $('html, body').animate({
                            scrollTop: scrollTarget.offset().top - 100
                        }, 500);
                    }
                }, 100);
                isSubmitting = false;
                return false;
            }
            
            // Double check for any validation errors before submitting
            if ($('.is-invalid').length > 0) {
                showErrorNotification('Please fix all validation errors before submitting. Form submission has been blocked.');
                setTimeout(function() {
                    let firstError = $('.is-invalid').first();
                    if (firstError.length > 0) {
                        let scrollTarget = firstError;
                        if (firstError.hasClass('select2-hidden-accessible')) {
                            scrollTarget = firstError.next('.select2-container');
                        }
                        $('html, body').animate({
                            scrollTop: scrollTarget.offset().top - 100
                        }, 500);
                    }
                }, 100);
                isSubmitting = false;
                return false;
            }
            
            // Set submitting flag
            isSubmitting = true;
            
            // Continue with form submission only if all validations pass
            submitPurchaseReturnForm();
        });
        
        return false;
    });
    
    // Function to submit the purchase return form
    function submitPurchaseReturnForm() {
        // Get form action URL to determine if it's create or update
        const formAction = $('#purchaseReturnForm').attr('action');
        const isUpdate = formAction.includes('/edit') || formAction.includes('/update') || (formAction.includes('purchase-return/') && !formAction.endsWith('/purchase-return'));
        let validationUrl;
        
        if (isUpdate) {
            // Check if formAction is absolute or relative
            if (formAction.startsWith('http://') || formAction.startsWith('https://')) {
                // It's an absolute URL, use as is
                validationUrl = formAction;
            } else {
                // It's a relative URL, prepend base_url
                validationUrl = base_url + (formAction.startsWith('/') ? formAction : '/' + formAction);
            }
        } else {
            validationUrl = route('purchase-return.store', [], false, Ziggy);
            // If route returns relative path, prepend base_url
            if (validationUrl && !validationUrl.startsWith('http://') && !validationUrl.startsWith('https://')) {
                validationUrl = base_url + (validationUrl.startsWith('/') ? validationUrl : '/' + validationUrl);
            }
        }
        
        // Update Medicine product IMEI/Serial fields to send full format (quantity - expiry_date) to backend
        $('#purchaseReturnItems tr').each(function() {
            const row = $(this);
            const itemType = row.find('.item-type').val();
            const imeiSerialInput = row.find('.imei-serial');
            
            // For Medicine_Product, reconstruct full format from quantity and expiry date
            if (itemType === 'Medicine_Product') {
                const quantity = row.find('.quantity').val();
                const expiryDate = imeiSerialInput.val();
                
                // If expiry date exists and doesn't already contain " - ", reconstruct the full format
                if (expiryDate && !expiryDate.includes(' - ')) {
                    const fullValue = `${quantity} - ${expiryDate}`;
                    imeiSerialInput.val(fullValue); // Update value before submission
                } else if (imeiSerialInput.attr('data-full-value')) {
                    // Use data-full-value if available (for newly added items)
                    const fullValue = imeiSerialInput.attr('data-full-value');
                    imeiSerialInput.val(fullValue);
                }
            }
        });
        
        // Prepare form data
        let formData = new FormData($('#purchaseReturnForm')[0]);
        if (isUpdate) {
            formData.append('_method', 'PUT');
        }
        
        // Validate via AJAX (server-side validation)
        $.ajax({
            type: "POST",
            url: validationUrl,
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'X-Requested-With': 'XMLHttpRequest'
            },
            contentType: false,
            processData: false,
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    // Validation passed, redirect to index page
                    let redirectUrl = route('purchase-return.index', [], false, Ziggy);
                    if (redirectUrl && !redirectUrl.startsWith('http://') && !redirectUrl.startsWith('https://')) {
                        redirectUrl = base_url + (redirectUrl.startsWith('/') ? redirectUrl : '/' + redirectUrl);
                    } else if (!redirectUrl) {
                        redirectUrl = base_url + '/purchase-return';
                    }
                    window.location.href = redirectUrl + '?status=success';
                } else {
                    isSubmitting = false; // Reset flag on error
                    showErrorNotification(response.message || 'Something went wrong');
                }
            },
            error: function (xhr) {
                isSubmitting = false; // Reset flag on error
                if (xhr.status === 422) {
                    // Handle validation errors
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        showValidationErrors('#purchaseReturnForm', xhr.responseJSON.errors);
                        showErrorNotification('Please check the form for errors. Form submission has been blocked.');
                        // Scroll to first error
                        setTimeout(function() {
                            let firstError = $('.is-invalid').first();
                            if (firstError.length > 0) {
                                let scrollTarget = firstError;
                                if (firstError.hasClass('select2-hidden-accessible')) {
                                    scrollTarget = firstError.next('.select2-container');
                                }
                                $('html, body').animate({
                                    scrollTop: scrollTarget.offset().top - 100
                                }, 500);
                            }
                        }, 100);
                    } else {
                        let msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Validation failed. Please check the form.';
                        showErrorNotification(msg);
                    }
                } else {
                    showErrorNotification('An unexpected error occurred');
                }
            }
        });
    }
    
    // Initial update of summary
    updateSummary();
    
    // Initialize status fields on page load
    toggleStatusFields();
});

