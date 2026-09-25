<?php

namespace App\Data\Production\Dfengine;

use Spatie\LaravelData\Data;

class ProjectListData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly string $name,
        public readonly string $classification,
        public readonly bool $is_active,
        public readonly int $task_count,
        public readonly string $created_at,
        public readonly ?string $updated_at,
        public readonly array $action
    ) {}
}
