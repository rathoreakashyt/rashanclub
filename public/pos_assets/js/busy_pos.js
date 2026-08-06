/**
 * Busy POS - Grid-based POS integrated with existing POS system
 * Uses pos_indexeddb.js for products, pos_cart.js for cart, existing modals for payment/hold/etc.
 */
(function() {
    'use strict';

    let products = [];
    let ddIndex = -1;
    let searchTimeout = null;
    const DB = new POSIndexedDB();

    // ==========================================
    // INIT
    // ==========================================
    document.addEventListener('DOMContentLoaded', async function() {
        await loadProducts();
        setupGridListeners();
        setupKeyboard();
        setupActionButtons();
        startClock();
        focusItemInput(1);
    });

    async function loadProducts() {
        try {
            await DB.init();
            products = await DB.getAllProducts();
            if (!products || products.length === 0) {
                await DB.fetchAndStoreAllProducts();
                products = await DB.getAllProducts();
            }
            console.log('[BusyPOS] Products loaded:', products.length);
        } catch (e) {
            console.error('[BusyPOS] Failed to load products:', e);
        }
    }

    // ==========================================
    // SYNC GRID → posCartManager
    // ==========================================
    function syncGridToCart() {
        if (typeof posCartManager === 'undefined') return;
        posCartManager.cartItems = [];
        document.querySelectorAll('#busy-cart-tbody tr[data-row]').forEach(tr => {
            const pid = tr.dataset.productId;
            if (!pid) return;
            const rowNum = parseInt(tr.dataset.row);
            const qty = parseFloat(getCellValue(tr, 'qty')) || 1;
            const price = parseFloat(getCellValue(tr, 'price')) || 0;
            const disc = parseFloat(getCellValue(tr, 'disc')) || 0;
            const taxStr = getCellValue(tr, 'tax');
            const taxPct = parseFloat(taxStr) || 0;
            const productType = tr.dataset.productType || 'General_Product';
            const taxType = tr.dataset.taxType || 'Inclusive';
            const applicableTaxId = tr.dataset.applicableTaxId || null;

            const taxInfo = [];
            if (taxPct > 0) {
                taxInfo.push({
                    tax_field_name: 'GST',
                    tax_field_percentage: taxPct,
                    tax_field_amount: 0
                });
            }

            posCartManager.cartItems.push({
                product_id: parseInt(pid),
                product_name: getCellValue(tr, 'item'),
                product_code: '',
                product_type: productType,
                quantity: qty,
                unit_price: price,
                mrp_price: parseFloat(getCellValue(tr, 'mrp')) || price,
                discount: disc,
                discount_type: 'fixed',
                total: parseFloat(getCellValue(tr, 'amount')) || 0,
                tax_information: taxInfo,
                applicable_tax_id: applicableTaxId,
                tax_type: taxType,
                item_seller_id: null,
                promotion: null,
                has_promotion_discount: false,
                is_promotion_free_item: false,
                promotion_id: null,
                combo_items: null,
                selected_imei_serial: [],
                selected_medicine_expiry: []
            });
        });
        posCartManager.updateCartSummary();
    }

    // ==========================================
    // PRODUCT SEARCH & DROPDOWN
    // ==========================================
    function searchProducts(term) {
        if (!term || term.length < 1) return [];
        const t = term.toLowerCase();
        return products.filter(p => {
            return (p.name && p.name.toLowerCase().includes(t)) ||
                   (p.code && p.code.toLowerCase().includes(t)) ||
                   (p.alternative_name && p.alternative_name.toLowerCase().includes(t)) ||
                   (p.generic_name && p.generic_name.toLowerCase().includes(t)) ||
                   (p.brand_name && p.brand_name.toLowerCase().includes(t));
        }).filter(p => {
            const stock = parseFloat(p.stock) || 0;
            return stock > 0;
        }).slice(0, 20);
    }

    function showDropdown(rowNum, results) {
        const dd = document.getElementById('dd-row-' + rowNum);
        if (!dd) return;
        if (results.length === 0) {
            dd.innerHTML = '<div class="dd-empty">No products found</div>';
            dd.classList.add('show');
            ddIndex = -1;
            return;
        }
        let html = '<div class="dd-header"><span>Item Name</span><span>Code</span><span>MRP</span><span>Price</span><span>Stock</span></div>';
        results.forEach((p, i) => {
            const stock = parseFloat(p.stock) || 0;
            html += `<div class="dd-item" data-idx="${i}" data-id="${p.id}">
                <span class="dd-name">${esc(p.name||'')}</span>
                <span class="dd-code">${esc(p.code||'')}</span>
                <span class="dd-mrp">${fmt(p.mrp_price)}</span>
                <span class="dd-price">${fmt(p.sale_price)}</span>
                <span class="dd-stock${stock<=0?' oos':''}">${stock}</span>
            </div>`;
        });
        dd.innerHTML = html;
        dd.classList.add('show');
        ddIndex = -1;
        dd.querySelectorAll('.dd-item').forEach(el => {
            el.addEventListener('mousedown', function(e) {
                e.preventDefault();
                selectProduct(rowNum, parseInt(this.dataset.id));
            });
        });
    }

    function hideDropdown(rowNum) {
        const dd = document.getElementById('dd-row-' + rowNum);
        if (dd) { dd.classList.remove('show'); dd.innerHTML = ''; }
        ddIndex = -1;
    }

    function hideAllDropdowns() {
        document.querySelectorAll('.busy-product-dropdown').forEach(dd => { dd.classList.remove('show'); dd.innerHTML=''; });
        ddIndex = -1;
    }

    function highlightDD(rowNum, idx) {
        const dd = document.getElementById('dd-row-' + rowNum);
        if (!dd) return;
        const items = dd.querySelectorAll('.dd-item');
        items.forEach((el, i) => {
            el.classList.toggle('active', i === idx);
            if (i === idx) el.scrollIntoView({ block: 'nearest' });
        });
    }

    // ==========================================
    // SELECT PRODUCT → FILL ROW
    // ==========================================
    function selectProduct(rowNum, productId) {
        const product = products.find(p => p.id === productId);
        if (!product) return;
        const tr = document.querySelector(`tr[data-row="${rowNum}"]`);
        if (!tr) return;
        const stock = parseFloat(product.stock) || 0;
        if (stock <= 0) { alert('Out of stock!'); return; }

        tr.dataset.productId = product.id;
        tr.dataset.productType = product.type || 'General_Product';
        tr.dataset.taxType = product.tax_type || 'Inclusive';
        tr.dataset.applicableTaxId = product.applicable_tax_id || '';

        // Tax calculation
        const custSelect = document.getElementById('customer-select');
        const custStateId = custSelect ? custSelect.options[custSelect.selectedIndex]?.dataset.stateId : null;
        let taxRate = 0;
        const applicableTaxId = product.applicable_tax_id;
        if (applicableTaxId && window.posTaxs && window.posTaxs.length > 0) {
            const isIntraState = custStateId && window.posOutletStateId && (custStateId == window.posOutletStateId);
            if (isIntraState) {
                const cgst = window.posTaxs.find(t => t.parent_tax_id == applicableTaxId && t.tax_name?.toLowerCase().includes('cgst'));
                if (cgst) taxRate = parseFloat(cgst.tax_rate) || 0;
            } else {
                const igst = window.posTaxs.find(t => t.parent_tax_id == applicableTaxId && t.tax_name?.toLowerCase().includes('igst'));
                if (igst) taxRate = parseFloat(igst.tax_rate) || 0;
            }
        } else if (product.tax_information && product.tax_information.length > 0) {
            product.tax_information.forEach(t => { taxRate += parseFloat(t.tax_field_percentage) || 0; });
        }

        let salePrice = parseFloat(product.sale_price) || 0;
        let mrp = parseFloat(product.mrp_price) || 0;
        const custType = document.getElementById('selected-customer-type')?.value || '';
        if (custType === 'wholesale' && product.whole_sale_price) salePrice = parseFloat(product.whole_sale_price) || salePrice;

        setCell(tr, 'item', product.name || '');
        setCell(tr, 'hsn', product.hsn_code || '');
        setCell(tr, 'mrp', fmt(mrp));
        setCell(tr, 'qty', '1');
        setCell(tr, 'unit', product.sale_unit_name || 'PCS');
        setCell(tr, 'price', fmt(salePrice));
        setCell(tr, 'disc', '');
        setCell(tr, 'tax', taxRate > 0 ? taxRate + '%' : '');

        calculateRow(tr);
        hideDropdown(rowNum);
        tr.querySelector('.busy-qty-input')?.focus();
    }

    // ==========================================
    // ROW CALCULATION
    // ==========================================
    function calculateRow(tr) {
        const qty = parseFloat(getCell(tr, 'qty')) || 0;
        const price = parseFloat(getCell(tr, 'price')) || 0;
        const disc = parseFloat(getCell(tr, 'disc')) || 0;
        const amount = (qty * price) - disc;
        setCell(tr, 'amount', fmt(amount));
        updateTotals();
    }

    function updateTotals() {
        let totalQty = 0, totalAmount = 0, itemCount = 0;
        document.querySelectorAll('#busy-cart-tbody tr[data-row]').forEach(tr => {
            if (!tr.dataset.productId) return;
            totalQty += parseFloat(getCell(tr, 'qty')) || 0;
            totalAmount += parseFloat(getCell(tr, 'amount')) || 0;
            itemCount++;
        });
        document.getElementById('busy-total-qty').textContent = totalQty || '0';
        document.getElementById('busy-total-amount').textContent = fmt(totalAmount);
        document.getElementById('busy-total-payable').textContent = fmt(totalAmount);
        const lbl = document.querySelector('.busy-total-box .total-label');
        if (lbl) lbl.textContent = `Total Amount (${itemCount} items) -`;
        calculateTaxBreakdown();
    }

    function calculateTaxBreakdown() {
        let totalCGST = 0, totalSGST = 0, totalIGST = 0;
        const custSelect = document.getElementById('customer-select');
        const custStateId = custSelect ? custSelect.options[custSelect.selectedIndex]?.dataset.stateId : null;
        const isIntraState = custStateId && window.posOutletStateId && (custStateId == window.posOutletStateId);
        let itemLines = [];

        document.querySelectorAll('#busy-cart-tbody tr[data-row]').forEach(tr => {
            if (!tr.dataset.productId) return;
            const taxPct = parseFloat(getCell(tr, 'tax')) || 0;
            if (taxPct <= 0) return;
            const amt = parseFloat(getCell(tr, 'amount')) || 0;
            const name = getCell(tr, 'item') || 'Item';
            const qty = getCell(tr, 'qty') || '1';
            if (isIntraState) {
                const half = taxPct / 2;
                const c = (amt * half) / (100 + taxPct);
                const s = (amt * half) / (100 + taxPct);
                totalCGST += c; totalSGST += s;
                itemLines.push(`<div class="bill-line"><span>${esc(name)} (${qty}x)</span><span>CGST ${half}%: ${fmt(c)} | SGST ${half}%: ${fmt(s)}</span></div>`);
            } else {
                const ig = (amt * taxPct) / (100 + taxPct);
                totalIGST += ig;
                itemLines.push(`<div class="bill-line"><span>${esc(name)} (${qty}x)</span><span>IGST ${taxPct}%: ${fmt(ig)}</span></div>`);
            }
        });

        document.getElementById('busy-tax-lines').innerHTML = `
            <div class="bill-items-section">${itemLines.length ? itemLines.join('') : '<div class="bill-line bill-line-empty">No taxable items</div>'}</div>
            <div class="bill-divider"></div>
            <div class="tax-line tax-total-line"><span class="tax-label"><strong>CGST:</strong></span><span class="tax-value"><strong>${fmt(totalCGST)}</strong></span></div>
            <div class="tax-line tax-total-line"><span class="tax-label"><strong>SGST:</strong></span><span class="tax-value"><strong>${fmt(totalSGST)}</strong></span></div>
            <div class="tax-line tax-total-line"><span class="tax-label"><strong>IGST:</strong></span><span class="tax-value"><strong>${fmt(totalIGST)}</strong></span></div>
            <div class="bill-divider"></div>
            <div class="tax-line tax-total-line"><span class="tax-label"><strong>Total Tax:</strong></span><span class="tax-value"><strong>${fmt(totalCGST+totalSGST+totalIGST)}</strong></span></div>`;
    }

    // ==========================================
    // GRID EVENT LISTENERS
    // ==========================================
    function setupGridListeners() {
        const tbody = document.getElementById('busy-cart-tbody');

        // Item search
        tbody.addEventListener('input', function(e) {
            if (!e.target.classList.contains('busy-item-input')) return;
            const tr = e.target.closest('tr');
            const rowNum = parseInt(tr.dataset.row);
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const term = e.target.value.trim();
                if (term.length >= 1) showDropdown(rowNum, searchProducts(term));
                else hideDropdown(rowNum);
            }, 150);
        });

        // Item keyboard nav
        tbody.addEventListener('keydown', function(e) {
            if (!e.target.classList.contains('busy-item-input')) return;
            const tr = e.target.closest('tr');
            const rowNum = parseInt(tr.dataset.row);
            if (e.key === 'ArrowDown') { e.preventDefault(); const items = document.querySelectorAll('#dd-row-'+rowNum+' .dd-item'); if(items.length){ddIndex=Math.min(ddIndex+1,items.length-1);highlightDD(rowNum,ddIndex);} }
            else if (e.key === 'ArrowUp') { e.preventDefault(); const items = document.querySelectorAll('#dd-row-'+rowNum+' .dd-item'); if(items.length){ddIndex=Math.max(ddIndex-1,0);highlightDD(rowNum,ddIndex);} }
            else if (e.key === 'Enter') { e.preventDefault(); const items = document.querySelectorAll('#dd-row-'+rowNum+' .dd-item'); if(ddIndex>=0&&ddIndex<items.length){selectProduct(rowNum,parseInt(items[ddIndex].dataset.id));}else if(items.length===1){selectProduct(rowNum,parseInt(items[0].dataset.id));} }
            else if (e.key === 'Escape') { hideDropdown(rowNum); e.target.blur(); }
        });

        tbody.addEventListener('focusout', function(e) { if(e.target.classList.contains('busy-item-input')){const r=parseInt(e.target.closest('tr').dataset.row);setTimeout(()=>hideDropdown(r),200);} });
        tbody.addEventListener('focusin', function(e) { if(e.target.classList.contains('busy-item-input')){const r=parseInt(e.target.closest('tr').dataset.row);const t=e.target.value.trim();if(t.length>=1)showDropdown(r,searchProducts(t));} });

        // Qty/Price/Disc change
        tbody.addEventListener('input', function(e) {
            if (e.target.classList.contains('busy-qty-input')||e.target.classList.contains('busy-price-input')||e.target.classList.contains('busy-disc-input')) {
                calculateRow(e.target.closest('tr'));
            }
        });

        // Tab navigation
        tbody.addEventListener('keydown', function(e) {
            if (e.key !== 'Tab') return;
            const input = e.target;
            if (!input.classList.contains('grid-input')) return;
            const tr = input.closest('tr');
            const editableCols = ['item','qty','price','disc'];
            const curIdx = editableCols.indexOf(input.dataset.col);
            if (e.shiftKey) {
                if (curIdx > 0) { e.preventDefault(); tr.querySelector(`.grid-input[data-col="${editableCols[curIdx-1]}"]`)?.focus(); }
            } else {
                if (curIdx < editableCols.length - 1) { e.preventDefault(); tr.querySelector(`.grid-input[data-col="${editableCols[curIdx+1]}"]`)?.focus(); }
                else { const next = tr.nextElementSibling; if(next&&!next.classList.contains('busy-totals-row')){e.preventDefault();next.querySelector('.busy-item-input')?.focus();} }
            }
        });
    }

    // ==========================================
    // KEYBOARD SHORTCUTS
    // ==========================================
    function setupKeyboard() {
        document.addEventListener('keydown', function(e) {
            if (e.target.tagName==='INPUT'||e.target.tagName==='SELECT'||e.target.tagName==='TEXTAREA') return;
            switch(e.key) {
                case 'F1': e.preventDefault(); focusItemInput(1); break;
                case 'F2': e.preventDefault(); focusNextEmpty(); break;
                case 'F3': e.preventDefault(); document.getElementById('pos-add-customer-btn')?.click(); break;
                case 'F4': e.preventDefault(); calculateTaxBreakdown(); break;
                case 'F5': e.preventDefault(); openPayment(); break;
                case 'F6': e.preventDefault(); openPayment(); break;
                case 'F7': e.preventDefault(); openAddHold(); break;
                case 'F8': e.preventDefault(); openPayment(); break;
                case 'F9': e.preventDefault(); deleteLine(); break;
                case 's': if(e.ctrlKey){e.preventDefault();openPayment();} break;
                case 'S': e.preventDefault(); openPayment(); break;
                case 'c': if(!e.ctrlKey){e.preventDefault();clearAll();} break;
                case 'f': e.preventDefault(); toggleFullscreen(); break;
            }
        });
    }

    // ==========================================
    // ACTION BUTTONS
    // ==========================================
    function setupActionButtons() {
        // F-key sidebar
        document.getElementById('busy-f1-help')?.addEventListener('click', () => focusItemInput(1));
        document.getElementById('busy-f2-additem')?.addEventListener('click', () => focusNextEmpty());
        document.getElementById('busy-f3-addmaster')?.addEventListener('click', () => document.getElementById('pos-add-customer-btn')?.click());
        document.getElementById('busy-f4-tax')?.addEventListener('click', () => calculateTaxBreakdown());
        document.getElementById('busy-f5-payment')?.addEventListener('click', () => openPayment());
        document.getElementById('busy-f7-hold')?.addEventListener('click', () => openAddHold());
        document.getElementById('busy-f9-del')?.addEventListener('click', () => deleteLine());
        document.getElementById('busy-sc-save')?.addEventListener('click', () => openPayment());
        document.getElementById('busy-sc-clear')?.addEventListener('click', () => clearAll());
        document.getElementById('busy-sc-fullscreen')?.addEventListener('click', () => toggleFullscreen());

        // Bottom action bar
        document.getElementById('busy-btn-save')?.addEventListener('click', () => openPayment());
        document.getElementById('busy-btn-quit')?.addEventListener('click', () => clearAll());
        document.getElementById('busy-btn-hold')?.addEventListener('click', () => openAddHold());
        document.getElementById('busy-btn-saleslist')?.addEventListener('click', () => openSalesList());
        document.getElementById('busy-btn-register')?.addEventListener('click', () => openRegisterSummary());
        document.getElementById('busy-btn-refresh')?.addEventListener('click', async () => { await loadProducts(); alert('Refreshed! ' + products.length + ' products'); });

        // Sidebar toggle
        document.getElementById('busy-sidebar-toggle')?.addEventListener('click', () => document.getElementById('busy-sidebar')?.classList.toggle('show'));
    }

    // ==========================================
    // FEATURE: OPEN PAYMENT MODAL
    // ==========================================
    function openPayment() {
        const hasItems = document.querySelector('#busy-cart-tbody tr[data-row][data-product-id]');
        if (!hasItems) { alert('No items in cart!'); return; }
        syncGridToCart();
        if (typeof posCartManager !== 'undefined') {
            posCartManager.openPaymentModal();
        }
    }

    // ==========================================
    // FEATURE: HOLD SALE
    // ==========================================
    function openAddHold() {
        const hasItems = document.querySelector('#busy-cart-tbody tr[data-row][data-product-id]');
        if (!hasItems) { alert('No items in cart!'); return; }
        syncGridToCart();
        document.querySelector('.add-hold-btn')?.click();
    }

    // ==========================================
    // FEATURE: SALES LIST
    // ==========================================
    function openSalesList() {
        document.querySelector('[data-bs-target="#modal_pos_sales"]')?.click();
    }

    // ==========================================
    // FEATURE: SALE RETURNS
    // ==========================================
    function openSaleReturns() {
        document.querySelector('[data-bs-target="#modal_pos_sale_returns"]')?.click();
    }

    // ==========================================
    // FEATURE: REGISTER SUMMARY
    // ==========================================
    function openRegisterSummary() {
        document.getElementById('pos-register-btn')?.click();
    }

    // ==========================================
    // FEATURE: CALCULATOR
    // ==========================================
    function openCalculator() {
        document.getElementById('pos-calculator-btn')?.click();
    }

    // ==========================================
    // FEATURE: PRINT LAST INVOICE
    // ==========================================
    function printLastInvoice() {
        document.getElementById('pos-print-last-invoice-btn')?.click();
    }

    // ==========================================
    // FEATURE: CLEAR CART
    // ==========================================
    function clearAll() {
        if (!confirm('Clear all items?')) return;
        document.querySelectorAll('#busy-cart-tbody tr[data-row]').forEach(tr => {
            tr.dataset.productId = '';
            tr.dataset.productType = '';
            tr.dataset.taxType = '';
            tr.dataset.applicableTaxId = '';
            tr.querySelectorAll('.grid-input').forEach(inp => { inp.value = ''; });
        });
        if (typeof posCartManager !== 'undefined') posCartManager.clearCart();
        updateTotals();
        focusItemInput(1);
    }

    // ==========================================
    // FEATURE: DELETE LINE
    // ==========================================
    function deleteLine() {
        const focused = document.activeElement;
        let tr = focused?.closest?.('tr[data-row]');
        if (!tr || tr.classList.contains('busy-totals-row')) {
            const rows = document.querySelectorAll('#busy-cart-tbody tr[data-row]:not(.busy-totals-row)');
            for (let i = rows.length - 1; i >= 0; i--) {
                if (rows[i].dataset.productId) { tr = rows[i]; break; }
            }
        }
        if (!tr) return;
        tr.dataset.productId = '';
        tr.dataset.productType = '';
        tr.dataset.taxType = '';
        tr.dataset.applicableTaxId = '';
        tr.querySelectorAll('.grid-input').forEach(inp => { inp.value = ''; });
        updateTotals();
        tr.querySelector('.busy-item-input')?.focus();
    }

    // ==========================================
    // HELPERS
    // ==========================================
    function focusItemInput(rowNum) {
        const inp = document.querySelector(`tr[data-row="${rowNum}"] .busy-item-input`);
        if (inp) inp.focus();
    }
    function focusNextEmpty() {
        document.querySelectorAll('#busy-cart-tbody tr[data-row]').forEach(tr => {
            if (!tr.dataset.productId) { tr.querySelector('.busy-item-input')?.focus(); return; }
        });
    }
    function getCell(tr, col) { const i = tr.querySelector(`.grid-input[data-col="${col}"]`); return i ? i.value : ''; }
    function setCell(tr, col, val) { const i = tr.querySelector(`.grid-input[data-col="${col}"]`); if (i) i.value = val; }
    function fmt(n) { return (parseFloat(n)||0).toFixed(2); }
    function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function toggleFullscreen() { !document.fullscreenElement ? document.documentElement.requestFullscreen() : document.exitFullscreen(); }
    function startClock() {
        const tick = () => {
            const now = new Date();
            const el = document.getElementById('busy-clock');
            if (el) el.innerHTML = '<label>Date & Time:</label> ' + now.toLocaleDateString('en-GB') + ' ' + now.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true});
        };
        tick(); setInterval(tick, 1000);
    }

})();
