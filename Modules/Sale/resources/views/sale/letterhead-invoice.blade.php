@php
    $invConfig = session('company.invoice_configuration') ? json_decode(session('company.invoice_configuration'), true) : [];
    $outletStateCode = $sale->outlet && $sale->outlet->state ? ($sale->outlet->state->state_code ?? null) : null;
    $customerStateCode = $sale->customer && $sale->customer->state ? ($sale->customer->state->state_code ?? null) : null;
    $isIntraState = ($customerStateCode === null) || ($outletStateCode === null) || ($customerStateCode === $outletStateCode);
    $vatObjects = $sale->sale_vat_objects ? json_decode($sale->sale_vat_objects, true) : [];
    $vatObjects = is_array($vatObjects) ? $vatObjects : [];
    $summaryTaxes = [];
    foreach ($vatObjects as $v) {
        $taxName = $v['tax_field_type'] ?? $v['tax_field_name'] ?? '';
        if (!$taxName) continue;
        $amt = (float) ($v['tax_field_amount'] ?? 0);
        $pct = (float) ($v['tax_field_percentage'] ?? 0);
        if (!isset($summaryTaxes[$taxName])) {
            $summaryTaxes[$taxName] = ['amount' => 0, 'rate' => $pct];
        }
        $summaryTaxes[$taxName]['amount'] += $amt;
        if ($pct > 0 && $summaryTaxes[$taxName]['rate'] == 0) {
            $summaryTaxes[$taxName]['rate'] = $pct;
        }
    }
    $defaultHsn = $invConfig['default_hsn_code'] ?? '0000';
    $totalItemDiscount = 0;
    $letterHeadGap = session('company.letter_head_gap', '200px');
    $letterFooterGap = session('company.letter_footer_gap', '100px');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Sale No') }}: {{ $sale->sale_no }}</title>
    <link rel="stylesheet" href="{{ asset('backend_assets/css/invoice-all.css') }}" />
    <style>
        /* Apply letterhead/footer gaps on every printed page */
        @media print {
            @page {
                margin-top: {{ $letterHeadGap }};
                margin-bottom: {{ $letterFooterGap }};
            }
        }
    </style>
</head>
<body class="letterhead-invoice">

    <!-- CUSTOMER + SALE INFO -->
    <table style="margin-top:10px">
        <tr>
            <td width="50%" valign="top">
                <div class="heading-title">{{ __('Customer') }} {{ __('Information') }}</div>
                <table class="info-table">
                    @if($sale->customer)
                        @if($sale->customer->name)
                        <tr><td>{{ __('Name') }}</td><td>: {{ $sale->customer->name }}</td></tr>
                        @endif
                        @if($sale->customer->phone)
                        <tr><td>{{ __('Phone') }}</td><td>: {{ $sale->customer->phone }}</td></tr>
                        @endif
                        @if($sale->customer->email)
                        <tr><td>{{ __('Email') }}</td><td>: {{ $sale->customer->email }}</td></tr>
                        @endif
                        @if($sale->customer->address)
                        <tr><td>{{ __('Address') }}</td><td>: {{ $sale->customer->address }}</td></tr>
                        @endif
                        @if(isEnableGST() && session('company.collect_tax') == 'Yes')
                            @if($sale->customer->gst_number)
                            <tr><td>{{ __('GSTIN') }}</td><td>: {{ $sale->customer->gst_number }}</td></tr>
                            @endif
                            @if($sale->customer->state && ($sale->customer->state->state_name || $sale->customer->state->state_code))
                            <tr><td>{{ __('Customer State') }}</td><td>: {{ $sale->customer->state->state_name ?? '' }} ({{ $sale->customer->state->state_code ?? '' }})</td></tr>
                            @endif
                        @endif
                    @else
                        <tr><td>{{ __('Name') }}</td><td>: {{ __('Walk-in Customer') }}</td></tr>
                    @endif
                </table>
            </td>
            <td width="50%" valign="top">
                <div class="heading-title">{{ __('Sale') }} {{ __('Information') }}</div>
                <table class="info-table">
                    @if($sale->sale_no)
                    <tr><td>{{ __('Sale No') }}</td><td>: {{ $sale->sale_no }}</td></tr>
                    @endif
                    @php
                        // Compute DateTime string as "YYYY/MM/DD h:iA"
                        $dateTimeString = null;
                        if (!empty($sale->date_time)) {
                            $date = \Carbon\Carbon::parse($sale->date_time);
                            $dateTimeString = $date->format('Y/m/d g:ia');
                            $dateTimeString = strtoupper(substr($dateTimeString, 0, -2)) . ' ' . substr($dateTimeString, -2); // Make AM/PM uppercase
                        } elseif (!empty($sale->sale_date)) {
                            // Fallback to sale_date without time
                            $date = \Carbon\Carbon::parse($sale->sale_date);
                            $dateTimeString = $date->format('Y/m/d');
                        }
                    @endphp
                    @if(!empty($dateTimeString))
                    <tr><td>{{ __('Date Time') }}</td><td>: {{ $dateTimeString }}</td></tr>
                    @endif
                    @if($sale->employee)
                    <tr><td>{{ __('Sales By') }}</td><td>: {{ $sale->employee->name }}</td></tr>
                    @endif
                    <tr><td>{{ __('Status') }}</td><td>: {{ $sale->due_amount > 0 ? __('Due') : __('Paid') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ITEMS TABLE -->
    <table class="items-table" style="margin-top:10px">
        <thead>
            <tr>
                <th width="5%">#</th>
                @if(isEnableGST() && isset($invConfig['show_hsn_code']) && $invConfig['show_hsn_code'] == 'Yes')
                <th width="6%">{{ __('HSN') }}</th>
                @endif
                <th width="{{ isEnableGST() && isset($invConfig['show_hsn_code']) && $invConfig['show_hsn_code'] == 'Yes' ? '22%' : '25%' }}">{{ __('Item') }}</th>
                <th width="15%">{{ __('IMEI/Serial') }}</th>
                <th width="8%">{{ __('Qty') }}</th>
                <th width="12%">{{ __('Unit Price') }}</th>
                @if(session('company.collect_tax') == 'Yes')
                <th width="12%">{{ __('Tax') }}</th>
                @if($isIntraState)
                <th width="8%">{{ __('Tax Type') }}</th>
                @endif
                @endif
                <th width="12%">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @php $i = 0; @endphp
            @if($sale->saleDetails && $sale->saleDetails->count() > 0)
                @foreach($sale->saleDetails as $detail)
                    @php
                        $i++;
                        $lineSubtotal = $detail->qty * $detail->menu_price_with_discount;
                        $lineTotal = $lineSubtotal;
                        $detailTaxDisplay = '';
                        $itemTaxType = $detail->item && $detail->item->tax_type ? $detail->item->tax_type : 'Inclusive';
                        $menuTaxes = $detail->menu_taxes ? json_decode($detail->menu_taxes, true) : [];
                        $menuTaxes = is_array($menuTaxes) ? $menuTaxes : [];
                        foreach ($menuTaxes as $t) {
                            $name = $t['tax_field_name'] ?? $t['tax_field_type'] ?? '';
                            $amt = (float) ($t['tax_field_amount'] ?? 0);
                            $pct = (float) ($t['tax_field_percentage'] ?? 0);
                            if ($name && $amt > 0) {
                                $detailTaxDisplay .= ($detailTaxDisplay ? ', ' : '') . $name . ($pct > 0 ? ' ' . $pct . '%' : '') . ' - ' . formatAmount($amt);
                            }
                        }
                        if ($detailTaxDisplay && !$isIntraState) {
                            $detailTaxDisplay .= ' (' . __($itemTaxType) . ')';
                        }
                        $hsnCode = ($detail->item && $detail->item->hsn_code) ? $detail->item->hsn_code : ($defaultHsn ?? '0000');
                    @endphp
                    <tr>
                        <td>{{ $i }}</td>
                        @if(isEnableGST() && isset($invConfig['show_hsn_code']) && $invConfig['show_hsn_code'] == 'Yes')
                        <td>{{ $hsnCode }}</td>
                        @endif
                        <td>
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
                        </td>
                        <td>
                            @if($detail->expiry_imei_serial)
                                @if($detail->item && in_array($detail->item->type, ['IMEI_Product', 'Serial_Product']))
                                    {{ $detail->item->type === 'IMEI_Product' ? 'IMEI' : 'Serial' }}: {{ $detail->expiry_imei_serial }}
                                @elseif($detail->item && $detail->item->type === 'Medicine_Product')
                                    {{ $detail->expiry_imei_serial }}
                                @else
                                    {{ $detail->expiry_imei_serial }}
                                @endif
                            @else
                                -
                            @endif
                        </td>
                        <td class="num">{{ $detail->qty }}</td>
                        <td class="num">{{ formatAmount($detail->menu_unit_price) }}</td>
                        @if(session('company.collect_tax') == 'Yes')
                        <td class="num">
                            @if($detailTaxDisplay)
                                {{ $detailTaxDisplay }}
                            @else
                                -
                            @endif
                        </td>
                        @if($isIntraState)
                        <td>{{ __($itemTaxType) }}</td>
                        @endif
                        @endif
                        <td class="num">{{ formatAmount($lineTotal) }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>

    <!-- SUMMARY -->
    <table style="margin-top:10px">
        <tr>
            <td width="60%" valign="top">
                @if($sale->note)
                <div class="notes">
                    <strong>{{ __('Note') }}:</strong><br>
                    {{ $sale->note }}
                </div>
                @endif
            </td>
            <td width="40%" valign="top">
                <table class="summary-table">
                    <tr>
                        <td>{{ __('Subtotal') }}</td>
                        <td>{{ formatAmount($sale->sub_total) }}</td>
                    </tr>
                    @if(isset($sale->total_item_discount_amount) && $sale->total_item_discount_amount > 0)
                    <tr>
                        <td>{{ __('Item Discount') }}</td>
                        <td>{{ formatAmount($sale->total_item_discount_amount) }}</td>
                    </tr>
                    @endif
                    @if(isset($sale->sub_total_discount_amount) && $sale->sub_total_discount_amount > 0)
                    <tr>
                        <td>{{ __('Order Discount') }}</td>
                        <td>{{ formatAmount($sale->sub_total_discount_amount) }}</td>
                    </tr>
                    @endif
                    @if($sale->total_discount_amount > 0 && !isset($sale->total_item_discount_amount) && !isset($sale->sub_total_discount_amount))
                    <tr>
                        <td>{{ __('Discount') }}</td>
                        <td>{{ formatAmount($sale->total_discount_amount) }}</td>
                    </tr>
                    @endif
                    @foreach($summaryTaxes ?? [] as $taxName => $taxData)
                        @if(($taxData['amount'] ?? 0) > 0)
                    <tr>
                        <td>{{ $taxName }}{{ ($taxData['rate'] ?? 0) > 0 ? ' (' . $taxData['rate'] . '%)' : '' }}</td>
                        <td>{{ formatAmount($taxData['amount']) }}</td>
                    </tr>
                        @endif
                    @endforeach
                    @if($sale->delivery_charge > 0)
                    <tr>
                        <td>{{ __('Delivery Charge') }}</td>
                        <td>{{ formatAmount($sale->delivery_charge) }}</td>
                    </tr>
                    @endif
                    <tr class="grand-total">
                        <td>{{ __('Grand Total') }}</td>
                        <td>{{ formatAmount($sale->total_payable ?? $sale->grand_total) }}</td>
                    </tr>
                    <tr>
                        <td>{{ __('Paid Amount') }}</td>
                        <td>{{ formatAmount($sale->paid_amount) }}</td>
                    </tr>
                    <tr>
                        <td>{{ __('Due Amount') }}</td>
                        <td>{{ formatAmount($sale->due_amount) }}</td>
                    </tr>
                    @if(isset($invConfig['show_payment_method']) && $invConfig['show_payment_method'] == 'Yes' && $sale->salePayments && $sale->salePayments->count() > 0)
                    <tr>
                        <td>{{ $invConfig['payment_method_label'] ?? __('Payment Method') }}</td>
                        <td></td>
                    </tr>
                    @foreach($sale->salePayments as $payment)
                    <tr>
                        <td>{{ $payment->paymentMethod->name ?? __('Cash') }}</td>
                        <td>{{ formatAmount($payment->amount) }}</td>
                    </tr>
                    @endforeach
                    @endif
                </table>
            </td>
        </tr>
    </table>


    {{-- Zatca QR Code --}}
    @if($sale->zatca_phase1_qr_code)
    @php
        $qrData = base64_decode($sale->zatca_phase1_qr_code);
        $qrDataEncoded = urlencode($qrData);
        $qrCodeImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . $qrDataEncoded;
    @endphp
    <div style="text-align: center; margin-top: 20px;">
        <p style="font-weight: 600; margin-bottom: 10px;">{{ __('ZATCA QR Code') }}</p>
        <img src="{{ $qrCodeImageUrl }}" alt="ZATCA QR Code" style="max-width: 150px; height: auto;">
    </div>
    @endif

    <div style="text-align: center; margin-top: 30px;">
        <!-- add tailwind button class -->
        <button onclick="window.print();" type="button" class="print-btn" style="padding:0.75rem 1.5rem;border-radius:0.75rem;background:#4f46e5;color:#fff;font-weight:500;border:none;cursor:pointer">{{ __('Print') }}</button>
    </div>

    <script>
        window.onload = function () {
            window.print();
        };
    </script>
</body>
</html>
