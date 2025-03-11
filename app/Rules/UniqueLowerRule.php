<?php

namespace App\Rules;

use App\Enums\Employee\Status;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class UniqueLowerRule implements ValidationRule
{
    private $model;

    private $uid;

    private $column;

    private $justId;

    public function __construct($model, string $uid = '', string $column = 'name', bool $justId = false)
    {
        $this->model = $model;

        $this->uid = $uid;

        $this->column = $column;

        $this->justId = $justId;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        logging('uid', [$this->uid]);

        $column = $this->column;

        $query = $this->model->query();

        if ($this->justId) {
            $query->where('id', '!=', $this->uid);
        } else {
            if (!empty($this->uid)) {
                $query->where('uid', '!=', $this->uid);
            }
        }
        $query->where('status', '!=', Status::Deleted->value);
        $query->whereRaw("lower({$column}) = '" . strtolower($value) . "'");

        if ($query->count() > 0) {
            $fail('global.attributeAlreadyExists')->translate();
        }
    }
}
