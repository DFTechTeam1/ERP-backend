<?php

namespace App\Data\Production\Entertainment;

use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class GroupSongItemData extends Data
{
    public function __construct(
        public readonly string $name,
        
        #[Min(1)]
        public readonly array $songs
    ) {}
}
