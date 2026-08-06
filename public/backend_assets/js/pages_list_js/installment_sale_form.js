/**
 * Installment Sale Form JavaScript
 * Handles form interactions, calculations, and validation for installment sales
 */
$(function () {
    "use strict";
    
    /** #################### -- Configuration & Variables --  #################### **/
    let base_url = $("#base_url").val();
    let op_precision = parseInt($("#op_precision").val()) || 2;
    
    // Translation variables
    let account_field_required = $("#account_field_required").val();
    let total_amount_equal_check_sale_total = $("#total_amount_equal_check_sale_total").val();
    let check_issue_date = $("#check_issue_date").val();
    let check_no = $("#check_no").val();
    let check_expiry_date = $("#check_expiry_date").val();
    let mobile_no = $("#mobile_no").val();
    let transaction_no = $("#transaction_no").val();
    let card_holder_name = $("#card_holder_name").val();
    let card_holding_number = $("#card_holding_number").val();
    let paypal_email = $("#paypal_email").val();
    let stripe_email = $("#stripe_email").val();
    let note = $("#note").val();
    let The_customer_field_is_required = $('#The_customer_field_is_required').val();
    let The_items_field_is_required = $('#The_items_field_is_required').val();
    let The_price_field_is_required = $('#The_price_field_is_required').val();
    let The_number_of_installment_required = $('#The_number_of_installment_required').val();
    let The_total_field_is_required = $('#The_total_field_is_required').val();
    let The_installment_duration_field_is_required = $('#The_installment_duration_field_is_required').val();
    let The_down_payment_field_is_required = $('#The_down_payment_field_is_required').val();
    let The_remaining_field_is_required = $('#The_remaining_field_is_required').val();

    /** #################### -- Utility Functions --  #################### **/
    
    /**
     * Format number with precision
     */
    function formatNumber(num) {
        return parseFloat(num).toFixed(op_precision);
    }

    /**
     * Clean numeric value - remove non-numeric characters except decimal and minus
     */
    function cleanNumericValue(value) {
        if (value === null || value === undefined || value === '') {
            return 0;
        }
        return parseFloat(String(value).replace(/[^0-9.-]/g, '')) || 0;
    }

    /**
     * Calculate discount amount from discount string
     * Supports both percentage (e.g., "10%") and fixed amount (e.g., "100")
     */
    function getDiscountAmount(totalAmount, discountString) {
        if (!discountString || discountString === '' || discountString === '%') {
            return 0;
        }
        
        discountString = String(discountString).trim();
        
        // Check if percentage discount
        if (discountString.includes('%')) {
            let percentage = parseFloat(discountString.replace('%', ''));
            if (isNaN(percentage)) return 0;
            return (totalAmount * percentage) / 100;
        }
        
        // Fixed amount discount
        return parseFloat(discountString) || 0;
    }

    /**
     * Show error message for a field
     */
    function showFieldError(fieldId, message) {
        $(`#${fieldId}_err_msg`).text(message);
        $(`.${fieldId}_err_msg_contnr`).show(200).delay(6000).hide(200);
    }

    /**
     * Hide error message for a field
     */
    function hideFieldError(fieldId) {
        $(`#${fieldId}_err_msg`).text('');
        $(`.${fieldId}_err_msg_contnr`).hide();
    }

    /** #################### -- Input Validation Handlers --  #################### **/
    
    // Discount field keydown - allow numbers, decimal, percentage
    $(document).on('keydown', '.discount', function(e) {
        let keys = e.charCode || e.keyCode || 0;
        return (
            keys == 8 ||   // backspace
            keys == 9 ||   // tab
            keys == 13 ||  // enter
            keys == 46 ||  // delete
            keys == 110 || // numpad decimal
            keys == 86 ||  // v (for paste)
            keys == 190 || // period
            keys == 53 ||  // 5 (for % with shift)
            (keys >= 35 && keys <= 40) ||   // home, end, arrows
            (keys >= 48 && keys <= 57) ||   // numbers
            (keys >= 96 && keys <= 105)     // numpad numbers
        );
    });

    // Discount field keyup - clean invalid characters
    $(document).on('keyup', '.discount', function(e) {
        let input = $(this).val();
        let ponto = input.split('.').length;
        
        if (ponto > 2) {
            $(this).val(input.substr(0, input.length - 1));
        }
        
        $(this).val(input.replace(/[^0-9.%]/g, ''));
        
        if (ponto == 2) {
            $(this).val(input.substr(0, input.indexOf('.') + 4));
        }
        
        if (input == '.') {
            $(this).val("");
        }
    });

    // Integer check for numeric fields
    $(document).on('keydown', '.integerchk', function(e) {
        let keys = e.which || e.keyCode;
        return (
            keys == 8 ||   // backspace
            keys == 9 ||   // tab
            keys == 13 ||  // enter
            keys == 46 ||  // delete
            keys == 110 || // numpad decimal
            keys == 86 ||  // v (for paste)
            keys == 190 || // period
            (keys >= 35 && keys <= 40) ||   // home, end, arrows
            (keys >= 48 && keys <= 57) ||   // numbers
            (keys >= 96 && keys <= 105)     // numpad numbers
        );
    });

    $(document).on('keyup', '.integerchk', function(e) {
        let input = $(this).val();
        let ponto = input.split('.').length;
        
        if (ponto > 2) {
            $(this).val(input.substr(0, input.length - 1));
        }
        
        $(this).val(input.replace(/[^0-9.]/g, ''));
        
        if (ponto == 2) {
            $(this).val(input.substr(0, input.indexOf('.') + 4));
        }
        
        if (input == '.') {
            $(this).val("");
        }
    });

    // Integer check for installment amount fields (dynamically added)
    $(document).on('keydown', '.integerchk1', function(e) {
        let keys = e.which || e.keyCode;
        return (
            keys == 8 ||
            keys == 9 ||
            keys == 13 ||
            keys == 46 ||
            keys == 110 ||
            keys == 86 ||
            keys == 190 ||
            (keys >= 35 && keys <= 40) ||
            (keys >= 48 && keys <= 57) ||
            (keys >= 96 && keys <= 105)
        );
    });

    $(document).on('keyup', '.integerchk1', function(e) {
        let input = $(this).val();
        let ponto = input.split('.').length;
        
        if (ponto > 2) {
            $(this).val(input.substr(0, input.length - 1));
        }
        
        $(this).val(input.replace(/[^0-9.]/g, ''));
        
        if (ponto == 2) {
            $(this).val(input.substr(0, input.indexOf('.') + 4));
        }
        
        if (input == '.') {
            $(this).val("");
        }
    });

    /** #################### -- IMEI/Serial Field Handling --  #################### **/
    
    /**
     * Show/hide IMEI/Serial field based on product type
     */
    function imeiSerialFieldHideShow() {
        let itemType = $('option:selected', '.item_id').attr('data-item-type');
        
        if (itemType == 'IMEI_Product' || itemType == 'Serial_Product') {
            $('.imeiSerialHideShow').show();
        } else {
            $('.imeiSerialHideShow').hide();
            $('#expiry_imei_serial').val('');
            $('#item_type').val('');
        }
    }
    
    // Initialize IMEI/Serial visibility on page load
    imeiSerialFieldHideShow();

    // Product selection change - load price and handle IMEI/Serial
    $(document).on('change click', '.item_id', function() {
        imeiSerialFieldHideShow();
        
        let item_id = $('option:selected', this).val();
        let item_type = $('option:selected', this).attr('data-item-type');
        let price = $('option:selected', this).attr('data-price');
        let item_name = $('option:selected', this).text();
        let old_imei_serial = $('#expiry_imei_serial').val();
        
        $('.modal_hidden_type').val(item_type);
        
        if (item_type == 'IMEI_Product' || item_type == 'Serial_Product') {
            let labelText = item_type == 'IMEI_Product' ? 'IMEI' : 'Serial';
            $('.imei_serial_label').text(`${labelText} Number`);
            $('#imei_serial_modal .modal-title').text(`${item_name}`);
            
            // Fetch available IMEI/Serial numbers via AJAX
            $.ajax({
                url: base_url + "/installment-sale/get-imei-serial",
                method: "POST",
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: { item_id: item_id },
                success: function(response) {
                    let imeiHtml = '';
                    imeiHtml = `<option value="">Select ${item_type == 'IMEI_Product' ? 'IMEI' : 'Serial'}</option>`;
                    
                    if (response.data && response.data.allimei) {
                        let stockIMEI = response.data.allimei.split("||");
                        $.each(stockIMEI, function(i, v) {
                            v = $.trim(v);
                            if (v) {
                                let selected = $.trim(old_imei_serial) == v ? 'selected' : '';
                                imeiHtml += `<option ${selected} value="${v}">${v}</option>`;
                            }
                        });
                    }
                    
                    $('#IMEI_Serial').html('');
                    $('#IMEI_Serial').append(imeiHtml);
                },
                error: function(xhr) {
                    console.error('Error fetching IMEI/Serial:', xhr);
                }
            });
            
            // Show IMEI/Serial selection modal
            $('#imei_serial_modal').modal('show');
        }
        
        // Set price field
        $("#price").val(formatNumber(price ? price : 0));
        
        // Recalculate totals
        calculate();
    });

    // IMEI/Serial modal submit
    $(document).on('click', '#imei_serial_submit', function() {
        let error = false;
        let item_type = $('.modal_hidden_type').val();
        let imei_serial = $('#IMEI_Serial').val();
        
        if (imei_serial == '') {
            error = true;
            let labelText = item_type == 'IMEI_Product' ? 'IMEI' : 'Serial';
            $("#imei_serial_err_msg").text(`The ${labelText} number field is required`);
            $(".imei_serial_err_msg_contnr").show(200);
        }
        
        if (error) {
            return false;
        } else {
            $('#item_type').val($.trim(item_type));
            $('#expiry_imei_serial').val($.trim(imei_serial));
            $('#imei_serial_modal').modal('hide');
        }
    });

    /** #################### -- Calculation Functions --  #################### **/
    
    /**
     * Main calculation function for total, interest, and remaining
     */
    function calculate() {
        let price = cleanNumericValue($("#price").val());
        let shipping_other = cleanNumericValue($("#shipping_other").val());
        let down_payment = cleanNumericValue($("#down_payment_cal").val());
        let discount = $("#discount").val() || '0';
        let percentage_of_interest = cleanNumericValue($("#percentage_of_interest").val());
        
        // Calculate discount amount
        let discount_amount = getDiscountAmount(price, discount);
        
        // Calculate price after discount
        let price_after_discount = price - discount_amount;
        
        // Calculate interest amount
        let interest_amount = (price_after_discount * percentage_of_interest) / 100;
        
        // Calculate total: (price - discount) + interest + shipping
        let total = price_after_discount + interest_amount + shipping_other;
        
        // Calculate remaining: total - down payment
        let remaining = total - down_payment;
        
        // Update fields
        $("#total").val(formatNumber(total));
        $("#remaining").val(formatNumber(Math.max(0, remaining)));
    }

    /**
     * Calculate total of all installment amounts
     */
    function calculateAddedAmount() {
        let total_amount = 0;
        
        $(".amount_of_payment").each(function() {
            let this_value = cleanNumericValue($(this).val());
            total_amount += this_value;
        });
        
        $(".total_amount").html(formatNumber(total_amount));
    }

    // Trigger calculation on input change
    $(document).on('keyup change', '.change_data', function(e) {
        e.preventDefault();
        calculate();
    });

    // Update installment total on amount change
    $(document).on('keyup', '.amount_of_payment', function(e) {
        e.preventDefault();
        calculateAddedAmount();
    });

    /** #################### -- Down Payment Account Handling --  #################### **/
    
    // Disable/enable payment method based on down payment value
    $(document).on('keyup change', '#down_payment_cal', function() {
        let amount = cleanNumericValue($(this).val());
        
        if (amount <= 0) {
            $("#payment_method_id").prop("disabled", true);
            $('#show_account_type').html('');
        } else {
            $("#payment_method_id").prop("disabled", false);
        }
    });

    // Payment method change - show account-specific fields
    $(document).on('change', '#payment_method_id', function(e) {
        e.preventDefault();
        
        let account_type = $(this).find(":selected").attr('data-type');
        $('#account_type').val(account_type);
        
        let html = '';
        
        if (account_type == 'Cash' && account_type != undefined) {
            html = `
                <div class="mb-3">
                    <label class="form-label">${note}</label>
                    <input type="text" name="p_note" class="form-control" placeholder="${note}">
                </div>
            `;
        } else if (account_type == 'Bank_Account' && account_type != undefined) {
            html = `
                <div class="mb-3">
                    <label class="form-label">${check_no}</label>
                    <input type="text" name="check_no" class="form-control" placeholder="${check_no}">
                </div>
                <div class="mb-3">
                    <label class="form-label">${check_issue_date}</label>
                    <input type="date" name="check_issue_date" class="form-control" placeholder="${check_issue_date}">
                </div>
                <div class="mb-3">
                    <label class="form-label">${check_expiry_date}</label>
                    <input type="date" name="check_expiry_date" class="form-control" placeholder="${check_expiry_date}">
                </div>
            `;
        } else if (account_type == 'Card' && account_type != undefined) {
            html = `
                <div class="mb-3">
                    <label class="form-label">${card_holder_name}</label>
                    <input type="text" name="card_holder_name" class="form-control" placeholder="${card_holder_name}">
                </div>
                <div class="mb-3">
                    <label class="form-label">${card_holding_number}</label>
                    <input type="text" name="card_holding_number" class="form-control" placeholder="${card_holding_number}">
                </div>
            `;
        } else if (account_type == 'Mobile_Banking' && account_type != undefined) {
            html = `
                <div class="mb-3">
                    <label class="form-label">${mobile_no}</label>
                    <input type="text" name="mobile_no" class="form-control" placeholder="${mobile_no}">
                </div>
                <div class="mb-3">
                    <label class="form-label">${transaction_no}</label>
                    <input type="text" name="transaction_no" class="form-control" placeholder="${transaction_no}">
                </div>
            `;
        } else if (account_type == 'Paypal' && account_type != undefined) {
            html = `
                <div class="mb-3">
                    <label class="form-label">${paypal_email}</label>
                    <input type="email" name="paypal_email" class="form-control" placeholder="${paypal_email}">
                </div>
            `;
        } else if (account_type == 'Stripe' && account_type != undefined) {
            html = `
                <div class="mb-3">
                    <label class="form-label">${stripe_email}</label>
                    <input type="email" name="stripe_email" class="form-control" placeholder="${stripe_email}">
                </div>
            `;
        } else {
            html = '';
        }
        
        $('#show_account_type').html(html);
    });

    /** #################### -- Installment Generation --  #################### **/
    
    // Generate installments button click
    $(document).on('click', '.next_button', function(e) {
        e.preventDefault();
        
        let error = false;
        let item_id = $('#item_id').val();
        let price = $('#price').val();
        let number_of_installment = parseInt($("#number_of_installment").val());
        let total = $('#total').val();
        let installment_type = parseInt($("#installment_type").val());
        
        // Validation
        if (!item_id || item_id == '') {
            error = true;
            showFieldError('item_id', The_items_field_is_required);
        }
        
        if (!price || price == '' || cleanNumericValue(price) <= 0) {
            error = true;
            showFieldError('price', The_price_field_is_required);
        }
        
        if (!number_of_installment || number_of_installment <= 0) {
            error = true;
            showFieldError('number_of_installment', The_number_of_installment_required);
        }
        
        if (!total || total == '' || cleanNumericValue(total) <= 0) {
            error = true;
            showFieldError('total', The_total_field_is_required);
        }
        
        if (!installment_type || installment_type <= 0) {
            error = true;
            showFieldError('installment_duration', The_installment_duration_field_is_required);
        }
        
        if (error) {
            return false;
        }
        
        // Generate installment rows
        let html = '';
        let selected_date = $(".datePicker").val() || new Date().toISOString().slice(0, 10);
        let remaining = cleanNumericValue($("#remaining").val());
        
        // Calculate installment amounts
        let divided_remaining = remaining / number_of_installment;
        let divided_result_floor = Math.floor(divided_remaining * Math.pow(10, op_precision)) / Math.pow(10, op_precision);
        let divided_result_floor_mul = divided_result_floor * number_of_installment;
        let total_divided_last_value = remaining - divided_result_floor_mul;
        let first_value = divided_result_floor + total_divided_last_value;
        
        let installment_days_temp = installment_type;
        
        for (let i = 1; i <= number_of_installment; i++) {
            // Calculate next payment date
            let d = new Date(selected_date);
            let next_payment_date = new Date(d.setDate(d.getDate() + installment_days_temp));
            let output_date = next_payment_date.toISOString().slice(0, 10);
            
            let amount = (i === 1) ? first_value : divided_result_floor;
            
            html += `
                <tr>
                    <td class="text-center align-middle">${i}</td>
                    <td>
                        <input type="hidden" name="paid_status[]" value="Unpaid">
                        <input type="text" class="form-control amount_of_payment integerchk1" 
                            value="${formatNumber(amount)}" onfocus="select()" name="amount_of_payment[]">
                    </td>
                    <td>
                        <input type="text" class="form-control datePicker" 
                            value="${output_date}" readonly name="payment_date[]">
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn text-danger delete_row">
                            <i class="icon-base ti tabler-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            
            installment_days_temp += installment_type;
        }
        
        $(".show_tb_data").html(html);
        calculateAddedAmount();
        
        // Initialize datepicker on newly added fields if available
        $('.datePicker').flatpickr({
            altInput: true,
            altFormat: 'Y-m-d',
            dateFormat: 'Y-m-d',
            static: true,
            allowInput: true
        });
    });

    // Delete installment row
    $(document).on('click', '.delete_row', function(e) {
        e.preventDefault();
        $(this).closest('tr').remove();
        
        // Update number of installments
        let rowCount = $(".amount_of_payment").length;
        $("#number_of_installment").val(rowCount);
        
        // Recalculate total
        calculateAddedAmount();
        
        // Renumber rows
        $(".show_tb_data tr").each(function(index) {
            $(this).find('td:first').text(index + 1);
        });
    });

    /** #################### -- Form Validation & Submission --  #################### **/
    
    // Form validation on submit
    $(document).on('submit', '#installment_form', function(e) {
        let error = false;
        let down_payment = cleanNumericValue($('#down_payment_cal').val());
        
        // Validate payment method if down payment is provided
        if (down_payment > 0) {
            let payment_method_id = $("#payment_method_id").val();
            if (!payment_method_id || payment_method_id == "") {
                $("#payment_method_id_err_msg").text(account_field_required);
                $(".payment_method_id_err_msg_contnr").show(200);
                error = true;
            }
        }
        
        // Validate IMEI/Serial for specific product types
        let item_type = $('#item_type').val();
        if (item_type == 'IMEI_Product' || item_type == 'Serial_Product') {
            let expiry_imei_serial = $('#expiry_imei_serial').val();
            if (!expiry_imei_serial || expiry_imei_serial == "") {
                let labelText = item_type == 'IMEI_Product' ? 'IMEI' : 'Serial';
                $("#imei_serial_field_err_msg").text(`The ${labelText} number field is required`);
                $(".imei_serial_field_err_msg_contnr").show(200);
                error = true;
            }
        }
        
        if (error) {
            return false;
        }
    });

    // Pre-submit validation button click
    $(document).on('click', '.check_required_field', function(e) {
        let error = false;
        
        let customer_id = $('#customer_id').val();
        let item_id = $('#item_id').val();
        let price = $('#price').val();
        let number_of_installment = $('#number_of_installment').val();
        let percentage_of_interest = $('#percentage_of_interest').val();
        let total = $('#total').val();
        let down_payment_cal = $('#down_payment_cal').val();
        let remaining = $('#remaining').val();
        let installment_type = $('#installment_type').val();
        
        // Field validations
        if (!customer_id || customer_id == '') {
            error = true;
            showFieldError('customer_id', The_customer_field_is_required);
        }
        
        if (!item_id || item_id == '') {
            error = true;
            showFieldError('item_id', The_items_field_is_required);
        }
        
        if (!price || price == '' || cleanNumericValue(price) <= 0) {
            error = true;
            showFieldError('price', The_price_field_is_required);
        }
        
        if (!number_of_installment || number_of_installment == '' || parseInt(number_of_installment) <= 0) {
            error = true;
            showFieldError('number_of_installment', The_number_of_installment_required);
        }
        
        if (!total || total == '' || cleanNumericValue(total) <= 0) {
            error = true;
            showFieldError('total', The_total_field_is_required);
        }
        
        if (remaining === '' || remaining === null) {
            error = true;
            showFieldError('remaining', The_remaining_field_is_required);
        }
        
        if (!installment_type || installment_type == '' || parseInt(installment_type) <= 0) {
            error = true;
            showFieldError('installment_duration', The_installment_duration_field_is_required);
        }
        
        // Validate each installment amount
        let hasInvalidAmount = false;
        $(".amount_of_payment").each(function() {
            let this_value = $(this).val();
            if (this_value == '' || isNaN(this_value) || parseFloat(this_value) <= 0) {
                $(this).addClass('is-invalid').css({"border-color": "red"});
                hasInvalidAmount = true;
            } else {
                $(this).removeClass('is-invalid').css({"border": "1px solid #d9dee3"});
            }
        });
        
        if (hasInvalidAmount) {
            error = true;
        }
        
        // Check if total installment amounts equal remaining
        let total_installment_amount = 0;
        $(".amount_of_payment").each(function() {
            total_installment_amount += cleanNumericValue($(this).val());
        });
        
        let remaining_amount = cleanNumericValue(remaining);
        
        // Allow small floating point differences (0.01)
        if (Math.abs(total_installment_amount - remaining_amount) > 0.01 && $(".amount_of_payment").length > 0) {
            Swal.fire({
                title: "Warning!",
                text: total_amount_equal_check_sale_total,
                icon: 'warning',
                showDenyButton: false,
                showCancelButton: false,
                confirmButtonText: 'OK'
            });
            return false;
        }
        
        if (error) {
            return false;
        }
    });

    // Save and Add More button
    $(document).on('click', '#save_and_add_more', function() {
        $('#set_save_and_add_more').val('1');
    });

    /** #################### -- Initialization --  #################### **/
    
    // Initialize on page load
    $(document).ready(function() {
        // Initialize calculation
        calculate();
        
        // Check if down payment field has value and enable/disable payment method
        let downPaymentVal = cleanNumericValue($('#down_payment_cal').val());
        if (downPaymentVal <= 0) {
            $("#payment_method_id").prop("disabled", true);
        }
        
        // Initialize tooltips
        if (typeof bootstrap !== 'undefined') {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    });
});

