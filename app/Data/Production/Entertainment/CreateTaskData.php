<?php

namespace App\Data\Production\Entertainment;

use Spatie\LaravelData\Data;

class CreateTaskData extends Data
{
    public function __construct(
        /** @var array<int, string> */
        public array $assignees,
        public string $deadline,
        public ?string $description,
        public string $name,
        public string $type,
    ) {}
}
