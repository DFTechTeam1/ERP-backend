<?php

namespace App\Http\Requests;

use App\Enums\ErrorCode\Code;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class FormInteractiveRequest extends FormRequest
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
                'unique:form_interactives,name',
            ],
            'forms.*.name' => 'required',
            'image' => 'nullable',
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
