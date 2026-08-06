@extends('backend.backend_layout')
@section('page-title', __('Profit Loss Report'))
@push('page-css')
<style>
    .profit-loss-table tbody tr:nth-child(11),
    .profit-loss-table tbody tr:nth-child(14) {
        background-color: #f3f4f6;
        font-weight: 600;
    }
</style>
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Profit Loss Report') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => route('report.index')],
                ['label' => __('Report'), 'link' => route('report.index')],
                ['label' => __('Profit Loss Report'), 'active' => true]
            ]
        ])
    </div>

    @if(session('success'))
        {!! insertSuccess(session('success')) !!}
    @endif
    @if(session('error'))
        {!! insertFailed(session('error')) !!}
    @endif

    <!-- Filter Section -->
    <div class="card mb-4" id="filterSection" style="display: none;">
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
                            @if(isset($outlets))
                                @foreach($outlets as $outlet)
                                    <option value="{{ $outlet->id }}" {{ request('outlet_id') == $outlet->id ? 'selected' : '' }}>
                                        {{ $outlet->name }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('Costing Method') }}</label>
                        <select class="form-select select2" id="costing_method" name="costing_method">
                            <option value="last_purchase_price" {{ request('costing_method') == 'last_purchase_price' ? 'selected' : '' }}>{{ __('Last Purchase Price') }}</option>
                            <option value="last_three_purchase_avg" {{ request('costing_method') == 'last_three_purchase_avg' ? 'selected' : '' }}>{{ __('Last 3 Purchase AVG') }}</option>
                        </select>
                    </div>
                    <div class="col-md-12 mt-3">
                        <button type="button" class="btn btn-primary me-2" id="applyFilter">
                            <i class="ti tabler-filter me-1"></i>{{ __('Apply Filter') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <!-- show filtered information eg: outlet info, date range, report header -->
                <div class="card-body filtered_information" id="filteredInformation">
                    <h5 class="card-title">{{ __('Profit Loss Report') }}</h5>
                    <div id="filterInfoContent"></div>
                </div>
                <div class="card-datatable table-responsive pt-0">
                    <table class="table profit-loss-table" id="profitLossReportTable">
                        <thead>
                            <tr>
                                <th style="width: 50px;">{{ __('SN') }}</th>
                                <th>{{ __('Description') }}</th>
                                <th style="text-align: right; width: 200px;">{{ __('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>{{ __('Total Sales') }} ({{ __('Paid') }} & {{ __('Unpaid') }}) ({{ __('Incl. Tax & Discount') }})</td>
                                <td style="text-align: right;" id="total_sales">৳0.00</td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>{{ __('Total Cost of Sale') }}</td>
                                <td style="text-align: right;" id="total_cost_of_sale">৳0.00</td>
                            </tr>
                            <tr>
                                <td>3</td>
                                <td>{{ __('Tax') }}</td>
                                <td style="text-align: right;" id="tax">৳0.00</td>
                            </tr>
                            <tr>
                                <td>4</td>
                                <td>{{ __('Delivery/Service') }}</td>
                                <td style="text-align: right;" id="delivery_service">৳0.00</td>
                            </tr>
                            <tr>
                                <td>5</td>
                                <td>{{ __('Discount') }}</td>
                                <td style="text-align: right;" id="discount">৳0.00</td>
                            </tr>
                            <tr>
                                <td>6</td>
                                <td>{{ __('Installment Sale') }} ({{ __('Incl.') }} ({{ __('Delivery Charge') }} + {{ __('Percentage of Interest') }}) - {{ __('Discount') }})</td>
                                <td style="text-align: right;" id="installment_sale">৳0.00</td>
                            </tr>
                            <tr>
                                <td>7</td>
                                <td>{{ __('Income') }}</td>
                                <td style="text-align: right;" id="income">৳0.00</td>
                            </tr>
                            <tr>
                                <td>8</td>
                                <td>{{ __('Sale Return') }}</td>
                                <td style="text-align: right;" id="sale_return">৳0.00</td>
                            </tr>
                            <tr>
                                <td>9</td>
                                <td>{{ __('Cost Of Sale Return') }}</td>
                                <td style="text-align: right;" id="cost_of_sale_return">৳0.00</td>
                            </tr>
                            <tr>
                                <td>10</td>
                                <td>{{ __('Servicing') }}</td>
                                <td style="text-align: right;" id="servicing">৳0.00</td>
                            </tr>
                            <tr>
                                <td>11</td>
                                <td><strong>{{ __('Gross Profit') }} (1+4+6+7+10) - (2+3+5+8+9)</strong></td>
                                <td style="text-align: right;" id="gross_profit"><strong>৳0.00</strong></td>
                            </tr>
                            <tr>
                                <td>12</td>
                                <td>{{ __('Total Salaries') }}</td>
                                <td style="text-align: right;" id="total_salaries">৳0.00</td>
                            </tr>
                            <tr>
                                <td>13</td>
                                <td>{{ __('Expense') }}</td>
                                <td style="text-align: right;" id="expense">৳0.00</td>
                            </tr>
                            <tr>
                                <td>14</td>
                                <td><strong>{{ __('Net Profit') }} (11) - (12+13)</strong></td>
                                <td style="text-align: right;" id="net_profit"><strong>৳0.00</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('page-js')
@routes
<script src="{{ asset('backend_assets/js/report/report-base.js') }}"></script>
<script src="{{ asset('backend_assets/js/report/profit_loss_report.js') }}"></script>
@endpush
