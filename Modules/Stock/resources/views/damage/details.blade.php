@extends('backend.backend_layout')
@section('page-title', __('Damage') . ' ' . __('Details'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/css/view-details.css') }}">
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Damage') }} {{ __('Details') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Damage'), 
                    'link' => route('damage.index')
                ],
                [
                    'label' => __('Damage') . ' ' . __('Details'),
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
                                    @if($damage->outlet)
                                        @if($damage->outlet->outlet_name)
                                            <p class="mb-0">{{ $damage->outlet->outlet_name }}</p>
                                        @endif
                                        @if($damage->outlet->email)
                                            <p class="mb-0">{{ __('Email') }}: {{ $damage->outlet->email }}</p>
                                        @endif
                                        @if($damage->outlet->phone)
                                            <p class="mb-0">{{ __('Phone') }}: {{ $damage->outlet->phone }}</p>
                                        @endif
                                        @if($damage->outlet->address)
                                            <p class="mb-0">{{ __('Address') }}: {{ $damage->outlet->address }}</p>
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
                                    <h5 class="mb-2"><b>{{ __('Damage') }} {{ __('Info') }}</b></h5>
                                    @if($damage->date)
                                        <p class="mb-0">{{ __('Date') }}: {{ formatDate($damage->date) }}</p>
                                    @endif
                                    @if($damage->reference_no)
                                        <p class="mb-0">{{ __('Reference No') }}: {{ $damage->reference_no }}</p>
                                    @endif
                                </div>
                                <div class="col-md-6 pb-3">
                                    <div class="d-flex justify-content-center">
                                        <div>
                                            <h5 class="mb-2"><b>{{ __('Employee') }} {{ __('Info') }}</b></h5>
                                            @if($damage->employee ?? $damage->responsiblePerson)
                                                @php
                                                    $employee = $damage->employee ?? $damage->responsiblePerson;
                                                @endphp
                                                @if($employee->name)
                                                    <p class="mb-0">{{ __('Name') }}: {{ $employee->name }}</p>
                                                @endif
                                                @if($employee->email)
                                                    <p class="mb-0">{{ __('Email') }}: {{ $employee->email }}</p>
                                                @endif
                                                @if($employee->phone ?? null)
                                                    <p class="mb-0">{{ __('Phone') }}: {{ $employee->phone }}</p>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                        <h5 class="pt-4 font-width-700"><span class="ont-700 pt-2 pb-2">{{ __('Damage') }} {{ __('Details') }}</span></h5>
                        <div>
                            <div class="table-responsive"> 
                                <table class="table w-100 mt-20">
                                    <thead class="br-3">
                                        <tr>
                                            <th class="w-5">{{ __('SN') }}</th>
                                            <th class="w-30 text-start">{{ __('Item') }}-{{ __('Code') }}-{{ __('Brand') }}</th>
                                            <th class="w-15 text-center">{{ __('IMEI/Serial/Medicine') }}</th>
                                            <th class="w-15 text-center">{{ __('Damage Qty') }}</th>
                                            <th class="w-15">{{ __('Damage Amount') }}</th>
                                            <th class="w-20">{{ __('Total') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $i = 0;
                                        @endphp
                                        @if($damage->damageDetails && $damage->damageDetails->count() > 0)
                                            @foreach($damage->damageDetails as $detail)
                                                @php
                                                    $i++;
                                                @endphp
                                                <tr>
                                                    <td>
                                                        <span>{{ $i }}</span>
                                                    </td>
                                                    <td class="text-start">
                                                        <span>
                                                            @if($detail->item)
                                                                @if($detail->item->parent_id && $detail->item->parent)
                                                                    {{ $detail->item->parent->name }} - {{ $detail->item->name }}
                                                                @else
                                                                    {{ $detail->item->name }}
                                                                @endif
                                                                @if($detail->item->code)
                                                                    ({{ $detail->item->code }})
                                                                @endif
                                                                @if($detail->item->brand_id && $detail->item->brand)
                                                                    - {{ $detail->item->brand->name }}
                                                                @endif
                                                            @else
                                                                N/A
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
                                                            N/A
                                                        @endif
                                                    </td>
                                                    <td class="text-center">{{ $detail->damage_quantity ?? 0 }}</td>
                                                    <td>{{ formatAmount($detail->last_purchase_price ?? 0) }}</td>
                                                    <td>{{ formatAmount($detail->total_amount ?? 0) }}</td>
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
                                    @if($damage->note)
                                    <h5 class="d-block pb-10">{{ __('Note') }}</h5>
                                    <div class="w-100 pt-10">
                                        <p>
                                            {{ $damage->note }}
                                        </p>
                                    </div> 
                                    @endif
                                </div>
                            </div>
                            <div>
                                <div class="details_footer d-flex justify-content-between foot_common_bg br-3">
                                    <p class="color-71">{{ __('Total Loss') }}</p>
                                    <p>{{ formatAmount($damage->total_loss) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> 
                <div class="card-footer">
                    <div class="d-flex">
                        <a href="{{ route('damage.edit', $damage->encrypted_id) }}" class="btn btn-primary waves-effect waves-light me-4">
                            {!! editIconWithText(__('Edit')) !!}
                        </a>
                        <a href="{{ route('damage.index') }}" class="btn btn-primary waves-effect waves-light">
                            {!! backIconWithText() !!}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

