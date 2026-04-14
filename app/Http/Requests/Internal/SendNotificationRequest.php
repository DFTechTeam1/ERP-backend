<?php

namespace App\Http\Requests\Internal;

use App\Enums\ErrorCode\Code;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SendNotificationRequest extends FormRequest
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
            'recipient_email'  => 'required|email',
            'action'           => 'required|string',
            'channels'         => 'required|array|min:1',
            'channels.*'       => 'required|string|in:email,slack,telegram,database',
            'data'             => 'nullable|array',
            'options'          => 'nullable|array',
        ];
    }

    /**
     * Return validation errors as JSON.
     */
    public function failedValidation(Validator $validator): void
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
