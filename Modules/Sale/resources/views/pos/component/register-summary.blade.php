<div class="modal fade" id="modal_pos_register_summary" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Register Summary') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="register-summary-loading" class="text-center py-5" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">{{ __('Loading') }}...</span>
                    </div>
                    <p class="mt-3 text-muted">{{ __('Loading register summary') }}...</p>
                </div>
                <div id="register-summary-content" style="display: none;">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>{{ __('User Name') }}:</strong> <span id="register-user-name"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>{{ __('Time Range') }}:</strong> <span id="register-time-range"></span></p>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="register-summary-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="w-5">{{ __('SN') }}</th>
                                    <th class="w-20">{{ __('Payment Method Name') }}</th>
                                    <th class="w-30">{{ __('Transaction') }}</th>
                                    <th class="w-15 text-end">{{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody id="register-summary-tbody">
                                <!-- Data will be populated here -->
                            </tbody>
                        </table>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12">
                            <h6 class="mb-3">{{ __('Summary') }}</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered" id="register-summary-totals-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="w-50">{{ __('Payment Method') }}</th>
                                            <th class="w-50 text-end">{{ __('Total Amount') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="register-summary-totals-tbody">
                                        <!-- Summary totals will be populated here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="register-summary-error" class="text-center py-5" style="display: none;">
                    <p class="text-danger" id="register-summary-error-message"></p>
                </div>
            </div>
            <div class="modal-footer">
                <div class="d-flex justify-content-between w-100">
                    <div>
                        <button type="button" class="btn btn-primary" id="register-summary-print-btn">
                            <i class="icon-base ti tabler-printer me-1"></i> {{ __('Print') }}
                        </button>
                        <button type="button" class="btn btn-primary" id="register-summary-excel-btn">
                            <i class="icon-base ti tabler-file-excel me-1"></i> {{ __('Excel') }}
                        </button>
                        <button type="button" class="btn btn-primary" id="register-summary-pdf-btn">
                            <i class="icon-base ti tabler-file-type-pdf me-1"></i> {{ __('PDF') }}
                        </button>
                    </div>
                    <button type="button" class="btn btn-label-danger" data-bs-dismiss="modal">
                        {!! closeIconWithText() !!}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
