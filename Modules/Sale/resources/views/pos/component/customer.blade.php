<div class="modal fade" id="modal_pos_customer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <form id="posCustomerForm">
                <input type="hidden" id="pos_customer_id" name="customer_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="pos_customer_modal_title">{{ __('Add') }} {{ __('Customer') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 col-md-6 col-lg-4 mb-4 validate_wrapper">
                            <label for="pos_customer_name" class="form-label">{{ __('Name') }} {!! requiredField() !!}</label>
                            <input type="text" id="pos_customer_name" name="name" class="form-control" placeholder="{{ __('Enter Customer Name') }}" />
                        </div>
                        <div class="col-12 col-md-6 col-lg-4 mb-4 validate_wrapper">
                            <label for="pos_customer_phone" class="form-label">{{ __('Phone') }} {!! requiredField() !!}</label>
                            <input type="text" id="pos_customer_phone" name="phone" class="form-control" placeholder="{{ __('Enter Phone Number') }}" />
                        </div>
                        <div class="col-12 col-md-6 col-lg-4 mb-4 validate_wrapper">
                            <label for="pos_customer_email" class="form-label">{{ __('Email') }}</label>
                            <input type="text" id="pos_customer_email" name="email" class="form-control" placeholder="{{ __('Enter Email Address') }}" />
                        </div>
                        <div class="col-12 col-md-6 col-lg-4 mb-4 validate_wrapper d-none">
                            <label for="pos_customer_type" class="form-label">{{ __('customer_type') }}</label>
                            <select id="pos_customer_type" name="customer_type" class="select2 form-select" data-placeholder="{{ __('Select') }} {{ __('customer_type') }}">
                                <option value="1">{{ __('Retail') }}</option>
                                <option value="2">{{ __('Wholesale') }}</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-lg-4 mb-4 validate_wrapper">
                            <label class="form-label" for="opening_balance">{{ __('opening_balance') }}</label>
                            <div class="d-flex jsutify-content-between w-100">
                                <div class="me-1 flex-grow-1 w-50">
                                    <input type="text" class="form-control number-input" id="pos_customer_opening_balance"
                                        placeholder="{{ __('opening_balance') }}" name="opening_balance" 
                                        value="" />
                                </div>
                                <div class="flex-grow-1 w-50">
                                    <select class="w-100 form-select select2 form-select" 
                                        name="opening_balance_type" id="pos_customer_opening_balance_type" data-placeholder="{{ __('opening_balance_type') }}">
                                        <option value="">{{ __('opening_balance_type') }}</option>
                                        <option value="Debit">
                                            {{ __('Debit') }}
                                        </option>
                                        <option value="Credit">
                                            {{ __('Credit') }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-md-6 col-lg-4 mb-4 validate_wrapper">
                            <label for="pos_customer_credit_limit" class="form-label">{{ __('Credit_Limit') }}</label>
                            <input type="text" class="form-control number-input" id="pos_customer_credit_limit" name="credit_limit" placeholder="{{ __('Credit_Limit') }}" value="0" />
                        </div>
                        <div class="col-12 col-md-6 col-lg-4 mb-4 validate_wrapper d-none">
                            <label for="pos_customer_discount" class="form-label">{{ __('Default_Discount') }}</label>
                            <input type="text" class="form-control number-input" id="pos_customer_discount" name="discount" placeholder="{{ __('Default_Discount') }}" value="0" />
                        </div>

                        <div class="col-12 col-md-6 col-lg-4 mb-4 validate_wrapper">
                            <label for="pos_customer_business_type" class="form-label">{{ __('Business_Type') }} {!! requiredField() !!}</label>
                            <select id="pos_customer_business_type" name="business_type" class="select2 form-select" data-placeholder="{{ __('Business_Type') }}">
                                <option value="B2B">{{ __('B2B') }}</option>
                                <option value="B2C" selected>{{ __('B2C') }}</option>
                            </select>
                        </div>

                        @if(isEnableGST() && session('company.collect_tax') == 'Yes')
                        <div class="col-12 col-md-6 col-lg-4 mb-4 validate_wrapper">
                            <div class="flex-grow-1">
                                <label class="form-label" for="pos_customer_same_or_diff_state">{{ __('same_or_diff_state') }} {!! requiredField() !!}</label>
                                <select class="w-100 form-select select2 form-select" 
                                    name="same_or_diff_state" id="pos_customer_same_or_diff_state" data-placeholder="{{ __('same_or_diff_state') }}">
                                    <option value="1">
                                        {{ __('Same_State') }}
                                    </option>
                                    <option value="2">
                                        {{ __('Different_State') }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4 mb-4 validate_wrapper">
                            <div class="flex-grow-1">
                                <label class="form-label" for="pos_customer_state_id">{{ __('State') }} {!! requiredField() !!}</label>
                                <select class="w-100 form-select select2 form-select" 
                                    name="state_id" id="pos_customer_state_id" data-placeholder="{{ __('State') }}">
                                    <option value="">{{ __('Select') }} {{ __('State') }}</option>
                                    @foreach($states ?? [] as $state)
                                        <option value="{{ $state->id }}">{{ $state->state_name }} ({{ $state->state_code }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4 mb-4 validate_wrapper">
                            <label class="form-label" for="pos_customer_gst_number">{{ __('GSTIN') }}<span class="pos-gstin-required text-danger" style="display:none">*</span></label>
                            <input type="text" class="form-control" 
                                placeholder="{{ __('GSTIN') }}" name="gst_number" id="pos_customer_gst_number" 
                                value="" />
                        </div>
                        @endif

                        <div class="col-12 col-md-6 col-lg-4 mb-4 validate_wrapper">
                            <label for="pos_customer_date_of_birth" class="form-label">{{ __('date_of_birth') }}</label>
                            <input type="text" id="pos_customer_date_of_birth" name="date_of_birth" class="form-control datePicker" placeholder="{{ __('Select') }} {{ __('date_of_birth') }}" />
                        </div>
                        <div class="col-12 col-md-6 col-lg-4 mb-4 validate_wrapper">
                            <label for="pos_customer_date_of_anniversary" class="form-label">{{ __('date_of_anniversary') }}</label>
                            <input type="text" id="pos_customer_date_of_anniversary" name="date_of_anniversary" class="form-control datePicker" placeholder="{{ __('Select') }} {{ __('date_of_anniversary') }}" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary pos_customer_submit">
                        {!! submitIconWithText('') !!}
                    </button>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                        {!! closeIconWithText() !!}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>