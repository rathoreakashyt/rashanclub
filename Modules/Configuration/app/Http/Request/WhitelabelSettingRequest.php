<?php

namespace Modules\Configuration\Http\Request;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\ValidationException;

class WhitelabelSettingRequest extends BaseRequest
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
        return [
            'site_name' => ['required', 'string', 'max:255'],
            'site_footer' => ['required', 'string', 'max:255'],
            'site_title' => ['required', 'string', 'max:255'],
            'site_link' => ['required', 'string', 'max:255'],
            'site_logo' => ['nullable', 'string'],
            'site_logo_file' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
            'site_favicon' => ['nullable', 'string'],
            'site_favicon_file' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,gif,svg,ico', 'max:2048'],
            'site_logo_hidden' => ['nullable', 'string'],
            'site_favicon_hidden' => ['nullable', 'string'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate that either logo exists or new logo is provided
            if (empty($this->site_logo_hidden) && empty($this->site_logo) && !$this->hasFile('site_logo_file')) {
                $siteLogo = __('Site_Logo') ?: 'Site Logo';
                $validator->errors()->add('site_logo', $siteLogo . ' ' . __('is required.'));
            }

            // Validate that either favicon exists or new favicon is provided
            if (empty($this->site_favicon_hidden) && empty($this->site_favicon) && !$this->hasFile('site_favicon_file')) {
                $siteFavicon = __('Site_Favicon') ?: 'Site Favicon';
                $validator->errors()->add('site_favicon', $siteFavicon . ' ' . __('is required.'));
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        $siteName = __('Site Name');
        $siteFooter = __('Site Footer');
        $siteTitle = __('Site Title');
        $siteLink = __('Site Link');
        $siteLogo = __('Site Logo');
        $siteFavicon = __('Site Favicon');
        
        return [
            'site_name.required' => $siteName . ' ' . __('is required.'),
            'site_footer.required' => $siteFooter . ' ' . __('is required.'),
            'site_title.required' => $siteTitle . ' ' . __('is required.'),
            'site_link.required' => $siteLink . ' ' . __('is required.'),
            'site_logo' => $siteLogo . ' ' . __('is required.'),
            'site_favicon' => $siteFavicon . ' ' . __('is required.'),
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
            'site_name' => __('Site Name'),
            'site_footer' => __('Site Footer'),
            'site_title' => __('Site Title'),
            'site_link' => __('Site Link'),
            'site_logo' => __('Site Logo'),
            'site_favicon' => __('Site Favicon'),
        ];
    }
}

