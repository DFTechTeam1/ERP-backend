<?php

namespace Modules\Production\Http\Requests\Deals;

use Illuminate\Foundation\Http\FormRequest;

class MoreQuotation extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // quotation
            'quotation' => 'required|array',
            'quotation.main_ballroom' => 'required',
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
