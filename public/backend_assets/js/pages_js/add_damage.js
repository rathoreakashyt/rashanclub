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
    let item_current_stock_url = $('#item_current_stock_url').val() || (base_url + '/api/damage/item-current-stock');
    let damage_check_imei_in_outlet_url = $('#damage_check_imei_in_outlet_url').val() || (base_url + '/api/damage/check-imei-in-outlet');
    let outlet_id = $('#outlet_id').val() || '';
    let checkStockAvailability = true; // Variable to enable/disable stock checking

    // Store the current row being edited
    let currentRow = null;
    
    // Track IMEI/Serial/Medicine data
    let damageIMEISerialData = []; // Array to track all IMEI/Serial numbers
    let damageMedicineData = []; // Array to track medicine data (quantity + expiry)

    // Function to check if an item already exists in table
    function itemExists(itemId) {
        let exists = false;
        $('#damageItems tr').each(function() {
            if ($(this).find('.item-id').val() == itemId) {
                exists = true;
                return false; // break the loop
            }
        });
        return exists;
    }
    
    // Function to check if a variation product's child items already exist in the table
    function variationExists(parentId) {
        let exists = false;
        $('#damageItems tr').each(function() {
            if ($(this).find('.parent-id').val() == parentId) {
                exists = true;
                return false; // break the loop
            }
        });
        return exists;
    }

    /**
     * Get all IMEI/Serial values already in the damage cart (table rows).
     * When excludeRow is provided (e.g. currentRow when editing), that row's values are excluded
     * so the user can keep the same IMEIs when editing that row.
     */
    function getIMEISerialInCart(excludeRow) {
        const inCart = [];
        $('#damageItems tr').each(function() {
            if (excludeRow && this === excludeRow[0]) return;
            const val = $(this).find('.imei-serial').val();
            if (val && val.toString().trim()) {
                val.toString().split(',').forEach(function(part) {
                    const trimmed = part.trim();
                    if (trimmed && inCart.indexOf(trimmed) === -1) inCart.push(trimmed);
                });
            }
        });
        return inCart;
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
            data: { item_id: itemId, outlet_id: outlet_id },
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
     * Get sum of damage quantity for all rows with the given item_id (excluding optional excludeRow).
     */
    function getTotalDamageQtyForItem(itemId, excludeRow) {
        let total = 0;
        $('#damageItems tr').each(function() {
            if ($(this).find('.item-id').val() == itemId && (!excludeRow || this !== excludeRow[0])) {
                total += parseFloat($(this).find('.damage-qty').val()) || 0;
            }
        });
        return total;
    }

    /**
     * Validate damage quantity does not exceed available stock. Sets invalid class and message on damage-qty input if invalid.
     * For same item in multiple rows, available = current_stock - (sum of other rows' damage_qty).
     */
    function validateDamageQuantityAgainstStock(row, callback) {
        const itemId = row.find('.item-id').val();
        const quantityInput = row.find('.damage-qty');
        const qty = parseFloat(quantityInput.val()) || 0;

        if (!itemId || !checkStockAvailability) {
            quantityInput.removeClass('is-invalid');
            row.find('.damage-qty').closest('td').find('.invalid-feedback').remove();
            if (callback) callback(true);
            return;
        }

        getItemCurrentStock(itemId, function(currentStock) {
            row.data('current-stock', currentStock);
            const otherRowsQty = getTotalDamageQtyForItem(itemId, row);
            const availableForThisRow = currentStock - otherRowsQty;
            quantityInput.attr('data-max-stock', availableForThisRow);

            if (qty > availableForThisRow) {
                quantityInput.addClass('is-invalid');
                let td = quantityInput.closest('td');
                td.find('.invalid-feedback').remove();
                td.append('<div class="invalid-feedback">Damage quantity cannot exceed current stock (available: ' + availableForThisRow + ')</div>');
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
        $('#damageItems tr').each(function() {
            const total = parseFloat($(this).find('.total').val()) || 0;
            subtotal += total;
        });
        return subtotal;
    }
    
    // Function to update total item count
    function updateTotalItemCount() {
        const count = $('#damageItems tr').length;
        $('#totalItemCount').text(count);
    }
    
    // Function to calculate and update summary
    function updateSummary() {
        const totalLoss = calculateSubtotal();
        
        // Update display
        $('#totalLoss').val(totalLoss.toFixed(2));
        updateTotalItemCount();
    }
    
    // Function to add a new row to the damage items table
    function addNewRow(itemData = null) {
        const rowCount = $('#damageItems tr').length;
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
        
        // Get sale unit name from item data or select option
        let saleUnitName = itemData ? (itemData.saleUnitName || '') : '';
        if (!saleUnitName && itemData && itemData.id) {
            const selectedOption = $('#quickItemSelect option[value="' + itemData.id + '"]');
            saleUnitName = selectedOption.data('sale-unit-name') || '';
        }
        
        // Create row HTML
        let rowHtml = `
            <td style="vertical-align: top;">${rowCount + 1}</td>
            <td style="vertical-align: top;">
                <input type="hidden" class="item-id" name="items[]" value="${itemData ? itemData.id : ''}">
                <input type="hidden" class="parent-id" name="parent_ids[]" value="${itemData && itemData.parentId ? itemData.parentId : ''}">
                <input type="hidden" class="item-type" name="item_types[]" value="${itemData ? itemData.type : ''}">
                <span class="item-name">${itemData ? itemData.name : ''}</span>
            </td>
            <td class="imei-serial-cell" style="vertical-align: top;">
                <div class="imei-serial-container">
                    <input type="text" class="form-control imei-serial" name="expiry_imei_serial[]" placeholder="IMEI/Serial" readonly style="${showImeiSerial ? '' : 'display:none;'}">
                </div>
            </td>
            <td style="vertical-align: top;">
                <div class="input-group">
                    <input type="text" class="form-control number-input damage-qty" name="damage_quantity[]" value="1">
                    <button type="button" class="btn btn-outline-secondary" type="button">
                        ${saleUnitName}
                    </button>
                </div>
            </td>
            <td style="vertical-align: top;">
                <input type="text" class="form-control number-input damage-amount" name="last_purchase_price[]">
            </td>
            <td style="vertical-align: top;">
                <input type="text" class="form-control number-input total" name="total_amount[]" readonly>
                <input type="hidden" class="loss-amount" name="loss_amount[]" value="0">
            </td>
            <td style="vertical-align: top;">
                <button type="button" class="btn text-danger remove-row">
                    <i class="icon-base ti tabler-trash"></i>
                </button>
            </td>
        `;
        
        newRow.html(rowHtml);
        $('#damageItems').append(newRow);
        
        // If item data is provided, populate the row
        if (itemData) {
            newRow.find('.damage-amount').val(itemData.purchasePrice || 0);
            calculateTotal(newRow);

            // Fetch current stock and validate damage quantity (for all item types)
            if (itemData.id && checkStockAvailability) {
                getItemCurrentStock(itemData.id, function(currentStock) {
                    newRow.data('current-stock', currentStock);
                    newRow.find('.damage-qty').attr('data-max-stock', currentStock);
                    validateDamageQuantityAgainstStock(newRow);
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

            // IMEI/Serial product: damage quantity is always 1 per row, so make it readonly
            if (itemData.type === 'IMEI_Product' || itemData.type === 'Serial_Product') {
                newRow.find('.damage-qty').prop('readonly', true);
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
            const damageAmount = row.find('.damage-amount').val();
            const itemId = row.find('.item-id').val();
            
            // Get item type from row's hidden input first, then fallback to select options
            let itemType = row.find('.item-type').val();
            let itemName = row.find('.item-name').text().trim();
            let purchasePrice = parseFloat(damageAmount) || 0;
            
            // Try to get from select options if available
            const selectedOption = $('#quickItemSelect option[value="' + itemId + '"]');
            if (selectedOption.length > 0) {
                itemType = selectedOption.data('item-type') || itemType;
                itemName = selectedOption.data('item-name') || itemName;
                purchasePrice = parseFloat(selectedOption.data('purchase-price')) || purchasePrice;
            }
            
            // If item type is still not found, show error
            if (!itemType) {
                showErrorNotification('Item type not found. Please refresh the page and try again.');
                return;
            }
            
            const expiryDateMaintain = selectedOption.data('expiry-date-maintain') || 'Yes';
            
            // Set current values in modal
            $('#modal_damage_amount').val(damageAmount || purchasePrice);
            
            // Reset modal sections
            $('#imei_serial_section').hide();
            $('#medicine_section').hide();
            damageIMEISerialData = [];
            damageMedicineData = [];
            
            // Show appropriate section based on item type
            if (itemType === 'IMEI_Product' || itemType === 'Serial_Product') {
                const fieldLabel = itemType === 'IMEI_Product' ? 'IMEI' : 'Serial';
                $('#modal_imei_serial_title').text(`Edit ${fieldLabel} Numbers`);
                $('#imei_serial_label').text(`${fieldLabel} Numbers`);
                $('#damage_imei_serial_input').attr('placeholder', `Enter ${fieldLabel} Number`);
                $('#imei_serial_section').show();
                
                // If there are existing values, populate them
                const existingValues = row.find('.imei-serial').val();
                if (existingValues) {
                    const values = existingValues.split(',').map(v => v.trim());
                    values.forEach(value => {
                        if (value) {
                            damageIMEISerialData.push(value);
                            addDamageIMEISerialField(value);
                        }
                    });
                }
            } else if (itemType === 'Medicine_Product' && expiryDateMaintain === 'Yes') {
                $('#modal_imei_serial_title').text('Edit Medicine Details');
                $('#medicine_section').show();
                $('#damage_medicine_quantity_input').val('');
                $('#damage_medicine_expiry_input').val('');
                $('#damage_medicine_mmyy_input').val('');
                $('#damage_medicine_mmyy_input').val('');
                
                // If there are existing values, populate them
                const existingValues = row.find('.imei-serial').val();
                if (existingValues) {
                    const rowQuantity = row.find('.damage-qty').val() || 1;
                    
                    if (existingValues.includes(' - ')) {
                        const entries = existingValues.split(',').map(v => v.trim());
                        entries.forEach(entry => {
                            const parts = entry.split(' - ').map(p => p.trim());
                            if (parts.length === 2) {
                                const quantity = parts[0];
                                const expiryDate = parts[1];
                                damageMedicineData.push({ quantity: quantity, expiry_date: expiryDate });
                                addDamageMedicineField(quantity, expiryDate);
                            }
                        });
                    } else {
                        const expiryDate = existingValues.trim();
                        if (expiryDate) {
                            damageMedicineData.push({ quantity: rowQuantity, expiry_date: expiryDate });
                            addDamageMedicineField(rowQuantity, expiryDate);
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
                    const expiryInput = $('#damage_medicine_expiry_input');
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
        
        // Damage qty and damage amount change events
        row.find('.damage-qty, .damage-amount').on('input', function() {
            calculateTotal(row);
            if ($(this).hasClass('damage-qty')) {
                validateDamageQuantityAgainstStock(row);
                // Re-validate other rows with same item_id so "available" updates
                const itemId = row.find('.item-id').val();
                $('#damageItems tr').each(function() {
                    if ($(this).find('.item-id').val() === itemId && this !== row[0]) {
                        validateDamageQuantityAgainstStock($(this));
                    }
                });
            }
        });
        row.find('.damage-qty').on('blur', function() {
            validateDamageQuantityAgainstStock(row);
        });

        // Remove row button click event
        row.find('.remove-row').on('click', function() {
            const itemId = row.find('.item-id').val();
            row.remove();
            updateRowNumbers();
            updateSummary();
            // Re-validate rows with same item_id as removed row (available stock changes)
            if (itemId) {
                $('#damageItems tr').each(function() {
                    if ($(this).find('.item-id').val() === itemId) {
                        validateDamageQuantityAgainstStock($(this));
                    }
                });
            }
        });
    }

    // ===============================
    // FIX FOR EDIT PAGE CALCULATION
    // ===============================
    $('#damageItems tr').each(function () {
        const row = $(this);

        // Attach events to existing rows
        initializeRowEvents(row);

        // Recalculate row total (important if qty/amount changed)
        calculateTotal(row);

        // IMEI/Serial product: damage quantity is readonly (always 1 per row)
        const itemType = row.find('.item-type').val();
        if (itemType === 'IMEI_Product' || itemType === 'Serial_Product') {
            row.find('.damage-qty').prop('readonly', true);
        }
    });

    // Recalculate summary after all rows are initialized
    updateRowNumbers();
    updateSummary();
    
    // Function to calculate total for a row
    function calculateTotal(row) {
        const damageQty = parseFloat(row.find('.damage-qty').val()) || 0;
        const damageAmount = parseFloat(row.find('.damage-amount').val()) || 0;
        const total = damageQty * damageAmount;
        row.find('.total').val(total.toFixed(2));
        
        // Update loss_amount (for now, loss_amount equals total_amount)
        const lossAmount = row.find('.loss-amount');
        if (lossAmount.length) {
            lossAmount.val(total.toFixed(2));
        } else {
            // Create hidden input for loss_amount if it doesn't exist
            row.find('.total').after('<input type="hidden" class="loss-amount" name="loss_amount[]" value="' + total.toFixed(2) + '">');
        }
        
        // Update summary when row total changes
        updateSummary();
    }
    
    // Function to update row numbers
    function updateRowNumbers() {
        $('#damageItems tr').each(function(index) {
            $(this).find('td:first').text(index + 1);
        });
    }
    
    // Initialize summary on page load
    updateSummary();
    
    // Employee ID validation is handled by form validation
    
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
            saleUnitName: selectedOption.data('sale-unit-name'),
            purchaseUnitName: selectedOption.data('purchase-unit-name'),
            type: itemType
        };
        
        // Check for duplicate items for General_Product and Installment_Product
        if (itemType === 'General_Product' || itemType === 'Installment_Product') {
            if (itemExists(itemId)) {
                showErrorNotification('This item already exists in the damage list.');
                $(this).val(''); // Reset the select
                return;
            }
            // Add item directly to the table
            addNewRow(itemData);
        } else if (itemType === 'Variation_Product') {
            // Check if this variation product's child items already exist
            if (variationExists(itemId)) {
                showErrorNotification('This variation product already exists in the damage list.');
                $(this).val(''); // Reset the select
                return;
            }
            
            // Fetch child items via AJAX
            $.ajax({
                url: variation_child_items_url || (base_url + '/api/damage/variation-child-items'),
                type: 'GET',
                data: { parent_id: itemId },
                success: function(response) {
                    if (response.length > 0) {
                        const parentName = selectedOption.data('item-parent-name');
                        
                        response.forEach(function(childItem) {
                            let displayName = childItem.name + ' (' + childItem.code + ')';
                            if (parentName) {
                                displayName = parentName + ' - ' + displayName;
                            }
                            
                            const childData = {
                                id: childItem.id,
                                name: displayName,
                                purchasePrice: parseFloat(childItem.purchase_price) || 0,
                                saleUnitName: selectedOption.data('sale-unit-name'),
                                type: '0',
                                parentId: itemId
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
                $('#modal_damage_amount').val(itemData.purchasePrice);
                
                // Reset modal sections
                $('#imei_serial_section').hide();
                $('#medicine_section').hide();
                damageIMEISerialData = [];
                damageMedicineData = [];
                
                // Show appropriate section based on item type
                if (itemType === 'IMEI_Product' || itemType === 'Serial_Product') {
                    const fieldLabel = itemType === 'IMEI_Product' ? 'IMEI' : 'Serial';
                    $('#modal_imei_serial_title').text(`Add ${fieldLabel} Numbers`);
                    $('#imei_serial_label').text(`${fieldLabel} Numbers`);
                    $('#damage_imei_serial_input').attr('placeholder', `Enter ${fieldLabel} Number`);
                    $('#imei_serial_section').show();
                    $('#damage_imei_serial_list').empty();
                    $('#damage_imei_serial_input').val('');
                } else if (itemType === 'Medicine_Product' && expiryDateMaintain === 'Yes') {
                    $('#modal_imei_serial_title').text('Add Medicine Details');
                    $('#medicine_section').show();
                    $('#damage_medicine_list').empty();
                    $('#damage_medicine_quantity_input').val('');
                    $('#damage_medicine_expiry_input').val('');
                    $('#damage_medicine_mmyy_input').val('');
                }
                
                // Store item data for later use when saving from modal
                $('#modal_imei_serial').data('itemData', itemData);
                
                // Initialize date picker for medicine expiry if needed
                if (itemType === 'Medicine_Product' && expiryDateMaintain === 'Yes') {
                    setTimeout(function() {
                        const expiryInput = $('#damage_medicine_expiry_input');
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
            }
            // Show modal
            $('#modal_imei_serial').modal('show');
        }
        
        // Reset the select
        $(this).val('');
    });
    
    // Add IMEI/Serial field on Enter key
    $(document).on('keypress', '.damage-imei-serial-input', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            const value = $(this).val().trim();
            if (value) {
                const input = $(this);
                input.prop('disabled', true);
                addDamageIMEISerialField(value, function(success) {
                    input.prop('disabled', false);
                    if (success) input.val('').focus();
                    else input.focus();
                });
            }
        }
    });

    // Add IMEI/Serial field on Plus button click
    $(document).on('click', '.add-damage-imei-serial-field', function() {
        const input = $('#damage_imei_serial_input');
        const value = input.val().trim();
        const button = $(this);
        if (value) {
            input.prop('disabled', true);
            button.prop('disabled', true);
            addDamageIMEISerialField(value, function(success) {
                input.prop('disabled', false);
                button.prop('disabled', false);
                if (success) input.val('').focus();
                else input.focus();
            });
        }
    });
    
    // Function to add IMEI/Serial field with stock availability check (must exist in stock for damage)
    function addDamageIMEISerialField(value, callback) {
        if (typeof callback !== 'function') callback = function() {};

        if (damageIMEISerialData.includes(value)) {
            const itemData = $('#modal_imei_serial').data('itemData');
            const fieldLabel = itemData && itemData.type === 'IMEI_Product' ? 'IMEI' : 'Serial';
            showErrorNotification(`${fieldLabel} number "${value}" already exists in the list.`);
            callback(false);
            return;
        }

        // Same IMEI/Serial must not already be in the cart (other rows). When editing currentRow, exclude that row.
        const inCart = getIMEISerialInCart(currentRow || null);
        if (inCart.indexOf(value) !== -1) {
            const itemData = $('#modal_imei_serial').data('itemData');
            const fieldLabel = itemData && itemData.type === 'IMEI_Product' ? 'IMEI' : 'Serial';
            showErrorNotification(`${fieldLabel} number "${value}" is already in the damage cart. You cannot add the same ${fieldLabel} again.`);
            callback(false);
            return;
        }

        const itemData = $('#modal_imei_serial').data('itemData');
        if (!itemData || !itemData.id) {
            showErrorNotification('Item data not found');
            callback(false);
            return;
        }

        // For damage: IMEI/Serial must EXIST in current outlet's stock (outlet-wise validation)
        if (checkStockAvailability && (itemData.type === 'IMEI_Product' || itemData.type === 'Serial_Product')) {
            const itemDetails = [{
                type: itemData.type === 'IMEI_Product' ? 'imei' : 'serial',
                value: value
            }];
            let checkStockUrl = damage_check_imei_in_outlet_url;
            $.ajax({
                type: 'POST',
                url: checkStockUrl,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    item_id: itemData.id,
                    item_type: itemData.type,
                    item_details: itemDetails,
                    outlet_id: outlet_id
                },
                dataType: 'json',
                success: function(response) {
                    // For damage: allow when IMEI/Serial EXISTS in this outlet's stock (response.available === false)
                    if (!response.available) {
                        damageIMEISerialData.push(value);
                        const fieldLabel = itemData.type === 'IMEI_Product' ? 'IMEI' : 'Serial';
                        const listContainer = $('#damage_imei_serial_list');
                        const fieldHtml = `
                            <div class="input-group mb-2 damage-imei-serial-item" data-value="${value}">
                                <input type="text" class="form-control damage-imei-serial-value" value="${value}" readonly />
                                <button type="button" class="btn btn-outline-danger remove-damage-imei-serial-field" data-value="${value}">
                                    <i class="ti tabler-trash"></i>
                                </button>
                            </div>
                        `;
                        listContainer.append(fieldHtml);
                        $('#damage_imei_serial_input').focus();
                        callback(true);
                    } else {
                        const fieldLabel = itemData.type === 'IMEI_Product' ? 'IMEI' : 'Serial';
                        const message = response.message || `${fieldLabel} number "${value}" does not exist in this outlet's stock. Please enter a ${fieldLabel} that is in stock at the current outlet.`;
                        showErrorNotification(message);
                        $('#damage_imei_serial_input').focus();
                        callback(false);
                    }
                },
                error: function(xhr) {
                    showErrorNotification('Error checking stock availability. Please try again.');
                    callback(false);
                }
            });
        } else {
            damageIMEISerialData.push(value);
            const listContainer = $('#damage_imei_serial_list');
            const fieldHtml = `
                <div class="input-group mb-2 damage-imei-serial-item" data-value="${value}">
                    <input type="text" class="form-control damage-imei-serial-value" value="${value}" readonly />
                    <button type="button" class="btn btn-outline-danger remove-damage-imei-serial-field" data-value="${value}">
                        <i class="ti tabler-trash"></i>
                    </button>
                </div>
            `;
            listContainer.append(fieldHtml);
            $('#damage_imei_serial_input').focus();
            callback(true);
        }
    }
    
    // Remove IMEI/Serial field
    $(document).on('click', '.remove-damage-imei-serial-field', function() {
        const value = $(this).data('value');
        const index = damageIMEISerialData.indexOf(value);
        if (index > -1) {
            damageIMEISerialData.splice(index, 1);
        }
        $(this).closest('.damage-imei-serial-item').remove();
    });
    
    // MM/YY input handler - Auto-formatting like bank card expiry
    $(document).on('input', '.damage-medicine-mmyy-input', function() {
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
                let expiryInput = $('#damage_medicine_expiry_input');
                
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
    $(document).on('keypress', '.damage-medicine-mmyy-input', function(e) {
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
    $(document).on('keypress', '.damage-medicine-quantity-input, .damage-medicine-expiry-input', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            const quantityInput = $('#damage_medicine_quantity_input');
            const expiryInput = $('#damage_medicine_expiry_input');
            const mmyyInput = $('#damage_medicine_mmyy_input');
            const quantity = quantityInput.val().trim();
            const expiryDate = expiryInput.val().trim();
            
            if (quantity && expiryDate) {
                addDamageMedicineField(quantity, expiryDate);
                quantityInput.val('');
                expiryInput.val('');
                mmyyInput.val('');
                quantityInput.focus();
            }
        }
    });
    
    // Add Medicine field on Plus button click
    $(document).on('click', '.add-damage-medicine-field', function() {
        const quantityInput = $('#damage_medicine_quantity_input');
        const expiryInput = $('#damage_medicine_expiry_input');
        const mmyyInput = $('#damage_medicine_mmyy_input');
        const quantity = quantityInput.val().trim();
        const expiryDate = expiryInput.val().trim();
        
        if (quantity && expiryDate) {
            addDamageMedicineField(quantity, expiryDate);
            quantityInput.val('');
            expiryInput.val('');
            mmyyInput.val('');
            quantityInput.focus();
        } else {
            showErrorNotification('Please enter both quantity and expiry date.');
        }
    });
    
    // Function to add Medicine field
    function addDamageMedicineField(quantity, expiryDate) {
        const key = `${quantity}-${expiryDate}`;
        const exists = damageMedicineData.some(item => item.quantity === quantity && item.expiry_date === expiryDate);
        
        if (exists) {
            showErrorNotification('This medicine entry already exists in the list.');
            return;
        }
        
        damageMedicineData.push({ quantity: quantity, expiry_date: expiryDate });
        const listContainer = $('#damage_medicine_list');
        
        const fieldHtml = `
            <div class="input-group mb-2 damage-medicine-item" data-quantity="${quantity}" data-expiry="${expiryDate}">
                <input type="text" 
                    class="form-control damage-medicine-value" 
                    value="${quantity} - ${expiryDate}"
                    readonly />
                <button type="button" class="btn btn-outline-danger remove-damage-medicine-field" data-quantity="${quantity}" data-expiry="${expiryDate}">
                    <i class="ti tabler-trash"></i>
                </button>
            </div>
        `;
        
        listContainer.append(fieldHtml);
        $('#damage_medicine_quantity_input').focus();
    }
    
    // Remove Medicine field
    $(document).on('click', '.remove-damage-medicine-field', function() {
        const quantity = $(this).data('quantity');
        const expiryDate = $(this).data('expiry');
        const index = damageMedicineData.findIndex(item => item.quantity === quantity && item.expiry_date === expiryDate);
        if (index > -1) {
            damageMedicineData.splice(index, 1);
        }
        $(this).closest('.damage-medicine-item').remove();
    });
    
    // Save IMEI/Serial/Medicine modal
    $('#save_imei_serial').on('click', function() {
        const itemData = $('#modal_imei_serial').data('itemData');
        if (!itemData) {
            showErrorNotification('Item data not found');
            return;
        }
        
        const damageAmount = parseFloat($('#modal_damage_amount').val()) || 0;
        if (damageAmount <= 0) {
            showErrorNotification('Please enter a valid damage amount');
            return;
        }
        
        if (currentRow) {
            // Editing existing row - keep old behavior (single row with all values)
            let imeiSerialValue = '';
            
            if (itemData.type === 'IMEI_Product' || itemData.type === 'Serial_Product') {
                if (damageIMEISerialData.length === 0) {
                    showErrorNotification('Please add at least one IMEI/Serial number');
                    return;
                }
                imeiSerialValue = damageIMEISerialData.join(', ');
            } else if (itemData.type === 'Medicine_Product') {
                if (damageMedicineData.length === 0) {
                    showErrorNotification('Please add at least one medicine entry');
                    return;
                }
                imeiSerialValue = damageMedicineData.map(item => `${item.quantity} - ${item.expiry_date}`).join(', ');
            }
            
            currentRow.find('.damage-amount').val(damageAmount);
            currentRow.find('.imei-serial').val(imeiSerialValue);
            
            // Calculate quantity based on item type
            let quantity = 1;
            if (itemData.type === 'IMEI_Product' || itemData.type === 'Serial_Product') {
                quantity = damageIMEISerialData.length;
            } else if (itemData.type === 'Medicine_Product') {
                quantity = damageMedicineData.reduce((sum, item) => sum + parseInt(item.quantity), 0);
            }
            currentRow.find('.damage-qty').val(quantity);
            
            calculateTotal(currentRow);
            $('#modal_imei_serial').modal('hide');
        } else {
            // Adding new rows - create one row per IMEI/Serial/Medicine entry
            if (itemData.type === 'IMEI_Product' || itemData.type === 'Serial_Product') {
                if (damageIMEISerialData.length === 0) {
                    showErrorNotification('Please add at least one IMEI/Serial number');
                    return;
                }
                
                // Create a separate row for each IMEI/Serial number
                damageIMEISerialData.forEach(function(imeiSerialValue) {
                    const itemDataWithSerial = {
                        ...itemData,
                        imeiSerial: imeiSerialValue
                    };
                    
                    addNewRow(itemDataWithSerial);
                    const newRow = $('#damageItems tr').last();
                    newRow.find('.damage-amount').val(damageAmount);
                    newRow.find('.damage-qty').val(1); // Each IMEI/Serial is quantity 1
                    newRow.find('.imei-serial').val(imeiSerialValue);
                    calculateTotal(newRow);
                    if (checkStockAvailability) validateDamageQuantityAgainstStock(newRow);
                });
            } else if (itemData.type === 'Medicine_Product') {
                if (damageMedicineData.length === 0) {
                    showErrorNotification('Please add at least one medicine entry');
                    return;
                }
                
                // Create a separate row for each medicine entry
                damageMedicineData.forEach(function(medicineItem) {
                    const imeiSerialValue = `${medicineItem.quantity} - ${medicineItem.expiry_date}`;
                    const itemDataWithSerial = {
                        ...itemData,
                        imeiSerial: imeiSerialValue
                    };
                    
                    addNewRow(itemDataWithSerial);
                    const newRow = $('#damageItems tr').last();
                    newRow.find('.damage-amount').val(damageAmount);
                    newRow.find('.damage-qty').val(parseInt(medicineItem.quantity) || 1);
                    newRow.find('.imei-serial').val(imeiSerialValue);
                    calculateTotal(newRow);
                    if (checkStockAvailability) validateDamageQuantityAgainstStock(newRow);
                });
            }
            
            $('#modal_imei_serial').modal('hide');
        }
        
        // Reset modal
        damageIMEISerialData = [];
        damageMedicineData = [];
        currentRow = null;
    });
    
    // Show validation error below a field
    function showFieldError(fieldId, message) {
        const $field = $('#' + fieldId);
        const $errorEl = $('#' + fieldId + '_error');
        if ($field.length && $errorEl.length) {
            $field.addClass('is-invalid');
            $errorEl.text(message).show();
        }
    }

    // Clear validation error for a field
    function clearFieldError(fieldId) {
        const $field = $('#' + fieldId);
        const $errorEl = $('#' + fieldId + '_error');
        if ($field.length && $errorEl.length) {
            $field.removeClass('is-invalid');
            $errorEl.text('').hide();
        }
    }

    // Clear all field-level validation errors (reference_no, date, employee_id)
    function clearAllFieldErrors() {
        ['reference_no', 'date', 'employee_id'].forEach(function(id) {
            clearFieldError(id);
        });
    }

    // Clear field error when user changes the field
    $('#reference_no, #date').on('input change', function() {
        clearFieldError(this.id);
    });
    $('#employee_id').on('change', function() {
        clearFieldError('employee_id');
    });

    // Form submission validation
    $('#damageForm').on('submit', function(e) {
        e.preventDefault();
        let isValid = true;
        clearAllFieldErrors();

        // Validate reference_no
        const referenceNo = $('#reference_no').val();
        if (!referenceNo || !referenceNo.toString().trim()) {
            const msg = (language_key && language_key.Reference_No_is_required) ? language_key.Reference_No_is_required : 'The Reference No is required.';
            showFieldError('reference_no', msg);
            isValid = false;
        }

        // Validate date
        const dateVal = $('#date').val();
        if (!dateVal || !dateVal.toString().trim()) {
            const msg = (language_key && language_key.Date_is_required) ? language_key.Date_is_required : 'The Date is required.';
            showFieldError('date', msg);
            isValid = false;
        }

        // Validate employee
        const employeeId = $('#employee_id').val();
        if (!employeeId) {
            const msg = (language_key && language_key.The_Employee_is_required) ? language_key.The_Employee_is_required : 'The Employee is required.';
            showFieldError('employee_id', msg);
            isValid = false;
        }

        // Check if there are any items in the table -> show in toast only
        if ($('#damageItems tr').length === 0) {
            const msg = (language_key && language_key.Please_add_at_least_one_item_to_the_damage) ? language_key.Please_add_at_least_one_item_to_the_damage : 'Please add at least one item to the damage.';
            showErrorNotification(msg);
            isValid = false;
        } else {
            // Validate damage items (row-level issues can stay as toast or we could show first error in toast)
            const itemErrors = [];
            $('#damageItems tr').each(function(index) {
                const itemId = $(this).find('.item-id').val();
                if (!itemId) {
                    itemErrors.push('Row ' + (index + 1) + ': Please select an item');
                    return;
                }
                const itemType = $(this).find('.item-type').val();
                const imeiSerial = $(this).find('.imei-serial').val();
                let requiresImeiSerial = false;
                if (itemType === 'IMEI_Product' || itemType === 'Serial_Product') {
                    requiresImeiSerial = true;
                } else if (itemType === 'Medicine_Product') {
                    const selectedOption = $('#quickItemSelect option[value="' + itemId + '"]');
                    const expiryDateMaintain = selectedOption.data('expiry-date-maintain') || 'Yes';
                    requiresImeiSerial = (expiryDateMaintain === 'Yes');
                }
                if (requiresImeiSerial && !imeiSerial) {
                    itemErrors.push('Row ' + (index + 1) + ': Please add IMEI/Serial/Medicine details');
                }
                const damageQty = parseFloat($(this).find('.damage-qty').val()) || 0;
                const damageAmount = parseFloat($(this).find('.damage-amount').val()) || 0;
                if (damageQty <= 0) {
                    itemErrors.push('Row ' + (index + 1) + ': Please enter a valid damage quantity');
                }
                if (damageAmount <= 0) {
                    itemErrors.push('Row ' + (index + 1) + ': Please enter a valid damage amount');
                }
            });
            if (itemErrors.length > 0) {
                showErrorNotification(itemErrors.join('\n'));
                isValid = false;
            }
        }

        function doSubmitIfValid() {
            if (!isValid) return;
            if (checkStockAvailability && $('#damageItems .damage-qty.is-invalid').length > 0) {
                showErrorNotification('Damage quantity cannot exceed current stock. Please correct the highlighted rows.');
                return;
            }
            $('#damageForm')[0].submit();
        }

        // If sync validation failed, stop
        if (!isValid) {
            return;
        }

        // Run async stock validation for all rows, then decide submit
        if (checkStockAvailability && $('#damageItems tr').length > 0) {
            const rows = $('#damageItems tr');
            let pending = rows.length;
            if (pending === 0) {
                doSubmitIfValid();
                return;
            }
            rows.each(function() {
                validateDamageQuantityAgainstStock($(this), function() {
                    pending--;
                    if (pending === 0) {
                        doSubmitIfValid();
                    }
                });
            });
        } else {
            doSubmitIfValid();
        }
    });
});
