<?php

namespace Modules\Hrd\Data\Employee;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * The previously-missing Greatday create fields the user filled in the link form.
 * All optional — a pure match (employee already exists in Greatday) sends none of them.
 * Provided values are persisted onto the employee and used to build the Greatday create payload.
 */
class LinkEmployeeData extends Data
{
    public function __construct(
        public string|Optional|null $greatday_nationality,
        public int|string|Optional|null $greatday_company,
        public string|Optional|null $greatday_job_grade,
        public string|Optional|null $greatday_cost_center,
        public string|Optional|null $greatday_employment_status,
        public string|Optional|null $greatday_work_location,
        public string|Optional|null $greatday_religion,
        public int|string|Optional|null $greatday_timezone,
        public string|Optional|null $greatday_shift_pattern,
        /** ERP employee uid of the chosen supervisor */
        public string|Optional|null $boss_id,
    ) {}

    /**
     * Return the provided values as a plain array keyed by employee column, skipping anything
     * the caller did not send (Optional) or sent as null/empty.
     *
     * @return array<string, mixed>
     */
    public function filled(): array
    {
        $out = [];
        foreach (get_object_vars($this) as $key => $value) {
            if ($value instanceof Optional || $value === null || $value === '') {
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }
}
