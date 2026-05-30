<?php

namespace App\Data\Whatsapp;

use Spatie\LaravelData\Data;

class CommunityListSchemaData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $communityId,
        public int $group_count
    ) {}
}
