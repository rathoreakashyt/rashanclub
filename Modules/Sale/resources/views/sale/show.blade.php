@extends('backend.backend_layout')
@section('page-title', __('Sale Details'))
@push('page-css')
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Sale Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Sale') }} {{ __('Details') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => route('dashboard')
                ],
                [
                    'label' => __('Sale'),
                    'link' => '#'
                ],
                [
                    'label' => __('Sale') . ' ' . __('Details'),
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

    <!-- Sale Info Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ __('Sale') }} {{ __('Information') }}</h5>
                    <div>
                        <a href="{{ route('sale.print-invoice', $sale->encrypted_id) }}" target="_blank" class="btn btn-info">
                            <i class="ti tabler-printer me-1"></i>{{ __('Print') }}
                        </a>
                        <a href="{{ route('sale.generate-pdf', $sale->encrypted_id) }}" class="btn btn-secondary">
                            <i class="ti tabler-download me-1"></i>{{ __('Download PDF') }}
                        </a>
                        <a href="{{ route('sale.edit', $sale->encrypted_id) }}" class="btn btn-warning">
                            <i class="ti tabler-edit me-1"></i>{{ __('Edit') }}
                        </a>
                        <a href="{{ route('pos.index') }}" class="btn btn-primary">
                            <i class="ti tabler-arrow-left me-1"></i>{{ __('Back') }}
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>{{ __('Sale No') }}:</strong> {{ $sale->sale_no }}</p>
                            <p><strong>{{ __('Sale Date') }}:</strong> {{ formatDate($sale->sale_date) }}</p>
                            @if($sale->date_time)
                            <p><strong>{{ __('Date Time') }}:</strong> {{ formatDateTime($sale->date_time) }}</p>
                            @endif
                            <p><strong>{{ __('Customer') }}:</strong> {{ $sale->customer ? $sale->customer->name : 'Walk-in Customer' }}</p>
                            @if($sale->employee)
                            <p><strong>{{ __('Employee') }}:</strong> {{ $sale->employee->name }}</p>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <p><strong>{{ __('Subtotal') }}:</strong> {{ formatAmount($sale->sub_total) }}</p>
                            @if($sale->total_discount_amount > 0)
                            <p><strong>{{ __('Discount') }}:</strong> {{ formatAmount($sale->total_discount_amount) }}</p>
                            @endif
                            @if($sale->vat > 0)
                            <p><strong>{{ __('VAT') }}:</strong> {{ formatAmount($sale->vat) }}</p>
                            @endif
                            <p><strong>{{ __('Paid Amount') }}:</strong> {{ formatAmount($sale->paid_amount) }}</p>
                            <p><strong>{{ __('Due Amount') }}:</strong> {{ formatAmount($sale->due_amount) }}</p>
                            <p><strong>{{ __('Grand Total') }}:</strong> <span class="text-primary fw-bold">{{ formatAmount($sale->grand_total) }}</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sale Details Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Sale') }} {{ __('Items') }}</h5>
                </div>
                <div class="card-datatable table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('SN') }}</th>
                                <th>{{ __('Item') }}({{ __('Code') }})</th>
                                <th>{{ __('IMEI/Serial') }}</th>
                                <th>{{ __('Quantity') }}</th>
                                <th>{{ __('Unit Price') }}</th>
                                <th>{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $i = 0;
                            @endphp
                            @if($sale->saleDetails && $sale->saleDetails->count() > 0)
                                @foreach($sale->saleDetails as $detail)
                                    @php
                                        $i++;
                                    @endphp
                                    <tr>
                                        <td>{{ $i }}</td>
                                        <td>
                                            @if($detail->item)
                                                @if($detail->item->parent_id && $detail->item->parent)
                                                    {{ $detail->item->parent->name }} - {{ $detail->item->name }}
                                                @else
                                                    {{ $detail->item->name }}
                                                @endif
                                                @if($detail->item->code)
                                                    ({{ $detail->item->code }})
                                                @endif
                                            @endif
                                        </td>
                                        <td>
                                            @if($detail->expiry_imei_serial)
                                                {{ $detail->expiry_imei_serial }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $detail->qty }}</td>
                                        <td>{{ formatAmount($detail->menu_unit_price) }}</td>
                                        <td>{{ formatAmount($detail->qty * $detail->menu_price_with_discount) }}</td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('page-js')
@endpush
