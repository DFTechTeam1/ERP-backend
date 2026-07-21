<?php

namespace Modules\Hrd\Data\Employee;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

/**
 * One Greatday employee selected from the out_of_sync_employees table to be
 * synced into the ERP. Mirrors the frontend SyncErpEmployeeWithGreatdaySchema.
 */
class SyncEmployeeItemData extends Data
{
    public function __construct(
        #[Required, Email]
        public string $email,
        #[Required]
        public string $employee_id,
        #[Required]
        public string $first_name,
        #[Nullable]
        public ?string $middle_name,
        #[Nullable]
        public ?string $last_name,
        #[Required]
        public string $nickname,
        #[Required]
        public string $id_number,
        #[Required]
        public string $nationality,
        #[Required]
        public string $gender,
        #[Required]
        public string $birth_day,
        #[Required]
        public string $birth_place,
        #[Required]
        public string $religion,
        #[Required]
        public string $marital_status,
        #[Required]
        public int $timezone_id,
        #[Required]
        public string $mobile_phone,
        #[Required]
        public string $address,
        #[Required]
        public string $bank_name,
        #[Required]
        public string $bank_account_number,
        #[Required]
        public string $bank_account_holder_name,
        #[Required]
        public int $out_of_sync_id,
        #[Required]
        public string $greatday_employee_id,
        #[Required]
        public string $employee_no,
        #[Required]
        public string $join_date,
        #[Nullable]
        public ?string $end_date,
        #[Required]
        public string $company_id,
        #[Required]
        public string $position,
        #[Required]
        public string $job_grade,
        #[Required]
        public string $cost_center,
        #[Required]
        public string $employment_status,
        #[Required]
        public string $work_location,
        #[Required]
        public string $supervisor,
        #[Required]
        public string $manager,
        #[Required]
        public string $shift_pattern,
        #[Nullable]
        public ?string $department,
        #[Required]
        public string $job_status,
        #[Required]
        public int $role,
    ) {}
}
