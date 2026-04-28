<?php

namespace Modules\Email\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendSlackMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'messageTitle' => ['required', 'string'],
            'title' => ['required', 'string'],
            'sectionBlock' => ['nullable', 'array'],
            'sectionBlock.*.message' => ['required_with:sectionBlock', 'string'],
            'sectionBlock.*.type' => ['nullable', 'string'],
            'contextBlock' => ['nullable', 'array'],
            'contextBlock.*.message' => ['required_with:contextBlock', 'string'],
            'contextBlock.*.type' => ['nullable', 'string'],
        ];
    }
}
