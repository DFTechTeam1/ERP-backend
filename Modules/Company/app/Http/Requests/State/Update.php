<?php

namespace Modules\Company\Http\Requests\State;

use Illuminate\Foundation\Http\FormRequest;

class Update extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:191|unique:states,name,' . $this->route('stateId') . ',id',
            'country_id' => 'required|integer|exists:countries,id',
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
