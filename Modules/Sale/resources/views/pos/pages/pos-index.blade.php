@extends('sale::pos.pos-layout')
@section('page-title', 'Point of Sale (POS)')
@push('page-css')
<link rel="stylesheet" href="{{ asset('pos_assets/css/pos_busy.css') }}">
<style>
.pos-logo { height: 32px; width: auto; margin-right: 8px; filter: brightness(0) invert(1); }
.pos-header-brand { display: flex; align-items: center; gap: 8px; margin-right: 12px; padding-right: 12px; border-right: 1px solid rgba(255,255,255,0.2); }
.pos-header-brand span { color: #fff; font-weight: 700; font-size: 15px; }
.pos-header-time { color: rgba(255,255,255,0.8); font-size: 12px; font-weight: 500; padding: 4px 10px; background: rgba(255,255,255,0.1); border-radius: 20px; margin-right: 8px; }

/* POS Main Layout */
.pos-container { display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
.pos-header { display: flex; align-items: center; justify-content: space-between; padding: 0 12px; height: 52px; background: linear-gradient(135deg, #B71C1C 0%, #7F0000 100%); color: #fff; flex-shrink: 0; z-index: 10; }
.pos-header-left { display: flex; align-items: center; gap: 8px; }
.pos-header-right { display: flex; align-items: center; gap: 6px; }
.pos-header-icon { width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 8px; cursor: pointer; transition: all 0.2s; color: #fff; }
.pos-header-icon:hover { background: rgba(255,255,255,0.15); }
.pos-header-icon span { display: flex; align-items: center; justify-content: center; }

/* Main Content - Cart + Sidebar */
.pos-main-content { display: flex; flex: 1; overflow: hidden; }

/* Cart Panel */
.pos-cart-panel { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.pos-cart-header { padding: 8px 12px; border-bottom: 1px solid #e1e5eb; display: flex; align-items: center; gap: 8px; background: #fafbfc; flex-shrink: 0; }
.pos-cart-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.pos-cart-items { flex: 1; padding: 0; overflow-y: auto; }
.pos-cart-summary { padding: 8px 12px; border-top: 1px solid #e1e5eb; background: #fafbfc; flex-shrink: 0; }
.pos-total-amount { display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: linear-gradient(135deg, #f8e8e8 0%, #fce8e8 100%); border-top: 2px solid #D32F2F; flex-shrink: 0; }

/* Right - Shortcut Sidebar - Busy Style */
.pos-shortcut-sidebar { width: 170px; display: flex; flex-direction: column; background: #f0f0f0; border-left: 1px solid #aaa; overflow-y: auto; font-size: 12px; flex-shrink: 0; padding: 4px; gap: 2px; }
.pos-shortcut-item { display: flex; align-items: center; gap: 0; padding: 5px 8px; cursor: pointer; border: 1px solid #888; border-radius: 3px; background: #fff; transition: background 0.1s; text-decoration: none; }
.pos-shortcut-item:hover { background: #e8e8e8; }
.pos-shortcut-item kbd { display: inline-block; background: transparent; border: none; padding: 0; font-family: 'Consolas', 'Courier New', monospace; font-size: 12px; font-weight: 700; min-width: 30px; text-align: left; text-decoration: underline; color: #000; box-shadow: none; }
.pos-shortcut-item .sc-label { color: #000; font-size: 12px; font-weight: 400; margin-left: 2px; }
.pos-shortcut-item.sc-single kbd { min-width: 20px; text-decoration: underline; background: transparent; border: none; color: #000; font-weight: 700; }

/* Footer */
.pos-footer-actions { display: flex; align-items: center; justify-content: space-between; padding: 6px 12px; background: linear-gradient(180deg, #e0e0e0 0%, #bdbdbd 100%); border-top: 1px solid #999; flex-shrink: 0; }
.pos-footer-actions .left-part { display: flex; gap: 6px; }
.pos-footer-actions .right-part { display: flex; gap: 6px; align-items: center; font-size: 11px; color: #555; }

/* Status Bar */
.pos-status-bar { display: flex; align-items: center; justify-content: space-between; padding: 3px 12px; background: linear-gradient(180deg, #2e7d32 0%, #1b5e20 100%); color: #fff; font-size: 11px; flex-shrink: 0; }
.pos-status-bar .status-left, .pos-status-bar .status-right { display: flex; align-items: center; gap: 16px; }
.pos-status-item { display: flex; align-items: center; gap: 4px; }
.pos-status-item label { opacity: 0.7; }

/* Cart summary rows */
.pos-summary-row { display: flex; justify-content: space-between; align-items: center; padding: 3px 0; font-size: 12px; }
.pos-summary-label { color: #666; font-weight: 500; }
.pos-summary-value { font-weight: 600; }
.pos-summary-input { width: 80px; padding: 3px 6px; border: 1px solid #e1e5eb; border-radius: 4px; font-size: 12px; text-align: right; }
.discount-input-group { display: flex; align-items: center; gap: 4px; }
.discount-type-select { height: 26px; border: 1px solid #e1e5eb; border-radius: 4px; width: 40px; font-size: 11px; }
.pos-cash-register-btn { padding: 4px 12px; font-size: 12px; }

/* ============================================
   GRID TABLE - Busy POS Style Text Fields
   ============================================ */
.pos-grid-table { width: 100%; border-collapse: collapse; font-size: 12px; font-family: 'Consolas', 'Courier New', monospace; }
.pos-grid-table thead { position: sticky; top: 0; z-index: 5; }
.pos-grid-table thead th { background: linear-gradient(180deg, #4caf50 0%, #388e3c 100%); color: #fff; border: 1px solid #2e7d32; padding: 6px 6px; font-weight: 600; font-size: 11px; text-align: center; white-space: nowrap; user-select: none; }
.pos-grid-table .col-sno { width: 38px; }
.pos-grid-table .col-item { min-width: 180px; text-align: left; }
.pos-grid-table .col-hsn { width: 80px; }
.pos-grid-table .col-mrp { width: 70px; }
.pos-grid-table .col-qty { width: 55px; }
.pos-grid-table .col-unit { width: 50px; }
.pos-grid-table .col-price { width: 80px; }
.pos-grid-table .col-disc { width: 60px; }
.pos-grid-table .col-amount { width: 90px; }
.pos-grid-table .col-tax { width: 55px; }
.pos-grid-table tbody tr { background: #fff; transition: background 0.05s; }
.pos-grid-table tbody tr:nth-child(even) { background: #f5f9f5; }
.pos-grid-table tbody tr:hover { background: #c8e6c9 !important; }
.pos-grid-table tbody tr.pos-row-filled { background: #e8f5e9 !important; }
.pos-grid-table tbody tr.pos-row-selected { background: #a5d6a7 !important; outline: 2px solid #B71C1C; outline-offset: -2px; }
.pos-grid-table tbody td { border: 1px solid #cfd8dc; padding: 0; vertical-align: middle; font-size: 12px; }
.pos-grid-table tbody td.sno-cell { text-align: center; font-weight: 600; background: #e8f5e9; color: #666; font-size: 11px; }

/* Grid Inputs - Text Field Style */
.pos-grid-input { width: 100%; border: none; outline: none; padding: 5px 6px; font-size: 12px; font-family: 'Consolas', monospace; background: transparent; }
.pos-grid-input:focus { background: #fffde7; }
.pos-grid-input.text-left { text-align: left; }
.pos-grid-input.text-center { text-align: center; }
.pos-grid-input.text-right { text-align: right; }
.pos-grid-input[readonly] { color: #555; cursor: default; }

/* Filled row styling */
.pos-row-filled .sno-cell { background: #c8e6c9; color: #1b5e20; }

/* ============================================
   PRODUCT DROPDOWN - Inline Grid Search
   ============================================ */
.pos-product-dropdown { position: absolute; z-index: 1000; background: #fff; border: 2px solid #2e7d32; border-radius: 4px; box-shadow: 0 6px 20px rgba(0,0,0,0.25); max-height: 280px; overflow-y: auto; min-width: 420px; display: none; }
.pos-product-dropdown.show { display: block; }
.pos-product-dropdown .dd-header { background: #2e7d32; color: #fff; padding: 6px 10px; font-size: 11px; font-weight: 600; display: flex; gap: 8px; }
.pos-product-dropdown .dd-header span { flex: 1; }
.pos-product-dropdown .dd-item { display: flex; padding: 7px 10px; border-bottom: 1px solid #e0e0e0; cursor: pointer; font-size: 12px; font-family: 'Consolas', monospace; transition: background 0.05s; }
.pos-product-dropdown .dd-item:hover, .pos-product-dropdown .dd-item.active { background: #c8e6c9; }
.pos-product-dropdown .dd-item .dd-name { flex: 2; font-weight: 600; }
.pos-product-dropdown .dd-item .dd-code { flex: 1; color: #666; }
.pos-product-dropdown .dd-item .dd-mrp { flex: 0.7; text-align: right; }
.pos-product-dropdown .dd-item .dd-price { flex: 0.7; text-align: right; color: #1b5e20; font-weight: 600; }
.pos-product-dropdown .dd-item .dd-stock { flex: 0.6; text-align: right; }
.pos-product-dropdown .dd-item .dd-stock.oos { color: #c62828; font-weight: 600; }
.pos-product-dropdown .dd-empty { padding: 12px; text-align: center; color: #999; font-size: 12px; }

/* Hide empty cart placeholder - grid always shows */
.pos-empty-cart, #pos-empty-cart { display: none !important; visibility: hidden !important; }
.pos-cart-table, .pos-cart-table.pos-grid-table, table.pos-cart-table { display: table !important; visibility: visible !important; opacity: 1 !important; }
.pos-cart-items { overflow-y: auto !important; }
.pos-cart-content { display: flex !important; flex-direction: column !important; }

/* Responsive */
@media (max-width: 1200px) { .pos-shortcut-sidebar { width: 150px; } }
@media (max-width: 992px) {
    .pos-shortcut-sidebar { display: none; position: fixed; right: 0; top: 0; bottom: 0; z-index: 100; width: 200px; box-shadow: -4px 0 12px rgba(0,0,0,0.2); }
    .pos-shortcut-sidebar.show { display: flex; }
}
@media (max-width: 768px) {
    .pos-footer-actions .mobile-text { display: none; }
}
</style>
@endpush
@section('page-content')
<script>window.posPrinterSettings = @json($printer_settings ?? null);</script>
<div class="pos-container">
    <!-- Header -->
    <div class="pos-header">
        <div class="pos-header-left">
            <div class="pos-header-brand">
                @php $logo = session('company.logo') ?? 'uploads/business_setting/initial-logo.png'; @endphp
                <img src="{{ asset('uploads/site_settings/' . $logo) }}" alt="Logo" class="pos-logo" onerror="this.style.display='none'">
                <span>{{ strtoupper(getWhiteLabel('site_name') ?? 'POS') }}</span>
            </div>
            <a class="pos-header-icon" href="javascript:void(0);" id="pos-menu-search-toggler" title="Search (Ctrl+K)">
                <span><i class="icon-base ti tabler-search"></i></span>
            </a>
            <a href="{{ url('/dashboard') }}" target="_blank" class="pos-header-icon" title="Dashboard">
                <span><i class="icon-base ti tabler-dashboard"></i></span>
            </a>
        </div>
        <div class="pos-header-right">
            <div class="pos-header-time" id="pos-current-time"></div>
            <div class="pos-header-icon" id="pos-print-last-invoice-btn" title="Print Last Invoice">
                <span><i class="icon-base ti tabler-printer"></i></span>
            </div>
            <div class="pos-header-icon customer-display-btn" title="Customer Display">
                <span><i class="icon-base ti tabler-device-desktop"></i></span>
            </div>
            <div class="pos-header-icon" id="pos-calculator-btn" title="Calculator">
                <span><i class="icon-base ti tabler-calculator"></i></span>
            </div>
            <div class="pos-header-icon" id="pos-fullscreen-btn" title="Fullscreen">
                <span><i class="icon-base ti tabler-maximize" id="pos-fullscreen-icon"></i></span>
            </div>
            <div class="pos-header-icon" id="pos-sync-products-btn" title="Re-sync Products">
                <span><i class="icon-base ti tabler-database"></i></span>
            </div>
            <div class="pos-header-icon logout-btn" title="Logout">
                <span><i class="icon-base ti tabler-power"></i></span>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="pos-main-content">
        <!-- Left: Cart -->
        <div class="pos-cart-panel">
            <div class="pos-cart-header">
                <div class="employee-section" style="flex:1;">
                    @php $currentUser = Auth::user()->id; @endphp
                    <select name="employee_id" id="employee-select" class="form-select" style="font-size:12px;padding:4px 8px;">
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" {{ $currentUser == $employee->id ? 'selected' : '' }}>{{ $employee->name }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" class="default_employee_id" value="{{ $currentUser }}">
                </div>
                <div class="customer-section" style="flex:1;">
                    @php $default_customer = session('company.default_customer'); @endphp
                    <select name="customer_id" id="customer-select" class="form-select" style="font-size:12px;padding:4px 8px;">
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" data-customer-type="{{ $customer->customer_type ?? '' }}" data-state-id="{{ $customer->state_id ?? '' }}" {{ $default_customer == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" id="selected-customer-type" value="{{ $customers->firstWhere('id', $default_customer)->customer_type ?? '' }}">
                </div>
                <button class="pos-add-btn" id="pos-edit-customer-btn" title="Edit Customer" style="padding:4px 8px;font-size:11px;">
                    <i class="icon-base ti tabler-edit"></i>
                </button>
                <button class="pos-add-btn" id="pos-add-customer-btn" data-bs-toggle="modal" data-bs-target="#modal_pos_customer" title="Add Customer" style="padding:4px 8px;font-size:11px;">
                    <i class="icon-base ti tabler-user-plus"></i>
                </button>
            </div>
            <div class="pos-cart-content">
                <div class="pos-cart-items thin-scroll" id="pos-cart-items">
                    <table class="pos-cart-table pos-grid-table">
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
                        <tbody id="pos-cart-items-tbody">
                            @for($i = 1; $i <= 10; $i++)
                            <tr data-row="{{ $i }}" data-product-id="" class="pos-empty-row">
                                <td class="sno-cell">{{ $i }}</td>
                                <td style="position:relative;">
                                    <input type="text" class="pos-grid-input text-left pos-item-input" data-col="item" placeholder="" autocomplete="off">
                                    <div class="pos-product-dropdown" id="dd-row-{{ $i }}"></div>
                                </td>
                                <td><input type="text" class="pos-grid-input text-center" data-col="hsn" readonly></td>
                                <td><input type="text" class="pos-grid-input text-right" data-col="mrp" readonly></td>
                                <td><input type="text" class="pos-grid-input text-center pos-qty-input" data-col="qty"></td>
                                <td><input type="text" class="pos-grid-input text-center" data-col="unit" readonly></td>
                                <td><input type="text" class="pos-grid-input text-right pos-price-input" data-col="price"></td>
                                <td><input type="text" class="pos-grid-input text-center pos-disc-input" data-col="disc"></td>
                                <td><input type="text" class="pos-grid-input text-right pos-amount-input" data-col="amount" readonly></td>
                                <td><input type="text" class="pos-grid-input text-center" data-col="tax" readonly></td>
                            </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
                <div class="pos-cart-summary">
                    <div class="pos-summary-row">
                        <span class="pos-summary-label">Subtotal</span>
                        <span class="pos-summary-value" id="pos-subtotal">0.00</span>
                    </div>
                    <div class="pos-summary-row tax-summary-row">
                        <div class="pos-summary-label"><span>Tax</span> <span class="tax-view-icon"><i class="ti tabler-eye tax-view-btn" style="cursor:pointer;font-size:14px;color:#D32F2F;"></i></span></div>
                        <span class="pos-summary-value"><span id="pos-tax">0.00</span> <small class="text-muted" id="pos-tax-inclusive" style="display:none;"></small></span>
                    </div>
                    <div class="pos-summary-row">
                        <span class="pos-summary-label">Discount</span>
                        <div class="discount-input-group">
                            <span class="show-total-discount-value"></span>
                            <select class="discount-type-select" id="pos-cart-discount-type"><option value="fixed">$</option><option value="percentage">%</option></select>
                            <input type="text" class="pos-summary-input" id="pos-cart-discount-input" value="0" min="0">
                        </div>
                    </div>
                    <div class="pos-summary-row">
                        <span class="pos-summary-label">Shipping</span>
                        <input type="text" class="pos-summary-input" id="pos-shipping-input" value="0">
                    </div>
                    <div class="pos-summary-row">
                        <span class="pos-summary-label">Coupon</span>
                        <div style="display:flex;gap:4px;">
                            <input type="text" class="pos-summary-input" id="pos-coupon-input" placeholder="Code" style="width:60px;">
                            <button class="btn btn-sm btn-primary" id="pos-coupon-apply-btn" style="padding:2px 8px;font-size:11px;">Apply</button>
                        </div>
                        <small class="text-muted" id="pos-coupon-message"></small>
                    </div>
                </div>
                <div class="pos-total-amount">
                    <div class="total-payable">
                        <span class="pos-total-label">Total Payable</span>
                        <span class="pos-total-value" id="pos-total-amount" style="font-size:20px;font-weight:700;color:#B71C1C;">0.00</span>
                    </div>
                    <div style="display:flex;gap:6px;">
                        <button class="btn btn-success btn-sm" id="pos-cash-register-btn"><i class="icon-base ti tabler-cash"></i> Quick Cash</button>
                        <button class="btn btn-primary btn-sm" id="pos-payment-btn"><i class="icon-base ti tabler-credit-card"></i> Payment</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Shortcut Sidebar -->
        <div class="pos-shortcut-sidebar" id="pos-shortcut-sidebar">
            <div class="pos-shortcut-item" onclick="document.getElementById('pos-menu-search-toggler')?.click()"><kbd>F1</kbd><span class="sc-label">Help</span></div>
            <div class="pos-shortcut-item" onclick="document.getElementById('pos-add-customer-btn')?.click()"><kbd>F1</kbd><span class="sc-label">Add Account</span></div>
            <div class="pos-shortcut-item" onclick="document.getElementById('pos-product-search')?.focus()"><kbd>F2</kbd><span class="sc-label">Add Item</span></div>
            <div class="pos-shortcut-item" onclick="document.querySelector('.tax-view-btn')?.click()"><kbd>F3</kbd><span class="sc-label">Add Master</span></div>
            <div class="pos-shortcut-item" onclick="document.querySelector('.tax-view-btn')?.click()"><kbd>F4</kbd><span class="sc-label">Add Meather</span></div>
            <div class="pos-shortcut-item" onclick="document.getElementById('pos-payment-btn')?.click()"><kbd>F5</kbd><span class="sc-label">Add Payment</span></div>
            <div class="pos-shortcut-item" onclick="document.querySelector('.add-hold-btn')?.click()"><kbd>F6</kbd><span class="sc-label">Add Receipt</span></div>
            <div class="pos-shortcut-item" onclick="document.querySelector('.list-hold-btn')?.click()"><kbd>F7</kbd><span class="sc-label">Add Receipt</span></div>
            <div class="pos-shortcut-item" onclick="document.querySelector('[data-bs-target=\\'#modal_pos_sales\\']')?.click()"><kbd>F8</kbd><span class="sc-label">Add Sales</span></div>
            <div class="pos-shortcut-item" onclick="document.querySelector('[data-bs-target=\\'#modal_pos_sale_returns\\']')?.click()"><kbd>F9</kbd><span class="sc-label">Add Purchase</span></div>

            <div style="height:8px;border-bottom:1px solid #aaa;margin:4px 0;"></div>

            <div class="pos-shortcut-item sc-single" onclick=""><kbd>B</kbd><span class="sc-label">Balance Sheet</span></div>
            <div class="pos-shortcut-item sc-single" onclick=""><kbd>I</kbd><span class="sc-label">Trial Balance</span></div>
            <div class="pos-shortcut-item sc-single" onclick=""><kbd>S</kbd><span class="sc-label">Stock Status</span></div>
            <div class="pos-shortcut-item sc-single" onclick=""><kbd>A</kbd><span class="sc-label">Acc. Summary</span></div>
            <div class="pos-shortcut-item sc-single" onclick=""><kbd>L</kbd><span class="sc-label">Acc. Lodger</span></div>
            <div class="pos-shortcut-item sc-single" onclick=""><kbd>I</kbd><span class="sc-label">Item Summary</span></div>
        </div>
    </div>

    <!-- Footer -->
    <div class="pos-footer-actions">
        <div class="left-part">
            <button type="button" class="add-hold-btn btn btn-warning" style="font-size:11px;padding:4px 10px;"><i class="icon-base ti tabler-player-pause"></i> Hold</button>
            <button type="button" class="list-hold-btn btn btn-warning" style="font-size:11px;padding:4px 10px;"><i class="icon-base ti tabler-list"></i> List</button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal_pos_sales" style="font-size:11px;padding:4px 10px;"><i class="icon-base ti tabler-receipt"></i> Sales</button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal_pos_sale_returns" style="font-size:11px;padding:4px 10px;"><i class="icon-base ti tabler-arrow-left-right"></i> Return</button>
            <button class="btn btn-primary" id="pos-register-btn" style="font-size:11px;padding:4px 10px;"><i class="icon-base ti tabler-cash-register"></i> Register</button>
            <button class="btn btn-danger clear-cart-btn" style="font-size:11px;padding:4px 10px;"><i class="icon-base ti tabler-trash"></i> Clear</button>
        </div>
        <div class="right-part">
            <span>Products: <strong class="text-primary" id="pos-total-products-count">{{ number_format($totalProducts ?? 0) }}</strong></span>
            <span id="pos-product-load-progress-wrap" style="display:none;">
                <span id="pos-product-load-progress-text">0%</span>
            </span>
        </div>
    </div>

    <!-- Status Bar -->
    <div class="pos-status-bar">
        <div class="status-left">
            <span class="pos-status-item"><label>Firm:</label> {{ session('company.business_name') ?? 'POS' }}</span>
            <span class="pos-status-item"><label>F.Y.:</label> {{ date('Y') }}-{{ date('Y', strtotime('+1 year')) }}</span>
            <span class="pos-status-item"><label>User:</label> {{ substr(Auth::user()->name ?? 'U', 0, 1) }}</span>
            <span class="pos-status-item"><label>State:</label> {{ session('outlet.state_name') ?? '' }}</span>
        </div>
        <div class="status-right">
            <span class="pos-status-item"><label>GSTIN:</label> {{ session('company.tax_registration_no') ?? '-' }}</span>
            <span class="pos-status-item" id="pos-status-clock"><label>Date & Time:</label> {{ date('d-m-Y H:i') }}</span>
        </div>
    </div>
</div>

<!-- Mobile Sidebar Toggle -->
<button class="busy-sidebar-toggle" id="pos-sidebar-toggle" style="display:none;"><i class="icon-base ti tabler-keyboard"></i></button>

@endsection
@push('page-js')
@routes
<script>
    window.posOutletStateCode = @json(optional($outlet)->state_code);
    window.posOutletStateId = @json(optional($outlet)->state_id);
    window.posTaxs = @json($taxs ?? []);
    window.posStates = @json($states->map(fn($s) => ['id' => $s->id, 'state_code' => $s->state_code])->values()->toArray());
    window.posCustomersWithState = @json($customers->keyBy('id')->map(fn($c) => ['id' => $c->id, 'state_id' => $c->state_id])->toArray());

    // Clock
    function updatePOSClock() {
        const now = new Date();
        const time = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        const el = document.getElementById('pos-current-time');
        if (el) el.textContent = time;
        const el2 = document.getElementById('pos-status-clock');
        if (el2) el2.innerHTML = '<label>Date & Time:</label> ' + now.toLocaleDateString('en-GB') + ' ' + time;
    }
    updatePOSClock();
    setInterval(updatePOSClock, 1000);

    // Sidebar toggle (mobile)
    document.getElementById('pos-sidebar-toggle')?.addEventListener('click', function() {
        document.getElementById('pos-shortcut-sidebar')?.classList.toggle('show');
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA') return;
        switch(e.key) {
            case 'F1': e.preventDefault(); document.getElementById('pos-menu-search-toggler')?.click(); break;
            case 'F2': e.preventDefault(); document.getElementById('pos-product-search')?.focus(); break;
            case 'F3': e.preventDefault(); document.getElementById('pos-add-customer-btn')?.click(); break;
            case 'F4': e.preventDefault(); document.querySelector('.tax-view-btn')?.click(); break;
            case 'F5': e.preventDefault(); document.getElementById('pos-payment-btn')?.click(); break;
            case 'F7': e.preventDefault(); document.querySelector('.add-hold-btn')?.click(); break;
            case 'F8': e.preventDefault(); document.querySelector('.list-hold-btn')?.click(); break;
            case 'F9': e.preventDefault(); document.querySelector('.clear-cart-btn')?.click(); break;
            case 'F10': e.preventDefault(); document.querySelector('[data-bs-target="#modal_pos_sales"]')?.click(); break;
            case 'F11': e.preventDefault(); document.querySelector('[data-bs-target="#modal_pos_sale_returns"]')?.click(); break;
            case 'F12': e.preventDefault(); document.getElementById('pos-register-btn')?.click(); break;
        }
    });
</script>
@if(!empty($editSaleId))
<script>window.posEditSaleId = @json($editSaleId);</script>
@endif
<script src="{{ asset('pos_assets/js/pos_search.js') }}"></script>
@if(config('zatca.enabled', false))
<script src="{{ asset('pos_assets/js/pos_zatca.js') }}"></script>
@endif

<script>
/**
 * POS Grid Manager - Text-field style grid with auto-expansion
 * Works alongside pos_cart.js: fills grid rows, syncs to posCartManager for payment
 */
(function() {
    'use strict';

    let gridProducts = [];
    let gridDDIndex = -1;
    let gridSearchTimeout = null;
    let gridRowCounter = 10;
    let DB = null;
    const INITIAL_ROWS = 10;
    const MIN_VISIBLE_ROWS = 10;

    document.addEventListener('DOMContentLoaded', function() {
        DB = new POSIndexedDB();

        const tbody = document.getElementById('pos-cart-items-tbody');
        if (tbody) {
            tbody.querySelectorAll('tr:not([data-row])').forEach(tr => tr.remove());
        }
        setupGridListeners();
        setupGridKeyboard();
        ensureEmptyRows();
        const cartTable = document.querySelector('.pos-cart-table');
        if (cartTable) { cartTable.style.display = 'table'; cartTable.removeAttribute('style'); }
        const emptyCart = document.getElementById('pos-empty-cart');
        if (emptyCart) emptyCart.style.display = 'none';

        loadGridProducts();
    });

    async function loadGridProducts() {
        try {
            await DB.init();
            gridProducts = await DB.getAllProducts();
            if (!gridProducts || gridProducts.length === 0) {
                await DB.fetchAndStoreAllProducts();
                gridProducts = await DB.getAllProducts();
            }
            console.log('[POSGrid] Products loaded:', gridProducts.length);
        } catch (e) {
            console.error('[POSGrid] Failed to load products:', e);
        }
    }

    // Search from IndexedDB directly if gridProducts not loaded yet
    async function searchGridProductsAsync(term) {
        if (!term || term.length < 1) return [];
        // Use gridProducts if loaded
        if (gridProducts && gridProducts.length > 0) {
            return searchGridProducts(term);
        }
        // Fallback: search from IndexedDB directly
        try {
            const results = await DB.searchProducts(term);
            return results.filter(p => {
                const stock = parseFloat(p.stock) || 0;
                return stock > 0;
            }).slice(0, 20);
        } catch (e) {
            return [];
        }
    }

    function ensureEmptyRows() {
        const tbody = document.getElementById('pos-cart-items-tbody');
        const existingRows = tbody.querySelectorAll('tr[data-row]');
        const filledCount = tbody.querySelectorAll('tr[data-row][data-product-id]').length;
        const totalNeeded = Math.max(MIN_VISIBLE_ROWS, filledCount + 1);
        gridRowCounter = Math.max(gridRowCounter, totalNeeded);

        for (let i = existingRows.length + 1; i <= gridRowCounter; i++) {
            tbody.appendChild(createEmptyRow(i));
        }
    }

    function createEmptyRow(num) {
        const tr = document.createElement('tr');
        tr.setAttribute('data-row', num);
        tr.setAttribute('data-product-id', '');
        tr.className = 'pos-empty-row';
        tr.innerHTML = `
            <td class="sno-cell">${num}</td>
            <td style="position:relative;">
                <input type="text" class="pos-grid-input text-left pos-item-input" data-col="item" placeholder="" autocomplete="off">
                <div class="pos-product-dropdown" id="dd-row-${num}"></div>
            </td>
            <td><input type="text" class="pos-grid-input text-center" data-col="hsn" readonly></td>
            <td><input type="text" class="pos-grid-input text-right" data-col="mrp" readonly></td>
            <td><input type="text" class="pos-grid-input text-center pos-qty-input" data-col="qty"></td>
            <td><input type="text" class="pos-grid-input text-center" data-col="unit" readonly></td>
            <td><input type="text" class="pos-grid-input text-right pos-price-input" data-col="price"></td>
            <td><input type="text" class="pos-grid-input text-center pos-disc-input" data-col="disc"></td>
            <td><input type="text" class="pos-grid-input text-right pos-amount-input" data-col="amount" readonly></td>
            <td><input type="text" class="pos-grid-input text-center" data-col="tax" readonly></td>
        `;
        return tr;
    }

    function searchGridProducts(term) {
        if (!term || term.length < 1) return [];
        const t = term.toLowerCase();
        return gridProducts.filter(p => {
            return (p.name && p.name.toLowerCase().includes(t)) ||
                   (p.code && p.code.toLowerCase().includes(t)) ||
                   (p.alternative_name && p.alternative_name.toLowerCase().includes(t)) ||
                   (p.generic_name && p.generic_name.toLowerCase().includes(t)) ||
                   (p.brand_name && p.brand_name.toLowerCase().includes(t));
        }).filter(p => {
            const stock = parseFloat(p.stock) || 0;
            return stock > 0;
        }).slice(0, 20);
    }

    function showGridDropdown(rowNum, results) {
        const dd = document.getElementById('dd-row-' + rowNum);
        if (!dd) return;
        if (results.length === 0) {
            dd.innerHTML = '<div class="dd-empty">No products found</div>';
            dd.classList.add('show');
            gridDDIndex = -1;
            return;
        }
        let html = '<div class="dd-header"><span>Item Name</span><span>Code</span><span>MRP</span><span>Price</span><span>Stock</span></div>';
        results.forEach((p, i) => {
            const stock = parseFloat(p.stock) || 0;
            html += `<div class="dd-item" data-idx="${i}" data-id="${p.id}">
                <span class="dd-name">${escHtml(p.name||'')}</span>
                <span class="dd-code">${escHtml(p.code||'')}</span>
                <span class="dd-mrp">${fmtN(p.mrp_price)}</span>
                <span class="dd-price">${fmtN(p.sale_price)}</span>
                <span class="dd-stock${stock<=0?' oos':''}">${stock}</span>
            </div>`;
        });
        dd.innerHTML = html;
        dd.classList.add('show');
        gridDDIndex = -1;
        dd.querySelectorAll('.dd-item').forEach(el => {
            el.addEventListener('mousedown', function(e) {
                e.preventDefault();
                selectGridProduct(rowNum, parseInt(this.dataset.id));
            });
        });
    }

    function hideGridDropdown(rowNum) {
        const dd = document.getElementById('dd-row-' + rowNum);
        if (dd) { dd.classList.remove('show'); dd.innerHTML = ''; }
        gridDDIndex = -1;
    }

    function hideAllGridDropdowns() {
        document.querySelectorAll('.pos-product-dropdown').forEach(dd => { dd.classList.remove('show'); dd.innerHTML = ''; });
        gridDDIndex = -1;
    }

    function highlightGridDD(rowNum, idx) {
        const dd = document.getElementById('dd-row-' + rowNum);
        if (!dd) return;
        const items = dd.querySelectorAll('.dd-item');
        items.forEach((el, i) => {
            el.classList.toggle('active', i === idx);
            if (i === idx) el.scrollIntoView({ block: 'nearest' });
        });
    }

    function selectGridProduct(rowNum, productId) {
        const product = gridProducts.find(p => p.id === productId);
        if (!product) return;
        const tr = document.querySelector(`tr[data-row="${rowNum}"]`);
        if (!tr) return;
        const stock = parseFloat(product.stock) || 0;
        if (stock <= 0) { alert('Out of stock!'); return; }

        tr.dataset.productId = product.id;
        tr.dataset.productType = product.type || 'General_Product';
        tr.dataset.taxType = product.tax_type || 'Inclusive';
        tr.dataset.applicableTaxId = product.applicable_tax_id || '';
        tr.classList.add('pos-row-filled');
        tr.classList.remove('pos-empty-row');

        const custSelect = document.getElementById('customer-select');
        const custStateId = custSelect ? custSelect.options[custSelect.selectedIndex]?.dataset.stateId : null;
        let taxRate = 0;
        const applicableTaxId = product.applicable_tax_id;
        if (applicableTaxId && window.posTaxs && window.posTaxs.length > 0) {
            const isIntraState = custStateId && window.posOutletStateId && (custStateId == window.posOutletStateId);
            if (isIntraState) {
                const cgst = window.posTaxs.find(t => t.parent_tax_id == applicableTaxId && t.tax_name?.toLowerCase().includes('cgst'));
                if (cgst) taxRate = parseFloat(cgst.tax_rate) || 0;
            } else {
                const igst = window.posTaxs.find(t => t.parent_tax_id == applicableTaxId && t.tax_name?.toLowerCase().includes('igst'));
                if (igst) taxRate = parseFloat(igst.tax_rate) || 0;
            }
        } else if (product.tax_information && product.tax_information.length > 0) {
            product.tax_information.forEach(t => { taxRate += parseFloat(t.tax_field_percentage) || 0; });
        }

        let salePrice = parseFloat(product.sale_price) || 0;
        let mrp = parseFloat(product.mrp_price) || 0;
        const custType = document.getElementById('selected-customer-type')?.value || '';
        if (custType === 'wholesale' && product.whole_sale_price) salePrice = parseFloat(product.whole_sale_price) || salePrice;

        setGridCell(tr, 'item', product.name || '');
        setGridCell(tr, 'hsn', product.hsn_code || '');
        setGridCell(tr, 'mrp', fmtN(mrp));
        setGridCell(tr, 'qty', '1');
        setGridCell(tr, 'unit', product.sale_unit_name || 'PCS');
        setGridCell(tr, 'price', fmtN(salePrice));
        setGridCell(tr, 'disc', '');
        setGridCell(tr, 'tax', taxRate > 0 ? taxRate + '%' : '');

        calcGridRow(tr);
        hideGridDropdown(rowNum);
        syncGridToPosCart();

        gridDDIndex = 0;
        tr.querySelector('.pos-qty-input')?.focus();
        ensureEmptyRows();
    }

    function calcGridRow(tr) {
        const qty = parseFloat(getGridCell(tr, 'qty')) || 0;
        const price = parseFloat(getGridCell(tr, 'price')) || 0;
        const disc = parseFloat(getGridCell(tr, 'disc')) || 0;
        const amount = (qty * price) - disc;
        setGridCell(tr, 'amount', fmtN(amount));
    }

    function syncGridToPosCart() {
        if (typeof posCartManager === 'undefined') return;
        posCartManager.cartItems = [];
        document.querySelectorAll('#pos-cart-items-tbody tr[data-row][data-product-id]').forEach(tr => {
            const pid = tr.dataset.productId;
            if (!pid) return;
            const qty = parseFloat(getGridCell(tr, 'qty')) || 1;
            const price = parseFloat(getGridCell(tr, 'price')) || 0;
            const disc = parseFloat(getGridCell(tr, 'disc')) || 0;
            const taxStr = getGridCell(tr, 'tax');
            const taxPct = parseFloat(taxStr) || 0;
            const productType = tr.dataset.productType || 'General_Product';
            const taxType = tr.dataset.taxType || 'Inclusive';
            const applicableTaxId = tr.dataset.applicableTaxId || null;

            const taxInfo = [];
            if (taxPct > 0) {
                taxInfo.push({ tax_field_name: 'GST', tax_field_percentage: taxPct, tax_field_amount: 0 });
            }

            posCartManager.cartItems.push({
                product_id: parseInt(pid),
                product_name: getGridCell(tr, 'item'),
                product_code: '',
                product_type: productType,
                quantity: qty,
                unit_price: price,
                mrp_price: parseFloat(getGridCell(tr, 'mrp')) || price,
                discount: disc,
                discount_type: 'fixed',
                total: parseFloat(getGridCell(tr, 'amount')) || 0,
                tax_information: taxInfo,
                applicable_tax_id: applicableTaxId,
                tax_type: taxType,
                item_seller_id: null,
                promotion: null,
                has_promotion_discount: false,
                is_promotion_free_item: false,
                promotion_id: null,
                combo_items: null,
                selected_imei_serial: [],
                selected_medicine_expiry: [],
                tax_rate: taxPct > 0 ? taxPct : null,
                hsn_code: getGridCell(tr, 'hsn'),
                sale_unit_name: getGridCell(tr, 'unit')
            });
        });
        posCartManager.updateCartSummary();
    }

    function setupGridListeners() {
        const tbody = document.getElementById('pos-cart-items-tbody');

        tbody.addEventListener('input', async function(e) {
            if (!e.target.classList.contains('pos-item-input')) return;
            const tr = e.target.closest('tr');
            const rowNum = parseInt(tr.dataset.row);
            clearTimeout(gridSearchTimeout);
            gridSearchTimeout = setTimeout(async () => {
                const term = e.target.value.trim();
                gridDDIndex = 0;
                if (term.length >= 1) {
                    const results = await searchGridProductsAsync(term);
                    showGridDropdown(rowNum, results);
                } else {
                    hideGridDropdown(rowNum);
                }
            }, 150);
        });

        tbody.addEventListener('keydown', function(e) {
            if (!e.target.classList.contains('pos-item-input')) return;
            const tr = e.target.closest('tr');
            const rowNum = parseInt(tr.dataset.row);
            if (e.key === 'ArrowDown') { e.preventDefault(); gridDDIndex = 0; const items = document.querySelectorAll('#dd-row-'+rowNum+' .dd-item'); if(items.length){highlightGridDD(rowNum, gridDDIndex);} }
            else if (e.key === 'ArrowUp') { e.preventDefault(); const items = document.querySelectorAll('#dd-row-'+rowNum+' .dd-item'); if(items.length){gridDDIndex=Math.max(0,items.length-1);highlightGridDD(rowNum, gridDDIndex);} }
            else if (e.key === 'Enter') { e.preventDefault(); const items = document.querySelectorAll('#dd-row-'+rowNum+' .dd-item'); if(gridDDIndex>=0&&gridDDIndex<items.length){selectGridProduct(rowNum,parseInt(items[gridDDIndex].dataset.id));}else if(items.length===1){selectGridProduct(rowNum,parseInt(items[0].dataset.id));} }
            else if (e.key === 'Escape') { hideGridDropdown(rowNum); e.target.blur(); }
        });

        tbody.addEventListener('focusout', function(e) {
            if(e.target.classList.contains('pos-item-input')){const r=parseInt(e.target.closest('tr').dataset.row);setTimeout(()=>hideGridDropdown(r),200);}
        });
        tbody.addEventListener('focusin', async function(e) {
            if(e.target.classList.contains('pos-item-input')){
                const r=parseInt(e.target.closest('tr').dataset.row);
                const t=e.target.value.trim();
                if(t.length>=1){
                    const results = await searchGridProductsAsync(t);
                    showGridDropdown(r, results);
                }
            }
        });

        tbody.addEventListener('input', function(e) {
            if (e.target.classList.contains('pos-qty-input')||e.target.classList.contains('pos-price-input')||e.target.classList.contains('pos-disc-input')) {
                calcGridRow(e.target.closest('tr'));
                syncGridToPosCart();
            }
        });

        tbody.addEventListener('keydown', function(e) {
            if (e.key !== 'Tab') return;
            const input = e.target;
            if (!input.classList.contains('pos-grid-input')) return;
            const tr = input.closest('tr');
            const editableCols = ['item','qty','price','disc'];
            const curIdx = editableCols.indexOf(input.dataset.col);
            if (e.shiftKey) {
                if (curIdx > 0) { e.preventDefault(); tr.querySelector(`.pos-grid-input[data-col="${editableCols[curIdx-1]}"]`)?.focus(); }
            } else {
                if (curIdx < editableCols.length - 1) { e.preventDefault(); tr.querySelector(`.pos-grid-input[data-col="${editableCols[curIdx+1]}"]`)?.focus(); }
                else { const next = tr.nextElementSibling; if(next){e.preventDefault();next.querySelector('.pos-item-input')?.focus();} }
            }
        });

        tbody.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter') return;
            const input = e.target;
            if (input.classList.contains('pos-qty-input') || input.classList.contains('pos-price-input') || input.classList.contains('pos-disc-input')) {
                e.preventDefault();
                const tr = input.closest('tr');
                const next = tr.nextElementSibling;
                if (next && next.dataset.row) {
                    next.querySelector('.pos-item-input')?.focus();
                }
            }
        });
    }

    function setupGridKeyboard() {
        document.addEventListener('keydown', function(e) {
            if (e.target.tagName==='INPUT'||e.target.tagName==='SELECT'||e.target.tagName==='TEXTAREA') return;
            if (!document.querySelector('.pos-grid-table')) return;
            switch(e.key) {
                case 'F1': e.preventDefault(); const firstInput = document.querySelector('tr[data-row="1"] .pos-item-input'); if(firstInput) firstInput.focus(); break;
                case 'F2': e.preventDefault(); focusNextEmptyGridRow(); break;
            }
        });
    }

    function focusNextEmptyGridRow() {
        document.querySelectorAll('#pos-cart-items-tbody tr[data-row]').forEach(tr => {
            if (!tr.dataset.productId) { tr.querySelector('.pos-item-input')?.focus(); return; }
        });
    }

    function getGridCell(tr, col) { const i = tr.querySelector(`.pos-grid-input[data-col="${col}"]`); return i ? i.value : ''; }
    function setGridCell(tr, col, val) { const i = tr.querySelector(`.pos-grid-input[data-col="${col}"]`); if (i) i.value = val; }
    function fmtN(n) { return (parseFloat(n)||0).toFixed(2); }
    function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

})();
</script>
@endpush
