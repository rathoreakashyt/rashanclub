@extends('backend.backend_layout')
@section('page-title', __('Installment') . ' ' . __('Collection'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
<style>
    .badge-unpaid { background-color: var(--bs-form-invalid-color);; }
    .badge-paid { background-color: #28a745; }
    .badge-partial { background-color: #ffc107; color: #000; }
    .badge-overdue { background-color: #6f42c1; }
    .payment-trigger {
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
</style>
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Installment') }} {{ __('Collection') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Installment') . ' ' . __('Sale'), 
                    'link' => '#'
                ],
                [
                    'label' => __('Installment') . ' ' . __('Collection'),
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ __('Due') }} {{ __('Installment') }} {{ __('List') }}</h5>
                    <div>
                        <button type="button" class="btn btn-sm btn-primary payment-trigger" id="sendSmsBtn" disabled>
                            <i class="ti tabler-device-mobile-message"></i> {{ __('Send') }} {{ __('SMS') }} {{ __('to') }} {{ __('Selected') }}
                        </button>
                        <button type="button" class="btn btn-sm btn-info payment-trigger" id="sendEmailBtn" disabled>
                            <i class="ti tabler-mail-opened"></i> {{ __('Send') }} {{ __('Email') }} {{ __('to') }} {{ __('Selected') }}
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Due Installments Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered" id="due_installments_table">
                            <thead>
                                <tr>
                                    <th>
                                        <input class="form-check-input" type="checkbox" id="select_all" title="{{ __('Select All') }}">
                                    </th>
                                    <th>{{ __('SN') }}</th>
                                    <th>{{ __('Reference') }} {{ __('No') }}</th>
                                    <th>{{ __('Customer') }} {{ __('Name') }}</th>
                                    <th>{{ __('Installment') }} {{ __('Date') }}</th>
                                    <th>{{ __('Installment') }} {{ __('Amount') }}</th>
                                    <th>{{ __('Paid') }} {{ __('Amount') }}</th>
                                    <th>{{ __('Remaining') }} {{ __('Amount') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody id="due_installments_tbody">
                                <tr>
                                    <td colspan="12" class="text-center">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">{{ __('Loading') }}...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
@push('page-js')
@routes
<script src="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{ asset('backend_assets/js/extended-ui-sweetalert2.js')}}"></script>
<script>
    $(document).ready(function() {
        const baseUrl = window.location.origin;
        let csrfToken = $('meta[name="csrf-token"]').attr('content');
        let selectedInstallments = [];

        // Load due installments on page load
        loadDueInstallments();

        function loadDueInstallments() {
            $.ajax({
                url: route('installment-collection.get-due-installments'),
                type: 'GET',
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        let tbody = '';
                        response.data.forEach(function(item, index) {
                            let statusClass = 'badge-unpaid';
                            let statusText = item.paid_status;
                            
                            if (item.paid_status === 'Paid') {
                                statusClass = 'badge-paid';
                            } else if (item.paid_status === 'Partial') {
                                statusClass = 'badge-partial';
                            }
                            
                            if (item.is_overdue) {
                                statusClass = 'badge-overdue';
                                statusText = '{{ __('Overdue') }}';
                            }

                            tbody += `
                                <tr>
                                    <td>
                                        <div class="form-check mt-4">
                                            <input class="form-check-input installment-checkbox" type="checkbox" value="${item.encrypted_id}" data-detail-id="${item.encrypted_id}">
                                        </div>
                                    </td>
                                    <td>${index + 1}</td>
                                    <td>${item.reference_no} <br> - ${item.item_name}</td>
                                    <td>${item.customer_name} ${item.customer_phone ? '(' + item.customer_phone + ')' : ''}</td>
                                    <td>${item.payment_date}</td>
                                    <td>${item.amount}</td>
                                    <td>${item.paid_amount}</td>
                                    <td>${item.remaining_amount}</td>
                                    <td><span class="badge ${statusClass}">${statusText}</span></td>
                                    <td>
                                        <a href="${route('installment-collection.payment', { detail_id: item.encrypted_id })}" class="btn btn-sm btn-primary payment-trigger">
                                            <i class="ti tabler-cash"></i> {{ __('Payment') }}
                                        </a>
                                    </td>
                                </tr>
                            `;
                        });
                        $('#due_installments_tbody').html(tbody);
                    } else {
                        $('#due_installments_tbody').html('<tr><td colspan="12" class="text-center">{{ __('No') }} {{ __('Due') }} {{ __('Installment') }} {{ __('Found') }}</td></tr>');
                    }
                },
                error: function(xhr) {
                    $('#due_installments_tbody').html('<tr><td colspan="12" class="text-center text-danger">{{ __('Failed') }} {{ __('to') }} {{ __('load') }} {{ __('due') }} {{ __('installments') }}</td></tr>');
                }
            });
        }

        // Select all checkbox
        $('#select_all').on('change', function() {
            const isChecked = $(this).is(':checked');
            $('.installment-checkbox').prop('checked', isChecked);
            updateSelectedInstallments();
        });

        // Individual checkbox change
        $(document).on('change', '.installment-checkbox', function() {
            updateSelectedInstallments();
            // Update select all checkbox state
            const totalCheckboxes = $('.installment-checkbox').length;
            const checkedCheckboxes = $('.installment-checkbox:checked').length;
            $('#select_all').prop('checked', totalCheckboxes === checkedCheckboxes);
        });

        function updateSelectedInstallments() {
            selectedInstallments = [];
            $('.installment-checkbox:checked').each(function() {
                selectedInstallments.push($(this).val());
            });
            
            // Enable/disable buttons based on selection
            if (selectedInstallments.length > 0) {
                $('#sendSmsBtn, #sendEmailBtn').prop('disabled', false);
            } else {
                $('#sendSmsBtn, #sendEmailBtn').prop('disabled', true);
            }
        }

        // Send SMS to selected installments
        $('#sendSmsBtn').on('click', function() {
            if (selectedInstallments.length === 0) {
                showErrorNotification('{{ __('Please') }} {{ __('select') }} {{ __('at') }} {{ __('least') }} {{ __('one') }} {{ __('installment') }}');
                return;
            }

            Swal.fire({
                title: '{{ __('Confirm') }}',
                text: '{{ __('Are') }} {{ __('you') }} {{ __('sure') }} {{ __('you') }} {{ __('want') }} {{ __('to') }} {{ __('send') }} {{ __('SMS') }} {{ __('to') }} {{ __('selected') }} {{ __('installments') }}?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '{{ __('Yes') }}, {{ __('Send') }}',
                cancelButtonText: '{{ __('Cancel') }}'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: route('installment-collection.send-due-notifications'),
                        type: 'POST',
                        data: { 
                            type: 'sms', 
                            detail_ids: selectedInstallments,
                            _token: csrfToken 
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: '{{ __('Success') }}',
                                    text: response.message
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
                            Swal.fire({
                                icon: 'error',
                                title: '{{ __('Error') }}',
                                text: '{{ __('Failed') }} {{ __('to') }} {{ __('send') }} {{ __('SMS') }}'
                            });
                        }
                    });
                }
            });
        });

        // Send Email to selected installments
        $('#sendEmailBtn').on('click', function() {
            if (selectedInstallments.length === 0) {
                showErrorNotification('{{ __('Please') }} {{ __('select') }} {{ __('at') }} {{ __('least') }} {{ __('one') }} {{ __('installment') }}');
                return;
            }

            Swal.fire({
                title: '{{ __('Confirm') }}',
                text: '{{ __('Are') }} {{ __('you') }} {{ __('sure') }} {{ __('you') }} {{ __('want') }} {{ __('to') }} {{ __('send') }} {{ __('Email') }} {{ __('to') }} {{ __('selected') }} {{ __('installments') }}?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '{{ __('Yes') }}, {{ __('Send') }}',
                cancelButtonText: '{{ __('Cancel') }}'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: route('installment-collection.send-due-notifications'),
                        type: 'POST',
                        data: { 
                            type: 'email', 
                            detail_ids: selectedInstallments,
                            _token: csrfToken 
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: '{{ __('Success') }}',
                                    text: response.message
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
                            Swal.fire({
                                icon: 'error',
                                title: '{{ __('Error') }}',
                                text: '{{ __('Failed') }} {{ __('to') }} {{ __('send') }} {{ __('Email') }}'
                            });
                        }
                    });
                }
            });
        });
    });
</script>
@endpush

