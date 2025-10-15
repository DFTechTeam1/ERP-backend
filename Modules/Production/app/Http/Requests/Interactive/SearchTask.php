<?php

namespace Modules\Production\Http\Requests\Interactive;

use Illuminate\Foundation\Http\FormRequest;

class SearchTask extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'my_task' => 'nullable|boolean',
            'search' => 'nullable|string',
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
