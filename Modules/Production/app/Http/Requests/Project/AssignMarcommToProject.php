<?php

namespace Modules\Production\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class AssignMarcommToProject extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'marcomm_ids' => 'array|required',
            'marcomm_ids.*.employee_uid' => 'string|required|exists:employees,uid',
            'remove_ids' => 'array|nullable',
            'remove_ids.*.employee_uid' => 'string|required|exists:employees,uid',
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
