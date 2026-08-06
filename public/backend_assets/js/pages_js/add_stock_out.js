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

  // Function to check if an item already exists in table
  function itemExists(itemId) {
      let exists = false;
      $('#stockOutItems tr').each(function() {
          if ($(this).find('.item-id').val() == itemId) {
              exists = true;
              return false; // break the loop
          }
      });
      return exists;
  }
  
  // Function to calculate subtotal of all items
  function calculateSubtotal() {
      let subtotal = 0;
      $('#stockOutItems tr').each(function() {
          const total = parseFloat($(this).find('.total').val()) || 0;
          subtotal += total;
      });
      return subtotal;
  }
  
  // Function to update total item count
  function updateTotalItemCount() {
      const count = $('#stockOutItems tr').length;
      $('#totalItemCount').text(count);
  }
  
  // Function to calculate and update summary
  function updateSummary() {
      const grandTotal = calculateSubtotal();
      
      // Update display
      $('#grandTotal').val(grandTotal.toFixed(2));
      updateTotalItemCount();
  }
  
  // Function to add a new row to the stock out items table
  function addNewRow(itemData = null) {
      const rowCount = $('#stockOutItems tr').length;
      const newRow = $('<tr></tr>');
      
      // Create row HTML
      let rowHtml = `
            <td style="vertical-align: top;">${rowCount + 1}</td>
            <td style="vertical-align: top;">
                <input type="hidden" class="item-id" name="items[]" value="${itemData ? itemData.id : ''}">
                <span class="item-name">${itemData ? itemData.name : ''}</span>
            </td>
            <td style="vertical-align: top;">
                <input type="text" placeholder="Quantity" class="form-control number-input quantity" name="quantity[]" min="1" value="1">
            </td>
            <td style="vertical-align: top;">
                <input type="text" placeholder="Unit Price" class="form-control number-input unit-price" name="unit_price[]">
            </td>
            <td style="vertical-align: top;">
                <input type="text" placeholder="Total" class="form-control number-input total" name="total[]" readonly>
            </td>
            <td style="vertical-align: top;">
                <button type="button" class="btn text-danger remove-row">
                    <i class="icon-base ti tabler-trash"></i>
                </button>
            </td>
      `;
      
      newRow.html(rowHtml);
      $('#stockOutItems').append(newRow);
      
      // Initialize row events
      initializeRowEvents(newRow);
  }
  
  // Function to initialize events for a row
  function initializeRowEvents(row) {
      // Quantity and unit price change events
      row.find('.quantity, .unit-price').on('input', function() {
          calculateTotal(row);
      });
      
      // Remove row button click event
      row.find('.remove-row').on('click', function() {
          row.remove();
          updateRowNumbers();
          updateSummary();
      });
  }

    // ===============================
    // FIX FOR EDIT PAGE CALCULATION
    // ===============================
    $('#stockOutItems tr').each(function () {
        const row = $(this);

        // Attach events to existing rows
        initializeRowEvents(row);

        // Recalculate row total (important if qty/price changed)
        calculateTotal(row);
    });

    // Recalculate summary after all rows are initialized
    updateRowNumbers();
    updateSummary();
  
  // Function to calculate total for a row
  function calculateTotal(row) {
      const quantity = parseFloat(row.find('.quantity').val()) || 0;
      const unitPrice = parseFloat(row.find('.unit-price').val()) || 0;
      const total = quantity * unitPrice;
      row.find('.total').val(total.toFixed(2));
      
      // Update summary when row total changes
      updateSummary();
  }
  
  // Function to update row numbers
  function updateRowNumbers() {
      $('#stockOutItems tr').each(function(index) {
          $(this).find('td:first').text(index + 1);
      });
  }
  
  // Initialize summary on page load
  updateSummary();
  
  // Quick item select change event
  $('#quickItemSelect').on('change', function() {
      const selectedOption = $(this).find('option:selected');
      const itemId = $(this).val();
      
      if (!itemId) return;
      
      const itemData = {
          id: itemId,
          name: selectedOption.data('item-name')
      };
      
      // Check for duplicate items
      if (itemExists(itemId)) {
          showErrorNotification('This item already exists in the stock out list.');
          $(this).val(''); // Reset the select
          return;
      }
      
      // Add item to the table
      addNewRow(itemData);
      
      // Reset the select
      $(this).val('');
  });
  
  // Form submission validation
  $('#stockOutForm').on('submit', function(e) {
      let isValid = true;
      const errors = [];
      
      // Check if there are any items in the table
      if ($('#stockOutItems tr').length === 0) {
          errors.push('Please add at least one item to the stock out');
          isValid = false;
      } else {
          // Validate stock out items
          $('#stockOutItems tr').each(function(index) {
              const itemId = $(this).find('.item-id').val();
              
              if (!itemId) {
                  errors.push(`Row ${index + 1}: Please select an item`);
                  isValid = false;
                  return;
              }
              
              const quantity = parseFloat($(this).find('.quantity').val()) || 0;
              const unitPrice = parseFloat($(this).find('.unit-price').val()) || 0;
              
              if (quantity <= 0) {
                  errors.push(`Row ${index + 1}: Please enter a valid quantity`);
                  isValid = false;
              }
              
              if (unitPrice <= 0) {
                  errors.push(`Row ${index + 1}: Please enter a valid unit price`);
                  isValid = false;
              }
          });
      }
      
      if (!isValid) {
          e.preventDefault();
          showErrorNotification(errors.join('\n'));
      }
  });
});


