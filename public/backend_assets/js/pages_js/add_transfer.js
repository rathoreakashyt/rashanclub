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
    let transfer_item_stock_at_outlet_url = $('#transfer_item_stock_at_outlet_url').val();
    let transfer_check_imei_in_outlet_url = $('#transfer_check_imei_in_outlet_url').val();
    // Store the current row being edited
    let currentRow = null;
    
    // Track IMEI/Serial/Medicine data
    let transferIMEISerialData = []; // Array to track all IMEI/Serial numbers
    let transferMedicineData = []; // Array to track medicine data (quantity + expiry)
    
    // Store initial outlet values to detect changes
    let initialFromOutlet = $('#from_outlet_id').val();
    let initialToOutlet = $('#to_outlet_id').val();

    // Function to check if an item already exists in table
    function itemExists(itemId) {
        let exists = false;
        $('#transferItems tr').each(function() {
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
        $('#transferItems tr').each(function() {
            if ($(this).find('.parent-id').val() == parentId) {
                exists = true;
                return false; // break the loop
            }
        });
        return exists;
    }
  
    // Function to update total item count
    function updateTotalItemCount() {
        const count = $('#transferItems tr').length;
        $('#totalItemCount').text(count);
    }

    // Function to clear all items from cart
    function clearCartItems() {
        $('#transferItems').empty();
        updateTotalItemCount();
    }

    // Function to show confirmation when outlets change
    function confirmOutletChange(callback) {
        const itemCount = $('#transferItems tr').length;
        
        if (itemCount > 0) {
            Swal.fire({
                title: 'Confirm Outlet Change',
                text: 'If you change the outlet, cart items will be removed. Do you want to continue?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, remove items',
                cancelButtonText: 'No, keep items'
            }).then((result) => {
                if (result.isConfirmed) {
                    clearCartItems();
                    if (callback) callback();
                } else {
                    // Revert to previous values
                    $('#from_outlet_id').val(initialFromOutlet).trigger('change');
                    $('#to_outlet_id').val(initialToOutlet).trigger('change');
                }
            });
        } else {
            if (callback) callback();
        }
    }

    // From Outlet change event with confirmation
    $('#from_outlet_id').on('change', function() {
        const newValue = $(this).val();
        
        // Only show confirmation if value actually changed and there are items
        if (newValue !== initialFromOutlet && $('#transferItems tr').length > 0) {
            const oldValue = initialFromOutlet;
            confirmOutletChange(function() {
                initialFromOutlet = newValue;
                filterToOutlet();
            });
        } else {
            initialFromOutlet = newValue;
            filterToOutlet();
        }
    });

    // To Outlet change event with confirmation
    $('#to_outlet_id').on('change', function() {
        const newValue = $(this).val();
        
        // Only show confirmation if value actually changed and there are items
        if (newValue !== initialToOutlet && $('#transferItems tr').length > 0) {
            const oldValue = initialToOutlet;
            confirmOutletChange(function() {
                initialToOutlet = newValue;
            });
        } else {
            initialToOutlet = newValue;
        }
    });

    // Function to filter To Outlet dropdown based on From Outlet selection
    function filterToOutlet() {
        const fromOutletId = $('#from_outlet_id').val();
        const toOutletSelect = $('#to_outlet_id');
        
        if (fromOutletId) {
            // Disable the selected from outlet in to outlet dropdown
            toOutletSelect.find('option').each(function() {
                if ($(this).val() === fromOutletId) {
                    $(this).prop('disabled', true);
                } else {
                    $(this).prop('disabled', false);
                }
            });
            
            // If current to outlet is same as from outlet, clear it
            if (toOutletSelect.val() === fromOutletId) {
                toOutletSelect.val('').trigger('change');
            }
        } else {
            // Enable all options if no from outlet selected
            toOutletSelect.find('option').prop('disabled', false);
        }
    }

    // Initialize on page load
    filterToOutlet();
    
    // Function to add a new row to the transfer items table
    function addNewRow(itemData = null) {
        const rowCount = $('#transferItems tr').length;
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
        
        // Build display name with brand (Item-Code-Brand format)
        let displayName = itemData ? itemData.name : '';
        if (itemData && itemData.brandName) {
            displayName += ' - ' + itemData.brandName;
        }
        
        // Create row HTML
        let rowHtml = `
            <td><span class="tf-sn">${rowCount + 1}</span></td>
            <td>
                <input type="hidden" class="item-id" name="items[]" value="${itemData ? itemData.id : ''}">
                <input type="hidden" class="parent-id" name="parent_ids[]" value="${itemData && itemData.parentId ? itemData.parentId : ''}">
                <input type="hidden" class="item-type" name="item_types[]" value="${itemData ? itemData.type : ''}">
                <span class="tf-item-name item-name">${displayName}</span>
            </td>
            <td class="imei-serial-cell">
                <div class="imei-serial-container">
                    <input type="text" class="form-control imei-serial" name="expiry_imei_serial[]" placeholder="IMEI/Serial" readonly style="${showImeiSerial ? '' : 'display:none;'}">
                </div>
            </td>
            <td>
                <div class="input-group">
                    <input type="text" class="form-control number-input quantity" name="quantity_amount[]" min="1" value="1" ${showImeiSerial && (itemData && (itemData.type === 'IMEI_Product' || itemData.type === 'Serial_Product')) ? 'readonly' : ''}>
                    <button type="button" class="btn btn-outline-secondary" type="button">
                        ${saleUnitName}
                    </button>
                </div>
            </td>
            <td>
                <button type="button" class="btn tf-row-remove remove-row">
                    <i class="icon-base ti tabler-trash"></i>
                </button>
            </td>
        `;
        
        newRow.html(rowHtml);
        $('#transferItems').append(newRow);
        
        // If item data is provided and has IMEI/Serial, populate it
        if (itemData && itemData.imeiSerial) {
            newRow.find('.imei-serial').val(itemData.imeiSerial);
        }
        
        // Initialize row events
        initializeRowEvents(newRow);
    }
  
    // Function to initialize events for a row
    function initializeRowEvents(row) {
        // Open IMEI/Serial/Medicine modal on click (edit) - require From Outlet
        function openImeiSerialModalForRow() {
            const fromOutletId = $('#from_outlet_id').val();
            if (!fromOutletId) {
                showErrorNotification('Please select From Outlet first.');
                return;
            }
            currentRow = row;
            const itemId = row.find('.item-id').val();
            
            // Get item type from row's hidden input first, then fallback to select options
            let itemType = row.find('.item-type').val();
            let itemName = row.find('.item-name').text().trim();
            
            // Try to get from select options if available
            const selectedOption = $('#quickItemSelect option[value="' + itemId + '"]');
            if (selectedOption.length > 0) {
                itemType = selectedOption.data('item-type') || itemType;
                itemName = selectedOption.data('item-name') || itemName;
            }
            
            // If item type is still not found, show error
            if (!itemType) {
                showErrorNotification('Item type not found. Please refresh the page and try again.');
                return;
            }
            
            const expiryDateMaintain = selectedOption.data('expiry-date-maintain') || 'Yes';
            
            // Reset modal sections
            $('#imei_serial_section').hide();
            $('#medicine_section').hide();
            transferIMEISerialData = [];
            transferMedicineData = [];
            
            // Show appropriate section based on item type
            if (itemType === 'IMEI_Product' || itemType === 'Serial_Product') {
                const fieldLabel = itemType === 'IMEI_Product' ? 'IMEI' : 'Serial';
                $('#modal_imei_serial_title').text(`Edit ${fieldLabel} Numbers`);
                $('#imei_serial_label').text(`${fieldLabel} Numbers`);
                $('#transfer_imei_serial_input').attr('placeholder', `Enter ${fieldLabel} Number`);
                $('#imei_serial_section').show();
                
                // If there are existing values, populate them
                const existingValues = row.find('.imei-serial').val();
                if (existingValues) {
                    const values = existingValues.split(',').map(v => v.trim());
                    values.forEach(value => {
                        if (value) {
                            transferIMEISerialData.push(value);
                            addTransferIMEISerialField(value);
                        }
                    });
                }
            } else if (itemType === 'Medicine_Product' && expiryDateMaintain === 'Yes') {
                $('#modal_imei_serial_title').text('Edit Medicine Details');
                $('#medicine_section').show();
                $('#transfer_medicine_quantity_input').val('');
                $('#transfer_medicine_expiry_input').val('');
                $('#transfer_medicine_mmyy_input').val('');
                $('#transfer_medicine_mmyy_input').val('');
                
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
                                transferMedicineData.push({ quantity: quantity, expiry_date: expiryDate });
                                addTransferMedicineField(quantity, expiryDate);
                            }
                        });
                    } else {
                        const expiryDate = existingValues.trim();
                        if (expiryDate) {
                            transferMedicineData.push({ quantity: rowQuantity, expiry_date: expiryDate });
                            addTransferMedicineField(rowQuantity, expiryDate);
                        }
                    }
                }
            }
            
            // Store item data for modal
            $('#modal_imei_serial').data('itemData', {
                id: itemId,
                name: itemName,
                type: itemType
            });
            
            // Initialize date picker for medicine expiry if needed
            if (itemType === 'Medicine_Product' && expiryDateMaintain === 'Yes') {
                setTimeout(function() {
                    const expiryInput = $('#transfer_medicine_expiry_input');
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
        row.find('.add-imei-serial, .imei-serial').on('click', openImeiSerialModalForRow);
        
        // Quantity change event - validate against From Outlet stock on keyup (debounced)
        const itemType = row.find('.item-type').val();
        if (itemType !== 'IMEI_Product' && itemType !== 'Serial_Product') {
            row.find('.quantity').on('input', function() {
                const qty = parseFloat($(this).val()) || 0;
                if (qty <= 0) {
                    $(this).val(1);
                }
            });
            // (function() {
                let quantityCheckTimeout;
                row.find('.quantity').on('keyup', function() {
                    const qtyInput = $(this);
                    const fromOutletId = $('#from_outlet_id').val();
                    const itemId = row.find('.item-id').val();
                    const qty = parseInt(qtyInput.val(), 10) || 0;
                    if (!fromOutletId || !itemId || qty <= 0) return;
                    clearTimeout(quantityCheckTimeout);
                    quantityCheckTimeout = setTimeout(function() {
                        $.get(transfer_item_stock_at_outlet_url || (base_url + '/api/transfer/item-stock-at-outlet'), {
                            from_outlet_id: fromOutletId,
                            item_id: itemId
                        }).done(function(res) {
                            const available = parseInt(res.current_stock, 10) || 0;
                            if (qty > available) {
                                showErrorNotification('Quantity cannot exceed available stock at From Outlet. Available: ' + available);
                                qtyInput.val(available > 0 ? available : 1);
                            }
                        });
                    }, 400);
                });
            // })();
        } else {
            // Make quantity readonly for IMEI/Serial products
            row.find('.quantity').prop('readonly', true);
        }
        
        // Remove row button click event
        row.find('.remove-row').on('click', function() {
            row.remove();
            updateRowNumbers();
            updateTotalItemCount();
        });
    }

    // ===============================
    // FIX FOR EDIT PAGE
    // ===============================
    $('#transferItems tr').each(function () {
        const row = $(this);
        const itemType = row.find('.item-type').val();

        // Attach events to existing rows
        initializeRowEvents(row);
        
        // Make quantity readonly for IMEI/Serial products on edit page
        if (itemType === 'IMEI_Product' || itemType === 'Serial_Product') {
            row.find('.quantity').prop('readonly', true);
        }
    });

    // Recalculate summary after all rows are initialized
    updateRowNumbers();
    updateTotalItemCount();
    
    // Function to update row numbers
    function updateRowNumbers() {
        $('#transferItems tr').each(function(index) {
            const sn = $(this).find('.tf-sn');
            if (sn.length > 0) {
                sn.text(index + 1);
            } else {
                $(this).find('td:first').text(index + 1);
            }
        });
    }
    
    // Initialize summary on page load
    updateTotalItemCount();
  
    // Quick item select change event
    $('#quickItemSelect').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const itemId = $(this).val();
        const itemType = selectedOption.data('item-type');
        
        if (!itemId) return;
        
        const fromOutletId = $('#from_outlet_id').val();
        if (!fromOutletId) {
            showErrorNotification('Please select From Outlet first, then add items.');
            $(this).val('');
            return;
        }
        
        const itemData = {
            id: itemId,
            name: selectedOption.data('item-name'),
            parentName: selectedOption.data('item-parent-name'),
            brandName: selectedOption.data('brand-name'),
            saleUnitName: selectedOption.data('sale-unit-name'),
            purchaseUnitName: selectedOption.data('purchase-unit-name'),
            type: itemType
        };
        
        // Build display name with brand (Item-Code-Brand format)
        let displayName = itemData.name;
        if (itemData.brandName) {
            displayName += ' - ' + itemData.brandName;
        }
        itemData.displayName = displayName;
      
        // Check for duplicate items for General_Product and Installment_Product
        if (itemType === 'General_Product' || itemType === 'Installment_Product') {
            if (itemExists(itemId)) {
                showErrorNotification('This item already exists in the transfer list.');
                $(this).val(''); // Reset the select
                return;
            }
            // Add item directly to the table
            addNewRow(itemData);
        } else if (itemType === 'Variation_Product') {
            // Check if this variation product's child items already exist
            if (variationExists(itemId)) {
                showErrorNotification('This variation product already exists in the transfer list.');
                $(this).val(''); // Reset the select
                return;
            }
            
            // Fetch child items via AJAX
            $.ajax({
                url: variation_child_items_url || (base_url + '/api/transfer/variation-child-items'),
                type: 'GET',
                data: { parent_id: itemId },
                success: function(response) {
                    if (response.length > 0) {
                        const parentName = itemData.parentName;
                        
                        response.forEach(function(childItem) {
                            let displayName = childItem.name + ' (' + childItem.code + ')';
                            if (parentName) {
                                displayName = parentName + ' - ' + displayName;
                            }
                            
                            const childData = {
                                id: childItem.id,
                                name: displayName,
                                saleUnitName: itemData.saleUnitName,
                                type: '0',
                                parentId: itemId,
                                brandName: childItem.brand ? childItem.brand.name : ''
                            };
                            
                            // Build display name with brand
                            if (childData.brandName) {
                                childData.name += ' - ' + childData.brandName;
                            }
                            childData.displayName = childData.name;
                            
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
                // Open modal for IMEI/Serial/Medicine products (From Outlet already checked above)
                currentRow = null;
                
                // Reset modal sections
                $('#imei_serial_section').hide();
                $('#medicine_section').hide();
                transferIMEISerialData = [];
                transferMedicineData = [];
                
                // Show appropriate section based on item type
                if (itemType === 'IMEI_Product' || itemType === 'Serial_Product') {
                    const fieldLabel = itemType === 'IMEI_Product' ? 'IMEI' : 'Serial';
                    $('#modal_imei_serial_title').text(`Add ${fieldLabel} Numbers`);
                    $('#imei_serial_label').text(`${fieldLabel} Numbers`);
                    $('#transfer_imei_serial_input').attr('placeholder', `Enter ${fieldLabel} Number`);
                    $('#imei_serial_section').show();
                    $('#transfer_imei_serial_list').empty();
                    $('#transfer_imei_serial_input').val('');
                } else if (itemType === 'Medicine_Product' && expiryDateMaintain === 'Yes') {
                    $('#modal_imei_serial_title').text('Add Medicine Details');
                    $('#medicine_section').show();
                    $('#transfer_medicine_list').empty();
                    $('#transfer_medicine_quantity_input').val('');
                    $('#transfer_medicine_expiry_input').val('');
                    $('#transfer_medicine_mmyy_input').val('');
                }
                
                // Store item data for later use when saving from modal
                $('#modal_imei_serial').data('itemData', itemData);
                
                // Initialize date picker for medicine expiry if needed
                if (itemType === 'Medicine_Product' && expiryDateMaintain === 'Yes') {
                    setTimeout(function() {
                        const expiryInput = $('#transfer_medicine_expiry_input');
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
  
    // Add IMEI/Serial field on Enter key (validate against From Outlet first)
    $(document).on('keypress', '.transfer-imei-serial-input', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            const value = $(this).val().trim();
            if (value) {
                validateAndAddTransferIMEISerial(value, function() {
                    $(this).val('').focus();
                }.bind(this));
            }
        }
    });
    
    // Add IMEI/Serial field on Plus button click (validate against From Outlet first)
    $(document).on('click', '.add-transfer-imei-serial-field', function() {
        const input = $('#transfer_imei_serial_input');
        const value = input.val().trim();
        if (value) {
            validateAndAddTransferIMEISerial(value, function() {
                input.val('').focus();
            });
        }
    });

    // Validate IMEI/Serial is in From Outlet stock, then add to list
    function validateAndAddTransferIMEISerial(value, onSuccess) {
        const fromOutletId = $('#from_outlet_id').val();
        const itemData = $('#modal_imei_serial').data('itemData');
        if (!fromOutletId || !itemData || !itemData.id) {
            showErrorNotification('Please select From Outlet first.');
            if (onSuccess) onSuccess();
            return;
        }
        if (itemData.type !== 'IMEI_Product' && itemData.type !== 'Serial_Product') {
            addTransferIMEISerialField(value);
            if (onSuccess) onSuccess();
            return;
        }
        $.ajax({
            url: transfer_check_imei_in_outlet_url || (base_url + '/api/transfer/check-imei-in-outlet'),
            type: 'POST',
            data: {
                from_outlet_id: fromOutletId,
                item_id: itemData.id,
                'item_details[0][value]': value,
                _token: $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val()
            },
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).done(function(res) {
            if (res.available === true) {
                showErrorNotification(res.message || 'This IMEI/Serial does not exist in From Outlet\'s stock.');
            } else {
                addTransferIMEISerialField(value);
            }
            if (onSuccess) onSuccess();
        }).fail(function(xhr) {
            const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Could not validate IMEI/Serial.';
            showErrorNotification(msg);
            if (onSuccess) onSuccess();
        });
    }
  
    // Get all IMEI/Serial values already in the transfer cart (table rows), optionally excluding one row (when editing)
    function getImeiSerialsInCart(excludeRow) {
        const seen = [];
        $('#transferItems tr').each(function() {
            if (excludeRow && excludeRow.length && this === excludeRow[0]) return;
            const val = $(this).find('.imei-serial').val();
            if (!val || typeof val !== 'string') return;
            val.split(',').forEach(function(part) {
                const p = part.trim();
                if (p && seen.indexOf(p) === -1) seen.push(p);
            });
        });
        return seen;
    }

    // Function to add IMEI/Serial field (no outlet check - call validateAndAddTransferIMEISerial for that)
    function addTransferIMEISerialField(value) {
        if (transferIMEISerialData.includes(value)) {
            const itemData = $('#modal_imei_serial').data('itemData');
            const fieldLabel = itemData && itemData.type === 'IMEI_Product' ? 'IMEI' : 'Serial';
            showErrorNotification(`${fieldLabel} number "${value}" already exists in the list.`);
            return;
        }
        const inCart = getImeiSerialsInCart(currentRow);
        if (inCart.indexOf(value) !== -1) {
            const itemData = $('#modal_imei_serial').data('itemData');
            const fieldLabel = itemData && itemData.type === 'IMEI_Product' ? 'IMEI' : 'Serial';
            showErrorNotification(`${fieldLabel} number "${value}" is already in the transfer cart. Duplicate not allowed.`);
            return;
        }
        transferIMEISerialData.push(value);
        const itemData = $('#modal_imei_serial').data('itemData');
        const fieldLabel = itemData && itemData.type === 'IMEI_Product' ? 'IMEI' : 'Serial';
        const listContainer = $('#transfer_imei_serial_list');
        
        const fieldHtml = `
            <div class="input-group mb-2 transfer-imei-serial-item" data-value="${value}">
                <input type="text" 
                    class="form-control transfer-imei-serial-value" 
                    value="${value}"
                    readonly />
                <button type="button" class="btn btn-outline-danger remove-transfer-imei-serial-field" data-value="${value}">
                    <i class="ti tabler-trash"></i>
                </button>
            </div>
        `;
        
        listContainer.append(fieldHtml);
        $('#transfer_imei_serial_input').focus();
    }
  
    // Remove IMEI/Serial field
    $(document).on('click', '.remove-transfer-imei-serial-field', function() {
        const value = $(this).data('value');
        const index = transferIMEISerialData.indexOf(value);
        if (index > -1) {
            transferIMEISerialData.splice(index, 1);
        }
        $(this).closest('.transfer-imei-serial-item').remove();
    });
  
    // MM/YY input handler - Auto-formatting like bank card expiry
    $(document).on('input', '.transfer-medicine-mmyy-input', function() {
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
                let expiryInput = $('#transfer_medicine_expiry_input');
                
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
    $(document).on('keypress', '.transfer-medicine-mmyy-input', function(e) {
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
    $(document).on('keypress', '.transfer-medicine-quantity-input, .transfer-medicine-expiry-input', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            const quantityInput = $('#transfer_medicine_quantity_input');
            const expiryInput = $('#transfer_medicine_expiry_input');
            const mmyyInput = $('#transfer_medicine_mmyy_input');
            const quantity = quantityInput.val().trim();
            const expiryDate = expiryInput.val().trim();
            
            if (quantity && expiryDate) {
                addTransferMedicineField(quantity, expiryDate);
                quantityInput.val('');
                expiryInput.val('');
                mmyyInput.val('');
                quantityInput.focus();
            }
        }
    });
  
    // Add Medicine field on Plus button click
    $(document).on('click', '.add-transfer-medicine-field', function() {
        const quantityInput = $('#transfer_medicine_quantity_input');
        const expiryInput = $('#transfer_medicine_expiry_input');
        const mmyyInput = $('#transfer_medicine_mmyy_input');
        const quantity = quantityInput.val().trim();
        const expiryDate = expiryInput.val().trim();
        
        if (quantity && expiryDate) {
            addTransferMedicineField(quantity, expiryDate);
            quantityInput.val('');
            expiryInput.val('');
            mmyyInput.val('');
            quantityInput.focus();
        } else {
            showErrorNotification('Please enter both quantity and expiry date.');
        }
    });
  
    // Function to add Medicine field
    function addTransferMedicineField(quantity, expiryDate) {
        const key = `${quantity}-${expiryDate}`;
        const exists = transferMedicineData.some(item => item.quantity === quantity && item.expiry_date === expiryDate);
        
        if (exists) {
            showErrorNotification('This medicine entry already exists in the list.');
            return;
        }
        
        transferMedicineData.push({ quantity: quantity, expiry_date: expiryDate });
        const listContainer = $('#transfer_medicine_list');
        
        const fieldHtml = `
            <div class="input-group mb-2 transfer-medicine-item" data-quantity="${quantity}" data-expiry="${expiryDate}">
                <input type="text" 
                    class="form-control transfer-medicine-value" 
                    value="${quantity} - ${expiryDate}"
                    readonly />
                <button type="button" class="btn btn-outline-danger remove-transfer-medicine-field" data-quantity="${quantity}" data-expiry="${expiryDate}">
                    <i class="ti tabler-trash"></i>
                </button>
            </div>
        `;
        
        listContainer.append(fieldHtml);
        $('#transfer_medicine_quantity_input').focus();
    }
  
    // Remove Medicine field
    $(document).on('click', '.remove-transfer-medicine-field', function() {
        const quantity = $(this).data('quantity');
        const expiryDate = $(this).data('expiry');
        const index = transferMedicineData.findIndex(item => item.quantity === quantity && item.expiry_date === expiryDate);
        if (index > -1) {
            transferMedicineData.splice(index, 1);
        }
        $(this).closest('.transfer-medicine-item').remove();
    });
  
    // Save IMEI/Serial/Medicine modal
    $('#save_imei_serial').on('click', function() {
        const itemData = $('#modal_imei_serial').data('itemData');
        if (!itemData) {
            showErrorNotification('Item data not found');
            return;
        }
        
        if (currentRow) {
            // Editing existing row - keep old behavior (single row with all values)
            let imeiSerialValue = '';
            
            if (itemData.type === 'IMEI_Product' || itemData.type === 'Serial_Product') {
                if (transferIMEISerialData.length === 0) {
                    showErrorNotification('Please add at least one IMEI/Serial number');
                    return;
                }
                imeiSerialValue = transferIMEISerialData.join(', ');
            } else if (itemData.type === 'Medicine_Product') {
                if (transferMedicineData.length === 0) {
                    showErrorNotification('Please add at least one medicine entry');
                    return;
                }
                imeiSerialValue = transferMedicineData.map(item => `${item.quantity} - ${item.expiry_date}`).join(', ');
            }
            
            currentRow.find('.imei-serial').val(imeiSerialValue);
            
            // Calculate quantity based on item type
            let quantity = 1;
            if (itemData.type === 'IMEI_Product' || itemData.type === 'Serial_Product') {
                quantity = transferIMEISerialData.length;
                // Make quantity readonly for IMEI/Serial
                currentRow.find('.quantity').prop('readonly', true);
            } else if (itemData.type === 'Medicine_Product') {
                quantity = transferMedicineData.reduce((sum, item) => sum + parseInt(item.quantity), 0);
            }
            currentRow.find('.quantity').val(quantity);
            
            $('#modal_imei_serial').modal('hide');
        } else {
            // Adding new rows - create one row per IMEI/Serial/Medicine entry
            if (itemData.type === 'IMEI_Product' || itemData.type === 'Serial_Product') {
                if (transferIMEISerialData.length === 0) {
                    showErrorNotification('Please add at least one IMEI/Serial number');
                    return;
                }
                
                // Create a separate row for each IMEI/Serial number
                transferIMEISerialData.forEach(function(imeiSerialValue) {
                    const itemDataWithSerial = {
                        ...itemData,
                        imeiSerial: imeiSerialValue
                    };
                    
                    addNewRow(itemDataWithSerial);
                    const newRow = $('#transferItems tr').last();
                    newRow.find('.quantity').val(1).prop('readonly', true); // Each IMEI/Serial is quantity 1, readonly
                    newRow.find('.imei-serial').val(imeiSerialValue);
                });
            } else if (itemData.type === 'Medicine_Product') {
                if (transferMedicineData.length === 0) {
                    showErrorNotification('Please add at least one medicine entry');
                    return;
                }
                
                // Create a separate row for each medicine entry
                transferMedicineData.forEach(function(medicineItem) {
                    const imeiSerialValue = `${medicineItem.quantity} - ${medicineItem.expiry_date}`;
                    const itemDataWithSerial = {
                        ...itemData,
                        imeiSerial: imeiSerialValue
                    };
                    
                    addNewRow(itemDataWithSerial);
                    const newRow = $('#transferItems tr').last();
                    newRow.find('.quantity').val(parseInt(medicineItem.quantity) || 1);
                    newRow.find('.imei-serial').val(imeiSerialValue);
                });
            }
            
            $('#modal_imei_serial').modal('hide');
        }
        
        // Reset modal
        transferIMEISerialData = [];
        transferMedicineData = [];
        currentRow = null;
        updateTotalItemCount();
    });
  
    // Form submission validation
    $('#transferForm').on('submit', function(e) {
        let isValid = true;
        const errors = [];
        
        // Check if there are any items in the table
        if ($('#transferItems tr').length === 0) {
            errors.push('Please add at least one item to the transfer');
            isValid = false;
        } else {
            // Validate transfer items
            $('#transferItems tr').each(function(index) {
                const itemId = $(this).find('.item-id').val();
                const itemType = $(this).find('.item-type').val();
                
                if (!itemId) {
                    errors.push(`Row ${index + 1}: Please select an item`);
                    isValid = false;
                    return;
                }
                
                // Validate based on item type
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
                    const imeiSerial = $(this).find('.imei-serial').val();
                    
                    if (!imeiSerial || imeiSerial.trim() === '') {
                        const fieldLabel = itemType === 'Medicine_Product' ? 'Medicine' : (itemType === 'IMEI_Product' ? 'IMEI' : 'Serial');
                        errors.push(`Row ${index + 1}: Please add ${fieldLabel} details`);
                        isValid = false;
                    }
                    
                    // For IMEI/Serial, quantity should equal the number of IMEI/Serial entries
                    if (itemType === 'IMEI_Product' || itemType === 'Serial_Product') {
                        const quantity = parseInt($(this).find('.quantity').val()) || 0;
                        const imeiSerialCount = imeiSerial ? imeiSerial.split(',').filter(v => v.trim()).length : 0;
                        
                        if (quantity !== imeiSerialCount) {
                            errors.push(`Row ${index + 1}: Quantity must match the number of ${itemType === 'IMEI_Product' ? 'IMEI' : 'Serial'} entries`);
                            isValid = false;
                        }
                    }
                } else {
                    const quantity = parseFloat($(this).find('.quantity').val()) || 0;
                    
                    if (quantity <= 0 || !Number.isInteger(quantity)) {
                        errors.push(`Row ${index + 1}: Please enter a valid quantity (must be a whole number greater than 0)`);
                        isValid = false;
                    }
                }
            });
        }
        
        // Validate from outlet
        if (!$('#from_outlet_id').val()) {
            errors.push('Please select a from outlet');
            isValid = false;
        }
        
        // Validate to outlet
        if (!$('#to_outlet_id').val()) {
            errors.push('Please select a to outlet');
            isValid = false;
        }
        
        // Validate that from and to outlets are different
        if ($('#from_outlet_id').val() === $('#to_outlet_id').val()) {
            errors.push('From outlet and To outlet must be different');
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            showErrorNotification(errors.join('\n'));
        }
    });
});
