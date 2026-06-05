<?php

namespace App\Data\Whatsapp;

use App\Enums\Whatsapp\GroupTargetType;
use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Data;

class CreateGroupSchemaData extends Data
{
    public function __construct(
        public string $group_name,
        #[Exists(table: 'whatsapp_communities', column: 'community_id')]
        public string $community_id,
        #[Enum(GroupTargetType::class)]
        public string $target_type,
        public string $employee_uid,
    ) {}
}
