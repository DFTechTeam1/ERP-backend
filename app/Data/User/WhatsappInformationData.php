<?php

namespace App\Data\User;

use Spatie\LaravelData\Data;

class WhatsappInformationData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $type,
        public bool $joined,
        public string $invitationLink
    ) {}
}
