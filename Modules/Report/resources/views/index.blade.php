@extends('backend.backend_layout')
@section('page-title', __('Reports'))
@push('page-css')
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Reports') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Report'),
                    'active' => true
                ]
            ]
        ])
    </div>

    @if(session('success'))
        {!! insertSuccess(session('success')) !!}
    @endif
    @if(session('error'))
        {!! insertFailed(session('error')) !!}
    @endif

    <!-- Reports Grid -->
    <div class="row g-4">
        <!-- Register Report -->
        @can('register_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-file-text mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Register Report') }}</h5>
                    <a href="{{ route('report.register-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Z Report -->
        @can('z_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-file-z mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Z Report') }}</h5>
                    <a href="{{ route('report.z-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Daily Summary Report -->
        @can('daily_summary_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-calendar-event mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Daily Summary Report') }}</h5>
                    <a href="{{ route('report.daily-summary-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Sale Report -->
        @can('sale_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-shopping-cart mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Sale Report') }}</h5>
                    <a href="{{ route('report.sale-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Due Sale Report -->
        @can('due_sale_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-currency-dollar mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Due Sale Report') }}</h5>
                    <a href="{{ route('report.due-sale-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Final Invoice Due Report -->
        @can('final_invoice_due_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-receipt mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Final Invoice Due Report') }}</h5>
                    <a href="{{ route('report.final-invoice-due-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Service Sale Report -->
        @can('service_sale_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-tools mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Service Sale Report') }}</h5>
                    <a href="{{ route('report.service-sale-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Combo Service Report -->
        @can('combo_service_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-package mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Combo Service Report') }}</h5>
                    <a href="{{ route('report.combo-service-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Stock Report -->
        @can('stock_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-box mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Stock Report') }}</h5>
                    <a href="{{ route('report.stock-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Low Stock Report -->
        @can('alert_stock_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-alert-triangle mb-3" style="font-size: 3rem; color: #ffab00;"></i>
                    <h5 class="card-title">{{ __('Low Stock Report') }}</h5>
                    <a href="{{ route('report.alert-stock-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Expire Soon Report -->
        @can('expire_soon_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-clock-exclamation mb-3" style="font-size: 3rem; color: #ffab00;"></i>
                    <h5 class="card-title">{{ __('Expire Soon Report') }}</h5>
                    <a href="{{ route('report.expire-soon-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Employee Sale Report -->
        @can('employee_sale_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-user mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Employee Sale Report') }}</h5>
                    <a href="{{ route('report.employee-sale-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Customer Receive Report -->
        @can('customer_receive_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-hand-receiving mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Customer Receive Report') }}</h5>
                    <a href="{{ route('report.customer-receive-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Attendance Report -->
        @can('attendance_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-clock-check mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Attendance Report') }}</h5>
                    <a href="{{ route('report.attendance-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Product Profit Report -->
        @can('product_profit_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-trending-up mb-3" style="font-size: 3rem; color: #71dd37;"></i>
                    <h5 class="card-title">{{ __('Product Profit Report') }}</h5>
                    <a href="{{ route('report.product-profit-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Supplier Ledger Report -->
        @can('supplier_ledger_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-book mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Supplier Ledger Report') }}</h5>
                    <a href="{{ route('report.supplier-ledger-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Supplier Balance Report -->
        @can('supplier_balance_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-scale mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Supplier Balance Report') }}</h5>
                    <a href="{{ route('report.supplier-balance-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Customer Ledger Report -->
        @can('customer_ledger_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-book-2 mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Customer Ledger Report') }}</h5>
                    <a href="{{ route('report.customer-ledger-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Customer Balance Report -->
        @can('customer_balance_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-scale-balance mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Customer Balance Report') }}</h5>
                    <a href="{{ route('report.customer-balance-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Servicing Report -->
        @can('servicing_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-settings mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Servicing Report') }}</h5>
                    <a href="{{ route('report.servicing-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Product Sale Report -->
        @can('product_sale_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-shopping-bag mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Product Sale Report') }}</h5>
                    <a href="{{ route('report.product-sale-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Tax Report -->
        @can('tax_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-receipt-tax mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Tax Report') }}</h5>
                    <a href="{{ route('report.tax-report') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- GST Reports -->
        @can('tax_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-file-invoice mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('GST Reports') }}</h5>
                    <a href="{{ route('report.gst-report') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Detailed Sale Report -->
        @can('detailed_sale_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-file-analytics mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Detailed Sale Report') }}</h5>
                    <a href="{{ route('report.detailed-sale-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Profit Loss Report -->
        @can('profit_loss_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-chart-line mb-3" style="font-size: 3rem; color: #71dd37;"></i>
                    <h5 class="card-title">{{ __('Profit Loss Report') }}</h5>
                    <a href="{{ route('report.profit-loss-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Purchase Report -->
        @can('purchase_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-basket mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Purchase Report') }}</h5>
                    <a href="{{ route('report.purchase-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Expense Report -->
        @can('expense_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-currency-dollar-off mb-3" style="font-size: 3rem; color: #ff3e1d;"></i>
                    <h5 class="card-title">{{ __('Expense Report') }}</h5>
                    <a href="{{ route('report.expense-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Income Report -->
        @can('income_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-currency-dollar mb-3" style="font-size: 3rem; color: #71dd37;"></i>
                    <h5 class="card-title">{{ __('Income Report') }}</h5>
                    <a href="{{ route('report.income-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Salary Report -->
        @can('salary_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-wallet mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Salary Report') }}</h5>
                    <a href="{{ route('report.salary-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Purchase Return Report -->
        @can('purchase_return_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-arrow-back mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Purchase Return Report') }}</h5>
                    <a href="{{ route('report.purchase-return-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Sale Return Report -->
        @can('sale_return_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-arrow-back-up mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Sale Return Report') }}</h5>
                    <a href="{{ route('report.sale-return-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Damage Report -->
        @can('damage_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-trash mb-3" style="font-size: 3rem; color: #ff3e1d;"></i>
                    <h5 class="card-title">{{ __('Damage Report') }}</h5>
                    <a href="{{ route('report.damage-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Installment Report -->
        @can('installment_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-credit-card mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Installment Report') }}</h5>
                    <a href="{{ route('report.installment-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Installment Due Report -->
        @can('installment_due_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-alert-circle mb-3" style="font-size: 3rem; color: #ffab00;"></i>
                    <h5 class="card-title">{{ __('Installment Due Report') }}</h5>
                    <a href="{{ route('report.installment-due-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Item Tracking Report -->
        @can('item_tracking_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-gps mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Item Tracking Report') }}</h5>
                    <a href="{{ route('report.item-tracking-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Price History Report -->
        @can('price_history_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-history mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Price History Report') }}</h5>
                    <a href="{{ route('report.price-history-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Cash Flow Report -->
        @can('cash_flow_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-cash mb-3" style="font-size: 3rem; color: #71dd37;"></i>
                    <h5 class="card-title">{{ __('Cash Flow Report') }}</h5>
                    <a href="{{ route('report.cash-flow-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Available Loyalty Point Report -->
        @can('available_loyalty_point_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-star mb-3" style="font-size: 3rem; color: #ffab00;"></i>
                    <h5 class="card-title">{{ __('Available Loyalty Point Report') }}</h5>
                    <a href="{{ route('report.available-loyalty-point-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan

        <!-- Usage Loyalty Point Report -->
        @can('usage_loyalty_point_report-view')
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="ti tabler-star-off mb-3" style="font-size: 3rem; color: #696cff;"></i>
                    <h5 class="card-title">{{ __('Usage Loyalty Point Report') }}</h5>
                    <a href="{{ route('report.usage-loyalty-point-report.view') }}" class="btn btn-primary btn-sm">
                        {{ __('View Report') }}
                    </a>
                </div>
            </div>
        </div>
        @endcan
    </div>
</div>
@endsection
@push('page-js')
@endpush
