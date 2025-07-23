<?php

namespace Modules\Finance\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Finance\Rules\EditInvoiceAmountRule;

class EditInvoice extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'amount' => [
                'nullable',
                new EditInvoiceAmountRule()
            ],
            'payment_date' => 'nullable|date_format:Y-m-d',
            'invoice_uid' => 'required'
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
