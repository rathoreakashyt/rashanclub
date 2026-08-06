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
  
  let logged_in_user_id = parseInt($('#logged_in_user_id').val()) || null;
  const route_path = route('attendance.index', {}, false, Ziggy);
  const dt_basic_table = $('.datatables-basic');
  
  // Filter variables
  let dateFromFilter = '';
  let dateToFilter = '';
  let employeeFilter = '';
  let attendanceTable;

  // Initialize Select2 for filter dropdown
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

  // Calculate time difference in hours
  function calculateTimeDifference(inTime, outTime) {
    if (!inTime || !outTime || inTime === 'N/A' || outTime === 'N/A') {
      return 'N/A';
    }

    alert(inTime + ' ' + outTime);
    
    try {
      // Parse the datetime strings
      const inDate = new Date(inTime);
      const outDate = new Date(outTime);
      
      if (isNaN(inDate.getTime()) || isNaN(outDate.getTime())) {
        return 'N/A';
      }
      
      // Calculate difference in milliseconds
      const diffMs = outDate - inDate;
      
      if (diffMs < 0) {
        return 'N/A';
      }
      
      // Convert to hours
      const diffHours = diffMs / (1000 * 60 * 60);
      
      // Format as hours and minutes
      const hours = Math.floor(diffHours);
      const minutes = Math.floor((diffHours - hours) * 60);
      
      return `${hours}h ${minutes}m`;
    } catch (e) {
      return 'N/A';
    }
  }

  // Update total hours display from server response
  function updateTotalHoursDisplay(totalHoursFormatted) {
    if (totalHoursFormatted) {
      $('#totalHoursDisplay').text(totalHoursFormatted);
      $('#totalHoursCard').slideDown();
    } else {
      $('#totalHoursCard').slideUp();
    }
  }

  if (dt_basic_table) {
    attendanceTable = new DataTable(dt_basic_table, {
      processing: true,
      serverSide: true,
      ajax: {
        url: base_url + route_path,
        type: 'GET',
        data: function(d) {
          d.date_from = dateFromFilter;
          d.date_to = dateToFilter;
          d.employee_id = employeeFilter;
        },
        dataSrc: function(json) {
          // Update total hours display from server response
          if (json.totalHoursFormatted) {
            updateTotalHoursDisplay(json.totalHoursFormatted);
          } else {
            updateTotalHoursDisplay(null);
          }
          return json.data;
        },
        // error: function(xhr, error, thrown) {
        //   window.location.reload();
        // }
      },
      columns: [
        { 
          data: null,
          orderable: false,
          render: function(data, type, row, meta) {
            const pageInfo = attendanceTable.page.info();
            const totalRecords = pageInfo.recordsTotal;
            const currentPage = pageInfo.page;
            const pageLength = pageInfo.length;
            const rowIndex = meta.row;
            // Calculate serial number: total - (page * pageLength) - rowIndex
            const serialNumber = totalRecords - (currentPage * pageLength) - rowIndex;
            return serialNumber;
          }
        },
        { data: 'reference_no' },
        { data: 'date' },
        { data: 'employee_name' },
        { data: 'in_time' },
        { data: 'out_time' },
        { data: 'total_time', },
        { 
          data: 'note',
          render: function(data) {
            return data ? data.substring(0, 50) + (data.length > 50 ? '...' : '') : '';
          }
        },
        { 
          data: null,
          orderable: false,
          searchable: false,
          render: function(data) {
            const csrf = $('meta[name="csrf-token"]').attr('content');
            const edit_url = route('attendance.edit', { attendance: data.encrypted_id }, false, Ziggy);
            const destroy_url = route('attendance.destroy', { attendance: data.encrypted_id }, false, Ziggy);
            
            let actionButtons = '<div class="d-inline-flex datatable-action">';
            
            // Edit button
            actionButtons += `<a href="${base_url+edit_url}" class="btn btn-sm btn-icon edit-record">
              <i class="ti tabler-edit"></i>
            </a>`;
            
            // Delete button
            actionButtons += `<button type="button" data-id="${ data.id }" class="delete_data btn btn-sm btn-icon delete-record">
              <i class="ti tabler-trash delete"></i>
            </button>
            <form id="deleteForm-${ data.id }" action="${ base_url+destroy_url }" method="POST" style="display: none;">
              <input type="hidden" name="_token" value="${ csrf }">
              <input type="hidden" name="_method" value="DELETE">
            </form>`;
            
            actionButtons += '</div>';
            return actionButtons;
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
          text: '<i class="ti tabler-file-export me-sm-1"></i> <span class="d-none d-sm-inline-block">'+language_key['Export']+'</span>',
          buttons: [
            {
              extend: 'print',
              text: '<i class="ti tabler-printer me-1" ></i>'+language_key.Print,
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] }
            },
            {
              extend: 'excel',
              text: '<i class="ti tabler-file-spreadsheet me-1"></i>'+language_key.Excel,
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] }
            },
            {
              extend: 'pdf',
              text: '<i class="ti tabler-file-description me-1"></i>'+language_key.Pdf,
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] }
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
          text: '<i class="ti tabler-plus me-sm-1"></i> <span class="d-none d-sm-inline-block">'+language_key['Add Attendance']+'</span>',
          className: 'btn create-new btn-primary',
          action: function (e, dt, node, config) {
            const create_url = route('attendance.create', {}, false, Ziggy);
            window.location.href = base_url + create_url;
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
    dateFromFilter = $('#date_from_filter').val() || '';
    dateToFilter = $('#date_to_filter').val() || '';
    employeeFilter = $('#employee_filter').val() || '';
    if (attendanceTable) {
      attendanceTable.ajax.reload();
    }
    return false;
  });

  // Reset filter
  $(document).on('click', '#resetFilter', function(e) {
    e.preventDefault();
    e.stopPropagation();
    $('#date_from_filter').val('');
    $('#date_to_filter').val('');
    $('#employee_filter').val('').trigger('change');
    dateFromFilter = '';
    dateToFilter = '';
    employeeFilter = '';
    if (attendanceTable) {
      attendanceTable.ajax.reload();
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
