@extends('backend.backend_layout')
@section('page-title', __('Sort') . ' ' . __('Payment_Method'))
@push('page-css')
<style>
    .sortable-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .sortable-item {
        background: #fff;
        border: 1px solid #d9dee3;
        border-radius: 0.375rem;
        padding: 1rem;
        margin-bottom: 0.75rem;
        cursor: move;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .sortable-item:hover {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        border-color: #696cff;
    }
    .sortable-item.sortable-ghost {
        opacity: 0.4;
        background: #f5f5f5;
    }
    .sortable-item.sortable-drag {
        opacity: 0.8;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }
    .sortable-handle-2 {
        cursor: move;
        color: #696cff;
        font-size: 1.25rem;
        margin-right: 1rem;
        display: flex;
        align-items: center;
    }
    .payment-method-info {
        flex: 1;
    }
    .payment-method-name {
        font-weight: 600;
        color: #566a7f;
    }
    .payment-method-description {
        font-size: 0.875rem;
        color: #a1acb8;
    }
    .payment-method-type {
        font-size: 0.875rem;
        color: #696cff;
        margin-top: 0.25rem;
    }
    .sort-number {
        background: #696cff;
        color: #fff;
        width: 2rem;
        height: 2rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        margin-right: 1rem;
        flex-shrink: 0;
    }
</style>
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Sort') }} {{ __('Payment_Method') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Payment_Method'), 
                    'link' => route('payment-method.index')
                ],
                [
                    'label' => __('Sort') . ' ' . __('Payment_Method'),
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
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Drag and drop to reorder payment methods') }}</h5>
                    <p class="text-danger mb-0 ">{{ __('The order will be saved automatically when you click the Save Order button') }}</p>
                </div>
                <div class="card-body">
                    @php
                        $paymentMethods = $paymentMethods ?? collect([]);
                    @endphp
                    @if($paymentMethods->count() > 0)
                        <ul id="sortablePaymentMethods" class="sortable-list">
                            @foreach($paymentMethods as $paymentMethod)
                                <li class="sortable-item sortable-handle" data-id="{{ $paymentMethod->id }}">
                                    <div class="sort-number">{{ $loop->iteration }}</div>
                                    <div class="sortable-handle-2">
                                        <i class="ti tabler-grip-vertical"></i>
                                    </div>
                                    <div class="payment-method-info">
                                        <div class="payment-method-name">{{ $paymentMethod->name ?? 'N/A' }}</div>
                                        @if(!empty($paymentMethod->description))
                                            <div class="payment-method-description">{{ $paymentMethod->description }}</div>
                                        @endif
                                        @if(!empty($paymentMethod->account_type))
                                            <div class="payment-method-type">
                                                <span class="badge bg-label-info">{{ $paymentMethod->account_type }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="alert alert-info">
                            <i class="ti tabler-info-circle me-2"></i>
                            {{ __('No payment methods found') }}. {{ __('Please add payment methods first') }}.
                        </div>
                    @endif
                </div>
                @if($paymentMethods->count() > 0)
                    <div class="card-footer">
                        <div class="d-flex justify-content-start">
                            <button type="button" id="saveSortOrder" class="btn btn-primary me-2">
                                <i class="ti tabler-device-floppy me-1"></i>
                                {{ __('Save Order') }}
                            </button>
                            <a href="{{ route('payment-method.index') }}" class="btn btn-label-secondary">
                                {!! backIconWithText() !!}
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection

@push('page-js')
@routes
<script src="{{ asset('backend_assets/vendor/libs/sortablejs/sortable.js') }}"></script>
<script>
$(function () {
    "use strict";
    
    /** #################### -- The base JS part should be on top of all JS files --  #################### **/
    // Language Translator
    let language_name = $('#language_name').val();
    let language_key;
    try {
        language_key = JSON.parse(language_translator);
    } catch (e) {
        console.error('Error parsing language translator:', e);
        language_key = {};
    }

    // Company Info
    let company_data = $('#company_data').val();
    let company_session_data;
    try {
        company_session_data = JSON.parse(company_data);
    } catch (e) {
        console.error('Error parsing company info:', e);
        company_session_data = {};
    }
    let base_url = $('#base_url').val();
    /** #################### -- The base JS part should be on top of all JS files --  #################### **/

    // Wait for SortableJS to be loaded
    function initializeSortable() {
        // Check if Sortable is available
        if (typeof Sortable === 'undefined') {
            console.error('SortableJS library is not loaded');
            if (typeof showErrorNotification === 'function') {
                showErrorNotification('SortableJS library is not loaded. Please refresh the page.');
            }
            return;
        }

        // Initialize Sortable
        let sortableList = document.getElementById('sortablePaymentMethods');
        if (sortableList && sortableList.children.length > 0) {
            try {
                let sortable = Sortable.create(sortableList, {
                    handle: '.sortable-handle',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    dragClass: 'sortable-drag',
                    onEnd: function(evt) {
                        // Update sort numbers
                        updateSortNumbers();
                    }
                });

                // Update sort numbers on page load
                updateSortNumbers();

                // Function to update sort numbers
                function updateSortNumbers() {
                    $('#sortablePaymentMethods .sortable-item').each(function(index) {
                        $(this).find('.sort-number').text(index + 1);
                    });
                }

                // Save sort order
                $('#saveSortOrder').on('click', function() {
                    let $btn = $(this);
                    let originalText = $btn.html();
                    
                    // Get sorted IDs
                    let sortedIds = [];
                    $('#sortablePaymentMethods .sortable-item').each(function() {
                        let paymentMethodId = $(this).data('id');
                        if (paymentMethodId) {
                            sortedIds.push(paymentMethodId);
                        }
                    });

                    if (sortedIds.length === 0) {
                        if (typeof showErrorNotification === 'function') {
                            showErrorNotification('No payment methods to sort');
                        }
                        return;
                    }


                    // Get route
                    const updateSortRoute = route('payment-method.update-sort-order', {}, false, Ziggy);

                    // Send AJAX request
                    $.ajax({
                        type: "POST",
                        url: base_url + updateSortRoute,
                        data: {
                            sorted_ids: sortedIds,
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        dataType: "json",
                        success: function (response) {
                            if (response.status === 'success') {
                                if (typeof showSuccessNotification === 'function') {
                                    showSuccessNotification(response.message || 'Payment method order updated successfully');
                                }
                                // Update sort numbers to reflect saved state
                                updateSortNumbers();
                            } else {
                                if (typeof showErrorNotification === 'function') {
                                    showErrorNotification(response.message || 'Failed to update payment method order');
                                }
                            }
                        },
                        error: function (xhr) {
                            let errorMessage = 'An error occurred while updating payment method order';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMessage = xhr.responseJSON.message;
                            }
                            if (typeof showErrorNotification === 'function') {
                                showErrorNotification(errorMessage);
                            }
                        },
                        complete: function() {
                            // Re-enable button
                            $btn.prop('disabled', false).html(originalText);
                        }
                    });
                });
            } catch (error) {
                console.error('Error initializing Sortable:', error);
                if (typeof showErrorNotification === 'function') {
                    showErrorNotification('Error initializing drag and drop. Please refresh the page.');
                }
            }
        }
    }

    // Initialize when document is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(initializeSortable, 100);
        });
    } else {
        setTimeout(initializeSortable, 100);
    }
});
</script>
@endpush
