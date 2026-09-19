<?php

namespace App\Data\Finance\ProjectCost;

use Spatie\LaravelData\Data;

class DashboardCostTrendData extends Data
{
    public function __construct(
        public readonly string $month,
        public readonly float $total_cost,
        public readonly float $employee_reward,
    ) {}
}
