<?php

namespace Modules\Hrd\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

/**
 * v2 employee-creation payload, mirroring the erp-backend-node createEmployeeSchema
 * (POST /api/v2/hrd/employees). References are sent as codes/uids:
 *  - employment_status: employment status CODE
 *  - position / supervisor / manager: employee/position UID
 *  - company_id, cost_center, work_location, shift_pattern, job_status, nationality: raw Greatday values
 */
class CreateEmployeeV2 extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'nickname' => 'nullable|string|max:100',
            'id_number' => 'required|string|max:32',
            'nationality' => 'required|string',
            'gender' => 'required|string',
            'birth_day' => 'required|date',
            'birth_place' => 'required|string',
            'religion' => 'required|string',
            'marital_status' => 'required|string',
            'timezone_id' => 'required|string',
            'mobile_phone' => 'required|string',
            'address' => 'required|string',
            'bank_name' => 'required|string',
            'bank_account_number' => 'required|string',
            'bank_account_holder_name' => 'required|string',
            'employee_no' => 'required|string',
            'join_date' => 'required|date',
            'company_id' => 'required|string',
            'position' => 'required|string',
            'job_grade' => 'required|string',
            'cost_center' => 'required|string',
            'employment_status' => 'required|string',
            'work_location' => 'required|string',
            'supervisor' => 'required|string',
            'manager' => 'required|string',
            'shift_pattern' => 'required|string',
            'job_status' => 'required|string',
            'username' => 'nullable|string|max:50',
            'register_on_greatday' => 'required|integer',
            'invite_on_erp' => 'required|integer',
            'role' => 'required_if:invite_on_erp,1',
            'greatday_employee_id' => 'nullable|string',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
