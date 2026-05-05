<?php

namespace Modules\Production\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class CreatePoolTask extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'end_date' => 'nullable|date',
            'description' => 'nullable|string',
            'pic' => 'nullable|array',
            'pic.*' => 'nullable|string',
            'media' => 'nullable|array',
            'media.*' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf',
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
