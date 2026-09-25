<?php

namespace App\Data\Dfengine;

use App\Models\User;
use Spatie\LaravelData\Data;

class UserData extends Data
{
    public function __construct(
        public readonly User $user,
        public readonly bool $isRoot,
        public readonly bool $isProduction,
        public readonly bool $isProjectManager
    ) {}
}
