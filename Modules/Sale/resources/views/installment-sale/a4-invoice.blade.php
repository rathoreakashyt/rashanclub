<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Installment Sale Invoice') }}: {{ $installmentSale->reference_no }}</title>
    <link rel="stylesheet" href="{{ asset('backend_assets/css/print-invoice-a4.css') }}" />
    <link rel="stylesheet" href="{{ asset('backend_assets/css/inv-common.css') }}" />
</head>
<body>
    <div id="wrapper" class="m-auto border-2s-e4e5ea br-5 p-30">
        
        <div class="d-flex justify-content-between">
            <div>
                <h3 class="pb-7 shop-name">{{ session('company.business_name', '') }}</h3>
                @if($installmentSale->outlet)
                    <p class="pb-7">{{ $installmentSale->outlet->outlet_name ?? '' }}</p>
                    <p class="pb-7 f-w-500 color-71">{{ $installmentSale->outlet->address ?? '' }}</p>
                    <p class="pb-7 f-w-500 color-71">{{ __('Email') }}: {{ $installmentSale->outlet->email ?? '' }}</p>
                    <p class="pb-7 f-w-500 color-71">{{ __('Phone') }}: {{ $installmentSale->outlet->phone ?? '' }}</p>
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
            <h2 class="invoice-heading">{{ __('Installment Sale Invoice') }}</h2>
        </div>

        <div class="d-grid g-template-c-48-48 g-gap-2">
            <div class="d-flex justify-content-between">
                <div>
                    <h3 class="pb-7 common-heading">{{ __('Sale') }} {{ __('Info') }}</h3>
                    @if($installmentSale->reference_no)
                    <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Reference No') }}:</span> {{ $installmentSale->reference_no }}</p>
                    @endif
                    @if($installmentSale->date)
                    <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Date') }}:</span> {{ formatDate($installmentSale->date) }}</p>
                    @endif
                    @if($installmentSale->item)
                    <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Item') }}:</span> {{ $installmentSale->item->name ?? 'N/A' }}</p>
                    @endif
                    @if($installmentSale->expiry_imei_serial)
                    <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('IMEI/Serial') }}:</span> {{ $installmentSale->expiry_imei_serial }}</p>
                    @endif


                    <div class="col-md-6">
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Price') }}:</span> {{ formatAmount($installmentSale->price) }}</p>
                    </div>
                    <div class="col-md-6">
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Discount') }}:</span> {{ $installmentSale->discount }} ({{ formatAmount($installmentSale->discount_amount) }})</p>
                    </div>
                    <div class="col-md-6">
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Interest') }}:</span> {{ $installmentSale->percentage_of_interest }}% ({{ formatAmount($installmentSale->interest_amount) }})</p>
                    </div>
                    <div class="col-md-6">
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Shipping/Other') }}:</span> {{ formatAmount($installmentSale->shipping_other) }}</p>
                    </div>
                    <div class="col-md-6">
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Total') }}:</span> {{ formatAmount($installmentSale->total) }}</p>
                    </div>
                    <div class="col-md-6">
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Down Payment') }}:</span> {{ formatAmount($installmentSale->down_payment) }}</p>
                    </div>
                    <div class="col-md-6">
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Remaining') }}:</span> {{ formatAmount($installmentSale->remaining) }}</p>
                    </div>
                    <div class="col-md-6">
                        <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Number of Installments') }}:</span> {{ $installmentSale->number_of_installment }}</p>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end">
                <div>
                    <h3 class="pb-7 common-heading">{{ __('Customer') }} {{ __('Info') }}</h3>
                    @if($installmentSale->customer)
                        <div class="d-flex align-items-start mb-3">
                            
                            <div>
                                @if($installmentSale->customer->name)
                                <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Name') }}:</span> {{ $installmentSale->customer->name }}</p>
                                @endif
                                @if($installmentSale->customer->phone)
                                <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Phone') }}:</span> {{ $installmentSale->customer->phone }}</p>
                                @endif
                                @if($installmentSale->customer->address)
                                <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Address') }}:</span> {{ $installmentSale->customer->address }}</p>
                                @endif
                            </div>
                            @if($installmentSale->customer->photo)
                                <img src="{{ asset('' . $installmentSale->customer->photo) }}" alt="Customer Photo" width="80" height="80" class="me-3" style="border-radius: 5px;">
                            @endif
                        </div>
                    @endif

                    @if($installmentSale->customer && ($installmentSale->customer->g_name || $installmentSale->customer->g_mobile))
                    <div class="d-grid g-template-c-48-48 g-gap-2 mt-20">
                        <div>
                            <h3 class="pb-7 common-heading">{{ __('Guarantor') }} {{ __('Info') }}</h3>
                            @if($installmentSale->customer->g_name)
                            <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Name') }}:</span> {{ $installmentSale->customer->g_name }}</p>
                            @endif
                            @if($installmentSale->customer->g_mobile)
                            <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Mobile') }}:</span> {{ $installmentSale->customer->g_mobile }}</p>
                            @endif
                            @if($installmentSale->customer->g_pre_address)
                            <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Present Address') }}:</span> {{ $installmentSale->customer->g_pre_address }}</p>
                            @endif
                            @if($installmentSale->customer->g_work_address)
                            <p class="pb-7 f-w-500 color-71"><span class="f-w-600">{{ __('Work Address') }}:</span> {{ $installmentSale->customer->g_work_address }}</p>
                            @endif
                        </div>
                        <div class="d-flex justify-content-end">
                            <div>
                                @if($installmentSale->customer->g_photo)
                                    <img src="{{ asset('' . $installmentSale->customer->g_photo) }}" alt="Guarantor Photo" width="80" height="80" style="border-radius: 5px;">
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        


        <div>
            <h3 class="pb-7 common-heading mt-20">{{ __('Installment') }} {{ __('Summary') }}</h3>
            <table class="table w-100 mt-20">
                <thead class="br-3 bg-00c53">
                    <tr>
                        <th class="w-5 text-center">{{ __('SN') }}</th>
                        <th class="w-20 text-start">{{ __('Payment') }} {{ __('Date') }}</th>
                        <th class="w-20 text-center">{{ __('Paid') }} {{ __('Date') }}</th>
                        <th class="w-20 text-center">{{ __('Amount') }}</th>
                        <th class="w-20 text-center">{{ __('Paid') }} {{ __('Amount') }}</th>
                        <th class="w-15 text-right pr-5">{{ __('Remaining') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $i = 0;
                        $totalAmount = 0;
                        $totalPaid = 0;
                    @endphp
                    @if($installmentDetails && $installmentDetails->count() > 0)
                        @foreach($installmentDetails as $detail)
                            @php
                                $i++;
                                $totalAmount += $detail->amount_of_payment;
                                $totalPaid += $detail->paid_amount;
                            @endphp
                            <tr>
                                <td class="text-center">
                                    <span>{{ $i }}</span>
                                </td>
                                <td class="text-start">
                                    <span>{{ formatDate($detail->payment_date) }}</span>
                                </td>
                                <td class="text-center">
                                    <span>{{ $detail->paid_date ? formatDate($detail->paid_date) : '-' }}</span>
                                </td>
                                <td class="text-center">{{ formatAmount($detail->amount_of_payment) }}</td>
                                <td class="text-center">{{ formatAmount($detail->paid_amount) }}</td>
                                <td class="text-right">{{ formatAmount($detail->amount_of_payment - $detail->paid_amount) }}</td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
                <tfoot>
                    <tr class="bg-00c53">
                        <th colspan="3" class="text-right">{{ __('Total') }}:</th>
                        <th class="text-center">{{ formatAmount($totalAmount) }}</th>
                        <th class="text-center">{{ formatAmount($totalPaid) }}</th>
                        <th class="text-right">{{ formatAmount($totalAmount - $totalPaid) }}</th>
                    </tr>
                </tfoot>
            </table>
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

