<?php

namespace Modules\Hrd\Http\Requests\Employee;

use App\Enums\Employee\LevelStaff;
use App\Rules\Employee\BossRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployment extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'branch_id' => 'required',
            'employee_id' => [
                'required',
                Rule::unique('employees', 'employee_id')->ignore($this->route('employeeUid'), 'uid'),
            ],
            'position_id' => 'required',
            'level_staff' => [
                'required',
                Rule::enum(LevelStaff::class),
            ],
            'status' => 'required',
            'join_date' => 'required',
            'boss_id' => [
                new BossRule,
            ],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
