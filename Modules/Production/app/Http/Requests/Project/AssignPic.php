<?php

namespace Modules\Production\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class AssignPic extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'pics' => 'required|array',
            'pics.*' => 'required|string',
            // Optional: employee uid of the PM to flag as Lead (largest share of the PM reward
            // pot). When omitted, no PIC is flagged and the Lead falls back to the earliest one.
            'lead' => 'nullable|string',
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
