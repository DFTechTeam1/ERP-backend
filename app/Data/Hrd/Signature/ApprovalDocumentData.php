<?php

namespace App\Data\Hrd\Signature;

use Spatie\LaravelData\Data;

class ApprovalDocumentData extends Data
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $reason
    ) {}
}
