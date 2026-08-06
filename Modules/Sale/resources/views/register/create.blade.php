@extends('backend.backend_layout')
@section('page-title', __('Open') . ' ' . __('Register'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/flatpickr/flatpickr.css') }}" />
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Open Register -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Open') }} {{ __('Register') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Register'), 
                    'link' => '#'
                ],
                [
                    'label' => __('Open') . ' ' . __('Register'),
                    'active' => true
                ]
            ]
        ])
    </div>

    @if(session('error'))
        {!! insertFailed(session('error')) !!}
    @endif

    <div class="row">
        <div class="col-12">
            <form id="form_register_open">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="d-flex justify-content-between">
                                    <div class="mb-5 w-100 validate_wrapper">
                                        <label class="form-label">{{ __('Counter') }} <span class="text-danger">*</span></label>
                                        <select class="form-control select2" id="register_counter_id" name="counter_id" required>
                                            <option value="">{{ __('Select') }} {{ __('Counter') }}</option>
                                            @foreach($counters as $counter)
                                                <option value="{{ $counter->id }}">{{ $counter->name }}</option>
                                            @endforeach
                                        </select>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    <a href="{{ route('counter.create') }}" class="fw-medium btn btn-icon btn-label-primary ms-4 add-plus-btn" target="_blank"
                                        ><i class="icon-base ti tabler-plus icon-md"></i></a>
                                </div>
                            </div>
                        </div>
                        <div class="row" id="payment_methods_container">
                            @foreach($payment_methods as $method)
                            <div class="col-md-6 col-lg-4 mb-3">
                                <label class="form-label">{{ $method->name }}</label>
                                <input 
                                    type="text" 
                                    class="form-control number-input payment-method-balance" 
                                    data-payment-method-id="{{ $method->id }}"
                                    data-payment-method-name="{{ $method->name }}"
                                    placeholder="0.00"
                                    value="0"
                                >
                            </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4" id="btn_open_register">
                                {!! submitIconWithText(isset($promotion) ? $promotion : '') !!}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('page-js')
@routes
<script src="{{ asset('backend_assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
<script src="{{ asset('backend_assets/js/custom_js/register.js') }}"></script>
@endpush
