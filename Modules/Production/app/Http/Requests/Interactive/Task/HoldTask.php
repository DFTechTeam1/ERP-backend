<?php

namespace Modules\Production\Http\Requests\Interactive\Task;

use Illuminate\Foundation\Http\FormRequest;

class HoldTask extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'reason' => 'required|string',
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
