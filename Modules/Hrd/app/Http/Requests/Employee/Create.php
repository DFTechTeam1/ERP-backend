<?php

namespace Modules\Hrd\Http\Requests\Employee;

use App\Enums\Employee\Education;
use App\Enums\Employee\Gender;
use App\Enums\Employee\LevelStaff;
use App\Enums\Employee\MaritalStatus;
use App\Enums\Employee\MartialStatus;
use App\Enums\Employee\ProbationStatus;
use App\Enums\Employee\Religion;
use App\Enums\Employee\Status;
use App\Rules\Employee\BossRule;
use App\Rules\Employee\InactiveEmployeeRule;
use App\Rules\Employee\OnlyFilledRule;
use App\Rules\InactiveEmployeeEmailRule;
use App\Rules\InactiveEmployeeNikRule;
use App\Rules\UniqueLowerRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Modules\Hrd\Models\Employee;

class Create extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required',
            'nickname' => 'required',
            'employee_id' => [
                'required',
                Rule::unique('employees', 'employee_id'),
            ],
            'email' => [
                'required',
                Rule::unique('employees', 'email'),
            ],
            'phone' => 'required',
            'id_number' => [
                'required',
                'max:16',
                'min:16',
                Rule::unique('employees', 'id_number'),
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
        ];

        return $rules;

        // return [
        //     'employee_id' => 'unique:employees,employee_id',
        //     'photo' => 'nullable|image|max:2048',
        //     'fingerspot_id' => 'nullable',
        //     'email' => [
        //         'email',
        //         new InactiveEmployeeRule(new Employee()),
        //     ],
        //     'name' => [
        //         'required',
        //         new InactiveEmployeeRule(new Employee()),
        //     ],
        //     'nickname' => 'required',
        //     'address' => 'required',
        //     'current_address' => 'required',
        //     'phone' => 'required',
        //     'position_id' => 'nullable',
        //     'level_staff' => ['required', Rule::enum(LevelStaff::class)],
        //     'status' => ['required', Rule::enum(Status::class)],
        //     'join_date' => 'required',
        //     'probation_status' => [
        //         'nullable',
        //         Rule::enum(ProbationStatus::class),
        //         new OnlyFilledRule(new Employee(), '', request('status')),
        //     ],
        //     'start_review_probation' => [
        //         'nullable',
        //         'required_if:status,'.Status::Probation->value,
        //         new OnlyFilledRule(new Employee(), '', request('status')),
        //         'after:join_date',
        //     ],
        //     'end_probation_date' => [
        //         'nullable',
        //         'required_if:status,'.Status::Probation->value,
        //         new OnlyFilledRule(new Employee(), '', request('status')),
        //         'after:join_date',
        //         'after:start_review_probation',
        //     ],
        //     'exit_date' => [
        //         'nullable',
        //         'required_if:status,'.Status::Inactive->value,
        //         new OnlyFilledRule(new Employee(), '', request('status')),
        //         'after:join_date',
        //     ],
        //     'gender' => [
        //         'required',
        //         Rule::enum(Gender::class),
        //     ],
        //     'education' => 'required', Rule::enum(Education::class),
        //     'education_name' => 'required',
        //     'education_major' => 'required',
        //     'graduation_year' => 'required',
        //     'nik' => [
        //         'required',
        //         'digits:16',
        //         new InactiveEmployeeRule(new Employee()),
        //     ],
        //     'bank_detail' => 'nullable',
        //     'pob' => 'nullable',
        //     'dob' => 'nullable',
        //     'religion' => Rule::enum(Religion::class),
        //     'marital_status' => Rule::enum(MaritalStatus::class),
        //     'relation_contact' => 'nullable',
        //     'referral_code' => 'nullable',
        //     'resign_notes' => [
        //         'nullable',
        //         'required_if:status,'.Status::Inactive->value,
        //         new OnlyFilledRule(new Employee(), '', request('status')),
        //     ],
        //     'kk_file' => 'nullable|image|max:2048',
        //     'ktp_file' => 'nullable|image|max:2048',
        //     'npwp_file' => 'nullable|image|max:2048',
        //     'bpjs_file' => 'nullable|image|max:2048',
        //     'id_expiration_date' => 'nullable',
        //     'bpjs_status' => 'nullable',
        //     'bpjs_kesehatan_number' => 'nullable',
        //     'bpjs_ketenagakerjaan_number' => 'nullable',
        //     'npwp_number' => 'nullable',
        //     'boss_id' => [
        //         new BossRule(new Employee(), request('level_staff')),
        //     ],
        //     'tanggungan' => 'nullable',
        //     'postal_code' => 'nullable',
        //     'blood_type' => 'nullable',
        //     'ptkp' => 'nullable',
        //     'penempatan' => 'nullable',
        //     'contract_duration' => [
        //         'nullable',
        //         'required_if:status,'.Status::Contract->value,
        //         new OnlyFilledRule(new Employee(), '', request('status')),
        //     ],
        //     'probation_extend_duration' => [
        //         Rule::requiredIf(!empty(request('probation_status')) && request('probation_status') == ProbationStatus::Perpanjang->value),
        //     ],
        // ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
