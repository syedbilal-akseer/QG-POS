<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!preg_match('/[A-Z]/', $value)) {
            $fail('The :attribute must contain at least one uppercase letter.');
        }

        if (!preg_match('/\d/', $value)) {
            $fail('The :attribute must contain at least one numeric digit.');
        }

        if (!preg_match('/[\W_]/', $value)) {
            $fail('The :attribute must contain at least one special character (e.g., @, #, $, etc.).');
        }
    }
}
