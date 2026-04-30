<?php

namespace Modules\Email\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Email\Enums\EmailType;

class SendEmailRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'emailType' => Rule::enum(EmailType::class),
            'recipientEmail' => 'required',
        ];

        $additionalRules = EmailType::injectTypeValidator($this->request->get('emailType'));

        return array_merge($rules, $additionalRules);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
