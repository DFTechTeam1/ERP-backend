<?php

namespace App\Data\Hrd\Signer;

use Spatie\LaravelData\Data;

class DetailDocumentSignEmployeeData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $initials,
        public readonly string $color
    ) {}
}
