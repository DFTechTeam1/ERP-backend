<?php

namespace Modules\Inventory\Http\Requests\RequestInventory;

use Illuminate\Foundation\Http\FormRequest;

class Update extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'approval_target' => 'nullable|array',
            'name' => 'required',
            'description' => 'nullable',
            'price' => 'nullable',
            'quantity' => 'required',
            'purchase_source' => 'nullable',
            'purchase_link' => 'nullable|array',
            'store_name' => 'nullable',
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
