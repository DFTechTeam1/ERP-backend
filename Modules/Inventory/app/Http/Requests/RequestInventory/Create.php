<?php

namespace Modules\Inventory\Http\Requests\RequestInventory;

use Illuminate\Foundation\Http\FormRequest;

class Create extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'approval_target' => 'nullable|array',
            'items' => 'required|array',
            'items.*.name' => 'required',
            'items.*.description' => 'nullable',
            'items.*.price' => 'nullable',
            'items.*.quantity' => 'required',
            'items.*.purchase_source' => 'nullable',
            'items.*.purchase_link' => 'nullable|array',
            'items.*.store_name' => 'nullable'
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
