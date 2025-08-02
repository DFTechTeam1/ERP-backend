<?php

namespace Modules\Addon\Http\Requests\Addon;

use Illuminate\Foundation\Http\FormRequest;

class Create extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'addon_file' => 'required',
            'tutorial_video' => 'nullable',
            'preview_image' => 'required',
            'name' => 'required',
            'description' => 'required',
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
