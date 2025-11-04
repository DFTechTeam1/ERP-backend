<?php

namespace Modules\Production\Http\Requests\Interactive\Task;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskDeadline extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'end_date' => 'required',
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
