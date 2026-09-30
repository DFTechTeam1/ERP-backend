<?php

namespace App\Data\Company\ProjectClass;

use Spatie\LaravelData\Data;

class ListTierClassData extends Data
{
    public function __construct(
        public readonly int $pmCount,
        public readonly float $pmReward,
        public readonly float $productionReward
    ) {}
}
