<?php

namespace Modules\Administrator\Http\Request;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends BaseRequest
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
        $roleId = $this->resolveRoleId($this->route('role'));

        return [
            'name' => [
                'required',
                'string',
                'max:25',
                Rule::unique('roles')->ignore($roleId)
            ],
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'required|exists:permissions,id'
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $roleName = __('Role_Name') ?: __('Role') . ' ' . __('Name');
        $permissions = __('Permissions');
        
        return [
            'name.required' => __('The') . ' ' . $roleName . ' ' . __('is required.'),
            'name.unique' => __('A role with this name already exists.'),
            'name.max' => __('The') . ' ' . __('role name') . ' ' . __('may not be greater than') . ' 25 ' . __('characters.'),
            'permissions.required' => __('At least one') . ' ' . strtolower($permissions) . ' ' . __('must be selected.'),
            'permissions.min' => __('At least one') . ' ' . strtolower($permissions) . ' ' . __('must be selected.'),
            'permissions.*.exists' => __('Selected') . ' ' . strtolower($permissions) . ' ' . __('is invalid.'),
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
            'name' => __('Role_Name') ?: __('Role') . ' ' . __('Name'),
            'permissions' => __('Permissions'),
        ];
    }

    /**
     * Resolve role ID from route parameter (encrypted string or numeric).
     */
    protected function resolveRoleId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        try {
            return (int) decrypt($value);
        } catch (\Exception $e) {
            return null;
        }
    }
}
