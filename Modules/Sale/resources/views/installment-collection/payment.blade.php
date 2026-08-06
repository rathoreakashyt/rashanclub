@extends('backend.backend_layout')
@section('page-title', __('Installment') . ' ' . __('Payment'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
<style>
    .badge-unpaid { background-color: var(--bs-form-invalid-color);; }
    .badge-paid { background-color: #28a745; }
    .badge-partial { background-color: #ffc107; color: #000; }
    .badge-overdue { background-color: #6f42c1; }
    .info-card { border-left: 4px solid #696cff; }
    .payment-trigger {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }
    .payment-trigger i{
        width: 1.25em;
        height: 1.25em;
    }
</style>
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Installment') }} {{ __('Payment') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Installment') . ' ' . __('Collection'), 
                    'link' => route('installment-collection.index')
                ],
                [
                    'label' => __('Payment'),
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
        <!-- Customer Information -->
        <div class="col-12 col-lg-6 mb-4">
            <div class="card info-card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Customer') }} {{ __('Information') }}</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>{{ __('Name') }}:</strong><br>
                        <span>{{ $customer->name ?? 'N/A' }}</span>
                    </div>
                    <div class="mb-3">
                        <strong>{{ __('Phone') }}:</strong><br>
                        <span>{{ $customer->phone ?? 'N/A' }}</span>
                    </div>
                    @if($customer->email)
                    <div class="mb-3">
                        <strong>{{ __('Email') }}:</strong><br>
                        <span>{{ $customer->email }}</span>
                    </div>
                    @endif
                    @if($customer->permanent_address)
                    <div class="mb-3">
                        <strong>{{ __('Address') }}:</strong><br>
                        <span>{{ $customer->permanent_address }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Installment Sale Summary -->
        <div class="col-12 col-lg-6 mb-4">
            <div class="card info-card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Installment') }} {{ __('Sale') }} {{ __('Summary') }}</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>{{ __('Reference') }}:</strong>
                        <span>{{ $installmentSale->reference_no }}</span>
                    </div>
                        <div class="mb-3">
                        <strong>{{ __('Date') }}:</strong>
                        <span>{{ formatDate($installmentSale->date) }}</span>
                    </div>
                        <div class="mb-3">
                        <strong>{{ __('Item') }}:</strong>
                        <span>{{ $installmentSale->item->name ?? 'N/A' }}</span>
                    </div>
                        <div class="mb-3">
                        <strong>{{ __('Total') }}:</strong>
                        <span>{{ formatAmount($installmentSale->total) }}</span>
                    </div>
                        <div class="mb-3">
                        <strong>{{ __('Down') }} {{ __('Payment') }}:</strong>
                        <span>{{ formatAmount($installmentSale->down_payment) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Payment Form -->
        <div class="col-12 col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Record') }} {{ __('Payment') }}</h5>
                </div>
                <div class="card-body">
                    <form id="paymentForm">
                        <input type="hidden" id="detail_id" value="{{ $detail->encrypted_id }}">
                        <input type="hidden" id="remaining_amount" value="{{ $detail->remaining_amount }}">
                        
                        <div class="mb-3">
                            <label class="form-label">{{ __('Payment') }} {{ __('Date') }}</label>
                            <input type="date" class="form-control datePicker" id="paid_date" name="paid_date" value="{{ date('Y-m-d') }}" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">{{ __('Paid') }} {{ __('Amount') }} {!! requiredField() !!}</label>
                            <input type="text" class="form-control number-input" id="payment_amount" name="amount" max="{{ $detail->amount_of_payment }}" value="{{ $detail->amount_of_payment }}" required readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">{{ __('Payment') }} {{ __('Account') }} {!! requiredField() !!}</label>
                            <select class="form-select select2" id="payment_method_id" name="payment_method_id" required>
                                <option value="">{{ __('Select') }}</option>
                                @foreach($payment_methods as $method)
                                    <option value="{{ $method->id }}" data-type="{{ $method->account_type }}">
                                        {{ $method->name }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="account_type" id="account_type">
                        </div>
                        
                        <div id="account_type_fields"></div>
                        
                        
                        <button type="submit" class="btn btn-primary w-100 payment-trigger">
                            <i class="ti tabler-cash"></i> 
                            {{ __('Record') }} {{ __('Payment') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <a href="{{ route('installment-collection.index') }}" class="btn btn-secondary">
                        <i class="ti tabler-arrow-back-up me-1"></i>
                        {{ __('Back') }} {{ __('to') }} {{ __('Collection') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden translation inputs -->
<input type="hidden" id="check_issue_date" value="{{ __('Check_Issue_Date') }}">
<input type="hidden" id="check_no" value="{{ __('Check_No') }}">
<input type="hidden" id="check_expiry_date" value="{{ __('Check_Expiry_Date') }}">
<input type="hidden" id="mobile_no" value="{{ __('Mobile_No') }}">
<input type="hidden" id="transaction_no" value="{{ __('Transaction_No') }}">
<input type="hidden" id="card_holder_name" value="{{ __('Card_Holder_Name') }}">
<input type="hidden" id="card_holding_number" value="{{ __('Card_Holding_Number') }}">
<input type="hidden" id="paypal_email" value="{{ __('PayPal_Email') }}">
<input type="hidden" id="stripe_email" value="{{ __('Stripe_Email') }}">

@endsection
@push('page-js')
@routes
<script src="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{ asset('backend_assets/js/extended-ui-sweetalert2.js')}}"></script>
<script>
$(function() {
    "use strict";
    
    let csrfToken = $('meta[name="csrf-token"]').attr('content');
    let remainingAmount = parseFloat($('#remaining_amount').val());
    
    // Payment method change - show account-specific fields
    $(document).on('change', '#payment_method_id', function() {
        let accountType = $(this).find(':selected').data('type');
        $('#account_type').val(accountType);
        
        let html = '';
        let check_no = $('#check_no').val();
        let check_issue_date = $('#check_issue_date').val();
        let check_expiry_date = $('#check_expiry_date').val();
        let mobile_no = $('#mobile_no').val();
        let transaction_no = $('#transaction_no').val();
        let card_holder_name = $('#card_holder_name').val();
        let card_holding_number = $('#card_holding_number').val();
        let paypal_email = $('#paypal_email').val();
        let stripe_email = $('#stripe_email').val();
        
        if(accountType == 'Cash') {
            // Cash doesn't need extra fields
        } else if(accountType == 'Bank_Account') {
            html = `
                <div class="mb-3">
                    <label class="form-label">${check_no}</label>
                    <input type="text" class="form-control" name="check_no">
                </div>
                <div class="mb-3">
                    <label class="form-label">${check_issue_date}</label>
                    <input type="date" class="form-control" name="check_issue_date">
                </div>
                <div class="mb-3">
                    <label class="form-label">${check_expiry_date}</label>
                    <input type="date" class="form-control" name="check_expiry_date">
                </div>
            `;
        } else if(accountType == 'Mobile_Banking') {
            html = `
                <div class="mb-3">
                    <label class="form-label">${mobile_no}</label>
                    <input type="text" class="form-control" name="mobile_no">
                </div>
                <div class="mb-3">
                    <label class="form-label">${transaction_no}</label>
                    <input type="text" class="form-control" name="transaction_no">
                </div>
            `;
        } else if(accountType == 'Card') {
            html = `
                <div class="mb-3">
                    <label class="form-label">${card_holder_name}</label>
                    <input type="text" class="form-control" name="card_holder_name">
                </div>
                <div class="mb-3">
                    <label class="form-label">${card_holding_number}</label>
                    <input type="text" class="form-control" name="card_holding_number">
                </div>
            `;
        } else if(accountType == 'PayPal') {
            html = `
                <div class="mb-3">
                    <label class="form-label">${paypal_email}</label>
                    <input type="email" class="form-control" name="paypal_email">
                </div>
            `;
        } else if(accountType == 'Stripe') {
            html = `
                <div class="mb-3">
                    <label class="form-label">${stripe_email}</label>
                    <input type="email" class="form-control" name="stripe_email">
                </div>
            `;
        }
        
        $('#account_type_fields').html(html);
    });
    
    // Validate payment amount
    $('#payment_amount').on('input', function() {
        let amount = parseFloat($(this).val());
        if (amount > remainingAmount) {
            $(this).val(remainingAmount);
            showErrorNotification('{{ __('Payment') }} {{ __('amount') }} {{ __('cannot') }} {{ __('exceed') }} {{ __('remaining') }} {{ __('amount') }}');
        }
    });
    
    // Submit payment form
    $('#paymentForm').on('submit', function(e) {
        e.preventDefault();
        
        let detailId = $('#detail_id').val();
        let formData = $(this).serializeArray();
        formData.push({name: '_token', value: csrfToken});
        
        // Add paid_date to form data
        formData.push({name: 'paid_date', value: $('#paid_date').val()});
        
        $.ajax({
            url: route('installment-sale.record-payment', { detail_id: detailId }),
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '{{ __('Success') }}',
                        text: response.message,
                        confirmButtonText: '{{ __('OK') }}'
                    }).then(() => {
                        window.location.href = route('installment-collection.index');
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '{{ __('Error') }}',
                        text: response.message
                    });
                }
            },
            error: function(xhr) {
                let message = '{{ __('Failed') }} {{ __('to') }} {{ __('record') }} {{ __('payment') }}';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                Swal.fire({
                    icon: 'error',
                    title: '{{ __('Error') }}',
                    text: message
                });
            }
        });
    });
});
</script>
@endpush

