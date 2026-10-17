<?php

namespace App\Data\Production\Cost;

use Spatie\LaravelData\Data;

class CostListData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $employee_id,
        public readonly string $name,
        public readonly string $position,
        public readonly ?string $avatar,
        public readonly string $usage,
        public readonly float $total,
    ) {}
}
