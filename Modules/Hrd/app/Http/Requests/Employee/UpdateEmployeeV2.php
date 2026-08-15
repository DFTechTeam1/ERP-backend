<?php

namespace Modules\Hrd\Http\Requests\Employee;

use App\Rules\UniqueLowerRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Hrd\Models\Employee;

/**
 * Validation for the V2 employee update form.
 *
 * Every field is `sometimes`: the service applies a partial payload without blanking the columns
 * the form left out, so only what is actually sent gets validated — and only what is validated
 * reaches the service.
 *
 * `username`, `invite_on_erp` and `register_on_greatday` are intentionally absent: the first lives
 * on the users table and the other two are create-time concerns, so they are dropped here rather
 * than silently ignored further down.
 */
class UpdateEmployeeV2 extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $employeeUid = $this->route('employeeUid');

        return [
            'nickname' => 'sometimes|nullable|string|max:255',
            'id_number' => 'sometimes|nullable|string|max:50',
            'email' => [
                'sometimes',
                'required',
                'email',
                new UniqueLowerRule(new Employee, $employeeUid, 'email'),
            ],

            'first_name' => 'sometimes|required|string|max:255',
            'middle_name' => 'sometimes|nullable|string|max:255',
            'last_name' => 'sometimes|nullable|string|max:255',

            // greatday codes: 1 = Male, 0 = Female
            'gender' => 'sometimes|required|in:0,1',
            'birth_day' => 'sometimes|required|date',
            'birth_place' => 'sometimes|nullable|string|max:255',
            'address' => 'sometimes|nullable|string',
            'mobile_phone' => 'sometimes|nullable|string|max:30',

            'employee_no' => [
                'sometimes',
                'required',
                'string',
                Rule::unique('employees', 'employee_id')->ignore($employeeUid, 'uid'),
            ],
            'join_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|nullable|date',

            'position' => 'sometimes|nullable|string|exists:position_backups,uid',
            'supervisor' => 'sometimes|nullable|string|exists:employees,uid',
            'manager' => 'sometimes|nullable|string|exists:employees,uid',

            'company_id' => 'sometimes|nullable|string|max:100',
            'job_grade' => 'sometimes|nullable|string|max:100',
            'cost_center' => 'sometimes|nullable|string|max:100',
            'employment_status' => 'sometimes|nullable|string|max:100',
            'work_location' => 'sometimes|nullable|string|max:100',
            'shift_pattern' => 'sometimes|nullable|string|max:100',
            'job_status' => 'sometimes|nullable|string|max:100',
            'nationality' => 'sometimes|nullable|string|max:100',
            'religion' => 'sometimes|nullable|string|max:100',
            'timezone_id' => 'sometimes|nullable',

            // greatday codes: 0 = Single, 1 = Married, 2 = Widow, 3 = Widower
            'marital_status' => 'sometimes|nullable|in:0,1,2,3',

            'bank_name' => 'sometimes|nullable|string|max:100',
            'bank_account_number' => 'sometimes|nullable|string|max:100',
            'bank_account_holder_name' => 'sometimes|nullable|string|max:255',

            'update_on_greatday' => 'sometimes|boolean',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.email' => __('validation.email', ['attribute' => 'email']),
            'employee_no.unique' => __('validation.unique', ['attribute' => 'employee id']),
            'gender.in' => __('validation.in', ['attribute' => 'gender']),
            'marital_status.in' => __('validation.in', ['attribute' => 'marital status']),
            'position.exists' => __('validation.exists', ['attribute' => 'position']),
            'supervisor.exists' => __('validation.exists', ['attribute' => 'supervisor']),
            'manager.exists' => __('validation.exists', ['attribute' => 'manager']),
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
