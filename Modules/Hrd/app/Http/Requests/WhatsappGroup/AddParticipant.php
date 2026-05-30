<?php

namespace Modules\Hrd\Http\Requests\WhatsappGroup;

use Illuminate\Foundation\Http\FormRequest;

class AddParticipant extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'employee_uid' => 'required|string',
            'is_admin' => 'sometimes|boolean',
        ];
    }
}
