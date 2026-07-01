<?php

namespace App\Data\Production\Entertainment;

use App\Enums\Production\Entertainment\TaskStatus;
use App\Enums\Production\Entertainment\TaskType;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class CreateEntertainmentTaskData extends Data
{
    public function __construct(
        public string $project_id,
        #[WithCast(EnumCast::class)]
        public TaskType $type,
        public string $name,
        public ?string $description,
        public string $deadline,
        #[WithCast(EnumCast::class)]
        public TaskStatus $status,
    ) {}
}
