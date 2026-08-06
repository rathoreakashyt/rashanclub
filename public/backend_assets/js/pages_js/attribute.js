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

    $(document).on('click', '.add_attribute', function (e) {
        e.preventDefault();
        let html_content = `
        <div class="col-12 mb-2 wrapper_row">
            <label class="form-label">${ language_key.Attribute_Value }</label>
            <div class="input-group">
                <input type="text" class="form-control variation_value" 
                    placeholder="${ language_key.Attribute_Value }" name="variation_value[]" />
                <button class="btn btn-outline-danger delete-attribute" type="button">
                    <i class="ti tabler-trash"></i>
                </button>
            </div>
        </div>`;
        $('.append_attribute').append(html_content);
    });

    $(document).on('click', '.delete-attribute', function (e) {
        e.preventDefault();
        $(this).closest('.wrapper_row').remove();
    });

    // Function to check for duplicate values
    function hasDuplicateValues() {
        let values = [];
        let hasDuplicates = false;
        $('.variation_value').each(function() {
            let value = $(this).val().trim();
            if (value !== '') {
                if (values.includes(value)) {
                    hasDuplicates = true;
                    $(this).css('border', '2px solid #ff4c51');
                } else {
                    values.push(value);
                    $(this).css('border', '1px solid #cfced2');
                }
            }
        });
        return hasDuplicates;
    }

    // Function to clear validation errors
    function clearValidationErrors() {
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').remove();
    }

    // Function to show validation errors from backend
    function showValidationErrors(errors) {
        clearValidationErrors();
        
        $.each(errors, function(field, messages) {
            let input = null;
            let formGroup = null;
            
            // Handle variation_name field
            if (field === 'variation_name') {
                input = $('#variation_name');
                if (input.length > 0) {
                    formGroup = input.closest('.mb-5, .mb-3');
                    input.addClass('is-invalid');
                    if (formGroup.length > 0) {
                        formGroup.find('.invalid-feedback').remove();
                        formGroup.append(`<div class="invalid-feedback">${messages[0]}</div>`);
                    } else {
                        input.after(`<div class="invalid-feedback">${messages[0]}</div>`);
                    }
                }
            }
            // Handle variation_value array fields (variation_value.0, variation_value.1, etc.)
            else if (field.startsWith('variation_value.')) {
                let index = field.split('.')[1];
                input = $(`.variation_value[name="variation_value[]"]`).eq(parseInt(index));
                if (input.length > 0) {
                    formGroup = input.closest('.wrapper_row');
                    input.addClass('is-invalid');
                    if (formGroup.length > 0) {
                        formGroup.find('.invalid-feedback').remove();
                        formGroup.append(`<div class="invalid-feedback">${messages[0]}</div>`);
                    } else {
                        input.after(`<div class="invalid-feedback">${messages[0]}</div>`);
                    }
                }
            }
            // Handle general variation_value error (for array-level errors like "distinct")
            else if (field === 'variation_value') {
                // Show error notification for array-level errors
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification(messages[0]);
                } else {
                    alert(messages[0]);
                }
            }
        });
    }

    // Show backend validation errors on page load if they exist
    $(document).ready(function() {
        // Check if validation errors exist (passed from blade template)
        if (typeof window.validationErrors !== 'undefined' && window.validationErrors) {
            showValidationErrors(window.validationErrors);
        }
    });

    // Frontend validation (still do basic checks, but allow form to submit for backend validation)
    $(document).on('click', '.attribute_submit', function (e) {
        let error = false;
        let variation_name = $('#variation_name').val().trim();
        
        // Clear previous errors
        clearValidationErrors();
        
        // Basic frontend validation
        if(variation_name == ''){
            $('#variation_name').addClass('is-invalid');
            $('#variation_name').closest('.mb-5').append('<div class="invalid-feedback">' + (language_key.Variation_Name || 'Variation Name') + ' ' + (language_key.is_required || 'is required') + '</div>');
            error = true;
        }

        // Check for empty values
        $('.variation_value').each(function (index) {
            if ($(this).val().trim() === '') {
                $(this).addClass('is-invalid');
                let wrapper = $(this).closest('.wrapper_row');
                wrapper.find('.invalid-feedback').remove();
                wrapper.append('<div class="invalid-feedback">' + (language_key.Each || 'Each') + ' ' + (language_key.Attribute_Value || 'Attribute Value') + ' ' + (language_key.is_required || 'is required') + '</div>');
                error = true;
            }
        });

        // Check for duplicate values
        if (hasDuplicateValues()) {
            error = true;
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification((language_key.Attribute_Value || 'Variation Value') + ' ' + (language_key.must_be_unique || 'must be unique'));
            } else {
                alert((language_key.Attribute_Value || 'Variation Value') + ' ' + (language_key.must_be_unique || 'must be unique'));
            }
        }

        // If frontend validation fails, prevent submission
        if (error) {
            e.preventDefault();
            return false;
        }
        
        // If frontend validation passes, allow form to submit
        // Backend will validate and show errors if any
    });

    // Clear validation errors when user starts typing
    $(document).on('input', '#variation_name', function() {
        $(this).removeClass('is-invalid');
        $(this).closest('.mb-5').find('.invalid-feedback').remove();
    });

    $(document).on('input', '.variation_value', function() {
        $(this).removeClass('is-invalid');
        $(this).closest('.wrapper_row').find('.invalid-feedback').remove();
        // Reset border color
        $(this).css('border', '1px solid #cfced2');
    });
});