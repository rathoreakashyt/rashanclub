<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Warranty Invoice') }}: {{ $sale->sale_no }}</title>
    <link rel="stylesheet" href="{{ asset('backend_assets/css/print-invoice-a4.css') }}" />
    <link rel="stylesheet" href="{{ asset('backend_assets/css/inv-common.css') }}" />
</head>
<body>
    <div id="wrapper" class="m-auto border-2s-e4e5ea br-5 p-30">
        
        <div class="d-flex justify-content-between">
            <div>
                <h3 class="pb-7 shop-name">{{ session('company.business_name', '') }}</h3>

                @if($sale->outlet)
                    <p class="pb-7">{{ $sale->outlet->outlet_name ?? '' }}</p>
                    <p class="pb-7 f-w-500 color-71">{{ $sale->outlet->address ?? '' }}</p>
                    <p class="pb-7 f-w-500 color-71">{{ __('Email') }}: {{ $sale->outlet->email ?? '' }}</p>
                    <p class="pb-7 f-w-500 color-71">{{ __('Phone') }}: {{ $sale->outlet->phone ?? '' }}</p>
                @endif
            </div>
            <div class="d-flex align-items-center">
                <div class="m-auto">
                    @php
                        $invoice_logo = session('company.invoice_logo');
                    @endphp
                    @if($invoice_logo)
                        <img src="{{ asset('uploads/site_settings/' . $invoice_logo) }}" alt="Logo" height="50px">
                    @endif
                </div>
            </div>
        </div>

        <div class="text-center py-10">
            <h2 class="invoice-heading">{{ __('Warranty Invoice') }}</h2>
        </div>

        <div class="d-grid g-template-c-48-48 g-gap-2">
            <div class="d-flex justify-content-between">
                <div>
                    <h3 class="pb-7 common-heading">{{ __('Sale') }} {{ __('Info') }}</h3>
                    @if($sale->sale_no)
                    <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Sale No') }}:</span> {{ $sale->sale_no }}</p>
                    @endif
                    @if($sale->sale_date)
                    <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Sale Date') }}:</span> {{ formatDate($sale->sale_date) }}</p>
                    @endif
                    @if($sale->date_time)
                    <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Date Time') }}:</span> {{ formatDateTime($sale->date_time) }}</p>
                    @endif
                </div>
            </div>
            <div class="d-flex justify-content-end">
                <div>
                    <h3 class="pb-7 common-heading">{{ __('Customer') }} {{ __('Info') }}</h3>
                    @if($sale->customer)
                        @if($sale->customer->name)
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Name') }}:</span> {{ $sale->customer->name }}</p>
                        @endif
                        @if($sale->customer->phone)
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Phone') }}:</span> {{ $sale->customer->phone }}</p>
                        @endif
                        @if($sale->customer->address)
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Address') }}:</span> {{ $sale->customer->address }}</p>
                        @endif
                    @else
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Name') }}:</span> Walk-in Customer</p>
                    @endif
                    @if($sale->employee)
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Employee') }}:</span> {{ $sale->employee->name }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div>
            <table class="table w-100 mt-20">
                <thead class="br-3 bg-00c53">
                    <tr>
                        <th class="w-5 text-center">{{ __('SN') }}</th>
                        <th class="w-25 text-start">{{ __('Item') }}-{{ __('Code') }}</th>
                        <th class="w-15 text-center">{{ __('IMEI/Serial') }}</th>
                        <th class="w-10 text-center">{{ __('Qty') }}</th>
                        <th class="w-15 text-center">{{ __('Unit Price') }}</th>
                        <th class="w-15 text-center">{{ __('Warranty') }}</th>
                        <th class="w-15 text-right pr-5">{{ __('Total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $i = 0;
                    @endphp
                    @if($sale->saleDetails && $sale->saleDetails->count() > 0)
                        @foreach($sale->saleDetails as $detail)
                            @php
                                $i++;
                                $item = $detail->item;
                                
                                // Calculate warranty/guarantee periods
                                $warrantyInfo = 'N/A';
                                $guaranteeInfo = 'N/A';
                                
                                if ($item) {
                                    if ($item->warranty && $item->warranty_date) {
                                        $warrantyValue = (int)$item->warranty;
                                        $warrantyUnit = $item->warranty_date;
                                        $saleDate = $sale->sale_date;
                                        
                                        if ($saleDate) {
                                            $warrantyExpiry = clone $saleDate;
                                            switch ($warrantyUnit) {
                                                case 'day':
                                                    $warrantyExpiry->addDays($warrantyValue);
                                                    break;
                                                case 'month':
                                                    $warrantyExpiry->addMonths($warrantyValue);
                                                    break;
                                                case 'year':
                                                    $warrantyExpiry->addYears($warrantyValue);
                                                    break;
                                            }
                                            $warrantyInfo = $warrantyValue . ' ' . $warrantyUnit . '(s)<br><small>' . formatDate($saleDate) . ' - ' . formatDate($warrantyExpiry) . '</small>';
                                        }
                                    }
                                    
                                    if ($item->guarantee && $item->guarantee_date) {
                                        $guaranteeValue = (int)$item->guarantee;
                                        $guaranteeUnit = $item->guarantee_date;
                                        $saleDate = $sale->sale_date;
                                        
                                        if ($saleDate) {
                                            $guaranteeExpiry = clone $saleDate;
                                            switch ($guaranteeUnit) {
                                                case 'day':
                                                    $guaranteeExpiry->addDays($guaranteeValue);
                                                    break;
                                                case 'month':
                                                    $guaranteeExpiry->addMonths($guaranteeValue);
                                                    break;
                                                case 'year':
                                                    $guaranteeExpiry->addYears($guaranteeValue);
                                                    break;
                                            }
                                            $guaranteeInfo = $guaranteeValue . ' ' . $guaranteeUnit . '(s)<br><small>' . formatDate($saleDate) . ' - ' . formatDate($guaranteeExpiry) . '</small>';
                                        }
                                    }
                                }
                            @endphp
                            <tr>
                                <td class="text-center">
                                    <span>{{ $i }}</span>
                                </td>
                                <td class="text-start">
                                    <span>
                                        @if($item)
                                            @if($item->parent_id && $item->parent)
                                                {{ $item->parent->name }} - {{ $item->name }}
                                            @else
                                                {{ $item->name }}
                                            @endif
                                            @if($item->code)
                                                ({{ $item->code }})
                                            @endif
                                        @endif
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if($detail->expiry_imei_serial)
                                        <small class="text-muted">
                                            @if($item && in_array($item->type, ['IMEI_Product', 'Serial_Product']))
                                                {{ $item->type === 'IMEI_Product' ? 'IMEI' : 'Serial' }}: {{ $detail->expiry_imei_serial }}
                                            @elseif($item && $item->type === 'Medicine_Product')
                                                {{ $detail->expiry_imei_serial }}
                                            @endif
                                        </small>
                                    @endif
                                </td>
                                <td class="text-center">{{ $detail->qty }}</td>
                                <td class="text-center">{{ formatAmount($detail->menu_unit_price) }}</td>
                                <td class="text-center">
                                    <div>
                                        @if($warrantyInfo !== 'N/A')
                                            <strong>{{ __('Warranty') }}:</strong><br>
                                            {!! $warrantyInfo !!}
                                        @else
                                            {{ __('N/A') }}
                                        @endif
                                        @if($guaranteeInfo !== 'N/A')
                                            <br><br>
                                            <strong>{{ __('Guarantee') }}:</strong><br>
                                            {!! $guaranteeInfo !!}
                                        @endif
                                    </div>
                                </td>
                                <td class="text-right">{{ formatAmount($detail->qty * $detail->menu_price_with_discount) }}</td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>

        <div class="d-grid g-template-c-50-40 grid-gap-10 pt-20">
            <div>
                <div class="pt-10">
                    <h4 class="d-block pb-10">{{ __('Warranty & Guarantee Information') }}</h4>
                    <div class="w-100 bg-240 m-h-120px-m-h-220px p-15 b-1s-240">
                        <p class="mb-3">
                            <strong>{{ __('Note') }}:</strong> {{ __('This invoice serves as proof of purchase and warranty/guarantee coverage. Please keep this document safe for warranty claims.') }}
                        </p>
                        @if($sale->note)
                        <p class="mb-0">
                            <strong>{{ __('Additional Note') }}:</strong> {{ $sale->note }}
                        </p>
                        @endif
                    </div>
                </div>
            </div>
            <div>
                <div class="d-flex justify-content-between pt-10">
                    <p class="f-w-600">{{ __('Subtotal') }}</p>
                    <p>{{ formatAmount($sale->sub_total) }}</p>
                </div>
                @if($sale->total_discount_amount > 0)
                <div class="d-flex justify-content-between pt-10 mt-10">
                    <p class="f-w-600">{{ __('Discount') }}</p>
                    <p>{{ formatAmount($sale->total_discount_amount) }}</p>
                </div>
                @endif
                @if($sale->vat > 0)
                <div class="d-flex justify-content-between pt-10 mt-10">
                    <p class="f-w-600">{{ __('VAT') }}</p>
                    <p>{{ formatAmount($sale->vat) }}</p>
                </div>
                @endif
                @if($sale->delivery_charge > 0)
                <div class="d-flex justify-content-between pt-10 mt-10">
                    <p class="f-w-600">{{ __('Delivery Charge') }}</p>
                    <p>{{ formatAmount($sale->delivery_charge) }}</p>
                </div>
                @endif
                <div class="d-flex justify-content-between pt-10 mt-10">
                    <p class="f-w-600">{{ __('Paid') }}</p>
                    <p>{{ formatAmount($sale->paid_amount) }}</p>
                </div>
                <div class="d-flex justify-content-between pt-10 mt-10">
                    <p class="f-w-600">{{ __('Due') }}</p>
                    <p>{{ formatAmount($sale->due_amount) }}</p>
                </div>
                <div class="d-flex justify-content-between pt-10 mt-10 p-10 bg-00c53 br-3">
                    <p class="f-w-600">{{ __('Grand Total') }}</p>
                    <p>{{ formatAmount($sale->grand_total) }}</p>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end mt-50">
            <div>
                <p class="color-71 d-inline b-t-1p-e4e5ea pt-10">{{ __('Authorized Signature') }}</p>
            </div>
        </div>
        <div class="d-flex justify-content-center pt-30">
            <button onclick="window.print();" type="button" class="print-btn">{{ __('Print') }}</button>
        </div>
    </div>

    <script>
        window.onload = function () {
            window.print();
        };
    </script>
</body>
</html>
