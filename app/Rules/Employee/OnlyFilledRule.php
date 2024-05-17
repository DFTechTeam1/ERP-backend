<?php

namespace App\Rules\Employee;

use App\Enums\Employee\ProbationStatus;
use App\Enums\Employee\Status;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class OnlyFilledRule implements ValidationRule
{
    private $model;
    private $uid;
    private $field;

    public function __construct($model, string $uid = '', string $field = '')
    {
        $this->model = $model;
        $this->uid = $uid;
        $this->field = $field;
    }


    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if(empty($this->uid)) {
            if($attribute == 'probation_status' || $attribute == 'start_review_probation' || $attribute == 'end_probation_date') {
                if ($this->field != Status::Probation->value) {
                    $fail('only fill :attribute when employee status is probation');
                }
            }
        } else {
            $employee = $this->model->where('uid', $this->uid)->first();
            if(empty($employee)) {
                $fail('Employee not found!');
            }
        }

        if($attribute == 'exit_date' || $attribute == 'resign_notes') {
            if ($this->field != Status::Inactive->value) {
                $fail('only fill :attribute when employee status is inactive');
            }
        }

        // contract duration hanya bisa diisi ketika status contract, part time, freelance, or internship
        if($attribute == 'contract_duration') {
            if ($this->field == Status::Permanent->value OR $this->field == Status::Probation->value) {
                $fail('only fill :attribute when employee status is contract, part time, freelance, or internship');
            }
        }
    }
}
