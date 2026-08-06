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

  const route_path = route('role.index', {}, false, Ziggy);
  const dt_basic_table = $('.datatables-basic');
  if (dt_basic_table) {
    const table = new DataTable(dt_basic_table, {
      processing: true,
      serverSide: true,
      ajax: {
        url: base_url + route_path,
        type: 'GET',
        error: function(xhr, error, thrown) { console.error('list_role AJAX error:', error, thrown); }
      },
      columns: [
        { 
          data: null,
          orderable: false,
          render: function(data, type, row, meta) {
            const pageInfo = table.page.info();
            const totalRecords = pageInfo.recordsTotal;
            const currentPage = pageInfo.page;
            const pageLength = pageInfo.length;
            const rowIndex = meta.row;
            // Calculate serial number: total - (page * pageLength) - rowIndex
            const serialNumber = totalRecords - (currentPage * pageLength) - rowIndex;
            return serialNumber;
          }
        },
        { data: 'name' },
        { data: 'permissions_count' },
        { data: 'users_count' },
        { 
          data: null,
          orderable: false,
          searchable: false,
          render: function(data) {
            const csrf = $('meta[name="csrf-token"]').attr('content');
            const edit_url = route('role.edit', { role: data.encrypted_id }, false, Ziggy);
            const destroy_url = route('role.destroy', { role: data.encrypted_id }, false, Ziggy);
            
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
              exportOptions: { columns: [0, 1, 2, 3] }
            },
            {
              extend: 'excel',
              text: '<i class="ti tabler-file-spreadsheet me-1"></i>'+language_key.Excel,
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3] }
            },
            {
              extend: 'pdf',
              text: '<i class="ti tabler-file-description me-1"></i>'+language_key.Pdf,
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3] }
            }
          ]
        }, 
        {
          text: '<i class="ti tabler-plus me-sm-1"></i> <span class="d-none d-sm-inline-block">'+language_key['Add Role']+'</span>',
          className: 'btn create-new btn-primary',
          action: function (e, dt, node, config) {
            const create_url = route('role.create', {}, false, Ziggy);
            window.location.href = base_url + create_url;
          }
        }
      ],
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      pageLength: 10,
      order: [[0, 'desc']]
    });
  }
});
