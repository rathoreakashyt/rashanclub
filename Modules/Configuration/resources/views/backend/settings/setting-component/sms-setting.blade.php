<div class="tab-pane fade {{ request()->route('tab') == 'sms_setting' ? 'active show' : '' }}" id="sms_setting" role="tabpanel">
    <form id="sms_setting_form">
        @csrf
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('SMS Setting') }}</h5>
            </div>

            <div class="card-body">
                @php 
                $smsDetails = json_decode($company->sms_details ?? '{}', true);
                if (!is_array($smsDetails)) {
                    $smsDetails = [];
                }
                $twilioDetails = isset($smsDetails['twilio']) && is_array($smsDetails['twilio']) ? $smsDetails['twilio'] : [];
                $mobishastraDetails = isset($smsDetails['mobishastra']) && is_array($smsDetails['mobishastra']) ? $smsDetails['mobishastra'] : [];
                $mimSmsDetails = isset($smsDetails['mim_sms']) && is_array($smsDetails['mim_sms']) ? $smsDetails['mim_sms'] : [];
                $textLocalDetails = isset($smsDetails['text_local']) && is_array($smsDetails['text_local']) ? $smsDetails['text_local'] : [];
                @endphp

                <div class="row mb-6 g-6">
                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="sms_enable_status">{{ __('SMS Status') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="sms_enable_status" name="sms_enable_status" data-placeholder="{{ __('Select') }} {{ __('Status') }}">
                            <option value=""></option>
                            <option value="1" {{ $company->sms_enable_status == '1' ? 'selected' : '' }}>{{ __('Enable') }}</option>
                            <option value="2" {{ $company->sms_enable_status == '2' ? 'selected' : '' }}>{{ __('Disable') }}</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-6 g-6 sms_service_wrap">
                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="sms_service_provider">{{ __('SMS Service Provider') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="sms_service_provider" name="sms_service_provider" data-placeholder="{{ __('Select') }} {{ __('Provider') }}">
                            <option value="None">{{ __('None') }}</option>
                            <option value="1" {{ $company->sms_service_provider == '1' ? 'selected' : '' }}>{{ __('Twillio') }}</option>
                            <option value="2" {{ $company->sms_service_provider == '2' ? 'selected' : '' }}>{{ __('Mobishastra') }}</option>
                            <option value="3" {{ $company->sms_service_provider == '3' ? 'selected' : '' }}>{{ __('MiMSMS') }}</option>
                            <option value="4" {{ $company->sms_service_provider == '4' ? 'selected' : '' }}>{{ __('Text Local') }}</option>
                        </select>
                    </div>
                    <!-- Twilio -->
                    <div class="col-12 col-md-6 validate_wrapper provider-1-fields d-none">
                        <label class="form-label mb-1" for="twilio_sid">{{ __('SID') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="twilio_sid" name="twilio_sid" placeholder="{{ __('Enter') }} {{ __('Twilio SID') }}" value="{{ $twilioDetails['sid'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-6 validate_wrapper provider-1-fields d-none">
                        <label class="form-label mb-1" for="twilio_token">{{ __('Token') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="twilio_token" name="twilio_token" placeholder="{{ __('Enter') }} {{ __('Twilio Token') }}" value="{{ $twilioDetails['token'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-6 validate_wrapper provider-1-fields d-none">
                        <label class="form-label mb-1" for="twilio_number">{{ __('Twilio Number') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="twilio_number" name="twilio_number" placeholder="{{ __('Enter') }} {{ __('Twilio Number') }}" value="{{ $twilioDetails['number'] ?? '' }}">
                    </div>
                    <!-- Mobishastra -->
                    <div class="col-12 col-md-6 validate_wrapper provider-2-fields d-none">
                        <label class="form-label mb-1" for="mobishastra_profile_id">{{ __('Profile ID') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="mobishastra_profile_id" name="mobishastra_profile_id" placeholder="{{ __('Enter') }} {{ __('Mobishastra Profile ID') }}" value="{{ $mobishastraDetails['profile_id'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-6 validate_wrapper provider-2-fields d-none">
                        <label class="form-label mb-1" for="mobishastra_password">{{ __('Password') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="mobishastra_password" name="mobishastra_password" placeholder="{{ __('Enter') }} {{ __('Mobishastra Password') }}" value="{{ $mobishastraDetails['password'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-6 validate_wrapper provider-2-fields d-none">
                        <label class="form-label mb-1" for="mobishastra_sender_id">{{ __('Sender ID') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="mobishastra_sender_id" name="mobishastra_sender_id" placeholder="{{ __('Enter') }} {{ __('Mobishastra Sender ID') }}" value="{{ $mobishastraDetails['sender_id'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-6 validate_wrapper provider-2-fields d-none">
                        <label class="form-label mb-1" for="mobishastra_country_code">{{ __('Country Code') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="mobishastra_country_code" name="mobishastra_country_code" placeholder="{{ __('Enter') }} {{ __('Mobishastra Country Code') }}" value="{{ $mobishastraDetails['country_code'] ?? '' }}">
                    </div>
                    <!-- Mim SMS -->
                    <div class="col-12 col-md-6 validate_wrapper provider-3-fields d-none">
                        <label class="form-label mb-1" for="mim_sms_api_key">{{ __('API Key') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="mim_sms_api_key" name="mim_sms_api_key" placeholder="{{ __('Enter') }} {{ __('Mim SMS API Key') }}" value="{{ $mimSmsDetails['api_key'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-6 validate_wrapper provider-3-fields d-none">
                        <label class="form-label mb-1" for="mim_sms_sender_id">{{ __('Sender ID') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="mim_sms_sender_id" name="mim_sms_sender_id" placeholder="{{ __('Enter') }} {{ __('Mim SMS Sender ID') }}" value="{{ $mimSmsDetails['sender_id'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-6 validate_wrapper provider-3-fields d-none">
                        <label class="form-label mb-1" for="mim_sms_username">{{ __('Username') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="mim_sms_username" name="mim_sms_username" placeholder="{{ __('Enter') }} {{ __('Mim SMS Username') }}" value="{{ $mimSmsDetails['username'] ?? '' }}">
                    </div>

                    <!-- Text Local -->
                    <div class="col-12 col-md-6 validate_wrapper provider-4-fields d-none">
                        <label class="form-label mb-1" for="text_local_profile_id">{{ __('Profile ID') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="text_local_profile_id" name="text_local_profile_id" placeholder="{{ __('Enter') }} {{ __('Text Local Profile ID') }}" value="{{ $textLocalDetails['profile_id'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-6 validate_wrapper provider-4-fields d-none">
                        <label class="form-label mb-1" for="text_local_api_key">{{ __('API Key') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="text_local_api_key" name="text_local_api_key" placeholder="{{ __('Enter') }} {{ __('Text Local API Key') }}" value="{{ $textLocalDetails['api_key'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-6 validate_wrapper provider-4-fields d-none">
                        <label class="form-label mb-1" for="text_local_sender_id">{{ __('Sender ID') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="text_local_sender_id" name="text_local_sender_id" placeholder="{{ __('Enter') }} {{ __('Text Local Sender ID') }}" value="{{ $textLocalDetails['sender_id'] ?? '' }}">
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-4">
            <button id="testSMSBtn" class="btn btn-secondary me-2 test-sms-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#testSMSOffcanvas" aria-controls="testSMSOffcanvas" disabled>
                <i class="ti tabler-message-2 me-1"></i>
                {{ __('Test SMS') }}
            </button>
            <button type="submit" class="btn btn-primary waves-effect waves-light sms_setting_submit">
                <i class="ti tabler-checkbox me-1"></i>
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</div>