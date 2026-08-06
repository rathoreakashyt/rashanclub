@extends('backend.backend_layout')
@section('page-title', isset($customer) ? __('Update') . ' ' . __('Customer') : __('Add') . ' ' . __('Customer') )
@push('page-css')
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($customer) ? __('Update') . ' ' . __('Customer') : __('Add') . ' ' . __('Customer') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Customer'), 
                    'link' => '#'
                ],
                [
                    'label' => isset($customer) ? __('Update') . ' ' . __('Customer') : __('Add') . ' ' . __('Customer'),
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
            <form action="{{ isset($customer) ? route('customer.update', encrypt($customer->id)) : route('customer.store') }}" method="POST">
                @csrf
                @if(isset($customer))
                    @method('PUT')
                @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="name">{{ __('Name') }} {!! requiredField() !!}</label>
                                    @php
                                        $isWalkInCustomer = isset($customer) && $customer->name === 'Walk-in Customer';
                                    @endphp
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                        placeholder="{{ __('Name') }}" name="name" id="name" 
                                        value="{{ old('name', isset($customer) ? $customer->name : '') }}" 
                                        {{ $isWalkInCustomer ? 'readonly' : '' }} />
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="phone">{{ __('Phone') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('phone') is-invalid @enderror" 
                                        placeholder="{{ __('Phone') }}" name="phone" id="phone" 
                                        value="{{ old('phone', isset($customer) ? $customer->phone : '') }}" />
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="email">{{ __('Email') }}</label>
                                    <input type="text" class="form-control @error('email') is-invalid @enderror" 
                                        placeholder="{{ __('Email') }}" name="email" id="email" 
                                        value="{{ old('email', isset($customer) ? $customer->email : '') }}" />
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <label class="form-label" for="opening_balance">{{ __('opening_balance') }}</label>
                                <div class="d-flex jsutify-content-between w-100">
                                    <div class="mb-5 me-1 flex-grow-1 w-50">
                                        <input type="text" class="form-control number-input @error('opening_balance') is-invalid @enderror" 
                                            placeholder="{{ __('opening_balance') }}" name="opening_balance" id="opening_balance" 
                                            value="{{ old('opening_balance', isset($customer) ? $customer->opening_balance : '') }}" />
                                        @error('opening_balance')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="mb-5 flex-grow-1 w-50">
                                        <select class="w-100 form-select select2 form-select @error('opening_balance_type') is-invalid @enderror" 
                                            name="opening_balance_type" id="opening_balance_type" data-placeholder="{{ __('opening_balance_type') }}">
                                            <option value="">{{ __('opening_balance_type') }}</option>
                                            <option value="Debit" {{ old('opening_balance_type', $customer->opening_balance_type ?? '') == 'Debit' ? 'selected' : '' }}>
                                                {{ __('Debit') }}
                                            </option>
                                            <option value="Credit" {{ old('opening_balance_type', $customer->opening_balance_type ?? '') == 'Credit' ? 'selected' : '' }}>
                                                {{ __('Credit') }}
                                            </option>
                                        </select>
                                        @error('opening_balance_type')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="credit_limit">{{ __('Credit_Limit') }}</label>
                                    <input type="text" class="form-control number-input @error('credit_limit') is-invalid @enderror" 
                                        placeholder="{{ __('Credit_Limit') }}" name="credit_limit" id="credit_limit" 
                                        value="{{ old('credit_limit', isset($customer) ? $customer->credit_limit : '') }}" />
                                    @error('credit_limit')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 d-none">
                                <div class="mb-5">
                                    <label class="form-label" for="discount">{{ __('Default_Discount') }}</label>
                                    <input type="text" class="form-control @error('discount') is-invalid @enderror" 
                                        placeholder="{{ __('Discount_10_or_percentage') }}" name="discount" id="discount" 
                                        value="{{ old('discount', isset($customer) ? $customer->discount : '') }}" />
                                    @error('discount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 d-none">
                                <div class="mb-5">
                                    <label class="form-label" for="customer_type">{{ __('customer_type') }}</label>
                                    <select class="form-select select2 form-select @error('customer_type') is-invalid @enderror" 
                                        name="customer_type" id="customer_type" data-placeholder="{{ __('customer_type') }}">
                                        <option value="">{{ __('Select') }} {{ __('customer_type') }}</option>
                                        <option value="1" {{ old('customer_type', isset($customer) ? $customer->customer_type : '') == '1' ? 'selected' : '' }}>
                                            {{ __('Retail') }}
                                        </option>
                                        <option value="2" {{ old('customer_type', isset($customer) ? $customer->customer_type : '') == '2' ? 'selected' : '' }}>
                                            {{ __('Wholesale') }}
                                        </option>
                                    </select>
                                    @error('customer_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="business_type">{{ __('Business_Type') }} {!! requiredField() !!}</label>
                                    <select class="form-select select2 form-select @error('business_type') is-invalid @enderror" 
                                        name="business_type" id="business_type" data-placeholder="{{ __('Business_Type') }}">
                                        <option value="B2B" {{ old('business_type', $customer->business_type ?? 'B2C') == 'B2B' ? 'selected' : '' }}>
                                            {{ __('B2B') }}
                                        </option>
                                        <option value="B2C" {{ old('business_type', $customer->business_type ?? 'B2C') == 'B2C' ? 'selected' : '' }}>
                                            {{ __('B2C') }}
                                        </option>
                                    </select>
                                    @error('business_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="address">{{ __('Address') }}</label>
                                    <textarea type="text" class="form-control @error('address') is-invalid @enderror" 
                                        placeholder="{{ __('Address') }}" name="address" id="address" 
                                        >{{ old('address', isset($customer) ? $customer->address : '') }}</textarea>
                                    @error('address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="clear-fix"></div>
                            @if(isEnableGST() && session('company.collect_tax') == 'Yes')
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 flex-grow-1">
                                    <label class="form-label" for="same_or_diff_state">{{ __('same_or_diff_state') }} {!! requiredField() !!}</label>
                                    <select class="w-100 form-select select2 form-select @error('same_or_diff_state') is-invalid @enderror" 
                                        name="same_or_diff_state" id="same_or_diff_state" data-placeholder="{{ __('same_or_diff_state') }}">
                                        <option value="1" {{ old('same_or_diff_state', $customer->same_or_diff_state ?? '') == '1' ? 'selected' : '' }}>
                                            {{ __('Same_State') }}
                                        </option>
                                        <option value="2" {{ old('same_or_diff_state', $customer->same_or_diff_state ?? '') == '2' ? 'selected' : '' }}>
                                            {{ __('Different_State') }}
                                        </option>
                                    </select>
                                    @error('same_or_diff_state')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 flex-grow-1">
                                    <label class="form-label" for="state_id">{{ __('State') }} {!! requiredField() !!}</label>
                                    <select class="w-100 form-select select2 form-select @error('state_id') is-invalid @enderror" 
                                        name="state_id" id="state_id" data-placeholder="{{ __('State') }}">
                                        <option value="">{{ __('Select') }} {{ __('State') }}</option>
                                        @foreach($states ?? [] as $state)
                                            <option value="{{ $state->id }}" data-state-code="{{ $state->state_code }}"
                                                {{ old('state_id', isset($customer) ? $customer->state_id : '') == $state->id ? 'selected' : '' }}>
                                                {{ $state->state_name }} ({{ $state->state_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('state_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="gst_number">{{ __('GSTIN') }}<span class="gstin-required text-danger" style="{{ old('business_type', isset($customer) ? $customer->business_type : 'B2C') === 'B2B' ? '' : 'display:none' }}">*</span></label>
                                    <input type="text" class="form-control @error('gst_number') is-invalid @enderror"
                                        placeholder="27AABCU9603R1ZM" name="gst_number" id="gst_number" maxlength="15"
                                        value="{{ old('gst_number', isset($customer) ? $customer->gst_number : '') }}" />
                                    <div class="form-text" id="gstin-hint">{{ __('15 character Indian GSTIN') }}</div>
                                    <div class="invalid-feedback" id="gst_number-error">@error('gst_number'){{ $message }}@enderror</div>
                                </div>
                            </div>
                            @endif

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="date_of_birth">{{ __('date_of_birth') }}</label>
                                    <input type="text" class="form-control datePicker @error('date_of_birth') is-invalid @enderror" 
                                        placeholder="{{ __('date_of_birth') }}" name="date_of_birth" id="date_of_birth" 
                                        value="{{ old('date_of_birth', isset($customer) ? $customer->date_of_birth : '') }}" readonly />
                                    @error('date_of_birth')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="date_of_anniversary">{{ __('date_of_anniversary') }}</label>
                                    <input type="text" class="form-control datePicker @error('date_of_anniversary') is-invalid @enderror" 
                                        placeholder="{{ __('date_of_anniversary') }}" name="date_of_anniversary" id="date_of_anniversary" 
                                        value="{{ old('date_of_anniversary', isset($customer) ? $customer->date_of_anniversary : '') }}" readonly />
                                    @error('date_of_anniversary')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText(isset($customer) ? $customer : '') !!}
                            </button>
                            <a href="{{ route('customer.index') }}" class="btn btn-primary waves-effect waves-light">
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
@if(isEnableGST() && session('company.collect_tax') == 'Yes')
<script>
(function() {
    const GSTIN_REGEX = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[A-Z0-9]{1}Z[A-Z0-9]{1}$/;

    function validateGstinForState() {
        const gstEl = document.getElementById('gst_number');
        const stateEl = document.getElementById('state_id');
        const errorEl = document.getElementById('gst_number-error');

        if (!gstEl || !stateEl) return;

        const gstin = gstEl.value.trim().toUpperCase().replace(/[\s-]/g, '');
        const selectedOption = stateEl.options[stateEl.selectedIndex];
        const stateCode = selectedOption?.dataset?.stateCode || '';

        gstEl.classList.remove('is-invalid');
        if (errorEl) errorEl.textContent = '';

        if (!gstin) return;

        if (gstin.length !== 15) {
            gstEl.classList.add('is-invalid');
            if (errorEl) errorEl.textContent = '{{ __("GSTIN must be exactly 15 characters.") }}';
            return;
        }

        if (!GSTIN_REGEX.test(gstin)) {
            gstEl.classList.add('is-invalid');
            if (errorEl) errorEl.textContent = '{{ __("Invalid GSTIN format.") }}';
            return;
        }

        if (stateCode && gstin.substring(0, 2) !== stateCode) {
            gstEl.classList.add('is-invalid');
            if (errorEl) errorEl.textContent = '{{ __("GSTIN state code must match the selected state.") }}';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        var businessType = document.getElementById('business_type');
        var gstinRequired = document.querySelector('.gstin-required');
        if (businessType && gstinRequired) {
            function toggleGstinRequired() {
                gstinRequired.style.display = businessType.value === 'B2B' ? '' : 'none';
            }
            businessType.addEventListener('change', toggleGstinRequired);
            toggleGstinRequired();
        }

        var gstEl = document.getElementById('gst_number');
        var stateEl = document.getElementById('state_id');
        if (gstEl) {
            gstEl.addEventListener('input', function() {
                this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                validateGstinForState();
            });
            gstEl.addEventListener('blur', validateGstinForState);
        }
        if (stateEl) {
            stateEl.addEventListener('change', validateGstinForState);
        }
    });
})();
</script>
@else
<script>
document.addEventListener('DOMContentLoaded', function() {
    var businessType = document.getElementById('business_type');
    var gstinRequired = document.querySelector('.gstin-required');
    if (businessType && gstinRequired) {
        function toggleGstinRequired() {
            gstinRequired.style.display = businessType.value === 'B2B' ? '' : 'none';
        }
        businessType.addEventListener('change', toggleGstinRequired);
        toggleGstinRequired();
    }
});
</script>
@endif
@endpush
