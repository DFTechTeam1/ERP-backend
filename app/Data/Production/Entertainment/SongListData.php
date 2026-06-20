<?php

namespace App\Data\Production\Entertainment;

use Spatie\LaravelData\Data;

class SongListData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly string $name,
        public readonly string $group,
        public readonly string $status,
        public readonly string $status_color,
    ) {}
}
