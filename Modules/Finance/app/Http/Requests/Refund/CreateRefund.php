<?php

namespace Modules\Finance\Http\Requests\Refund;

use Illuminate\Foundation\Http\FormRequest;

class CreateRefund extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'refund_type' => 'required|string|in:fixed,percentage',
            'refund_amount' => 'required|numeric|min:0',
            'refund_percentage' => 'required_if:refund_type,percentage|min:0|max:100',
            'refund_reason' => 'nullable|string|max:255',
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
