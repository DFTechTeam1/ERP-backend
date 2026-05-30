<?php

namespace App\Data\Whatsapp;

use Spatie\LaravelData\Data;

class CommunityGroupsListSchemaData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $groupId,
        public int $participant_count,
        public ?object $pic,
    ) {}
}
