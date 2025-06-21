<?php

namespace App\Http\Requests\Roles;

use App\Enums\ErrorCode\Code;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class Update extends FormRequest
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
            'name' => [
                'required',
                Rule::unique('roles', 'name')->ignore($this->route('role')),
            ],
            'permissions' => 'required',
        ];
    }

    /**
     * Return validation errors as json
     *
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
                    Code::BadRequest->value,
                ),
            ),
        );
    }
}
