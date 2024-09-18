<?php

namespace Modules\Production\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class StoreReferences extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'files.*.path' => 'nullable',
            'link.*.href' => 'nullable',
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
