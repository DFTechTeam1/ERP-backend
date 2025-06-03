<?php

namespace Modules\Inventory\Http\Requests\Inventory;

use App\Rules\UniqueLowerRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Inventory\Models\Inventory;

class Update extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                new UniqueLowerRule(new Inventory, $this->route('inventory')),
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
                'string',
            ],
            'status' => 'nullable',
            'current_images' => 'nullable',
            'deleted_item_stock' => 'nullable',
            'deleted_images' => 'nullable',
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
