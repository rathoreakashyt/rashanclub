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
    
    

    // Initialize Select2 if not already initialized
    if ($('#customer_id').length && !$('#customer_id').hasClass('select2-hidden-accessible')) {
        $('#customer_id').select2({
            dropdownParent: $('#customer_id').closest('.card-body'),
            width: '100%'
        });
    }

    // Load customer balance on page load if customer is already selected (edit mode)
    const selectedCustomerId = $('#customer_id').val();
    if (selectedCustomerId) {
        loadCustomerBalance(selectedCustomerId);
    }

    // Handle customer selection change
    $(document).on('change', '#customer_id', function() {
        const customerId = $(this).val();
        loadCustomerBalance(customerId);
    });

    function loadCustomerBalance(customerId) {
        if (!customerId) {
            $('#customer_balance_display').hide();
            return;
        }
    
        const baseUrl = $('#base_url').val() || '';
        
        // Get customer encrypted ID from the selected option's data attribute or fetch it
        // For now, we'll use a route that accepts regular customer ID
        let routeUrl;
        try {
            // Try to get encrypted ID from customer data if available
            // Otherwise, we'll need to create a route that accepts regular ID
            // For now, let's use a direct API call with customer ID
            routeUrl = baseUrl + '/customer/get-balance-by-id/' + customerId;
        } catch (e) {
            routeUrl = baseUrl + '/customer/get-balance-by-id/' + customerId;
        }
        
        $.ajax({
            url: routeUrl,
            type: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            success: function(response) {
                if (response.success && response.data) {
                    const balance = parseFloat(response.data.balance || 0);
                    const balanceType = balance >= 0 ? 'Debit' : 'Credit';
                    const balanceAmount = Math.abs(balance);
    
                    if(balance == 0) {
                       $('#customer_balance_display').hide();
                       return;
                    }
                    
                    // Format amount
                    const formattedAmount = balanceAmount.toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    
                    $('#customer_balance_amount').text(formattedAmount);
                    $('#customer_balance_type').text('(' + balanceType + ')');
                    
                    // Set badge color based on type
                    $('#customer_balance_type').removeClass('bg-label-success bg-label-danger bg-label-warning');
                    if (balanceType === 'Debit') {
                        $('#customer_balance_type').addClass('bg-label-success');
                    } else {
                        $('#customer_balance_type').addClass('bg-label-danger');
                    }
                    
                    $('#customer_balance_display').show();
                } else {
                    $('#customer_balance_display').hide();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading customer balance:', error);
                $('#customer_balance_display').hide();
            }
        });
    }

});
