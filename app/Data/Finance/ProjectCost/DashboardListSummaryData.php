<?php

namespace App\Data\Finance\ProjectCost;

use Spatie\LaravelData\Data;

class DashboardListSummaryData extends Data
{
    public function __construct(
        public readonly int $total_projects,
        public readonly float $total_cost,
        public readonly float $total_employee_reward,
        public readonly float $average_cost,
        public readonly float $total_fix_price,
        public readonly float $total_gross_profit,
        public readonly float $provisional_count,
        public readonly string $currency,
    ) {}
}
