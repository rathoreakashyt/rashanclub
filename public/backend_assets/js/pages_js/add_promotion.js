$(async function () {
    "use strict";
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
    // Don't let a slow/hanging language fetch block the page bindings (max 3s wait)
    await Promise.race([loadLanguage(), new Promise(function(resolve) { setTimeout(resolve, 3000); })]);
   
    let company_data = $('#company_data').val();
    let company_session_data = {};
    try {
        company_session_data = JSON.parse(company_data);
    } catch (e) {
        console.error('Error parsing company info:', e);
    }

    $('.type-1, .type-2, .type-3, .scheme-bill').hide();
    showFieldsByType($('#type').val());
    showSchemeBasis($('#scheme_basis').val());

    $('#type').change(function() {
        showFieldsByType($(this).val());
    });

    $('#scheme_basis').change(function() {
        showSchemeBasis($(this).val());
    });

    function showSchemeBasis(basis) {
        $('.scheme-bill').hide();
        $('#item_id').prop('disabled', true);
        $('#item_id_2').prop('disabled', true);
        if (basis === 'bill') {
            $('.scheme-bill').show();
        } else if (basis === 'item') {
            var type = $('#type').val();
            if (type === '1') {
                $('#item_id').prop('disabled', false);
            } else if (type === '3') {
                $('#item_id_2').prop('disabled', false);
            }
        }
    }

    function showFieldsByType(type) {
        $('.type-1, .type-2, .type-3').hide();
        $('#item_id').prop('disabled', true);
        $('#item_id_2').prop('disabled', true);
        switch(type) {
            case '1':
                $('.type-1').show();
                if ($('#scheme_basis').val() === 'item') {
                    $('#item_id').prop('disabled', false);
                }
                break;
            case '2':
                $('.type-2').show();
                break;
            case '3':
                $('.type-3').show();
                $('#item_id_2').prop('disabled', false);
                break;
        }
        $('.card-body select.select2').each(function() {
            var $sel = $(this);
            if ($sel.hasClass('select2-hidden-accessible')) {
                var val = $sel.val();
                $sel.val(val).trigger('change.select2');
            }
        });
    }

    (function() {
        var $discount = $('#discount');
        $discount.on('input', function() {
            var v = this.value;
            var numPart = v.replace(/%.*$/, '').replace(/[^\d.]/g, '');
            var parts = numPart.split('.');
            if (parts.length > 2) numPart = parts[0] + '.' + parts.slice(1).join('');
            var hasPercent = (v.indexOf('%') !== -1);
            var newVal = numPart + (hasPercent ? '%' : '');
            if (this.value !== newVal) this.value = newVal;
        });
        $discount.on('keypress', function(e) {
            var k = e.key, v = this.value;
            if (k === '%') { if (v.indexOf('%') !== -1) e.preventDefault(); return; }
            if (k === '.') { if (v.replace(/%.*$/, '').indexOf('.') !== -1) e.preventDefault(); return; }
            if (!/\d/.test(k) && !e.ctrlKey && !e.metaKey && e.key.length === 1) e.preventDefault();
        });
    })();

    // Same discount validation for bill_level_discount
    (function() {
        var $bd = $('#bill_level_discount');
        $bd.on('input', function() {
            var v = this.value;
            var numPart = v.replace(/%.*$/, '').replace(/[^\d.]/g, '');
            var parts = numPart.split('.');
            if (parts.length > 2) numPart = parts[0] + '.' + parts.slice(1).join('');
            var hasPercent = (v.indexOf('%') !== -1);
            var newVal = numPart + (hasPercent ? '%' : '');
            if (this.value !== newVal) this.value = newVal;
        });
        $bd.on('keypress', function(e) {
            var k = e.key, v = this.value;
            if (k === '%') { if (v.indexOf('%') !== -1) e.preventDefault(); return; }
            if (k === '.') { if (v.replace(/%.*$/, '').indexOf('.') !== -1) e.preventDefault(); return; }
            if (!/\d/.test(k) && !e.ctrlKey && !e.metaKey && e.key.length === 1) e.preventDefault();
        });
    })();

    // ============ Tier pricing (Buy X Get Y partial quantity) ============
    // Each tier can be a PERCENTAGE (percent of list price) or a FIXED AMOUNT (custom unit price).
    // Stored as { qty, percent } or { qty, custom_price }.
    var currencySymbol = (company_session_data.currency_symbol) || '₹';

    function suggestedPercent() {
        var buyQty = parseFloat($('#qty').val()) || 0;
        var getQty = parseFloat($('#get_qty').val()) || 0;
        var total = buyQty + getQty;
        if (total <= 0) return 50;
        return Math.round((buyQty / total) * 100);
    }

    function tierRowTemplate(tier) {
        tier = tier || {};
        var qty = (tier.qty != null && tier.qty !== '') ? tier.qty : '';
        var isAmount = (tier.custom_price != null && tier.custom_price !== '');
        var val = isAmount ? tier.custom_price : ((tier.percent != null && tier.percent !== '') ? tier.percent : '');
        var row = $('<div class="row g-2 tier-percentage-row"></div>');
        var col1 = $('<div class="col-3"></div>');
        col1.append($('<input type="text" class="form-control number-input tier-qty-input" placeholder="' + (language_key.Qty || 'Qty') + '">').val(qty));
        var col2 = $('<div class="col-3"></div>');
        var sel = $('<select class="form-select tier-type-select"></select>')
            .append($('<option value="percent">% (' + (language_key.Percentage || 'Percentage') + ')</option>'))
            .append($('<option value="amount">' + currencySymbol + ' (' + (language_key.Amount || 'Amount') + ')</option>'));
        sel.val(isAmount ? 'amount' : 'percent');
        col2.append(sel);
        var col3 = $('<div class="col-4"></div>');
        var $valueInput = $('<input type="text" class="form-control number-input tier-value-input" placeholder="' + (isAmount ? currencySymbol : (language_key.Percentage || 'Percentage')) + '">').val(val);
        if (tier.auto === true) {
            $valueInput.data('auto-filled', true);
        }
        col3.append($valueInput);
        var col4 = $('<div class="col-2"></div>');
        col4.append($('<button type="button" class="btn btn-outline-danger btn-sm remove-tier-row-btn w-100"><i class="ti tabler-x"></i></button>'));
        row.append(col1, col2, col3, col4);
        return row;
    }

    function tierValuePlaceholder($row) {
        var isAmount = $row.find('.tier-type-select').val() === 'amount';
        $row.find('.tier-value-input').attr('placeholder', isAmount ? currencySymbol : (language_key.Percentage || 'Percentage'));
    }

    function renderTierRows() {
        var $wrap = $('#tier-percentages-wrap');
        $wrap.empty();
        var raw = $('#tier_percentages').val();
        var tiers = [];
        if (raw) {
            try { tiers = JSON.parse(raw); } catch (e) { tiers = []; }
        }
        if (!Array.isArray(tiers) || tiers.length === 0) {
            // No tiers saved: auto-create one box per partial qty (1..buyQty-1),
            // so the shopkeeper just fills the rate for each qty.
            // Full qty (buyQty+) => normal scheme applies, no tier needed.
            var buyQty = parseInt($('#qty').val()) || 0;
            var pct = suggestedPercent();
            if (buyQty > 1) {
                for (var q = 1; q < buyQty; q++) {
                    $wrap.append(tierRowTemplate({ qty: q, percent: pct, auto: true }));
                }
            } else {
                $wrap.append(tierRowTemplate({ qty: 1, percent: pct }));
            }
        } else {
            tiers.forEach(function(t) {
                $wrap.append(tierRowTemplate(t));
            });
        }
    }

    function collectTierRows() {
        var tiers = [];
        $('.tier-percentage-row').each(function() {
            var qty = parseFloat($(this).find('.tier-qty-input').val());
            var val = parseFloat($(this).find('.tier-value-input').val());
            if (qty > 0 && !isNaN(val) && val >= 0) {
                if ($(this).find('.tier-type-select').val() === 'amount') {
                    tiers.push({ qty: qty, custom_price: val });
                } else if (val <= 100) {
                    tiers.push({ qty: qty, percent: val });
                }
            }
        });
        tiers.sort(function(a, b) { return a.qty - b.qty; });
        return tiers;
    }

    // Delegated binding so the button works even if rows are re-rendered
    $(document).on('click', '#add-tier-row-btn', function() {
        $('#tier-percentages-wrap').append(tierRowTemplate({}));
    });

    $(document).on('click', '.remove-tier-row-btn', function() {
        if ($('.tier-percentage-row').length > 1) {
            $(this).closest('.tier-percentage-row').remove();
        } else {
            $(this).closest('.tier-percentage-row').find('input').val('');
        }
    });

    // Change placeholder when tier type toggles (% vs Amount)
    $(document).on('change', '.tier-type-select', function() {
        var $row = $(this).closest('.tier-percentage-row');
        tierValuePlaceholder($row);
        $row.find('.tier-value-input').val('').removeData('auto-filled');
    });

    // When type 3 selected (or edit mode), render saved tiers
    if ($('#type').val() === '3') {
        renderTierRows();
    }

    // Serialize tiers on submit
    $('form').on('submit', function() {
        $('#tier_percentages').val(JSON.stringify(collectTierRows()));
    });

    // Update suggested default % when buy/get qty changes (only for empty percent cells).
    // If the user hasn't typed any rate yet, re-create rows for the new buy qty.
    $('#qty, #get_qty').on('input', function() {
        var pct = suggestedPercent();
        var untouched = true;
        $('.tier-percentage-row').each(function() {
            var $val = $(this).find('.tier-value-input');
            if ($val.val() !== '' && $val.data('auto-filled') !== true) {
                untouched = false;
            }
        });
        if (untouched && $('#tier_percentages').val() === '') {
            renderTierRows();
            return;
        }
        $('.tier-percentage-row').each(function() {
            var $val = $(this).find('.tier-value-input');
            if ($(this).find('.tier-type-select').val() === 'percent' &&
                ($val.val() === '' || $val.data('auto-filled') === true)) {
                $val.val(pct).data('auto-filled', true);
            }
        });
    });

    // User manually typed a value - stop auto-filling that cell
    $(document).on('input', '.tier-value-input', function() {
        $(this).removeData('auto-filled');
    });

    // Auto-fill first tier row on type selection
    $('#type').on('change', function() {
        if ($(this).val() === '3') {
            renderTierRows();
        }
    });

    // ============ Flavour Alternatives (Buy X Get Y free item flavours) ============

    function flavourRowTemplate(flavour) {
        flavour = flavour || {};
        var itemId = flavour.get_item_id || flavour.item_id || '';
        var name = flavour.name || '';
        var row = $('<div class="row g-2 flavour-alternative-row"></div>');
        var col1 = $('<div class="col-5"></div>');
        var sel = $('<select class="form-select flavour-item-select"></select>');
        sel.append($('<option value="">' + (language_key.Select || 'Select') + ' ' + (language_key.Item || 'Item') + '</option>'));
        $('#get_item_id option').each(function() {
            if ($(this).val() !== '') {
                sel.append($('<option></option>').val($(this).val()).text($(this).text()));
            }
        });
        sel.val(itemId);
        col1.append(sel);
        var col2 = $('<div class="col-5"></div>');
        col2.append($('<input type="text" class="form-control flavour-name-input" placeholder="' + (language_key['Flavour Name (optional)'] || 'Flavour Name (optional)') + '">').val(name));
        var col3 = $('<div class="col-2"></div>');
        col3.append($('<button type="button" class="btn btn-outline-danger btn-sm remove-flavour-row-btn w-100"><i class="ti tabler-x"></i></button>'));
        row.append(col1, col2, col3);
        return row;
    }

    function renderFlavourRows() {
        var $wrap = $('#flavour-alternatives-wrap');
        $wrap.empty();
        var raw = $('#flavour_alternatives').val();
        var flavours = [];
        if (raw) {
            try { flavours = JSON.parse(raw); } catch (e) { flavours = []; }
        }
        if (!Array.isArray(flavours) || flavours.length === 0) {
            // Add one default row with the get_item_id pre-selected
            var getDefaultItemId = $('#get_item_id').val() || '';
            $wrap.append(flavourRowTemplate({ get_item_id: getDefaultItemId, name: '' }));
        } else {
            flavours.forEach(function(f) {
                $wrap.append(flavourRowTemplate(f));
            });
        }
    }

    function collectFlavourRows() {
        var flavours = [];
        $('.flavour-alternative-row').each(function() {
            var itemId = parseInt($(this).find('.flavour-item-select').val()) || 0;
            var name = $(this).find('.flavour-name-input').val().trim();
            if (itemId > 0) {
                flavours.push({ get_item_id: itemId, name: name });
            }
        });
        return flavours;
    }

    $(document).on('click', '#add-flavour-row-btn', function() {
        $('#flavour-alternatives-wrap').append(flavourRowTemplate({}));
    });

    $(document).on('click', '.remove-flavour-row-btn', function() {
        if ($('.flavour-alternative-row').length > 1) {
            $(this).closest('.flavour-alternative-row').remove();
        } else {
            $(this).closest('.flavour-alternative-row').find('select').val('');
            $(this).closest('.flavour-alternative-row').find('input').val('');
        }
    });

    // When type 3 selected (or edit mode), render saved flavours
    if ($('#type').val() === '3') {
        renderFlavourRows();
    }

    // Serialize flavours on submit
    $('form').on('submit', function() {
        $('#flavour_alternatives').val(JSON.stringify(collectFlavourRows()));
    });

    // Auto-fill first flavour row on type selection
    $('#type').on('change', function() {
        if ($(this).val() === '3') {
            renderFlavourRows();
        }
    });

});