<?php

namespace Modules\Company\Http\Requests\NotificationTemplate;

use App\Services\NotificationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class Create extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'regex:/^[a-z0-9_]+$/', Rule::unique('notification_settings', 'action')],
            'template' => ['required', 'string'],
            'template_html' => ['nullable', 'string'],
            'notification_channel' => ['required', 'array', 'min:1'],
            'notification_channel.*' => ['string', Rule::in(NotificationService::ALL_CHANNELS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.regex' => 'The action may only contain lowercase letters, numbers and underscores.',
            'action.unique' => 'A template with this action already exists.',
            'notification_channel.*.in' => 'Each channel must be one of: '.implode(', ', NotificationService::ALL_CHANNELS).'.',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
