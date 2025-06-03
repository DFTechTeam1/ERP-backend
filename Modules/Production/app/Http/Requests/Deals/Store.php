<?php

namespace Modules\Production\Http\Requests\Deals;

use Illuminate\Foundation\Http\FormRequest;

class Store extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // project detail data
            'name' => 'required',
            'project_date' => 'required|date_format:Y-m-d',
            'customer_id' => 'required',
            'event_type' => 'required',
            'venue' => 'required',
            'collaboration' => 'nullable',
            'note' => 'nullable',
            'led_area' => 'nullable',
            'led_detail' => 'nullable',
            'country_id' => 'required',
            'state_id' => 'required',
            'city_id' => 'required',
            'project_class_id' => 'required',
            'longitude' => 'nullable',
            'latitude' => 'nullable',
            'equipment_type' => 'string|required',
            'is_high_season' => 'required',

            'client_portal' => 'required',
            'marketing_id' => 'nullable|array',
            'marketing_id.*' => 'string',
            'status' => 'nullable',

            // quotation
            'quotation' => 'required|array',
            'quotation.main_ballroom' => 'required',
            'quotation.prefunction' => 'required',
            'quotation.prefunction' => 'required',
            'quotation.high_season_fee' => 'required',
            'quotation.equipment_fee' => 'required',
            'quotation.sub_total' => 'required',
            'quotation.maximum_discount' => 'required',
            'quotation.total' => 'required',
            'quotation.maximum_markup_price' => 'required',
            'quotation.fix_price' => 'required',
            'quotation.quotation_id' => 'required',
            'quotation.is_final' => 'required|boolean',
            'quotation.event_location_guide' => 'required',
            'quotation.equipment_type' => 'nullable',
            'quotation.is_high_season' => 'required',
            'quotation.description' => 'nullable',

            'quotation.items' => 'required|array',
            'quotation.items.*' => 'required',

            'request_type' => 'nullable', // this will be save,draft or save_and_download
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
