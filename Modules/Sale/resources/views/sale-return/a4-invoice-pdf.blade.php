<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Sale Return No') }}: {{ $saleReturn->reference_no }}</title>
    <link rel="stylesheet" href="{{ asset('backend_assets/css/pdf-common.css') }}" />
</head>
<body>
    <div id="wrapper" class="m-auto b-r-5 p-30">
        <table>
            <tr>
                <td class="w-50">
                    <h3 class="pb-7">{{ session('company.business_name', '') }}</h3>
                    @if($saleReturn->outlet)
                        <p class="pb-7 f-w-900 rgb-71">{{ $saleReturn->outlet->outlet_name ?? '' }}</p>
                        <p class="pb-7 f-w-900 rgb-71">{{ $saleReturn->outlet->address ?? '' }}</p>
                        <p class="pb-7 f-w-900 rgb-71">{{ __('Email') }}: {{ $saleReturn->outlet->email ?? '' }}</p>
                        <p class="pb-7 f-w-900 rgb-71">{{ __('Phone') }}: {{ $saleReturn->outlet->phone ?? '' }}</p>
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
            <h2 class="invoice-heading">{{ __('Sale Return') }}</h2>
        </div>
        
        <table>
            <tr>
                <td valign="top">
                    <h3 class="pb-7">{{ __('Sale Return') }} {{ __('Info') }}</h3>
                    @if($saleReturn->reference_no)
                    <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Reference No') }}:</span> {{ $saleReturn->reference_no }}</p>
                    @endif
                    @if($saleReturn->date)
                    <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Date') }}:</span> {{ formatDate($saleReturn->date) }}</p>
                    @endif
                    @if($saleReturn->sale)
                    <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Sale Invoice') }}:</span> {{ $saleReturn->sale->sale_no ?? '' }}</p>
                    @endif
                </td>
                <td valign="top" class="text-right">
                    <h3 class="pb-7">{{ __('Customer') }} {{ __('Info') }}</h3>
                    @if($saleReturn->customer)
                        @if($saleReturn->customer->name)
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Name') }}:</span> {{ $saleReturn->customer->name }}</p>
                        @endif
                        @if($saleReturn->customer->phone)
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Phone') }}:</span> {{ $saleReturn->customer->phone }}</p>
                        @endif
                        @if($saleReturn->customer->address)
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Address') }}:</span> {{ $saleReturn->customer->address }}</p>
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
                    <th class="w-10 text-center">{{ __('Sale Qty') }}</th>
                    <th class="w-10 text-center">{{ __('Return Qty') }}</th>
                    <th class="w-15 text-center">{{ __('Return Price') }}</th>
                    <th class="w-15 text-right pr-5">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $i = 0;
                @endphp
                @if($saleReturn->saleReturnDetails && $saleReturn->saleReturnDetails->count() > 0)
                    @foreach($saleReturn->saleReturnDetails as $detail)
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
                            <td class="text-center">{{ $detail->sale_quantity_amount }}</td>
                            <td class="text-center">{{ $detail->return_quantity_amount }}</td>
                            <td class="text-center">{{ formatAmount($detail->unit_price_in_return) }}</td>
                            <td class="text-right">{{ formatAmount($detail->return_quantity_amount * $detail->unit_price_in_return) }}</td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>

        <table class="w-100 mt-20">
            <tr>
                <td class="w-50" valign="top">
                    @if($saleReturn->note)
                    <h4 class="d-block pb-10">{{ __('Note') }}</h4>
                    <div class="w-100 bg-240 m-h-120px-m-h-220px p-15 b-1s-240">
                        <p>
                            {{ $saleReturn->note }}
                        </p>
                    </div>
                    @endif
                </td>
                <td class="w-50" valign="top">
                    <table class="w-100">
                        <tr>
                            <td class="text-right pt-10">
                                <p class="f-w-600 d-inline">{{ __('Paid') }}: </p>
                            </td>
                            <td class="text-right">
                               <p class="d-inline">{{ formatAmount($saleReturn->paid) }}</p>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-right pt-10">
                                <p class="f-w-600 d-inline">{{ __('Due') }}: </p>
                            </td>
                            <td class="text-right pt-10">
                                <p class="d-inline">{{ formatAmount($saleReturn->due) }}</p>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-right pt-10 mt-10 p-10 bg-00c53 br-3">
                                <p class="f-w-600 d-inline">{{ __('Grand Total') }}: </p>
                            </td>
                            <td class="text-right pt-10 mt-10 p-10 bg-00c53 br-3">
                                <p class="d-inline">{{ formatAmount($saleReturn->total_return_amount) }}</p>
                            </td>
                        </tr>
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
