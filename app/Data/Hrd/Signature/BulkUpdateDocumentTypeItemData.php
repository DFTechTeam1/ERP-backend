<?php

namespace App\Data\Hrd\Signature;

use App\Enums\Hrd\Signature\DocumentType\Type;
use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Data;

class BulkUpdateDocumentTypeItemData extends Data
{
    public function __construct(
        #[Enum(Type::class)]
        public readonly ?string $category,
        public readonly ?int $retention_years,
        public readonly ?bool $is_active
    ) {}
}
