<?php

namespace Modules\Company\Http\Requests\Branch;

use Illuminate\Foundation\Http\FormRequest;

class Create extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|unique:branches',
            'short_name' => 'required|unique:branches',
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
