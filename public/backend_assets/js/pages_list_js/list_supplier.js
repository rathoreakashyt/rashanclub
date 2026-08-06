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


  let supplierTypeFilter = '';
  let supplierTable;

  // Initialize Select2 for filter dropdown
  function initSelect2() {
    if ($('#supplier_type_filter').length && !$('#supplier_type_filter').hasClass('select2-hidden-accessible')) {
      $('#supplier_type_filter').select2({
        dropdownParent: $('#filterSection'),
        width: '100%',
        placeholder: language_key.AllSupplier || 'All Supplier',
        allowClear: true
      });
    }
  }

  const dt_basic_table = $('.datatables-basic');
  if (dt_basic_table) {
    supplierTable = new DataTable(dt_basic_table, {
      processing: true,
      serverSide: true,
      ajax: {
        url: base_url + '/supplier',
        type: 'GET',
        data: function(d) {
          d.type_filter = supplierTypeFilter;
        },
        error: function(xhr, error, thrown) { console.error('list_supplier AJAX error:', error, thrown); }
      },
      columns: [
        { data: 'id' },
        { data: 'name' },
        { data: 'contact_person' },
        { data: 'phone' },
        { data: 'current_balance' },
        { 
          data: null,
          orderable: false,
          searchable: false,
          render: function(data) {
            const csrf = $('meta[name="csrf-token"]').attr('content');
            const edit_url = route('supplier.edit', { supplier: data.encrypted_id }, false, Ziggy);
            const destroy_url = route('supplier.destroy', { supplier: data.encrypted_id }, false, Ziggy);
            return `<div class="d-inline-flex datatable-action">
              <a href="${base_url+edit_url}" class="btn btn-sm btn-icon edit-record">
                <i class="ti tabler-edit"></i>
              </a>
              <button type="button" data-id="${ data.actual_id }" class="delete_data btn btn-sm btn-icon delete-record">
                <i class="ti tabler-trash delete"></i>
              </button>
              <form id="deleteForm-${ data.actual_id }" action="${ base_url+destroy_url }" method="POST" style="display: none;">
                <input type="hidden" name="_token" value="${ csrf }">
                <input type="hidden" name="_method" value="DELETE">
              </form>
            </div>`;
          }
        }
      ],
      dom: '<"card-header border-bottom p-3"<"head-label"><"dt-action-buttons text-end"B>>' +
          '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
          '<"row"<"col-sm-12"tr>>' +
          '<"row"<"col-sm-12 col-md-5 d-flex align-items-center"i><"col-sm-12 col-md-7 d-flex align-items-center justify-content-end"p>>',
      buttons: [
        {
          extend: 'collection',
          className: 'btn btn-primary dropdown-toggle me-2',
          text: '<i class="ti tabler-file-export me-sm-1"></i> <span class="d-none d-sm-inline-block">'+language_key['Export']+'</span>',
          buttons: [
            {
              extend: 'print',
              text: '<i class="ti tabler-printer me-1" ></i>'+language_key.Print,
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4] }
            },
            {
              extend: 'excel',
              text: '<i class="ti tabler-file-spreadsheet me-1"></i>'+language_key.Excel,
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4] }
            },
            {
              extend: 'pdf',
              text: '<i class="ti tabler-file-description me-1"></i>'+language_key.Pdf,
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4] }
            }
          ]
        },
        {
          text: '<i class="ti tabler-filter me-sm-1"></i> <span class="d-none d-sm-inline-block">'+language_key['Filter']+'</span>',
          className: 'btn create-new btn-primary me-2',
          action: function (e, dt, node, config) {
            $('#filterSection').slideToggle();
            let isVisible = $('#filterSection').is(':visible');
            if (isVisible) {
              initSelect2();
            }
          }
        },
        {
          text: '<i class="ti tabler-plus me-sm-1"></i> <span class="d-none d-sm-inline-block">'+language_key['Add Supplier']+'</span>',
          className: 'btn create-new btn-primary',
          action: function (e, dt, node, config) {
            window.location.href = base_url + '/supplier/create';
          }
        }
      ],
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      pageLength: 10,
      order: [[0, 'desc']]
    });
  }

  // Prevent form submission
  $(document).on('submit', '#filterForm', function(e) {
    e.preventDefault();
    e.stopPropagation();
    return false;
  });

  // Prevent Enter key from submitting form
  $(document).on('keydown', '#filterForm input, #filterForm select', function(e) {
    if (e.key === 'Enter' || e.keyCode === 13) {
      e.preventDefault();
      e.stopPropagation();
      $('#applyFilter').click();
      return false;
    }
  });

  // Apply filter
  $(document).on('click', '#applyFilter', function(e) {
    e.preventDefault();
    e.stopPropagation();
    supplierTypeFilter = $('#supplier_type_filter').val() || '';
    if (supplierTable) {
      supplierTable.ajax.reload();
    }
    return false;
  });

  // Reset filter
  $(document).on('click', '#resetFilter', function(e) {
    e.preventDefault();
    e.stopPropagation();
    $('#supplier_type_filter').val('').trigger('change');
    supplierTypeFilter = '';
    if (supplierTable) {
      supplierTable.ajax.reload();
    }
    return false;
  });

  // Reinitialize Select2 when filter section is toggled
  $('#filterSection').on('shown.bs.collapse shown', function () {
    initSelect2();
  });

  // Initialize Select2 on page load if filter section is visible
  initSelect2();
});
