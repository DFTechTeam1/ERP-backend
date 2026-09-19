<?php

namespace App\Data\Finance\ProjectCost;

use Spatie\LaravelData\Data;

class EmployeeRewardListData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $uid,
        public readonly string $name,
        public readonly ?string $avatar,
        public readonly string $position,
        public readonly string $role,
        public readonly float $total_point,
        public readonly float $total_reward
    ) {}
}
