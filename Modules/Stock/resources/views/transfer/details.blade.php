@extends('backend.backend_layout')
@section('page-title', __('Transfer') . ' ' . __('Details'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/css/view-details.css') }}">
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Transfer') }} {{ __('Details') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Transfer'), 
                    'link' => route('transfer.index')
                ],
                [
                    'label' => __('Transfer') . ' ' . __('Details'),
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
                <div id="printableArea" class="card-body">
                    <div id="wrapper" class="m-auto border-2s-e4e5ea br-5">
                
                        <div class="row mt-4">
                            <div class="col-md-6 pb-3">
                                <h5 class="mb-2"><b>{{ __('Transfer') }} {{ __('Info') }}</b></h5>
                                @if($transfer->date)
                                    <p class="mb-0">{{ __('Date') }}: {{ formatDate($transfer->date) }}</p>
                                @endif
                                @if($transfer->reference_no)
                                    <p class="mb-0">{{ __('Reference No') }}: {{ $transfer->reference_no }}</p>
                                @endif
                                @if($transfer->status)
                                    @php
                                        $statusMap = [1 => 'Draft', 2 => 'Sent', 3 => 'Received'];
                                        $statusString = is_numeric($transfer->status) ? ($statusMap[$transfer->status] ?? 'Draft') : $transfer->status;
                                        $statusClass = $statusString == 'Draft' ? 'secondary' : ($statusString == 'Sent' ? 'warning' : 'success');
                                    @endphp
                                    <p class="mb-0">{{ __('Status') }}: <span class="badge bg-{{ $statusClass }}">{{ $statusString }}</span></p>
                                @endif
                            </div>
                            <div class="col-md-6 pb-3">
                                <div class="d-flex justify-content-center">
                                    <div>
                                        <h5 class="mb-2"><b>{{ __('Outlet') }} {{ __('Info') }}</b></h5>
                                        @if($transfer->fromOutlet)
                                            <p class="mb-0"><strong>{{ __('From Outlet') }}:</strong> {{ $transfer->fromOutlet->outlet_name }} ({{ $transfer->fromOutlet->outlet_code }})</p>
                                        @endif
                                        @if($transfer->toOutlet)
                                            <p class="mb-0"><strong>{{ __('To Outlet') }}:</strong> {{ $transfer->toOutlet->outlet_name }} ({{ $transfer->toOutlet->outlet_code }})</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
              
                        <h5 class="pt-4 font-width-700"><span class="ont-700 pt-2 pb-2">{{ __('Transfer') }} {{ __('Details') }}</span></h5>
                        <div>
                            <div class="table-responsive"> 
                                <table class="table w-100 mt-20">
                                    <thead class="br-3">
                                        <tr>
                                            <th class="w-5">{{ __('SN') }}</th>
                                            <th class="w-30 text-start">{{ __('Item') }}-{{ __('Code') }}-{{ __('Brand') }}</th>
                                            <th class="w-15 text-center">{{ __('IMEI/Serial') }}</th>
                                            <th class="w-15 text-center">{{ __('Quantity') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $i = 0;
                                        @endphp
                                        @if($transfer->transferDetails && $transfer->transferDetails->count() > 0)
                                            @foreach($transfer->transferDetails as $detail)
                                                @php
                                                    $i++;
                                                @endphp
                                                <tr>
                                                    <td>
                                                        <span>{{ $i }}</span>
                                                    </td>
                                                    <td class="text-start">
                                                        <span>
                                                            {{ $detail->item->name ?? '' }}
                                                            @if($detail->item && $detail->item->code)
                                                                ({{ $detail->item->code }})
                                                            @endif
                                                            @if($detail->item && $detail->item->brand_id && $detail->item->brand)
                                                                - {{ $detail->item->brand->name }}
                                                            @endif
                                                        </span>
                                                    </td>
                                                    
                                                    <td class="text-center">
                                                        @if($detail->expiry_imei_serial)
                                                            @if($detail->item && in_array($detail->item_type ?? $detail->item->type, ['IMEI_Product', 'Serial_Product']))
                                                                <span class="badge bg-light text-dark me-1">{{ $detail->expiry_imei_serial }}</span>
                                                            @elseif($detail->item && ($detail->item_type ?? $detail->item->type) === 'Medicine_Product')
                                                                <span class="badge bg-light text-dark me-1">{{ $detail->expiry_imei_serial }}</span>
                                                            @else
                                                                {{ $detail->expiry_imei_serial }}
                                                            @endif
                                                        @else
                                                            {{ __('N/A') }}
                                                        @endif
                                                    </td>
                                                    <td class="text-center">
                                                        {{ $detail->quantity_amount ?? $detail->quantity ?? 0 }}
                                                        @if($detail->item && $detail->item->saleUnit)
                                                            {{ $detail->item->saleUnit->unit_name }}
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="d-grid g-template-c-50-40 grid-gap-10 pt-20">
                            <div>
                                <div class="pt-20">
                                    @if($transfer->note_for_sender || $transfer->note_for_receiver)
                                    <h5 class="d-block pb-10">{{ __('Notes') }}</h5>
                                    <div class="w-100 pt-10">
                                        @if($transfer->note_for_sender)
                                        <p><strong>{{ __('Note for Sender') }}:</strong> {{ $transfer->note_for_sender }}</p>
                                        @endif
                                        @if($transfer->note_for_receiver)
                                        <p><strong>{{ __('Note for Receiver') }}:</strong> {{ $transfer->note_for_receiver }}</p>
                                        @endif
                                    </div> 
                                    @endif
                                </div>
                            </div>
                            <div>
                                <div class="details_footer d-flex justify-content-between foot_common_bg br-3">
                                    <p class="color-71">{{ __('Total Items') }}</p>
                                    <p>{{ $transfer->transferDetails ? $transfer->transferDetails->count() : 0 }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> 
                <div class="card-footer">
                    <div class="d-flex">
                        <a href="{{ route('transfer.edit', $transfer->encrypted_id) }}" class="btn btn-primary waves-effect waves-light me-4">
                            {!! editIconWithText(__('Edit')) !!}
                        </a>
                        <a href="{{ route('transfer.index') }}" class="btn btn-primary waves-effect waves-light">
                            {!! backIconWithText() !!}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


