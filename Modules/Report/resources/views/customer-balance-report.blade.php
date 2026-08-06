@extends('backend.backend_layout')
@section('page-title', __('Customer Balance Report'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Customer Balance Report') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => route('report.index')],
                ['label' => __('Report'), 'link' => route('report.index')],
                ['label' => __('Customer Balance Report'), 'active' => true]
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
            <form id="filterForm" onsubmit="event.preventDefault(); return false;" action="javascript:void(0);">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('Type') }}</label>
                        <select class="form-select select2" id="type" name="type">
                            <option value="All" {{ request('type', 'All') == 'All' ? 'selected' : '' }}>{{ __('All') }}</option>
                            <option value="Debit" {{ request('type') == 'Debit' ? 'selected' : '' }}>{{ __('Debit') }}</option>
                            <option value="Credit" {{ request('type') == 'Credit' ? 'selected' : '' }}>{{ __('Credit') }}</option>
                        </select>
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
                <!-- show filtered information eg: type, report header -->
                <div class="card-body filtered_information" id="filteredInformation">
                    <h5 class="card-title">{{ __('Customer Balance Report') }}</h5>
                    <div id="filterInfoContent"></div>
                </div>
                <div class="card-datatable table-responsive pt-0">
                    <table class="datatables-basic table report-table" id="customerBalanceReportTable">
                        <thead>
                            <tr>
                                <th>{{ __('SN') }}</th>
                                <th>{{ __('Customer Name') }}</th>
                                <th>{{ __('Current Balance') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2" class="text-end">{{ __('Total Balance') }}:</th>
                                <th id="totalBalance">0.00</th>
                            </tr>
                            <tr>
                                <th colspan="2" class="text-end">{{ __('Total Customers') }}:</th>
                                <th id="totalCustomers">0</th>
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
<script src="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{ asset('backend_assets/js/report/customer_balance.js') }}"></script>
@endpush
