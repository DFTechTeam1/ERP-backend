<?php

namespace Modules\Company\Http\Requests\NotificationTemplate;

use App\Services\NotificationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class Update extends FormRequest
{
    /**
     * `action` is intentionally absent: it is the integration key senders look up,
     * so it is immutable after create. Any `action` sent by the client is ignored
     * (validated() will not include it).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
            'notification_channel.*.in' => 'Each channel must be one of: '.implode(', ', NotificationService::ALL_CHANNELS).'.',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
