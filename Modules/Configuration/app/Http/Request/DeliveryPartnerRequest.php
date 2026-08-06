<?php

namespace Modules\Configuration\Http\Request;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class DeliveryPartnerRequest extends BaseRequest
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
        // Get the encrypted ID or model from the route parameter (matches route name {delivery_partner})
        $encryptedId = $this->route('delivery_partner');
        $partnerId = null;

        // If we have an encrypted ID or model, resolve it to the actual delivery partner ID (same as UnitRequest)
        if ($encryptedId) {
            try {
                if (is_string($encryptedId)) {
                    $partnerId = decrypt($encryptedId);
                    $partnerId = (int) $partnerId;
                } else {
                    $partnerId = $encryptedId->id ?? null;
                }
            } catch (\Exception $e) {
                $partnerId = null;
            }
        }

        $companyId = session('company.company_id');

        return [
            'partner_name' => [
                'required',
                'string',
                'max:55',
                Rule::unique('delivery_partners', 'name')->ignore($partnerId)->where('company_id', $companyId)->where('del_status', 'Live')
            ],
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
        $partnerName = __('Partner Name');
        $description = __('Description');
        
        return [
            'partner_name.required' => __('The') . ' ' . $partnerName . ' ' . __('is required.'),
            'partner_name.unique' => __('A Delivery Partner with this name already exists.'),
            'partner_name.max' => __('The') . ' ' . __('Delivery Partner Name') . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
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
            'partner_name' => __('Partner Name'),
            'description' => __('Description'),
        ];
    }
}
