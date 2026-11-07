<?php

namespace App\Data\Entertainment;

use Spatie\LaravelData\Data;

class SongTaskLogsData extends Data
{
    public function __construct(
        public readonly string $text,
        public readonly string $time,
    ) {}
}
