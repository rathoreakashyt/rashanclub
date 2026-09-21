@php
    // ═══ Derived values matching desktop PdfService.cs exactly ═══
    $mrpTotal   = (float) ($sale->mrp_total ?? 0);
    $savings    = (float) ($sale->savings ?? 0);
    $givenAmt   = (float) ($sale->given_amount ?? 0);
    $changeAmt  = (float) ($sale->change_amount ?? 0);
    $rounding   = (float) ($sale->rounding ?? 0);
    $subTotal   = (float) ($sale->sub_total ?? 0);
    $grandTotal = (float) ($sale->total_payable ?? $sale->grand_total ?? 0);
    $vat        = (float) ($sale->vat ?? 0);
    $disc       = (float) ($sale->total_discount_amount ?? 0);
    $paidAmt    = (float) ($sale->paid_amount ?? 0);
    $dueAmt     = (float) ($sale->due_amount ?? 0);

    $totalItems = $sale->saleDetails ? $sale->saleDetails->count() : 0;
    $totalQty   = 0;
    $itemSavings = 0;
    $priceTotal  = 0;
    if ($sale->saleDetails) {
        foreach ($sale->saleDetails as $d) {
            $totalQty   += (float) $d->qty;
            $mrp        = (float) ($d->item->mrp_price ?? $d->menu_unit_price ?? 0);
            $selling    = (float) ($d->menu_price_with_discount ?? $d->menu_unit_price ?? 0);
            $itemSavings += (float) $d->qty * max(0, $mrp - $selling);
            $priceTotal += (float) $d->qty * $selling;
        }
    }
    $totalSavings = $savings > 0 ? $savings : ($mrpTotal > 0 ? max(0, $mrpTotal - $grandTotal) : $disc);
    $billDiscount = $disc;
    // Per-customer cumulative total saving (is customer ke pichhle bills ka saving)
    $prevTotalSaving = 0;
    if ($sale->customer_id)
        $prevTotalSaving = (float) \Modules\Sale\Models\Sale::where('id', '<', $sale->id)
            ->where('customer_id', $sale->customer_id)
            ->where('del_status', 'Live')->sum('savings');

    // Payment mode
    $paymentMode = 'Cash';
    if ($sale->salePayments && $sale->salePayments->count() > 0)
        $paymentMode = $sale->salePayments->first()->paymentMethod->name ?? 'Cash';

    // User / Customer
    $userName     = $sale->user->name ?? ($sale->employee->name ?? '');
    $customerName = $sale->customer->name ?? 'Walk-in Customer';
    $customerPhone = $sale->customer->phone ?? '';

    // Company / Outlet
    $companyName  = session('company.business_name', 'Rashan Ki Dukan');
    $companyEmail = session('company.company_email');
    $outletAddr   = $sale->outlet->address ?? '';
    $outletPhone  = $sale->outlet->phone ?? '';
    $outletGstin  = $sale->outlet->gstin ?? ($sale->company->tax_registration_no ?? '');

    // Date / Time
    $saleDate = \Carbon\Carbon::parse($sale->sale_date)->format('d/m/y');
    $saleTime = !empty($sale->date_time) ? \Carbon\Carbon::parse($sale->date_time)->format('h:i A') : '';

    // Tax breakdown by rate (CGST/SGST)
    $taxByRate   = [];
    $totalTaxable = 0; $totalCgst = 0; $totalSgst = 0;
    if ($sale->saleDetails) {
        foreach ($sale->saleDetails as $d) {
            $gp = (float) ($d->menu_vat_percentage ?? 0);
            if ($gp > 0) {
                $lt = (float) ($d->qty * $d->menu_unit_price);
                $ta = $lt * $gp / (100 + $gp);
                $tx = $lt - $ta;
                if (!isset($taxByRate[$gp])) $taxByRate[$gp] = ['taxable'=>0,'cgst'=>0,'sgst'=>0];
                $taxByRate[$gp]['taxable'] += $tx;
                $taxByRate[$gp]['cgst']    += $ta / 2;
                $taxByRate[$gp]['sgst']    += $ta / 2;
            }
        }
        ksort($taxByRate);
        foreach ($taxByRate as $t) { $totalTaxable += $t['taxable']; $totalCgst += $t['cgst']; $totalSgst += $t['sgst']; }
    }

    // Amount in words (Indian numbering: Lakh/Crore)
    function rdkWords($amount) {
        $n = (int) round($amount);
        if ($n == 0) return 'Zero';
        $o=['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine',
            'Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen',
            'Seventeen','Eighteen','Nineteen'];
        $t=['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
        $w='';
        if($n>=10000000){$w.=rdkWords(intdiv($n,10000000)).' Crore ';$n%=10000000;}
        if($n>=100000)  {$w.=rdkWords(intdiv($n,100000)).' Lakh ';$n%=100000;}
        if($n>=1000)    {$w.=rdkWords(intdiv($n,1000)).' Thousand ';$n%=1000;}
        if($n>=100)     {$w.=$o[intdiv($n,100)].' Hundred ';$n%=100;}
        if($n>=20)      {$w.=$t[intdiv($n,10)].' ';$n%=10;}
        if($n>0)        {$w.=$o[$n];}
        return 'Rupees '.trim($w).' Only';
    }

    // Gram (loyalty) data — same computation as desktop
    $gramEarned = 0;
    if ($sale->saleDetails) {
        foreach ($sale->saleDetails as $d) {
            $itemLoyalty = (float) ($d->item->loyalty_point ?? 0);
            $gramEarned += (float) $d->qty * $itemLoyalty;
        }
    }
    $gramRedeemed = 0;
    if ($sale->salePayments) {
        foreach ($sale->salePayments as $sp) {
            if (stripos($sp->paymentMethod->name ?? '', 'loyalty') !== false)
                $gramRedeemed += (float) $sp->amount;
        }
    }
    $gramAvailable = (float) ($sale->customer->loyalty_point ?? 0);
    $gramPrevious = $gramAvailable - $gramEarned + $gramRedeemed;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $sale->sale_no }}</title>
    <link rel="stylesheet" href="{{ asset('backend_assets/css/rdk-invoice.css') }}" />
</head>
<body class="rdk-invoice">

    <!-- ═══ HEADER ═══ -->
    <div class="rdk-header">
        @php
            $invoiceLogo = session('company.invoice_logo');
            $invLogoShow = session('company.inv_logo_is_show');
        @endphp
        @if($invoiceLogo && $invLogoShow !== 'No')
            <div class="rdk-logo"><img src="{{ asset('uploads/site_settings/' . $invoiceLogo) }}" alt="Logo" /></div>
        @else
            <div class="store-icon">RK</div>
        @endif
        <div class="store-name"><span style="font-family: freesans;">राशन की दुकान</span></div>
        <div class="tagline">INDIA'S TRUSTED MULTI BRAND GROCERY CHAIN STORES</div>
        <div class="tagline-line"></div>
        @if($outletAddr)<div class="address">{{ $outletAddr }}</div>@endif
        @if($outletPhone)<div class="phone-left">Customercare No : {{ $outletPhone }}</div>@endif
        @if($companyEmail)<div class="email-left">Email : {{ $companyEmail }}</div>@endif
        @if($outletGstin)<div class="gstin-center">GSTIN : {{ $outletGstin }}</div>@endif
    </div>

    <!-- ═══ TAX INVOICE ═══ -->
    <div class="rdk-tax-invoice"><span>TAX INVOICE</span></div>

    <!-- ═══ BILL INFO ═══ -->
    <div class="rdk-bill-info">
        <table>
            <tr><td class="lbl">Invoice No/Date/Time :</td><td class="val">{{ $sale->sale_no }}&nbsp;&nbsp;{{ $saleDate }}&nbsp;&nbsp;{{ $saleTime }}</td></tr>
            <tr><td class="lbl">User Name</td><td class="val">{{ $userName ?: 'Busy' }}</td></tr>
            <tr><td class="lbl">Customer Name :</td><td class="val">{{ $customerName }}</td></tr>
            <tr><td class="lbl">Cust Mobile No :</td><td class="val">{{ $customerPhone }}</td></tr>
        </table>
    </div>

    <!-- ═══ ITEMS TABLE ═══ -->
    <div class="rdk-items">
        <div class="line-top"></div>
        <table>
            <thead>
                <tr>
                    <th class="c1">S.no</th>
                    <th class="c2">Qty</th>
                    <th>Product</th>
                </tr>
                <tr>
                    <th></th><th></th>
                    <th></th>
                    <th class="c4 r">MRP</th>
                    <th class="c5 r">Price</th>
                    <th class="c6 r">Amt.</th>
                </tr>
            </thead>
            <tbody>
                <div class="line-bot"></div>
                @php $sno=0; @endphp
                @if($sale->saleDetails)
                    @foreach($sale->saleDetails as $d)
                        @php
                            $sno++;
                            $mrpP = (float) ($d->item->mrp_price ?? $d->menu_unit_price ?? 0);
                            $selP = (float) ($d->menu_price_with_discount ?? $d->menu_unit_price ?? 0);
                            $amt  = (float) ($d->qty * $d->menu_unit_price);
                            $nm = '';
                            if ($d->item) {
                                $nm = ($d->item->parent_id && $d->item->parent)
                                    ? $d->item->parent->name.' - '.$d->item->name
                                    : $d->item->name;
                            }
                        @endphp
                        {{-- Line 1: S.no + Qty + Product --}}
                        <tr>
                            <td>{{ $sno }}</td>
                            <td>{{ $d->qty }}</td>
                            <td>{{ $nm }}</td>
                        </tr>
                        {{-- Line 2: MRP + Price + Amt --}}
                        <tr>
                            <td></td><td></td><td></td>
                            <td class="r">{{ number_format($mrpP,2) }}</td>
                            <td class="r">{{ number_format($selP,2) }}</td>
                            <td class="r">{{ number_format($amt,2) }}</td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    </div>

    <!-- ═══ TOTALS ═══ -->
    <div class="rdk-totals">
        <div class="line"></div>
        <table>
            <tr><td></td><td class="r lbl lblcol">Total</td><td class="r valcol">{{ number_format($subTotal,2) }}</td></tr>
            @if(abs($rounding) > 0.001)
            <tr><td></td><td class="r lblcol">Rounded Off (-)</td><td class="r valcol">{{ number_format(abs($rounding),2) }}</td></tr>
            @endif
            <tr><td></td><td class="net-payable lblcol">Net Payable</td><td class="r net-payable valcol">{{ number_format($grandTotal,2) }}</td></tr>
        </table>
    </div>

    <!-- ═══ MRP/PRICE/AMT TOTALS + AMOUNT IN WORDS ═══ -->
    <div class="rdk-mrp-summary">
        <table class="rdk-mrp-box">
            <tr>
                <td class="bold">MRP TOTAL<br>{{ number_format($mrpTotal,2) }}</td>
                <td class="bold">Price Total<br>{{ number_format($priceTotal,2) }}</td>
                <td class="bold">Amt. Total<br>{{ number_format($subTotal,2) }}</td>
            </tr>
        </table>
        <div class="line"></div>
        <div class="rdk-words">{{ rdkWords($grandTotal) }}</div>
    </div>

    <!-- ═══ YOU SAVE ═══ -->
    @if($totalSavings > 0)
    <div class="rdk-save">
        <div class="rdk-save-box">
            <div class="big">YOU SAVE:&nbsp;&nbsp;{{ number_format($totalSavings,2) }}</div>
            <div class="sub">Total Saving : {{ number_format($prevTotalSaving,2) }} + {{ number_format($totalSavings,2) }} = {{ number_format($prevTotalSaving + $totalSavings,2) }}</div>
        </div>
    </div>
    @endif

    <!-- ═══ TAX TABLE (CGST/SGST) ═══ -->
    @if(count($taxByRate) > 0)
    <div class="rdk-tax">
        <div class="line"></div>
        <table>
            <thead><tr>
                <th class="c1">Tax Rate</th>
                <th class="c2 r">Taxable</th>
                <th class="c3 r">CGST</th>
                <th class="c4 r">SGST</th>
            </tr></thead>
            <tbody>
                <div class="line"></div>
                @foreach($taxByRate as $rate => $t)
                <tr>
                    <td>{{ number_format($rate,0) }}%</td>
                    <td class="r">{{ number_format($t['taxable'],2) }}</td>
                    <td class="r">{{ number_format($t['cgst'],2) }}</td>
                    <td class="r">{{ number_format($t['sgst'],2) }}</td>
                </tr>
                @endforeach
                <div class="line"></div>
                <tr class="total-row">
                    <td>Total</td>
                    <td class="r">{{ number_format($totalTaxable,2) }}</td>
                    <td class="r">{{ number_format($totalCgst,2) }}</td>
                    <td class="r">{{ number_format($totalSgst,2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    <!-- ═══ TOTAL GST + PAYMENT INFO ═══ -->
    <div class="rdk-payment">
        <div class="line"></div>
        <p>Total GST&nbsp;&nbsp;:&nbsp;&nbsp;{{ number_format($vat,2) }}</p>
        <p>Payment Mode&nbsp;&nbsp;:&nbsp;&nbsp;{{ $paymentMode }}</p>
        @if($givenAmt > 0)
        <p>Tender Amount&nbsp;&nbsp;:&nbsp;&nbsp;{{ number_format($givenAmt,2) }}</p>
        <p>Return Amount&nbsp;&nbsp;:&nbsp;&nbsp;{{ number_format($changeAmt,2) }}</p>
        @endif
    </div>

    <!-- ═══ POINTS (LOYALTY) SECTION ═══ -->
    @if(($sale->customer ?? null) && ($gramEarned > 0 || $gramRedeemed > 0 || $gramAvailable > 0))
    <div class="rdk-payment">
        <p>Previous Points&nbsp;&nbsp;:&nbsp;&nbsp;{{ number_format($gramPrevious,0) }}</p>
        <p>Points Earned&nbsp;&nbsp;:&nbsp;&nbsp;{{ number_format($gramEarned,0) }}</p>
        <p>Points Redeemed&nbsp;&nbsp;:&nbsp;&nbsp;{{ number_format($gramRedeemed,0) }}</p>
        <p>Available Points&nbsp;&nbsp;:&nbsp;&nbsp;{{ number_format($gramAvailable,0) }}</p>
    </div>
    @endif

    <!-- ═══ FOOTER ═══ -->
    <div class="rdk-footer">
        <div class="line"></div>
        <div class="thanks">THANKS FOR SHOPPING</div>
        <div class="store"><span style="font-family: freesans;">राशन की दुकान</span></div>
        <div class="noreturn">NO EXCHANGE, NO RETURN, NO REFUND</div>
        <div class="tc">*T&C APPLY&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;SCAN ME FOR LATEST OFFER</div>
        @if($sale->zatca_phase1_qr_code)
        <div class="qr">
            @php
                $qr = base64_decode($sale->zatca_phase1_qr_code);
                $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data='.urlencode($qr);
            @endphp
            <img src="{{ $qrUrl }}" alt="QR" />
        </div>
        @endif
    </div>

</body>
</html>
