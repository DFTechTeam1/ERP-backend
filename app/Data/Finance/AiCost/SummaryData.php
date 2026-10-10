<?php

namespace App\Data\Finance\AiCost;

use Spatie\LaravelData\Data;

class SummaryData extends Data
{
    public function __construct(
        public readonly float $costUsd,
        public readonly float $costIdr,
        public readonly float $tokens,
        public readonly int $actions
    ) {}
}
