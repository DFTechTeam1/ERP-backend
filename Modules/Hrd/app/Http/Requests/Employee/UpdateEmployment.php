<?php

namespace Modules\Hrd\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateEmployment extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'position_id' => 'required',
            'boss_id' => 'nullable',
            'status' => 'required',
            'level' => 'required',
            'employee_id' => [
                'required',
                Rule::unique('employees', 'employee_id')->ignore($this->route('employeeUid')),
            ],
            'join_date' => 'required',
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
