<?php

namespace App\Data\Hrd\Signature;

use Spatie\LaravelData\Data;

class BulkDeleteDocumentTypeData extends Data
{
    public function __construct(
        /** var @array<int, string> */
        public readonly array $uids
    ) {}
}
