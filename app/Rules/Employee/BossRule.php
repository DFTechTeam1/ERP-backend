<?php

namespace App\Rules\Employee;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BossRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (request()->level_staff != 'manager' && ! $value) {
            $fail('global.bossIdRequired')->translate();
        }
    }
}
