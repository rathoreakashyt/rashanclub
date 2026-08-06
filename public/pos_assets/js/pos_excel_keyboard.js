/**
 * Excel-like Keyboard Navigation for POS
 * Handles arrow keys, Tab, Enter, F-keys, and shortcuts
 */
class ExcelKeyboardNav {
    constructor() {
        this.currentRow = 0;
        this.currentCol = 0;
        this.isEditing = false;
        this.cartTable = null;
        this.productList = null;
        this.productIndex = 0;
        
        this.init();
    }

    init() {
        document.addEventListener('DOMContentLoaded', () => {
            this.cartTable = document.getElementById('excel-cart-tbody');
            this.productList = document.querySelector('.excel-product-list');
            this.setupGlobalKeys();
            this.setupCartNavigation();
            this.setupProductNavigation();
            this.setupToolbarActions();
            this.setupFormulaBar();
            this.updateStatus();
        });
    }

    // ============================================
    // GLOBAL KEYBOARD SHORTCUTS
    // ============================================
    setupGlobalKeys() {
        document.addEventListener('keydown', (e) => {
            // Don't handle if typing in a non-cart input
            const tag = e.target.tagName.toLowerCase();
            const isCartInput = e.target.closest('.excel-cart-panel');
            const isSearchInput = e.target.closest('.search-box');
            const isFormulaInput = e.target.closest('.excel-formula-bar');
            
            // F-key shortcuts
            if (e.key.startsWith('F') && e.key.length <= 3) {
                const fNum = parseInt(e.key.substring(1));
                if (fNum >= 1 && fNum <= 12) {
                    e.preventDefault();
                    this.handleFKey(fNum);
                    return;
                }
            }

            // Ctrl+K - Search
            if (e.ctrlKey && e.key === 'k') {
                e.preventDefault();
                this.focusSearch();
                return;
            }

            // Ctrl+N - New Sale
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                this.newSale();
                return;
            }

            // Ctrl+P - Print Last
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                this.printLastInvoice();
                return;
            }

            // Escape - Cancel/Back
            if (e.key === 'Escape') {
                if (this.isEditing) {
                    this.stopEditing();
                } else if (document.querySelector('.keyboard-hints-overlay.show')) {
                    document.querySelector('.keyboard-hints-overlay').classList.remove('show');
                }
                return;
            }

            // If not in cart or formula bar, skip cart navigation
            if (!isCartInput && !isFormulaInput) return;

            // Arrow keys in cart
            if (!this.isEditing && (e.key === 'ArrowUp' || e.key === 'ArrowDown' || 
                e.key === 'ArrowLeft' || e.key === 'ArrowRight')) {
                e.preventDefault();
                this.navigateCart(e.key);
                return;
            }

            // Enter - Start edit or move down
            if (e.key === 'Enter' && !this.isEditing) {
                e.preventDefault();
                this.startEditing();
                return;
            }

            // Enter while editing - Move down
            if (e.key === 'Enter' && this.isEditing) {
                e.preventDefault();
                this.moveDown();
                return;
            }

            // Tab - Move right
            if (e.key === 'Tab' && this.isEditing) {
                e.preventDefault();
                if (e.shiftKey) {
                    this.moveLeft();
                } else {
                    this.moveRight();
                }
                return;
            }

            // Delete/Backspace - Clear cell
            if ((e.key === 'Delete' || e.key === 'Backspace') && !this.isEditing) {
                if (e.target.closest('.excel-cart-panel')) {
                    e.preventDefault();
                    this.clearCell();
                }
                return;
            }

            // F2 - Edit cell
            if (e.key === 'F2' && !this.isEditing) {
                e.preventDefault();
                this.startEditing();
                return;
            }
        });
    }

    // ============================================
    // F-KEY HANDLERS
    // ============================================
    handleFKey(num) {
        switch(num) {
            case 1: this.showHelp(); break;
            case 2: this.startEditing(); break;
            case 3: this.focusSearch(); break;
            case 4: this.focusQuickAdd(); break;
            case 5: this.refreshProducts(); break;
            case 6: this.focusCustomerSelect(); break;
            case 7: this.holdSale(); break;
            case 8: this.openPayment(); break;
            case 9: this.listHolds(); break;
            case 10: this.newSale(); break;
            case 11: this.toggleFullscreen(); break;
            case 12: this.quickCash(); break;
        }
        this.updateFormulaBar(`F${num} - ${this.getFKeyLabel(num)}`);
    }

    getFKeyLabel(num) {
        const labels = {
            1: 'Help', 2: 'Edit Cell', 3: 'Search Products', 4: 'Quick Add',
            5: 'Refresh', 6: 'Select Customer', 7: 'Hold Sale', 8: 'Payment',
            9: 'List Holds', 10: 'New Sale', 11: 'Fullscreen', 12: 'Quick Cash'
        };
        return labels[num] || '';
    }

    // ============================================
    // CART TABLE NAVIGATION
    // ============================================
    setupCartNavigation() {
        if (!this.cartTable) return;

        // Click on cell to select
        this.cartTable.addEventListener('click', (e) => {
            const td = e.target.closest('td');
            if (!td || td.classList.contains('row-num') || td.classList.contains('excel-action-cell')) return;
            
            const tr = td.closest('tr');
            if (!tr) return;
            
            this.selectCell(tr.rowIndex, this.getColumnIndex(td));
        });

        // Double-click to edit
        this.cartTable.addEventListener('dblclick', (e) => {
            const td = e.target.closest('td');
            if (!td || td.classList.contains('row-num') || td.classList.contains('excel-action-cell')) return;
            
            const tr = td.closest('tr');
            if (!tr) return;
            
            this.selectCell(tr.rowIndex, this.getColumnIndex(td));
            this.startEditing();
        });
    }

    getColumnIndex(td) {
        const cols = ['product', 'qty', 'price', 'discount', 'total', 'action'];
        for (let i = 0; i < td.parentElement.children.length; i++) {
            if (td.parentElement.children[i] === td) {
                return Math.min(i, cols.length - 1);
            }
        }
        return 0;
    }

    navigateCart(key) {
        const rows = this.cartTable?.querySelectorAll('tr');
        if (!rows || rows.length === 0) return;

        switch(key) {
            case 'ArrowUp':
                this.currentRow = Math.max(0, this.currentRow - 1);
                break;
            case 'ArrowDown':
                this.currentRow = Math.min(rows.length - 1, this.currentRow + 1);
                break;
            case 'ArrowLeft':
                this.currentCol = Math.max(1, this.currentCol - 1); // Skip row num
                break;
            case 'ArrowRight':
                this.currentCol = Math.min(4, this.currentCol + 1); // Skip action
                break;
        }

        this.highlightCell();
    }

    selectCell(row, col) {
        this.currentRow = row;
        this.currentCol = col;
        this.highlightCell();
    }

    highlightCell() {
        // Remove previous selection
        this.cartTable?.querySelectorAll('td.cell-active, td.cell-editing').forEach(td => {
            td.classList.remove('cell-active', 'cell-editing');
        });
        this.cartTable?.querySelectorAll('tr.selected').forEach(tr => {
            tr.classList.remove('selected');
        });

        const rows = this.cartTable?.querySelectorAll('tr');
        if (!rows || !rows[this.currentRow]) return;

        const row = rows[this.currentRow];
        row.classList.add('selected');

        const cells = row.querySelectorAll('td');
        const targetCol = this.currentCol + 1; // +1 for row num offset
        if (cells[targetCol]) {
            cells[targetCol].classList.add('cell-active');
            
            // Scroll into view
            cells[targetCol].scrollIntoView({ block: 'nearest', inline: 'nearest' });
        }

        this.updateCellRef();
    }

    startEditing() {
        const rows = this.cartTable?.querySelectorAll('tr');
        if (!rows || !rows[this.currentRow]) return;

        const row = rows[this.currentRow];
        const cells = row.querySelectorAll('td');
        const targetCol = this.currentCol + 1;
        const td = cells[targetCol];
        if (!td) return;

        const input = td.querySelector('input');
        if (!input) return;

        td.classList.add('cell-editing');
        this.isEditing = true;
        input.focus();
        input.select();

        // Handle input blur
        input.addEventListener('blur', () => {
            if (this.isEditing) {
                this.stopEditing();
            }
        }, { once: true });

        // Handle Enter/Tab in input
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.moveDown();
            } else if (e.key === 'Tab') {
                e.preventDefault();
                if (e.shiftKey) {
                    this.moveLeft();
                } else {
                    this.moveRight();
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                this.stopEditing();
            }
        });
    }

    stopEditing() {
        const activeCell = this.cartTable?.querySelector('.cell-editing');
        if (activeCell) {
            const input = activeCell.querySelector('input');
            if (input) {
                input.blur();
                // Trigger input event to update cart
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
            activeCell.classList.remove('cell-editing');
        }
        this.isEditing = false;
        this.highlightCell();
    }

    moveUp() {
        this.stopEditing();
        this.currentRow = Math.max(0, this.currentRow - 1);
        this.highlightCell();
        this.startEditing();
    }

    moveDown() {
        this.stopEditing();
        const rows = this.cartTable?.querySelectorAll('tr');
        if (rows) {
            this.currentRow = Math.min(rows.length - 1, this.currentRow + 1);
        }
        this.highlightCell();
        this.startEditing();
    }

    moveLeft() {
        this.stopEditing();
        this.currentCol = Math.max(1, this.currentCol - 1);
        this.highlightCell();
        this.startEditing();
    }

    moveRight() {
        this.stopEditing();
        this.currentCol = Math.min(4, this.currentCol + 1);
        this.highlightCell();
        this.startEditing();
    }

    clearCell() {
        const rows = this.cartTable?.querySelectorAll('tr');
        if (!rows || !rows[this.currentRow]) return;

        const row = rows[this.currentRow];
        const cells = row.querySelectorAll('td');
        const targetCol = this.currentCol + 1;
        const td = cells[targetCol];
        if (!td) return;

        const input = td.querySelector('input');
        if (input) {
            input.value = '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    // ============================================
    // PRODUCT LIST NAVIGATION
    // ============================================
    setupProductNavigation() {
        document.addEventListener('keydown', (e) => {
            const isSearchFocused = document.activeElement?.closest('.search-box');
            const isQuickAddFocused = document.activeElement?.closest('.quick-add');
            
            if (isSearchFocused || isQuickAddFocused) return;

            const items = document.querySelectorAll('.excel-product-item');
            if (!items.length) return;

            // Arrow keys for product list
            if (e.key === 'ArrowDown' && e.altKey) {
                e.preventDefault();
                this.productIndex = Math.min(items.length - 1, this.productIndex + 1);
                this.highlightProduct(items);
            } else if (e.key === 'ArrowUp' && e.altKey) {
                e.preventDefault();
                this.productIndex = Math.max(0, this.productIndex - 1);
                this.highlightProduct(items);
            } else if (e.key === 'Enter' && !this.isEditing && !e.target.closest('.excel-cart-panel')) {
                e.preventDefault();
                if (items[this.productIndex]) {
                    items[this.productIndex].click();
                }
            }
        });
    }

    highlightProduct(items) {
        items.forEach((item, i) => {
            item.classList.toggle('active', i === this.productIndex);
        });
        items[this.productIndex]?.scrollIntoView({ block: 'nearest' });
    }

    // ============================================
    // TOOLBAR & ACTION HANDLERS
    // ============================================
    setupToolbarActions() {
        // Toolbar buttons
        document.querySelector('[data-action="new-sale"]')?.addEventListener('click', () => this.newSale());
        document.querySelector('[data-action="hold"]')?.addEventListener('click', () => this.holdSale());
        document.querySelector('[data-action="holds-list"]')?.addEventListener('click', () => this.listHolds());
        document.querySelector('[data-action="payment"]')?.addEventListener('click', () => this.openPayment());
        document.querySelector('[data-action="quick-cash"]')?.addEventListener('click', () => this.quickCash());
        document.querySelector('[data-action="search"]')?.addEventListener('click', () => this.focusSearch());
        document.querySelector('[data-action="help"]')?.addEventListener('click', () => this.showHelp());
    }

    setupFormulaBar() {
        const formulaInput = document.querySelector('.formula-input');
        if (formulaInput) {
            formulaInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const value = formulaInput.value.trim();
                    if (value) {
                        this.processFormulaInput(value);
                        formulaInput.value = '';
                    }
                }
            });
        }
    }

    processFormulaInput(value) {
        // Try to add product by code/name
        const searchInput = document.getElementById('pos-product-search');
        if (searchInput) {
            searchInput.value = value;
            searchInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    updateFormulaBar(text) {
        const formulaInput = document.querySelector('.formula-input');
        if (formulaInput) {
            formulaInput.value = text;
        }
    }

    updateCellRef() {
        const cellRef = document.querySelector('.cell-ref');
        if (cellRef) {
            const colLetter = String.fromCharCode(65 + this.currentCol);
            cellRef.textContent = `${colLetter}${this.currentRow + 1}`;
        }
    }

    updateStatus() {
        const statusLeft = document.querySelector('.status-left');
        if (statusLeft) {
            const totalItems = this.cartTable?.querySelectorAll('tr').length || 0;
            const totalAmount = document.getElementById('excel-total-amount')?.textContent || '0.00';
            statusLeft.innerHTML = `
                <span class="status-item">Items: <strong>${totalItems}</strong></span>
                <span class="status-item">Total: <strong>${totalAmount}</strong></span>
                <span class="status-item">Row: ${this.currentRow + 1} | Col: ${String.fromCharCode(65 + this.currentCol)}</span>
            `;
        }
    }

    // ============================================
    // ACTION IMPLEMENTATIONS
    // ============================================
    focusSearch() {
        const searchInput = document.getElementById('pos-product-search');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }

    focusQuickAdd() {
        const quickAdd = document.querySelector('.quick-add input');
        if (quickAdd) {
            quickAdd.focus();
            quickAdd.select();
        }
    }

    focusCustomerSelect() {
        const customerSelect = document.getElementById('customer-select');
        if (customerSelect) {
            customerSelect.focus();
        }
    }

    newSale() {
        if (confirm('Start new sale? Current cart will be cleared.')) {
            document.querySelector('.clear-cart-btn')?.click();
            this.currentRow = 0;
            this.currentCol = 1;
            this.updateStatus();
        }
    }

    holdSale() {
        document.querySelector('.add-hold-btn')?.click();
    }

    listHolds() {
        document.querySelector('.list-hold-btn')?.click();
    }

    openPayment() {
        document.getElementById('pos-payment-btn')?.click();
    }

    quickCash() {
        document.getElementById('pos-cash-register-btn')?.click();
    }

    printLastInvoice() {
        document.getElementById('pos-print-last-invoice-btn')?.click();
    }

    toggleFullscreen() {
        document.getElementById('pos-fullscreen-btn')?.click();
    }

    refreshProducts() {
        document.getElementById('pos-sync-products-btn')?.click();
    }

    showHelp() {
        const overlay = document.querySelector('.keyboard-hints-overlay');
        if (overlay) {
            overlay.classList.toggle('show');
        }
    }
}

// Initialize
const excelKeyboard = new ExcelKeyboardNav();
