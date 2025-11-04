<?php

namespace Modules\Development\Http\Requests\DevelopmentProject\Task;

use Illuminate\Foundation\Http\FormRequest;

class ReviseTask extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'reason' => 'required|string',
            'images' => 'required|array',
            'images.*.image' => 'required|mimes:jpg,png,jpeg,webp',
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
