<?php

namespace App\Data\Finance\ProjectCost;

use Spatie\LaravelData\Data;

class DashboardCostByClassData extends Data
{
    public function __construct(
        public readonly string $event_class,
        public readonly float $total_cost,
    ) {}
}
