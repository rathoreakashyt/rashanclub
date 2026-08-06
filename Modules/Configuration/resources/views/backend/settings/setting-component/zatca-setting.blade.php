@php
    // Parse ZATCA configuration
    $zatcaConfig = [];
    if (isset($company->zatca_configuration) && $company->zatca_configuration != '') {
        $zatcaConfig = json_decode($company->zatca_configuration, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $zatcaConfig = [];
        }
    }
    
    // Set default values
    $defaults = [
        'zatca_phase' => '0', // 0 = None, 1 = Phase 1, 2 = Phase 2
        'zatca_status' => 'Disable',
        'vat_registration_number' => '',
        'legal_business_name_arabic' => '',
        'legal_business_name_english' => '',
        'zatca_address' => '',
        'compliance_csid' => '',
        'production_csid' => '',
        'zatca_secret_key' => '',
    ];
    $zatcaConfig = array_merge($defaults, $zatcaConfig);
    
    // Get current selected phase (for backward compatibility, check old zatca_1 and zatca_2)
    $selectedPhase = $zatcaConfig['zatca_phase'] ?? '0';
    if ($selectedPhase == '0') {
        // Check old format for backward compatibility
        if (isset($zatcaConfig['zatca_1']) && $zatcaConfig['zatca_1'] == '1') {
            $selectedPhase = '1';
        } elseif (isset($zatcaConfig['zatca_2']) && $zatcaConfig['zatca_2'] == '1') {
            $selectedPhase = '2';
        }
    }
    
    // Check if CSR files exist (using direct path to storage/app/zatca)
    $csrExists = file_exists(storage_path('app/zatca/csr.pem'));
    $privateKeyExists = file_exists(storage_path('app/zatca/private_key.pem'));
@endphp

<div class="tab-pane fade {{ request()->route('tab') == 'zatca_setting' ? 'active show' : '' }}" id="zatca_setting" role="tabpanel">
    <form id="zatca_setting_form" enctype="multipart/form-data">
        @csrf

        <div class="card mb-6">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('ZATCA') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-6">
                    <!-- Radio buttons for ZATCA Phase selection -->
                    <div class="col-12 validate_wrapper">
                        <label class="form-label mb-3">{{ __('Select ZATCA Phase') }} {!! requiredField() !!}</label>
                        <div class="d-flex flex-column gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="zatca_phase" id="zatca_phase_0" value="0" {{ $selectedPhase == '0' ? 'checked' : '' }}>
                                <label class="form-check-label" for="zatca_phase_0">
                                    {{ __('None (Disable ZATCA)') }}
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="zatca_phase" id="zatca_phase_1" value="1" {{ $selectedPhase == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="zatca_phase_1">
                                    <strong>{{ __('ZATCA Phase 1') }}</strong> - {{ __('Basic QR Code Generation (No API Submission)') }}
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="zatca_phase" id="zatca_phase_2" value="2" {{ $selectedPhase == '2' ? 'checked' : '' }}>
                                <label class="form-check-label" for="zatca_phase_2">
                                    <strong>{{ __('ZATCA Phase 2') }}</strong> - {{ __('Full Compliance (API Submission, Digital Signatures, Hash Chain)') }}
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ZATCA Phase 2 Status (Only shown when Phase 2 is selected) -->
        <div class="card mb-6" id="zatca_phase2_status_card" style="display: {{ $selectedPhase == '2' ? 'block' : 'none' }};">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('ZATCA Phase 2 Status') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-6">
                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="zatca_status">{{ __('ZATCA Phase 2 Status') }} {!! requiredField() !!}</label>
                        <select class="select2 form-select" id="zatca_status" name="zatca_status" data-placeholder="{{ __('Select') }} {{ __('Status') }}">
                            <option value=""></option>
                            <option value="Enable" {{ ($zatcaConfig['zatca_status'] ?? 'Disable') == 'Enable' ? 'selected' : '' }}>{{ __('Enable') }}</option>
                            <option value="Disable" {{ ($zatcaConfig['zatca_status'] ?? 'Disable') == 'Disable' ? 'selected' : '' }}>{{ __('Disable') }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Environment Information (Hidden when Phase is None) -->
        <div class="card mb-6" id="zatca_environment_info_card" style="display: {{ $selectedPhase != '0' ? 'block' : 'none' }};">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('Environment Information') }}</h5>
            </div>
            <div class="card-body">
                <div class="row">

                    <div class="col-12">
                        <div class="alert alert-info" role="alert">
                            <h6 class="alert-heading">{{ __('Instructions') }}</h6>
                            <p class="mb-0">{{ __('Fillup the below required fields and Click the "Generate CSR" button to create Certificate Signing Request (CSR) and Private Key files. After generation, download the CSR file and upload it to your ZATCA account.') }}</p>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 mb-3 validate_wrapper">
                        <label class="form-label mb-1" for="legal_business_name_english">{{ __('Legal Business Name (English)') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="legal_business_name_english" name="legal_business_name_english" placeholder="{{ __('Enter') }} {{ __('Legal Business Name (English)') }}" value="{{ $zatcaConfig['legal_business_name_english'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 mb-3 validate_wrapper">
                        <label class="form-label mb-1" for="legal_business_name_arabic">{{ __('Legal Business Name (Arabic)') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="legal_business_name_arabic" name="legal_business_name_arabic" placeholder="{{ __('Enter') }} {{ __('Legal Business Name (Arabic)') }}" value="{{ $zatcaConfig['legal_business_name_arabic'] ?? '' }}" dir="rtl">
                    </div>

                    <div class="col-12 col-md-6 mb-3 validate_wrapper">
                        <label class="form-label mb-1" for="vat_registration_number">{{ __('VAT Registration Number') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="vat_registration_number" name="vat_registration_number" placeholder="{{ __('Enter') }} {{ __('VAT Registration Number') }}" value="{{ $zatcaConfig['vat_registration_number'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 mb-3 validate_wrapper">
                        <label class="form-label mb-1" for="zatca_address">{{ __('Address') }} {!! requiredField() !!}</label>
                        <textarea class="form-control" id="zatca_address" name="zatca_address" rows="3" placeholder="{{ __('Enter') }} {{ __('Address') }}">{{ $zatcaConfig['zatca_address'] ?? '' }}</textarea>
                    </div>

                    @if(isset($zatcaConfig) && $zatcaConfig && $zatcaConfig['vat_registration_number'] != '' && $zatcaConfig['legal_business_name_arabic'] != '' && $zatcaConfig['legal_business_name_english'] != '' && $zatcaConfig['zatca_address'] != '')
                        <div class="col-12 cs-generate-btn" id="cs_generate_btn_wrapper" style="display: {{ $selectedPhase == '2' ? 'block' : 'none' }};">
                            <button type="button" class="btn btn-primary waves-effect waves-light" id="generate_csr_btn">
                                <i class="ti tabler-key me-1"></i>
                                {{ __('Generate CSR') }}
                            </button>
                        </div>
                        
                        @if($csrExists || $privateKeyExists)
                        <div class="col-12 cs-generated-files" id="cs_generated_files_wrapper" style="display: {{ $selectedPhase == '2' ? 'block' : 'none' }};">
                            <hr>
                            <div class="alert alert-success" role="alert">
                                <h6 class="alert-heading">{{ __('Files Generated Successfully') }}</h6>
                                <p class="mb-2">{{ __('Your CSR and Private Key files have been generated. Please download the CSR file and upload it to ZATCA.') }}</p>
                                <div class="d-flex gap-2 flex-wrap">
                                    @if($csrExists)
                                        <a href="{{ route('zatca.download', ['file' => 'csr']) }}" class="btn btn-sm btn-success">
                                            <i class="ti tabler-download me-1"></i>
                                            {{ __('Download CSR') }}
                                        </a>
                                    @endif
                                    @if($privateKeyExists)
                                        <a href="{{ route('zatca.download', ['file' => 'private_key']) }}" class="btn btn-sm btn-warning">
                                            <i class="ti tabler-download me-1"></i>
                                            {{ __('Download Private Key') }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endif
                    @endif

                </div>
            </div>
        </div>


        <!-- ZATCA Credentials (After Upload) - Only shown when Phase 2 is selected -->
        <div class="card mb-6 zatca_credentials_card" id="zatca_credentials_card" style="display: {{ $selectedPhase == '2' ? 'block' : 'none' }};">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('ZATCA Credentials') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-6">
                    <div class="col-12">
                        <div class="alert alert-warning" role="alert">
                            <h6 class="alert-heading">{{ __('Important') }}</h6>
                            <p class="mb-0">{{ __('After uploading the CSR file to your ZATCA account, you will receive three credentials. Please enter them below:') }}</p>
                        </div>
                    </div>

                    <div class="col-12 col-md-4 validate_wrapper">
                        <label class="form-label mb-1" for="compliance_csid">{{ __('Compliance CSID') }}</label>
                        <input type="text" class="form-control" id="compliance_csid" name="compliance_csid" placeholder="{{ __('Enter') }} {{ __('Compliance CSID') }}" value="{{ $zatcaConfig['compliance_csid'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-4 validate_wrapper">
                        <label class="form-label mb-1" for="production_csid">{{ __('Production CSID') }}</label>
                        <input type="text" class="form-control" id="production_csid" name="production_csid" placeholder="{{ __('Enter') }} {{ __('Production CSID') }}" value="{{ $zatcaConfig['production_csid'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-4 validate_wrapper">
                        <label class="form-label mb-1" for="zatca_secret_key">{{ __('ZATCA Secret Key') }}</label>
                        <input type="text" class="form-control" id="zatca_secret_key" name="zatca_secret_key" placeholder="{{ __('Enter') }} {{ __('ZATCA Secret Key') }}" value="{{ $zatcaConfig['zatca_secret_key'] ?? '' }}">
                    </div>
                </div>
            </div>
        </div>


        <div class="d-flex justify-content-end gap-4">
            <button type="submit" class="btn btn-primary waves-effect waves-light zatca_setting_submit">
                <i class="ti tabler-checkbox me-1"></i>
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</div>

<script>
    // Show/hide sections based on selected ZATCA phase
    (function() {
        function toggleZatcaSections(selectedPhase) {
            // Phase 0 (None): Hide Phase 2 Status, Environment Info, Credentials
            // Phase 1: Hide Phase 2 Status, CSR button, CSR files, Credentials (but show Environment Info)
            // Phase 2: Show everything
            
            // Helper function to safely show/hide elements
            function safeToggle(selector, show) {
                const element = document.querySelector(selector);
                if (element) {
                    if (show) {
                        element.style.display = 'block';
                    } else {
                        element.style.display = 'none';
                    }
                }
            }
            
            // Helper function for elements that might not exist (CSR button/files)
            function safeToggleOptional(selector, show) {
                const element = document.querySelector(selector);
                if (element) {
                    if (show) {
                        element.style.display = 'block';
                    } else {
                        element.style.display = 'none';
                    }
                }
            }
            
            if (selectedPhase == '0') {
                // None - Hide everything except the phase selection
                safeToggle('#zatca_phase2_status_card', false);
                safeToggle('#zatca_environment_info_card', false);
                safeToggle('#zatca_credentials_card', false);
            } else if (selectedPhase == '1') {
                // Phase 1 - Show Environment Info, but hide Phase 2 specific items
                safeToggle('#zatca_phase2_status_card', false);
                safeToggle('#zatca_environment_info_card', true);
                safeToggleOptional('#cs_generate_btn_wrapper', false);
                safeToggleOptional('#cs_generated_files_wrapper', false);
                safeToggle('#zatca_credentials_card', false);
            } else if (selectedPhase == '2') {
                // Phase 2 - Show everything
                safeToggle('#zatca_phase2_status_card', true);
                safeToggle('#zatca_environment_info_card', true);
                safeToggleOptional('#cs_generate_btn_wrapper', true);
                safeToggleOptional('#cs_generated_files_wrapper', true);
                safeToggle('#zatca_credentials_card', true);
            }
        }
        
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
        
        function init() {
            // Handle phase change using both jQuery (if available) and vanilla JS
            const radioButtons = document.querySelectorAll('input[name="zatca_phase"]');
            radioButtons.forEach(function(radio) {
                radio.addEventListener('change', function() {
                    const selectedPhase = this.value;
                    toggleZatcaSections(selectedPhase);
                });
            });
            
            // Also use jQuery if available (for compatibility)
            if (typeof jQuery !== 'undefined') {
                jQuery('input[name="zatca_phase"]').on('change', function() {
                    const selectedPhase = jQuery(this).val();
                    toggleZatcaSections(selectedPhase);
                });
            }
            
            // Initialize on page load
            const checkedRadio = document.querySelector('input[name="zatca_phase"]:checked');
            if (checkedRadio) {
                toggleZatcaSections(checkedRadio.value);
            }
        }
    })();
</script>
