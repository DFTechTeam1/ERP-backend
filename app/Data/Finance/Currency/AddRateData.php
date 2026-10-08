<?php

namespace App\Data\Finance\Currency;

use Spatie\LaravelData\Data;

class AddRateData extends Data
{
    public function __construct(
        public readonly string $effective_date,
        public readonly float $rate
    ) {}
}
