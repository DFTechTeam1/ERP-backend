<?php

namespace App\Rules\Employee;

use App\Enums\Employee\LevelStaff;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;

class BossRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (request()->level != 'manager' && !$value) {
            $fail('global.bossIdRequired')->translate();
        }
    }
}
