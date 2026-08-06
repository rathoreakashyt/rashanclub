<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Quotation No') }}: {{ $quotation->reference_no }}</title>
    <link rel="stylesheet" href="{{ asset('backend_assets/css/print-invoice-a4.css') }}" />
    <link rel="stylesheet" href="{{ asset('backend_assets/css/inv-common.css') }}" />
</head>
<body>
    <div id="wrapper" class="m-auto border-2s-e4e5ea br-5 p-30">
        
        <div class="d-flex justify-content-between">
            <div>
                <h3 class="pb-7 shop-name">{{ session('company.business_name', '') }}</h3>
                @if($quotation->outlet)
                    <p class="pb-7">{{ $quotation->outlet->outlet_name ?? '' }}</p>
                    <p class="pb-7 f-w-500 color-71">{{ $quotation->outlet->address ?? '' }}</p>
                    <p class="pb-7 f-w-500 color-71">{{ __('Email') }}: {{ $quotation->outlet->email ?? '' }}</p>
                    <p class="pb-7 f-w-500 color-71">{{ __('Phone') }}: {{ $quotation->outlet->phone ?? '' }}</p>
                @endif
                @if(session('company.collect_tax') == 'Yes')
                    <p class="pb-7 f-w-900 rgb-71">{{ session('company.tax_title', '') }}: {{ session('company.tax_registration_no', '') }}</p>
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
            <h2 class="invoice-heading">{{ __('Quotation') }}</h2>
        </div>

        <div class="d-grid g-template-c-48-48 g-gap-2">
            <div class="d-flex justify-content-between">
                <div>
                    <h3 class="pb-7 common-heading">{{ __('Quotation') }} {{ __('Info') }}</h3>
                    @if($quotation->reference_no)
                    <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Reference No') }}:</span> {{ $quotation->reference_no }}</p>
                    @endif
                    @if($quotation->date)
                    <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Date') }}:</span> {{ formatDate($quotation->date) }}</p>
                    @endif
                </div>
            </div>
            <div class="d-flex justify-content-end">
                <div>
                    <h3 class="pb-7 common-heading">{{ __('Customer') }} {{ __('Info') }}</h3>
                    @if($quotation->customer)
                        @if($quotation->customer->name)
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Name') }}:</span> {{ $quotation->customer->name }}</p>
                        @endif
                        @if($quotation->customer->phone)
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Phone') }}:</span> {{ $quotation->customer->phone }}</p>
                        @endif
                        @if($quotation->customer->address)
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Address') }}:</span> {{ $quotation->customer->address }}</p>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <div>
            <table class="table w-100 mt-20">
                <thead class="br-3 bg-00c53">
                    <tr>
                        <th class="w-5 text-center">{{ __('SN') }}</th>
                        <th class="w-30 text-start">{{ __('Item') }}-{{ __('Code') }}-{{ __('Brand') }}</th>
                        <th class="w-15 text-center">{{ __('Qty') }}</th>
                        <th class="w-15 text-center">{{ __('Unit Price') }}</th>
                        <th class="w-20 text-right pr-5">{{ __('Total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $i = 0;
                    @endphp
                    @if($quotation->quotationDetails && $quotation->quotationDetails->count() > 0)
                        @foreach($quotation->quotationDetails as $detail)
                            @php
                                $i++;
                            @endphp
                            <tr>
                                <td class="text-center">
                                    <span>{{ $i }}</span>
                                </td>
                                <td class="text-start">
                                    <span>
                                        {{ $detail->item->name ?? '' }}
                                        @if($detail->item && $detail->item->code)
                                            ({{ $detail->item->code }})
                                        @endif
                                        @if($detail->item && $detail->item->brand_id && $detail->item->brand)
                                            - {{ $detail->item->brand->name }}
                                        @endif
                                    </span>
                                </td>
                                <td class="text-center">{{ $detail->quantity }}</td>
                                <td class="text-center">{{ formatAmount($detail->unit_price) }}</td>
                                <td class="text-right">{{ formatAmount($detail->total) }}</td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>

        <div class="d-grid g-template-c-50-40 grid-gap-10 pt-20">
            <div>
                <div class="pt-10">
                    @if($quotation->note)
                    <h4 class="d-block pb-10">{{ __('Note') }}</h4>
                    <div class="w-100 bg-240 m-h-120px-m-h-220px p-15 b-1s-240">
                        <p>
                            {{ $quotation->note }}
                        </p>
                    </div>
                    @endif
                </div>
            </div>
            <div>
                <div class="d-flex justify-content-between pt-10">
                    @php
                        $discount = $quotation->discount ?? '0';
                        $discount_ac = '';
                        if(strpos($discount, '%') !== false) {
                            $discount_ac = $discount;
                        } else {
                            $discount_ac = formatAmount($discount);
                        }
                    @endphp
                    <p class="f-w-600">{{ __('Discount') }}</p>
                    <p>{{ $discount_ac }}</p>
                </div>
                <div class="d-flex justify-content-between pt-10 mt-10 p-10 bg-00c53 br-3">
                    <p class="f-w-600">{{ __('Grand Total') }}</p>
                    <p>{{ formatAmount($quotation->grand_total) }}</p>
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


