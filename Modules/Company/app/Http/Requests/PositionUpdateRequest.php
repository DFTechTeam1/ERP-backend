<?php

namespace Modules\Company\Http\Requests;

use App\Rules\UniqueLowerRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Company\Models\Position;

class PositionUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => [
                Rule::unique('positions')->ignore(request('uid'),'uid'),
                new UniqueLowerRule(new Position(), request('uid')),
            ],
            'division_id' => 'nullable'
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
