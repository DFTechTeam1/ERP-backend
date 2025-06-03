<?php

namespace App\Rules\Employee;

use App\Enums\Employee\Status;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class InactiveEmployeeRule implements ValidationRule
{
    private $model;

    private $uid;

    public function __construct($model, string $uid = '')
    {
        $this->model = $model;

        $this->uid = $uid;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $query = $this->model->query();

        if (! empty($this->uid)) {
            $query->where('uid', '!=', $this->uid);
        }

        if ($attribute == 'name') {
            $query->where(DB::raw('lower(name)'), strtolower($value));
        } elseif ($attribute == 'email') {
            $query->where('email', $value);
        } elseif ($attribute == 'nik') {
            $query->where('nik', $value);
        }

        $query->where('status', '!=', Status::Inactive->value);

        if ($query->count() > 0) {
            $fail('global.attributeAlreadyExists')->translate();
        }
    }
}
