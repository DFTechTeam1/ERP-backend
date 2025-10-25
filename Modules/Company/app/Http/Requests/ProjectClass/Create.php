<?php

namespace Modules\Company\Http\Requests\ProjectClass;

use Illuminate\Foundation\Http\FormRequest;

class Create extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|unique:project_classes,name',
            'maximal_point' => 'required',
            'color' => 'required',
            'base_point' => 'required|integer|min:0',
            'point_2_team' => 'required|integer|min:0',
            'point_3_team' => 'required|integer|min:0',
            'point_4_team' => 'required|integer|min:0',
            'point_5_team' => 'required|integer|min:0',
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
