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
            'points.*.point' => 'required|integer|min:0',
            'points.*.additional_point' => 'integer|min:0',
            'points.*.calculated_prorate_point' => 'required|integer|min:0',
            'points.*.original_point' => 'required|integer|min:0',
            'points.*.prorate_point' => 'required|integer|min:0',
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
