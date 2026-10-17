<?php

namespace App\Data\Company\ProjectClass;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class ListClassData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly string $name,
        public readonly string $color,
        public readonly float $reward,
        public readonly float $pm_reward,
        public readonly float $vj_reward,
        public readonly bool $is_active,
        #[DataCollectionOf(ListTierClassData::class)]
        public readonly array $pmTiers,
    ) {}
}
