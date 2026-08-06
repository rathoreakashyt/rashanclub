@extends('backend.backend_layout')
@section('page-title', isset($outlet) ? __('Update') . ' ' . __('Outlet') : __('Add') . ' ' . __('Outlet'))
@push('page-css')
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($outlet) ? __('Update Outlet') : __('Add Outlet') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Outlet'), 
                    'link' => '#'
                ],
                [
                    'label' => isset($outlet) ? __('Update Outlet') : __('Add Outlet'),
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
            <form action="{{ isset($outlet) ? route('outlet.update', encrypt($outlet->id)) : route('outlet.store') }}" method="POST">
                @csrf
                @if(isset($outlet))
                    @method('PUT')
                @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="outlet_code">{{ __('Outlet') }} {{ __('Code') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('outlet_code') is-invalid @enderror" 
                                        placeholder="{{ __('Outlet') }} {{ __('Code') }}" name="outlet_code" id="outlet_code" 
                                        value="{{ old('outlet_code', isset($outlet) ? $outlet->outlet_code : $outlet_code) }}" readonly />
                                    @error('outlet_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="outlet_name">{{ __('Outlet') }} {{ __('Name') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('outlet_name') is-invalid @enderror" 
                                        placeholder="{{ __('Outlet') }} {{ __('Name') }}" name="outlet_name" id="outlet_name" 
                                        value="{{ old('outlet_name', isset($outlet) ? $outlet->outlet_name : '') }}" />
                                    @error('outlet_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="phone">{{ __('Phone') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('phone') is-invalid @enderror" 
                                        placeholder="{{ __('Phone') }}" name="phone" id="phone" 
                                        value="{{ old('phone', isset($outlet) ? $outlet->phone : '') }}" />
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
                                        value="{{ old('email', isset($outlet) ? $outlet->email : '') }}" />
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            @if(isEnableGST())
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="state_id">{{ __('State') }} {!! requiredField() !!}</label>
                                    <select id="state_id" class="select2 form-select @error('state_id') is-invalid @enderror"
                                        name="state_id" data-placeholder="{{ __('Select State') }}">
                                        <option value="">{{ __('Select State') }}</option>
                                        @foreach($states ?? [] as $state)
                                            <option value="{{ $state->id }}" data-state-code="{{ $state->state_code }}"
                                                {{ old('state_id', isset($outlet) ? $outlet->state_id : '') == $state->id ? 'selected' : '' }}>
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
                                    <label class="form-label" for="gstin">{{ __('GSTIN') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('gstin') is-invalid @enderror"
                                        placeholder="27AABCU9603R1ZM" name="gstin" id="gstin" maxlength="15"
                                        value="{{ old('gstin', isset($outlet) ? $outlet->gstin : '') }}" />
                                    <div class="form-text" id="gstin-hint">{{ __('15 character Indian GSTIN') }}</div>
                                    <div class="invalid-feedback" id="gstin-state-error">@error('gstin'){{ $message }}@enderror</div>
                                </div>
                            </div>
                            @endif
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="status">{{ __('Status') }} {!! requiredField() !!}</label>
                                    <select id="status" class="select2 form-select @error('active_status') is-invalid @enderror" 
                                        name="active_status" data-placeholder="{{ __('Status') }}">
                                        <option value="Active" {{ old('active_status', isset($outlet) && $outlet->active_status == 'Active' ? 'selected' : '') }}>{{ __('Active') }}</option>
                                        <option value="Inactive" {{ old('active_status', isset($outlet) && $outlet->active_status == 'Inactive' ? 'selected' : '') }}>{{ __('Inactive') }}</option>
                                    </select>
                                    @error('active_status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="address">{{ __('Address') }} {!! requiredField() !!}</label>
                                    <textarea class="form-control @error('address') is-invalid @enderror" 
                                        placeholder="{{ __('Address') }}" name="address" id="address">{{ old('address', isset($outlet) ? $outlet->address : '') }}</textarea>
                                    @error('address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText(isset($outlet) ? $outlet : '') !!}
                            </button>
                            <a href="{{ route('outlet.index') }}" class="btn btn-primary waves-effect waves-light">
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
@if(isEnableGST())
<script>
(function() {
    const GSTIN_REGEX = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[A-Z0-9]{1}Z[A-Z0-9]{1}$/;

    function validateGstinForState() {
        const gstinEl = document.getElementById('gstin');
        const stateEl = document.getElementById('state_id');
        const hintEl = document.getElementById('gstin-hint');
        const errorEl = document.getElementById('gstin-state-error');

        if (!gstinEl || !stateEl) return;

        const gstin = gstinEl.value.trim().toUpperCase().replace(/[\s-]/g, '');
        const selectedOption = stateEl.options[stateEl.selectedIndex];
        const stateCode = selectedOption?.dataset?.stateCode || '';

        gstinEl.classList.remove('is-invalid');
        if (errorEl) errorEl.textContent = '';

        if (!gstin) return;

        if (gstin.length !== 15) {
            gstinEl.classList.add('is-invalid');
            if (errorEl) errorEl.textContent = '{{ __("GSTIN must be exactly 15 characters.") }}';
            return;
        }

        if (!GSTIN_REGEX.test(gstin)) {
            gstinEl.classList.add('is-invalid');
            if (errorEl) errorEl.textContent = '{{ __("Invalid GSTIN format.") }}';
            return;
        }

        if (stateCode && gstin.substring(0, 2) !== stateCode) {
            gstinEl.classList.add('is-invalid');
            if (errorEl) errorEl.textContent = '{{ __("GSTIN state code must match the selected state.") }}';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const gstinEl = document.getElementById('gstin');
        const stateEl = document.getElementById('state_id');

        if (gstinEl) {
            gstinEl.addEventListener('input', function() {
                this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                validateGstinForState();
            });
            gstinEl.addEventListener('blur', validateGstinForState);
        }

        if (stateEl) {
            stateEl.addEventListener('change', validateGstinForState);
        }
    });
})();
</script>
@endif
@endpush

