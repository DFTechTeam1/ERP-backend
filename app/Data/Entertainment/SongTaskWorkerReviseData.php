<?php

namespace App\Data\Entertainment;

use Spatie\LaravelData\Data;

class SongTaskWorkerReviseData extends Data
{
    public function __construct(
        public readonly string $created_at,
        public readonly string $reason,
    ) {}
}
