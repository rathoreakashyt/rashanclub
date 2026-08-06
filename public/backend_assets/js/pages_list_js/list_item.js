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
        url: base_url + '/item',
        type: 'GET',
        error: function(xhr, error, thrown) { console.error('list_item AJAX error:', error, thrown); }
      },
      columns: [
        { data: 'id' },
        { data: 'name' },
        { data: 'type' },
        { data: 'category_id' },
        { data: 'purchase_price' },
        { data: 'sale_price' },
        { data: 'mrp_price' },
        { 
          data: null,
          orderable: false,
          searchable: false,
          render: function(data) {
            const csrf = $('meta[name="csrf-token"]').attr('content');
            const show_url = route('item.show', { item: data.encrypted_id }, false, Ziggy);
            const edit_url = route('item.edit', { item: data.encrypted_id }, false, Ziggy);
            const destroy_url = route('item.destroy', { item: data.encrypted_id }, false, Ziggy);
            return `<div class="d-inline-flex datatable-action">
              
              <button type="button" data-item-id="${data.encrypted_id}" data-item-name="${data.name || ''}" data-item-code="${data.code || ''}" data-item-price="${data.sale_price_raw || 0}" data-item-type="${data.type || ''}" class="btn btn-sm btn-icon print-label-btn" title="Print Label">
                <i class="ti tabler-barcode"></i>
              </button>
              
              <a href="${base_url+show_url}" class="btn btn-sm btn-icon" title="View">
                <i class="ti tabler-eye"></i>
              </a>
              <a href="${base_url+edit_url}" class="btn btn-sm btn-icon edit-record" title="Edit">
                <i class="ti tabler-edit"></i>
              </a>
              <button type="button" data-id="${ data.actual_id }" class="delete_data btn btn-sm btn-icon delete-record" title="Delete">
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
          text: '<i class="ti tabler-plus me-sm-1"></i> <span class="d-none d-sm-inline-block">'+language_key['Add Item']+'</span>',
          className: 'btn create-new btn-primary',
          action: function (e, dt, node, config) {
            window.location.href = base_url + '/item/create';
          }
        }
      ],
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      pageLength: 10,
      order: [[0, 'desc']]
    });
  }

  // <button type="button" data-item-id="${data.encrypted_id}" data-item-name="${data.name || ''}" data-item-code="${data.code || ''}" data-item-price="${data.sale_price_raw || 0}" data-item-type="${data.type || ''}" class="btn btn-sm btn-icon print-barcode-btn" title="Print Barcode">
  //               <i class="ti tabler-barcode"></i>
  //             </button>

  // Store selected items for barcode printing
  let selectedItemsForPrint = [];
  let currentItemType = '';
  let currentVariations = [];
  let originalItemData = null;

  // Handle Print Label button click
  $(document).on('click', '.print-label-btn', function() {
    const $btn = $(this);
    const itemData = {
      id: $btn.data('item-id'),
      name: $btn.data('item-name') || '',
      code: $btn.data('item-code') || '',
      price: parseFloat($btn.data('item-price')) || 0,
      mrp_price: parseFloat($btn.data('mrp-price')) || 0,
      type: $btn.data('item-type') || ''
    };
    
    // Normalize type (handle both "Variation Product" and "Variation_Product")
    const normalizedType = itemData.type.replace(/\s+/g, '_');
    currentItemType = normalizedType;
    originalItemData = JSON.parse(JSON.stringify(itemData)); // Deep copy
    selectedItemsForPrint = [itemData];
    
    // Set item data in modal
    $('#barcodeModalItemName').text(itemData.name || 'N/A');
    $('#barcodeModalItemCode').text(itemData.code || 'N/A');
    $('#barcodeModalItemPrice').text(isNaN(itemData.price) ? '0.00' : itemData.price.toFixed(2));
    
    // Show modal first
    $('#barcodePrintModal').modal('show');
    
    // Fetch variations if Variation_Product
    if (normalizedType === 'Variation_Product') {
      fetchItemVariations(itemData.id, function(variations) {
        currentVariations = variations || [];
        setupVariationSelect();
        // Update preview after modal is shown
        setTimeout(function() {
          updateBarcodeModalPreview();
        }, 300);
      });
    } else {
      currentVariations = [];
      setupVariationSelect();
      // Update preview after modal is shown
      setTimeout(function() {
        updateBarcodeModalPreview();
      }, 300);
    }
  });

  // Handle Print Barcode button click
  $(document).on('click', '.print-barcode-btn', function() {
    const $btn = $(this);
    const itemData = {
      id: $btn.data('item-id'),
      name: $btn.data('item-name') || '',
      code: $btn.data('item-code') || '',
      price: parseFloat($btn.data('item-price')) || 0,
      mrp_price: parseFloat($btn.data('mrp-price')) || 0,
      type: $btn.data('item-type') || ''
    };
    
    // Normalize type (handle both "Variation Product" and "Variation_Product")
    const normalizedType = itemData.type.replace(/\s+/g, '_');
    currentItemType = normalizedType;
    originalItemData = JSON.parse(JSON.stringify(itemData)); // Deep copy
    selectedItemsForPrint = [itemData];
    
    // Set item data in modal
    $('#barcodeModalItemName').text(itemData.name || 'N/A');
    $('#barcodeModalItemCode').text(itemData.code || 'N/A');
    $('#barcodeModalItemPrice').text(isNaN(itemData.price) ? '0.00' : itemData.price.toFixed(2));
    
    // Show modal first
    $('#barcodePrintModal').modal('show');
    
    // Fetch variations if Variation_Product
    if (normalizedType === 'Variation_Product') {
      fetchItemVariations(itemData.id, function(variations) {
        currentVariations = variations || [];
        setupVariationSelect();
        // Update preview after modal is shown
        setTimeout(function() {
          updateBarcodeModalPreview();
        }, 300);
      });
    } else {
      currentVariations = [];
      setupVariationSelect();
      // Update preview after modal is shown
      setTimeout(function() {
        updateBarcodeModalPreview();
      }, 300);
    }
  });

  // Fetch item variations via AJAX
  function fetchItemVariations(itemEncryptedId, callback) {
    const variationsUrl = route('item.variations', { item: itemEncryptedId }, false, Ziggy);
    $.ajax({
      url: base_url + variationsUrl,
      type: 'GET',
      dataType: 'json',
      success: function(response) {
        if (typeof callback === 'function') {
          callback(response.variations || []);
        }
      },
      error: function() {
        if (typeof callback === 'function') {
          callback([]);
        }
      }
    });
  }

  // Setup variation select dropdown
  function setupVariationSelect() {
    const variationSelect = $('#modalVariationSelect');
    const container = $('#variationSelectContainer');
    
    // Normalize currentItemType for comparison (it should already be normalized, but double-check)
    const normalizedType = currentItemType ? currentItemType.replace(/\s+/g, '_') : '';
    
    if (normalizedType === 'Variation_Product' && currentVariations && currentVariations.length > 0) {
      container.show();
      variationSelect.empty();
      variationSelect.append('<option value="">Use Parent Item</option>');
      
      currentVariations.forEach(function(variation) {
        variationSelect.append(`<option value="${variation.item_code}" data-name="${variation.variation_name}" data-price="${variation.sale_price || 0}" data-mrp-price="${variation.mrp_price || 0}">${variation.variation_name} (${variation.item_code})</option>`);
      });
    } else {
      container.hide();
      variationSelect.empty();
      variationSelect.append('<option value="">Use Parent Item</option>');
    }
  }

  // Handle variation selection change
  $(document).on('change', '#modalVariationSelect', function() {
    const selectedOption = $(this).find('option:selected');
    if (selectedOption.val() && selectedItemsForPrint.length > 0) {
      const itemData = selectedItemsForPrint[0];
      itemData.code = selectedOption.val();
      itemData.name = selectedOption.data('name') || itemData.name;
      itemData.price = parseFloat(selectedOption.data('price')) || itemData.price;
      
      // Update modal display
      $('#barcodeModalItemName').text(itemData.name || 'N/A');
      $('#barcodeModalItemCode').text(itemData.code || 'N/A');
      $('#barcodeModalItemPrice').text(isNaN(itemData.price) ? '0.00' : itemData.price.toFixed(2));
      
      updateBarcodeModalPreview();
    } else {
      // Reset to parent item
      if (originalItemData && selectedItemsForPrint.length > 0) {
        const itemData = selectedItemsForPrint[0];
        itemData.code = originalItemData.code;
        itemData.name = originalItemData.name;
        itemData.price = originalItemData.price;
        
        $('#barcodeModalItemName').text(itemData.name || 'N/A');
        $('#barcodeModalItemCode').text(itemData.code || 'N/A');
        $('#barcodeModalItemPrice').text(isNaN(itemData.price) ? '0.00' : itemData.price.toFixed(2));
        
        updateBarcodeModalPreview();
      }
    }
  });

  // Update barcode preview in modal
  function updateBarcodeModalPreview() {
    if (selectedItemsForPrint.length === 0) return;
    
    const itemData = selectedItemsForPrint[0];
    const showItemName = $('#modalShowItemName').is(':checked');
    const showItemCode = $('#modalShowItemCode').is(':checked');
    const showPrice = $('#modalShowPrice').is(':checked');
    const showBarcode = $('#modalShowBarcode').is(':checked');
    
    const itemNameFontSize = $('#modalItemNameFontSize').val() || 14;
    const itemCodeFontSize = $('#modalItemCodeFontSize').val() || 14;
    const priceFontSize = $('#modalPriceFontSize').val() || 14;
    const barcodeFontSize = $('#modalBarcodeFontSize').val() || 12;
    const barcodeWidth = parseFloat($('#modalBarcodeWidth').val()) || 1;
    const barcodeHeight = parseInt($('#modalBarcodeHeight').val()) || 50;
    
    // Ensure valid values
    const itemName = itemData.name || 'N/A';
    const itemCode = itemData.code || '';
    const itemPrice = isNaN(itemData.price) ? 0 : parseFloat(itemData.price);
    
    let html = '<div class="barcode-print-area">';
    
    if (showItemName) {
      html += `<div style="font-size: ${itemNameFontSize}px; font-weight: bold; margin-bottom: 5px;">${itemName}</div>`;
    }
    
    if (showItemCode) {
      html += `<div style="font-size: ${itemCodeFontSize}px; margin-bottom: 5px;">Code: ${itemCode}</div>`;
    }
    
    if (showPrice) {
      html += `<div style="font-size: ${priceFontSize}px; margin-bottom: 10px;">Price: ${itemPrice.toFixed(2)}</div>`;
    }
    
    if (showBarcode && itemCode) {
      html += `<svg id="barcode-modal-svg"></svg>`;
    }
    
    html += '</div>';
    
    $('#barcodeModalPreview').html(html);
    
    // Generate barcode if enabled and code exists
    if (showBarcode && itemCode) {
      function generateModalBarcode() {
        if (typeof JsBarcode === 'undefined') {
          setTimeout(generateModalBarcode, 100);
          return;
        }
        
        const svgElement = document.getElementById('barcode-modal-svg');
        if (svgElement) {
          try {
            JsBarcode(svgElement, itemCode, {
              format: "CODE128",
              width: barcodeWidth,
              height: barcodeHeight,
              displayValue: true,
              fontSize: parseInt(barcodeFontSize) || 12,
              margin: 10
            });
          } catch (e) {
            console.error('Barcode generation error:', e);
          }
        } else {
          setTimeout(generateModalBarcode, 50);
        }
      }
      
      setTimeout(generateModalBarcode, 50);
    }
  }

  // Auto-update preview when controls change
  $(document).on('change input', '#modalShowItemName, #modalShowItemCode, #modalShowPrice, #modalShowBarcode, #modalItemNameFontSize, #modalItemCodeFontSize, #modalPriceFontSize, #modalBarcodeFontSize, #modalBarcodeWidth, #modalBarcodeHeight', function() {
    updateBarcodeModalPreview();
  });

  // Regenerate barcode when modal is fully shown
  $('#barcodePrintModal').on('shown.bs.modal', function() {
    setTimeout(function() {
      updateBarcodeModalPreview();
    }, 100);
  });

  // Print Barcode from modal
  $(document).on('click', '#modalPrintBarcode', function() {
    if (selectedItemsForPrint.length === 0) return;
    
    const printWindow = window.open('', '_blank');
    const showItemName = $('#modalShowItemName').is(':checked');
    const showItemCode = $('#modalShowItemCode').is(':checked');
    const showPrice = $('#modalShowPrice').is(':checked');
    const showBarcode = $('#modalShowBarcode').is(':checked');
    
    const itemNameFontSize = $('#modalItemNameFontSize').val() || 14;
    const itemCodeFontSize = $('#modalItemCodeFontSize').val() || 14;
    const priceFontSize = $('#modalPriceFontSize').val() || 14;
    const barcodeFontSize = $('#modalBarcodeFontSize').val() || 12;
    const barcodeWidth = parseFloat($('#modalBarcodeWidth').val()) || 1;
    const barcodeHeight = parseInt($('#modalBarcodeHeight').val()) || 50;
    const barcodeQuantity = parseInt($('#modalBarcodeQuantity').val()) || 1;
    
    let html = `
      <!DOCTYPE html>
      <html>
      <head>
        <title>Product Barcode</title>
        <style>
          body { 
            margin: 0; 
            padding: 20px; 
            font-family: Arial, sans-serif;
          }
          .barcode-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: flex-start;
          }
          .barcode-item {
            border: 2px solid #000;
            padding: 20px;
            text-align: center;
            width: calc(50% - 10px);
            min-width: 300px;
            page-break-inside: avoid;
          }
          @media print {
            .barcode-item {
              width: calc(50% - 10px);
            }
          }
        </style>
      </head>
      <body>
        <div class="barcode-container">
    `;
    
    // Generate barcodes - create multiple copies based on quantity
    let barcodeIndex = 0;
    selectedItemsForPrint.forEach((itemData) => {
      for (let qty = 0; qty < barcodeQuantity; qty++) {
        html += '<div class="barcode-item">';
        
        if (showItemName) {
          html += `<div style="font-size: ${itemNameFontSize}px; font-weight: bold; margin-bottom: 10px;">${itemData.name}</div>`;
        }
        
        const itemCode = itemData.code || '';
        const itemPrice = isNaN(itemData.price) ? 0 : parseFloat(itemData.price);
        
        if (showItemCode && itemCode) {
          html += `<div style="font-size: ${itemCodeFontSize}px; margin-bottom: 10px;">Code: ${itemCode}</div>`;
        }
        
        if (showPrice) {
          html += `<div style="font-size: ${priceFontSize}px; margin-bottom: 15px;">Price: ${itemPrice.toFixed(2)}</div>`;
        }
        
        if (showBarcode && itemCode) {
          html += `<svg id="barcode-product-${barcodeIndex}"></svg>`;
        }
        
        html += '</div>';
        barcodeIndex++;
      }
    });
    
    const itemsData = JSON.stringify(selectedItemsForPrint);
    const barcodeConfig = {
      format: "CODE128",
      width: barcodeWidth,
      height: barcodeHeight,
      displayValue: true,
      fontSize: parseInt(barcodeFontSize) || 12,
      margin: 15
    };
    const quantity = barcodeQuantity;
    
    html += `
        </div>
        <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"><\/script>
        <script>
          function generateBarcodes() {
            if (typeof JsBarcode === 'undefined') {
              setTimeout(generateBarcodes, 100);
              return;
            }
            
            ${showBarcode ? `var items = ${itemsData};
            var config = ${JSON.stringify(barcodeConfig)};
            var quantity = ${quantity};
            var barcodeIndex = 0;
            
            items.forEach(function(item) {
              if (item.code) {
                for (var qty = 0; qty < quantity; qty++) {
                  var svgElement = document.getElementById("barcode-product-" + barcodeIndex);
                  if (svgElement) {
                    try {
                      JsBarcode(svgElement, item.code, config);
                    } catch (e) {
                      console.error('Barcode error for item ' + barcodeIndex + ':', e);
                    }
                  }
                  barcodeIndex++;
                }
              }
            });` : ''}
          }
          
          if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(generateBarcodes, 100);
          } else {
            window.addEventListener('load', generateBarcodes);
            document.addEventListener('DOMContentLoaded', generateBarcodes);
          }
        <\/script>
      </body>
      </html>
    `;
    
    printWindow.document.write(html);
    printWindow.document.close();
    
    // Wait for content and barcode generation before printing
    printWindow.onload = function() {
      setTimeout(function() {
        printWindow.print();
      }, 500);
    };
  });
});
