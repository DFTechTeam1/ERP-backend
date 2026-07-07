<?php

namespace App\Data\Hrd\Signature;

use Spatie\LaravelData\Data;

class ListDocumentTypeSignerData extends Data
{
    public function __construct(
        public readonly string $role,
        public readonly string $description,
        public readonly ?bool $locked
    ) {}
}
