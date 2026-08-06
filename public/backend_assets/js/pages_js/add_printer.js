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
    
    // Function to toggle fields based on invoice print selection
    function toggleFields() {
        var invoicePrint = $('#invoice_print').val();
        if(invoicePrint === 'live_server_print') {
            $('.live-server-fields').removeClass('d-none');
            toggleNetworkFields(); // Check type field as well
        } else {
            $('.live-server-fields, .network-fields').addClass('d-none');
        }
    }

    // Function to toggle network-specific fields
    function toggleNetworkFields() {
        var type = $('#type').val();
        if(type === 'network') {
            $('.network-fields').removeClass('d-none');
        } else {
            $('.network-fields').addClass('d-none');
        }
    }

    // Initial check
    toggleFields();

    // Listen for changes
    $('#invoice_print').change(toggleFields);
    $('#type').change(toggleNetworkFields);
});