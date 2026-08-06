@extends('backend.backend_layout')
@section('page-title', __('Servicing Report'))
@push('page-css')
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Servicing Report') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => route('report.index')],
                ['label' => __('Report'), 'link' => route('report.index')],
                ['label' => __('Servicing Report'), 'active' => true]
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
                        <label class="form-label">{{ __('Date From') }}</label>
                        <input type="text" class="form-control datePicker" id="date_from" name="date_from" value="{{ request('date_from') }}" placeholder="{{ __('Date From') }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('Date To') }}</label>
                        <input type="text" class="form-control datePicker" id="date_to" name="date_to" value="{{ request('date_to') }}" placeholder="{{ __('Date To') }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('Outlet') }}</label>
                        <select class="form-select select2" id="outlet_id" name="outlet_id">
                            <option value="">{{ __('All Outlets') }}</option>
                            @foreach($outlets as $outlet)
                                <option value="{{ $outlet->id }}" {{ request('outlet_id') == $outlet->id ? 'selected' : '' }}>
                                    {{ $outlet->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('Customer') }}</label>
                        <select class="form-select select2" id="customer_id" name="customer_id">
                            <option value="">{{ __('All Customers') }}</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" {{ request('customer_id') == $customer->id ? 'selected' : '' }}>
                                    {{ $customer->name }}
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
                <!-- show filtered information eg: outlet name, customer info, date range, report header -->
                <div class="card-body filtered_information" id="filteredInformation">
                    <h5 class="card-title">{{ __('Servicing Report') }}</h5>
                    <div id="filterInfoContent"></div>
                </div>
                <div class="card-datatable table-responsive pt-0">
                    <table class="datatables-basic table report-table" id="servicingReportTable">
                        <thead>
                            <tr>
                                <th>{{ __('SN') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Delivery Date') }}</th>
                                <th>{{ __('Customer Name & Mobile') }}</th>
                                <th>{{ __('Details') }}</th>
                                <th>{{ __('Servicing Charge') }}</th>
                                <th>{{ __('Paid Amount') }}</th>
                                <th>{{ __('Due Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="5" class="text-end">{{ __('Total') }}:</th>
                                <th id="totalServicingCharge">0.00</th>
                                <th id="totalPaidAmount">0.00</th>
                                <th id="totalDueAmount">0.00</th>
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
<script src="{{ asset('backend_assets/js/report/servicing_report.js') }}"></script>
@endpush
