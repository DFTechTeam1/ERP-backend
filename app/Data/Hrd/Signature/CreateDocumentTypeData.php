<?php

namespace App\Data\Hrd\Signature;

use App\Enums\Hrd\Signature\DocumentType\Type;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Data;

class CreateDocumentTypeData extends Data
{
    public function __construct(
        #[Enum(Type::class)]
        public string $category,
        #[Unique(table: 'document_types', column: 'code')]
        public string $code,
        #[DataCollectionOf(DefaultSignerItemData::class)]
        public array $default_signers,
        public string $name,
        public int $retention_years,
        public bool $is_active = true,
    ) {}
}
