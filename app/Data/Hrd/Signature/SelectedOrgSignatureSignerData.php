<?php

namespace App\Data\Hrd\Signature;

use Spatie\LaravelData\Data;

class SelectedOrgSignatureSignerData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly string $name,
        public readonly string $role,
        public readonly string $initial,
        public readonly ?string $color,
    ) {}
}
