<?php

namespace App\Data\Finance\Currency;

use Spatie\LaravelData\Data;

class ListCurrencyData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly string $name,
        public readonly string $symbol,
        public readonly string $code,
        public readonly bool $isBase,
        public readonly bool $isActive,
        public readonly float $currentRate,
        public readonly float $rateChange,
        public readonly ?string $rateAsOf,
    ) {}
}
