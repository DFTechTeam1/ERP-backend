<?php

namespace Modules\Company\Http\Requests;

use App\Rules\UniqueLowerRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Company\Models\Position;
use Modules\Company\Models\PositionBackup;

class PositionCreateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                new UniqueLowerRule(new PositionBackup())
            ],
            'division_id' => 'required',
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
