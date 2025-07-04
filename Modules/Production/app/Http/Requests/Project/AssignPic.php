<?php

namespace Modules\Production\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class AssignPic extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'pics' => 'required|array',
            'pics.*' => 'required|string',
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
