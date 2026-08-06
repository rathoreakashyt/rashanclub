<?php

namespace Modules\Configuration\Http\Request;

use App\Http\Requests\BaseRequest;

class DenominationRequest extends BaseRequest
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
        return [
            'amount' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $amount = __('Amount');
        $description = __('Description');
        
        return [
            'amount.required' => __('The') . ' ' . $amount . ' ' . __('is required.'),
            'amount.numeric' => __('The') . ' ' . $amount . ' ' . __('must be a valid number.'),
            'amount.min' => __('The') . ' ' . $amount . ' ' . __('must be at least') . ' 0.',
            'description.max' => __('The') . ' ' . $description . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'amount' => __('Amount'),
            'description' => __('Description'),
        ];
    }
}

