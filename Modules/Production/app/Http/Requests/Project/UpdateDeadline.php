<?php

namespace Modules\Production\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeadline extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'end_date' => 'required',
            'type' => 'required|in:add,update',
            'reason_id' => 'required_if:type,update',
            'custom_reason' => 'string|required_if:reason_id,0'
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
