<?php

namespace Modules\Production\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadPicRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'current_pic_id' => 'required',
            'new_pic_id' => 'required',
            'project_name' => 'required|string'
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
