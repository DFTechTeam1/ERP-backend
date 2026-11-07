<?php

namespace App\Data\Entertainment;

use Spatie\LaravelData\Data;

class UpdateSongData extends Data
{
    public function __construct(
        public readonly string $song,
        public readonly string $group,
    ) {}
}
