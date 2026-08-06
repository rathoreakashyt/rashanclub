@extends('backend.backend_layout')
@section('page-title', __('Dashboard'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/swiper/swiper.css') }}" />
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Dashboard') }}</h4>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="card mb-4" id="filterSection">
        <div class="card-body">
            <form id="filterForm" onsubmit="event.preventDefault(); return false;" action="javascript:void(0);">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('Date From') }}</label>
                        <input type="text" class="form-control datePicker" id="date_from" name="date_from" value="{{ request('date_from') }}" placeholder="{{ __('Date From') }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('Date To') }}</label>
                        <input type="text" class="form-control datePicker" id="date_to" name="date_to" value="{{ request('date_to') }}" placeholder="{{ __('Date To') }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('Outlet') }}</label>
                        <select class="form-select select2" id="outlet_id" name="outlet_id">
                            <option value="">{{ __('All Outlets') }}</option>
                            @foreach($outlets as $outlet)
                                <option value="{{ $outlet->id }}" {{ request('outlet_id') == $outlet->id ? 'selected' : '' }}>
                                    {{ $outlet->outlet_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3 d-flex align-items-end">
                        <button type="button" class="btn btn-primary w-100" id="applyFilter">
                            <i class="ti tabler-filter me-1"></i><span>{{ __('Apply Filter') }}</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-6 mb-5">
        
        <!-- Card Border Shadow -->
        <div class="col-lg-3 col-sm-6">
            <div class="card card-border-shadow-primary h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="avatar me-4">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="icon-base ti tabler-shopping-cart icon-28px"></i>
                            </span>
                        </div>
                        <h4 class="mb-0" id="statSalesCount">{{ number_format($totalSalesCount) }}</h4>
                    </div>
                    <p class="mb-1">{{ __('Sales') }}</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="card card-border-shadow-warning h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="avatar me-4">
                            <span class="avatar-initial rounded bg-label-warning">
                            <i class="icon-base ti tabler-users icon-28px"></i>
                            </span>
                        </div>
                        <h4 class="mb-0" id="statCustomersCount">{{ number_format($totalCustomers) }}</h4>
                    </div>
                    <p class="mb-1">{{ __('Customers') }}</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="card card-border-shadow-danger h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="avatar me-4">
                            <span class="avatar-initial rounded bg-label-danger"><i class="icon-base ti tabler-package icon-28px"></i></span>
                        </div>
                        <h4 class="mb-0" id="statProductsCount">{{ number_format($totalProducts) }}</h4>
                    </div>
                    <p class="mb-1">{{ __('Products') }}</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="card card-border-shadow-info h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="avatar me-4">
                            <span class="avatar-initial rounded bg-label-info"><i class="icon-base ti tabler-clock icon-28px"></i></span>
                        </div>
                        <h4 class="mb-0" id="statRevenueAmount">{{ formatAmount($totalSalesAmount) }}</h4>
                    </div>
                    <p class="mb-1">{{ __('Revenue') }}</p>
                </div>
            </div>
        </div>
        <!--/ Card Border Shadow -->
    </div>

    <div class="row g-6">
        <!-- Revenue Report -->
        <div class="col-xxl-12">
            <div class="card h-100">
                <div class="card-header pb-0">
                    <h5 class="card-title mb-1">{{ __('Revenue Report') }}</h5>
                </div>
                <div class="card-body p-0">
                    <div class="row row-bordered g-0">
                        <div class="col-md-12 position-relative p-6">
                            <div id="totalRevenueChart" class="mt-n1" style="height: 300px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--/ Revenue Report -->

        <!-- Earning Reports -->
        <div class="col-xxl-4 col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">
                    <div class="card-title mb-0">
                        <h5 class="mb-1">{{ __('Earning Reports') }}</h5>
                    </div>
                </div>
                <div class="card-body pb-0">
                    <ul class="p-0 m-0">
                    <li class="d-flex align-items-center mb-5">
                        <div class="me-4">
                        <span class="badge bg-label-primary rounded p-1_5"
                            ><i class="icon-base ti tabler-chart-pie-2 icon-md"></i
                        ></span>
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                        <div class="me-2">
                            <h6 class="mb-0">{{ __('Net Profit') }}</h6>
                            <small class="text-body" id="earningSalesCount">{{ number_format($totalSalesCount) }} Sales</small>
                        </div>
                        <div class="user-progress d-flex align-items-center gap-4">
                            <small id="earningNetProfit">{{ formatAmount($netProfit) }}</small>
                            <div class="d-flex align-items-center gap-1">
                            <i class="icon-base ti tabler-chevron-{{ $netProfitChange >= 0 ? 'up' : 'down' }} text-{{ $netProfitChange >= 0 ? 'success' : 'danger' }}" id="earningNetProfitIcon"></i>
                            <small class="text-body-secondary" id="earningNetProfitChange">{{ number_format(abs($netProfitChange), 1) }}%</small>
                            </div>
                        </div>
                        </div>
                    </li>
                    <li class="d-flex align-items-center mb-5">
                        <div class="me-4">
                        <span class="badge bg-label-success rounded p-1_5"
                            ><i class="icon-base ti tabler-currency-dollar icon-md"></i
                        ></span>
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                        <div class="me-2">
                            <h6 class="mb-0">{{ __('Total Income') }}</h6>
                            <small class="text-body">Sales, Income</small>
                        </div>
                        <div class="user-progress d-flex align-items-center gap-4">
                            <small id="earningTotalIncome">{{ formatAmount($totalIncome) }}</small>
                            <div class="d-flex align-items-center gap-1">
                            <i class="icon-base ti tabler-chevron-{{ $totalIncomeChange >= 0 ? 'up' : 'down' }} text-{{ $totalIncomeChange >= 0 ? 'success' : 'danger' }}" id="earningTotalIncomeIcon"></i>
                            <small class="text-body-secondary" id="earningTotalIncomeChange">{{ number_format(abs($totalIncomeChange), 1) }}%</small>
                            </div>
                        </div>
                        </div>
                    </li>
                    <li class="d-flex align-items-center mb-5">
                        <div class="me-4">
                        <span class="badge bg-label-secondary text-body rounded p-1_5"
                            ><i class="icon-base ti tabler-credit-card icon-md"></i
                        ></span>
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                        <div class="me-2">
                            <h6 class="mb-0">{{ __('Total Expenses') }}</h6>
                            <small class="text-body">{{ __('Expenses') }}</small>
                        </div>
                        <div class="user-progress d-flex align-items-center gap-4">
                            <small id="earningTotalExpenses">{{ formatAmount($totalExpenses) }}</small>
                            <div class="d-flex align-items-center gap-1">
                            <i class="icon-base ti tabler-chevron-{{ $totalExpensesChange >= 0 ? 'up' : 'down' }} text-{{ $totalExpensesChange >= 0 ? 'danger' : 'success' }}" id="earningTotalExpensesIcon"></i>
                            <small class="text-body-secondary" id="earningTotalExpensesChange">{{ number_format(abs($totalExpensesChange), 1) }}%</small>
                            </div>
                        </div>
                        </div>
                    </li>
                    </ul>
                    <div id="reportBarChart"></div>
                </div>
            </div>
        </div>
        <!--/ Earning Reports -->

        <!-- Popular Product -->
        <div class="col-xxl-4 col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">
                    <div class="card-title m-0 me-2">
                        <h5 class="mb-1">{{ __('Top Selling Products') }}</h5>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="p-0 m-0">
                        @forelse($popularProducts as $product)
                        <li class="d-flex {{ !$loop->last ? 'mb-6' : '' }}">
                            <div class="me-4">
                            @if($product['item'] && $product['item']->photo)
                                <img src="{{ asset('uploads/items/' . $product['item']->photo) }}" alt="{{ $product['item']->name ?? 'Product' }}" class="rounded" width="46" height="46" style="object-fit: cover;" />
                            @else
                                <div class="rounded bg-label-secondary d-flex align-items-center justify-content-center" style="width: 46px; height: 46px;">
                                    <i class="icon-base ti tabler-package icon-md"></i>
                                </div>
                            @endif
                            </div>
                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                            <div class="me-2">
                                <h6 class="mb-0">{{ $product['item']->name ?? 'N/A' }}</h6>
                                <small class="text-body d-block">Item: #{{ $product['item_code'] }}</small>
                            </div>
                            <div class="user-progress d-flex align-items-center gap-1">
                                <p class="mb-0">{{ formatAmount($product['total_revenue']) }}</p>
                            </div>
                            </div>
                        </li>
                        @empty
                        <li class="d-flex">
                            <div class="w-100 text-center py-4">
                                <small class="text-body-secondary">{{ __('No products found') }}</small>
                            </div>
                        </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
        <!--/ Popular Product -->


        <!-- Transactions -->
        <div class="col-xxl-4 col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">
                    <div class="card-title m-0 me-2">
                        <h5 class="mb-1">{{ __('Transactions') }}</h5>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="p-0 m-0">
                        @forelse($recentTransactions as $transaction)
                        <li class="d-flex {{ !$loop->last ? 'mb-3 pb-1 align-items-center' : 'align-items-center' }}">
                            <div class="badge {{ $transaction['badge_class'] }} me-4 rounded p-1_5">
                            <i class="icon-base ti {{ $transaction['icon'] }} icon-md"></i>
                            </div>
                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                            <div class="me-2">
                                <h6 class="mb-0">{{ $transaction['title'] }}</h6>
                                <small class="text-body d-block">{{ $transaction['description'] }}</small>
                            </div>
                            <div class="user-progress d-flex align-items-center gap-1">
                                <h6 class="mb-0 {{ $transaction['text_class'] }}">{{ $transaction['is_positive'] ? '+' : '-' }}{{ formatAmount($transaction['amount']) }}</h6>
                            </div>
                            </div>
                        </li>
                        @empty
                        <li class="d-flex align-items-center">
                            <div class="w-100 text-center py-4">
                                <small class="text-body-secondary">{{ __('No recent transactions') }}</small>
                            </div>
                        </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
        <!--/ Transactions -->

        <!-- Profit last month -->
        <div class="col-xxl-4">
            <div class="card h-100">
                <div class="card-header pb-0">
                    <h5 class="card-title mb-1">{{ __('Profit') }}</h5>
                    <p class="card-subtitle" id="profitSubtitle">{{ __('Last Month') }}</p>
                </div>
                <div class="card-body">
                    <div id="profitLastMonth"></div>
                    <div class="d-flex justify-content-between align-items-center mt-3 gap-3">
                    <h4 class="mb-0" id="profitAmount">{{ formatAmount($lastMonthProfit) }}</h4>
                    @php
                        $profitPercentage = 0;
                        if ($lastMonthSales > 0) {
                            $profitPercentage = ($lastMonthProfit / $lastMonthSales) * 100;
                        }
                    @endphp
                    <small class="{{ $lastMonthProfit >= 0 ? 'text-success' : 'text-danger' }}" id="profitPercentage">{{ $lastMonthProfit >= 0 ? '+' : '' }}{{ number_format($profitPercentage, 2) }}%</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Operational Comparison  -->
        <div class="col-xxl-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">
                    <div class="card-title mb-0">
                        <h5 class="mb-1">{{ __('Operational Comparison') }}</h5>
                    </div>
                </div>
                <div class="card-body">
                    <div id="operationalComparisonChart"></div>
                </div>
            </div>
        </div>
        <!-- Operational Comparison End  -->


    </div>
</div>

@endsection
@push('page-js')
<script src="{{ asset('backend_assets/vendor/libs/swiper/swiper.js') }}"></script>
<!-- Vendors JS -->
<script src="{{ asset('backend_assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
<!-- Dashboard JS -->
<script src="{{ asset('backend_assets/js/dashboard/dashboard.js') }}"></script>
<script>
    // Pass PHP data to JavaScript
    const dashboardData = {
        revenueData: @json($revenueData),
        lastMonthProfit: {{ $lastMonthProfit }},
        netProfit: {{ $netProfit }},
        totalIncome: {{ $totalIncome }},
        totalExpenses: {{ $totalExpenses }},
        operationalData: @json($operationalData)
    };
    const dashboardRoute = '{{ route("dashboard") }}';
</script>
@endpush
