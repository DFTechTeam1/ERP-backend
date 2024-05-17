<?php

namespace Modules\Addon\Http\Requests\Addon;

use Illuminate\Foundation\Http\FormRequest;

class AskDeveloper extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required',
            'email' => 'nullable',
            'topic' => 'required',
            'addon' => 'required_if:topic,addon',
            'message' => 'required',
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
