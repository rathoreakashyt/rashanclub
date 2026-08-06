<!-- Register Open Modal -->
<div class="modal fade" id="modal_register_open" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Open') }} {{ __('Register') }}</h5>
            </div>
            <div class="modal-body">
                <form id="form_register_open">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Counter') }} <span class="text-danger">*</span></label>
                        <select class="form-control" id="register_counter_id" name="counter_id" required>
                            <option value="">{{ __('Select') }} {{ __('Counter') }}</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('Opening Balance') }}</label>
                        <div id="payment_methods_container">
                            <!-- Payment methods will be loaded here -->
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('Opening Details') }}</label>
                        <textarea class="form-control" id="register_opening_details" name="opening_details" rows="3" placeholder="{{ __('Optional notes') }}"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="btn_open_register">
                    <i class="icon-base ti tabler-device-floppy me-1"></i> {{ __('Open') }} {{ __('Register') }}
                </button>
            </div>
        </div>
    </div>
</div>
