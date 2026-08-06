@extends('backend.backend_layout')
@section('page-title', isset($deposit_withdraw) ? __('Update') . ' ' . __('Deposit') . '/' . __('Withdraw') : __('Add') . ' ' . __('Deposit') . '/' . __('Withdraw'))
@push('page-css')
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($deposit_withdraw) ? __('Update') . ' ' . __('Deposit') . '/' . __('Withdraw') : __('Add') . ' ' . __('Deposit') . '/' . __('Withdraw') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Accounting'),
                    'link' => '#'
                ],
                [
                    'label' => isset($deposit_withdraw) ? __('Update') . ' ' . __('Deposit') . '/' . __('Withdraw') : __('Add') . ' ' . __('Deposit') . '/' . __('Withdraw'),
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
            <form action="{{ isset($deposit_withdraw) ? route('deposit-withdraw.update', encrypt($deposit_withdraw->id)) : route('deposit-withdraw.store') }}" method="POST">
                @csrf
                @if(isset($deposit_withdraw))
                    @method('PUT')
                @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="reference_no">{{ __('Reference') }} {{ __('No') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('reference_no') is-invalid @enderror" 
                                        placeholder="{{ __('Reference') }} {{ __('No') }}" name="reference_no" id="reference_no" 
                                        value="{{ old('reference_no', isset($deposit_withdraw) ? $deposit_withdraw->reference_no : $reference_no) }}" />
                                    @error('reference_no')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="date">{{ __('Date') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control datePicker @error('date') is-invalid @enderror" 
                                        placeholder="{{ date('Y-m-d') }}" name="date" id="date" 
                                        value="{{ old('date', isset($deposit_withdraw) ? $deposit_withdraw->date : '') }}" readonly="readonly" />
                                    @error('date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="type">{{ __('Type') }} {!! requiredField() !!}</label>
                                    <select class="form-select select2 @error('type') is-invalid @enderror" 
                                        name="type" id="type" data-placeholder="{{ __('Select') }} {{ __('Type') }}">
                                        <option value="">{{ __('Select') }} {{ __('Type') }}</option>
                                        <option value="Deposit" {{ old('type', isset($deposit_withdraw) && $deposit_withdraw->type == 'Deposit' ? 'selected' : '') }}>
                                            {{ __('Deposit') }}
                                        </option>
                                        <option value="Withdraw" {{ old('type', isset($deposit_withdraw) && $deposit_withdraw->type == 'Withdraw' ? 'selected' : '') }}>
                                            {{ __('Withdraw') }}
                                        </option>
                                    </select>
                                    @error('type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="amount">{{ __('Amount') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('amount') is-invalid @enderror" 
                                        placeholder="{{ __('Amount') }}" name="amount" id="amount" 
                                        value="{{ old('amount', isset($deposit_withdraw) ? $deposit_withdraw->amount : '') }}" />
                                    @error('amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="payment_method_id">{{ __('Payment') }} {{ __('Method') }} {!! requiredField() !!}</label>
                                    <select class="form-select select2 @error('payment_method_id') is-invalid @enderror" 
                                        name="payment_method_id" id="payment_method_id" data-placeholder="{{ __('Select') }} {{ __('Payment') }} {{ __('Method') }}">
                                        <option value="">{{ __('Select') }} {{ __('Payment') }} {{ __('Method') }}</option>
                                        @foreach($payment_methods as $method)
                                            <option value="{{ $method->id }}" {{ old('payment_method_id', isset($deposit_withdraw) && $deposit_withdraw->payment_method_id == $method->id ? 'selected' : '') }}>
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
                                    <label class="form-label" for="note">{{ __('Note') }} {!! requiredField() !!}</label>
                                    <textarea type="text" class="form-control @error('note') is-invalid @enderror" 
                                        placeholder="{{ __('Note') }}" name="note" id="note" 
                                        >{{ old('note', isset($deposit_withdraw) ? $deposit_withdraw->note : '') }}</textarea>
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
                                {!! submitIconWithText(isset($deposit_withdraw) ? $deposit_withdraw : '') !!}
                            </button>
                            <a href="{{ route('deposit-withdraw.index') }}" class="btn btn-primary waves-effect waves-light">
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
@endpush

