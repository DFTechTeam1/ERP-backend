<?php

namespace App\Data\Finance\CostCenter;

use App\Enums\Finance\CostCenter\CostCenterType;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Data;

class StoreCostCenterData extends Data
{
    public function __construct(
        #[Unique('cost_centers', 'code')]
        public readonly string $code,
        #[Unique('cost_centers', 'name')]
        public readonly string $name,
        public readonly CostCenterType $type,
        public readonly ?string $parent_uid,
    ) {}
}
