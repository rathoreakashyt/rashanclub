<?php

namespace Modules\Administrator\Http\Request;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class UserRequest extends BaseRequest
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
        // Get the encrypted ID from the route parameter (null for profile update route)
        $encryptedId = $this->route('user');
        $userId = null;

        if ($encryptedId) {
            // Edit/update user: decrypt route parameter to get user ID
            try {
                if (is_string($encryptedId)) {
                    $userId = decrypt($encryptedId);
                    $userId = (int) $userId;
                } else {
                    $userId = $encryptedId->id ?? null;
                }
            } catch (\Exception $e) {
                $userId = null;
            }
        } else {
            // Profile update: no user in route, ignore current user's email/username
            $userId = $this->user()?->id;
        }
        
        $companyId = session('company.company_id');
        
        $rules = [
            'name' => ['required', 'string', 'max:55'],
            'email' => ['required', 'string', 'max:55', Rule::unique('users')->ignore($userId)->where('company_id', $companyId)->where('del_status', 'Live')],
            'phone' => ['required', 'string'],
            'role' => ['required', 'integer', 'exists:roles,id'],
            'salary' => ['nullable', 'numeric'],
            'commission' => ['nullable', 'numeric'],
            'discount_permission_code' => ['nullable', 'string', 'max:55'],
            'discount_amt' => ['nullable', 'string', 'max:11'],
            'start_date' => ['nullable', 'string'],
            'end_date' => ['nullable', 'string'],
            'will_login' => ['required', 'in:Yes,No'],
            'photo' => ['nullable', 'string'], // Base64 image from cropper
        ];

        // If will_login is Yes, password, confirm password, and outlets are required
        if ($this->will_login === 'Yes') {
            if ($this->isMethod('post') || ($this->isMethod('put') && $this->filled('password'))) {
                $rules['password'] = ['required', 'string', 'min:6', 'max:32', 'confirmed'];
                $rules['password_confirmation'] = ['required', 'string', 'min:6'];
            }
            $rules['outlets'] = ['required', 'array', 'min:1'];
            $rules['outlets.*'] = ['required', 'integer', 'exists:outlets,id'];
        } else {
            $rules['outlets'] = ['nullable', 'array'];
            $rules['outlets.*'] = ['nullable', 'integer', 'exists:outlets,id'];
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
        $name = __('Name');
        $email = __('Email');
        $phone = __('Phone');
        $role = __('Role');
        $salary = __('Salary');
        $commission = __('Commission');
        $discountPermissionCode = __('Discount Permission Code');
        $discountAmount = __('Discount_Amount') ?: 'Discount Amount';
        $startDate = __('Start_Date');
        $endDate = __('End_Date');
        $loginPermission = __('Login_Permission') ?: 'Login Permission';
        $photo = __('Photo');
        $password = __('Password') ?: 'Password';
        $passwordConfirmation = __('Password_Confirmation') ?: 'Password Confirmation';
        $outlets = __('Outlets') ?: 'Outlets';
        $outlet = __('Outlet');
        
        return [
            'name.required' => __('The') . ' ' . $name . ' ' . __('is required.'),
            'name.regex' => __('The') . ' ' . $name . ' ' . __('may only contain letters, spaces, and hyphens.'),
            'name.max' => __('The') . ' ' . $name . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'email.required' => __('The') . ' ' . __('Email or Username') . ' ' . __('is required.'),
            'email.unique' => __('This Email or Username is already in use.'),
            'email.max' => __('The') . ' ' . $email . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'phone.required' => __('The') . ' ' . $phone . ' ' . __('is required.'),
            'phone.regex' => __('The') . ' ' . $phone . ' ' . __('number format is invalid.'),
            'phone.min' => __('The') . ' ' . $phone . ' ' . __('number must be at least') . ' 10 ' . __('characters.'),
            'phone.max' => __('The') . ' ' . $phone . ' ' . __('number may not be greater than') . ' 20 ' . __('characters.'),
            'role.required' => __('The') . ' ' . $role . ' ' . __('is required.'),
            'role.exists' => __('The selected') . ' ' . $role . ' ' . __('is invalid.'),
            'salary.numeric' => __('The') . ' ' . $salary . ' ' . __('must be a number.'),
            'commission.numeric' => __('The') . ' ' . $commission . ' ' . __('must be a number.'),
            'discount_permission_code.max' => __('The') . ' ' . $discountPermissionCode . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'discount_amt.max' => __('The') . ' ' . $discountAmount . ' ' . __('may not be greater than') . ' 11 ' . __('characters.'),
            'will_login.required' => __('The') . ' ' . $loginPermission . ' ' . __('is required.'),
            'will_login.in' => __('The') . ' ' . $loginPermission . ' ' . __('must be either') . ' ' . __('Yes') . ' ' . __('or') . ' ' . __('No') . '.',
            'password.required' => __('The') . ' ' . $password . ' ' . __('is required.'),
            'password.min' => __('The') . ' ' . $password . ' ' . __('must be at least') . ' 6 ' . __('characters.'),
            'password.max' => __('The') . ' ' . $password . ' ' . __('may not be greater than') . ' 32 ' . __('characters.'),
            'password.confirmed' => __('The') . ' ' . $passwordConfirmation . ' ' . __('does not match.'),
            'password_confirmation.required' => __('The') . ' ' . $passwordConfirmation . ' ' . __('is required.'),
            'outlets.required' => __('At least one') . ' ' . strtolower($outlet) . ' ' . __('must be selected.'),
            'outlets.array' => __('The') . ' ' . $outlets . ' ' . __('must be an array.'),
            'outlets.min' => __('At least one') . ' ' . $outlet . ' ' . __('must be selected.'),
            'outlets.*.required' => __('An') . ' ' . $outlet . ' ' . __('must be selected.'),
            'outlets.*.exists' => __('The selected') . ' ' . $outlet . ' ' . __('is invalid.'),
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
            'name' => __('Name'),
            'email' => __('Email'),
            'phone' => __('Phone'),
            'role' => __('Role'),
            'salary' => __('Salary'),
            'commission' => __('Commission'),
            'discount_permission_code' => __('Discount Permission Code'),
            'discount_amt' => __('Discount_Amount') ?: 'Discount Amount',
            'start_date' => __('Start_Date'),
            'end_date' => __('End_Date'),
            'will_login' => __('Login_Permission') ?: 'Login Permission',
            'photo' => __('Photo'),
            'password' => __('Password') ?: 'Password',
            'password_confirmation' => __('Password_Confirmation') ?: 'Password Confirmation',
            'outlets' => __('Outlets') ?: 'Outlets',
        ];
    }
}
