<?php

namespace App\Rules;

use App\Helpers\GstinValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that the value is a valid Indian GSTIN (15 chars, regex format + state code).
 */
class GstinRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $value = is_string($value) ? $value : (string) $value;

        if (! GstinValidator::isValid($value)) {
            $fail(__('The :attribute must be a valid Indian GSTIN (15 characters, correct format).'));
        }
    }
}
