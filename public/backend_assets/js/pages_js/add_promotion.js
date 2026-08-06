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
    await loadLanguage();
   
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

});