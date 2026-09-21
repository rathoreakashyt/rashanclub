@extends('sale::pos.pos-layout')
@section('page-title', 'POS - Busy Mode')
@push('page-css')
<link rel="stylesheet" href="{{ asset('pos_assets/css/pos_busy.css') }}">
<style>
.busy-product-dropdown{position:absolute;z-index:1000;background:#fff;border:2px solid #2e7d32;border-radius:4px;box-shadow:0 6px 20px rgba(0,0,0,0.25);max-height:320px;overflow-y:auto;min-width:420px;display:none;}
.busy-product-dropdown.show{display:block;}
.busy-product-dropdown .dd-header{background:#2e7d32;color:#fff;padding:6px 10px;font-size:11px;font-weight:600;display:flex;gap:8px;}
.busy-product-dropdown .dd-header span{flex:1;}
.busy-product-dropdown .dd-item{display:flex;padding:7px 10px;border-bottom:1px solid #e0e0e0;cursor:pointer;font-size:12px;font-family:'Consolas',monospace;transition:background 0.05s;}
.busy-product-dropdown .dd-item:hover,.busy-product-dropdown .dd-item.active{background:#c8e6c9;}
.busy-product-dropdown .dd-item .dd-name{flex:2;font-weight:600;}
.busy-product-dropdown .dd-item .dd-code{flex:1;color:#666;}
.busy-product-dropdown .dd-item .dd-mrp{flex:0.7;text-align:right;}
.busy-product-dropdown .dd-item .dd-price{flex:0.7;text-align:right;color:#1b5e20;font-weight:600;}
.busy-product-dropdown .dd-item .dd-stock{flex:0.6;text-align:right;}
.busy-product-dropdown .dd-item .dd-stock.oos{color:#c62828;font-weight:600;}
.busy-product-dropdown .dd-empty{padding:12px;text-align:center;color:#999;font-size:12px;}
</style>
@endpush
@section('page-content')
<script>window.posPrinterSettings = @json($printer_settings ?? null);</script>
<div class="busy-pos">
    <!-- Top Header -->
    <div class="busy-header">
        <div class="busy-header-top">
            <span class="company-name">{{ session('company.business_name') ?? 'Rashan Ki Dukan' }}</span>
            <span>F.Y. {{ date('Y') }}-{{ date('Y', strtotime('+1 year')) }}</span>
        </div>
        <div class="busy-header-main">
            <div class="busy-header-left">
                <div class="title-section">
                    <span class="page-title">Sales - Add</span>
                    <span class="subtitle">Series - {{ session('outlet.outlet_name') ?? 'Counter 1' }}</span>
                </div>
            </div>
            <div class="info-item" style="margin-right:16px;">
                <span id="desktopLiveBadge"
                      style="display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;background:#FEF3C7;color:#92400E;border:1px solid #FCD34D;">
                    <span id="desktopLiveDot" style="width:8px;height:8px;border-radius:50%;background:#F59E0B;display:inline-block;"></span>
                    <span id="desktopLiveText">Desktop: checking…</span>
                </span>
            </div>
            <div class="busy-header-info">
                <div class="info-item"><label>Salesman:</label><span>{{ Auth::user()->name ?? 'User' }}</span></div>
                <div class="info-item"><label>User:</label><span>{{ substr(Auth::user()->name ?? 'U', 0, 1) }}</span></div>
                <div class="info-item"><label>State:</label><span>{{ session('outlet.state_name') ?? 'Delhi' }}</span></div>
            </div>
        </div>
    </div>

    <!-- Company Banner -->
    <div class="busy-company-banner">
        <div class="company-details">
            @php $logo = session('company.logo') ?? 'uploads/business_setting/initial-logo.png'; @endphp
            <img src="{{ asset('uploads/site_settings/' . $logo) }}" alt="Logo" class="company-logo" onerror="this.style.display='none'">
            <div class="company-text">
                <h1>{{ strtoupper(getWhiteLabel('site_name') ?? 'RASHAN KI DUKAN') }}</h1>
                <p>{{ getWhiteLabel('tagline') ?? 'Sasta & Achha' }}</p>
            </div>
        </div>
        <div class="company-meta">
            <span><strong>GSTIN:</strong> {{ session('company.tax_registration_no') ?? '-' }}</span>
            <span><strong>Date:</strong> {{ date('d-m-Y (D)') }}</span>
        </div>
    </div>

    <!-- Voucher Info Bar -->
    <div class="busy-voucher-bar">
        <div class="voucher-field">
            <label>Date:</label>
            <input type="text" id="busy-sale-date" value="{{ date('d-m-Y') }}" style="width:100px;" placeholder="dd-mm-yyyy">
        </div>
        <div class="voucher-field">
            <label>Vch. No:</label>
            <input type="text" id="busy-voucher-no" value="{{ $counterName ?? 'C-1' }}" readonly style="width:80px;background:#f0f0f0;">
        </div>
        <div class="voucher-field">
            <label>Sale Type:</label>
            <select id="busy-sale-type">
                <option value="local">Local - TaxIncl.</option>
                <option value="interstate">Interstate - TaxIncl.</option>
                <option value="exempt">Exempt</option>
            </select>
        </div>
        <div class="voucher-field" style="margin-left:auto;">
            <label>Employee:</label>
            @php $currentUser = Auth::user()->id; @endphp
            <select name="employee_id" id="employee-select" style="min-width:120px;">
                @foreach($employees as $employee)
                    <option value="{{ $employee->id }}" {{ $currentUser == $employee->id ? 'selected' : '' }}>{{ $employee->name }}</option>
                @endforeach
            </select>
            <input type="hidden" class="default_employee_id" value="{{ $currentUser }}">
        </div>
        <div class="voucher-field">
            <label>Customer:</label>
            @php $default_customer = session('company.default_customer'); @endphp
            <select name="customer_id" id="customer-select" style="min-width:150px;">
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" data-customer-type="{{ $customer->customer_type ?? '' }}" data-state-id="{{ $customer->state_id ?? '' }}" {{ $default_customer == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                @endforeach
            </select>
            <input type="hidden" id="selected-customer-type" value="{{ $customers->firstWhere('id', $default_customer)->customer_type ?? '' }}">
            <button class="btn btn-sm" style="background:var(--busy-primary);color:#fff;border:none;padding:2px 6px;border-radius:3px;" id="pos-add-customer-btn" data-bs-toggle="modal" data-bs-target="#modal_pos_customer">+</button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="busy-main">
        <div class="busy-table-area">
            <div class="busy-grid-wrapper" id="busy-grid-wrapper">
                <table class="busy-grid" id="busy-grid">
                    <thead>
                        <tr>
                            <th class="col-sno">S.No</th>
                            <th class="col-item">Item</th>
                            <th class="col-hsn">HSN Code</th>
                            <th class="col-mrp">MRP</th>
                            <th class="col-qty">Qty.</th>
                            <th class="col-unit">Unit</th>
                            <th class="col-price">List Price</th>
                            <th class="col-disc">Disc.</th>
                            <th class="col-amount">Amount</th>
                            <th class="col-tax">Tax(%)</th>
                        </tr>
                    </thead>
                    <tbody id="busy-cart-tbody">
                        @for($i = 1; $i <= 25; $i++)
                        <tr data-row="{{ $i }}" data-product-id="" data-product-type="" data-tax-type="" data-applicable-tax-id="">
                            <td class="sno-cell">{{ $i }}</td>
                            <td style="position:relative;">
                                <input type="text" class="grid-input text-left busy-item-input" data-col="item" placeholder="" autocomplete="off">
                                <div class="busy-product-dropdown" id="dd-row-{{ $i }}"></div>
                            </td>
                            <td><input type="text" class="grid-input text-center" data-col="hsn" readonly></td>
                            <td><input type="text" class="grid-input text-right" data-col="mrp" readonly></td>
                            <td><input type="text" class="grid-input text-center busy-qty-input" data-col="qty"></td>
                            <td><input type="text" class="grid-input text-center" data-col="unit" readonly></td>
                            <td><input type="text" class="grid-input text-right busy-price-input" data-col="price"></td>
                            <td><input type="text" class="grid-input text-center busy-disc-input" data-col="disc"></td>
                            <td><input type="text" class="grid-input text-right busy-amount-input" data-col="amount" readonly></td>
                            <td><input type="text" class="grid-input text-center" data-col="tax" readonly></td>
                        </tr>
                        @endfor
                        <tr class="busy-totals-row">
                            <td colspan="4" style="text-align:right;font-weight:700;">TOTAL:</td>
                            <td class="total-cell" id="busy-total-qty" style="text-align:center;">0</td>
                            <td></td><td></td>
                            <td class="total-cell" id="busy-total-amount" style="text-align:right;">0.00</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Bill Sundry & Total -->
            <div class="busy-bill-section">
                <div class="busy-bill-sundry">
                    <h4>Bill Sundry Details (Apply Tax - F4)</h4>
                    <div class="tax-lines" id="busy-tax-lines">
                        <div class="bill-line bill-line-empty">No taxable items</div>
                        <div class="bill-divider"></div>
                        <div class="tax-line tax-total-line"><span class="tax-label"><strong>CGST:</strong></span><span class="tax-value"><strong>0.00</strong></span></div>
                        <div class="tax-line tax-total-line"><span class="tax-label"><strong>SGST:</strong></span><span class="tax-value"><strong>0.00</strong></span></div>
                        <div class="tax-line tax-total-line"><span class="tax-label"><strong>IGST:</strong></span><span class="tax-value"><strong>0.00</strong></span></div>
                        <div class="bill-divider"></div>
                        <div class="tax-line tax-total-line"><span class="tax-label"><strong>Total Tax:</strong></span><span class="tax-value"><strong>0.00</strong></span></div>
                    </div>
                </div>
                <div class="busy-settlement">
                    <div class="settlement-header"><span>Settlement</span><span>Amount</span></div>
                    <div class="busy-total-box">
                        <div class="total-label">Total Amount -</div>
                        <div class="total-amount" id="busy-total-payable">0.00</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Sidebar -->
        <div class="busy-sidebar" id="busy-sidebar">
            <div class="busy-sidebar-section">
                <h5>F-Keys</h5>
                <div class="busy-sidebar-item" id="busy-f1-help"><kbd>F1</kbd><span class="shortcut-label">Help / Search</span></div>
                <div class="busy-sidebar-item" id="busy-f2-additem"><kbd>F2</kbd><span class="shortcut-label">Add Item</span></div>
                <div class="busy-sidebar-item" id="busy-f3-addmaster"><kbd>F3</kbd><span class="shortcut-label">Add Master</span></div>
                <div class="busy-sidebar-item" id="busy-f4-tax"><kbd>F4</kbd><span class="shortcut-label">Apply Tax</span></div>
                <div class="busy-sidebar-item" id="busy-f5-payment"><kbd>F5</kbd><span class="shortcut-label">Payment</span></div>
                <div class="busy-sidebar-item" id="busy-f7-hold"><kbd>F7</kbd><span class="shortcut-label">Hold Vch.</span></div>
                <div class="busy-sidebar-item" id="busy-f9-del"><kbd>F9</kbd><span class="shortcut-label">Del. Line</span></div>
            </div>
            <div class="busy-sidebar-section">
                <h5>Shortcuts</h5>
                <div class="busy-sidebar-item single-char" id="busy-sc-save"><kbd>S</kbd><span class="shortcut-label">Save</span></div>
                <div class="busy-sidebar-item single-char" id="busy-sc-clear"><kbd>C</kbd><span class="shortcut-label">Clear All</span></div>
                <div class="busy-sidebar-item single-char" id="busy-sc-fullscreen"><kbd>F</kbd><span class="shortcut-label">Fullscreen</span></div>
            </div>
        </div>
    </div>

    <!-- Bottom Action Bar -->
    <div class="busy-action-bar">
        <div class="action-buttons">
            <button class="busy-action-btn" id="busy-btn-saleslist"><i class="icon-base ti tabler-receipt"></i> Sales List</button>
            <button class="busy-action-btn" id="busy-btn-hold"><i class="icon-base ti tabler-player-pause"></i> Hold Vch.</button>
            <button class="busy-action-btn" id="busy-btn-register"><i class="icon-base ti tabler-cash-register"></i> Register</button>
            <button class="busy-action-btn" id="busy-btn-refresh"><i class="icon-base ti tabler-refresh"></i> Refresh</button>
            <button class="busy-action-btn save-btn" id="busy-btn-save"><i class="icon-base ti tabler-check"></i> Save</button>
            <button class="busy-action-btn quit-btn" id="busy-btn-quit"><i class="icon-base ti tabler-x"></i> Quit</button>
        </div>
        <div class="busy-shortcut-hints">
            <span><kbd>Tab</kbd> Next</span>
            <span><kbd>Enter</kbd> Select</span>
            <span><kbd>Esc</kbd> Close</span>
            <span><kbd>F5</kbd> Pay</span>
            <span><kbd>F9</kbd> Del</span>
            <span><kbd>Ctrl+S</kbd> Save</span>
        </div>
    </div>

    <!-- Status Bar -->
    <div class="busy-status-bar">
        <div class="status-left">
            <span class="busy-status-item"><span class="brand-logo">Busy</span></span>
            <span class="busy-status-item"><label>Firm:</label> {{ session('company.business_name') ?? 'Rashan Ki Dukan' }}</span>
            <span class="busy-status-item"><label>F.Y.:</label> {{ date('Y') }}-{{ date('Y', strtotime('+1 year')) }}</span>
            <span class="busy-status-item"><label>User:</label> {{ substr(Auth::user()->name ?? 'B', 0, 1) }}</span>
            <span class="busy-status-item"><label>State:</label> {{ session('outlet.state_name') ?? 'Delhi' }}</span>
        </div>
        <div class="status-right">
            <span class="busy-status-item"><label>GSTIN:</label> {{ session('company.tax_registration_no') ?? '-' }}</span>
            <span class="busy-status-item" id="busy-clock"><label>Date & Time:</label> {{ date('d-m-Y H:i') }}</span>
        </div>
    </div>
</div>

<button class="busy-sidebar-toggle" id="busy-sidebar-toggle"><i class="icon-base ti tabler-keyboard"></i></button>

@endsection
@push('page-js')
@routes
<script>
window.posOutletId = @json(session('outlet.outlet_id'));
window.posCompanyId = @json(session('company.company_id'));
window.posOutletStateCode = @json(optional($outlet)->state_code);
window.posOutletStateId = @json(optional($outlet)->state_id);
window.posTaxs = @json($taxs ?? []);
window.posStates = @json($states->map(fn($s) => ['id' => $s->id, 'state_code' => $s->state_code])->values()->toArray());
window.posCustomersWithState = @json($customers->keyBy('id')->map(fn($c) => ['id' => $c->id, 'state_id' => $c->state_id])->toArray());
window.posDefaultCustomer = @json(session('company.default_customer'));
window.posDirectCart = @json(session('company.direct_cart') ?? 'No');
window.posAllowLessSale = @json(session('company.allow_less_sale') ?? 'No');
window.posSaleTaxType = @json(session('company.sale_tax_type') ?? 'Inclusive');
</script>
<script src="{{ asset('pos_assets/js/busy_pos.js') }}"></script>
<script>
// ═══ DESKTOP LIVE BADGE — web POS par desktop software ka live status ═══
// Har 30s me /api/live-status poll — desktop ne pichhle 5 min me sync ki
// ho to GREEN "Desktop: Live", warna amber "Desktop: hh:mm last sync".
(function () {
    var dot = document.getElementById('desktopLiveDot');
    var txt = document.getElementById('desktopLiveText');
    var badge = document.getElementById('desktopLiveBadge');
    if (!dot || !txt || !badge) return;

    function setStyle(bg, color, border, dotColor) {
        badge.style.background = bg; badge.style.color = color; badge.style.borderColor = border;
        dot.style.background = dotColor;
    }

    function poll() {
        fetch('/api/live-status', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d) { setStyle('#FEE2E2', '#B91C1C', '#FCA5A5', '#EF4444'); txt.textContent = 'Desktop: offline'; return; }
                if (d.any_live) {
                    setStyle('#DCFCE7', '#166534', '#86EFAC', '#10B981');
                    txt.textContent = 'Desktop: Live' + (d.devices && d.devices[0] && d.devices[0].last_sync ? ' · ' + d.devices[0].last_sync.slice(11, 19) : '');
                } else {
                    var last = d.devices && d.devices[0] ? d.devices[0].last_sync : null;
                    setStyle('#FEF3C7', '#92400E', '#FCD34D', '#F59E0B');
                    txt.textContent = 'Desktop: ' + (last ? last.slice(11, 19) + ' last sync' : 'no sync yet');
                }
            })
            .catch(function () {
                setStyle('#FEE2E2', '#B91C1C', '#FCA5A5', '#EF4444');
                txt.textContent = 'Desktop: offline';
            });
    }

    poll();
    setInterval(poll, 30000);
})();
</script>
@if(!empty($editSaleId))
<script>window.posEditSaleId = @json($editSaleId);</script>
@endif
@endpush
