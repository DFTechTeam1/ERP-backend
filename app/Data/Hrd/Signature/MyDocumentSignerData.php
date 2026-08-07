<?php

namespace App\Data\Hrd\Signature;

use Spatie\LaravelData\Data;

class MyDocumentSignerData extends Data
{
    public function __construct(
        public readonly string $role,
        public readonly string $name,
        public readonly string $status,
        public readonly bool $is_me = false,
    ) {}
}
