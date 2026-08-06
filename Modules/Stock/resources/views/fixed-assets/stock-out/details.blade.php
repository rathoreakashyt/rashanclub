@extends('backend.backend_layout')
@section('page-title', __('Stock Out') . ' ' . __('Details'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/css/view-details.css') }}">
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Stock Out') }} {{ __('Details') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Fixed Assets'), 
                    'link' => '#'
                ],
                [
                    'label' => __('Stock Out') . ' ' . __('Details'),
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
                            <div class="row mt-4">
                                <div class="col-md-6 pb-3">
                                    <h5 class="mb-2"><b>{{ __('Stock Out') }} {{ __('Info') }}</b></h5>
                                    @if($stockOut->name)
                                        <p class="mb-0">{{ __('Name') }}: {{ $stockOut->name }}</p>
                                    @endif
                                    @if($stockOut->date)
                                        <p class="mb-0">{{ __('Date') }}: {{ formatDate($stockOut->date) }}</p>
                                    @endif
                                    @if($stockOut->reference_no)
                                        <p class="mb-0">{{ __('Reference No') }}: {{ $stockOut->reference_no }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <h5 class="pt-4 font-width-700"><span class="ont-700 pt-2 pb-2">{{ __('Stock Out') }} {{ __('Details') }}</span></h5>
                        <div>
                            <div class="table-responsive"> 
                                <table class="table w-100 mt-20">
                                    <thead class="br-3">
                                        <tr>
                                            <th class="w-5">{{ __('SN') }}</th>
                                            <th class="w-30 text-start">{{ __('Item') }}-{{ __('Code') }}-{{ __('Brand') }}</th>
                                            <th class="w-15 text-center">{{ __('Quantity') }}</th>
                                            <th class="w-15">{{ __('Unit Price') }}</th>
                                            <th class="w-20">{{ __('Total') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $i = 0;
                                        @endphp
                                        @if($stockOut->stockOutDetails && $stockOut->stockOutDetails->count() > 0)
                                            @foreach($stockOut->stockOutDetails as $detail)
                                                @php
                                                    $i++;
                                                @endphp
                                                <tr>
                                                    <td>
                                                        <span>{{ $i }}</span>
                                                    </td>
                                                    <td class="text-start">
                                                        <span>
                                                            {{ $detail->fixedAssetItem->name ?? '' }}
                                                        </span>
                                                    </td>
                                                    
                                                    <td class="text-center">{{ $detail->quantity }}</td>
                                                    <td>{{ formatAmount($detail->unit_price) }}</td>
                                                    <td>{{ formatAmount($detail->total) }}</td>
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
                                    @if($stockOut->note)
                                    <h5 class="d-block pb-10">{{ __('Note') }}</h5>
                                    <div class="w-100 pt-10">
                                        <p>
                                            {{ $stockOut->note }}
                                        </p>
                                    </div> 
                                    @endif
                                </div>
                            </div>
                            <div>
                                <div class="details_footer d-flex justify-content-between foot_common_bg br-3">
                                    <p class="color-71">{{ __('Grand Total') }}</p>
                                    <p>{{ formatAmount($stockOut->grand_total) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> 
                <div class="card-footer">
                    <div class="d-flex">
                        <a href="{{ route('fixed-asset-stock-out.edit', $stockOut->encrypted_id) }}" class="btn btn-primary waves-effect waves-light me-4">
                            {!! editIconWithText(__('Edit')) !!}
                        </a>
                        <a href="{{ route('fixed-asset-stock-out.index') }}" class="btn btn-primary waves-effect waves-light">
                            {!! backIconWithText() !!}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

