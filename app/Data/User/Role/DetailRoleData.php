<?php

namespace App\Data\User\Role;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class DetailRoleData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly string $name,
        #[DataCollectionOf(DetailPermissionRoleData::class)]
        public readonly array $permissions,
    ) {}
}
