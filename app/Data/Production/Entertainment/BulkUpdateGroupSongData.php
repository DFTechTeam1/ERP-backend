<?php

namespace App\Data\Production\Entertainment;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class BulkUpdateGroupSongData extends Data
{
    public function __construct(
        /** @var array<int, string> */
        public readonly array $deleted,
        public readonly string $group_uid,
        public readonly string $from,
        public readonly string $name,
        #[DataCollectionOf(BulkUpdateGroupSongListData::class)]
        public readonly array $songs
    ) {}
}
