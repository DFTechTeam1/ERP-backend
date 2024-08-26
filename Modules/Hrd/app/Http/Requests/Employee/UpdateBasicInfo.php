<?php

namespace Modules\Hrd\Http\Requests\Employee;

use App\Enums\Employee\Gender;
use App\Enums\Employee\MartialStatus;
use App\Enums\Employee\Religion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Hrd\Models\Employee;

class UpdateBasicInfo extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required',
            'nickname' => 'required',
            'email' => [
                'required',
                new \App\Rules\UniqueLowerRule(new Employee(), $this->route('uid'), 'email'),
            ],
            'phone' => 'required',
            'religion' => [
                'required',
                Rule::enum(Religion::class),
            ],
            'martial_status' => [
                'required',
                Rule::enum(MartialStatus::class),
            ],
            'blood_type' => 'nullable',
            'date_of_birth' => 'required',
            'place_of_birth' => 'required',
            'gender' => [
                'required',
                Rule::enum(Gender::class),
            ],
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
