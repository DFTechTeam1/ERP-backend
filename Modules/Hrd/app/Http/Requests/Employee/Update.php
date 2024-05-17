<?php

namespace Modules\Hrd\Http\Requests\Employee;

use App\Enums\Employee\Education;
use App\Enums\Employee\Gender;
use App\Enums\Employee\LevelStaff;
use App\Enums\Employee\MaritalStatus;
use App\Enums\Employee\ProbationStatus;
use App\Enums\Employee\Religion;
use App\Enums\Employee\MartialStatus;
use App\Enums\Employee\Status;
use App\Rules\Employee\BossRule;
use App\Rules\Employee\InactiveEmployeeRule;
use App\Rules\Employee\OnlyFilledRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Modules\Hrd\Models\Employee;

class Update extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required',
            'nickname' => 'required',
            'employee_id' => [
                'required',
                new \App\Rules\UniqueLowerRule(new Employee(), $this->route('uid'), 'employee_id'),
            ],
            'email' => [
                'required',
                new \App\Rules\UniqueLowerRule(new Employee(), $this->route('uid'), 'email'),
            ],
            'phone' => 'required',
            'id_number' => [
                'required',
                'max:16',
                'min:16',
                new \App\Rules\UniqueLowerRule(new Employee(), $this->route('uid'), 'id_number'),
            ],
            'religion' => [
                'required',
                Rule::enum(Religion::class),
            ],
            'martial_status' => [
                'required',
                Rule::enum(MartialStatus::class),
            ],
            'address' => 'required',
            'province_id' => 'nullable',
            'city_id' => 'nullable',
            'district_id' => 'nullable',
            'village_id' => 'nullable',
            'postal_code' => 'required',
            'is_residence_same' => 'nullable',
            'current_address' => 'required_if:is_residence_same,1',
            'blood_type' => 'nullable',
            'date_of_birth' => 'required',
            'place_of_birth' => 'required',
            'dependents' => 'nullable',
            'gender' => [
                'required',
                Rule::enum(Gender::class),
            ],
            'banks' => 'nullable',
            'educations.education' => 'required',
            'educations.education_name' => 'required',
            'educations.graduation_year' => 'required',
            'educations.education_major' => 'required',
            'relation.name' => 'required',
            'relation.phone' => 'required',
            'position_id' => 'required',
            'level' => [
                'required',
                Rule::enum(LevelStaff::class),
            ],
            'boss_id' => [
                new BossRule(),
            ],
            'status' => [
                'required',
                Rule::enum(Status::class),
            ],
            'placement' => 'required',
            'join_date' => 'required',
            'start_review_date' => 'nullable',
            'end_probation_date' => 'nullable',
            'company' => 'required',
            'id_number_photo' => [
                'nullable',
                File::types(['jpeg', 'jpg', 'png', 'webp'])
            ],
            'npwp_photo' => [
                'nullable',
                File::types(['jpeg', 'jpg', 'png', 'webp'])
            ],
            'bpjs_photo' => [
                'nullable',
                File::types(['jpeg', 'jpg', 'png', 'webp'])
            ],
            'kk_photo' => [
                'nullable',
                File::types(['jpeg', 'jpg', 'png', 'webp'])
            ],
            'deleted_image' => 'nullable',
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
