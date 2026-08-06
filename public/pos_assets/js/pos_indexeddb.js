/**
 * POS IndexedDB Manager
 * Consolidated IndexedDB management for POS products
 * Database Structure:
 *   - Database: POS_Database
 *   - Store: products (primary key: id)
 *   - Each product stored with id as key and product information as value
 */

class POSIndexedDB {
    constructor() {
        this.dbName = 'POS_Database';
        this.dbVersion = 2; // Increment version to add sales store
        this.storeName = 'products';
        this.salesStoreName = 'sales';
        this.db = null;
        this.isInitialized = false;
        this.syncInProgress = false;
    }

    /**
     * Initialize IndexedDB
     * Creates database with products store using id as primary key
     */
    async init() {
        return new Promise((resolve, reject) => {
            if (!('indexedDB' in window)) {
                reject(new Error('IndexedDB is not supported in this browser'));
                return;
            }
            const request = indexedDB.open(this.dbName, this.dbVersion);
            request.onerror = () => {
                reject(new Error('Failed to open IndexedDB'));
            };
            request.onsuccess = (event) => {
                this.db = event.target.result;
                this.isInitialized = true;
                resolve(this.db);
            };
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                // Create products store with id as primary key
                if (!db.objectStoreNames.contains(this.storeName)) {
                    db.createObjectStore(this.storeName, { keyPath: 'id' });
                }
                // Create sales store for offline sales with auto-increment key
                if (!db.objectStoreNames.contains(this.salesStoreName)) {
                    db.createObjectStore(this.salesStoreName, { keyPath: 'id', autoIncrement: true });
                }
            };
        });
    }

    /**
     * Store products in IndexedDB
     * Each product uses its id as the primary key
     */
    async storeProducts(products) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const store = transaction.objectStore(this.storeName);
            let successCount = 0;
            let errorCount = 0;
            products.forEach((product) => {
                const request = store.put(product);
                request.onsuccess = () => {
                    successCount++;
                    if (successCount + errorCount === products.length) {
                        resolve({ success: successCount, errors: errorCount });
                    }
                };
                request.onerror = () => {
                    errorCount++;
                    if (successCount + errorCount === products.length) {
                        resolve({ success: successCount, errors: errorCount });
                    }
                };
            });
        });
    }

    /**
     * Get all products from IndexedDB
     */
    async getAllProducts() {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readonly');
            const store = transaction.objectStore(this.storeName);
            const request = store.getAll();

            request.onsuccess = () => {
                resolve(request.result);
            };
            request.onerror = () => {
                reject(new Error('Failed to get products from IndexedDB'));
            };
        });
    }

    /**
     * Get product by ID (primary key)
     */
    async getProductById(id) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readonly');
            const store = transaction.objectStore(this.storeName);
            const request = store.get(id);
            request.onsuccess = () => {
                resolve(request.result);
            };
            request.onerror = () => {
                reject(new Error('Failed to get product from IndexedDB'));
            };
        });
    }

    /**
     * Get product or variation by ID.
     * For General_Product etc.: returns product directly (same as getProductById).
     * For Variation_Product: variations are stored in parent's variations array - searches and returns the variation.
     * @param {number} id - Product or variation ID
     * @returns {Promise<Object|null>} Product/variation object or null
     */
    async getProductOrVariationById(id) {
        const numericId = parseInt(id, 10);
        if (isNaN(numericId)) return null;
        try {
            const product = await this.getProductById(numericId);
            if (product) {
                return product;
            }
            const allProducts = await this.getAllProducts();
            for (const p of allProducts) {
                if (p.variations && Array.isArray(p.variations)) {
                    const variation = p.variations.find(v => v && (v.id == numericId || v.id === numericId));
                    if (variation) {
                        const parentName = p.name || '';
                        const variationName = variation.name || '';
                        const displayName = parentName && variationName ? `${parentName} - ${variationName}` : (variationName || parentName || 'Free Item');
                        return {
                            id: variation.id,
                            name: displayName,
                            code: variation.code,
                            type: variation.type || 'Standard',
                            tax_information: variation.tax_information || p.tax_information || [],
                            tax_string: variation.tax_string || p.tax_string || '',
                            applicable_tax_id: variation.applicable_tax_id ?? p.applicable_tax_id ?? null,
                            tax_type: variation.tax_type || p.tax_type || 'Inclusive'
                        };
                    }
                }
            }
            return null;
        } catch (error) {
            console.error('Error in getProductOrVariationById:', error);
            return null;
        }
    }

    /**
     * Search products by name, code, brand, category, or generic_name
     */
    async searchProducts(searchTerm) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readonly');
            const store = transaction.objectStore(this.storeName);
            const request = store.getAll();
            request.onsuccess = () => {
                const products = request.result;
                const searchLower = searchTerm.toLowerCase();
                const filtered = products.filter((product) => {
                    return (
                        (product.name && product.name.toLowerCase().includes(searchLower)) ||
                        (product.code && product.code.toLowerCase().includes(searchLower)) ||
                        (product.alternative_name && product.alternative_name.toLowerCase().includes(searchLower)) ||
                        (product.generic_name && product.generic_name.toLowerCase().includes(searchLower)) ||
                        (product.brand_name && product.brand_name.toLowerCase().includes(searchLower)) ||
                        (product.category_name && product.category_name.toLowerCase().includes(searchLower))
                    );
                });
                resolve(filtered);
            };
            request.onerror = () => {
                reject(new Error('Failed to search products in IndexedDB'));
            };
        });
    }

    /**
     * Get products by category
     */
    async getProductsByCategory(categoryId) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readonly');
            const store = transaction.objectStore(this.storeName);
            const request = store.getAll();
            request.onsuccess = () => {
                const products = request.result;
                const filtered = products.filter((product) => {
                    return product.category_id === categoryId;
                });
                resolve(filtered);
            };
            request.onerror = () => {
                reject(new Error('Failed to get products by category from IndexedDB'));
            };
        });
    }

    /**
     * Get products by generic name (for medicine alternatives)
     */
    async getProductsByGenericName(genericName) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readonly');
            const store = transaction.objectStore(this.storeName);
            const request = store.getAll();
            request.onsuccess = () => {
                const products = request.result;
                const filtered = products.filter((product) => {
                    return product.generic_name && product.generic_name.toLowerCase() === genericName.toLowerCase();
                });
                resolve(filtered);
            };
            request.onerror = () => {
                reject(new Error('Failed to get products by generic name from IndexedDB'));
            };
        });
    }

    /**
     * Clear all products from IndexedDB
     */
    async clearAllProducts() {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const store = transaction.objectStore(this.storeName);
            const request = store.clear();
            request.onsuccess = () => {
                resolve(true);
            };
            request.onerror = () => {
                reject(new Error('Failed to clear products from IndexedDB'));
            };
        });
    }

    /**
     * Get count of products in IndexedDB
     */
    async getProductCount() {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readonly');
            const store = transaction.objectStore(this.storeName);
            const request = store.count();
            request.onsuccess = () => {
                resolve(request.result);
            };
            request.onerror = () => {
                reject(new Error('Failed to get product count from IndexedDB'));
            };
        });
    }

    /**
     * Fetch products in batches from server and store in IndexedDB.
     * Fetches every page until all products are stored (scalable to 50k+).
     * @param {Function|null} progressCallback - optional callback({ currentPage, totalPages, stored, total })
     * @param {number} perPage - products per batch (100-1000, default 500)
     */
    async fetchAndStoreAllProducts(progressCallback = null, perPage = 500) {
        let currentPage = 1;
        let totalPages = 1;
        let totalStored = 0;
        let totalProducts = 0;
        let hasError = false;
        let errorMessage = '';
        try {
            await this.clearAllProducts();
            const firstResponse = await this.fetchProductsBatch(1, perPage);
            if (firstResponse.status === 'success') {
                totalPages = Math.max(1, firstResponse.pagination.total_pages);
                totalProducts = firstResponse.pagination.total || 0;
                const storeResult = await this.storeProducts(firstResponse.data);
                totalStored += storeResult.success;
                if (progressCallback) {
                    progressCallback({
                        currentPage: 1,
                        totalPages: totalPages,
                        stored: totalStored,
                        total: totalProducts
                    });
                }
                for (currentPage = 2; currentPage <= totalPages; currentPage++) {
                    try {
                        const response = await this.fetchProductsBatch(currentPage, perPage);
                        if (response.status === 'success') {
                            const storeResult = await this.storeProducts(response.data);
                            totalStored += storeResult.success;
                            if (progressCallback) {
                                progressCallback({
                                    currentPage: currentPage,
                                    totalPages: totalPages,
                                    stored: totalStored,
                                    total: totalProducts
                                });
                            }
                            await this.delay(100);
                        } else {
                            hasError = true;
                            errorMessage = response.message || 'Failed to fetch products';
                            break;
                        }
                    } catch (error) {
                        hasError = true;
                        errorMessage = error.message || 'Error fetching products';
                        break;
                    }
                }
                return {
                    success: !hasError,
                    totalStored: totalStored,
                    totalPages: totalPages,
                    error: hasError ? errorMessage : null
                };
            } else {
                return {
                    success: false,
                    totalStored: 0,
                    totalPages: 0,
                    error: firstResponse.message || 'Failed to fetch products'
                };
            }
        } catch (error) {
            return {
                success: false,
                totalStored: totalStored,
                totalPages: totalPages,
                error: error.message || 'Unknown error occurred'
            };
        }
    }

    /**
     * Fetch a single batch of products from server.
     * Uses per_page (default 500) so all products sync efficiently (e.g. 50k = 100 requests).
     */
    async fetchProductsBatch(page, perPage = 500) {
        const baseUrl = $('#base_url').val() || window.location.origin;
        const url = `${baseUrl}/pos/products/batch?page=${page}&per_page=${perPage}`;
        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin'
            });
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error('Error fetching products batch:', error);
            return {
                status: 'error',
                message: error.message
            };
        }
    }

    /**
     * Utility function to add delay
     */
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Start product sync with UI updates
     */
    async startSync() {
        if (this.syncInProgress) {
            if (typeof showWarningNotification !== 'undefined') {
                showWarningNotification('Product sync is already in progress');
            } else {
                alert('Product sync is already in progress');
            }
            return;
        }

        if (!('indexedDB' in window)) {
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('IndexedDB is not supported in this browser');
            } else {
                alert('IndexedDB is not supported in this browser');
            }
            return;
        }

        this.syncInProgress = true;
        $('#pos-sync-products-btn').prop('disabled', true);
        
        const syncModal = document.getElementById('pos-sync-progress-modal');
        if (syncModal) {
            const modal = new bootstrap.Modal(syncModal);
            $('#pos-sync-close-btn').hide();
            $('#pos-sync-error').hide();
            this.updateSyncProgress(0, 0, 0, 0, 'Starting sync...');
            modal.show();

            try {
                await this.init();
                const result = await this.fetchAndStoreAllProducts((progress) => {
                    this.updateSyncProgress(
                        progress.currentPage,
                        progress.totalPages,
                        progress.stored,
                        progress.total,
                        `Syncing page ${progress.currentPage} of ${progress.totalPages}...`
                    );
                });

                this.syncInProgress = false;
                $('#pos-sync-products-btn').prop('disabled', false);
                
                if (result.success) {
                    this.updateSyncProgress(
                        result.totalPages,
                        result.totalPages,
                        result.totalStored,
                        result.totalStored,
                        'Sync completed successfully!'
                    );
                    this.updateFooterProgressBar(0, result.totalStored, result.totalStored, false);
                    if (typeof showSuccessNotification !== 'undefined') {
                        showSuccessNotification(`Successfully synced ${result.totalStored} products to IndexedDB`);
                    }
                    $('#pos-sync-close-btn').show();
                } else {
                    this.updateFooterProgressBar(0, 0, 0, false);
                    $('#pos-sync-error').show();
                    $('#pos-sync-error-message').text(result.error || 'Unknown error occurred');
                    this.updateSyncProgress(0, 0, 0, 0, 'Sync failed');
                    $('#pos-sync-close-btn').show();
                }
            } catch (error) {
                this.syncInProgress = false;
                $('#pos-sync-products-btn').prop('disabled', false);
                this.updateFooterProgressBar(0, 0, 0, false);
                $('#pos-sync-error').show();
                $('#pos-sync-error-message').text(error.message || 'Failed to sync products');
                this.updateSyncProgress(0, 0, 0, 0, 'Sync failed');
                $('#pos-sync-close-btn').show();
                console.error('Product sync error:', error);
            }
        } else {
            try {
                await this.init();
                const result = await this.fetchAndStoreAllProducts((progress) => {
                    this.updateFooterProgressBar(
                        progress.total > 0 ? Math.round((progress.stored / progress.total) * 100) : 0,
                        progress.stored,
                        progress.total,
                        true
                    );
                });
                this.updateFooterProgressBar(0, result.totalStored, result.totalStored, false);
                if (typeof showSuccessNotification !== 'undefined') {
                    showSuccessNotification('Products synced successfully');
                }
            } catch (error) {
                this.updateFooterProgressBar(0, 0, 0, false);
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('Failed to sync products: ' + error.message);
                }
                console.error('Product sync error:', error);
            } finally {
                this.syncInProgress = false;
                $('#pos-sync-products-btn').prop('disabled', false);
            }
        }
    }

    /**
     * Update sync progress UI (modal and footer progress bar)
     */
    updateSyncProgress(currentPage, totalPages, stored, total, statusText) {
        const percentage = total > 0 ? Math.round((stored / total) * 100) : 0;
        
        $('#pos-sync-status-text').text(statusText);
        $('#pos-sync-percentage').text(percentage + '%');
        $('#pos-sync-progress-bar').css('width', percentage + '%').attr('aria-valuenow', percentage);
        $('#pos-sync-details').text(`Page ${currentPage} of ${totalPages} | Stored: ${stored} of ${total} products`);
        
        this.updateFooterProgressBar(percentage, stored, total, true);
    }

    /**
     * Show/update/hide the footer product loading progress bar
     * @param {number} percentage - 0-100
     * @param {number} stored - loaded count
     * @param {number} total - total count
     * @param {boolean} show - true to show bar, false to hide
     */
    updateFooterProgressBar(percentage, stored, total, show) {
        const $wrap = $('#pos-product-load-progress-wrap');
        const $bar = $('#pos-product-load-progress-bar');
        const $text = $('#pos-product-load-progress-text');
        const $count = $('#pos-total-products-count');
        if (show) {
            $wrap.removeClass('d-none').addClass('d-flex');
            $('.loading-text').removeClass('d-none');
            $bar.css('width', percentage + '%').attr('aria-valuenow', percentage);
            $text.text(percentage + '%');
            if (total !== undefined && total !== null) {
                $count.text(typeof total === 'number' ? total.toLocaleString() : total);
            }
        } else {
            $('.loading-text').addClass('d-none');
            $wrap.addClass('d-none').removeClass('d-flex');
            $bar.css('width', '0%').attr('aria-valuenow', 0);
            $text.text('0%');
            if (stored !== undefined && stored !== null && stored > 0 && $count.length) {
                $count.text(typeof stored === 'number' ? stored.toLocaleString() : stored);
            }
        }
    }

    /**
     * Sync a single product to IndexedDB (for new products)
     * @param {number} productId - Product ID to sync
     */
    async syncSingleProduct(productId) {
        try {
            if (!this.isInitialized || !this.db) {
                await this.init();
            }
            
            const baseUrl = $('#base_url').val() || window.location.origin;
            const perPage = 500;
            const response = await fetch(`${baseUrl}/pos/products/batch?page=1&per_page=${perPage}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin'
            });

            if (response.ok) {
                const data = await response.json();
                if (data.status === 'success' && data.data) {
                    // Find the specific product
                    const product = data.data.find(p => p.id == productId);
                    if (product) {
                        await this.storeProducts([product]);
                        return true;
                    }
                    // If not found in first page, search through all pages
                    const totalPages = data.pagination?.total_pages || 1;
                    for (let page = 2; page <= totalPages; page++) {
                        const pageResponse = await fetch(`${baseUrl}/pos/products/batch?page=${page}&per_page=${perPage}`, {
                            method: 'GET',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            credentials: 'same-origin'
                        });
                        if (pageResponse.ok) {
                            const pageData = await pageResponse.json();
                            if (pageData.status === 'success' && pageData.data) {
                                const foundProduct = pageData.data.find(p => p.id == productId);
                                if (foundProduct) {
                                    await this.storeProducts([foundProduct]);
                                    return true;
                                }
                            }
                        }
                    }
                }
            }
            return false;
        } catch (error) {
            console.error('Error syncing single product:', error);
            return false;
        }
    }

    /**
     * Check and sync product if not in IndexedDB
     * @param {number} productId - Product ID to check
     */
    async ensureProductInIndexedDB(productId) {
        try {
            const product = await this.getProductById(productId);
            if (!product) {
                // Product not found, try to sync it
                await this.syncSingleProduct(productId);
            }
        } catch (error) {
            console.error('Error ensuring product in IndexedDB:', error);
        }
    }

    /**
     * Update product stock in IndexedDB
     * @param {number} productId - Product ID
     * @param {number} quantity - Quantity to decrease (positive number)
     */
    async decreaseProductStock(productId, quantity) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const store = transaction.objectStore(this.storeName);
            const request = store.get(productId);
            
            request.onsuccess = () => {
                const product = request.result;
                if (product) {
                    const currentStock = parseFloat(product.stock) || 0;
                    const newStock = Math.max(0, currentStock - quantity);
                    product.stock = newStock;
                    
                    // Update product in IndexedDB
                    const updateRequest = store.put(product);
                    updateRequest.onsuccess = () => {
                        resolve(true);
                    };
                    updateRequest.onerror = () => {
                        reject(new Error('Failed to update product stock in IndexedDB'));
                    };
                } else {
                    resolve(false); // Product not found
                }
            };
            
            request.onerror = () => {
                reject(new Error('Failed to get product from IndexedDB'));
            };
        });
    }

    /**
     * Remove IMEI/Serial numbers from product in IndexedDB
     * @param {number} productId - Product ID
     * @param {Array<string>} imeiSerialNumbers - Array of IMEI/Serial numbers to remove
     */
    async removeIMEISerialNumbers(productId, imeiSerialNumbers) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const store = transaction.objectStore(this.storeName);
            const request = store.get(productId);
            
            request.onsuccess = () => {
                const product = request.result;
                if (product && product.imei_number && Array.isArray(product.imei_number)) {
                    const originalCount = product.imei_number.length;
                    
                    // Remove the sold IMEI/serial numbers
                    const updatedIMEINumbers = product.imei_number.filter(imei => {
                        const imeiStr = String(imei).trim();
                        return !imeiSerialNumbers.some(sold => String(sold).trim() === imeiStr);
                    });
                    
                    product.imei_number = updatedIMEINumbers;
                    
                    // Also update stock (decrease by the number of IMEI/serial removed)
                    const currentStock = parseFloat(product.stock) || 0;
                    const removedCount = originalCount - updatedIMEINumbers.length;
                    product.stock = Math.max(0, currentStock - removedCount);
                    
                    // Update product in IndexedDB
                    const updateRequest = store.put(product);
                    updateRequest.onsuccess = () => {
                        resolve(true);
                    };
                    updateRequest.onerror = () => {
                        reject(new Error('Failed to remove IMEI/Serial numbers from IndexedDB'));
                    };
                } else {
                    resolve(false); // Product not found or not IMEI/Serial product
                }
            };
            
            request.onerror = () => {
                reject(new Error('Failed to get product from IndexedDB'));
            };
        });
    }

    /**
     * Decrease medicine stock for specific expiry dates
     * @param {number} productId - Product ID
     * @param {Array} medicineExpiryData - Array of {expiry_date, quantity}
     */
    async decreaseMedicineStock(productId, medicineExpiryData) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const store = transaction.objectStore(this.storeName);
            const request = store.get(productId);
            
            request.onsuccess = () => {
                const product = request.result;
                if (product && product.medicine && Array.isArray(product.medicine)) {
                    // Process each expiry date
                    medicineExpiryData.forEach(medExpiry => {
                        const expiryDate = medExpiry.expiry_date;
                        const quantityToDecrease = parseFloat(medExpiry.quantity) || 0;
                        
                        if (quantityToDecrease <= 0) return;
                        
                        // Find and update medicine entries with matching expiry date
                        let remainingQty = quantityToDecrease;
                        product.medicine = product.medicine.filter(med => {
                            if (med.expiry_imei_serial === expiryDate && remainingQty > 0) {
                                const currentStock = parseFloat(med.stock_quantity) || 0;
                                if (currentStock <= remainingQty) {
                                    // Remove this entry completely
                                    remainingQty -= currentStock;
                                    return false;
                                } else {
                                    // Decrease stock quantity
                                    med.stock_quantity = currentStock - remainingQty;
                                    remainingQty = 0;
                                    return true;
                                }
                            }
                            return true;
                        });
                    });
                    
                    // Update product in IndexedDB
                    const updateRequest = store.put(product);
                    updateRequest.onsuccess = () => {
                        resolve(true);
                    };
                    updateRequest.onerror = () => {
                        reject(new Error('Failed to update medicine stock in IndexedDB'));
                    };
                } else {
                    resolve(false); // Product not found or not medicine product
                }
            };
            
            request.onerror = () => {
                reject(new Error('Failed to get product from IndexedDB'));
            };
        });
    }
    
    /**
     * Update multiple products after sale (decrease stock and remove IMEI/serial)
     * @param {Array} cartItems - Array of cart items with product_id, quantity, selected_imei_serial, selected_medicine_expiry
     */
    async updateProductsAfterSale(cartItems) {
        try {
            const updatePromises = cartItems.map(async (item) => {
                try {
                    const productId = item.product_id;
                    const quantity = parseFloat(item.quantity) || 0;
                    const selectedImeiSerial = item.selected_imei_serial || [];
                    const selectedMedicineExpiry = item.selected_medicine_expiry || [];
                    const productType = item.product_type || '';
                    
                    // Check if it's IMEI/Serial product
                    const isIMEISerialProduct = productType === 'IMEI_Product' || productType === 'Serial_Product';
                    
                    // Check if it's Medicine_Product with expiry dates
                    const isMedicineProduct = productType === 'Medicine_Product' && selectedMedicineExpiry.length > 0;
                    
                    if (isIMEISerialProduct && selectedImeiSerial.length > 0) {
                        // Remove IMEI/Serial numbers (this also decreases stock)
                        const result = await this.removeIMEISerialNumbers(productId, selectedImeiSerial);
                        if (result) {
                            console.log(`Successfully removed ${selectedImeiSerial.length} IMEI/Serial numbers for product ${productId}`);
                        } else {
                            console.warn(`Failed to remove IMEI/Serial numbers for product ${productId} - product not found or not IMEI/Serial product`);
                        }
                    } else if (isMedicineProduct) {
                        // Decrease medicine stock for specific expiry dates
                        const result = await this.decreaseMedicineStock(productId, selectedMedicineExpiry);
                        if (result) {
                            console.log(`Successfully decreased medicine stock for product ${productId}`);
                        } else {
                            console.warn(`Failed to decrease medicine stock for product ${productId} - product not found or not medicine product`);
                        }
                    } else {
                        // Regular product - just decrease stock
                        if (quantity > 0) {
                            const result = await this.decreaseProductStock(productId, quantity);
                            if (result) {
                                console.log(`Successfully decreased stock by ${quantity} for product ${productId}`);
                            } else {
                                console.warn(`Failed to decrease stock for product ${productId} - product not found`);
                            }
                        }
                    }
                } catch (itemError) {
                    console.error(`Error updating product ${item.product_id} after sale:`, itemError);
                    // Continue with other items even if one fails
                }
            });
            
            await Promise.all(updatePromises);
            return true;
        } catch (error) {
            console.error('Error updating products after sale:', error);
            return false;
        }
    }

    /**
     * Save offline sale to IndexedDB
     * @param {Object} saleData - Sale data with sale_detail and sale_payment properties
     * @returns {Promise<number>} - Returns the ID of the saved sale
     */
    async saveOfflineSale(saleData) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.salesStoreName], 'readwrite');
            const store = transaction.objectStore(this.salesStoreName);
            
            // Add timestamp for tracking
            const saleRecord = {
                ...saleData,
                created_at: new Date().toISOString(),
                synced: false
            };
            
            const request = store.add(saleRecord);
            request.onsuccess = () => {
                resolve(request.result); // Returns the auto-increment ID
            };
            request.onerror = () => {
                reject(new Error('Failed to save offline sale to IndexedDB'));
            };
        });
    }

    /**
     * Get all unsynced offline sales
     * @returns {Promise<Array>} - Returns array of unsynced sales
     */
    async getUnsyncedSales() {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.salesStoreName], 'readonly');
            const store = transaction.objectStore(this.salesStoreName);
            const request = store.getAll();

            request.onsuccess = () => {
                const allSales = request.result;
                const unsyncedSales = allSales.filter(sale => !sale.synced);
                resolve(unsyncedSales);
            };
            request.onerror = () => {
                reject(new Error('Failed to get unsynced sales from IndexedDB'));
            };
        });
    }

    /**
     * Mark sale as synced
     * @param {number} saleId - Sale ID in IndexedDB
     * @returns {Promise<boolean>}
     */
    async markSaleAsSynced(saleId) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.salesStoreName], 'readwrite');
            const store = transaction.objectStore(this.salesStoreName);
            const request = store.get(saleId);
            
            request.onsuccess = () => {
                const sale = request.result;
                if (sale) {
                    sale.synced = true;
                    sale.synced_at = new Date().toISOString();
                    const updateRequest = store.put(sale);
                    updateRequest.onsuccess = () => resolve(true);
                    updateRequest.onerror = () => reject(new Error('Failed to mark sale as synced'));
                } else {
                    resolve(false);
                }
            };
            request.onerror = () => {
                reject(new Error('Failed to get sale from IndexedDB'));
            };
        });
    }

    /**
     * Delete synced sale from IndexedDB
     * @param {number} saleId - Sale ID in IndexedDB
     * @returns {Promise<boolean>}
     */
    async deleteSyncedSale(saleId) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.salesStoreName], 'readwrite');
            const store = transaction.objectStore(this.salesStoreName);
            const request = store.delete(saleId);
            
            request.onsuccess = () => {
                resolve(true);
            };
            request.onerror = () => {
                reject(new Error('Failed to delete synced sale from IndexedDB'));
            };
        });
    }

    /**
     * Get count of unsynced sales
     * @returns {Promise<number>}
     */
    async getUnsyncedSalesCount() {
        const unsyncedSales = await this.getUnsyncedSales();
        return unsyncedSales.length;
    }

    /**
     * Sync offline sales to server
     * @returns {Promise<Object>} - Returns sync result with success count and errors
     */
    async syncOfflineSales() {
        try {
            const unsyncedSales = await this.getUnsyncedSales();
            if (unsyncedSales.length === 0) {
                return { success: 0, errors: 0, message: 'No offline sales to sync' };
            }

            const baseUrl = $('#base_url').val() || window.location.origin;
            let successCount = 0;
            let errorCount = 0;
            const errors = [];

            for (const sale of unsyncedSales) {
                try {
                    // Prepare sale data for API (remove IndexedDB-specific fields)
                    const saleDataToSync = {
                        customer_id: sale.sale_detail.customer_id,
                        employee_id: sale.sale_detail.employee_id,
                        cart_items: sale.sale_detail.cart_items,
                        subtotal: sale.sale_detail.subtotal,
                        tax: sale.sale_detail.tax,
                        discount: sale.sale_detail.discount,
                        discount_type: sale.sale_detail.discount_type,
                        shipping: sale.sale_detail.shipping,
                        total_payable: sale.sale_detail.total_payable,
                        payments: sale.sale_payment.payments,
                        total_paid: sale.sale_payment.total_paid,
                        change_amount: sale.sale_payment.change_amount,
                        due_amount: sale.sale_payment.due_amount
                    };

                    const response = await fetch(`${baseUrl}/pos/sale`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(saleDataToSync)
                    });

                    const result = await response.json();

                    if (response.ok && result.status === 'success') {
                        // Mark as synced
                        await this.markSaleAsSynced(sale.id);
                        // Delete from IndexedDB after successful sync
                        await this.deleteSyncedSale(sale.id);
                        successCount++;
                    } else {
                        errorCount++;
                        console.error(`Sync failed for sale ${sale.id}:`, JSON.stringify(result, null, 2));

                        // Check if register is closed - notify user
                        if (result.register_closed) {
                            if (typeof showErrorNotification !== 'undefined') {
                                showErrorNotification('Register is closed. Please open the register to sync offline sales.');
                            }
                            // Stop syncing further sales - register needs to be open
                            break;
                        }

                        errors.push({
                            saleId: sale.id,
                            error: result.message || 'Unknown error',
                            errors: result.errors || null
                        });
                    }
                } catch (error) {
                    errorCount++;
                    console.error(`Sync network error for sale ${sale.id}:`, error);
                    errors.push({
                        saleId: sale.id,
                        error: error.message || 'Network error'
                    });
                }
            }

            return {
                success: successCount,
                errors: errorCount,
                total: unsyncedSales.length,
                errorDetails: errors
            };
        } catch (error) {
            console.error('Error syncing offline sales:', error);
            return {
                success: 0,
                errors: 0,
                total: 0,
                error: error.message || 'Failed to sync offline sales'
            };
        }
    }
}

// Initialize global instance
const posIndexedDB = new POSIndexedDB();

// Auto-initialize IndexedDB on page load
$(document).ready(function() {
    posIndexedDB.init().then(() => {
        console.log('IndexedDB initialized successfully');
    }).catch((error) => {
        console.error('Failed to initialize IndexedDB:', error);
    });

    // Sync button click handler
    $(document).on('click', '#pos-sync-products-btn', function(e) {
        e.preventDefault();

        Swal.fire({
            title: 'Alert!',
            text: "This will re-sync all products to IndexedDB. This may take a few minutes. Continue?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Continue',
            cancelButtonText: 'Cancel',
            customClass: {
                confirmButton: 'btn btn-primary me-3',
                cancelButton: 'btn btn-label-secondary'
            },
            buttonsStyling: false
        }).then(function(result) {
            if (result.value) {
                posIndexedDB.startSync();
            }
        });
    });

    // Auto-sync products on page load
    if ('indexedDB' in window) {
        setTimeout(function() {
            if (!posIndexedDB.syncInProgress) {
                posIndexedDB.startSync();
            }
        }, 500);
    }
});
