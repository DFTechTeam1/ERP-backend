<?php

namespace App\Data\Production\Entertainment;

use Spatie\LaravelData\Data;

class CreateWorkStateData extends Data
{
    public function __construct(
        public ?string $started_at,
        public ?string $first_finish_at,
        public ?string $complete_at,
        public int $task_id,
        public int $employee_id
    ) {}
}
