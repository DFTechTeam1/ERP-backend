<?php

namespace Modules\Inventory\Http\Requests\UserInventory;

use Illuminate\Foundation\Http\FormRequest;

class DeleteInventory extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'employee_id' => 'required',
            'inventory_id' => 'required',
            'type' => 'required'
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
