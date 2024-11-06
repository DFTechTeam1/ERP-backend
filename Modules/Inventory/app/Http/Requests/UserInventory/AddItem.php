<?php

namespace Modules\Inventory\Http\Requests\UserInventory;

use Illuminate\Foundation\Http\FormRequest;

class AddItem extends FormRequest
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
