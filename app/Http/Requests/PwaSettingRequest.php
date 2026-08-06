<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PwaSettingRequest extends FormRequest
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
            'app_name' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:100'],
            'theme_color' => ['required', 'string', 'max:20'],
            'background_color' => ['required', 'string', 'max:20'],
            'start_url' => ['required', 'string', 'max:500'],
            'logo_file' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,gif', 'dimensions:min_width=512,min_height=512', 'max:5120'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'app_name' => __('App Name'),
            'short_name' => __('Short Name'),
            'theme_color' => __('Theme Color'),
            'background_color' => __('Background Color'),
            'start_url' => __('Start URL'),
            'logo_file' => __('Logo'),
        ];
    }
}
