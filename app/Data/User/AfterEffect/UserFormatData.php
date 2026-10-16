<?php

namespace App\Data\User\AfterEffect;

use Spatie\LaravelData\Data;

class UserFormatData extends Data
{
    public function __construct(
        public readonly string $userName,
        public readonly int $userId,
        public readonly string $userEmail,

    ) {}
}
