<?php

namespace App\Data\Company\ProjectClass;

use Spatie\LaravelData\Data;

class ListTierClassData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly int $pmCount,
        public readonly float $pmReward,
        public readonly float $productionReward,
        public readonly float $leadReward,
        public readonly float $supportReward
    ) {}
}
