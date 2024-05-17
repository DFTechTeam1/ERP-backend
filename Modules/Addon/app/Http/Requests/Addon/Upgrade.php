<?php

namespace Modules\Addon\Http\Requests\Addon;

use Illuminate\Foundation\Http\FormRequest;

class Upgrade extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'main_file' => 'required',
            'tutorial_video' => 'nullable',
            'preview_image' => 'nullable',
            'improvements' => 'nullable',
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
