$(async function () {
    "use strict";
    /** #################### -- The base JS part should be on top of all JS files --  #################### **/
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

    let company_data = $('#company_data').val();
    let company_session_data = {};
    try {
        company_session_data = JSON.parse(company_data);
    } catch (e) {
        console.error('Error parsing company info:', e);
    }
    /** #################### -- The base JS part should be on top of all JS files --  #################### **/

    let itemCode = '';
    let categoryId = '';
    let brandId = '';
    let itemId = '';
    let genericName = '';
    let supplierId = '';
    let summaryData = {};
    let currentFilterInfo = {
        item_code: '', category_id: '', brand_id: '', item_id: '', generic_name: '', supplier_id: ''
    };

    let lowStockReportRoute;
    let reportSearchItemsUrl;
    try {
        lowStockReportRoute = route('report.low-stock-report', {}, false, Ziggy);
        var searchPath = route('report.search-items', {}, false, Ziggy);
        reportSearchItemsUrl = (typeof searchPath === 'string' && searchPath.indexOf('http') === 0) ? searchPath : (String(base_url || '').replace(/\/?$/, '') + (searchPath ? '/' + String(searchPath).replace(/^\//, '') : '/reports/search-items'));
    } catch (e) {
        lowStockReportRoute = '/reports/low-stock-report';
        reportSearchItemsUrl = (String(base_url || '').replace(/\/?$/, '') + '/reports/search-items');
    }

    // --- Scalable report: chunked loading + virtual scroll (no pagination) ---
    var CHUNK_SIZE = 2000;
    var ROW_HEIGHT = 42;
    var allRows = [];
    var totalCount = 0;
    var virtualContainer = null;
    var virtualSpacer = null;
    var virtualTableBody = null;
    var scrollStartIndex = 0;
    var visibleCount = 0;
    var loadAborted = false;

    function getFilterParams() {
        return {
            item_code: itemCode,
            category_id: categoryId,
            brand_id: brandId,
            item_id: itemId,
            generic_name: genericName,
            supplier_id: supplierId
        };
    }

    function updateFilteredInformation(filterInfo) {
        var html = '';
        if (filterInfo.item_code) html += '<p class="pb-0 mb-0"><strong>' + (language_key.Code || 'Code') + ': </strong> ' + filterInfo.item_code + '</p>';
        if (filterInfo.category && filterInfo.category.name) html += '<p class="pb-0 mb-0"><strong>' + (language_key.Category || 'Category') + ': </strong> ' + filterInfo.category.name + '</p>';
        if (filterInfo.brand && filterInfo.brand.name) html += '<p class="pb-0 mb-0"><strong>' + (language_key.Brand || 'Brand') + ': </strong> ' + filterInfo.brand.name + '</p>';
        if (filterInfo.item && filterInfo.item.name) html += '<p class="pb-0 mb-0"><strong>' + (language_key.Item || 'Item') + ': </strong> ' + filterInfo.item.name + '</p>';
        if (filterInfo.generic_name) html += '<p class="pb-0 mb-0"><strong>' + (language_key.GenericName || 'Generic Name') + ': </strong> ' + filterInfo.generic_name + '</p>';
        if (filterInfo.supplier && filterInfo.supplier.name) html += '<p class="pb-0 mb-0"><strong>' + (language_key.Supplier || 'Supplier') + ': </strong> ' + filterInfo.supplier.name + '</p>';
        if (html) {
            $('#filterInfoContent').html(html);
            $('#filteredInformation').show();
        } else {
            $('#filteredInformation').hide();
        }
    }

    function getExportHeader() {
        var header = '<div style="margin-bottom: 20px;"><h3 style="text-align: center; margin-bottom: 15px;">' + (language_key.AlertStockReport || 'Low Stock Report') + '</h3>';
        if (currentFilterInfo.item_code) header += '<p style="margin: 5px 0;"><strong>' + (language_key.Code || 'Code') + ': </strong> ' + currentFilterInfo.item_code + '</p>';
        if (currentFilterInfo.category && currentFilterInfo.category.name) header += '<p style="margin: 5px 0;"><strong>' + (language_key.Category || 'Category') + ': </strong> ' + currentFilterInfo.category.name + '</p>';
        if (currentFilterInfo.brand && currentFilterInfo.brand.name) header += '<p style="margin: 5px 0;"><strong>' + (language_key.Brand || 'Brand') + ': </strong> ' + currentFilterInfo.brand.name + '</p>';
        if (currentFilterInfo.item && currentFilterInfo.item.name) header += '<p style="margin: 5px 0;"><strong>' + (language_key.Item || 'Item') + ': </strong> ' + currentFilterInfo.item.name + '</p>';
        if (currentFilterInfo.generic_name) header += '<p style="margin: 5px 0;"><strong>' + (language_key.GenericName || 'Generic Name') + ': </strong> ' + currentFilterInfo.generic_name + '</p>';
        if (currentFilterInfo.supplier && currentFilterInfo.supplier.name) header += '<p style="margin: 5px 0;"><strong>' + (language_key.Supplier || 'Supplier') + ': </strong> ' + currentFilterInfo.supplier.name + '</p>';
        header += '</div>';
        return header;
    }

    function fetchChunk(offset) {
        var params = getFilterParams();
        params.offset = offset;
        params.limit = CHUNK_SIZE;
        return $.ajax({
            url: base_url + lowStockReportRoute,
            type: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            data: params
        });
    }

    function loadReportData() {
        loadAborted = false;
        allRows = [];
        totalCount = 0;
        $('#lowStockReportLoading').removeClass('d-none');
        $('#lowStockReportVirtualWrap').addClass('d-none');

        fetchChunk(0).then(function (response) {
            if (loadAborted || !response.success || !response.data) {
                $('#lowStockReportLoading').addClass('d-none');
                return;
            }
            var data = response.data;
            if (data.summary) summaryData = data.summary;
            if (data.filter_info) {
                currentFilterInfo = data.filter_info;
                updateFilteredInformation(currentFilterInfo);
            } else {
                currentFilterInfo = { item_code: itemCode, category: null, brand: null, item: null, generic_name: genericName, supplier: null };
                updateFilteredInformation(currentFilterInfo);
            }
            totalCount = data.total_count || 0;
            if (data.stocks && data.stocks.length) allRows = allRows.concat(data.stocks);

            function fetchNext() {
                if (loadAborted || allRows.length >= totalCount) {
                    finishLoad();
                    return;
                }
                var nextOffset = allRows.length;
                fetchChunk(nextOffset).then(function (nextResp) {
                    if (loadAborted) { finishLoad(); return; }
                    if (nextResp.success && nextResp.data && nextResp.data.stocks && nextResp.data.stocks.length) {
                        allRows = allRows.concat(nextResp.data.stocks);
                    }
                    fetchNext();
                }).fail(function () { finishLoad(); });
            }
            fetchNext();
        }).fail(function () {
            $('#lowStockReportLoading').addClass('d-none');
            $('#filteredInformation').hide();
        });

        function finishLoad() {
            $('#lowStockReportLoading').addClass('d-none');
            if (allRows.length === 0 && totalCount === 0) {
                $('#filteredInformation').hide();
                return;
            }
            buildVirtualDom();
            $('#lowStockReportVirtualWrap').removeClass('d-none');
            renderVisibleRows();
        }
    }

    function buildVirtualDom() {
        var wrap = $('#alertStockReportTableVirtual');
        wrap.empty();
        var containerHeight = wrap.get(0) ? Math.max(400, wrap.get(0).clientHeight || 600) : 600;
        visibleCount = Math.ceil(containerHeight / ROW_HEIGHT) + 20;
        virtualSpacer = $('<div class="report-virtual-spacer"></div>').css('height', (totalCount * ROW_HEIGHT) + 'px');
        wrap.append(virtualSpacer);
        var colgroup = '<colgroup><col style="width:5%"><col style="width:22%"><col style="width:12%"><col style="width:18%"><col style="width:18%"><col style="width:12%"><col style="width:13%"></colgroup>';
        var tbl = $('<table class="datatables-basic table report-table report-virtual-table">' + colgroup + '<tbody></tbody></table>');
        virtualTableBody = tbl.find('tbody');
        wrap.append(tbl);
        virtualContainer = wrap.get(0);
        wrap.off('scroll').on('scroll', function () {
            requestAnimationFrame(renderVisibleRows);
        });
    }

    function renderVisibleRows() {
        if (!virtualContainer || !virtualTableBody || allRows.length === 0) return;
        var scrollTop = virtualContainer.scrollTop;
        scrollStartIndex = Math.max(0, Math.floor(scrollTop / ROW_HEIGHT));
        var endIndex = Math.min(allRows.length, scrollStartIndex + visibleCount);
        var offsetY = scrollStartIndex * ROW_HEIGHT;
        virtualTableBody.css('transform', 'translateY(' + offsetY + 'px)');
        var fragment = document.createDocumentFragment();
        for (var i = scrollStartIndex; i < endIndex; i++) {
            var row = allRows[i];
            var sn = (i + 1);
            var tr = $('<tr></tr>');
            tr.append($('<td></td>').text(sn));
            tr.append($('<td></td>').html(row.item_code || ''));
            tr.append($('<td></td>').text(row.category || ''));
            tr.append($('<td></td>').html(row.stock_details || ''));
            tr.append($('<td></td>').text(row.total_stock_qty || ''));
            tr.append($('<td></td>').text(row.lpp || ''));
            tr.append($('<td class="text-right"></td>').text(row.total || ''));
            fragment.appendChild(tr.get(0));
        }
        virtualTableBody.get(0).innerHTML = '';
        virtualTableBody.get(0).appendChild(fragment);
    }

    function initSelect2() {
        if ($('#item_code_f').length) {
            if ($('#item_code_f').hasClass('select2-hidden-accessible')) {
                try { $('#item_code_f').select2('destroy'); } catch (e) { }
            }
            var codePlaceholder = $('#item_code_f').data('placeholder') || (language_key.AllCodes || 'All Codes');
            $('#item_code_f').select2({
                dropdownParent: $('#filterSection'),
                width: '100%',
                placeholder: codePlaceholder,
                allowClear: true,
                ajax: {
                    url: reportSearchItemsUrl,
                    dataType: 'json',
                    delay: 300,
                    data: function (params) { return { q: params.term || '' }; },
                    processResults: function (data) {
                        if (!data || !Array.isArray(data.results)) return { results: [] };
                        var list = data.results.map(function (r) { return { id: r.code, text: r.code + ' - ' + (r.text || '').replace(/\s*\([^)]*\)\s*$/, '') }; });
                        return { results: list };
                    }
                },
                minimumInputLength: 1,
                language: { inputTooShort: function () { return ''; }, searching: function () { return language_key.Searching || 'Searching…'; }, noResults: function () { return language_key.NoResults || 'No results found'; } }
            });
        }
        if ($('#category_id_f').length && !$('#category_id_f').hasClass('select2-hidden-accessible')) {
            $('#category_id_f').select2({ dropdownParent: $('#filterSection'), width: '100%', placeholder: language_key.AllCategories || 'All Categories', allowClear: true });
        }
        if ($('#brand_id_f').length && !$('#brand_id_f').hasClass('select2-hidden-accessible')) {
            $('#brand_id_f').select2({ dropdownParent: $('#filterSection'), width: '100%', placeholder: language_key.AllBrands || 'All Brands', allowClear: true });
        }
        if ($('#item_id_f').length) {
            if ($('#item_id_f').hasClass('select2-hidden-accessible')) {
                try { $('#item_id_f').select2('destroy'); } catch (e) { }
            }
            var itemPlaceholder = $('#item_id_f').data('placeholder') || (language_key.AllItems || 'All Items');
            $('#item_id_f').select2({
                dropdownParent: $('#filterSection'),
                width: '100%',
                placeholder: itemPlaceholder,
                allowClear: true,
                ajax: {
                    url: reportSearchItemsUrl,
                    dataType: 'json',
                    delay: 300,
                    data: function (params) { return { q: params.term || '' }; },
                    processResults: function (data) {
                        if (!data || !Array.isArray(data.results)) return { results: [] };
                        return { results: data.results };
                    }
                },
                minimumInputLength: 1,
                language: { inputTooShort: function () { return ''; }, searching: function () { return language_key.Searching || 'Searching…'; }, noResults: function () { return language_key.NoResults || 'No results found'; } }
            });
        }
        if ($('#supplier_id_f').length && !$('#supplier_id_f').hasClass('select2-hidden-accessible')) {
            $('#supplier_id_f').select2({ dropdownParent: $('#filterSection'), width: '100%', placeholder: language_key.AllSuppliers || 'All Suppliers', allowClear: true });
        }
    }

    $(document).on('submit', '#filterForm', function (e) { e.preventDefault(); return false; });
    $(document).on('keydown', '#filterForm input, #filterForm select', function (e) {
        if (e.key === 'Enter' || e.keyCode === 13) { e.preventDefault(); $('#applyFilter').click(); return false; }
    });
    $(document).on('click', '#applyFilter', function (e) {
        e.preventDefault();
        itemCode = $('#item_code_f').val() || '';
        categoryId = $('#category_id_f').val() || '';
        brandId = $('#brand_id_f').val() || '';
        itemId = $('#item_id_f').val() || '';
        genericName = $('#generic_name_f').val() || '';
        supplierId = $('#supplier_id_f').val() || '';
        loadReportData();
        return false;
    });

    $('#lowStockReportFilterBtn').on('click', function () {
        $('#filterSection').slideToggle();
        setTimeout(function () { if ($('#filterSection').is(':visible')) initSelect2(); }, 200);
    });
    $(document).on('click', '#lowStockReportExportBtn, [data-export]', function (e) {
        var exportType = $(e.target).closest('[data-export]').attr('data-export');
        if (!exportType) return;
        if (allRows.length === 0) { alert(language_key.NoDataToExport || 'No data to export.'); return; }
        if (exportType === 'print') {
            var win = window.open('', '_blank');
            win.document.write('<html><head><title>' + (language_key.AlertStockReport || 'Low Stock Report') + '</title><link rel="stylesheet" href="' + base_url + '/public/backend_assets/css/bootstrap.min.css"/></head><body>');
            win.document.write(getExportHeader());
            win.document.write('<table class="table table-bordered"><thead><tr><th>' + (language_key.SN || 'SN') + '</th><th>' + (language_key.Item || 'Item') + '(' + (language_key.Code || 'Code') + ')</th><th>' + (language_key.Category || 'Category') + '</th><th>' + (language_key.Stock || 'Stock') + ' ' + (language_key.Details || 'Details') + '</th><th>' + (language_key.Total || 'Total') + ' ' + (language_key.Stock || 'Stock') + ' ' + (language_key.Quantity || 'Qty') + '</th><th>LPP/PP</th><th>' + (language_key.Total || 'Total') + '</th></tr></thead><tbody>');
            for (var i = 0; i < allRows.length; i++) {
                var r = allRows[i];
                win.document.write('<tr><td>' + (i + 1) + '</td><td>' + (r.item_code || '').replace(/<[^>]+>/g, '') + '</td><td>' + (r.category || '') + '</td><td>-</td><td>' + (r.total_stock_qty || '') + '</td><td>' + (r.lpp || '') + '</td><td>' + (r.total || '') + '</td></tr>');
            }
            win.document.write('</tbody></table></body></html>');
            win.document.close();
            win.focus();
            setTimeout(function () { win.print(); win.close(); }, 500);
        } else if (exportType === 'excel') {
            var csv = '\uFEFF';
            csv += (language_key.AlertStockReport || 'Low Stock Report') + '\n';
            csv += 'SN,' + (language_key.Item || 'Item') + ',' + (language_key.Category || 'Category') + ',Total Stock Qty,LPP,Total\n';
            for (var j = 0; j < allRows.length; j++) {
                var x = allRows[j];
            
                csv += (j + 1) + ',"' +
                    String(x.item_code ?? '')
                        .replace(/<[^>]+>/g, '')
                        .replace(/"/g, '""') + '","' +
                    String(x.category ?? '')
                        .replace(/<[^>]+>/g, '')
                        .replace(/"/g, '""') + '","' +
                    (x.total_stock_qty ?? '') + '","' +
                    (x.lpp ?? '') + '","' +
                    (x.total ?? '') + '"\n';
            }
            var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'low-stock-report.csv';
            link.click();
            URL.revokeObjectURL(link.href);
        } else if (exportType === 'pdf' && typeof pdfMake !== 'undefined') {
            var body = [['SN', language_key.Item || 'Item', language_key.Category || 'Category', 'Total Stock Qty', 'LPP', 'Total']];
            for (var k = 0; k < Math.min(allRows.length, 5000); k++) {
                var y = allRows[k];
                body.push([(k + 1), String(y.item_code || '').replace(/<[^>]+>/g, ''), y.category || '', y.total_stock_qty || '', y.lpp || '', y.total || '']);
            }
            pdfMake.createPdf({
                pageSize: 'A4',
                pageOrientation: 'portrait',
                content: [
                    { text: (language_key.AlertStockReport || 'Low Stock Report'), style: 'header' },
                    { table: { body: body }, layout: 'lightHorizontalLines' }
                ],
                styles: { header: { fontSize: 16, bold: true, margin: [0, 0, 0, 10] } }
            }).download('low-stock-report.pdf');
        }
    });

    function getStockSegmentationOfItem(item_id, item_type) {
        var table_no = (item_type === 'IMEI_Product' || item_type === 'Serial_Product') ? 2 : (item_type === 'Medicine_Product' ? 3 : 4);
        $.ajax({
            type: 'POST',
            url: base_url + '/stock/getStockSegmentationOfItem',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { item_id: item_id, item_type: item_type },
            dataType: 'json',
            success: function (response) {
                $('#datatable' + table_no + ' tbody').html(response);
                if ($.fn.DataTable.isDataTable('#datatable' + table_no)) $('#datatable' + table_no).DataTable().destroy();
                $('#datatable' + table_no).DataTable({
                    ordering: false,
                    paging: true,
                    dom: '<"card-header border-bottom p-3"<"head-label"><"dt-action-buttons text-end"B>>' + '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' + '<"row"<"col-sm-12"tr>>' + '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                    buttons: [{ extend: 'collection', className: 'btn btn-primary dropdown-toggle me-2', text: '<i class="ti tabler-file-export me-sm-1"></i> Export', buttons: [{ extend: 'print', text: '<i class="ti tabler-printer me-1"></i>' + language_key.Print, className: 'dropdown-item' }, { extend: 'excel', text: '<i class="ti tabler-file-spreadsheet me-1"></i>' + language_key.Excel, className: 'dropdown-item' }, { extend: 'pdf', text: '<i class="ti tabler-file-description me-1"></i>' + language_key.Pdf, className: 'dropdown-item' }] }],
                    language: { paginate: { next: 'Next', previous: 'Previous' } }
                });
            },
            error: function (xhr, status, error) { console.error('Error loading table data: ', error); }
        });
    }

    $(document).on('click', '.modal_trigger', function () {
        var item_id = $(this).attr('data-id');
        var item_type = $(this).attr('data-type');
        var item_name = $(this).attr('data-name');
        var table_no = (item_type === 'IMEI_Product' || item_type === 'Serial_Product') ? 2 : (item_type === 'Medicine_Product' ? 3 : 4);
        var heading = (item_type === 'IMEI_Product') ? 'All IMEI Number of ' + item_name : (item_type === 'Serial_Product') ? 'All Serial Number of ' + item_name : (item_type === 'Medicine_Product') ? 'All Expiry Date of ' + item_name : 'All Variations of ' + item_name;
        $('.stock_segment_title').text(heading);
        [2, 3, 4].forEach(function (n) {
            if ($.fn.DataTable.isDataTable('#datatable' + n)) $('#datatable' + n).DataTable().clear().destroy();
            $('.datatable' + n).css('display', n === table_no ? 'table' : 'none');
        });
        getStockSegmentationOfItem(item_id, item_type);
    });

    function checkAndShowFilters() {
        var itemCodeVal = $('#item_code_f').val();
        var categoryIdVal = $('#category_id_f').val();
        var brandIdVal = $('#brand_id_f').val();
        var itemIdVal = $('#item_id_f').val();
        var genericNameVal = $('#generic_name_f').val();
        var supplierIdVal = $('#supplier_id_f').val();
        if (itemCodeVal || categoryIdVal || brandIdVal || itemIdVal || genericNameVal || supplierIdVal) {
            itemCode = itemCodeVal || '';
            categoryId = categoryIdVal || '';
            brandId = brandIdVal || '';
            itemId = itemIdVal || '';
            genericName = genericNameVal || '';
            supplierId = supplierIdVal || '';
            $('#filterSection').show();
            initSelect2();
            loadReportData();
        }
    }

    $('#filterSection').on('shown.bs.collapse shown', function () { setTimeout(initSelect2, 50); });
    initSelect2();
    checkAndShowFilters();
    var hasFilter = $('#item_code_f').val() || $('#category_id_f').val() || $('#brand_id_f').val() || $('#item_id_f').val() || $('#generic_name_f').val() || $('#supplier_id_f').val();
    if (!hasFilter) loadReportData();
});
