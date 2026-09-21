@extends('backend.backend_layout')
@section('page-title', 'List Item')
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
<style>
    .barcode-preview {
        border: 1px solid #ddd;
        padding: 20px;
        text-align: center;
        background: white;
        margin: 10px 0;
    }
    .barcode-controls {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    .barcode-field-control {
        margin-bottom: 10px;
    }
</style>
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Add Product -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('List') }} {{ __('Item') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' =>  __('Item') . ' ' . __('Stock'), 
                    'link' => '#'
                ],
                [
                    'label' => __('List') . ' ' .  __('Item'),
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

    @if($status == 'success')
        {!! insertSuccess('Item created successfully') !!}
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-datatable table-responsive pt-0">
                    <table class="datatables-basic table">
                        <thead>
                            <tr>
                                <th>{{ __('SN') }}</th>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Group') }}</th>
                                <th>{{ __('Purchase') }} {{ __('Price') }}</th>
                                <th>{{ __('Sale') }} {{ __('Price') }}</th>
                                <th>{{ __('MRP') }} {{ __('Price') }}</th>
                                <th>{{ __('Action') }}</th>
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

<!-- Barcode Print Modal -->
<div class="modal fade" id="barcodePrintModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header pb-3">
                <h5 class="modal-title">{{ __('Barcode Print Options') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="mb-3">{{ __('Item Information') }}</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <strong>{{ __('Name') }}:</strong> <span id="barcodeModalItemName">N/A</span>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>{{ __('Code') }}:</strong> <span id="barcodeModalItemCode">N/A</span>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>{{ __('Price') }}:</strong> <span id="barcodeModalItemPrice">0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="variationSelectContainer" class="row mb-3" style="display: none;">
                    <div class="col-12">
                        <label class="form-label">{{ __('Select Variation Item') }}</label>
                        <select class="form-select select2" id="modalVariationSelect">
                            <option value="">{{ __('Use Parent Item') }}</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label">{{ __('Quantity') }} ({{ __('Number of Barcodes') }})</label>
                        <input type="text" class="form-control number-input" id="modalBarcodeQuantity" value="1" min="1">
                        <small class="text-muted">{{ __('Enter the number of barcodes to generate') }}</small>
                    </div>
                </div>

                <div class="barcode-controls">
                    <h6 class="mb-3">{{ __('Barcode Options') }}</h6>
                    
                    <!-- Name Control -->
                    <div class="barcode-field-control">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="modalShowItemName" checked>
                                    <label class="form-check-label" for="modalShowItemName">{{ __('Show Name') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Font Size') }}</label>
                                <input type="number" class="form-control form-control-sm" id="modalItemNameFontSize" value="14" min="8" max="72">
                            </div>
                        </div>
                    </div>

                    <!-- Code Control -->
                    <div class="barcode-field-control">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="modalShowItemCode" checked>
                                    <label class="form-check-label" for="modalShowItemCode">{{ __('Show Code') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Font Size') }}</label>
                                <input type="number" class="form-control form-control-sm" id="modalItemCodeFontSize" value="14" min="8" max="72">
                            </div>
                        </div>
                    </div>

                    <!-- Price Control -->
                    <div class="barcode-field-control">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="modalShowPrice" checked>
                                    <label class="form-check-label" for="modalShowPrice">{{ __('Show Price') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Font Size') }}</label>
                                <input type="number" class="form-control form-control-sm" id="modalPriceFontSize" value="14" min="8" max="72">
                            </div>
                        </div>
                    </div>

                    <!-- Barcode Control -->
                    <div class="barcode-field-control">
                        <div class="row align-items-center justify-content-end">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="modalShowBarcode" checked>
                                    <label class="form-check-label" for="modalShowBarcode">{{ __('Show Barcode') }}</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Width') }}</label>
                                <input type="number" class="form-control form-control-sm" id="modalBarcodeWidth" value="1" min="1" max="10" step="0.1">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Height') }}</label>
                                <input type="number" class="form-control form-control-sm" id="modalBarcodeHeight" value="50" min="20" max="200">
                            </div>
                            <div class="col-md-6 mt-2">
                                <label class="form-label">{{ __('Code Font Size') }}</label>
                                <input type="number" class="form-control form-control-sm" id="modalBarcodeFontSize" value="12" min="8" max="72">
                            </div>
                        </div>
                        
                    </div>
                </div>

                <div class="barcode-preview">
                    <h6 class="mb-3">{{ __('Preview') }}</h6>
                    <div id="barcodeModalPreview"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="modalPrintBarcode">
                    <i class="ti tabler-printer me-2"></i> {{ __('Print Barcode') }}
                </button>
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
            </div>
        </div>
    </div>
</div>

@endsection
@push('page-js')
@routes
<!-- Page JS -->
<script src="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{ asset('backend_assets/js/extended-ui-sweetalert2.js')}}"></script>
<script src="{{ asset('backend_assets/vendor/js/barcode.js')}}"></script>
<script src="{{ asset('backend_assets/js/pages_list_js/list_item.js')}}"></script>
<script src="{{ asset('backend_assets/js/pages_list_js/delete_confirmation.js')}}"></script>
@endpush

