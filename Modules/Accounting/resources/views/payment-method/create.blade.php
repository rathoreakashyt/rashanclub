@extends('backend.backend_layout')
@section('page-title', isset($payment_method) ? __('Update') . ' ' . __('Payment_Method') : __('Add') . ' ' . __('Payment_Method'))
@push('page-css')
@endpush
@php
    /**
     * Helper function to get configuration value from nested structure
     * Supports both old flat structure and new nested structure
     */
    function getConfigValue($config, $gateway, $key) {
        if (!$config || !is_array($config)) {
            return '';
        }
        
        // Try new nested structure first: config[gateway][key]
        if (isset($config[$gateway]) && is_array($config[$gateway]) && isset($config[$gateway][$key])) {
            return $config[$gateway][$key];
        }
        
        // Fallback to old flat structure: config[gateway_key]
        $flatKey = $gateway . '_' . $key;
        if (isset($config[$flatKey])) {
            return $config[$flatKey];
        }
        
        return '';
    }
    
    // Get gateway name from configuration or account type
    $gatewayName = null;
    if (isset($payment_method) && $payment_method->configuration) {
        $gatewayName = $payment_method->configuration['gateway'] ?? strtolower($payment_method->account_type);
    }
@endphp
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($payment_method) ? __('Update') . ' ' . __('Payment_Method') : __('Add') . ' ' . __('Payment_Method') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Account'),
                    'link' => '#'
                ],
                [
                    'label' => isset($payment_method) ? __('Update') . ' ' . __('Payment_Method') : __('Add') . ' ' . __('Payment_Method'),
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


    <div class="row">
        <div class="col-12">
            <form action="{{ isset($payment_method) ? route('payment-method.update', encrypt($payment_method->id)) : route('payment-method.store') }}" method="POST">
                @csrf
                @if(isset($payment_method))
                    @method('PUT')
                @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="account_type">{{ __('Account_Type') }} {!! requiredField() !!}</label>
                                    <select class="form-select select2 @error('account_type') is-invalid @enderror" 
                                        name="account_type" id="account_type" data-placeholder="{{ __('Select') }} {{ __('Account_Type') }}">
                                        <option value="">{{ __('Select') }} {{ __('Account_Type') }}</option>
                                        <option value="Cash" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'Cash' ? 'selected' : '') }}>
                                            {{ __('Cash') }}
                                        </option>
                                        <option value="Bank_Account" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'Bank_Account' ? 'selected' : '') }}>
                                            {{ __('Bank_Account') }}
                                        </option>
                                        <option value="Card" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'Card' ? 'selected' : '') }}>
                                            {{ __('Card') }}
                                        </option>
                                        <option value="Mobile_Banking" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'Mobile_Banking' ? 'selected' : '') }}>
                                            {{ __('Mobile_Banking') }}
                                        </option>
                                        <option value="Paypal" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'Paypal' ? 'selected' : '') }}>
                                            {{ __('Paypal') }}
                                        </option>
                                        <option value="Stripe" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'Stripe' ? 'selected' : '') }}>
                                            {{ __('Stripe') }}
                                        </option>
                                        <option value="Razorpay" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'Razorpay' ? 'selected' : '') }}>
                                            {{ __('Razorpay') }}
                                        </option>
                                        <option value="Paystack" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'Paystack' ? 'selected' : '') }}>
                                            {{ __('Paystack') }}
                                        </option>
                                        <option value="Paytm" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'Paytm' ? 'selected' : '') }}>
                                            {{ __('Paytm') }}
                                        </option>
                                        <option value="Flutterwave" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'Flutterwave' ? 'selected' : '') }}>
                                            {{ __('Flutterwave') }}
                                        </option>
                                        <option value="SslCommerz" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'SslCommerz' ? 'selected' : '') }}>
                                            {{ __('SslCommerz') }}
                                        </option>
                                        <option value="Mollie" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'Mollie' ? 'selected' : '') }}>
                                            {{ __('Mollie') }}
                                        </option>
                                        <option value="Senangpay" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'Senangpay' ? 'selected' : '') }}>
                                            {{ __('Senangpay') }}
                                        </option>
                                        <option value="Bkash" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'Bkash' ? 'selected' : '') }}>
                                            {{ __('Bkash') }}
                                        </option>
                                        <option value="Mercadopago" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'Mercadopago' ? 'selected' : '') }}>
                                            {{ __('Mercadopago') }}
                                        </option>
                                        <option value="Cashfree" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'Cashfree' ? 'selected' : '') }}>
                                            {{ __('Cashfree') }}
                                        </option>
                                        <option value="Payfast" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'Payfast' ? 'selected' : '') }}>
                                            {{ __('Payfast') }}
                                        </option>
                                        <option value="Skrill" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'Skrill' ? 'selected' : '') }}>
                                            {{ __('Skrill') }}
                                        </option>
                                        <option value="PhonePe" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'PhonePe' ? 'selected' : '') }}>
                                            {{ __('PhonePe') }}
                                        </option>
                                        <option value="Telr" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'Telr' ? 'selected' : '') }}>
                                            {{ __('Telr') }}
                                        </option>
                                        <option value="Iyzico" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'Iyzico' ? 'selected' : '') }}>
                                            {{ __('Iyzico') }}
                                        </option>
                                        <option value="Pesapal" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'Pesapal' ? 'selected' : '') }}>
                                            {{ __('Pesapal') }}
                                        </option>
                                        <option value="Midtrans" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'Midtrans' ? 'selected' : '') }}>
                                            {{ __('Midtrans') }}
                                        </option>
                                        <option value="MyFatoorah" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'MyFatoorah' ? 'selected' : '') }}>
                                            {{ __('MyFatoorah') }}
                                        </option>
                                        <option value="EasyPaisa" {{ old('account_type', isset($payment_method) && $payment_method->account_type == 'EasyPaisa' ? 'selected' : '') }}>
                                            {{ __('EasyPaisa') }}
                                        </option>
                                    </select>
                                    @error('account_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="clear-fix"></div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="name">{{ __('Account') }} {{ __('Name') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                        placeholder="{{ __('Account') }} {{ __('Name') }}" name="name" id="name" 
                                        value="{{ old('name', isset($payment_method) ? $payment_method->name : '') }}" />
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="description">{{ __('Description') }}</label>
                                    <textarea type="text" class="form-control @error('description') is-invalid @enderror" 
                                        placeholder="{{ __('Description') }}" name="description" id="description" 
                                        >{{ old('description', isset($payment_method) ? $payment_method->description : '') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="current_balance">{{ __('opening_balance') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control number-input @error('current_balance') is-invalid @enderror" 
                                        placeholder="{{ __('opening_balance') }}" name="current_balance" id="current_balance" 
                                        value="{{ old('current_balance', isset($payment_method) ? $payment_method->current_balance : '') }}" />
                                    @error('current_balance')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="status">{{ __('Status') }} {!! requiredField() !!}</label>
                                    <select class="form-select select2 @error('status') is-invalid @enderror" 
                                        name="status" id="status" data-placeholder="{{ __('Select') }} {{ __('Status') }}">
                                        <option value="">{{ __('Select') }} {{ __('Status') }}</option>
                                            <option value="Enable" {{ old('status', isset($payment_method) && $payment_method->status == 'Enable' ? 'selected' : '') }}>
                                            {{ __('Enable') }}
                                            </option>
                                            <option value="Disable" {{ old('status', isset($payment_method) && $payment_method->status == 'Disable' ? 'selected' : '') }}>
                                            {{ __('Disable') }}
                                            </option>
                                    </select>
                                    @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="row">

                            <!-- Paypal Fields -->
                            <div class="payment-method-fields payment-method-Paypal" style="display: none;">
                                <div class="row">
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="paypal_client_id">{{ __('Client ID') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.paypal_client_id') is-invalid @enderror" 
                                                placeholder="{{ __('Client ID') }}" name="configuration[paypal_client_id]" id="paypal_client_id" 
                                                value="{{ old('configuration.paypal_client_id', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'paypal', 'client_id') : '') }}" />
                                            @error('configuration.paypal_client_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="paypal_secret_key">{{ __('Secret Key') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.paypal_secret_key') is-invalid @enderror" 
                                                placeholder="{{ __('Secret Key') }}" name="configuration[paypal_secret_key]" id="paypal_secret_key" 
                                                value="{{ old('configuration.paypal_secret_key', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'paypal', 'secret_key') : '') }}" />
                                            @error('configuration.paypal_secret_key')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="paypal_mode">{{ __('Mode') }} {!! requiredField() !!}</label>
                                            <select class="form-select select2 @error('configuration.paypal_mode') is-invalid @enderror" 
                                                name="configuration[paypal_mode]" id="paypal_mode">
                                                <option value="">{{ __('Select') }} {{ __('Mode') }}</option>
                                                <option value="Sandbox" {{ old('configuration.paypal_mode', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'paypal', 'mode') : '') == 'Sandbox' ? 'selected' : '' }}>
                                                    {{ __('Sandbox') }}
                                                </option>
                                                <option value="Live" {{ old('configuration.paypal_mode', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'paypal', 'mode') : '') == 'Live' ? 'selected' : '' }}>
                                                    {{ __('Live') }}
                                                </option>
                                            </select>
                                            @error('configuration.paypal_mode')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Stripe Fields -->
                            <div class="payment-method-fields payment-method-Stripe" style="display: none;">
                                <div class="row">
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="stripe_secret_key">{{ __('Secret Key') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.stripe_secret_key') is-invalid @enderror" 
                                                placeholder="{{ __('Secret Key') }}" name="configuration[stripe_secret_key]" id="stripe_secret_key" 
                                                value="{{ old('configuration.stripe_secret_key', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'stripe', 'secret_key') : '') }}" />
                                            @error('configuration.stripe_secret_key')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="stripe_api_key">{{ __('API Key') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.stripe_api_key') is-invalid @enderror" 
                                                placeholder="{{ __('API Key') }}" name="configuration[stripe_api_key]" id="stripe_api_key" 
                                                value="{{ old('configuration.stripe_api_key', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'stripe', 'api_key') : '') }}" />
                                            @error('configuration.stripe_api_key')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="stripe_mode">{{ __('Mode') }} {!! requiredField() !!}</label>
                                            <select class="form-select select2 @error('configuration.stripe_mode') is-invalid @enderror" 
                                                name="configuration[stripe_mode]" id="stripe_mode">
                                                <option value="">{{ __('Select') }} {{ __('Mode') }}</option>
                                                <option value="Sandbox" {{ old('configuration.stripe_mode', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'stripe', 'mode') : '') == 'Sandbox' ? 'selected' : '' }}>
                                                    {{ __('Sandbox') }}
                                                </option>
                                                <option value="Live" {{ old('configuration.stripe_mode', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'stripe', 'mode') : '') == 'Live' ? 'selected' : '' }}>
                                                    {{ __('Live') }}
                                                </option>
                                            </select>
                                            @error('configuration.stripe_mode')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Razorpay Fields -->
                            <div class="payment-method-fields payment-method-Razorpay" style="display: none;">
                                <div class="row">
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="razorpay_key_id">{{ __('Key ID') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.razorpay_key_id') is-invalid @enderror" 
                                                placeholder="{{ __('Key ID') }}" name="configuration[razorpay_key_id]" id="razorpay_key_id" 
                                                value="{{ old('configuration.razorpay_key_id', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'razorpay', 'key_id') : '') }}" />
                                            @error('configuration.razorpay_key_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="razorpay_key_secret">{{ __('Key Secret') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.razorpay_key_secret') is-invalid @enderror" 
                                                placeholder="{{ __('Key Secret') }}" name="configuration[razorpay_key_secret]" id="razorpay_key_secret" 
                                                value="{{ old('configuration.razorpay_key_secret', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'razorpay', 'key_secret') : '') }}" />
                                            @error('configuration.razorpay_key_secret')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="razorpay_mode">{{ __('Mode') }} {!! requiredField() !!}</label>
                                            <select class="form-select select2 @error('configuration.razorpay_mode') is-invalid @enderror" 
                                                name="configuration[razorpay_mode]" id="razorpay_mode">
                                                <option value="">{{ __('Select') }} {{ __('Mode') }}</option>
                                                <option value="Sandbox" {{ old('configuration.razorpay_mode', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'razorpay', 'mode') : '') == 'Sandbox' ? 'selected' : '' }}>
                                                    {{ __('Sandbox') }}
                                                </option>
                                                <option value="Live" {{ old('configuration.razorpay_mode', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'razorpay', 'mode') : '') == 'Live' ? 'selected' : '' }}>
                                                    {{ __('Live') }}
                                                </option>
                                            </select>
                                            @error('configuration.razorpay_mode')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Paystack Fields -->
                            <div class="payment-method-fields payment-method-Paystack" style="display: none;">
                                <div class="row">
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="paystack_secret_key">{{ __('Secret Key') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.paystack_secret_key') is-invalid @enderror" 
                                                placeholder="{{ __('Secret Key') }}" name="configuration[paystack_secret_key]" id="paystack_secret_key" 
                                                value="{{ old('configuration.paystack_secret_key', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'paystack', 'secret_key') : '') }}" />
                                            @error('configuration.paystack_secret_key')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="paystack_mode">{{ __('Mode') }} {!! requiredField() !!}</label>
                                            <select class="form-select select2 @error('configuration.paystack_mode') is-invalid @enderror" 
                                                name="configuration[paystack_mode]" id="paystack_mode">
                                                <option value="">{{ __('Select') }} {{ __('Mode') }}</option>
                                                <option value="Sandbox" {{ old('configuration.paystack_mode', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'paystack', 'mode') : '') == 'Sandbox' ? 'selected' : '' }}>
                                                    {{ __('Sandbox') }}
                                                </option>
                                                <option value="Live" {{ old('configuration.paystack_mode', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'paystack', 'mode') : '') == 'Live' ? 'selected' : '' }}>
                                                    {{ __('Live') }}
                                                </option>
                                            </select>
                                            @error('configuration.paystack_mode')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Paytm Fields -->
                            <div class="payment-method-fields payment-method-Paytm" style="display: none;">
                                <div class="row">
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="paytm_merchant_id">{{ __('Merchant ID') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.paytm_merchant_id') is-invalid @enderror" 
                                                placeholder="{{ __('Merchant ID') }}" name="configuration[paytm_merchant_id]" id="paytm_merchant_id" 
                                                value="{{ old('configuration.paytm_merchant_id', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'paytm', 'merchant_id') : '') }}" />
                                            @error('configuration.paytm_merchant_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="paytm_merchant_key">{{ __('Merchant Key') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.paytm_merchant_key') is-invalid @enderror" 
                                                placeholder="{{ __('Merchant Key') }}" name="configuration[paytm_merchant_key]" id="paytm_merchant_key" 
                                                value="{{ old('configuration.paytm_merchant_key', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'paytm', 'merchant_key') : '') }}" />
                                            @error('configuration.paytm_merchant_key')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="paytm_mode">{{ __('Mode') }} {!! requiredField() !!}</label>
                                            <select class="form-select select2 @error('configuration.paytm_mode') is-invalid @enderror" 
                                                name="configuration[paytm_mode]" id="paytm_mode">
                                                <option value="">{{ __('Select') }} {{ __('Mode') }}</option>
                                                <option value="Sandbox" {{ old('configuration.paytm_mode', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'paytm', 'mode') : '') == 'Sandbox' ? 'selected' : '' }}>
                                                    {{ __('Sandbox') }}
                                                </option>
                                                <option value="Live" {{ old('configuration.paytm_mode', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'paytm', 'mode') : '') == 'Live' ? 'selected' : '' }}>
                                                    {{ __('Live') }}
                                                </option>
                                            </select>
                                            @error('configuration.paytm_mode')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Flutterwave Fields -->
                            <div class="payment-method-fields payment-method-Flutterwave" style="display: none;">
                                <div class="row">
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="flutterwave_public_key">{{ __('Public Key') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.flutterwave_public_key') is-invalid @enderror" 
                                                placeholder="{{ __('Public Key') }}" name="configuration[flutterwave_public_key]" id="flutterwave_public_key" 
                                                value="{{ old('configuration.flutterwave_public_key', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'flutterwave', 'public_key') : '') }}" />
                                            @error('configuration.flutterwave_public_key')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="flutterwave_secret_key">{{ __('Secret Key') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.flutterwave_secret_key') is-invalid @enderror" 
                                                placeholder="{{ __('Secret Key') }}" name="configuration[flutterwave_secret_key]" id="flutterwave_secret_key" 
                                                value="{{ old('configuration.flutterwave_secret_key', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'flutterwave', 'secret_key') : '') }}" />
                                            @error('configuration.flutterwave_secret_key')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="flutterwave_mode">{{ __('Mode') }} {!! requiredField() !!}</label>
                                            <select class="form-select select2 @error('configuration.flutterwave_mode') is-invalid @enderror" 
                                                name="configuration[flutterwave_mode]" id="flutterwave_mode">
                                                <option value="">{{ __('Select') }} {{ __('Mode') }}</option>
                                                <option value="Sandbox" {{ old('configuration.flutterwave_mode', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'flutterwave', 'mode') : '') == 'Sandbox' ? 'selected' : '' }}>
                                                    {{ __('Sandbox') }}
                                                </option>
                                                <option value="Live" {{ old('configuration.flutterwave_mode', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'flutterwave', 'mode') : '') == 'Live' ? 'selected' : '' }}>
                                                    {{ __('Live') }}
                                                </option>
                                            </select>
                                            @error('configuration.flutterwave_mode')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SslCommerz Fields -->
                            <div class="payment-method-fields payment-method-SslCommerz" style="display: none;">
                                <div class="row">
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="sslcommerz_store_name">{{ __('Store Name') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.sslcommerz_store_name') is-invalid @enderror" 
                                                placeholder="{{ __('Store Name') }}" name="configuration[sslcommerz_store_name]" id="sslcommerz_store_name" 
                                                value="{{ old('configuration.sslcommerz_store_name', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'sslcommerz', 'store_name') : '') }}" />
                                            @error('configuration.sslcommerz_store_name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="sslcommerz_store_id">{{ __('Store ID') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.sslcommerz_store_id') is-invalid @enderror" 
                                                placeholder="{{ __('Store ID') }}" name="configuration[sslcommerz_store_id]" id="sslcommerz_store_id" 
                                                value="{{ old('configuration.sslcommerz_store_id', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'sslcommerz', 'store_id') : '') }}" />
                                            @error('configuration.sslcommerz_store_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="sslcommerz_store_password">{{ __('Store Password') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.sslcommerz_store_password') is-invalid @enderror" 
                                                placeholder="{{ __('Store Password') }}" name="configuration[sslcommerz_store_password]" id="sslcommerz_store_password" 
                                                value="{{ old('configuration.sslcommerz_store_password', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'sslcommerz', 'store_password') : '') }}" />
                                            @error('configuration.sslcommerz_store_password')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="sslcommerz_mode">{{ __('Mode') }} {!! requiredField() !!}</label>
                                            <select class="form-select select2 @error('configuration.sslcommerz_mode') is-invalid @enderror" 
                                                name="configuration[sslcommerz_mode]" id="sslcommerz_mode">
                                                <option value="">{{ __('Select') }} {{ __('Mode') }}</option>
                                                <option value="Sandbox" {{ old('configuration.sslcommerz_mode', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'sslcommerz', 'mode') : '') == 'Sandbox' ? 'selected' : '' }}>
                                                    {{ __('Sandbox') }}
                                                </option>
                                                <option value="Live" {{ old('configuration.sslcommerz_mode', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'sslcommerz', 'mode') : '') == 'Live' ? 'selected' : '' }}>
                                                    {{ __('Live') }}
                                                </option>
                                            </select>
                                            @error('configuration.sslcommerz_mode')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Mollie Fields -->
                            <div class="payment-method-fields payment-method-Mollie" style="display: none;">
                                <div class="row">
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="mollie_api_key">{{ __('API Key') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.mollie_api_key') is-invalid @enderror" 
                                                placeholder="{{ __('API Key') }}" name="configuration[mollie_api_key]" id="mollie_api_key" 
                                                value="{{ old('configuration.mollie_api_key', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'mollie', 'api_key') : '') }}" />
                                            @error('configuration.mollie_api_key')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="mollie_mode">{{ __('Mode') }} {!! requiredField() !!}</label>
                                            <select class="form-select select2 @error('configuration.mollie_mode') is-invalid @enderror" 
                                                name="configuration[mollie_mode]" id="mollie_mode">
                                                <option value="">{{ __('Select') }} {{ __('Mode') }}</option>
                                                <option value="Sandbox" {{ old('configuration.mollie_mode', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'mollie', 'mode') : '') == 'Sandbox' ? 'selected' : '' }}>
                                                    {{ __('Sandbox') }}
                                                </option>
                                                <option value="Live" {{ old('configuration.mollie_mode', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'mollie', 'mode') : '') == 'Live' ? 'selected' : '' }}>
                                                    {{ __('Live') }}
                                                </option>
                                            </select>
                                            @error('configuration.mollie_mode')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Senangpay Fields -->
                            <div class="payment-method-fields payment-method-Senangpay" style="display: none;">
                                <div class="row">
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="senangpay_merchant_id">{{ __('Merchant ID') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.senangpay_merchant_id') is-invalid @enderror" 
                                                placeholder="{{ __('Merchant ID') }}" name="configuration[senangpay_merchant_id]" id="senangpay_merchant_id" 
                                                value="{{ old('configuration.senangpay_merchant_id', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'senangpay', 'merchant_id') : '') }}" />
                                            @error('configuration.senangpay_merchant_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="senangpay_secret_key">{{ __('Secret Key') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.senangpay_secret_key') is-invalid @enderror" 
                                                placeholder="{{ __('Secret Key') }}" name="configuration[senangpay_secret_key]" id="senangpay_secret_key" 
                                                value="{{ old('configuration.senangpay_secret_key', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'senangpay', 'secret_key') : '') }}" />
                                            @error('configuration.senangpay_secret_key')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="senangpay_mode">{{ __('Mode') }} {!! requiredField() !!}</label>
                                            <select class="form-select select2 @error('configuration.senangpay_mode') is-invalid @enderror" 
                                                name="configuration[senangpay_mode]" id="senangpay_mode">
                                                <option value="">{{ __('Select') }} {{ __('Mode') }}</option>
                                                <option value="Sandbox" {{ old('configuration.senangpay_mode', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'senangpay', 'mode') : '') == 'Sandbox' ? 'selected' : '' }}>
                                                    {{ __('Sandbox') }}
                                                </option>
                                                <option value="Live" {{ old('configuration.senangpay_mode', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'senangpay', 'mode') : '') == 'Live' ? 'selected' : '' }}>
                                                    {{ __('Live') }}
                                                </option>
                                            </select>
                                            @error('configuration.senangpay_mode')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bkash Fields -->
                            <div class="payment-method-fields payment-method-Bkash" style="display: none;">
                                <div class="row">
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="bkash_app_key">{{ __('App Key') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.bkash_app_key') is-invalid @enderror" 
                                                placeholder="{{ __('App Key') }}" name="configuration[bkash_app_key]" id="bkash_app_key" 
                                                value="{{ old('configuration.bkash_app_key', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'bkash', 'app_key') : '') }}" />
                                            @error('configuration.bkash_app_key')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="bkash_app_secret">{{ __('App Secret') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.bkash_app_secret') is-invalid @enderror" 
                                                placeholder="{{ __('App Secret') }}" name="configuration[bkash_app_secret]" id="bkash_app_secret" 
                                                value="{{ old('configuration.bkash_app_secret', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'bkash', 'app_secret') : '') }}" />
                                            @error('configuration.bkash_app_secret')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="bkash_username">{{ __('Username') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.bkash_username') is-invalid @enderror" 
                                                placeholder="{{ __('Username') }}" name="configuration[bkash_username]" id="bkash_username" 
                                                value="{{ old('configuration.bkash_username', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'bkash', 'username') : '') }}" />
                                            @error('configuration.bkash_username')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="bkash_password">{{ __('Password') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.bkash_password') is-invalid @enderror" 
                                                placeholder="{{ __('Password') }}" name="configuration[bkash_password]" id="bkash_password" 
                                                value="{{ old('configuration.bkash_password', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'bkash', 'password') : '') }}" />
                                            @error('configuration.bkash_password')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="bkash_mode">{{ __('Mode') }} {!! requiredField() !!}</label>
                                            <select class="form-select select2 @error('configuration.bkash_mode') is-invalid @enderror" 
                                                name="configuration[bkash_mode]" id="bkash_mode">
                                                <option value="">{{ __('Select') }} {{ __('Mode') }}</option>
                                                <option value="Sandbox" {{ old('configuration.bkash_mode', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'bkash', 'mode') : '') == 'Sandbox' ? 'selected' : '' }}>
                                                    {{ __('Sandbox') }}
                                                </option>
                                                <option value="Live" {{ old('configuration.bkash_mode', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'bkash', 'mode') : '') == 'Live' ? 'selected' : '' }}>
                                                    {{ __('Live') }}
                                                </option>
                                            </select>
                                            @error('configuration.bkash_mode')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Mercadopago Fields -->
                            <div class="payment-method-fields payment-method-Mercadopago" style="display: none;">
                                <div class="row">
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="mercadopago_client_id">{{ __('Client ID') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.mercadopago_client_id') is-invalid @enderror" 
                                                placeholder="{{ __('Client ID') }}" name="configuration[mercadopago_client_id]" id="mercadopago_client_id" 
                                                value="{{ old('configuration.mercadopago_client_id', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'mercadopago', 'client_id') : '') }}" />
                                            @error('configuration.mercadopago_client_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="mercadopago_client_secret">{{ __('Client Secret') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.mercadopago_client_secret') is-invalid @enderror" 
                                                placeholder="{{ __('Client Secret') }}" name="configuration[mercadopago_client_secret]" id="mercadopago_client_secret" 
                                                value="{{ old('configuration.mercadopago_client_secret', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'mercadopago', 'client_secret') : '') }}" />
                                            @error('configuration.mercadopago_client_secret')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="mercadopago_mode">{{ __('Mode') }} {!! requiredField() !!}</label>
                                            <select class="form-select select2 @error('configuration.mercadopago_mode') is-invalid @enderror" 
                                                name="configuration[mercadopago_mode]" id="mercadopago_mode">
                                                <option value="">{{ __('Select') }} {{ __('Mode') }}</option>
                                                <option value="Sandbox" {{ old('configuration.mercadopago_mode', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'mercadopago', 'mode') : '') == 'Sandbox' ? 'selected' : '' }}>
                                                    {{ __('Sandbox') }}
                                                </option>
                                                <option value="Live" {{ old('configuration.mercadopago_mode', isset($payment_method) && $payment_method->configuration ? getConfigValue($payment_method->configuration, 'mercadopago', 'mode') : '') == 'Live' ? 'selected' : '' }}>
                                                    {{ __('Live') }}
                                                </option>
                                            </select>
                                            @error('configuration.mercadopago_mode')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Cashfree Fields -->
                            <div class="payment-method-fields payment-method-Cashfree" style="display: none;">
                                <div class="row">
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="cashfree_app_id">{{ __('APP Id') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.cashfree_app_id') is-invalid @enderror" 
                                                placeholder="{{ __('APP Id') }}" name="configuration[cashfree_app_id]" id="cashfree_app_id" 
                                                value="{{ old('configuration.cashfree_app_id', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['cashfree_app_id'] ?? '') : '') }}" />
                                            @error('configuration.cashfree_app_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="cashfree_secret_key">{{ __('Secret Key') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.cashfree_secret_key') is-invalid @enderror" 
                                                placeholder="{{ __('Secret Key') }}" name="configuration[cashfree_secret_key]" id="cashfree_secret_key" 
                                                value="{{ old('configuration.cashfree_secret_key', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['cashfree_secret_key'] ?? '') : '') }}" />
                                            @error('configuration.cashfree_secret_key')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="cashfree_mode">{{ __('Mode') }} {!! requiredField() !!}</label>
                                            <select class="form-select select2 @error('configuration.cashfree_mode') is-invalid @enderror" 
                                                name="configuration[cashfree_mode]" id="cashfree_mode">
                                                <option value="">{{ __('Select') }} {{ __('Mode') }}</option>
                                                <option value="Sandbox" {{ old('configuration.cashfree_mode', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['cashfree_mode'] ?? '') : '') == 'Sandbox' ? 'selected' : '' }}>
                                                    {{ __('Sandbox') }}
                                                </option>
                                                <option value="Live" {{ old('configuration.cashfree_mode', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['cashfree_mode'] ?? '') : '') == 'Live' ? 'selected' : '' }}>
                                                    {{ __('Live') }}
                                                </option>
                                            </select>
                                            @error('configuration.cashfree_mode')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Payfast Fields -->
                            <div class="payment-method-fields payment-method-Payfast" style="display: none;">
                                <div class="row">
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="payfast_merchant_id">{{ __('Merchant ID') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.payfast_merchant_id') is-invalid @enderror" 
                                                placeholder="{{ __('Merchant ID') }}" name="configuration[payfast_merchant_id]" id="payfast_merchant_id" 
                                                value="{{ old('configuration.payfast_merchant_id', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['payfast_merchant_id'] ?? '') : '') }}" />
                                            @error('configuration.payfast_merchant_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="payfast_merchant_key">{{ __('Merchant Key') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.payfast_merchant_key') is-invalid @enderror" 
                                                placeholder="{{ __('Merchant Key') }}" name="configuration[payfast_merchant_key]" id="payfast_merchant_key" 
                                                value="{{ old('configuration.payfast_merchant_key', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['payfast_merchant_key'] ?? '') : '') }}" />
                                            @error('configuration.payfast_merchant_key')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="payfast_passphrase">{{ __('Pass Phrase') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.payfast_passphrase') is-invalid @enderror" 
                                                placeholder="{{ __('Pass Phrase') }}" name="configuration[payfast_passphrase]" id="payfast_passphrase" 
                                                value="{{ old('configuration.payfast_passphrase', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['payfast_passphrase'] ?? '') : '') }}" />
                                            @error('configuration.payfast_passphrase')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="payfast_mode">{{ __('Mode') }} {!! requiredField() !!}</label>
                                            <select class="form-select select2 @error('configuration.payfast_mode') is-invalid @enderror" 
                                                name="configuration[payfast_mode]" id="payfast_mode">
                                                <option value="">{{ __('Select') }} {{ __('Mode') }}</option>
                                                <option value="Sandbox" {{ old('configuration.payfast_mode', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['payfast_mode'] ?? '') : '') == 'Sandbox' ? 'selected' : '' }}>
                                                    {{ __('Sandbox') }}
                                                </option>
                                                <option value="Live" {{ old('configuration.payfast_mode', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['payfast_mode'] ?? '') : '') == 'Live' ? 'selected' : '' }}>
                                                    {{ __('Live') }}
                                                </option>
                                            </select>
                                            @error('configuration.payfast_mode')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Skrill Fields -->
                            <div class="payment-method-fields payment-method-Skrill" style="display: none;">
                                <div class="row">
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="skrill_merchant_email">{{ __('Merchant Email') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.skrill_merchant_email') is-invalid @enderror" 
                                                placeholder="{{ __('Merchant Email') }}" name="configuration[skrill_merchant_email]" id="skrill_merchant_email" 
                                                value="{{ old('configuration.skrill_merchant_email', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['skrill_merchant_email'] ?? '') : '') }}" />
                                            @error('configuration.skrill_merchant_email')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="skrill_api_password">{{ __('API Password') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.skrill_api_password') is-invalid @enderror" 
                                                placeholder="{{ __('API Password') }}" name="configuration[skrill_api_password]" id="skrill_api_password" 
                                                value="{{ old('configuration.skrill_api_password', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['skrill_api_password'] ?? '') : '') }}" />
                                            @error('configuration.skrill_api_password')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="skrill_mode">{{ __('Mode') }} {!! requiredField() !!}</label>
                                            <select class="form-select select2 @error('configuration.skrill_mode') is-invalid @enderror" 
                                                name="configuration[skrill_mode]" id="skrill_mode">
                                                <option value="">{{ __('Select') }} {{ __('Mode') }}</option>
                                                <option value="Sandbox" {{ old('configuration.skrill_mode', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['skrill_mode'] ?? '') : '') == 'Sandbox' ? 'selected' : '' }}>
                                                    {{ __('Sandbox') }}
                                                </option>
                                                <option value="Live" {{ old('configuration.skrill_mode', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['skrill_mode'] ?? '') : '') == 'Live' ? 'selected' : '' }}>
                                                    {{ __('Live') }}
                                                </option>
                                            </select>
                                            @error('configuration.skrill_mode')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- PhonePe Fields -->
                            <div class="payment-method-fields payment-method-PhonePe" style="display: none;">
                                <div class="row">
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="phonepe_client_id">{{ __('Client ID') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.phonepe_client_id') is-invalid @enderror" 
                                                placeholder="{{ __('Client ID') }}" name="configuration[phonepe_client_id]" id="phonepe_client_id" 
                                                value="{{ old('configuration.phonepe_client_id', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['phonepe_client_id'] ?? '') : '') }}" />
                                            @error('configuration.phonepe_client_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="phonepe_merchant_user_id">{{ __('Merchant User ID') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.phonepe_merchant_user_id') is-invalid @enderror" 
                                                placeholder="{{ __('Merchant User ID') }}" name="configuration[phonepe_merchant_user_id]" id="phonepe_merchant_user_id" 
                                                value="{{ old('configuration.phonepe_merchant_user_id', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['phonepe_merchant_user_id'] ?? '') : '') }}" />
                                            @error('configuration.phonepe_merchant_user_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="phonepe_key_index">{{ __('Key Index') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.phonepe_key_index') is-invalid @enderror" 
                                                placeholder="{{ __('Key Index') }}" name="configuration[phonepe_key_index]" id="phonepe_key_index" 
                                                value="{{ old('configuration.phonepe_key_index', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['phonepe_key_index'] ?? '') : '') }}" />
                                            @error('configuration.phonepe_key_index')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="phonepe_secret_key">{{ __('Secret Key') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.phonepe_secret_key') is-invalid @enderror" 
                                                placeholder="{{ __('Secret Key') }}" name="configuration[phonepe_secret_key]" id="phonepe_secret_key" 
                                                value="{{ old('configuration.phonepe_secret_key', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['phonepe_secret_key'] ?? '') : '') }}" />
                                            @error('configuration.phonepe_secret_key')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="phonepe_mode">{{ __('Mode') }} {!! requiredField() !!}</label>
                                            <select class="form-select select2 @error('configuration.phonepe_mode') is-invalid @enderror" 
                                                name="configuration[phonepe_mode]" id="phonepe_mode">
                                                <option value="">{{ __('Select') }} {{ __('Mode') }}</option>
                                                <option value="Sandbox" {{ old('configuration.phonepe_mode', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['phonepe_mode'] ?? '') : '') == 'Sandbox' ? 'selected' : '' }}>
                                                    {{ __('Sandbox') }}
                                                </option>
                                                <option value="Live" {{ old('configuration.phonepe_mode', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['phonepe_mode'] ?? '') : '') == 'Live' ? 'selected' : '' }}>
                                                    {{ __('Live') }}
                                                </option>
                                            </select>
                                            @error('configuration.phonepe_mode')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Telr Fields -->
                            <div class="payment-method-fields payment-method-Telr" style="display: none;">
                                <div class="row">
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="telr_store_id">{{ __('Store ID') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.telr_store_id') is-invalid @enderror" 
                                                placeholder="{{ __('Store ID') }}" name="configuration[telr_store_id]" id="telr_store_id" 
                                                value="{{ old('configuration.telr_store_id', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['telr_store_id'] ?? '') : '') }}" />
                                            @error('configuration.telr_store_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="telr_store_auth_key">{{ __('Store Auth Key') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.telr_store_auth_key') is-invalid @enderror" 
                                                placeholder="{{ __('Store Auth Key') }}" name="configuration[telr_store_auth_key]" id="telr_store_auth_key" 
                                                value="{{ old('configuration.telr_store_auth_key', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['telr_store_auth_key'] ?? '') : '') }}" />
                                            @error('configuration.telr_store_auth_key')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="telr_mode">{{ __('Mode') }} {!! requiredField() !!}</label>
                                            <select class="form-select select2 @error('configuration.telr_mode') is-invalid @enderror" 
                                                name="configuration[telr_mode]" id="telr_mode">
                                                <option value="">{{ __('Select') }} {{ __('Mode') }}</option>
                                                <option value="Sandbox" {{ old('configuration.telr_mode', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['telr_mode'] ?? '') : '') == 'Sandbox' ? 'selected' : '' }}>
                                                    {{ __('Sandbox') }}
                                                </option>
                                                <option value="Live" {{ old('configuration.telr_mode', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['telr_mode'] ?? '') : '') == 'Live' ? 'selected' : '' }}>
                                                    {{ __('Live') }}
                                                </option>
                                            </select>
                                            @error('configuration.telr_mode')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Iyzico Fields -->
                            <div class="payment-method-fields payment-method-Iyzico" style="display: none;">
                                <div class="row">
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="iyzico_api_key">{{ __('API Key') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.iyzico_api_key') is-invalid @enderror" 
                                                placeholder="{{ __('API Key') }}" name="configuration[iyzico_api_key]" id="iyzico_api_key" 
                                                value="{{ old('configuration.iyzico_api_key', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['iyzico_api_key'] ?? '') : '') }}" />
                                            @error('configuration.iyzico_api_key')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="iyzico_secret_key">{{ __('Secret Key') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.iyzico_secret_key') is-invalid @enderror" 
                                                placeholder="{{ __('Secret Key') }}" name="configuration[iyzico_secret_key]" id="iyzico_secret_key" 
                                                value="{{ old('configuration.iyzico_secret_key', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['iyzico_secret_key'] ?? '') : '') }}" />
                                            @error('configuration.iyzico_secret_key')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="iyzico_mode">{{ __('Mode') }} {!! requiredField() !!}</label>
                                            <select class="form-select select2 @error('configuration.iyzico_mode') is-invalid @enderror" 
                                                name="configuration[iyzico_mode]" id="iyzico_mode">
                                                <option value="">{{ __('Select') }} {{ __('Mode') }}</option>
                                                <option value="Sandbox" {{ old('configuration.iyzico_mode', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['iyzico_mode'] ?? '') : '') == 'Sandbox' ? 'selected' : '' }}>
                                                    {{ __('Sandbox') }}
                                                </option>
                                                <option value="Live" {{ old('configuration.iyzico_mode', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['iyzico_mode'] ?? '') : '') == 'Live' ? 'selected' : '' }}>
                                                    {{ __('Live') }}
                                                </option>
                                            </select>
                                            @error('configuration.iyzico_mode')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pesapal Fields -->
                            <div class="payment-method-fields payment-method-Pesapal" style="display: none;">
                                <div class="row">
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="pesapal_consumer_key">{{ __('Consumer Key') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.pesapal_consumer_key') is-invalid @enderror" 
                                                placeholder="{{ __('Consumer Key') }}" name="configuration[pesapal_consumer_key]" id="pesapal_consumer_key" 
                                                value="{{ old('configuration.pesapal_consumer_key', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['pesapal_consumer_key'] ?? '') : '') }}" />
                                            @error('configuration.pesapal_consumer_key')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="pesapal_consumer_secret">{{ __('Consumer Secret') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.pesapal_consumer_secret') is-invalid @enderror" 
                                                placeholder="{{ __('Consumer Secret') }}" name="configuration[pesapal_consumer_secret]" id="pesapal_consumer_secret" 
                                                value="{{ old('configuration.pesapal_consumer_secret', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['pesapal_consumer_secret'] ?? '') : '') }}" />
                                            @error('configuration.pesapal_consumer_secret')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="pesapal_ipn_id">{{ __('IPN Id') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.pesapal_ipn_id') is-invalid @enderror" 
                                                placeholder="{{ __('IPN Id') }}" name="configuration[pesapal_ipn_id]" id="pesapal_ipn_id" 
                                                value="{{ old('configuration.pesapal_ipn_id', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['pesapal_ipn_id'] ?? '') : '') }}" />
                                            @error('configuration.pesapal_ipn_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="pesapal_mode">{{ __('Mode') }} {!! requiredField() !!}</label>
                                            <select class="form-select select2 @error('configuration.pesapal_mode') is-invalid @enderror" 
                                                name="configuration[pesapal_mode]" id="pesapal_mode">
                                                <option value="">{{ __('Select') }} {{ __('Mode') }}</option>
                                                <option value="Sandbox" {{ old('configuration.pesapal_mode', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['pesapal_mode'] ?? '') : '') == 'Sandbox' ? 'selected' : '' }}>
                                                    {{ __('Sandbox') }}
                                                </option>
                                                <option value="Live" {{ old('configuration.pesapal_mode', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['pesapal_mode'] ?? '') : '') == 'Live' ? 'selected' : '' }}>
                                                    {{ __('Live') }}
                                                </option>
                                            </select>
                                            @error('configuration.pesapal_mode')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Midtrans Fields -->
                            <div class="payment-method-fields payment-method-Midtrans" style="display: none;">
                                <div class="row">
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="midtrans_server_key">{{ __('Server Key') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.midtrans_server_key') is-invalid @enderror" 
                                                placeholder="{{ __('Server Key') }}" name="configuration[midtrans_server_key]" id="midtrans_server_key" 
                                                value="{{ old('configuration.midtrans_server_key', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['midtrans_server_key'] ?? '') : '') }}" />
                                            @error('configuration.midtrans_server_key')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="midtrans_client_key">{{ __('Client Key') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.midtrans_client_key') is-invalid @enderror" 
                                                placeholder="{{ __('Client Key') }}" name="configuration[midtrans_client_key]" id="midtrans_client_key" 
                                                value="{{ old('configuration.midtrans_client_key', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['midtrans_client_key'] ?? '') : '') }}" />
                                            @error('configuration.midtrans_client_key')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="midtrans_mode">{{ __('Mode') }} {!! requiredField() !!}</label>
                                            <select class="form-select select2 @error('configuration.midtrans_mode') is-invalid @enderror" 
                                                name="configuration[midtrans_mode]" id="midtrans_mode">
                                                <option value="">{{ __('Select') }} {{ __('Mode') }}</option>
                                                <option value="Sandbox" {{ old('configuration.midtrans_mode', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['midtrans_mode'] ?? '') : '') == 'Sandbox' ? 'selected' : '' }}>
                                                    {{ __('Sandbox') }}
                                                </option>
                                                <option value="Live" {{ old('configuration.midtrans_mode', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['midtrans_mode'] ?? '') : '') == 'Live' ? 'selected' : '' }}>
                                                    {{ __('Live') }}
                                                </option>
                                            </select>
                                            @error('configuration.midtrans_mode')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- MyFatoorah Fields -->
                            <div class="payment-method-fields payment-method-MyFatoorah" style="display: none;">
                                <div class="row">
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="myfatoorah_api_key">{{ __('API Key') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.myfatoorah_api_key') is-invalid @enderror" 
                                                placeholder="{{ __('API Key') }}" name="configuration[myfatoorah_api_key]" id="myfatoorah_api_key" 
                                                value="{{ old('configuration.myfatoorah_api_key', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['myfatoorah_api_key'] ?? '') : '') }}" />
                                            @error('configuration.myfatoorah_api_key')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="myfatoorah_mode">{{ __('Mode') }} {!! requiredField() !!}</label>
                                            <select class="form-select select2 @error('configuration.myfatoorah_mode') is-invalid @enderror" 
                                                name="configuration[myfatoorah_mode]" id="myfatoorah_mode">
                                                <option value="">{{ __('Select') }} {{ __('Mode') }}</option>
                                                <option value="Sandbox" {{ old('configuration.myfatoorah_mode', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['myfatoorah_mode'] ?? '') : '') == 'Sandbox' ? 'selected' : '' }}>
                                                    {{ __('Sandbox') }}
                                                </option>
                                                <option value="Live" {{ old('configuration.myfatoorah_mode', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['myfatoorah_mode'] ?? '') : '') == 'Live' ? 'selected' : '' }}>
                                                    {{ __('Live') }}
                                                </option>
                                            </select>
                                            @error('configuration.myfatoorah_mode')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- EasyPaisa Fields -->
                            <div class="payment-method-fields payment-method-EasyPaisa" style="display: none;">
                                <div class="row">
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="easypaisa_store_id">{{ __('Store ID') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.easypaisa_store_id') is-invalid @enderror" 
                                                placeholder="{{ __('Store ID') }}" name="configuration[easypaisa_store_id]" id="easypaisa_store_id" 
                                                value="{{ old('configuration.easypaisa_store_id', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['easypaisa_store_id'] ?? '') : '') }}" />
                                            @error('configuration.easypaisa_store_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="easypaisa_hash_key">{{ __('Hash Key') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.easypaisa_hash_key') is-invalid @enderror" 
                                                placeholder="{{ __('Hash Key') }}" name="configuration[easypaisa_hash_key]" id="easypaisa_hash_key" 
                                                value="{{ old('configuration.easypaisa_hash_key', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['easypaisa_hash_key'] ?? '') : '') }}" />
                                            @error('configuration.easypaisa_hash_key')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="easypaisa_username">{{ __('Username') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.easypaisa_username') is-invalid @enderror" 
                                                placeholder="{{ __('Username') }}" name="configuration[easypaisa_username]" id="easypaisa_username" 
                                                value="{{ old('configuration.easypaisa_username', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['easypaisa_username'] ?? '') : '') }}" />
                                            @error('configuration.easypaisa_username')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="easypaisa_password">{{ __('Password') }} {!! requiredField() !!}</label>
                                            <input type="text" class="form-control @error('configuration.easypaisa_password') is-invalid @enderror" 
                                                placeholder="{{ __('Password') }}" name="configuration[easypaisa_password]" id="easypaisa_password" 
                                                value="{{ old('configuration.easypaisa_password', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['easypaisa_password'] ?? '') : '') }}" />
                                            @error('configuration.easypaisa_password')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="mb-5">
                                            <label class="form-label" for="easypaisa_mode">{{ __('Mode') }} {!! requiredField() !!}</label>
                                            <select class="form-select select2 @error('configuration.easypaisa_mode') is-invalid @enderror" 
                                                name="configuration[easypaisa_mode]" id="easypaisa_mode">
                                                <option value="">{{ __('Select') }} {{ __('Mode') }}</option>
                                                <option value="Sandbox" {{ old('configuration.easypaisa_mode', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['easypaisa_mode'] ?? '') : '') == 'Sandbox' ? 'selected' : '' }}>
                                                    {{ __('Sandbox') }}
                                                </option>
                                                <option value="Live" {{ old('configuration.easypaisa_mode', isset($payment_method) && $payment_method->configuration ? (($payment_method->configuration ?? [])['easypaisa_mode'] ?? '') : '') == 'Live' ? 'selected' : '' }}>
                                                    {{ __('Live') }}
                                                </option>
                                            </select>
                                            @error('configuration.easypaisa_mode')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText(isset($payment_method) ? $payment_method : '') !!}
                            </button>
                            <a href="{{ route('payment-method.index') }}" class="btn btn-primary waves-effect waves-light">
                                {!! backIconWithText() !!}
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
@push('page-js')
<script>
$(function () {
    "use strict";

    // Payment methods that require configuration fields
    const paymentMethodsWithFields = [
        'Paypal', 'Stripe', 'Razorpay', 'Paystack', 'Paytm', 'Flutterwave', 
        'SslCommerz', 'Mollie', 'Senangpay', 'Bkash', 'Mercadopago', 'Cashfree', 
        'Payfast', 'Skrill', 'PhonePe', 'Telr', 'Iyzico', 'Pesapal', 'Midtrans', 
        'MyFatoorah', 'EasyPaisa'
    ];

    // Function to show/hide payment method fields
    function togglePaymentMethodFields(accountType) {
        // Hide all payment method fields first
        $('.payment-method-fields').hide();
        
        // Show fields for selected payment method
        if (accountType && paymentMethodsWithFields.includes(accountType)) {
            $('.payment-method-' + accountType).show();
        }
    }

    // Handle account type change
    $('#account_type').on('change', function() {
        const selectedType = $(this).val();
        togglePaymentMethodFields(selectedType);
    });

    // Initialize on page load (for edit mode)
    const initialAccountType = $('#account_type').val();
    if (initialAccountType) {
        togglePaymentMethodFields(initialAccountType);
    }
});
</script>
@endpush

