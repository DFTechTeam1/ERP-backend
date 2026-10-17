<?php

namespace App\Data\User\Role;

use Spatie\LaravelData\Data;

class DetailPermissionRoleData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly string $name,
    ) {}
}
