<?php

namespace Modules\Production\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class Create extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required',
            'client_portal' => 'required',
            'project_date' => 'required',
            'event_type' => 'required',
            'venue' => 'required',
            'marketing_id' => 'required',
            'collaboration' => 'nullable',
            'note' => 'nullable',
            'classification' => 'required',
            'pic' => 'required',
            'led_area' => 'required',
            'led' => 'required',
            'status' => 'required',
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
