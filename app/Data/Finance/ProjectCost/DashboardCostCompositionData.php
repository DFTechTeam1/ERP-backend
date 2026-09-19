<?php

namespace App\Data\Finance\ProjectCost;

use Spatie\LaravelData\Data;

class DashboardCostCompositionData extends Data
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly float $total,
    ) {}
}
