<?php

namespace App\Data\Hrd\Signature;

use Spatie\LaravelData\Data;

class GenerateDocumentData extends Data
{
    public function __construct(
        public readonly string $employee_id,
        public readonly string $version_label
    ) {}
}
