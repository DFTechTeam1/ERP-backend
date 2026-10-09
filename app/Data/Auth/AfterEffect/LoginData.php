<?php

namespace App\Data\Auth\AfterEffect;

use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Data;

class LoginData extends Data
{
    public function __construct(
        #[Exists('users', 'email')]
        public readonly string $email,
        public readonly string $password,
    ) {}
}
