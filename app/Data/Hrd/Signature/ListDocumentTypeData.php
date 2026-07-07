<?php

namespace App\Data\Hrd\Signature;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class ListDocumentTypeData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $uid,
        public readonly string $code,
        public readonly string $category,
        public readonly string $category_color,
        public readonly int $retention_years,
        public readonly int $default_signers,
        public readonly bool $is_have_active_template,
        public readonly bool $is_active,
        #[DataCollectionOf(ListDocumentTypeSignerData::class)]
        public readonly ?array $default_signer_items
    ) {}
}
