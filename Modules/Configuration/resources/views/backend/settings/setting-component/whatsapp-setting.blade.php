
<div class="tab-pane fade {{ request()->route('tab') == 'whatsapp_setting' ? 'active show' : '' }}" id="whatsapp_setting" role="tabpanel">
    <form id="whatsapp_setting_form">
        @csrf
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('WhatsApp Setting') }}</h5>
            </div>
            <div class="card-body">
                <div class="row mb-6 g-6">
                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="whatsapp_invoice_enable_status">{{ __('Enable WhatsApp Invoice') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="whatsapp_invoice_enable_status" name="whatsapp_invoice_enable_status" data-placeholder="{{ __('Select') }} {{ __('Status') }}">
                            <option value="Enable" {{ $company->whatsapp_invoice_enable_status == 'Enable' ? 'selected' : '' }}>{{ __('Enable') }}</option>
                            <option value="Disable" {{ $company->whatsapp_invoice_enable_status == 'Disable' ? 'selected' : '' }}>{{ __('Disable') }}</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-6 g-6 whatsapp_service_wrap">
                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="whatsapp_provider">{{ __('WhatsApp Provider') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="whatsapp_provider" name="whatsapp_provider" data-placeholder="{{ __('Select') }} {{ __('Provider') }}">
                            <option value="None">{{ __('None') }}</option>
                            <option value="RC Soft" {{ ($company->whatsapp_provider ?? '') == 'RC Soft' ? 'selected' : '' }}>{{ __('RC Soft') }}</option>
                            <option value="Twilio" {{ ($company->whatsapp_provider ?? '') == 'Twilio' ? 'selected' : '' }}>{{ __('Twilio') }}</option>
                        </select>
                    </div>

                    <!-- RC Soft Fields -->
                    <div class="col-12 col-md-6 validate_wrapper whatsapp-rcsoft-fields">
                        <label class="form-label mb-1" for="whatsapp_app_key">{{ __('App Key') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="whatsapp_app_key" name="whatsapp_app_key" placeholder="{{ __('Enter') }} {{ __('WhatsApp App Key') }}" value="{{ $company->whatsapp_app_key ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper whatsapp-rcsoft-fields">
                        <label class="form-label mb-1" for="whatsapp_authkey">{{ __('Auth Key') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="whatsapp_authkey" name="whatsapp_authkey" placeholder="{{ __('Enter') }} {{ __('WhatsApp Auth Key') }}" value="{{ $company->whatsapp_authkey ?? '' }}">
                    </div>

                    <!-- Twilio Fields -->
                    <div class="col-12 col-md-6 validate_wrapper whatsapp-twilio-fields d-none">
                        <label class="form-label mb-1" for="whatsapp_account_sid">{{ __('Account SID') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="whatsapp_account_sid" name="whatsapp_account_sid" placeholder="{{ __('Enter') }} {{ __('Twilio Account SID') }}" value="{{ $company->whatsapp_account_sid ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper whatsapp-twilio-fields d-none">
                        <label class="form-label mb-1" for="whatsapp_auth_token">{{ __('Auth Token') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="whatsapp_auth_token" name="whatsapp_auth_token" placeholder="{{ __('Enter') }} {{ __('Twilio Auth Token') }}" value="{{ $company->whatsapp_auth_token ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper whatsapp-twilio-fields d-none">
                        <label class="form-label mb-1" for="whatsapp_from_number">{{ __('From Number') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="whatsapp_from_number" name="whatsapp_from_number" placeholder="{{ __('Enter') }} {{ __('Twilio WhatsApp Number') }}" value="{{ $company->whatsapp_from_number ?? '' }}">
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-4">
            <button id="testWhatsappBtn" class="btn btn-secondary me-2 test-whatsapp-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#testWhatsappOffcanvas" aria-controls="testWhatsappOffcanvas" disabled>
                <i class="ti tabler-brand-whatsapp me-1"></i>
                {{ __('Test WhatsApp') }}
            </button>
            <button type="submit" class="btn btn-primary waves-effect waves-light whatsapp_setting_submit">
                <i class="ti tabler-checkbox me-1"></i>
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</div>