<?php

namespace App\Data\Finance\AiCost;

use Spatie\LaravelData\Data;

class TopupCreatorData extends Data
{
    public function __construct(
        public readonly string $nickname,
        public readonly string $image,
        public readonly string|int $user_id
    ) {}
}
