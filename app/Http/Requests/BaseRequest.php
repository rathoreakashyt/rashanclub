<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class BaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (array_keys($this->rules()) as $field) {
            // Use translation if available, otherwise convert snake_case to Title Case
            $translated = trans('validation.attributes.' . $field, [], $this->getLocale());
            $attributes[$field] = $translated !== 'validation.attributes.' . $field ? $translated : Str::title(Str::snake($field, ' '));
        }

        return $attributes;
    }
}