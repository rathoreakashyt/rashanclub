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
    // Fetch suppliers on page load
    // fetchSuppliers();
    
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
    
    // Payment methods data
    let paymentMethods = [];
    let paymentMethodIdCounter = 0;
    
    // Track IMEI/Serial/Medicine data for stock validation
    let purchaseIMEISerialData = []; // Array to track all IMEI/Serial numbers
    let purchaseMedicineData = []; // Array to track medicine data (quantity + expiry)
    let checkStockAvailability = true; // Variable to enable/disable stock checking
    
    // Function to check if an item already exists in table
    function itemExists(itemId) {
        let exists = false;
        $('#purchaseItems tr').each(function() {
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
        $('#purchaseItems tr').each(function() {
            if ($(this).find('.parent-id').val() === parentId) {
                exists = true;
                return false; // break the loop
            }
        });
        return exists;
    }
    
    // Function to calculate subtotal of all items
    function calculateSubtotal() {
        let subtotal = 0;
        $('#purchaseItems tr').each(function() {
            const total = parseFloat($(this).find('.total').val()) || 0;
            subtotal += total;
        });
        return subtotal;
    }
    
    // Function to calculate and update payment summary
    function updatePaymentSummary() {
        const subtotal = calculateSubtotal();
        const discountValue = $('#discount').val();
        let discountAmount = 0;
        
        // Calculate discount
        if (discountValue) {
            if (discountValue.includes('%')) {
                const discountPercent = parseFloat(discountValue.replace('%', '')) || 0;
                discountAmount = subtotal * (discountPercent / 100);
            } else {
                discountAmount = parseFloat(discountValue) || 0;
            }
        }
        
        // Calculate grand total
        const grandTotal = subtotal - discountAmount;
        
        // Calculate total paid amount
        let totalPaid = 0;
        paymentMethods.forEach(method => {
            totalPaid += parseFloat(method.amount) || 0;
        });
        
        // Calculate due amount
        const dueAmount = grandTotal - totalPaid;
        
        // Update display
        $('#grandTotal').val(grandTotal.toFixed(2));
        $('#paidAmount').val(totalPaid.toFixed(2));
        $('#dueAmount').val(dueAmount.toFixed(2));
    }
    
    // Function to add a payment method field
    function addPaymentMethodField(methodId = null, methodName = '', amount = '') {
        if (!methodId) {
            showErrorNotification('Please select a payment method');
            return;
        }
        
        // Check if this payment method already exists
        const existingMethod = paymentMethods.find(m => m.payment_id == methodId);
        if (existingMethod) {
            showErrorNotification('This payment method already exists');
            return;
        }
        
        const paymentMethodDiv = $(`
            <div class="payment-method-row mb-3" data-payment-id="${methodId}">
                <div class="input-group">
                    <span class="input-group-text">${methodName}</span>
                    <input type="text" class="form-control number-input payment-amount" placeholder="Amount" value="${amount}">
                    <button class="btn btn-outline-danger" type="button">
                        <i class="ti tabler-trash"></i>
                    </button>
                </div>
            </div>
        `);
        
        $('#paymentMethodsContainer').append(paymentMethodDiv);
        
        // Add to payment methods array with actual payment_id
        const paymentMethod = {
            payment_id: parseInt(methodId),
            name: methodName,
            amount: parseFloat(amount) || 0
        };
        paymentMethods.push(paymentMethod);
        
        // Add event listeners (cap payment amount so total paid cannot exceed grand total)
        paymentMethodDiv.find('.payment-amount').on('input', function() {
            const paymentId = $(this).closest('.payment-method-row').data('payment-id');
            let amount = parseFloat($(this).val()) || 0;
            const methodIndex = paymentMethods.findIndex(m => m.payment_id == paymentId);
            
            if (methodIndex !== -1) {
                const subtotal = calculateSubtotal();
                const discountValue = $('#discount').val();
                let discountAmount = 0;
                if (discountValue) {
                    if (discountValue.includes('%')) {
                        const discountPercent = parseFloat(discountValue.replace('%', '')) || 0;
                        discountAmount = subtotal * (discountPercent / 100);
                    } else {
                        discountAmount = parseFloat(discountValue) || 0;
                    }
                }
                const grandTotal = subtotal - discountAmount;
                const otherPaid = paymentMethods.reduce((sum, m, i) => i !== methodIndex ? sum + (parseFloat(m.amount) || 0) : sum, 0);
                const maxAllowed = Math.max(0, grandTotal - otherPaid);
                if (amount > maxAllowed) {
                    amount = maxAllowed;
                    $(this).val(amount.toFixed(2));
                }
                paymentMethods[methodIndex].amount = amount;
                updatePaymentSummary();
            }
        });
        
        paymentMethodDiv.find('button').on('click', function() {
            const paymentId = $(this).closest('.payment-method-row').data('payment-id');
            const methodIndex = paymentMethods.findIndex(m => m.payment_id == paymentId);
            
            if (methodIndex !== -1) {
                paymentMethods.splice(methodIndex, 1);
            }
            
            $(this).closest('.payment-method-row').remove();
            updatePaymentSummary();
        });
        
        updatePaymentSummary();
    }
    
    // Function to add a new row to the purchase items table
    function addNewRow(itemData = null) {
        const rowCount = $('#purchaseItems tr').length;
        const newRow = $('<tr></tr>');
        
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
        
        // Get purchase unit name from item data or select option
        let purchaseUnitName = itemData ? (itemData.purchaseUnitName || '') : '';
        if (!purchaseUnitName && itemData && itemData.id) {
            const selectedOption = $('#quickItemSelect option[value="' + itemData.id + '"]');
            purchaseUnitName = selectedOption.data('purchase-unit-name') || '';
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
                </div>
            </td>
            <td>
                <div class="input-group">
                    <input type="text" class="form-control number-input quantity" name="quantity[]" min="1" value="1">
                    <button type="button" class="btn btn-outline-secondary" type="button">
                        ${purchaseUnitName}
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
        $('#purchaseItems').append(newRow);
        
        // If item data is provided, populate the row
        if (itemData) {
            newRow.find('.unit-price').val(itemData.purchasePrice);
            calculateTotal(newRow);
            
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
            const expiryDateMaintain = selectedOption.data('expiry-date-maintain') || 'Yes';
            
            // Set current values in modal
            $('#modal_unit_price').val(unitPrice || purchasePrice);
            
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
                            purchaseIMEISerialData.push(value);
                            addPurchaseIMEISerialField(value);
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
                    // Get quantity from the row (for existing records, expiry_imei_serial might only contain expiry date)
                    const rowQuantity = row.find('.quantity').val() || 1;
                    
                    // Check if format is "quantity - expiry_date" or just "expiry_date"
                    if (existingValues.includes(' - ')) {
                        // Format: "quantity - expiry_date" (new entries or old format)
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
                        // Format: just "expiry_date" (existing records from database)
                        // Use quantity from the row
                        const expiryDate = existingValues.trim();
                        if (expiryDate) {
                            purchaseMedicineData.push({ quantity: rowQuantity, expiry_date: expiryDate });
                            addPurchaseMedicineField(rowQuantity, expiryDate);
                        }
                    }
                }
            }
            
            // Store item data for modal
            $('#modal_imei_serial').data('itemData', {
                id: itemId,
                name: itemName,
                purchasePrice: purchasePrice,
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
        });
        
        // Remove row button click event
        row.find('.remove-row').on('click', function() {
            row.remove();
            updateRowNumbers();
            updatePaymentSummary();
        });
    }
    
    // Function to calculate total for a row
    function calculateTotal(row) {
        const quantity = parseFloat(row.find('.quantity').val()) || 0;
        const unitPrice = parseFloat(row.find('.unit-price').val()) || 0;
        const total = quantity * unitPrice;
        row.find('.total').val(total.toFixed(2));
        
        // Update payment summary when row total changes
        updatePaymentSummary();
    }
    
    // Function to update row numbers
    function updateRowNumbers() {
        $('#purchaseItems tr').each(function(index) {
            $(this).find('td:first').text(index + 1);
        });
    }
    
    // Event listeners for payment summary
    $('#discount').on('input', function() {
        updatePaymentSummary();
    });
    
    $('#paymentMethodSelect').on('change', function() {
        const selectedMethod = $(this).val();
        const selectedMethodName = $(this).find('option:selected').text();
        
        if (!selectedMethod) {
            return;
        }
        
        // Create input group with payment name as label
        addPaymentMethodField(selectedMethod, selectedMethodName, '0');
        
        // Reset the select
        $(this).val('');
    });
    
    // Initialize payment summary on page load
    updatePaymentSummary();
    
    // Load existing payment methods on edit page (data passed from blade via window.EXISTING_PURCHASE_PAYMENTS)
    if (typeof window.EXISTING_PURCHASE_PAYMENTS !== 'undefined' && Array.isArray(window.EXISTING_PURCHASE_PAYMENTS) && window.EXISTING_PURCHASE_PAYMENTS.length > 0) {
        window.EXISTING_PURCHASE_PAYMENTS.forEach(function(payment) {
            const methodId = payment.payment_id || payment.payment_method_id;
            const name = payment.name || '';
            const amount = parseFloat(payment.amount) || 0;
            if (methodId && name) {
                addPaymentMethodField(methodId, name, amount);
            }
        });
        updatePaymentSummary();
    }
    
    // Quick item select change event
    $('#quickItemSelect').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const itemId = $(this).val();
        const itemType = selectedOption.data('item-type');
        
        if (!itemId) return;
        
        const itemData = {
            id: itemId,
            name: selectedOption.data('item-name'),
            purchasePrice: parseFloat(selectedOption.data('purchase-price')) || 0,
            purchaseUnitName: selectedOption.data('purchase-unit-name') || '',
            saleUnitName: selectedOption.data('sale-unit-name') || '',
            type: itemType
        };
        
        // Check for duplicate items for General_Product and Installment_Product
        if (itemType === 'General_Product' || itemType === 'Installment_Product') {
            if (itemExists(itemId)) {
                showErrorNotification('This item already exists in the purchase list.');
                $(this).val(''); // Reset the select
                return;
            }
            // Add item directly to the table
            addNewRow(itemData);
        } else if (itemType === 'Variation_Product') {
            // Check if this variation product's child items already exist
            if (variationExists(itemId)) {
                showErrorNotification('This variation product already exists in the purchase list.');
                $(this).val(''); // Reset the select
                return;
            }
            
            // Fetch child items via AJAX
            $.ajax({
                url: variation_child_items_url || (base_url + '/quotation/variation-child-items'),
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
                            
                            const childData = {
                                id: childItem.id,
                                name: displayName,
                                purchasePrice: parseFloat(childItem.purchase_price) || 0,
                                purchaseUnitName: childItem.purchase_unit?.unit_name || selectedOption.data('purchase-unit-name') || '',
                                saleUnitName: childItem.sale_unit?.unit_name || selectedOption.data('sale-unit-name') || '',
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
                // Open modal for IMEI/Serial/Medicine products
                currentRow = null;
                $('#modal_unit_price').val(itemData.purchasePrice);
                
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
    
    // Function to get all IMEI/Serial values already in the cart (purchase items table)
    function getIMEISerialValuesInCart() {
        const valuesInCart = [];
        $('#purchaseItems tr').each(function() {
            const imeiSerialVal = $(this).find('.imei-serial').val();
            if (imeiSerialVal && imeiSerialVal.trim()) {
                // Medicine format is "qty - expiry"; IMEI/Serial is a single value (no " - ")
                if (imeiSerialVal.includes(' - ')) {
                    return; // Skip medicine rows
                }
                const parts = imeiSerialVal.split(',').map(v => v.trim()).filter(Boolean);
                parts.forEach(function(part) {
                    valuesInCart.push(part);
                });
            }
        });
        return valuesInCart;
    }
    
    // Function to add IMEI/Serial field with stock availability check
    function addPurchaseIMEISerialField(value, callback) {
        // Check for duplicates in current list
        if (purchaseIMEISerialData.includes(value)) {
            const itemData = $('#modal_imei_serial').data('itemData');
            const fieldLabel = itemData && itemData.type === 'IMEI_Product' ? 'IMEI' : 'Serial';
            showErrorNotification(`${fieldLabel} number "${value}" already exists in the list. Please enter a unique ${fieldLabel}.`);
            if (callback) callback(false);
            return false;
        }
        
        // Check if this IMEI/Serial is already in the cart (existing rows)
        const valuesInCart = getIMEISerialValuesInCart();
        if (valuesInCart.includes(value)) {
            const itemData = $('#modal_imei_serial').data('itemData');
            const fieldLabel = itemData && itemData.type === 'IMEI_Product' ? 'IMEI' : 'Serial';
            showErrorNotification(`${fieldLabel} number "${value}" is already in the cart. Please enter a different ${fieldLabel}.`);
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
        
        // Check stock availability if enabled
        if (checkStockAvailability && (itemData.type === 'IMEI_Product' || itemData.type === 'Serial_Product')) {
            // Prepare data for stock check
            const itemDetails = [{
                type: itemData.type === 'IMEI_Product' ? 'imei' : 'serial',
                value: value
            }];
            
            // Make AJAX call to check stock availability
            let routePath = route('purchase.check-existing-imei-serial', [], false, Ziggy);
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
                    if (response.available) {
                        // IMEI/Serial is available, add it
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
                        // IMEI/Serial already exists in stock - don't reset input, just show message
                        const fieldLabel = itemData.type === 'IMEI_Product' ? 'IMEI' : 'Serial';
                        const message = response.message || `${fieldLabel} number "${value}" already exists in stock.`;
                        showErrorNotification(message);
                        // Keep the input value and focus on it
                        $('#purchase_imei_serial_input').focus();
                        if (callback) callback(false);
                    }
                },
                error: function(xhr) {
                    console.error('Error checking stock availability:', xhr);
                    // On error, allow adding (you can change this behavior if needed)
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
        const index = purchaseIMEISerialData.indexOf(value);
        if (index > -1) {
            purchaseIMEISerialData.splice(index, 1);
        }
        $(this).closest('.purchase-imei-serial-item').remove();
    });
    
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
        
        let itemDetails = [];
        let quantity = 0;
        let displayValue = '';
        
        if (itemData.type === 'IMEI_Product' || itemData.type === 'Serial_Product') {
            // Get IMEI/Serial values
            const imeiSerialValues = purchaseIMEISerialData;
            
            if (imeiSerialValues.length === 0) {
                const fieldLabel = itemData.type === 'IMEI_Product' ? 'IMEI' : 'Serial';
                showErrorNotification(`Please enter at least one ${fieldLabel} number`);
                return;
            }
            
            itemDetails = imeiSerialValues.map(value => ({
                type: itemData.type === 'IMEI_Product' ? 'imei' : 'serial',
                value: value
            }));
            
            // Check stock availability if enabled
            if (checkStockAvailability) {
                // Check each IMEI/Serial before adding
                const validItems = [];
                const invalidItems = [];
                
                // Check each IMEI/Serial synchronously (for now, can be made async later)
                imeiSerialValues.forEach(value => {
                    // For now, we'll check on backend during save
                    // This is a placeholder for future async validation
                    validItems.push(value);
                });
                
                if (invalidItems.length > 0) {
                    const fieldLabel = itemData.type === 'IMEI_Product' ? 'IMEI' : 'Serial';
                    showErrorNotification(`The following ${fieldLabel} numbers already exist: ${invalidItems.join(', ')}`);
                    return;
                }
            }
            
            // Create separate rows for each IMEI/Serial
            if (currentRow) {
                // If editing existing row, remove it first and create new rows
                currentRow.remove();
                updateRowNumbers();
            }
            
            // Create one row per IMEI/Serial
            imeiSerialValues.forEach((value, index) => {
                const rowCount = $('#purchaseItems tr').length;
                const newRow = $('<tr></tr>');
                
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
                            <input type="hidden" name="item_details[]" value='${JSON.stringify([{type: itemData.type === 'IMEI_Product' ? 'imei' : 'serial', value: value}])}'>
                        </div>
                    </td>
                    <td>
                        <div class="input-group">
                            <input type="text" class="form-control number-input quantity" name="quantity[]" min="1" value="1" readonly>
                            <button type="button" class="btn btn-outline-secondary" type="button">
                                ${itemData.purchaseUnitName || ''}
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
                $('#purchaseItems').append(newRow);
                initializeRowEvents(newRow);
            });
            
            // Update payment summary and close modal
            updatePaymentSummary();
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
                const rowCount = $('#purchaseItems tr').length;
                const newRow = $('<tr></tr>');
                const medicineQuantity = parseFloat(medicineItem.quantity) || 1;
                const medicineTotal = medicineQuantity * parseFloat(unitPrice);
                
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
                            <input type="hidden" name="item_details[]" value='${JSON.stringify([{type: 'medicine', quantity: medicineItem.quantity, expiry_date: medicineItem.expiry_date}])}'>
                        </div>
                    </td>
                    <td>
                        <div class="input-group">
                            <input type="text" class="form-control number-input quantity" name="quantity[]" min="1" value="${medicineQuantity}" readonly>
                            <button type="button" class="btn btn-outline-secondary" type="button">
                                ${itemData.purchaseUnitName || ''}
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
                $('#purchaseItems').append(newRow);
                initializeRowEvents(newRow);
            });
        }
        
        // Reset modal data
        purchaseIMEISerialData = [];
        purchaseMedicineData = [];
        
        // Update payment summary
        updatePaymentSummary();
        
        // Close modal
        $('#modal_imei_serial').modal('hide');
    });
    
    // Function to add hidden fields for payment methods
    function addPaymentMethodHiddenFields() {
        // Clear existing hidden fields
        $('#paymentMethodsHiddenFields').empty();
        
        // Add hidden fields for each payment method
        paymentMethods.forEach((method, index) => {
            if (method.payment_id && method.amount > 0) {
                $('#paymentMethodsHiddenFields').append(`
                    <input type="hidden" name="payments[${index}][payment_id]" value="${method.payment_id}">
                    <input type="hidden" name="payments[${index}][amount]" value="${method.amount}">
                    <input type="hidden" name="payments[${index}][note]" value="${method.note || ''}">
                `);
            }
        });
    }
    
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
        
        // Validate Items - Check if there are any items in the table
        if ($('#purchaseItems tr').length === 0) {
            showErrorNotification('Please add at least one item to the purchase');
            hasError = true;
        } else {
            // Validate each purchase item
            $('#purchaseItems tr').each(function(index) {
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
                }
            });
        }
        
        // Validate payment methods (optional but if added, must be valid)
        if (paymentMethods.length > 0) {
            paymentMethods.forEach((method, index) => {
                if (!method.payment_id) {
                    hasError = true;
                }
                
                const amount = parseFloat(method.amount) || 0;
                if (isNaN(amount) || amount <= 0) {
                    hasError = true;
                }
            });
        }
        
        // Validate paid amount doesn't exceed grand total
        const grandTotal = parseFloat($('#grandTotal').val()) || 0;
        const totalPaid = parseFloat($('#paidAmount').val()) || 0;
        
        if (totalPaid > grandTotal) {
            $('#paidAmount').addClass('is-invalid');
            let paidAmountWrapper = $('#paidAmount').closest('.mb-5');
            if (paidAmountWrapper.find('.invalid-feedback').length === 0) {
                paidAmountWrapper.append('<div class="invalid-feedback">Paid amount cannot exceed grand total</div>');
            }
            hasError = true;
        } else {
            $('#paidAmount').removeClass('is-invalid');
            $('#paidAmount').closest('.mb-5').find('.invalid-feedback').remove();
        }
        
        return !hasError;
    }

    // Form submission validation with real-time backend validation
    $('#purchaseForm').on('submit', function(e) {
        e.preventDefault();
        
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
        
        // Add hidden fields for payment methods before submission
        addPaymentMethodHiddenFields();
        
        // Update Medicine product IMEI/Serial fields to send full format (quantity - expiry_date) to backend
        $('#purchaseItems tr').each(function() {
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
        
        // Get form action URL to determine if it's create or update
        const formAction = $('#purchaseForm').attr('action');
        const isUpdate = formAction.includes('/edit') || formAction.includes('/update') || (formAction.includes('purchase/') && !formAction.endsWith('/purchase'));
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
            validationUrl = route('purchase.store', [], false, Ziggy);
            // If route returns relative path, prepend base_url
            if (validationUrl && !validationUrl.startsWith('http://') && !validationUrl.startsWith('https://')) {
                validationUrl = base_url + (validationUrl.startsWith('/') ? validationUrl : '/' + validationUrl);
            }
        }
        
        // Prepare form data
        let formData = new FormData($('#purchaseForm')[0]);
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
                    // window.location.href = base_url + route('purchase.index', [], false, Ziggy) + '?status=success';

                    window.location.href = base_url + '/purchase?status=success';
                } else {
                    showErrorNotification(response.message || 'Something went wrong');
                }
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    // Handle validation errors
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        showValidationErrors('#purchaseForm', xhr.responseJSON.errors);
                        showErrorNotification('Please check the form for errors');
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
    });
    
    // Initial update of payment summary
    updatePaymentSummary();
    
    /**
     * Function to check stock availability for IMEI/Serial/Medicine
     * This function will be called when checkStockAvailability is true
     * @param {Array} itemDetails - Array of item details (IMEI/Serial numbers or Medicine data)
     * @param {String} itemType - Type of item (IMEI_Product, Serial_Product, Medicine_Product)
     * @returns {Promise<Boolean>} - Returns true if all items are available, false otherwise
     */
    function checkStockAvailabilityForPurchase(itemDetails, itemType) {
        if (!checkStockAvailability) {
            return Promise.resolve(true); // Skip check if disabled
        }
        
        return new Promise((resolve) => {
            $.ajax({
                url: route('purchase.check-existing-imei-serial', [], false, Ziggy),
                type: 'POST',
                data: {
                    item_details: itemDetails,
                    item_type: itemType
                },
                success: function(response) {
                    if (response.available) {
                        resolve(true);
                    } else {
                        showErrorNotification(response.message);
                        resolve(false);
                    }
                },
                error: function() {
                    resolve(true); // Allow on error (or handle as needed)
                }
            });
        });
    }
});

