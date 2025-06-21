<?php

namespace Modules\Production\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class MoreDetailUpdate extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'client_portal' => 'required',
            'collaboration' => 'nullable',
            'event_type' => 'required',
            'note' => 'nullable',
            'status' => 'nullable',
            'venue' => 'required',
            'country_id' => 'required',
            'state_id' => 'required',
            'city_id' => 'required',
            'led_area' => 'nullable',
            'led_detail' => 'nullable',
            'pic' => 'array|nullable',
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
