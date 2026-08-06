@extends('backend.backend_layout')
@section('page-title', __('Add') . ' ' . __('Sale Return'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/flatpickr/flatpickr.css') }}" />
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Add Sale Return -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Add') }} {{ __('Sale Return') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Sale Return'), 
                    'link' => '#'
                ],
                [
                    'label' => __('Add') . ' ' . __('Sale Return'),
                    'active' => true
                ]
            ]
        ])
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                @include('sale::sale-return.partials.form', [
                    'isPosContext' => false,
                    'customers' => $customers,
                    'payment_methods' => $payment_methods,
                    'reference_no' => $reference_no
                ])
            </div>
        </div>
    </div>
    
</div>

@endsection

@push('page-js')
@routes
<script src="{{ asset('backend_assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
<script src="{{ asset('backend_assets/js/pages_js/add_sale_return.js') }}"></script>
@endpush
