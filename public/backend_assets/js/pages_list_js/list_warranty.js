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


  const dt_basic_table = $('.datatables-basic');
  if (dt_basic_table) {
    new DataTable(dt_basic_table, {
      processing: true,
      serverSide: true,
      ajax: {
        url: base_url + '/warranty',
        type: 'GET',
        error: function(xhr, error, thrown) { console.error('list_warranty AJAX error:', error, thrown); }
      },
      columns: [
        { data: 'id' },
        { data: 'customer_name' },
        { data: 'customer_mobile' },
        { data: 'product_name' },
        { data: 'product_serial_no' },
        { data: 'receiving_date' },
        { data: 'delivery_date' },
        { 
          data: 'current_status',
          render: function(data, type, full) {
            const statuses = {
              'R_F_C': language_key['Receive From Customer'] || 'Receive From Customer',
              'S_T_V': language_key['Send To Vendor'] || 'Send To Vendor',
              'R_T_V': language_key['Receive From Vendor'] || 'Receive From Vendor',
              'D_T_C': language_key['Delivered To Customer'] || 'Delivered To Customer'
            };
            
            const currentStatus = full.current_status || 'N/A';
            const statusLabel = statuses[currentStatus] || currentStatus;
            
            return `
              <select class="form-select form-select-sm warranty-status-select" 
                      data-id="${full.encrypted_id}" 
                      data-actual-id="${full.actual_id}"
                      style="min-width: 150px;">
                <option value="R_F_C" ${currentStatus === 'R_F_C' ? 'selected' : ''}>${statuses['R_F_C'] || 'Receive From Customer'}</option>
                <option value="S_T_V" ${currentStatus === 'S_T_V' ? 'selected' : ''}>${statuses['S_T_V'] || 'Send To Vendor'}</option>
                <option value="R_T_V" ${currentStatus === 'R_T_V' ? 'selected' : ''}>${statuses['R_T_V'] || 'Receive From Vendor'}</option>
                <option value="D_T_C" ${currentStatus === 'D_T_C' ? 'selected' : ''}>${statuses['D_T_C'] || 'Delivered To Customer'}</option>
              </select>
            `;
          }
        },
        { data: 'technician_name' },
        { 
          data: null,
          orderable: false,
          searchable: false,
          render: function(data) {
            const csrf = $('meta[name="csrf-token"]').attr('content');
            const edit_url = route('warranty.edit', { warranty: data.encrypted_id }, false, Ziggy);
            const destroy_url = route('warranty.destroy', { warranty: data.encrypted_id }, false, Ziggy);
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
              exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8] }
            },
            {
              extend: 'excel',
              text: '<i class="ti tabler-file-spreadsheet me-1"></i>'+language_key.Excel,
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8] }
            },
            {
              extend: 'pdf',
              text: '<i class="ti tabler-file-description me-1"></i>'+language_key.Pdf,
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8] }
            }
          ]
        }, 
        {
          text: '<i class="ti tabler-plus me-sm-1"></i> <span class="d-none d-sm-inline-block">'+language_key['Add Warranty']+'</span>',
          className: 'btn create-new btn-primary',
          action: function (e, dt, node, config) {
            window.location.href = base_url + '/warranty/create';
          }
        }
      ],
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      pageLength: 10,
      order: [[0, 'desc']],
      drawCallback: function() {
        // Handle status change after table is drawn
        $('.warranty-status-select').off('change').on('change', function() {
          const select = $(this);
          const warrantyId = select.data('id');
          const newStatus = select.val();
          const originalStatus = select.data('original-status') || select.find('option:selected').val();
          
          // Disable select while updating
          select.prop('disabled', true);
          
          // Show loading state
          // const originalHtml = select.html();
          // select.html('<option>Updating...</option>');
          
          $.ajax({
            url: base_url + '/warranty/update-status',
            type: 'POST',
            data: {
              id: warrantyId,
              status: newStatus,
              _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
              if (response.success) {
                // Show success notification
                if (typeof showSuccessNotification !== 'undefined') {
                  showSuccessNotification(response.message || 'Status updated successfully');
                } else if (typeof toastr !== 'undefined') {
                  toastr.success(response.message || 'Status updated successfully');
                }
                
                // Update the select with new status
                select.data('original-status', newStatus);
                select.prop('disabled', false);
              } else {
                // Revert on error
                select.val(originalStatus);
                select.prop('disabled', false);
                
                if (typeof showErrorNotification !== 'undefined') {
                  showErrorNotification(response.error || 'Failed to update status');
                } else if (typeof toastr !== 'undefined') {
                  toastr.error(response.error || 'Failed to update status');
                } else {
                  alert(response.error || 'Failed to update status');
                }
              }
            },
            error: function(xhr) {
              // Revert on error
              select.val(originalStatus);
              select.prop('disabled', false);
              
              let errorMessage = 'Failed to update status';
              if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMessage = xhr.responseJSON.error;
              }
              
              if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification(errorMessage);
              } else if (typeof toastr !== 'undefined') {
                toastr.error(errorMessage);
              } else {
                alert(errorMessage);
              }
            }
          });
        });
      }
    });
  }
});
