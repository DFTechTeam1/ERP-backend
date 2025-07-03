<?php

namespace Modules\Company\Http\Requests\ProjectClass;

use App\Rules\UniqueLowerRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Company\Models\ProjectClass;

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
                new UniqueLowerRule(new ProjectClass, $this->route('projectClass'), 'name', true),
            ],
            'maximal_point' => 'required',
            'color' => 'required',
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
