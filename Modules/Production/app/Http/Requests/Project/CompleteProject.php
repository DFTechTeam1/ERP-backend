<?php

namespace Modules\Production\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class CompleteProject extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'feedback' => 'required',
            'points' => 'nullable',
            'points.*.uid' => 'nullable',
            'points.*.point' => 'nullable',
            'points.*.additional_point' => 'nullable',
            'points.*.tasks' => 'nullable|array',
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
