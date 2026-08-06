@extends('backend.backend_layout')
@section('page-title', __('Employee Advance Payment') . ' ' . __('Details'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Employee Advance Payment') }} {{ __('Details') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => '#'],
                ['label' => __('Employee Advance Payment'), 'link' => route('employee-advance-payment.index')],
                ['label' => __('Details'), 'active' => true]
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
            <div class="card">
                <div class="card-header border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">{{ $advancePayment->reference_no }}</h5>
                        <div>
                            @can('employee_advance_payment-edit')
                            <a href="{{ route('employee-advance-payment.edit', $advancePayment->encrypted_id) }}" class="btn btn-warning">
                                <i class="ti tabler-edit me-1"></i>{{ __('Edit') }}
                            </a>
                            @endcan
                            <a href="{{ route('employee-advance-payment.index') }}" class="btn btn-primary">
                                <i class="ti tabler-arrow-back-up me-1"></i>{{ __('Back') }}
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row my-4">
                        <div class="col-md-6 pb-3">
                            <h5 class="mb-2"><b>{{ __('Advance Payment') }} {{ __('Info') }}</b></h5>
                            <p class="mb-0">{{ __('Reference No') }}: {{ $advancePayment->reference_no }}</p>
                            <p class="mb-0">{{ __('Date') }}: {{ formatDate($advancePayment->date) }}</p>
                            <p class="mb-0">{{ __('Amount') }}: {{ formatAmount($advancePayment->amount, 2) }}</p>
                            <p class="mb-0">{{ __('Employee') }}: {{ $advancePayment->employee ? $advancePayment->employee->name : 'N/A' }}</p>
                            <p class="mb-0">{{ __('Payment Method') }}: {{ $advancePayment->paymentMethod ? $advancePayment->paymentMethod->name : 'N/A' }}</p>
                            @if($advancePayment->branch_id)
                                <p class="mb-0">{{ __('Branch') }}: {{ $advancePayment->branch_id }}</p>
                            @endif
                            @if($advancePayment->note)
                                <p class="mb-0 mt-2">{{ __('Note') }}: {{ $advancePayment->note }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
