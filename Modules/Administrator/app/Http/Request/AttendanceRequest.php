<?php

namespace Modules\Administrator\Http\Request;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class AttendanceRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Get the attendance ID from the route parameter
        $attendanceId = $this->route('attendance');
        
        $rules = [
            'date' => ['required', 'date', 'before_or_equal:today'],
            'employee_id' => ['required', 'integer', 'exists:users,id'],
            'in_time' => ['required', 'date_format:H:i'],
            'out_time' => ['nullable', 'date_format:H:i', 'after:in_time'],
            'note' => ['nullable', 'string', 'max:500'],
        ];

        // For create requests, reference_no is required and must be unique
        if (!$attendanceId) {
            $rules['reference_no'] = ['required', 'string', 'max:50', 'unique:attendances,reference_no'];
        } else {
            // For update requests, reference_no must be unique except for the current record
            $rules['reference_no'] = ['required', 'string', 'max:50', 'unique:attendances,reference_no,'.$attendanceId];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $date = __('Date');
        $employee = __('Employee');
        $refNum = __('Reference_Number') ?: 'Reference Number';
        $inTime = __('In_Time') ?: 'In Time';
        $outTime = __('Out_Time') ?: 'Out Time';
        
        return [
            'date.required' => __('The') . ' ' . $date . ' ' . __('is required.'),
            'date.before_or_equal' => __('The') . ' ' . $date . ' ' . __('must be today or a previous date.'),
            'employee_id.required' => __('Please select an') . ' ' . $employee . '.',
            'employee_id.exists' => __('The selected') . ' ' . $employee . ' ' . __('is invalid.'),
            'in_time.required' => __('The') . ' ' . $inTime . ' ' . __('is required.'),
            'in_time.date_format' => __('The') . ' ' . $inTime . ' ' . __('must be in HH:MM format.'),
            'out_time.date_format' => __('The') . ' ' . $outTime . ' ' . __('must be in HH:MM format.'),
            'out_time.after' => __('The') . ' ' . $outTime . ' ' . __('must be after the') . ' ' . $inTime . '.',
            'reference_no.required' => __('The') . ' ' . $refNum . ' ' . __('is required.'),
            'reference_no.unique' => __('The') . ' ' . $refNum . ' ' . __('has already been taken.'),
        ];
    }

    /**
     * Get custom attribute names.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date' => __('Date'),
            'employee_id' => __('Employee'),
            'in_time' => __('In_Time') ?: 'In Time',
            'out_time' => __('Out_Time') ?: 'Out Time',
            'note' => __('Note'),
            'reference_no' => __('Reference_Number') ?: 'Reference Number',
        ];
    }
}
