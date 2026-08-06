@extends('backend.backend_layout')
@section('page-title', __('Sale Return') . ' ' . __('Details'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
<style>
    .border-dashed-bottom {
        border-bottom: 1px dashed #959292;
    }
</style>
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Sale Return Details -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Sale Return') }} {{ __('Details') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Sale Return'), 
                    'link' => route('sale-return.index')
                ],
                [
                    'label' => __('Sale Return') . ' ' . __('Details'),
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

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"></h5>
                        <div>
                            <a href="{{ route('sale-return.print-invoice', $saleReturn->encrypted_id) }}" target="_blank" class="btn btn-info">
                                <i class="ti tabler-printer me-1"></i>
                            </a>
                            <a href="{{ route('sale-return.generate-pdf', $saleReturn->encrypted_id) }}" class="btn btn-secondary">
                                <i class="ti tabler-download me-1"></i>
                            </a>
                            <a href="{{ route('sale-return.edit', $saleReturn->encrypted_id) }}" class="btn btn-warning">
                                <i class="ti tabler-edit me-1"></i>
                            </a>
                            <a href="{{ route('sale-return.index') }}" class="btn btn-primary">
                                <i class="ti tabler-arrow-back-up me-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row my-4">
                        <div class="col-md-6 pb-3">
                            <h5 class="mb-2"><b>{{ __('Sale Return') }} {{ __('Info') }}</b></h5>
                            @if($saleReturn->reference_no)
                                <p class="mb-0">{{ __('Reference No') }}: {{ $saleReturn->reference_no }}</p>
                            @endif
                            @if($saleReturn->date)
                                <p class="mb-0">{{ __('Date') }}: {{ formatDate($saleReturn->date) }}</p>
                            @endif
                            @if($saleReturn->sale)
                                <p class="mb-0">{{ __('Sale Invoice') }}: {{ $saleReturn->sale->sale_no ?? '' }}</p>
                            @endif
                            @if($saleReturn->customer)
                                <p class="mb-0">{{ __('Customer') }}: {{ $saleReturn->customer->name }}</p>
                            @endif
                        </div>
                        <div class="col-md-6 pb-3">
                            <h5 class="mb-2"><b>{{ __('Customer') }} {{ __('Info') }}</b></h5>
                            @if($saleReturn->customer)
                                <p class="mb-0">{{ __('Name') }}: {{ $saleReturn->customer->name }}</p>
                            @endif
                            @if($saleReturn->customer && $saleReturn->customer->phone)
                                <p class="mb-0">{{ __('Phone') }}: {{ $saleReturn->customer->phone }}</p>
                            @endif
                            @if($saleReturn->customer && $saleReturn->customer->address)
                                <p class="mb-0">{{ __('Address') }}: {{ $saleReturn->customer->address }}</p>
                            @endif
                        </div>
                    </div>
                    
                    @if($saleReturn->note)
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6>{{ __('Note') }}:</h6>
                            <p>{{ $saleReturn->note }}</p>
                        </div>
                    </div>
                    @endif
                    
                    <h6 class="mb-3">{{ __('Sale Return') }} {{ __('Items') }}</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>{{ __('SN') }}</th>
                                    <th>{{ __('Item Name') }}</th>
                                    <th>{{ __('IMEI_Serial') }}/{{ __('Medicine') }}</th>
                                    <th>{{ __('Sale') }} {{ __('Qty') }}</th>
                                    <th>{{ __('Return') }} {{ __('Qty') }}</th>
                                    <th>{{ __('Unit Price') }} ({{ __('Sale') }})</th>
                                    <th>{{ __('Return') }} {{ __('Price') }}</th>
                                    <th>{{ __('Total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($saleReturn->saleReturnDetails as $index => $detail)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
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
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td>
                                        @if($detail->expiry_imei_serial)
                                            @if($detail->item && in_array($detail->item->type, ['IMEI_Product', 'Serial_Product']))
                                                <span class="badge bg-light text-dark me-1">{{ $detail->expiry_imei_serial }}</span>
                                            @elseif($detail->item && $detail->item->type === 'Medicine_Product')
                                                <span class="badge bg-light text-dark me-1">{{ $detail->expiry_imei_serial }}</span>
                                            @else
                                                {{ $detail->expiry_imei_serial }}
                                            @endif
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td>{{ $detail->sale_quantity_amount }}</td>
                                    <td>{{ $detail->return_quantity_amount }}</td>
                                    <td>{{ formatAmount($detail->unit_price_in_sale) }}</td>
                                    <td>{{ formatAmount($detail->unit_price_in_return) }}</td>
                                    <td>{{ formatAmount($detail->return_quantity_amount * $detail->unit_price_in_return) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    
                    <!-- Payment Information -->
                    
                    <div class="row mb-4 justify-content-end">
                        <div class="col-md-6 col-lg-4 col-xl-3">
                            <h5 class="mb-2 mt-4 border-dashed-bottom pb-2"><b>{{ __('Sale Return') }} {{ __('Summary') }}</b></h5>
                            <table class="w-100">
                                <tr>
                                    <td width="w-50">{{ __('Grand Total') }}</td>
                                    <td width="w-50">: {{ formatAmount($saleReturn->total_return_amount) }}</td>
                                </tr>
                                <tr>
                                    <td width="w-50">{{ __('Paid') }} {{ __('Amount') }}</td>
                                    <td width="w-50">: {{ formatAmount($saleReturn->paid) }}</td>
                                </tr>
                                <tr>
                                    <td width="w-50">{{ __('Due') }} {{ __('Amount') }}</td>
                                    <td width="w-50">: {{ formatAmount($saleReturn->due) }}</td>
                                </tr>
                                @if($saleReturn->paymentMethod)
                                <tr>
                                    <td width="w-50">{{ __('Payment_Method') }}</td>
                                    <td width="w-50">: {{ $saleReturn->paymentMethod->name }}</td>
                                </tr>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('page-js')
@routes
<!-- Page JS -->
<script src="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{ asset('backend_assets/js/extended-ui-sweetalert2.js')}}"></script>
@endpush
