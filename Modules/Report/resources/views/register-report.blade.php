@extends('backend.backend_layout')
@section('page-title', __('Register Report'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Register Report') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => route('report.index')],
                ['label' => __('Report'), 'link' => route('report.index')],
                ['label' => __('Register Report'), 'active' => true]
            ]
        ])
    </div>

    @if(session('success'))
        {!! insertSuccess(session('success')) !!}
    @endif
    @if(session('error'))
        {!! insertFailed(session('error')) !!}
    @endif

    <!-- Filter Section (slide toggle like Expense Report) -->
    <div class="card mb-4" id="filterSection" style="display: none;">
        <div class="card-body">
            <form id="filterForm" onsubmit="event.preventDefault(); return false;" action="javascript:void(0);">
                <div class="row">
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
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('Date') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control datePicker" id="date" name="date" value="{{ request('date') }}" placeholder="{{ __('Date') }}" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('Register') }}</label>
                        <select class="form-select select2" id="register_id" name="register_id">
                            <option value="">{{ __('All Registers') }}</option>
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
                <div class="card-body filtered_information" id="filteredInformation" style="display: none;">
                    <h5 class="card-title">{{ __('Register Report') }}</h5>
                    <div id="filterInfoContent"></div>
                </div>
                <div class="card-datatable table-responsive pt-0">
                    <table class="datatables-basic table report-table" id="registerReportTable">
                        <thead>
                            <tr>
                                <th>{{ __('SN') }}</th>
                                <th>{{ __('Employee') }}</th>
                                <th>{{ __('Opening Date & Time') }}</th>
                                <th>{{ __('Opening Balance') }}</th>
                                <th>{{ __('Sale') }} ({{ __('Paid Amount') }})</th>
                                <th>{{ __('Sale Return') }}</th>
                                <th>{{ __('Customer Receive') }}</th>
                                <th>{{ __('Purchase') }}</th>
                                <th>{{ __('Purchase Return') }}</th>
                                <th>{{ __('Supplier Payment') }}</th>
                                <th>{{ __('Expense') }}</th>
                                <th>{{ __('Down Payment') }}</th>
                                <th>{{ __('Installment Collection') }}</th>
                                <th>{{ __('Servicing') }}</th>
                                <th>{{ __('Closing Balance') }}</th>
                                <th>{{ __('Closing Date & Time') }}</th>
                                <th>{{ __('Sale In Payment Method') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('page-js')
@routes
<input type="hidden" id="base_url" value="{{ url('/') }}">
<input type="hidden" id="company_data" value="{{ json_encode(session('company', [])) }}">
<script src="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
<script src="{{ asset('backend_assets/js/report/register_report.js') }}"></script>
@endpush
