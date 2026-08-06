@extends('backend.backend_layout')
@section('page-title', __('Purchase Details'))
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
    <!-- Purchase Details -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">Purchase Details</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Purchase'), 
                    'link' => route('purchase.index')
                ],
                [
                    'label' => __('Purchase Details'),
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
                            <a href="{{ route('purchase.print-invoice', $purchase->encrypted_id) }}" target="_blank" class="btn btn-info">
                                <i class="ti tabler-printer me-1"></i>
                            </a>
                            <a href="{{ route('purchase.generate-pdf', $purchase->encrypted_id) }}" class="btn btn-secondary">
                                <i class="ti tabler-download me-1"></i>
                            </a>
                            <a href="{{ route('purchase.edit', $purchase->encrypted_id) }}" class="btn btn-warning">
                                <i class="ti tabler-edit me-1"></i>
                            </a>
                            <a href="{{ route('purchase.index') }}" class="btn btn-primary">
                                <i class="ti tabler-arrow-back-up me-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row my-4">
                        <div class="col-md-6 pb-3">
                            <h5 class="mb-2"><b>{{ __('Purchase') }} {{ __('Info') }}</b></h5>
                            @if($purchase->reference_no)
                                <p class="mb-0">Reference No: {{ $purchase->reference_no }}</p>
                            @endif
                            @if($purchase->date)
                                <p class="mb-0">Date: {{ formatDate($purchase->date) }}</p>
                            @endif
                            @if($purchase->supplier_invoice_no)
                                <p class="mb-0">Supplier Invoice No: {{ $purchase->supplier_invoice_no }}</p>
                            @endif
                            @if($purchase->supplier)
                                <p class="mb-0">Supplier: {{ $purchase->supplier->name }}</p>
                            @endif
                        </div>
                        <div class="col-md-6 pb-3">
                            <h5 class="mb-2"><b>{{ __('Supplier') }} {{ __('Info') }}</b></h5>
                            @if($purchase->supplier)
                                <p class="mb-0">{{ __('Name') }}: {{ $purchase->supplier->name }}</p>
                            @endif
                            @if($purchase->supplier->phone)
                                <p class="mb-0">{{ __('Phone') }}: {{ $purchase->supplier->phone }}</p>
                            @endif
                            @if($purchase->supplier->address)
                                <p class="mb-0">{{ __('Address') }}: {{ $purchase->supplier->address }}</p>
                            @endif
                        </div>
                    </div>
                    
                    @if($purchase->note)
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6>{{ __('Note') }}:</h6>
                            <p>{{ $purchase->note }}</p>
                        </div>
                    </div>
                    @endif
                    
                    <h6 class="mb-3">Purchase Items</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>{{ __('SN') }}</th>
                                    <th>{{ __('Item Name') }}</th>
                                    <th>{{ __('IMEI/Serial/Medicine') }}</th>
                                    <th>{{ __('Quantity') }}</th>
                                    <th>{{ __('Unit Price') }}</th>
                                    <th>{{ __('Total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($purchase->purchaseDetails as $index => $detail)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        @if($detail->item)
                                            @if($detail->item->parent_id && $detail->item->parent)
                                                {{ $detail->item->parent->name }} - {{ $detail->item->name }}
                                            @else
                                                {{ $detail->item->name }}
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
                                    <td>{{ $detail->quantity_amount }}</td>
                                    <td>{{ formatAmount($detail->unit_price) }}</td>
                                    <td>{{ formatAmount($detail->total) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>


                    
                    <!-- Payment Information -->
                    
                    <div class="row mb-4 justify-content-end">
                        <div class="col-md-6 col-lg-4 col-xl-3">
                            <h5 class="mb-2 mt-4 border-dashed-bottom pb-2"><b>{{ __('Purchase Summary') }}</b></h5>
                            <table class="w-100">
                                <tr>
                                    <td width="w-50">{{ __('Discount') }}</td>
                                    <td width="w-50">: {{ $purchase->discount ?? __('N/A') }}</td>
                                </tr>
                                <tr>
                                    <td width="w-50">{{ __('Grand Total') }}</td>
                                    <td width="w-50">: {{ formatAmount($purchase->grand_total) }}</td>
                                </tr>
                                <tr>
                                    <td width="w-50">{{ __('Paid Amount') }}</td>
                                    <td width="w-50">: {{ formatAmount($purchase->paid) }}</td>
                                </tr>
                                <tr>
                                    <td width="w-50">{{ __('Due Amount') }}</td>
                                    <td width="w-50">: {{ formatAmount($purchase->due_amount) }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="row mb-4 justify-content-end">
                        <div class="col-md-6 col-lg-4 col-xl-3">
                            @if($purchase->purchasePayments && $purchase->purchasePayments->count() > 0)
                            <table class="w-100">
                                <thead>
                                    <tr>
                                        <th width="w-50">{{ __('Payment Method') }}</th>
                                        <th width="w-50">{{ __('Amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($purchase->purchasePayments as $index => $payment)
                                    <tr>
                                        <td width="w-50">{{ $payment->paymentMethod ? $payment->paymentMethod->name : __('N/A') }}</td>
                                        <td width="w-50">{{ formatAmount($payment->amount) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @endif
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
