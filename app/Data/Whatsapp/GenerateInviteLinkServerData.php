<?php

namespace App\Data\Whatsapp;

use Spatie\LaravelData\Data;

class GenerateInviteLinkServerData extends Data
{
    public function __construct(
        public readonly string $groupId
    ) {}
}
