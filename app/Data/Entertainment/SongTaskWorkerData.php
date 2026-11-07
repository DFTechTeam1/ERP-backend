<?php

namespace App\Data\Entertainment;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class SongTaskWorkerData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly string $name,
        public readonly string $employee_id,
        #[DataCollectionOf(SongTaskWorkerReviseData::class)]
        public readonly array $revises,
    ) {}
}
