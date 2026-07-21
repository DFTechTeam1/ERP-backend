<?php

namespace App\Data\Hrd\Signature;

use Spatie\LaravelData\Data;

class EmployeeSignatureData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly string $sign_url,
        public readonly bool $is_active,
        public readonly ?string $created_at,
    ) {}
}
