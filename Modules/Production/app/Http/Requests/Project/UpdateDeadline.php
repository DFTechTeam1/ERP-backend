<?php

namespace Modules\Production\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeadline extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'task_id' => 'required',
            'due_date' => [
                'required',
                Rule::date()->format('Y-m-d H:i')
            ],
            'reason_id' => 'required',
            'reason_custom' => [
                Rule::requiredIf(fn() => request('reason_id') == 'custom')
            ]
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
