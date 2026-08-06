@extends('backend.backend_layout')
@section('page-title', isset($warranty) ? __('Update') . ' ' . __('Warranty') : __('Add') . ' ' . __('Warranty') )
@push('page-css')
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($warranty) ? __('Update') . ' ' . __('Warranty') : __('Add') . ' ' . __('Warranty') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Warranty'), 
                    'link' => '#'
                ],
                [
                    'label' => isset($warranty) ? __('Update') . ' ' . __('Warranty') : __('Add') . ' ' . __('Warranty'),
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
            <form action="{{ isset($warranty) ? route('warranty.update', $warranty->encrypted_id) : route('warranty.store') }}" method="POST">
                @csrf
                @if(isset($warranty))
                    @method('PUT')
                @endif
                <div class="card">
                    <div class="card-body">
                        <!-- Product Search Section -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h5 class="card-title mb-3">{{ __('Search Product by IMEI/Serial Number') }}</h5>
                                        <div class="row">
                                            <div class="col-12 col-md-8">
                                                <div class="input-group">
                                                    <input type="text" class="form-control" 
                                                        id="imei_serial_search" 
                                                        placeholder="{{ __('Enter IMEI/Serial Number') }}" 
                                                        autocomplete="off">
                                                    <button type="button" class="btn btn-primary" id="search_product_btn">
                                                        <i class="ti tabler-search"></i> {{ __('Search') }}
                                                    </button>
                                                </div>
                                                <div id="search_results" class="mt-3" style="display: none;">
                                                    <div class="list-group" id="product_results_list"></div>
                                                </div>
                                                <div id="search_error" class="alert alert-danger mt-2" style="display: none;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="customer_id">{{ __('Customer') }} {!! requiredField() !!}</label>
                                    <select class="form-select select2 @error('customer_id') is-invalid @enderror" 
                                        name="customer_id" id="customer_id" data-placeholder="{{ __('Select Customer') }}">
                                        <option value="">{{ __('Select Customer') }}</option>
                                        @foreach($customers ?? [] as $customer)
                                            <option value="{{ $customer->id }}" 
                                                {{ old('customer_id', isset($warranty) ? $warranty->customer_id : '') == $customer->id ? 'selected' : '' }}>
                                                {{ $customer->name }} @if($customer->phone) ({{ $customer->phone }}) @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('customer_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="product_name">{{ __('Product Name') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('product_name') is-invalid @enderror" 
                                        placeholder="{{ __('Product Name') }}" name="product_name" id="product_name" 
                                        value="{{ old('product_name', isset($warranty) ? $warranty->product_name : '') }}" />
                                    @error('product_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="product_serial_no">{{ __('Product Serial No') }}</label>
                                    <input type="text" class="form-control @error('product_serial_no') is-invalid @enderror" 
                                        placeholder="{{ __('Product Serial No') }}" name="product_serial_no" id="product_serial_no" 
                                        value="{{ old('product_serial_no', isset($warranty) ? $warranty->product_serial_no : '') }}" />
                                    @error('product_serial_no')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="receiving_date">{{ __('Receiving Date') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control datePicker @error('receiving_date') is-invalid @enderror" 
                                        placeholder="{{ __('Receiving Date') }}" name="receiving_date" id="receiving_date" 
                                        value="{{ old('receiving_date', isset($warranty) ? $warranty->receiving_date->format('Y-m-d') : date('Y-m-d')) }}" readonly />
                                    @error('receiving_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="delivery_date">{{ __('Delivery Date') }}</label>
                                    <input type="text" class="form-control datePicker @error('delivery_date') is-invalid @enderror" 
                                        placeholder="{{ __('Delivery Date') }}" name="delivery_date" id="delivery_date" 
                                        value="{{ old('delivery_date', isset($warranty) && $warranty->delivery_date ? $warranty->delivery_date->format('Y-m-d') : '') }}" readonly />
                                    @error('delivery_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="current_status">{{ __('Status') }} {!! requiredField() !!}</label>
                                    <select class="form-select select2 @error('current_status') is-invalid @enderror" 
                                        name="current_status" id="current_status" data-placeholder="{{ __('Select Status') }}">
                                        <option value="">{{ __('Select Status') }}</option>
                                        <option value="R_F_C" {{ old('current_status', isset($warranty) ? $warranty->current_status : '') == 'Pending' ? 'selected' : '' }}>
                                            {{ __('Receive From Customer') }}
                                        </option>
                                        <option value="S_T_V" {{ old('current_status', isset($warranty) ? $warranty->current_status : '') == 'S_T_V' ? 'selected' : '' }}>
                                            {{ __('Send To Vendor') }}
                                        </option>
                                        <option value="R_T_V" {{ old('current_status', isset($warranty) ? $warranty->current_status : '') == 'R_T_V' ? 'selected' : '' }}>
                                            {{ __('Receive From Vendor') }}
                                        </option>
                                        <option value="D_T_C" {{ old('current_status', isset($warranty) ? $warranty->current_status : '') == 'D_T_C' ? 'selected' : '' }}>
                                            {{ __('Delivered To Customer') }}
                                        </option>
                                    </select>
                                    @error('current_status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="technician_id">{{ __('Technician') }}</label>
                                    <select class="form-select select2 @error('technician_id') is-invalid @enderror" 
                                        name="technician_id" id="technician_id" data-placeholder="{{ __('Select Technician') }}">
                                        <option value="">{{ __('Select Technician') }}</option>
                                        @foreach($technicians ?? [] as $technician)
                                            <option value="{{ $technician->id }}" 
                                                {{ old('technician_id', isset($warranty) ? $warranty->technician_id : '') == $technician->id ? 'selected' : '' }}>
                                                {{ $technician->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('technician_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            
                            <div class="col-12">
                                <div class="mb-5">
                                    <label class="form-label" for="description">{{ __('Description') }}</label>
                                    <textarea class="form-control @error('description') is-invalid @enderror" 
                                        placeholder="{{ __('Description') }}" name="description" id="description" 
                                        rows="3">{{ old('description', isset($warranty) ? $warranty->description : '') }}</textarea>
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
                                {!! submitIconWithText(isset($warranty) ? $warranty : '') !!}
                            </button>
                            <a href="{{ route('warranty.index') }}" class="btn btn-primary waves-effect waves-light">
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
<script>
    $(document).ready(function() {
        let base_url = $('#base_url').val();
        let csrfToken = $('meta[name="csrf-token"]').attr('content');

        // Search product by IMEI/Serial
        $('#search_product_btn').on('click', function() {
            searchProduct();
        });

        // Search on Enter key press
        $('#imei_serial_search').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                searchProduct();
            }
        });

        function searchProduct() {
            let imeiSerial = $('#imei_serial_search').val().trim();
            
            if (!imeiSerial) {
                showError('Please enter IMEI/Serial number');
                return;
            }

            // Hide previous results and errors
            $('#search_results').hide();
            $('#search_error').hide();
            $('#product_results_list').empty();

            // Show loading
            $('#search_product_btn').prop('disabled', true).html('<i class="ti tabler-loader"></i> {{ __('Searching') }}...');

            $.ajax({
                url: base_url + '/warranty/search-product',
                type: 'GET',
                data: {
                    imei_serial: imeiSerial
                },
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                },
                success: function(response) {
                    $('#search_product_btn').prop('disabled', false).html('<i class="ti tabler-search"></i> {{ __('Search') }}');
                    
                    if (response.error) {
                        showError(response.error);
                        return;
                    }

                    // Fill form fields
                    if (response.customer_id) {
                        $('#customer_id').val(response.customer_id).trigger('change');
                    }
                    if (response.product_name) {
                        $('#product_name').val(response.product_name);
                    }
                    if (response.product_serial_no) {
                        $('#product_serial_no').val(response.product_serial_no);
                    }

                    // Show success message
                    showSuccessNotification('Product found and form filled successfully!');
                },
                error: function(xhr) {
                    $('#search_product_btn').prop('disabled', false).html('<i class="ti tabler-search"></i> {{ __('Search') }}');
                    
                    let errorMessage = 'Product not found with this IMEI/Serial number';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMessage = xhr.responseJSON.error;
                    }
                    showError(errorMessage);
                }
            });
        }

        function showError(message) {
            $('#search_error').text(message).show();
            $('#search_results').hide();
        }

    });
</script>
@endpush
