@extends('backend.backend_layout')
@section('page-title', __('Purchase Return Details'))
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
    <!-- Purchase Return Details -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Purchase Return Details') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Purchase Return'), 
                    'link' => route('purchase-return.index')
                ],
                [
                    'label' => __('Purchase Return Details'),
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
                            <a href="{{ route('purchase-return.print-invoice', $purchaseReturn->encrypted_id) }}" target="_blank" class="btn btn-info">
                                <i class="ti tabler-printer me-1"></i>
                            </a>
                            <a href="{{ route('purchase-return.generate-pdf', $purchaseReturn->encrypted_id) }}" class="btn btn-secondary">
                                <i class="ti tabler-download me-1"></i>
                            </a>
                            <a href="{{ route('purchase-return.edit', $purchaseReturn->encrypted_id) }}" class="btn btn-warning">
                                <i class="ti tabler-edit me-1"></i>
                            </a>
                            <a href="{{ route('purchase-return.index') }}" class="btn btn-primary">
                                <i class="ti tabler-arrow-back-up me-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row my-4">
                        <div class="col-md-6 pb-3">
                            <h5 class="mb-2"><b>{{ __('Purchase Return') }} {{ __('Info') }}</b></h5>
                            @if($purchaseReturn->reference_no)
                                <p class="mb-0">{{ __('Reference No') }}: {{ $purchaseReturn->reference_no }}</p>
                            @endif
                            @if($purchaseReturn->date)
                                <p class="mb-0">{{ __('Date') }}: {{ formatDate($purchaseReturn->date) }}</p>
                            @endif
                            @if($purchaseReturn->purchase_date)
                                <p class="mb-0">{{ __('Purchase Date') }}: {{ formatDate($purchaseReturn->purchase_date) }}</p>
                            @endif
                            @if($purchaseReturn->invoice_no)
                                <p class="mb-0">{{ __('Invoice No') }}: {{ $purchaseReturn->invoice_no }}</p>
                            @endif
                        </div>
                        <div class="col-md-6 pb-3">
                            <h5 class="mb-2"><b>{{ __('Supplier') }} {{ __('Info') }}</b></h5>
                            @if($purchaseReturn->supplier)
                                <p class="mb-0">{{ __('Name') }}: {{ $purchaseReturn->supplier->name }}</p>
                            @endif
                            @if($purchaseReturn->supplier && $purchaseReturn->supplier->phone)
                                <p class="mb-0">{{ __('Phone') }}: {{ $purchaseReturn->supplier->phone }}</p>
                            @endif
                            @if($purchaseReturn->supplier && $purchaseReturn->supplier->address)
                                <p class="mb-0">{{ __('Address') }}: {{ $purchaseReturn->supplier->address }}</p>
                            @endif
                        </div>
                    </div>
                    
                    @if($purchaseReturn->note)
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6>{{ __('Note') }}:</h6>
                            <p>{{ $purchaseReturn->note }}</p>
                        </div>
                    </div>
                    @endif
                    
                    <h6 class="mb-3">{{ __('Purchase Return Items') }}</h6>
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
                                @foreach($purchaseReturn->purchaseReturnDetails as $index => $detail)
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
                                            {{ __('N/A') }}
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
                                            {{ __('N/A') }}
                                        @endif
                                    </td>
                                    <td>{{ $detail->return_quantity_amount }}</td>
                                    <td>{{ formatAmount($detail->unit_price) }}</td>
                                    <td>{{ formatAmount($detail->total) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    
                    <!-- Purchase Return Summary -->
                    
                    <div class="row mb-4 justify-content-end">
                        <div class="col-md-6 col-lg-4 col-xl-3">
                            <h5 class="mb-2 mt-4 border-dashed-bottom pb-2"><b>{{ __('Purchase Return Summary') }}</b></h5>
                            <table class="w-100">
                                <tr>
                                    <td width="w-50">{{ __('Status') }}</td>
                                    <td width="w-50">:
                                        @php
                                            $status = $purchaseReturn->return_status ?? 'draft';
                                            if ($status === 'draft') {
                                                $statusClass = 'secondary';
                                                $statusText = __('Draft');
                                            } elseif ($status === 'taken_by_sup_pro_not_returned') {
                                                $statusClass = 'warning';
                                                $statusText = __('Taken By Supplier Product Not Returned');
                                            } elseif ($status === 'taken_by_sup_money_returned') {
                                                $statusClass = 'success';
                                                $statusText = __('Taken By Supplier Money Returned');
                                            } elseif ($status === 'taken_by_sup_pro_returned') {
                                                $statusClass = 'info';
                                                $statusText = __('Taken By Supplier Product Returned');
                                            } else {
                                                $statusClass = 'secondary';
                                                $statusText = ucfirst(str_replace('_', ' ', $status));
                                            }
                                        @endphp
                                        <span class="badge bg-label-{{ $statusClass }}">{{ $statusText }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td width="w-50">{{ __('Grand Total') }}</td>
                                    <td width="w-50">: {{ formatAmount($purchaseReturn->total_return_amount) }}</td>
                                </tr>
                                @if($purchaseReturn->return_status === 'taken_by_sup_money_returned' && $purchaseReturn->total_return_amount)
                                <tr>
                                    <td width="w-50">{{ __('Amount') }}</td>
                                    <td width="w-50">: {{ formatAmount($purchaseReturn->total_return_amount) }}</td>
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

