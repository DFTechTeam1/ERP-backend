<?php

namespace App\Data\Whatsapp;

use Spatie\LaravelData\Data;

class ParticipantsGroupData extends Data
{
    public function __construct(
        public int $id,
        public string $employee_uid,
        public string $name,
        public string $phone,
        public bool $is_admin
    ) {}
}
