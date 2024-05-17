<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class UniqueLowerRule implements ValidationRule
{
    private $model;

    private $uid;

    private $column;

    public function __construct($model, string $uid = '', string $column = 'name')
    {
        $this->model = $model;

        $this->uid = $uid;

        $this->column = $column;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $column = $this->column;

        $query = $this->model->query();
        if (!empty($this->uid)) {
            $query->where('uid', '!=', $this->uid);
        }
        $query->where(DB::raw("lower({$column})"), strtolower($value));

        if ($query->count() > 0) {
            $fail('global.attributeAlreadyExists')->translate();
        }
    }
}
