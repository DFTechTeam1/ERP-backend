<?php

namespace App\Data\Production\Dfengine;

use Spatie\LaravelData\Data;

class TaskListData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly string $project_uid,
        public readonly string $name,
        public readonly string $status,
        public readonly string $created_at,
        public readonly ?string $updated_at,
        public readonly array $action,
    ) {}
}
