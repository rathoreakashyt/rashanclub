<?php

namespace App\Rules;

use App\Helpers\GstinValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Configuration\Models\State;

/**
 * Validates that the value is a valid Indian GSTIN (regex format)
 * and that the GSTIN state code matches the selected state.
 */
class GstinRuleForState implements ValidationRule
{
    public function __construct(
        protected ?int $stateId = null
    ) {}

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

            return;
        }

        if ($this->stateId) {
            $state = State::find($this->stateId);
            if ($state) {
                $gstinStateCode = GstinValidator::getStateCode($value);
                $expectedStateCode = $state->state_code;
                if ($gstinStateCode !== $expectedStateCode) {
                    $fail(__('The :attribute state code must match the selected state (:state).', [
                        'state' => $state->state_name . ' (' . $state->state_code . ')',
                    ]));
                }
            }
        }
    }
}
