<?php

namespace App\Data\Hrd\Signature;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use App\Data\Hrd\Signature\CreateDocumentTypeData;
use Spatie\LaravelData\Attributes\Validation\Min;

class BulkCreateDocumentTypeData extends Data
{
    public function __construct(
        #[DataCollectionOf(CreateDocumentTypeData::class)]
        #[Min(1)]
        public readonly array $types
    ) {}
}
