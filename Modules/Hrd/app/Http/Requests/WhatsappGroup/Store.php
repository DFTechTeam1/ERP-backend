<?php

namespace Modules\Hrd\Http\Requests\WhatsappGroup;

use App\Enums\Whatsapp\GroupTargetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class Store extends FormRequest
{
    public function rules(): array
    {
        return [
            'group_name' => 'required|string|max:255',
            'community_id' => 'required|string',
            'target_type' => [
                'required',
                Rule::enum(GroupTargetType::class),
            ],
            'employee_uid' => [
                'nullable',
                'required_if:target_type,team',
                Rule::exists('employees', 'uid'),
            ],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
