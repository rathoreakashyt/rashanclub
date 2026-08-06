@extends('backend.backend_layout')
@section('page-title', isset($attendance) ? __('Edit') . ' ' . __('Attendance') : __('Add') . ' ' . __('Attendance'))
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Add/Edit Attendance -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($attendance) ? __('Update') . ' ' . __('Attendance') : __('Add') . ' ' . __('Attendance') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' =>  __('Attendance'), 
                    'link' => '#'
                ],
                [
                    'label' => isset($attendance) ? __('Update') . ' ' . __('Attendance') : __('Add') . ' ' . __('Attendance'),
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
            <form id="attendanceForm" action="{{ isset($attendance) ? route('attendance.update', $attendance->encrypted_id) : route('attendance.store') }}" method="POST">
            @csrf
            @if(isset($attendance))
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
                                    value="{{ old('reference_no', isset($attendance) ? $attendance->reference_no : ($reference_no ?? '')) }}" 
                                    @if(isset($attendance)) readonly @endif />
                                @error('reference_no')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5">
                                <label class="form-label" for="date">{{ __('Date') }} {!! requiredField() !!}</label>
                                <input type="text" class="form-control datePicker @error('date') is-invalid @enderror" 
                                    placeholder="{{ __('Date') }}" name="date" id="date" 
                                    value="{{ old('date', isset($attendance) ? $attendance->date : '') }}" />
                                @error('date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5">
                                <label class="form-label" for="employee_id">{{ __('Employee') }} {!! requiredField() !!}</label>
                                <select id="employee_id" name="employee_id" class="select2 form-select @error('employee_id') is-invalid @enderror" data-placeholder="{{ __('Select') }} {{ __('Employee') }}">
                                    <option value="">{{ __('Select') }} {{ __('Employee') }}</option>
                                    @foreach($employees as $id => $name)
                                        <option value="{{ $id }}" {{ old('employee_id', isset($attendance) ? $attendance->employee_id : '') == $id ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                                @error('employee_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5">
                                <label class="form-label" for="in_time">{{ __('In Time') }} {!! requiredField() !!}</label>
                                <input type="text" class="form-control timepicker @error('in_time') is-invalid @enderror" 
                                    placeholder="{{ __('In Time') }}" name="in_time" id="in_time" 
                                    value="{{ old('in_time', isset($attendance) ? $attendance->in_time : '') }}" />
                                @error('in_time')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5">
                                <label class="form-label" for="out_time">{{ __('Out Time') }}</label>
                                <input type="text" class="form-control timepicker @error('out_time') is-invalid @enderror" 
                                    placeholder="{{ __('Out Time') }}" name="out_time" id="out_time" 
                                    value="{{ old('out_time', isset($attendance) ? $attendance->out_time : '') }}" />
                                @error('out_time')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="mb-5">
                                <label class="form-label" for="note">{{ __('Note') }}</label>
                                <textarea class="form-control @error('note') is-invalid @enderror" 
                                    placeholder="{{ __('Note') }}" name="note" id="note" rows="3">{{ old('note', isset($attendance) ? $attendance->note : '') }}</textarea>
                                @error('note')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex">
                        <button type="submit" value="submit" name="submit" class="btn btn-primary waves-effect waves-light me-4">
                            {!! submitIconWithText(isset($attendance) ? $attendance : '') !!}
                        </button>
                        <a href="{{ route('attendance.index') }}" type="button" class="btn btn-primary waves-effect waves-light">
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
    $(document).ready(function () {
        // Initialize date picker
        $('.datePicker').flatpickr({
            dateFormat: "Y-m-d",
            maxDate: "today"
        });
        
        // Initialize time picker
        $('.timepicker').flatpickr({
            enableTime: true,
            noCalendar: true,
            dateFormat: "H:i",
            time_24hr: true
        });
    });
</script>
@endpush
