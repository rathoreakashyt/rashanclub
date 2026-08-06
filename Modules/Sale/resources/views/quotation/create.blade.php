@extends('backend.backend_layout')
@section('page-title', isset($quotation) ? __('Edit') . ' ' . __('Quotation') : __('Add') . ' ' . __('Quotation'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/dropzone/dropzone.css') }}" />
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/flatpickr/flatpickr.css') }}" />
@endpush
<style>
    #quotationItems tr td {
        vertical-align: top;
    }
</style>
@section('page-content')


<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Add Quotation -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($quotation) ? __('Edit') : __('Add') }} {{ __('Quotation') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Quotation'), 
                    'link' => '#'
                ],
                [
                    'label' => (isset($quotation) ? __('Edit') : __('Add')) . ' ' . __('Quotation'),
                    'active' => true
                ]
            ]
        ])
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <form action="{{ isset($quotation) ? route('quotation.update', $quotation->encrypted_id) : route('quotation.store') }}" method="POST" id="quotationForm" enctype="multipart/form-data">
                    @csrf
                    @if(isset($quotation))
                        @method('PUT')
                    @endif
                
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="reference_no">{{ __('Reference No') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control" placeholder="{{ __('Reference No') }}" name="reference_no" id="reference_no" value="{{ isset($quotation) ? $quotation->reference_no : ($reference_no ?? '') }}" readonly />
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="customer_id">{{ __('Customer') }} {!! requiredField() !!}</label>
                                    <select id="customer_id" name="customer_id" class="select2 form-select" data-placeholder="{{ __('Select') }} {{ __('Customer') }}">
                                        <option value="">{{ __('Select') }} {{ __('Customer') }}</option>
                                        @foreach($customers ?? [] as $customer)
                                            <option value="{{ $customer->id }}" {{ (isset($quotation) && $quotation->customer_id == $customer->id) ? 'selected' : '' }}>{{ $customer->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="date">{{ __('Date') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control datePicker" placeholder="{{ __('Date') }}" name="date" id="date" value="{{ isset($quotation) ? $quotation->date->format('Y-m-d') : date('Y-m-d') }}" />
                                </div>
                            </div>
                            <div class="clear-fix"></div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label">{{ __('Items') }} {!! requiredField() !!}</label>
                                    <select id="quickItemSelect" class="form-select select2">
                                        <option value="">{{ __('Select') }} {{ __('Item') }}</option>
                                        @foreach($items ?? [] as $item)
                                            <option value="{{ $item->id }}" 
                                                data-sale-price="{{ $item->sale_price }}"
                                                data-mrp-price="{{ $item->mrp_price }}"
                                                data-purchase-price="{{ $item->purchase_price }}"
                                                data-purchase-unit="{{ $item->purchase_unit_id }}"
                                                data-sale-unit="{{ $item->sale_unit_id }}"
                                                data-item-type="{{ $item->type }}"
                                                data-purchase-unit-name="{{ $item->purchaseUnit->unit_name ?? '' }}"
                                                data-sale-unit-name="{{ $item->saleUnit->unit_name ?? '' }}"
                                                data-item-name="{{ $item->name }} ({{ $item->code }})"
                                                data-item-name-only="{{ $item->name }}">
                                                {{ $item->name }} ({{ $item->code }})
                                            </option>
                                        @endforeach
                                    </select>
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
                                                <th>{{ __('Item Name') }}</th>
                                                <th>{{ __('Quantity') }}</th>
                                                <th>{{ __('Unit Price') }}</th>
                                                <th>{{ __('Total') }}</th>
                                                <th>{{ __('Description') }}</th>
                                                <th>{{ __('Action') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody id="quotationItems">
                                            @if(isset($quotation) && $quotation->quotationDetails)
                                                @foreach($quotation->quotationDetails as $detail)
                                                <tr>
                                                    <td>{{ $loop->iteration }}</td>
                                                    <td>
                                                        <input type="hidden" class="item-id" name="items[]" value="{{ $detail->item->id }}">
                                                        <input type="hidden" class="parent-id" name="parent_ids[]" value="">
                                                        <span class="item-name">{{ $detail->item->name }} ({{ $detail->item->code }})</span>
                                                    </td>
                                                    <td>
                                                        <div class="input-group ">
                                                            <input type="text" class="form-control number-input quantity" name="quantity[]" value="{{ $detail->quantity }}">
                                                            <button type="button" class="btn btn-outline-secondary" type="button">
                                                                {{ $detail->item->saleUnit->unit_name }}
                                                            </button>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control number-input unit-price" name="unit_price[]" value="{{ $detail->unit_price }}">
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control number-input total" name="total[]" readonly value="{{ $detail->total }}">
                                                    </td>
                                                    <td>
                                                        <textarea class="form-control description" name="description[]" rows="2" placeholder="Enter description">{{ $detail->description || '' }}</textarea>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn text-danger btn-sm remove-row">
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
                            <div class="col-12 col-md-6 col-lg-4">
                                <h5 class="mb-4">{{ __('Total Item') }} <span id="totalItemCount">0</span></h5>
                            </div>
                            <div class="clear-fix"></div>
                            
                            <!-- Discount Field -->
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="discount">{{ __('Discount') }}</label>
                                    <input type="text" class="form-control" id="discount" name="discount" placeholder="{{ __('Discount_10_or_percentage') }}" value="{{ isset($quotation) ? $quotation->discount : '' }}">
                                </div>
                            </div>
                            <div class="clear-fix"></div>
                            
                            <!-- Grand Total Field -->
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="grandTotal">{{ __('Grand Total') }}</label>
                                    <input type="text" class="form-control" id="grandTotal" name="grand_total" readonly>
                                </div>
                            </div>
                            <div class="clear-fix"></div>
                            
                            <!-- Note Field -->
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="note">{{ __('Note') }}</label>
                                    <textarea class="form-control" id="note" name="note" rows="3" placeholder="{{ __('Enter any additional notes') }}...">{{ isset($quotation) ? $quotation->note : '' }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden input for submit action (email / print) -->
                    <input type="hidden" name="submit_action" id="submit_action" value="">

                    <!-- Submit Button -->
                    <div class="card-footer">
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary waves-effect waves-light btn-submit">
                                {!! submitIconWithText(isset($quotation) ? $quotation : '') !!}
                            </button>
                            <button type="button" class="btn btn-primary waves-effect waves-light btn-save-email" data-action="email">
                                <i class="ti tabler-mail me-1"></i> {{ __('Save and Email') }}
                            </button>
                            <button type="button" class="btn btn-primary waves-effect waves-light btn-save-print" data-action="print">
                                <i class="ti tabler-printer me-1"></i> {{ __('Save and Print') }}
                            </button>
                            <a href="{{ route('quotation.index') }}" class="btn btn-primary waves-effect waves-light">
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
<script src="{{ asset('backend_assets/vendor/libs/dropzone/dropzone.js') }}"></script>
<script src="{{ asset('backend_assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
<!-- Pass data to JavaScript -->
<input type="hidden" id="variation_child_items_url" value="{{ route('quotation.variation-child-items') }}">
<script src="{{ asset('backend_assets/js/pages_js/add_quotation.js') }}"></script>
@endpush
