<?php

namespace Modules\Company\Http\Requests\Country;

use Illuminate\Foundation\Http\FormRequest;

class Create extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:countries,name',
            'iso3' => 'required|string|max:3|unique:countries,iso3',
            'iso2' => 'required|string|max:3|unique:countries,iso2',
            'phone_code' => 'required|string|max:10',
            'currency' => 'required|string|max:10',
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
