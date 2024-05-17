<?php

namespace Modules\Inventory\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class Create extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                Rule::unique('inventories', 'name'),
            ],
            'inventory_code' => 'nullable',
            'item_type' => 'required',
            'brand_id' => 'required',
            'unit_id' => 'nullable',
            'supplier_id' => 'nullable',
            'unit_id' => 'nullable',
            'description' => 'required',
            'stock' => 'nullable',
            'warranty' => 'nullable',
            'year_of_purchase' => 'nullable',
            'purchase_price' => 'nullable',
            'item_locations' => 'nullable',
            'status' => 'nullable',
            'images.*' => [
                'nullable',
                File::types(['jpeg', 'jpg', 'png', 'webp'])
                    ->max(2048)
            ],
            'status' => 'nullable',
            'current_images' => 'nullable',
        ];
    }

    /**
     * Handle a passed validation attempt.
     * This will only show when we use ->all() method in the controller
     * ->validated() will not work
     */
    protected function passedValidation(): void
    {
        $price = $this->purchase_price;
        if ($price) {
            $price = str_replace(',', '', $price);
            $this->merge(['purchase_price' => $price]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
