<?php

namespace Modules\Production\Http\Requests\Deals;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class RequestFinalChange extends FormRequest
{
    /**
     * Friendly, optional fields. Only the provided ones are translated into a
     * pending change request. At least one must be present.
     */
    public function rules(): array
    {
        return [
            'name' => 'nullable|string',
            'event_type' => 'nullable|string',
            'note' => 'nullable|string',
            'led_area' => 'nullable|string',
            'led_detail' => 'nullable|array',
            'quotation_note' => 'nullable|string',
            'include_tax' => 'nullable|boolean',
            'with_accommodation' => 'nullable|boolean',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $changeable = ['name', 'event_type', 'note', 'led_area', 'led_detail', 'quotation_note', 'include_tax', 'with_accommodation'];

            $provided = collect($changeable)->filter(fn ($field) => $this->input($field) !== null);

            if ($provided->isEmpty()) {
                $validator->errors()->add('detail_changes', __('notification.noFinalDealChangeProvided'));
            }
        });
    }
}
