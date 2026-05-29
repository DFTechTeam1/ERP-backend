<?php

namespace Modules\Hrd\Http\Requests\WhatsappGroup;

use App\Enums\Whatsapp\GroupTargetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class Update extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('whatsapp_group');

        return [
            'group_name' => 'required|string|max:255',
            'group_id' => [
                'required',
                'string',
                Rule::unique('whatsapp_groups', 'group_id')->ignore($id),
            ],
            'invitation_link' => 'required|string',
            'target_type' => [
                'required',
                Rule::enum(GroupTargetType::class),
            ],
            'employee_id' => [
                'nullable',
                'required_if:target_type,team',
                Rule::exists('employees', 'id'),
            ],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
