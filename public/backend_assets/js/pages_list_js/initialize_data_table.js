$(function () {
  "use strict";
  let base_url = $('#base_url').val();
  
  const dt_basic_table = $('.datatables-basic');
  if (dt_basic_table) {
    new DataTable(dt_basic_table, {
      // Basic DataTable initialization
      searching: true,
      ordering: true,
      paging: true,
      info: true,
      lengthChange: true,
      pageLength: 10,
      responsive: true
    });
  }
});
