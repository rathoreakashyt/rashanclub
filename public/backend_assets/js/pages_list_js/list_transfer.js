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
        url: base_url + '/transfer',
        type: 'GET',
        dataSrc: function(json) {
          // Update stat cards from summary counts
          const s = json.summary || {};
          animateStat('#statTotal', s.total || 0);
          animateStat('#statDraft', s.Draft || 0);
          animateStat('#statSent', s.Sent || 0);
          animateStat('#statReceived', s.Received || 0);
          return json.data;
        },
        error: function(xhr, error, thrown) { console.error('list_transfer AJAX error:', error, thrown); }
      },
      columns: [
        { data: 'id' },
        { 
          data: 'reference_no',
          render: function(data) { return '<span class="tf-ref">' + data + '</span>'; }
        },
        { 
          data: 'date',
          render: function(data) { return '<span class="tf-date"><i class="ti tabler-calendar"></i>' + data + '</span>'; }
        },
        { 
          data: 'from_outlet_name',
          render: function(data) { return '<span class="tf-outlet"><i class="ti tabler-building-store"></i>' + data + '</span>'; }
        },
        { 
          data: 'to_outlet_name',
          render: function(data) { return '<span class="tf-outlet"><i class="ti tabler-building-store"></i>' + data + '</span>'; }
        },
        { 
          data: 'status',
          render: function(data) {
            let cls = 'tf-status-draft';
            if (data === 'Sent') {
              cls = 'tf-status-sent';
            } else if (data === 'Received') {
              cls = 'tf-status-received';
            }
            return '<span class="tf-status ' + cls + '">' + data + '</span>';
          }
        },
        { 
          data: null,
          orderable: false,
          searchable: false,
          render: function(data) {
            const csrf = $('meta[name="csrf-token"]').attr('content');
            const edit_url = route('transfer.edit', { transfer: data.encrypted_id }, false, Ziggy);
            const destroy_url = route('transfer.destroy', { transfer: data.encrypted_id }, false, Ziggy);
            const show_url = route('transfer.show', { transfer: data.encrypted_id }, false, Ziggy);
            return `<div class="d-inline-flex datatable-action gap-1">
              <a href="${base_url+show_url}" class="btn tf-action tf-action-view" title="View" data-bs-toggle="tooltip" data-bs-placement="top">
                <i class="ti tabler-eye"></i>
              </a>
              <a href="${base_url+edit_url}" class="btn tf-action tf-action-edit edit-record" title="Edit" data-bs-toggle="tooltip" data-bs-placement="top">
                <i class="ti tabler-edit"></i>
              </a>
              <button type="button" data-id="${ data.actual_id }" class="btn tf-action tf-action-del delete_data delete-record" title="Delete" data-bs-toggle="tooltip" data-bs-placement="top">
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
      dom: '<"card-header border-bottom p-3 d-flex justify-content-between align-items-center flex-wrap gap-2"<"head-label"><"dt-action-buttons text-end"B>>' +
          '<"row mt-3"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
          '<"row"<"col-sm-12"tr>>' +
          '<"row"<"col-sm-12 col-md-5 d-flex align-items-center"i><"col-sm-12 col-md-7 d-flex align-items-center justify-content-end"p>>',
      buttons: [
        {
          extend: 'collection',
          className: 'btn btn-outline-secondary me-2',
          text: '<i class="ti tabler-file-export me-sm-1"></i> <span class="d-none d-sm-inline-block">'+language_key['Export']+'</span>',
          buttons: [
            {
              extend: 'print',
              text: '<i class="ti tabler-printer me-1" ></i>'+language_key.Print,
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5] }
            },
            {
              extend: 'excel',
              text: '<i class="ti tabler-file-spreadsheet me-1"></i>'+language_key.Excel,
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5] }
            },
            {
              extend: 'pdf',
              text: '<i class="ti tabler-file-description me-1"></i>'+language_key.Pdf,
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5] }
            }
          ]
        }, 
        {
          text: '<i class="ti tabler-plus me-sm-1"></i> <span class="d-none d-sm-inline-block">'+language_key['Add Transfer']+'</span>',
          className: 'btn create-new btn-primary',
          action: function (e, dt, node, config) {
            window.location.href = base_url + '/transfer/create';
          }
        }
      ],
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      pageLength: 10,
      order: [[0, 'desc']],
      initComplete: function() {
        if (typeof $.fn.tooltip !== 'undefined') {
          $('[data-bs-toggle="tooltip"]').tooltip();
        }
      }
    });
  }

  // Animate a stat counter from current value to target
  function animateStat(selector, target) {
    const $el = $(selector);
    const current = parseInt($el.text(), 10) || 0;
    if (current === target) return;
    const steps = 20;
    const diff = target - current;
    let i = 0;
    const interval = setInterval(function() {
      i++;
      const val = current + Math.round(diff * (i / steps));
      $el.text(val);
      if (i >= steps) {
        clearInterval(interval);
        $el.text(target);
      }
    }, 20);
  }
});


