<?php

namespace App\Data\Finance\CostCenter;

use App\Enums\Finance\CostCenter\CostCenterType;
use Spatie\LaravelData\Data;

class ListCostCenterData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly string $code,
        public readonly string $name,
        public readonly CostCenterType $type,
        public readonly ?string $parentUid,
        public readonly ?string $parentName,
        public readonly int $transactionCount,
        public readonly bool $isActive
    ) {}
}
