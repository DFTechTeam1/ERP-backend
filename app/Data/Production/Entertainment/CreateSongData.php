<?php

namespace App\Data\Production\Entertainment;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class CreateSongData extends Data
{
    public function __construct(
        #[DataCollectionOf(GroupSongItemData::class)]
        #[Min(1)]
        public readonly array $groups
    ) {}
}
