@php
    $isPosContext = isset($isPosContext) ? $isPosContext : false;
    $saleReturn = $saleReturn ?? null;
    $customers = $customers ?? collect();
    $payment_methods = $payment_methods ?? collect();
    $reference_no = $reference_no ?? '';
@endphp

<form action="{{ $saleReturn ? route('sale-return.update', $saleReturn->encrypted_id) : route('sale-return.store') }}" method="POST" id="saleReturnForm" enctype="multipart/form-data" data-is-pos="{{ $isPosContext ? 'true' : 'false' }}">
    @csrf
    @if($saleReturn)
        @method('PUT')
    @endif

    <div class="card-body">
        <div class="row">
            <div class="col-12 col-md-6 col-lg-4">
                <div class="mb-5 validate_wrapper">
                    <label class="form-label" for="reference_no">{{ __('Reference No') }} {!! requiredField() !!}</label>
                    <input type="text" class="form-control" placeholder="{{ __('Reference No') }}" name="reference_no" id="reference_no" value="{{ $saleReturn ? $saleReturn->reference_no : $reference_no }}" readonly />
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-4">
                <div class="mb-5 validate_wrapper">
                    <label class="form-label" for="date">{{ __('Date') }} {!! requiredField() !!}</label>
                    <input type="text" class="form-control datePicker" placeholder="Date" name="date" id="date" value="{{ $saleReturn ? $saleReturn->date : date('Y-m-d') }}" />
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-4">
                <div class="mb-5 validate_wrapper">
                    <label class="form-label" for="customer_id">
                        {{ __('Customer') }} {!! requiredField() !!}
                    </label>
                    <select id="customer_id" name="customer_id" class="select2 form-select" data-placeholder="{{ __('Select Customer') }}">
                        <option value="">{{ __('Select Customer') }}</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" {{ $saleReturn && $saleReturn->customer_id == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-4">
                <div class="mb-5 validate_wrapper">
                    <label class="form-label" for="sale_id">
                        {{ __('Sale Invoice') }} {!! requiredField() !!}
                    </label>
                    <select id="sale_id" name="sale_id" class="select2 form-select" data-placeholder="{{ __('Select Sale Invoice') }}">
                        <option value="">{{ __('Select Sale Invoice') }}</option>
                        @if($saleReturn && $saleReturn->sale)
                            <option value="{{ $saleReturn->sale_id }}" selected>
                                {{ $saleReturn->sale->sale_no }} - {{ formatDate($saleReturn->sale->sale_date) }}
                            </option>
                        @endif
                    </select>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-4">
                <div class="mb-5 validate_wrapper">
                    <label class="form-label" for="sale_item_id">{{ __('Sale Items') }}</label>
                    <select id="sale_item_id" class="form-select select2" data-placeholder="{{ __('Select Sale Item') }}">
                        <option value="">{{ __('Select Sale Item') }}</option>
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
                            <th class="w-20">{{ __('Item - Code - Brand') }}</th>
                            <th class="w-15">{{ __('IMEI/Serial/Medicine') }}</th>
                            <th class="w-10">{{ __('Sale Qty') }}</th>
                            <th class="w-10">{{ __('Return Qty') }}</th>
                            <th class="w-10">{{ __('Unit Price') }}</th>
                            <th class="w-10">{{ __('Return Price') }}</th>
                            <th class="w-10">{{ __('Total') }}</th>
                            <th class="w-5">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody id="saleReturnItems">
                        @if($saleReturn)
                            @foreach($saleReturn->saleReturnDetails as $index => $detail)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    <input type="hidden" class="item-id" name="items[]" value="{{ $detail->item_id }}">
                                    <input type="hidden" class="item-type" name="item_types[]" value="{{ $detail->item_type ?? 'General_Product' }}">
                                    <input type="hidden" class="sale-quantity" name="sale_quantities[]" value="{{ $detail->sale_quantity_amount }}">
                                    <span class="item-name">
                                        @if($detail->item)
                                            @if($detail->item->parent_id && $detail->item->parent)
                                                {{ $detail->item->parent->name }} - {{ $detail->item->name }}
                                            @else
                                                {{ $detail->item->name }}
                                            @endif
                                            @if($detail->item->code)
                                                ({{ $detail->item->code }})
                                            @endif
                                        @else
                                            N/A
                                        @endif
                                    </span>
                                </td>
                                <td class="imei-serial-cell">
                                    <div class="imei-serial-container">
                                        <input type="text" class="form-control imei-serial" name="imei_serial[]" placeholder="IMEI/Serial" readonly value="{{ $detail->expiry_imei_serial ?? '' }}" style="{{ in_array($detail->item_type ?? '', ['IMEI_Product', 'Serial_Product', 'Medicine_Product']) ? '' : 'display:none;' }}">
                                    </div>
                                </td>
                                <td>
                                    <span class="sale-quantity-display">{{ $detail->sale_quantity_amount }}</span>
                                </td>
                                <td>
                                    <input type="text" class="form-control number-input return-quantity" name="return_quantities[]" value="{{ $detail->return_quantity_amount }}">
                                </td>
                                <td>
                                    <span class="unit-price-sale">{{ number_format($detail->unit_price_in_sale, 2) }}</span>
                                    <input type="hidden" class="unit-price-sale-hidden" name="unit_prices_sale[]" value="{{ $detail->unit_price_in_sale }}">
                                </td>
                                <td>
                                    <input type="text" class="form-control number-input unit-price-return" name="unit_prices_return[]" value="{{ $detail->unit_price_in_return }}">
                                </td>
                                <td>
                                    <span class="total-display">{{ number_format($detail->return_quantity_amount * $detail->unit_price_in_return, 2) }}</span>
                                    <input type="hidden" class="total-hidden" name="total[]" value="{{ ($detail->return_quantity_amount * $detail->unit_price_in_return) }}">
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

    <!-- Payment Summary Section -->
    <div class="card-body">
        <div class="row justify-content-end">
            <div class="col-12 col-md-6 col-lg-4">
                <h5 class="mb-4" id="totalItemCount">{{ __('Total Item') }} {{ $saleReturn ? $saleReturn->saleReturnDetails->count() : 0 }} (0)</h5>
            </div>
            <div class="clear-fix"></div>
            
            <!-- Grand Total Field -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="mb-5">
                    <label class="form-label" for="grandTotal">{{ __('Grand Total') }}</label>
                    <input type="text" placeholder="0.00" class="form-control" id="grandTotal" name="grandTotal" readonly value="{{ $saleReturn ? number_format($saleReturn->total_return_amount, 2) : '' }}">
                </div>
            </div>
            <div class="clear-fix"></div>
            
            <!-- Payment Method Field -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="mb-5 validate_wrapper">
                    <label class="form-label" for="payment_method_id">{{ __('Payment Method') }} {!! requiredField() !!}</label>
                    <select id="payment_method_id" name="payment_method_id" class="select2 form-select" data-placeholder="{{ __('Select Payment Method') }}">
                        <option value="">{{ __('Select Payment Method') }}</option>
                        @foreach($payment_methods ?? [] as $method)
                            <option value="{{ $method->id }}" {{ $saleReturn && $saleReturn->payment_method_id == $method->id ? 'selected' : '' }}>{{ $method->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="clear-fix"></div>
            
            <!-- Paid Field -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="mb-5 validate_wrapper">
                    <label class="form-label" for="paid">{{ __('Paid Amount') }} {!! requiredField() !!}</label>
                    <input type="text" placeholder="0.00" class="form-control" id="paid" name="paid" readonly value="{{ $saleReturn ? number_format($saleReturn->paid, 2) : '' }}">
                </div>
            </div>
            <div class="clear-fix"></div>
            
            <!-- Due Field -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="mb-5">
                    <label class="form-label" for="due">{{ __('Due Amount') }}</label>
                    <input type="text" placeholder="0.00" class="form-control" id="due" name="due" readonly value="{{ $saleReturn ? number_format($saleReturn->due, 2) : '' }}">
                </div>
            </div>
        </div>
    </div>

    <!-- Submit Button -->
    <div class="card-footer">
        <button type="submit" name="submit" value="submit" class="btn btn-primary add_item_submit">
            {!! submitIconWithText('') !!}
        </button>
        @if($saleReturn && !$isPosContext)
            <a href="{{ route('sale-return.index') }}" class="btn btn-danger">
                {!! closeIconWithText() !!}
            </a>
        @endif
    </div>
</form>
