@extends('backend.backend_layout')
@section('page-title', isset($installmentSale) ? __('Update') . ' ' . __('Installment') . ' ' . __('Sale') : __('Add') . ' ' . __('Installment') . ' ' . __('Sale'))
@push('page-css')
<style>
    .error-msg { display: none; color: var(--bs-form-invalid-color); font-size: 0.8125rem; margin-top: 5px; }
    .inst-heading { margin-bottom: 0px; color: #566a7f; }
    .imeiSerialHideShow { display: none; }
    .empty-data-icon { font-size: 60px; color: #6c757d; }
</style>
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($installmentSale) ? __('Update') . ' ' . __('Installment') . ' ' . __('Sale') : __('Add') . ' ' . __('Installment') . ' ' . __('Sale') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Installment') . ' ' . __('Sale'), 
                    'link' => '#'
                ],
                [
                    'label' => isset($installmentSale) ? __('Update') . ' ' . __('Installment') . ' ' . __('Sale') : __('Add') . ' ' . __('Installment') . ' ' . __('Sale'),
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
            <form id="installment_form" action="{{ isset($installmentSale) ? route('installment-sale.update', $installmentSale->encrypted_id) : route('installment-sale.store') }}" method="POST">
                @csrf
                @if(isset($installmentSale))
                    @method('PUT')
                @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <!-- Left Column - Form Fields -->
                            <div class="col-md-12 col-lg-6">
                                <div class="row">
                                    <!-- Date -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label" for="date">{{ __('Date') }} {!! requiredField() !!}</label>
                                        <input type="text" class="form-control datePicker @error('date') is-invalid @enderror" 
                                            name="date" id="date" readonly
                                            value="{{ old('date', isset($installmentSale) ? $installmentSale->date->format('Y-m-d') : date('Y-m-d')) }}" />
                                        @error('date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Reference No -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label" for="reference_no">{{ __('Reference No') }} {!! requiredField() !!}</label>
                                        <input type="text" class="form-control @error('reference_no') is-invalid @enderror" 
                                            name="reference_no" id="reference_no" readonly
                                            value="{{ old('reference_no', isset($installmentSale) ? $installmentSale->reference_no : $reference_no) }}" />
                                        @error('reference_no')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Customer -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label" for="customer_id">{{ __('Customer') }} {!! requiredField() !!}</label>
                                        <select class="form-select select2 @error('customer_id') is-invalid @enderror" 
                                            name="customer_id" id="customer_id">
                                            <option value="">{{ __('Select') }}</option>
                                            @foreach($customers as $customer)
                                                <option value="{{ $customer->id }}" 
                                                    {{ old('customer_id', isset($installmentSale) ? $installmentSale->customer_id : '') == $customer->id ? 'selected' : '' }}>
                                                    {{ $customer->name }} ({{ $customer->phone }})
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="error-msg customer_id_err_msg_contnr">
                                            <p id="customer_id_err_msg"></p>
                                        </div>
                                        @error('customer_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Product -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label" for="item_id">{{ __('Product') }} {!! requiredField() !!}</label>
                                        <select class="form-select select2 item_id @error('item_id') is-invalid @enderror" 
                                            name="item_id" id="item_id">
                                            <option value="">{{ __('Select') }}</option>
                                            @foreach($products as $product)
                                                <option value="{{ $product->id }}" 
                                                    data-item-type="{{ $product->type }}"
                                                    data-price="{{ $product->sale_price }}"
                                                    data-mrp-price="{{ $product->mrp_price }}"
                                                    {{ old('item_id', isset($installmentSale) ? $installmentSale->item_id : '') == $product->id ? 'selected' : '' }}>
                                                    {{ $product->name }}{{ $product->brand_name ? ' - ' . $product->brand_name : '' }} - {{ $product->code }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="error-msg item_id_err_msg_contnr">
                                            <p id="item_id_err_msg"></p>
                                        </div>
                                        @error('item_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- IMEI/Serial (Hidden by default) -->
                                    <div class="col-md-6 mb-4 imeiSerialHideShow">
                                        <label class="form-label imei_serial_label" for="expiry_imei_serial">{{ __('IMEI_Serial') }} {!! requiredField() !!}</label>
                                        <input type="text" class="form-control @error('expiry_imei_serial') is-invalid @enderror" 
                                            name="expiry_imei_serial" id="expiry_imei_serial" readonly
                                            value="{{ old('expiry_imei_serial', isset($installmentSale) ? $installmentSale->expiry_imei_serial : '') }}" />
                                        <input type="hidden" name="item_type" id="item_type" value="{{ old('item_type', isset($installmentSale) ? $installmentSale->item_type : '') }}">
                                        <div class="error-msg imei_serial_field_err_msg_contnr">
                                            <p id="imei_serial_field_err_msg"></p>
                                        </div>
                                        @error('expiry_imei_serial')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Price -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label" for="price">{{ __('Price') }} {!! requiredField() !!}</label>
                                        <input type="text" class="form-control change_data integerchk @error('price') is-invalid @enderror" 
                                            name="price" id="price" onfocus="select()" placeholder="{{ __('0.00') }}"
                                            value="{{ old('price', isset($installmentSale) ? $installmentSale->price : '') }}" />
                                        <div class="error-msg price_err_msg_contnr">
                                            <p id="price_err_msg"></p>
                                        </div>
                                        @error('price')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Discount -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label" for="discount">{{ __('Discount') }}</label>
                                        <input type="text" class="form-control change_data discount @error('discount') is-invalid @enderror" 
                                            name="discount" id="discount" onfocus="select()"
                                            placeholder="{{ __('e.g. 10 or 10%') }}"
                                            value="{{ old('discount', isset($installmentSale) ? $installmentSale->discount : '') }}" />
                                        @error('discount')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Number of Installments -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label" for="number_of_installment">{{ __('Number of Installment') }} {!! requiredField() !!}</label>
                                        <input type="text" class="form-control number-input @error('number_of_installment') is-invalid @enderror" 
                                            name="number_of_installment" id="number_of_installment" min="1"
                                            placeholder="{{ __('Number of Installment') }}"
                                            value="{{ old('number_of_installment', isset($installmentSale) ? $installmentSale->number_of_installment : '') }}" />
                                        <div class="error-msg number_of_installment_err_msg_contnr">
                                            <p id="number_of_installment_err_msg"></p>
                                        </div>
                                        @error('number_of_installment')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Interest Percentage -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label" for="percentage_of_interest">{{ __('Percentage of Interest') }} {!! requiredField() !!}</label>
                                        <input type="text" class="form-control number-input change_data @error('percentage_of_interest') is-invalid @enderror" 
                                            name="percentage_of_interest" id="percentage_of_interest" min="0" step="0.01"
                                            placeholder="{{ __('Percentage of Interest') }}"
                                            value="{{ old('percentage_of_interest', isset($installmentSale) ? $installmentSale->percentage_of_interest : '0') }}" />
                                        <div class="error-msg percentage_of_interest_err_msg_contnr">
                                            <p id="percentage_of_interest_err_msg"></p>
                                        </div>
                                        @error('percentage_of_interest')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Shipping/Other -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label" for="shipping_other">{{ __('Shipping Other') }}</label>
                                        <input type="text" class="form-control change_data integerchk @error('shipping_other') is-invalid @enderror" 
                                            name="shipping_other" id="shipping_other" placeholder="{{ __('Shipping Other') }}"
                                            value="{{ old('shipping_other', isset($installmentSale) ? $installmentSale->shipping_other : '0') }}" />
                                        @error('shipping_other')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Total -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label" for="total">{{ __('Total') }} {!! requiredField() !!}</label>
                                        <input type="text" class="form-control @error('total') is-invalid @enderror" 
                                            name="total" id="total" readonly
                                            value="{{ old('total', isset($installmentSale) ? $installmentSale->total : '') }}" />
                                        <div class="error-msg total_err_msg_contnr">
                                            <p id="total_err_msg"></p>
                                        </div>
                                        @error('total')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Down Payment -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label" for="down_payment_cal">{{ __('Down Payment') }}</label>
                                        <input type="text" class="form-control change_data integerchk @error('down_payment') is-invalid @enderror" 
                                            name="down_payment" id="down_payment_cal" placeholder="{{ __('Down Payment') }}"
                                            value="{{ old('down_payment', isset($installmentSale) ? $installmentSale->down_payment : '') }}" />
                                        <div class="error-msg down_payment_err_msg_contnr">
                                            <p id="down_payment_err_msg"></p>
                                        </div>
                                        @error('down_payment')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Remaining -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label" for="remaining">{{ __('Remaining') }} {!! requiredField() !!}</label>
                                        <input type="text" class="form-control @error('remaining') is-invalid @enderror" 
                                            name="remaining" id="remaining" readonly
                                            value="{{ old('remaining', isset($installmentSale) ? $installmentSale->remaining : '') }}" />
                                        <div class="error-msg remaining_err_msg_contnr">
                                            <p id="remaining_err_msg"></p>
                                        </div>
                                        @error('remaining')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Payment Account -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label" for="payment_method_id">{{ __('Down Payment Account') }} {!! requiredField() !!}</label>
                                        <select class="form-select select2 @error('payment_method_id') is-invalid @enderror" 
                                            name="payment_method_id" id="payment_method_id">
                                            <option value="">{{ __('Select') }}</option>
                                            @foreach($payment_methods as $method)
                                                <option value="{{ $method->id }}" 
                                                    data-type="{{ $method->account_type }}"
                                                    {{ old('payment_method_id', isset($installmentSale) ? '' : '') == $method->id ? 'selected' : '' }}>
                                                    {{ $method->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <input type="hidden" name="account_type" id="account_type">
                                        <div class="error-msg payment_method_id_err_msg_contnr">
                                            <p id="payment_method_id_err_msg"></p>
                                        </div>
                                        @error('payment_method_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div id="show_account_type" class="mt-3"></div>
                                    </div>

                                    <!-- Installment Duration -->
                                    <div class="col-md-6 mb-4">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <label class="form-label" for="installment_type">{{ __('Installment Duration') }} ({{ __('Days') }}) {!! requiredField() !!}</label>
                                            <i class="ti tabler-info-circle" data-bs-toggle="tooltip" title="{{ __('Enter number of days between installments. E.g., 7 for weekly, 30 for monthly') }}"></i>
                                        </div>
                                        <div class="d-flex">
                                            <input type="text" class="form-control number-input me-2 @error('installment_type') is-invalid @enderror" 
                                                name="installment_type" id="installment_type" min="1"
                                                placeholder="{{ __('Installment Duration') }}"
                                                value="{{ old('installment_type', isset($installmentSale) ? $installmentSale->installment_type : '') }}" />
                                            <button type="button" class="btn btn-primary next_button" data-bs-toggle="tooltip" title="{{ __('Click to generate installments') }}">
                                                {{ __('Generate') }}
                                                <i class="ti tabler-arrow-right ms-1"></i>
                                            </button>
                                        </div>
                                        <div class="error-msg installment_duration_err_msg_contnr">
                                            <p id="installment_duration_err_msg"></p>
                                        </div>
                                        @error('installment_type')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column - Installments Table -->
                            <div class="col-md-12 col-lg-6">
                                <h5 class="inst-heading">{{ __('Installments') }}</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center">{{ __('SN') }}</th>
                                                <th>{{ __('Amount') }}</th>
                                                <th>{{ __('Payment Date') }}</th>
                                                <th class="text-center">{{ __('Action') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="show_tb_data">
                                            @php
                                                $total_amount = 0;
                                            @endphp
                                            @if(isset($installmentSale) && $installmentSale->installmentDetails->count() > 0)
                                                @foreach($installmentSale->installmentDetails as $key => $detail)
                                                    <tr>
                                                        <td class="text-center align-middle">{{ $key + 1 }}</td>
                                                        <td>
                                                            <input type="hidden" name="paid_status[]" value="Unpaid">
                                                            <input type="text" class="form-control amount_of_payment integerchk1" 
                                                                value="{{ ($detail->amount_of_payment) }}" onfocus="select()" name="amount_of_payment[]">
                                                                @php
                                                                    $total_amount += $detail->amount_of_payment;
                                                                @endphp
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control datePicker" 
                                                                value="{{ formatDate($detail->payment_date) }}" readonly name="payment_date[]">
                                                        </td>
                                                        <td class="text-center">
                                                            <button type="button" class="btn text-danger delete_row">
                                                                <i class="icon-base ti tabler-trash"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            @else   
                                                <tr>
                                                    <td colspan="4">
                                                        <div class="d-flex align-items-center justify-content-center empty-data-icon">
                                                        <i class="ti tabler-mood-empty"></i>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endif
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-light">
                                                <th class="text-end">{{ __('Total') }}</th>
                                                <th><span class="total_amount">{{ formatAmount($total_amount) }}</span></th>
                                                <th></th>
                                                <th></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary check_required_field">
                                <i class="ti tabler-device-floppy me-1"></i>
                                {{ isset($installmentSale) ? __('Update') : __('Submit') }}
                            </button>
                            
                            <a href="{{ route('installment-sale.index') }}" class="btn btn-primary">
                                <i class="ti tabler-arrow-back-up me-1"></i>
                                {{ __('Back') }}
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- IMEI/Serial Selection Modal -->
<div class="modal fade" id="imei_serial_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Select') }} <span class="imei_serial_label">{{ __('IMEI_Serial') }}</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" class="modal_hidden_type" value="">
                <div class="mb-3">
                    <label class="form-label imei_serial_label">{{ __('IMEI_Serial') }} {{ __('Number') }}</label>
                    <select class="form-select select2" id="IMEI_Serial" style="width: 100%;">
                        <option value="">{{ __('Select') }}</option>
                    </select>
                    <div class="error-msg imei_serial_err_msg_contnr mt-2">
                        <p id="imei_serial_err_msg" class="text-danger mb-0"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                <button type="button" class="btn btn-primary" id="imei_serial_submit">{{ __('Submit') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden inputs for JS translations -->
<input type="hidden" id="op_precision" value="{{ $precision ?? 2 }}">
<input type="hidden" id="The_customer_field_is_required" value="{{ __('The customer field is required.') }}">
<input type="hidden" id="The_items_field_is_required" value="{{ __('The product field is required.') }}">
<input type="hidden" id="The_price_field_is_required" value="{{ __('The price field is required.') }}">
<input type="hidden" id="The_number_of_installment_required" value="{{ __('The number of installments field is required.') }}">
<input type="hidden" id="The_total_field_is_required" value="{{ __('The total field is required.') }}">
<input type="hidden" id="The_installment_duration_field_is_required" value="{{ __('The installment duration field is required.') }}">
<input type="hidden" id="The_down_payment_field_is_required" value="{{ __('The down payment field is required.') }}">
<input type="hidden" id="The_remaining_field_is_required" value="{{ __('The remaining field is required.') }}">
<input type="hidden" id="account_field_required" value="{{ __('Payment account is required when down payment is provided.') }}">
<input type="hidden" id="total_amount_equal_check_sale_total" value="{{ __('Total installment amounts must equal the remaining amount.') }}">
<input type="hidden" id="check_issue_date" value="{{ __('Check_Issue_Date') }}">
<input type="hidden" id="check_no" value="{{ __('Check_No') }}">
<input type="hidden" id="check_expiry_date" value="{{ __('Check_Expiry_Date') }}">
<input type="hidden" id="mobile_no" value="{{ __('Mobile_No') }}">
<input type="hidden" id="transaction_no" value="{{ __('Transaction_No') }}">
<input type="hidden" id="card_holder_name" value="{{ __('Card_Holder_Name') }}">
<input type="hidden" id="card_holding_number" value="{{ __('Card_Holding_Number') }}">
<input type="hidden" id="paypal_email" value="{{ __('PayPal_Email') }}">
<input type="hidden" id="stripe_email" value="{{ __('Stripe_Email') }}">
<input type="hidden" id="note" value="{{ __('Note') }}">

@endsection
@push('page-js')
@routes
<script src="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{ asset('backend_assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
<script src="{{ asset('backend_assets/js/pages_list_js/installment_sale_form.js')}}"></script>
@endpush

