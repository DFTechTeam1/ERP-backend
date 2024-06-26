<?php

namespace Modules\Production\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Production\Rules\ChangeBoardRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Enums\ErrorCode\Code;

class ManualChangeTaskBoard extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'board_id' => [
                'required',
                new ChangeBoardRule
            ],
            'task_id' => 'nullable',
            'board_source_id' => 'nullable',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
