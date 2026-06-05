<?php

namespace App\Data\Whatsapp;

use Spatie\LaravelData\Data;

class CreateGroupServerSchemaData extends Data
{
    public function __construct(
        public readonly string $communityId,
        public readonly string $subject,
        public readonly array $participants,
    ) {}
}
