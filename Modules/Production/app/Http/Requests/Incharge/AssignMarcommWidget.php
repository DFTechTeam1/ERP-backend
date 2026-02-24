<?php

namespace Modules\Production\Http\Requests\Incharge;

use Illuminate\Foundation\Http\FormRequest;

class AssignMarcommWidget extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'email' => 'nullable|string|email',
            'password' => 'required|string',
            'assignments' => 'required|array',
            'assignments.*.project_id' => 'required|string',
            'assignments.*.member_ids' => 'nullable|array',
            'assignments.*.member_ids.*' => 'nullable|string',
            'assignments.*.after_party_member_ids' => 'nullable|array',
            'assignments.*.after_party_member_ids.*' => 'nullable|string',
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
