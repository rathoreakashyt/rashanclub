@extends('backend.backend_layout')
@section('page-title', __('GST Reports'))
@push('page-css')
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('GST Reports') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => route('report.index')],
                ['label' => __('Report'), 'link' => route('report.index')],
                ['label' => __('GST Reports'), 'active' => true]
            ]
        ])
    </div>

    @if(session('success'))
        {!! insertSuccess(session('success')) !!}
    @endif
    @if(session('error'))
        {!! insertFailed(session('error')) !!}
    @endif

    <div class="card mb-4" id="filterSection">
        <div class="card-body">
            <form id="filterForm" onsubmit="event.preventDefault(); return false;">
                <div class="row">
                    <div class="col-md-2 mb-3">
                        <label class="form-label">{{ __('Report Type') }}</label>
                        <select class="form-select select2" id="report_type" name="report_type">
                            <option value="tax-summary">{{ __('1. GST Tax Summary') }}</option>
                            <option value="monthly-summary">{{ __('2. GST Monthly/Date Wise') }}</option>
                            <option value="rate-wise">{{ __('3. GST Rate Wise Summary') }}</option>
                            <option value="b2b">{{ __('4. B2B Report (GSTR-1)') }}</option>
                            <option value="b2c-small">{{ __('5. B2C Small (State Wise)') }}</option>
                            <option value="b2c-large">{{ __('6. B2C Large (Interstate >2.5L)') }}</option>
                            <option value="hsn-summary">{{ __('7. HSN Summary') }}</option>
                            <option value="gstr3b">{{ __('8. GSTR-3B Summary') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">{{ __('Date From') }}</label>
                        <input type="text" class="form-control datePicker" id="date_from" name="date_from" placeholder="{{ __('Date From') }}">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">{{ __('Date To') }}</label>
                        <input type="text" class="form-control datePicker" id="date_to" name="date_to" placeholder="{{ __('Date To') }}">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">{{ __('Outlet') }}</label>
                        <select class="form-select select2" id="outlet_id" name="outlet_id">
                            <option value="">{{ __('All Outlets') }}</option>
                            @foreach($outlets as $outlet)
                                <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-3 d-flex align-items-end">
                        <button type="button" class="btn btn-primary" id="applyFilter">
                            <i class="ti tabler-filter me-1"></i>{{ __('Apply Filter') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body filtered_information" id="filteredInformation">
            <h5 class="card-title" id="reportTitle">{{ __('GST Tax Summary Report') }}</h5>
            <div id="filterInfoContent"></div>
        </div>
        <div class="card-datatable table-responsive pt-0">
            <div id="gstReportContent"></div>
        </div>
    </div>
</div>
@endsection
@push('page-js')
@routes
<script src="{{ asset('backend_assets/js/report/report-base.js') }}"></script>
<script src="{{ asset('backend_assets/js/report/gst_report.js') }}"></script>
@endpush
