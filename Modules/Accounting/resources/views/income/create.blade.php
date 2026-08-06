@extends('backend.backend_layout')
@section('page-title', isset($income) ? __('Update') . ' ' . __('Income') : __('Add') . ' ' . __('Income') )
@push('page-css')
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($income) ? __('Update') . ' ' . __('Income') : __('Add') . ' ' . __('Income') }}</h4>
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
                    'label' => isset($income) ? __('Update') . ' ' . __('Income') : __('Add') . ' ' . __('Income'),
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
            <form action="{{ isset($income) ? route('income.update', encrypt($income->id)) : route('income.store') }}" method="POST">
                @csrf
                @if(isset($income))
                    @method('PUT')
                @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="reference_no">{{ __('Reference No') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('reference_no') is-invalid @enderror" 
                                        placeholder="{{ __('Reference No') }}" name="reference_no" id="reference_no" 
                                        value="{{ old('reference_no', isset($income) ? $income->reference_no : $reference_no) }}" />
                                    @error('reference_no')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="date">{{ __('Date') }} {!! requiredField() !!}</label>
                                    <input type="text"
                                        class="form-control datePicker @error('date') is-invalid @enderror"
                                        name="date"
                                        id="date"
                                        value="{{ old('date', $income->date ?? date('Y-m-d')) }}"
                                        readonly>
                                    @error('date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="d-flex justify-content-between">
                                    <div class="mb-5 w-100">
                                        <label class="form-label" for="category_id">{{ __('Income') }} {{ __('Category') }} {!! requiredField() !!}</label>
                                        <select class="form-select select2 @error('category_id') is-invalid @enderror" 
                                            name="category_id" id="category_id" data-placeholder="{{ __('Select') }} {{ __('Category') }}">
                                            <option value="">{{ __('Select') }} {{ __('Category') }}</option>
                                            @foreach($income_categories as $category)
                                            <option value="{{ $category->id }}"
                                                {{ old('category_id', $income->category_id ?? '') == $category->id ? 'selected' : '' }}>
                                                {{ $category->name }}
                                            </option>
                                            @endforeach
                                        </select>
                                        @error('category_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <a type="button" href="javascript:void(0);" class="fw-medium btn btn-icon btn-label-primary ms-4 add-plus-btn" data-bs-toggle="modal"
                                        data-bs-target="#modal_income_category">
                                        <i class="icon-base ti tabler-plus icon-md"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="employee_id">{{ __('Employee') }} {!! requiredField() !!}</label>
                                    <select class="form-select select2 @error('employee_id') is-invalid @enderror" 
                                        name="employee_id" id="employee_id" data-placeholder="{{ __('Select') }} {{ __('Employee') }}">
                                        <option value="">{{ __('Select') }} {{ __('Employee') }}</option>
                                        @foreach($employees as $employee)
                                            <option value="{{ $employee->id }}"
                                                {{ old('employee_id', $income->employee_id ?? '') == $employee->id ? 'selected' : '' }}>
                                                {{ $employee->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('employee_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="amount">{{ __('Amount') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control number-input @error('amount') is-invalid @enderror" 
                                        placeholder="{{ __('Amount') }}" name="amount" id="amount" 
                                        value="{{ old('amount', isset($income) ? $income->amount : '') }}" />
                                    @error('amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="payment_method_id">{{ __('Payment_Method') }} {!! requiredField() !!}</label>
                                    <select class="form-select select2 @error('payment_method_id') is-invalid @enderror" 
                                        name="payment_method_id" id="payment_method_id" data-placeholder="{{ __('Select') }} {{ __('Payment_Method') }}">
                                        <option value="">{{ __('Select') }} {{ __('Payment_Method') }}</option>
                                        @foreach($payment_methods as $method)
                                            <option value="{{ $method->id }}"
                                                {{ old('payment_method_id', $income->payment_method_id ?? '') == $method->id ? 'selected' : '' }}>
                                                {{ $method->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('payment_method_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="note">{{ __('Note') }}</label>
                                    <textarea type="text" class="form-control @error('note') is-invalid @enderror" 
                                        placeholder="{{ __('Note') }}" name="note" id="note" 
                                        >{{ old('note', isset($income) ? $income->note : '') }}</textarea>
                                    @error('note')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText(isset($income) ? $income : '') !!}
                            </button>
                            <a href="{{ route('income.index') }}" class="btn btn-primary waves-effect waves-light">
                                {!! backIconWithText() !!}
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Income Category Quick Add -->
<div class="modal fade" id="modal_income_category" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="incomeCategoryForm">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Income') }} {{ __('Category') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 mb-4 validate_wrapper">
                            <label for="income_category_name" class="form-label">{{ __('Category') }} {{ __('Name') }} {!! requiredField() !!}</label>
                            <input type="text" id="income_category_name" name="name" class="form-control" placeholder="{{ __('Enter') }} {{ __('Category') }} {{ __('Name') }}" />
                        </div>
                        <div class="col-12 mb-4 validate_wrapper">
                            <label for="income_category_description" class="form-label">{{ __('Description') }}</label>
                            <textarea id="income_category_description" name="description" class="form-control" placeholder="{{ __('Enter') }} {{ __('Description') }}"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary add_income_category">
                        {!! submitIconWithText('') !!}
                    </button>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                        {!! closeIconWithText() !!}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script>
    (function ($) {
        'use strict';
        const incomeCategoryForm = $('#incomeCategoryForm');
        const incomeCategoryModal = $('#modal_income_category');
        const categorySelect = $('#category_id');
        const placeholder = "{{ __('Select') }} {{ __('Category') }}";
        const storeUrl = "{{ route('income-category.store') }}";
        function clearValidation() {
            incomeCategoryForm.find('.is-invalid').removeClass('is-invalid');
            incomeCategoryForm.find('.invalid-feedback').remove();
        }
        function refreshCategoryOptions(categories, selectedId = null) {
            categorySelect.empty();
            categorySelect.append(`<option value="">${placeholder}</option>`);
            categories.forEach(function (category) {
                const option = $('<option></option>').val(category.id).text(category.name);
                if (selectedId && Number(category.id) === Number(selectedId)) {
                    option.prop('selected', true);
                }
                categorySelect.append(option);
            });
            if (categorySelect.hasClass('select2-hidden-accessible')) {
                categorySelect.select2('destroy');
            }
            categorySelect.select2({
                dropdownParent: categorySelect.parent(),
                placeholder: placeholder
            });
        }
        incomeCategoryForm.on('submit', function (e) {
            e.preventDefault();
            clearValidation();
            $.ajax({
                type: 'POST',
                url: storeUrl,
                data: incomeCategoryForm.serialize(),
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function (response) {
                    if (response && response.status === 'success' && response.data && response.data.categories) {
                        refreshCategoryOptions(response.data.categories, response.data.category ? response.data.category.id : null);
                        incomeCategoryForm[0].reset();
                        incomeCategoryModal.modal('hide');
                        if (typeof showSuccessNotification === 'function') {
                            showSuccessNotification(response.message || '{{ __('Income category added successfully') }}');
                        }
                    } else if (typeof showErrorNotification === 'function') {
                        showErrorNotification(response.message || '{{ __('Something went wrong') }}');
                    }
                },
                error: function (xhr) {
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        const errors = xhr.responseJSON.errors;
                        Object.keys(errors).forEach(function (field) {
                            const input = incomeCategoryForm.find('[name="' + field + '"]');
                            if (input.length) {
                                input.addClass('is-invalid');
                                input.after('<div class="invalid-feedback">' + errors[field][0] + '</div>');
                            }
                        });
                    } else if (typeof showErrorNotification === 'function') {
                        showErrorNotification('{{ __('An unexpected error occurred') }}');
                    }
                }
            });
        });
    })(jQuery);
</script>
@endpush