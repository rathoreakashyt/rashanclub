@extends('backend.backend_layout')
@section('page-title', __('Email Marketing'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Email Marketing') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Marketing'),
                    'link' => '#'
                ],
                [
                    'label' => __('Email Marketing'),
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

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-12 col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-md me-3 bg-label-primary d-flex align-items-center justify-content-center rounded-circle">
                            <i class="ti tabler-cake fs-4"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0">{{ __('Customers with Birthday Today') }}</h6>
                            <p class="mb-0 fs-4 fw-semibold" id="birthday_recipients_count">0</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-md me-3 bg-label-success d-flex align-items-center justify-content-center rounded-circle">
                            <i class="ti tabler-heart fs-4"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0">{{ __('Customers with Anniversary Today') }}</h6>
                            <p class="mb-0 fs-4 fw-semibold" id="anniversary_recipients_count">0</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-md me-3 bg-label-info d-flex align-items-center justify-content-center rounded-circle">
                            <i class="ti tabler-mail fs-4"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0">{{ __('Customers with Email') }}</h6>
                            <p class="mb-0 fs-4 fw-semibold" id="eligible_email_count">0</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Campaign Cards -->
    <div class="row">
        <!-- Birthday Email -->
        <div class="col-12 col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Birthday Email') }}</h5>
                </div>
                <div class="card-body">
                    <form id="birthday_email_form">
                        @csrf
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label" for="birthday_subject">{{ __('Email Subject') }} {!! requiredField() !!}</label>
                                <input type="text" class="form-control" 
                                    id="birthday_subject" 
                                    name="subject"
                                    placeholder="{{ __('Enter Email Subject') }}" 
                                    required />
                                <div class="invalid-feedback" id="birthday_subject_error"></div>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label" for="birthday_message">{{ __('Email Message') }} {!! requiredField() !!}</label>
                                <textarea class="form-control" 
                                    id="birthday_message" 
                                    name="message"
                                    rows="6"
                                    placeholder="{{ __('Enter Email Message') }}" 
                                    required></textarea>
                                <div class="invalid-feedback" id="birthday_message_error"></div>
                                
                            </div>
                            <div class="col-12 mb-3">
                                <div class="alert alert-info">
                                    <p class="mb-2">{{ __('This email targets all customers celebrating their birthday today') }}</p>
                                    <p class="mb-0">
                                        <strong>{{ __('Total Recipients:') }}</strong>
                                        <span id="birthday_alert_count">0</span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary" id="submit_birthday_email">
                                    <i class="ti tabler-mail-forward me-2"></i>
                                    {{ __('Send Birthday Email') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Anniversary Email -->
        <div class="col-12 col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Anniversary Email') }}</h5>
                </div>
                <div class="card-body">
                    <form id="anniversary_email_form">
                        @csrf
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label" for="anniversary_subject">{{ __('Email Subject') }} {!! requiredField() !!}</label>
                                <input type="text" class="form-control" 
                                    id="anniversary_subject" 
                                    name="subject"
                                    placeholder="{{ __('Enter Email Subject') }}" 
                                    required />
                                <div class="invalid-feedback" id="anniversary_subject_error"></div>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label" for="anniversary_message">{{ __('Email Message') }} {!! requiredField() !!}</label>
                                <textarea class="form-control" 
                                    id="anniversary_message" 
                                    name="message"
                                    rows="6"
                                    placeholder="{{ __('Enter Email Message') }}" 
                                    required></textarea>
                                <div class="invalid-feedback" id="anniversary_message_error"></div>
                                
                            </div>
                            <div class="col-12 mb-3">
                                <div class="alert alert-info">
                                    <p class="mb-2">{{ __('This email targets customers celebrating their anniversary today') }}</p>
                                    <p class="mb-0">
                                        <strong>{{ __('Total Recipients:') }}</strong>
                                        <span id="anniversary_alert_count">0</span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary" id="submit_anniversary_email">
                                    <i class="ti tabler-mail-forward me-2"></i>
                                    {{ __('Send Anniversary Email') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Custom Email -->
        <div class="col-12 col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Custom Email') }}</h5>
                    <p class="text-muted mb-0">{{ __('Send a targeted email to selected customers or everyone') }}</p>
                </div>
                <div class="card-body">
                    <form id="custom_email_form">
                        @csrf
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label" for="custom_recipients">{{ __('Recipients') }} {!! requiredField() !!}</label>
                                <select class="select2 form-select" 
                                    id="custom_recipients" 
                                    name="selected_customers[]"
                                    multiple
                                    data-placeholder="{{ __('Search and select customers...') }}">
                                </select>
                                <input type="hidden" id="custom_send_to_all" name="send_to_all" value="0">
                                <div class="invalid-feedback" id="custom_recipients_error"></div>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label" for="custom_subject">{{ __('Email Subject') }} {!! requiredField() !!}</label>
                                <input type="text" class="form-control" 
                                    id="custom_subject" 
                                    name="subject"
                                    placeholder="{{ __('Enter Email Subject') }}" 
                                    required />
                                <div class="invalid-feedback" id="custom_subject_error"></div>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label" for="custom_message">{{ __('Email Message') }} {!! requiredField() !!}</label>
                                <textarea class="form-control" 
                                    id="custom_message" 
                                    name="message"
                                    rows="6"
                                    placeholder="{{ __('Enter Email Message') }}" 
                                    required></textarea>
                                <div class="invalid-feedback" id="custom_message_error"></div>
                            </div>
                            <div class="col-12 mb-3">
                                <div class="alert alert-info">
                                    <p class="mb-2" id="custom_alert_message">
                                        {{ __('This email will be sent only to the customers you selected above.') }}
                                    </p>
                                    <p class="mb-0">
                                        <strong>{{ __('Recipients selected:') }}</strong>
                                        <span id="custom_alert_count">0</span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary" id="submit_custom_email">
                                    <i class="ti tabler-mail-forward me-2"></i>
                                    {{ __('Send Custom Email') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
@push('page-js')
<script src="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{ asset('backend_assets/js/extended-ui-sweetalert2.js')}}"></script>
<script>
$(document).ready(function() {
    const base_url = $('#base_url').val() || $('meta[name="base-url"]').attr('content') || window.location.origin;
    const ALL_RECIPIENTS_VALUE = 'all_customers';
    let emailOptions = [];
    let stats = {
        email: {
            birthday: 0,
            anniversary: 0,
            eligible: 0
        }
    };

    // Initialize Select2 for custom recipients (multi-select with search)
    $('#custom_recipients').select2({
        theme: 'bootstrap-5',
        allowClear: true,
        closeOnSelect: false,
        placeholder: '{{ __("Search and select customers...") }}',
        minimumResultsForSearch: 0
    });

    // Fetch stats
    function fetchStats() {
        $.ajax({
            type: "GET",
            url: base_url + "/marketing/email/stats",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    stats = response.data;
                    updateStatsDisplay();
                }
            },
            error: function (xhr) {
                console.error('Failed to fetch stats');
            }
        });
    }

    // Update stats display
    function updateStatsDisplay() {
        $('#birthday_recipients_count').text(stats.email.birthday || 0);
        $('#anniversary_recipients_count').text(stats.email.anniversary || 0);
        $('#eligible_email_count').text(stats.email.eligible || 0);
        $('#birthday_alert_count').text(stats.email.birthday || 0);
        $('#anniversary_alert_count').text(stats.email.anniversary || 0);
    }

    // Fetch recipients
    function fetchRecipients() {
        $.ajax({
            type: "GET",
            url: base_url + "/marketing/email/recipients",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    emailOptions = response.data;
                    populateRecipientsSelect();
                }
            },
            error: function (xhr) {
                console.error('Failed to fetch recipients');
            }
        });
    }

    // Populate recipients select
    function populateRecipientsSelect() {
        const options = [{
            id: ALL_RECIPIENTS_VALUE,
            text: '{{ __('All Customers') }}'
        }];
        
        emailOptions.forEach(function(option) {
            options.push({
                id: option.value,
                text: option.title
            });
        });

        $('#custom_recipients').empty();
        options.forEach(function(option) {
            const newOption = new Option(option.text, option.id, false, false);
            $('#custom_recipients').append(newOption);
        });
        $('#custom_recipients').trigger('change');
    }

    // Handle custom recipients change (matching Vue logic)
    let customSelection = [];
    
    // Computed-like function for recipient count (matching Vue customRecipientCount)
    function getCustomRecipientCount() {
        const sendToAll = $('#custom_send_to_all').val() === '1';
        if (sendToAll) {
            return emailOptions.length;
        }
        return customSelection.length;
    }
    
    function handleCustomRecipientsChange(selected) {
        selected = selected || [];
        
        if (selected.includes(ALL_RECIPIENTS_VALUE)) {
            customSelection = [ALL_RECIPIENTS_VALUE];
            $('#custom_send_to_all').val('1');
            $('#custom_recipients').val([ALL_RECIPIENTS_VALUE]).trigger('change');
            $('#custom_alert_message').text('{{ __('This email will be sent to every customer with a valid email address.') }}');
            $('#custom_alert_count').text(getCustomRecipientCount());
        } else {
            customSelection = selected;
            $('#custom_send_to_all').val('0');
            $('#custom_alert_message').text('{{ __('This email will be sent only to the customers you selected above.') }}');
            $('#custom_alert_count').text(getCustomRecipientCount());
        }
    }

    $('#custom_recipients').on('change', function() {
        const selected = $(this).val() || [];
        handleCustomRecipientsChange(selected);
    });

    // Load recommended message into form (birthday)
    function loadRecommendedBirthday() {
        $.ajax({
            type: "GET",
            url: base_url + "/marketing/email/recommended-message/birthday",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    $('#birthday_subject').val(response.data.subject || '');
                    $('#birthday_message').val(response.data.message || '');
                }
            }
        });
    }

    // Load recommended message into form (anniversary)
    function loadRecommendedAnniversary() {
        $.ajax({
            type: "GET",
            url: base_url + "/marketing/email/recommended-message/anniversary",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    $('#anniversary_subject').val(response.data.subject || '');
                    $('#anniversary_message').val(response.data.message || '');
                }
            }
        });
    }

    // Submit birthday email
    $('#birthday_email_form').on('submit', function(e) {
        e.preventDefault();
        const btn = $('#submit_birthday_email');
        const originalText = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>{{ __('Sending...') }}</span>');

        $.ajax({
            type: "POST",
            url: base_url + "/marketing/email/send/birthday",
            data: $(this).serialize(),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    showSuccessNotification(response.message || '{{ __('Birthday email sent successfully') }}');
                    $('#birthday_email_form')[0].reset();
                    fetchStats();
                } else {
                    showErrorNotification(response.message || '{{ __('Failed to send birthday email') }}');
                    if (response.errors) {
                        displayFormErrors('#birthday_email_form', response.errors);
                    }
                }
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    displayFormErrors('#birthday_email_form', xhr.responseJSON.errors);
                    showErrorNotification('{{ __('Please check the form for errors') }}');
                } else {
                    showErrorNotification(xhr.responseJSON?.message || '{{ __('An unexpected error occurred') }}');
                }
            },
            complete: function() {
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Submit anniversary email
    $('#anniversary_email_form').on('submit', function(e) {
        e.preventDefault();
        const btn = $('#submit_anniversary_email');
        const originalText = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>{{ __('Sending...') }}</span>');

        $.ajax({
            type: "POST",
            url: base_url + "/marketing/email/send/anniversary",
            data: $(this).serialize(),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    showSuccessNotification(response.message || '{{ __('Anniversary email sent successfully') }}');
                    $('#anniversary_email_form')[0].reset();
                    fetchStats();
                } else {
                    showErrorNotification(response.message || '{{ __('Failed to send anniversary email') }}');
                    if (response.errors) {
                        displayFormErrors('#anniversary_email_form', response.errors);
                    }
                }
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    displayFormErrors('#anniversary_email_form', xhr.responseJSON.errors);
                    showErrorNotification('{{ __('Please check the form for errors') }}');
                } else {
                    showErrorNotification(xhr.responseJSON?.message || '{{ __('An unexpected error occurred') }}');
                }
            },
            complete: function() {
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Submit custom email (matching Vue handleSubmit logic)
    $('#custom_email_form').on('submit', function(e) {
        e.preventDefault();
        const btn = $('#submit_custom_email');
        const originalText = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>{{ __('Sending...') }}</span>');

        const formData = $(this).serializeArray();
        const selectedCustomers = $('#custom_recipients').val() || [];
        
        // Remove the select2 field and add proper data
        formData.push({name: 'selected_customers', value: JSON.stringify(selectedCustomers)});

        $.ajax({
            type: "POST",
            url: base_url + "/marketing/email/send/custom",
            data: $.param(formData),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    showSuccessNotification(response.message || '{{ __('Custom email sent successfully') }}');
                    $('#custom_email_form')[0].reset();
                    // Clear custom selection (matching Vue logic)
                    customSelection = [];
                    $('#custom_recipients').val(null).trigger('change');
                    fetchStats();
                } else {
                    showErrorNotification(response.message || '{{ __('Failed to send custom email') }}');
                    if (response.errors) {
                        displayFormErrors('#custom_email_form', response.errors);
                    }
                }
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    displayFormErrors('#custom_email_form', xhr.responseJSON.errors);
                    showErrorNotification('{{ __('Please check the form for errors') }}');
                } else {
                    showErrorNotification(xhr.responseJSON?.message || '{{ __('An unexpected error occurred') }}');
                }
            },
            complete: function() {
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Display form errors
    function displayFormErrors(formSelector, errors) {
        $(formSelector + ' .is-invalid').removeClass('is-invalid');
        $(formSelector + ' .invalid-feedback').text('');
        
        $.each(errors, function(field, messages) {
            const fieldElement = $(formSelector + ' [name="' + field + '"]');
            fieldElement.addClass('is-invalid');
            const errorElement = fieldElement.siblings('.invalid-feedback').first();
            if (errorElement.length) {
                errorElement.text(Array.isArray(messages) ? messages[0] : messages);
            } else {
                $('#' + field + '_error').text(Array.isArray(messages) ? messages[0] : messages);
            }
        });
    }

    // Initialize on page load: fetch stats, recipients, and load recommended messages
    fetchStats();
    fetchRecipients();
    loadRecommendedBirthday();
    loadRecommendedAnniversary();
});
</script>
@endpush
