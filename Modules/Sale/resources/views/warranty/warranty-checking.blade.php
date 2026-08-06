@extends('backend.backend_layout')
@section('page-title', __('Warranty Checking'))
@push('page-css')
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Warranty Checking') }}</h4>
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
                    'label' => __('Warranty Checking'),
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
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 col-md-8 offset-md-2">
                            <div class="text-center mb-4">
                                <h5 class="mb-3">{{ __('Search Product for Warranty Check') }}</h5>
                                <p class="text-muted">{{ __('Enter IMEI/Serial Number or Invoice Number to check warranty information') }}</p>
                            </div>

                            <form id="warranty-search-form">
                                <div class="mb-4">
                                    <label class="form-label">{{ __('Search By') }}</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="search_type" id="search_imei" value="imei" checked>
                                        <label class="btn btn-outline-primary" for="search_imei">{{ __('IMEI/Serial Number') }}</label>

                                        <input type="radio" class="btn-check" name="search_type" id="search_invoice" value="invoice">
                                        <label class="btn btn-outline-primary" for="search_invoice">{{ __('Invoice Number') }}</label>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label" for="search_value">{{ __('Search Keyword') }} {!! requiredField() !!}</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" 
                                            id="search_value" 
                                            name="search_value"
                                            placeholder="{{ __('Enter IMEI/Serial Number or Invoice Number') }}" 
                                            autocomplete="off"
                                            required>
                                        <button type="submit" class="btn btn-primary" id="search_btn">
                                            <i class="ti tabler-search"></i> {{ __('Search') }}
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <div id="search_results" style="display: none;">
                                <div class="card mt-4">
                                    <div class="card-body">
                                        <div id="search_error" class="alert alert-danger" style="display: none;"></div>
                                        <div id="search_success" style="display: none;">
                                            <div class="text-center mb-3">
                                                <h5 class="text-success" id="result_heading"></h5>
                                            </div>
                                            <div id="product_info" class="mb-3"></div>
                                            <div id="products_list" class="mb-3" style="display: none;"></div>
                                            <div class="text-center">
                                                <a href="#" id="view_invoice_link" class="btn btn-primary me-2" target="_blank">
                                                    <i class="ti tabler-file-invoice"></i> {{ __('View Invoice') }}
                                                </a>
                                                <button type="button" id="print_invoice_btn" class="btn btn-success">
                                                    <i class="ti tabler-printer"></i> {{ __('Print Invoice') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
@push('page-js')
<script>
    $(document).ready(function() {
        let base_url = $('#base_url').val();
        let csrfToken = $('meta[name="csrf-token"]').attr('content');
        let currentSaleId = null;

        // Handle form submission
        $('#warranty-search-form').on('submit', function(e) {
            e.preventDefault();
            searchWarranty();
        });

        // Search on Enter key press
        $('#search_value').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                searchWarranty();
            }
        });

        // Print invoice button
        $('#print_invoice_btn').on('click', function() {
            if (currentSaleId) {
                const printUrl = base_url + '/warranty/checking/invoice/' + currentSaleId;
                window.open(printUrl, '_blank');
            }
        });

        function searchWarranty() {
            let searchType = $('input[name="search_type"]:checked').val();
            let searchValue = $('#search_value').val().trim();
            
            if (!searchValue) {
                showError('Please enter a search value');
                return;
            }

            // Hide previous results
            $('#search_results').hide();
            $('#search_error').hide();
            $('#search_success').hide();
            $('#product_info').empty();
            $('#products_list').empty().hide();

            // Show loading
            $('#search_btn').prop('disabled', true).html('<i class="ti tabler-loader"></i> {{ __('Searching') }}...');

            $.ajax({
                url: base_url + '/warranty/checking/search',
                type: 'GET',
                data: {
                    search_type: searchType,
                    search_value: searchValue
                },
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                },
                success: function(response) {
                    $('#search_btn').prop('disabled', false).html('<i class="ti tabler-search"></i> {{ __('Search') }}');
                    
                    if (response.error) {
                        showError(response.error);
                        return;
                    }

                    if (response.success && response.data) {
                        currentSaleId = response.data.encrypted_sale_id;
                        if (response.data.search_type === 'invoice' && response.data.products && response.data.products.length > 0) {
                            displayInvoiceResults(response.data);
                        } else {
                            displayProductInfo(response.data);
                            $('#result_heading').text('{{ __("Product Found!") }}');
                        }
                        $('#view_invoice_link').attr('href', base_url + '/warranty/checking/invoice/' + currentSaleId);
                        $('#search_results').show();
                        $('#search_success').show();
                    }
                },
                error: function(xhr) {
                    $('#search_btn').prop('disabled', false).html('<i class="ti tabler-search"></i> {{ __('Search') }}');
                    
                    let errorMessage = 'Product not found';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMessage = xhr.responseJSON.error;
                    }
                    showError(errorMessage);
                }
            });
        }

        function displayProductInfo(data) {
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="mb-3">{{ __('Sale Information') }}</h6>
                        <p class="mb-2"><strong>{{ __('Sale No') }}:</strong> ${data.sale_no || 'N/A'}</p>
                        <p class="mb-2"><strong>{{ __('Sale Date') }}:</strong> ${data.sale_date || 'N/A'}</p>
                        <p class="mb-2"><strong>{{ __('Invoice No') }}:</strong> ${data.sale_no || 'N/A'}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="mb-3">{{ __('Customer Information') }}</h6>
                        <p class="mb-2"><strong>{{ __('Name') }}:</strong> ${data.customer_name || 'Walk-in Customer'}</p>
                        <p class="mb-2"><strong>{{ __('Phone') }}:</strong> ${data.customer_mobile || 'N/A'}</p>
                        <p class="mb-2"><strong>{{ __('Address') }}:</strong> ${data.customer_address || 'N/A'}</p>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="mb-3">{{ __('Product Information') }}</h6>
                        <p class="mb-2"><strong>{{ __('Product Name') }}:</strong> ${data.product_name || 'N/A'}</p>
                        <p class="mb-2"><strong>{{ __('Product Code') }}:</strong> ${data.product_code || 'N/A'}</p>
                        <p class="mb-2"><strong>{{ __('IMEI/Serial') }}:</strong> ${data.imei_serial || 'N/A'}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="mb-3">{{ __('Warranty & Guarantee') }}</h6>
                        <p class="mb-2"><strong>{{ __('Warranty') }}:</strong> ${data.warranty || 'N/A'}</p>
                        <p class="mb-2"><strong>{{ __('Warranty Period') }}:</strong> ${data.warranty_period || 'N/A'}</p>
                        <p class="mb-2"><strong>{{ __('Guarantee') }}:</strong> ${data.guarantee || 'N/A'}</p>
                        <p class="mb-2"><strong>{{ __('Guarantee Period') }}:</strong> ${data.guarantee_period || 'N/A'}</p>
                    </div>
                </div>
            `;
            $('#product_info').html(html);
        }

        function displayInvoiceResults(data) {
            const count = data.products.length;
            const heading = count === 1
                ? '{{ __("Product Found!") }}'
                : (count + ' {{ __("Products Found!") }}');
            $('#result_heading').text(heading);

            let saleHtml = `
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="mb-3">{{ __('Sale Information') }}</h6>
                        <p class="mb-2"><strong>{{ __('Sale No') }}:</strong> ${data.sale_no || 'N/A'}</p>
                        <p class="mb-2"><strong>{{ __('Sale Date') }}:</strong> ${data.sale_date || 'N/A'}</p>
                        <p class="mb-2"><strong>{{ __('Invoice No') }}:</strong> ${data.sale_no || 'N/A'}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="mb-3">{{ __('Customer Information') }}</h6>
                        <p class="mb-2"><strong>{{ __('Name') }}:</strong> ${data.customer_name || 'Walk-in Customer'}</p>
                        <p class="mb-2"><strong>{{ __('Phone') }}:</strong> ${data.customer_mobile || 'N/A'}</p>
                        <p class="mb-2"><strong>{{ __('Address') }}:</strong> ${data.customer_address || 'N/A'}</p>
                    </div>
                </div>
                <hr>
                <h6 class="mb-3">{{ __('Products with Warranty & Guarantee') }}</h6>
            `;
            $('#product_info').html(saleHtml);

            let productsHtml = '<div class="row">';
            data.products.forEach(function(p, index) {
                productsHtml += `
                    <div class="col-12 mb-3">
                        <div class="border rounded-3">
                            <div class="card-body py-3">
                                <h6 class="card-title text-primary mb-2">${index + 1}. ${p.product_name || 'N/A'}</h6>
                                <div class="row small">
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>{{ __('Product Code') }}:</strong> ${p.product_code || 'N/A'}</p>
                                        <p class="mb-1"><strong>{{ __('IMEI/Serial') }}:</strong> ${p.imei_serial || 'N/A'}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>{{ __('Warranty') }}:</strong> ${p.warranty || 'N/A'}</p>
                                        <p class="mb-1"><strong>{{ __('Warranty Period') }}:</strong> ${p.warranty_period || 'N/A'}</p>
                                        <p class="mb-1"><strong>{{ __('Guarantee') }}:</strong> ${p.guarantee || 'N/A'}</p>
                                        <p class="mb-1"><strong>{{ __('Guarantee Period') }}:</strong> ${p.guarantee_period || 'N/A'}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            productsHtml += '</div>';
            $('#products_list').html(productsHtml).show();
        }

        function showError(message) {
            $('#search_error').text(message).show();
            $('#search_results').show();
        }
    });
</script>
@endpush
