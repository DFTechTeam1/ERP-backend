<?php

namespace Modules\Company\Http\Requests\NotificationTemplate;

use Illuminate\Foundation\Http\FormRequest;

class Preview extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'template' => ['required', 'string'],
            'template_html' => ['nullable', 'string'],
            'data' => ['nullable', 'array'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
