<?php

namespace Modules\Company\Http\Requests\Branch;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Company\Models\Branch;

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
                new \App\Rules\UniqueLowerRule(model: new Branch, uid: $this->route('branch'), column: 'name', justId: true),
            ],
            'short_name' => [
                'required',
                new \App\Rules\UniqueLowerRule(model: new Branch, uid: $this->route('branch'), column: 'short_name', justId: true),
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
