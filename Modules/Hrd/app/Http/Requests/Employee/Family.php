<?php

namespace Modules\Hrd\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class Family extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'id_number' => 'required',
            'name' => 'required',
            'date_of_birth' => 'nullable',
            'relation' => 'required',
            'gender' => 'nullable',
            'job' => 'nullable',
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
