<?php

namespace Modules\Inventory\Http\Requests\RequestInventory;

use Illuminate\Foundation\Http\FormRequest;

class ConvertToInventory extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'brand_id' => 'required',
            'item_locations' => 'nullable',
            'item_type' => 'required',
            'unit_id' => 'nullable',
            'warehouse_id' => 'required',
            'images.*' => [
                'nullable',
                'string'
            ],
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
