@extends('backend.backend_layout')
@section('page-title', __('Price Lists'))
@push('page-css')
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Price Lists') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => '#'],
                ['label' => __('Stock'), 'link' => '#'],
                ['label' => __('Price Lists'), 'active' => true]
            ]
        ])
    </div>

    @if(session('success'))
        {!! insertSuccess(session('success')) !!}
    @endif

    <div class="card">
        <div class="card-header">
            <a href="{{ route('price-list.create') }}" class="btn btn-primary waves-effect waves-light">
                {!! addIconWithText(__('Add') . ' ' . __('Price List')) !!}
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered datatables-basic-price-lists">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th>{{ __('Customer_Type') }}</th>
                            <th>{{ __('Items_Count') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
@push('page-js')
<script src="{{ asset('backend_assets/js/pages_list_js/list_price_list.js')}}"></script>
@endpush
