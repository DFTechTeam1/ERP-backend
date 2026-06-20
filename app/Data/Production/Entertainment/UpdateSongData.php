<?php

namespace App\Data\Production\Entertainment;

use Spatie\LaravelData\Data;

class UpdateSongData extends Data
{
    public function __construct(
        public readonly string $song
    ) {}
}
