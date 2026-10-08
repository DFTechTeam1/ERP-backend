<?php

namespace App\Data\Finance\Currency;

use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Data;

class StoreCurrencyData extends Data
{
    public function __construct(
        #[Unique('currencies', 'code')]
        public readonly string $code,
        #[Unique('currencies', 'name')]
        public readonly string $name,
        public readonly string $symbol,
        public readonly float $opening_rate,
    ) {}
}
