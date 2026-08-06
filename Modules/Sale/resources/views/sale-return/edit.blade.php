@extends('backend.backend_layout')
@section('page-title', __('Edit') . ' ' . __('Sale Return'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/flatpickr/flatpickr.css') }}" />
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Edit Sale Return -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Edit') }} {{ __('Sale Return') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Sale Return'), 
                    'link' => route('sale-return.index')
                ],
                [
                    'label' => __('Edit') . ' ' . __('Sale Return'),
                    'active' => true
                ]
            ]
        ])
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                @include('sale::sale-return.partials.form', [
                    'isPosContext' => false,
                    'saleReturn' => $saleReturn,
                    'customers' => $customers,
                    'payment_methods' => $payment_methods
                ])
            </div>
        </div>
    </div>
    
</div>

@endsection

@push('page-js')
@routes
<script src="{{ asset('backend_assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
<script src="{{ asset('backend_assets/js/pages_js/add_sale_return.js') }}"></script>
<script>
    // Initialize existing rows when page loads
    $(function() {
        setTimeout(function() {
            // Initialize events for existing rows
            $('#saleReturnItems tr').each(function() {
                const row = $(this);
                const itemType = row.find('.item-type').val();
                const imeiSerialInput = row.find('.imei-serial');
                
                // Show/hide IMEI/Serial input field based on item type
                if (itemType === 'IMEI_Product' || itemType === 'Serial_Product' || itemType === 'Medicine_Product') {
                    imeiSerialInput.show();
                    imeiSerialInput.prop('readonly', true);
                } else {
                    imeiSerialInput.hide();
                }
                
                // Initialize row events if function exists
                if (typeof initializeRowEvents === 'function') {
                    initializeRowEvents(row);
                }
            });
            
            // Update summary after loading
            if (typeof updateSummary === 'function') {
                updateSummary();
            }
            
            // Load sale invoice items if sale is selected
            const saleId = $('#sale_id').val();
            if (saleId) {
                $('#sale_id').trigger('change');
            }
        }, 300);
    });
</script>
@endpush
