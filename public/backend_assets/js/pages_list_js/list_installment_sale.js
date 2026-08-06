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


  const dt_installment_sale_table = $('.datatables-installment-sale');
  if (dt_installment_sale_table.length) {
    new DataTable(dt_installment_sale_table, {
      processing: true,
      serverSide: true,
      ajax: {
        url: base_url + '/installment-sale',
        type: 'GET',
        error: function(xhr, error, thrown) {
          console.error('DataTable error:', error);
          // Optionally reload on error
          // window.location.reload();
        }
      },
      columns: [
        { data: 'id' },
        { data: 'reference_no' },
        { data: 'date' },
        { 
          data: null,
          render: function(data) {
            return `<div>
              <strong>${data.customer_name}</strong><br>
              <small class="text-muted">${data.customer_phone}</small>
            </div>`;
          }
        },
        { data: 'item_name' },
        { data: 'total' },
        { data: 'down_payment' },
        { 
          data: null,
          orderable: false,
          searchable: false,
          render: function(data) {
            const csrf = $('meta[name="csrf-token"]').attr('content');
            const view_url = route('installment-sale.show', { id: data.encrypted_id }, false, Ziggy);
            const edit_url = route('installment-sale.edit', { id: data.encrypted_id }, false, Ziggy);
            const destroy_url = route('installment-sale.destroy', { id: data.encrypted_id }, false, Ziggy);
            const print_url = route('installment-sale.print', { id: data.encrypted_id }, false, Ziggy);
            const pdf_url = route('installment-sale.pdf', { id: data.encrypted_id }, false, Ziggy);
            
            return `<div class="d-inline-flex datatable-action">
              <a href="${base_url + print_url}" target="_blank" class="btn btn-sm btn-icon" title="Print Invoice">
                <i class="ti tabler-printer"></i>
              </a>
              <a href="${base_url + pdf_url}" class="btn btn-sm btn-icon" title="Download PDF">
                <i class="ti tabler-file-type-pdf"></i>
              </a>
              <a href="${base_url + view_url}" class="btn btn-sm btn-icon view-record" title="View">
                <i class="ti tabler-eye"></i>
              </a>
              <a href="${base_url + edit_url}" class="btn btn-sm btn-icon edit-record" title="Edit">
                <i class="ti tabler-edit"></i>
              </a>
              <button type="button" data-id="${data.actual_id}" class="delete_data btn btn-sm btn-icon delete-record" title="Delete">
                <i class="ti tabler-trash delete"></i>
              </button>
              <form id="deleteForm-${data.actual_id}" action="${base_url + destroy_url}" method="POST" style="display: none;">
                <input type="hidden" name="_token" value="${csrf}">
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
              text: '<i class="ti tabler-printer me-1"></i>' + (language_key.Print || 'Print'),
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9] }
            },
            {
              extend: 'excel',
              text: '<i class="ti tabler-file-spreadsheet me-1"></i>' + (language_key.Excel || 'Excel'),
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9] }
            },
            {
              extend: 'pdf',
              text: '<i class="ti tabler-file-description me-1"></i>' + (language_key.Pdf || 'PDF'),
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9] }
            }
          ]
        }, 
        {
          text: '<i class="ti tabler-plus me-sm-1"></i> <span class="d-none d-sm-inline-block">' + language_key['Add Installment Sale'] + '</span>',
          className: 'btn create-new btn-primary',
          action: function (e, dt, node, config) {
            window.location.href = base_url + '/installment-sale/create';
          }
        }
      ],
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      pageLength: 10,
      order: [[0, 'desc']]
    });
  }
});

