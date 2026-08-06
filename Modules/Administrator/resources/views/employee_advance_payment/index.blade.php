@extends('backend.backend_layout')
@section('page-title', __('List') . ' ' . __('Employee Advance Payment'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('List') }} {{ __('Employee Advance Payment') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => '#'],
                ['label' => __('Employee Advance Payment'), 'link' => '#'],
                ['label' => __('List') . ' ' . __('Employee Advance Payment'), 'active' => true]
            ]
        ])
    </div>

    @if(session('success'))
        {!! insertSuccess(session('success')) !!}
    @endif
    @if(session('error'))
        {!! insertFailed(session('error')) !!}
    @endif

    <!-- Filter Section -->
    <div class="card mb-4" id="filterSection" style="display: none;">
        <div class="card-body">
            <form id="filterForm" onsubmit="event.preventDefault(); return false;" action="javascript:void(0);">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('Date From') }}</label>
                        <input type="text" class="form-control datePicker" placeholder="{{ __('Date From') }}" id="date_from_filter" name="date_from_filter">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('Date To') }}</label>
                        <input type="text" class="form-control datePicker" placeholder="{{ __('Date To') }}" id="date_to_filter" name="date_to_filter">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">{{ __('Employee') }}</label>
                        <select class="form-select select2" id="employee_filter" name="employee_filter">
                            <option value="">{{ __('All Employees') }}</option>
                            @php
                                $employees = \App\Models\User::where('del_status', 'Live')
                                    ->where('company_id', session('company.company_id'))
                                    ->orderBy('name')
                                    ->get();
                            @endphp
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mt-3 d-inline-flex align-items-center">
                        <button type="button" class="btn btn-primary me-2" id="applyFilter">
                            <i class="ti tabler-filter me-1"></i>{{ __('Apply Filter') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Total Amount Display -->
    <div class="card mb-4" id="totalAmountCard" style="display: none;">
        <div class="card-body">
            <h5 class="mb-0">
                <strong>{{ __('Total Amount') }}: <span id="totalAmountDisplay">0</span></strong>
            </h5>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                @routes
                <input type="hidden" id="logged_in_user_id" value="{{ Auth::id() }}">
                <div class="card-datatable table-responsive pt-0">
                    <table class="datatables-basic table">
                        <thead>
                            <tr>
                                <th>{{ __('SN') }}</th>
                                <th>{{ __('Reference No') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Amount') }}</th>
                                <th>{{ __('Employee') }}</th>
                                <th>{{ __('Payment Method') }}</th>
                                <th>{{ __('Note') }}</th>
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
@endsection
@push('page-js')
@routes
<script src="{{ asset('vendor/ziggy/ziggy.js') }}"></script>
<script src="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{ asset('backend_assets/js/extended-ui-sweetalert2.js')}}"></script>
<script src="{{ asset('backend_assets/js/pages_list_js/list_employee_advance_payment.js')}}"></script>
<script src="{{ asset('backend_assets/js/pages_list_js/delete_confirmation.js')}}"></script>
@endpush
