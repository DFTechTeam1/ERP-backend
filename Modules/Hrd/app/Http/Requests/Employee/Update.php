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
        $rules = [
            'name' => 'required',
            'nickname' => 'required',
            'email' => [
                'required',
                new \App\Rules\UniqueLowerRule(new Employee(), $this->route('uid'), 'email'),
            ],
            'date_of_birth' => 'required',
            'place_of_birth' => 'required',
            'martial_status' => [
                'required',
                Rule::enum(MartialStatus::class),
            ],
            'religion' => [
                'required',
                Rule::enum(Religion::class),
            ],
            'phone' => 'required',
            'id_number' => [
                'required',
                'max:16',
                'min:16',
                new \App\Rules\UniqueLowerRule(new Employee(), $this->route('uid'), 'id_number'),
            ],
            'address' => 'required',
            'current_address' => 'required_if:is_residence_same,1',
            'is_residence_same' => 'nullable',
            'position_id' => 'required',
            'employee_id' => [
                'required',
                new \App\Rules\UniqueLowerRule(new Employee(), $this->route('uid'), 'employee_id'),
            ],
            'level_staff' => [
                'nullable',
            ],
            'job_level_id' => [
                'nullable',
            ],
            'boss_id' => [
                new BossRule(),
            ],
            'status' => [
                'required',
                Rule::enum(Status::class),
            ],
            'branch_id' => 'required',
            'join_date' => 'required',
            'gender' => [
                'required',
                Rule::enum(Gender::class),
            ],
            'ptkp_status' => 'required',
            'basic_salary' => 'required',
            'salary_type' => 'required',
            'tax_configuration' => 'required',
            'salary_configuration' => 'required',
            'jht_configuration' => 'required',
            'employee_tax_status' => 'required',
            'jp_configuration' => 'required',
            'overtime_status' => 'required',
            'bpjs_kesehatan_config' => 'required',

            'bpjs_ketenagakerjaan_number' => 'nullable',
            'npwp_number' => 'nullable',

            'province_id' => 'nullable',
            'city_id' => 'nullable',
            'district_id' => 'nullable',
            'village_id' => 'nullable',
            'postal_code' => 'nullable',
            'blood_type' => 'nullable',
            'bank_detail' => 'nullable',
            'education' => 'nullable',
            'education_name' => 'nullable',
            'education_major' => 'nullable',
            'education_year' => 'nullable',
            'relation_contact.name' => 'nullable',
            'relation_contact.phone' => 'nullable',
            'relation_contact.relation' => 'nullable',
            'start_review_date' => 'nullable',
            'end_probation_date' => 'nullable',
            'invite_to_erp' => 'nullable',
            'password' => 'required_if:invite_to_erp,1',
            'invite_to_talenta' => 'nullable',
            'role_id' => 'required_if:invite_to_erp,1',

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
        ];

        return $rules;
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
