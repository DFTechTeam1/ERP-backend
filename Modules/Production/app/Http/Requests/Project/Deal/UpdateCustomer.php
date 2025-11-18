<?php

namespace Modules\Production\Http\Requests\Project\Deal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomer extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                Rule::unique(table: 'customers', column: 'name')->ignore(
                    id: $this->route('customer'),
                    idColumn: 'id'
                ),
            ],
            'phone' => 'required',
            'email' => 'nullable',
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
