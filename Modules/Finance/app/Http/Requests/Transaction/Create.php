<?php

namespace Modules\Finance\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class Create extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'payment_amount' => 'required',
            'transaction_date' => 'required|date_format:Y-m-d',
            'invoice_id' => 'required|string',
            'note' => 'nullable',
            'reference' => 'nullable',
            'images' => [
                'nullable',
                'array',
            ],
            'images.*image' => 'image',
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
