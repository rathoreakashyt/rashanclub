@extends('backend.backend_layout')
@section('page-title', __('Business Club') . ' - ' . __('Dashboard'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/apex-charts/apex-charts.css') }}">
<style>
    .bc-stat-card {
        border-left: 4px solid;
        transition: transform 0.2s;
    }
    .bc-stat-card:hover {
        transform: translateY(-2px);
    }
    .bc-stat-card .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    .bc-stat-card .stat-value {
        font-size: 1.75rem;
        font-weight: 700;
        margin: 0;
    }
    .bc-stat-card .stat-label {
        font-size: 0.8rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .bc-stat-card .stat-change {
        font-size: 0.8rem;
        font-weight: 600;
    }
    .bc-settings-preview .setting-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    .bc-settings-preview .setting-row:last-child {
        border-bottom: none;
    }
    .bc-settings-preview .setting-label {
        color: #6c757d;
        font-size: 0.9rem;
    }
    .bc-settings-preview .setting-value {
        font-weight: 600;
        color: #333;
    }
    .bc-partner-card {
        text-align: center;
    }
    .bc-partner-card .partner-name {
        font-weight: 600;
        font-size: 0.85rem;
        margin-top: 8px;
    }
    .bc-partner-card .partner-amount {
        color: #28a745;
        font-weight: 700;
    }
    .bc-transaction-table .badge-credit {
        background-color: #d4edda;
        color: #155724;
    }
    .bc-transaction-table .badge-redeem {
        background-color: #f8d7da;
        color: #721c24;
    }
    .bc-growth-card .growth-value {
        font-size: 2rem;
        font-weight: 700;
        color: #28a745;
    }
    .bc-growth-card .growth-label {
        font-size: 0.8rem;
        color: #6c757d;
        text-transform: uppercase;
    }
    .bc-growth-card .latest-count {
        font-size: 1.5rem;
        font-weight: 700;
    }
</style>
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0 fw-bold">{{ __('BUSINESS CLUB DASHBOARD') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => '#'],
                ['label' => __('Business Club'), 'link' => '#'],
                ['label' => __('Dashboard'), 'active' => true]
            ]
        ])
    </div>

    <!-- Stats Cards Row -->
    <div class="row mb-6">
        <!-- Total Wallets Active -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card bc-stat-card" style="border-left-color: #6366f1;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="stat-label mb-1">{{ __('Total Wallets Active') }}</p>
                            <h3 class="stat-value" style="color: #6366f1;">{{ number_format($totalWallets) }}</h3>
                            @if($walletChange != 0)
                            <span class="stat-change {{ $walletChange > 0 ? 'text-success' : 'text-danger' }}">
                                <i class="ti tabler-arrow-{{ $walletChange > 0 ? 'up' : 'down' }}"></i> {{ abs($walletChange) }}%
                            </span>
                            @endif
                        </div>
                        <div class="stat-icon" style="background: rgba(99,102,241,0.1); color: #6366f1;">
                            <i class="ti tabler-wallet"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Balance Held -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card bc-stat-card" style="border-left-color: #f59e0b;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="stat-label mb-1">{{ __('Total Balance Held') }}</p>
                            <h3 class="stat-value" style="color: #f59e0b;">₹{{ number_format($totalBalance) }}</h3>
                            @if($balanceChange != 0)
                            <span class="stat-change {{ $balanceChange > 0 ? 'text-success' : 'text-danger' }}">
                                <i class="ti tabler-arrow-{{ $balanceChange > 0 ? 'up' : 'down' }}"></i> {{ abs($balanceChange) }}%
                            </span>
                            @endif
                        </div>
                        <div class="stat-icon" style="background: rgba(245,158,11,0.1); color: #f59e0b;">
                            <i class="ti tabler-coins"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Rewarded -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card bc-stat-card" style="border-left-color: #10b981;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="stat-label mb-1">{{ __('Total Rewarded') }}</p>
                            <h3 class="stat-value" style="color: #10b981;">₹{{ number_format($totalEarned) }}</h3>
                        </div>
                        <div class="stat-icon" style="background: rgba(16,185,129,0.1); color: #10b981;">
                            <i class="ti tabler-gift"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Redeemed -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card bc-stat-card" style="border-left-color: #ef4444;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="stat-label mb-1">{{ __('Total Redeemed') }}</p>
                            <h3 class="stat-value" style="color: #ef4444;">₹{{ number_format($totalRedeemed) }}</h3>
                        </div>
                        <div class="stat-icon" style="background: rgba(239,68,68,0.1); color: #ef4444;">
                            <i class="ti tabler-receipt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Admin Settings Preview + Weekly Chart Row -->
    <div class="row mb-6">
        <!-- Weekly Earning & Redemption Chart -->
        <div class="col-xl-8 col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">{{ __('Weekly Earning & Redemption') }}</h5>
                </div>
                <div class="card-body">
                    <div id="weeklyChart"></div>
                </div>
            </div>
        </div>

        <!-- Admin Settings Preview -->
        <div class="col-xl-4 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">{{ __('Admin Settings Preview') }}</h5>
                </div>
                <div class="card-body bc-settings-preview">
                    @if($settings)
                    <div class="setting-row">
                        <span class="setting-label">{{ __('Profit Share') }}</span>
                        <span class="setting-value" style="color: #10b981;">{{ $settings->profit_share_percentage }}%</span>
                    </div>
                    <div class="setting-row">
                        <span class="setting-label">{{ __('Redemption Date') }}</span>
                        <span class="setting-value">{{ $settings->redemption_day }}{{ ($settings->redemption_day % 10 == 1 && $settings->redemption_day != 11) ? 'st' : (($settings->redemption_day % 10 == 2 && $settings->redemption_day != 12) ? 'nd' : (($settings->redemption_day % 10 == 3 && $settings->redemption_day != 13) ? 'rd' : 'th')) }} of Month</span>
                    </div>
                    <div class="setting-row">
                        <span class="setting-label">{{ __('Membership Amount') }}</span>
                        <span class="setting-value">₹{{ number_format($settings->membership_amount) }}</span>
                    </div>
                    @else
                    <p class="text-muted text-center">{{ __('No settings configured') }}</p>
                    @endif
                    <div class="mt-3 text-center">
                        <a href="{{ route('businessclub.settings') }}" class="btn btn-sm btn-outline-primary">
                            <i class="ti tabler-settings"></i> {{ __('Edit Settings') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Partners + Membership Growth Row -->
    <div class="row mb-6">
        <!-- Top Earning Business Partners -->
        <div class="col-xl-8 col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">{{ __('Top-Earning Business Partners (Customers)') }}</h5>
                </div>
                <div class="card-body">
                    @if($topPartners->count() > 0)
                    <div class="d-flex justify-content-around align-items-end" style="height: 250px;">
                        @foreach($topPartners as $partner)
                        <div class="text-center bc-partner-card">
                            <p class="partner-name mb-1">{{ \Str::limit($partner['name'], 10) }}</p>
                            <p class="partner-amount mb-0">₹{{ number_format($partner['earned']) }}</p>
                        </div>
                        @endforeach
                    </div>
                    <div id="topPartnersChart" class="mt-3"></div>
                    @else
                    <p class="text-muted text-center py-4">{{ __('No earning partners yet') }}</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Club Membership Growth -->
        <div class="col-xl-4 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">{{ __('Club Membership Growth') }}</h5>
                </div>
                <div class="card-body bc-growth-card">
                    <div class="text-center py-3">
                        <p class="growth-label mb-2">{{ __('Club Membership Growth') }}</p>
                        <div class="growth-value">
                            {{ $membershipGrowth }}% 
                            @if($membershipGrowth > 0)
                                <i class="ti tabler-arrow-up" style="font-size: 1rem;"></i>
                            @elseif($membershipGrowth < 0)
                                <i class="ti tabler-arrow-down text-danger" style="font-size: 1rem;"></i>
                            @endif
                        </div>
                    </div>
                    <hr>
                    <div class="text-center py-3">
                        <p class="growth-label mb-2">{{ __('Latest Member Joins') }}</p>
                        <div class="latest-count" style="color: #6366f1;">{{ $latestJoins }}</div>
                        <small class="text-muted">{{ __('this month') }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Club Transactions -->
    <div class="card mb-6">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">{{ __('Recent Club Transactions') }}</h5>
            <div>
                <span class="badge bg-success me-2">Credit (Sale Reward)</span>
                <span class="badge bg-danger">Redemption (Payment Used)</span>
            </div>
        </div>
        <div class="card-body bc-transaction-table">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('Date/Time') }}</th>
                            <th>{{ __('Customer Name') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Sale Amount') }}</th>
                            <th>{{ __('Reward/Redeem') }}</th>
                            <th>{{ __('Current Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentTransactions as $tx)
                        <tr>
                            <td>{{ $tx['date'] }}</td>
                            <td>{{ $tx['customer_name'] }}</td>
                            <td>
                                @if(strpos($tx['type'], 'profit') !== false)
                                    <span class="badge badge-credit">Credit - Profit Share</span>
                                @else
                                    <span class="badge badge-redeem">{{ ucfirst($tx['type']) }}</span>
                                @endif
                            </td>
                            <td>
                                @if(strpos($tx['type'], 'profit') !== false)
                                    <span class="text-success">₹{{ number_format($tx['sale_amount'], 2) }}</span>
                                @else
                                    <span class="text-danger">-₹{{ number_format($tx['sale_amount'], 2) }}</span>
                                @endif
                            </td>
                            <td>
                                @if(strpos($tx['type'], 'profit') !== false)
                                    <span class="text-success">₹{{ number_format($tx['amount'], 2) }}</span>
                                @else
                                    <span class="text-danger">-₹{{ number_format($tx['amount'], 2) }}</span>
                                @endif
                            </td>
                            <td>₹{{ number_format($tx['balance_after'], 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">{{ __('No transactions yet') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="text-center py-3">
        <small class="text-muted">{{ __('Powered by') }} {{ getWhiteLabel('site_name') ?? 'Rashan Ki Dukan' }} {{ __('Business Club. All Rights Reserved.') }}</small>
    </div>
</div>
@endsection

@push('page-js')
<script src="{{ asset('backend_assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Weekly Earning & Redemption Chart
    var weeklyOptions = {
        series: [
            {
                name: 'Earned Rewarded to Customer',
                data: @json(array_column($weeklyData, 'earned'))
            },
            {
                name: 'Total Amount Redeemed by Them',
                data: @json(array_column($weeklyData, 'redeemed'))
            }
        ],
        chart: {
            type: 'area',
            height: 300,
            toolbar: { show: false },
            zoom: { enabled: false }
        },
        colors: ['#6366f1', '#f59e0b'],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.4,
                opacityTo: 0.1,
                stops: [0, 90, 100]
            }
        },
        xaxis: {
            categories: @json(array_column($weeklyData, 'label')),
            labels: { style: { fontSize: '12px' } }
        },
        yaxis: {
            labels: {
                style: { fontSize: '12px' },
                formatter: function(val) { return '₹' + val.toLocaleString('en-IN'); }
            }
        },
        legend: {
            position: 'top',
            horizontalAlign: 'center',
            fontSize: '12px'
        },
        tooltip: {
            y: {
                formatter: function(val) { return '₹' + val.toLocaleString('en-IN'); }
            }
        }
    };
    var weeklyChart = new ApexCharts(document.querySelector('#weeklyChart'), weeklyOptions);
    weeklyChart.render();

    // Top Partners Bar Chart
    @if($topPartners->count() > 0)
    var partnerOptions = {
        series: [{
            name: 'Total Earned',
            data: @json($topPartners->pluck('earned')->toArray())
        }],
        chart: {
            type: 'bar',
            height: 150,
            toolbar: { show: false },
            sparkline: { enabled: true }
        },
        colors: ['#6366f1'],
        plotOptions: {
            bar: {
                columnWidth: '40%',
                borderRadius: 4
            }
        },
        xaxis: {
            categories: @json($topPartners->pluck('name')->map(fn($n) => \Str::limit($n, 8))->toArray())
        },
        yaxis: {
            labels: {
                formatter: function(val) { return '₹' + val.toLocaleString('en-IN'); }
            }
        },
        tooltip: {
            y: {
                formatter: function(val) { return '₹' + val.toLocaleString('en-IN'); }
            }
        }
    };
    var partnerChart = new ApexCharts(document.querySelector('#topPartnersChart'), partnerOptions);
    partnerChart.render();
    @endif
});
</script>
@endpush
