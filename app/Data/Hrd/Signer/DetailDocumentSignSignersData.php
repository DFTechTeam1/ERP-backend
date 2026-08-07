<?php

namespace App\Data\Hrd\Signer;

use Spatie\LaravelData\Data;

class DetailDocumentSignSignersData extends Data
{
    public function __construct(
        public readonly string $role,
        public readonly string $name,
        public readonly string $email,
        public readonly string $status,
        public readonly ?string $signed_at,
    ) {}
}
