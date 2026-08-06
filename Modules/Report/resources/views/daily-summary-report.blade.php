@extends('backend.backend_layout')
@section('page-title', __('Daily Summary Report'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
<style>
    .section-header-row {
        background-color: #f0f0f0 !important;
        font-weight: bold !important;
    }
    .column-header-row {
        background-color: #e8e8e8 !important;
        font-weight: bold !important;
    }
</style>
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Daily Summary Report') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => route('report.index')],
                ['label' => __('Report'), 'link' => route('report.index')],
                ['label' => __('Daily Summary Report'), 'active' => true]
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
                        <label class="form-label">{{ __('Date') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control datePicker" id="date" name="date" value="{{ request('date') }}" placeholder="{{ __('Date') }}" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('Outlet') }} <span class="text-danger">*</span></label>
                        <select class="form-select select2" id="outlet_id" name="outlet_id" required>
                            <option value="">{{ __('Select Outlet') }}</option>
                            @foreach($outlets as $outlet)
                                <option value="{{ $outlet->id }}" {{ request('outlet_id') == $outlet->id ? 'selected' : '' }}>
                                    {{ $outlet->name }}
                                </option>
                            @endforeach
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
                <!-- show filtered information eg: outlet name, date, report header -->
                <div class="card-body filtered_information" id="filteredInformation">
                    <h5 class="card-title">{{ __('Daily Summary Report') }}</h5>
                    <div id="filterInfoContent"></div>
                </div>
                <div class="card-datatable table-responsive pt-0">
                    <table class="datatables-basic table report-table" id="dailySummaryReportTable">
                        <!-- <thead>
                            <tr>
                                <th>{{ __('SN') }}</th>
                                <th>{{ __('Reference No') }}</th>
                                <th>{{ __('Supplier/Customer/Category') }}</th>
                                <th>{{ __('Amount/Sub Total') }}</th>
                                <th>{{ __('Tax/Paid/Responsible Person') }}</th>
                                <th>{{ __('Charge/Due/Payment Method') }}</th>
                                <th>{{ __('Discount/Items') }}</th>
                                <th>{{ __('Total Payable') }}</th>
                                <th>{{ __('Paid') }}</th>
                                <th>{{ __('Due') }}</th>
                            </tr>
                        </thead> -->
                        <tbody>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="9" class="text-end">{{ __('Total Amount') }}:</th>
                                <th id="totalAmount">0.00</th>
                            </tr>
                            <!-- <tr>
                                <th colspan="9" class="text-end">{{ __('Total Transactions') }}:</th>
                                <th id="totalTransactions">0</th>
                            </tr> -->
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
<script src="{{ asset('backend_assets/js/report/daily_summary_report.js') }}"></script>
@endpush
