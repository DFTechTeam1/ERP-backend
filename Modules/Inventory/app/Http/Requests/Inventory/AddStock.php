<?php

namespace Modules\Inventory\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class AddStock extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'item_locations.*.location' => 'required',
            'item_locations.*.user_id' => 'nullable',
            'item_locations.*.status' => 'nullable',
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
