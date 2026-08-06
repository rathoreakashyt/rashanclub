@extends('backend.backend_layout')
@section('page-title', __('Setting'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/quill/editor.css') }}" />
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Setting') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Setting'), 
                    'link' => '#'
                ],
                [
                    'label' => __('Setting'),
                    'active' => true
                ]
            ]
        ])
    </div>

    @if(session('success'))
        {!! insertSuccess(session('success')) !!}
    @endif
    @if(session('error'))
        {!! insertFailed(session('error')) !!}
    @endif

    @php
        // Define variables that are used across multiple setting components
        $invFormat = date('Y') . '-XXXX';
        
        // Parse invoice configuration for components that need it
        $invoiceConfig = [];
        if (isset($company->invoice_configuration) && $company->invoice_configuration != '') {
            $invoiceConfig = json_decode($company->invoice_configuration, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $invoiceConfig = [];
            }
        }
    @endphp

    <div class="row">
        <!-- Navigation -->
        <div class="col-12 col-lg-4 col-xl-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Setting List') }}</h5>
                </div>
                <div class="card-body">
                    <ul class="nav nav-align-left nav-pills flex-column">
                        <li class="nav-item mb-1">
                            <a class="nav-link waves-effect waves-light {{ request()->route('tab') == 'business_setting' ? 'active' : '' }}" href="{{ route('setting', 'business_setting') }}">
                                <i class="icon-base ti tabler-building-store icon-sm me-1_5"></i>
                                <span class="align-middle">{{ __('Business Setting') }}</span>
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a class="nav-link waves-effect waves-light {{ request()->route('tab') == 'pos_setting' ? 'active' : '' }}" href="{{ route('setting', 'pos_setting') }}">
                                <i class="icon-base ti tabler-basket-cog icon-sm me-1_5"></i>
                                <span class="align-middle">{{ __('POS Setting') }}</span>
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a class="nav-link waves-effect waves-light {{ request()->route('tab') == 'tax_setting' ? 'active' : '' }}" href="{{ route('setting', 'tax_setting') }}">
                                <i class="icon-base ti tabler-receipt-2 icon-sm me-1_5"></i>
                                <span class="align-middle">{{ __('Tax Setting') }}</span>
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a class="nav-link waves-effect waves-light {{ request()->route('tab') == 'invoice_setting' ? 'active' : '' }}" href="{{ route('setting', 'invoice_setting') }}">
                                <i class="icon-base ti tabler-file-settings icon-sm me-1_5"></i>
                                <span class="align-middle">{{ __('Invoice Setting') }}</span>
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a class="nav-link waves-effect waves-light {{ request()->route('tab') == 'zatca_setting' ? 'active' : '' }}" href="{{ route('setting', 'zatca_setting') }}">
                                <i class="icon-base ti tabler-receipt-2 icon-sm me-1_5"></i>
                                <span class="align-middle">{{ __('Zatca Setting') }}</span>
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a class="nav-link waves-effect waves-light {{ request()->route('tab') == 'email_setting' ? 'active' : '' }}" href="{{ route('setting', 'email_setting') }}">
                                <i class="icon-base ti tabler-mail-cog icon-sm me-1_5"></i>
                                <span class="align-middle">{{ __('Email Setting') }}</span>
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a class="nav-link waves-effect waves-light {{ request()->route('tab') == 'sms_setting' ? 'active' : '' }}" href="{{ route('setting', 'sms_setting') }}">
                                <i class="icon-base ti tabler-device-mobile-message icon-sm me-1_5"></i>
                                <span class="align-middle">{{ __('SMS Setting') }}</span>
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a class="nav-link waves-effect waves-light {{ request()->route('tab') == 'whatsapp_setting' ? 'active' : '' }}" href="{{ route('setting', 'whatsapp_setting') }}">
                                <i class="icon-base ti tabler-brand-whatsapp icon-sm me-1_5"></i>
                                <span class="align-middle">{{ __('Whatsapp Setting') }}</span>
                            </a>
                        </li>
                        @if(!(defined('LP') && strtoupper((string) LP) === 'BD'))
                        <li class="nav-item mb-1">
                            <a class="nav-link waves-effect waves-light {{ request()->route('tab') == 'whitelabel_setting' ? 'active' : '' }}" href="{{ route('setting', 'whitelabel_setting') }}">
                                <i class="icon-base ti tabler-world-dollar icon-sm me-1_5"></i>
                                <span class="align-middle">{{ __('Whitelabel Setting') }}</span>
                            </a>
                        </li>
                        @endif
                        <li class="nav-item mb-1">
                            <a class="nav-link waves-effect waves-light {{ request()->route('tab') == 'pwa_setting' ? 'active' : '' }}" href="{{ route('setting', 'pwa_setting') }}">
                                <i class="icon-base ti tabler-device-mobile icon-sm me-1_5"></i>
                                <span class="align-middle">{{ __('PWA Setting') }}</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- /Navigation -->

        <!-- Options -->
        <div class="col-12 col-lg-8 col-xl-9 pt-6 pt-lg-0 right scrollable">
            <div class="tab-content p-0" >
                <!-- Business Setting Tab -->
                @include('configuration::backend.settings.setting-component.business-setting')
                <!-- /Business Setting Tab -->

                <!-- POS Setting Tab -->
                @include('configuration::backend.settings.setting-component.pos-setting')
                <!-- /POS Setting Tab -->

                <!-- Tax Setting Tab -->
                @include('configuration::backend.settings.setting-component.tax-setting')
                <!-- /Tax Setting Tab -->

                <!-- Invoice Setting Tab -->
                @include('configuration::backend.settings.setting-component.invoice-setting')
                <!-- /Invoice Setting Tab -->

                <!-- Zatca Phase 2 Tab -->
                @include('configuration::backend.settings.setting-component.zatca-setting')
                <!-- /Zatca Phase 2 Tab -->

                <!-- Email Setting Tab -->
                @include('configuration::backend.settings.setting-component.email-setting')
                <!-- /Email Setting Tab -->

                <!-- SMS Setting Tab -->
                @include('configuration::backend.settings.setting-component.sms-setting')
                <!-- /SMS Setting Tab -->

                <!-- WhatsApp Setting Tab -->
                @include('configuration::backend.settings.setting-component.whatsapp-setting')
                <!-- /WhatsApp Setting Tab -->

                @if(!(defined('LP') && strtoupper((string) LP) === 'BD'))
                <!-- Whitelabel Setting Tab -->
                @include('configuration::backend.settings.setting-component.white-label')
                <!-- /Whitelabel Setting Tab -->
                @endif

                <!-- PWA Setting Tab -->
                @include('configuration::backend.settings.setting-component.pwa-setting')
                <!-- /PWA Setting Tab -->
            </div>
        </div>
    </div>
</div>


<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasEnd" aria-labelledby="offcanvasEndLabel">
    <div class="offcanvas-header">
        <h5 id="offcanvasEndLabel" class="offcanvas-title mail_sms_offcanvas_title">Offcanvas End</h5>
        <button
        type="button"
        class="btn-close text-reset"
        data-bs-dismiss="offcanvas"
        aria-label="Close"></button>
    </div>
    <div class="offcanvas-body mx-0 flex-grow-0">
        <div class="test_mail_part">
            <div class="mb-3">
                <label for="email" class="form-label">Email {!! requiredField() !!}</label>
                <input type="email" class="form-control" id="email" placeholder="Enter email">
            </div>
            <div class="mb-3">
                <label for="message" class="form-label">Message {!! requiredField() !!}</label>
                <textarea class="form-control" id="message" rows="3" placeholder="Enter message"></textarea>
            </div>
            <button type="button" class="btn btn-primary mb-2 d-grid w-100">Continue</button>
            <button type="button" class="btn btn-label-secondary d-grid w-100" data-bs-dismiss="offcanvas">Cancel</button>
        </div>

        <div class="test_sms_part" style="display: none;">
            <div class="mb-3">
                <label for="sms_number" class="form-label">SMS Number {!! requiredField() !!}</label>
                <input type="text" class="form-control" id="sms_number" placeholder="Enter sms number">
            </div>
            <div class="mb-3">
                <label for="sms_message" class="form-label">Message {!! requiredField() !!}</label>
                <textarea class="form-control" id="sms_message" rows="3" placeholder="Enter message"></textarea>
            </div>
            <button type="button" class="btn btn-primary mb-2 d-grid w-100">Continue</button>
            <button type="button" class="btn btn-label-secondary d-grid w-100" data-bs-dismiss="offcanvas">Cancel</button>
        </div>
    </div>
</div>



@endsection

@push('page-js')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/css/pages/cropper.min.css') }}" />
<script src="{{ asset('backend_assets/vendor/js/cropper.js') }}"></script>
<script src="{{ asset('backend_assets/vendor/libs/quill/quill.js') }}"></script>
<script>
    // Pass Blade variables to JavaScript
    window.settingsConfig = {
        defaultImagePath: '{{ asset('uploads/dummy_images/default-picture.png') }}',
        siteLogoOriginalSrc: '{{ isset($whiteLabelDetails["site_logo"]) ? asset("uploads/whitelabel/" . $whiteLabelDetails["site_logo"]) : "" }}',
        siteFaviconOriginalSrc: '{{ isset($whiteLabelDetails["site_favicon"]) ? asset("uploads/whitelabel/" . $whiteLabelDetails["site_favicon"]) : "" }}',
        hasSiteLogo: {{ isset($whiteLabelDetails['site_logo']) ? 'true' : 'false' }},
        hasSiteFavicon: {{ isset($whiteLabelDetails['site_favicon']) ? 'true' : 'false' }},
        translations: {
            imagePreview: '{{ __('Image Preview') }}',
            siteLogo: '{{ __('Site Logo') }}',
            siteFavicon: '{{ __('Site Favicon') }}',
            invoiceLogo: '{{ __('Invoice Logo') }}',
            close: '{{ __('Close') }}',
            cropSiteLogo: '{{ __('Crop Site Logo') }}',
            cropSiteFavicon: '{{ __('Crop Site Favicon') }}',
            cropInvoiceLogo: '{{ __('Crop Invoice Logo') }}',
            cropSave: '{{ __('Crop & Save') }}',
            cancel: '{{ __('Cancel') }}',
            invoiceLogoPreview: '{{ __('Invoice Logo Preview') }}'
        }
    };
</script>
<script src="{{ asset('backend_assets/js/pages_js/settings.js') }}"></script>

<!-- Test Email Offcanvas -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="testEmailOffcanvas" aria-labelledby="testEmailOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 id="testEmailOffcanvasLabel" class="offcanvas-title">Test Email</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body mx-0 flex-grow-0">
        <div class="mb-3">
            <label for="test_email_to" class="form-label">To Email {!! requiredField() !!}</label>
            <input type="email" class="form-control" id="test_email_to" placeholder="Enter email address">
        </div>
        <div class="mb-3">
            <label for="test_email_subject" class="form-label">Subject {!! requiredField() !!}</label>
            <input type="text" class="form-control" id="test_email_subject" placeholder="Enter email subject" value="Test Email">
        </div>
        <div class="mb-3">
            <label for="test_email_message" class="form-label">Message {!! requiredField() !!}</label>
            <textarea class="form-control" id="test_email_message" rows="4" placeholder="Enter test message">This is a test email to verify email configuration.</textarea>
        </div>
        <button type="button" class="btn btn-primary mb-2 d-grid w-100" id="sendTestEmailBtn">Send Test Email</button>
        <button type="button" class="btn btn-label-secondary d-grid w-100" data-bs-dismiss="offcanvas">Cancel</button>
    </div>
</div>

<!-- Test SMS Offcanvas -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="testSMSOffcanvas" aria-labelledby="testSMSOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 id="testSMSOffcanvasLabel" class="offcanvas-title">Test SMS</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body mx-0 flex-grow-0">
        <div class="mb-3">
            <label for="test_sms_number" class="form-label">Phone Number {!! requiredField() !!}</label>
            <input type="text" class="form-control" id="test_sms_number" placeholder="Enter phone number">
        </div>
        <div class="mb-3">
            <label for="test_sms_message" class="form-label">Message {!! requiredField() !!}</label>
            <textarea class="form-control" id="test_sms_message" rows="4" placeholder="Enter test message">This is a test SMS to verify SMS configuration.</textarea>
        </div>
        <button type="button" class="btn btn-primary mb-2 d-grid w-100" id="sendTestSMSBtn">Send Test SMS</button>
        <button type="button" class="btn btn-label-secondary d-grid w-100" data-bs-dismiss="offcanvas">Cancel</button>
    </div>
</div>

<!-- Test WhatsApp Offcanvas -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="testWhatsappOffcanvas" aria-labelledby="testWhatsappOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 id="testWhatsappOffcanvasLabel" class="offcanvas-title">Test WhatsApp</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body mx-0 flex-grow-0">
        <div class="mb-3">
            <label for="test_whatsapp_number" class="form-label">Phone Number {!! requiredField() !!}</label>
            <input type="text" class="form-control" id="test_whatsapp_number" placeholder="Enter phone number with country code">
        </div>
        <div class="mb-3">
            <label for="test_whatsapp_message" class="form-label">Message {!! requiredField() !!}</label>
            <textarea class="form-control" id="test_whatsapp_message" rows="4" placeholder="Enter test message">This is a test WhatsApp message to verify WhatsApp configuration.</textarea>
        </div>
        <button type="button" class="btn btn-primary mb-2 d-grid w-100" id="sendTestWhatsappBtn">Send Test WhatsApp</button>
        <button type="button" class="btn btn-label-secondary d-grid w-100" data-bs-dismiss="offcanvas">Cancel</button>
    </div>
</div>
@endpush