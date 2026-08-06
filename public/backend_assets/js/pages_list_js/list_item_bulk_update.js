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

  // Store changes for bulk update
  let bulkUpdateData = {};
  let itemCropperInstance = null;
  let currentCroppingItemId = null;
  let currentCroppingImageSrc = null;
  let dt_bulk_update_table = null;

  // Initialize DataTable for bulk update
  const dt_bulk_update = $('.datatables-basic-bulk-update');
  if (dt_bulk_update.length) {
    dt_bulk_update_table = new DataTable(dt_bulk_update, {
      processing: true,
      serverSide: true,
      ajax: {
        url: base_url + '/item',
        type: 'GET',
        data: function(d) {
          d.bulk_update = 'true';
        },
        error: function(xhr, error, thrown) { console.error('list_item_bulk_update AJAX error:', error, thrown); }
      },
      columns: [
        { 
          data: null,
          orderable: false,
          searchable: false,
          render: function(data, type, row) {
            const itemId = row.encrypted_id || '';
            return `<div class="form-check ps-0">
                      <input class="form-check-input bulk-select-checkbox" 
                             type="checkbox" 
                             value="${itemId}"
                             data-item-id="${itemId}">
                    </div>`;
          }
        },
        { 
          data: 'id',
          render: function(data, type, row, meta) {
            return meta.row + meta.settings._iDisplayStart + 1;
          }
        },
        { 
          data: 'name',
          render: function(data, type, row) {
            return data || 'N/A';
          }
        },
        { 
          data: null,
          orderable: false,
          render: function(data, type, row) {
            const itemId = row.encrypted_id || '';
            const purchasePrice = row.purchase_price_raw ? parseFloat(row.purchase_price_raw) : 0;
            return `<input type="text" 
                     class="form-control editable-price purchase-price-input number-input" 
                     data-item-id="${itemId}"
                     value="${purchasePrice.toFixed(3)}"
                     data-original="${purchasePrice.toFixed(3)}">`;
          }
        },
        { 
          data: null,
          orderable: false,
          render: function(data, type, row) {
            const itemId = row.encrypted_id || '';
            const mrpPrice = parseFloat(row.mrp_price) || 0;
            return `<input type="text"
                     class="form-control number-input editable-price mrp-price-input" 
                     data-item-id="${itemId}"
                     value="${mrpPrice.toFixed(2)}"
                     data-original="${mrpPrice.toFixed(2)}">`;
          }
        },
        { 
          data: null,
          orderable: false,
          render: function(data, type, row) {
            const itemId = row.encrypted_id || '';
            // Use raw sale price if available, otherwise parse from formatted string
            const currentSalePrice = row.sale_price_raw ? parseFloat(row.sale_price_raw) : (parseFloat(String(row.sale_price).replace(/[^\d.]/g, '')) || 0);
            return `<input type="text" 
                     class="form-control editable-price sale-price-input number-input" 
                     data-item-id="${itemId}"
                     value="${currentSalePrice.toFixed(2)}"
                     data-original="${currentSalePrice.toFixed(2)}">`;
          }
        },
        { 
          data: null,
          orderable: false,
          render: function(data, type, row) {
            const itemId = row.encrypted_id || '';
            const wholeSalePrice = parseFloat(row.whole_sale_price) || 0;
            return `<input type="text"
                     class="form-control number-input editable-price whole-sale-price-input" 
                     data-item-id="${itemId}"
                     value="${wholeSalePrice.toFixed(2)}"
                     data-original="${wholeSalePrice.toFixed(2)}">`;
          }
        },
        { 
          data: null,
          orderable: false,
          render: function(data, type, row) {
            const itemId = row.encrypted_id || '';
            const enableStatus = row.enable_disable_status === 'Enable' ? true : false;
            return `<label class="switch switch-primary" data-item-id="${itemId}">
                      <input type="checkbox" class="switch-input" ${enableStatus ? 'checked' : ''} data-original="${enableStatus ? '1' : '0'}" data-item-id="${itemId}">
                      <span class="switch-toggle-slider">
                        <span class="switch-on">
                          <i class="icon-base ti tabler-check"></i>
                        </span>
                        <span class="switch-off">
                          <i class="icon-base ti tabler-x"></i>
                        </span>
                      </span>
                    </label>`;
          }
        },
        { 
          data: null,
          orderable: false,
          render: function(data, type, row) {
            const itemId = row.encrypted_id || '';
            const hasImage = row.photo ? true : false;
            const imagePath = row.photo ? base_url + '/uploads/items/' + row.photo : base_url + '/uploads/dummy_images/default-picture.png';
            return `<div class="image-upload-wrapper" style="position: relative; display: inline-block;">
                     <img src="${imagePath}" 
                          alt="Item Image" 
                          class="item-image-preview" 
                          data-item-id="${itemId}"
                          data-image-path="${imagePath}"
                          data-has-image="${hasImage ? '1' : '0'}"
                          style="width: 60px; height: 60px; object-fit: cover; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;">
                     <div class="image-upload-btn" data-item-id="${itemId}" title="Upload New Image">
                       <i class="ti tabler-plus" style="font-size: 12px;"></i>
                     </div>
                     <input type="file" 
                            class="item-image-file-input" 
                            data-item-id="${itemId}"
                            accept="image/*" 
                            style="display: none;">
            </div>`;
          }
        }
      ],
      dom: '<"card-header border-bottom p-3"<"head-label"><"dt-action-buttons text-end"B>>' +
          '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
          '<"row"<"col-sm-12"tr>>' +
          '<"row"<"col-sm-12 col-md-5 d-flex align-items-center"i><"col-sm-12 col-md-7 d-flex align-items-center justify-content-end"p>>',
      buttons: [],
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      pageLength: 10,
      order: [[0, 'desc']]
    });
  }

  // Track changes in input fields
  $(document).on('change blur', '.purchase-price-input, .sale-price-input, .mrp-price-input, .whole-sale-price-input', function() {
    const $input = $(this);
    const itemId = $input.data('item-id');
    const originalValue = parseFloat($input.data('original')) || 0;
    const newValue = parseFloat($input.val()) || 0;
    
    if (itemId) {
      if (newValue !== originalValue) {
        if (!bulkUpdateData[itemId]) {
          bulkUpdateData[itemId] = {};
        }
        if ($input.hasClass('purchase-price-input')) {
          bulkUpdateData[itemId].purchase_price = newValue;
        } else if ($input.hasClass('sale-price-input')) {
          bulkUpdateData[itemId].sale_price = newValue;
        } else if ($input.hasClass('mrp-price-input')) {
          bulkUpdateData[itemId].mrp_price = newValue;
        } else if ($input.hasClass('whole-sale-price-input')) {
          bulkUpdateData[itemId].whole_sale_price = newValue;
        }
        $input.addClass('border-warning');
      } else {
        if (bulkUpdateData[itemId]) {
          if ($input.hasClass('purchase-price-input')) {
            delete bulkUpdateData[itemId].purchase_price;
          } else if ($input.hasClass('sale-price-input')) {
            delete bulkUpdateData[itemId].sale_price;
          } else if ($input.hasClass('mrp-price-input')) {
            delete bulkUpdateData[itemId].mrp_price;
          } else if ($input.hasClass('whole-sale-price-input')) {
            delete bulkUpdateData[itemId].whole_sale_price;
          }
          if (Object.keys(bulkUpdateData[itemId]).length === 0) {
            delete bulkUpdateData[itemId];
          }
        }
        $input.removeClass('border-warning');
      }
    }
  });

  // Track changes in enable status switch
  // Use a more reliable approach - listen on the label click which is the actual clickable area
  $(document).on('click', 'label.switch[data-item-id]', function(e) {
    // Don't prevent default - let the label toggle the checkbox naturally
    const $label = $(this);
    const itemId = $label.data('item-id');
    const $switch = $label.find('.switch-input[data-item-id]');
    
    if (!$switch.length || !itemId) {
      return;
    }
    
    // Wait for the checkbox state to update after click (label will toggle it)
    setTimeout(function() {
      handleSwitchChange($switch, itemId);
    }, 100);
  });
  
  // Also listen on change event as backup
  $(document).on('change', '.switch-input[data-item-id]', function() {
    const $switch = $(this);
    const itemId = $switch.data('item-id');
    if (itemId) {
      handleSwitchChange($switch, itemId);
    }
  });
  
  // Helper function to handle switch change
  function handleSwitchChange($switch, itemId) {
    if (!itemId) {
      itemId = $switch.data('item-id');
    }
    
    if (!itemId) {
      console.warn('handleSwitchChange: No item ID found');
      return;
    }
    
    // jQuery's .data() converts '1' to number 1, so we need to handle both
    const originalData = $switch.data('original');
    const originalValue = originalData === '1' || originalData === 1 || originalData === true;
    const newValue = $switch.is(':checked');

    if (newValue !== originalValue) {
      if (!bulkUpdateData[itemId]) {
        bulkUpdateData[itemId] = {};
      }
      bulkUpdateData[itemId].enable_disable_status = newValue ? 'Enable' : 'Disable';
        } else {
      if (bulkUpdateData[itemId]) {
        delete bulkUpdateData[itemId].enable_disable_status;
        if (Object.keys(bulkUpdateData[itemId]).length === 0) {
          delete bulkUpdateData[itemId];
        }
      }
    }
  }

  // Handle image click to open cropper for existing images
  $(document).on('click', '.item-image-preview', function(e) {
    // Don't trigger if clicking on upload button
    if ($(e.target).closest('.image-upload-btn').length) {
      return;
    }
    
    const $img = $(this);
    const itemId = $img.data('item-id');
    const imageSrc = $img.data('image-path');
    
    // Open cropper for existing images (not default placeholder)
    if (imageSrc && !imageSrc.includes('default-picture.png')) {
      currentCroppingItemId = itemId;
      currentCroppingImageSrc = imageSrc;
      showItemCropperModal(imageSrc);
    }
  });

  // Handle upload button click
  $(document).on('click', '.image-upload-btn', function(e) {
    e.stopPropagation();
    const itemId = $(this).data('item-id');
    $(`.item-image-file-input[data-item-id="${itemId}"]`).click();
  });

  // Handle file input change
  $(document).on('change', '.item-image-file-input', function() {
    const $input = $(this);
    const itemId = $input.data('item-id');
    const file = this.files[0];
    
    if (file && file.type.startsWith('image/')) {
      // Check file size (max 5MB)
      if (file.size > 5 * 1024 * 1024) {
        Swal.fire({
          icon: 'error',
          title: 'File Too Large',
          text: 'Image size should be less than 5MB.',
          confirmButtonText: 'OK'
        });
        $input.val('');
        return;
      }
      
      const reader = new FileReader();
      reader.onload = function(e) {
        const imageSrc = e.target.result;
        currentCroppingItemId = itemId;
        currentCroppingImageSrc = imageSrc;
        showItemCropperModal(imageSrc);
        // Reset file input after opening cropper
        $input.val('');
      };
      reader.onerror = function() {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Failed to load image. Please try again.',
          confirmButtonText: 'OK'
        });
        $input.val('');
      };
      reader.readAsDataURL(file);
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Invalid File',
        text: 'Please select a valid image file.',
        confirmButtonText: 'OK'
      });
      $input.val('');
    }
  });

  // Show cropper modal
  function showItemCropperModal(imageSrc) {
    // Remove existing modal if any
    $('#itemCropperModal').remove();
    
    const modalHtml = `
      <div class="modal fade" id="itemCropperModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Crop Item Image</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="img-container">
                <img id="cropperImage" src="${imageSrc}" alt="Item Image" style="max-width: 100%;">
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-primary" id="cropItemImageBtn">Crop Image</button>
            </div>
          </div>
        </div>
      </div>
    `;
    
    $('body').append(modalHtml);
    const modalElement = document.getElementById('itemCropperModal');
    const itemCropperModal = new bootstrap.Modal(modalElement, {
      backdrop: 'static',
      keyboard: false
    });
    
    $(modalElement).on('shown.bs.modal', function () {
      const cropperImage = document.getElementById('cropperImage');
      if (cropperImage && typeof Cropper !== 'undefined') {
        itemCropperInstance = new Cropper(cropperImage, {
          aspectRatio: 1,
          viewMode: 1,
          autoCropArea: 0.8,
          responsive: true,
          restore: false,
          guides: true,
          center: true,
          highlight: false,
          cropBoxMovable: true,
          cropBoxResizable: true,
          toggleDragModeOnDblclick: false
        });
      } else {
        console.error('Cropper image element not found or Cropper library not loaded');
      }
    });
    
    $(modalElement).on('click', '#cropItemImageBtn', function() {
      if (itemCropperInstance && currentCroppingItemId) {
        let canvas = itemCropperInstance.getCroppedCanvas({
          width: 400,
          height: 400,
          imageSmoothingEnabled: true,
          imageSmoothingQuality: 'high',
        });
        
        if (canvas) {
          let croppedImageDataUrl = canvas.toDataURL('image/png');
          
          // Update preview image
          const $previewImg = $(`.item-image-preview[data-item-id="${currentCroppingItemId}"]`);
          $previewImg.attr('src', croppedImageDataUrl);
          $previewImg.data('has-image', '1');
          $previewImg.data('image-path', croppedImageDataUrl);
          
          // Store cropped image data for bulk update
          if (!bulkUpdateData[currentCroppingItemId]) {
            bulkUpdateData[currentCroppingItemId] = {};
          }
          bulkUpdateData[currentCroppingItemId].cropped_image = croppedImageDataUrl;
          
          // Mark image as changed
          $previewImg.addClass('border border-warning');
          $previewImg.css('border-width', '2px');
        }
        
        itemCropperModal.hide();
      }
    });
    
    $(modalElement).on('hidden.bs.modal', function () {
      if (itemCropperInstance) {
        itemCropperInstance.destroy();
        itemCropperInstance = null;
      }
      currentCroppingItemId = null;
      currentCroppingImageSrc = null;
      $(this).remove();
    });
    
    itemCropperModal.show();
  }

  // Save bulk update
  $(document).on('click', '#saveBulkUpdate', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const $btn = $(this);
    const csrf = $('meta[name="csrf-token"]').attr('content');
  
    
    if (Object.keys(bulkUpdateData).length === 0) {
      showErrorNotification('No changes to save.');
      return false;
    }
    
    // Prepare form data
    const formData = new FormData();
    formData.append('_token', csrf);
    formData.append('items', JSON.stringify(bulkUpdateData));
    
    // Add cropped images as files
    Object.keys(bulkUpdateData).forEach(function(itemId) {
      if (bulkUpdateData[itemId].cropped_image) {
        // Convert data URL to blob
        const dataUrl = bulkUpdateData[itemId].cropped_image;
        const blob = dataURLtoBlob(dataUrl);
        formData.append(`images[${itemId}]`, blob, `item-${itemId}.png`);
      }
    });
    
    // Disable button and show loading
    $btn.prop('disabled', true).html('<i class="ti tabler-loader-2"></i> Saving...');
    
    $.ajax({
      url: base_url + '/item/bulk-update',
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      success: function(response) {
        if (response.status === 'success') {

            showSuccessNotification(response.message || 'Items updated successfully.');
          
            // Clear bulk update data
            bulkUpdateData = {};
            // Reload DataTable
            if (dt_bulk_update_table) {
              dt_bulk_update_table.ajax.reload(null, false);
            }
            // Remove warning borders
            $('.border-warning').removeClass('border-warning');
            // Restore scroll position
          
        } else {
          showErrorNotification(response.message || 'Failed to update items.');
        }
      },
      error: function(xhr) {
        let errorMessage = 'Failed to update items.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          errorMessage = xhr.responseJSON.message;
        }
        showErrorNotification(errorMessage);
      },
      complete: function() {
        $btn.prop('disabled', false).html('<i class="ti tabler-device-floppy"></i> Save All Changes');
      }
    });
  });

  // Convert data URL to blob
  function dataURLtoBlob(dataurl) {
    const arr = dataurl.split(',');
    const mime = arr[0].match(/:(.*?);/)[1];
    const bstr = atob(arr[1]);
    let n = bstr.length;
    const u8arr = new Uint8Array(n);
    while (n--) {
      u8arr[n] = bstr.charCodeAt(n);
    }
    return new Blob([u8arr], { type: mime });
  }

  // Select All checkbox
  $(document).on('change', '#selectAllItems', function() {
    const isChecked = $(this).is(':checked');
    $('.bulk-select-checkbox').prop('checked', isChecked);
    updateBulkDeleteButton();
  });

  // Individual checkbox change
  $(document).on('change', '.bulk-select-checkbox', function() {
    updateBulkDeleteButton();
    // Update select all checkbox state
    const totalCheckboxes = $('.bulk-select-checkbox').length;
    const checkedCheckboxes = $('.bulk-select-checkbox:checked').length;
    $('#selectAllItems').prop('checked', totalCheckboxes === checkedCheckboxes && totalCheckboxes > 0);
  });

  // Update bulk delete button visibility
  function updateBulkDeleteButton() {
    const selectedCount = $('.bulk-select-checkbox:checked').length;
    if (selectedCount > 0) {
      $('#bulkDeleteItems').show().html(`<i class="ti tabler-trash"></i> Delete Selected (${selectedCount})`);
    } else {
      $('#bulkDeleteItems').hide();
    }
  }

  // Bulk delete items
  $(document).on('click', '#bulkDeleteItems', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const selectedItems = $('.bulk-select-checkbox:checked');
    const itemIds = selectedItems.map(function() {
      return $(this).data('item-id');
    }).get();
    
    if (itemIds.length === 0) {
      showErrorNotification('Please select at least one item to delete.');
      return false;
    }
    
    Swal.fire({
      title: 'Are you sure?',
      text: `You are about to delete ${itemIds.length} item(s). This action cannot be undone!`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Yes, delete them!',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        const csrf = $('meta[name="csrf-token"]').attr('content');
        const $btn = $('#bulkDeleteItems');
        
        $btn.prop('disabled', true).html('<i class="ti tabler-loader-2"></i> Deleting...');
        
        $.ajax({
          url: base_url + '/item/bulk-delete',
          type: 'POST',
          data: {
            _token: csrf,
            item_ids: itemIds
          },
          success: function(response) {
            if (response.status === 'success') {
              showSuccessNotification(response.message || 'Items deleted successfully.');
              // Reload DataTable
              if (dt_bulk_update_table) {
                dt_bulk_update_table.ajax.reload(null, false);
              }
              // Reset checkboxes
              $('#selectAllItems').prop('checked', false);
              $('#bulkDeleteItems').hide();
            } else {
              showErrorNotification(response.message || 'Failed to delete items.');
            }
          },
          error: function(xhr) {
            let errorMessage = 'Failed to delete items.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
              errorMessage = xhr.responseJSON.message;
            }
            showErrorNotification(errorMessage);
          },
          complete: function() {
            $btn.prop('disabled', false);
            updateBulkDeleteButton();
          }
        });
      }
    });
  });
});
