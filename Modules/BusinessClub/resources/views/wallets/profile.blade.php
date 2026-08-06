@extends('backend.backend_layout')
@section('page-title', __('Partner Profile') . ' - ' . $customer->name)
@push('page-css')
<style>
    .profile-header {
        background: #fff;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    }
    .profile-header h2 {
        font-size: 1.5rem;
        font-weight: 800;
        color: #1a1a2e;
        margin-bottom: 4px;
    }
    .profile-header .subtitle {
        color: #555;
        font-size: 1rem;
    }

    /* Spend Tracker */
    .spend-tracker {
        background: #fff;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        height: 100%;
    }
    .spend-tracker h5 {
        font-weight: 700;
        font-size: 0.85rem;
        text-transform: uppercase;
        color: #1a1a2e;
        margin-bottom: 20px;
    }
    .spend-ring-wrapper {
        display: flex;
        align-items: center;
        gap: 20px;
    }
    .spend-ring {
        position: relative;
        width: 160px;
        height: 160px;
        flex-shrink: 0;
    }
    .spend-ring svg {
        transform: rotate(-90deg);
    }
    .spend-ring-center {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
    }
    .spend-ring-center .amount {
        font-size: 1.2rem;
        font-weight: 700;
        color: #1a1a2e;
    }
    .spend-ring-center .target {
        font-size: 0.75rem;
        color: #888;
    }
    .spend-info {
        flex: 1;
    }
    .spend-info p {
        font-size: 0.95rem;
        color: #444;
        line-height: 1.5;
    }
    .spend-info strong {
        color: #1a1a2e;
    }

    /* Wallet Balance */
    .wallet-balance-card {
        background: #fff;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        text-align: center;
        height: 100%;
    }
    .wallet-balance-card h5 {
        font-weight: 700;
        font-size: 0.85rem;
        text-transform: uppercase;
        color: #1a1a2e;
        margin-bottom: 16px;
    }
    .wallet-amount {
        font-size: 2.2rem;
        font-weight: 800;
        color: #1a1a2e;
    }
    .wallet-credit {
        display: inline-block;
        background: #d4edda;
        color: #155724;
        font-size: 0.85rem;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 20px;
        margin-left: 8px;
    }
    .btn-redeem {
        background: #2563eb;
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 10px 40px;
        font-weight: 600;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 16px;
        transition: background 0.2s;
    }
    .btn-redeem:hover {
        background: #1d4ed8;
        color: #fff;
    }

    /* How It Works */
    .how-it-works {
        background: #fff;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    }
    .how-it-works h5 {
        font-weight: 700;
        font-size: 0.85rem;
        text-transform: uppercase;
        color: #1a1a2e;
        margin-bottom: 20px;
        text-align: center;
    }
    .how-step {
        text-align: center;
        padding: 12px 8px;
    }
    .how-step .step-icon {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: #e8f4fd;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 12px;
        font-size: 1.8rem;
        color: #2563eb;
    }
    .how-step p {
        font-size: 0.82rem;
        color: #555;
        line-height: 1.4;
        margin: 0;
    }
    .how-step strong {
        color: #1a1a2e;
    }

    /* Profit Shares Table */
    .profit-shares-table {
        background: #fff;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    }
    .profit-shares-table h5 {
        font-weight: 700;
        font-size: 0.85rem;
        text-transform: uppercase;
        color: #1a1a2e;
        margin-bottom: 16px;
    }

    /* Shopping History */
    .shopping-history {
        background: #fff;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    }
    .shopping-history h5 {
        font-weight: 700;
        font-size: 0.85rem;
        text-transform: uppercase;
        color: #1a1a2e;
        margin-bottom: 16px;
    }
    .history-steps {
        display: flex;
        gap: 12px;
        margin-bottom: 16px;
    }
    .history-step {
        flex: 1;
        text-align: center;
        padding: 12px 4px;
        background: #f8f9fa;
        border-radius: 8px;
    }
    .history-step .step-icon {
        font-size: 1.5rem;
        color: #2563eb;
        margin-bottom: 6px;
    }
    .history-step p {
        font-size: 0.75rem;
        color: #555;
        margin: 0;
        line-height: 1.3;
    }
    .history-step p strong {
        display: block;
        color: #1a1a2e;
    }
    .btn-shop {
        background: #2563eb;
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 8px 20px;
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
    }
    .btn-shop:hover {
        background: #1d4ed8;
        color: #fff;
    }
    .btn-browse {
        background: #2563eb;
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 10px 24px;
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
    }
    .btn-browse:hover {
        background: #1d4ed8;
        color: #fff;
    }

    /* Recent Transactions Mini */
    .recent-tx-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    .recent-tx-item:last-child {
        border-bottom: none;
    }
    .recent-tx-item .tx-label {
        font-size: 0.85rem;
        color: #555;
    }
    .recent-tx-item .tx-amount {
        font-weight: 700;
        font-size: 0.9rem;
    }
    .recent-tx-item .tx-amount.credit { color: #155724; }
    .recent-tx-item .tx-amount.debit { color: #721c24; }
    .recent-tx-item .tx-date {
        font-size: 0.75rem;
        color: #999;
    }

    /* Profile Info */
    .profile-info-card {
        background: #fff;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        text-align: center;
    }
    .profile-info-card .avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #e3e6f0;
        margin-bottom: 12px;
    }
    .profile-info-card h5 {
        font-weight: 700;
        color: #1a1a2e;
        margin-bottom: 4px;
    }
    .profile-info-card .phone {
        color: #666;
        font-size: 0.9rem;
    }

    /* Footer */
    .profile-footer {
        text-align: center;
        padding: 16px;
        color: #999;
        font-size: 0.8rem;
    }
</style>
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">

    <!-- Header -->
    <div class="profile-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2>{{ strtoupper(getWhiteLabel('site_name') ?? 'Rashan Ki Dukan') }} MART - CUSTOMER DASHBOARD</h2>
                <p class="subtitle mb-0">{{ __('आपकी खरीदारी, आपका फायदा') }}</p>
            </div>
            <a href="{{ route('businessclub.wallets') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti tabler-arrow-left"></i> {{ __('Back') }}
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="row">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Customer Dashboard Card -->
            <div class="d-flex align-items-center mb-4">
                <div class="me-3">
                    <img src="{{ $customer->photo ? asset('uploads/customer/' . $customer->photo) : asset('uploads/dummy_images/admin.png') }}"
                         alt="{{ $customer->name }}" class="avatar">
                </div>
                <div>
                    <h5 class="mb-0 fw-bold">{{ __('CUSTOMER DASHBOARD') }}</h5>
                    <p class="text-muted mb-0 small">{{ $customer->name }} | {{ $customer->phone ?? '' }}</p>
                </div>
            </div>

            <!-- Monthly Spend Tracker + Wallet Balance -->
            <div class="row mb-4">
                <div class="col-md-7 mb-4">
                    <div class="spend-tracker">
                        <h5>{{ __('Monthly Spend Tracker') }}</h5>
                        <div class="spend-ring-wrapper">
                            <div class="spend-ring">
                                @php
                                    $spendPercent = $minPurchase > 0 ? min(($monthlySpend / $minPurchase) * 100, 100) : 0;
                                    $radius = 65;
                                    $circumference = 2 * pi() * $radius;
                                    $offset = $circumference - ($spendPercent / 100) * $circumference;
                                @endphp
                                <svg width="160" height="160" viewBox="0 0 160 160">
                                    <circle cx="80" cy="80" r="{{ $radius }}" fill="none" stroke="#e8f4fd" stroke-width="14"/>
                                    <circle cx="80" cy="80" r="{{ $radius }}" fill="none" stroke="#2563eb" stroke-width="14"
                                        stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $offset }}"
                                        stroke-linecap="round" style="transition: stroke-dashoffset 1s ease;"/>
                                </svg>
                                <div class="spend-ring-center">
                                    <div class="amount">₹{{ number_format($monthlySpend) }}</div>
                                    <div class="target">/ ₹{{ number_format($minPurchase) }}</div>
                                </div>
                            </div>
                            <div class="spend-info">
                                <p>{{ __('Collect') }} <strong>₹{{ number_format($minPurchase) }}</strong> {{ __('this Month to Activate') }} <strong>{{ $profitPercentage }}/{{ 100 - $profitPercentage }}</strong> {{ __('Profit Sharing!') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-5 mb-4">
                    <div class="wallet-balance-card">
                        <h5>{{ __('Wallet Balance') }}</h5>
                        <div>
                            <span class="wallet-amount">₹{{ number_format($wallet->balance ?? 0, 2) }}</span>
                            @if(($wallet->total_earned ?? 0) > 0)
                                <span class="wallet-credit">+ ₹{{ number_format($wallet->total_earned, 2) }}</span>
                            @endif
                        </div>
                        <a href="{{ route('businessclub.wallets') }}" class="btn btn-redeem">{{ __('Redeem Now') }}</a>
                    </div>
                </div>
            </div>

            <!-- Shopping History + Recent Profit Shares -->
            <div class="row mb-4">
                <div class="col-md-6 mb-4">
                    <div class="shopping-history">
                        <h5>{{ __('Shopping History') }} ({{ __('Recent Sales') }})</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ __('Date') }}</th>
                                        <th>{{ __('Invoice') }}</th>
                                        <th>{{ __('Items') }}</th>
                                        <th class="text-end">{{ __('Total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentSales as $sale)
                                    <tr>
                                        <td>{{ $sale->sale_date ? $sale->sale_date->format('d-m-Y') : '-' }}</td>
                                        <td>{{ $sale->invoice_no ?? $sale->sale_no ?? '#' . $sale->id }}</td>
                                        <td>
                                            @if($sale->saleDetails->count() > 0)
                                                @foreach($sale->saleDetails as $detail)
                                                    <span class="d-block small">
                                                        @if($detail->item)
                                                            {{ $detail->item->name }} 
                                                        @else
                                                            {{ __('Item #') }}{{ $detail->item_id }}
                                                        @endif
                                                        <span class="text-muted">(x{{ number_format($detail->qty) }})</span>
                                                    </span>
                                                @endforeach
                                            @else
                                                <span class="text-muted small">-</span>
                                            @endif
                                        </td>
                                        <td class="text-end">₹{{ number_format($sale->grand_total, 2) }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">{{ __('No purchases yet') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="profit-shares-table">
                        <h5>{{ __('Recent Profit Shares') }}</h5>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Description') }}</th>
                                    <th class="text-end">{{ __('Profit Share Credit') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentProfitShares as $ps)
                                <tr>
                                    <td>{{ $ps->transaction_date->format('d-M-Y') }}</td>
                                    <td>{{ $ps->description ?? '-' }}</td>
                                    <td class="text-end text-success fw-bold">+ ₹{{ number_format($ps->amount, 2) }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted">{{ __('No profit shares yet') }}</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Profile Info -->
            <div class="profile-info-card mb-4">
                <img src="{{ $customer->photo ? asset('uploads/customer/' . $customer->photo) : asset('uploads/dummy_images/admin.png') }}"
                     alt="{{ $customer->name }}" class="avatar">
                <h5>{{ $customer->name }}</h5>
                <p class="phone mb-1"><i class="ti tabler-phone me-1"></i>{{ $customer->phone ?? 'N/A' }}</p>
                @if($customer->email)
                <p class="text-muted mb-1 small"><i class="ti tabler-mail me-1"></i>{{ $customer->email }}</p>
                @endif
                @if($customer->address)
                <p class="text-muted mb-0 small"><i class="ti tabler-map-pin me-1"></i>{{ $customer->address }}</p>
                @endif
                <hr>
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <p class="text-muted small mb-1">{{ __('Total Sales') }}</p>
                        <h6 class="fw-bold mb-0">{{ number_format($totalSales) }}</h6>
                    </div>
                    <div class="col-6 mb-3">
                        <p class="text-muted small mb-1">{{ __('Total Amount') }}</p>
                        <h6 class="fw-bold mb-0">₹{{ number_format($totalSaleAmount) }}</h6>
                    </div>
                    <div class="col-6">
                        <p class="text-muted small mb-1">{{ __('Earned') }}</p>
                        <h6 class="fw-bold mb-0 text-success">₹{{ number_format($wallet->total_earned ?? 0) }}</h6>
                    </div>
                    <div class="col-6">
                        <p class="text-muted small mb-1">{{ __('Redeemed') }}</p>
                        <h6 class="fw-bold mb-0 text-danger">₹{{ number_format($wallet->total_redeemed ?? 0) }}</h6>
                    </div>
                </div>
            </div>

            <!-- How It Works -->
            <div class="how-it-works mb-4">
                <h5>{{ __('How It Works:') }} {{ $profitPercentage }}/{{ 100 - $profitPercentage }} {{ __('Profit Share') }}</h5>
                <div class="row">
                    <div class="col-6 how-step">
                        <div class="step-icon"><i class="ti tabler-shopping-cart"></i></div>
                        <p>{{ __('Reach') }} <strong>₹{{ number_format($minPurchase) }}</strong> {{ __('Total Spends in a Month.') }}</p>
                    </div>
                    <div class="col-6 how-step">
                        <div class="step-icon"><i class="ti tabler-calculator"></i></div>
                        <p>{{ __('System Calculates Dukan\'s Profit') }} ({{ __('Purchase Price vs. Sale Price') }}).</p>
                    </div>
                    <div class="col-6 how-step">
                        <div class="step-icon"><i class="ti tabler-scale"></i></div>
                        <p>{{ __('Profit is split') }} {{ $profitPercentage }}/{{ 100 - $profitPercentage }}: {{ $profitPercentage }}% {{ __('You') }}, {{ 100 - $profitPercentage }}% {{ __('Dukan') }}.</p>
                    </div>
                    <div class="col-6 how-step">
                        <div class="step-icon"><i class="ti tabler-wallet"></i></div>
                        <p>{{ __('Funds instantly credit to your in-app Wallet!') }}</p>
                    </div>
                </div>
            </div>

            <!-- Recent Profit Shares (Right Side) -->
            <div class="profit-shares-table">
                <h5>{{ __('Recent Profit Shares') }}</h5>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th class="text-end">{{ __('Credit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentProfitShares->take(5) as $ps)
                        <tr>
                            <td>{{ $ps->transaction_date->format('d-M-Y') }}</td>
                            <td>{{ $ps->description ?? '-' }}</td>
                            <td class="text-end text-success fw-bold">+ ₹{{ number_format($ps->amount, 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted">{{ __('No profit shares yet') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="profile-footer">
        {{ strtoupper(getWhiteLabel('site_name') ?? 'Rashan Ki Dukan') }} MART | {{ __('All Rights Reserved') }}
    </div>
</div>
@endsection
