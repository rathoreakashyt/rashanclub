@extends('backend.backend_layout')
@section('page-title', __('Bulk Item Update'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/css/pages/cropper.min.css') }}" />
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
<style>
    .item-image-preview {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border: 1px solid #ddd;
        border-radius: 4px;
        cursor: pointer;
    }
    .item-image-preview:hover {
        opacity: 0.8;
    }
    .editable-field {
        min-width: 100px;
    }
    .editable-price {
        width: 120px;
    }
    .image-upload-wrapper {
        position: relative;
        display: inline-block;
    }
    .image-upload-btn {
        position: absolute;
        bottom: -5px;
        right: -5px;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: #696cff;
        color: white;
        border: 2px solid white;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 12px;
        z-index: 10;
    }
    .image-upload-btn:hover {
        background: #5f62e0;
    }
    .form-check {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .form-check-input {
        margin-top: 0;
    }
    #bulkDeleteItems, #saveBulkUpdate {
        display: flex;
        gap: 5px;
    }
    /* .dt-container .card-header {
        display: none !important;
    } */
</style>
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Bulk Item Update -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Bulk Item Update') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Items'), 
                    'link' => route('item.index')
                ],
                [
                    'label' => __('Bulk Item Update'),
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

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center gap-2">
                    <h5 class="card-title mb-0">{{ __('Update Items') }}</h5>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-danger" id="bulkDeleteItems" style="display: none;">
                            <i class="ti tabler-trash"></i> {{ __('Delete Selected') }}
                        </button>
                        <button type="button" class="btn btn-primary" id="saveBulkUpdate">
                            <i class="ti tabler-device-floppy"></i> {{ __('Save All Changes') }}
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="card-datatable table-responsive pt-0">
                        <table class="datatables-basic-bulk-update table">
                            <thead>
                                <tr>
                                    <th>
                                        <div class="form-check ps-0">
                                            <input class="form-check-input" type="checkbox" id="selectAllItems">
                                        </div>
                                    </th>
                                    <th>{{ __('SN') }}</th>
                                    <th>{{ __('Item Name') }}</th>
                                    <th>{{ __('Purchase Price') }}</th>
                                    <th>{{ __('MRP Price') }}</th>
                                    <th>{{ __('Sale Price') }}</th>
                                    <th>{{ __('Whole Sale Price') }}</th>
                                    <th>{{ __('Enable Status') }}</th>
                                    <th>{{ __('Image') }}</th>
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
</div>

<!-- Image Cropper Modal -->
<div class="modal fade" id="itemCropperModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Crop Item Image') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="img-container">
                    <img id="cropperImage" src="" alt="Item Image" style="max-width: 100%;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary" id="cropItemImageBtn">{{ __('Crop Image') }}</button>
            </div>
        </div>
    </div>
</div>

@endsection
@push('page-js')
@routes
<script src="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{ asset('backend_assets/js/extended-ui-sweetalert2.js')}}"></script>
<script src="{{ asset('backend_assets/vendor/js/cropper.js') }}"></script>
<script src="{{ asset('backend_assets/js/pages_list_js/list_item_bulk_update.js') }}"></script>
@endpush
