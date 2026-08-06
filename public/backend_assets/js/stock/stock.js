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
  
  let op_precision = $('#op_precision').val() || 2;
  let currency_ = $('#currency_').val() || '';
  let APPLICATION_DEMO_TYPE = $('#APPLICATION_DEMO_TYPE').val() || '';
  // Get filter values from URL params
  const urlParams = new URLSearchParams(window.location.search);
  let item_code = urlParams.get('item_code') || '';
  let category_id_f = urlParams.get('category_id') || '';
  let brand_id = urlParams.get('brand_id') || '';
  let item_id_f = urlParams.get('item_id') || '';
  let generic_name = urlParams.get('generic_name') || '';
  let supplier_id = urlParams.get('supplier_id') || '';
  let price_type = urlParams.get('price_type') || 'last_three_purchase_avg';

  // Filter Toggle
  $('#toggleFilter').on('click', function() {
    $('#filterSection').slideToggle();
  });

  // Apply Filter
  $('#applyFilter').on('click', function() {
    item_code = $('#item_code_f').val() || '';
    category_id_f = $('#category_id_f').val() || '';
    brand_id = $('#brand_id_f').val() || '';
    item_id_f = $('#item_id_f').val() || '';
    generic_name = $('#generic_name_f').val() || '';
    supplier_id = $('#supplier_id_f').val() || '';
    price_type = $('#price_type_f').val() || 'last_three_purchase_avg';
    
    // Reload DataTable with new filters
    if ($.fn.DataTable.isDataTable('.datatables-basic')) {
      $('.datatables-basic').DataTable().ajax.reload();
    }
  });

  // Reset Filter
  $('#resetFilter').on('click', function() {
    $('#item_code_f').val('').trigger('change');
    $('#category_id_f').val('').trigger('change');
    $('#brand_id_f').val('').trigger('change');
    $('#item_id_f').val('').trigger('change');
    $('#generic_name_f').val('');
    $('#supplier_id_f').val('').trigger('change');
    $('#price_type_f').val('last_three_purchase_avg').trigger('change');
    
    item_code = '';
    category_id_f = '';
    brand_id = '';
    item_id_f = '';
    generic_name = '';
    supplier_id = '';
    price_type = 'last_three_purchase_avg';
    
    // Reload DataTable
    if ($.fn.DataTable.isDataTable('.datatables-basic')) {
      $('.datatables-basic').DataTable().ajax.reload();
    }
  });

  // Initialize DataTable for Stock
  const dt_basic_table = $('.datatables-basic');
  if (dt_basic_table.length) {
    new DataTable(dt_basic_table, {
      processing: true,
      serverSide: true,
      ordering: true,
      paging: true,
      pageLength: 10,
      lengthMenu: [[10, 25, 50, 100, 1000, 2000, -1], [10, 25, 50, 100, 1000, 2000, "All"]],
      ajax: {
        url: base_url + '/stock/stock',
        type: "POST",
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        dataType: "json",
        data: function(d) {
          d.item_code = item_code;
          d.category_id = category_id_f;
          d.brand_id = brand_id;
          d.item_id = item_id_f;
          d.generic_name = generic_name;
          d.supplier_id = supplier_id;
          d.price_type = price_type;
        },
        error: function(xhr, error, thrown) {
          // window.location.reload();
        }
      },
      columns: [
        { data: 0, orderable: false },
        { data: 1, orderable: false },
        { data: 2, orderable: false },
        { data: 3, orderable: false },
        { data: 4, orderable: true },
        { data: 5, orderable: false },
        { data: 6, orderable: true }
      ],
      drawCallback: function(settings) {
        const json = settings.json;
        if (json && json.stock_value) {
          $('#stock_value_span').text(currency_ + ' ' + parseFloat(json.stock_value.stock_value || 0).toFixed(op_precision));
          $('#stock_count_span').text(parseFloat(json.stock_value.stock_count || 0).toFixed(op_precision));
        }
      },
      dom: '<"card-header border-bottom py-3"<"head-label"><"dt-action-buttons text-end"B>>' +
          '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
          '<"row"<"col-sm-12"tr>>' +
          '<"row"<"col-sm-12 col-md-5 d-flex align-items-center"i><"col-sm-12 col-md-7 d-flex align-items-center justify-content-end"p>>',
      buttons: APPLICATION_DEMO_TYPE != 'Pharmacy' ? [
        {
          extend: 'collection',
          className: 'btn btn-primary dropdown-toggle',
          text: '<i class="ti tabler-file-export me-sm-1"></i> <span class="d-none d-sm-inline-block">'+language_key['Export']+'</span>',
          buttons: [
            {
              extend: 'print',
              text: '<i class="ti tabler-printer me-1" ></i>'+language_key.Print,
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
            },
            {
              extend: 'excel',
              text: '<i class="ti tabler-file-spreadsheet me-1"></i>'+language_key.Excel,
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
            },
            {
              extend: 'pdf',
              text: '<i class="ti tabler-file-description me-1"></i>'+language_key.Pdf,
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
            }
          ]
        },
        {
          text: '<i class="ti tabler-filter me-sm-1"></i> <span class="d-none d-sm-inline-block">'+language_key['Filter']+'</span>',
          className: 'btn create-new btn-primary',
          action: function (e, dt, node, config) {
              $('#filterSection').slideToggle();
              let isVisible = $('#filterSection').is(':visible');
          }
        }
      ] : [],
      language: {
        paginate: {
          previous: "Previous",
          next: "Next",
        }
      }
    });
  }

  // Get Stock Segmentation of Item
  function getStockSegmentationOfItem(item_id, item_type) {
    let table_no = '';
    if(item_type == 'IMEI_Product' || item_type == 'Serial_Product'){
      table_no = 2;
    } else if(item_type == 'Medicine_Product'){
      table_no = 3;
    } else if(item_type == 'Variation_Product'){
      table_no = 4;
    }
    
    $.ajax({
      type: "POST",
      url: base_url + '/stock/getStockSegmentationOfItem',
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      data: {
        item_id: item_id,
        item_type: item_type,
      },
      dataType: "json",
      success: function (response) {
        $(`#datatable${table_no} tbody`).html(response);
        if ($.fn.DataTable.isDataTable(`#datatable${table_no}`)) {
          $(`#datatable${table_no}`).DataTable().destroy();
        }
        $(`#datatable${table_no}`).DataTable({
          'ordering': false,
          'paging': true,
          'dom': '<"card-header border-bottom py-3"<"head-label"><"dt-action-buttons text-end"B>>' +
              '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
              '<"row"<"col-sm-12"tr>>' +
              '<"row"<"col-sm-12 col-md-5 d-flex align-items-center"i><"col-sm-12 col-md-7 d-flex align-items-center justify-content-end"p>>',
          'buttons': APPLICATION_DEMO_TYPE !== 'Pharmacy' ? [
            {
              extend: 'collection',
              className: 'btn btn-primary dropdown-toggle',
              text: '<i class="ti tabler-file-export me-sm-1"></i> <span class="d-none d-sm-inline-block">'+language_key['Export']+'</span>',
              buttons: [
                {
                  extend: 'print',
                  text: '<i class="ti tabler-printer me-1" ></i>'+language_key.Print,
                  className: 'dropdown-item'
                },
                {
                  extend: 'excel',
                  text: '<i class="ti tabler-file-spreadsheet me-1"></i>'+language_key.Excel,
                  className: 'dropdown-item'
                },
                {
                  extend: 'pdf',
                  text: '<i class="ti tabler-file-description me-1"></i>'+language_key.Pdf,
                  className: 'dropdown-item'
                }
              ]
            }
          ] : [],
          'language': {
            'paginate': {
              'next': 'Next',
              'previous': 'Previous'
            }
          }
        });
      },
      error: function (xhr, status, error) {
        console.error("Error loading table data: ", error);
      }
    });
  }

  // Modal Trigger
  $(document).on('click', '.modal_trigger', function(){
    let item_id = $(this).attr('data-id');
    let item_type = $(this).attr('data-type');
    let item_name = $(this).attr('data-name');

    let heading = '';
    let table_no = '';
    
    if(item_type == 'IMEI_Product'){
      table_no = 2;
      if ($.fn.DataTable.isDataTable(`#datatable3`)) {
        $(`#datatable3`).DataTable().clear().destroy();
      }
      if ($.fn.DataTable.isDataTable(`#datatable4`)) {
        $(`#datatable4`).DataTable().clear().destroy();
      }
      heading = `All IMEI Number of ${item_name}`;
      $('.datatable2').show();
      $('.datatable3').hide();
      $('.datatable4').hide();
    } else if(item_type == 'Serial_Product'){
      table_no = 2;
      if ($.fn.DataTable.isDataTable(`#datatable3`)) {
        $(`#datatable3`).DataTable().clear().destroy();
      }
      if ($.fn.DataTable.isDataTable(`#datatable4`)) {
        $(`#datatable4`).DataTable().clear().destroy();
      }
      heading = `All Serial Number of ${item_name}`;
      $('.datatable2').show();
      $('.datatable3').hide();
      $('.datatable4').hide();
    } else if(item_type == 'Medicine_Product'){
      table_no = 3;
      if ($.fn.DataTable.isDataTable(`#datatable2`)) {
        $(`#datatable2`).DataTable().clear().destroy();
      }
      if ($.fn.DataTable.isDataTable(`#datatable4`)) {
        $(`#datatable4`).DataTable().clear().destroy();
      }
      heading = `All Expiry Date of ${item_name}`;
      $('.datatable2').hide();
      $('.datatable3').show();
      $('.datatable4').hide();
    } else if(item_type == 'Variation_Product'){
      table_no = 4;
      if ($.fn.DataTable.isDataTable(`#datatable2`)) {
        $(`#datatable2`).DataTable().clear().destroy();
      }
      if ($.fn.DataTable.isDataTable(`#datatable3`)) {
        $(`#datatable3`).DataTable().clear().destroy();
      }
      heading = `All Variations of ${item_name}`;
      $('.datatable2').hide();
      $('.datatable3').hide();
      $('.datatable4').show();
    }
    
    $('.stock_segment_title').text(heading);
    if ($.fn.DataTable.isDataTable(`#datatable${table_no}`)) {
      $(`#datatable${table_no}`).DataTable().clear().destroy();
    }
    getStockSegmentationOfItem(item_id, item_type);
  });
});
