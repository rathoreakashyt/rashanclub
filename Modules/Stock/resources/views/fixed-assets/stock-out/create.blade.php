@extends('backend.backend_layout')
@section('page-title', isset($stockOut) ? __('Update') . ' ' . __('Stock Out') : __('Add') . ' ' . __('Stock Out'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/flatpickr/flatpickr.css') }}" />
@endpush
<style>
    #stockOutItems tr td {
        vertical-align: top;
    }
</style>
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Add Stock Out -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($stockOut) ? __('Update') . ' ' . __('Stock Out') : __('Add') . ' ' . __('Stock Out') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Fixed Assets'), 
                    'link' => '#'
                ],
                [
                    'label' => isset($stockOut) ? __('Update') . ' ' . __('Stock Out') : __('Add') . ' ' . __('Stock Out'),
                    'active' => true
                ]
            ]
        ])
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <form action="{{ isset($stockOut) ? route('fixed-asset-stock-out.update', $stockOut->encrypted_id) : route('fixed-asset-stock-out.store') }}" method="POST" id="stockOutForm" enctype="multipart/form-data">
                    @csrf
                    @if(isset($stockOut))
                        @method('PUT')
                    @endif
                
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="reference_no">{{ __('Reference No') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control" placeholder="{{ __('Reference No') }}" name="reference_no" id="reference_no" value="{{ isset($stockOut) ? $stockOut->reference_no : ($reference_no ?? '') }}" readonly />
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="date">{{ __('Date') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control datePicker" placeholder="{{ __('Date') }}" name="date" id="date" value="{{ isset($stockOut) ? $stockOut->date->format('Y-m-d') : date('Y-m-d') }}" />
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label">{{ __('Items') }}</label>
                                    <select id="quickItemSelect" class="form-select select2">
                                        <option value="">{{ __('Select Item') }}</option>
                                        @foreach($items ?? [] as $item)
                                            <option value="{{ $item->id }}" 
                                                data-item-name="{{ $item->name }}">
                                                {{ $item->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="note">{{ __('Note') }}</label>
                                    <textarea type="text" class="form-control" placeholder="{{ __('Note') }}" name="note" id="note" rows="3">{{ isset($stockOut) ? $stockOut->note : old('note') }}</textarea>
                                    @error('note')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>{{ __('SN') }}</th>
                                                <th>{{ __('Item') }} - {{ __('Code') }} - {{ __('Brand') }}</th>
                                                <th>{{ __('Quantity') }}</th>
                                                <th>{{ __('Unit Price') }}</th>
                                                <th>{{ __('Total') }}</th>
                                                <th>{{ __('Action') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody id="stockOutItems">
                                            @if(isset($stockOut) && $stockOut->stockOutDetails)
                                                @foreach($stockOut->stockOutDetails as $detail)
                                                <tr>
                                                    <td>{{ $loop->iteration }}</td>
                                                    <td>
                                                        <input type="hidden" class="item-id" name="items[]" value="{{ $detail->fixedAssetItem->id }}">
                                                        <span class="item-name">{{ $detail->fixedAssetItem->name }}</span>
                                                    </td>
                                                    <td>
                                                        <input type="text" placeholder="{{ __('Quantity') }}" class="form-control number-input quantity" name="quantity[]" min="1" value="{{ $detail->quantity }}">
                                                    </td>
                                                    <td>
                                                        <input type="text" placeholder="{{ __('Unit Price') }}" class="form-control number-input unit-price" name="unit_price[]" value="{{ $detail->unit_price }}">
                                                    </td>
                                                    <td>
                                                        <input type="text" placeholder="{{ __('Total') }}" class="form-control number-input total" name="total[]" readonly value="{{ $detail->total }}">
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn text-danger remove-row">
                                                            <i class="icon-base ti tabler-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                @endforeach
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Section -->
                    <div class="card-body">
                        <div class="row justify-content-end">
                            <!-- Grand Total Field -->
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="grandTotal">{{ __('Grand Total') }}</label>
                                    <input type="text" class="form-control" id="grandTotal" name="grand_total" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText(isset($stockOut) ? $stockOut : '') !!}
                            </button>
                            <a href="{{ route('fixed-asset-stock-out.index') }}" class="btn btn-primary waves-effect waves-light">
                                {!! backIconWithText() !!}
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
@routes
<script src="{{ asset('backend_assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
<script src="{{ asset('backend_assets/js/pages_js/add_stock_out.js') }}"></script>
@endpush

