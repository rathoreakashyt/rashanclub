/**
 * POS Cart Manager
 * Handles cart operations for POS system
 */

class POSCartManager {
    constructor() {
        this.cartItems = [];
        this.inputTimeouts = {}; // Store timeouts for debouncing
        this.editingFields = new Set(); // Track which fields are being edited
        this.cartSummaryDiscount = 0;
        this.cartSummaryDiscountType = 'fixed';
        this.cartSummaryShipping = 0;
        this.init();
        this.company_session_data = this.getCompanySessionData() || {};
    }

    getCompanySessionData() {
        try {
            let company_data =  JSON.parse($('#company_data').val()) || {};
            company_data.default_employee_id = $('.default_employee_id').val() || null;
            return company_data;
        } catch (e) {
            console.error('Error parsing company info:', e);
            return {};
        }
    }

    /**
     * Initialize cart
     */
    init() {
        this.loadCartFromStorage();
        // Recalculate totals for all items after loading from storage
        this.recalculateCartTotals();
        this.renderCart(); // This will also update the cart count badge
        this.updateCartSummary(); // This will save summary to localStorage
        this.setupEventListeners();
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        const self = this;
        
        // Cart item quantity change - use debounced input and blur for better responsiveness
        $(document).on('input', '.item-qty', function(e) {
            const $input = $(this);
            const row = $input.closest('tr');
            const productId = row.data('product-id');
            const cartIndex = row.data('cart-index');
            const fieldKey = `qty-${cartIndex}`;
            
            // Mark field as being edited
            self.editingFields.add(fieldKey);
            
            // Clear existing timeout
            if (self.inputTimeouts[fieldKey]) {
                clearTimeout(self.inputTimeouts[fieldKey]);
            }
            
            // Debounce the update
            self.inputTimeouts[fieldKey] = setTimeout(() => {
                let quantity = parseFloat($input.val()) || 1;
                
                // Ensure minimum quantity is 1
                if (quantity < 1) {
                    quantity = 1;
                    $input.val(1);
                }
                
                self.updateItemQuantity(productId, quantity, false, cartIndex); // false = don't re-render
                self.editingFields.delete(fieldKey);
            }, 300);
        });

        // Handle blur for immediate update when user leaves field
        $(document).on('blur', '.item-qty', function(e) {
            const $input = $(this);
            const row = $input.closest('tr');
            const productId = row.data('product-id');
            const cartIndex = row.data('cart-index');
            const fieldKey = `qty-${cartIndex}`;
            
            // Clear timeout if exists
            if (self.inputTimeouts[fieldKey]) {
                clearTimeout(self.inputTimeouts[fieldKey]);
            }
            
            let quantity = parseFloat($input.val()) || 1;
            if (quantity < 1) {
                quantity = 1;
                $input.val(1);
            }
            
            self.updateItemQuantity(productId, quantity, true, cartIndex); // true = re-render
            self.editingFields.delete(fieldKey);
        });

        // Cart item price change - use debounced input and blur
        $(document).on('input', '.unit-price', function(e) {
            const $input = $(this);
            const row = $input.closest('tr');
            const productId = row.data('product-id');
            const fieldKey = `price-${productId}`;
            
            // Mark field as being edited
            self.editingFields.add(fieldKey);
            
            // Clear existing timeout
            if (self.inputTimeouts[fieldKey]) {
                clearTimeout(self.inputTimeouts[fieldKey]);
            }
            
            // Debounce the update
            self.inputTimeouts[fieldKey] = setTimeout(() => {
                const price = parseFloat($input.val()) || 0;
                self.updateItemPrice(productId, price, false); // false = don't re-render
                self.editingFields.delete(fieldKey);
            }, 300);
        });

        // Handle blur for immediate update when user leaves field
        $(document).on('blur', '.unit-price', function(e) {
            const $input = $(this);
            const row = $input.closest('tr');
            const productId = row.data('product-id');
            const fieldKey = `price-${productId}`;
            
            // Clear timeout if exists
            if (self.inputTimeouts[fieldKey]) {
                clearTimeout(self.inputTimeouts[fieldKey]);
            }
            
            const price = parseFloat($input.val()) || 0;
            self.updateItemPrice(productId, price, true); // true = re-render
            self.editingFields.delete(fieldKey);
        });

        // Cart item discount change - use debounced input and blur, support % symbol
        $(document).on('input', '.item-discount', function () {
            const $input = $(this);
            const row = $input.closest('tr');
            const productId = row.data('product-id');
            const fieldKey = `discount-${productId}`;
        
            self.editingFields.add(fieldKey);
        
            if (self.inputTimeouts[fieldKey]) {
                clearTimeout(self.inputTimeouts[fieldKey]);
            }
        
            self.inputTimeouts[fieldKey] = setTimeout(() => {
                const raw = $input.val().toString().trim();
        
                let discountType = 'fixed';
                let discount = 0;
        
                if (raw.includes('%')) {
                    discountType = 'percentage';
                    discount = parseFloat(raw.replace('%', '')) || 0;
                } else if (raw !== '') {
                    discountType = 'fixed';
                    discount = parseFloat(raw) || 0;
                }
        
                const item = self.cartItems.find(
                    i => String(i.product_id) === String(productId)
                );
        
                if (item) {
                    item.discount_type = discountType;
                }
        
                self.updateItemDiscount(productId, discount, false);
                self.editingFields.delete(fieldKey);
            }, 300);
        });
        

        $(document).on('blur', '.item-discount', function () {
            const $input = $(this);
            const row = $input.closest('tr');
            const productId = row.data('product-id');
            const fieldKey = `discount-${productId}`;
        
            if (self.inputTimeouts[fieldKey]) {
                clearTimeout(self.inputTimeouts[fieldKey]);
            }
        
            const raw = $input.val().toString().trim();
        
            let discountType = 'fixed';
            let discount = 0;
        
            if (raw.includes('%')) {
                discountType = 'percentage';
                discount = parseFloat(raw.replace('%', '')) || 0;
            } else if (raw !== '') {
                discountType = 'fixed';
                discount = parseFloat(raw) || 0;
            }
        
            if (raw === '') {
                $input.val('');
            }
        
            const item = self.cartItems.find(
                i => String(i.product_id) === String(productId)
            );
        
            if (item) {
                item.discount_type = discountType;
            }
        
            self.updateItemDiscount(productId, discount, true);
            self.editingFields.delete(fieldKey);
        });

        // Remove item from cart - use event delegation with proper context
        $(document).on('click', '.cart-item-remove', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $button = $(this);
            const row = $button.closest('tr');
            const productId = row.data('product-id');
            const cartIndex = row.data('cart-index');
            
            if (productId !== undefined && productId !== null) {
                self.removeItem(productId, cartIndex);
            }
        });

        // Cart summary discount type change
        $(document).on('change', '#pos-cart-discount-type', function(e) {
            const discountType = $(this).val() || 'fixed';
            self.cartSummaryDiscountType = discountType;
            
            // Update display format based on type
            const $discountInput = $('#pos-cart-discount-input');
            const currentValue = $discountInput.val().toString().trim();
            const cleanValue = currentValue.replace(/%/g, '');
            const discount = parseFloat(cleanValue) || 0;
            
            // Only format if value exists and is valid
            // Don't force % symbol - let user control it
            if (discount > 0) {
                // If switching to percentage and no % in input, add it
                // If switching to fixed and % in input, remove it
                if (discountType === 'percentage' && !currentValue.includes('%')) {
                    $discountInput.val(discount + '%');
                } else if (discountType === 'fixed' && currentValue.includes('%')) {
                    $discountInput.val(cleanValue);
                }
            }
            
            self.updateCartSummary();
        });

        // Cart summary discount input - debounced
        let summaryDiscountTimeout;
        $(document).on('input', '#pos-cart-discount-input', function(e) {
            const $input = $(this);
            
            // Clear existing timeout
            if (summaryDiscountTimeout) {
                clearTimeout(summaryDiscountTimeout);
            }
            
            // Debounce the update
            summaryDiscountTimeout = setTimeout(() => {
                const discountInput = $input.val().toString().trim();
                
                // Auto-detect discount type based on % symbol
                let discountType = $('#pos-cart-discount-type').val() || 'fixed';
                if (discountInput.includes('%')) {
                    discountType = 'percentage';
                    $('#pos-cart-discount-type').val('percentage');
                }
                
                // Parse discount value (remove % if present)
                let discount = 0;
                if (discountInput && discountInput !== '0' && discountInput !== '') {
                    const cleanValue = discountInput.replace(/%/g, '');
                    discount = parseFloat(cleanValue) || 0;
                }
                
                self.cartSummaryDiscount = discount;
                self.cartSummaryDiscountType = discountType;
                self.updateCartSummary();
            }, 300);
        });

        // Cart summary discount blur - immediate update and format
        $(document).on('blur', '#pos-cart-discount-input', function(e) {
            const $input = $(this);
            
            // Clear timeout if exists
            if (summaryDiscountTimeout) {
                clearTimeout(summaryDiscountTimeout);
            }
            
            const discountInput = $input.val().toString().trim();
            // Auto-detect discount type based on % symbol
            let discountType = $('#pos-cart-discount-type').val() || 'fixed';
            if (discountInput.includes('%')) {
                discountType = 'percentage';
                $('#pos-cart-discount-type').val('percentage');
            }
            
            // Parse discount value (remove % if present)
            let discount = 0;
            if (discountInput && discountInput !== '0' && discountInput !== '') {
                const cleanValue = discountInput.replace(/%/g, '');
                discount = parseFloat(cleanValue) || 0;
            }
            
            // Don't force % symbol - preserve what user typed (10 or 10%)
            // Only update if value is empty or invalid
            if (discountInput === '' || discountInput === '0') {
                $input.val('0');
            }
            // Otherwise keep user's input as-is (with or without %)
            
            self.cartSummaryDiscount = discount;
            self.cartSummaryDiscountType = discountType;
            self.updateCartSummary();
        });

        // Cart summary shipping input - debounced
        let shippingTimeout;
        $(document).on('input', '#pos-shipping-input', function(e) {
            const $input = $(this);
            
            // Clear existing timeout
            if (shippingTimeout) {
                clearTimeout(shippingTimeout);
            }
            
            // Debounce the update
            shippingTimeout = setTimeout(() => {
                const shipping = parseFloat($input.val()) || 0;
                self.cartSummaryShipping = shipping;
                self.updateCartSummary();
            }, 300);
        });

        // Cart summary shipping blur - immediate update
        $(document).on('blur', '#pos-shipping-input', function(e) {
            const $input = $(this);
            
            // Clear timeout if exists
            if (shippingTimeout) {
                clearTimeout(shippingTimeout);
            }
            
            const shipping = parseFloat($input.val()) || 0;
            self.cartSummaryShipping = shipping;
            self.updateCartSummary();
        });

        // Tax breakdown modal click handler
        $(document).on('click', '.tax-view-btn', function(e) {
            e.preventDefault();
            self.showTaxBreakdown();
        });

        // Recalculate tax when customer changes (GST intra/inter-state depends on customer state)
        $(document).on('change', '#customer-select', function() {
            self.updateCartSummary();
        });
    }

    /**
     * Show tax breakdown modal
     */
    showTaxBreakdown() {
        const taxBreakdown = this.getCartTaxBreakdown();
        const $tbody = $('#pos-tax-breakdown-tbody');
        
        $tbody.empty();
        
        if (taxBreakdown.length === 0) {
            $tbody.append('<tr><td colspan="2" class="text-center text-muted">No tax applied</td></tr>');
        } else {
            taxBreakdown.forEach(tax => {
                const row = $(`
                    <tr>
                        <td>${this.escapeHtml(tax.name)}</td>
                        <td class="text-end">${this.formatNumber(tax.amount)}</td>
                    </tr>
                `);
                $tbody.append(row);
            });
        }
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('modal_pos_tax_breakdown'));
        modal.show();
    }

    /**
     * Add item to cart
     * @param {Object} cartItem - The cart item to add
     * @param {Boolean} replaceIfExists - If true, replace existing item instead of adding quantity
     */
    addItem(cartItem, replaceIfExists = false) {
        // Ensure cartItem has tax_information (default to empty array if missing)
        if (!cartItem.tax_information || !Array.isArray(cartItem.tax_information)) {
            cartItem.tax_information = [];
        }
        
        // Check if item already exists in cart - distinguish main vs free items when same product_id (Buy X get X)
        const isFreeItem = cartItem.is_promotion_free_item === true;
        const existingIndex = this.cartItems.findIndex(item => {
            if (String(item.product_id) !== String(cartItem.product_id)) return false;
            if (isFreeItem) {
                return item.is_promotion_free_item === true && item.promotion_main_item_id == cartItem.promotion_main_item_id;
            }
            return item.is_promotion_free_item !== true; // Match main item only
        });
        
        if (existingIndex !== -1) {
            if (replaceIfExists) {
                // Replace existing item completely (from modal - General_Product or Variation_Product)
                this.cartItems[existingIndex] = cartItem;
                this.cartItems[existingIndex].total = this.calculateItemTotal(this.cartItems[existingIndex]);
            } else {
                // Update existing item quantity (add to existing)
                this.cartItems[existingIndex].quantity += cartItem.quantity;
                this.cartItems[existingIndex].total = this.calculateItemTotal(this.cartItems[existingIndex]);
            }
            // Recalculate Buy X Get Y free item quantity when main item quantity changes (General_Product or Variation_Product)
            const updatedItem = this.cartItems[existingIndex];
            if (updatedItem.promotion && updatedItem.promotion.type === '3') {
                this.updateBuyXGetYFreeItemQuantity(updatedItem.product_id, updatedItem.quantity).then(() => {
                    this.saveCartToStorage();
                    this.renderCart();
                    this.updateCartSummary();
                }).catch(() => {
                    this.saveCartToStorage();
                    this.renderCart();
                    this.updateCartSummary();
                });
                return;
            }
        } else {
            // Add new item - ensure total is calculated
            cartItem.total = this.calculateItemTotal(cartItem);
            this.cartItems.push(cartItem);
        }

        this.saveCartToStorage();
        this.renderCart();
        this.updateCartSummary(); // This will calculate and display tax
    }

    /**
     * Update item quantity
     * @param {String|Number} productId - Product ID
     * @param {Number} quantity - New quantity
     * @param {Boolean} shouldRender - Whether to re-render cart (default: true)
     * @param {Number} cartIndex - Optional cart index to identify specific item when multiple rows share product_id
     */
    updateItemQuantity(productId, quantity, shouldRender = true, cartIndex = null) {
        // Ensure minimum quantity is 1
        quantity = Math.max(1, quantity);
        
        // Find the specific item: by cartIndex if provided, otherwise first main item with product_id
        let item;
        if (cartIndex != null && cartIndex >= 0 && this.cartItems[cartIndex]) {
            item = this.cartItems[cartIndex];
            if (String(item.product_id) !== String(productId)) item = null;
        }
        if (!item) {
            item = this.cartItems.find(i =>
                String(i.product_id) === String(productId) && i.is_promotion_free_item !== true
            );
        }
        if (item) {
            // Check if this is a promotion free item - don't allow manual quantity change
            if (item.is_promotion_free_item === true) {
                // Free items quantity is managed automatically based on main item
                return;
            }
            

            
            // Check if quantity should be disabled for this product type
            const productType = item.product_type || '';
            const isQuantityDisabled = productType === 'Combo_Product' || 
                                       productType === 'IMEI_Product' || 
                                       productType === 'Serial_Product' ||
                                       (productType === 'Medicine_Product' && item.selected_medicine_expiry && item.selected_medicine_expiry.length > 0);
            
            // Don't allow quantity change if disabled
            if (isQuantityDisabled) {
                return;
            }
            
            item.quantity = quantity;
            item.total = this.calculateItemTotal(item);
            this.saveCartToStorage();
            
            // Handle Buy X Get Y promotion - update free item quantity (async)
            if (item.promotion && String(item.promotion.type) === '3') {
                // Update free item asynchronously; pass shouldRender for partial vs full update
                this.updateBuyXGetYFreeItemQuantity(productId, quantity, shouldRender).then(() => {
                    this.updateCartSummary();
                }).catch(error => {
                    console.error('Error updating Buy X Get Y free item:', error);
                    this.renderCart();
                    this.updateCartSummary();
                });
            } else {
                // No promotion, just update UI normally
                if (!shouldRender) {
                    const rowSelector = cartIndex != null ? `tr[data-cart-index="${cartIndex}"]` : `tr[data-product-id="${productId}"]:not([data-is-free-item="true"])`;
                    const $row = $(rowSelector).first();
                    const $input = $row.find('.item-qty');
                    if ($input.length) {
                        $input.val(quantity);
                    }
                    // Update total display
                    const $total = $row.find('.item-total');
                    if ($total.length) {
                        $total.text(this.formatNumber(item.total));
                    }
                } else {
                    this.renderCart();
                }
            }
            this.updateCartSummary();
        }
    }

    /**
     * Update Buy X Get Y free item quantity based on main item quantity
     * @param {String|Number} mainItemProductId - Main item product ID
     * @param {Number} mainItemQuantity - Main item quantity
     * @param {Boolean} shouldRender - If false, only update free item row (partial) to avoid overwriting main input while typing
     */
    async updateBuyXGetYFreeItemQuantity(mainItemProductId, mainItemQuantity, shouldRender = true) {
        // Get the main item with Buy X Get Y promotion (exclude free items; when multiple rows share product_id, find the one with type 3)
        const mainItem = this.cartItems.find(item =>
            item.product_id == mainItemProductId &&
            item.is_promotion_free_item !== true &&
            item.promotion && String(item.promotion.type) === '3'
        );
        if (!mainItem || !mainItem.promotion || String(mainItem.promotion.type) !== '3') {
            // If we don't have promotion data or it's not Buy X Get Y, remove any existing free item
            // Use removeFreeItemLinkedToMainItem to avoid removing main item when same product (Buy X get X free)
            this.removeFreeItemLinkedToMainItem(mainItemProductId);
            return;
        }
        
        const promotion = mainItem.promotion;
        // buy_qty from API; fallback to qty (DB column) for compatibility
        const buyQty = Math.max(1, Number(promotion.buy_qty ?? promotion.qty) || 1);
        const getQty = Math.max(1, Number(promotion.get_qty) || 1);
        const getItemId = promotion.get_item_id;
        
        if (!getItemId) {
            return; // No free item ID specified
        }
        
        // Calculate free item quantity: Math.floor(mainQuantity / buyQty) * getQty
        // Example: Buy 2 Get 1, if main quantity is 5, free = Math.floor(5/2) * 1 = 2
        const freeItemQuantity = Math.floor(mainItemQuantity / buyQty) * getQty;
        
        // Find existing free item linked to this main item
        const freeItem = this.cartItems.find(item => 
            item.is_promotion_free_item === true && 
            item.promotion_main_item_id == mainItemProductId
        );
        
        if (freeItemQuantity <= 0) {
            // Remove free item if quantity is 0 or less
            // Use removeFreeItemLinkedToMainItem to avoid removing main item when same product (Buy X get X free)
            if (freeItem) {
                this.removeFreeItemLinkedToMainItem(mainItemProductId);
            }
        } else {
            if (freeItem) {
                // Update existing free item quantity
                freeItem.quantity = freeItemQuantity;
                freeItem.total = 0; // Free items always have 0 total
                this.saveCartToStorage();
                if (shouldRender) {
                    this.renderCart();
                } else {
                    // Partial update: only update free item row's quantity display (avoids overwriting main input while typing)
                    const $freeRow = $(`tr[data-is-free-item="true"][data-promotion-main-item-id="${mainItemProductId}"]`);
                    if ($freeRow.length) {
                        $freeRow.find('.item-qty').val(this.formatNumber(freeItemQuantity));
                    }
                }
            } else {
                // Create new free item - need to get product details from IndexedDB
                try {
                    // Ensure IndexedDB is initialized
                    if (typeof posIndexedDB !== 'undefined' && posIndexedDB.isInitialized) {
                        // Use getProductOrVariationById for both General_Product and Variation_Product (free item can be a variation)
                        const getItem = await posIndexedDB.getProductOrVariationById(getItemId);
                        if (getItem) {
                            // Find the index of the main item to insert free item right after it (exclude free items)
                            const mainItemIndex = this.cartItems.findIndex(item =>
                                item.product_id == mainItemProductId && item.is_promotion_free_item !== true
                            );
                            
                            const freeCartItem = {
                                product_id: getItemId,
                                product_name: getItem.name || 'Free Item',
                                product_code: getItem.code || '',
                                product_type: getItem.type || 'Standard',
                                unit_price: 0, // Free item has no price
                                quantity: freeItemQuantity,
                                discount: 0,
                                discount_type: 'fixed',
                                total: 0, // Free item total is always 0
                                tax_information: getItem.tax_information || [],
                                tax_string: getItem.tax_string || '',
                                applicable_tax_id: getItem.applicable_tax_id || null,
                                tax_type: getItem.tax_type || 'Inclusive',
                                is_promotion_free_item: true, // Mark as promotion free item
                                promotion_id: promotion.id,
                                promotion_main_item_id: mainItemProductId, // Link to main item
                                imei_number: [],
                                medicine: [],
                                combo_items: []
                            };
                            
                            // Insert free item right after the main item
                            if (mainItemIndex !== -1) {
                                this.cartItems.splice(mainItemIndex + 1, 0, freeCartItem);
                            } else {
                                // If main item not found, just add to end
                                this.cartItems.push(freeCartItem);
                            }
                            
                            this.saveCartToStorage();
                            this.renderCart();
                        }
                    }
                } catch (error) {
                    console.error('Error creating free item for Buy X Get Y promotion:', error);
                }
            }
        }
    }

    /**
     * Update item price
     * @param {String|Number} productId - Product ID
     * @param {Number} price - New price
     * @param {Boolean} shouldRender - Whether to re-render cart (default: true)
     */
    updateItemPrice(productId, price, shouldRender = true) {
        const item = this.cartItems.find(item => String(item.product_id) === String(productId));
        if (item) {
            // Don't allow price change for promotion free items
            if (item.is_promotion_free_item === true) {
                return;
            }
            
            // Don't allow price change if item has promotion discount (Type 1)
            if (item.has_promotion_discount === true) {
                return;
            }
            
            // Don't allow price change for Buy X Get Y main items (Type 3)
            if (item.promotion && item.promotion.type === '3') {
                return;
            }
            
            item.unit_price = price;
            item.total = this.calculateItemTotal(item);
            this.saveCartToStorage();
            
            // Only update the specific input value if not re-rendering
            if (!shouldRender) {
                const $row = $(`tr[data-product-id="${productId}"]`);
                const $input = $row.find('.unit-price');
                if ($input.length) {
                    $input.val(price);
                }
                // Update total display
                const $total = $row.find('.item-total');
                if ($total.length) {
                    $total.text(this.formatNumber(item.total));
                }
            } else {
                this.renderCart();
            }
            this.updateCartSummary();
        }
    }

    /**
     * Update item discount
     * @param {String|Number} productId - Product ID
     * @param {Number} discount - New discount
     * @param {Boolean} shouldRender - Whether to re-render cart (default: true)
     */
    updateItemDiscount(productId, discount, shouldRender = true) {
        const item = this.cartItems.find(item => String(item.product_id) === String(productId));
        if (item) {
            // Don't allow discount change for promotion items
            if (item.is_promotion_free_item === true || item.has_promotion_discount === true) {
                return;
            }
            
            item.discount = discount;
            item.total = this.calculateItemTotal(item);
            this.saveCartToStorage();
            
            // Only update the total display if not re-rendering (don't update input value while typing)
            if (!shouldRender) {
                const $row = $(`tr[data-product-id="${productId}"]`);
                // Update total display only, keep input value as user typed it
                const $total = $row.find('.item-total');
                if ($total.length) {
                    $total.text(this.formatNumber(item.total));
                }
            } else {
                // On blur, format and re-render to ensure proper display
                this.renderCart();
            }
            this.updateCartSummary();
        }
    }

    /**
     * Remove item from cart
     * @param {String|Number} productId - Product ID
     * @param {Number} cartIndex - Optional cart index to remove specific item when multiple rows share product_id
     */
    removeItem(productId, cartIndex = null) {
        // Resolve the specific item to remove: by cartIndex if provided, otherwise first match
        let itemToRemove;
        if (cartIndex != null && cartIndex >= 0 && this.cartItems[cartIndex]) {
            const candidate = this.cartItems[cartIndex];
            if (String(candidate.product_id) === String(productId)) {
                itemToRemove = candidate;
            }
        }
        if (!itemToRemove) {
            itemToRemove = this.cartItems.find(item => String(item.product_id) === String(productId));
        }
        
        if (!itemToRemove) return;
        
        // If removing a main item with Buy X Get Y promotion, also remove its linked free item only
        if (!itemToRemove.is_promotion_free_item && itemToRemove.promotion && String(itemToRemove.promotion.type) === '3') {
            this.removeFreeItemLinkedToMainItem(itemToRemove.product_id);
        }
        
        // Remove only this specific item (by index to handle same product_id in multiple rows)
        if (cartIndex != null && cartIndex >= 0 && cartIndex < this.cartItems.length && this.cartItems[cartIndex] === itemToRemove) {
            this.cartItems.splice(cartIndex, 1);
        } else {
            const idx = this.cartItems.indexOf(itemToRemove);
            if (idx !== -1) {
                this.cartItems.splice(idx, 1);
            }
        }
        this.saveCartToStorage();
        this.renderCart();
        this.updateCartSummary();
    }

    /**
     * Remove only the free item linked to a specific main item (for Buy X Get Y).
     * Use this when main item quantity drops so free qty becomes 0, to avoid removing other free items with same product_id.
     * @param {String|Number} mainItemProductId - The main item's product_id (cart row) this free item is linked to
     */
    removeFreeItemLinkedToMainItem(mainItemProductId) {
        const before = this.cartItems.length;
        this.cartItems = this.cartItems.filter(item =>
            !(item.is_promotion_free_item === true && item.promotion_main_item_id == mainItemProductId)
        );
        if (this.cartItems.length < before) {
            this.saveCartToStorage();
            this.renderCart();
            this.updateCartSummary();
        }
    }

    /**
     * Calculate item total
     */
    calculateItemTotal(item) {
        const subtotal = item.quantity * item.unit_price;
        const discountAmount = item.discount_type === 'percentage' 
            ? (subtotal * item.discount) / 100 
            : item.discount;
        return Math.max(0, subtotal - discountAmount);
    }

    /**
     * Update cart count badge
     */
    updateCartCountBadge() {
        const cartCount = this.cartItems.length;
        $('.product-length-count').text(cartCount);
    }

    /**
     * Render cart items
     */
    renderCart() {
        const $tbody = $('#pos-cart-items-tbody');
        const $emptyCart = $('#pos-empty-cart');
        const $cartTable = $('.pos-cart-table');

        // Grid mode: rows are managed by pos-index grid system, skip rendering
        if (document.querySelector('.pos-grid-table')) {
            this.updateCartCountBadge();
            return;
        }

        if (this.cartItems.length === 0) {
            $tbody.empty();
            $cartTable.hide();
            $emptyCart.show();
            this.updateCartCountBadge(); // Update badge even when cart is empty
            return;
        }

        $cartTable.show();
        $emptyCart.hide();
        $tbody.empty();

        this.cartItems.forEach((item, index) => {
            const row = this.createCartRow(item, index);
            $tbody.append(row);
        });
        
        this.updateCartCountBadge(); // Update badge after rendering
    }

    /**
     * Create cart row HTML
     * @param {Object} item - Cart item
     * @param {number} cartIndex - Index in cartItems array (for unique row identification)
     */
    createCartRow(item, cartIndex = 0) {
        // Format discount display: preserve user's format (10 or 10%)
        // Check if discount was stored with % or without
        const discountValue = item.discount || 0;
        let discountDisplay = discountValue;
        
        // If discount type is percentage and discount > 0, show with % by default
        // But allow user to remove it later
        if (item.discount_type === 'percentage' && discountValue > 0) {
            // Check if the stored value already has % (from user input)
            // For now, default to showing % for percentage type
            discountDisplay = `${discountValue}%`;
        }

        // Display IMEI/Serial numbers if available
        let imeiSerialDisplay = '';
        if (item.selected_imei_serial && Array.isArray(item.selected_imei_serial) && item.selected_imei_serial.length > 0) {
            const imeiSerialText = item.selected_imei_serial.join(', ');
            imeiSerialDisplay = `<br><small class="text-info badge bg-label-primary" style="font-size: 0.75rem;">${this.escapeHtml(imeiSerialText)}</small>`;
        }
        
        // Display Medicine expiry dates if available
        let medicineExpiryDisplay = '';
        if (item.selected_medicine_expiry && Array.isArray(item.selected_medicine_expiry) && item.selected_medicine_expiry.length > 0) {
            const expiryTexts = item.selected_medicine_expiry.map(med => {
                const formattedDate = this.formatDateForMedicineDisplay(med.expiry_date);
                return `${formattedDate} - ${med.quantity}`;
            });
            const medicineText = expiryTexts.join(', ');
            medicineExpiryDisplay = `<br><small class="text-info badge bg-label-primary" style="font-size: 0.75rem;">${this.escapeHtml(medicineText)}</small>`;
        }

        // Check if quantity should be disabled
        const productType = item.product_type || '';
        const isQuantityDisabled = productType === 'Combo_Product' || 
                                   productType === 'IMEI_Product' || 
                                   productType === 'Serial_Product' ||
                                   (productType === 'Medicine_Product' && item.selected_medicine_expiry && item.selected_medicine_expiry.length > 0);
        
        // Check if item is a promotion item
        const isPromotionFreeItem = item.is_promotion_free_item === true;
        const hasPromotionDiscount = item.has_promotion_discount === true;
        const isBuyXGetYMainItem = item.promotion && item.promotion.type === '3' && !isPromotionFreeItem;
        
        // For Discount Promotion (Type 1): Enable edit icon, quantity, remove icon. Disable price and discount.
        // For Buy X Get Y Main Item (Type 3): Enable edit icon, quantity, remove icon. Disable price only.
        // For Free Item: Disable everything.
        
        // Quantity field: Disable only for free items or if quantity is disabled for product type
        const quantityDisabledAttr = (isQuantityDisabled || isPromotionFreeItem) ? 'disabled' : '';
        const quantityReadonlyAttr = (isQuantityDisabled || isPromotionFreeItem) ? 'readonly' : '';
        
        // Price field: Disable for discount promotion, buy x get y main item, or free items
        const priceDisabledAttr = (hasPromotionDiscount || isBuyXGetYMainItem || isPromotionFreeItem) ? 'disabled' : '';
        const priceReadonlyAttr = (hasPromotionDiscount || isBuyXGetYMainItem || isPromotionFreeItem) ? 'readonly' : '';
        
        // Discount field: Disable for discount promotion, buy x get y main item, or free items
        const discountDisabledAttr = (hasPromotionDiscount || isBuyXGetYMainItem || isPromotionFreeItem) ? 'disabled' : '';
        const discountReadonlyAttr = (hasPromotionDiscount || isBuyXGetYMainItem || isPromotionFreeItem) ? 'readonly' : '';
        
        // Edit icon: Hide only for free items
        const editIconDisplay = isPromotionFreeItem ? 'style="display: none;"' : '';
        
        // Remove icon: Hide only for free items
        const deleteButtonDisplay = isPromotionFreeItem ? 'style="display: none;"' : '';
        
        // Promotion badge
        let promotionBadge = '';
        if (isPromotionFreeItem) {
            promotionBadge = '<br><small class="text-success badge bg-label-success" style="font-size: 0.75rem;"><i class="icon-base ti tabler-gift me-1"></i>Free Item</small>';
        } else if (hasPromotionDiscount && item.promotion) {
            promotionBadge = `<br><small class="text-info badge bg-label-info" style="font-size: 0.75rem;"><i class="icon-base ti tabler-tag me-1"></i>${this.escapeHtml(item.promotion.title || 'Promotion')}</small>`;
        }

        // For free items: hide unit price, discount, and subtotal - show only quantity
        const priceDisplay = isPromotionFreeItem ? '<span class="text-muted">—</span>' : `<input class="common-input unit-price number-input" value="${this.formatNumber(item.unit_price)}" type="text" ${priceDisabledAttr} ${priceReadonlyAttr}>`;
        const discountDisplayCell = isPromotionFreeItem ? '<span class="text-muted">—</span>' : `<input class="common-input item-discount discount-format" value="${discountDisplay}" min="0" type="text" data-discount-type="${item.discount_type || 'fixed'}" ${discountDisabledAttr} ${discountReadonlyAttr}>`;
        const totalDisplay = isPromotionFreeItem ? '<span class="text-muted">—</span>' : `<span class="item-total">${this.formatNumber(item.total)}</span>`;

        return $(`
            <tr data-product-id="${item.product_id}" data-cart-index="${cartIndex}" ${isPromotionFreeItem ? 'data-is-free-item="true"' : ''} ${isPromotionFreeItem && item.promotion_main_item_id != null ? `data-promotion-main-item-id="${item.promotion_main_item_id}"` : ''}>
                <td class="sno-cell">${cartIndex + 1}</td>
                <td>
                    <div class="item-info d-flex align-items-center">
                        <div class="item-edit-icon me-1 text-primary" data-product-id="${item.product_id}" ${editIconDisplay}>
                            <i class="icon-base ti tabler-pencil"></i>
                        </div>
                        <div class="item-info-content">
                            <div class="item-name">${this.escapeHtml(item.product_name)}</div>
                            <small class="text-muted">${this.escapeHtml(item.product_code || '')}</small>
                            ${promotionBadge}
                            ${imeiSerialDisplay}
                            ${medicineExpiryDisplay}
                        </div>
                    </div>
                </td>
                <td class="text-center"><span class="hsn-code">${this.escapeHtml(item.hsn_code || '')}</span></td>
                <td class="text-right"><span class="mrp-price">${this.formatNumber(item.mrp_price || 0)}</span></td>
                <td>
                    <input class="common-input item-qty number-input" value="${this.formatNumber(item.quantity)}" type="text" ${quantityDisabledAttr} ${quantityReadonlyAttr}>
                </td>
                <td class="text-center"><span class="sale-unit">${this.escapeHtml(item.sale_unit_name || 'PCS')}</span></td>
                <td>
                    ${priceDisplay}
                </td>
                <td>
                    ${discountDisplayCell}
                </td>
                <td>
                    <div class="total-with-action">
                        ${totalDisplay}
                        <div class="cart-inline-actions">
                            <button class="text-danger cart-item-remove" ${deleteButtonDisplay}>
                                <i class="icon-base ti tabler-trash"></i>
                            </button>
                        </div>
                    </div>
                </td>
                <td class="text-center"><span class="tax-rate">${item.tax_rate ? item.tax_rate + '%' : ''}</span></td>
            </tr>
        `);
    }

    /**
     * Get current customer state code (for GST intra/inter-state)
     */
    getCustomerStateCode() {
        const customerId = $('#customer-select').val();
        if (!customerId) return null;
        const customers = window.posCustomersWithState || {};
        const cust = customers[customerId];
        if (!cust || !cust.state_id) return null;
        const states = window.posStates || [];
        const state = states.find(s => s.id == cust.state_id);
        return state ? state.state_code : null;
    }

    /**
     * Calculate item tax - New GST logic (Intra/Inter-state, Exclusive/Inclusive)
     * Uses applicable_tax_id + tax_type from item, falls back to tax_information for legacy
     */
    calculateItemTax(item) {
        const itemSubtotal = item.quantity * item.unit_price;
        const discountAmount = item.discount_type === 'percentage'
            ? (itemSubtotal * (item.discount || 0)) / 100
            : (item.discount || 0);
        const lineTotal = Math.max(0, itemSubtotal - discountAmount);

        const taxs = window.posTaxs || [];
        const taxsMap = {};
        taxs.forEach(t => { taxsMap[t.id] = t; });

        const applicableTaxIds = item.applicable_tax_id;
        const taxType = item.tax_type || 'Inclusive';

        if (applicableTaxIds && taxs.length > 0) {
            return this._calculateGstTax(item, lineTotal, applicableTaxIds, taxType, taxsMap);
        }

        // Legacy: tax_information
        if (item.tax_information && Array.isArray(item.tax_information) && item.tax_information.length > 0) {
            const taxBreakdown = [];
            let totalTax = 0;
            item.tax_information.forEach(taxInfo => {
                if (taxInfo.tax_field_percentage != null && taxInfo.tax_field_name) {
                    const taxAmount = (lineTotal * parseFloat(taxInfo.tax_field_percentage)) / 100;
                    taxBreakdown.push({
                        name: taxInfo.tax_field_name,
                        percentage: parseFloat(taxInfo.tax_field_percentage),
                        amount: taxAmount
                    });
                    totalTax += taxAmount;
                }
            });
            return { totalTax, taxBreakdown, isInclusive: taxType === 'Inclusive' };
        }

        return { totalTax: 0, taxBreakdown: [], isInclusive: false };
    }

    /**
     * GST tax calculation: Intra-State (CGST+SGST) vs Inter-State (IGST)
     */
    _calculateGstTax(item, lineTotal, applicableTaxIds, taxType, taxsMap) {
        const outletStateCode = window.posOutletStateCode || null;
        const customerStateCode = this.getCustomerStateCode();
        // Intra-State: Walk-in (null customer state), or outlet has no state, or same state
        const isIntraState = customerStateCode === null || outletStateCode === null || customerStateCode === outletStateCode;

        const taxIds = String(applicableTaxIds).split(',').map(s => s.trim()).filter(Boolean);
        let totalGstRate = 0;
        let cgstRate = 0;
        let sgstRate = 0;
        let igstRate = 0;
        const taxBreakdown = [];
        let totalTax = 0;

        for (const taxId of taxIds) {
            const tax = taxsMap[taxId];
            if (!tax) continue;

            const taxName = (tax.tax_name || '').toUpperCase();
            const taxRate = parseFloat(tax.tax_rate) || 0;
            const parentId = tax.parent_tax_id;

            // GST parent: resolve from children - Intra: CGST+SGST, Inter: IGST (get actual rates from profile)
            if (taxName === 'GST' || (taxRate <= 0 && !parentId)) {
                for (const tid in taxsMap) {
                    const child = taxsMap[tid];
                    if (child.parent_tax_id != null && String(child.parent_tax_id) === String(taxId)) {
                        const cName = (child.tax_name || '').toUpperCase();
                        const cRate = parseFloat(child.tax_rate) || 0;
                        if (isIntraState && cName === 'CGST') {
                            cgstRate = cRate;
                            totalGstRate += cRate;
                        } else if (isIntraState && cName === 'SGST') {
                            sgstRate = cRate;
                            totalGstRate += cRate;
                        } else if (!isIntraState && cName === 'IGST') {
                            igstRate = cRate;
                            totalGstRate += cRate;
                        }
                    }
                }
                continue;
            }

            // GST children: Intra-State = CGST+SGST only, Inter-State = IGST only
            if (taxName === 'CGST' && isIntraState) {
                cgstRate = taxRate;
                totalGstRate += taxRate;
            } else if (taxName === 'SGST' && isIntraState) {
                sgstRate = taxRate;
                totalGstRate += taxRate;
            } else if (taxName === 'IGST' && !isIntraState) {
                igstRate = taxRate;
                totalGstRate += taxRate;
            } else {
                const taxAmount = taxType === 'Exclusive'
                    ? (lineTotal * taxRate) / 100
                    : (lineTotal * taxRate) / (100 + taxRate);
                taxBreakdown.push({ name: tax.tax_name, percentage: taxRate, amount: taxAmount });
                totalTax += taxAmount;
            }
        }

        if (totalGstRate > 0) {
            // Inclusive: Taxable = Price / (1 + GST/100), GST = Price - Taxable
            // Exclusive: GST = Price * rate / 100
            const gstAmount = taxType === 'Exclusive'
                ? (lineTotal * totalGstRate) / 100
                : lineTotal - (lineTotal / (1 + totalGstRate / 100));
            if (isIntraState) {
                // Split by actual CGST/SGST rates from profile (not totalGstRate/2)
                const cgstAmount = (cgstRate + sgstRate) > 0 ? gstAmount * (cgstRate / (cgstRate + sgstRate)) : gstAmount / 2;
                const sgstAmount = gstAmount - cgstAmount;
                taxBreakdown.push({ name: 'CGST', percentage: cgstRate, amount: cgstAmount });
                taxBreakdown.push({ name: 'SGST', percentage: sgstRate, amount: sgstAmount });
            } else {
                taxBreakdown.push({ name: 'IGST', percentage: igstRate, amount: gstAmount });
            }
            totalTax += gstAmount;
        }

        return { totalTax, taxBreakdown, isInclusive: taxType === 'Inclusive' };
    }

    /**
     * Get all tax breakdown for cart
     */
    getCartTaxBreakdown() {
        const taxMap = {}; // Group taxes by name

        this.cartItems.forEach(item => {
            const { taxBreakdown } = this.calculateItemTax(item);
            taxBreakdown.forEach(tax => {
                if (!taxMap[tax.name]) {
                    taxMap[tax.name] = {
                        name: tax.name,
                        amount: 0
                    };
                }
                taxMap[tax.name].amount += tax.amount;
            });
        });

        return Object.values(taxMap);
    }

    /**
     * Update cart summary
     * Inclusive tax: already in item price, do NOT add to total payable
     * Exclusive tax: add to total payable
     */
    updateCartSummary() {
        let subtotal = 0; // Sum of line totals (after item discounts)
        let totalItemDiscount = 0;
        let totalExclusiveTax = 0;
        let totalInclusiveTax = 0;

        this.cartItems.forEach(item => {
            subtotal += item.total;

            const itemSubtotal = item.quantity * item.unit_price;
            const discountAmount = item.discount_type === 'percentage'
                ? (itemSubtotal * item.discount) / 100
                : item.discount;
            totalItemDiscount += discountAmount;

            const { totalTax: itemTax, isInclusive } = this.calculateItemTax(item);
            if (isInclusive) {
                totalInclusiveTax += itemTax;
            } else {
                totalExclusiveTax += itemTax;
            }
        });

        // Calculate cart-level discount
        let cartDiscountAmount = 0;
        if (this.cartSummaryDiscount > 0) {
            if (this.cartSummaryDiscountType === 'percentage') {
                cartDiscountAmount = (subtotal * this.cartSummaryDiscount) / 100;
            } else {
                cartDiscountAmount = this.cartSummaryDiscount;
            }
        }

        const shipping = this.cartSummaryShipping || parseFloat($('#pos-shipping-input').val()) || 0;

        // Total Payable = Subtotal - Cart Discount + Exclusive Tax Only + Shipping
        // (Inclusive tax is already in subtotal)
        const total = subtotal - cartDiscountAmount + totalExclusiveTax + shipping;

        // Update displays
        $('#pos-subtotal').text(this.formatNumber(subtotal));
        $('#pos-tax').text(this.formatNumber(totalExclusiveTax));
        if (totalInclusiveTax > 0) {
            $('#pos-tax-inclusive').text('(Incl. ' + this.formatNumber(totalInclusiveTax) + ')').show();
        } else {
            $('#pos-tax-inclusive').hide();
        }
        $('#pos-total-amount').text(this.formatNumber(total));

        const totalDiscount = totalItemDiscount + cartDiscountAmount;
        $('.show-total-discount-value').text(this.formatNumber(totalDiscount));

        // Save: pass totalExclusiveTax + totalInclusiveTax for display, total is correct
        this.saveCartSummaryToStorage(subtotal, totalExclusiveTax + totalInclusiveTax, totalDiscount, shipping, total);
    }

    /**
     * Save cart to localStorage
     */
    saveCartToStorage() {
        try {
            localStorage.setItem('pos_cart', JSON.stringify(this.cartItems));
            // Trigger storage event for customer display window
            window.dispatchEvent(new Event('storage'));
        } catch (e) {
            console.error('Failed to save cart to storage:', e);
        }
    }

    /**
     * Save cart summary to localStorage for customer display
     */
    saveCartSummaryToStorage(subtotal, tax, discount, shipping, total) {
        try {
            const summary = {
                subtotal: subtotal,
                tax: tax,
                discount: discount,
                shipping: shipping,
                total: total
            };
            localStorage.setItem('pos_cart_summary', JSON.stringify(summary));
            // Trigger storage event for customer display window
            window.dispatchEvent(new Event('storage'));
        } catch (e) {
            console.error('Failed to save cart summary to storage:', e);
        }
    }

    /**
     * Load cart from localStorage
     */
    loadCartFromStorage() {
        try {
            const saved = localStorage.getItem('pos_cart');
            if (saved) {
                this.cartItems = JSON.parse(saved);
            }
        } catch (e) {
            console.error('Failed to load cart from storage:', e);
            this.cartItems = [];
        }
    }

    /**
     * Recalculate totals for all cart items
     * This is needed after loading from localStorage
     */
    recalculateCartTotals() {
        this.cartItems.forEach(item => {
            item.total = this.calculateItemTotal(item);
        });
    }

    /**
     * Clear cart
     */
    clearCart() {
        this.cartItems = [];
        this.cartSummaryDiscount = 0;
        this.cartSummaryDiscountType = 'fixed';
        this.cartSummaryShipping = 0;
        
        // Reset summary inputs
        $('#pos-cart-discount-input').val('0');
        $('#pos-cart-discount-type').val('fixed').trigger('change');
        $('#pos-shipping-input').val('0');
        
        this.saveCartToStorage();
        this.renderCart();
        this.updateCartSummary(); // This will save empty summary to localStorage
    }

    /**
     * Format number - show decimals only if needed (50.55 -> 50.55, 50 -> 50)
     */
    /**
     * Format date for medicine display (DD-MM-YYYY)
     */
    formatDateForMedicineDisplay(dateString) {
        if (!dateString) return '';
        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString;
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            return `${day}-${month}-${year}`;
        } catch (e) {
            return dateString;
        }
    }
    
    formatNumber(num) {
        if (num === null || num === undefined || isNaN(num)) {
            return '0';
        }
        const numValue = parseFloat(num);
        // Check if number has decimal places
        if (numValue % 1 === 0) {
            return numValue.toString();
        }
        // Show up to 2 decimal places, remove trailing zeros
        return parseFloat(numValue.toFixed(2)).toString();
    }

    /**
     * Save sale as cash payment
     */
    async saveSaleAsCash() {
        // Validation
        let customerId = $('#customer-select').val() || null;
        let employeeId = $('#employee-select').val() || null;
        if (!customerId) {
            showErrorNotification('The Customer is required, Please select a customer.');
            return;
        }
        if (!employeeId) {
            showErrorNotification('The Employee is required, Please select an employee.');
            return;
        }
        if (this.cartItems.length === 0) {
            showErrorNotification('Cart is empty. Please add items to cart.');
            return;
        }


        const subtotal = parseFloat($('#pos-subtotal').text().replace(/,/g, '')) || 0;
        const tax = parseFloat($('#pos-tax').text().replace(/,/g, '')) || 0;
        const shipping = this.cartSummaryShipping || parseFloat($('#pos-shipping-input').val()) || 0;
        const discount = this.cartSummaryDiscount || 0;
        const discountType = this.cartSummaryDiscountType || 'fixed';
        const totalPayable = parseFloat($('#pos-total-amount').text().replace(/,/g, '')) || 0;

        // Prepare cart items with all required data (include applicable_tax_id, tax_type for GST)
        const cartItems = this.cartItems.map(item => ({
            product_id: item.product_id,
            product_name: item.product_name,
            product_code: item.product_code,
            product_type: item.product_type,
            quantity: item.quantity,
            unit_price: item.unit_price,
            discount: item.discount || 0,
            discount_type: item.discount_type || 'fixed',
            tax_information: item.tax_information || [],
            applicable_tax_id: item.applicable_tax_id || null,
            tax_type: item.tax_type || 'Inclusive',
            selected_imei_serial: item.selected_imei_serial || [], // IMEI/Serial numbers
            selected_medicine_expiry: item.selected_medicine_expiry || [], // Medicine expiry dates
            item_seller_id: item.item_seller_id || null,
            combo_items: item.combo_items || [],
            // Promotion data
            is_promotion_free_item: item.is_promotion_free_item || false,
            promotion_id: item.promotion_id || (item.promotion && item.promotion.id) || null,
            promotion: item.promotion || null,
            has_promotion_discount: item.has_promotion_discount || false
        }));

        const saleData = {
            customer_id: customerId ? parseInt(customerId) : null,
            employee_id: employeeId ? parseInt(employeeId) : null,
            cart_items: cartItems,
            subtotal: subtotal,
            tax: tax,
            discount: discount,
            discount_type: discountType,
            shipping: shipping,
            total_payable: totalPayable
        };

        // Check internet connection - but don't block if navigator.onLine is unreliable
        const internetConnected = window.internetConnected !== undefined ? window.internetConnected : navigator.onLine;
        
        // Only save offline if we're definitely offline AND the browser says so
        // Don't save offline just because navigator.onLine is false (it can be unreliable)
        if (!internetConnected && !navigator.onLine) {
            // Save to IndexedDB for offline sync
            return await this.saveOfflineSale(saleData);
        }

        try {
            // Show loading
            if (typeof showInfoNotification !== 'undefined') {
                showInfoNotification('Processing sale...');
            }

            const baseUrl = $('#base_url').val() || window.location.origin;
            const isEditingSale = !!window.posEditingSaleId;
            const saveUrl = isEditingSale
                ? (baseUrl + route('sale.update', { sale: window.posEditingSaleId }, false, Ziggy))
                : `${baseUrl}/pos/sale`;
            const saveMethod = isEditingSale ? 'PUT' : 'POST';
            
            // Set a reasonable timeout (60 seconds) to handle ZATCA processing
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 60000); // 60 second timeout
            
            const response = await fetch(saveUrl, {
                method: saveMethod,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(saleData),
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);

            const result = await response.json();

                if (response.ok && result.status === 'success') {
                if (isEditingSale) {
                    showSuccessNotification('Sale updated successfully! Sale No: ' + (result.sale_no || ''));
                    window.posEditingSaleId = null;
                    // Remove edit_sale from URL so it shows only /pos
                    if (window.history && window.history.replaceState) {
                        window.history.replaceState({}, document.title, window.location.pathname);
                    }
                } else {
                    showSuccessNotification(`Sale saved successfully! Sale No: ${result.sale_no}`);
                }
                
                // Open invoice popup window
                if (result.encrypted_id && typeof window.openInvoicePopup === 'function') {
                    window.openInvoicePopup(result.encrypted_id);
                }

                // Update IndexedDB: remove IMEI/serial numbers and decrease stock (only for new sales, not edit)
                if (!isEditingSale && typeof posIndexedDB !== 'undefined' && cartItems.length > 0) {
                    try {
                        await posIndexedDB.updateProductsAfterSale(cartItems);
                        
                        // Update individual product cards immediately for visual feedback
                        for (const item of cartItems) {
                            try {
                                const product = await posIndexedDB.getProductById(item.product_id);
                                if (product && typeof posProductsDisplay !== 'undefined') {
                                    await posProductsDisplay.updateProductCardStock(item.product_id, product.stock || 0);
                                }
                            } catch (error) {
                                console.error(`Error updating product card for product ${item.product_id}:`, error);
                            }
                        }
                        
                        // Refresh product display to reflect updated stock
                        if (typeof posProductsDisplay !== 'undefined') {
                            // Trigger a refresh of the product grid
                            const currentCategoryId = posProductsDisplay.currentCategoryId;
                            const currentSearchTerm = posProductsDisplay.currentSearchTerm;
                            if (currentSearchTerm) {
                                await posProductsDisplay.handleSearch(currentSearchTerm);
                            } else if (currentCategoryId) {
                                await posProductsDisplay.handleCategoryClick(currentCategoryId, $(`.pos-category-item[data-category-id="${currentCategoryId}"], .pos-category-header[data-category-id="${currentCategoryId}"]`));
                            } else {
                                await posProductsDisplay.loadFirstChunk();
                            }
                        }
                    } catch (error) {
                        console.error('Error updating IndexedDB after sale:', error);
                        // Don't block the success flow if IndexedDB update fails
                    }
                }

                // Clear cart after successful save
                this.clearCart();
                $('#customer-select').val(this.company_session_data.default_customer).trigger('change');
                $('#employee-select').val(this.company_session_data.default_employee_id).trigger('change');
            } else {

                // If save fails and we're online, try to save offline as fallback (only for new sales)
                if (!isEditingSale && internetConnected) {
                    console.warn('Online save failed, attempting offline save:', result.message);
                    return await this.saveOfflineSale(saleData);
                }
                showErrorNotification(result.message || (isEditingSale ? 'Failed to update sale' : 'Failed to save sale'));
            }
        } catch (error) {
            console.error('Error saving sale:', error);
            
            // Only save offline if it's a genuine network error, not a timeout or other error
            // Don't save offline if the request was aborted due to timeout (might be ZATCA processing)
            if (error.name === 'AbortError') {
                showErrorNotification('Sale processing is taking longer than expected. Please check if the sale was saved.');
                return; // Don't save to IndexedDB - sale might have been saved
            }
            
            // If network error (not timeout), save offline
            if (error.message && (error.message.includes('fetch') || error.message.includes('network') || error.message.includes('Failed to fetch'))) {
                // Only save offline if we're actually offline
                if (!navigator.onLine) {
                    return await this.saveOfflineSale(saleData);
                }
            }
            showErrorNotification('An error occurred while saving the sale: ' + error.message);
        }
    }

    /**
     * Save sale to IndexedDB for offline sync
     * @param {Object} saleData - Sale data
     */
    async saveOfflineSale(saleData) {
        try {
            // Prepare sale_detail and sale_payment structure
            const saleDetail = {
                customer_id: saleData.customer_id,
                employee_id: saleData.employee_id,
                cart_items: saleData.cart_items,
                subtotal: saleData.subtotal,
                tax: saleData.tax,
                discount: saleData.discount,
                discount_type: saleData.discount_type,
                shipping: saleData.shipping,
                total_payable: saleData.total_payable
            };

            const salePayment = {
                payments: saleData.payments || [{ payment_id: 1, payment_name: 'Cash', amount: saleData.total_payable }],
                total_paid: saleData.total_paid || saleData.total_payable,
                change_amount: saleData.change_amount || 0,
                due_amount: saleData.due_amount || 0
            };

            const offlineSaleData = {
                sale_detail: saleDetail,
                sale_payment: salePayment
            };

            // Save to IndexedDB
            if (typeof posIndexedDB !== 'undefined') {
                const saleId = await posIndexedDB.saveOfflineSale(offlineSaleData);
                
                // Update IndexedDB: remove IMEI/serial numbers and decrease stock (for offline tracking)
                if (saleData.cart_items && saleData.cart_items.length > 0) {
                    try {
                        await posIndexedDB.updateProductsAfterSale(saleData.cart_items);
                    } catch (error) {
                        console.error('Error updating IndexedDB products after offline sale:', error);
                    }
                }

                if (typeof showSuccessNotification !== 'undefined') {
                    showSuccessNotification(`Sale saved offline! Will sync when internet is available. (ID: ${saleId})`);
                } else {
                    alert(`Sale saved offline! Will sync when internet is available. (ID: ${saleId})`);
                }

                // Generate and open offline invoice (no server needed)
                if (typeof POSOfflineInvoice !== 'undefined' && typeof POSOfflineInvoice.generateOfflineInvoice === 'function') {
                    const offlineRecord = { sale_detail: saleDetail, sale_payment: salePayment, created_at: new Date().toISOString(), synced: false };
                    POSOfflineInvoice.generateOfflineInvoice(offlineRecord, saleId, {
                        companySessionData: this.company_session_data,
                        customerName: $('#customer-select option:selected').text().trim(),
                        employeeName: $('#employee-select option:selected').text().trim(),
                        outletName: $('.outlet-name').text().trim()
                    });
                }

                // Clear cart after offline save
                this.clearCart();
                $('#customer-select').val(this.company_session_data.default_customer).trigger('change');
                $('#employee-select').val(this.company_session_data.default_employee_id).trigger('change');
            } else {
                throw new Error('IndexedDB not available');
            }
        } catch (error) {
            console.error('Error saving offline sale:', error);
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Failed to save sale offline: ' + error.message);
            } else {
                alert('Failed to save sale offline: ' + error.message);
            }
        }
    }

    /**
     * Open payment modal
     */
    openPaymentModal() {
        // Validation
        if (this.cartItems.length === 0) {
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Cart is empty. Please add items to cart.');
            } else {
                alert('Cart is empty. Please add items to cart.');
            }
            return;
        }

        // Update total in payment modal
        const totalPayable = parseFloat($('#pos-total-amount').text().replace(/,/g, '')) || 0;
        $('#pos-payment-total').text(this.formatNumber(totalPayable));

        // Show payment modal (placeholder - will be implemented later)
        const modal = new bootstrap.Modal(document.getElementById('modal_pos_payment'));
        modal.show();
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, (m) => map[m]);
    }

    /**
     * Apply coupon discount (type 2 promotion) based on coupon code
     */
    applyCouponDiscount(couponCode) {
        if (!this.promotionsCache) {
            this.promotionsCache = window.posPromotionsData || null;
        }
        if (!this.promotionsCache) {
            var $promoEl = $('#pos-promotions-json');
            if ($promoEl.length) {
                try { this.promotionsCache = JSON.parse($promoEl.val()); } catch(e) {}
            }
        }
        var promotions = this.promotionsCache || [];
        var matched = promotions.find(function(p) {
            return p.type == '2' && p.coupon_code && p.coupon_code.toLowerCase() === couponCode.toLowerCase()
                && p.status == '1';
        });
        if (!matched) {
            this.clearCouponDiscount();
            return null;
        }
        var discType = matched.discount_type || (matched.discount && matched.discount.indexOf('%') !== -1 ? 'percentage' : 'fixed');
        var discVal = matched.discount_value || (discType === 'percentage' ? parseFloat(matched.discount) : parseFloat(matched.discount));
        if (!discVal || discVal <= 0) {
            this.clearCouponDiscount();
            return null;
        }
        this.couponApplied = { promotion: matched, discount: discVal, discount_type: discType };
        this.updateCartSummary();
        return matched;
    }

    clearCouponDiscount() {
        this.couponApplied = null;
        this.updateCartSummary();
    }

    /**
     * Apply bill-level promotions (scheme_basis = 'bill')
     */
    applyBillLevelPromotions() {
        if (this._billPromoApplied) return;
        if (!this.promotionsCache) return;
        var promotions = this.promotionsCache;
        var now = new Date();
        var today = now.toISOString().split('T')[0];
        var currentTime = now.toTimeString().split(' ')[0].substring(0, 5);
        var subtotal = 0;
        this.cartItems.forEach(function(item) { subtotal += parseFloat(item.total) || 0; });
        var billPromos = promotions.filter(function(p) {
            if (p.type != '1' && p.type != '2') return false;
            if (p.scheme_basis !== 'bill') return false;
            if (p.status != '1') return false;
            if (p.start_date > today || p.end_date < today) return false;
            if (p.start_time && currentTime < p.start_time) return false;
            if (p.end_time && currentTime > p.end_time) return false;
            if (p.min_purchase_amount > 0 && subtotal < p.min_purchase_amount) return false;
            var custType = $('#selected-customer-type').val();
            if (p.applicable_customer_types && p.applicable_customer_types.length > 0 && custType) {
                if (!p.applicable_customer_types.includes(custType)) return false;
            }
            return true;
        });
        if (billPromos.length > 0) {
            var best = billPromos[0];
            var bd = best.bill_level_discount || best.discount || best.discount_value || 0;
            var bdType = best.bill_level_discount_type || best.discount_type || 'fixed';
            if (bd > 0) {
                var discVal = parseFloat(bd);
                if (bdType === 'percentage') {
                    var discAmount = (subtotal * discVal) / 100;
                    if (best.max_discount_amount > 0) discAmount = Math.min(discAmount, best.max_discount_amount);
                    $('#pos-cart-discount-input').val(discVal + '%').trigger('input');
                } else {
                    if (best.max_discount_amount > 0) discVal = Math.min(discVal, best.max_discount_amount);
                    $('#pos-cart-discount-input').val(discVal.toFixed(2)).trigger('input');
                }
                this._billPromoApplied = true;
            }
        }
    }
}

// Initialize global instance
const posCartManager = new POSCartManager();

// Clear cart button handler
$(document).ready(function() {
    $(document).on('click', '.clear-cart-btn', function(e) {
        e.preventDefault();

        Swal.fire({
            title: 'Are you sure you want to clear the cart?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, clear it!'
        }).then((result) => {
            if (result.isConfirmed) {
                posCartManager.clearCart();
                showSuccessNotification('Cart cleared');
            }
        });
    });

    // Cash register button handler
    $(document).on('click', '#pos-cash-register-btn', function(e) {
        e.preventDefault();
        posCartManager.saveSaleAsCash();
    });

    // Payment button handler
    $(document).on('click', '#pos-payment-btn', function(e) {
        e.preventDefault();
        posCartManager.openPaymentModal();
    });

    // Customer display button handler
    $(document).on('click', '.customer-display-btn', function(e) {
        e.preventDefault();
        
        const baseUrl = $('#base_url').val() || window.location.origin;
        const customerDisplayUrl = baseUrl + '/pos/customer-display';
        
        // Open customer display in a new window
        const displayWindow = window.open(
            customerDisplayUrl,
            'CustomerDisplay',
            'width=1024,height=768,menubar=no,toolbar=no,location=no,status=no,resizable=yes,scrollbars=yes'
        );
        
        if (!displayWindow) {
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Please allow popups for this site to open customer display');
            } else {
                alert('Please allow popups for this site to open customer display');
            }
        } else {
            // Focus the window
            displayWindow.focus();
            
            if (typeof showSuccessNotification !== 'undefined') {
                showSuccessNotification('Customer display window opened');
            }
        }
    });
});
