<?php

namespace App\Data\Entertainment;

use Spatie\LaravelData\Data;

class SongTaskResultData extends Data
{
    public function __construct(
        public readonly string $nas_path,
        public readonly array $images
    ) {}
}
