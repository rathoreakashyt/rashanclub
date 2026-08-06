$(async function () {
    "use strict";
    /** #################### -- The base JS part should be on top of all JS files --  #################### **/
    // Language Translator
    let base_url = $('#base_url').val();
    let language_name = $('#language_name').val();
    let language_path = '/resources/lang/' + language_name + '.json';
    let language_file = base_url + language_path;
    let language_key = {};
  
    async function loadLanguage() {
      try {
        const res = await fetch(language_file);
        language_key = await res.json();
      } catch (err) {
        console.error('Failed to load language file:', err);
      }
    }
    await loadLanguage();
  
  
    // Company Info
    let company_data = $('#company_data').val();
    let company_session_data = {};
    try {
        company_session_data = JSON.parse(company_data);
    } catch (e) {
        console.error('Error parsing company info:', e);
    }
    /** #################### -- The base JS part should be on top of all JS files --  #################### **/

    // Necessary Date Time Range Picker call
    $('.datePicker').flatpickr({
        altInput: true,
        altFormat: 'Y-m-d',
        dateFormat: 'Y-m-d',
        static: true,
        allowInput: true
    });
    $('.datePickerRange').flatpickr({
        mode: 'range',
        altInput: true,
        altFormat: 'Y-m-d',
        dateFormat: 'Y-m-d',
        static: true,
        allowInput: true
    });
    $('.datePickerTime').flatpickr({
        enableTime: true,
        altInput: true,
        dateFormat: 'Y-m-d H:i',
        static: true,
        allowInput: true,
        time_24hr: true
    });

    // Select2 Initialization (exclude #customer-select - it uses AJAX search in pos_main.js)
    $('.select2').each(function() {
        if ($(this).attr('id') === 'customer-select') return;
        $(this).wrap('<div class="position-relative"></div>').select2({
            dropdownParent: $(this).parent(),
            placeholder: $(this).data('placeholder')
        });
    });

    // Notification Utility
    window.showNotification = function(type, message, options = {}) {
        const defaultOptions = {
            duration: 3000,
            dismissible: true,
            position: { x: 'right', y: 'top' }
        };

        const finalOptions = { ...defaultOptions, ...options, type, message };
        
        // Initialize Notyf if not already initialized
        if (!window.notyf) {
            window.notyf = new Notyf({
                duration: 3000,
                ripple: true,
                dismissible: true,
                position: { x: 'right', y: 'top' },
                types: [
                    {
                        type: 'info',
                        background: config.colors.info,
                        className: 'notyf__info',
                        icon: {
                            className: 'icon-base ti tabler-info-circle-filled icon-md text-white',
                            tagName: 'i'
                        }
                    },
                    {
                        type: 'warning',
                        background: config.colors.warning,
                        className: 'notyf__warning',
                        icon: {
                            className: 'icon-base ti tabler-alert-triangle-filled icon-md text-white',
                            tagName: 'i'
                        }
                    },
                    {
                        type: 'success',
                        background: config.colors.success,
                        className: 'notyf__success',
                        icon: {
                            className: 'icon-base ti tabler-circle-check-filled icon-md text-white',
                            tagName: 'i'
                        }
                    },
                    {
                        type: 'error',
                        background: config.colors.danger,
                        className: 'notyf__error',
                        icon: {
                            className: 'icon-base ti tabler-xbox-x-filled icon-md text-white',
                            tagName: 'i'
                        }
                    }
                ]
            });
        }

        window.notyf.open(finalOptions);
    };

    // Helper functions for different notification types
    window.showSuccessNotification = function(message, options = {}) {
        showNotification('success', message, options);
    };

    window.showErrorNotification = function(message, options = {}) {
        showNotification('error', message, options);
    };

    window.showWarningNotification = function(message, options = {}) {
        showNotification('warning', message, options);
    };

    window.showInfoNotification = function(message, options = {}) {
        showNotification('info', message, options);
    };

    $(document).on('keyup', '.discount-format', function () {
        let value = this.value;
        value = value.replace(/[^0-9.%]/g, '');
        value = value.replace(/(\..*)\./g, '$1');
        value = value.replace(/%(?=.*%)/g, '');
        value = value.replace(/%(?=.)/g, '');
        if (value === '%' || value.startsWith('%')) {
            value = '';
        }
        if (value.startsWith('.')) {
            value = value.substring(1);
        }
        if (value === '') {
            value = 0;
        }
        this.value = value;
    });


    $('.number-input').on('input', function () {
        let value = this.value;
        value = value.replace(/[^0-9.]/g, '');
        const dotIndex = value.indexOf('.');
        if (dotIndex !== -1) {
            value =
                value.substring(0, dotIndex + 1) +
                value.substring(dotIndex + 1).replace(/\./g, '');
        }
        if (value.includes('.')) {
            let [int, dec] = value.split('.');
            value = int + '.' + dec.substring(0, company_session_data.precision);
        }
        this.value = value;
    });

    
    // on focust select all text
    $(document).on('focus', '.focus-select', function () {
        $(this).select();
    });



});