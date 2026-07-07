<?php

namespace App\Data\Hrd\Signature;

use Spatie\LaravelData\Data;

class BulkUpdateDocumentTypeData extends Data
{
    public function __construct(
        public readonly BulkUpdateDocumentTypeItemData $changes,
        /** var @array<int, string> */
        public readonly array $uids
    ) {}
}
