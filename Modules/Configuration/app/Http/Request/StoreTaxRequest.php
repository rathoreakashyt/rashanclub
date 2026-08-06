<?php

namespace Modules\Configuration\Http\Request;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreTaxRequest extends BaseRequest
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
     */
    public function rules(): array
    {
        $companyId = session('company.company_id');
        $taxId = $this->route('id');

        $rules = [
            'tax_name' => [
                'required',
                'string',
                'max:55',
                Rule::unique('taxs', 'tax_name')
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live')
                    ->ignore($taxId),
            ],
            'show_in_item_profile' => ['required', 'string', 'in:Yes,No'],
        ];

        if (strtoupper($this->tax_name ?? '') === 'GST') {
            $rules['sub_tax_ids'] = [
                'required',
                'array',
                'size:3',
                function ($attribute, $value, $fail) {
                    $subTaxes = \Modules\Configuration\Models\Tax::forCompany()->live()
                        ->whereIn('tax_name', ['IGST', 'CGST', 'SGST'])
                        ->whereIn('id', array_map('intval', (array) $value))
                        ->pluck('tax_name')->toArray();
                    $required = ['IGST', 'CGST', 'SGST'];
                    $missing = array_diff($required, $subTaxes);
                    $extra = array_diff($subTaxes, $required);
                    if (!empty($missing) || !empty($extra) || count($subTaxes) !== 3) {
                        $fail(__('GST requires exactly 3 sub taxes: IGST, CGST and SGST. Neither more nor less.'));
                    }
                },
            ];
            $rules['sub_tax_ids.*'] = [
                'required',
                'integer',
                'exists:taxs,id',
                function ($attribute, $value, $fail) {
                    $allowedIds = \Modules\Configuration\Models\Tax::forCompany()->live()
                        ->whereIn('tax_name', ['IGST', 'CGST', 'SGST'])
                        ->pluck('id')->toArray();
                    if (!in_array((int) $value, $allowedIds)) {
                        $fail(__('Only IGST, CGST, SGST can be assigned as sub taxes for GST.'));
                    }
                },
            ];
            $rules['tax_rate'] = ['nullable', 'numeric', 'min:0', 'max:100'];
        } else {
            $rules['tax_rate'] = ['required', 'numeric', 'min:0', 'max:100'];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'tax_name.required' => __('Tax Name') . ' ' . __('is required.'),
            'tax_name.unique' => __('Tax Name') . ' ' . __('already exists.'),
            'tax_rate.required' => __('Tax Rate') . ' ' . __('is required.'),
            'tax_rate.numeric' => __('Tax Rate') . ' ' . __('must be a number'),
            'sub_tax_ids.required' => __('Create IGST, CGST, SGST first and assign to GST.'),
            'sub_tax_ids.size' => __('GST requires exactly 3 sub taxes: IGST, CGST and SGST. Neither more nor less.'),
            'sub_tax_ids.*.exists' => __('Selected sub tax is invalid.'),
            'show_in_item_profile.required' => __('Show in Item Profile') . ' ' . __('is required.'),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'tax_name' => __('Tax Name'),
            'tax_rate' => __('Tax Rate'),
            'sub_tax_ids' => __('Sub Taxes'),
            'show_in_item_profile' => __('Show in Item Profile'),
        ];
    }
}
