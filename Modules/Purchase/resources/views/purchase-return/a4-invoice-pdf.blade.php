<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Purchase Return No') }}: {{ $purchaseReturn->reference_no }}</title>
    <link rel="stylesheet" href="{{ asset('backend_assets/css/pdf-common.css') }}" />
</head>
<body>
    <div id="wrapper" class="m-auto b-r-5 p-30">
        <table>
            <tr>
                <td class="w-50">
                    <h3 class="pb-7">{{ session('company.business_name', '') }}</h3>
                    @if($purchaseReturn->outlet)
                        <p class="pb-7 f-w-900 rgb-71">{{ $purchaseReturn->outlet->outlet_name ?? '' }}</p>
                        <p class="pb-7 f-w-900 rgb-71">{{ $purchaseReturn->outlet->address ?? '' }}</p>
                        <p class="pb-7 f-w-900 rgb-71">{{ __('Email') }}: {{ $purchaseReturn->outlet->email ?? '' }}</p>
                        <p class="pb-7 f-w-900 rgb-71">{{ __('Phone') }}: {{ $purchaseReturn->outlet->phone ?? '' }}</p>
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
            <h2 class="invoice-heading">{{ __('Purchase Return') }}</h2>
        </div>
        
        <table>
            <tr>
                <td valign="top">
                    <h3 class="pb-7">{{ __('Purchase Return') }} {{ __('Info') }}</h3>
                    @if($purchaseReturn->reference_no)
                    <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Reference No') }}:</span> {{ $purchaseReturn->reference_no }}</p>
                    @endif
                    @if($purchaseReturn->date)
                    <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Date') }}:</span> {{ formatDate($purchaseReturn->date) }}</p>
                    @endif
                    @if($purchaseReturn->purchase_date)
                    <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Purchase Date') }}:</span> {{ formatDate($purchaseReturn->purchase_date) }}</p>
                    @endif
                    @if($purchaseReturn->invoice_no)
                    <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Invoice No') }}:</span> {{ $purchaseReturn->invoice_no }}</p>
                    @endif
                </td>
                <td valign="top" class="text-right">
                    <h3 class="pb-7">{{ __('Supplier') }} {{ __('Info') }}</h3>
                    @if($purchaseReturn->supplier)
                        @if($purchaseReturn->supplier->name)
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Name') }}:</span> {{ $purchaseReturn->supplier->name }}</p>
                        @endif
                        @if($purchaseReturn->supplier->phone)
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Phone') }}:</span> {{ $purchaseReturn->supplier->phone }}</p>
                        @endif
                        @if($purchaseReturn->supplier->address)
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Address') }}:</span> {{ $purchaseReturn->supplier->address }}</p>
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
                @if($purchaseReturn->purchaseReturnDetails && $purchaseReturn->purchaseReturnDetails->count() > 0)
                    @foreach($purchaseReturn->purchaseReturnDetails as $detail)
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
                            <td class="text-center">{{ $detail->return_quantity_amount }}</td>
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
                    @if($purchaseReturn->note)
                    <h4 class="d-block pb-10">{{ __('Note') }}</h4>
                    <div class="w-100 bg-240 m-h-120px-m-h-220px p-15 b-1s-240">
                        <p>
                            {{ $purchaseReturn->note }}
                        </p>
                    </div>
                    @endif
                </td>
                <td class="w-50" valign="top">
                    <table class="w-100">
                        <tr>
                            <td class="text-right pt-10">
                                <p class="f-w-600 d-inline">{{ __('Status') }}: </p>
                            </td>
                            <td class="text-right">
                                <p class="d-inline">
                                    @php
                                        $status = $purchaseReturn->return_status ?? 'draft';
                                        if ($status === 'draft') {
                                            $statusClass = 'secondary';
                                            $statusText = __('Draft');
                                        } elseif ($status === 'taken_by_sup_pro_not_returned') {
                                            $statusClass = 'warning';
                                            $statusText = __('Taken By Supplier Product Not Returned');
                                        } elseif ($status === 'taken_by_sup_money_returned') {
                                            $statusClass = 'success';
                                            $statusText = __('Taken By Supplier Money Returned');
                                        } elseif ($status === 'taken_by_sup_pro_returned') {
                                            $statusClass = 'info';
                                            $statusText = __('Taken By Supplier Product Returned');
                                        } else {
                                            $statusClass = 'secondary';
                                            $statusText = ucfirst(str_replace('_', ' ', $status));
                                        }
                                    @endphp
                                    <span class="badge bg-label-{{ $statusClass }}">{{ $statusText }}</span>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-right pt-10 mt-10 p-10 bg-00c53 br-3">
                                <p class="f-w-600 d-inline">{{ __('Grand Total') }}: </p>
                            </td>
                            <td class="text-right pt-10 mt-10 p-10 bg-00c53 br-3">
                                <p class="d-inline">{{ formatAmount($purchaseReturn->total_return_amount) }}</p>
                            </td>
                        </tr>
                        @if($purchaseReturn->return_status === 'taken_by_sup_money_returned' && $purchaseReturn->total_return_amount)
                        <tr>
                            <td class="text-right pt-10">
                                <p class="f-w-600 d-inline">{{ __('Amount') }}: </p>
                            </td>
                            <td class="text-right pt-10">
                                <p class="d-inline">{{ formatAmount($purchaseReturn->total_return_amount) }}</p>
                            </td>
                        </tr>
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
