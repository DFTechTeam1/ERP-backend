<?php

namespace App\Data\Finance\ProjectCost;

use App\Data\Production\Cost\EmployeeRewardListData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class EmployeeRewardData extends Data
{
    public function __construct(
        public readonly float $total,
        #[DataCollectionOf(EmployeeRewardListData::class)]
        public readonly array $items
    ) {}
}
