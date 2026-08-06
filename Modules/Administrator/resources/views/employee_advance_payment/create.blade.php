@extends('backend.backend_layout')
@section('page-title', isset($advancePayment) ? __('Edit') . ' ' . __('Employee Advance Payment') : __('Add') . ' ' . __('Employee Advance Payment'))
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($advancePayment) ? __('Update') . ' ' . __('Employee Advance Payment') : __('Add') . ' ' . __('Employee Advance Payment') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => '#'],
                ['label' => __('Employee Advance Payment'), 'link' => route('employee-advance-payment.index')],
                ['label' => isset($advancePayment) ? __('Update') . ' ' . __('Employee Advance Payment') : __('Add') . ' ' . __('Employee Advance Payment'), 'active' => true]
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
            <form id="advancePaymentForm" action="{{ isset($advancePayment) ? route('employee-advance-payment.update', $advancePayment->encrypted_id) : route('employee-advance-payment.store') }}" method="POST">
                @csrf
                @if(isset($advancePayment))
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
                                        value="{{ old('reference_no', isset($advancePayment) ? $advancePayment->reference_no : ($reference_no ?? '')) }}"
                                        @if(isset($advancePayment)) readonly @endif />
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
                                        value="{{ old('date', isset($advancePayment) ? $advancePayment->date?->format('Y-m-d') : '') }}" />
                                    @error('date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="amount">{{ __('Amount') }} {!! requiredField() !!}</label>
                                    <input type="text" step="0.01" min="0.01" class="form-control number-input @error('amount') is-invalid @enderror"
                                        placeholder="{{ __('Amount') }}" name="amount" id="amount"
                                        value="{{ old('amount', isset($advancePayment) ? $advancePayment->amount : '') }}" />
                                    @error('amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="employee_id">{{ __('Employee') }} {!! requiredField() !!}</label>
                                    <select id="employee_id" name="employee_id" class="select2 form-select @error('employee_id') is-invalid @enderror" data-placeholder="{{ __('Select') }} {{ __('Employee') }}">
                                        <option value="">{{ __('Select') }} {{ __('Employee') }}</option>
                                        @foreach($employees as $id => $name)
                                            <option value="{{ $id }}" {{ old('employee_id', isset($advancePayment) ? $advancePayment->employee_id : '') == $id ? 'selected' : '' }}>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    @error('employee_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="payment_method_id">{{ __('Payment Method') }} {!! requiredField() !!}</label>
                                    <select id="payment_method_id" name="payment_method_id" class="select2 form-select @error('payment_method_id') is-invalid @enderror" data-placeholder="{{ __('Select') }} {{ __('Payment Method') }}">
                                        <option value="">{{ __('Select') }} {{ __('Payment Method') }}</option>
                                        @foreach($paymentMethods as $pm)
                                            <option value="{{ $pm->id }}" {{ old('payment_method_id', isset($advancePayment) ? $advancePayment->payment_method_id : '') == $pm->id ? 'selected' : '' }}>{{ $pm->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('payment_method_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-5">
                                    <label class="form-label" for="note">{{ __('Note') }}</label>
                                    <textarea class="form-control @error('note') is-invalid @enderror"
                                        placeholder="{{ __('Note') }}" name="note" id="note" rows="3">{{ old('note', isset($advancePayment) ? $advancePayment->note : '') }}</textarea>
                                    @error('note')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" value="submit" name="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText(isset($advancePayment) ? $advancePayment : '') !!}
                            </button>
                            <a href="{{ route('employee-advance-payment.index') }}" type="button" class="btn btn-primary waves-effect waves-light">
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
    $(document).ready(function () {
        $('.datePicker').flatpickr({
            dateFormat: "Y-m-d"
        });
    });
</script>
@endpush
