<?php

namespace Modules\Production\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class RequestEquipment extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'items.*.inventory_id' => 'required',
            'items.*.qty' => 'required',
            'items.*.item_type' => 'required',
            'items.*.inventories' => 'nullable|array',
            'equipments' => 'array',
            'equipments.*.type' => 'required|string',
            'equipments.*.inventory_id' => 'required|string',
            'equipments.*.qty' => 'required|string',
            'equipments.*.inventory_ids' => 'array',
            'type' => 'required|string'
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
