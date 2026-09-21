@extends('backend.backend_layout')
@section('page-title', __('List') . ' ' . __('Transfer'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/css/transfer_modern.css') }}" />
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Hero Header -->
    <div class="tf-hero">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 position-relative">
            <div class="d-flex align-items-center gap-3">
                <div class="tf-hero-icon"><i class="ti tabler-transfer-in"></i></div>
                <div>
                    <h4 class="tf-hero-title">{{ __('List') }} {{ __('Transfer') }}</h4>
                    <span class="tf-hero-sub">{{ __('Manage stock transfers between outlets') }}</span>
                </div>
            </div>
            <div class="d-flex flex-column align-items-md-end gap-2">
                @include('backend.components.breadcrumb', [
                    'breadcrumbs' => [
                        [
                            'label' => '<i class="ti tabler-home"></i>',
                            'link' => '#'
                        ],
                        [
                            'label' => __('Transfer'),
                            'link' => '#'
                        ],
                        [
                            'label' => __('List') . ' ' . __('Transfer'),
                            'active' => true
                        ]
                    ]
                ])
                <a href="{{ route('transfer.create') }}" class="tf-hero-btn">
                    <i class="ti tabler-plus"></i>
                    <span>{{ __('Add Transfer') }}</span>
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
        {!! insertSuccess(session('success')) !!}
    @endif
    @if(session('error'))
        {!! insertFailed(session('error')) !!}
    @endif

    <!-- Stat Cards -->
    <div class="row g-4 mb-6">
        <div class="col-sm-6 col-xl-3">
            <div class="card tf-stat-card">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="tf-stat-icon tf-stat-total"><i class="ti tabler-transfer-in"></i></div>
                    <div>
                        <div class="tf-count" id="statTotal">0</div>
                        <div class="tf-label">{{ __('Total Transfers') }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card tf-stat-card">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="tf-stat-icon tf-stat-draft"><i class="ti tabler-file-download"></i></div>
                    <div>
                        <div class="tf-count" id="statDraft">0</div>
                        <div class="tf-label">{{ __('Draft') }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card tf-stat-card">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="tf-stat-icon tf-stat-sent"><i class="ti tabler-send"></i></div>
                    <div>
                        <div class="tf-count" id="statSent">0</div>
                        <div class="tf-label">{{ __('Sent') }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card tf-stat-card">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="tf-stat-icon tf-stat-recvd"><i class="ti tabler-checkbox"></i></div>
                    <div>
                        <div class="tf-count" id="statReceived">0</div>
                        <div class="tf-label">{{ __('Received') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="row">
        <div class="col-12">
            <div class="card tf-card">
                <div class="card-datatable table-responsive pt-0">
                    <table class="datatables-basic table">
                        <thead>
                            <tr>
                                <th>{{ __('SN') }}</th>
                                <th>{{ __('Reference No') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('From Outlet') }}</th>
                                <th>{{ __('To Outlet') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
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
<!-- Page JS -->
<script src="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{ asset('backend_assets/js/extended-ui-sweetalert2.js')}}"></script>
<script src="{{ asset('backend_assets/js/pages_list_js/list_transfer.js')}}"></script>
<script src="{{ asset('backend_assets/js/pages_list_js/delete_confirmation.js')}}"></script>
@endpush
