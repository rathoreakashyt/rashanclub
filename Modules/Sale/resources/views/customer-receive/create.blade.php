@extends('backend.backend_layout')
@section('page-title', isset($customer_receive) ? __('Update') . ' ' . __('Customer_Receive') : __('Add') . ' ' . __('Customer_Receive') )
@push('page-css')
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($customer_receive) ? __('Update') . ' ' . __('Customer_Receive') : __('Add') . ' ' . __('Customer_Receive') }}</h4>
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
                    'label' => isset($customer_receive) ? __('Update') . ' ' . __('Customer_Receive') : __('Add') . ' ' . __('Customer_Receive'),
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
            <form action="{{ isset($customer_receive) ? route('customer-receive.update', encrypt($customer_receive->id)) : route('customer-receive.store') }}" method="POST">
                @csrf
                @if(isset($customer_receive))
                    @method('PUT')
                @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="reference_no">{{ __('reference_no') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('reference_no') is-invalid @enderror" 
                                        placeholder="{{ __('reference_no') }}" name="reference_no" id="reference_no" 
                                        value="{{ old('reference_no', isset($customer_receive) ? $customer_receive->reference_no : ($reference_no ?? '')) }}" />
                                    @error('reference_no')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="date">{{ __('Date') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control datePicker @error('date') is-invalid @enderror" 
                                        placeholder="{{ __('Date') }}" name="date" id="date" 
                                        value="{{ old('date', isset($customer_receive) ? $customer_receive->date : '') }}" readonly />
                                    @error('date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="customer_id">{{ __('Customer') }} {!! requiredField() !!}</label>
                                    <select class="form-select select2 @error('customer_id') is-invalid @enderror" 
                                        name="customer_id" id="customer_id" data-placeholder="{{ __('Select') }} {{ __('Customer') }}">
                                        <option value="">{{ __('Select') }} {{ __('Customer') }}</option>
                                        @foreach($customers as $customer)
                                            <option value="{{ $customer->id }}" {{ old('customer_id', $customer_receive->customer_id ?? '') == $customer->id ? 'selected' : '' }}>
                                                {{ $customer->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('customer_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror

               
                                    <div id="customer_balance_display" class="mt-2" style="display: none;">
                                        <small class="text-muted">
                                            <strong>{{ __('Current Balance') }}:</strong> 
                                            <span id="customer_balance_amount" class="fw-bold"></span>
                                            <span id="customer_balance_type" class="badge ms-1"></span>
                                        </small>
                                    </div>

                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="amount">{{ __('Amount') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control number-input @error('amount') is-invalid @enderror" 
                                        placeholder="{{ __('Amount') }}" name="amount" id="amount" 
                                        value="{{ old('amount', isset($customer_receive) ? $customer_receive->amount : '') }}" />
                                    @error('amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="payment_method_id">{{ __('Payment_Method') }} {!! requiredField() !!}</label>
                                    <select class="form-select select2 @error('payment_method_id') is-invalid @enderror" 
                                        name="payment_method_id" id="payment_method_id" data-placeholder="{{ __('Select') }} {{ __('Payment_Method') }}">
                                        <option value="">{{ __('Select') }} {{ __('Payment_Method') }}</option>
                                        @foreach($payment_methods as $payment_method)
                                            <option value="{{ $payment_method->id }}" {{ old('payment_method_id', $customer_receive->payment_method_id ?? '') == $payment_method->id ? 'selected' : '' }}>
                                                {{ $payment_method->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('payment_method_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="note">{{ __('Note') }}</label>
                                    <textarea type="text" class="form-control @error('note') is-invalid @enderror" 
                                        placeholder="{{ __('Note') }}" name="note" id="note" 
                                        >{{ old('note', isset($customer_receive) ? $customer_receive->note : '') }}</textarea>
                                    @error('note')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText(isset($customer_receive) ? $customer_receive : '') !!}
                            </button>
                            <a href="{{ route('customer-receive.index') }}" class="btn btn-primary waves-effect waves-light">
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
@routes
<script src="{{ asset('backend_assets/js/pages_js/add_customer_receive.js') }}"></script>
@endpush
