<?php

namespace App\Data\Finance\AiCost;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;

class SummaryByActorData extends Data
{
    public function __construct(
        #[MapOutputName('actor_id')]
        public readonly int $actorId,
        public readonly string $name,
        public readonly string $role,
        public readonly int $count,
        public readonly int $tokens,
        public readonly float $costUsd,
        public readonly float $costIdr
    ) {}
}
