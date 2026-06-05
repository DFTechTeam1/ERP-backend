<?php

namespace App\Data\Whatsapp;

use Spatie\LaravelData\Data;

class CreateCommunityServerSchemaData extends Data
{
    public function __construct(
        public readonly string $subject,
        public readonly string $description,
    ) {}
}
