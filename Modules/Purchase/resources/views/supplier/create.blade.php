@extends('backend.backend_layout')
@section('page-title', isset($supplier) ? __('Update') . ' ' . __('Supplier') : __('Add') . ' ' . __('Supplier') )
@push('page-css')
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($supplier) ? __('Update') . ' ' . __('Supplier') : __('Add') . ' ' . __('Supplier') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Purchase'), 
                    'link' => '#'
                ],
                [
                    'label' => isset($supplier) ? __('Update') . ' ' . __('Supplier') : __('Add') . ' ' . __('Supplier'),
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
            <form action="{{ isset($supplier) ? route('supplier.update', encrypt($supplier->id)) : route('supplier.store') }}" method="POST">
                @csrf
                @if(isset($supplier))
                    @method('PUT')
                @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="name">{{ __('Name') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                        placeholder="{{ __('Name') }}" name="name" id="name" 
                                        value="{{ old('name', isset($supplier) ? $supplier->name : '') }}" />
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="contact_person">{{ __('contact_person') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('contact_person') is-invalid @enderror" 
                                        placeholder="{{ __('contact_person') }}" name="contact_person" id="contact_person" 
                                        value="{{ old('contact_person', isset($supplier) ? $supplier->contact_person : '') }}" />
                                    @error('contact_person')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="phone">{{ __('Phone') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('phone') is-invalid @enderror" 
                                        placeholder="{{ __('Phone') }}" name="phone" id="phone" 
                                        value="{{ old('phone', isset($supplier) ? $supplier->phone : '') }}" />
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="email">{{ __('Email') }}</label>
                                    <input type="text" class="form-control @error('email') is-invalid @enderror" 
                                        placeholder="{{ __('Email') }}" name="email" id="email" 
                                        value="{{ old('email', isset($supplier) ? $supplier->email : '') }}" />
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <label class="form-label" for="opening_balance">{{ __('opening_balance') }}</label>
                                <div class="d-flex jsutify-content-between">
                                    <div class="mb-5 me-1 w-100">
                                        <input type="text" class="form-control @error('opening_balance') is-invalid @enderror" 
                                            placeholder="{{ __('opening_balance') }}" name="opening_balance" id="opening_balance" 
                                            value="{{ old('opening_balance', isset($supplier) ? $supplier->opening_balance : '') }}" />
                                        @error('opening_balance')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="mb-5 w-100">
                                        <select class="form-select select2 form-select @error('opening_balance_type') is-invalid @enderror" 
                                            name="opening_balance_type" id="opening_balance_type" data-placeholder="{{ __('opening_balance_type') }}">
                                            <option value="Debit" {{ (old('opening_balance_type') == 'Debit' || (isset($supplier) && in_array($supplier->opening_balance_type, ['Debit', 'Dr']) && !old('opening_balance_type'))) ? 'selected' : '' }}>
                                                {{ __('Debit') }}
                                            </option>
                                            <option value="Credit" {{ (old('opening_balance_type') == 'Credit' || (isset($supplier) && in_array($supplier->opening_balance_type, ['Credit', 'Cr']) && !old('opening_balance_type'))) ? 'selected' : '' }}>
                                                {{ __('Credit') }}
                                            </option>
                                        </select>
                                        @error('opening_balance_type')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="address">{{ __('Address') }}</label>
                                    <textarea type="text" class="form-control @error('address') is-invalid @enderror" 
                                        placeholder="{{ __('Address') }}" name="address" id="address" 
                                        >{{ old('address', isset($supplier) ? $supplier->address : '') }}</textarea>
                                    @error('address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="description">{{ __('Description') }}</label>
                                    <textarea type="text" class="form-control @error('description') is-invalid @enderror" 
                                        placeholder="{{ __('Description') }}" name="description" id="description" 
                                        >{{ old('description', isset($supplier) ? $supplier->description : '') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText(isset($supplier) ? $supplier : '') !!}
                            </button>
                            <a href="{{ route('supplier.index') }}" class="btn btn-primary waves-effect waves-light">
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

