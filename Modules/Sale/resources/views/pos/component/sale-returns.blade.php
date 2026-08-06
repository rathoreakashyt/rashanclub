<div class="modal fade" id="modal_pos_sale_returns" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pos_sale_returns_modal_title">{{ __('Sale Returns') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs mb-3" id="pos_sale_returns_tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pos_sale_returns_list_tab" data-bs-toggle="tab" data-bs-target="#pos_sale_returns_list" type="button" role="tab">
                            <i class="icon-base ti tabler-list me-1"></i> {{ __('List') }}
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pos_sale_returns_create_tab" data-bs-toggle="tab" data-bs-target="#pos_sale_returns_create" type="button" role="tab">
                            <i class="icon-base ti tabler-plus me-1"></i> {{ __('Add Sale Return') }}
                        </button>
                    </li>
                </ul>

                <!-- Tabs Content -->
                <div class="tab-content p-0" id="pos_sale_returns_tab_content">
                    <!-- List Tab -->
                    <div class="tab-pane fade show active" id="pos_sale_returns_list" role="tabpanel">
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-datatable table-responsive pt-0">
                                        <table class="datatables-basic-sale-returns table">
                                            <thead>
                                                <tr>
                                                    <th>{{ __('SN') }}</th>
                                                    <th>{{ __('Reference No') }}</th>
                                                    <th>{{ __('Customer') }}</th>
                                                    <th>{{ __('Sale Invoice') }}</th>
                                                    <th>{{ __('Date') }}</th>
                                                    <th>{{ __('Total Return Amount') }}</th>
                                                    <th>{{ __('Paid') }}</th>
                                                    <th>{{ __('Due') }}</th>
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

                    <!-- Create Tab -->
                    <div class="tab-pane fade" id="pos_sale_returns_create" role="tabpanel">
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div id="pos_sale_return_form_container">
                                        <!-- Form will be loaded here via AJAX -->
                                        <div class="text-center p-5">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">{{ __('Loading') }}...</span>
                                            </div>
                                            <p class="mt-3 text-muted">{{ __('Loading form') }}...</p>
                                        </div>
                                    </div>
                                </div>
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