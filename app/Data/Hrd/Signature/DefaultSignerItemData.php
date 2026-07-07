<?php

namespace App\Data\Hrd\Signature;

use Spatie\LaravelData\Data;

class DefaultSignerItemData extends Data
{
    public function __construct(
        public readonly string $division_id,
        public readonly int $order
    ) {}
}
