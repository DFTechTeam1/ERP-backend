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
            'name' => 'required',
            'relationship' => 'required',
            'address' => 'nullable',
            'id_number' => 'nullable',
            'gender' => 'required',
            'date_of_birth' => 'required',
            'religion' => 'nullable',
            'martial_status' => 'nullable',
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
