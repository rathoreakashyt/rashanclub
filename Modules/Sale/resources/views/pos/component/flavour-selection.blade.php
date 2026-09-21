<div class="modal fade" id="modal_flavour_selection" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title"><i class="icon-base ti tabler-flask me-1"></i>{{ __('Choose_Flavour') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" id="flavour-selection-subtitle">{{ __('Select a flavour for the free item') }}</p>
                <div id="flavour-options-list" class="d-flex flex-wrap gap-2">
                    <!-- Flavour options will be populated dynamically -->
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary btn-sm" id="flavour-confirm-btn" disabled>
                    <i class="icon-base ti tabler-check me-1"></i>{{ __('Confirm') }}
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let selectedFlavourItemId = null;
    let flavourCallback = null;

    window.openFlavourSelectionModal = function(flavourAlternatives, callback, currentFlavourId) {
        selectedFlavourItemId = null;
        flavourCallback = callback;

        var $list = $('#flavour-options-list');
        $list.empty();

        if (!flavourAlternatives || !Array.isArray(flavourAlternatives) || flavourAlternatives.length === 0) {
            return;
        }

        flavourAlternatives.forEach(function(flavour) {
            var isSelected = currentFlavourId && String(flavour.get_item_id) === String(currentFlavourId);
            if (isSelected) {
                selectedFlavourItemId = flavour.get_item_id;
            }
            var label = flavour.name || ('Flavour ' + flavour.get_item_id);
            var $btn = $(`
                <button type="button" class="btn btn-outline-primary flavour-option-btn ${isSelected ? 'active' : ''}"
                    data-item-id="${flavour.get_item_id}" data-name="${label}">
                    <i class="icon-base ti tabler-circle-dot me-1"></i>${label}
                </button>
            `);
            $list.append($btn);
        });

        $('#flavour-confirm-btn').prop('disabled', !selectedFlavourItemId);
        $('#modal_flavour_selection').modal('show');
    };

    $(document).on('click', '.flavour-option-btn', function() {
        $('.flavour-option-btn').removeClass('active');
        $(this).addClass('active');
        selectedFlavourItemId = $(this).data('item-id');
        $('#flavour-confirm-btn').prop('disabled', false);
    });

    $(document).on('click', '#flavour-confirm-btn', function() {
        if (selectedFlavourItemId && typeof flavourCallback === 'function') {
            flavourCallback(selectedFlavourItemId);
        }
        $('#modal_flavour_selection').modal('hide');
    });

    $('#modal_flavour_selection').on('hidden.bs.modal', function() {
        selectedFlavourItemId = null;
        flavourCallback = null;
    });
})();
</script>
