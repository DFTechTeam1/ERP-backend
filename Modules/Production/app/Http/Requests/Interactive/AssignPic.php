<?php

namespace Modules\Production\Http\Requests\Interactive;

use Illuminate\Foundation\Http\FormRequest;

class AssignPic extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'pics' => 'array|required',
            'pics.*.employee_uid' => 'required|string',
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
