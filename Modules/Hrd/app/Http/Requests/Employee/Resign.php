<?php

namespace Modules\Hrd\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class Resign extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'reason' => 'required',
            'resign_date' => 'date_format:Y-m-d|required',
            'severance' => 'required',
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
