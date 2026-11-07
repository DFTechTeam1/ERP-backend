<?php

namespace App\Data\Entertainment;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class SongTaskData extends Data
{
    public function __construct(
        public readonly SongTaskWorkerData $worker,
        #[DataCollectionOf(SongTaskResultData::class)]
        public readonly array $results,
        #[DataCollectionOf(SongTaskLogsData::class)]
        public readonly object $logs,
    ) {}
}
