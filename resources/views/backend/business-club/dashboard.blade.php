@extends('backend.backend_layout')
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="mb-4"><i class="ti tabler-wallet me-2"></i>Business Club Dashboard</h4>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="d-flex justify-content-end mb-3 gap-2">
        <a href="{{ route('businessclub.register') }}" class="btn btn-primary">
            <i class="ti tabler-user-plus me-1"></i>Register Member
        </a>
        <a href="{{ route('businessclub.wallets') }}" class="btn btn-outline-secondary">
            <i class="ti tabler-wallet me-1"></i>Customer Wallets
        </a>
    </div>

    <div class="row">
        <div class="col-md-6 col-xl-3 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <span class="text-muted d-block">Total Active Members</span>
                            <h4 class="mb-0">{{ number_format($totalActiveMembers) }}</h4>
                        </div>
                        <div class="flex-shrink-0">
                            <span class="badge bg-label-primary p-2"><i class="ti tabler-users fs-4"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <span class="text-muted d-block">Total Balance Held</span>
                            <h4 class="mb-0">{{ number_format($totalBalanceHeld, 2) }}</h4>
                        </div>
                        <div class="flex-shrink-0">
                            <span class="badge bg-label-success p-2"><i class="ti tabler-wallet fs-4"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <span class="text-muted d-block">Total Earned</span>
                            <h4 class="mb-0">{{ number_format($totalEarned, 2) }}</h4>
                        </div>
                        <div class="flex-shrink-0">
                            <span class="badge bg-label-info p-2"><i class="ti tabler-chart-line fs-4"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <span class="text-muted d-block">Total Redeemed</span>
                            <h4 class="mb-0">{{ number_format($totalRedeemed, 2) }}</h4>
                        </div>
                        <div class="flex-shrink-0">
                            <span class="badge bg-label-warning p-2"><i class="ti tabler-arrow-up-circle fs-4"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Recent Transactions</h5>
                </div>
                <div class="card-body">
                    @if($recentTransactions->isEmpty())
                    <p class="text-muted mb-0">No transactions yet.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Type</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-end">Balance After</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentTransactions as $tx)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($tx->transaction_date)->format('d-m-Y') }}</td>
                                    <td>{{ $tx->customer_name ?? 'N/A' }}</td>
                                    <td>
                                        @php
                                            $badge = 'bg-label-secondary';
                                            if ($tx->type === 'membership_deposit') { $badge = 'bg-label-primary'; }
                                            elseif ($tx->type === 'profit_credit') { $badge = 'bg-label-success'; }
                                            elseif ($tx->type === 'redemption') { $badge = 'bg-label-warning'; }
                                        @endphp
                                        <span class="badge {{ $badge }}">{{ ucwords(str_replace('_', ' ', $tx->type)) }}</span>
                                    </td>
                                    <td class="text-end">{{ number_format($tx->amount, 2) }}</td>
                                    <td class="text-end">{{ number_format($tx->balance_after, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Top 5 Earners</h5>
                </div>
                <div class="card-body">
                    @if($topEarners->isEmpty())
                    <p class="text-muted mb-0">No members yet.</p>
                    @else
                    <ul class="list-unstyled mb-0">
                        @foreach($topEarners as $index => $earner)
                        <li class="d-flex align-items-center mb-3">
                            <span class="badge bg-primary rounded-circle me-3">{{ $index + 1 }}</span>
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $earner->customer->name ?? 'N/A' }}</div>
                                <small class="text-muted">{{ $earner->customer->phone ?? '-' }}</small>
                            </div>
                            <div class="text-end">
                                <div class="fw-semibold">{{ number_format($earner->earned_balance, 2) }}</div>
                                <small class="text-muted">Earned: {{ number_format($earner->total_earned, 2) }}</small>
                            </div>
                        </li>
                        @endforeach
                    </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection