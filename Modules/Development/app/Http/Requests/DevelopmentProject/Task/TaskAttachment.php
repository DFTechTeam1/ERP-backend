<?php

namespace Modules\Development\Http\Requests\DevelopmentProject\Task;

use Illuminate\Foundation\Http\FormRequest;

class TaskAttachment extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'images' => 'nullable|array',
            'images.*.image' => 'file|mimes:jpeg,png,jpg,gif,webp|max:2048',
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
