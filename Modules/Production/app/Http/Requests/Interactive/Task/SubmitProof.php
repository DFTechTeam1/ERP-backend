<?php

namespace Modules\Production\Http\Requests\Interactive\Task;

use Illuminate\Foundation\Http\FormRequest;

class SubmitProof extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'images' => 'array|required',
            'images.*image' => 'required|mimes:jpg,png,webp,jpeg',
            'nas_path' => 'required',
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
