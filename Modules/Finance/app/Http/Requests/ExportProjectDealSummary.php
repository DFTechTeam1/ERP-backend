<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportProjectDealSummary extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'date' => 'nullable',
            'marketings' => 'array|nullable',
            'marketings.*.id' => 'nullable',
            'marketings.*.name' => 'nullable',
            'status' => 'array|nullable',
            'status.*.id' => 'nullable',
            'status.*.name' => 'nullable',
            'price' => 'array|nullable',
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
