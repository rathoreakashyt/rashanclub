@extends('backend.backend_layout')
@section('page-title', __('Salary') . ' ' . __('Details'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
<style>
    .border-dashed-bottom {
        border-bottom: 1px dashed #959292;
    }
</style>
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Salary Details -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Salary') }} {{ __('Details') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Salary'), 
                    'link' => route('salary.index')
                ],
                [
                    'label' => __('Salary') . ' ' . __('Details'),
                    'active' => true
                ]
            ]
        ])
    </div>

    @if(session('success'))
        {!! insertSuccess(session('success')) !!}
    @endif
    @if(session('error'))
        {!! insertFailed(session('error')) !!}
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"></h5>
                        <div>
                            <a href="{{ route('salary.edit', $salary->encrypted_id) }}" class="btn btn-warning">
                                <i class="ti tabler-edit me-1"></i>
                            </a>
                            <a href="{{ route('salary.index') }}" class="btn btn-primary">
                                <i class="ti tabler-arrow-back-up me-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row my-4">
                        <div class="col-md-6 pb-3">
                            <h5 class="mb-2"><b>{{ __('Salary') }} {{ __('Info') }}</b></h5>
                            @if($salary->reference_no)
                                <p class="mb-0">{{ __('Reference No') }}: {{ $salary->reference_no }}</p>
                            @endif
                            @if($salary->year)
                                <p class="mb-0">{{ __('Year') }}: {{ $salary->year }}</p>
                            @endif
                            @if($salary->month)
                                @php
                                    $monthNames = [
                                        1 => __('January'), 2 => __('February'), 3 => __('March'), 4 => __('April'),
                                        5 => __('May'), 6 => __('June'), 7 => __('July'), 8 => __('August'),
                                        9 => __('September'), 10 => __('October'), 11 => __('November'), 12 => __('December')
                                    ];
                                @endphp
                                <p class="mb-0">{{ __('Month') }}: {{ $monthNames[$salary->month] ?? $salary->month }}</p>
                            @endif
                            @if($salary->generated_date)
                                <p class="mb-0">{{ __('Generation Date') }}: {{ formatDate($salary->generated_date) }}</p>
                            @endif
                            @if($salary->user)
                                <p class="mb-0">{{ __('Created By') }}: {{ $salary->user->name }}</p>
                            @endif
                            @if($salary->created_at)
                                <p class="mb-0">{{ __('Created At') }}: {{ formatDateTime($salary->created_at) }}</p>
                            @endif
                        </div>
                        <div class="col-md-6 pb-3">
                            <h5 class="mb-2"><b>{{ __('Summary') }}</b></h5>
                            <p class="mb-0">{{ __('Total Employees') }}: {{ $salary->salaryItems->count() }}</p>
                            <p class="mb-0">{{ __('Total Amount') }}: {{ formatAmount($salary->total_amount) }}</p>
                            @if($salary->salaryPayments && $salary->salaryPayments->count() > 0)
                                <p class="mb-0">{{ __('Total Payment') }}: {{ formatAmount($salary->salaryPayments->sum('amount')) }}</p>
                            @endif
                        </div>
                    </div>
                    
                    <h6 class="mb-3">{{ __('Employee') }} {{ __('Salary') }} {{ __('Details') }}</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>{{ __('SN') }}</th>
                                    <th>{{ __('Employee') }} {{ __('Name') }}</th>
                                    <th>{{ __('Salary') }} {{ __('Amount') }}</th>
                                    <th>{{ __('Overtime') }}</th>
                                    <th>{{ __('Additional') }}</th>
                                    <th>{{ __('Deduction') }}</th>
                                    <th>{{ __('Absent') }}</th>
                                    <th>{{ __('Advance') }}</th>
                                    <th>{{ __('Net') }} {{ __('Salary') }}</th>
                                    <th>{{ __('Note') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($salary->salaryItems as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $item->employee ? $item->employee->name : __('N/A') }}</td>
                                    <td>{{ formatAmount($item->salary_amount) }}</td>
                                    <td>
                                        @if($item->overtime_rate > 0 && $item->overtime_hour > 0)
                                            {{ formatAmount($item->overtime_rate * $item->overtime_hour) }}
                                            <small class="text-muted d-block">({{ $item->overtime_rate }} × {{ $item->overtime_hour }}h)</small>
                                        @else
                                            {{ formatAmount(0) }}
                                        @endif
                                    </td>
                                    <td>{{ formatAmount($item->additional_amount) }}</td>
                                    <td>{{ formatAmount($item->deduction_amount) }}</td>
                                    <td>
                                        @if($item->absent_day > 0)
                                            {{ formatAmount($item->absent_day * $item->absent_day_amount) }}
                                            <small class="text-muted d-block">({{ $item->absent_day }} {{ __('Days') }})</small>
                                        @else
                                            {{ formatAmount(0) }}
                                        @endif
                                    </td>
                                    <td>{{ formatAmount($item->advance_taken) }}</td>
                                    <td><strong>{{ formatAmount($item->net_salary) }}</strong></td>
                                    <td>{{ $item->note ?? __('N/A') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="row mb-4 justify-content-end">
                        <div class="col-md-6 col-lg-4 col-xl-3">
                            <h5 class="mb-2 mt-4 border-dashed-bottom pb-2"><b>{{ __('Salary') }} {{ __('Summary') }}</b></h5>
                            <table class="w-100">
                                <tr>
                                    <td width="w-50">{{ __('Total Employees') }}</td>
                                    <td width="w-50">: {{ $salary->salaryItems->count() }}</td>
                                </tr>
                                <tr>
                                    <td width="w-50">{{ __('Total Amount') }}</td>
                                    <td width="w-50">: {{ formatAmount($salary->total_amount) }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="row mb-4 justify-content-end">
                        <div class="col-md-6 col-lg-4 col-xl-3">
                            @if($salary->salaryPayments && $salary->salaryPayments->count() > 0)
                            <h5 class="mb-2 border-dashed-bottom pb-2"><b>{{ __('Payment') }} {{ __('Information') }}</b></h5>
                            <table class="w-100">
                                <thead>
                                    <tr>
                                        <th width="w-50">{{ __('Payment Method') }}</th>
                                        <th width="w-50">{{ __('Amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($salary->salaryPayments as $index => $payment)
                                    <tr>
                                        <td width="w-50">{{ $payment->paymentMethod ? $payment->paymentMethod->name : __('N/A') }}</td>
                                        <td width="w-50">{{ formatAmount($payment->amount) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td width="w-50"><strong>{{ __('Total Payment') }}</strong></td>
                                        <td width="w-50"><strong>: {{ formatAmount($salary->salaryPayments->sum('amount')) }}</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('page-js')
@routes
<!-- Page JS -->
<script src="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{ asset('backend_assets/js/extended-ui-sweetalert2.js')}}"></script>
@endpush

