<?php

namespace Modules\Hrd\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PerformanceReport extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'start_date' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'end_date' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'all_employee' => 'required|boolean',
            'employee_uids' => [
                'array',
                Rule::requiredIf(request('all_employee') == 0 && empty(request('position_uids'))),
            ],
            'position_uids' => [
                'array',
                Rule::requiredIf(request('all_employee') == 0 && empty(request('employee_uids'))),
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
