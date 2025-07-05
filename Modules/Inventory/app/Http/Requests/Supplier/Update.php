<?php

namespace Modules\Inventory\Http\Requests\Supplier;

use App\Rules\UniqueLowerRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Inventory\Models\Supplier;

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
                new UniqueLowerRule(new Supplier, $this->route('supplier')),
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
