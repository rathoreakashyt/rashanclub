@extends('backend.backend_layout')
@section('page-title', 'List Sale Return')
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <!-- List Sale Return -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('List') }} {{ __('Sale Return') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' =>  __('Sale Return'), 
                    'link' => '#'
                ],
                [
                    'label' => __('List') . ' ' .  __('Sale Return'),
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
    @if($status == 'success')
        {!! insertSuccess('Sale return created successfully') !!}
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-datatable table-responsive pt-0">
                    <table class="datatables-basic table">
                        <thead>
                            <tr>
                                <th>{{ __('SN') }}</th>
                                <th>{{ __('Reference No') }}</th>
                                <th>{{ __('Customer') }}</th>
                                <th>{{ __('Sale Invoice') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Total Return Amount') }}</th>
                                <th>{{ __('Paid') }}</th>
                                <th>{{ __('Due') }}</th>
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
<script src="{{ asset('backend_assets/js/pages_list_js/list_sale_returns.js') }}"></script>
<script src="{{ asset('backend_assets/js/pages_list_js/delete_confirmation.js') }}"></script>
@endpush
