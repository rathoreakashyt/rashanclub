/**
 * Add new role Modal JS
 */
'use strict';
document.addEventListener('DOMContentLoaded', function (e) {
  (function () {
    // Select All checkbox click
    const selectAll = document.querySelector('#selectAll'),
    checkboxList = document.querySelectorAll('[type="checkbox"]');
    selectAll.addEventListener('change', t => {
      checkboxList.forEach(e => {
        e.checked = t.target.checked;
      });
    });
  })();
});
