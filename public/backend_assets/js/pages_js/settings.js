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

    // Function to clear validation errors
    function clearValidationErrors() {
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').remove();
    }

    // Function to show validation errors
    function showValidationErrors(wrapperSelector, errors) {
        clearValidationErrors();
        $.each(errors, function(field, messages) {
            // Handle array fields (fields with [] in the name)
            if (field.includes('.')) {
                let [baseName, index] = field.split('.');
                let input = $(`${wrapperSelector} [name="${baseName}[]"]`).eq(index);
                let formGroup = input.closest('.validate_wrapper');
                input.addClass('is-invalid');
                formGroup.append(`<div class="invalid-feedback">${messages[0]}</div>`);
            } else {
                // Handle regular fields
                let input = $(`${wrapperSelector} [name="${field}"]`);
                let formGroup = input.closest('.validate_wrapper');
                input.addClass('is-invalid');
                formGroup.append(`<div class="invalid-feedback">${messages[0]}</div>`);
            }
        });
        $('.invalid-feedback').show();
    }


    // Business Setting
    $(document).on('click', '.business_setting_submit', function (e) {
        e.preventDefault();
        let formData = $('#business_setting_form').serialize();
        // Clear previous errors
        clearValidationErrors();
        $.ajax({
            type: "POST",
            url: base_url + "/business-setting",
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    showSuccessNotification(response.message || 'Business setting save successfully');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showErrorNotification(response.message || 'Something went wrong');
                }
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    // Handle validation errors
                    showValidationErrors('#business_setting_form', xhr.responseJSON.errors);
                    showErrorNotification('Please check the form for errors');
                } else {
                    showErrorNotification('An unexpected error occurred');
                }
            }
        });
    });

    // Pos Setting
    $(document).on('click', '.pos_setting_submit', function (e) {
        e.preventDefault();
        let formData = $('#pos_setting_form').serialize();
        // Clear previous errors
        clearValidationErrors();
        $.ajax({
            type: "POST",
            url: base_url + "/pos-setting",
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    showSuccessNotification(response.message || 'Pos setting save successfully');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showErrorNotification(response.message || 'Something went wrong');
                }
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    // Handle validation errors
                    showValidationErrors('#pos_setting_form', xhr.responseJSON.errors);
                    showErrorNotification('Please check the form for errors');
                } else {
                    showErrorNotification('An unexpected error occurred');
                }
            }
        });
    });


    // Tax Setting - Add/Edit Tax Modal
    function getAvailableSubTaxes() {
        if (typeof window.AVAILABLE_SUB_TAXES !== 'undefined' && Array.isArray(window.AVAILABLE_SUB_TAXES)) {
            return window.AVAILABLE_SUB_TAXES;
        }
        return [];
    }
    function toggleSubTaxField(isEdit, editSubTaxIds, excludeTaxId) {
        const taxName = ($('#tax_name').val() || '').trim().toUpperCase();
        let subTaxes = getAvailableSubTaxes();
        if (taxName === 'GST') {
            $('#tax_name_hint').removeClass('d-none');
            $('#tax_rate_wrapper').hide();
            $('#tax_rate').val('').prop('required', false);
            $('#sub_tax_wrapper').show();
            const $select = $('#sub_tax_ids');
            $select.empty();
            if (excludeTaxId) {
                subTaxes = subTaxes.filter(function(st) { return st.id != excludeTaxId; });
            }
            if (subTaxes.length === 0) {
                $select.append('<option value="" disabled>' + (language_key.Create_taxes_first || 'Create taxes first, then assign to GST') + '</option>');
                var $msg = $('#gst_sub_tax_message');
                $msg.addClass('alert-info').removeClass('alert-danger').find('i').addClass('tabler-info-circle').removeClass('tabler-alert-circle');
                $msg.find('span').text($msg.data('create-first') || (language_key.Create_taxes_first || 'Create IGST, CGST, SGST first, then assign to GST.'));
                $msg.show();
            } else {
                subTaxes.forEach(function(st) {
                    $select.append('<option value="' + st.id + '">' + (st.name || '') + ' (' + parseFloat(st.rate || 0).toFixed(2) + '%)</option>');
                });
                var $msg = $('#gst_sub_tax_message');
                $msg.addClass('alert-info').removeClass('alert-danger').find('i').addClass('tabler-info-circle').removeClass('tabler-alert-circle');
                $msg.find('span').text($msg.data('guide') || 'GST requires exactly 3 sub taxes: IGST, CGST and SGST. Select all sub taxes.');
                $msg.show();
                if (isEdit && editSubTaxIds && Array.isArray(editSubTaxIds) && editSubTaxIds.length) {
                    $select.val(editSubTaxIds.map(String));
                }
            }
        } else {
            $('#tax_name_hint').addClass('d-none');
            $('#tax_rate_wrapper').show();
            $('#tax_rate').prop('required', true);
            $('#sub_tax_wrapper').hide();
        }
    }
    $(document).on('input keyup change', '#tax_name', function() {
        if (!$('#tax_form_id').val()) toggleSubTaxField(false);
    });
    let taxEditData = null;
    $('#modal_add_tax').on('show.bs.modal', function() {
        $('#tax_form_id').val('');
        $('#tax_form')[0].reset();
        $('#modal_add_tax_title').text(language_key.Add_Tax || 'Add Tax');
        $('#tax_form_submit_btn').html('<i class="ti tabler-check me-1"></i>' + (language_key.Save_Tax || 'Save Tax'));
        toggleSubTaxField(false);
        $('#tax_form .is-invalid').removeClass('is-invalid');
        $('#tax_form .invalid-feedback').remove();
    });
    $('#modal_add_tax').on('shown.bs.modal', function() {
        if (taxEditData) {
            $('#tax_form_id').val(taxEditData.taxId);
            $('#tax_name').val(taxEditData.taxName);
            $('#tax_rate').val(taxEditData.taxRate);
            $('input[name="show_in_item_profile"][value="' + taxEditData.showInItemProfile + '"]').prop('checked', true);
            $('#modal_add_tax_title').text(language_key.Edit_Tax || 'Edit Tax');
            $('#tax_form_submit_btn').html('<i class="ti tabler-check me-1"></i>' + (language_key.Update_Tax || 'Update Tax'));
            toggleSubTaxField(true, taxEditData.subTaxIds || [], taxEditData.taxId);
            taxEditData = null;
        } else {
            $('#tax_name').trigger('input');
        }
    });
    $(document).on('click', '.edit-tax-btn', function() {
        const $btn = $(this);
        let subTaxIds = $btn.data('subTaxIds') || $btn.attr('data-sub-tax-ids');
        if (typeof subTaxIds === 'string' && subTaxIds) {
            try { subTaxIds = JSON.parse(subTaxIds); } catch (e) { subTaxIds = []; }
        }
        taxEditData = {
            taxId: $btn.data('tax-id') || $btn.attr('data-tax-id'),
            taxName: $btn.data('tax-name') || $btn.attr('data-tax-name'),
            taxRate: $btn.data('tax-rate') ?? $btn.attr('data-tax-rate') ?? '',
            subTaxIds: Array.isArray(subTaxIds) ? subTaxIds : [],
            showInItemProfile: $btn.data('show-in-item-profile') || $btn.attr('data-show-in-item-profile') || 'No'
        };
        $('#modal_add_tax').modal('show');
    });
    function renderTaxGroups(taxGroups) {
        const tbody = $('#tax_table tbody');
        tbody.empty();
        if (!taxGroups || taxGroups.length === 0) {
            tbody.append('<tr id="tax_table_empty_row"><td colspan="4" class="text-center text-muted py-4">' + (language_key.No_tax_added_yet || 'No tax added yet. Click Add Tax to add.') + '</td></tr>');
        } else {
            taxGroups.forEach(function(group) {
                tbody.append(buildTaxGroupRow(group));
            });
        }
    }
    function buildTaxGroupRow(group) {
        const editTitle = language_key.Edit || 'Edit';
        const deleteTitle = language_key.Delete || 'Delete';
        const taxRateStr = group.tax_rate !== null && group.tax_rate !== undefined ? parseFloat(group.tax_rate).toFixed(2) + '%' : '-';
        let subTaxHtml = '-';
        if (group.sub_taxes && group.sub_taxes.length) {
            subTaxHtml = group.sub_taxes.map(function(st) {
                return '<span class="badge bg-label-primary me-1">' + (st.name || '') + ':' + parseFloat(st.rate || 0).toFixed(2) + '%</span>';
            }).join('');
        }
        const subTaxIdsJson = group.sub_taxes && group.sub_taxes.length
            ? JSON.stringify((group.sub_taxes || []).map(function(st) { return st.id; }))
            : '[]';
        return '<tr data-tax-ids="' + (group.tax_ids || []).join(',') + '" data-tax-id="' + (group.tax_id || '') + '">' +
            '<td>' + (group.tax_name || '') + '</td>' +
            '<td>' + taxRateStr + '</td>' +
            '<td>' + subTaxHtml + '</td>' +
            '<td class="text-center">' +
            '<div class="d-inline-flex datatable-action gap-1">' +
            '<button type="button" class="btn btn-sm btn-icon edit-record edit-tax-btn" data-tax-id="' + (group.tax_id || '') + '" data-tax-name="' + (group.tax_name || '') + '" data-tax-rate="' + (group.tax_rate ?? '') + '" data-show-in-item-profile="' + (group.show_in_item_profile || 'No') + '" data-tax-ids="' + (group.tax_ids || []).join(',') + '" data-sub-tax-ids=\'' + subTaxIdsJson + '\' title="' + editTitle + '"><i class="ti tabler-edit"></i></button>' +
            '<button type="button" class="btn btn-sm btn-icon delete-record delete-tax-btn text-danger" data-tax-ids="' + (group.tax_ids || []).join(',') + '" title="' + deleteTitle + '"><i class="ti tabler-trash"></i></button>' +
            '</div></td></tr>';
    }
    $(document).on('submit', '#tax_form', function(e) {
        e.preventDefault();
        const taxId = $('#tax_form_id').val();
        const taxName = ($('#tax_name').val() || '').trim().toUpperCase();
        const subTaxIds = $('#sub_tax_ids').val() || [];
        const gstSubTaxMsg = language_key.GST_requires_exactly_3_sub_taxes || 'GST requires exactly 3 sub taxes: IGST, CGST and SGST. Neither more nor less.';
        if (taxName === 'GST') {
            if (!Array.isArray(subTaxIds) || subTaxIds.length !== 3) {
                $('#tax_form .is-invalid').removeClass('is-invalid');
                $('#tax_form .invalid-feedback').remove();
                $('#sub_tax_wrapper').addClass('is-invalid').find('.invalid-feedback').remove().end()
                    .append('<div class="invalid-feedback d-block">' + gstSubTaxMsg + '</div>');
                showErrorNotification(gstSubTaxMsg);
                return;
            }
            const subTaxes = getAvailableSubTaxes();
            const selectedNames = subTaxes.filter(function(st) { return subTaxIds.indexOf(String(st.id)) !== -1; }).map(function(st) { return (st.name || '').toUpperCase(); });
            const required = ['IGST', 'CGST', 'SGST'];
            const hasAll = required.every(function(r) { return selectedNames.indexOf(r) !== -1; });
            if (!hasAll) {
                $('#tax_form .is-invalid').removeClass('is-invalid');
                $('#tax_form .invalid-feedback').remove();
                $('#sub_tax_wrapper').addClass('is-invalid').find('.invalid-feedback').remove().end()
                    .append('<div class="invalid-feedback d-block">' + gstSubTaxMsg + '</div>');
                showErrorNotification(gstSubTaxMsg);
                return;
            }
        }
        let formData = $(this).serialize();
        const url = taxId ? base_url + '/tax/' + taxId : base_url + '/tax';
        if (taxId) formData = formData + '&_method=PUT';
        $('#tax_form .is-invalid').removeClass('is-invalid');
        $('#tax_form .invalid-feedback').remove();
        $.ajax({
            type: 'POST',
            url: url,
            data: formData,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            dataType: "json",
            success: function(response) {
                if (response.status === 'success') {
                    if (response.available_sub_taxes && Array.isArray(response.available_sub_taxes)) {
                        window.AVAILABLE_SUB_TAXES = response.available_sub_taxes;
                    }
                    showSuccessNotification(response.message || 'Tax saved successfully');
                    $('#modal_add_tax').modal('hide');
                    if (response.tax_groups && response.tax_groups.length >= 0) {
                        renderTaxGroups(response.tax_groups);
                    } else if (response.taxs && response.taxs.length) {
                        $('#tax_table_empty_row').remove();
                        response.taxs.forEach(function(tax) {
                            $('#tax_table tbody').append(buildTaxGroupRow({ tax_ids: [tax.id], tax_id: tax.id, tax_name: tax.tax_name, tax_rate: tax.tax_rate, sub_taxes: tax.sub_tax ? [{ id: tax.sub_tax_id, name: tax.sub_tax.name, rate: tax.tax_rate }] : [], show_in_item_profile: tax.show_in_item_profile }));
                        });
                    } else if (response.tax) {
                        const tax = response.tax;
                        const group = { tax_ids: [tax.id], tax_id: tax.id, tax_name: tax.tax_name, tax_rate: tax.tax_rate, sub_taxes: tax.sub_tax ? [{ id: tax.sub_tax_id, name: tax.sub_tax.name, rate: tax.tax_rate }] : [], show_in_item_profile: tax.show_in_item_profile };
                        if (taxId) {
                            $('tr[data-tax-id="' + taxId + '"], tr[data-tax-ids*="' + taxId + '"]').first().replaceWith(buildTaxGroupRow(group));
                        } else {
                            $('#tax_table_empty_row').remove();
                            $('#tax_table tbody').append(buildTaxGroupRow(group));
                        }
                    }
                } else {
                    showErrorNotification(response.message || 'Something went wrong');
                }
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    $.each(xhr.responseJSON.errors || {}, function(field, messages) {
                        const nameAttr = field.includes('.') ? field.replace('.', '[') + ']' : field;
                        let input = $('#tax_form [name="' + field + '"]');
                        if (!input.length) input = $('#tax_form [name="' + nameAttr + '"]');
                        if (!input.length && (field.startsWith('sub_tax_ids') || field.startsWith('sub_tax_rate'))) {
                            $('#sub_tax_wrapper').addClass('is-invalid').find('.invalid-feedback').remove().end()
                                .append('<div class="invalid-feedback d-block">' + (messages[0] || '') + '</div>');
                        } else if (input.length) {
                            input.addClass('is-invalid');
                            input.closest('.validate_wrapper').find('.invalid-feedback').remove();
                            input.closest('.validate_wrapper').append('<div class="invalid-feedback">' + (messages[0] || '') + '</div>');
                        }
                    });
                    showErrorNotification('Please check the form for errors');
                } else {
                    showErrorNotification(xhr.responseJSON?.message || 'An unexpected error occurred');
                }
            }
        });
    });
    $(document).on('click', '.delete-tax-btn', function() {
        const taxIds = $(this).data('tax-ids');
        const ids = taxIds ? String(taxIds).split(',').map(function(s) { return s.trim(); }).filter(Boolean) : [];
        const taxId = ids.length ? ids[0] : $(this).data('tax-id');
        const idsToDelete = ids.length ? ids : (taxId ? [taxId] : []);
        if (!idsToDelete.length) return;
        const deleteTitle = language_key.Are_You_Sure || language_key.Are_you_sure_delete_tax || 'Are you sure?';
        const deleteText = language_key.you_want_to_delete || language_key.Are_you_sure_delete_tax || 'You want to delete this tax?';
        const yesText = language_key.yes_delete_it || 'Yes, delete it!';
        const cancelText = language_key.no_cancel || 'Cancel';
        function doDelete() {
            function deleteNext(index, lastResponse) {
            if (index >= idsToDelete.length) {
                if (lastResponse && lastResponse.available_sub_taxes && Array.isArray(lastResponse.available_sub_taxes)) {
                    window.AVAILABLE_SUB_TAXES = lastResponse.available_sub_taxes;
                }
                if (lastResponse && lastResponse.tax_groups) {
                    renderTaxGroups(lastResponse.tax_groups);
                } else {
                    $('tr[data-tax-ids*="' + idsToDelete[0] + '"], tr[data-tax-id="' + idsToDelete[0] + '"]').first().remove();
                    if ($('#tax_table tbody tr').length === 0) {
                        $('#tax_table tbody').append('<tr id="tax_table_empty_row"><td colspan="4" class="text-center text-muted py-4">' + (language_key.No_tax_added_yet || 'No tax added yet. Click Add Tax to add.') + '</td></tr>');
                    }
                }
                showSuccessNotification(language_key.Tax_deleted_successfully || 'Tax deleted successfully');
                return;
            }
            $.ajax({
                type: "DELETE",
                url: base_url + '/tax/' + idsToDelete[index],
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                dataType: "json",
                success: function(response) {
                    if (response.status === 'success') {
                        deleteNext(index + 1, response);
                    } else {
                        showErrorNotification(response.message || 'Something went wrong');
                    }
                },
                error: function(xhr) {
                    showErrorNotification(xhr.responseJSON?.message || 'Failed to delete tax');
                }
            });
        }
        deleteNext(0);
        }
        if (typeof Swal === 'undefined') {
            if (confirm(deleteTitle + ' ' + deleteText)) doDelete();
        } else {
            Swal.fire({
                title: deleteTitle,
                text: deleteText,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: yesText,
                cancelButtonText: cancelText,
                confirmButtonClass: 'btn btn-success',
                cancelButtonClass: 'btn btn-danger',
                buttonsStyling: false,
                reverseButtons: true
            }).then(function(result) {
                if (result.isConfirmed || result.value) doDelete();
            });
        }
    });

    // Invoice Setting
    $(document).on('click', '.invoice_setting_submit', function (e) {
        e.preventDefault();
        // Sync Quill editor content back to hidden textareas before form submit
        if (window.invoiceQuillEditors) {
            if (window.invoiceQuillEditors.term_conditions) {
                $('#term_conditions').val(window.invoiceQuillEditors.term_conditions.root.innerHTML);
            }
            if (window.invoiceQuillEditors.invoice_footer) {
                $('#invoice_footer').val(window.invoiceQuillEditors.invoice_footer.root.innerHTML);
            }
        }
        let formData = new FormData($('#invoice_setting_form')[0]);
        // Clear previous errors
        clearValidationErrors();
        $.ajax({
            type: "POST",
            url: base_url + "/invoice-setting",
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    showSuccessNotification(response.message || 'Invoice setting save successfully');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showErrorNotification(response.message || 'Something went wrong');
                }
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    // Handle validation errors
                    showValidationErrors('#invoice_setting_form', xhr.responseJSON.errors);
                    showErrorNotification('Please check the form for errors');
                } else {
                    showErrorNotification('An unexpected error occurred');
                }
            }
        });
    });

    // Initialize Vuexy Quill editors for Invoice Terms & Conditions and Invoice Footer
    function initInvoiceQuillEditors() {
        if (typeof Quill === 'undefined' || $('#term_conditions_editor').length === 0) return;
        if (window.invoiceQuillEditors && window.invoiceQuillEditors.term_conditions) return;
        window.invoiceQuillEditors = {};
        var termConditionsEditor = new Quill('#term_conditions_editor', {
            theme: 'snow',
            placeholder: 'Terms & Conditions',
            modules: {
                toolbar: '#term_conditions_toolbar'
            }
        });
        termConditionsEditor.root.innerHTML = $('#term_conditions').val() || '';
        window.invoiceQuillEditors.term_conditions = termConditionsEditor;

        var invoiceFooterEditor = new Quill('#invoice_footer_editor', {
            theme: 'snow',
            placeholder: 'Invoice Footer',
            modules: {
                toolbar: '#invoice_footer_toolbar'
            }
        });
        invoiceFooterEditor.root.innerHTML = $('#invoice_footer').val() || '';
        window.invoiceQuillEditors.invoice_footer = invoiceFooterEditor;
    }
    $(document).ready(function () {
        initInvoiceQuillEditors();
    });

    // Tax Setting - Show/Hide fields based on collect_tax
    function toggleTaxFields(collectTax) {
        if (collectTax === 'No') {
            $('.tax-field').hide();
        } else {
            $('.tax-field').show();
        }
    }

    // Initialize tax fields visibility on page load
    $(document).ready(function() {
        let collectTax = $('input[name="collect_tax"]:checked').val();
        if (collectTax) {
            toggleTaxFields(collectTax);
        }
    });

    // Handle collect_tax radio button change
    $(document).on('change', 'input[name="collect_tax"]', function() {
        let collectTax = $(this).val();
        toggleTaxFields(collectTax);
    });

    // Tax Setting - Migrate legacy tax_setting
    $(document).on('click', '#tax_setting_migrate_btn', function (e) {
        e.preventDefault();
        const $btn = $(this);
        const migrateTitle = language_key.Are_You_Sure || 'Are you sure?';
        const migrateText = language_key.Migrate_tax_setting_confirmation || 'This will migrate tax_setting to taxs table and update items, sales, sale_details. Continue?';
        const yesText = language_key.yes_continue || 'Yes, continue';
        const cancelText = language_key.no_cancel || 'Cancel';
        function doMigrate() {
            $btn.prop('disabled', true);
            $.ajax({
                type: 'POST',
                url: base_url + '/tax-setting-migrate',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        showSuccessNotification(response.message || 'Tax migration completed successfully');
                        setTimeout(function () { window.location.reload(); }, 1500);
                    } else {
                        showErrorNotification(response.message || 'Migration failed');
                        $btn.prop('disabled', false);
                    }
                },
                error: function (xhr) {
                    showErrorNotification(xhr.responseJSON?.message || 'Migration failed');
                    $btn.prop('disabled', false);
                }
            });
        }
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: migrateTitle,
                text: migrateText,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: yesText,
                cancelButtonText: cancelText
            }).then(function (result) {
                if (result.isConfirmed) doMigrate();
            });
        } else {
            if (confirm(migrateTitle + ' ' + migrateText)) doMigrate();
        }
    });

    // Tax Setting
    $(document).on('click', '.tax_setting_submit', function (e) {
        e.preventDefault();
        let formData = $('#tax_setting_form').serialize();
        // Clear previous errors
        clearValidationErrors();
        $.ajax({
            type: "POST",
            url: base_url + "/tax-setting",
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    showSuccessNotification(response.message || 'Tax setting save successfully');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showErrorNotification(response.message || 'Something went wrong');
                }
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    showValidationErrors('#tax_setting_form', xhr.responseJSON.errors);
                    showErrorNotification('Please check the form for errors');
                } else {
                    showErrorNotification('An unexpected error occurred');
                }
            }
        });
    });


    // Email Setting
    $(document).on('change', '#smtp_type', function() {
        let smtp_type = $(this).val();
        if(smtp_type == 'Gmail') {
            $('.sendinblue-api-field').hide();
        } else if(smtp_type == 'Sendinblue') {
            $('.sendinblue-api-field').show();
        }
    });

    // Email Setting
    $(document).on('click', '.email_setting_submit', function (e) {
        e.preventDefault();
        let formData = $('#email_setting_form').serialize();
        // Clear previous errors
        clearValidationErrors();
        $.ajax({
            type: "POST",
            url: base_url + "/email-setting",
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    showSuccessNotification(response.message || 'Email setting save successfully');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showErrorNotification(response.message || 'Something went wrong');
                }
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    // Handle validation errors
                    showValidationErrors('#email_setting_form', xhr.responseJSON.errors);
                    showErrorNotification('Please check the form for errors');
                } else {
                    showErrorNotification('An unexpected error occurred');
                }
            }
        });
    });

    // Old mail-send-btn handler removed - using modals now
    

    // SMS Setting
    $(document).on('change', '#sms_service_provider', function() {
        let provider = $(this).val();
        
        // Hide all provider fields initially
        $('.provider-1-fields').addClass('d-none');
        $('.provider-2-fields').addClass('d-none'); 
        $('.provider-3-fields').addClass('d-none');
        $('.provider-4-fields').addClass('d-none');
    
        // Show fields based on selected provider
        if (provider == '1') {
            $('.provider-1-fields').removeClass('d-none');
        } else if (provider == '2') {
            $('.provider-2-fields').removeClass('d-none');
        } else if (provider == '3') {
            $('.provider-3-fields').removeClass('d-none');
        } else if (provider == '4') {
            $('.provider-4-fields').removeClass('d-none');
        }
    });
    
    // Trigger change event on page load if provider is pre-selected
    $(document).ready(function() {
        $('#sms_service_provider').trigger('change');
    });
    
    $(document).on('change', '#sms_enable_status', function() {
        let status = $(this).val();
        
        if (status == '0') {
            $('#sms_service_provider').prop('disabled', true);
            $('.provider-1-fields, .provider-2-fields, .provider-3-fields, .provider-4-fields').addClass('d-none');
        } else {
            $('#sms_service_provider').prop('disabled', false);
            $('#sms_service_provider').trigger('change');
        }
    });

    $(document).on('click', '.sms_setting_submit', function (e) {
        e.preventDefault();
        let formData = $('#sms_setting_form').serialize();
        // Clear previous errors
        clearValidationErrors();
        $.ajax({
            type: "POST",
            url: base_url + "/sms-setting",
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    showSuccessNotification(response.message || 'SMS setting save successfully');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showErrorNotification(response.message || 'Something went wrong');
                }
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    // Handle validation errors
                    showValidationErrors('#sms_setting_form', xhr.responseJSON.errors);
                    showErrorNotification('Please check the form for errors');
                } else {
                    showErrorNotification('An unexpected error occurred');
                }
            }
        });
    });

    // Old sms-send-btn handler removed - using modals now


    // WhatsApp Setting
    $(document).on('click', '.whatsapp_setting_submit', function (e) {
        e.preventDefault();
        let formData = $('#whatsapp_setting_form').serialize();
        // Clear previous errors
        clearValidationErrors();
        $.ajax({
            type: "POST",
            url: base_url + "/whatsapp-setting",
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    showSuccessNotification(response.message || 'WhatsApp setting save successfully');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showErrorNotification(response.message || 'Something went wrong');
                }
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    // Handle validation errors
                    showValidationErrors('#whatsapp_setting_form', xhr.responseJSON.errors);
                    showErrorNotification('Please check the form for errors');
                } else {
                    showErrorNotification('An unexpected error occurred');
                }
            }
        });
    });

    // Whitelabel Setting
    $(document).on('click', '.whitelabel_setting_submit', function (e) {
        e.preventDefault();
        let formData = new FormData($('#whitelabel_setting_form')[0]);
        // Clear previous errors
        clearValidationErrors();
        $.ajax({
            type: "POST",
            url: base_url + "/whitelabel-setting",
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    showSuccessNotification(response.message || 'Whitelabel setting save successfully');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showErrorNotification(response.message || 'Something went wrong');
                }
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    // Handle validation errors
                    showValidationErrors('#whitelabel_setting_form', xhr.responseJSON.errors);
                    showErrorNotification('Please check the form for errors');
                } else {
                    showErrorNotification('An unexpected error occurred');
                }
            }
        });
    });
    $(document).on('change', '#site_logo', function() {
        previewImage(this, 'site_logo_preview');
    });
    $(document).on('change', '#site_favicon', function() {
        previewImage(this, 'site_favicon_preview');
    });

    // PWA Setting
    $(document).on('click', '.pwa_setting_submit', function (e) {
        e.preventDefault();
        let formData = new FormData($('#pwa_setting_form')[0]);
        clearValidationErrors();
        $.ajax({
            type: "POST",
            url: base_url + "/pwa-setting",
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    showSuccessNotification(response.message || 'PWA setting saved successfully');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showErrorNotification(response.message || 'Something went wrong');
                }
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    showValidationErrors('#pwa_setting_form', xhr.responseJSON.errors);
                    showErrorNotification('Please check the form for errors');
                } else {
                    showErrorNotification(xhr.responseJSON?.message || 'An unexpected error occurred');
                }
            }
        });
    });
    $(document).on('change', '#pwa_logo_file', function() {
        previewImage(this, 'pwa_logo_preview');
    });
    function previewImage(input, previewId) {
        const preview = document.getElementById(previewId);
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }



    $(window).on('wheel', function(e) {
        const scrollable = $('.scrollable');
        scrollable.scrollTop(scrollable.scrollTop() + e.originalEvent.deltaY);
        e.preventDefault();
    });

    // ZATCA Setting Form Submission
    $(document).on('click', '.zatca_setting_submit', function (e) {
        e.preventDefault();
        let formData = $('#zatca_setting_form').serialize();
        // Clear previous errors
        clearValidationErrors();
        $.ajax({
            type: "POST",
            url: base_url + "/zatca-setting",
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    showSuccessNotification(response.message || 'ZATCA setting saved successfully');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showErrorNotification(response.message || 'Something went wrong');
                }
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    // Handle validation errors
                    showValidationErrors('#zatca_setting_form', xhr.responseJSON.errors);
                    showErrorNotification('Please check the form for errors');
                } else {
                    showErrorNotification(xhr.responseJSON?.message || 'An unexpected error occurred');
                }
            }
        });
    });

    // Generate Directory Button
    $(document).on('click', '#generate_directory_btn', function (e) {
        e.preventDefault();
        let btn = $(this);
        let originalText = btn.html();


        $.ajax({
            type: "POST",
            url: base_url + "/zatca-generate-directory",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    showSuccessNotification(response.message || 'Directory created successfully');
                } else {
                    showErrorNotification(response.message || 'Failed to create directory');
                }
                btn.prop('disabled', false).html(originalText);
            },
            error: function (xhr) {
                showErrorNotification(xhr.responseJSON?.message || 'Failed to create directory');
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Generate CSR Button
    $(document).on('click', '#generate_csr_btn', function (e) {
        e.preventDefault();
        let btn = $(this);
        let originalText = btn.html();
        
        // Validate required fields
        let vatNumber = $('#vat_registration_number').val();
        let arabicName = $('#legal_business_name_arabic').val();
        let englishName = $('#legal_business_name_english').val();
        let address = $('#zatca_address').val();

        if (!vatNumber || !arabicName || !englishName || !address) {
            showErrorNotification('Please fill in all environment information fields before generating CSR');
            return;
        }

        $.ajax({
            type: "POST",
            url: base_url + "/zatca-generate-csr",
            data: {
                vat_registration_number: vatNumber,
                legal_business_name_arabic: arabicName,
                legal_business_name_english: englishName,
                zatca_address: address
            },
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    showSuccessNotification(response.message || 'CSR generated successfully');
                    // Reload page to show download buttons
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    showErrorNotification(response.message || 'Failed to generate CSR');
                    btn.prop('disabled', false).html(originalText);
                }
            },
            error: function (xhr) {
                showErrorNotification(xhr.responseJSON?.message || 'Failed to generate CSR');
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // ========== Settings Page Initialization ==========
    // This code was moved from the Blade template to keep JS separate
    
    // Get config (set by Blade template)
    const config = window.settingsConfig || {};
    const defaultImagePath = config.defaultImagePath || '';
    const translations = config.translations || {};

    // Site Logo Cropper Variables
    let siteLogoCropperInstance = null;
    let siteLogoCropperModal = null;
    let siteLogoOriginalSrc = null;
    let siteLogoPreview = document.getElementById('site_logo_preview');
    let siteLogoFileInput = document.getElementById('site_logo_file');
    let siteLogoHiddenInput = document.getElementById('site_logo');
    let siteLogoFileType = null;
    
    // Site Favicon Cropper Variables
    let siteFaviconCropperInstance = null;
    let siteFaviconCropperModal = null;
    let siteFaviconOriginalSrc = null;
    let siteFaviconPreview = document.getElementById('site_favicon_preview');
    let siteFaviconFileInput = document.getElementById('site_favicon_file');
    let siteFaviconHiddenInput = document.getElementById('site_favicon');
    let siteFaviconFileType = null;
    
    // Invoice Logo Cropper Variables
    let invoiceLogoCropperInstance = null;
    let invoiceLogoCropperModal = null;
    let invoiceLogoOriginalSrc = null;
    let invoiceLogoPreview = document.getElementById('invoice_logo_preview');
    let invoiceLogoFileInput = document.getElementById('invoice_logo_file');
    let invoiceLogoHiddenInput = document.getElementById('invoice_logo');
    let invoiceLogoFileType = null;
    
    // Initialize variables if elements exist
    if (siteLogoFileInput && document.getElementById('site_logo_hidden')) {
        let siteLogoHiddenValue = document.getElementById('site_logo_hidden').value;
        if (siteLogoHiddenValue && config.siteLogoOriginalSrc) {
            siteLogoOriginalSrc = config.siteLogoOriginalSrc;
        }
    }
    
    if (siteFaviconFileInput && document.getElementById('site_favicon_hidden')) {
        let siteFaviconHiddenValue = document.getElementById('site_favicon_hidden').value;
        if (siteFaviconHiddenValue && config.siteFaviconOriginalSrc) {
            siteFaviconOriginalSrc = config.siteFaviconOriginalSrc;
        }
    }
    
    if (invoiceLogoFileInput && document.getElementById('invoice_logo_p')) {
        let invoiceLogoHiddenValue = document.getElementById('invoice_logo_p').value;
        if (invoiceLogoPreview && invoiceLogoHiddenValue) {
            invoiceLogoOriginalSrc = invoiceLogoPreview.src;
        }
    }

    // Get SMTP and SMS enable status
    let smtpEnableStatus = document.getElementById('smtp_enable_status') ? document.getElementById('smtp_enable_status').value : '0';
    let smsEnableStatus = document.getElementById('sms_enable_status') ? document.getElementById('sms_enable_status').value : '0';

    // SMTP Enable Status Change
    $(document).on('change', '#smtp_enable_status', function() {
        smtpEnableStatus = $(this).val();
        smtpEnableStatusChange(smtpEnableStatus);
        validateEmailSettings();
    });

    function smtpEnableStatusChange(smtpEnableStatus) {
        if (smtpEnableStatus == '1') {
            $('.email_service_wrap').show();
        } else {
            $('.email_service_wrap').hide();
        }
    }
    if (document.getElementById('smtp_enable_status')) {
        smtpEnableStatusChange(smtpEnableStatus);
    }

    // SMS Enable Status Change
    $(document).on('change', '#sms_enable_status', function() {
        smsEnableStatus = $(this).val();
        smsEnableStatusChange(smsEnableStatus);
        validateSMSSettings();
    });

    function smsEnableStatusChange(smsEnableStatus) {
        if (smsEnableStatus == '1') {
            $('.sms_service_wrap').show();
        } else {
            $('.sms_service_wrap').hide();
        }
    }
    if (document.getElementById('sms_enable_status')) {
        smsEnableStatusChange(smsEnableStatus);
    }

    // Site Logo file change handler
    if (siteLogoFileInput) {
        $(siteLogoFileInput).on('change', function(e) {
            if (this.files && this.files[0]) {
                let file = this.files[0];
                if (!file.type.match('image.*')) {
                    showErrorNotification('Please select an image file');
                    $(this).val('');
                    return;
                }
                // Validate file size (max 1MB)
                if (file.size > 1 * 1024 * 1024) {
                    showErrorNotification('Image size should be less than 1MB');
                    $(this).val('');
                    return;
                }

                // Store file type for transparency preservation
                siteLogoFileType = file.type;
                
                let reader = new FileReader();
                reader.onload = function(e) {
                    showSiteLogoCropperModal(e.target.result);
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // Site Favicon file change handler
    if (siteFaviconFileInput) {
        $(siteFaviconFileInput).on('change', function(e) {
            if (this.files && this.files[0]) {
                let file = this.files[0];
                if (!file.type.match('image.*')) {
                    showErrorNotification('Please select an image file');
                    $(this).val('');
                    return;
                }
                // Validate file size (max 1MB)
                if (file.size > 1 * 1024 * 1024) {
                    showErrorNotification('Image size should be less than 1MB');
                    $(this).val('');
                    return;
                }

                // Store file type for transparency preservation
                siteFaviconFileType = file.type;

                let reader = new FileReader();
                reader.onload = function(e) {
                    showSiteFaviconCropperModal(e.target.result);
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // Remove Site Logo
    $(document).on('click', '.remove-site-logo', function() {
        if (siteLogoOriginalSrc) {
            $(siteLogoFileInput).val('');
            $(siteLogoHiddenInput).val('');
            $(siteLogoPreview).attr('src', siteLogoOriginalSrc).show();
        } else {
            $(siteLogoFileInput).val('');
            $(siteLogoHiddenInput).val('');
            $(siteLogoPreview).attr('src', defaultImagePath);
            $('.remove-site-logo').hide();
            $('.preview-site-logo').hide();
        }
    });

    // Remove Site Favicon
    $(document).on('click', '.remove-site-favicon', function() {
        if (siteFaviconOriginalSrc) {
            $(siteFaviconFileInput).val('');
            $(siteFaviconHiddenInput).val('');
            $(siteFaviconPreview).attr('src', siteFaviconOriginalSrc).show();
        } else {
            $(siteFaviconFileInput).val('');
            $(siteFaviconHiddenInput).val('');
            $(siteFaviconPreview).attr('src', defaultImagePath);
            $('.remove-site-favicon').hide();
            $('.preview-site-favicon').hide();
        }
    });

    // Preview Site Logo
    $(document).on('click', '.preview-site-logo', function() {
        let imageSrc = $(siteLogoPreview).attr('src');
        let defaultImageSrc = defaultImagePath;
        
        if (!imageSrc || imageSrc === defaultImageSrc) {
            return;
        }

        let previewModalHtml = `
            <div class="modal fade" id="siteLogoPreviewModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${translations.imagePreview || 'Image Preview'}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center">
                            <img src="${imageSrc}" class="img-fluid" alt="${translations.siteLogo || 'Site Logo'}" style="max-height: 70vh;">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">${translations.close || 'Close'}</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        $('#siteLogoPreviewModal').remove();
        $('body').append(previewModalHtml);
        let previewModalElement = document.getElementById('siteLogoPreviewModal');
        let previewModal = new bootstrap.Modal(previewModalElement);
        
        $(previewModalElement).on('hidden.bs.modal', function () {
            $(this).remove();
        });
        
        previewModal.show();
    });

    // Preview Site Favicon
    $(document).on('click', '.preview-site-favicon', function() {
        let imageSrc = $(siteFaviconPreview).attr('src');
        let defaultImageSrc = defaultImagePath;
        
        if (!imageSrc || imageSrc === defaultImageSrc) {
            return;
        }

        let previewModalHtml = `
            <div class="modal fade" id="siteFaviconPreviewModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${translations.imagePreview || 'Image Preview'}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center">
                            <img src="${imageSrc}" class="img-fluid" alt="${translations.siteFavicon || 'Site Favicon'}" style="max-height: 70vh;">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">${translations.close || 'Close'}</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        $('#siteFaviconPreviewModal').remove();
        $('body').append(previewModalHtml);
        let previewModalElement = document.getElementById('siteFaviconPreviewModal');
        let previewModal = new bootstrap.Modal(previewModalElement);
        
        $(previewModalElement).on('hidden.bs.modal', function () {
            $(this).remove();
        });
        
        previewModal.show();
    });

    // Show Site Logo Cropper Modal
    function showSiteLogoCropperModal(imageSrc) {
        $('#siteLogoCropperModal').remove();
        if (siteLogoCropperInstance) {
            siteLogoCropperInstance.destroy();
            siteLogoCropperInstance = null;
        }
        if (siteLogoCropperModal) {
            siteLogoCropperModal.dispose();
            siteLogoCropperModal = null;
        }

        let modalHtml = `
            <div class="modal fade" id="siteLogoCropperModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${translations.cropSiteLogo || 'Crop Site Logo'}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="image-cropper-container" style="min-height: 400px;">
                                <img id="siteLogoCropperImage" src="${imageSrc}" style="max-width: 100%; display: block;">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" id="cropSiteLogoBtn">${translations.cropSave || 'Crop & Save'}</button>
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">${translations.cancel || 'Cancel'}</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHtml);
        let modalElement = document.getElementById('siteLogoCropperModal');
        siteLogoCropperModal = new bootstrap.Modal(modalElement, {
            backdrop: 'static',
            keyboard: false
        });
        $(modalElement).on('shown.bs.modal', function () {
            let cropperImage = document.getElementById('siteLogoCropperImage');
            if (cropperImage && typeof Cropper !== 'undefined') {
                siteLogoCropperInstance = new Cropper(cropperImage, {
                    autoCropArea: 0.8,
                    responsive: true,
                    restore: false,
                    guides: true,
                    center: true,
                    highlight: false,
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                    toggleDragModeOnDblclick: false,
                });
            } else {
                console.error('Cropper image element not found or Cropper library not loaded');
            }
        });

        $(modalElement).on('click', '#cropSiteLogoBtn', function() {
            if (siteLogoCropperInstance) {
                let canvas = siteLogoCropperInstance.getCroppedCanvas({
                    width: 400,
                    height: 400,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                });

                if (canvas) {
                    // Detect original image format to preserve transparency
                    let originalImg = document.getElementById('siteLogoCropperImage');
                    let imageFormat = 'image/jpeg';
                    let imageQuality = 0.9;
                    
                    // Check stored file type first (most accurate)
                    if (siteLogoFileType === 'image/png') {
                        imageFormat = 'image/png';
                        imageQuality = undefined; // PNG doesn't use quality parameter
                    } else if (siteLogoFileType === 'image/gif') {
                        imageFormat = 'image/png'; // Convert GIF to PNG to preserve transparency
                        imageQuality = undefined;
                    } else if (originalImg && originalImg.src) {
                        // Fallback to checking data URL
                        if (originalImg.src.toLowerCase().includes('data:image/png')) {
                            imageFormat = 'image/png';
                            imageQuality = undefined;
                        } else if (originalImg.src.toLowerCase().includes('data:image/gif')) {
                            imageFormat = 'image/png';
                            imageQuality = undefined;
                        }
                    }
                    
                    let croppedImage = imageQuality !== undefined 
                        ? canvas.toDataURL(imageFormat, imageQuality)
                        : canvas.toDataURL(imageFormat);
                    $(siteLogoPreview).attr('src', croppedImage).show();
                    $(siteLogoHiddenInput).val(croppedImage);
                    $('.remove-site-logo').show();
                    $('.preview-site-logo').show();
                    siteLogoCropperModal.hide();
                }
            }
        });
        $(modalElement).on('hidden.bs.modal', function () {
            if (siteLogoCropperInstance) {
                siteLogoCropperInstance.destroy();
                siteLogoCropperInstance = null;
            }
            $(this).remove();
            siteLogoCropperModal = null;
        });
        siteLogoCropperModal.show();
    }

    // Show Site Favicon Cropper Modal
    function showSiteFaviconCropperModal(imageSrc) {
        $('#siteFaviconCropperModal').remove();
        if (siteFaviconCropperInstance) {
            siteFaviconCropperInstance.destroy();
            siteFaviconCropperInstance = null;
        }
        if (siteFaviconCropperModal) {
            siteFaviconCropperModal.dispose();
            siteFaviconCropperModal = null;
        }

        let modalHtml = `
            <div class="modal fade" id="siteFaviconCropperModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${translations.cropSiteFavicon || 'Crop Site Favicon'}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="image-cropper-container" style="min-height: 400px;">
                                <img id="siteFaviconCropperImage" src="${imageSrc}" style="max-width: 100%; display: block;">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" id="cropSiteFaviconBtn">${translations.cropSave || 'Crop & Save'}</button>
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">${translations.cancel || 'Cancel'}</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHtml);
        let modalElement = document.getElementById('siteFaviconCropperModal');
        siteFaviconCropperModal = new bootstrap.Modal(modalElement, {
            backdrop: 'static',
            keyboard: false
        });
        $(modalElement).on('shown.bs.modal', function () {
            let cropperImage = document.getElementById('siteFaviconCropperImage');
            if (cropperImage && typeof Cropper !== 'undefined') {
                siteFaviconCropperInstance = new Cropper(cropperImage, {
                    aspectRatio: 1,
                    viewMode: 1,
                    autoCropArea: 0.8,
                    responsive: true,
                    restore: false,
                    guides: true,
                    center: true,
                    highlight: false,
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                    toggleDragModeOnDblclick: false,
                });
            } else {
                console.error('Cropper image element not found or Cropper library not loaded');
            }
        });

        $(modalElement).on('click', '#cropSiteFaviconBtn', function() {
            if (siteFaviconCropperInstance) {
                let canvas = siteFaviconCropperInstance.getCroppedCanvas({
                    width: 256,
                    height: 256,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                });

                if (canvas) {
                    // Detect original image format to preserve transparency
                    let originalImg = document.getElementById('siteFaviconCropperImage');
                    let imageFormat = 'image/jpeg';
                    let imageQuality = 0.9;
                    
                    // Check stored file type first (most accurate)
                    if (siteFaviconFileType === 'image/png') {
                        imageFormat = 'image/png';
                        imageQuality = undefined; // PNG doesn't use quality parameter
                    } else if (siteFaviconFileType === 'image/gif') {
                        imageFormat = 'image/png'; // Convert GIF to PNG to preserve transparency
                        imageQuality = undefined;
                    } else if (originalImg && originalImg.src) {
                        // Fallback to checking data URL
                        if (originalImg.src.toLowerCase().includes('data:image/png')) {
                            imageFormat = 'image/png';
                            imageQuality = undefined;
                        } else if (originalImg.src.toLowerCase().includes('data:image/gif')) {
                            imageFormat = 'image/png';
                            imageQuality = undefined;
                        }
                    }
                    
                    let croppedImage = imageQuality !== undefined 
                        ? canvas.toDataURL(imageFormat, imageQuality)
                        : canvas.toDataURL(imageFormat);
                    $(siteFaviconPreview).attr('src', croppedImage).show();
                    $(siteFaviconHiddenInput).val(croppedImage);
                    $('.remove-site-favicon').show();
                    $('.preview-site-favicon').show();
                    siteFaviconCropperModal.hide();
                }
            }
        });
        $(modalElement).on('hidden.bs.modal', function () {
            if (siteFaviconCropperInstance) {
                siteFaviconCropperInstance.destroy();
                siteFaviconCropperInstance = null;
            }
            $(this).remove();
            siteFaviconCropperModal = null;
        });
        siteFaviconCropperModal.show();
    }

    // Check and show remove buttons
    function checkAndShowRemoveButtons() {
        if (!siteLogoPreview || !siteFaviconPreview) return;
        
        let siteLogoPreviewSrc = $(siteLogoPreview).attr('src');
        let siteFaviconPreviewSrc = $(siteFaviconPreview).attr('src');
        let defaultImageSrc = defaultImagePath;
        
        if ($(siteLogoPreview).is(':visible') && siteLogoPreviewSrc && siteLogoPreviewSrc !== defaultImageSrc) {
            $('.remove-site-logo').show();
            if (config.hasSiteLogo) {
                $('.preview-site-logo').show();
            }
        } else {
            $('.remove-site-logo').hide();
            $('.preview-site-logo').hide();
        }

        if ($(siteFaviconPreview).is(':visible') && siteFaviconPreviewSrc && siteFaviconPreviewSrc !== defaultImageSrc) {
            $('.remove-site-favicon').show();
            if (config.hasSiteFavicon) {
                $('.preview-site-favicon').show();
            }
        } else {
            $('.remove-site-favicon').hide();
            $('.preview-site-favicon').hide();
        }
    }
    checkAndShowRemoveButtons();

    // Invoice Numbering Format Preview
    function updateInvoiceFormatPreview() {
        let schemaType = $('input[name="schema_type"]:checked').val() || 'XXXX';
        let prefix = $('#inv_prefix').val() || '';
        let startFrom = $('#inv_start_from').val() || '1';
        let numberOfDigits = $('#inv_number_of_digit').val() || '4';
        let numberingType = $('#inv_numbering_type').val() || 'Sequential';
        
        let preview = '';
        
        // If numbering type is Random, generate a random number based on digits
        if (numberingType === 'Random') {
            let min = Math.pow(10, parseInt(numberOfDigits) - 1);
            let max = Math.pow(10, parseInt(numberOfDigits)) - 1;
            let randomNum = Math.floor(Math.random() * (max - min + 1)) + min;
            let paddedNumber = String(randomNum).padStart(parseInt(numberOfDigits), '0');
            
            // Replace XXXX with random number
            if (schemaType.includes('XXXX')) {
                preview = schemaType.replace(/XXXX/g, paddedNumber);
            } else {
                preview = paddedNumber;
            }
        } else {
            // Sequential numbering
            let paddedNumber = String(startFrom).padStart(parseInt(numberOfDigits), '0');
            
            // Replace XXXX with padded number (works for both XXXX and YYYY-XXXX formats)
            if (schemaType.includes('XXXX')) {
                preview = schemaType.replace(/XXXX/g, paddedNumber);
            } else if (schemaType && schemaType !== 'XXXX') {
                preview = schemaType.replace(/XXXX/g, paddedNumber);
            } else {
                preview = paddedNumber;
            }
        }
        
        // Add prefix if exists
        if (prefix) {
            preview = prefix + preview;
        }
        
        $('.inv-format-number').text(preview || 'Preview will appear here');
    }

    // Show/hide Start From field based on numbering type
    function toggleStartFromField() {
        let numberingType = $('#inv_numbering_type').val();
        if (numberingType === 'Random') {
            $('.start_from_wrap').hide();
            $('#inv_start_from').val(''); // Clear value when Random is selected
        } else {
            $('.start_from_wrap').show();
            if (!$('#inv_start_from').val() || $('#inv_start_from').val() === '') {
                $('#inv_start_from').val('1'); // Set default value
            }
        }
    }

    // Initialize start from field visibility
    toggleStartFromField();

    // Update preview on change
    $(document).on('change', 'input[name="schema_type"]', function() {
        updateInvoiceFormatPreview();
    });

    $(document).on('input change', '#inv_prefix, #inv_start_from, #inv_number_of_digit', function() {
        updateInvoiceFormatPreview();
    });

    $(document).on('change', '#inv_numbering_type', function() {
        toggleStartFromField();
        updateInvoiceFormatPreview();
    });

    // Initialize preview on page load
    updateInvoiceFormatPreview();

    // Invoice Logo File Change Handler
    if (invoiceLogoFileInput) {
        $(invoiceLogoFileInput).on('change', function(e) {
            if (this.files && this.files[0]) {
                let file = this.files[0];
                if (!file.type.match('image.*')) {
                    showErrorNotification('Please select an image file');
                    $(this).val('');
                    return;
                }
                // Validate file size (max 1MB)
                if (file.size > 1 * 1024 * 1024) {
                    showErrorNotification('Image size should be less than 1MB');
                    $(this).val('');
                    return;
                }

                // Store file type for transparency preservation
                invoiceLogoFileType = file.type;

                let reader = new FileReader();
                reader.onload = function(e) {
                    showInvoiceLogoCropperModal(e.target.result);
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // Show Invoice Logo Cropper Modal
    function showInvoiceLogoCropperModal(imageSrc) {
        $('#invoiceLogoCropperModal').remove();
        if (invoiceLogoCropperInstance) {
            invoiceLogoCropperInstance.destroy();
            invoiceLogoCropperInstance = null;
        }
        if (invoiceLogoCropperModal) {
            invoiceLogoCropperModal.dispose();
            invoiceLogoCropperModal = null;
        }

        let modalHtml = `
            <div class="modal fade" id="invoiceLogoCropperModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${translations.cropInvoiceLogo || 'Crop Invoice Logo'}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="image-cropper-container" style="min-height: 400px;">
                                <img id="invoiceLogoCropperImage" src="${imageSrc}" style="max-width: 100%; display: block;">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" id="cropInvoiceLogoBtn">${translations.cropSave || 'Crop & Save'}</button>
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">${translations.cancel || 'Cancel'}</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHtml);
        let modalElement = document.getElementById('invoiceLogoCropperModal');
        invoiceLogoCropperModal = new bootstrap.Modal(modalElement, {
            backdrop: 'static',
            keyboard: false
        });
        $(modalElement).on('shown.bs.modal', function () {
            let cropperImage = document.getElementById('invoiceLogoCropperImage');
            if (cropperImage && typeof Cropper !== 'undefined') {
                invoiceLogoCropperInstance = new Cropper(cropperImage, {
                    aspectRatio: NaN,
                    viewMode: 1,
                    autoCropArea: 0.8,
                    responsive: true,
                    restore: false,
                    guides: true,
                    center: true,
                    highlight: false,
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                    toggleDragModeOnDblclick: false,
                });
            } else {
                console.error('Cropper image element not found or Cropper library not loaded');
            }
        });

        $(modalElement).on('click', '#cropInvoiceLogoBtn', function() {
            if (invoiceLogoCropperInstance) {
                let canvas = invoiceLogoCropperInstance.getCroppedCanvas({
                    width: 400,
                    height: 400,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                });

                if (canvas) {
                    // Detect original image format to preserve transparency
                    let originalImg = document.getElementById('invoiceLogoCropperImage');
                    let imageFormat = 'image/jpeg';
                    let imageQuality = 0.9;
                    
                    // Check stored file type first (most accurate)
                    if (invoiceLogoFileType === 'image/png') {
                        imageFormat = 'image/png';
                        imageQuality = undefined;
                    } else if (invoiceLogoFileType === 'image/gif') {
                        imageFormat = 'image/png';
                        imageQuality = undefined;
                    } else if (originalImg && originalImg.src) {
                        // Fallback to checking data URL
                        if (originalImg.src.toLowerCase().includes('data:image/png')) {
                            imageFormat = 'image/png';
                            imageQuality = undefined;
                        } else if (originalImg.src.toLowerCase().includes('data:image/gif')) {
                            imageFormat = 'image/png';
                            imageQuality = undefined;
                        }
                    }
                    
                    let croppedImage = imageQuality !== undefined 
                        ? canvas.toDataURL(imageFormat, imageQuality)
                        : canvas.toDataURL(imageFormat);
                    $(invoiceLogoPreview).attr('src', croppedImage).show();
                    $(invoiceLogoHiddenInput).val(croppedImage);
                    $('.remove-invoice-logo').show();
                    if (invoiceLogoOriginalSrc) {
                        $('.preview-invoice-logo').show();
                    }
                    invoiceLogoCropperModal.hide();
                }
            }
        });
        $(modalElement).on('hidden.bs.modal', function () {
            if (invoiceLogoCropperInstance) {
                invoiceLogoCropperInstance.destroy();
                invoiceLogoCropperInstance = null;
            }
            $(this).remove();
            invoiceLogoCropperModal = null;
        });
        invoiceLogoCropperModal.show();
    }

    // Remove Invoice Logo
    $(document).on('click', '.remove-invoice-logo', function() {
        if (invoiceLogoOriginalSrc) {
            $(invoiceLogoFileInput).val('');
            $(invoiceLogoHiddenInput).val('');
            $(invoiceLogoPreview).attr('src', invoiceLogoOriginalSrc).show();
        } else {
            $(invoiceLogoFileInput).val('');
            $(invoiceLogoHiddenInput).val('');
            $(invoiceLogoPreview).attr('src', defaultImagePath);
            $('.remove-invoice-logo').hide();
            $('.preview-invoice-logo').hide();
        }
    });

    // Preview Invoice Logo in Modal
    $(document).on('click', '.preview-invoice-logo', function() {
        if (invoiceLogoPreview && invoiceLogoPreview.src) {
            let imageSrc = invoiceLogoPreview.src;
            let defaultImageSrc = defaultImagePath;
            
            if (!imageSrc || imageSrc === defaultImageSrc) {
                return;
            }

            let previewModalHtml = `
                <div class="modal fade" id="invoiceLogoPreviewModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">${translations.invoiceLogoPreview || 'Invoice Logo Preview'}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-center">
                                <img src="${imageSrc}" class="img-fluid" alt="${translations.invoiceLogo || 'Invoice Logo'}" style="max-height: 70vh;">
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">${translations.close || 'Close'}</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('#invoiceLogoPreviewModal').remove();
            $('body').append(previewModalHtml);
            let previewModalElement = document.getElementById('invoiceLogoPreviewModal');
            let previewModal = new bootstrap.Modal(previewModalElement);
            
            $(previewModalElement).on('hidden.bs.modal', function () {
                $(this).remove();
            });
            
            previewModal.show();
        }
    });

    // Reset Heading and Label to Default
    $(document).on('click', '.reset-heading-labels-btn', function() {
        Swal.fire({
            title: 'Reset to Default?',
            text: 'Are you sure you want to reset all heading and label fields to their default values?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, reset them',
            cancelButtonText: 'Cancel',
            confirmButtonClass: 'btn btn-success',
            cancelButtonClass: 'btn btn-danger',
            buttonsStyling: false,
            reverseButtons: true
        }).then(function(result) {
            if (result.isConfirmed) {
                // Reset all required fields to their default values
            $('#invoice_heading').val('Invoice');
            $('#invoice_heading_arabic').val('');
            $('#invoice_heading_due').val('Due');
            $('#invoice_heading_paid').val('Paid');
            $('#invoice_no_label').val('Invoice No');
            $('#invoice_no_label_arabic').val('');
            $('#invoice_date_label').val('Date');
            $('#invoice_date_label_arabic').val('');
            $('#invoice_show_due_date').val('Yes').trigger('change');
            $('#invoice_due_date_label').val('Due Date');
            $('#invoice_due_date_label_arabic').val('');
            $('#sales_person_label').val('Sales Person');
            $('#commission_agent_label').val('Commission Agent');
            $('#show_business_name').val('Yes').trigger('change');
            $('#business_name_arabic').val('');
            let defaultTaxNumber = $('#show_business_tax_number').data('default') || 'No';
            $('#show_business_tax_number').val(defaultTaxNumber).trigger('change');
            $('#business_tax_number_label').val('Business Tax Number');
            $('#customer_label').val('Customer');
            $('#customer_tax_number_label').val('Customer Tax Number');
            $('#show_customer_phone_number').val('Yes').trigger('change');
            $('#show_customer_email').val('No').trigger('change');
            $('#show_customer_address').val('Yes').trigger('change');
            $('#serial_no_label').val('SN');
            $('#serial_no_label_arabic').val('');
            $('#item_label').val('Item');
            $('#item_label_arabic').val('');
            $('#price_label').val('Price');
            $('#price_label_arabic').val('');
            $('#quantity_label').val('Qty');
            $('#quantity_label_arabic').val('');
            $('#item_discount_label').val('Discount');
            $('#item_discount_label_arabic').val('');
            $('#subtotal_label').val('Subtotal');
            $('#subtotal_label_arabic').val('');
            $('#total_label').val('Total');
            $('#total_label_arabic').val('');
            $('#total_item_label').val('Total Item');
            $('#total_item_label_arabic').val('');
            $('#tax_label').val('Tax');
            $('#tax_label_arabic').val('');
            $('#charge_label').val('Charge');
            $('#charge_label_arabic').val('');
            $('#discount_label').val('Discount');
            $('#discount_label_arabic').val('');
            $('#delivery_partner_label').val('Delivery Partner');
            $('#delivery_partner_label_arabic').val('');
            $('#rounding_label').val('Rounding');
            $('#rounding_label_arabic').val('');
            $('#total_payable_label').val('Total Payable');
            $('#total_payable_label_arabic').val('');
            $('#previous_balance_label').val('Previous Balance');
            $('#previous_balance_label_arabic').val('');
            $('#paid_amount_label').val('Paid Amount');
            $('#paid_amount_label_arabic').val('');
            $('#due_amount_label').val('Due Amount');
            $('#due_amount_label_arabic').val('');
            $('#due_receive_label').val('Due Receive');
            $('#due_receive_label_arabic').val('');
            $('#advance_receive_label').val('Advance Receive');
            $('#advance_receive_label_arabic').val('');
            $('#given_amount_label').val('Given Amount');
            $('#given_amount_label_arabic').val('');
            $('#change_amount_label').val('Change Amount');
            $('#change_amount_label_arabic').val('');
            $('#servicing_charge_label').val('Servicing Charge');
            $('#servicing_charge_label_arabic').val('');
            $('#show_payment_method').val('Yes').trigger('change');
            $('#payment_method_label').val('Payment Method');
            $('#payment_method_label_arabic').val('');
            $('#show_hsn_code').val('No').trigger('change');
            
                showSuccessNotification('All heading and label fields have been reset to default values');
            }
        });
    });

    // Website Preview Button - Only show if website exists
    $('#website').on('input', function() {
        let websiteUrl = $(this).val();
        if (websiteUrl && websiteUrl.trim() !== '') {
            // Ensure URL has protocol
            let url = websiteUrl.trim();
            if (!url.match(/^https?:\/\//i)) {
                url = 'https://' + url;
            }
            $('.preview-website').show().attr('href', url);
        } else {
            $('.preview-website').hide();
        }
    });

    // Initialize website preview button visibility
    let initialWebsite = $('#website').val();
    if (initialWebsite && initialWebsite.trim() !== '') {
        let url = initialWebsite.trim();
        if (!url.match(/^https?:\/\//i)) {
            url = 'https://' + url;
        }
        $('.preview-website').attr('href', url).show();
    } else {
        $('.preview-website').hide();
    }

    // Email Settings - Validate required fields and enable/disable Test Email button
    function validateEmailSettings() {
        const smtpType = $('#smtp_type').val();
        const smtpEnableStatus = $('#smtp_enable_status').val();
        
        // Don't validate if SMTP is disabled or type is None
        if (smtpEnableStatus !== '1' || smtpType === 'None') {
            $('#testEmailBtn').prop('disabled', true);
            if ($('#testEmailBtn').hasClass('btn-primary')) {
                $('#testEmailBtn').removeClass('btn-primary').addClass('btn-secondary');
            }
            return;
        }

        const requiredFields = {
            'smtp_type': smtpType,
            'smtp_enable_status': smtpEnableStatus,
            'host_name': $('#host_name').val(),
            'port_address': $('#port_address').val(),
            'encryption': $('#encryption').val(),
            'user_name': $('#user_name').val(),
            'password': $('#password').val(),
            'from_name': $('#from_name').val(),
            'from_email': $('#from_email').val()
        };

        // Check if Sendinblue is selected, then API key is also required
        if (smtpType === 'Sendinblue') {
            requiredFields['api_key'] = $('#api_key').val();
        }

        // Check if all required fields are filled
        let isValid = true;
        for (let field in requiredFields) {
            if (!requiredFields[field] || requiredFields[field].trim() === '') {
                isValid = false;
                break;
            }
        }

        // Enable/disable Test Email button
        const testEmailBtn = $('#testEmailBtn');
        if (isValid) {
            testEmailBtn.prop('disabled', false);
            if (testEmailBtn.hasClass('btn-secondary')) {
                testEmailBtn.removeClass('btn-secondary').addClass('btn-primary');
            }
        } else {
            testEmailBtn.prop('disabled', true);
            if (testEmailBtn.hasClass('btn-primary')) {
                testEmailBtn.removeClass('btn-primary').addClass('btn-secondary');
            }
        }
    } 

    // Validate email settings on input change
    $('#smtp_type, #smtp_enable_status, #host_name, #port_address, #encryption, #user_name, #password, #from_name, #from_email, #api_key').on('change input', function() {
        validateEmailSettings();
    });

    // Check SMTP type change to show/hide API key field and validate
    $('#smtp_type').on('change', function() {
        if ($(this).val() === 'Sendinblue') {
            $('.sendinblue-api-field').show();
        } else {
            $('.sendinblue-api-field').hide();
        }
        validateEmailSettings();
    });

    // Initialize validation on page load
    validateEmailSettings();

    // Test Email Offcanvas Handler
    $(document).on('click', '.test-email-btn', function(e) {
        e.preventDefault();
        if (!$(this).prop('disabled')) {
            $('#testEmailOffcanvas').offcanvas('show');
        }
    });

    // Send Test Email
    $(document).on('click', '#sendTestEmailBtn', function() {
        const to = $('#test_email_to').val();
        const subject = $('#test_email_subject').val();
        const message = $('#test_email_message').val();
        
        if (!to || !subject || !message) {
            showErrorNotification('Please fill in all fields');
            return;
        }

        // Get form data to send credentials
        const formData = $('#email_setting_form').serialize();
        
        $.ajax({
            type: "POST",
            url: base_url + "/test-email",
            data: {
                ...parseFormData(formData),
                to: to,
                subject: subject,
                message: message
            },
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    showSuccessNotification(response.message || 'Test email sent successfully');
                    $('#testEmailOffcanvas').offcanvas('hide');
                    $('#test_email_to').val('');
                    $('#test_email_subject').val('Test Email');
                    $('#test_email_message').val('This is a test email to verify email configuration.');
                } else {
                    showErrorNotification(response.message || 'Failed to send test email');
                }
            },
            error: function (xhr) {
                showErrorNotification(xhr.responseJSON?.message || 'Failed to send test email');
            }
        });
    });

    // SMS Settings - Validate required fields and enable/disable Test SMS button
    function validateSMSSettings() {
        const smsProvider = $('#sms_service_provider').val();
        const smsEnableStatus = $('#sms_enable_status').val();
        
        // Don't validate if SMS is disabled or provider is None
        if (smsEnableStatus !== '1' || smsProvider === 'None') {
            $('#testSMSBtn').prop('disabled', true);
            if ($('#testSMSBtn').hasClass('btn-primary')) {
                $('#testSMSBtn').removeClass('btn-primary').addClass('btn-secondary');
            }
            return;
        }

        const requiredFields = {
            'sms_service_provider': smsProvider,
            'sms_enable_status': smsEnableStatus
        };

        // Add provider-specific required fields
        if (smsProvider === '1') { // Twilio
            requiredFields['twilio_sid'] = $('#twilio_sid').val();
            requiredFields['twilio_token'] = $('#twilio_token').val();
            requiredFields['twilio_number'] = $('#twilio_number').val();
        } else if (smsProvider === '2') { // Mobishastra
            requiredFields['mobishastra_profile_id'] = $('#mobishastra_profile_id').val();
            requiredFields['mobishastra_password'] = $('#mobishastra_password').val();
            requiredFields['mobishastra_sender_id'] = $('#mobishastra_sender_id').val();
            requiredFields['mobishastra_country_code'] = $('#mobishastra_country_code').val();
        } else if (smsProvider === '3') { // MiMSMS
            requiredFields['mim_sms_api_key'] = $('#mim_sms_api_key').val();
            requiredFields['mim_sms_sender_id'] = $('#mim_sms_sender_id').val();
            requiredFields['mim_sms_username'] = $('#mim_sms_username').val();
        } else if (smsProvider === '4') { // Text Local
            requiredFields['text_local_profile_id'] = $('#text_local_profile_id').val();
            requiredFields['text_local_api_key'] = $('#text_local_api_key').val();
            requiredFields['text_local_sender_id'] = $('#text_local_sender_id').val();
        }

        // Check if all required fields are filled
        let isValid = true;
        for (let field in requiredFields) {
            if (!requiredFields[field] || requiredFields[field].trim() === '' || requiredFields[field] === 'None') {
                isValid = false;
                break;
            }
        }

        // Enable/disable Test SMS button
        const testSMSBtn = $('#testSMSBtn');
        if (isValid) {
            testSMSBtn.prop('disabled', false);
            if (testSMSBtn.hasClass('btn-secondary')) {
                testSMSBtn.removeClass('btn-secondary').addClass('btn-primary');
            }
        } else {
            testSMSBtn.prop('disabled', true);
            if (testSMSBtn.hasClass('btn-primary')) {
                testSMSBtn.removeClass('btn-primary').addClass('btn-secondary');
            }
        }
    }

    // SMS Service Provider change handler (from settings.js)
    $(document).on('change', '#sms_service_provider', function() {
        validateSMSSettings();
    });

    // Validate SMS settings on input change
    $('#sms_service_provider, #sms_enable_status, #twilio_sid, #twilio_token, #twilio_number, #mobishastra_profile_id, #mobishastra_password, #mobishastra_sender_id, #mobishastra_country_code, #mim_sms_api_key, #mim_sms_sender_id, #mim_sms_username, #text_local_profile_id, #text_local_api_key, #text_local_sender_id').on('change input', function() {
        validateSMSSettings();
    });

    // Initialize SMS validation on page load
    validateSMSSettings();

    // Test SMS Handler
    $(document).on('click', '.test-sms-btn', function(e) {
        e.preventDefault();
        if (!$(this).prop('disabled')) {
            $('#testSMSOffcanvas').offcanvas('show');
        }
    });

    // Send Test SMS
    $(document).on('click', '#sendTestSMSBtn', function() {
        const to = $('#test_sms_number').val();
        const message = $('#test_sms_message').val();
        
        if (!to || !message) {
            showErrorNotification('Please fill in all fields');
            return;
        }

        const formData = $('#sms_setting_form').serialize();
        
        $.ajax({
            type: "POST",
            url: base_url + "/test-sms",
            data: {
                ...parseFormData(formData),
                to: to,
                message: message
            },
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    showSuccessNotification(response.message || 'Test SMS sent successfully');
                    $('#testSMSOffcanvas').offcanvas('hide');
                    $('#test_sms_number').val('');
                    $('#test_sms_message').val('This is a test SMS to verify SMS configuration.');
                } else {
                    showErrorNotification(response.message || 'Failed to send test SMS');
                }
            },
            error: function (xhr) {
                showErrorNotification(xhr.responseJSON?.message || 'Failed to send test SMS');
            }
        });
    });

    // WhatsApp Settings - Validate required fields and enable/disable Test WhatsApp button
    function validateWhatsappSettings() {
        const whatsappProvider = $('#whatsapp_provider').val();
        const requiredFields = {
            'whatsapp_invoice_enable_status': $('#whatsapp_invoice_enable_status').val(),
            'whatsapp_provider': whatsappProvider
        };

        // Add provider-specific required fields
        if (whatsappProvider === 'RC Soft') {
            requiredFields['whatsapp_app_key'] = $('#whatsapp_app_key').val();
            requiredFields['whatsapp_authkey'] = $('#whatsapp_authkey').val();
        } else if (whatsappProvider === 'Twilio') {
            requiredFields['whatsapp_account_sid'] = $('#whatsapp_account_sid').val();
            requiredFields['whatsapp_auth_token'] = $('#whatsapp_auth_token').val();
            requiredFields['whatsapp_from_number'] = $('#whatsapp_from_number').val();
        }

        // Check if all required fields are filled
        let isValid = true;
        for (let field in requiredFields) {
            if (!requiredFields[field] || requiredFields[field].trim() === '' || requiredFields[field] === 'None') {
                isValid = false;
                break;
            }
        }

        // Enable/disable Test WhatsApp button
        const testWhatsappBtn = $('#testWhatsappBtn');
        if (isValid && $('#whatsapp_invoice_enable_status').val() === 'Enable') {
            testWhatsappBtn.prop('disabled', false);
            if (testWhatsappBtn.hasClass('btn-secondary')) {
                testWhatsappBtn.removeClass('btn-secondary').addClass('btn-primary');
            }
        } else {
            testWhatsappBtn.prop('disabled', true);
            if (testWhatsappBtn.hasClass('btn-primary')) {
                testWhatsappBtn.removeClass('btn-primary').addClass('btn-secondary');
            }
        }
    }

    // WhatsApp enable status change
    $(document).on('change', '#whatsapp_invoice_enable_status', function() {
        const status = $(this).val();
        if (status === 'Enable') {
            $('.whatsapp_service_wrap').show();
        } else {
            $('.whatsapp_service_wrap').hide();
        }
        validateWhatsappSettings();
    });

    // WhatsApp type change
    $(document).on('change', '#whatsapp_provider', function() {
        const whatsappProvider = $(this).val();
        if (whatsappProvider === 'None') {
            $('.whatsapp-rcsoft-fields, .whatsapp-twilio-fields').hide();
        } else if (whatsappProvider === 'RC Soft') {
            $('.whatsapp-rcsoft-fields').show();
            $('.whatsapp-twilio-fields').hide();
        } else if (whatsappProvider === 'Twilio') {
            $('.whatsapp-rcsoft-fields').hide();
            $('.whatsapp-twilio-fields').show();
        }
        validateWhatsappSettings();
    });

    // Validate WhatsApp settings on input change
    $('#whatsapp_provider, #whatsapp_app_key, #whatsapp_authkey, #whatsapp_account_sid, #whatsapp_auth_token, #whatsapp_from_number').on('change input', function() {
        validateWhatsappSettings();
    });

    // Initialize WhatsApp validation and visibility
    const whatsappEnableStatus = $('#whatsapp_invoice_enable_status').val();
    if (whatsappEnableStatus === 'Enable') {
        $('.whatsapp_service_wrap').show();
    } else {
        $('.whatsapp_service_wrap').hide();
    }
    const whatsappProvider = $('#whatsapp_provider').val();
    if (whatsappProvider === 'RC Soft') {
        $('.whatsapp-rcsoft-fields').show();
        $('.whatsapp-twilio-fields').hide();
    } else if (whatsappProvider === 'Twilio') {
        $('.whatsapp-rcsoft-fields').hide();
        $('.whatsapp-twilio-fields').show();
    } else {
        $('.whatsapp-rcsoft-fields, .whatsapp-twilio-fields').hide();
    }
    validateWhatsappSettings();

    // Test WhatsApp Handler
    $(document).on('click', '.test-whatsapp-btn', function(e) {
        e.preventDefault();
        if (!$(this).prop('disabled')) {
            $('#testWhatsappOffcanvas').offcanvas('show');
        }
    });

    // Send Test WhatsApp
    $(document).on('click', '#sendTestWhatsappBtn', function() {
        const to = $('#test_whatsapp_number').val();
        const message = $('#test_whatsapp_message').val();
        
        if (!to || !message) {
            showErrorNotification('Please fill in all fields');
            return;
        }

        const formData = $('#whatsapp_setting_form').serialize();
        
        $.ajax({
            type: "POST",
            url: base_url + "/test-whatsapp",
            data: {
                ...parseFormData(formData),
                to: to,
                message: message
            },
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status === 'success') {
                    showSuccessNotification(response.message || 'Test WhatsApp sent successfully');
                    $('#testWhatsappOffcanvas').offcanvas('hide');
                    $('#test_whatsapp_number').val('');
                    $('#test_whatsapp_message').val('This is a test WhatsApp message to verify WhatsApp configuration.');
                } else {
                    showErrorNotification(response.message || 'Failed to send test WhatsApp');
                }
            },
            error: function (xhr) {
                showErrorNotification(xhr.responseJSON?.message || 'Failed to send test WhatsApp');
            }
        });
    });

    // Helper function to parse form data
    function parseFormData(formDataString) {
        const params = new URLSearchParams(formDataString);
        const data = {};
        for (const [key, value] of params.entries()) {
            data[key] = value;
        }
        return data;
    }
    
});