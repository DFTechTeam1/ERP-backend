<?php

namespace App\Data\Whatsapp;

use Spatie\LaravelData\Data;

class UserWhatsappGroupData extends Data
{
    // id?: string | number
    // groupId?: string | number
    // group_id?: string | number
    // name?: string
    // subject?: string
    // type?: string
    // description?: string
    // joined?: boolean
    // is_joined?: boolean
    // isMember?: boolean
    // invitationLink?: string
    // invitation_link?: string
    // invite_link?: string
    // inviteUrl?: string
    public function __construct(
        public int $id,
        public string $group_id,
        public string $name,
        public string $type,
        public bool $joined,
        public string $invitation_link
    ) {}
}
