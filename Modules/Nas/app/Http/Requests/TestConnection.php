<?php

namespace Modules\Nas\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TestConnection extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'server' => 'required',
            'folder' => 'required',
            'user' => 'required',
            'password' => 'required',
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
