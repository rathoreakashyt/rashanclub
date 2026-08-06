@extends('backend.backend_layout')
@section('page-title', isset($salary) ? __('Edit') . ' ' . __('Salary') : __('Add') . ' ' . __('Salary'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/flatpickr/flatpickr.css') }}" />
<style>
    .payment-amount.is-invalid {
        border-color: #ea5455;
    }
    .payment-error {
        display: block;
        color: #ea5455;
        font-size: 0.875rem;
        margin-top: 0.25rem;
    }
</style>
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Add/Edit Salary -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($salary) ? __('Update') . ' ' . __('Salary') : __('Add') . ' ' . __('Salary') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' =>  __('Salary'), 
                    'link' => route('salary.index')
                ],
                [
                    'label' => isset($salary) ? __('Update') . ' ' . __('Salary') : __('Add') . ' ' . __('Salary'),
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
            <form id="salaryForm" action="{{ isset($salary) ? route('salary.update', $salary->encrypted_id) : route('salary.store') }}" method="POST">
            @csrf
            @if(isset($salary))
                @method('PUT')
            @endif
            <div class="card">
                <div class="card-body">
                    
                    <div class="row">
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5">
                                <label class="form-label" for="reference_no">{{ __('Reference No') }} {!! requiredField() !!}</label>
                                <input type="text" class="form-control @error('reference_no') is-invalid @enderror" 
                                    placeholder="{{ __('Reference No') }}" name="reference_no" id="reference_no" 
                                    value="{{ old('reference_no', isset($salary) ? $salary->reference_no : ($reference_no ?? '')) }}" 
                                    @if(isset($salary)) readonly @endif />
                                @error('reference_no')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5">
                                <label class="form-label" for="year">{{ __('Year') }} {!! requiredField() !!}</label>
                                <input type="text" class="form-control number-input @error('year') is-invalid @enderror" 
                                    placeholder="{{ __('Year') }}" name="year" id="year" 
                                    value="{{ old('year', isset($salary) ? $salary->year : date('Y')) }}" 
                                    min="2000" max="2100" />
                                @error('year')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5">
                                <label class="form-label" for="month">{{ __('Month') }} {!! requiredField() !!}</label>
                                <select id="month" name="month" class="select2 form-select @error('month') is-invalid @enderror" data-placeholder="{{ __('Select Month') }}">
                                    <option value="">{{ __('Select Month') }}</option>
                                    @php $selectedMonth = old('month', isset($salary) ? $salary->month : (int) date('n')); @endphp
                                    <option value="1" {{ $selectedMonth == 1 ? 'selected' : '' }}>{{ __('January') }}</option>
                                    <option value="2" {{ $selectedMonth == 2 ? 'selected' : '' }}>{{ __('February') }}</option>
                                    <option value="3" {{ $selectedMonth == 3 ? 'selected' : '' }}>{{ __('March') }}</option>
                                    <option value="4" {{ $selectedMonth == 4 ? 'selected' : '' }}>{{ __('April') }}</option>
                                    <option value="5" {{ $selectedMonth == 5 ? 'selected' : '' }}>{{ __('May') }}</option>
                                    <option value="6" {{ $selectedMonth == 6 ? 'selected' : '' }}>{{ __('June') }}</option>
                                    <option value="7" {{ $selectedMonth == 7 ? 'selected' : '' }}>{{ __('July') }}</option>
                                    <option value="8" {{ $selectedMonth == 8 ? 'selected' : '' }}>{{ __('August') }}</option>
                                    <option value="9" {{ $selectedMonth == 9 ? 'selected' : '' }}>{{ __('September') }}</option>
                                    <option value="10" {{ $selectedMonth == 10 ? 'selected' : '' }}>{{ __('October') }}</option>
                                    <option value="11" {{ $selectedMonth == 11 ? 'selected' : '' }}>{{ __('November') }}</option>
                                    <option value="12" {{ $selectedMonth == 12 ? 'selected' : '' }}>{{ __('December') }}</option>
                                </select>
                                @error('month')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5">
                                <label class="form-label" for="generated_date">{{ __('Generation Date') }} {!! requiredField() !!}</label>
                                <input type="text" class="form-control datePicker @error('generated_date') is-invalid @enderror" 
                                    placeholder="{{ __('Generation Date') }}" name="generated_date" id="generated_date" 
                                    value="{{ old('generated_date', isset($salary) ? $salary->generated_date : date('Y-m-d')) }}" />
                                @error('generated_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Employee Salaries Section -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-h6">{{ __('Employee Salaries') }} <span class="text-danger">*</span></h6>
                            </div>
                            
                            @php
                                // Build employee items from $salary (edit) or old() (validation failed on create)
                                $employeeItems = [];
                                if (isset($salary) && $salary->salaryItems->count() > 0) {
                                    foreach ($salary->salaryItems as $index => $item) {
                                        $employeeItems[$index] = [
                                            'employee_id' => $item->employee_id,
                                            'employee_name' => $item->employee ? $item->employee->name : __('Employee') . ' ' . ($index + 1),
                                            'salary_amount' => $item->salary_amount,
                                            'overtime_rate' => $item->overtime_rate,
                                            'overtime_hour' => $item->overtime_hour,
                                            'additional_amount' => $item->additional_amount,
                                            'deduction_amount' => $item->deduction_amount,
                                            'absent_day' => $item->absent_day,
                                            'absent_day_amount' => $item->absent_day_amount,
                                            'advance_taken' => $item->advance_taken,
                                            'note' => $item->note,
                                            'net_salary' => $item->net_salary,
                                        ];
                                    }
                                } elseif (old('items')) {
                                    $employeesMap = collect($employees ?? [])->keyBy('id');
                                    foreach (old('items') as $index => $item) {
                                        $empId = $item['employee_id'] ?? null;
                                        $emp = $employeesMap->get($empId);
                                        $employeeItems[$index] = [
                                            'employee_id' => $empId,
                                            'employee_name' => ($emp && isset($emp['name'])) ? $emp['name'] : __('Employee') . ' ' . ($index + 1),
                                            'salary_amount' => $item['salary_amount'] ?? 0,
                                            'overtime_rate' => $item['overtime_rate'] ?? 0,
                                            'overtime_hour' => $item['overtime_hour'] ?? 0,
                                            'additional_amount' => $item['additional_amount'] ?? 0,
                                            'deduction_amount' => $item['deduction_amount'] ?? 0,
                                            'absent_day' => $item['absent_day'] ?? 0,
                                            'absent_day_amount' => $item['absent_day_amount'] ?? 0,
                                            'advance_taken' => $item['advance_taken'] ?? 0,
                                            'note' => $item['note'] ?? '',
                                            'net_salary' => $item['net_salary'] ?? 0,
                                        ];
                                    }
                                }
                            @endphp
                            <div id="employeeItems">
                                @foreach($employeeItems as $index => $item)
                                    <div class="card mb-3 employee-item" data-index="{{ $index }}">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0">{{ $item['employee_name'] }}</h6>
                                            <button type="button" class="btn  remove-employee">
                                                <i class="icon-base ti tabler-trash text-danger"></i>
                                            </button>
                                        </div>
                                        <div class="card-body">
                                            <input type="hidden" name="items[{{ $index }}][employee_id]" class="employee-id" value="{{ $item['employee_id'] }}">
                                            <div class="row">
                                                <div class="col-6 col-md-3 mb-2">
                                                    <label class="form-label">{{ __('Salary Amount') }}</label>
                                                    <input type="text" class="form-control number-input salary-amount" name="items[{{ $index }}][salary_amount]" value="{{ $item['salary_amount'] }}">
                                                </div>
                                                <div class="col-6 col-md-3 mb-2">
                                                    <label class="form-label">{{ __('Overtime Rate') }}</label>
                                                    <input type="text" class="form-control number-input overtime-rate" name="items[{{ $index }}][overtime_rate]" value="{{ $item['overtime_rate'] }}">
                                                </div>
                                                <div class="col-6 col-md-3 mb-2">
                                                    <label class="form-label">{{ __('Overtime Hour') }}</label>
                                                    <input type="text" class="form-control number-input overtime-hour" name="items[{{ $index }}][overtime_hour]" value="{{ $item['overtime_hour'] }}">
                                                </div>
                                                <div class="col-6 col-md-3 mb-2">
                                                    <label class="form-label">{{ __('Additional Amount') }}</label>
                                                    <input type="text" class="form-control number-input additional-amount" name="items[{{ $index }}][additional_amount]" value="{{ $item['additional_amount'] }}">
                                                </div>
                                                <div class="col-6 col-md-3 mb-2">
                                                    <label class="form-label">{{ __('Deduction Amount') }}</label>
                                                    <input type="text" class="form-control number-input deduction-amount" name="items[{{ $index }}][deduction_amount]" value="{{ $item['deduction_amount'] }}">
                                                </div>
                                                <div class="col-6 col-md-3 mb-2">
                                                    <label class="form-label">{{ __('Absent Days') }}</label>
                                                    <input type="text" class="form-control number-input absent-day" name="items[{{ $index }}][absent_day]" value="{{ $item['absent_day'] }}">
                                                </div>
                                                <div class="col-6 col-md-3 mb-2">
                                                    <label class="form-label">{{ __('Absent Amount') }}</label>
                                                    <input type="text" class="form-control number-input absent-day-amount" name="items[{{ $index }}][absent_day_amount]" value="{{ $item['absent_day_amount'] }}">
                                                </div>
                                                <div class="col-6 col-md-3 mb-2">
                                                    <label class="form-label">{{ __('Advance Taken') }}</label>
                                                    <input type="text" class="form-control number-input advance-taken" name="items[{{ $index }}][advance_taken]" value="{{ $item['advance_taken'] }}" readonly>
                                                </div>
                                                <div class="col-6 col-md-6">
                                                    <label class="form-label">{{ __('Note') }}</label>
                                                    <input type="text" class="form-control" name="items[{{ $index }}][note]" value="{{ $item['note'] }}" placeholder="{{ __('Enter Note') }}">
                                                </div>
                                                <div class="col-12 col-md-3">
                                                    <label class="form-label"><strong>{{ __('Net Salary') }}</strong></label>
                                                    <input type="text" class="form-control number-input net-salary" name="items[{{ $index }}][net_salary]" value="{{ $item['net_salary'] }}" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Total Amount -->
                    <div class="row">
                        <div class="col-12 col-md-6 col-lg-4 ms-auto">
                            <div class="mb-5">
                                <label class="form-label" for="total_amount">{{ __('Total Amount') }}</label>
                                <input type="text" class="form-control number-input" 
                                    placeholder="{{ __('Total Amount') }}" name="total_amount" id="total_amount" 
                                    value="{{ old('total_amount', isset($salary) ? $salary->total_amount : 0) }}" 
                                    readonly />
                            </div>
                        </div>
                    </div>

                    <!-- Payment Methods Section -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-h6">{{ __('Payment Methods') }} <span class="text-danger">*</span></h6>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-12 col-md-6 col-lg-4 ms-auto">
                                    <div class="mb-3" id="payment_method_select_wrapper">
                                        <label class="form-label" for="payment_method_select">{{ __('Add Payment Account') }} {!! requiredField() !!}</label>
                                        <select id="payment_method_select" class="select2 form-select" data-placeholder="{{ __('Select Payment Account') }}">
                                            <option value="">{{ __('Select Payment Account') }}</option>
                                            @foreach($paymentMethods as $paymentMethod)
                                                <option value="{{ $paymentMethod->id }}" data-name="{{ $paymentMethod->name }}">{{ $paymentMethod->name }}</option>
                                            @endforeach
                                        </select>
                                        <div class="invalid-feedback d-block" id="payment_method_select_error" style="display: none;"></div>
                                    </div>
                                </div>
                            </div>
                            
                            @error('payments')
                                <div class="alert alert-danger py-2 mb-3" role="alert">
                                    {{ $message }}
                                </div>
                            @enderror
                            @php
                                // Build payment items from $salary (edit) or old() (validation failed on create)
                                $paymentItems = [];
                                if (isset($salary) && $salary->salaryPayments->count() > 0) {
                                    foreach ($salary->salaryPayments as $index => $payment) {
                                        $paymentItems[$index] = [
                                            'payment_method_id' => $payment->payment_method_id,
                                            'payment_method_name' => $payment->paymentMethod ? $payment->paymentMethod->name : __('Payment Method'),
                                            'amount' => $payment->amount,
                                        ];
                                    }
                                } elseif (old('payments')) {
                                    $paymentMethodsMap = collect($paymentMethods ?? [])->keyBy('id');
                                    foreach (old('payments') as $index => $payment) {
                                        $pmId = $payment['payment_method_id'] ?? null;
                                        $pm = $paymentMethodsMap->get($pmId);
                                        $paymentItems[$index] = [
                                            'payment_method_id' => $pmId,
                                            'payment_method_name' => $pm ? $pm->name : __('Payment Method'),
                                            'amount' => $payment['amount'] ?? 0,
                                        ];
                                    }
                                }
                            @endphp
                            <div id="paymentItems">
                                @foreach($paymentItems as $index => $payment)
                                    <div class="row mb-2 payment-item" data-index="{{ $index }}" data-payment-method-id="{{ $payment['payment_method_id'] }}">
                                        <div class="col-12 col-md-6 col-lg-4 ms-auto">
                                            <div class="input-group @error('payments.'.$index.'.amount') is-invalid @enderror">
                                                <span class="input-group-text">{{ $payment['payment_method_name'] }}</span>
                                                <input type="hidden" name="payments[{{ $index }}][payment_method_id]" value="{{ $payment['payment_method_id'] }}">
                                                <input type="text" class="form-control number-input payment-amount @error('payments.'.$index.'.amount') is-invalid @enderror" name="payments[{{ $index }}][amount]" value="{{ $payment['amount'] }}" placeholder="{{ $payment['payment_method_name'] }}" required>
                                                <span class="input-group-text remove-payment cursor-pointer">
                                                    <i class="icon-base ti tabler-trash text-danger"></i>
                                                </span>
                                            </div>
                                            @error('payments.'.$index.'.amount')
                                                <div class="invalid-feedback payment-error d-block">{{ $message }}</div>
                                            @else
                                                <div class="invalid-feedback payment-error" style="display: none;"></div>
                                            @enderror
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div id="paymentSectionError" class="invalid-feedback text-danger" style="display: none;"></div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex">
                        <button type="submit" value="submit" name="submit" class="btn btn-primary waves-effect waves-light me-4">
                            {!! submitIconWithText(isset($salary) ? $salary : '') !!}
                        </button>
                        <a href="{{ route('salary.index') }}" type="button" class="btn btn-primary waves-effect waves-light">
                            {!! backIconWithText() !!}
                        </a>
                    </div>
                </div>
            </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('page-js')
<script>
    // Single config object so external script always has access (avoids scope/order issues)
    window.salaryFormConfig = {
        salaryItemsCount: {{ count($employeeItems ?? []) }},
        salaryPaymentsCount: {{ count($paymentItems ?? []) }},
        employeesData: @json($employees ?? []),
        paymentMethodsData: @json($paymentMethods ?? []),
        isNewSalary: {{ !isset($salary) ? 'true' : 'false' }},
        hasRenderedItems: {{ (count($employeeItems ?? []) > 0) ? 'true' : 'false' }},
        advancesByMonthUrl: @json(route('salary.advances-by-month'))
    };
</script>
<script src="{{ asset('backend_assets/js/pages_js/add_salary.js') }}"></script>
@endpush

