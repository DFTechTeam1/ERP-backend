<?php

namespace App\Data\Hrd\Signature;

use App\Enums\Hrd\Signature\GenerateDocument\AssignTo;
use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Data;

class BulkGenerateDocumentData extends Data
{
    public function __construct(
        #[Enum(AssignTo::class)]
        public readonly string $assign_to,
        public readonly ?string $division_id,
        public readonly ?string $position_id,
        public readonly ?string $template_uid,
    ) {}
}
