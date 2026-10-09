<?php

namespace App\Data\Finance\ExchangeRate;

use Spatie\LaravelData\Data;

class ListExchangeRateData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly string $date,
        public readonly float $rate,
        public readonly string $source,
        public readonly string $by,
    ) {}
}
