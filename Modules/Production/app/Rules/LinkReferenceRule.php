<?php

namespace Modules\Production\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class LinkReferenceRule implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        [,$index] = explode('.', $attribute);

        if (! empty(request("link.{$index}.href")) && empty($value)) {
            $fail('validation.referenceNameRequired')->translate();
        }
    }
}
