$(async function () {
  "use strict";
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

  let company_data = $('#company_data').val();
  let company_session_data = {};
  try {
    company_session_data = JSON.parse(company_data);
  } catch (e) {
    console.error('Error parsing company info:', e);
  }

  let dateFromFilter = '';
  let dateToFilter = '';
  let employeeFilter = '';
  const route_path = route('employee-advance-payment.index', {}, false, Ziggy);
  const dt_basic_table = $('.datatables-basic');
  let advancePaymentTable;

  function initSelect2() {
    if ($('#employee_filter').length && !$('#employee_filter').hasClass('select2-hidden-accessible')) {
      $('#employee_filter').select2({
        dropdownParent: $('#filterSection'),
        width: '100%',
        placeholder: language_key.AllEmployees || 'All Employees',
        allowClear: true
      });
    }
  }

  function updateTotalAmountDisplay(totalAmountFormatted) {
    if (totalAmountFormatted) {
      $('#totalAmountDisplay').text(totalAmountFormatted);
      $('#totalAmountCard').slideDown();
    } else {
      $('#totalAmountCard').slideUp();
    }
  }

  if (dt_basic_table) {
    advancePaymentTable = new DataTable(dt_basic_table, {
      processing: true,
      serverSide: true,
      ajax: {
        url: base_url + route_path,
        type: 'GET',
        data: function (d) {
          d.date_from = dateFromFilter;
          d.date_to = dateToFilter;
          d.employee_id = employeeFilter;
        },
        dataSrc: function (json) {
          if (json.totalAmountFormatted) {
            updateTotalAmountDisplay(json.totalAmountFormatted);
          } else {
            updateTotalAmountDisplay(null);
          }
          return json.data;
        }
      },
      columns: [
        { data: 'id', name: 'DT_RowIndex', orderable: false, searchable: false },
        { data: 'reference_no' },
        { data: 'date' },
        { data: 'amount' },
        { data: 'employee_name' },
        { data: 'payment_method' },
        {
          data: 'note',
          render: function (data) {
            return data ? (data.length > 50 ? data.substring(0, 50) + '...' : data) : '';
          }
        },
        {
          data: null,
          orderable: false,
          searchable: false,
          render: function (data) {
            const csrf = $('meta[name="csrf-token"]').attr('content');
            const show_url = route('employee-advance-payment.show', { employee_advance_payment: data.encrypted_id }, false, Ziggy);
            const edit_url = route('employee-advance-payment.edit', { employee_advance_payment: data.encrypted_id }, false, Ziggy);
            const destroy_url = route('employee-advance-payment.destroy', { employee_advance_payment: data.encrypted_id }, false, Ziggy);
            return '<div class="d-inline-flex datatable-action">' +
              '<a href="' + (base_url + show_url) + '" class="btn btn-sm btn-icon" title="View"><i class="ti tabler-eye"></i></a>' +
              '<a href="' + (base_url + edit_url) + '" class="btn btn-sm btn-icon edit-record" title="Edit"><i class="ti tabler-edit"></i></a>' +
              '<button type="button" data-id="' + (data.actual_id || data.id) + '" class="delete_data btn btn-sm btn-icon delete-record" title="Delete"><i class="ti tabler-trash delete"></i></button>' +
              '<form id="deleteForm-' + (data.actual_id || data.id) + '" action="' + (base_url + destroy_url) + '" method="POST" style="display: none;">' +
              '<input type="hidden" name="_token" value="' + csrf + '">' +
              '<input type="hidden" name="_method" value="DELETE">' +
              '</form></div>';
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
          className: 'btn btn-primary btn-label-primary dropdown-toggle me-2',
          text: '<i class="ti tabler-file-export me-sm-1"></i> <span class="d-none d-sm-inline-block">' + (language_key['Export'] || 'Export') + '</span>',
          buttons: [
            { extend: 'print', text: '<i class="ti tabler-printer me-1"></i>' + (language_key.Print || 'Print'), className: 'dropdown-item', exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] } },
            { extend: 'excel', text: '<i class="ti tabler-file-spreadsheet me-1"></i>' + (language_key.Excel || 'Excel'), className: 'dropdown-item', exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] } },
            { extend: 'pdf', text: '<i class="ti tabler-file-description me-1"></i>' + (language_key.Pdf || 'Pdf'), className: 'dropdown-item', exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] } }
          ]
        },
        {
          text: '<i class="ti tabler-filter me-sm-1"></i> <span class="d-none d-sm-inline-block">' + (language_key['Filter'] || 'Filter') + '</span>',
          className: 'btn create-new btn-primary me-2',
          action: function (e, dt, node, config) {
            $('#filterSection').slideToggle();
            if ($('#filterSection').is(':visible')) initSelect2();
          }
        },
        {
          text: '<i class="ti tabler-plus me-sm-1"></i> <span class="d-none d-sm-inline-block">' + (language_key['Add Employee Advance Payment'] || 'Add Employee Advance Payment') + '</span>',
          className: 'btn create-new btn-primary',
          action: function () {
            window.location.href = base_url + route('employee-advance-payment.create', {}, false, Ziggy);
          }
        }
      ],
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      pageLength: 10,
      order: [[2, 'desc']]
    });
  }

  $(document).on('submit', '#filterForm', function (e) {
    e.preventDefault();
    return false;
  });

  $(document).on('click', '#applyFilter', function (e) {
    e.preventDefault();
    dateFromFilter = $('#date_from_filter').val() || '';
    dateToFilter = $('#date_to_filter').val() || '';
    employeeFilter = $('#employee_filter').val() || '';
    if (advancePaymentTable) advancePaymentTable.ajax.reload();
    return false;
  });
});
