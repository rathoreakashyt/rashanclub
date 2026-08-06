<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Quotation No') }}: {{ $quotation->reference_no }}</title>
    <link rel="stylesheet" href="{{ asset('backend_assets/css/pdf-common.css') }}" />
</head>
<body>
    <div id="wrapper" class="m-auto b-r-5 p-30">
        <table>
            <tr>
                <td class="w-50">
                    <h3 class="pb-7">{{ session('company.business_name', '') }}</h3>
                    @if($quotation->outlet)
                        <p class="pb-7 f-w-900 rgb-71">{{ $quotation->outlet->outlet_name ?? '' }}</p>
                        <p class="pb-7 f-w-900 rgb-71">{{ $quotation->outlet->address ?? '' }}</p>
                        <p class="pb-7 f-w-900 rgb-71">{{ __('Email') }}: {{ $quotation->outlet->email ?? '' }}</p>
                        <p class="pb-7 f-w-900 rgb-71">{{ __('Phone') }}: {{ $quotation->outlet->phone ?? '' }}</p>
                    @endif
                    @if(session('company.collect_tax') == 'Yes')
                        <p class="pb-7 f-w-900 rgb-71">{{ session('company.tax_title', '') }}: {{ session('company.tax_registration_no', '') }}</p>
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
            <h2 class="invoice-heading">{{ __('Quotation') }}</h2>
        </div>
        
        <table>
            <tr>
                <td valign="top">
                    <h3 class="pb-7">{{ __('Quotation') }} {{ __('Info') }}</h3>
                    @if($quotation->reference_no)
                    <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Reference No') }}:</span> {{ $quotation->reference_no }}</p>
                    @endif
                    @if($quotation->date)
                    <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Date') }}:</span> {{ formatDate($quotation->date) }}</p>
                    @endif
                    @if($quotation->customer)
                    <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Customer') }}:</span> {{ $quotation->customer->name }}</p>
                    @endif
                </td>
                
                <td valign="top">
                    <h3 class="pb-7">{{ __('Customer') }} {{ __('Info') }}</h3>
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
                </td>
            </tr>
        </table>


        <table class="w-100 mt-20">
            <thead class="b-r-3 color-white">
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

        <table class="mt-20">
            <tr>
                <td valign="top" class="w-48">
                    <div class="pt-10">
                        @if($quotation->note)
                        <h4 class="d-block pb-10">{{ __('Note') }}</h4>
                        <div>
                            <p class="h-180 color-black">
                                {{ $quotation->note }}
                            </p>
                        </div>
                        @endif
                    </div>
                </td>
                <td class="w-4"></td>
                <td class="w-48">
                    <table class="pt-10">
                        <tr>
                            @php
                                $discount = $quotation->discount ?? '0';
                                $discount_ac = '';
                                if(strpos($discount, '%') !== false) {
                                    $discount_ac = $discount;
                                } else {
                                    $discount_ac = formatAmount($discount);
                                }
                            @endphp
                            <td class="w-50">
                                <p class="f-w-600">{{ __('Discount') }}</p>
                            </td>
                            <td class="w-50 text-right">
                                <p>{{ $discount_ac }}</p>
                            </td>
                        </tr>
                    </table>
                    <table>
                        <tr class="bg-00c53">
                            <td class="w-50">
                                <p class="f-w-600">{{ __('Grand Total') }}</p>
                            </td>
                            <td class="w-50 text-right">
                                <p>{{ formatAmount($quotation->grand_total) }}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>


        <table class="mt-50">
            <tr>
                <td class="w-50">
                </td>
                <td class="w-50 text-right">
                    <p class="rgb-71 d-inline border-top-e4e5ea pt-10">{{ __('Authorized Signature') }}</p>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
