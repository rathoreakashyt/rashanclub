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

  // Clear validation errors (same pattern as add_item.js)
  function clearValidationErrors() {
      $('#salaryForm .is-invalid').removeClass('is-invalid');
      $('#salaryForm .input-group.is-invalid').removeClass('is-invalid');
      $('#salaryForm .select2-container.is-invalid').removeClass('is-invalid');
      $('#paymentSectionError').hide().text('');
      $('.payment-error').hide().text('');
      $('.js-month-error').remove();
      $('#payment_method_select_error').hide().text('');
  }

  // Scroll to first validation error (same pattern as add_item.js)
  function scrollToFirstError() {
      setTimeout(function() {
          let firstError = $('#salaryForm .is-invalid').first();
          if (firstError.length > 0) {
              let scrollTarget = firstError.hasClass('select2-hidden-accessible') ? firstError.next('.select2-container') : firstError;
              if (scrollTarget.length > 0) {
                  $('html, body').animate({ scrollTop: scrollTarget.offset().top - 100 }, 500);
              }
          } else if ($('#paymentSectionError').text()) {
              $('html, body').animate({ scrollTop: $('#paymentSectionError').offset().top - 100 }, 500);
          }
      }, 100);
  }

  // Initialize date picker
  $('.datePicker').flatpickr({
      dateFormat: "Y-m-d",
      maxDate: "today"
  });

  // Read config from window so it's always available (avoids double cards / 0 employees from scope issues)
  const config = typeof window.salaryFormConfig !== 'undefined' ? window.salaryFormConfig : {};
  let employeeIndex = config.salaryItemsCount !== undefined ? config.salaryItemsCount : 0;
  let paymentIndex = config.salaryPaymentsCount !== undefined ? config.salaryPaymentsCount : 0;
  const employees = Array.isArray(config.employeesData) ? config.employeesData : [];
  const paymentMethods = Array.isArray(config.paymentMethodsData) ? config.paymentMethodsData : [];
  const usedPaymentMethodIds = new Set(); // Track used payment method IDs

  // Initialize used payment method IDs from existing payments
  $('.payment-item').each(function() {
      const paymentMethodId = $(this).find('input[name*="[payment_method_id]"]').val();
      if (paymentMethodId) {
          usedPaymentMethodIds.add(parseInt(paymentMethodId));
      }
  });

  // Auto-add employee cards only when: new salary, we have employees, and container has no cards (initial load).
  // When validation failed, blade already rendered cards from old() — do not add again (prevents double cards).
  const existingEmployeeCards = $('#employeeItems .employee-item').length;
  const shouldAutoAddEmployees = config.isNewSalary === true && employees.length > 0 && existingEmployeeCards === 0;
  if (shouldAutoAddEmployees) {
      employees.forEach(function(employee) {
          addEmployeeItem(employee);
      });
  }

  // Add Payment Method
  $('#payment_method_select').on('change', function() {
      const paymentMethodId = $(this).val();
      if (paymentMethodId) {
          const selectedOption = $(this).find('option:selected');
          const paymentMethodName = selectedOption.data('name');
          const paymentMethodIdInt = parseInt(paymentMethodId);
          
          // Check if payment method is already added
          if (usedPaymentMethodIds.has(paymentMethodIdInt)) {
              if (typeof showErrorNotification === 'function') {
                  showErrorNotification('This payment method already added');
              } else if (typeof Swal !== 'undefined') {
                  Swal.fire({
                      icon: 'warning',
                      title: 'Warning',
                      text: 'This payment method already added',
                      timer: 2000,
                      showConfirmButton: false
                  });
              } else {
                  alert('This payment method already added');
              }
              $(this).val('').trigger('change');
              return;
          }
          
          addPaymentItem(paymentMethodIdInt, paymentMethodName);
          $(this).val('').trigger('change');
      }
  });

  function addEmployeeItem(employeeData = null) {
      const employeeId = employeeData ? employeeData.id : '';
      const employeeName = employeeData ? employeeData.name : '';
      const salaryAmount = employeeData ? (employeeData.salary || 0) : 0;
      
      const itemHtml = `
          <div class="card mb-3 employee-item" data-index="${employeeIndex}">
              <div class="card-header d-flex justify-content-between align-items-center">
                  <h6 class="mb-0">${employeeName || 'Employee ' + (employeeIndex + 1)}</h6>
                  <button type="button" class="btn remove-employee">
                      <i class="icon-base ti tabler-trash text-danger"></i>
                  </button>
              </div>
              <div class="card-body">
                  <input type="hidden" name="items[${employeeIndex}][employee_id]" class="employee-id" value="${employeeId}">
                  <div class="row">
                      <div class="col-6 col-md-3 mb-2">
                          <label class="form-label">${language_key['Salary Amount']}</label>
                          <input type="text" class="form-control number-input salary-amount" name="items[${employeeIndex}][salary_amount]" value="${salaryAmount}" step="0.01" min="0">
                      </div>
                      <div class="col-6 col-md-3 mb-2">
                          <label class="form-label">${language_key['Overtime Rate']}</label>
                          <input type="text" class="form-control number-input overtime-rate" name="items[${employeeIndex}][overtime_rate]" value="0">
                      </div>
                      <div class="col-6 col-md-3 mb-2">
                          <label class="form-label">${language_key['Overtime Hour']}</label>
                          <input type="text" class="form-control number-input overtime-hour" name="items[${employeeIndex}][overtime_hour]" value="0">
                      </div>
                      <div class="col-6 col-md-3 mb-2">
                          <label class="form-label">${language_key['Additional Amount']}</label>
                          <input type="text" class="form-control number-input additional-amount" name="items[${employeeIndex}][additional_amount]" value="0">
                      </div>
                      <div class="col-6 col-md-3 mb-2">
                          <label class="form-label">${language_key['Deduction Amount']}</label>
                          <input type="text" class="form-control number-input deduction-amount" name="items[${employeeIndex}][deduction_amount]" value="0">
                      </div>
                      <div class="col-6 col-md-3 mb-2">
                          <label class="form-label">${language_key['Absent Days']}</label>
                          <input type="text" class="form-control number-input absent-day" name="items[${employeeIndex}][absent_day]" value="0">
                      </div>
                      <div class="col-6 col-md-3 mb-2">
                          <label class="form-label">${language_key['Absent Amount']}</label>
                          <input type="text" class="form-control number-input absent-day-amount" name="items[${employeeIndex}][absent_day_amount]" value="0">
                      </div>
                      <div class="col-6 col-md-3 mb-2">
                          <label class="form-label">${language_key['Advance Taken']}</label>
                          <input type="text" class="form-control number-input advance-taken" name="items[${employeeIndex}][advance_taken]" value="0" readonly>
                      </div>
                      <div class="col-6 col-md-6">
                          <label class="form-label">${language_key['Note']}</label>
                          <input type="text" class="form-control" name="items[${employeeIndex}][note]" placeholder="${language_key['Enter Note']}">
                      </div>
                      <div class="col-12 col-md-3">
                          <label class="form-label"><strong>${language_key['Net Salary']}</strong></label>
                          <input type="text" class="form-control number-input net-salary" name="items[${employeeIndex}][net_salary]" value="0" readonly>
                      </div>
                  </div>
              </div>
          </div>
      `;
      
      $('#employeeItems').append(itemHtml);
      
      // Bind calculation events
      $(`#employeeItems .employee-item[data-index="${employeeIndex}"]`).find('.salary-amount, .overtime-rate, .overtime-hour, .additional-amount, .deduction-amount, .absent-day, .absent-day-amount, .advance-taken').on('input', function() {
          calculateNetSalary($(this).closest('.employee-item'));
      });
      
      // Remove employee
      $(`#employeeItems .employee-item[data-index="${employeeIndex}"]`).find('.remove-employee').on('click', function() {
          $(this).closest('.employee-item').remove();
          updateTotalAmount();
      });
      
      // Calculate initial net salary
      if (employeeData) {
          calculateNetSalary($(`#employeeItems .employee-item[data-index="${employeeIndex}"]`));
      }
      
      employeeIndex++;
      fetchAndApplyAdvanceTaken();
  }

    function addPaymentItem(paymentMethodId, paymentMethodName) {
        // Add to used payment methods set
        usedPaymentMethodIds.add(paymentMethodId);
        
        const paymentHtml = `
            <div class="row mb-2 payment-item" data-index="${paymentIndex}" data-payment-method-id="${paymentMethodId}">
                <div class="col-12 col-md-6 col-lg-4 ms-auto">
                    <div class="input-group">
                        <span class="input-group-text">${paymentMethodName}</span>
                        <input type="hidden" name="payments[${paymentIndex}][payment_method_id]" value="${paymentMethodId}">
                        <input type="text" class="form-control number-input payment-amount" name="payments[${paymentIndex}][amount]" placeholder="${paymentMethodName}">
                        <span class="input-group-text remove-payment cursor-pointer">
                            <i class="icon-base ti tabler-trash text-danger"></i>
                        </span>
                    </div>
                    <div class="invalid-feedback payment-error" style="display: none;"></div>
                </div>
            </div>
        `;
        
        $('#paymentItems').append(paymentHtml);
        
        // Bind validation on payment amount change
        $(`#paymentItems .payment-item[data-index="${paymentIndex}"]`).find('.payment-amount').on('input', function() {
            validatePaymentAmounts();
        });
        
        // Remove payment
        $(`#paymentItems .payment-item[data-index="${paymentIndex}"]`).find('.remove-payment').on('click', function() {
            const paymentMethodIdToRemove = parseInt($(this).closest('.payment-item').data('payment-method-id'));
            usedPaymentMethodIds.delete(paymentMethodIdToRemove);
            $(this).closest('.payment-item').remove();
            validatePaymentAmounts();
        });
        
        paymentIndex++;
    }

  // Validate required fields (e.g. month) — show error below field like add_item.js
  function validateRequiredFields() {
      let hasError = false;
      const monthVal = $('#month').val();
      if (!monthVal || monthVal === '' || monthVal === null) {
          $('#month').addClass('is-invalid');
          if ($('#month').hasClass('select2-hidden-accessible') && $('#month').next('.select2-container').length) {
              $('#month').next('.select2-container').addClass('is-invalid');
          }
          const monthMsg = (typeof language_key !== 'undefined' && language_key['The Month field is required']) ? language_key['The Month field is required'] : 'The Month field is required.';
          const $monthWrapper = $('#month').closest('.mb-5');
          if ($monthWrapper.find('.js-month-error').length === 0) {
              $monthWrapper.append('<div class="invalid-feedback d-block js-month-error">' + monthMsg + '</div>');
          }
          hasError = true;
      } else {
          $('#month').removeClass('is-invalid');
          $('#month').next('.select2-container').removeClass('is-invalid');
          $('#month').closest('.mb-5').find('.js-month-error').remove();
      }
      return !hasError;
  }

  function validatePaymentAmounts() {
      // Clear only payment-related errors so month error from validateRequiredFields is kept
      $('#paymentSectionError').hide().text('');
      $('.payment-error').hide().text('');
      $('.payment-amount').removeClass('is-invalid');
      $('.input-group').removeClass('is-invalid');
      $('#payment_method_select').removeClass('is-invalid');
      $('#payment_method_select').next('.select2-container').removeClass('is-invalid');
      $('#payment_method_select_error').hide().text('').removeClass('d-block');

      const $sectionError = $('#paymentSectionError');
      const $paymentItems = $('.payment-item');
      if (!$paymentItems.length) {
          const msg = (typeof language_key !== 'undefined' && language_key.Please_select_at_least_one_payment_method)
              ? language_key.Please_select_at_least_one_payment_method
              : 'Please select at least one payment method.';
          $('#payment_method_select').addClass('is-invalid');
          if ($('#payment_method_select').hasClass('select2-hidden-accessible') && $('#payment_method_select').next('.select2-container').length) {
              $('#payment_method_select').next('.select2-container').addClass('is-invalid');
          }
          $('#payment_method_select_error').text(msg).addClass('d-block').show();
          $sectionError.text(msg).show();
          return false;
      }
      $('#payment_method_select').removeClass('is-invalid');
      $('#payment_method_select').next('.select2-container').removeClass('is-invalid');
      $('#payment_method_select_error').hide().text('').removeClass('d-block');

      let totalPaymentAmount = 0;
      let hasZeroAmount = false;
      $('.payment-item').each(function() {
          const $amountInput = $(this).find('.payment-amount');
          const amount = parseFloat($amountInput.val());
          const numAmount = isNaN(amount) ? 0 : amount;
          totalPaymentAmount += numAmount;

          const $errorEl = $(this).find('.payment-error').first();
          if (numAmount <= 0) {
              hasZeroAmount = true;
              $amountInput.addClass('is-invalid');
              $(this).find('.input-group').addClass('is-invalid');
              $errorEl.text(typeof language_key !== 'undefined' && language_key.Payment_method_amount_cannot_be_zero
                  ? language_key.Payment_method_amount_cannot_be_zero
                  : 'Payment method amount cannot be zero.').show();
          } else {
              $errorEl.hide().text('');
          }
      });
      totalPaymentAmount = Math.round(totalPaymentAmount * 100) / 100;
      const totalAmount = Math.round((parseFloat($('#total_amount').val()) || 0) * 100) / 100;

      if (hasZeroAmount) {
          if (!$sectionError.text()) {
              $sectionError.text(typeof language_key !== 'undefined' && language_key.Payment_method_amount_cannot_be_zero
                  ? language_key.Payment_method_amount_cannot_be_zero
                  : 'Payment method amount cannot be zero.').show();
          }
          return false;
      }

      if (totalPaymentAmount > totalAmount) {
          const errorMessage = (typeof language_key !== 'undefined' && language_key.Total_payment_cannot_exceed_total_amount)
              ? language_key.Total_payment_cannot_exceed_total_amount.replace('{total}', totalPaymentAmount.toFixed(2)).replace('{salary}', totalAmount.toFixed(2))
              : 'Total payment amount (' + totalPaymentAmount.toFixed(2) + ') cannot exceed total salary amount (' + totalAmount.toFixed(2) + ').';
          $('.payment-amount').addClass('is-invalid');
          $sectionError.text(errorMessage).show();
          return false;
      }

      if (totalPaymentAmount < totalAmount) {
          const errorMessage = (typeof language_key !== 'undefined' && language_key.Total_payment_must_equal_total_salary_amount)
              ? language_key.Total_payment_must_equal_total_salary_amount
              : 'Total payment amount must equal total salary amount (Total: ' + totalAmount.toFixed(2) + ', Payment total: ' + totalPaymentAmount.toFixed(2) + ').';
          $('.payment-amount').addClass('is-invalid');
          $sectionError.text(errorMessage).show();
          return false;
      }

      return true;
  }

  function calculateNetSalary(itemElement) {
      const salaryAmount = parseFloat(itemElement.find('.salary-amount').val()) || 0;
      const overtimeRate = parseFloat(itemElement.find('.overtime-rate').val()) || 0;
      const overtimeHour = parseFloat(itemElement.find('.overtime-hour').val()) || 0;
      const additionalAmount = parseFloat(itemElement.find('.additional-amount').val()) || 0;
      const deductionAmount = parseFloat(itemElement.find('.deduction-amount').val()) || 0;
      const absentDay = parseInt(itemElement.find('.absent-day').val()) || 0;
      const absentDayAmount = parseFloat(itemElement.find('.absent-day-amount').val()) || 0;
      const advanceTaken = parseFloat(itemElement.find('.advance-taken').val()) || 0;

      const overtimeAmount = overtimeRate * overtimeHour;
      const absentDeduction = absentDay * absentDayAmount;

      const netSalary = salaryAmount + overtimeAmount + additionalAmount - deductionAmount - absentDeduction - advanceTaken;
      
      itemElement.find('.net-salary').val(Math.max(0, netSalary).toFixed(2));
      updateTotalAmount();
  }

  function updateTotalAmount() {
      let total = 0;
      $('.net-salary').each(function() {
          total += parseFloat($(this).val()) || 0;
      });
      $('#total_amount').val(total.toFixed(2));
  }

  // Fetch advance taken per employee for the selected month/year and update form
  function fetchAndApplyAdvanceTaken() {
      const url = config.advancesByMonthUrl;
      if (!url) return;
      const year = parseInt($('#year').val(), 10);
      const month = parseInt($('#month').val(), 10);
      if (!year || !month || month < 1 || month > 12) return;

      const params = new URLSearchParams({ year: year, month: month });
      fetch(url + '?' + params.toString(), {
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
      }).then(function(res) { return res.json(); }).then(function(data) {
          const advances = data.advances || {};
          $('.employee-item').each(function() {
              const empId = $(this).find('.employee-id').val();
              const amount = advances[empId] !== undefined ? parseFloat(advances[empId]) : 0;
              $(this).find('.advance-taken').val(isNaN(amount) ? 0 : amount.toFixed(2));
              calculateNetSalary($(this));
          });
          updateTotalAmount();
      }).catch(function() {});
  }

  // When month or year changes, refresh advance taken for all employees
  $('#month, #year').on('change', function() {
      fetchAndApplyAdvanceTaken();
  });

  // Initialize calculations for existing items
  $('.employee-item').each(function() {
      calculateNetSalary($(this));
      
      // Bind calculation events for existing items
      $(this).find('.salary-amount, .overtime-rate, .overtime-hour, .additional-amount, .deduction-amount, .absent-day, .absent-day-amount, .advance-taken').on('input', function() {
          calculateNetSalary($(this).closest('.employee-item'));
      });
      
      // Remove employee for existing items
      $(this).find('.remove-employee').on('click', function() {
          $(this).closest('.employee-item').remove();
          updateTotalAmount();
      });
  });

  // Load advance taken for selected month/year on create (edit form already has values from server)
  if (config.isNewSalary === true) {
      fetchAndApplyAdvanceTaken();
  }

  // Remove payment for existing items
  $('.payment-item').each(function() {
      $(this).find('.remove-payment').on('click', function() {
          const paymentMethodIdToRemove = parseInt($(this).closest('.payment-item').data('payment-method-id'));
          usedPaymentMethodIds.delete(paymentMethodIdToRemove);
          $(this).closest('.payment-item').remove();
          validatePaymentAmounts();
      });
      
      // Bind validation on payment amount change for existing items
      $(this).find('.payment-amount').on('input', function() {
          validatePaymentAmounts();
      });
  });
  
  // Form submission: validate month and payments, show errors below fields, scroll to first error
  $('#salaryForm').on('submit', function(e) {
      clearValidationErrors();
      const requiredOk = validateRequiredFields();
      const paymentOk = validatePaymentAmounts();
      if (!requiredOk || !paymentOk) {
          e.preventDefault();
          scrollToFirstError();
          return false;
      }
  });

  // On page load with server validation errors, scroll to first error (Month, Payment, etc.)
  const $firstInvalid = $('#salaryForm .is-invalid').first();
  if ($firstInvalid.length) {
      setTimeout(function() {
          let scrollTarget = $firstInvalid.hasClass('select2-hidden-accessible') ? $firstInvalid.next('.select2-container') : $firstInvalid;
          if (scrollTarget.length) {
              $('html, body').animate({ scrollTop: scrollTarget.offset().top - 100 }, 500);
          }
      }, 100);
  }
  if ($('#salaryForm .alert.alert-danger').length) {
      setTimeout(function() {
          $('html, body').animate({ scrollTop: $('#salaryForm .alert.alert-danger').first().offset().top - 100 }, 500);
      }, 100);
  }

  updateTotalAmount();
});
