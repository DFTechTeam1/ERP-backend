<?php

namespace App\Data\Hrd\Overtime;

use Spatie\LaravelData\Data;

class PayloadOvertimeDatabaseData extends Data
{
    public function __construct(
        public readonly string|int $employee_id,
        public readonly float $overtime_hours,
        public readonly string $remark,
        public readonly ?string $project_id,
        public readonly ?string $task_id,
        public readonly string $task_name,
        public readonly string $project_name,
        public readonly string $employee_name,
        public readonly string $position_name,
        public readonly string $employee_number,
        public readonly string $overtime_date
    ) {}
}
