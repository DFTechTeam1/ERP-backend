<?php

namespace Modules\Development\Http\Requests\DevelopmentProject;

use App\Enums\Development\Project\ReferenceType;
use Illuminate\Foundation\Http\FormRequest;

class Update extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable',
            'project_date' => 'required|date_format:Y-m-d',
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
