@extends('backend.backend_layout')
@section('page-title', isset($supplier_payment) ? __('Update') . ' ' . __('Supplier Payment') : __('Add') . ' ' . __('Supplier Payment') )
@push('page-css')
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($supplier_payment) ? __('Update') . ' ' . __('Supplier Payment') : __('Add') . ' ' . __('Supplier Payment') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Purchase'), 
                    'link' => '#'
                ],
                [
                    'label' => isset($supplier_payment) ? __('Update') . ' ' . __('Supplier_Payment') : __('Add') . ' ' . __('Supplier_Payment'),
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
            <form action="{{ isset($supplier_payment) ? route('supplier-payment.update', encrypt($supplier_payment->id)) : route('supplier-payment.store') }}" method="POST">
                @csrf
                @if(isset($supplier_payment))
                    @method('PUT')
                @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="reference_no">{{ __('Reference No') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('reference_no') is-invalid @enderror" 
                                        placeholder="{{ __('Reference No') }}" name="reference_no" id="reference_no" 
                                        value="{{ old('reference_no', isset($supplier_payment) ? $supplier_payment->reference_no : (isset($reference_no) ? $reference_no : '')) }}" />
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
                                        value="{{ old('date', isset($supplier_payment) ? $supplier_payment->date : date('Y-m-d')) }}" />
                                    @error('date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="supplier_id">{{ __('Supplier') }} {!! requiredField() !!}</label>
                                    <select class="form-select select2 @error('supplier_id') is-invalid @enderror" 
                                        name="supplier_id" id="supplier_id" data-placeholder="{{ __('Select') }} {{ __('Supplier') }}">
                                        <option value="">{{ __('Select') }} {{ __('Supplier') }}</option>
                                        @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}"
                                                {{ old('supplier_id', $supplier_payment->supplier_id ?? '') == $supplier->id ? 'selected' : '' }}>
                                                {{ $supplier->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('supplier_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    
                                    <div id="supplier_balance_display" class="mt-2" style="display: none;">
                                        <small class="text-muted">
                                            <strong>{{ __('Current Balance') }}:</strong> 
                                            <span id="supplier_balance_amount" class="fw-bold"></span>
                                            <span id="supplier_balance_type" class="badge ms-1"></span>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="amount">{{ __('Amount') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control number-input @error('amount') is-invalid @enderror" 
                                        placeholder="{{ __('Amount') }}" name="amount" id="amount" 
                                        value="{{ old('amount', isset($supplier_payment) ? $supplier_payment->amount : '') }}" />
                                    @error('amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="payment_method_id">{{ __('Payment Method') }} {!! requiredField() !!}</label>
                                    <select class="form-select select2 @error('payment_method_id') is-invalid @enderror" 
                                        name="payment_method_id" id="payment_method_id" data-placeholder="{{ __('Select') }} {{ __('Payment Method') }}">
                                        <option value="">{{ __('Select') }} {{ __('Payment Method') }}</option>
                                        @foreach($payment_methods as $method)
                                            <option value="{{ $method->id }}"
                                                {{ old('payment_method_id', $supplier_payment->payment_method_id ?? '') == $method->id ? 'selected' : '' }}>
                                                {{ $method->name }}
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
                                        >{{ old('note', isset($supplier_payment) ? $supplier_payment->note : '') }}</textarea>
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
                                {!! submitIconWithText(isset($supplier_payment) ? $supplier_payment : '') !!}
                            </button>
                            <a href="{{ route('supplier-payment.index') }}" class="btn btn-primary waves-effect waves-light">
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
<script src="{{ asset('backend_assets/js/pages_js/add_supplier_payment.js') }}"></script>
@endpush

