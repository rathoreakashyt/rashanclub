/**
 * Customer Display Manager
 * Handles cart display synchronization for customer display window
 */

class CustomerDisplayManager {
    constructor() {
        this.cartItems = [];
        this.cartSummary = {
            subtotal: 0,
            tax: 0,
            discount: 0,
            shipping: 0,
            total: 0
        };
        this.init();
    }

    /**
     * Initialize customer display
     */
    init() {
        this.loadCartFromStorage();
        this.renderCart();
        this.updateSummary();
        this.setupStorageListener();
    }

    /**
     * Setup storage event listener to sync with main POS window
     */
    setupStorageListener() {
        const self = this;
        
        // Listen for storage changes (when cart is updated in main POS window)
        window.addEventListener('storage', function(e) {
            if (e.key === 'pos_cart' || e.key === 'pos_cart_summary') {
                self.loadCartFromStorage();
                self.renderCart();
                self.updateSummary();
            }
        });

        // Also poll for changes (fallback for same-window updates)
        setInterval(function() {
            self.checkForCartUpdates();
        }, 500); // Check every 500ms
    }

    /**
     * Check for cart updates in localStorage
     */
    checkForCartUpdates() {
        try {
            const savedCart = localStorage.getItem('pos_cart');
            const savedSummary = localStorage.getItem('pos_cart_summary');
            
            if (savedCart) {
                const newCart = JSON.parse(savedCart);
                const cartChanged = JSON.stringify(this.cartItems) !== JSON.stringify(newCart);
                
                if (cartChanged) {
                    this.loadCartFromStorage();
                    this.renderCart();
                }
            }

            if (savedSummary) {
                const newSummary = JSON.parse(savedSummary);
                const summaryChanged = JSON.stringify(this.cartSummary) !== JSON.stringify(newSummary);
                
                if (summaryChanged) {
                    this.loadCartFromStorage();
                    this.updateSummary();
                }
            }
        } catch (e) {
            console.error('Error checking cart updates:', e);
        }
    }

    /**
     * Load cart from localStorage
     */
    loadCartFromStorage() {
        try {
            const savedCart = localStorage.getItem('pos_cart');
            if (savedCart) {
                this.cartItems = JSON.parse(savedCart);
            } else {
                this.cartItems = [];
            }

            const savedSummary = localStorage.getItem('pos_cart_summary');
            if (savedSummary) {
                this.cartSummary = JSON.parse(savedSummary);
            } else {
                this.cartSummary = {
                    subtotal: 0,
                    tax: 0,
                    discount: 0,
                    shipping: 0,
                    total: 0
                };
            }
        } catch (e) {
            console.error('Failed to load cart from storage:', e);
            this.cartItems = [];
            this.cartSummary = {
                subtotal: 0,
                tax: 0,
                discount: 0,
                shipping: 0,
                total: 0
            };
        }
    }

    /**
     * Render cart items
     */
    renderCart() {
        const $tbody = $('#customer-display-cart-tbody');
        const $emptyCart = $('#customer-display-empty-cart');
        const $cartList = $('#customer-display-cart-list');

        if (this.cartItems.length === 0) {
            $tbody.empty();
            $cartList.hide();
            $emptyCart.show();
            return;
        }

        $emptyCart.hide();
        $cartList.show();
        $tbody.empty();

        this.cartItems.forEach((item) => {
            // Skip promotion free items from display (optional - you can show them if needed)
            if (item.is_promotion_free_item === true) {
                return; // Skip free items
            }

            const row = this.createCartRow(item);
            $tbody.append(row);
        });
    }

    /**
     * Create cart row HTML
     */
    createCartRow(item) {
        const itemName = this.escapeHtml(item.product_name || '');
        const itemCode = item.product_code ? `<div class="display-item-code">${this.escapeHtml(item.product_code)}</div>` : '';
        
        // Promotion badge
        let promotionBadge = '';
        if (item.is_promotion_free_item === true) {
            promotionBadge = '<span class="display-item-badge">Free Item</span>';
        } else if (item.has_promotion_discount === true && item.promotion) {
            promotionBadge = `<span class="display-item-badge">${this.escapeHtml(item.promotion.title || 'Promotion')}</span>`;
        }

        // IMEI/Serial display
        let imeiSerialDisplay = '';
        if (item.selected_imei_serial && Array.isArray(item.selected_imei_serial) && item.selected_imei_serial.length > 0) {
            const imeiSerialText = item.selected_imei_serial.join(', ');
            imeiSerialDisplay = `<div class="display-item-code">${this.escapeHtml(imeiSerialText)}</div>`;
        }

        // Medicine expiry display
        let medicineExpiryDisplay = '';
        if (item.selected_medicine_expiry && Array.isArray(item.selected_medicine_expiry) && item.selected_medicine_expiry.length > 0) {
            const expiryTexts = item.selected_medicine_expiry.map(med => {
                const formattedDate = this.formatDateForMedicineDisplay(med.expiry_date);
                return `${formattedDate} - ${med.quantity}`;
            });
            const medicineText = expiryTexts.join(', ');
            medicineExpiryDisplay = `<div class="display-item-code">${this.escapeHtml(medicineText)}</div>`;
        }

        return $(`
            <tr>
                <td class="item-name-col">
                    <div class="display-item-name">${itemName}</div>
                    ${itemCode}
                    ${promotionBadge}
                    ${imeiSerialDisplay}
                    ${medicineExpiryDisplay}
                </td>
                <td class="qty-col">${this.formatNumber(item.quantity || 1)}</td>
                <td class="price-col">${this.formatNumber(item.unit_price || 0)}</td>
                <td class="total-col">${this.formatNumber(item.total || 0)}</td>
            </tr>
        `);
    }

    /**
     * Update summary section
     */
    updateSummary() {
        const $summary = $('#customer-display-summary');
        
        if (this.cartItems.length === 0) {
            $summary.hide();
            return;
        }

        $summary.show();
        $('#customer-display-subtotal').text(this.formatNumber(this.cartSummary.subtotal || 0));
        $('#customer-display-tax').text(this.formatNumber(this.cartSummary.tax || 0));
        $('#customer-display-discount').text(this.formatNumber(this.cartSummary.discount || 0));
        $('#customer-display-shipping').text(this.formatNumber(this.cartSummary.shipping || 0));
        $('#customer-display-total').text(this.formatNumber(this.cartSummary.total || 0));
    }

    /**
     * Format number - show decimals only if needed
     */
    formatNumber(num) {
        if (num === null || num === undefined || isNaN(num)) {
            return '0.00';
        }
        const numValue = parseFloat(num);
        // Check if number has decimal places
        if (numValue % 1 === 0) {
            return numValue.toString() + '.00';
        }
        // Show up to 2 decimal places
        return numValue.toFixed(2);
    }

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
}

// Initialize customer display when page loads
$(document).ready(function() {
    if (typeof CustomerDisplayManager !== 'undefined') {
        window.customerDisplayManager = new CustomerDisplayManager();
    }
});
