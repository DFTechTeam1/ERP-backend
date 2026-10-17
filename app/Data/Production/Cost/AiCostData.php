<?php

namespace App\Data\Production\Cost;

use App\Data\Finance\ProjectCost\EmployeeRewardData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class AiCostData extends Data
{
    public function __construct(
        public readonly float $total,
        #[DataCollectionOf(EmployeeRewardData::class)]
        public readonly array $items,
    ) {}
}
