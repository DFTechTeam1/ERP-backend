<?php

namespace App\Data\Hrd\Signature;

use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class BulkDeleteGeneratedDocumentData extends Data
{
    public function __construct(
        /** @var array<int, string> */
        #[Min(1)]
        public readonly array $uids
    ) {}
}
