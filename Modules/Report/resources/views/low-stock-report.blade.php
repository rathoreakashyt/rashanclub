@extends('backend.backend_layout')
@section('page-title', __('Low Stock Report'))
@push('page-css')
<style>
.report-virtual-container { height: 60vh; min-height: 400px; overflow: auto; position: relative; }
.report-virtual-spacer { position: absolute; left: 0; top: 0; width: 1px; pointer-events: none; }
.report-table-header { table-layout: fixed; width: 100%; }
.report-virtual-table { position: absolute; left: 0; top: 0; width: 100%; margin: 0; table-layout: fixed; }
.report-virtual-table tbody tr { height: 42px; }
.report-virtual-table td { overflow: hidden; text-overflow: ellipsis; }
.report-virtual-table colgroup col:nth-child(1) { width: 5%; }
.report-virtual-table colgroup col:nth-child(2) { width: 22%; }
.report-virtual-table colgroup col:nth-child(3) { width: 12%; }
.report-virtual-table colgroup col:nth-child(4) { width: 18%; }
.report-virtual-table colgroup col:nth-child(5) { width: 18%; }
.report-virtual-table colgroup col:nth-child(6) { width: 12%; }
.report-virtual-table colgroup col:nth-child(7) { width: 13%; }
body {
    overflow-x: hidden;
}
</style>
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Low Stock Report') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => route('report.index')],
                ['label' => __('Report'), 'link' => route('report.index')],
                ['label' => __('Low Stock Report'), 'active' => true]
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
                        <label class="form-label">{{ __('Category') }}</label>
                        <select class="form-select select2" name="category_id" id="category_id_f">
                            <option value="">{{ __('All Categories') }}</option>
                            @foreach ($item_categories as $value)
                                <option value="{{ $value->id }}" {{ request('category_id') == $value->id ? 'selected' : '' }}>
                                    {{ $value->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('Brand') }}</label>
                        <select class="form-select select2" name="brand_id" id="brand_id_f">
                            <option value="">{{ __('All Brands') }}</option>
                            @foreach ($brands as $value)
                                <option value="{{ $value->id }}" {{ request('brand_id') == $value->id ? 'selected' : '' }}>
                                    {{ $value->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3 items_select2">
                        <label class="form-label">{{ __('Item') }}</label>
                        <select class="form-select select2 select2-item-ajax" name="item_id" id="item_id_f" data-placeholder="{{ __('Search by item name or code...') }}">
                            <option value="">{{ __('All Items') }}</option>
                            @if(isset($selected_item) && $selected_item)
                                <option value="{{ $selected_item->id }}" selected>{{ $selected_item->name }} ({{ $selected_item->code }})</option>
                            @endif
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('Generic') }} {{ __('Name') }}</label>
                        <input id="generic_name_f" type="text" class="form-control" name="generic_name" 
                               placeholder="{{ __('Generic') }} {{ __('Name') }}" 
                               value="{{ request('generic_name') }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('Supplier') }}</label>
                        <select class="form-select select2" id="supplier_id_f" name="supplier_id">
                            <option value="">{{ __('All Suppliers') }}</option>
                            @foreach ($suppliers as $splr)
                                <option value="{{ $splr->id }}" {{ request('supplier_id') == $splr->id ? 'selected' : '' }}>
                                    {{ $splr->name }}
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
                <!-- show filtered information -->
                <div class="card-body filtered_information" id="filteredInformation">
                    <h5 class="card-title">{{ __('Low Stock Report') }}</h5>
                    <div id="filterInfoContent"></div>
                </div>
                <div class="card-datatable table-responsive pt-0">
                    <div class="card-header border-bottom p-3 d-flex flex-wrap justify-content-between align-items-center">
                        <div class="head-label"></div>
                        <div class="dt-action-buttons text-end">
                            <div class="btn-group-stock">
                                <button type="button" class="btn btn-primary dropdown-toggle me-2" data-bs-toggle="dropdown" id="lowStockReportExportBtn">
                                    <i class="ti tabler-file-export me-sm-1"></i> <span class="d-none d-sm-inline-block">{{ __('Export') }}</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="javascript:void(0);" data-export="print"><i class="ti tabler-printer me-1"></i> {{ __('Print') }}</a></li>
                                    <li><a class="dropdown-item" href="javascript:void(0);" data-export="excel"><i class="ti tabler-file-spreadsheet me-1"></i> {{ __('Excel') }}</a></li>
                                    <li><a class="dropdown-item" href="javascript:void(0);" data-export="pdf"><i class="ti tabler-file-description me-1"></i> {{ __('PDF') }}</a></li>
                                </ul>
                                <button type="button" class="btn btn-primary" id="lowStockReportFilterBtn"><i class="ti tabler-filter me-sm-1"></i> <span class="d-none d-sm-inline-block">{{ __('Filter') }}</span></button>
                            </div>
                        </div>
                    </div>
                    <div id="lowStockReportLoading" class="text-center py-5 d-none">{{ __('Loading...') }}</div>
                    <div id="lowStockReportVirtualWrap" class="d-none">
                        <table class="datatables-basic table report-table report-table-header" id="alertStockReportTable">
                            <colgroup>
                                <col style="width: 5%">
                                <col style="width: 22%">
                                <col style="width: 12%">
                                <col style="width: 18%">
                                <col style="width: 18%">
                                <col style="width: 12%">
                                <col style="width: 13%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>{{ __('SN') }}</th>
                                    <th>{{ __('Item') }}({{ __('Code') }})</th>
                                    <th>{{ __('Category') }}</th>
                                    <th>{{ __('Stock') }} {{ __('Details') }}</th>
                                    <th>{{ __('Total') }} {{ __('Stock') }} {{ __('Quantity') }}</th>
                                    <th>
                                        {{ __('LPP') }}/{{ __('PP') }}
                                        <i data-tippy-content="{{ __('LPP_PP') }}" class="ti tabler-info-circle tippyBtnCall font-16 theme-color"></i>
                                    </th>
                                    <th class="text-right" style="text-align: right;">{{ __('Total') }}</th>
                                </tr>
                            </thead>
                        </table>
                        <div id="alertStockReportTableVirtual" class="report-virtual-container"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stock Segmentation Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title stock_segment_title">{{ __('Stock') }} {{ __('Details') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body stock_modal_print_btn">
                <div class="box-wrapper">
                    <div class="table-box">
                        <div class="table-responsive">
                            <!-- IMEI/Serial Table -->
                            <table id="datatable2" class="table table-bordered table-striped datatable2" style="display: none;">
                                <thead>
                                    <tr class="imeiHeading">
                                        <th class="w-5 text-left">{{ __('SN') }}</th>
                                        <th class="w-30">{{ __('IMEI') }}/{{ __('Serial') }} {{ __('Number') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                            
                            <!-- Expiry Date Table -->
                            <table id="datatable3" class="table table-bordered table-striped datatable3" style="display: none;">
                                <thead>
                                    <tr>
                                        <th class="w-5 text-left">{{ __('SN') }}</th>
                                        <th class="w-30">{{ __('Expiry') }} {{ __('Date') }}</th>
                                        <th class="w-30">{{ __('Quantity') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                            
                            <!-- Variation Table -->
                            <table id="datatable4" class="table table-bordered table-striped datatable4" style="display: none;">
                                <thead>
                                    <tr>
                                        <th class="w-5 text-left">{{ __('SN') }}</th>
                                        <th class="w-30">{{ __('Items') }}</th>
                                        <th class="w-30">{{ __('Quantity') }}</th>
                                        <th class="w-30">
                                            {{ __('LPP') }}/{{ __('PP') }}
                                            <i data-tippy-content="{{ __('LPP_PP') }}" class="ti tabler-info-circle tippyBtnCall font-16 theme-color"></i>
                                        </th>
                                        <th class="w-30">{{ __('Total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn bg-blue-btn" data-bs-dismiss="modal">{{ __('Close') }}</button>
            </div>
        </div>
    </div>
</div>

@endsection
@push('page-js')
@routes
<script src="{{ asset('backend_assets/js/report/low_stock_report.js') }}"></script>
@endpush
