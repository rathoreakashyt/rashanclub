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

  // Get route path - use Ziggy if available, otherwise use direct path
  const route_path = route('user.sales', {}, false, Ziggy);

  const dt_basic_table = $('.datatables-basic');
  if (dt_basic_table.length) {
    new DataTable(dt_basic_table, {
      processing: true,
      serverSide: true,
      ajax: {
        url: base_url + route_path,
        type: 'GET',
        error: function(xhr, error, thrown) {
          console.error('DataTable error:', error);
          if (typeof showErrorNotification !== 'undefined') {
            showErrorNotification('Failed to load sales data');
          }
        }
      },
      columns: [
        { data: 'id' },
        { data: 'sale_no' },
        { 
          data: 'customer_name',
          render: function(data, type, row) {
            return data || 'Walk-in Customer';
          }
        },
        { data: 'total_items' },
        { 
          data: 'total_payable',
          render: function(data, type, row) {
            return data || '0.00';
          }
        },
        { 
          data: 'paid_amount',
          render: function(data, type, row) {
            return data || '0.00';
          }
        },
        { data: 'sale_date' }
      ],
      dom:
      '<"card-header border-bottom p-3 d-flex align-items-center"' +
        '<"head-label me-auto">' +
        '<"dt-action-buttons text-end"B>' +
      '>' +
      '<"row"' +
        '<"col-sm-12 col-md-6"l>' +
        '<"col-sm-12 col-md-6"f>' +
      '>' +
      '<"row"<"col-sm-12"tr>>' +
      '<"row align-items-center"' +
        '<"col-sm-12 col-md-5 "i>' +
        '<"col-sm-12 col-md-7 d-flex justify-content-end"p>' +
      '>',
      buttons: [
        {
          extend: 'collection',
          className: 'btn btn-primary dropdown-toggle me-2',
          text: '<i class="ti tabler-file-export me-sm-1"></i> <span class="d-none d-sm-inline-block">Export</span>',
          buttons: [
            {
              extend: 'print',
              text: '<i class="ti tabler-printer me-1" ></i>' + (language_key.Print || 'Print'),
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
            },
            {
              extend: 'excel',
              text: '<i class="ti tabler-file-spreadsheet me-1"></i>' + (language_key.Excel || 'Excel'),
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
            },
            {
              extend: 'pdf',
              text: '<i class="ti tabler-file-description me-1"></i>' + (language_key.Pdf || 'PDF'),
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
            }
          ]
        }
      ],
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      pageLength: 10,
      order: [[0, 'desc']]
    });
    $('div.head-label').html(
      '<h5 class="mb-0">' + (language_key.Latest_Sale || 'Latest Sale') + '</h5>'
    );
  }



  // Load this week statistics
  function loadThisWeekStatistics() {
    // Get route path - use Ziggy if available, otherwise use direct path
    let route_path;
    try {
        if (typeof route !== 'undefined' && typeof Ziggy !== 'undefined') {
            route_path = route('this.week.statistics', {}, false, Ziggy);
        } else {
            route_path = '/this-week-statistics';
        }
    } catch (e) {
        console.warn('Route helper not available, using direct path');
        route_path = '/this-week-statistics';
    }
    
    $.ajax({
        url: base_url + route_path,
        type: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        success: function(response) {
            if (response.status === 'success' && response.data) {
                const data = response.data;
                
                // Update Sales Card
                $('#this-week-sales-count').text(data.sales.count || 0);
                const salesChange = data.sales.change || 0;
                const salesSign = salesChange >= 0 ? '+' : '';
                $('#this-week-sales-change').text(salesSign + salesChange + '%');
                $('#this-week-sales-change').removeClass('text-success text-danger');
                $('#this-week-sales-change').addClass(salesChange >= 0 ? 'text-success' : 'text-danger');
                
                // Update Purchases Card
                $('#this-week-purchases-count').text(data.purchases.count || 0);
                const purchasesChange = data.purchases.change || 0;
                const purchasesSign = purchasesChange >= 0 ? '+' : '';
                $('#this-week-purchases-change').text(purchasesSign + purchasesChange + '%');
                $('#this-week-purchases-change').removeClass('text-success text-danger');
                $('#this-week-purchases-change').addClass(purchasesChange >= 0 ? 'text-success' : 'text-danger');
                
                // Update Customer Receive Card
                $('#this-week-customer-receive-count').text(data.customer_receive.count || 0);
                const customerReceiveChange = data.customer_receive.change || 0;
                const customerReceiveSign = customerReceiveChange >= 0 ? '+' : '';
                $('#this-week-customer-receive-change').text(customerReceiveSign + customerReceiveChange + '%');
                $('#this-week-customer-receive-change').removeClass('text-success text-danger');
                $('#this-week-customer-receive-change').addClass(customerReceiveChange >= 0 ? 'text-success' : 'text-danger');
                
                // Update Supplier Payment Card
                $('#this-week-supplier-payment-count').text(data.supplier_payment.count || 0);
                const supplierPaymentChange = data.supplier_payment.change || 0;
                const supplierPaymentSign = supplierPaymentChange >= 0 ? '+' : '';
                $('#this-week-supplier-payment-change').text(supplierPaymentSign + supplierPaymentChange + '%');
                $('#this-week-supplier-payment-change').removeClass('text-success text-danger');
                $('#this-week-supplier-payment-change').addClass(supplierPaymentChange >= 0 ? 'text-success' : 'text-danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading statistics:', error);
            // Keep default values (0) if error occurs
        }
    });
  }

  // Load statistics on page load
  loadThisWeekStatistics();


});
