<?php

namespace App\Data\Finance\Currency;

use Spatie\LaravelData\Data;

class UpdateCurrencyData extends Data
{
    public function __construct(
        public readonly string $name
    ) {}
}
