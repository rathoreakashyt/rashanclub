<div class="modal fade" id="modal_pos_sales" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pos_sales_modal_title">{{ __('List of Sales') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-datatable table-responsive pt-0">
                                <table class="datatables-basic-sales table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('SN') }}</th>
                                            <th>{{ __('Sale No') }}</th>
                                            <th>{{ __('Customer') }}</th>
                                            <th>{{ __('Employee') }}</th>
                                            <th>{{ __('Sale Date') }}</th>
                                            <th>{{ __('Grand Total') }}</th>
                                            <th>{{ __('Paid Amount') }}</th>
                                            <th>{{ __('Due Amount') }}</th>
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