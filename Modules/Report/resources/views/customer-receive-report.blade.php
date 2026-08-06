@extends('backend.backend_layout')
@section('page-title', __('Customer Receive Report'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Customer Receive Report') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => route('report.index')],
                ['label' => __('Report'), 'link' => route('report.index')],
                ['label' => __('Customer Receive Report'), 'active' => true]
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
                        <label class="form-label">{{ __('Date To') }} </label>
                        <input type="text" class="form-control datePicker" id="date_to" name="date_to" value="{{ request('date_to') }}" placeholder="{{ __('Date To') }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('Customer') }} {!! requiredField() !!}</label>
                        <select class="form-select select2" id="customer_id" name="customer_id" required>
                            <option value="">{{ __('Select Customer') }}</option>
                            @if(isset($customers))
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}" {{ request('customer_id') == $customer->id ? 'selected' : '' }}>
                                        {{ $customer->name }}@if($customer->phone) ({{ $customer->phone }})@endif
                                    </option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('Outlet') }}</label>
                        <select class="form-select select2" id="outlet_id" name="outlet_id">
                            <option value="">{{ __('All Outlets') }}</option>
                            @if(isset($outlets))
                                @foreach($outlets as $outlet)
                                    <option value="{{ $outlet->id }}" {{ request('outlet_id') == $outlet->id ? 'selected' : '' }}>
                                        {{ $outlet->name }}
                                    </option>
                                @endforeach
                            @endif
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
                    <h5 class="card-title">{{ __('Customer Receive Report') }}</h5>
                    <div id="filterInfoContent"></div>
                </div>
                <div class="card-datatable table-responsive pt-0">
                    <table class="datatables-basic table report-table" id="customerReceiveReportTable">
                        <thead>
                            <tr>
                                <th>{{ __('SN') }}</th>
                                <th>{{ __('Reference No') }}</th>
                                <th>{{ __('Date Time') }}</th>
                                <th>{{ __('Customer') }}</th>
                                <th>{{ __('Amount') }}</th>
                                <th>{{ __('Note') }}</th>
                                <th>{{ __('Receive By') }}</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end">{{ __('Total') }}:</th>
                                <th id="totalAmount">0.00</th>
                                <th colspan="2"></th>
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
<script src="{{ asset('backend_assets/js/report/customer_due_receive_report.js') }}"></script>
@endpush
