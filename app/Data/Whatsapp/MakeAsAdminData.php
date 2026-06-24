<?php

namespace App\Data\Whatsapp;

use Spatie\LaravelData\Data;

class MakeAsAdminData extends Data
{
    public function __construct(
        public int $is_admin,
        public string $employee_uid
    ) {}
}
