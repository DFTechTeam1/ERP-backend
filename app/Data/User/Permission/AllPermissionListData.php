<?php

namespace App\Data\User\Permission;

use Spatie\LaravelData\Data;

class AllPermissionListData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly string $name,
        public readonly string $group
    ) {}
}
