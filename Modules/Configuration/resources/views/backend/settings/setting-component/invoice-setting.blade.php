<div class="tab-pane fade {{ request()->route('tab') == 'invoice_setting' ? 'active show' : '' }}" id="invoice_setting" role="tabpanel">
    @php
        $invoiceConfig = [];
        if (isset($company->invoice_configuration) && $company->invoice_configuration != '') {
            $invoiceConfig = json_decode($company->invoice_configuration, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $invoiceConfig = [];
            }
        }
        
        // Set default values
        $defaults = [
            'invoice_format_or_size' => '56mm',
            'show_hsn_code' => 'No',
            'schema_type' => 'XXXX',
            'inv_numbering_type' => 'Sequential',
            'inv_number_of_digit' => 4,
            'inv_prefix' => '',
            'inv_start_from' => '1',
            'show_letter_head' => 'No',
            'letter_head_gap' => $company->letter_head_gap ?? '200px',
            'letter_footer_gap' => $company->letter_footer_gap ?? '100px',
            'invoice_heading' => 'Invoice',
            'invoice_heading_arabic' => '',
            'invoice_heading_due' => 'Due',
            'invoice_heading_paid' => 'Paid',
            'invoice_no_label' => 'Invoice No',
            'invoice_no_label_arabic' => '',
            'invoice_date_label' => 'Date',
            'invoice_date_label_arabic' => '',
            'invoice_show_due_date' => 'Yes',
            'invoice_due_date_label' => 'Due Date',
            'invoice_due_date_label_arabic' => '',
            'sales_person_label' => 'Sales Person',
            'commission_agent_label' => 'Commission Agent',
            'show_business_name' => 'Yes',
            'business_name_arabic' => '',
            'show_business_tax_number' => $company->collect_tax == 'Yes' ? 'Yes' : 'No',
            'business_tax_number_label' => 'Business Tax Number',
            'customer_label' => 'Customer',
            'customer_tax_number_label' => 'Customer Tax Number',
            'show_customer_phone_number' => 'Yes',
            'show_customer_email' => 'No',
            'show_customer_address' => 'Yes',
            'serial_no_label' => 'SN',
            'serial_no_label_arabic' => '',
            'item_label' => 'Item',
            'item_label_arabic' => '',
            'price_label' => 'Price',
            'price_label_arabic' => '',
            'quantity_label' => 'Qty',
            'quantity_label_arabic' => '',
            'item_discount_label' => 'Discount',
            'item_discount_label_arabic' => '',
            'subtotal_label' => 'Subtotal',
            'subtotal_label_arabic' => '',
            'total_label' => 'Total',
            'total_label_arabic' => '',
            'total_item_label' => 'Total Item',
            'total_item_label_arabic' => '',
            'tax_label' => 'Tax',
            'tax_label_arabic' => '',
            'charge_label' => 'Charge',
            'charge_label_arabic' => '',
            'discount_label' => 'Discount',
            'discount_label_arabic' => '',
            'delivery_partner_label' => 'Delivery Partner',
            'delivery_partner_label_arabic' => '',
            'rounding_label' => 'Rounding',
            'rounding_label_arabic' => '',
            'total_payable_label' => 'Total Payable',
            'total_payable_label_arabic' => '',
            'previous_balance_label' => 'Previous Balance',
            'previous_balance_label_arabic' => '',
            'paid_amount_label' => 'Paid Amount',
            'paid_amount_label_arabic' => '',
            'due_amount_label' => 'Due Amount',
            'due_amount_label_arabic' => '',
            'due_receive_label' => 'Due Receive',
            'due_receive_label_arabic' => '',
            'advance_receive_label' => 'Advance Receive',
            'advance_receive_label_arabic' => '',
            'given_amount_label' => 'Given Amount',
            'given_amount_label_arabic' => '',
            'change_amount_label' => 'Change Amount',
            'change_amount_label_arabic' => '',
            'servicing_charge_label' => 'Servicing Charge',
            'servicing_charge_label_arabic' => '',
            'show_payment_method' => 'Yes',
            'payment_method_label' => 'Payment Method',
            'payment_method_label_arabic' => '',
            'show_brand' => 'No',
            'show_product_code' => 'No',
            'show_product_imei_serial_number' => 'No',
            'show_product_image' => 'No',
            'show_warranty_period' => 'Yes',
            'show_warranty_expiry_date' => 'Yes',
            'show_guarantee_period' => 'Yes',
            'show_guarantee_expiry_date' => 'Yes',
            'show_total_in_words' => 'Yes',
            'word_format' => 'Indian',
            'qr_code_option' => 'ZATCA QR Code',
            'qr_code_business' => null,
            'qr_code_address' => null,
            'qr_code_taxnumber' => null,
            'qr_code_invoice_no' => null,
            'qr_code_invoice_date_time' => null,
            'qr_code_subtotal' => null,
            'qr_code_charge' => null,
            'qr_code_tax' => null,
            'qr_code_total_payable' => null,
            'qr_code_customer_name' => null,
            'qr_code_invoice_url' => null,
            'qr_code_outlet' => null,
        ];
        $invoiceConfig = array_merge($defaults, $invoiceConfig);
        $invFormat = date('Y') . '-XXXX';
        $currentYear = date('Y');
    @endphp
    <form id="invoice_setting_form" enctype="multipart/form-data">
        @csrf
        <div class="card mb-6">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('Invoice Size') }} ({{ __('All of our available sizes') }})</h5>
            </div>
            <div class="card-body">
                <div class="row justify-content-center option-div-group">
                    <div class="col-md-2 text-center mb-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input invoice_format_element" type="radio" name="invoice_format_or_size" id="invoice_format_56mm" value="56mm" {{ ($invoiceConfig['invoice_format_or_size'] ?? '56mm') == '56mm' ? 'checked' : '' }}>
                            <label class="form-check-label" for="invoice_format_56mm">
                                <iconify-icon icon="solar:book-bookmark-broken" width="60"></iconify-icon>
                                <h6>{{ __('Thermal 56mm') }}</h6>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-2 text-center mb-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input invoice_format_element" type="radio" name="invoice_format_or_size" id="invoice_format_80mm" value="80mm" {{ ($invoiceConfig['invoice_format_or_size'] ?? '') == '80mm' ? 'checked' : '' }}>
                            <label class="form-check-label" for="invoice_format_80mm">
                                <iconify-icon icon="solar:book-bookmark-broken" width="60"></iconify-icon>
                                <h6>{{ __('Thermal 80mm') }}</h6>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-2 text-center mb-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input invoice_format_element" type="radio" name="invoice_format_or_size" id="invoice_format_a4" value="A4 Print" {{ ($invoiceConfig['invoice_format_or_size'] ?? '') == 'A4 Print' ? 'checked' : '' }}>
                            <label class="form-check-label" for="invoice_format_a4">
                                <iconify-icon icon="solar:book-bookmark-broken" width="60"></iconify-icon>
                                <h6>{{ __('A4 Print') }}</h6>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-2 text-center mb-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input invoice_format_element" type="radio" name="invoice_format_or_size" id="invoice_format_half_a4" value="Half A4 Print" {{ ($invoiceConfig['invoice_format_or_size'] ?? '') == 'Half A4 Print' ? 'checked' : '' }}>
                            <label class="form-check-label" for="invoice_format_half_a4">
                                <iconify-icon icon="solar:book-bookmark-broken" width="60"></iconify-icon>
                                <h6>{{ __('A5 Print') }}</h6>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-2 text-center mb-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input invoice_format_element" type="radio" name="invoice_format_or_size" id="invoice_format_letter" value="Letter Head" {{ ($invoiceConfig['invoice_format_or_size'] ?? '') == 'Letter Head' ? 'checked' : '' }}>
                            <label class="form-check-label" for="invoice_format_letter">
                                <iconify-icon icon="solar:book-bookmark-broken" width="60"></iconify-icon>
                                <h6>{{ __('Letter Head') }}</h6>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-6">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('Numbering') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input schema_type numbering_format_element" type="radio" name="schema_type" id="schema_type_xxxx" value="XXXX" {{ ($invoiceConfig['schema_type'] ?? 'XXXX') == 'XXXX' ? 'checked' : '' }}>
                            <label class="form-check-label" for="schema_type_xxxx">
                                <strong>{{ __('FORMAT') }}:</strong><br>XXXX
                            </label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input schema_type numbering_format_element" type="radio" name="schema_type" id="schema_type_year" value="{{ $invFormat }}" {{ ($invoiceConfig['schema_type'] ?? '') == $invFormat ? 'checked' : '' }}>
                            <label class="form-check-label" for="schema_type_year">
                                <strong>{{ __('FORMAT') }}:</strong><br>{{ $invFormat }}
                            </label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <p class="mb-0">{{ __('Preview') }}</p>
                        <p class="inv-format-number fw-bold" style="font-size: 1.1em; color: #696cff;"></p>
                    </div>
                    <div class="clear-fix"></div>

                    <div class="col-12 col-md-3 validate_wrapper">
                        <label class="form-label mb-1" for="inv_numbering_type">{{ __('Numbering Types') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="inv_numbering_type" name="inv_numbering_type" data-placeholder="{{ __('Select') }} {{ __('Type') }}">
                            <option value=""></option>
                            <option value="Sequential" {{ ($invoiceConfig['inv_numbering_type'] ?? 'Sequential') == 'Sequential' ? 'selected' : '' }}>{{ __('Sequential') }}</option>
                            <option value="Random" {{ ($invoiceConfig['inv_numbering_type'] ?? '') == 'Random' ? 'selected' : '' }}>{{ __('Random') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-3 validate_wrapper">
                        <label class="form-label mb-1" for="inv_number_of_digit">{{ __('Numbering of digits') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="inv_number_of_digit" name="inv_number_of_digit" data-placeholder="{{ __('Select') }} {{ __('Digits') }}">
                            <option value=""></option>
                            @for($i = 4; $i <= 10; $i++)
                                <option value="{{ $i }}" {{ ($invoiceConfig['inv_number_of_digit'] ?? 4) == $i ? 'selected' : '' }}>{{ $i }}</option>
                            @endfor
                        </select>
                    </div>

                    <div class="col-12 col-md-3 validate_wrapper">
                        <label class="form-label mb-1" for="inv_prefix">{{ __('Prefix') }}</label>
                        <input type="text" class="form-control" id="inv_prefix" name="inv_prefix" placeholder="{{ __('Prefix') }}" value="{{ $invoiceConfig['inv_prefix'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-3 validate_wrapper start_from_wrap">
                        <label class="form-label mb-1" for="inv_start_from">{{ __('Start From') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="inv_start_from" name="inv_start_from" placeholder="{{ __('Start From') }}" value="{{ $invoiceConfig['inv_start_from'] ?? '1' }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-6">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('Invoice Logo') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="inv_logo_is_show">{{ __('Invoice Logo Show') }}</label>
                        <select class="select2 form-select" id="inv_logo_is_show" name="inv_logo_is_show" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="Yes" {{ ($company->inv_logo_is_show ?? 'Yes') == 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="No" {{ ($company->inv_logo_is_show ?? '') == 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="invoice_logo">Invoice Logo <small class="text-danger">(Max 1 MB, Type: jpeg/gif/png)</small></label>
                        <input type="file" class="form-control" id="invoice_logo_file" name="invoice_logo_file" accept="image/*">
                        <input type="hidden" name="invoice_logo" id="invoice_logo">
                        <input type="hidden" name="invoice_logo_p" id="invoice_logo_p" value="{{ $company->invoice_logo ?? '' }}">
                    </div>
                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <div class="mt-2">
                            <img id="invoice_logo_preview" src="{{ $company->invoice_logo ? asset('uploads/site_settings/' . $company->invoice_logo) : asset('uploads/dummy_images/default-picture.png') }}" alt="Invoice Logo" style="border:1px dashed #d1d0d4; padding: 10px; border-radius: 5px; max-width: 100px; display: block;">
                            <div class="d-flex gap-2 mt-2">
                                @if($company->invoice_logo)
                                    <button type="button" class="btn btn-sm btn-primary preview-invoice-logo d-flex gap-2 align-items-center">
                                        <i class="ti tabler-eye"></i>
                                        <span>{{ __('Preview') }}</span>
                                    </button>
                                @endif
                                <button type="button" class="btn btn-sm btn-danger remove-invoice-logo d-flex gap-2 align-items-center">
                                    <i class="ti tabler-trash"></i>
                                    <span>{{ __('Remove') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-6">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('Invoice Letterhead') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="show_letter_head">{{ __('Show Letterhead') }}</label>
                        <select class="select2 form-select" id="show_letter_head" name="show_letter_head" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="Yes" {{ ($invoiceConfig['show_letter_head'] ?? 'No') == 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="No" {{ ($invoiceConfig['show_letter_head'] ?? 'No') == 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper letter_head_gap_wrap">
                        <label class="form-label mb-1" for="letter_head_gap">{{ __('Letter Head Gap') }}</label>
                        <input type="text" class="form-control" id="letter_head_gap" name="letter_head_gap" placeholder="{{ __('Letter Head Gap') }}" value="{{ $invoiceConfig['letter_head_gap'] ?? '200px' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper letter_footer_gap_wrap">
                        <label class="form-label mb-1" for="letter_footer_gap">{{ __('Letter Footer Gap') }}</label>
                        <input type="text" class="form-control" id="letter_footer_gap" name="letter_footer_gap" placeholder="{{ __('Letter Footer Gap') }}" value="{{ $invoiceConfig['letter_footer_gap'] ?? '100px' }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-6">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title m-0">{{ __('Heading and Label') }}</h5>
                    <button type="button" class="btn btn-sm btn-danger reset-heading-labels-btn">
                        <i class="ti tabler-refresh me-1"></i>
                        {{ __('Reset to Default') }}
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="invoice_heading">{{ __('Invoice Heading') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="invoice_heading" name="invoice_heading" placeholder="{{ __('Invoice Heading') }}" value="{{ $invoiceConfig['invoice_heading'] ?? 'Invoice' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="invoice_heading_arabic">{{ __('Invoice Heading Arabic') }}</label>
                        <input type="text" class="form-control" id="invoice_heading_arabic" name="invoice_heading_arabic" placeholder="{{ __('Invoice Heading Arabic') }}" value="{{ $invoiceConfig['invoice_heading_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="invoice_heading_due">{{ __('Heading Suffix for Due Invoice') }}</label>
                        <input type="text" class="form-control" id="invoice_heading_due" name="invoice_heading_due" placeholder="{{ __('Due') }}" value="{{ $invoiceConfig['invoice_heading_due'] ?? 'Due' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="invoice_heading_paid">{{ __('Heading Suffix for Paid Invoice') }}</label>
                        <input type="text" class="form-control" id="invoice_heading_paid" name="invoice_heading_paid" placeholder="{{ __('Paid') }}" value="{{ $invoiceConfig['invoice_heading_paid'] ?? 'Paid' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="invoice_no_label">{{ __('Invoice No Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="invoice_no_label" name="invoice_no_label" placeholder="{{ __('Invoice No') }}" value="{{ $invoiceConfig['invoice_no_label'] ?? 'Invoice No' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="invoice_no_label_arabic">{{ __('Invoice No Label Arabic') }}</label>
                        <input type="text" class="form-control" id="invoice_no_label_arabic" name="invoice_no_label_arabic" placeholder="{{ __('Invoice No Arabic') }}" value="{{ $invoiceConfig['invoice_no_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="invoice_date_label">{{ __('Invoice Date Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="invoice_date_label" name="invoice_date_label" placeholder="{{ __('Date') }}" value="{{ $invoiceConfig['invoice_date_label'] ?? 'Date' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="invoice_date_label_arabic">{{ __('Invoice Date Label Arabic') }}</label>
                        <input type="text" class="form-control" id="invoice_date_label_arabic" name="invoice_date_label_arabic" placeholder="{{ __('Date Arabic') }}" value="{{ $invoiceConfig['invoice_date_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="invoice_show_due_date">{{ __('Invoice Show Due Date') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="invoice_show_due_date" name="invoice_show_due_date" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="Yes" {{ ($invoiceConfig['invoice_show_due_date'] ?? 'Yes') == 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="No" {{ ($invoiceConfig['invoice_show_due_date'] ?? '') == 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="invoice_due_date_label">{{ __('Invoice Due Date Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="invoice_due_date_label" name="invoice_due_date_label" placeholder="{{ __('Due Date') }}" value="{{ $invoiceConfig['invoice_due_date_label'] ?? 'Due Date' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="invoice_due_date_label_arabic">Invoice Due Date Label Arabic</label>
                        <input type="text" class="form-control" id="invoice_due_date_label_arabic" name="invoice_due_date_label_arabic" placeholder="Due Date Arabic" value="{{ $invoiceConfig['invoice_due_date_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="sales_person_label">{{ __('Sales Person Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="sales_person_label" name="sales_person_label" placeholder="{{ __('Sales Person') }}" value="{{ $invoiceConfig['sales_person_label'] ?? 'Sales Person' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="commission_agent_label">{{ __('Commission Agent Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="commission_agent_label" name="commission_agent_label" placeholder="{{ __('Commission Agent') }}" value="{{ $invoiceConfig['commission_agent_label'] ?? 'Commission Agent' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="show_business_name">{{ __('Show Business Name') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="show_business_name" name="show_business_name" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="Yes" {{ ($invoiceConfig['show_business_name'] ?? 'Yes') == 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="No" {{ ($invoiceConfig['show_business_name'] ?? '') == 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="business_name_arabic">{{ __('Business Name Arabic') }}</label>
                        <input type="text" class="form-control" id="business_name_arabic" name="business_name_arabic" placeholder="{{ __('Business Name Arabic') }}" value="{{ $invoiceConfig['business_name_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="show_business_tax_number">{{ __('Show Business Tax Number') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="show_business_tax_number" name="show_business_tax_number" data-default="{{ $company->collect_tax == 'Yes' ? 'Yes' : 'No' }}" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="Yes" {{ ($invoiceConfig['show_business_tax_number'] ?? ($company->collect_tax == 'Yes' ? 'Yes' : 'No')) == 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="No" {{ ($invoiceConfig['show_business_tax_number'] ?? '') == 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="business_tax_number_label">{{ __('Business Tax Number Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="business_tax_number_label" name="business_tax_number_label" placeholder="{{ __('Business Tax Number') }}" value="{{ $invoiceConfig['business_tax_number_label'] ?? 'Business Tax Number' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="customer_label">{{ __('Customer Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="customer_label" name="customer_label" placeholder="{{ __('Customer') }}" value="{{ $invoiceConfig['customer_label'] ?? 'Customer' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="customer_tax_number_label">{{ __('Customer Tax Number Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="customer_tax_number_label" name="customer_tax_number_label" placeholder="{{ __('Customer Tax Number') }}" value="{{ $invoiceConfig['customer_tax_number_label'] ?? 'Customer Tax Number' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="show_customer_phone_number">{{ __('Show Customer Phone Number') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="show_customer_phone_number" name="show_customer_phone_number" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="Yes" {{ ($invoiceConfig['show_customer_phone_number'] ?? 'Yes') == 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="No" {{ ($invoiceConfig['show_customer_phone_number'] ?? '') == 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="show_customer_email">Show Customer Email {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="show_customer_email" name="show_customer_email" data-placeholder="Select option">
                            <option value=""></option>
                            <option value="Yes" {{ ($invoiceConfig['show_customer_email'] ?? 'No') == 'Yes' ? 'selected' : '' }}>Yes</option>
                            <option value="No" {{ ($invoiceConfig['show_customer_email'] ?? 'No') == 'No' ? 'selected' : '' }}>No</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="show_customer_address">{{ __('Show Customer Address') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="show_customer_address" name="show_customer_address" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="Yes" {{ ($invoiceConfig['show_customer_address'] ?? 'Yes') == 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="No" {{ ($invoiceConfig['show_customer_address'] ?? '') == 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="serial_no_label">{{ __('Serial No Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="serial_no_label" name="serial_no_label" placeholder="{{ __('Serial No Label') }}" value="{{ $invoiceConfig['serial_no_label'] ?? 'Serial No Label' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="serial_no_label_arabic">{{ __('Serial No Label Arabic') }}</label>
                        <input type="text" class="form-control" id="serial_no_label_arabic" name="serial_no_label_arabic" placeholder="{{ __('Serial No Arabic') }}" value="{{ $invoiceConfig['serial_no_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="item_label">{{ __('Item Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="item_label" name="item_label" placeholder="{{ __('Item') }}" value="{{ $invoiceConfig['item_label'] ?? 'Item' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="item_label_arabic">{{ __('Item Label Arabic') }}</label>
                        <input type="text" class="form-control" id="item_label_arabic" name="item_label_arabic" placeholder="{{ __('Item Arabic') }}" value="{{ $invoiceConfig['item_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="price_label">{{ __('Price Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="price_label" name="price_label" placeholder="{{ __('Price') }}" value="{{ $invoiceConfig['price_label'] ?? 'Price' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="price_label_arabic">{{ __('Price Label Arabic') }}</label>
                        <input type="text" class="form-control" id="price_label_arabic" name="price_label_arabic" placeholder="{{ __('Price Arabic') }}" value="{{ $invoiceConfig['price_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="quantity_label">{{ __('Quantity Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="quantity_label" name="quantity_label" placeholder="{{ __('Qty') }}" value="{{ $invoiceConfig['quantity_label'] ?? 'Qty' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="quantity_label_arabic">{{ __('Quantity Label Arabic') }}</label>
                        <input type="text" class="form-control" id="quantity_label_arabic" name="quantity_label_arabic" placeholder="{{ __('Quantity Arabic') }}" value="{{ $invoiceConfig['quantity_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="item_discount_label">{{ __('Item Discount Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="item_discount_label" name="item_discount_label" placeholder="{{ __('Discount') }}" value="{{ $invoiceConfig['item_discount_label'] ?? 'Discount' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="item_discount_label_arabic">{{ __('Item Discount Label Arabic') }}</label>
                        <input type="text" class="form-control" id="item_discount_label_arabic" name="item_discount_label_arabic" placeholder="{{ __('Discount Arabic') }}" value="{{ $invoiceConfig['item_discount_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="subtotal_label">{{ __('Subtotal Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="subtotal_label" name="subtotal_label" placeholder="{{ __('Subtotal') }}" value="{{ $invoiceConfig['subtotal_label'] ?? 'Subtotal' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="subtotal_label_arabic">{{ __('Subtotal Label Arabic') }}</label>
                        <input type="text" class="form-control" id="subtotal_label_arabic" name="subtotal_label_arabic" placeholder="{{ __('Subtotal Arabic') }}" value="{{ $invoiceConfig['subtotal_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="total_label">{{ __('Total Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="total_label" name="total_label" placeholder="{{ __('Total') }}" value="{{ $invoiceConfig['total_label'] ?? 'Total' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="total_label_arabic">{{ __('Total Label Arabic') }}</label>
                        <input type="text" class="form-control" id="total_label_arabic" name="total_label_arabic" placeholder="{{ __('Total Arabic') }}" value="{{ $invoiceConfig['total_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="total_item_label">{{ __('Total Item Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="total_item_label" name="total_item_label" placeholder="{{ __('Total Item') }}" value="{{ $invoiceConfig['total_item_label'] ?? 'Total Item' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="total_item_label_arabic">{{ __('Total Item Label Arabic') }}</label>
                        <input type="text" class="form-control" id="total_item_label_arabic" name="total_item_label_arabic" placeholder="{{ __('Total Item Arabic') }}" value="{{ $invoiceConfig['total_item_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="tax_label">{{ __('Tax Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="tax_label" name="tax_label" placeholder="{{ __('Tax') }}" value="{{ $invoiceConfig['tax_label'] ?? 'Tax' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="tax_label_arabic">{{ __('Tax Label Arabic') }}</label>
                        <input type="text" class="form-control" id="tax_label_arabic" name="tax_label_arabic" placeholder="{{ __('Tax Arabic') }}" value="{{ $invoiceConfig['tax_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="charge_label">{{ __('Charge Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="charge_label" name="charge_label" placeholder="{{ __('Charge') }}" value="{{ $invoiceConfig['charge_label'] ?? 'Charge' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="charge_label_arabic">{{ __('Charge Label Arabic') }}</label>
                        <input type="text" class="form-control" id="charge_label_arabic" name="charge_label_arabic" placeholder="{{ __('Charge Arabic') }}" value="{{ $invoiceConfig['charge_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="discount_label">{{ __('Discount Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="discount_label" name="discount_label" placeholder="{{ __('Discount') }}" value="{{ $invoiceConfig['discount_label'] ?? 'Discount' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="discount_label_arabic">{{ __('Discount Label Arabic') }}</label>
                        <input type="text" class="form-control" id="discount_label_arabic" name="discount_label_arabic" placeholder="{{ __('Discount Arabic') }}" value="{{ $invoiceConfig['discount_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="delivery_partner_label">{{ __('Delivery Partner Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="delivery_partner_label" name="delivery_partner_label" placeholder="{{ __('Delivery Partner') }}" value="{{ $invoiceConfig['delivery_partner_label'] ?? 'Delivery Partner' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="delivery_partner_label_arabic">{{ __('Delivery Partner Label Arabic') }}</label>
                        <input type="text" class="form-control" id="delivery_partner_label_arabic" name="delivery_partner_label_arabic" placeholder="{{ __('Delivery Partner Arabic') }}" value="{{ $invoiceConfig['delivery_partner_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="rounding_label">{{ __('Rounding Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="rounding_label" name="rounding_label" placeholder="{{ __('Rounding') }}" value="{{ $invoiceConfig['rounding_label'] ?? 'Rounding' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="rounding_label_arabic">{{ __('Rounding Label Arabic') }}</label>
                        <input type="text" class="form-control" id="rounding_label_arabic" name="rounding_label_arabic" placeholder="{{ __('Rounding Arabic') }}" value="{{ $invoiceConfig['rounding_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="total_payable_label">{{ __('Total Payable Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="total_payable_label" name="total_payable_label" placeholder="{{ __('Total Payable') }}" value="{{ $invoiceConfig['total_payable_label'] ?? 'Total Payable' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="total_payable_label_arabic">{{ __('Total Payable Label Arabic') }}</label>
                        <input type="text" class="form-control" id="total_payable_label_arabic" name="total_payable_label_arabic" placeholder="{{ __('Total Payable Arabic') }}" value="{{ $invoiceConfig['total_payable_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="previous_balance_label">{{ __('Previous Balance Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="previous_balance_label" name="previous_balance_label" placeholder="{{ __('Previous Balance') }}" value="{{ $invoiceConfig['previous_balance_label'] ?? 'Previous Balance' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="previous_balance_label_arabic">{{ __('Previous Balance Label Arabic') }}</label>
                        <input type="text" class="form-control" id="previous_balance_label_arabic" name="previous_balance_label_arabic" placeholder="{{ __('Previous Balance Arabic') }}" value="{{ $invoiceConfig['previous_balance_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="paid_amount_label">{{ __('Paid Amount Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="paid_amount_label" name="paid_amount_label" placeholder="{{ __('Paid Amount') }}" value="{{ $invoiceConfig['paid_amount_label'] ?? 'Paid Amount' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="paid_amount_label_arabic">{{ __('Paid Amount Label Arabic') }}</label>
                        <input type="text" class="form-control" id="paid_amount_label_arabic" name="paid_amount_label_arabic" placeholder="{{ __('Paid Amount Arabic') }}" value="{{ $invoiceConfig['paid_amount_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="due_amount_label">{{ __('Due Amount Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="due_amount_label" name="due_amount_label" placeholder="{{ __('Due Amount') }}" value="{{ $invoiceConfig['due_amount_label'] ?? 'Due Amount' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="due_amount_label_arabic">{{ __('Due Amount Label Arabic') }}</label>
                        <input type="text" class="form-control" id="due_amount_label_arabic" name="due_amount_label_arabic" placeholder="{{ __('Due Amount Arabic') }}" value="{{ $invoiceConfig['due_amount_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="due_receive_label">{{ __('Due Receive Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="due_receive_label" name="due_receive_label" placeholder="{{ __('Due Receive') }}" value="{{ $invoiceConfig['due_receive_label'] ?? 'Due Receive' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="due_receive_label_arabic">{{ __('Due Receive Label Arabic') }}</label>
                        <input type="text" class="form-control" id="due_receive_label_arabic" name="due_receive_label_arabic" placeholder="{{ __('Due Receive Arabic') }}" value="{{ $invoiceConfig['due_receive_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="advance_receive_label">{{ __('Advance Receive Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="advance_receive_label" name="advance_receive_label" placeholder="{{ __('Advance Receive') }}" value="{{ $invoiceConfig['advance_receive_label'] ?? 'Advance Receive' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="advance_receive_label_arabic">{{ __('Advance Receive Label Arabic') }}</label>
                        <input type="text" class="form-control" id="advance_receive_label_arabic" name="advance_receive_label_arabic" placeholder="{{ __('Advance Receive Arabic') }}" value="{{ $invoiceConfig['advance_receive_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="given_amount_label">{{ __('Given Amount Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="given_amount_label" name="given_amount_label" placeholder="{{ __('Given Amount') }}" value="{{ $invoiceConfig['given_amount_label'] ?? 'Given Amount' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="given_amount_label_arabic">{{ __('Given Amount Label Arabic') }}</label>
                        <input type="text" class="form-control" id="given_amount_label_arabic" name="given_amount_label_arabic" placeholder="{{ __('Given Amount Arabic') }}" value="{{ $invoiceConfig['given_amount_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="change_amount_label">{{ __('Change Amount Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="change_amount_label" name="change_amount_label" placeholder="{{ __('Change Amount') }}" value="{{ $invoiceConfig['change_amount_label'] ?? 'Change Amount' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="change_amount_label_arabic">{{ __('Change Amount Label Arabic') }}</label>
                        <input type="text" class="form-control" id="change_amount_label_arabic" name="change_amount_label_arabic" placeholder="{{ __('Change Amount Arabic') }}" value="{{ $invoiceConfig['change_amount_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="servicing_charge_label">{{ __('Servicing Charge Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="servicing_charge_label" name="servicing_charge_label" placeholder="{{ __('Servicing Charge') }}" value="{{ $invoiceConfig['servicing_charge_label'] ?? 'Servicing Charge' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="servicing_charge_label_arabic">{{ __('Servicing Charge Label Arabic') }}</label>
                        <input type="text" class="form-control" id="servicing_charge_label_arabic" name="servicing_charge_label_arabic" placeholder="{{ __('Servicing Charge Arabic') }}" value="{{ $invoiceConfig['servicing_charge_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="show_payment_method">{{ __('Show Payment Method') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="show_payment_method" name="show_payment_method" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="Yes" {{ ($invoiceConfig['show_payment_method'] ?? 'Yes') == 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="No" {{ ($invoiceConfig['show_payment_method'] ?? '') == 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="payment_method_label">{{ __('Payment Method Label') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="payment_method_label" name="payment_method_label" placeholder="{{ __('Payment Method') }}" value="{{ $invoiceConfig['payment_method_label'] ?? 'Payment Method' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="payment_method_label_arabic">{{ __('Payment Method Label Arabic') }}</label>
                        <input type="text" class="form-control" id="payment_method_label_arabic" name="payment_method_label_arabic" placeholder="{{ __('Payment Method Arabic') }}" value="{{ $invoiceConfig['payment_method_label_arabic'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="show_hsn_code">{{ __('Show HSN Code') }}</label>
                        <select class="select2 form-select" id="show_hsn_code" name="show_hsn_code" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="Yes" {{ ($invoiceConfig['show_hsn_code'] ?? 'No') == 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="No" {{ ($invoiceConfig['show_hsn_code'] ?? 'No') == 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                        </select>
                        <div class="form-text">{{ __('Show HSN Code in invoice when GST is enabled') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-6">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('Product Details Section') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="show_brand">{{ __('Show Brand') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="show_brand" name="show_brand" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="Yes" {{ ($invoiceConfig['show_brand'] ?? 'No') == 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="No" {{ ($invoiceConfig['show_brand'] ?? 'No') == 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="show_product_code">{{ __('Show Product Code') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="show_product_code" name="show_product_code" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="Yes" {{ ($invoiceConfig['show_product_code'] ?? 'No') == 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="No" {{ ($invoiceConfig['show_product_code'] ?? 'No') == 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="show_product_imei_serial_number">{{ __('Show Product IMEI/Serial Number') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="show_product_imei_serial_number" name="show_product_imei_serial_number" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="Yes" {{ ($invoiceConfig['show_product_imei_serial_number'] ?? 'No') == 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="No" {{ ($invoiceConfig['show_product_imei_serial_number'] ?? 'No') == 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="show_product_image">{{ __('Show Product Image') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="show_product_image" name="show_product_image" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="Yes" {{ ($invoiceConfig['show_product_image'] ?? 'No') == 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="No" {{ ($invoiceConfig['show_product_image'] ?? 'No') == 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="show_warranty_period">{{ __('Show Warranty Period') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="show_warranty_period" name="show_warranty_period" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="Yes" {{ ($invoiceConfig['show_warranty_period'] ?? 'Yes') == 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="No" {{ ($invoiceConfig['show_warranty_period'] ?? '') == 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="show_warranty_expiry_date">{{ __('Show Warranty Expiry Date') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="show_warranty_expiry_date" name="show_warranty_expiry_date" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="Yes" {{ ($invoiceConfig['show_warranty_expiry_date'] ?? 'Yes') == 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="No" {{ ($invoiceConfig['show_warranty_expiry_date'] ?? 'Yes') == 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="show_guarantee_period">{{ __('Show Guarantee Period') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="show_guarantee_period" name="show_guarantee_period" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="Yes" {{ ($invoiceConfig['show_guarantee_period'] ?? 'Yes') == 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="No" {{ ($invoiceConfig['show_guarantee_period'] ?? '') == 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="show_guarantee_expiry_date">{{ __('Show Guarantee Expiry Date') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="show_guarantee_expiry_date" name="show_guarantee_expiry_date" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="Yes" {{ ($invoiceConfig['show_guarantee_expiry_date'] ?? 'Yes') == 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="No" {{ ($invoiceConfig['show_guarantee_expiry_date'] ?? 'Yes') == 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="show_total_in_words">{{ __('Show Total in Words') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="show_total_in_words" name="show_total_in_words" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="Yes" {{ ($invoiceConfig['show_total_in_words'] ?? 'Yes') == 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="No" {{ ($invoiceConfig['show_total_in_words'] ?? '') == 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="word_format">{{ __('Word Format') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="word_format" name="word_format" data-placeholder="{{ __('Select') }} {{ __('Format') }}">
                            <option value=""></option>
                            <option value="International" {{ ($invoiceConfig['word_format'] ?? 'Indian') == 'International' ? 'selected' : '' }}>{{ __('International') }}</option>
                            <option value="Indian" {{ ($invoiceConfig['word_format'] ?? 'Indian') == 'Indian' ? 'selected' : '' }}>{{ __('Indian') }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-6">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('Footer') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="term_conditions_editor">{{ __('Invoice Terms & Conditions') }}</label>
                        <div id="term_conditions_toolbar" class="border rounded-top">
                            <span class="ql-formats">
                                <button type="button" class="ql-bold"></button>
                                <button type="button" class="ql-italic"></button>
                                <button type="button" class="ql-underline"></button>
                                <button type="button" class="ql-strike"></button>
                            </span>
                            <span class="ql-formats">
                                <button type="button" class="ql-list" value="ordered"></button>
                                <button type="button" class="ql-list" value="bullet"></button>
                            </span>
                            <span class="ql-formats">
                                <button type="button" class="ql-link"></button>
                            </span>
                        </div>
                        <div id="term_conditions_editor" class="invoice-quill-editor border border-top-0 rounded-bottom" style="min-height: 200px;"></div>
                        <textarea name="term_conditions" id="term_conditions" class="d-none">{{ $company->term_conditions ?? '' }}</textarea>
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="invoice_footer_editor">{{ __('Invoice Footer') }}</label>
                        <div id="invoice_footer_toolbar" class="border rounded-top">
                            <span class="ql-formats">
                                <button type="button" class="ql-bold"></button>
                                <button type="button" class="ql-italic"></button>
                                <button type="button" class="ql-underline"></button>
                                <button type="button" class="ql-strike"></button>
                            </span>
                            <span class="ql-formats">
                                <button type="button" class="ql-list" value="ordered"></button>
                                <button type="button" class="ql-list" value="bullet"></button>
                            </span>
                            <span class="ql-formats">
                                <button type="button" class="ql-link"></button>
                            </span>
                        </div>
                        <div id="invoice_footer_editor" class="invoice-quill-editor border border-top-0 rounded-bottom" style="min-height: 200px;"></div>
                        <textarea name="invoice_footer" id="invoice_footer" class="d-none">{{ $company->invoice_footer ?? '' }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-6">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('QR Code Options') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6 col-lg-3 validate_wrapper">
                        <label class="form-label mb-1" for="qr_code_option">{{ __('QR Code Option') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="qr_code_option" name="qr_code_option" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="ZATCA QR Code" {{ ($invoiceConfig['qr_code_option'] ?? 'ZATCA QR Code') == 'ZATCA QR Code' ? 'selected' : '' }}>{{ __('ZATCA QR Code') }}</option>
                            <option value="Regular QR Code" {{ ($invoiceConfig['qr_code_option'] ?? '') == 'Regular QR Code' ? 'selected' : '' }}>{{ __('Regular QR Code') }}</option>
                        </select>
                    </div>

                    <div class="col-12 d-none">
                        <h6 class="mb-3">{{ __('Regular QR Code Information') }}</h6>
                        <div class="row g-3">
                            <div class="col-md-6 col-lg-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="qr_code_business" name="qr_code_business" value="Yes" {{ ($invoiceConfig['qr_code_business'] ?? null) == 'Yes' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="qr_code_business">{{ __('QR Code Business') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="qr_code_address" name="qr_code_address" value="Yes" {{ ($invoiceConfig['qr_code_address'] ?? null) == 'Yes' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="qr_code_address">{{ __('QR Code Address') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="qr_code_taxnumber" name="qr_code_taxnumber" value="Yes" {{ ($invoiceConfig['qr_code_taxnumber'] ?? null) == 'Yes' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="qr_code_taxnumber">{{ __('QR Code Tax Number') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="qr_code_invoice_no" name="qr_code_invoice_no" value="Yes" {{ ($invoiceConfig['qr_code_invoice_no'] ?? null) == 'Yes' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="qr_code_invoice_no">{{ __('QR Code Invoice No') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="qr_code_invoice_date_time" name="qr_code_invoice_date_time" value="Yes" {{ ($invoiceConfig['qr_code_invoice_date_time'] ?? null) == 'Yes' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="qr_code_invoice_date_time">{{ __('QR Code Invoice Date Time') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="qr_code_subtotal" name="qr_code_subtotal" value="Yes" {{ ($invoiceConfig['qr_code_subtotal'] ?? null) == 'Yes' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="qr_code_subtotal">{{ __('QR Code Subtotal') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="qr_code_charge" name="qr_code_charge" value="Yes" {{ ($invoiceConfig['qr_code_charge'] ?? null) == 'Yes' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="qr_code_charge">{{ __('QR Code Charge') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="qr_code_tax" name="qr_code_tax" value="Yes" {{ ($invoiceConfig['qr_code_tax'] ?? null) == 'Yes' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="qr_code_tax">{{ __('QR Code Tax') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="qr_code_total_payable" name="qr_code_total_payable" value="Yes" {{ ($invoiceConfig['qr_code_total_payable'] ?? null) == 'Yes' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="qr_code_total_payable">QR Code Total Payable</label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="qr_code_customer_name" name="qr_code_customer_name" value="Yes" {{ ($invoiceConfig['qr_code_customer_name'] ?? null) == 'Yes' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="qr_code_customer_name">QR Code Customer Name</label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="qr_code_invoice_url" name="qr_code_invoice_url" value="Yes" {{ ($invoiceConfig['qr_code_invoice_url'] ?? null) == 'Yes' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="qr_code_invoice_url">QR Code Invoice URL</label>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="qr_code_outlet" name="qr_code_outlet" value="Yes" {{ ($invoiceConfig['qr_code_outlet'] ?? null) == 'Yes' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="qr_code_outlet">QR Code Outlet</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-4">
            <button type="submit" class="btn btn-primary waves-effect waves-light invoice_setting_submit">
                <i class="ti tabler-checkbox me-1"></i>
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</div>