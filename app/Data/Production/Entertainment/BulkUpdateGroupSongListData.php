<?php

namespace App\Data\Production\Entertainment;

use Spatie\LaravelData\Data;

class BulkUpdateGroupSongListData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $uid,
    ) {}
}
