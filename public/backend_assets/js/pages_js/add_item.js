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
            
            // Handle nested array fields (e.g., variations.0.item_code)
            if (field.includes('.')) {
                let parts = field.split('.');
                if (parts[0] === 'variations' && parts.length >= 3) {
                    // Handle variation fields: variations.0.item_code
                    let variationIndex = parts[1];
                    let fieldName = parts[2];
                    let selector = `.variation-${fieldName.replace(/_/g, '-')}`;
                    input = $(`#generatedVariationsBody [data-variation-index="${variationIndex}"] ${selector}`);
                    if (input.length === 0) {
                        // Try alternative selector
                        input = $(`#generatedVariationsBody [data-variation-index="${variationIndex}"] [name*="${fieldName}"]`);
                    }
                } else {
                    // Handle other nested fields
                    let [baseName, index] = field.split('.');
                    input = $(`${wrapperSelector} [name="${baseName}[]"]`).eq(parseInt(index));
                }
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
            }
        });
    }
    // Fetch suppliers on page load
    fetchSuppliers();
    
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



    // Function to update Brands select options
    function updateBrandSelect(brands, selectedId = null) {
        let select = $('#brand_id');
        select.empty();
        select.append(`<option value="">${ language_key.Select + ' ' + language_key.Brand }</option>`);
        brands.forEach(function(brand) {
            let option = $('<option></option>')
                .val(brand.id)
                .text(brand.name);
            
            if (selectedId && brand.id === selectedId) {
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
            placeholder: language_key.Select + ' ' + language_key.Brand
        });
    }

    // Function to fetch Brands
    function fetchBrands() {
        $.ajax({
            type: "GET",
            url: base_url + "/get-brands",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    updateBrandSelect(response.data);
                }
            }
        });
    }
    // Fetch brands on page load
    fetchBrands();

    $(document).on('click', '.add_brand', function (e) {
        e.preventDefault();
        let formData = $('#brandForm').serialize();
        // Clear previous errors
        clearValidationErrors();
        $.ajax({
            type: "POST",
            url: base_url + "/store-brand",
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    $('#modal_brand').modal('hide');
                    $('#brandForm')[0].reset();
                    showSuccessNotification(response.message || 'Brand added successfully');
                    // Update supplier select with new data and select the newly added supplier
                    if (response.data && response.data.brands) {
                        updateBrandSelect(response.data.brands, response.data.brand.id);
                    }
                } else {
                    showErrorNotification(response.message || 'Something went wrong');
                }
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    // Handle validation errors
                    showValidationErrors('#brandForm', xhr.responseJSON.errors);
                    showErrorNotification('Please check the form for errors');
                } else {
                    showErrorNotification('An unexpected error occurred');
                }
            }
        });
    });


    
    // Function to update Categories select options
    function updateCategorySelect(categories, selectedId = null) {
        let select = $('#category_id');
        select.empty();
        select.append(`<option value="">${ language_key.Select + ' ' + language_key.Category }</option>`);
        categories.forEach(function(category) {
            let option = $('<option></option>')
                .val(category.id)
                .text(category.name);
            
            if (selectedId && category.id === selectedId) {
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
            placeholder: language_key.Select + ' ' + language_key.Category
        });
    }

    // Function to fetch Categories
    function fetchCategories() {
        $.ajax({
            type: "GET", 
            url: base_url + "/get-categories",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    updateCategorySelect(response.data);
                }
            }
        });
    }
    // Fetch categories on page load
    fetchCategories();

    $(document).on('click', '.add_category', function (e) {
        e.preventDefault();
        let formData = $('#categoryForm').serialize();
        // Clear previous errors
        clearValidationErrors();
        $.ajax({
            type: "POST",
            url: base_url + "/store-category", 
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    $('#modal_category').modal('hide');
                    $('#categoryForm')[0].reset();
                    showSuccessNotification(response.message || 'Category added successfully');
                    // Update category select with new data and select the newly added category
                    if (response.data && response.data.categories) {
                        updateCategorySelect(response.data.categories, response.data.category.id);
                    }
                } else {
                    showErrorNotification(response.message || 'Something went wrong');
                }
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    // Handle validation errors
                    showValidationErrors('#categoryForm', xhr.responseJSON.errors);
                    showErrorNotification('Please check the form for errors');
                } else {
                    showErrorNotification('An unexpected error occurred');
                }
            }
        });
    });


    $(document).on('click', '.purchase_unit', function (e) {
        e.preventDefault();
        $('.sale_or_purchase').val('Purchase');
    });
    
    $(document).on('click', '.sale_unit', function (e) {
        e.preventDefault();
        $('.sale_or_purchase').val('Sale'); 
    });

    // Function to update Units select options
    function updateUnitSelect(selector, units, selectedId = null) {
        let select = selector;
        select.empty();
        select.append(`<option value="">${ language_key.Select + ' ' + language_key.Unit }</option>`);
        units.forEach(function(unit) {
            let option = $('<option></option>')
                .val(unit.id)
                .text(unit.unit_name);
            
            if (selectedId && unit.id === selectedId) {
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
            placeholder: language_key.Select + ' ' + language_key.Unit
        });
    }

    // Function to fetch Units
    function fetchUnits(selector) {
        $.ajax({
            type: "GET", 
            url: base_url + "/get-units",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    updateUnitSelect(selector, response.data);
                }
            }
        });
    }

    // Fetch units on page load
    fetchUnits($('#purchase_unit_id'));
    fetchUnits($('#sale_unit_id'));

    $(document).on('click', '.add_unit', function (e) {
        e.preventDefault();
        let formData = $('#unitForm').serialize();

        // Clear previous errors
        clearValidationErrors();
        $.ajax({
            type: "POST",
            url: base_url + "/store-unit", 
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    $('#modal_unit').modal('hide');
                    $('#unitForm')[0].reset();
                    showSuccessNotification(response.message || 'Unit added successfully');
                    // Update unit select with new data and select the newly added unit
                    if (response.data && response.data.units) {
                        if($('.sale_or_purchase').val() == 'Purchase'){
                            updateUnitSelect($('#purchase_unit_id'), response.data.units, response.data.unit.id);
                            fetchUnits($('#sale_unit_id'));
                            setTimeout(() => {
                                $('#sale_unit_id').parent().find('.select2-selection__placeholder').text(language_key['Sale Unit']);
                            }, 200);

                        }else{
                            updateUnitSelect($('#sale_unit_id'), response.data.units, response.data.unit.id);
                        }
                    }
                } else {
                    showErrorNotification(response.message || 'Something went wrong');
                }
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    // Handle validation errors
                    showValidationErrors('#unitForm', xhr.responseJSON.errors);
                    showErrorNotification('Please check the form for errors');
                } else {
                    showErrorNotification('An unexpected error occurred');
                }
            }
        });
    });


    
    // Function to update Racks select options
    function updateRackSelect(racks, selectedId = null) {
        let select = $('#rack_id');
        select.empty();
        select.append(`<option value="">${ language_key.Select + ' ' + language_key.Rack }</option>`);
        racks.forEach(function(rack) {
            let option = $('<option></option>')
                .val(rack.id)
                .text(rack.name);
            
            if (selectedId && rack.id === selectedId) {
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
            placeholder: language_key.Select + ' ' + language_key.Rack
        });
    }

    // Function to fetch Racks
    function fetchRacks() {
        $.ajax({
            type: "GET", 
            url: base_url + "/get-racks",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    updateRackSelect(response.data);
                }
            }
        });
    }
    // Fetch racks on page load
    fetchRacks();

    $(document).on('click', '.add_rack', function (e) {
        e.preventDefault();
        let formData = $('#rackForm').serialize();
        // Clear previous errors
        clearValidationErrors();
        $.ajax({
            type: "POST",
            url: base_url + "/store-rack", 
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    $('#modal_rack').modal('hide');
                    $('#rackForm')[0].reset();
                    showSuccessNotification(response.message || 'Rack added successfully');
                    // Update rack select with new data and select the newly added rack
                    if (response.data && response.data.racks) {
                        updateRackSelect(response.data.racks, response.data.rack.id);
                    }
                } else {
                    showErrorNotification(response.message || 'Something went wrong');
                }
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    // Handle validation errors
                    showValidationErrors('#rackForm', xhr.responseJSON.errors);
                    showErrorNotification('Please check the form for errors');
                } else {
                    showErrorNotification('An unexpected error occurred');
                }
            }
        });
    });




    /** #################### -- Add Item Main Code --  #################### **/

    $(document).on('change', '#unit_type', function (e) {
        let itemType = $('#type').val();
        // Don't handle unit type changes for Service_Product and Combo_Product
        if (itemType === 'Service_Product' || itemType === 'Combo_Product') {
            return;
        }
        if($(this).val() == '1'){
            $('.purchase_unit_wrap').slideUp();
            $('.sale_unit_wrap').slideDown();
            $('.conversion_rate_wrap').slideUp();
            $('.sale_unit_label').text('Unit');
            // Remove required from purchase unit and conversion rate
            $('#purchase_unit_id').removeAttr('required');
            $('#conversion_rate').removeAttr('required');
            // Set conversion rate to 1 for single unit
            $('#conversion_rate').val('1');
            // Add required to sale unit
            $('#sale_unit_id').attr('required', 'required');
            $('#sale_unit_id').parent().find('.select2-selection__placeholder').text('Unit');


            // fetchUnits($('#sale_unit_id'));
            // $('#sale_unit_id').parent().find('.select2-selection__placeholder').text('Unit');

        }else{
            $('.purchase_unit_wrap').slideDown();
            $('.sale_unit_wrap').slideDown();
            $('.conversion_rate_wrap').slideDown();
            $('.sale_unit_label').text(language_key['Sale Unit']);
            

            // Add required to all unit fields
            $('#purchase_unit_id').attr('required', 'required');
            $('#sale_unit_id').attr('required', 'required');
            $('#conversion_rate').attr('required', 'required');

            fetchUnits($('#purchase_unit_id'));
 
        }
        
        // Recalculate sale price from profit margin after unit_type change
        // Use setTimeout to ensure conversion_rate is updated first
        setTimeout(function() {
            calculateSalePriceFromProfitMargin();
        }, 100);
    });


    // Item Add Form
    $(document).on('change', '.item_type', function (e) {
        let newItemType = $(this).val();
        let previousType = $(this).data('previous-type') || 'General_Product';
        
        // Check if opening stock is set
        if (openingStockData && Object.keys(openingStockData).length > 0) {
            // Prevent default change behavior
            e.stopImmediatePropagation();
            // Revert to previous value temporarily
            $(this).val(previousType);
            
            Swal.fire({
                title: 'Warning',
                text: "You've set Opening stock, if you change it the opening stock will be gone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Continue',
                cancelButtonText: 'Cancel',
                customClass: {
                    confirmButton: 'btn btn-primary',
                    cancelButton: 'btn btn-label-secondary'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.value) {
                    // User confirmed, proceed with change
                    openingStockData = {};
                    $('#openingStockSummary').hide().empty();
                    $('#setOpeningStockBtn').html(`<i class="ti tabler-settings me-1"></i> ${language_key['Set Opening Stock']}`);
                    variationAttributesData = [];
                    // Set the new type and proceed
                    $(this).val(newItemType);
                    $(this).data('previous-type', newItemType);
                    fieldShowHideByType(newItemType);
                } else {
                    // User cancelled, keep previous type
                    $(this).val(previousType);
                    $(this).data('previous-type', previousType);
                }
            });
        } else {
            // No opening stock set, proceed normally
            openingStockData = {};
            $('#openingStockSummary').hide().empty();
            $('#setOpeningStockBtn').html(`<i class="ti tabler-settings me-1"></i> ${language_key['Set Opening Stock']}`);
            variationAttributesData = [];
            $(this).data('previous-type', newItemType);
            fieldShowHideByType(newItemType);
        }
    });

    function fieldShowHideByType(item_type){
        $('.item-description-wrap').html('')
        
        // Reset all hide-for-service-product fields visibility
        $('.hide-for-service-product').show();
        
        if(item_type == 'General_Product'){
            $('.pharmacy_section').slideUp();
            $('.purchase_price_wrap').slideDown();
            $('.mrp_price_wrap').slideDown();
            $('.sale_price_wrap').slideDown();
            $('.whole_sale_price_wrap').slideDown();
            // Show Price Information section for General_Product
            $('.card-header:has(h5:contains("Price Information"))').slideDown();
            $('.card-header:has(h5:contains("Price Information"))').next('.card-body').slideDown();
            $('.variation_section').slideUp();
            $('.generated_variations_section').slideUp();
            $('.stock_information_section').slideDown();
            // Enable double unit for General_Product
            $('#unit_type option[value="2"]').prop('disabled', false);
        }else if(item_type == 'Variation_Product') {
            $('.pharmacy_section').slideUp();
            $('.purchase_price_wrap').slideUp();
            $('.mrp_price_wrap').slideUp();
            $('.sale_price_wrap').slideUp();
            $('.whole_sale_price_wrap').slideUp();
            // Hide Price Information section (header and body) for Variation_Product
            $('.card-header:has(h5:contains("Price Information"))').slideUp();
            $('.card-header:has(h5:contains("Price Information"))').next('.card-body').slideUp();
            $('.variation_section').slideDown();
            // Hide stock information section for variation products
            $('.stock_information_section').slideUp();
            // Clear generated variations when switching to variation product
            $('#generatedVariationsBody').empty();
            $('.generated_variations_section').slideUp();
            generatedVariations = [];
        }else if(item_type == 'IMEI_Product') {
            $('.pharmacy_section').slideUp();
            $('.purchase_price_wrap').slideDown();
            $('.mrp_price_wrap').slideDown();
            $('.sale_price_wrap').slideDown();
            $('.whole_sale_price_wrap').slideDown();
            // Show Price Information section for IMEI_Product
            $('.card-header:has(h5:contains("Price Information"))').slideDown();
            $('.card-header:has(h5:contains("Price Information"))').next('.card-body').slideDown();
            $('.variation_section').slideUp();
            $('.generated_variations_section').slideUp();
            $('.stock_information_section').slideDown();
            // Disable double unit for IMEI_Product
            $('#unit_type option[value="2"]').prop('disabled', true);
            $('#unit_type').val('1').trigger('change');
            $('#conversion_rate').val('1');
        }else if(item_type == 'Serial_Product') {
            $('.pharmacy_section').slideUp();
            $('.purchase_price_wrap').slideDown();
            $('.mrp_price_wrap').slideDown();
            $('.sale_price_wrap').slideDown();
            $('.whole_sale_price_wrap').slideDown();
            // Show Price Information section for Serial_Product
            $('.card-header:has(h5:contains("Price Information"))').slideDown();
            $('.card-header:has(h5:contains("Price Information"))').next('.card-body').slideDown();
            $('.variation_section').slideUp();
            $('.generated_variations_section').slideUp();
            $('.stock_information_section').slideDown();
            // Disable double unit for Serial_Product
            $('#unit_type option[value="2"]').prop('disabled', true);
            $('#unit_type').val('1').trigger('change');
            $('#conversion_rate').val('1');
        }else if(item_type == 'Medicine_Product') {
            $('.pharmacy_section').slideDown();
            $('.purchase_price_wrap').slideDown();
            $('.mrp_price_wrap').slideDown();
            $('.sale_price_wrap').slideDown();
            $('.whole_sale_price_wrap').slideDown();
            // Show Price Information section for Medicine_Product
            $('.card-header:has(h5:contains("Price Information"))').slideDown();
            $('.card-header:has(h5:contains("Price Information"))').next('.card-body').slideDown();
            $('.variation_section').slideUp();
            $('.generated_variations_section').slideUp();
            $('.stock_information_section').slideDown();
            // Enable double unit for Medicine_Product
            $('#unit_type option[value="2"]').prop('disabled', false);
        }else if(item_type == 'Installment_Product') {
            $('.pharmacy_section').slideUp();
            $('.purchase_price_wrap').slideDown();
            $('.mrp_price_wrap').slideDown();
            $('.sale_price_wrap').slideDown();
            $('.whole_sale_price_wrap').slideDown();
            // Show Price Information section for Installment_Product
            $('.card-header:has(h5:contains("Price Information"))').slideDown();
            $('.card-header:has(h5:contains("Price Information"))').next('.card-body').slideDown();
            $('.variation_section').slideUp();
            $('.generated_variations_section').slideUp();
            $('.stock_information_section').slideDown();
            // Disable double unit for Installment_Product
            $('#unit_type option[value="2"]').prop('disabled', true);
            $('#unit_type').val('1').trigger('change');
            $('#conversion_rate').val('1');
        }else if(item_type == 'Service_Product') {
            // Hide all fields that should not be shown for Service_Product
            $('.pharmacy_section').slideUp();
            $('.purchase_price_wrap').slideUp();
            $('.mrp_price_wrap').slideUp();
            $('.sale_price_wrap').slideDown(); // Show sale price
            $('.whole_sale_price_wrap').slideUp();
            $('.variation_section').slideUp();
            $('.generated_variations_section').slideUp();
            $('.stock_information_section').slideUp();
            $('.hide-for-service-product').hide(); // Hide all fields marked to hide for service product (use hide() instead of slideUp() for immediate effect)
            generatedVariations = [];
            // Hide unit type and unit fields explicitly for Service_Product
            $('#unit_type').closest('.validate_wrapper').closest('.row').closest('.card-body').hide();
            $('#unit_type').closest('.validate_wrapper').closest('.row').closest('.card-body').prev('.card-header').hide();
            // Also hide individual unit fields
            $('#unit_type').closest('.validate_wrapper').hide();
            $('#purchase_unit_id').closest('.purchase_unit_wrap').hide();
            $('#sale_unit_id').closest('.sale_unit_wrap').hide();
            $('#conversion_rate').closest('.conversion_rate_wrap').hide();
            // Disable double unit for Service_Product
            $('#unit_type option[value="2"]').prop('disabled', true);
            $('#unit_type').val('1').trigger('change');
            $('#conversion_rate').val('1');
            // Remove required attributes from unit fields for Service_Product
            $('#unit_type').removeAttr('required');
            $('#purchase_unit_id').removeAttr('required');
            $('#sale_unit_id').removeAttr('required');
            $('#conversion_rate').removeAttr('required');
        }else if(item_type == 'Combo_Product') {
            $('.pharmacy_section').slideUp();
            $('.purchase_price_wrap').slideUp();
            $('.mrp_price_wrap').slideUp();
            $('.sale_price_wrap').slideUp(); // Hide regular sale price field
            $('.whole_sale_price_wrap').slideUp();
            $('.variation_section').slideUp();
            $('.generated_variations_section').slideUp();
            $('.stock_information_section').slideUp();
            $('.combo_product_section').slideDown();
            $('.hide-for-combo-product').slideUp();
            generatedVariations = [];
            // Disable double unit for Combo_Product
            $('#unit_type option[value="2"]').prop('disabled', true);
            $('#unit_type').val('1').trigger('change');
            $('#conversion_rate').val('1');
            // Remove required attributes from unit fields for Combo_Product
            $('#unit_type').removeAttr('required');
            $('#purchase_unit_id').removeAttr('required');
            $('#sale_unit_id').removeAttr('required');
            $('#conversion_rate').removeAttr('required');
            // Fetch General_Product items for combo
            fetchGeneralProductsForCombo();
        } else {
            // Enable double unit for other product types
            $('#unit_type option[value="2"]').prop('disabled', false);
        }
    }
    // Initialize on page load
    fieldShowHideByType('General_Product');
    
    // Ensure variation section is hidden on initial load if not Variation_Product
    if ($('#type').val() !== 'Variation_Product') {
        $('.variation_section').hide();
        $('.generated_variations_section').hide();
    }

    // Function to calculate sale price based on purchase price, profit margin, unit type and conversion rate
    function calculateSalePriceFromProfitMargin() {
        let purchasePrice = parseFloat($('#purchase_price').val()) || 0;
        let profitMargin = parseFloat($('#profit_margin').val()) || 0;
        let unitType = $('#unit_type').val();
        let conversionRate = parseFloat($('#conversion_rate').val()) || 1;
        
        if (purchasePrice > 0 && profitMargin >= 0) {
            let basePrice = purchasePrice;
            
            // If unit_type is 2, we need to convert purchase price (per purchase unit) to sale price (per sale unit)
            // by dividing by conversion_rate
            if (unitType == 2 && conversionRate > 0) {
                basePrice = purchasePrice / conversionRate;
            }
            
            // Calculate sale price: base_price * (1 + profit_margin / 100)
            let salePrice = basePrice * (1 + profitMargin / 100);
            $('#sale_price').val(salePrice.toFixed(2));
        }
    }

    // Profit margin calculation - calculate sale price based on purchase price and profit margin
    // Trigger on purchase_price, profit_margin, conversion_rate changes
    $(document).on('input', '#purchase_price, #profit_margin, #conversion_rate', function() {
        calculateSalePriceFromProfitMargin();
    });


    /** #################### -- Variation Product Code --  #################### **/
    
    let variationsData = [];
    let generatedVariations = [];
    let variationAttributesData = []; // Store variation attributes (rows) for variation_details

    // Fetch variations on page load
    function fetchVariations() {
        $.ajax({
            type: "GET",
            url: base_url + "/get-variations",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    variationsData = response.data;
                    updateVariationOptions();
                }
            }
        });
    }

    // Update variation options in all dropdowns
    function updateVariationOptions() {
        $('.variation-option').each(function() {
            let $select = $(this);
            let currentValue = $select.val();
            let isSelect2Initialized = $select.hasClass('select2-hidden-accessible');
            
            // Destroy select2 if initialized
            if (isSelect2Initialized) {
                $select.select2('destroy');
            }
            
            $select.empty().append('<option value="">Select Variation</option>');
            
            variationsData.forEach(function(variation) {
                let option = $('<option></option>')
                    .val(variation.id)
                    .text(variation.variation_name)
                    .data('values', variation.variation_value);
                $select.append(option);
            });
            
            // Reinitialize select2
            $select.select2({
                dropdownParent: $select.closest('.card-body'),
                placeholder: 'Select Variation'
            });
            
            // Restore previous value if exists
            if (currentValue) {
                $select.val(currentValue).trigger('change');
            }
        });
        
        // Ensure all variation-values selects are initialized with Select2
        $('.variation-values').each(function() {
            let $select = $(this);
            let isSelect2Initialized = $select.hasClass('select2-hidden-accessible');
            
            // Only initialize if not already initialized
            if (!isSelect2Initialized) {
                $select.select2({
                    dropdownParent: $select.closest('.card-body'),
                    placeholder: 'Select Values',
                    multiple: true
                });
            }
        });
    }

    // When variation option is selected, populate values
    $(document).on('change', '.variation-option', function() {
        let $select = $(this);
        let selectedVariationId = $select.val();
        let $valuesSelect = $select.closest('tr').find('.variation-values');
        let currentValues = $valuesSelect.val() || [];
        let isSelect2Initialized = $valuesSelect.hasClass('select2-hidden-accessible');
        
        // Check for duplicate variation selection
        if (selectedVariationId) {
            let selectedVariationName = variationsData.find(v => v.id == selectedVariationId)?.variation_name;
            let duplicateFound = false;
            
            $('#variationAttributesBody tr').each(function() {
                let $row = $(this);
                if ($row.find('.variation-option').is($select)) {
                    return; // Skip current row
                }
                let otherVariationId = $row.find('.variation-option').val();
                if (otherVariationId == selectedVariationId) {
                    duplicateFound = true;
                    return false;
                }
            });
            
            if (duplicateFound) {
                showErrorNotification('You\'ve already selected "' + selectedVariationName + '" variation. Please select another.');
                $select.val('').trigger('change');
                return;
            }
        }
        
        // Destroy select2 if initialized
        if (isSelect2Initialized) {
            $valuesSelect.select2('destroy');
        }
        
        $valuesSelect.empty();
        
        if (selectedVariationId) {
            let selectedVariation = variationsData.find(v => v.id == selectedVariationId);
            if (selectedVariation && selectedVariation.variation_value) {
                selectedVariation.variation_value.forEach(function(value) {
                    let option = $('<option></option>')
                        .val(value)
                        .text(value);
                    $valuesSelect.append(option);
                });
            }
        }
        
        // Reinitialize select2
        $valuesSelect.select2({
            dropdownParent: $valuesSelect.closest('.card-body'),
            placeholder: 'Select Values',
            multiple: true
        });
        
        // Restore previous values if they exist and are valid
        if (currentValues.length > 0 && selectedVariationId) {
            let validValues = currentValues.filter(val => {
                let selectedVariation = variationsData.find(v => v.id == selectedVariationId);
                return selectedVariation && selectedVariation.variation_value && selectedVariation.variation_value.includes(val);
            });
            if (validValues.length > 0) {
                $valuesSelect.val(validValues).trigger('change');
            }
        }
    });

    // Add new variation row
    $(document).on('click', '#addVariationRow', function() {
        // Preserve existing selected values before adding new row
        let existingSelections = [];
        $('#variationAttributesBody tr').each(function() {
            let variationId = $(this).find('.variation-option').val();
            let values = $(this).find('.variation-values').val() || [];
            if (variationId) {
                existingSelections.push({
                    variationId: variationId,
                    values: values
                });
            }
        });
        
        let newRow = `
            <tr class="variation-row">
                <td>
                    <select class="form-select select2 variation-option" data-placeholder="Select Variation">
                        <option value="">Select Variation</option>
                    </select>
                </td>
                <td>
                    <select class="form-select select2 variation-values" multiple data-placeholder="Select Values" style="width: 100%;">
                    </select>
                </td>
                <td>
                    <button type="button" class="btn text-danger remove-variation-row">
                        <i class="icon-base ti tabler-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        $('#variationAttributesBody').append(newRow);
        
        // Initialize Select2 for the new row's selects
        let $newRow = $('#variationAttributesBody tr').last();
        let $variationOption = $newRow.find('.variation-option');
        let $variationValues = $newRow.find('.variation-values');
        
        // Initialize variation-option Select2
        $variationOption.select2({
            dropdownParent: $variationOption.closest('.card-body'),
            placeholder: 'Select Variation'
        });
        
        // Populate variation options
        $variationOption.empty().append('<option value="">Select Variation</option>');
        variationsData.forEach(function(variation) {
            let option = $('<option></option>')
                .val(variation.id)
                .text(variation.variation_name)
                .data('values', variation.variation_value);
            $variationOption.append(option);
        });
        
        // Initialize variation-values Select2 (empty for now, will be populated when option is selected)
        $variationValues.select2({
            dropdownParent: $variationValues.closest('.card-body'),
            placeholder: 'Select Values',
            multiple: true
        });
        
        // Restore existing selections
        setTimeout(function() {
            $('#variationAttributesBody tr').each(function(index) {
                if (existingSelections[index]) {
                    let selection = existingSelections[index];
                    let $row = $(this);
                    $row.find('.variation-option').val(selection.variationId).trigger('change');
                    setTimeout(function() {
                        $row.find('.variation-values').val(selection.values).trigger('change');
                    }, 100);
                }
            });
        }, 200);
    });

    // Remove variation row
    $(document).on('click', '.remove-variation-row', function() {
        if ($('#variationAttributesBody tr').length > 1) {
            $(this).closest('tr').remove();
            // Clear generated variations if any row is removed
            $('#generatedVariationsBody').empty();
            $('.generated_variations_section').slideUp();
        } else {
            showErrorNotification('At least one variation row is required');
        }
    });

    // Generate all combinations
    function generateCombinations(arrays) {
        if (arrays.length === 0) return [[]];
        if (arrays.length === 1) return arrays[0].map(v => [v]);
        
        let result = [];
        let firstArray = arrays[0];
        let restCombinations = generateCombinations(arrays.slice(1));
        
        firstArray.forEach(function(firstValue) {
            restCombinations.forEach(function(restCombination) {
                result.push([firstValue].concat(restCombination));
            });
        });
        
        return result;
    }

    // Generate variations
    $(document).on('click', '#generateVariations', function() {
        let variationRows = [];
        let hasError = false;
        
        $('#variationAttributesBody tr').each(function() {
            let variationOption = $(this).find('.variation-option').val();
            let variationValues = $(this).find('.variation-values').val();
            
            if (!variationOption) {
                hasError = true;
                $(this).find('.variation-option').addClass('is-invalid');
                return false;
            }
            
            if (!variationValues || variationValues.length === 0) {
                hasError = true;
                $(this).find('.variation-values').addClass('is-invalid');
                return false;
            }
            
            // Get variation name
            let variation = variationsData.find(v => v.id == variationOption);
            let variationName = variation ? variation.variation_name : '';
            
            variationRows.push({
                name: variationName,
                values: variationValues
            });
        });
        
        if (hasError) {
            showErrorNotification('Please select variation option and at least one value for each row');
            return;
        }
        
        // Store variation attributes data for variation_details
        variationAttributesData = [];
        $('#variationAttributesBody tr').each(function() {
            let variationOption = $(this).find('.variation-option').val();
            let variationValues = $(this).find('.variation-values').val();
            
            if (variationOption && variationValues && variationValues.length > 0) {
                let variation = variationsData.find(v => v.id == variationOption);
                let variationName = variation ? variation.variation_name : '';
                
                variationAttributesData.push({
                    variation_name: variationName,
                    attribute_id: variationOption,
                    child_row_attribute: variationValues
                });
            }
        });
        
        // Generate all combinations
        let valueArrays = variationRows.map(row => row.values);
        let combinations = generateCombinations(valueArrays);
        
        // Create variation names for each combination
        generatedVariations = combinations.map(function(combination, index) {
            let variationParts = [];
            combination.forEach(function(value, idx) {
                variationParts.push(variationRows[idx].name + ': ' + value);
            });
            
            let variationName = variationParts.join(' - ');
            let itemCode = $('#code').val() + '-' + (index + 1);
            
            return {
                variation_name: variationName,
                variation_combination: combination,
                variation_attributes: variationRows.map((row, idx) => ({
                    name: row.name,
                    value: combination[idx]
                })),
                item_code: itemCode,
                purchase_price: '',
                sale_price: '',
                whole_sale_price: '',
                mrp_price: '',
                alert_quantity: '',
                opening_stock: {}
            };
        });
        
        // Display generated variations
        displayGeneratedVariations();
        
        // Hide variation generator and show generated variations
        $('.variation_generator').slideUp();
        $('.generated_variations_section').slideDown();
    });

    // Display generated variations in row/column format
    function displayGeneratedVariations() {
        let container = $('#generatedVariationsBody');
        container.empty();
        
        // Re-index variations to ensure proper array indices
        generatedVariations.forEach(function(variation, index) {
            let variationCard = `
                <div class="col-12 col-md-6 col-lg-4 mb-4" data-variation-index="${index}">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">${variation.variation_name}</h6>
                            <button type="button" class="btn text-danger remove-variation-card" data-variation-index="${index}">
                                <i class="icon-base ti tabler-trash"></i>
                            </button>
                        </div>
                        <div class="card-body">
                            <input type="hidden" name="variations[${index}][variation_name]" value="${variation.variation_name}">
                            <input type="hidden" name="variations[${index}][variation_combination]" value='${JSON.stringify(variation.variation_combination)}'>
                            <input type="hidden" name="variations[${index}][variation_attributes]" value='${JSON.stringify(variation.variation_attributes)}'>
                            
                            <!-- Image Upload -->
                            <div class="mb-3">
                                <label class="form-label">Variation Image</label>
                                <input type="file" class="form-control variation-image-input" 
                                    data-variation-index="${index}" 
                                    name="variations[${index}][photo]" 
                                    accept="image/*">
                                <div class="mt-2 variation-image-preview-${index}">
                                    <img src="${base_url}/uploads/dummy_images/default-picture.png" 
                                        alt="Variation Image" 
                                        class="variation-image-preview" 
                                        style="border:1px dashed #d1d0d4; padding: 10px; border-radius: 5px; max-width: 100%; max-height: 200px; cursor: pointer;"
                                        data-variation-index="${index}">
                                    <button type="button" class="btn btn-sm btn-danger mt-2 remove-variation-image" 
                                        data-variation-index="${index}" 
                                        style="display: none;">
                                        <i class="ti tabler-trash"></i> Remove Image
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Item Code -->
                            <div class="mb-3 validate_wrapper">
                                <label class="form-label">Item Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control variation-item-code" 
                                    name="variations[${index}][item_code]" 
                                    value="${variation.item_code}" 
                                    required>
                            </div>
                            
                            <!-- Purchase Price -->
                            <div class="mb-3">
                                <label class="form-label">Purchase Price</label>
                                <input type="text" class="form-control number-input variation-purchase-price" 
                                    name="variations[${index}][purchase_price]" 
                                    value="${variation.purchase_price}">
                            </div>
                            
                            <!-- Sale Price -->
                            <div class="mb-3 validate_wrapper">
                                <label class="form-label">Sale Price <span class="text-danger">*</span></label>
                                <input type="text" class="form-control number-input variation-sale-price" 
                                    name="variations[${index}][sale_price]" 
                                    value="${variation.sale_price}" 
                                    required>
                            </div>
                            
                            <!-- MRP Price -->
                            <div class="mb-3">
                                <label class="form-label">MRP Price</label>
                                <input type="text" class="form-control number-input variation-mrp-price" 
                                    name="variations[${index}][mrp_price]" 
                                    value="${variation.mrp_price}">
                            </div>
                            
                            <!-- Whole Sale Price -->
                            <div class="mb-3">
                                <label class="form-label">Whole Sale Price</label>
                                <input type="text" class="form-control number-input variation-whole-sale-price" 
                                    name="variations[${index}][whole_sale_price]" 
                                    value="${variation.whole_sale_price}">
                            </div>
                            
                            <!-- Alert Quantity -->
                            <div class="mb-3">
                                <label class="form-label">Alert Quantity</label>
                                <input type="text" class="form-control number-input variation-alert-qty" 
                                    name="variations[${index}][alert_quantity]" 
                                    value="${variation.alert_quantity}">
                            </div>
                            
                            <!-- Opening Stock -->
                            <div class="mb-3">
                                <button type="button" class="btn btn-sm btn-primary set-opening-stock" data-variation-index="${index}">
                                    <i class="ti tabler-settings"></i>
                                    <span>Set Opening Stock</span>
                                </button>
                                <div class="opening-stock-display-${index} mt-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            container.append(variationCard);
        });
    }
    
    // Back to generate button handler
    $(document).on('click', '#backToGenerate', function() {
        $('.variation_generator').slideDown();
        $('.generated_variations_section').slideUp();
        // Clear variation attributes data when going back
        variationAttributesData = [];
    });
    
    // Variation image cropper instances
    let variationCropperInstances = {};
    let variationCropperModals = {};
    
    // Handle variation image preview and cropper
    $(document).on('change', '.variation-image-input', function() {
        let variationIndex = $(this).data('variation-index');
        let input = this;
        let preview = $(`.variation-image-preview-${variationIndex} img`);
        let removeBtn = $(`.remove-variation-image[data-variation-index="${variationIndex}"]`);
        
        if (input.files && input.files[0]) {
            let reader = new FileReader();
            reader.onload = function(e) {
                showVariationCropperModal(e.target.result, variationIndex);
            };
            reader.readAsDataURL(input.files[0]);
        }
    });
    
    // Show cropper modal for variation image
    function showVariationCropperModal(imageSrc, variationIndex) {
        // Remove existing modal if any
        $(`#variationCropperModal-${variationIndex}`).remove();
        
        // Destroy existing cropper instance
        if (variationCropperInstances[variationIndex]) {
            variationCropperInstances[variationIndex].destroy();
            variationCropperInstances[variationIndex] = null;
        }
        
        // Dispose existing modal
        if (variationCropperModals[variationIndex]) {
            variationCropperModals[variationIndex].dispose();
            variationCropperModals[variationIndex] = null;
        }
        
        // Create modal HTML
        let modalHtml = `
            <div class="modal fade" id="variationCropperModal-${variationIndex}" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Crop Variation Image</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="image-cropper-container" style="min-height: 400px;">
                                <img id="variationCropperImage-${variationIndex}" src="${imageSrc}" style="max-width: 100%; display: block;">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" id="cropVariationImageBtn-${variationIndex}">Crop & Save</button>
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        let modalElement = document.getElementById(`variationCropperModal-${variationIndex}`);
        variationCropperModals[variationIndex] = new bootstrap.Modal(modalElement, {
            backdrop: 'static',
            keyboard: false
        });
        
        $(modalElement).on('shown.bs.modal', function () {
            let cropperImage = document.getElementById(`variationCropperImage-${variationIndex}`);
            if (cropperImage && typeof Cropper !== 'undefined') {
                variationCropperInstances[variationIndex] = new Cropper(cropperImage, {
                    aspectRatio: 1,
                    viewMode: 1,
                    autoCropArea: 0.8,
                    responsive: true,
                    restore: false,
                    guides: true,
                    center: true,
                    highlight: false,
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                    toggleDragModeOnDblclick: false,
                });
            } else {
                console.error('Cropper image element not found or Cropper library not loaded');
            }
        });
        
        $(modalElement).on('click', `#cropVariationImageBtn-${variationIndex}`, function() {
            if (variationCropperInstances[variationIndex]) {
                let canvas = variationCropperInstances[variationIndex].getCroppedCanvas({
                    width: 400,
                    height: 400,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                });
                
                if (canvas) {
                    let croppedImageDataUrl = canvas.toDataURL('image/png');
                    let preview = $(`.variation-image-preview-${variationIndex} img`);
                    let removeBtn = $(`.remove-variation-image[data-variation-index="${variationIndex}"]`);
                    
                    preview.attr('src', croppedImageDataUrl);
                    removeBtn.show();
                    
                    // Store cropped image as base64 in a hidden input
                    $(`.variation-image-input[data-variation-index="${variationIndex}"]`).data('cropped-image', croppedImageDataUrl);
                    
                    // Update the file input with the cropped image
                    canvas.toBlob(function(blob) {
                        let file = new File([blob], `variation-${variationIndex}.png`, { type: 'image/png' });
                        let dataTransfer = new DataTransfer();
                        dataTransfer.items.add(file);
                        $(`.variation-image-input[data-variation-index="${variationIndex}"]`)[0].files = dataTransfer.files;
                    });
                }
                
                variationCropperModals[variationIndex].hide();
            }
        });
        
        $(modalElement).on('hidden.bs.modal', function () {
            if (variationCropperInstances[variationIndex]) {
                variationCropperInstances[variationIndex].destroy();
                variationCropperInstances[variationIndex] = null;
            }
            $(this).remove();
        });
        
        variationCropperModals[variationIndex].show();
    }
    
    // Click on preview image to crop
    $(document).on('click', '.variation-image-preview', function() {
        let variationIndex = $(this).data('variation-index');
        let currentSrc = $(this).attr('src');
        if (currentSrc && !currentSrc.includes('default-picture.png')) {
            showVariationCropperModal(currentSrc, variationIndex);
        }
    });
    
    // Remove variation image
    $(document).on('click', '.remove-variation-image', function() {
        let variationIndex = $(this).data('variation-index');
        $(`.variation-image-input[data-variation-index="${variationIndex}"]`).val('');
        $(`.variation-image-preview-${variationIndex} img`).attr('src', base_url + '/public/uploads/dummy_images/default-picture.png');
        $(this).hide();
    });

    // Remove variation card
    $(document).on('click', '.remove-variation-card', function() {
        let variationIndex = $(this).data('variation-index');
        
        if (generatedVariations.length <= 1) {
            showErrorNotification('At least one variation is required');
            return;
        }
        
        // Remove from array
        generatedVariations.splice(variationIndex, 1);
        
        // Re-render all variations with updated indices
        displayGeneratedVariations();
        
        // Update variation indices for opening stock handlers
        updateVariationIndices();
    });
    
    // Update variation indices after removal
    function updateVariationIndices() {
        $('#generatedVariationsBody .col-12').each(function(newIndex) {
            $(this).attr('data-variation-index', newIndex);
            $(this).find('[data-variation-index]').attr('data-variation-index', newIndex);
            $(this).find('[name*="variations["]').each(function() {
                let name = $(this).attr('name');
                if (name) {
                    name = name.replace(/variations\[\d+\]/, `variations[${newIndex}]`);
                    $(this).attr('name', name);
                }
            });
            // Update preview class
            $(this).find('.variation-image-preview').removeClass(function(index, className) {
                return (className.match(/(^|\s)variation-image-preview-\d+/g) || []).join(' ');
            }).addClass(`variation-image-preview-${newIndex}`);
            $(this).find('.variation-image-preview').attr('data-variation-index', newIndex);
            $(this).find('.variation-image-input').attr('data-variation-index', newIndex);
            $(this).find('.remove-variation-image').attr('data-variation-index', newIndex);
            $(this).find('.set-opening-stock').attr('data-variation-index', newIndex);
            $(this).find('.save-opening-stock').attr('data-variation-index', newIndex);
            $(this).find('.cancel-opening-stock').attr('data-variation-index', newIndex);
            $(this).find('.variation-opening-stock-input').attr('data-variation-index', newIndex);
            $(this).find('.opening-stock-display').removeClass(function(index, className) {
                return (className.match(/(^|\s)opening-stock-display-\d+/g) || []).join(' ');
            }).addClass(`opening-stock-display-${newIndex}`);
        });
    }

    // Set opening stock for variation
    $(document).on('click', '.set-opening-stock', function() {
        let variationIndex = $(this).data('variation-index');
        let variation = generatedVariations[variationIndex];
        let currentStock = variation.opening_stock || {};
        let stockInputs = '';
        
        // Use outletsData from window (passed from blade)
        if (typeof window.outletsData !== 'undefined' && window.outletsData.length > 0) {
            window.outletsData.forEach(function(outlet) {
                stockInputs += `
                    <div class="mb-3">
                        <label class="form-label">${outlet.outlet_name}</label>
                        <input type="text" class="form-control variation-opening-stock-input" 
                            data-outlet-id="${outlet.id}" 
                            data-variation-index="${variationIndex}"
                            value="${currentStock[outlet.id] || ''}"
                            placeholder="Enter Quantity">
                    </div>
                `;
            });
        }
        
        // For simplicity, we'll use inline editing
        let displayDiv = $(`.opening-stock-display-${variationIndex}`);
        if (displayDiv.find('.opening-stock-edit').length > 0) {
            displayDiv.empty();
            $(this).html('<i class="ti tabler-settings"></i> Set Opening Stock');
        } else {
            displayDiv.html(`
                <div class="opening-stock-edit">
                    ${stockInputs}
                    <button type="button" class="btn btn-sm btn-success save-opening-stock" data-variation-index="${variationIndex}">
                        <i class="ti tabler-check"></i> Save
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary cancel-opening-stock" data-variation-index="${variationIndex}">
                        <i class="ti tabler-x"></i> Cancel
                    </button>
                </div>
            `);
            $(this).html('<i class="ti tabler-edit"></i> Edit Opening Stock');
        }
    });

    // Save opening stock for variation
    $(document).on('click', '.save-opening-stock', function() {
        let variationIndex = $(this).data('variation-index');
        let openingStock = {};
        
        // Initialize with all outlets (set to 0 if not provided)
        if (typeof window.outletsData !== 'undefined' && window.outletsData.length > 0) {
            window.outletsData.forEach(function(outlet) {
                openingStock[outlet.id] = 0; // Default to 0
            });
        }
        
        // Update with actual values
        $(`.variation-opening-stock-input[data-variation-index="${variationIndex}"]`).each(function() {
            let outletId = $(this).data('outlet-id');
            let quantity = $(this).val() || 0;
            openingStock[outletId] = parseFloat(quantity) || 0;
        });
        
        generatedVariations[variationIndex].opening_stock = openingStock;
        
        // Create hidden inputs for each outlet (Laravel expects array format)
        let hiddenInputs = '';
        let stockSummary = [];
        
        if (typeof window.outletsData !== 'undefined' && window.outletsData.length > 0) {
            window.outletsData.forEach(function(outlet) {
                let quantity = openingStock[outlet.id] || 0;
                hiddenInputs += `<input type="hidden" name="variations[${variationIndex}][opening_stock][${outlet.id}]" value="${quantity}">`;
                if (quantity > 0) {
                    stockSummary.push(outlet.outlet_name + ': ' + quantity);
                } else {
                    stockSummary.push(outlet.outlet_name + ': 0');
                }
            });
        }
        
        $(`.opening-stock-display-${variationIndex}`).html(`
            <small class="text-muted">${stockSummary.join(', ') || 'No stock set'}</small>
            ${hiddenInputs}
        `);
        
        $(`.set-opening-stock[data-variation-index="${variationIndex}"]`).html('<i class="ti tabler-settings"></i> Set Opening Stock');
    });

    // Cancel opening stock editing
    $(document).on('click', '.cancel-opening-stock', function() {
        let variationIndex = $(this).data('variation-index');
        $(`.opening-stock-display-${variationIndex}`).empty();
        $(`.set-opening-stock[data-variation-index="${variationIndex}"]`).html('<i class="ti tabler-settings"></i> Set Opening Stock');
    });

    // Fetch variations when page loads
    fetchVariations();
    
    // Initialize Select2 for initial variation row on page load
    $(document).ready(function() {
        // Wait a bit for Select2 to be available and variations to be loaded
        setTimeout(function() {
            $('.variation-values').each(function() {
                let $select = $(this);
                if (!$select.hasClass('select2-hidden-accessible')) {
                    $select.select2({
                        dropdownParent: $select.closest('.card-body'),
                        placeholder: 'Select Values',
                        multiple: true
                    });
                }
            });
        }, 500);
    });


    // Function to validate required fields
    function validateRequiredFields() {
        let hasError = false;
        
        // Clear previous errors
        clearValidationErrors();
        
        // Validate Item Type
        let itemType = $('#type').val();
        if (!itemType || itemType === '' || itemType === null) {
            $('#type').addClass('is-invalid');
            let typeWrapper = $('#type').closest('.validate_wrapper');
            if (typeWrapper.find('.invalid-feedback').length === 0) {
                typeWrapper.append('<div class="invalid-feedback">Item Type is required</div>');
            }
            hasError = true;
        } else {
            $('#type').removeClass('is-invalid');
            $('#type').closest('.validate_wrapper').find('.invalid-feedback').remove();
        }
        
        // Validate Item Name
        let itemName = $('#name').val();
        if (!itemName || itemName.trim() === '') {
            $('#name').addClass('is-invalid');
            let nameWrapper = $('#name').closest('.validate_wrapper');
            if (nameWrapper.find('.invalid-feedback').length === 0) {
                nameWrapper.append('<div class="invalid-feedback">' + language_key['Item Name is required'] + '</div>');
            }
            hasError = true;
        } else {
            $('#name').removeClass('is-invalid');
            $('#name').closest('.validate_wrapper').find('.invalid-feedback').remove();
        }
        
        // Validate Item Code
        let itemCode = $('#code').val();
        if (!itemCode || itemCode.trim() === '') {
            $('#code').addClass('is-invalid');
            let codeWrapper = $('#code').closest('.validate_wrapper');
            if (codeWrapper.find('.invalid-feedback').length === 0) {
                codeWrapper.append('<div class="invalid-feedback">Item Code is required</div>');
            }
            hasError = true;
        } else {
            $('#code').removeClass('is-invalid');
            $('#code').closest('.validate_wrapper').find('.invalid-feedback').remove();
        }
        
        // Validate Category
        let categoryId = $('#category_id').val();
        if (!categoryId || categoryId === '' || categoryId === null) {
            $('#category_id').addClass('is-invalid');
            let categoryWrapper = $('#category_id').closest('.validate_wrapper');
            if (categoryWrapper.find('.invalid-feedback').length === 0) {
                categoryWrapper.append('<div class="invalid-feedback">' + language_key['Category is required'] + '</div>');
            }
            hasError = true;
        } else {
            $('#category_id').removeClass('is-invalid');
            $('#category_id').closest('.validate_wrapper').find('.invalid-feedback').remove();
        }
        
        // Validate Sale Price (not required for Variation_Product, calculated for Combo_Product)
        if (itemType !== 'Variation_Product' && itemType !== 'Combo_Product') {
            let salePrice = $('#sale_price').val();
            // Clean the value by removing any formatting (commas, spaces, etc.) and parse it
            // This handles cases where the value might have formatting like "1,000" or "1 000"
            let cleanedSalePrice = salePrice ? salePrice.toString().trim().replace(/[^0-9.]/g, '') : '';
            let parsedSalePrice = cleanedSalePrice ? parseFloat(cleanedSalePrice) : NaN;
            
            if (!salePrice || salePrice.trim() === '' || isNaN(parsedSalePrice) || parsedSalePrice < 0) {
                $('#sale_price').addClass('is-invalid');
                let salePriceWrapper = $('#sale_price').closest('.validate_wrapper');
                if (salePriceWrapper.find('.invalid-feedback').length === 0) {
                    salePriceWrapper.append('<div class="invalid-feedback">' + language_key['Sale Price is required and must be a valid number'] + '</div>');
                }
                hasError = true;
            } else {
                $('#sale_price').removeClass('is-invalid');
                $('#sale_price').closest('.validate_wrapper').find('.invalid-feedback').remove();
            }
        }
        
        // Validate Purchase Price if opening stock exists
        if (itemType !== 'Service_Product' && itemType !== 'Combo_Product' && itemType !== 'Variation_Product') {
            // Check if opening stock exists
            let hasOpeningStock = false;
            if (Object.keys(openingStockData).length > 0) {
                // Check if any outlet has stock
                Object.keys(openingStockData).forEach(function(outletId) {
                    let outletData = openingStockData[outletId];
                    if (outletData) {
                        if (itemType === 'General_Product' || itemType === 'Installment_Product') {
                            if (outletData.quantity && parseFloat(outletData.quantity) > 0) {
                                hasOpeningStock = true;
                            }
                        } else if (itemType === 'IMEI_Product' || itemType === 'Serial_Product') {
                            if (outletData.items && outletData.items.length > 0) {
                                hasOpeningStock = true;
                            }
                        } else if (itemType === 'Medicine_Product') {
                            if (outletData.items && outletData.items.length > 0) {
                                hasOpeningStock = true;
                            }
                        }
                    }
                });
            }
            
            if (hasOpeningStock) {
                let purchasePrice = $('#purchase_price').val();
                let cleanedPurchasePrice = purchasePrice ? purchasePrice.toString().trim().replace(/[^0-9.]/g, '') : '';
                let parsedPurchasePrice = cleanedPurchasePrice ? parseFloat(cleanedPurchasePrice) : NaN;
                
                if (!purchasePrice || purchasePrice.trim() === '' || isNaN(parsedPurchasePrice) || parsedPurchasePrice < 0) {
                    $('#purchase_price').addClass('is-invalid');
                    let purchasePriceWrapper = $('#purchase_price').closest('.validate_wrapper');
                    if (purchasePriceWrapper.find('.invalid-feedback').length === 0) {
                        purchasePriceWrapper.append('<div class="invalid-feedback">Purchase Price is required when opening stock is set</div>');
                    }
                    hasError = true;
                } else {
                    $('#purchase_price').removeClass('is-invalid');
                    $('#purchase_price').closest('.validate_wrapper').find('.invalid-feedback').remove();
                }
            }
        }
        
        // For Combo_Product, validate that sale price is calculated (from combo items)
        if (itemType === 'Combo_Product') {
            // Sync combo items first to ensure sale price is up to date
            $('#comboItemsBody tr').each(function() {
                let itemId = $(this).data('item-id');
                let quantity = parseFloat($(this).find('.combo-quantity').val()) || 0;
                let amount = parseFloat($(this).find('.combo-amount').val()) || 0;
                
                let comboItem = comboItems.find(item => item.item_id == itemId);
                if (comboItem) {
                    comboItem.quantity = quantity;
                    comboItem.amount = amount;
                    comboItem.total = quantity * amount;
                }
            });
            
            // Update combo sale price
            updateComboSalePrice();
            
            let comboSalePrice = parseFloat($('#combo_sale_price').val()) || 0;
            if (comboSalePrice <= 0 || comboItems.length === 0) {
                $('#combo_sale_price').addClass('is-invalid');
                let wrapper = $('#combo_sale_price').closest('.mb-3');
                if (wrapper.find('.invalid-feedback').length === 0) {
                    wrapper.append('<div class="invalid-feedback">Please add at least one item to the combo</div>');
                }
                showErrorNotification('Please add at least one item to the combo');
                hasError = true;
            } else {
                $('#combo_sale_price').removeClass('is-invalid');
                $('#combo_sale_price').closest('.mb-3').find('.invalid-feedback').remove();
            }
        }
        
        // Validate Unit Type (not required for Service_Product and Combo_Product)
        if (itemType !== 'Service_Product' && itemType !== 'Combo_Product') {
            // Ensure unit section is visible before reading/validating (in case it was hidden)
            var $unitCardBody = $('#unit_type').closest('.card-body');
            if ($unitCardBody.length) {
                $unitCardBody.show();
                $unitCardBody.prev('.card-header').show();
            }
            
            // Read unit_type from native select (reliable with Select2)
            var unitTypeSelect = document.getElementById('unit_type');
            var unitType = unitTypeSelect ? (unitTypeSelect.value || '').toString().trim() : ($('#unit_type').val() || '').toString().trim();
            
            if (!unitType || unitType === '' || unitType === null) {
                $('#unit_type').addClass('is-invalid');
                $('#unit_type').next('.select2-container').addClass('is-invalid');
                let unitTypeWrapper = $('#unit_type').closest('.validate_wrapper');
                if (unitTypeWrapper.find('.invalid-feedback').length === 0) {
                    unitTypeWrapper.append('<div class="invalid-feedback">Unit Type is required</div>');
                }
                hasError = true;
            } else {
                $('#unit_type').removeClass('is-invalid');
                $('#unit_type').next('.select2-container').removeClass('is-invalid');
                $('#unit_type').closest('.validate_wrapper').find('.invalid-feedback').remove();
                
                // Double unit = "2" (string from select)
                var isDoubleUnit = (unitType === '2' || unitType === 2);
                
                if (!isDoubleUnit) {
                    // Single unit: validate sale unit only
                    let saleUnitId = $('#sale_unit_id').val();
                    if (!saleUnitId || saleUnitId === '' || saleUnitId === null) {
                        $('#sale_unit_id').addClass('is-invalid');
                        $('#sale_unit_id').next('.select2-container').addClass('is-invalid');
                        let saleUnitWrapper = $('#sale_unit_id').closest('.validate_wrapper');
                        if (saleUnitWrapper.find('.invalid-feedback').length === 0) {
                            saleUnitWrapper.append('<div class="invalid-feedback">' + (language_key['Unit is required'] || 'Unit is required') + '</div>');
                        }
                        hasError = true;
                    } else {
                        $('#sale_unit_id').removeClass('is-invalid');
                        $('#sale_unit_id').next('.select2-container').removeClass('is-invalid');
                        $('#sale_unit_id').closest('.validate_wrapper').find('.invalid-feedback').remove();
                    }
                } else {
                    // Double unit: show fields and validate all three
                    $('.purchase_unit_wrap, .conversion_rate_wrap').show();
                    
                    let purchaseUnitId = ($('#purchase_unit_id').val() || '').toString().trim();
                    let saleUnitId = ($('#sale_unit_id').val() || '').toString().trim();
                    let conversionRate = ($('#conversion_rate').val() || '').toString().trim();
                    let conversionRateNum = conversionRate === '' ? NaN : parseFloat(conversionRate);
                    
                    if (!purchaseUnitId) {
                        $('#purchase_unit_id').addClass('is-invalid');
                        $('#purchase_unit_id').next('.select2-container').addClass('is-invalid');
                        let purchaseUnitWrapper = $('#purchase_unit_id').closest('.validate_wrapper');
                        if (purchaseUnitWrapper.find('.invalid-feedback').length === 0) {
                            purchaseUnitWrapper.append('<div class="invalid-feedback">' + (language_key['Purchase Unit is required'] || 'Purchase Unit is required') + '</div>');
                        }
                        hasError = true;
                    } else {
                        $('#purchase_unit_id').removeClass('is-invalid');
                        $('#purchase_unit_id').next('.select2-container').removeClass('is-invalid');
                        $('#purchase_unit_id').closest('.validate_wrapper').find('.invalid-feedback').remove();
                    }
                    
                    if (!saleUnitId) {
                        $('#sale_unit_id').addClass('is-invalid');
                        $('#sale_unit_id').next('.select2-container').addClass('is-invalid');
                        let saleUnitWrapper = $('#sale_unit_id').closest('.validate_wrapper');
                        if (saleUnitWrapper.find('.invalid-feedback').length === 0) {
                            saleUnitWrapper.append('<div class="invalid-feedback">' + (language_key['Sale Unit is required'] || 'Sale Unit is required') + '</div>');
                        }
                        hasError = true;
                    } else {
                        $('#sale_unit_id').removeClass('is-invalid');
                        $('#sale_unit_id').next('.select2-container').removeClass('is-invalid');
                        $('#sale_unit_id').closest('.validate_wrapper').find('.invalid-feedback').remove();
                    }
                    
                    if (conversionRate === '' || isNaN(conversionRateNum) || conversionRateNum <= 0) {
                        $('#conversion_rate').addClass('is-invalid');
                        let conversionWrapper = $('#conversion_rate').closest('.validate_wrapper');
                        if (conversionWrapper.find('.invalid-feedback').length === 0) {
                            conversionWrapper.append('<div class="invalid-feedback">' + (language_key['Conversion Rate is required and must be greater than 0'] || 'Conversion Rate is required and must be greater than 0') + '</div>');
                        }
                        hasError = true;
                    } else {
                        $('#conversion_rate').removeClass('is-invalid');
                        $('#conversion_rate').closest('.validate_wrapper').find('.invalid-feedback').remove();
                    }
                }
            }
        }
        
        // Item Image is optional, no validation needed
        
        // Validate variations if Variation_Product
        if (itemType === 'Variation_Product') {
            if (!generatedVariations || generatedVariations.length === 0) {
                showErrorNotification('Please generate at least one variation');
                hasError = true;
            } else {
                // Validate each variation
                $('#generatedVariationsBody .col-12').each(function(index) {
                    let variationItemCode = $(this).find('.variation-item-code').val();
                    let variationSalePrice = $(this).find('.variation-sale-price').val();
                    let variationPurchasePrice = $(this).find('.variation-purchase-price').val();
                    
                    if (!variationItemCode || variationItemCode.trim() === '') {
                        $(this).find('.variation-item-code').addClass('is-invalid');
                        $(this).find('.variation-item-code').closest('.validate_wrapper').append('<div class="invalid-feedback">Item Code is required</div>');
                        hasError = true;
                    } else {
                        $(this).find('.variation-item-code').removeClass('is-invalid');
                        $(this).find('.variation-item-code').closest('.validate_wrapper').find('.invalid-feedback').remove();
                    }
                    
                    if (!variationSalePrice || variationSalePrice.trim() === '' || isNaN(variationSalePrice) || parseFloat(variationSalePrice) < 0) {
                        $(this).find('.variation-sale-price').addClass('is-invalid');
                        $(this).find('.variation-sale-price').closest('.validate_wrapper').append('<div class="invalid-feedback">' + language_key['Sale Price is required and must be a valid number'] + '</div>');
                        hasError = true;
                    } else {
                        $(this).find('.variation-sale-price').removeClass('is-invalid');
                        $(this).find('.variation-sale-price').closest('.validate_wrapper').find('.invalid-feedback').remove();
                    }
                    
                    // Check if opening stock exists for this variation
                    let variation = generatedVariations[index];
                    let hasOpeningStock = false;
                    if (variation && variation.opening_stock) {
                        Object.keys(variation.opening_stock).forEach(function(outletId) {
                            if (variation.opening_stock[outletId] > 0) {
                                hasOpeningStock = true;
                            }
                        });
                    }
                    
                    // If opening stock exists, purchase_price is required
                    if (hasOpeningStock) {
                        if (!variationPurchasePrice || variationPurchasePrice.trim() === '' || isNaN(variationPurchasePrice) || parseFloat(variationPurchasePrice) < 0) {
                            $(this).find('.variation-purchase-price').addClass('is-invalid');
                            $(this).find('.variation-purchase-price').closest('.mb-3').append('<div class="invalid-feedback">Purchase Price is required when opening stock is set</div>');
                            hasError = true;
                        } else {
                            $(this).find('.variation-purchase-price').removeClass('is-invalid');
                            $(this).find('.variation-purchase-price').closest('.mb-3').find('.invalid-feedback').remove();
                        }
                    }
                });
            }
        }
        
        // Validate combo items if Combo_Product
        if (itemType === 'Combo_Product') {
            // First, sync DOM values to comboItems array before validation
            $('#comboItemsBody tr').each(function() {
                let itemId = $(this).data('item-id');
                let quantityInput = $(this).find('.combo-quantity');
                let amountInput = $(this).find('.combo-amount');
                
                let quantity = parseFloat(quantityInput.val()) || 0;
                let amount = parseFloat(amountInput.val()) || 0;
                
                // Update combo item in array
                let comboItem = comboItems.find(item => item.item_id == itemId);
                if (comboItem) {
                    comboItem.quantity = quantity;
                    comboItem.amount = amount;
                    comboItem.total = quantity * amount;
                }
            });
            
            if (!comboItems || comboItems.length === 0) {
                showErrorNotification('Please add at least one item to the combo');
                hasError = true;
            } else {
                // Validate each combo item from both array and DOM
                let invalidItems = [];
                $('#comboItemsBody tr').each(function(index) {
                    let itemId = $(this).data('item-id');
                    let quantityInput = $(this).find('.combo-quantity');
                    let amountInput = $(this).find('.combo-amount');
                    
                    let quantity = parseFloat(quantityInput.val());
                    let amount = parseFloat(amountInput.val());
                    
                    let itemError = false;
                    
                    // Validate quantity
                    if (isNaN(quantity) || quantity <= 0) {
                        quantityInput.addClass('is-invalid');
                        let wrapper = quantityInput.closest('td');
                        if (wrapper.find('.invalid-feedback').length === 0) {
                            wrapper.append('<div class="invalid-feedback">Quantity must be greater than 0</div>');
                        }
                        itemError = true;
                    } else {
                        quantityInput.removeClass('is-invalid');
                        quantityInput.closest('td').find('.invalid-feedback').remove();
                    }
                    
                    // Validate amount
                    if (isNaN(amount) || amount < 0) {
                        amountInput.addClass('is-invalid');
                        let wrapper = amountInput.closest('td');
                        if (wrapper.find('.invalid-feedback').length === 0) {
                            wrapper.append('<div class="invalid-feedback">Amount must be 0 or greater</div>');
                        }
                        itemError = true;
                    } else {
                        amountInput.removeClass('is-invalid');
                        amountInput.closest('td').find('.invalid-feedback').remove();
                    }
                    
                    if (itemError) {
                        invalidItems.push(index + 1);
                        hasError = true;
                    }
                });
                
                if (hasError && invalidItems.length > 0) {
                    showErrorNotification('Please ensure all combo items have valid quantity and amount. Check row(s): ' + invalidItems.join(', '));
                }
            }
        }
        
        return !hasError;
    }

    // Validate form using AJAX before submission
    $(document).on('submit', '#itemForm', function (e) {
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
        
        // Validate via AJAX (server-side validation preview)
        // Use the form's own action URL so edit form uses update route, create form uses store route
        const formAction = $('#itemForm').attr('action');
        const url_store = formAction ? formAction : (base_url + route('item.store', [], false, Ziggy));
        let formData = new FormData($('#itemForm')[0]);
        
        // Add variation_details if Variation_Product
        if ($('#type').val() === 'Variation_Product' && variationAttributesData.length > 0) {
            // Format variation_details as array of JSON strings
            let variationDetailsArray = variationAttributesData.map(function(attr) {
                let detailObj = {
                    variation_name: attr.variation_name,
                    attribute_id: attr.attribute_id.toString(),
                    child_row_attribute: JSON.stringify(attr.child_row_attribute)
                };
                return JSON.stringify(detailObj);
            });
            formData.append('variation_details', JSON.stringify(variationDetailsArray));
        }
        
        // Add combo items data if Combo_Product
        if ($('#type').val() === 'Combo_Product') {
            // Sync DOM values to comboItems array before submission
            $('#comboItemsBody tr').each(function() {
                let itemId = $(this).data('item-id');
                let quantity = parseFloat($(this).find('.combo-quantity').val()) || 0;
                let amount = parseFloat($(this).find('.combo-amount').val()) || 0;
                let showInInvoice = $(this).find('.combo-show-invoice').is(':checked');
                
                let comboItem = comboItems.find(item => item.item_id == itemId);
                if (comboItem) {
                    comboItem.quantity = quantity;
                    comboItem.amount = amount;
                    comboItem.total = quantity * amount;
                    comboItem.show_in_invoice = showInInvoice;
                }
            });
            
            // Update combo sale price
            updateComboSalePrice();
            
            comboItems.forEach(function(comboItem, index) {
                formData.append(`combo_items[${index}][item_id]`, comboItem.item_id);
                formData.append(`combo_items[${index}][quantity]`, comboItem.quantity);
                formData.append(`combo_items[${index}][amount]`, comboItem.amount);
                formData.append(`combo_items[${index}][total]`, comboItem.total);
                formData.append(`combo_items[${index}][show_in_invoice]`, comboItem.show_in_invoice ? 1 : 0);
            });
        }
        
        $.ajax({
            type: "POST",
            url: url_store,
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            contentType: false,
            processData: false,
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    // Validation passed, submit form normally (remove event handler temporarily)
                    // $('#itemForm').off('submit');
                    // $('#itemForm')[0].submit();
                    // redirect to item index page
                    // window.location.href = base_url + '/items';
                    // showSuccessNotification(response.message || 'Item created successfully');

                    window.location.href = base_url + '/item?status=success';
                } else {
                    showErrorNotification(response.message || 'Something went wrong');
                }
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        showValidationErrors('#itemForm', xhr.responseJSON.errors);
                        showErrorNotification('Please check the form for errors');
                        setTimeout(function() {
                            let firstError = $('.is-invalid').first();
                            if (firstError.length > 0) {
                                let scrollTarget = firstError.hasClass('select2-hidden-accessible') ? firstError.next('.select2-container') : firstError;
                                if (scrollTarget.length > 0) {
                                    $('html, body').animate({ scrollTop: scrollTarget.offset().top - 100 }, 500);
                                }
                            }
                        }, 100);
                    } else {
                        let msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Validation failed. Please check the form.';
                        showErrorNotification(msg);
                    }
                } else {
                    let msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'An unexpected error occurred';
                    showErrorNotification(msg);
                }
            }
        });
        
        return false;
    });

    // Item Image Preview and Cropper
    let itemCropperInstance = null;
    let itemCropperModal = null;

    $(document).on('change', '#photo', function (e) {
        let input = this;
        if (input.files && input.files[0]) {
            let reader = new FileReader();
            reader.onload = function(e) {
                showItemCropperModal(e.target.result);
            };
            reader.readAsDataURL(input.files[0]);
        }
    });

    // Show cropper modal for item image
    function showItemCropperModal(imageSrc) {
        // Remove existing modal if any
        $('#itemCropperModal').remove();
        
        // Destroy existing cropper instance
        if (itemCropperInstance) {
            itemCropperInstance.destroy();
            itemCropperInstance = null;
        }
        
        // Dispose existing modal
        if (itemCropperModal) {
            itemCropperModal.dispose();
            itemCropperModal = null;
        }
        
        // Create modal HTML
        let modalHtml = `
            <div class="modal fade" id="itemCropperModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Crop Item Image</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="image-cropper-container" style="min-height: 400px;">
                                <img id="itemCropperImage" src="${imageSrc}" style="max-width: 100%; display: block;">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" id="cropItemImageBtn">Crop & Save</button>
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        let modalElement = document.getElementById('itemCropperModal');
        itemCropperModal = new bootstrap.Modal(modalElement, {
            backdrop: 'static',
            keyboard: false
        });
        
        $(modalElement).on('shown.bs.modal', function () {
            let cropperImage = document.getElementById('itemCropperImage');
            if (cropperImage && typeof Cropper !== 'undefined') {
                itemCropperInstance = new Cropper(cropperImage, {
                    aspectRatio: 1,
                    viewMode: 1,
                    autoCropArea: 0.8,
                    responsive: true,
                    restore: false,
                    guides: true,
                    center: true,
                    highlight: false,
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                    toggleDragModeOnDblclick: false,
                });
            } else {
                console.error('Cropper image element not found or Cropper library not loaded');
            }
        });
        
        $(modalElement).on('click', '#cropItemImageBtn', function() {
            if (itemCropperInstance) {
                let canvas = itemCropperInstance.getCroppedCanvas({
                    width: 400,
                    height: 400,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                });
                
                if (canvas) {
                    let croppedImageDataUrl = canvas.toDataURL('image/png');
                    let preview = $('#photo_preview');
                    let removeBtn = $('.remove-image');
                    
                    preview.attr('src', croppedImageDataUrl);
                    removeBtn.show();
                    
                    // Update the file input with the cropped image
                    canvas.toBlob(function(blob) {
                        let file = new File([blob], 'item-image.png', { type: 'image/png' });
                        let dataTransfer = new DataTransfer();
                        dataTransfer.items.add(file);
                        $('#photo')[0].files = dataTransfer.files;
                    });
                }
                
                itemCropperModal.hide();
            }
        });
        
        $(modalElement).on('hidden.bs.modal', function () {
            if (itemCropperInstance) {
                itemCropperInstance.destroy();
                itemCropperInstance = null;
            }
            $(this).remove();
        });
        
        itemCropperModal.show();
    }

    // Click on preview image to crop
    $(document).on('click', '#photo_preview', function() {
        let currentSrc = $(this).attr('src');
        if (currentSrc && !currentSrc.includes('default-picture.png')) {
            showItemCropperModal(currentSrc);
        }
    });

    $(document).on('click', '.remove-image', function() {
        $('#photo').val('');
        $('#photo_preview').attr('src', base_url + '/public/uploads/dummy_images/default-picture.png');
        $(this).hide();
    });



    //  ################## Opening Stock ##################
    let allIMEISerialNumbers = []; // Track all IMEI/Serial numbers for duplicate validation
    let medicineStockData = {}; // Track medicine stock data per outlet for duplicate validation {outletId: [{quantity, expiry_date}]}
    let openingStockData = {}; // Store opening stock data for summary and editing
    
    // Initialize opening stock modal based on product type
    function initializeOpeningStockModal(itemType, existingData = null) {
        allIMEISerialNumbers = [];
        medicineStockData = {}; // Reset medicine stock data

        // Check if Medicine_Product has expiry_date_maintain
        let expiryDateMaintain = '';
        if (itemType === 'Medicine_Product') {
            expiryDateMaintain = $('#expiry_date_maintain').val() || 'Yes';
            if(expiryDateMaintain == 'Yes') {
                $('.grid-change').removeClass('col-12 col-md-6 mb-4').addClass('col-12 mb-4');
            } else {
                $('.grid-change').removeClass('col-12 mb-4').addClass('col-12 col-md-6 mb-4');
            }
        } else {
            $('.grid-change').removeClass('col-12 mb-4').addClass('col-12 col-md-6 mb-4');
        }

        $('.outlet-stock-body').each(function() {
            let outletId = $(this).data('outlet-id');
            let outletCard = $(this);
            let outletData = existingData && existingData[outletId] ? existingData[outletId] : null;


            if (itemType === 'General_Product' || itemType === 'Installment_Product' || (itemType === 'Medicine_Product' && expiryDateMaintain === 'No')) {
                // Simple quantity input for General and Installment products
                let quantity = outletData ? outletData.quantity : '';
                outletCard.html(`
                    <div class="mb-3">
                        <label class="form-label">${ language_key['Opening Stock Quantity'] }</label>
                        <input type="text" 
                            class="form-control opening-stock-quantity" 
                            data-outlet-id="${outletId}"
                            name="opening_stock[${outletId}]"
                            value="${quantity}"
                            placeholder="${ language_key['Enter Quantity'] }" />
                    </div>
                `);
            } else if (itemType === 'IMEI_Product' || itemType === 'Serial_Product') {
                // Card system for IMEI/Serial products
                let fieldLabel = itemType === 'IMEI_Product' ? 'IMEI' : 'Serial';
                outletCard.html(`
                    <div class="imei-serial-fields-container" data-outlet-id="${outletId}">
                        <div class="mb-2">
                            <label class="form-label">${fieldLabel} Number</label>
                            <div class="input-group mb-2">
                                <input type="text" 
                                    class="form-control imei-serial-input" 
                                    data-outlet-id="${outletId}"
                                    placeholder="Enter ${fieldLabel} Number"
                                    autocomplete="off" />
                                <button type="button" class="btn btn-outline-primary add-imei-serial-field" data-outlet-id="${outletId}">
                                    <i class="ti tabler-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="imei-serial-list" data-outlet-id="${outletId}"></div>
                    </div>
                `);
                
                // Load existing IMEI/Serial data if available
                if (outletData && outletData.items && outletData.items.length > 0) {
                    outletData.items.forEach(function(item) {
                        addIMEISerialField(outletId, item, fieldLabel);
                    });
                }
            } else if (itemType === 'Medicine_Product' && expiryDateMaintain === 'Yes') {
                // Medicine product - similar to IMEI/Serial but with Quantity + MM/YY + Expiry Date
                outletCard.html(`
                    <div class="medicine-fields-container" data-outlet-id="${outletId}">
                        <div class="mb-2">
                            <div class="d-flex gap-2">
                                <div class="d-flex justify-content-between gap-2 w-100">
                                    <div class="mb-3 w-100">
                                        <label class="form-label">Quantity & Expiry Date</label>
                                        <input type="text" 
                                            class="form-control medicine-quantity-input number-input" 
                                            data-outlet-id="${outletId}"
                                            placeholder="Quantity"
                                            min="1"
                                            autocomplete="off" />
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">MM/YY</label>
                                        <input type="text" 
                                            class="form-control medicine-mmyy-input" 
                                            data-outlet-id="${outletId}"
                                            placeholder="MM/YY"
                                            maxlength="5"
                                            autocomplete="off" />
                                    </div>
                                    <div class="mb-3 w-100">
                                        <label class="form-label">Expiry Date</label>
                                        <input type="text" 
                                            class="form-control medicine-expiry-input datePickerJs" 
                                            data-outlet-id="${outletId}"
                                            placeholder="Expiry Date"
                                            autocomplete="off" />
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <button type="button" class="btn btn-outline-primary add-medicine-field" data-outlet-id="${outletId}" style="margin-top: 24px;">
                                        <i class="ti tabler-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="medicine-list" data-outlet-id="${outletId}"></div>
                    </div>
                `);
                
                // Initialize date picker for this outlet
                setTimeout(function() {
                    let expiryInput = $(`.medicine-expiry-input[data-outlet-id="${outletId}"]`);
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
                
                // Initialize medicine stock data for this outlet
                if (!medicineStockData[outletId]) {
                    medicineStockData[outletId] = [];
                }
                
                // Load existing medicine data if available
                if (outletData && outletData.items && outletData.items.length > 0) {
                    outletData.items.forEach(function(item) {
                        addMedicineField(outletId, item.quantity, item.expiry_date);
                    });
                }
            }
        });
    }
    
    // Set Opening Stock button click
    $(document).on('click', '#setOpeningStockBtn', function () {
        let itemType = $('#type').val();
        // Load existing data if available (for editing)
        initializeOpeningStockModal(itemType, openingStockData);
        $('#modal_outlet').modal('show');
    });
    
    
    // Reset opening stock when expiry_date_maintain changes for Medicine_Product
    $(document).on('change', '#expiry_date_maintain', function() {
        let itemType = $('#type').val();
        if (itemType === 'Medicine_Product') {
            // Check if opening stock is set
            if (openingStockData && Object.keys(openingStockData).length > 0) {
                Swal.fire({
                    title: 'Warning',
                    text: "You've set Opening stock, if you change it the opening stock will be gone.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Continue',
                    cancelButtonText: 'Cancel',
                    customClass: {
                        confirmButton: 'btn btn-primary',
                        cancelButton: 'btn btn-label-secondary'
                    },
                    buttonsStyling: false
                }).then((result) => {
                    if (result.value) {
                        // User confirmed, proceed with change
                        openingStockData = {};
                        $('#openingStockSummary').hide().empty();
                        $('#setOpeningStockBtn').html('<i class="ti tabler-settings me-1"></i> Set Opening Stock');
                    } else {
                        // User cancelled, revert the select
                        let previousValue = $(this).data('previous-value') || 'Yes';
                        $(this).val(previousValue).trigger('change');
                    }
                });
            }
        }
        
        // Store current value for potential revert
        $(this).data('previous-value', $(this).val());
    });
    
    // Add IMEI/Serial field on Enter key
    $(document).on('keypress', '.imei-serial-input', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            let outletId = $(this).data('outlet-id');
            let value = $(this).val().trim();
            let itemType = $('#type').val();
            let fieldLabel = itemType === 'IMEI_Product' ? 'IMEI' : 'Serial';
            
            if (value) {
                addIMEISerialField(outletId, value, fieldLabel);
                $(this).val('');
            }
        }
    });
    
    // Add IMEI/Serial field on Plus button click
    $(document).on('click', '.add-imei-serial-field', function() {
        let outletId = $(this).data('outlet-id');
        let input = $(`.imei-serial-input[data-outlet-id="${outletId}"]`);
        let value = input.val().trim();
        let itemType = $('#type').val();
        let fieldLabel = itemType === 'IMEI_Product' ? 'IMEI' : 'Serial';
        
        if (value) {
            addIMEISerialField(outletId, value, fieldLabel);
            input.val('').focus();
        } else {
            input.focus();
        }
    });
    
    // Function to add IMEI/Serial field
    function addIMEISerialField(outletId, value, fieldLabel) {
        // Check for duplicates
        if (allIMEISerialNumbers.includes(value)) {
            showErrorNotification(`${fieldLabel} number "${value}" already exists. Please enter a unique ${fieldLabel}.`);
            return;
        }
        
        allIMEISerialNumbers.push(value);
        let itemType = $('#type').val();
        let listContainer = $(`.imei-serial-list[data-outlet-id="${outletId}"]`);
        
        let fieldHtml = `
            <div class="input-group mb-2 imei-serial-item" data-value="${value}">
                <input type="text" 
                    class="form-control imei-serial-value" 
                    value="${value}"
                    data-outlet-id="${outletId}"
                    name="opening_stock[${outletId}][item_description][]"
                    readonly />
                <button type="button" class="btn btn-outline-danger remove-imei-serial-field" data-value="${value}">
                    <i class="ti tabler-trash"></i>
                </button>
            </div>
        `;
        
        listContainer.append(fieldHtml);
    }
    
    // Remove IMEI/Serial field
    $(document).on('click', '.remove-imei-serial-field', function() {
        let value = $(this).data('value');
        let index = allIMEISerialNumbers.indexOf(value);
        if (index > -1) {
            allIMEISerialNumbers.splice(index, 1);
        }
        $(this).closest('.imei-serial-item').remove();
    });

    // MM/YY input handler - Auto-formatting like bank card expiry
    $(document).on('input', '.medicine-mmyy-input', function() {
        let input = $(this);
        let value = input.val().replace(/\D/g, ''); // Remove non-digits
        let formattedValue = value;
        
        // Auto-add "/" after 2 digits (month)
        if (value.length >= 2) {
            formattedValue = value.substring(0, 2) + '/' + value.substring(2, 4);
        }
        
        input.val(formattedValue);
        
        // Validate month as user types (first 2 digits must be 01-12)
        if (value.length >= 2) {
            let month = parseInt(value.substring(0, 2), 10);
            if (month < 1 || month > 12) {
                input.addClass('is-invalid');
                input.next('.invalid-feedback').remove();
                input.after('<div class="invalid-feedback">Month must be between 01 and 12.</div>');
                return;
            }
        }
        input.removeClass('is-invalid');
        input.next('.invalid-feedback').remove();
        
        // If MM/YY is complete (MM/YY format), auto-set expiry date to last day of that month
        if (formattedValue.length === 5) {
            let parts = formattedValue.split('/');
            let month = parseInt(parts[0], 10);
            let year = parseInt(parts[1], 10);
            
            // Validate month (01-12) - show error if invalid
            if (month < 1 || month > 12) {
                input.addClass('is-invalid');
                input.next('.invalid-feedback').remove();
                input.after('<div class="invalid-feedback">Month must be between 01 and 12.</div>');
                return;
            }
            input.removeClass('is-invalid');
            input.next('.invalid-feedback').remove();
            
            // Convert 2-digit year to 4-digit (assume 2000-2099)
            let fullYear = year < 100 ? 2000 + year : year;
            
            // Get last day of the month
            let lastDay = new Date(fullYear, month, 0).getDate();
            let expiryDate = fullYear + '-' + String(month).padStart(2, '0') + '-' + String(lastDay).padStart(2, '0');
            
            // Set the expiry date field
            let outletId = input.data('outlet-id');
            let expiryInput = $(`.medicine-expiry-input[data-outlet-id="${outletId}"]`);
            
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
    });
    
    // MM/YY keypress handler - Allow only numbers and auto-format
    $(document).on('keypress', '.medicine-mmyy-input', function(e) {
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
    
    // Add Medicine field on Enter key (specifically for expiry date field, similar to IMEI system)
    $(document).on('keypress', '.medicine-expiry-input', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            let outletId = $(this).data('outlet-id');
            let quantityInput = $(`.medicine-quantity-input[data-outlet-id="${outletId}"]`);
            let expiryInput = $(this);
            let quantity = quantityInput.val().trim();
            let expiryDate = expiryInput.val().trim();
            
            if (quantity && expiryDate) {
                addMedicineField(outletId, quantity, expiryDate);
                quantityInput.val('');
                expiryInput.val('');
                $(`.medicine-mmyy-input[data-outlet-id="${outletId}"]`).val('');
                quantityInput.focus(); // Focus back on quantity for next entry
            } else {
                if (!quantity) {
                    quantityInput.focus();
                }
            }
        }
    });
    
    // Add Medicine field on Plus button click
    $(document).on('click', '.add-medicine-field', function() {
        let outletId = $(this).data('outlet-id');
        let quantityInput = $(`.medicine-quantity-input[data-outlet-id="${outletId}"]`);
        let expiryInput = $(`.medicine-expiry-input[data-outlet-id="${outletId}"]`);
        let mmyyInput = $(`.medicine-mmyy-input[data-outlet-id="${outletId}"]`);
        let quantity = quantityInput.val().trim();
        let expiryDate = expiryInput.val().trim();
        
        if (quantity && expiryDate) {
            addMedicineField(outletId, quantity, expiryDate);
            quantityInput.val('').focus();
            expiryInput.val('');
            mmyyInput.val('');
        } else {
            if (!quantity) {
                quantityInput.focus();
            } else {
                expiryInput.focus();
            }
        }
    });
    
    // Function to add Medicine field (Quantity + Expiry Date)
    function addMedicineField(outletId, quantity, expiryDate) {
        // Initialize outlet data if not exists
        if (!medicineStockData[outletId]) {
            medicineStockData[outletId] = [];
        }
        
        // Check for duplicate date within the same outlet (not exact duplicate)
        let duplicateDateFound = medicineStockData[outletId].some(function(item) {
            return item.expiry_date == expiryDate;
        });
        
        if (duplicateDateFound) {
            showErrorNotification('Please you\'ve added the date increase Quantity.');
            return;
        }
        
        // Check for exact duplicate (quantity + expiry_date) - this should not happen if date check passes, but keeping as safety
        let duplicateFound = medicineStockData[outletId].some(function(item) {
            return item.quantity == quantity && item.expiry_date == expiryDate;
        });
        
        if (duplicateFound) {
            showErrorNotification(`The combination of Quantity "${quantity}" and Expiry Date "${expiryDate}" already exists for this outlet. Please enter a unique combination.`);
            return;
        }
        
        // Add to tracking array
        medicineStockData[outletId].push({
            quantity: quantity,
            expiry_date: expiryDate
        });
        
        let listContainer = $(`.medicine-list[data-outlet-id="${outletId}"]`);
        
        let fieldHtml = `
            <div class="input-group mb-2 medicine-item" data-quantity="${quantity}" data-expiry="${expiryDate}">
                <input type="text" 
                    class="form-control medicine-value" 
                    value="${quantity} - ${expiryDate}"
                    data-outlet-id="${outletId}"
                    data-quantity="${quantity}"
                    data-expiry="${expiryDate}"
                    name="opening_stock[${outletId}][item_description][]"
                    readonly />
                <button type="button" class="btn btn-outline-danger remove-medicine-field" data-outlet-id="${outletId}" data-quantity="${quantity}" data-expiry="${expiryDate}">
                    <i class="ti tabler-trash"></i>
                </button>
            </div>
        `;
        
        listContainer.append(fieldHtml);
    }
    
    // Remove Medicine field
    $(document).on('click', '.remove-medicine-field', function() {
        let outletId = $(this).data('outlet-id');
        let quantity = $(this).data('quantity');
        let expiryDate = $(this).data('expiry');
        
        // Remove from tracking array
        if (medicineStockData[outletId]) {
            medicineStockData[outletId] = medicineStockData[outletId].filter(function(item) {
                return !(item.quantity == quantity && item.expiry_date == expiryDate);
            });
        }
        
        $(this).closest('.medicine-item').remove();
    });

    // Function to generate opening stock summary
    function generateOpeningStockSummary(itemType) {
        let summaryHtml = `<div class="alert alert-info"><strong>${ language_key['Opening Stock Summary'] }:</strong><ul class="mb-0 mt-2">`;
        let hasAnyStock = false;
        let expiryDateMaintain = '';
        if (itemType === 'Medicine_Product') {
            expiryDateMaintain = $('#expiry_date_maintain').val() || 'Yes';
        }
        
        if (itemType === 'General_Product' || itemType === 'Installment_Product' || (itemType === 'Medicine_Product' && expiryDateMaintain === 'No')) {
            Object.keys(openingStockData).forEach(function(outletId) {
                let outletData = openingStockData[outletId];
                if (outletData && outletData.quantity && parseFloat(outletData.quantity) > 0) {
                    let outletName = getOutletName(outletId);
                    summaryHtml += `<li>${outletName}: ${outletData.quantity}</li>`;
                    hasAnyStock = true;
                }
            });
        } else if (itemType === 'IMEI_Product' || itemType === 'Serial_Product') {
            let fieldLabel = itemType === 'IMEI_Product' ? 'IMEI' : 'Serial';
            Object.keys(openingStockData).forEach(function(outletId) {
                let outletData = openingStockData[outletId];
                if (outletData && outletData.items && outletData.items.length > 0) {
                    let outletName = getOutletName(outletId);
                    summaryHtml += `<li><strong>${outletName}:</strong> ${outletData.items.length} ${fieldLabel}(s)</li>`;
                    hasAnyStock = true;
                }
            });
        } else if (itemType === 'Medicine_Product' && expiryDateMaintain === 'Yes') {
            Object.keys(openingStockData).forEach(function(outletId) {
                let outletData = openingStockData[outletId];
                if (outletData && outletData.items && outletData.items.length > 0) {
                    let outletName = getOutletName(outletId);
                    let itemsList = outletData.items.map(function(item) {
                        return `${item.quantity} - ${item.expiry_date}`;
                    }).join(', ');
                    summaryHtml += `<li><strong>${outletName}:</strong> ${itemsList}</li>`;
                    hasAnyStock = true;
                }
            });
        }
        
        if (!hasAnyStock) {
            summaryHtml += '<li class="text-muted">No stock set</li>';
        }
        
        summaryHtml += '</ul></div>';
        return summaryHtml;
    }
    
    // Function to get outlet name
    function getOutletName(outletId) {
        if (typeof window.outletsData !== 'undefined' && window.outletsData.length > 0) {
            let outlet = window.outletsData.find(o => o.id == outletId);
            return outlet ? outlet.outlet_name : `Outlet ${outletId}`;
        }
        return `Outlet ${outletId}`;
    }
    
    // Opening Stock Modal Submit
    $(document).on('click', '.opening_stock_submit', function () {
        let itemType = $('#type').val();
        let expiryDateMaintain = '';
        if (itemType === 'Medicine_Product') {
            expiryDateMaintain = $('#expiry_date_maintain').val() || 'Yes';
        }
        $('.opening_stock_append').empty(); // Clear previous data
        let newOpeningStockData = {}; // Store new data temporarily
        
        if (itemType === 'General_Product' || itemType === 'Installment_Product' || (itemType === 'Medicine_Product' && expiryDateMaintain === 'No')) {
            // Simple quantity per outlet
            $('.opening-stock-quantity').each(function() {
                let outletId = $(this).data('outlet-id');
                let quantity = $(this).val().trim();
                
                if (quantity && !isNaN(quantity) && parseFloat(quantity) > 0) {
                    // Store data for summary and editing
                    newOpeningStockData[outletId] = {
                        quantity: quantity
                    };
                    
                    $('.opening_stock_append').append(`
                        <input type="hidden" name="outlet_id[]" value="${outletId}">
                        <input type="hidden" name="quantity[]" value="${quantity}">
                    `);
                }
            });
            openingStockData = newOpeningStockData; // Update stored data
            $('#modal_outlet').modal('hide');
            updateOpeningStockSummary(itemType);
        } else if (itemType === 'IMEI_Product' || itemType === 'Serial_Product') {
            // IMEI/Serial numbers per outlet
            let hasError = false;
            let fieldLabel = itemType === 'IMEI_Product' ? 'IMEI' : 'Serial';
            
            $('.imei-serial-list').each(function() {
                let outletId = $(this).data('outlet-id');
                let fields = $(this).find('.imei-serial-value');
                
                // IMEI/Serial: minimum 1 per outlet is not required - outlets can have 0 or no IMEI/Serial
                let items = [];
                fields.each(function() {
                    let value = $(this).val().trim();
                    if (value) {
                        items.push(value);
                        $('.opening_stock_append').append(`
                            <input type="hidden" name="outlet_id[]" value="${outletId}">
                            <input type="hidden" name="quantity[]" value="1">
                            <input type="hidden" name="item_description[]" value="${value}">
                        `);
                    }
                });
                
                // Store data for summary and editing
                if (items.length > 0) {
                    newOpeningStockData[outletId] = {
                        items: items
                    };
                }
            });
            
            if (!hasError) {
                openingStockData = newOpeningStockData; // Update stored data
                $('#modal_outlet').modal('hide');
                updateOpeningStockSummary(itemType);
            }
        } else if (itemType === 'Medicine_Product' && expiryDateMaintain === 'Yes') {
            // Medicine product - similar to IMEI/Serial but with Quantity + Expiry Date
            // Opening stock is optional, so no validation required
            
            $('.medicine-list').each(function() {
                let outletId = $(this).data('outlet-id');
                let fields = $(this).find('.medicine-value');
                
                let items = [];
                fields.each(function() {
                    let quantity = $(this).data('quantity');
                    let expiryDate = $(this).data('expiry');
                    if (quantity && expiryDate) {
                        items.push({
                            quantity: quantity,
                            expiry_date: expiryDate
                        });
                        $('.opening_stock_append').append(`
                            <input type="hidden" name="outlet_id[]" value="${outletId}">
                            <input type="hidden" name="quantity[]" value="${quantity}">
                            <input type="hidden" name="item_description[]" value="${expiryDate}">
                        `);
                    }
                });
                
                // Store data for summary and editing
                if (items.length > 0) {
                    newOpeningStockData[outletId] = {
                        items: items
                    };
                }
            });
            
            openingStockData = newOpeningStockData; // Update stored data
            $('#modal_outlet').modal('hide');
            updateOpeningStockSummary(itemType);
        }
    });
    
    // Function to update opening stock summary and button
    function updateOpeningStockSummary(itemType) {
        let summaryHtml = generateOpeningStockSummary(itemType);
        $('#openingStockSummary').html(summaryHtml).show();
        $('#setOpeningStockBtn').html(`<i class="ti tabler-edit me-1"></i> ${ language_key['Edit Opening Stock'] }`);
    }

    /** #################### -- Combo Product Code --  #################### **/
    
    let comboItems = []; // Store combo items data
    let comboItemCounter = 0; // Counter for SN

    // Fetch General_Product items for combo
    function fetchGeneralProductsForCombo() {
        $.ajax({
            type: "GET",
            url: base_url + "/get-general-products",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    updateComboItemSelect(response.data);
                }
            }
        });
    }

    // Update combo item select dropdown
    function updateComboItemSelect(items) {
        let select = $('#combo_item_select');
        select.empty();
        select.append(`<option value="">Select Item</option>`);
        items.forEach(function(item) {
            let option = $('<option></option>')
                .val(item.id)
                .text(item.name + ' - ' + item.code)
                .data('sale-price', item.sale_price || 0);
            select.append(option);
        });
        
        // Reinitialize select2 if it exists
        if (select.hasClass('select2-hidden-accessible')) {
            select.select2('destroy');
        }
        select.select2({
            dropdownParent: select.parent(),
            placeholder: 'Select Item'
        });
    }

    // Add item to combo when selected
    $(document).on('change', '#combo_item_select', function() {
        let itemId = $(this).val();
        if (!itemId) return;

        let selectedOption = $(this).find('option:selected');
        let itemName = selectedOption.text();
        let salePrice = parseFloat(selectedOption.data('sale-price')) || 0;

        // Check if item already exists in combo
        let existingItem = comboItems.find(item => item.item_id == itemId);
        if (existingItem) {
            showErrorNotification('This item is already added to the combo');
            $(this).val('').trigger('change');
            return;
        }

        // Add to combo items array
        comboItemCounter++;
        let comboItem = {
            sn: comboItemCounter,
            item_id: itemId,
            item_name: itemName,
            quantity: 1,
            amount: salePrice,
            total: salePrice,
            show_in_invoice: true
        };
        comboItems.push(comboItem);

        // Add to table
        addComboItemToTable(comboItem);

        // Clear selection
        $(this).val('').trigger('change');

        // Update sale price
        updateComboSalePrice();
    });

    // Add combo item to table
    function addComboItemToTable(comboItem) {
        let row = `
            <tr data-item-id="${comboItem.item_id}" data-sn="${comboItem.sn}">
                <td>${comboItem.sn}</td>
                <td>
                    <div class="form-check">
                        <input class="form-check-input combo-show-invoice" type="checkbox" 
                            data-item-id="${comboItem.item_id}" 
                            ${comboItem.show_in_invoice ? 'checked' : ''}>
                    </div>
                </td>
                <td>${comboItem.item_name}</td>
                <td>
                    <input type="text" class="form-control number-input combo-quantity" 
                        data-item-id="${comboItem.item_id}" 
                        value="${comboItem.quantity}">
                </td>
                <td>
                    <input type="text" class="form-control number-input combo-amount" 
                        data-item-id="${comboItem.item_id}" 
                        value="${comboItem.amount}">
                </td>
                <td>
                    <input type="text" class="form-control combo-total" 
                        data-item-id="${comboItem.item_id}" 
                        value="${comboItem.total.toFixed(2)}" 
                        readonly>
                </td>
                <td>
                    <button type="button" class="btn text-danger remove-combo-item" 
                        data-item-id="${comboItem.item_id}">
                        <i class="icon-base ti tabler-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        $('#comboItemsBody').append(row);
    }

    // Update quantity or amount
    $(document).on('input', '.combo-quantity, .combo-amount', function() {
        let itemId = $(this).data('item-id');
        let quantity = parseFloat($(this).closest('tr').find('.combo-quantity').val()) || 0;
        let amount = parseFloat($(this).closest('tr').find('.combo-amount').val()) || 0;
        let total = quantity * amount;

        // Update combo item in array
        let comboItem = comboItems.find(item => item.item_id == itemId);
        if (comboItem) {
            comboItem.quantity = quantity;
            comboItem.amount = amount;
            comboItem.total = total;
        }

        // Update total in table
        $(this).closest('tr').find('.combo-total').val(total.toFixed(2));

        // Update sale price
        updateComboSalePrice();
    });

    // Update show in invoice checkbox
    $(document).on('change', '.combo-show-invoice', function() {
        let itemId = $(this).data('item-id');
        let comboItem = comboItems.find(item => item.item_id == itemId);
        if (comboItem) {
            comboItem.show_in_invoice = $(this).is(':checked');
        }
    });

    // Remove combo item
    $(document).on('click', '.remove-combo-item', function() {
        let itemId = $(this).data('item-id');
        
        // Remove from array
        comboItems = comboItems.filter(item => item.item_id != itemId);
        
        // Remove from table
        $(this).closest('tr').remove();
        
        // Recalculate SN
        recalculateComboSN();
        
        // Update sale price
        updateComboSalePrice();
    });

    // Recalculate SN after removal
    function recalculateComboSN() {
        $('#comboItemsBody tr').each(function(index) {
            let newSN = index + 1;
            $(this).find('td:first').text(newSN);
            $(this).attr('data-sn', newSN);
            
            // Update SN in array
            let itemId = $(this).data('item-id');
            let comboItem = comboItems.find(item => item.item_id == itemId);
            if (comboItem) {
                comboItem.sn = newSN;
            }
        });
    }

    // Update combo sale price (sum of all totals)
    function updateComboSalePrice() {
        let total = 0;
        comboItems.forEach(function(item) {
            total += item.total;
        });
        $('#combo_sale_price').val(total.toFixed(2));
        $('#sale_price').val(total.toFixed(2)); // Also update hidden sale_price field
    }

    // Initialize combo section on page load if Combo_Product
    if ($('#type').val() === 'Combo_Product') {
        fetchGeneralProductsForCombo();
    }

    // Clear combo items when product type changes
    $(document).on('change', '#type', function() {
        if ($(this).val() !== 'Combo_Product') {
            comboItems = [];
            comboItemCounter = 0;
            $('#comboItemsBody').empty();
            $('#combo_sale_price').val('');
            $('#combo_item_select').val('').trigger('change');
        }
        // Clear variation attributes data when type changes
        if ($(this).val() !== 'Variation_Product') {
            variationAttributesData = [];
        }
    });




});