<?php

namespace Modules\Production\Http\Requests\Incharge;

use Illuminate\Foundation\Http\FormRequest;

class AssignEntertainmentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // 'entertainment_vj_uids' => 'nullable|array',
            // 'entertainment_vj_uids.*' => 'string',
            // 'entertainment_vj_note' => 'string|nullable',
            // 'entertainment_afpat_uids' => 'nullable|array',
            // 'entertainment_afpat_uids.*' => 'string',
            // 'entertainment_afpat_note' => 'string|nullable',
            // 'has_after_party' => 'boolean',

            'remove_main_event_uids' => 'nullable|array',
            'remove_main_event_uids.*' => 'string',
            'remove_after_party_uids' => 'nullable|array',
            'remove_after_party_uids.*' => 'string',
            'assign_main_event_uids' => 'nullable|array',
            'assign_main_event_uids.*' => 'string',
            'assign_after_party_uids' => 'nullable|array',
            'assign_after_party_uids.*' => 'string',
            'main_event_note' => 'string|nullable',
            'after_party_note' => 'string|nullable',
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
