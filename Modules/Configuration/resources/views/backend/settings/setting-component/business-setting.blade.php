<div class="tab-pane fade {{ request()->route('tab') == 'business_setting' ? 'active show' : '' }}" id="business_setting" role="tabpanel">
    <form id="business_setting_form">
        <div class="card mb-6">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('Business Setting') }}</h5>
            </div>
            <div class="card-body">
                <div class="row mb-6 g-6">
                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="business_name">{{ __('Business Name') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="business_name" name="business_name" placeholder="{{ __('Enter') }} {{ __('Business Name') }}" value="{{ $company->business_name }}">
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="address">{{ __('Address') }} {!! requiredField() !!}</label>
                        <textarea class="form-control" id="address" name="address" rows="3" placeholder="{{ __('Enter') }} {{ __('Address') }}">{{ $company->address }}</textarea>
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="website">{{ __('Website') }}</label>
                        <div class="d-flex gap-2">
                            <input type="url" class="form-control" id="website" name="website" placeholder="{{ __('Enter') }} {{ __('Website') }}" value="{{ $company->website }}">
                            @if($company->website)
                                <a href="{{ $company->website }}" target="_blank" class="btn btn-sm btn-primary preview-website d-flex gap-2 align-items-center">
                                    <i class="icon-base ti tabler-world"></i>
                                </a>
                            @else
                                <a href="#" target="_blank" class="btn btn-sm btn-primary preview-website d-flex gap-2 align-items-center" style="display: none;">
                                    <i class="icon-base ti tabler-world"></i>
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="email">{{ __('Email') }} {!! requiredField() !!}</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="{{ __('Enter') }} {{ __('Email') }}" value="{{ $company->email }}">
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="phone">{{ __('Phone') }} {!! requiredField() !!}</label>
                        <input type="tel" class="form-control phone-mask" id="phone" name="phone" placeholder="{{ __('Enter') }} {{ __('Phone') }}" value="{{ $company->phone }}">
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="date_format">{{ __('Date Format') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="date_format" name="date_format" data-placeholder="{{ __('Select') }} {{ __('Date Format') }}">
                            <option value=""></option>
                            <option value="d/m/Y" {{ $company->date_format == 'd/m/Y' ? 'selected' : '' }}>d/m/Y</option>
                            <option value="m/d/Y" {{ $company->date_format == 'm/d/Y' ? 'selected' : '' }}>m/d/Y</option>
                            <option value="Y/m/d" {{ $company->date_format == 'Y/m/d' ? 'selected' : '' }}>Y/m/d</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="zone_name">{{ __('Zone Name') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="zone_name" name="zone_name" data-placeholder="{{ __('Select') }} {{ __('Zone') }}">
                            <option value=""></option>
                            @foreach($timezones as $timezone)
                                <option value="{{ $timezone->zone_name }}" {{ $company->zone_name == $timezone->zone_name ? 'selected' : '' }}>{{ $timezone->zone_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="currency">{{ __('Currency') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="currency" name="currency" placeholder="{{ __('Enter') }} {{ __('Currency') }}" value="{{ $company->currency }}">
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="currency_position">{{ __('Currency Position') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="currency_position" name="currency_position" data-placeholder="{{ __('Select') }} {{ __('Currency Position') }}">
                            <option value=""></option>
                            <option value="Before Amount" {{ $company->currency_position == 'Before Amount' ? 'selected' : '' }}>{{ __('Before Amount') }}</option>
                            <option value="After Amount" {{ $company->currency_position == 'After Amount' ? 'selected' : '' }}>{{ __('After Amount') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="precision">{{ __('Precision') }}</label>
                        <select class="select2 form-select" id="precision" name="precision" data-placeholder="{{ __('Select') }} {{ __('Precision') }}">
                            <option value=""></option>
                            <option value="1" {{ $company->precision == '1' ? 'selected' : '' }}>{{ __('1 Digit') }}</option>
                            <option value="2" {{ $company->precision == '2' ? 'selected' : '' }}>{{ __('2 Digit') }}</option>
                            <option value="3" {{ $company->precision == '3' ? 'selected' : '' }}>{{ __('3 Digit') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="thousands_separator">{{ __('Thousand Separator') }}</label>
                        <select class="select2 form-select" id="thousands_separator" name="thousands_separator" data-placeholder="{{ __('Select') }} {{ __('Thousand Separator') }}">
                            <option value=""></option>
                            <option value="," {{ $company->thousands_separator == ',' ? 'selected' : '' }}>{{ __('Comma') }} (,)</option>
                            <option value="." {{ $company->thousands_separator == '.' ? 'selected' : '' }}>{{ __('Dot') }} (.)</option>
                            <option value="space" {{ $company->thousands_separator == 'space' ? 'selected' : '' }}>{{ __('Space') }} ( )</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="decimals_separator">{{ __('Decimal Separator') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="decimals_separator" name="decimals_separator" data-placeholder="{{ __('Select') }} {{ __('Decimal Separator') }}">
                            <option value=""></option>
                            <option value="." {{ $company->decimals_separator == '.' ? 'selected' : '' }}>{{ __('Dot') }} (.)</option>
                            <option value="," {{ $company->decimals_separator == ',' ? 'selected' : '' }}>{{ __('Comma') }} (,)</option>
                            <option value="space" {{ $company->decimals_separator == 'space' ? 'selected' : '' }}>{{ __('Space') }} ( )</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="installment_days">{{ __('Installment Days') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="installment_days" name="installment_days" data-placeholder="{{ __('Select') }} {{ __('Installment Days') }}">
                            <option value=""></option>
                            <option value="3" {{ $company->installment_days == '3' ? 'selected' : '' }}>{{ __('3 Days') }}</option>
                            <option value="7" {{ $company->installment_days == '7' ? 'selected' : '' }}>{{ __('7 Days') }}</option>
                            <option value="15" {{ $company->installment_days == '15' ? 'selected' : '' }}>{{ __('15 Days') }}</option>
                            <option value="30" {{ $company->installment_days == '30' ? 'selected' : '' }}>{{ __('30 Days') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="e_commerce_checker">{{ __('E-Commerce Checker') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="e_commerce_checker" name="e_commerce_checker" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="Yes" {{ $company->e_commerce_checker == 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="No" {{ $company->e_commerce_checker == 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="card mb-6">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('Item Setting') }}</h5>
            </div>
            <div class="card-body">
                <div class="row mb-6 g-6">
                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="is_loyalty_enable">{{ __('Is Loyalty Enable') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="is_loyalty_enable" name="is_loyalty_enable" data-placeholder="{{ __('Select') }} {{ __('Option') }}">
                            <option value=""></option>
                            <option value="Enable" {{ $company->is_loyalty_enable == 'Enable' ? 'selected' : '' }}>{{ __('Enable') }}</option>
                            <option value="Disable" {{ $company->is_loyalty_enable == 'Disable' ? 'selected' : '' }}>{{ __('Disable') }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="minimum_point_to_redeem">{{ __('Minimum Point To Redeem') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control number-input" id="minimum_point_to_redeem" name="minimum_point_to_redeem" placeholder="{{ __('Enter') }} {{ __('Minimum Point To Redeem') }}" value="{{ $company->minimum_point_to_redeem }}">
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="loyalty_rate">{{ __('Loyalty Rate') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control number-input" id="loyalty_rate" name="loyalty_rate" placeholder="{{ __('Enter') }} {{ __('Loyalty Rate') }}" value="{{ $company->loyalty_rate }}">
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="product_code_start_from">{{ __('Product Code Start From') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control number-input" id="product_code_start_from" name="product_code_start_from" placeholder="{{ __('Enter') }} {{ __('Product Code Start From') }}" value="{{ $company->product_code_start_from }}">
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-4">
            <button type="submit" name="submit" value="submit" class="btn btn-primary waves-effect waves-light business_setting_submit">
                <i class="ti tabler-checkbox me-1"></i>
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</div>