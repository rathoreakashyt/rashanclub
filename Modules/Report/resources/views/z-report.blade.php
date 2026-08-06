@extends('backend.backend_layout')
@section('page-title', __('Z Report'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
<style>
/* .z-report-container { max-width: 900px; margin: 0 auto; }
.z-report-container table { width: 100%; margin-bottom: 1rem; } */
.z-report-container th, .z-report-container td { padding: 0.35rem 0.5rem; text-align: left; border-bottom: 1px solid #dee2e6; }
.z-report-container th { font-weight: 600; }
.z-report-container .text-end { text-align: right; }
.z-report-container .section-title { font-weight: 700; margin-top: 1.25rem; margin-bottom: 0.5rem; }
@media print { .no-print { display: none !important; } .z-report-container { max-width: 100%; } }
</style>
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Z Report') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => route('report.index')],
                ['label' => __('Report'), 'link' => route('report.index')],
                ['label' => __('Z Report'), 'active' => true]
            ]
        ])
    </div>

    @if(session('success'))
        {!! insertSuccess(session('success')) !!}
    @endif
    @if(session('error'))
        {!! insertFailed(session('error')) !!}
    @endif

    <!-- Filter Section (slide toggle like Daily Summary Report) -->
    <div class="card mb-4 no-print" id="filterSection" style="display: none;">
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

    <div id="zReportLoading" class="text-center py-5 no-print" style="display: none;">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-2">{{ __('Loading...') }}</p>
    </div>

    <div id="zReportCard" class="card no-print">
        <div class="card-header border-bottom p-3 d-flex flex-wrap justify-content-between align-items-center">
            <div class="head-label"></div>
            <div class="dt-action-buttons text-end">
                <div class="btn-group-2">
                    <button type="button" class="btn btn-primary dropdown-toggle me-2" data-bs-toggle="dropdown" id="zReportExportBtn">
                        <i class="ti tabler-file-export me-sm-1"></i> <span class="d-none d-sm-inline-block">{{ __('Export') }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="javascript:void(0);" data-export="print"><i class="ti tabler-printer me-1"></i> {{ __('Print') }}</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0);" data-export="excel"><i class="ti tabler-file-spreadsheet me-1"></i> {{ __('Excel') }}</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0);" data-export="pdf"><i class="ti tabler-file-description me-1"></i> {{ __('PDF') }}</a></li>
                    </ul>
                    <button type="button" class="btn btn-primary" id="zReportFilterBtn"><i class="ti tabler-filter me-sm-1"></i> <span class="d-none d-sm-inline-block">{{ __('Filter') }}</span></button>
                </div>
            </div>
        </div>
        <!-- Filtered information header (like Daily Summary Report) -->
        <div class="card-body filtered_information pt-3" id="filteredInformation" style="display: none;">
            <h5 class="card-title">{{ __('Z Report') }}</h5>
            <div id="filterInfoContent"></div>
        </div>
        <div id="zReportEmpty" class="card-body no-print">
            <p class="mb-0 text-muted" id="zReportEmptyMsg">{{ __('Click Filter, select Date and Outlet, then Apply Filter to view the Z Report.') }}</p>
        </div>
        <div class="card-datatable table-responsive pt-0" id="zReportContentWrapper" style="display: none;">
            <div class="card-body z-report-container" id="zReportContent">
                <div class="section-title">{{ __('Sales and Taxes Summary') }}</div>
                <table class="table table-sm"><tbody id="zSalesTaxesSummary"></tbody></table>
                <div class="section-title">{{ __('Payment Method Breakdown') }}</div>
                <table class="table table-sm"><tbody id="zPaymentMethodBreakdown"></tbody></table>
                <div class="section-title">{{ __('Payment in Other Currencies') }}</div>
                <table class="table table-sm"><tbody id="zPaymentOtherCurrencies"></tbody></table>
                <div class="section-title">{{ __('Item Wise Sales') }}</div>
                <table class="table table-sm"><thead><tr><th>{{ __('Quantity Total') }}</th><th class="text-end">{{ __('Amount Total') }}</th></tr></thead><tbody id="zItemWiseSales"></tbody></table>
                <div class="section-title">{{ __('Purchase Paid') }}</div>
                <table class="table table-sm"><tbody id="zPurchasePaid"></tbody></table>
                <div class="section-title">{{ __('Expense') }}</div>
                <table class="table table-sm"><tbody id="zExpense"></tbody></table>
                <div class="section-title">{{ __('Supplier Payment') }}</div>
                <table class="table table-sm"><tbody id="zSupplierPayment"></tbody></table>
                <div class="section-title">{{ __('Customer Due Receives') }}</div>
                <table class="table table-sm"><tbody id="zCustomerDueReceives"></tbody></table>
                <div class="section-title">{{ __('Total In Hand by Payment Method') }}</div>
                <table class="table table-sm"><tbody id="zTotalInHand"></tbody></table>
                <div class="section-title">{{ __('In Hand Summary') }}</div>
                <table class="table table-sm"><tbody id="zInHandSummary"></tbody></table>
            </div>
        </div>
    </div>
</div>
@endsection
@push('page-js')
@routes
<input type="hidden" id="base_url" value="{{ url('/') }}">
<input type="hidden" id="company_data" value="{{ json_encode(session('company', [])) }}">
<input type="hidden" id="company_name" value="{{ session('company.company_name', config('app.name')) }}">
<script src="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
<script src="{{ asset('backend_assets/js/report/z_report.js') }}"></script>
@endpush
