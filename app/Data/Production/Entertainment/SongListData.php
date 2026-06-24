<?php

namespace App\Data\Production\Entertainment;

use Spatie\LaravelData\Data;

class SongListData extends Data
{
    public function __construct(
        public readonly string $uid,
        public string $name,
        public string $group,
        public string $status,
        public string $status_color,
    ) {}
}
