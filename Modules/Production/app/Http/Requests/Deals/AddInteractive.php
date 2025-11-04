<?php

namespace Modules\Production\Http\Requests\Deals;

use Illuminate\Foundation\Http\FormRequest;

class AddInteractive extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'interactive_detail' => 'required|array',
            'interactive_detail.*.name' => 'required|string',
            'interactive_detail.*.textDetail' => 'required|string',
            'interactive_detail.*.total' => 'required|string',
            'interactive_detail.*.totalRaw' => 'required|string',
            'interactive_detail.*.led' => 'required|array',
            'interactive_detail.*.led.*.height' => 'required|string',
            'interactive_detail.*.led.*.width' => 'required|string',
            'interactive_area' => 'required',
            'interactive_note' => 'nullable|string',
            'interactive_fee' => 'required|numeric',
            'fix_price' => 'nullable',
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
