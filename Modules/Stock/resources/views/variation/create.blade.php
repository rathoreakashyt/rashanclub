@extends('backend.backend_layout')
@section('page-title', isset($variation_attribute) ? __('Update') . ' ' . __('Variation') : __('Add') . ' ' . __('Variation') )
@push('page-css')
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($variation_attribute) ? __('Update') . ' ' . __('Variation') : __('Add') . ' ' . __('Variation') }}</h4>
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
                    'label' => isset($variation_attribute) ? __('Update') . ' ' . __('Variation') : __('Add') . ' ' . __('Variation'),
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
            <form action="{{ isset($variation_attribute) ? route('variation-attribute.update', $variation_attribute->encrypted_id) : route('variation-attribute.store') }}" method="POST">
                @csrf
                @if(isset($variation_attribute))
                    @method('PUT')
                @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <div class="mb-5">
                                    <label class="form-label" for="variation_name">{{ __('Variation') }} {{ __('Name') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('variation_name') is-invalid @enderror" 
                                        placeholder="{{ __('Variation') }} {{ __('Name') }}" name="variation_name" id="variation_name" 
                                        value="{{ old('variation_name', isset($variation_attribute) ? $variation_attribute->variation_name : '') }}" />
                                    @error('variation_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="row append_attribute">
                            @php
                            // Get old values first (from validation failure), then fallback to existing data
                            $oldVariationValues = old('variation_value', []);
                            $existingVariationValues = isset($variation_attribute) ? (is_array($variation_attribute->variation_value) ? $variation_attribute->variation_value : json_decode($variation_attribute->variation_value, true) ?? []) : [];
                            
                            // Use old values if available (from validation), otherwise use existing
                            $variation_values = !empty($oldVariationValues) ? $oldVariationValues : $existingVariationValues;
                            
                            // If still empty, add one empty field
                            if (empty($variation_values)) {
                                $variation_values = [''];
                            }
                            @endphp
                            @foreach($variation_values as $index => $value)
                            <div class="col-12 mb-2 wrapper_row">
                                <label class="form-label">{{ __('Attribute_Value') }} {!! requiredField() !!}</label>
                                <div class="input-group">
                                    <input type="text" class="form-control variation_value @error('variation_value.' . $index) is-invalid @enderror"
                                        placeholder="{{ __('Attribute_Value') }}"
                                        name="variation_value[]"
                                        value="{{ $value }}" />
                                    <button class="btn btn-outline-danger delete-attribute" type="button">
                                        <i class="ti tabler-trash"></i>
                                    </button>
                                </div>
                                @error('variation_value.' . $index)
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            @endforeach
                        </div>
                        
                        @error('variation_value')
                            @php
                                // Check if this is an array-level error (not a specific index error)
                                $errorMessage = $message;
                                $isArrayLevelError = !str_contains($errorMessage, 'variation_value.') && 
                                                    (str_contains($errorMessage, 'required') || 
                                                     str_contains($errorMessage, 'array') || 
                                                     str_contains($errorMessage, 'distinct') ||
                                                     str_contains($errorMessage, 'unique'));
                            @endphp
                            @if($isArrayLevelError)
                                <div class="alert alert-danger mt-2">
                                    <i class="ti tabler-alert-circle me-2"></i>{{ $message }}
                                </div>
                            @endif
                        @enderror
                        <button type="button" class="btn btn-primary btn-sm waves-effect waves-light me-4 add_attribute">
                            <i class="ti tabler-plus me-2"></i>
                            {{ __('Add') }} {{ __('Attribute') }}
                        </button>

                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4 attribute_submit">
                                {!! submitIconWithText(isset($variation_attribute) ? $variation_attribute : '') !!}
                            </button>
                            <a href="{{ route('variation-attribute.index') }}" class="btn btn-primary waves-effect waves-light">
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
@if($errors->any())
<script>
    // Pass validation errors to JavaScript
    window.validationErrors = @json($errors->getMessages());
</script>
@endif
<script src="{{ asset('backend_assets/js/pages_js/attribute.js')}}"></script>
@endpush