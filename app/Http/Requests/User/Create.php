<?php

namespace App\Http\Requests\User;

use App\Enums\ErrorCode\Code;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class Create extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_external_user' => 'boolean',
            'email' => [
                'required_if:is_external_user,1',
                Rule::unique('users', 'email'),
            ],
            'employee_id' => 'required_if:is_external_user,0',
            'password' => 'required',
            'role_id' => 'required',
        ];
    }

    /**
     * Return validation errors as json
     *
     * @param Validator $validator
     * @return void
     */
    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            apiResponse(
                generalResponse(
                    errorMessage(__('global.validationCheckFailed')),
                    true,
                    $validator->errors()->toArray(),
                    Code::ValidationError->value,
                ),
            ),
        );
    }
}
