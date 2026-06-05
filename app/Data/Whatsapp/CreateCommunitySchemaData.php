<?php

namespace App\Data\Whatsapp;

use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Data;

class CreateCommunitySchemaData extends Data
{
    public function __construct(
        #[Unique(table: 'whatsapp_communities', column: 'subject')]
        public readonly string $subject,
        public readonly string $description,
    ) {}
}
