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
            'points' => 'required',
            'points.*.uid' => 'required',
            'points.*.point' => 'required',
            'points.*.additional_point' => 'nullable',
            'points.*.tasks' => 'required|array',
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
