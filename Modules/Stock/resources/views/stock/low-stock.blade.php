@extends('backend.backend_layout')
@section('page-title', __('Low') . ' ' . __('Stock'))
@push('page-css')
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Low Stock Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Low') }} {{ __('Stock') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Item') . ' & ' . __('Stock'),
                    'link' => '#'
                ],
                [
                    'label' => __('Low') . ' ' . __('Stock'),
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
            <form id="filterForm" onsubmit="event.preventDefault(); return false;" action="javascript:void(0);">
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Code') }}</label>
                        <select class="form-select select2" name="item_code" id="item_code_f">
                            <option value="">{{ __('All Codes') }}</option>
                            @foreach ($items as $value)
                                <option value="{{ $value->code }}" {{ request('item_code') == $value->code ? 'selected' : '' }}>
                                    {{ $value->code }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
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
                    <div class="col-md-3">
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
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Item') }}</label>
                        <select class="form-select select2" name="item_id" id="item_id_f">
                            <option value="">{{ __('All Items') }}</option>
                            @foreach ($items as $value)
                                <option value="{{ $value->id }}" {{ request('item_id') == $value->id ? 'selected' : '' }}>
                                    @if($value->parent_id)
                                        {{ \Modules\Stock\Models\Item::find($value->parent_id)->name ?? '' }}
                                    @endif
                                    {{ $value->name }}({{ $value->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mt-3">
                        <label class="form-label">{{ __('Generic') }} {{ __('Name') }}</label>
                        <input id="generic_name_f" type="text" class="form-control" name="generic_name" 
                               placeholder="{{ __('Generic') }} {{ __('Name') }}" 
                               value="{{ request('generic_name') }}">
                    </div>
                    <div class="col-md-3 mt-3">
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

    <!-- Low Stock Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-datatable table-responsive pt-0">
                    <table class="datatables-basic table">
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
                        <tbody>
                        </tbody>
                    </table>
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
<script src="{{ asset('backend_assets/js/stock/low-stock.js') }}"></script>
@endpush
