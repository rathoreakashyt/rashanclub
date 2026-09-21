@extends('backend.backend_layout')
@section('page-title', __('BusyNotify Import') . ' - ' . __('BUSY Accounting Software'))

@push('page-css')
<style>
    .import-card {
        border: 2px solid #e9ecef;
        border-radius: 12px;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    .import-card:hover {
        border-color: #6366f1;
        box-shadow: 0 4px 15px rgba(99,102,241,0.15);
        transform: translateY(-2px);
    }
    .import-card .card-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    .import-card .card-title {
        font-weight: 700;
        font-size: 1.1rem;
    }
    .import-card .card-desc {
        color: #6c757d;
        font-size: 0.85rem;
    }
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    .status-ready { background: #d4edda; color: #155724; }
    .status-error { background: #f8d7da; color: #721c24; }
    .status-loading { background: #fff3cd; color: #856404; }
    .result-panel {
        border-radius: 12px;
        padding: 20px;
        margin-top: 20px;
        display: none;
    }
    .result-panel.show { display: block; }
    .result-success { background: #d4edda; border: 1px solid #c3e6cb; }
    .result-error { background: #f8d7da; border: 1px solid #f5c6cb; }
    .result-loading { background: #fff3cd; border: 1px solid #ffeeba; }
    .import-progress {
        height: 4px;
        background: #e9ecef;
        border-radius: 2px;
        overflow: hidden;
        margin-top: 10px;
    }
    .import-progress .bar {
        height: 100%;
        background: linear-gradient(90deg, #6366f1, #8b5cf6);
        border-radius: 2px;
        transition: width 0.5s ease;
        width: 0%;
    }
    .config-notice {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px;
        padding: 30px;
        text-align: center;
    }
    .config-notice h4 { font-weight: 700; }
    .config-notice code { background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 4px; }
    .btn-import {
        min-width: 120px;
        position: relative;
        overflow: hidden;
    }
    .btn-import .spinner-border {
        display: none;
    }
    .btn-import.loading .spinner-border { display: inline-block; }
    .btn-import.loading .btn-text { display: none; }
</style>
@endpush

@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0 fw-bold">{{ __('BUSY ACCOUNTING SOFTWARE IMPORT') }}</h4>
            <p class="text-muted mt-1">{{ __('Import data from BUSY Accounting via BusyNotify REST API') }}</p>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => '#'],
                ['label' => __('BusyNotify'), 'link' => '#'],
                ['label' => __('Import'), 'active' => true]
            ]
        ])
    </div>

    @if(!$isConfigured)
    <!-- Not Configured Notice + Token Form -->
    <div class="config-notice mb-6">
        <div class="mb-3">
            <i class="ti tabler-api" style="font-size: 3rem;"></i>
        </div>
        <h4>{{ __('BusyNotify API Not Configured') }}</h4>
        <p class="mt-2 mb-3">{{ __('Enter your BusyNotify Auth Token below to connect.') }}</p>
        <p class="mb-0" style="font-size: 0.82rem; opacity: 0.8;">
            {{ __('Get your token from') }} <a href="https://busynotify.in/solutions/custom-apis" target="_blank" style="color:#fff;text-decoration:underline;">busynotify.in</a>
        </p>
    </div>
    @endif

    <!-- Auth Token Card (always visible) -->
    <div class="card mb-6" id="tokenCard">
        <div class="card-header pb-0">
            <h5 class="mb-0 fw-bold"><i class="ti tabler-key me-2"></i>{{ __('BusyNotify Auth Token') }}</h5>
        </div>
        <div class="card-body">
            <div id="tokenSavedInfo" class="alert alert-success d-none mb-3 py-2">
                <i class="ti tabler-circle-check me-1"></i>
                <span id="tokenMaskedDisplay"></span>
                <a href="#" class="ms-2 small" onclick="showTokenInput(); return false;">{{ __('Change') }}</a>
            </div>

            <div id="tokenInputRow" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label mb-1">{{ __('Auth Token') }}</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="busynotifyToken"
                               placeholder="{{ __('Enter your BusyNotify authToken') }}"
                               autocomplete="new-password">
                        <button class="btn btn-outline-secondary" type="button" onclick="toggleTokenVisibility()">
                            <i class="ti tabler-eye" id="tokenEyeIcon"></i>
                        </button>
                    </div>
                    <small class="text-muted">{{ __('This token is saved securely in the database.') }}</small>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="button" class="btn btn-primary" id="saveTokenBtn" onclick="saveToken()">
                        <i class="ti tabler-device-floppy me-1"></i>{{ __('Save Token') }}
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="testAfterSaveBtn" onclick="testConnection()" title="{{ __('Test Connection') }}">
                        <i class="ti tabler-plug-connected"></i>
                    </button>
                </div>
            </div>

            <div id="tokenActionMsg" class="mt-2 d-none"></div>
        </div>
    </div>

    @if($isConfigured)
    <!-- Connection Status -->
    <div class="card mb-6">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <div class="status-badge status-ready" id="connectionBadge">
                        <i class="ti tabler-circle-check"></i>
                        <span>{{ __('API Configured') }}</span>
                    </div>
                    <span class="text-muted" id="connectionMessage"></span>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="testConnectionBtn" onclick="testConnection()">
                    <i class="ti tabler-plug-connected"></i> {{ __('Test Connection') }}
                </button>
            </div>
        </div>
    </div>

    <!-- Import Cards -->
    <div class="row mb-6">
        <!-- Customers Import -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card import-card h-100">
                <div class="card-body text-center">
                    <div class="card-icon mx-auto mb-3" style="background: rgba(99,102,241,0.1); color: #6366f1;">
                        <i class="ti tabler-users"></i>
                    </div>
                    <h5 class="card-title">{{ __('Customers') }}</h5>
                    <p class="card-desc mb-4">{{ __('Import customer master data including names, phone numbers, balances, and GST numbers.') }}</p>
                    <button type="button" class="btn btn-primary btn-import" id="importCustomersBtn" onclick="importData('customers')">
                        <span class="spinner-border spinner-border-sm me-1"></span>
                        <span class="btn-text"><i class="ti tabler-download"></i> {{ __('Import Customers') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Products Import -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card import-card h-100">
                <div class="card-body text-center">
                    <div class="card-icon mx-auto mb-3" style="background: rgba(16,185,129,0.1); color: #10b981;">
                        <i class="ti tabler-package"></i>
                    </div>
                    <h5 class="card-title">{{ __('Products') }}</h5>
                    <p class="card-desc mb-4">{{ __('Import product catalog with SKUs, prices, HSN codes, stock levels, and descriptions.') }}</p>
                    <button type="button" class="btn btn-success btn-import" id="importProductsBtn" onclick="importData('products')">
                        <span class="spinner-border spinner-border-sm me-1"></span>
                        <span class="btn-text"><i class="ti tabler-download"></i> {{ __('Import Products') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Bills Import -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card import-card h-100">
                <div class="card-body text-center">
                    <div class="card-icon mx-auto mb-3" style="background: rgba(245,158,11,0.1); color: #f59e0b;">
                        <i class="ti tabler-file-invoice"></i>
                    </div>
                    <h5 class="card-title">{{ __('Bills / Invoices') }}</h5>
                    <p class="card-desc mb-4">{{ __('Import invoices with items, quantities, rates, taxes, and payment status.') }}</p>
                    <button type="button" class="btn btn-warning btn-import" id="importBillsBtn" onclick="importData('bills')">
                        <span class="spinner-border spinner-border-sm me-1"></span>
                        <span class="btn-text"><i class="ti tabler-download"></i> {{ __('Import Bills') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Import All -->
    <div class="card mb-6">
        <div class="card-body text-center py-4">
            <h5 class="mb-2 fw-bold">{{ __('Import All Data') }}</h5>
            <p class="text-muted mb-3">{{ __('Import customers, products, and bills in one go. This may take a few minutes for large datasets.') }}</p>
            <button type="button" class="btn btn-dark btn-lg btn-import" id="importAllBtn" onclick="importData('all')">
                <span class="spinner-border spinner-border-sm me-1"></span>
                <span class="btn-text"><i class="ti tabler-download"></i> {{ __('Import All') }}</span>
            </button>
        </div>
    </div>

    <!-- Result Panel -->
    <div class="result-panel" id="resultPanel">
        <h5 class="mb-3" id="resultTitle"></h5>
        <div id="resultBody"></div>
        <div class="import-progress mt-3">
            <div class="bar" id="progressBar"></div>
        </div>
    </div>
    @endif

    <!-- Footer -->
    <div class="text-center py-3">
        <small class="text-muted">{{ __('Powered by') }} {{ getWhiteLabel('site_name') ?? 'Rashan Ki Dukan' }} {{ __('BusyNotify Integration. All Rights Reserved.') }}</small>
    </div>
</div>
@endsection

@push('page-js')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

// ─── On Page Load: check if token exists in DB ───────────────
document.addEventListener('DOMContentLoaded', function () {
    fetch('{{ route("busy-import.token-config") }}', {
        headers: { 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.has_token) {
            document.getElementById('tokenSavedInfo').classList.remove('d-none');
            document.getElementById('tokenMaskedDisplay').textContent = 
                '{{ __("Token saved") }}: ' + data.masked_token +
                (data.company_id ? ' | Company ID: ' + data.company_id : '');
            document.getElementById('tokenInputRow').classList.add('d-none');
        }
    })
    .catch(() => {});
});

function showTokenInput() {
    document.getElementById('tokenSavedInfo').classList.add('d-none');
    document.getElementById('tokenInputRow').classList.remove('d-none');
    document.getElementById('busynotifyToken').focus();
}

function toggleTokenVisibility() {
    const inp = document.getElementById('busynotifyToken');
    const icon = document.getElementById('tokenEyeIcon');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'ti tabler-eye-off';
    } else {
        inp.type = 'password';
        icon.className = 'ti tabler-eye';
    }
}

async function saveToken() {
    const token = document.getElementById('busynotifyToken').value.trim();
    const msgEl = document.getElementById('tokenActionMsg');
    const btn   = document.getElementById('saveTokenBtn');

    if (!token) {
        showMsg(msgEl, 'error', '{{ __("Please enter a token.") }}');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>{{ __("Saving...") }}';
    msgEl.classList.add('d-none');

    try {
        const res  = await fetch('{{ route("busy-import.save-token") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ busynotify_token: token }),
        });
        const data = await res.json();

        if (data.success) {
            showMsg(msgEl, 'success', '✅ ' + data.message + ' — {{ __("Now test the connection.") }}');
            // Refresh page after 1.5s to show updated state
            setTimeout(() => location.reload(), 1500);
        } else {
            showMsg(msgEl, 'error', '❌ ' + data.message);
        }
    } catch (e) {
        showMsg(msgEl, 'error', '❌ ' + e.message);
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="ti tabler-device-floppy me-1"></i>{{ __("Save Token") }}';
}

async function testConnection() {
    const badge   = document.getElementById('connectionBadge');
    const message = document.getElementById('connectionMessage');
    const btn     = document.getElementById('testConnectionBtn') || document.getElementById('testAfterSaveBtn');

    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }
    if (badge) { badge.className = 'status-badge status-loading'; badge.innerHTML = '<i class="ti tabler-loader"></i><span>Testing...</span>'; }

    try {
        const res  = await fetch('{{ route("busy-import.test-connection") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        });
        const data = await res.json();

        if (data.success) {
            if (badge) { badge.className = 'status-badge status-ready'; badge.innerHTML = '<i class="ti tabler-circle-check"></i><span>Connected</span>'; }
            if (message) { message.textContent = `✅ ${data.message} (${data.records_found} customers found)`; message.className = 'text-success'; }
            // Update masked display with company_id
            const infoEl = document.getElementById('tokenMaskedDisplay');
            if (infoEl && data.company_id) {
                infoEl.textContent = infoEl.textContent.replace(/\| Company ID: \d+/, '') + ' | Company ID: ' + data.company_id;
            }
        } else {
            if (badge) { badge.className = 'status-badge status-error'; badge.innerHTML = '<i class="ti tabler-circle-x"></i><span>Failed</span>'; }
            if (message) { message.textContent = `❌ ${data.message}`; message.className = 'text-danger'; }
        }
    } catch (e) {
        if (badge) { badge.className = 'status-badge status-error'; badge.innerHTML = '<i class="ti tabler-circle-x"></i><span>Error</span>'; }
        if (message) { message.textContent = `❌ ${e.message}`; message.className = 'text-danger'; }
    }

    if (btn) { btn.disabled = false; btn.innerHTML = btn.id === 'testAfterSaveBtn' ? '<i class="ti tabler-plug-connected"></i>' : '<i class="ti tabler-plug-connected"></i> {{ __("Test Connection") }}'; }
}

async function importData(type) {
    const btnId = type === 'all' ? 'importAllBtn' : `import${type.charAt(0).toUpperCase() + type.slice(1)}Btn`;
    const btn = document.getElementById(btnId);
    const resultPanel = document.getElementById('resultPanel');
    const resultTitle = document.getElementById('resultTitle');
    const resultBody = document.getElementById('resultBody');
    const progressBar = document.getElementById('progressBar');
    
    btn.classList.add('loading');
    btn.disabled = true;
    resultPanel.className = 'result-panel show result-loading';
    resultTitle.innerHTML = '<i class="ti tabler-loader"></i> Importing...';
    resultBody.innerHTML = `<p>Please wait while ${type === 'all' ? 'all data' : type} is being imported from BusyNotify API...</p>`;
    progressBar.style.width = '30%';
    
    try {
        const url = type === 'all' 
            ? '{{ route("busy-import.all") }}'
            : `{{ url("busy-import") }}/${type}`;
        
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });
        const data = await res.json();
        progressBar.style.width = '100%';
        
        if (data.success) {
            resultPanel.className = 'result-panel show result-success';
            resultTitle.innerHTML = '<i class="ti tabler-circle-check"></i> ' + data.message;
            
            let html = '';
            
            if (type === 'all' && data.results) {
                const s = data.summary;
                html = `
                    <div class="row text-center">
                        <div class="col-md-4">
                            <h6 class="text-success mb-1">Customers</h6>
                            <p class="mb-0"><strong>${s.customers_imported}</strong> new, <strong>${s.customers_updated}</strong> updated</p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-success mb-1">Products</h6>
                            <p class="mb-0"><strong>${s.products_imported}</strong> new, <strong>${s.products_updated}</strong> updated</p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-success mb-1">Bills</h6>
                            <p class="mb-0"><strong>${s.bills_imported}</strong> new, <strong>${s.bills_updated}</strong> updated</p>
                        </div>
                    </div>
                `;
                ['customers', 'products', 'bills'].forEach(key => {
                    if (data.results[key] && data.results[key].errors && data.results[key].errors.length > 0) {
                        html += `<hr><p class="text-danger mb-1"><strong>${key.charAt(0).toUpperCase() + key.slice(1)} errors:</strong></p>`;
                        data.results[key].errors.forEach(err => {
                            html += `<p class="text-danger mb-0" style="font-size:0.85rem;">• ${err}</p>`;
                        });
                    }
                });
            } else {
                html = `
                    <div class="row text-center">
                        <div class="col-md-4">
                            <h6 class="text-success mb-1">Imported (New)</h6>
                            <p class="mb-0"><strong style="font-size:1.5rem;">${data.imported}</strong></p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-primary mb-1">Updated</h6>
                            <p class="mb-0"><strong style="font-size:1.5rem;">${data.updated}</strong></p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted mb-1">Skipped</h6>
                            <p class="mb-0"><strong style="font-size:1.5rem;">${data.skipped}</strong></p>
                        </div>
                    </div>
                `;
                html += `<p class="text-muted mt-2 mb-0" style="font-size:0.85rem;">Total rows processed: ${data.total_rows}</p>`;
                if (data.errors && data.errors.length > 0) {
                    html += `<hr><p class="text-danger mb-1"><strong>Errors:</strong></p>`;
                    data.errors.forEach(err => {
                        html += `<p class="text-danger mb-0" style="font-size:0.85rem;">• ${err}</p>`;
                    });
                }
            }
            resultBody.innerHTML = html;
        } else {
            resultPanel.className = 'result-panel show result-error';
            resultTitle.innerHTML = '<i class="ti tabler-circle-x"></i> Import Failed';
            resultBody.innerHTML = `<p class="text-danger">${data.message}</p>`;
        }
    } catch (e) {
        progressBar.style.width = '100%';
        resultPanel.className = 'result-panel show result-error';
        resultTitle.innerHTML = '<i class="ti tabler-circle-x"></i> Import Error';
        resultBody.innerHTML = `<p class="text-danger">${e.message}</p>`;
    }
    
    btn.classList.remove('loading');
    btn.disabled = false;
}

function showMsg(el, type, text) {
    el.className = `mt-2 alert alert-${type === 'success' ? 'success' : 'danger'} py-2`;
    el.textContent = text;
    el.classList.remove('d-none');
}
</script>
@endpush
