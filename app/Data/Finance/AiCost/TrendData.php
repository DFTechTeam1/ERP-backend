<?php

namespace App\Data\Finance\AiCost;

use Spatie\LaravelData\Data;

class TrendData extends Data
{
    public function __construct(
        public readonly float $costUsd,
        public readonly float $costIdr,
        public readonly string $month,
    ) {}
}
