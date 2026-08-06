<!-- Add Hold Modal -->
<div class="modal fade" id="modal_pos_add_hold" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Add') }} {{ __('Hold') }} {{ __('Sale') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="form_add_hold">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Hold') }} {{ __('Reference No') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="hold_reference_no" name="hold_no" required>
                        <small class="text-muted">{{ __('Reference number will be auto-generated if left empty') }}</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="btn_save_hold">
                    <i class="icon-base ti tabler-device-floppy me-1"></i> {{ __('Save') }} {{ __('Hold') }}
                </button>
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                    {!! closeIconWithText() !!}
                </button>
            </div>
        </div>
    </div>
</div>

<!-- List Holds Modal -->
<div class="modal fade" id="modal_pos_list_holds" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Hold') }} {{ __('Sales') }} {{ __('List') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-datatable table-responsive pt-0">
                                <table class="datatables-basic-holds table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('SN') }}</th>
                                            <th>{{ __('Hold No') }}</th>
                                            <th>{{ __('Customer') }}</th>
                                            <th>{{ __('Employee') }}</th>
                                            <th>{{ __('Items') }}</th>
                                            <th>{{ __('Total') }}</th>
                                            <th>{{ __('Date') }}</th>
                                            <th>{{ __('Action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                    {!! closeIconWithText() !!}
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Preview Hold Modal -->
<div class="modal fade" id="modal_pos_preview_hold" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Preview') }} {{ __('Hold') }} {{ __('Sale') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="preview_hold_content">
                <div class="text-center p-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">{{ __('Loading') }}...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                    {!! closeIconWithText() !!}
                </button>
            </div>
        </div>
    </div>
</div>
