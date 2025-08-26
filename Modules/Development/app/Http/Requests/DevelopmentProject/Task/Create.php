<?php

namespace Modules\Development\Http\Requests\DevelopmentProject\Task;

use Illuminate\Foundation\Http\FormRequest;

class Create extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // basic information
            'name' => 'string|required',
            'description' => 'string|nullable',
            'board_id' => 'required',
            
            // attachments
            'images' => 'nullable|array',
            'images.*.image' => 'file|mimes:jpeg,png,jpg,gif,webp|max:2048',

            // pics
            'pics' => 'nullable|array',
            'pics.*.employee_uid' => 'nullable|string',

            // deadlines
            'end_date' => 'string|nullable',
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
