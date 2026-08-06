/**
 * POS Offline Invoice Generator
 * Generates and opens a printable invoice from offline sale data (IndexedDB) when there is no internet.
 * Shows a single invoice in the format from settings: 56mm, 80mm, A4 Print, Half A4 Print, Letter Head.
 */
(function (global) {
    'use strict';

    var precision = 2;

    /** All supported invoice format keys (must match backend invoice_format_or_size values) */
    var INVOICE_FORMATS = ['56mm', '80mm', 'A4 Print', 'Half A4 Print', 'Letter Head'];

    function formatNum(amount) {
        const n = parseFloat(amount);
        if (isNaN(n)) return '0.00';
        return n.toFixed(precision).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    /**
     * Build tax display string from item tax_information array
     */
    function getTaxDisplay(taxInfo) {
        if (!Array.isArray(taxInfo) || taxInfo.length === 0) return '';
        const parts = taxInfo.map(function (t) {
            const name = t.tax_field_name || t.tax_field_type || '';
            const amt = parseFloat(t.tax_field_amount) || 0;
            const pct = parseFloat(t.tax_field_percentage) || 0;
            if (!name || amt <= 0) return '';
            return name + (pct > 0 ? ' ' + pct + '%' : '') + ' - ' + formatNum(amt);
        }).filter(Boolean);
        return parts.join(', ');
    }

    /**
     * Get line total for an item (qty * unit_price minus item discount)
     */
    function getLineTotal(item) {
        const qty = parseFloat(item.quantity) || 0;
        const unitPrice = parseFloat(item.unit_price) || 0;
        let total = qty * unitPrice;
        const discount = parseFloat(item.discount) || 0;
        if (item.discount_type === 'percentage') {
            total -= total * (discount / 100);
        } else {
            total -= discount;
        }
        return Math.max(0, total);
    }

    function escapeHtml(text) {
        if (text == null) return '';
        const s = String(text);
        return s
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /**
     * Returns CSS for offline invoice: base styles + format-specific styles (single format).
     * Item table: first cell left, last cell right, others center.
     */
    function getInlineInvoiceStylesForFormat(formatKey) {
        var base = 'body{font-family:\'DM Sans\',system-ui,sans-serif;font-size:15px;color:#333;margin:0 auto;padding:20px;box-sizing:border-box;}' +
            'table{width:100%;border-collapse:collapse;}' +
            '.header-table td:first-child{vertical-align:top;}' +
            '.invoice-info div{display:flex;justify-content:center;}' +
            '.heading-title{font-size:16px;font-weight:700;margin-bottom:5px;}' +
            '.items-table th{background:#f5f5f5;border:1px solid #ddd;padding:8px;font-weight:bold;}' +
            '.items-table td{border:1px solid #ddd;padding:8px;}' +
            '.items-table th:first-child,.items-table td:first-child{text-align:left;}' +
            '.items-table th:last-child,.items-table td:last-child{text-align:right;}' +
            '.items-table th:not(:first-child):not(:last-child),.items-table td:not(:first-child):not(:last-child){text-align:center;}' +
            '.summary-table tr td:last-child{text-align:right;width:120px;}' +
            '.grand-total{font-size:14px;font-weight:bold;background:#f5f5f5;}' +
            '.grand-total td{padding:5px;}' +
            '.company-info .invoice-logo{max-width:120px;height:auto;display:block;margin-bottom:8px;}' +
            '.print-btn{background-color:#7367f0;color:#fff;padding:12px 24px;border:none;border-radius:8px;cursor:pointer;font-size:14px;}' +
            '@media print{.print-btn{display:none;}}';
        if (formatKey === '56mm') {
            base += 'body{width:447px !important;max-width:447px;font-size:14px !important;}' +
                '.items-table td,.items-table th{padding:4px;font-size:10px;border:none;border-top:1px dashed #ddd;border-bottom:1px dashed #ddd;}' +
                'p{font-size:12px;}.heading-title{font-size:14px;}.company-info{text-align:center !important; display:flex; justify-content:center; align-items:center; flex-direction: column;}';
        } else if (formatKey === '80mm') {
            base += 'body{width:639px !important;max-width:639px;font-size:14px !important;}' +
                '.items-table td,.items-table th{padding:4px;font-size:10px;border:none;border-top:1px dashed #ddd;border-bottom:1px dashed #ddd;}' +
                'p{font-size:12px;}.heading-title{font-size:14px;}.company-info{text-align:center !important; display:flex; justify-content:center; align-items:center; flex-direction: column;}';
        } else if (formatKey === 'A4 Print') {
            base += 'body{width:794px !important;max-width:794px;padding:30px;font-size:15px !important;}';
        } else if (formatKey === 'Half A4 Print') {
            base += 'body{width:559px !important;max-width:559px;padding:12px 10px;font-size:14px !important;}' +
                '.items-table td,.items-table th{padding:4px;font-size:12px;}.heading-title{font-size:14px;}';
        } else {
            base += 'body{width:794px !important;max-width:794px;font-size:15px !important;}' +
                '.items-table td,.items-table th{padding:4px;font-size:13px;}';
        }
        return base;
    }

    /**
     * Map invoice_format_or_size value to a CSS class name (no spaces).
     */
    function formatToClass(formatKey) {
        var map = {
            '56mm': 'offline-invoice-56mm',
            '80mm': 'offline-invoice-80mm',
            'A4 Print': 'offline-invoice-a4',
            'Half A4 Print': 'offline-invoice-half-a4',
            'Letter Head': 'offline-invoice-letterhead'
        };
        return map[formatKey] || 'offline-invoice-a4';
    }

    /**
     * Build the inner invoice content (header, customer/sale info, items, summary) as a single HTML string.
     * For 56mm/80mm (isThermal): header is single column, logo on top of company-info. Otherwise two columns.
     */
    function buildInvoiceContentHTML(data) {
        var d = data;
        var isThermal = d.isThermal === true;
        var logoHtml = '';
        if (d.invoiceLogoUrl) {
            logoHtml = '<img src="' + escapeHtml(d.invoiceLogoUrl) + '" alt="Logo" class="invoice-logo">\n';
        }
        var headerHtml;
        if (isThermal) {
            headerHtml = '<!-- HEADER (thermal: single column, logo on top) -->\n' +
                '<table class="header-table">\n<tr>\n<td class="company-info">\n' +
                logoHtml +
                '<h2 class="heading-title">' + escapeHtml(d.companyName) + '</h2>\n' +
                (d.outletName ? '<p>Outlet: ' + escapeHtml(d.outletName) + '</p>\n' : '') +
                (d.outletPhone ? '<p>Phone: ' + escapeHtml(d.outletPhone) + '</p>\n' : '') +
                (d.outletEmail ? '<p>Email: ' + escapeHtml(d.outletEmail) + '</p>\n' : '') +
                (d.outletAddress ? '<p>Address: ' + escapeHtml(d.outletAddress) + '</p>\n' : '') +
                '</td>\n</tr>\n</table>\n\n';
        } else {
            headerHtml = '<!-- HEADER -->\n' +
                '<table class="header-table">\n<tr>\n' +
                '<td width="50%" class="company-info">\n' +
                logoHtml +
                '<h2 class="heading-title">' + escapeHtml(d.companyName) + '</h2>\n' +
                (d.outletName ? '<p>Outlet: ' + escapeHtml(d.outletName) + '</p>\n' : '') +
                (d.outletPhone ? '<p>Phone: ' + escapeHtml(d.outletPhone) + '</p>\n' : '') +
                (d.outletEmail ? '<p>Email: ' + escapeHtml(d.outletEmail) + '</p>\n' : '') +
                (d.outletAddress ? '<p>Address: ' + escapeHtml(d.outletAddress) + '</p>\n' : '') +
                '</td>\n' +
                '<td width="50%" class="invoice-info" valign="center"><div></div></td>\n' +
                '</tr>\n</table>\n\n';
        }
        return headerHtml +
            '<!-- CUSTOMER + SALE INFO -->\n' +
            '<table style="margin-top:10px" class="customer-sale-info">\n<tr>\n' +
            '<td width="50%" valign="top">\n' +
            '<div class="heading-title">Customer Information</div>\n' +
            '<table class="info-table">\n<tr><td>Name</td><td>: ' + escapeHtml(d.customerName) + '</td></tr>\n</table>\n</td>\n' +
            '<td width="50%" valign="top">\n' +
            '<div class="heading-title">Sale Information</div>\n' +
            '<table class="info-table">\n' +
            '<tr><td>Sale No</td><td>: ' + escapeHtml(d.saleNo) + ' (Offline)</td></tr>\n' +
            '<tr><td>Date Time</td><td>: ' + escapeHtml(d.dateTime) + '</td></tr>\n' +
            (d.employeeName ? '<tr><td>Sales By</td><td>: ' + escapeHtml(d.employeeName) + '</td></tr>\n' : '') +
            '<tr><td>Status</td><td>: ' + escapeHtml(d.status) + '</td></tr>\n' +
            '</table>\n</td>\n</tr>\n</table>\n\n' +
            '<!-- ITEMS TABLE -->\n' +
            '<table class="items-table" style="margin-top:10px">\n<thead>\n<tr>\n' +
            '<th width="5%">#</th>\n' +
            (d.showHsn ? '<th width="6%">HSN</th>\n' : '') +
            '<th width="' + (d.showHsn ? '22' : '25') + '%">Item</th>\n' +
            '<th width="15%">IMEI/Serial</th>\n' +
            '<th width="8%">Qty</th>\n' +
            '<th width="12%">Unit Price</th>\n' +
            (d.collectTax ? '<th width="12%">Tax</th>\n' : '') +
            '<th width="12%">Total</th>\n' +
            '</tr>\n</thead>\n<tbody>\n' + d.itemsRows + '\n</tbody>\n</table>\n\n' +
            '<!-- SUMMARY -->\n' +
            '<table style="margin-top:10px">\n<tr>\n<td width="60%" valign="top"></td>\n<td width="40%" valign="top">\n' +
            '<table class="summary-table">\n' +
            '<tr><td>Subtotal</td><td>' + formatNum(d.subtotal) + '</td></tr>\n' +
            (d.discount > 0 ? '<tr><td>Discount</td><td>' + formatNum(d.discount) + '</td></tr>\n' : '') +
            (d.tax > 0 ? '<tr><td>Tax</td><td>' + formatNum(d.tax) + '</td></tr>\n' : '') +
            (d.shipping > 0 ? '<tr><td>Delivery Charge</td><td>' + formatNum(d.shipping) + '</td></tr>\n' : '') +
            '<tr class="grand-total"><td>Grand Total</td><td>' + formatNum(d.totalPayable) + '</td></tr>\n' +
            '<tr><td>Paid Amount</td><td>' + formatNum(d.totalPaid) + '</td></tr>\n' +
            '<tr><td>Due Amount</td><td>' + formatNum(d.dueAmountVal) + '</td></tr>\n' +
            (d.paymentRows ? '<tr><td>Payment Method</td><td></td></tr>\n' + d.paymentRows : '') +
            '</table>\n</td>\n</tr>\n</table>';
    }

    /**
     * Build offline invoice HTML for a single format from settings (56mm, 80mm, A4 Print, Half A4 Print, Letter Head).
     * @param {Object} offlineSale - { sale_detail, sale_payment } as stored in IndexedDB
     * @param {number} saleId - IndexedDB sale key (for display as offline ref)
     * @param {Object} options - companySessionData, customerName, employeeName, outletName, outletPhone, outletEmail, outletAddress, collectTax, showHsn, defaultHsn, invoiceFormatOrSize, baseUrl, invoiceLogo
     */
    function buildOfflineInvoiceHTML(offlineSale, saleId, options) {
        options = options || {};
        const sd = offlineSale.sale_detail || {};
        const sp = offlineSale.sale_payment || {};
        const cartItems = sd.cart_items || [];
        const companyName = (options.companySessionData && options.companySessionData.business_name) || 'Company';
        const outletName = options.outletName || '';
        const outletPhone = options.outletPhone || '';
        const outletEmail = options.outletEmail || '';
        const outletAddress = options.outletAddress || '';
        const customerName = options.customerName || 'Walk-in Customer';
        const employeeName = options.employeeName || '';
        const collectTax = options.collectTax === 'Yes';
        const showHsn = options.showHsn === 'Yes';
        const defaultHsn = options.defaultHsn || '0000';
        var selectedFormat = (options.invoiceFormatOrSize && INVOICE_FORMATS.indexOf(options.invoiceFormatOrSize) >= 0)
            ? options.invoiceFormatOrSize
            : 'A4 Print';
        var isThermal = (selectedFormat === '56mm' || selectedFormat === '80mm');
        var invoiceLogoUrl = '';
        if (options.baseUrl && options.invoiceLogo) {
            var base = (options.baseUrl + '').replace(/\/$/, '');
            invoiceLogoUrl = base + '/uploads/site_settings/' + (options.invoiceLogo + '').replace(/^\/+/, '');
        }

        const saleNo = 'OFFLINE-' + saleId;
        const dateTime = new Date().toLocaleString('en-GB', {
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', hour12: true
        }).replace(/,/g, '');
        const dueAmount = parseFloat(sp.due_amount) || 0;
        const status = dueAmount > 0 ? 'Due' : 'Paid';

        var itemsRows = '';
        var idx = 0;
        cartItems.forEach(function (item) {
            idx++;
            var lineTotal = getLineTotal(item);
            var taxDisplay = collectTax ? (getTaxDisplay(item.tax_information) || '-') : '';
            var imeiSerial = (item.selected_imei_serial && item.selected_imei_serial.length > 0)
                ? (item.selected_imei_serial.join(', '))
                : '-';
            var itemName = (item.product_name || '') + (item.product_code ? ' (' + item.product_code + ')' : '');
            var hsnCell = showHsn ? '<td>' + (item.hsn_code || defaultHsn) + '</td>' : '';
            var taxCell = collectTax ? '<td class="num">' + taxDisplay + '</td>' : '';
            itemsRows += '<tr><td>' + idx + '</td>' + hsnCell +
                '<td>' + escapeHtml(itemName) + '</td>' +
                '<td>' + escapeHtml(imeiSerial) + '</td>' +
                '<td class="num">' + (item.quantity || 0) + '</td>' +
                '<td class="num">' + formatNum(item.unit_price) + '</td>' +
                taxCell +
                '<td class="num">' + formatNum(lineTotal) + '</td></tr>';
        });

        var subtotal = parseFloat(sd.subtotal) || 0;
        var tax = parseFloat(sd.tax) || 0;
        var discount = parseFloat(sd.discount) || 0;
        var shipping = parseFloat(sd.shipping) || 0;
        var totalPayable = parseFloat(sd.total_payable) || 0;
        var totalPaid = parseFloat(sp.total_paid) || 0;
        var dueAmountVal = parseFloat(sp.due_amount) || 0;

        var paymentRows = '';
        var payments = sp.payments || [];
        if (payments.length > 0) {
            payments.forEach(function (p) {
                paymentRows += '<tr><td>' + escapeHtml(p.payment_name || 'Payment') + '</td><td>' + formatNum(p.amount) + '</td></tr>';
            });
        }

        var data = {
            companyName: companyName,
            outletName: outletName,
            outletPhone: outletPhone,
            outletEmail: outletEmail,
            outletAddress: outletAddress,
            customerName: customerName,
            employeeName: employeeName,
            saleNo: saleNo,
            dateTime: dateTime,
            status: status,
            itemsRows: itemsRows,
            showHsn: showHsn,
            collectTax: collectTax,
            subtotal: subtotal,
            tax: tax,
            discount: discount,
            shipping: shipping,
            totalPayable: totalPayable,
            totalPaid: totalPaid,
            dueAmountVal: dueAmountVal,
            paymentRows: paymentRows,
            isThermal: isThermal,
            invoiceLogoUrl: invoiceLogoUrl || undefined
        };

        var contentHtml = buildInvoiceContentHTML(data);
        var invoiceCss = getInlineInvoiceStylesForFormat(selectedFormat);
        var html = '<!DOCTYPE html>\n<html lang="en">\n<head>\n' +
            '<meta charset="UTF-8">\n' +
            '<meta http-equiv="X-UA-Compatible" content="IE=edge">\n' +
            '<meta name="viewport" content="width=device-width, initial-scale=1.0">\n' +
            '<title>Sale No: ' + escapeHtml(saleNo) + ' (Offline)</title>\n' +
            '<style>\n' + invoiceCss + '\n</style>\n' +
            '</head>\n<body>\n' +
            contentHtml +
            '\n<div style="text-align: center; margin-top: 24px;">' +
            '<button onclick="window.print();" type="button" class="print-btn">Print</button>' +
            '</div>' +
            '<p style="text-align:center;font-size:12px;color:#777;">Offline invoice. Synced when connection is restored.</p>\n' +
            '<script>window.onload = function () { window.print(); };</script>\n' +
            '</body>\n</html>';
        return html;
    }

    /**
     * Generate and open offline invoice in a new window (after sale is saved to IndexedDB).
     * Call this from pos_payment.js saveOfflineSale after posIndexedDB.saveOfflineSale().
     *
     * @param {Object} offlineSaleRecord - The record just saved: { sale_detail, sale_payment, created_at, synced }
     * @param {number} saleId - The ID returned by saveOfflineSale (IndexedDB key)
     * @param {Object} context - Optional. If not provided, reads from DOM where possible.
     *   context.companySessionData
     *   context.customerName  (or will use #customer-select option text)
     *   context.employeeName (or will use #employee-select option text)
     *   context.outletName   (or will use .outlet-name text)
     *   context.outletPhone, context.outletEmail, context.outletAddress
     */
    function generateOfflineInvoice(offlineSaleRecord, saleId, context) {
        context = context || {};
        let companySessionData = context.companySessionData;
        if (!companySessionData && typeof $ !== 'undefined' && $('#company_data').length) {
            try {
                companySessionData = JSON.parse($('#company_data').val()) || {};
            } catch (e) {
                companySessionData = {};
            }
        }
        var invConfig = {};
        if (companySessionData && companySessionData.invoice_configuration) {
            try {
                invConfig = typeof companySessionData.invoice_configuration === 'string'
                    ? JSON.parse(companySessionData.invoice_configuration)
                    : companySessionData.invoice_configuration;
            } catch (e) {
                invConfig = {};
            }
        }
        if (companySessionData && companySessionData.precision != null) {
            precision = parseInt(companySessionData.precision, 10) || 2;
        }
        let customerName = context.customerName;
        if (customerName == null && typeof $ !== 'undefined') {
            const opt = $('#customer-select option:selected');
            customerName = opt.length ? opt.text().trim() : 'Walk-in Customer';
        }
        let employeeName = context.employeeName;
        if (employeeName == null && typeof $ !== 'undefined') {
            const opt = $('#employee-select option:selected');
            employeeName = opt.length ? opt.text().trim() : '';
        }
        let outletName = context.outletName;
        if (outletName == null && typeof $ !== 'undefined' && $('.outlet-name').length) {
            outletName = $('.outlet-name').text().trim() || '';
        }
        var invoiceFormatOrSize = (invConfig && invConfig.invoice_format_or_size) || 'A4 Print';
        var baseUrl = context.baseUrl;
        if (baseUrl == null && typeof $ !== 'undefined' && $('#base_url').length) {
            baseUrl = $('#base_url').val() || '';
        }
        if (baseUrl == null || baseUrl === '') {
            baseUrl = (typeof global.location !== 'undefined' && global.location.origin) ? global.location.origin : '';
        }
        var invoiceLogo = (companySessionData && companySessionData.invoice_logo) ? companySessionData.invoice_logo : '';
        const options = {
            companySessionData: companySessionData,
            customerName: customerName || 'Walk-in Customer',
            employeeName: employeeName || '',
            outletName: outletName || '',
            outletPhone: context.outletPhone || '',
            outletEmail: context.outletEmail || '',
            outletAddress: context.outletAddress || '',
            collectTax: (companySessionData && companySessionData.collect_tax) || 'No',
            showHsn: (invConfig && invConfig.show_hsn_code) === 'Yes',
            defaultHsn: (invConfig && invConfig.default_hsn_code) || '0000',
            invoiceFormatOrSize: invoiceFormatOrSize,
            baseUrl: baseUrl,
            invoiceLogo: invoiceLogo
        };
        const html = buildOfflineInvoiceHTML(offlineSaleRecord, saleId, options);
        var popupWidth = 900;
        var popupHeight = 700;
        if (invoiceFormatOrSize === '56mm') { popupWidth = 480; popupHeight = 550; }
        else if (invoiceFormatOrSize === '80mm') { popupWidth = 685; popupHeight = 550; }
        const w = global.open('', 'OfflineInvoice_' + saleId, 'width=' + popupWidth + ',height=' + popupHeight + ',scrollbars=yes,resizable=yes');
        if (w) {
            w.document.write(html);
            w.document.close();
        }
    }

    global.POSOfflineInvoice = {
        buildOfflineInvoiceHTML: buildOfflineInvoiceHTML,
        generateOfflineInvoice: generateOfflineInvoice,
        formatNum: formatNum,
        INVOICE_FORMATS: INVOICE_FORMATS
    };
})(typeof window !== 'undefined' ? window : this);
