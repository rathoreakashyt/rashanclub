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
    if ($('#supplier_id').length && !$('#supplier_id').hasClass('select2-hidden-accessible')) {
        $('#supplier_id').select2({
            dropdownParent: $('#supplier_id').closest('.card-body'),
            width: '100%'
        });
    }

    // Load supplier balance on page load if supplier is already selected (edit mode)
    const selectedSupplierId = $('#supplier_id').val();
    if (selectedSupplierId) {
        loadSupplierBalance(selectedSupplierId);
    }

    // Handle supplier selection change
    $(document).on('change', '#supplier_id', function() {
        const supplierId = $(this).val();
        loadSupplierBalance(supplierId);
    });

    function loadSupplierBalance(supplierId) {
        if (!supplierId) {
            $('#supplier_balance_display').hide();
            return;
        }
    
        const baseUrl = $('#base_url').val() || '';
        const routeUrl = baseUrl + '/supplier/get-balance-by-id/' + supplierId;
        
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
                       $('#supplier_balance_display').hide();
                       return;
                    }
                    
                    // Format amount
                    const formattedAmount = balanceAmount.toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    
                    $('#supplier_balance_amount').text(formattedAmount);
                    $('#supplier_balance_type').text('(' + balanceType + ')');
                    
                    // Set badge color based on type
                    $('#supplier_balance_type').removeClass('bg-label-success bg-label-danger bg-label-warning');
                    if (balanceType === 'Debit') {
                        $('#supplier_balance_type').addClass('bg-label-success');
                    } else {
                        $('#supplier_balance_type').addClass('bg-label-danger');
                    }
                    
                    $('#supplier_balance_display').show();
                } else {
                    $('#supplier_balance_display').hide();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading supplier balance:', error);
                $('#supplier_balance_display').hide();
            }
        });
    }

});
