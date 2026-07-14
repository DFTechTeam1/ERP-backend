<?php

namespace App\Data\Hrd\Signatured;

use Spatie\LaravelData\Data;

class DocumentVersionListData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly string $label,
        public readonly string $status,
        public readonly bool $is_active,
        public readonly int $placeholders,
        public readonly string $version_status_color,
        public readonly string $date,
        public readonly ?string $rejected_reason,
        public readonly bool $is_pending,
        public readonly string $author,
        public readonly string $file_url,
    ) {}
}
