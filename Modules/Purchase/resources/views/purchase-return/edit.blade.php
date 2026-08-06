@extends('backend.backend_layout')
@section('page-title', __('Edit') . ' ' . __('Purchase Return'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/dropzone/dropzone.css') }}" />
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/flatpickr/flatpickr.css') }}" />
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />

<style>
    .add-purchase-medicine-field {
        height: 37px;
    }
</style>

@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Edit Purchase Return -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Edit') }} {{ __('Purchase Return') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Purchase Return'), 
                    'link' => route('purchase-return.index')
                ],
                [
                    'label' => __('Edit') . ' ' . __('Purchase Return'),
                    'active' => true
                ]
            ]
        ])
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <form action="{{ route('purchase-return.update', $purchaseReturn->encrypted_id) }}" method="POST" id="purchaseReturnForm" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="reference_no">{{ __('Reference No') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control" placeholder="{{ __('Reference No') }}" name="reference_no" id="reference_no" value="{{ $purchaseReturn->reference_no }}" readonly />
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="mb-5 w-100 validate_wrapper">
                                        <label class="form-label" for="supplier_id">
                                            {{ __('Supplier') }} {!! requiredField() !!}
                                        </label>
                                        <select id="supplier_id" name="supplier_id" class="select2 form-select" data-placeholder="{{ __('Select') }} {{ __('Supplier') }}">
                                            <option value="">{{ __('Select') }} {{ __('Supplier') }}</option>
                                            @foreach($suppliers as $supplier)
                                                <option value="{{ $supplier->id }}" {{ $purchaseReturn->supplier_id == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <a type="button" href="javascript:void(0);" class="fw-medium btn btn-icon btn-label-primary ms-4" data-bs-toggle="modal" data-bs-target="#modal_supplier"
                                    ><i class="icon-base ti tabler-plus icon-md"></i></a>
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="date">{{ __('Date') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control datePicker" placeholder="{{ __('Date') }}" name="date" id="date" value="{{ $purchaseReturn->date ? date('Y-m-d', strtotime($purchaseReturn->date)) : date('Y-m-d') }}" />
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="purchase_date">{{ __('Purchase Date') }}</label>
                                    <input type="text" class="form-control datePicker" placeholder="{{ __('Purchase Date') }}" name="purchase_date" id="purchase_date" value="{{ $purchaseReturn->purchase_date ? date('Y-m-d', strtotime($purchaseReturn->purchase_date)) : '' }}" />
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="status">{{ __('Status') }} {!! requiredField() !!}</label>
                                    <select id="status" name="status" class="form-select select2" data-placeholder="{{ __('Select') }} {{ __('Status') }}">
                                        <option value="">{{ __('Select Status') }}</option>
                                        <option value="taken_by_sup_pro_not_returned" {{ $purchaseReturn->return_status === 'taken_by_sup_pro_not_returned' ? 'selected' : '' }}>{{ __('Taken By Supplier Product Not Returned') }}</option>
                                        <option value="taken_by_sup_money_returned" {{ $purchaseReturn->return_status === 'taken_by_sup_money_returned' ? 'selected' : '' }}>{{ __('Taken By Supplier Money Returned') }}</option>
                                        <option value="taken_by_sup_pro_returned" {{ $purchaseReturn->return_status === 'taken_by_sup_pro_returned' ? 'selected' : '' }}>{{ __('Taken By Supplier Product Returned') }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4" id="amount_field_wrapper">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="amount">{{ __('Amount') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control number-input" placeholder="{{ __('Amount') }}" name="amount" id="amount" value="{{ $purchaseReturn->amount }}" />
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="invoice_no">{{ __('Invoice No') }}</label>
                                    <input type="text" class="form-control" placeholder="{{ __('Invoice No') }}" name="invoice_no" id="invoice_no" value="{{ $purchaseReturn->invoice_no }}" />
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="note">{{ __('Note') }}</label>
                                    <textarea class="form-control" placeholder="{{ __('Note') }}" name="note" id="note" rows="2">{{ $purchaseReturn->note }}</textarea>
                                </div>
                            </div>

                            <div class="clear-fix"></div>
                            <div class="clear-fix"></div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label">{{ __('Items') }}</label>
                                    <select id="quickItemSelect" class="form-select select2">
                                        <option value="">{{ __('Select') }} {{ __('Item') }}</option>
                                        @foreach($items as $item)
                                            @php
                                                $conversionRate = (float)($item->conversion_rate ?? 1);
                                                $conversionRate = $conversionRate > 0 ? $conversionRate : 1;
                                                $unitType = $item->unit_type ?? '1';
                                            @endphp
                                            <option value="{{ $item->id }}" 
                                                data-purchase-price="{{ $item->purchase_price }}"
                                                data-sale-price="{{ $item->sale_price }}"
                                                data-mrp-price="{{ $item->mrp_price }}"
                                                data-purchase-unit="{{ $item->purchase_unit_id }}"
                                                data-sale-unit="{{ $item->sale_unit_id }}"
                                                data-purchase-unit-name="{{ $item->purchaseUnit->unit_name ?? '' }}"
                                                data-sale-unit-name="{{ $item->saleUnit->unit_name ?? '' }}"
                                                data-conversion-rate="{{ $conversionRate }}"
                                                data-unit-type="{{ $unitType }}"
                                                data-item-type="{{ $item->type }}"
                                                data-expiry-date-maintain="{{ $item->expiry_date_maintain ?? 'Yes' }}"
                                                data-item-name="{{ $item->name }} ({{ $item->code }})"
                                                data-item-parent-name="{{ $item->name }}">
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
                                            <th class="w-5">{{ __('SN') }}</th>
                                            <th class="w-25">{{ __('Item Name') }}</th>
                                            <th class="w-20">{{ __('IMEI/Serial/Medicine') }}</th>
                                            <th class="w-15">{{ __('Quantity') }}</th>
                                            <th class="w-15">{{ __('Unit Price') }}</th>
                                            <th class="w-15">{{ __('Total') }}</th>
                                            <th class="w-5">{{ __('Action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="purchaseReturnItems">
                                        @foreach($purchaseReturn->purchaseReturnDetails as $index => $detail)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>
                                                <input type="hidden" class="item-id" name="items[]" value="{{ $detail->item_id }}">
                                                <input type="hidden" class="parent-id" name="parent_ids[]" value="{{ $detail->item && $detail->item->parent_id ? $detail->item->parent_id : '' }}">
                                                <input type="hidden" class="item-type" name="item_types[]" value="{{ $detail->item->type ?? 'General_Product' }}">
                                                <span class="item-name">
                                                    @if($detail->item)
                                                        @if($detail->item->parent_id && $detail->item->parent)
                                                            {{ $detail->item->parent->name }} - {{ $detail->item->name }} ({{ $detail->item->code }})
                                                        @else
                                                            {{ $detail->item->name }} ({{ $detail->item->code }})
                                                        @endif
                                                    @else
                                                        {{ __('N/A') }}
                                                    @endif
                                                </span>
                                            </td>
                                            <td class="imei-serial-cell">
                                                <div class="imei-serial-container">
                                                    <input type="text" class="form-control imei-serial" name="imei_serial[]" placeholder="{{ __('IMEI/Serial') }}" readonly value="{{ $detail->expiry_imei_serial ?? '' }}" style="{{ in_array($detail->item->type ?? '', ['IMEI_Product', 'Serial_Product', 'Medicine_Product']) ? '' : 'display:none;' }}">
                                                    @if(in_array($detail->item->type ?? '', ['IMEI_Product', 'Serial_Product', 'Medicine_Product']))
                                                        <button type="button" class="btn btn-sm btn-primary add-imei-serial" style="display:inline-block;">
                                                            <i class="ti tabler-plus"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                                @if(($purchaseReturn->status === 'taken_by_sup_pro_returned' || $purchaseReturn->status === 'taken by supplier product returned') && in_array($detail->item->type ?? '', ['IMEI_Product', 'Serial_Product', 'Medicine_Product']))
                                                <div class="mt-2">
                                                    <label class="form-label small">{{ __('Returned') }} {{ $detail->item->type === 'IMEI_Product' ? __('IMEI') : ($detail->item->type === 'Serial_Product' ? __('Serial') : __('Medicine')) }}</label>
                                                    <input type="text" class="form-control returned-imei-serial {{ $detail->item->type === 'Medicine_Product' ? 'datePicker' : '' }}" name="returned_imei_serial[]" placeholder="{{ __('Enter') }} {{ __('returned') }} {{ $detail->item->type === 'IMEI_Product' ? __('IMEI') : ($detail->item->type === 'Serial_Product' ? __('Serial') : __('Medicine')) }}" value="{{ $detail->returned_imei_serial ?? '' }}" style="display:block;">
                                                </div>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="input-group">
                                                    <input type="text" class="form-control number-input quantity" name="quantity[]" value="{{ $detail->return_quantity_amount ?? $detail->return_quantity_amount }}" data-max-stock="">
                                                    <button type="button" class="btn btn-outline-secondary unit-label" type="button">
                                                        {{ $detail->item->saleUnit->unit_name ?? $detail->item->purchaseUnit->unit_name ?? '' }}
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
                                                <button type="button" class="btn text-danger remove-row">
                                                    <i class="icon-base ti tabler-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        @endforeach
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
                                <h5 class="mb-4">{{ __('Total Item') }} <span id="totalItemCount">{{ $purchaseReturn->purchaseReturnDetails->count() }}</span> (<span id="totalItemQuantity">{{ $purchaseReturn->purchaseReturnDetails->sum('return_quantity_amount') }}</span>)</h5>
                            </div>
                            <div class="clear-fix"></div>
                            
                            <!-- Grand Total Field -->
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="total_return_amount">{{ __('Grand Total') }}</label>
                                    <input type="text" class="form-control" id="total_return_amount" name="total_return_amount" readonly value="{{ number_format($purchaseReturn->total_return_amount ?? $purchaseReturn->grand_total ?? 0, 2) }}">
                                </div>
                            </div>
                            <div class="clear-fix"></div>
                            
                            <!-- Payment Method Section (for taken_by_sup_money_returned status) -->
                            <div class="col-12 col-md-6 col-lg-4" id="payment_method_wrapper" style="display: {{ ($purchaseReturn->status === 'taken_by_sup_money_returned' || $purchaseReturn->status === 'Taken by supplier money returned') ? 'block' : 'none' }};">
                                <div class="mb-5">
                                    <label class="form-label">{{ __('Payment Method') }} {!! requiredField() !!}</label>
                                    <select id="paymentMethodSelect" class="form-select select2" data-placeholder="{{ __('Select') }} {{ __('Payment Method') }}">
                                        <option value="">{{ __('Select') }} {{ __('Payment Method') }}</option>
                                        @foreach($payment_methods ?? [] as $method)
                                            <option value="{{ $method->id }}" {{ ($purchaseReturn->payment_method_id ?? '') == $method->id ? 'selected' : '' }}>{{ $method->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" id="payment_method_id" name="payment_method_id" value="{{ $purchaseReturn->payment_method_id ?? '' }}">
                                    <input type="hidden" id="payment_amount" name="payment_amount" value="{{ $purchaseReturn->payment_amount ?? '' }}">
                                </div>
                            </div>
                            <div class="clear-fix"></div>
                            
                            <!-- Payment Amount Field (for taken_by_sup_money_returned status) -->
                            <div class="col-12 col-md-6 col-lg-4" id="payment_amount_wrapper" style="display: {{ ($purchaseReturn->status === 'taken_by_sup_money_returned' || $purchaseReturn->status === 'Taken by supplier money returned') ? 'block' : 'none' }};">
                                <div class="mb-5">
                                    <label class="form-label" for="payment_amount_input">{{ __('Payment Amount') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control number-input" id="payment_amount_input" placeholder="{{ __('Enter') }} {{ __('Payment Amount') }}" value="{{ $purchaseReturn->payment_amount ?? '' }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" name="submit" value="submit" class="btn btn-primary add_item_submit me-4">
                            {!! submitIconWithText('') !!}
                            </button>
                            <a href="{{ route('purchase-return.index') }}" class="btn btn-primary waves-effect waves-light">
                                {!! backIconWithText() !!}
                            </a>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>
    
</div>

<!-- Supplier Modal -->
<div class="modal fade" id="modal_supplier" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <form id="supplierForm">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Add Supplier') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-4 col-md-6 col-12 mb-4 validate_wrapper">
                            <label for="name" class="form-label">{{ __('Name') }} {!! requiredField() !!}</label>
                            <input type="text" name="name" id="name" class="form-control" placeholder="{{ __('Enter') }} {{ __('Name') }}" />
                        </div>
                        <div class="col-lg-4 col-md-6 col-12 mb-4 validate_wrapper">
                            <label for="contact_person" class="form-label">{{ __('contact_person') }} {!! requiredField() !!}</label>
                            <input type="text" name="contact_person" id="contact_person" class="form-control" placeholder="{{ __('Enter') }} {{ __('contact_person') }}" />
                        </div>
                        <div class="col-lg-4 col-md-6 col-12 mb-4 validate_wrapper">
                            <label for="phone" class="form-label">{{ __('Phone') }} {!! requiredField() !!}</label>
                            <input type="text" name="phone" id="phone" class="form-control" placeholder="{{ __('Enter') }} {{ __('Phone') }}" />
                        </div>
                        <div class="col-lg-4 col-md-6 col-12 mb-4 validate_wrapper">
                            <label for="email" class="form-label">{{ __('Email') }}</label>
                            <input type="email" name="email" id="email" class="form-control" placeholder="{{ __('Enter') }} {{ __('Email') }}" />
                        </div>
                        <div class="col-lg-4 col-md-6 col-12 mb-4">
                            <label for="opening_balance" class="form-label">{{ __('opening_balance') }}</label>
                            <div class="row">
                                <div class="col-7 validate_wrapper">
                                    <input type="text" name="opening_balance" id="opening_balance" class="form-control" placeholder="{{ __('opening_balance') }}" />
                                </div>
                                <div class="col-5 validate_wrapper">
                                    <select name="opening_balance_type" id="opening_balance_type" class="select2 form-select" data-placeholder="{{ __('opening_balance_type') }}">
                                        <option value="Debit">{{ __('Debit') }}</option>
                                        <option value="Credit">{{ __('Credit') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6 col-12 mb-4 validate_wrapper">
                            <label for="description" class="form-label">{{ __('Description') }}</label>
                            <textarea name="description" id="description" class="form-control" placeholder="{{ __('Enter') }} {{ __('Description') }}"></textarea>
                        </div>
                        <div class="col-lg-4 col-md-6 col-12 mb-4 validate_wrapper">
                            <label for="address" class="form-label">{{ __('Address') }}</label>
                            <textarea name="address" id="address" class="form-control" placeholder="{{ __('Enter') }} {{ __('Address') }}"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary add_supplier">
                        {!! submitIconWithText('') !!}
                    </button>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                        {!! closeIconWithText() !!}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Supplier Modal End -->

<!-- IMEI/Serial/Medicine Modal -->
<div class="modal fade" id="modal_imei_serial" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header pb-2">
                <h5 class="modal-title" id="modal_imei_serial_title">{{ __('Add') }} {{ __('Item Details') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">{{ __('Unit Price') }} {!! requiredField() !!}</label>
                    <input type="text" class="form-control number-input" id="modal_unit_price" required>
                </div>
                
                <!-- IMEI/Serial Product Section -->
                <div id="imei_serial_section" style="display: none;">
                    <div class="mb-3">
                        <label class="form-label" id="imei_serial_label">{{ __('IMEI/Serial Numbers') }}</label>
                        <div class="input-group mb-2">
                            <input type="text" class="form-control purchase-imei-serial-input" id="purchase_imei_serial_input" placeholder="{{ __('Enter') }} {{ __('IMEI/Serial') }}" autocomplete="off">
                            <button type="button" class="btn btn-outline-primary add-purchase-imei-serial-field">
                                <i class="ti tabler-plus"></i>
                            </button>
                        </div>
                        <div class="purchase-imei-serial-list" id="purchase_imei_serial_list"></div>
                    </div>
                </div>
                
                <!-- Medicine Product Section -->
                <div id="medicine_section" style="display: none;">
                    <div class="mb-3">
                        <div class="d-flex gap-2">
                            <div class="d-flex justify-content-between gap-2 w-100">
                                <div class="mb-3">
                                    <label class="form-label">Quantity</label>
                                    <input type="text"
                                        class="form-control number-input purchase-medicine-quantity-input" 
                                        id="purchase_medicine_quantity_input"
                                        placeholder="Quantity"
                                        min="1"
                                        autocomplete="off" />
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('MM/YY') }}</label>
                                    <input type="text" 
                                        class="form-control purchase-medicine-mmyy-input" 
                                        id="purchase_medicine_mmyy_input"
                                        placeholder="{{ __('MM/YY') }}"
                                        maxlength="5"
                                        autocomplete="off" />
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Expiry Date') }}</label>
                                    <input type="text" 
                                        class="form-control purchase-medicine-expiry-input datePicker" 
                                        id="purchase_medicine_expiry_input"
                                        placeholder="{{ __('Expiry Date') }}"
                                        autocomplete="off" />
                                </div>
                            </div>
                            <div class="mb-3 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-primary add-purchase-medicine-field">
                                    <i class="ti tabler-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="purchase-medicine-list" id="purchase_medicine_list"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="save_imei_serial">{{ __('Save') }}</button>
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
            </div>
        </div>
    </div>
</div>
<!-- IMEI/Serial/Medicine Modal End -->

@endsection

@push('page-js')
@routes
<script src="{{ asset('backend_assets/vendor/libs/dropzone/dropzone.js') }}"></script>
<script src="{{ asset('backend_assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
<script src="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{ asset('backend_assets/js/extended-ui-sweetalert2.js')}}"></script>
<!-- Pass data to JavaScript -->
<input type="hidden" id="variation_child_items_url" value="{{ route('purchase-return.variation-child-items') }}">
<input type="hidden" id="item_current_stock_url" value="{{ route('purchase-return.item-current-stock') }}">
<script src="{{ asset('backend_assets/js/pages_js/add_purchase_return.js') }}"></script>

<script>
    // Initialize existing rows and payment methods when page loads
    $(function() {
        // Wait for add_purchase_return.js to fully load
        setTimeout(function() {
            // Initialize events for existing rows
            $('#purchaseReturnItems tr').each(function() {
                const row = $(this);
                const itemType = row.find('.item-type').val();
                const imeiSerialInput = row.find('.imei-serial');
                
                // Show/hide IMEI/Serial input field based on item type
                if (itemType === 'IMEI_Product' || itemType === 'Serial_Product' || itemType === 'Medicine_Product') {
                    imeiSerialInput.show();
                    imeiSerialInput.prop('readonly', true);
                    row.find('.add-imei-serial').show();
                } else {
                    imeiSerialInput.hide();
                    row.find('.add-imei-serial').hide();
                }
                
                // Initialize row events if function exists
                if (typeof initializeRowEvents === 'function') {
                    initializeRowEvents(row);
                }
                
                // Initialize date picker for returned medicine fields
                if (itemType === 'Medicine_Product') {
                    const returnedInput = row.find('.returned-imei-serial.datePicker');
                    if (returnedInput.length > 0 && !returnedInput.hasClass('flatpickr-input')) {
                        returnedInput.flatpickr({
                            altInput: true,
                            altFormat: 'Y-m-d',
                            dateFormat: 'Y-m-d',
                            static: true,
                            allowInput: true
                        });
                    }
                }
            });
            
            // Initialize payment method if status requires it
            const status = $('#status').val();
            if (status === 'taken_by_sup_money_returned' || status === 'Taken by supplier money returned') {
                const paymentMethodId = $('#payment_method_id').val();
                if (paymentMethodId) {
                    $('#paymentMethodSelect').val(paymentMethodId).trigger('change');
                }
                const paymentAmount = $('#payment_amount').val();
                if (paymentAmount) {
                    $('#payment_amount_input').val(paymentAmount);
                }
            }
            
            // Update summary after loading
            if (typeof updateSummary === 'function') {
                updateSummary();
            }
            
            // Load current stock for existing rows (for return quantity validation)
            var stockUrl = $('#item_current_stock_url').val();
            if (stockUrl) {
                $('#purchaseReturnItems tr').each(function() {
                    var row = $(this);
                    var itemId = row.find('.item-id').val();
                    if (itemId) {
                        $.get(stockUrl, { item_id: itemId }, function(res) {
                            var stock = res.current_stock != null ? parseFloat(res.current_stock) : 0;
                            row.find('.quantity').attr('data-max-stock', stock);
                        });
                    }
                });
            }
            
            // Initialize status fields
            if (typeof toggleStatusFields === 'function') {
                toggleStatusFields();
            }
        }, 300);
    });
</script>

@endpush

