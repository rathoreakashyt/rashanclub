@extends('backend.backend_layout')
@section('page-title', __('Scheme_Report'))
@push('page-css')
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Scheme_Report') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => '#'],
                ['label' => __('Reports'), 'link' => '#'],
                ['label' => __('Scheme_Report'), 'active' => true]
            ]
        ])
    </div>

    <div class="card">
        <div class="card-body">
            <form id="filter-form" class="row mb-4">
                <div class="col-md-3">
                    <label class="form-label">{{ __('Date_From') }}</label>
                    <input type="text" class="form-control datePicker" name="date_from" id="date_from" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('Date_To') }}</label>
                    <input type="text" class="form-control datePicker" name="date_to" id="date_to" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('Type') }}</label>
                    <select class="form-select select2" name="type" id="type">
                        <option value="">{{ __('All') }}</option>
                        <option value="1">{{ __('Discount') }}</option>
                        <option value="2">{{ __('Coupon_Discount') }}</option>
                        <option value="3">{{ __('Free_Item') }}</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">{{ __('Filter') }}</button>
                    <button type="button" class="btn btn-label-secondary ms-2" id="reset-btn">{{ __('Reset') }}</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered" id="scheme-report-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('Title') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Scheme_Basis') }}</th>
                            <th>{{ __('Start_Date') }}</th>
                            <th>{{ __('End_Date') }}</th>
                            <th>{{ __('Start_Time') }}</th>
                            <th>{{ __('End_Time') }}</th>
                            <th>{{ __('Min_Purchase') }}</th>
                            <th>{{ __('Max_Discount') }}</th>
                            <th>{{ __('Discount') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
@push('page-js')
<script>
$(function() {
    var base_url = $('#base_url').val();
    var table = $('#scheme-report-table').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: base_url + '/reports/scheme-report',
            type: 'GET',
            data: function(d) {
                d.date_from = $('#date_from').val();
                d.date_to = $('#date_to').val();
                d.type = $('#type').val();
            }
        },
        columns: [
            { data: 'id' },
            { data: 'title' },
            { data: 'type' },
            { data: 'scheme_basis' },
            { data: 'start_date' },
            { data: 'end_date' },
            { data: 'start_time', defaultContent: '-' },
            { data: 'end_time', defaultContent: '-' },
            { data: 'min_purchase', render: function(d) { return parseFloat(d).toFixed(2); } },
            { data: 'max_discount', render: function(d) { return parseFloat(d).toFixed(2); } },
            { data: 'discount' },
            { data: 'status' }
        ],
        order: [[0, 'asc']]
    });

    $('#filter-form').on('submit', function(e) {
        e.preventDefault();
        table.ajax.reload();
    });

    $('#reset-btn').on('click', function() {
        $('#date_from, #date_to, #type').val('').trigger('change');
        table.ajax.reload();
    });
});
</script>
@endpush
