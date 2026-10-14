<?php

namespace App\Data\Finance\AiCost;

use Spatie\LaravelData\Data;

class TopupSummaryData extends Data
{
    public function __construct(
        public readonly int $total_count,
        public readonly float $total_amount_usd,
        public readonly float $total_amount_idr,
        public readonly string $last_topup_date,
    ) {}
}
