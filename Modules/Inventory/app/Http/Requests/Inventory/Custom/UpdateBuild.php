<?php

namespace Modules\Inventory\Http\Requests\Inventory\Custom;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBuild extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'inventories' => 'required|array',
            'removed_items' => 'nullable|array',
            'type' => 'required',
            'default_request_item' => 'required',
            'name' => [
                new \App\Rules\UniqueLowerRule(new \Modules\Inventory\Models\CustomInventory(), $this->route('uid')),
                'required'
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
