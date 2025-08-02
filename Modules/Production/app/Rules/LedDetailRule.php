<?php

namespace Modules\Production\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class LedDetailRule implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void {}
}
