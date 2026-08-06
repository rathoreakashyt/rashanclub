@extends('backend.backend_layout')
@section('page-title', __('Add') . ' ' . __('Purchase Return'))
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
    <!-- Add Purchase Return -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Add Purchase Return') }}</h4>
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
                    'label' => __('Add') . ' ' . __('Purchase Return'),
                    'active' => true
                ]
            ]
        ])
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <form action="{{ route('purchase-return.store') }}" method="POST" id="purchaseReturnForm" enctype="multipart/form-data">
                    @csrf
                
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="reference_no">{{ __('Reference No') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control" placeholder="{{ __('Reference No') }}" name="reference_no" id="reference_no" value="{{ $reference_no }}" readonly />
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="d-flex justify-content-between">
                                    <div class="mb-5 w-100 validate_wrapper">
                                        <label class="form-label" for="supplier_id">
                                            {{ __('Supplier') }} {!! requiredField() !!}
                                        </label>
                                        <select id="supplier_id" name="supplier_id" class="select2 form-select" data-placeholder="{{ __('Select') }} {{ __('Supplier') }}">
                                            <option value="">{{ __('Select') }} {{ __('Supplier') }}</option>
                                            @foreach($suppliers as $supplier)
                                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <a type="button" href="javascript:void(0);" class="fw-medium btn btn-icon btn-label-primary ms-4 add-plus-btn" data-bs-toggle="modal" data-bs-target="#modal_supplier"
                                    ><i class="icon-base ti tabler-plus icon-md"></i></a>
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="date">{{ __('Date') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control datePicker" placeholder="{{ __('Date') }}" name="date" id="date" value="{{ date('Y-m-d') }}" />
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="purchase_date">{{ __('Purchase Date') }}</label>
                                    <input type="text" class="form-control datePicker" placeholder="{{ __('Purchase Date') }}" name="purchase_date" id="purchase_date" value="" />
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="status">{{ __('Status') }} {!! requiredField() !!}</label>
                                    <select id="status" name="status" class="form-select select2" data-placeholder="{{ __('Select') }} {{ __('Status') }}">
                                        <option value="">{{ __('Select Status') }}</option>
                                        <option value="taken_by_sup_pro_not_returned">{{ __('Taken By Supplier Product Not Returned') }}</option>
                                        <option value="taken_by_sup_money_returned">{{ __('Taken By Supplier Money Returned') }}</option>
                                        <option value="taken_by_sup_pro_returned">{{ __('Taken By Supplier Product Returned') }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="invoice_no">{{ __('Invoice No') }}</label>
                                    <input type="text" class="form-control" placeholder="{{ __('Invoice No') }}" name="invoice_no" id="invoice_no" />
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="note">{{ __('Note') }}</label>
                                    <textarea class="form-control" placeholder="{{ __('Note') }}" name="note" id="note" rows="2"></textarea>
                                </div>
                            </div>

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
                                <h5 class="mb-4">{{ __('Total Item') }} <span id="totalItemCount">0</span> (<span id="totalItemQuantity">0</span>)</h5>
                            </div>
                            <div class="clear-fix"></div>
                            
                            <!-- Grand Total Field -->
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="total_return_amount">Grand Total</label>
                                    <input type="text" class="form-control" id="total_return_amount" name="total_return_amount" readonly>
                                </div>
                            </div>
                            <div class="clear-fix"></div>
                            
                            <!-- Payment Method Section (for taken_by_sup_money_returned status) -->
                            <div class="col-12 col-md-6 col-lg-4" id="payment_method_wrapper" style="display: none;">
                                <div class="mb-5">
                                    <label class="form-label">{{ __('Payment Method') }} {!! requiredField() !!}</label>
                                    <select id="paymentMethodSelect" class="form-select select2" data-placeholder="{{ __('Select') }} {{ __('Payment Method') }}">
                                        <option value="">{{ __('Select') }} {{ __('Payment Method') }}</option>
                                        @foreach($payment_methods ?? [] as $method)
                                            <option value="{{ $method->id }}">{{ $method->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" id="payment_method_id" name="payment_method_id" value="">
                                    <input type="hidden" id="payment_amount" name="payment_amount" value="">
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
                                    <label class="form-label">{{ __('Quantity') }}</label>
                                    <input type="text" 
                                        class="form-control number-input purchase-medicine-quantity-input" 
                                        id="purchase_medicine_quantity_input"
                                        placeholder="{{ __('Quantity') }}"
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
@endpush

