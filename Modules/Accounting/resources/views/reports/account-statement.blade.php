@extends('backend.backend_layout')
@section('page-title', __('Account Statement Report'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}" />
<style>
    body {
        overflow-x: hidden;
    }
</style>
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Account Statement Report') }}</h4>
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
                    'label' => __('Account Statement Report'),
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

    <!-- Filter Section -->
    <div class="card mb-4" id="filterSection" style="display: none;">
        <div class="card-body">
            <form id="filterForm">
                <div class="row">
                    <div class="col-md-3">
                        <div class="validate_wrapper payment_method_id">
                            <label class="form-label">{{ __('Payment Method') }} {!! requiredField() !!}</label>
                            <select class="form-select select2" id="payment_method_id" name="payment_method_id" required>
                                <option value="">{{ __('Select Payment Method') }}</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Outlet') }}</label>
                        <select class="form-select select2" id="outlet_id" name="outlet_id">
                            <option value="">{{ __('All Outlets') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Start Date') }}</label>
                        <input type="date" class="form-control datePicker" placeholder="{{ __('Select Start Date') }}" id="date_from" name="date_from">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('End Date') }}</label>
                        <input type="date" class="form-control datePicker" placeholder="{{ __('Select End Date') }}" id="date_to" name="date_to">
                    </div>
                    <div class="col-md-12 mt-3">
                        <button type="button" class="btn btn-primary me-2" id="applyFilter">
                            <i class="ti tabler-filter me-1"></i>{{ __('Apply Filter') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-datatable table-responsive pt-0">
                    <table class="datatables-basic table report-table" id="accountStatementTable">
                        <thead>
                            <tr>
                                <th>{{ __('SN') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Title') }}</th>
                                <th>{{ __('Debit') }}</th>
                                <th>{{ __('Credit') }}</th>
                                <th>{{ __('Balance') }}</th>
                                <th>{{ __('Created At') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">{{ __('Total') }}:</th>
                                <th id="totalDebit">0.00</th>
                                <th id="totalCredit">0.00</th>
                                <th id="closingBalance">0.00</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
@push('page-js')
@routes
<script src="{{ asset('backend_assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
<script src="{{ asset('backend_assets/js/pages_list_js/account_statement.js')}}"></script>
@endpush

