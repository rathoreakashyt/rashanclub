@extends('backend.backend_layout')
@section('page-title', isset($servicing) ? __('Update') . ' ' . __('Servicing') : __('Add') . ' ' . __('Servicing') )
@push('page-css')
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($servicing) ? __('Update') . ' ' . __('Servicing') : __('Add') . ' ' . __('Servicing') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Servicing'), 
                    'link' => '#'
                ],
                [
                    'label' => isset($servicing) ? __('Update') . ' ' . __('Servicing') : __('Add') . ' ' . __('Servicing'),
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
            <form action="{{ isset($servicing) ? route('servicing.update', $servicing->encrypted_id) : route('servicing.store') }}" method="POST">
                @csrf
                @if(isset($servicing))
                    @method('PUT')
                @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="customer_id">{{ __('Customer') }} {!! requiredField() !!}</label>
                                    <select class="form-select select2 @error('customer_id') is-invalid @enderror" 
                                        name="customer_id" id="customer_id" data-placeholder="{{ __('Select Customer') }}">
                                        <option value="">{{ __('Select Customer') }}</option>
                                        @foreach($customers ?? [] as $customer)
                                            <option value="{{ $customer->id }}" 
                                                {{ old('customer_id', isset($servicing) ? $servicing->customer_id : '') == $customer->id ? 'selected' : '' }}>
                                                {{ $customer->name }} @if($customer->phone) ({{ $customer->phone }}) @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('customer_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="date">{{ __('Date') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control datePicker @error('date') is-invalid @enderror" 
                                        placeholder="{{ __('Date') }}" name="date" id="date" 
                                        value="{{ old('date', isset($servicing) ? $servicing->date->format('Y-m-d') : date('Y-m-d')) }}" readonly />
                                    @error('date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="product_name">{{ __('Product Name') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('product_name') is-invalid @enderror" 
                                        placeholder="{{ __('Product Name') }}" name="product_name" id="product_name" 
                                        value="{{ old('product_name', isset($servicing) ? $servicing->product_name : '') }}" />
                                    @error('product_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="product_model">{{ __('Product Model') }}</label>
                                    <input type="text" class="form-control @error('product_model') is-invalid @enderror" 
                                        placeholder="{{ __('Product Model') }}" name="product_model" id="product_model" 
                                        value="{{ old('product_model', isset($servicing) ? $servicing->product_model : '') }}" />
                                    @error('product_model')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="receiving_date">{{ __('Receiving Date') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control datePicker @error('receiving_date') is-invalid @enderror" 
                                        placeholder="{{ __('Receiving Date') }}" name="receiving_date" id="receiving_date" 
                                        value="{{ old('receiving_date', isset($servicing) ? $servicing->receiving_date->format('Y-m-d') : date('Y-m-d')) }}" readonly />
                                    @error('receiving_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="delivery_date">{{ __('Delivery Date') }}</label>
                                    <input type="text" class="form-control datePicker @error('delivery_date') is-invalid @enderror" 
                                        placeholder="{{ __('Delivery Date') }}" name="delivery_date" id="delivery_date" 
                                        value="{{ old('delivery_date', isset($servicing) && $servicing->delivery_date ? $servicing->delivery_date->format('Y-m-d') : '') }}" readonly />
                                    @error('delivery_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="servicing_charge">{{ __('Servicing Charge') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control number-input @error('servicing_charge') is-invalid @enderror" 
                                        placeholder="{{ __('Servicing Charge') }}" name="servicing_charge" id="servicing_charge" 
                                        value="{{ old('servicing_charge', isset($servicing) ? $servicing->servicing_charge : '') }}" />
                                    @error('servicing_charge')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="paid_amount">{{ __('Paid Amount') }}</label>
                                    <input type="text" class="form-control number-input @error('paid_amount') is-invalid @enderror" 
                                        placeholder="{{ __('Paid Amount') }}" name="paid_amount" id="paid_amount" 
                                        value="{{ old('paid_amount', isset($servicing) ? $servicing->paid_amount : '0') }}" />
                                    @error('paid_amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="due_amount">{{ __('Due Amount') }}</label>
                                    <input type="text" class="form-control number-input @error('due_amount') is-invalid @enderror" 
                                        placeholder="{{ __('Due Amount') }}" name="due_amount" id="due_amount" 
                                        value="{{ old('due_amount', isset($servicing) ? $servicing->due_amount : '0') }}" readonly />
                                    @error('due_amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="status">{{ __('Status') }} {!! requiredField() !!}</label>
                                    <select class="form-select select2 @error('status') is-invalid @enderror" 
                                        name="status" id="status" data-placeholder="{{ __('Select Status') }}">
                                        <option value="">{{ __('Select Status') }}</option>
                                        <option value="Pending" {{ old('status', isset($servicing) ? $servicing->status : '') == 'Pending' ? 'selected' : '' }}>
                                            {{ __('Pending') }}
                                        </option>
                                        <option value="In Progress" {{ old('status', isset($servicing) ? $servicing->status : '') == 'In Progress' ? 'selected' : '' }}>
                                            {{ __('In Progress') }}
                                        </option>
                                        <option value="Completed" {{ old('status', isset($servicing) ? $servicing->status : '') == 'Completed' ? 'selected' : '' }}>
                                            {{ __('Completed') }}
                                        </option>
                                        <option value="Delivered" {{ old('status', isset($servicing) ? $servicing->status : '') == 'Delivered' ? 'selected' : '' }}>
                                            {{ __('Delivered') }}
                                        </option>
                                    </select>
                                    @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="employee_id">{{ __('Employee') }}</label>
                                    <select class="form-select select2 @error('employee_id') is-invalid @enderror" 
                                        name="employee_id" id="employee_id" data-placeholder="{{ __('Select Employee') }}">
                                        <option value="">{{ __('Select Employee') }}</option>
                                        @foreach($employees ?? [] as $employee)
                                            <option value="{{ $employee->id }}" 
                                                {{ old('employee_id', isset($servicing) ? $servicing->employee_id : '') == $employee->id ? 'selected' : '' }}>
                                                {{ $employee->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('employee_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="payment_method_id">{{ __('Payment Method') }}</label>
                                    <select class="form-select select2 @error('payment_method_id') is-invalid @enderror" 
                                        name="payment_method_id" id="payment_method_id" data-placeholder="{{ __('Select Payment Method') }}">
                                        <option value="">{{ __('Select Payment Method') }}</option>
                                        @foreach($paymentMethods ?? [] as $paymentMethod)
                                            <option value="{{ $paymentMethod->id }}" 
                                                {{ old('payment_method_id', isset($servicing) ? $servicing->payment_method_id : '') == $paymentMethod->id ? 'selected' : '' }}>
                                                {{ $paymentMethod->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('payment_method_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-5">
                                    <label class="form-label" for="problem_description">{{ __('Problem Description') }}</label>
                                    <textarea class="form-control @error('problem_description') is-invalid @enderror" 
                                        placeholder="{{ __('Problem Description') }}" name="problem_description" id="problem_description" 
                                        rows="3">{{ old('problem_description', isset($servicing) ? $servicing->problem_description : '') }}</textarea>
                                    @error('problem_description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText(isset($servicing) ? $servicing : '') !!}
                            </button>
                            <a href="{{ route('servicing.index') }}" class="btn btn-primary waves-effect waves-light">
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
    $(document).ready(function() {
        // Calculate due amount when servicing charge or paid amount changes
        $('#servicing_charge, #paid_amount').on('input', function() {
            calculateDueAmount();
        });

        function calculateDueAmount() {
            var servicingCharge = parseFloat($('#servicing_charge').val()) || 0;
            var paidAmount = parseFloat($('#paid_amount').val()) || 0;
            var dueAmount = Math.max(0, servicingCharge - paidAmount);
            $('#due_amount').val(dueAmount.toFixed(2));
        }

        // Initialize calculation on page load
        calculateDueAmount();
    });
</script>
@endpush
