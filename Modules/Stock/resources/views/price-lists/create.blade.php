@extends('backend.backend_layout')
@section('page-title', isset($priceList) ? __('Update') . ' ' . __('Price List') : __('Add') . ' ' . __('Price List'))
@push('page-css')
<style>
    .price-row { background: #f8f9fa; padding: 10px; border-radius: 4px; margin-bottom: 8px; }
    .price-row .remove-row { cursor: pointer; color: #dc3545; }
</style>
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($priceList) ? __('Update') . ' ' . __('Price List') : __('Add') . ' ' . __('Price List') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => '#'],
                ['label' => __('Stock'), 'link' => '#'],
                ['label' => __('Price Lists'), 'link' => route('price-list.index')],
                ['label' => isset($priceList) ? __('Update') : __('Add'), 'active' => true]
            ]
        ])
    </div>

    @if(session('error'))
        {!! insertFailed(session('error')) !!}
    @endif

    <div class="row">
        <div class="col-12">
            <form action="{{ isset($priceList) ? route('price-list.update', $priceList->encrypted_id) : route('price-list.store') }}" method="POST">
                @csrf
                @if(isset($priceList)) @method('PUT') @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="name">{{ __('Name') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror"
                                        name="name" id="name" placeholder="{{ __('Name') }}"
                                        value="{{ old('name', isset($priceList) ? $priceList->name : '') }}" />
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="customer_type">{{ __('Customer_Type') }}</label>
                                    @php $oldCT = old('customer_type', isset($priceList) ? $priceList->customer_type : ''); @endphp
                                    <select class="form-select select2 @error('customer_type') is-invalid @enderror" name="customer_type" id="customer_type">
                                        <option value="">{{ __('All') }}</option>
                                        <option value="retail" {{ $oldCT === 'retail' ? 'selected' : '' }}>{{ __('Retail') }}</option>
                                        <option value="wholesale" {{ $oldCT === 'wholesale' ? 'selected' : '' }}>{{ __('Wholesale') }}</option>
                                    </select>
                                    @error('customer_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="description">{{ __('Description') }}</label>
                                    <input type="text" class="form-control @error('description') is-invalid @enderror"
                                        name="description" id="description" placeholder="{{ __('Description') }}"
                                        value="{{ old('description', isset($priceList) ? $priceList->description : '') }}" />
                                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <label class="form-label mb-0 fw-bold">{{ __('Items_and_Prices') }}</label>
                                    <button type="button" class="btn btn-sm btn-primary" id="add-price-row">
                                        <i class="ti tabler-plus"></i> {{ __('Add_Item') }}
                                    </button>
                                </div>
                                <div id="price-items-container">
                                    @php
                                        $existingItems = old('items', isset($priceList) && $priceList->items ? $priceList->items->toArray() : []);
                                        $itemIndex = 0;
                                    @endphp
                                    @if(!empty($existingItems) && count($existingItems) > 0)
                                        @foreach($existingItems as $ei)
                                        <div class="price-row row align-items-center">
                                            <div class="col-5">
                                                <select class="form-select select2 item-select" name="items[{{ $itemIndex }}][item_id]" data-placeholder="{{ __('Select') }} {{ __('Item') }}">
                                                    <option value="">{{ __('Select') }} {{ __('Item') }}</option>
                                                    @foreach(\Modules\Stock\Models\Item::where('del_status', 'Live')->where('company_id', session('company.company_id'))->get() as $item)
                                                    <option value="{{ $item->id }}" {{ ((string)($ei['item_id'] ?? '') === (string)$item->id) ? 'selected' : '' }}>
                                                        {{ $item->name }} ({{ $item->code }})
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-4">
                                                <input type="text" class="form-control number-input" name="items[{{ $itemIndex }}][price]" placeholder="{{ __('Price') }}" value="{{ $ei['price'] ?? '' }}" />
                                            </div>
                                            <div class="col-2">
                                                <span class="remove-row"><i class="ti tabler-trash"></i></span>
                                            </div>
                                        </div>
                                        @php $itemIndex++; @endphp
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText(isset($priceList) ? $priceList : '') !!}
                            </button>
                            <a href="{{ route('price-list.index') }}" class="btn btn-primary waves-effect waves-light">
                                {!! backIconWithText() !!}
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
@push('page-js')
@routes
<script>
$(function() {
    'use strict';
    var rowIndex = {{ $itemIndex ?? 0 }};

    function initSelect2(row) {
        row.find('.item-select').each(function() {
            if ($(this).hasClass('select2-hidden-accessible')) {
                $(this).select2('destroy');
            }
            $(this).select2({
                dropdownParent: $(this).closest('.price-row')
            });
        });
    }

    $('#add-price-row').on('click', function() {
        var html = '<div class="price-row row align-items-center">' +
            '<div class="col-5">' +
            '<select class="form-select select2 item-select" name="items[' + rowIndex + '][item_id]" data-placeholder="{{ __('Select') }} {{ __('Item') }}">' +
            '<option value="">{{ __('Select') }} {{ __('Item') }}</option>' +
            '@foreach(\Modules\Stock\Models\Item::where("del_status", "Live")->where("company_id", session("company.company_id"))->get() as $item)' +
            '<option value="{{ $item->id }}">{{ $item->name }} ({{ $item->code }})</option>' +
            '@endforeach' +
            '</select>' +
            '</div>' +
            '<div class="col-4">' +
            '<input type="text" class="form-control number-input" name="items[' + rowIndex + '][price]" placeholder="{{ __('Price') }}" />' +
            '</div>' +
            '<div class="col-2">' +
            '<span class="remove-row"><i class="ti tabler-trash"></i></span>' +
            '</div>' +
            '</div>';
        var $row = $(html);
        $('#price-items-container').append($row);
        initSelect2($row);
        rowIndex++;
    });

    $(document).on('click', '.remove-row', function() {
        $(this).closest('.price-row').remove();
    });

    $('#price-items-container .price-row').each(function() {
        initSelect2($(this));
    });
});
</script>
@endpush
