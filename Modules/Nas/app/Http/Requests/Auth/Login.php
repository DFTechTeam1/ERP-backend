<?php

namespace Modules\Nas\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class Login extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'version' => 'required',
            'account' => 'required',
            'passwd' => 'required',
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
