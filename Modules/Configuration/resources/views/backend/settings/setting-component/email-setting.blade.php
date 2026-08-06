<div class="tab-pane fade {{ request()->route('tab') == 'email_setting' ? 'active show' : '' }}" id="email_setting" role="tabpanel">
    <form id="email_setting_form">
        <div class="card mb-6">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('Email Setting') }}</h5>
            </div>
            <div class="card-body">
                @php
                    $smtpDetails = json_decode($company->smtp_details ?? '{}', true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $smtpDetails = [];
                    }
                @endphp

                

                <div class="row mb-6 g-6">
                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="smtp_enable_status">{{ __('SMTP Enable Status') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="smtp_enable_status" name="smtp_enable_status" data-placeholder="{{ __('Select') }} {{ __('Status') }}">
                            <option value="1" {{ $company->smtp_enable_status == '1' ? 'selected' : '' }}>{{ __('Enable') }}</option>
                            <option value="2" {{ $company->smtp_enable_status == '2' ? 'selected' : '' }}>{{ __('Disable') }}</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-6 g-6 email_service_wrap" >
                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="smtp_type">{{ __('SMTP Type') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="smtp_type" name="smtp_type" data-placeholder="{{ __('Select') }} {{ __('SMTP Type') }}">
                            <option value="None">{{ __('None') }}</option>
                            <option value="Gmail" {{ $company->smtp_type == 'Gmail' ? 'selected' : '' }}>{{ __('Gmail') }}</option>
                            <option value="Sendinblue" {{ $company->smtp_type == 'Sendinblue' ? 'selected' : '' }}>{{ __('Sendinblue') }}</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="host_name">{{ __('Host Name') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="host_name" name="host_name" placeholder="{{ __('Enter') }} {{ __('Host Name') }}" value="{{ $smtpDetails['host_name'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="port_address">{{ __('Port Address') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="port_address" name="port_address" placeholder="{{ __('Enter') }} {{ __('Port Address') }}" value="{{ $smtpDetails['port_address'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="encryption">{{ __('Encryption') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="encryption" name="encryption" placeholder="{{ __('Enter') }} {{ __('Encryption') }}" value="{{ $smtpDetails['encryption'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="user_name">{{ __('User Name') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="user_name" name="user_name" placeholder="{{ __('Enter') }} {{ __('User Name') }}" value="{{ $smtpDetails['user_name'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="password">{{ __('Password') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="password" name="password" placeholder="{{ __('Enter') }} {{ __('Password') }}" value="{{ $smtpDetails['password'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="from_name">{{ __('From Name') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="from_name" name="from_name" placeholder="{{ __('Enter') }} {{ __('From Name') }}" value="{{ $smtpDetails['from_name'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="from_email">{{ __('From Email') }} {!! requiredField() !!}</label>
                        <input type="email" class="form-control" id="from_email" name="from_email" placeholder="{{ __('Enter') }} {{ __('From Email') }}" value="{{ $smtpDetails['from_email'] ?? '' }}">
                    </div>
                    <div class="col-12 sendinblue-api-field validate_wrapper" style="display: none;">
                        <label class="form-label mb-1" for="api_key">{{ __('API Key') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="api_key" name="api_key" placeholder="{{ __('Enter') }} {{ __('API Key') }}" value="{{ $smtpDetails['api_key'] ?? '' }}">
                    </div>

                    <div class="col-12 my-4">
                        <div class="alert alert-warning alert-dismissible mb-4" role="alert">
                            <div class="d-flex gap-4">
                                <div class="alert-icon flex-shrink-0 rounded me-0">
                                    <i class="icon-base ti tabler-percentage"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="alert-heading mb-1">{{ __('Guide & Rules Email Setting') }}</h5>
                                    <ul class="list-unstyled mb-0">
                                        <li>1. If you use port 587 then you should to fill up the encryption field with tls or TLS</li>
                                        <li>2. If you use port 465 then you should to fill up the encryption field with ssl or SSL</li>
                                    </ul>
                                </div>
                            </div>
                            <button type="button" class="btn-close btn-pinned" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-4">
            <button id="testEmailBtn" class="btn btn-secondary me-2 test-email-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#testEmailOffcanvas" aria-controls="testEmailOffcanvas" disabled>
                <i class="ti tabler-mail-forward me-1"></i>
                {{ __('Test Email') }}
            </button>
            <button type="submit" class="btn btn-primary waves-effect waves-light email_setting_submit">
                <i class="ti tabler-checkbox me-1"></i>
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</div>