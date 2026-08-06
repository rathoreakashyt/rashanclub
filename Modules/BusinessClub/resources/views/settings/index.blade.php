@extends('backend.backend_layout')
@section('page-title', __('Business Club') . ' ' . __('Settings'))
@push('page-css')
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Business Club') }} {{ __('Settings') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => '#'],
                ['label' => __('Business Club'), 'link' => route('businessclub.dashboard')],
                ['label' => __('Settings'), 'active' => true]
            ]
        ])
    </div>

    @if(session('success'))
        {!! insertSuccess(session('success')) !!}
    @endif

    <div class="card">
        <div class="card-header">
            <h5>{{ __('Profit') }} & {{ __('Redemption') }} {{ __('Settings') }}</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('businessclub.settings.update') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">{{ __('Profit_Percentage') }} (%)</label>
                        <input type="number" name="profit_percentage" class="form-control" step="0.01" min="0" max="100" value="{{ old('profit_percentage', $settings->profit_percentage ?? 50) }}">
                        <small class="text-muted">{{ __('Percentage of profit credited to customer wallet after billing') }}</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">{{ __('Redemption_Date') }} ({{ __('Day of month') }})</label>
                        <input type="number" name="redemption_date" class="form-control" min="1" max="31" value="{{ old('redemption_date', $settings->redemption_date ?? 1) }}">
                        <small class="text-muted">{{ __('Customers can redeem wallet on this date each month') }}</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">{{ __('Min_Purchase_Amount') }} (₹)</label>
                        <input type="number" name="min_purchase_amount" class="form-control" step="0.01" min="0" value="{{ old('min_purchase_amount', $settings->min_purchase_amount ?? 10000) }}">
                        <small class="text-muted">{{ __('Minimum monthly purchase to qualify for wallet credit') }}</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">{{ __('Minimum_Bill') }} (₹)</label>
                        <input type="number" name="minimum_bill_amount" class="form-control" step="0.01" min="0" value="{{ old('minimum_bill_amount', $settings->minimum_bill_amount ?? 0) }}">
                        <small class="text-muted">{{ __('Minimum bill amount required for customer to be eligible for Business Club wallet credit') }}</small>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">{!! submitIconWithText(true) !!}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
