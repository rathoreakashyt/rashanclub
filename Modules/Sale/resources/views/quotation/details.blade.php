@extends('backend.backend_layout')
@section('page-title', __('Quotation') . ' ' . __('Details'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/css/view-details.css') }}">
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Quotation') }} {{ __('Details') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Quotation'), 
                    'link' => route('quotation.index')
                ],
                [
                    'label' => __('Quotation') . ' ' . __('Details'),
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
                        <div>
                            <div class="row mt-3">
                                <div class="col-sm-6">
                                    <h5 class="mb-2"><b>{{ session('company.business_name', '') }}</b></h5>
                                    @if($quotation->outlet)
                                        @if($quotation->outlet->outlet_name)
                                            <p class="mb-0">{{ $quotation->outlet->outlet_name }}</p>
                                        @endif
                                        @if($quotation->outlet->email)
                                            <p class="mb-0">{{ __('Email') }}: {{ $quotation->outlet->email }}</p>
                                        @endif
                                        @if($quotation->outlet->phone)
                                            <p class="mb-0">{{ __('Phone') }}: {{ $quotation->outlet->phone }}</p>
                                        @endif
                                        @if($quotation->outlet->address)
                                            <p class="mb-0">{{ __('Address') }}: {{ $quotation->outlet->address }}</p>
                                        @endif
                                    @endif
                                </div>

                                <div class="col-sm-6">
                                    <div class="d-flex justify-content-end">
                                        @php
                                            $invoice_logo = session('company.invoice_logo');
                                        @endphp
                                        @if($invoice_logo)
                                            <img src="{{ asset('uploads/site_settings/' . $invoice_logo) }}" alt="Logo" height="50px">
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-4">
                                <div class="col-md-6 pb-3">
                                    <h5 class="mb-2"><b>{{ __('Quotation') }} {{ __('Info') }}</b></h5>
                                    @if($quotation->date)
                                        <p class="mb-0">{{ __('Date') }}: {{ formatDate($quotation->date) }}</p>
                                    @endif
                                    @if($quotation->reference_no)
                                        <p class="mb-0">{{ __('Reference No') }}: {{ $quotation->reference_no }}</p>
                                    @endif
                                </div>
                                <div class="col-md-6 pb-3">
                                    <div class="d-flex justify-content-center">
                                        <div>
                                            <h5 class="mb-2"><b>{{ __('Customer') }} {{ __('Info') }}</b></h5>
                                            @if($quotation->customer)
                                                @if($quotation->customer->name)
                                                    <p class="mb-0">{{ __('Name') }}: {{ $quotation->customer->name }}</p>
                                                @endif
                                                @if($quotation->customer->phone)
                                                    <p class="mb-0">{{ __('Phone') }}: {{ $quotation->customer->phone }}</p>
                                                @endif
                                                @if($quotation->customer->email)
                                                    <p class="mb-0">{{ __('Email') }}: {{ $quotation->customer->email }}</p>
                                                @endif
                                                @if($quotation->customer->address)
                                                    <p class="mb-0">{{ __('Address') }}: {{ $quotation->customer->address }}</p>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                        <h5 class="pt-4 font-width-700"><span class="ont-700 pt-2 pb-2">{{ __('Quotation') }} {{ __('Details') }}</span></h5>
                        <div>
                            <div class="table-responsive"> 
                                <table class="table w-100 mt-20">
                                    <thead class="br-3">
                                        <tr>
                                            <th class="w-5">{{ __('SN') }}</th>
                                            <th class="w-30 text-start">{{ __('Item') }}-{{ __('Code') }}-{{ __('Brand') }}</th>
                                            <th class="w-15 text-center">{{ __('Qty') }}</th>
                                            <th class="w-15">{{ __('Unit Price') }}</th>
                                            <th class="w-20">{{ __('Total') }}</th>
                                            <th class="w-15 text-start">{{ __('Note') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $i = 0;
                                        @endphp
                                        @if($quotation->quotationDetails && $quotation->quotationDetails->count() > 0)
                                            @foreach($quotation->quotationDetails as $detail)
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
                                                    
                                                    <td class="text-center">{{ $detail->quantity }}</td>
                                                    <td>{{ formatAmount($detail->unit_price) }}</td>
                                                    <td>{{ formatAmount($detail->total) }}</td>
                                                    <td class="text-start">
                                                        <span>{{ $detail->description ?? '' }}</span>
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
                                    @if($quotation->note)
                                    <h5 class="d-block pb-10">{{ __('Note') }}</h5>
                                    <div class="w-100 pt-10">
                                        <p>
                                            {{ $quotation->note }}
                                        </p>
                                    </div> 
                                    @endif
                                </div>
                            </div>
                            <div>
                                <div class="details_footer d-flex justify-content-between br-3">
                                    @php
                                        $discount = $quotation->discount ?? '0';
                                        $discount_ac = '';
                                        if(strpos($discount, '%') !== false) {
                                            $discount_ac = $discount;
                                        } else {
                                            $discount_ac = formatAmount($discount);
                                        }
                                    @endphp
                                    <p class="color-71">{{ __('Discount') }}</p>
                                    <p>{{ $discount_ac }}</p>
                                </div>
                                <div class="details_footer d-flex justify-content-between foot_common_bg br-3">
                                    <p class="color-71">{{ __('Grand Total') }}</p>
                                    <p>{{ formatAmount($quotation->grand_total) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> 
                <div class="card-footer">
                    <div class="d-flex">
                        <a href="{{ route('quotation.print-invoice', $quotation->encrypted_id) }}" target="_blank" class="btn btn-primary waves-effect waves-light me-4">
                            {!! printIconWithText(__('Print')) !!}
                        </a>
                        <a href="{{ route('quotation.edit', $quotation->encrypted_id) }}" class="btn btn-primary waves-effect waves-light me-4">
                            {!! editIconWithText(__('Edit')) !!}
                        </a>
                        <a href="{{ route('quotation.index') }}" class="btn btn-primary waves-effect waves-light">
                            {!! backIconWithText() !!}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

