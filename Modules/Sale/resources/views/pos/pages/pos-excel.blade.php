@extends('sale::pos.pos-layout')
@section('page-title', 'POS - Excel Mode')
@push('page-css')
<link rel="stylesheet" href="{{ asset('pos_assets/css/pos_excel.css') }}">
@endpush
@section('page-content')
<script>window.posPrinterSettings = @json($printer_settings ?? null);</script>
<div class="excel-pos-container">
    <!-- Excel Header Bar -->
    <div class="excel-header">
        <div class="excel-header-top">
            <div class="brand">
                @php $logo = session('company.logo') ?? 'uploads/business_setting/initial-logo.png'; @endphp
                <img src="{{ asset('uploads/site_settings/' . $logo) }}" alt="Logo" onerror="this.style.display='none'">
                <span>{{ strtoupper(getWhiteLabel('site_name') ?? 'POS') }} - Excel Mode</span>
            </div>
            <div class="outlet-info">
                <span>{{ session('outlet.outlet_name') }}</span>
                <span class="clock" id="pos-clock"></span>
            </div>
        </div>
        
        <!-- Quick Access Toolbar -->
        <div class="excel-toolbar">
            <button class="toolbar-btn" data-action="new-sale" title="New Sale">
                <i class="icon-base ti tabler-file-plus"></i> New <kbd>F10</kbd>
            </button>
            <button class="toolbar-btn" data-action="hold" title="Hold Sale">
                <i class="icon-base ti tabler-player-pause"></i> Hold <kbd>F7</kbd>
            </button>
            <button class="toolbar-btn" data-action="holds-list" title="List Holds">
                <i class="icon-base ti tabler-list"></i> Holds <kbd>F9</kbd>
            </button>
            <div class="separator"></div>
            <button class="toolbar-btn" data-action="payment" title="Payment">
                <i class="icon-base ti tabler-credit-card"></i> Pay <kbd>F8</kbd>
            </button>
            <button class="toolbar-btn" data-action="quick-cash" title="Quick Cash">
                <i class="icon-base ti tabler-cash"></i> Cash <kbd>F12</kbd>
            </button>
            <div class="separator"></div>
            <button class="toolbar-btn" data-action="search" title="Search Products">
                <i class="icon-base ti tabler-search"></i> Search <kbd>Ctrl+K</kbd>
            </button>
            <button class="toolbar-btn" id="pos-sync-products-btn" title="Refresh Products">
                <i class="icon-base ti tabler-refresh"></i> Refresh <kbd>F5</kbd>
            </button>
            <div class="separator"></div>
            <button class="toolbar-btn" data-action="help" title="Keyboard Shortcuts">
                <i class="icon-base ti tabler-keyboard"></i> Help <kbd>F1</kbd>
            </button>
            
            <!-- Search Box -->
            <div class="search-box">
                <i class="icon-base ti tabler-search search-icon"></i>
                <input type="text" id="pos-product-search" placeholder="Type barcode or product name...">
            </div>
        </div>
    </div>

    <!-- Formula Bar -->
    <div class="excel-formula-bar">
        <div class="cell-ref">A1</div>
        <input type="text" class="formula-input" placeholder="Type product code/name and press Enter to add...">
    </div>

    <!-- Main Content -->
    <div class="excel-main">
        <!-- Cart Panel (Left - Excel Table) -->
        <div class="excel-cart-panel">
            <!-- Cart Header -->
            <div class="excel-cart-header">
                <label>Employee:</label>
                @php $currentUser = Auth::user()->id; @endphp
                <select name="employee_id" id="employee-select">
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" {{ $currentUser == $employee->id ? 'selected' : '' }}>{{ $employee->name }}</option>
                    @endforeach
                </select>
                <input type="hidden" class="default_employee_id" value="{{ $currentUser }}">
                
                <label style="margin-left: 12px;">Customer:</label>
                @php $default_customer = session('company.default_customer'); @endphp
                <select name="customer_id" id="customer-select">
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" data-customer-type="{{ $customer->customer_type ?? '' }}" {{ $default_customer == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                    @endforeach
                </select>
                <input type="hidden" id="selected-customer-type" value="{{ $customers->firstWhere('id', $default_customer)->customer_type ?? '' }}">
                
                <button class="btn btn-sm" style="background:var(--excel-primary);color:#fff;border:none;padding:4px 8px;border-radius:3px;margin-left:4px;" id="pos-add-customer-btn" data-bs-toggle="modal" data-bs-target="#modal_pos_customer">
                    <i class="icon-base ti tabler-user-plus"></i>
                </button>
                <button class="btn btn-sm" style="background:var(--excel-primary);color:#fff;border:none;padding:4px 8px;border-radius:3px;" id="pos-edit-customer-btn">
                    <i class="icon-base ti tabler-edit"></i>
                </button>
            </div>

            <!-- Excel Cart Table -->
            <div class="excel-table-wrapper" id="excel-cart-wrapper">
                <table class="excel-table">
                    <thead>
                        <tr>
                            <th class="row-num">#</th>
                            <th class="col-product">Product</th>
                            <th class="col-qty">Qty</th>
                            <th class="col-price">Price</th>
                            <th class="col-disc">Disc</th>
                            <th class="col-total">Total</th>
                            <th class="col-action"></th>
                        </tr>
                    </thead>
                    <tbody id="excel-cart-tbody">
                        <!-- Cart items rendered here -->
                    </tbody>
                </table>
                
                <!-- Empty State -->
                <div class="excel-empty-cart" id="excel-empty-cart">
                    <i class="icon-base ti tabler-table"></i>
                    <p>No items in cart</p>
                    <p style="font-size:11px;">Scan barcode or press <kbd style="background:#fff;border:1px solid #ccc;padding:1px 6px;border-radius:2px;font-family:monospace;">F4</kbd> to add product</p>
                </div>
            </div>

            <!-- Summary Section -->
            <div class="excel-summary">
                <div class="excel-summary-left">
                    <div class="excel-summary-item">
                        <span class="label">Subtotal:</span>
                        <span class="value" id="excel-subtotal">0.00</span>
                    </div>
                    <div class="excel-summary-item">
                        <span class="label">Tax:</span>
                        <span class="value" id="excel-tax">0.00</span>
                    </div>
                    <div class="excel-summary-item discount-input">
                        <span class="label">Discount:</span>
                        <div class="value">
                            <select id="excel-discount-type" style="width:40px;padding:2px;border:1px solid #d4d4d4;border-radius:2px;font-size:11px;">
                                <option value="fixed">$</option>
                                <option value="percentage">%</option>
                            </select>
                            <input type="text" id="excel-discount-input" value="0" style="width:60px;padding:3px 6px;border:1px solid #d4d4d4;border-radius:2px;font-size:12px;font-family:monospace;text-align:right;">
                        </div>
                    </div>
                    <div class="excel-summary-item">
                        <span class="label">Shipping:</span>
                        <div class="value">
                            <input type="text" id="excel-shipping-input" value="0" style="width:70px;padding:3px 6px;border:1px solid #d4d4d4;border-radius:2px;font-size:12px;font-family:monospace;text-align:right;">
                        </div>
                    </div>
                </div>
                
                <!-- Total Section -->
                <div class="excel-total-section">
                    <div class="total-label">Total Payable</div>
                    <div class="total-amount" id="excel-total-amount">0.00</div>
                    <div class="total-actions">
                        <button onclick="document.getElementById('pos-cash-register-btn')?.click()">
                            <i class="icon-base ti tabler-cash"></i> Cash <kbd>F12</kbd>
                        </button>
                        <button class="primary" onclick="document.getElementById('pos-payment-btn')?.click()">
                            <i class="icon-base ti tabler-credit-card"></i> Pay <kbd>F8</kbd>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Panel (Right) -->
        <div class="excel-products-panel">
            <div class="excel-products-header">
                <div class="quick-add">
                    <i class="icon-base ti tabler-barcode add-icon"></i>
                    <input type="text" id="pos-quick-add" placeholder="Scan barcode to add...">
                </div>
                <div class="category-tabs" id="pos-category-tabs">
                    <div class="category-tab active" data-category-id="all">All</div>
                    @foreach($categories as $category)
                        <div class="category-tab" data-category-id="{{ $category->id }}">{{ $category->name }}</div>
                    @endforeach
                </div>
            </div>
            
            <div class="excel-product-list thin-scroll" id="pos-products-grid">
                <div class="pos-loading-state" style="text-align:center;padding:40px;">
                    <div class="spinner-border text-danger" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2" style="font-size:12px;color:#999;">Loading products...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Bar -->
    <div class="excel-status-bar">
        <div class="status-left">
            <span class="status-item">Items: <strong>0</strong></span>
            <span class="status-item">Total: <strong>0.00</strong></span>
            <span class="status-item hint">Press <kbd>F1</kbd> for keyboard shortcuts</span>
        </div>
        <div class="status-right">
            <span class="status-item"><kbd>↑↓</kbd> Navigate</span>
            <span class="status-item"><kbd>Enter</kbd> Edit</span>
            <span class="status-item"><kbd>Tab</kbd> Next Cell</span>
            <span class="status-item"><kbd>Esc</kbd> Cancel</span>
        </div>
    </div>
</div>

<!-- Keyboard Hints Overlay -->
<div class="keyboard-hints-overlay" id="keyboard-help-overlay">
    <div class="keyboard-hints-card">
        <h3><i class="icon-base ti tabler-keyboard"></i> Keyboard Shortcuts</h3>
        <div class="hints-grid">
            <div class="hint-item"><kbd>F1</kbd> <span>Show this help</span></div>
            <div class="hint-item"><kbd>F2</kbd> <span>Edit selected cell</span></div>
            <div class="hint-item"><kbd>F3</kbd> <span>Search products</span></div>
            <div class="hint-item"><kbd>F4</kbd> <span>Quick add (barcode)</span></div>
            <div class="hint-item"><kbd>F5</kbd> <span>Refresh products</span></div>
            <div class="hint-item"><kbd>F6</kbd> <span>Select customer</span></div>
            <div class="hint-item"><kbd>F7</kbd> <span>Hold sale</span></div>
            <div class="hint-item"><kbd>F8</kbd> <span>Open payment</span></div>
            <div class="hint-item"><kbd>F9</kbd> <span>List holds</span></div>
            <div class="hint-item"><kbd>F10</kbd> <span>New sale</span></div>
            <div class="hint-item"><kbd>F11</kbd> <span>Fullscreen</span></div>
            <div class="hint-item"><kbd>F12</kbd> <span>Quick cash</span></div>
            <div class="hint-item"><kbd>↑↓</kbd> <span>Navigate rows</span></div>
            <div class="hint-item"><kbd>←→</kbd> <span>Navigate columns</span></div>
            <div class="hint-item"><kbd>Enter</kbd> <span>Edit cell / Move down</span></div>
            <div class="hint-item"><kbd>Tab</kbd> <span>Next cell</span></div>
            <div class="hint-item"><kbd>Shift+Tab</kbd> <span>Previous cell</span></div>
            <div class="hint-item"><kbd>Delete</kbd> <span>Clear cell</span></div>
            <div class="hint-item"><kbd>Ctrl+K</kbd> <span>Global search</span></div>
            <div class="hint-item"><kbd>Esc</kbd> <span>Cancel / Close</span></div>
            <div class="hint-item"><kbd>Alt+↑↓</kbd> <span>Navigate products</span></div>
            <div class="hint-item"><kbd>Enter</kbd> <span>Add selected product</span></div>
        </div>
        <div style="text-align:center;margin-top:16px;">
            <button onclick="document.getElementById('keyboard-help-overlay').classList.remove('show')" style="padding:8px 24px;background:var(--excel-primary);color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:13px;">Close (Esc)</button>
        </div>
    </div>
</div>

@endsection
@push('page-js')
@routes
<script>
    window.posOutletStateCode = @json(optional($outlet)->state_code);
    window.posTaxs = @json($taxs ?? []);
    window.posStates = @json($states->map(fn($s) => ['id' => $s->id, 'state_code' => $s->state_code])->values()->toArray());
    window.posCustomersWithState = @json($customers->keyBy('id')->map(fn($c) => ['id' => $c->id, 'state_id' => $c->state_id])->toArray());
    
    // Clock
    function updateClock() {
        const now = new Date();
        const time = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        const el = document.getElementById('pos-clock');
        if (el) el.textContent = time;
    }
    updateClock();
    setInterval(updateClock, 1000);
    
    // Category tabs click
    document.querySelectorAll('.category-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.category-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            const catId = this.dataset.categoryId;
            // Trigger category filter
            const categoryItem = document.querySelector(`.pos-category-item[data-category-id="${catId}"]`);
            if (categoryItem) categoryItem.click();
        });
    });
</script>
@if(!empty($editSaleId))
<script>window.posEditSaleId = @json($editSaleId);</script>
@endif
<script src="{{ asset('pos_assets/js/pos_excel_keyboard.js') }}"></script>
<script src="{{ asset('pos_assets/js/pos_search.js') }}"></script>
@if(config('zatca.enabled', false))
<script src="{{ asset('pos_assets/js/pos_zatca.js') }}"></script>
@endif
@endpush
