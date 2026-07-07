<?php

namespace App\Data\Hrd\Signature;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use App\Data\Hrd\Signature\ListDocumentTypeData;

class OutputListDocumentTypeData extends Data
{
    public function __construct(
        #[DataCollectionOf(ListDocumentTypeData::class)]
        public array $paginated,
        public int $totalData
    ) {}
}
