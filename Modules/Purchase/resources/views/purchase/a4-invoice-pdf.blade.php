<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Purchase No') }}: {{ $purchase->reference_no }}</title>
    <link rel="stylesheet" href="{{ asset('backend_assets/css/pdf-common.css') }}" />
</head>
<body>
    <div id="wrapper" class="m-auto b-r-5 p-30">
        <table>
            <tr>
                <td class="w-50">
                    <h3 class="pb-7">{{ session('company.business_name', '') }}</h3>
                    @if($purchase->outlet)
                        <p class="pb-7 f-w-900 rgb-71">{{ $purchase->outlet->outlet_name ?? '' }}</p>
                        <p class="pb-7 f-w-900 rgb-71">{{ $purchase->outlet->address ?? '' }}</p>
                        <p class="pb-7 f-w-900 rgb-71">{{ __('Email') }}: {{ $purchase->outlet->email ?? '' }}</p>
                        <p class="pb-7 f-w-900 rgb-71">{{ __('Phone') }}: {{ $purchase->outlet->phone ?? '' }}</p>
                    @endif
                </td>
                <td class="w-50 text-right">
                    @php
                        $invoice_logo = session('company.invoice_logo');
                    @endphp
                    @if($invoice_logo)
                        <img src="{{ asset('uploads/site_settings/' . $invoice_logo) }}" alt="Logo" height="50px">
                    @endif
                </td>
            </tr>
        </table>
        <div class="text-center py-10">
            <h2 class="invoice-heading">{{ __('Purchase') }}</h2>
        </div>
        
        <table>
            <tr>
                <td valign="top">
                    <h3 class="pb-7">{{ __('Purchase') }} {{ __('Info') }}</h3>
                    @if($purchase->reference_no)
                    <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Reference No') }}:</span> {{ $purchase->reference_no }}</p>
                    @endif
                    @if($purchase->date)
                    <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Date') }}:</span> {{ formatDate($purchase->date) }}</p>
                    @endif
                    @if($purchase->supplier_invoice_no)
                    <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Supplier Invoice No') }}:</span> {{ $purchase->supplier_invoice_no }}</p>
                    @endif
                </td>
                <td valign="top" class="text-right">
                    <h3 class="pb-7">{{ __('Supplier') }} {{ __('Info') }}</h3>
                    @if($purchase->supplier)
                        @if($purchase->supplier->name)
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Name') }}:</span> {{ $purchase->supplier->name }}</p>
                        @endif
                        @if($purchase->supplier->phone)
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Phone') }}:</span> {{ $purchase->supplier->phone }}</p>
                        @endif
                        @if($purchase->supplier->address)
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Address') }}:</span> {{ $purchase->supplier->address }}</p>
                        @endif
                    @endif
                </td>
            </tr>
        </table>

        <table class="w-100 mt-20">
            <thead class="br-3 bg-00c53">
                <tr>
                    <th class="w-5 text-center">{{ __('SN') }}</th>
                    <th class="w-30 text-start">{{ __('Item') }}-{{ __('Code') }}</th>
                    <th class="w-15 text-center">{{ __('IMEI/Serial') }}</th>
                    <th class="w-15 text-center">{{ __('Qty') }}</th>
                    <th class="w-15 text-center">{{ __('Unit Price') }}</th>
                    <th class="w-20 text-right pr-5">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $i = 0;
                @endphp
                @if($purchase->purchaseDetails && $purchase->purchaseDetails->count() > 0)
                    @foreach($purchase->purchaseDetails as $detail)
                        @php
                            $i++;
                        @endphp
                        <tr>
                            <td class="text-center">
                                <span>{{ $i }}</span>
                            </td>
                            <td class="text-start">
                                <span>
                                    @if($detail->item)
                                        @if($detail->item->parent_id && $detail->item->parent)
                                            {{ $detail->item->parent->name }} - {{ $detail->item->name }}
                                        @else
                                            {{ $detail->item->name }}
                                        @endif
                                        @if($detail->item->code)
                                            ({{ $detail->item->code }})
                                        @endif
                                    @endif
                                </span>
                            </td>
                            <td class="text-center">
                                @if($detail->expiry_imei_serial)
                                    <small class="text-muted">
                                        @if($detail->item && in_array($detail->item->type, ['IMEI_Product', 'Serial_Product']))
                                            {{ $detail->item->type === 'IMEI_Product' ? 'IMEI' : 'Serial' }}: {{ $detail->expiry_imei_serial }}
                                        @elseif($detail->item && $detail->item->type === 'Medicine_Product')
                                            {{ $detail->expiry_imei_serial }}
                                        @endif
                                    </small>
                                @endif
                            </td>
                            <td class="text-center">{{ $detail->quantity_amount ?? $detail->quantity }}</td>
                            <td class="text-center">{{ formatAmount($detail->unit_price) }}</td>
                            <td class="text-right">{{ formatAmount($detail->total) }}</td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>

        <table class="w-100 mt-20">
            <tr>
                <td class="w-50" valign="top">
                    @if($purchase->note)
                    <h4 class="d-block pb-10">{{ __('Note') }}</h4>
                    <div class="w-100 bg-240 m-h-120px-m-h-220px p-15 b-1s-240">
                        <p>
                            {{ $purchase->note }}
                        </p>
                    </div>
                    @endif
                </td>
                <td class="w-50" valign="top">
                    <table class="w-100">
                        <tr>
                            <td class="text-right">
                                @php
                                    $discount = $purchase->discount ?? '0';
                                    $discount_ac = '';
                                    if(strpos($discount, '%') !== false) {
                                        $discount_ac = $discount;
                                    } else {
                                        $discount_ac = formatAmount($discount);
                                    }
                                @endphp
                                <p class="f-w-600 d-inline">{{ __('Discount') }}: </p>
                            </td>
                            <td class="text-right">
                                <p class="d-inline">{{ $discount_ac }}</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <td class="text-right pt-10">
                                <p class="f-w-600 d-inline">{{ __('Paid') }}: </p>
                            </td>
                            <td class="text-right">
                               <p class="d-inline">{{ formatAmount($purchase->paid) }}</p>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-right pt-10">
                                <p class="f-w-600 d-inline">{{ __('Due') }}: </p>
                            </td>
                            <td class="text-right pt-10">
                                <p class="d-inline">{{ formatAmount($purchase->due_amount) }}</p>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-right pt-10 mt-10 p-10 bg-00c53 br-3">
                                <p class="f-w-600 d-inline">{{ __('Grand Total') }}: </p>
                            </td>
                            <td class="text-right pt-10 mt-10 p-10 bg-00c53 br-3">
                                <p class="d-inline">{{ formatAmount($purchase->grand_total) }}</p>
                            </td>
                        </tr>

                        @if($purchase->purchasePayments && $purchase->purchasePayments->count() > 0)
                        @foreach($purchase->purchasePayments as $payment)
                        <tr>
                            <td class="text-right pt-5">
                                <p class="f-w-500 d-inline">{{ $payment->paymentMethod ? $payment->paymentMethod->name : __('N/A') }}: </p>
                            </td>
                            <td class="text-right">
                                <p class="d-inline">{{ formatAmount($payment->amount) }}</p>
                            </td>
                        </tr>
                        @endforeach
                        @endif
                    </table>
                </td>
            </tr>
        </table>

        <div class="text-right mt-50">
            <div>
                <p class="color-71 d-inline b-t-1p-e4e5ea pt-10">{{ __('Authorized Signature') }}</p>
            </div>
        </div>
    </div>
</body>
</html>

