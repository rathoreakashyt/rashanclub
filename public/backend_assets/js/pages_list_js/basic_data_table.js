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
 
  // import route from 'ziggy-js';
  // import { Ziggy } from './ziggy'; // If you're using Vite or a compiled setup
  const dt_basic_table = $('.datatables-basic');
  if (dt_basic_table) {
    new DataTable(dt_basic_table, {
      processing: true,
      serverSide: true,
      ajax: {
        url: base_url + '/user',
        type: 'GET'
      },
      columns: [
        { data: 'id' },
        { data: 'name' },
        { data: 'email' },
        { data: 'phone' },
        { data: 'role' },
        { data: 'outlets' },
        { 
          data: null,
          orderable: false,
          searchable: false,
          render: function(data) {
            const edit_url = route('user.edit', { user: data.encrypted_id }, false, Ziggy);
            const destroy_url = route('user.destroy', { user: data.encrypted_id }, false, Ziggy);
            return `<div class="d-inline-flex datatable-action">
              <a href="${edit_url}" class="btn btn-sm btn-icon edit-record">
                <i class="ti tabler-edit"></i>
              </a>
              <a href="${destroy_url}" class="btn btn-sm btn-icon delete-record" data-id="${data.encrypted_id}">
                <i class="ti tabler-trash delete"></i>
              </a>
            </div>`;
          }
        }
      ],
      dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
           '<"row"<"col-sm-12"tr>>' +
           '<"row px-5"<"col-sm-12 col-md-5 d-flex align-items-center"i><"col-sm-12 col-md-7 d-flex justify-content-end"p>>',
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      pageLength: 10,
      order: [[0, 'desc']]
    });
  }
});
