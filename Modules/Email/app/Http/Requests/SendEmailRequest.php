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
        return [
            'recipientEmail' => 'required',
            'emailType' => Rule::enum(EmailType::class),
            'supervisorName' => 'nullable',
            'employeeName' => 'nullable',
            'oldPosition' => 'nullable',
            'newPosition' => 'nullable',
            'department' => 'nullable',
            'effectiveDate' => 'nullable',
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
