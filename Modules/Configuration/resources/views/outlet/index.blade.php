@extends('backend.backend_layout')
@section('page-title', __('List') . ' ' . __('Outlet'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Add Product -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('List') }} {{ __('Outlet') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Outlet'), 
                    'link' => '#'
                ],
                [
                    'label' => __('List').' '.__('Outlet'),
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
        @foreach ($outlets as $outlet)
        <div class="col-xl-4 col-lg-4 col-md-6 col-sm-6 col-12 mb-5">
            <div class="card">
                <div class="card-body">
                    <img src="{{ asset('uploads/business_setting/outlet_icon.png') }}" alt="{{ __('Outlet') }}">
                    @if($outlet->outlet_name)
                        <h3 class="mt-2 text-truncate">{{ $outlet->outlet_name }}</h3>
                    @endif
                    @if($outlet->outlet_code)
                        <h5>{{ __('Outlet') }} {{ __('Code') }}: {{ $outlet->outlet_code }}</h5>
                    @endif
                    <hr>
                    @if($outlet->address)
                        <p> <i class="ti tabler-map-pin me-2"></i> {{ __('Address') }}: {{ $outlet->address }}</p>
                    @endif
                    @if($outlet->phone)
                        <p class="d-flex align-items-center"> <i class="ti tabler-phone me-2"></i> {{ __('Phone') }}: {{ $outlet->phone }}</p>
                    @endif
                    @if($outlet->email)
                        <p class="d-flex align-items-center mb-0"> <i class="ti tabler-mail me-2"></i> {{ __('Email') }}: {{ $outlet->email }}</p>
                    @endif
                </div>
                <div class="card-footer">
                    <div class="d-flex">
                        <a href="{{ route('outlet.edit', encrypt($outlet->id)) }}" class="btn btn-primary waves-effect waves-light w-100 me-2"> <i class="ti tabler-edit me-2"></i> {{ __('Edit') }}</a>
                            <button type="button" data-id="{{ $outlet->id }}" class="delete_data btn btn-danger waves-effect waves-light w-100">
                            <i class="ti tabler-trash me-2"></i>
                            {{ __('Delete') }}
                        </button>
                        <form id="deleteForm-{{ $outlet->id }}" action="{{ route('outlet.destroy', encrypt($outlet->id)) }}" method="POST" style="display: none;">
                            @csrf
                            @method('DELETE')
                        </form>
                    </div>
                    <a href="{{ route('outlet.enter', encrypt($outlet->id))}}" class="btn btn-primary waves-effect waves-light w-100 mt-2"> <i class="ti tabler-corner-down-right-double me-2"></i> {{ __('Enter') }}</a>
                    
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>

@endsection
@push('page-js')
<!-- Page JS -->
<script src="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{ asset('backend_assets/js/extended-ui-sweetalert2.js')}}"></script>
<script src="{{ asset('backend_assets/js/pages_list_js/delete_confirmation.js')}}"></script>
@endpush

