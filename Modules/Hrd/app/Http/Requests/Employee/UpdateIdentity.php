<?php

namespace Modules\Hrd\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIdentity extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'id_number' => 'required',
            // 'npwp_number' => 'nullable',
            // 'bpjs_ketenagakerjaan_number' => 'nullable',
            'address' => 'required',
            'current_address' => 'required_if:is_residence_same,1',
            'is_residence_same' => 'nullable',
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
