<?php

namespace App\Data\Whatsapp;

use Spatie\LaravelData\Data;

class PromoteUserData extends Data
{
    public function __construct(
        public string $phone,
        public string $groupId,
        public bool $isDemote = false
    ) {}
}
