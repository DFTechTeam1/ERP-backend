<?php

namespace Modules\Company\Http\Requests;

use App\Rules\UniqueLowerRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Company\Models\Division;

class DivisionUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => [
                Rule::unique('divisions')->ignore(request('uid'),'uid'),
                new UniqueLowerRule(new Division(), request('uid')),
            ],
            'parent_id' => 'nullable'
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
