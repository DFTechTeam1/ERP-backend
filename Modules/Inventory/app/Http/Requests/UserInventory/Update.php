<?php

namespace Modules\Inventory\Http\Requests\UserInventory;

use Illuminate\Foundation\Http\FormRequest;

class Update extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'inventories' => 'required',
            'inventories.*.id' => 'required',
            'inventories.*.quantity' => 'required',
            'inventories.*.current_id' => 'nullable',
            'deleted_inventories' => 'nullable',
            'deleted_inventories.*.current_id' => 'nullable',
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
