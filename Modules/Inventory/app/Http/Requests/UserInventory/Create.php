<?php

namespace Modules\Inventory\Http\Requests\UserInventory;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Inventory\Rules\EmployeeInventory\EmployeeRule;

class Create extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'employee_id' => [
                'required',
                new EmployeeRule()
            ],
            'custom_inventories' => 'nullable',
            'custom_inventories.*.id' => 'nullable',
            'inventories' => 'nullable',
            'inventories.*.id' => 'nullable'
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
