@extends('backend.backend_layout')
@section('page-title', __('View') . ' ' . __('Installment') . ' ' . __('Sale'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
<style>
    .badge-unpaid { background-color: var(--bs-form-invalid-color);; }
    .badge-paid { background-color: #28a745; }
    .badge-partial { background-color: #ffc107; color: #000; }
    .badge-overdue { background-color: #6f42c1; }
    .installment-card { border-left: 4px solid #696cff; }
    .installment-card.paid { border-left-color: #28a745; }
    .installment-card.overdue { border-left-color: var(--bs-form-invalid-color);; }
</style>
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('View') }} {{ __('Installment') }} {{ __('Sale') }} - {{ $installmentSale->reference_no }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Installment') . ' ' . __('Sale'), 
                    'link' => route('installment-sale.index')
                ],
                [
                    'label' => __('View') . ' ' . __('Installment') . ' ' . __('Sale'),
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
        <!-- Sale Information -->
        <div class="col-12 col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ __('Sale') }} {{ __('Information') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <strong>{{ __('Reference') }}:</strong>
                            <span>{{ $installmentSale->reference_no }}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>{{ __('Date') }}:</strong>
                            <span>{{ formatDate($installmentSale->date) }}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>{{ __('Customer') }}:</strong>
                            <span>{{ $installmentSale->customer->name ?? 'N/A' }}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>{{ __('Phone') }}:</strong>
                            <span>{{ $installmentSale->customer->phone ?? 'N/A' }}</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>{{ __('Product') }}:</strong>
                            <span>{{ $installmentSale->item->name ?? 'N/A' }}</span>
                        </div>
                        @if($installmentSale->expiry_imei_serial)
                        <div class="col-md-6 mb-3">
                            <strong>{{ __('IMEI Serial') }}:</strong>
                            <span>{{ $installmentSale->expiry_imei_serial }}</span>
                        </div>
                        @endif
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <strong>{{ __('Price') }}:</strong>
                            <span>{{ formatAmount($installmentSale->price) }}</span>
                        </div>
                        <div class="col-md-4 mb-3">
                            <strong>{{ __('Discount') }}:</strong>
                            <span>{{ $installmentSale->discount }} ({{ formatAmount($installmentSale->discount_amount) }})</span>
                        </div>
                        <div class="col-md-4 mb-3">
                            <strong>{{ __('Interest') }}:</strong>
                            <span>{{ $installmentSale->percentage_of_interest }}% ({{ formatAmount($installmentSale->interest_amount) }})</span>
                        </div>
                        <div class="col-md-4 mb-3">
                            <strong>{{ __('Shipping Other') }}:</strong>
                            <span>{{ formatAmount($installmentSale->shipping_other) }}</span>
                        </div>
                        <div class="col-md-4 mb-3">
                            <strong>{{ __('Total') }}:</strong>
                            <span class="text-primary fw-bold">{{ formatAmount($installmentSale->total) }}</span>
                        </div>
                        <div class="col-md-4 mb-3">
                            <strong>{{ __('Down Payment') }}:</strong>
                            <span>{{ formatAmount($installmentSale->down_payment) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Installment Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Installment') }} {{ __('Details') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center">{{ __('No') }}</th>
                                    <th>{{ __('Payment Date') }}</th>
                                    <th>{{ __('Paid Date') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Paid Amount') }}</th>
                                    <th>{{ __('Remaining') }}</th>
                                    <th class="text-center">{{ __('Status') }}</th>
                                    <th class="text-center">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $totalAmount = 0;
                                    $totalPaidAmount = 0;
                                @endphp
                                @foreach($installmentDetails as $key => $detail)
                                @php
                                    $totalAmount += $detail->amount_of_payment;
                                    $totalPaidAmount += $detail->paid_amount;
                                @endphp
                                @php
                                    $isOverdue = $detail->paid_status !== 'Paid' && $detail->payment_date < now()->toDateString();
                                @endphp
                                <tr class="{{ $isOverdue ? 'table-danger' : '' }}">
                                    <td class="text-center">{{ $key + 1 }}</td>
                                    <td>{{ formatDate($detail->payment_date) }}</td>
                                    <td>{{ formatDate($detail->paid_date) }}</td>
                                    <td>{{ formatAmount($detail->amount_of_payment) }}</td>
                                    <td>{{ formatAmount($detail->paid_amount) }}</td>
                                    <td>{{ formatAmount($detail->amount_of_payment - $detail->paid_amount) }}</td>
                                    <td class="text-center">
                                        @if($detail->paid_status === 'Paid')
                                            <span class="badge badge-paid">{{ __('Paid') }}</span>
                                        @elseif($detail->paid_status === 'Partial')
                                            <span class="badge badge-partial">{{ __('Partial') }}</span>
                                        @elseif($isOverdue)
                                            <span class="badge badge-overdue">{{ __('Overdue') }}</span>
                                        @else
                                            <span class="badge badge-unpaid">{{ __('Unpaid') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($detail->paid_status !== 'Paid')
                                        <button type="button" class="btn btn-sm btn-primary record-payment-btn" 
                                            data-detail-id="{{ $detail->encrypted_id }}"
                                            data-amount="{{ $detail->amount_of_payment - $detail->paid_amount }}"
                                            data-installment="">
                                            <i class="ti tabler-cash"></i>
                                        </button>
                                        @else
                                        <span class="text-success"><i class="ti tabler-check"></i></span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Payment History -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Payment') }} {{ __('History') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Account') }}</th>
                                    <th>{{ __('Note') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($payments as $payment)
                                <tr>
                                    <td>{{ formatDate($payment->paid_date ?? $payment->payment_date) }}</td>
                                    <td>
                                        <span class="badge bg-success">
                                            {{ __('Installment Payment') }}
                                        </span>
                                    </td>
                                    <td>{{ formatAmount($payment->paid_amount) }}</td>
                                    <td>{{ $payment->paymentMethod->name ?? 'N/A' }}</td>
                                    <td>{{ $payment->paid_status ?? '-' }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center">{{ __('No payments recorded') }}</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Card -->
        <div class="col-12 col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Payment') }} {{ __('Summary') }}</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <span>{{ __('Total') }}:</span>
                        <strong>{{ formatAmount($installmentSale->total) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>{{ __('Down Payment') }}:</span>
                        <strong>{{ formatAmount($installmentSale->down_payment) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>{{ __('Total Paid') }}:</span>
                        <strong class="text-success">{{ formatAmount($totalPaidAmount) }}</strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span>{{ __('Due') }}:</span>
                        <strong class="text-danger">{{ formatAmount($totalAmount - $totalPaidAmount) }}</strong>
                    </div>
                    
                    <div class="progress mt-4" style="height: 20px;">
                        @php
                            $paidPercentage = $totalAmount > 0 ? ($totalPaidAmount / $totalAmount) * 100 : 0;
                        @endphp
                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $paidPercentage }}%">
                            {{ number_format($paidPercentage, 1) }}%
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <a href="{{ route('installment-sale.index') }}" class="btn btn-secondary w-100 mb-2">
                        <i class="ti tabler-arrow-back-up me-1"></i>
                        {{ __('Back') }} {{ __('to') }} {{ __('List') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<div class="modal fade" id="recordPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Record') }} {{ __('Payment') }} - {{ __('Installment') }} #<span id="modal_installment_number"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="recordPaymentForm">
                    <input type="hidden" id="detail_id" name="detail_id">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Amount') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control number-input" id="payment_amount" name="amount" required readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Payment') }} {{ __('Account') }} {!! requiredField() !!}</label>
                        <select class="form-select select2" id="modal_payment_method_id" name="payment_method_id" required>
                            <option value="">{{ __('Select') }}</option>
                            @foreach($payment_methods as $method)
                                <option value="{{ $method->id }}" data-type="{{ $method->account_type }}">
                                    {{ $method->name }}
                                </option>
                            @endforeach
                        </select>
                        <input type="hidden" name="account_type" id="modal_account_type">
                    </div>
                    <div id="modal_account_type_fields"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                <button type="button" class="btn btn-primary" id="submitPaymentBtn">{{ __('Submit') }}</button>
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
<input type="hidden" id="note_label" value="{{ __('Note') }}">

@endsection
@push('page-js')
@routes
<script src="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script>
$(function() {
    "use strict";
    
    let base_url = $('#base_url').val();
    
    // Record payment button click
    $(document).on('click', '.record-payment-btn', function() {
        let detailId = $(this).data('detail-id');
        let amount = $(this).data('amount');
        let installmentNumber = $(this).data('installment');
        
        $('#detail_id').val(detailId);
        $('#payment_amount').val(amount);
        $('#modal_installment_number').text(installmentNumber);
        $('#recordPaymentModal').modal('show');
    });
    
    // Payment method change - show account-specific fields
    $(document).on('change', '#modal_payment_method_id', function() {
        let accountType = $(this).find(':selected').data('type');
        $('#modal_account_type').val(accountType);
        
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
        let note_label = $('#note_label').val();
        
        if(accountType == 'Cash') {
            // Cash doesn't need extra fields
        } else if(accountType == 'Bank_Account') {
            html = `
                <div class="mb-3">
                    <label class="form-label">${check_no}</label>
                    <input type="text" name="check_no" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">${check_issue_date}</label>
                    <input type="date" name="check_issue_date" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">${check_expiry_date}</label>
                    <input type="date" name="check_expiry_date" class="form-control">
                </div>`;
        } else if(accountType == 'Card') {
            html = `
                <div class="mb-3">
                    <label class="form-label">${card_holder_name}</label>
                    <input type="text" name="card_holder_name" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">${card_holding_number}</label>
                    <input type="text" name="card_holding_number" class="form-control">
                </div>`;
        } else if(accountType == 'Mobile_Banking') {
            html = `
                <div class="mb-3">
                    <label class="form-label">${mobile_no}</label>
                    <input type="text" name="mobile_no" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">${transaction_no}</label>
                    <input type="text" name="transaction_no" class="form-control">
                </div>`;
        } else if(accountType == 'Paypal') {
            html = `
                <div class="mb-3">
                    <label class="form-label">${paypal_email}</label>
                    <input type="email" name="paypal_email" class="form-control">
                </div>`;
        } else if(accountType == 'Stripe') {
            html = `
                <div class="mb-3">
                    <label class="form-label">${stripe_email}</label>
                    <input type="email" name="stripe_email" class="form-control">
                </div>`;
        }
        
        $('#modal_account_type_fields').html(html);
    });
    
    // Submit payment
    $(document).on('click', '#submitPaymentBtn', function() {
        let detailId = $('#detail_id').val();
        let formData = $('#recordPaymentForm').serialize();
        
        $.ajax({
            url: base_url + '/installment-sale/record-payment/' + detailId,
            type: 'POST',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if(response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: response.message,
                    }).then(function() {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message,
                    });
                }
            },
            error: function(xhr) {
                let errorMessage = xhr.responseJSON?.message || 'An error occurred';
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errorMessage,
                });
            }
        });
    });
});
</script>
@endpush

