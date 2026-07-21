<?php

namespace App\Data\Hrd\Signature;

use App\Data\Hrd\Signatured\DocumentVersionListData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class TemplateListData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly ?string $active_document_uid,
        public readonly string $name,
        public readonly string $type,
        public readonly string $latest_version_label,
        public readonly string $updated_at,
        public readonly string $active_version_label,
        public readonly string $active_version_status,
        public readonly string $active_version_status_color,
        public readonly int $versions_count,
        /** @var array<int, string> */
        public readonly array $signing_chain,
        #[DataCollectionOf(DocumentVersionListData::class)]
        public readonly array $versions
    ) {}
}
