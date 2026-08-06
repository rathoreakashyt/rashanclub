<div class="tab-pane fade {{ request()->route('tab') == 'pos_setting' ? 'active show' : '' }}" id="pos_setting" role="tabpanel">
    <form id="pos_setting_form">
        <div class="card mb-6">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('POS Setting') }}</h5>
            </div>
            <div class="card-body">
                <div class="row mb-6 g-6">
                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="allow_less_sale">{{ __('Allow Less Sale') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="allow_less_sale" name="allow_less_sale" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="Yes" {{ $company->allow_less_sale == 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="No" {{ $company->allow_less_sale == 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="default_customer">{{ __('Default Customer') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="default_customer" name="default_customer" data-placeholder="{{ __('Select') }} {{ __('Customer') }}">
                            <option value=""></option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" {{ $company->default_customer == $customer->id ? 'selected' : '' }}>
                                    {{ $customer->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="default_payment">{{ __('Default Payment') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="default_payment" name="default_payment" data-placeholder="{{ __('Select') }} {{ __('Payment') }}">
                            <option value=""></option>
                            @foreach($payment_methods as $payment_method)
                                <option value="{{ $payment_method->id }}" {{ $company->default_payment == $payment_method->id ? 'selected' : '' }}>
                                    {{ $payment_method->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="pos_total_payable_type">{{ __('POS Total Payable Type') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="pos_total_payable_type" name="pos_total_payable_type" data-placeholder="{{ __('Select') }} {{ __('Type') }}">
                            <option value=""></option>
                            <option value="0" {{ $company->pos_total_payable_type == '0' ? 'selected' : '' }}>{{ __('None') }}</option>
                            <option value="1" {{ $company->pos_total_payable_type == '1' ? 'selected' : '' }}>{{ __('Round to nearest whole number') }}</option>
                            <option value="0.05" {{ $company->pos_total_payable_type == '0.05' ? 'selected' : '' }}>{{ __('Round to nearest decimal (multiple of 0.05)') }}</option>
                            <option value="0.01" {{ $company->pos_total_payable_type == '0.01' ? 'selected' : '' }}>{{ __('Round to nearest decimal (multiple of 0.1)') }}</option>
                            <option value="0.5" {{ $company->pos_total_payable_type == '0.5' ? 'selected' : '' }}>{{ __('Round to nearest decimal (multiple of 0.5)') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="default_cursor_position">{{ __('Default Cursor Position') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="default_cursor_position" name="default_cursor_position" data-placeholder="{{ __('Select') }} {{ __('Position') }}">
                            <option value=""></option>
                            <option value="Search Box" {{ $company->default_cursor_position == 'Search Box' ? 'selected' : '' }}>{{ __('Search Box') }}</option>
                            <option value="Barcode Box" {{ $company->default_cursor_position == 'Barcode Box' ? 'selected' : '' }}>{{ __('Barcode Box') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="product_display">{{ __('Product Display') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="product_display" name="product_display" data-placeholder="{{ __('Select') }} {{ __('Display') }}">
                            <option value=""></option>
                            <option value="Image View" {{ $company->product_display == 'Image View' ? 'selected' : '' }}>{{ __('Image View') }}</option>
                            <option value="Box View" {{ $company->product_display == 'Box View' ? 'selected' : '' }}>{{ __('Box View') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="onscreen_keyboard_status">{{ __('Onscreen Keyboard Status') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="onscreen_keyboard_status" name="onscreen_keyboard_status" data-placeholder="{{ __('Select') }} {{ __('Status') }}">
                            <option value=""></option>
                            <option value="Enable" {{ $company->onscreen_keyboard_status == 'Enable' ? 'selected' : '' }}>{{ __('Enable') }}</option>
                            <option value="Disable" {{ $company->onscreen_keyboard_status == 'Disable' ? 'selected' : '' }}>{{ __('Disable') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="grocery_experience">{{ __('Grocery Experience') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="grocery_experience" name="grocery_experience" data-placeholder="{{ __('Select') }} {{ __('Experience') }}">
                            <option value=""></option>
                            <option value="Regular" {{ $company->grocery_experience == 'Regular' ? 'selected' : '' }}>{{ __('Regular') }}</option>
                            <option value="Medicine" {{ $company->grocery_experience == 'Medicine' ? 'selected' : '' }}>{{ __('Medicine') }}</option>
                            <option value="Grocery" {{ $company->grocery_experience == 'Grocery' ? 'selected' : '' }}>{{ __('Grocery') }}</option>
                        </select>
                    </div>


                    <!-- SMTP Default Selected in POS -->
                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1">{{ __('SMTP Default Selected in POS') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="smtp_default_selected_in_pos" name="smtp_default_selected_in_pos" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="Yes" {{ $company->smtp_default_selected_in_pos == 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="No" {{ $company->smtp_default_selected_in_pos == 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1">{{ __('SMS Default Selected in POS') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="sms_default_selected_in_pos" name="sms_default_selected_in_pos" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="Yes" {{ $company->sms_default_selected_in_pos == 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="No" {{ $company->sms_default_selected_in_pos == 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                        </select>
                    </div>

                    <!-- whatsapp default selected in pos -->
                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1">{{ __('Whatsapp Default Selected in POS') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="whatsapp_default_selected_in_pos" name="whatsapp_default_selected_in_pos" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="Yes" {{ $company->whatsapp_default_selected_in_pos == 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="No" {{ $company->whatsapp_default_selected_in_pos == 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                        </select>
                    </div>
                    
                    <div class="clear-fix"></div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1">{{ __('Direct Cart') }} {!! requiredField() !!}</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="direct_cart" id="direct_cart_yes" value="Yes" {{ $company->direct_cart == 'Yes' ? 'checked' : '' }}>
                            <label class="form-check-label" for="direct_cart_yes">{{ __('Yes') }}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="direct_cart" id="direct_cart_no" value="No" {{ $company->direct_cart == 'No' ? 'checked' : '' }}>
                            <label class="form-check-label" for="direct_cart_no">{{ __('No') }}</label>
                        </div>
                    </div>

                    <!-- <div class="col-12">
                        @php
                            $register_content = json_decode($company->register_content, true);
                        @endphp
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="register_content" class="form-label mb-1">{{ __('Register Content') }}</label>
                            </div>
                            <div class="col-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="expense" value="Expense" name="register_expense" {{ isset($register_content['register_expense']) && $register_content['register_expense'] == 'Expense' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="expense">{{ __('Expense') }}</label>
                                </div>
                            </div>

                            <div class="col-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="purchase" value="Purchase" name="register_purchase" {{ isset($register_content['register_purchase']) && $register_content['register_purchase'] == 'Purchase' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="purchase">{{ __('Purchase') }}</label>
                                </div>
                            </div>

                            <div class="col-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="purchase_return" value="Purchase Return" name="register_purchase_return" {{ isset($register_content['register_purchase_return']) && $register_content['register_purchase_return'] == 'Purchase Return' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="purchase_return">{{ __('Purchase Return') }}</label>
                                </div>
                            </div>

                            <div class="col-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="supplier_payment" value="Supplier Payment" name="register_supplier_payment" {{ isset($register_content['register_supplier_payment']) && $register_content['register_supplier_payment'] == 'Supplier Payment' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="supplier_payment">{{ __('Supplier Payment') }}</label>
                                </div>
                            </div>

                            <div class="col-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="sale" value="Sale" name="register_sale" {{ isset($register_content['register_sale']) && $register_content['register_sale'] == 'Sale' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="sale">{{ __('Sale') }}</label>
                                </div>
                            </div>

                            <div class="col-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="sale_return" value="Sale Return" name="register_sale_return" {{ isset($register_content['register_sale_return']) && $register_content['register_sale_return'] == 'Sale Return' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="sale_return">{{ __('Sale Return') }}</label>
                                </div>
                            </div>

                            <div class="col-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="installment_down_payment" value="Installment Down Payment" name="register_installment_down_payment" {{ isset($register_content['register_installment_down_payment']) && $register_content['register_installment_down_payment'] == 'Installment Down Payment' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="installment_down_payment">{{ __('Installment Down Payment') }}</label>
                                </div>
                            </div>

                            <div class="col-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="installment_collection" value="Installment Collection" name="register_installment_collection" {{ isset($register_content['register_installment_collection']) && $register_content['register_installment_collection'] == 'Installment Collection' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="installment_collection">{{ __('Installment Collection') }}</label>
                                </div>
                            </div>

                            <div class="col-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="customer_due_receive" value="Customer Due Receive" name="register_customer_due_receive" {{ isset($register_content['register_customer_due_receive']) && $register_content['register_customer_due_receive'] == 'Customer Due Receive' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="customer_due_receive">{{ __('Customer Due Receive') }}</label>
                                </div>
                            </div>

                            <div class="col-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="servicing" value="Servicing" name="register_servicing" {{ isset($register_content['register_servicing']) && $register_content['register_servicing'] == 'Servicing' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="servicing">Servicing</label>
                                </div>
                            </div>
                        </div>
                    </div> -->

                </div>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-4">
            <button type="submit" name="submit" value="submit" class="btn btn-primary waves-effect waves-light pos_setting_submit">
                <i class="ti tabler-checkbox me-1"></i>
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</div>