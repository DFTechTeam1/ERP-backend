<?php

namespace App\Data\Entertainment;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class BulkCreateSongData extends Data
{
    public function __construct(
        #[DataCollectionOf(BulkCreateSongItemData::class)]
        public readonly array $groups
    ) {}
}
