@extends('backend.backend_layout')
@section('page-title', isset($multiple_currency) ? __('Update') . ' ' . __('Multiple_Currency') : __('Add') . ' ' . __('Multiple_Currency') )
@push('page-css')
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($multiple_currency) ? __('Update') . ' ' . __('Multiple_Currency') : __('Add') . ' ' . __('Multiple_Currency') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Item') . ' ' . __('Configuration'), 
                    'link' => '#'
                ],
                [
                    'label' => isset($multiple_currency) ? __('Update') . ' ' . __('Multiple_Currency') : __('Add') . ' ' . __('Multiple_Currency'),
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
            <form action="{{ isset($multiple_currency) ? route('multiple-currency.update', encrypt($multiple_currency->id)) : route('multiple-currency.store') }}" method="POST">
                @csrf
                @if(isset($multiple_currency))
                    @method('PUT')
                @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="currency">{{ __('Currency') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('currency') is-invalid @enderror" 
                                        placeholder="{{ __('Currency') }}" name="currency" id="currency" 
                                        value="{{ old('currency', isset($multiple_currency) ? $multiple_currency->currency : '') }}" />
                                    @error('currency')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="conversion_rate">{{ __('Conversion_Rate') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control number-input @error('conversion_rate') is-invalid @enderror" 
                                        placeholder="{{ __('Conversion_Rate') }}" name="conversion_rate" id="conversion_rate" 
                                        value="{{ old('conversion_rate', isset($multiple_currency) ? $multiple_currency->conversion_rate : '') }}" />
                                    @error('conversion_rate')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText(isset($multiple_currency) ? $multiple_currency : '') !!}
                            </button>
                            <a href="{{ route('multiple-currency.index') }}" class="btn btn-primary waves-effect waves-light">
                                {!! backIconWithText() !!}
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('page-js')
@endpush

