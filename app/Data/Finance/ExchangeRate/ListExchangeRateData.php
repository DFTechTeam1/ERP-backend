<?php

namespace App\Data\Finance\ExchangeRate;

use Spatie\LaravelData\Data;

class ListExchangeRateData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly string $currencyUid,
        public readonly string $effectiveDate,
        public readonly float $rate,
        public readonly string $source,
        public readonly string $setBy,
        public readonly string $setByUid,
        public readonly string $createdAt,
        public readonly ?string $updatedAt,
    ) {}
}
