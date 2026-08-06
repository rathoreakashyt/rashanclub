/**
 * Register Management JavaScript
 * Handles register open/close functionality
 */

$(document).ready(function() {
    'use strict';

    /** #################### -- The base JS part should be on top of all JS files --  #################### **/
    // Get base URL
    let base_url = $('#base_url').val();
    if (!base_url) {
        base_url = window.location.origin;
    }
    /** #################### -- The base JS part should be on top of all JS files --  #################### **/

    // Global handler: session expired (419) on any AJAX request -> redirect to login
    $(document).ajaxError(function(event, xhr) {
        if (xhr.status === 419) {
            window.location.href = base_url + '/login?session_expired=1';
            return false;
        }
    });

    let openingBalances = {};

    // Initialize Select2 for counter dropdown if it exists
    if ($('#register_counter_id').length && $('#register_counter_id').hasClass('select2')) {
        $('#register_counter_id').select2({
            dropdownParent: $('#register_counter_id').closest('.card-body, body'),
            width: '100%'
        });
    }

    // Update opening balances on input change
    $(document).on('input', '.payment-method-balance', function() {
        const paymentMethodId = $(this).data('payment-method-id');
        const balance = parseFloat($(this).val()) || 0;
        openingBalances[paymentMethodId] = balance;
    });

    /**
     * Open register
     */
    $('#btn_open_register').on('click', function(e) {
        e.preventDefault();
        
        const btn = $(this);
        
        // Prevent double submission
        if (btn.data('submitting')) {
            return false;
        }
        
        const originalText = btn.html();
        btn.data('submitting', true).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Opening...');

        // Validate counter
        const counterId = $('#register_counter_id').val();
        if (!counterId) {
            showErrorNotification('Please select a counter');
            $('#register_counter_id').addClass('is-invalid');
            btn.data('submitting', false).prop('disabled', false).html(originalText);
            return;
        }

        // Collect opening balances - include ALL payment methods, even with 0 balance
        const balances = {};
        $('.payment-method-balance').each(function() {
            const paymentMethodId = $(this).data('payment-method-id');
            const balance = parseFloat($(this).val()) || 0;
            // Include all payment methods, even if balance is 0
            balances[paymentMethodId] = balance;
        });

        // Prepare form data
        const formData = {
            counter_id: counterId,
            opening_balance: balances,
            opening_details: $('#register_opening_details').val()
        };

        $.ajax({
            url: base_url + '/register/open',
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'X-Requested-With': 'XMLHttpRequest'
            },
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showSuccessNotification(response.message || 'Register opened successfully');
                    
                    // Get intended URL from session storage, cancel button, or default to dashboard
                    let intendedUrl = sessionStorage.getItem('intended_url');
                    if (!intendedUrl) {
                        // Try to get from the cancel button href
                        const cancelBtn = $('a.btn-secondary');
                        if (cancelBtn.length && cancelBtn.attr('href')) {
                            intendedUrl = cancelBtn.attr('href');
                        } else {
                            intendedUrl = base_url + '/dashboard';
                        }
                    }
                    
                    // Redirect to intended URL
                    setTimeout(function() {
                        window.location.href = intendedUrl;
                    }, 1000);
                } else {
                    showErrorNotification(response.message || 'Failed to open register');
                    if (response.errors) {
                        displayFormErrors('#form_register_open', response.errors);
                    }
                }
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    displayFormErrors('#form_register_open', xhr.responseJSON.errors);
                    showErrorNotification('Please check the form for errors');
                } else {
                    showErrorNotification(xhr.responseJSON?.message || 'An error occurred while opening register');
                }
            },
            complete: function() {
                btn.data('submitting', false).prop('disabled', false).html(originalText);
            }
        });
    });

    /**
     * Display form errors
     */
    function displayFormErrors(formSelector, errors) {
        // Clear previous errors
        $(formSelector + ' .is-invalid').removeClass('is-invalid');
        $(formSelector + ' .invalid-feedback').text('');

        // Display new errors
        $.each(errors, function(field, messages) {
            const fieldElement = $(formSelector + ' [name="' + field + '"]');
            if (fieldElement.length) {
                fieldElement.addClass('is-invalid');
                const feedbackElement = fieldElement.siblings('.invalid-feedback');
                if (feedbackElement.length) {
                    feedbackElement.text(Array.isArray(messages) ? messages[0] : messages);
                }
            }
        });
    }

    // Handle AJAX errors that return register_closed - redirect to register page
    $(document).ajaxError(function(event, xhr) {
        if (xhr.status === 403 && xhr.responseJSON?.register_closed) {
            sessionStorage.setItem('intended_url', window.location.href);
            if (xhr.responseJSON?.redirect_url) {
                window.location.href = xhr.responseJSON.redirect_url;
            } else {
                window.location.href = base_url + '/register/create';
            }
        }
    });



    // Handle logout with register check
    $(document).on('click', '.logout-btn', function(e) {
        e.preventDefault();
        
        // Check register status first
        $.ajax({
            url: base_url + '/register/check-status',
            type: 'GET',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'X-Requested-With': 'XMLHttpRequest'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.register_open) {
                    // Register is open, ask to close
                    Swal.fire({
                        title: 'Close Register?',
                        text: 'Your register is currently open. Do you want to close it before logging out?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, Close Register',
                        cancelButtonText: 'No, Logout Anyway',
                        customClass: {
                            confirmButton: 'btn btn-primary me-2 waves-effect waves-light',
                            cancelButton: 'btn btn-label-secondary waves-effect waves-light'
                        },
                        buttonsStyling: false
                    }).then(function(result) {
                        if (result.value) {
                            // User wants to close register
                            closeRegisterAndLogout();
                        } else {
                            // User wants to logout without closing register
                            proceedWithLogout();
                        }
                    });
                } else {
                    // Register is not open, proceed with logout
                    proceedWithLogout();
                }
            },
            error: function(xhr) {
                if (xhr.status === 419) {
                    window.location.href = base_url + '/login?session_expired=1';
                    return;
                }
                console.error('Error checking register status:', xhr);
                proceedWithLogout();
            }
        });
    });

    /**
     * Close register and then logout
     */
    function closeRegisterAndLogout() {
        $.ajax({
            url: base_url + '/register/close',
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'X-Requested-With': 'XMLHttpRequest'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Register Closed',
                        text: response.message || 'Register closed successfully.',
                        customClass: {
                            confirmButton: 'btn btn-success waves-effect waves-light'
                        },
                        buttonsStyling: false,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(function() {
                        proceedWithLogout();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Failed to close register.',
                        customClass: {
                            confirmButton: 'btn btn-danger waves-effect waves-light'
                        },
                        buttonsStyling: false
                    });
                }
            },
            error: function(xhr) {
                if (xhr.status === 419) {
                    window.location.href = base_url + '/login?session_expired=1';
                    return;
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: xhr.responseJSON?.message || 'An error occurred while closing register.',
                    customClass: {
                        confirmButton: 'btn btn-danger waves-effect waves-light'
                    },
                    buttonsStyling: false
                });
            }
        });
    }

    /**
     * Proceed with logout (AJAX so we can handle session expired 419)
     */
    function proceedWithLogout() {
        const token = $('meta[name="csrf-token"]').attr('content');
        $.ajax({
            url: base_url + '/logout',
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest'
            },
            data: { _token: token },
            dataType: 'json'
        }).done(function(data) {
            window.location.href = (data && data.redirect) ? data.redirect : base_url + '/';
        }).fail(function(xhr) {
            if (xhr.status === 419) {
                window.location.href = base_url + '/login?session_expired=1';
            } else {
                window.location.href = base_url + '/';
            }
        });
    }
});
