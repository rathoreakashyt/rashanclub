@extends('backend.backend_layout')
@section('page-title', isset($income_category) ? __('Update') . ' ' . __('Income_Category') : __('Add') . ' ' . __('Income_Category') )
@push('page-css')
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($income_category) ? __('Update') . ' ' . __('Income_Category') : __('Add') . ' ' . __('Income_Category') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Sale') . ' ' . __('Customer'), 
                    'link' => '#'
                ],
                [
                    'label' => isset($income_category) ? __('Update') . ' ' . __('Income_Category') : __('Add') . ' ' . __('Income_Category'),
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
            <form action="{{ isset($income_category) ? route('income-category.update', encrypt($income_category->id)) : route('income-category.store') }}" method="POST">
                @csrf
                @if(isset($income_category))
                    @method('PUT')
                @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="name">{{ __('Income Category Name') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                        placeholder="{{ __('Income Category Name') }}" name="name" id="name" 
                                        value="{{ old('name', isset($income_category) ? $income_category->name : '') }}" />
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="description">{{ __('Description') }}</label>
                                    <textarea type="text" class="form-control @error('description') is-invalid @enderror" 
                                        placeholder="{{ __('Description') }}" name="description" id="description" 
                                        >{{ old('description', isset($income_category) ? $income_category->description : '') }}</textarea>
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
                                {!! submitIconWithText(isset($income_category) ? $income_category : '') !!}
                            </button>
                            <a href="{{ route('income-category.index') }}" class="btn btn-primary waves-effect waves-light">
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

