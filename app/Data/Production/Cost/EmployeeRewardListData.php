<?php

namespace App\Data\Production\Cost;

use Spatie\LaravelData\Data;

class EmployeeRewardListData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $avatar,
        public readonly int $total_point,
        public readonly float $total_reward
    ) {}
}
