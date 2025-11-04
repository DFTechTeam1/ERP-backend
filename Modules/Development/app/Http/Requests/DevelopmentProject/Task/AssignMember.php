<?php

namespace Modules\Development\Http\Requests\DevelopmentProject\Task;

use Illuminate\Foundation\Http\FormRequest;

class AssignMember extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'removed' => 'array|nullable',
            'users' => 'array|nullable',
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
