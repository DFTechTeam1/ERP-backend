<?php

namespace App\Data\Production\Dfengine;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class TaskListData extends Data
{
    public function __construct(
        #[DataCollectionOf(ProductionTaskListData::class)]
        public readonly array $tasks,
        #[DataCollectionOf(SongListData::class)]
        public readonly array $songs
    ) {}
}
