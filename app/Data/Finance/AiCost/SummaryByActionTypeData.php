<?php

namespace App\Data\Finance\AiCost;

use Spatie\LaravelData\Data;

class SummaryByActionTypeData extends Data
{
    public function __construct(
        public readonly string $type,
        public readonly int $count,
        public readonly int $tokens,
        public readonly float $costUsd,
        public readonly float $costIdr
    ) {}
}
